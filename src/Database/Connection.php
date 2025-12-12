<?php 
declare(strict_types=1);
/**
 * Luminova Framework connection class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database;

use \Countable;
use \Exception;
use \Throwable;
use Luminova\Luminova;
use \App\Config\Database;
use Luminova\Logger\Logger;
use Luminova\Database\Helpers\Util;
use Luminova\Foundation\Core\Database as CoreDatabase;
use Luminova\Exceptions\{ErrorCode, DatabaseException};
use Luminova\Database\Driver\{PdoDatabase, MysqliDatabase};
use Luminova\Interface\{LazyObjectInterface, DatabaseInterface};

class Connection implements LazyObjectInterface, Countable
{
    /**
     * Database connection driver instance.
     *
     * @var DatabaseInterface|null $db
     */
    protected ?DatabaseInterface $db = null;

    /**
     * Connections pools.
     *
     * @var DatabaseInterface[] $pools
     */
    private static array $pools = [];

    /**
     * Database connection static instance.
     *
     * @var ?self $instance
     */
    private static ?self $instance = null;

    /**
     * Accumulate critical log messages
     * 
     * @var array $errs
     */
    private array $errs = [];

    /**
     * The identifier of the target shard server (e.g., region or server key).
     * Used to route the connection to a specific shard.
     *
     * @var string|null $shardServerLocation
     */
    private ?string $shardServerLocation = null;

    /**
     * Determines whether to fallback to available backup servers 
     * if the selected shard is unavailable.
     *
     * @var bool $isShardFallbackOnError
     */
    private bool $isShardFallbackOnError = false;

    /**
     * Create a new database connection instance.
     *
     * Initializes the connection with optional settings for pooling and maximum connections.
     * If not explicitly provided, values are loaded from environment variables:
     * - `database.connection.pool` for pooling.
     * - `database.max.connections` for maximum connections.
     *
     * When `$autoConnect` is true, the connection is automatically established on instantiation.
     *
     * @param bool|null $pool Whether to enable connection pooling. 
     *                         Overrides the `database.connection.pool` environment setting if set.
     * @param int|null $maxPoolConnections Maximum number of pooled connections. 
     *                                   Overrides `database.max.connections` from the environment if set.
     * @param bool $autoConnect Whether to immediately initiate the database connection (default: true).
     *
     * @throws DatabaseException If connection retries fail, the connection limit is exceeded, an invalid driver is specified, or any error occurs during connection.
     */
    public function __construct(
        private ?bool $pool = null, 
        private ?int $maxPoolConnections = null, 
        private bool $autoConnect = true
    )
    {
        $this->maxPoolConnections ??= (int) env('database.max.connections', 3);
        $this->pool ??= (bool) env('database.connection.pool', false);
        $this->errs = [];

        if ($this->autoConnect) {
            $this->db = $this->connect();
        }
    }

    /**
     * Database serialization.
     *
     * @return array Return the configuration array of database connection.
     */
    public function __serialize(): array
    {
        return ($this->db instanceof DatabaseInterface) 
            ? $this->db->getConfig(null)
            : [];
    }

    /**
     * Restores the connection after un-serialization.
     *
     * @param array<string,mixed> $data Un-serialized data.
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        if($data === []){
            $this->db ??= $this->connect();
            return;
        }

        $this->db = self::newInstance(Database::fromArray($data));
    }

    /**
     * Prevent database object cloning.
     *
     * @return void
     */
    private function __clone() {}

    /**
     * Initialize a database connection for a specific shard server.
     *
     * This static initializer creates (or reuses) an instance of the connection class,
     * optionally assigning it to a specific shard server identified by `$locationId`.
     * If the selected shard is unreachable, it can fallback to available backup servers.
     *
     * @param string $locationId Shard identifier (e.g., region name or server key).
     * @param bool $fallbackOnError Fallback to a backup server if shard server connection is unavailable.
     * @param ?bool $pool Enable connection pooling (if applicable).
     * @param ?int $maxPoolConnections Maximum number of connections allowed in the pool.
     * @param bool $sharedInstance Reuse a shared static instance if set to true.
     * 
     * @return static Returns an initialized database connection instance.
     * @throws DatabaseException If connection retries fail, max connection limit is reached, 
     *      an invalid driver is detected, or a connection error occurs.
     */
    public static function shard(
        string $locationId, 
        bool $fallbackOnError = false,
        ?bool $pool = null, 
        ?int $maxPoolConnections = null,
        bool $sharedInstance = false
    ): static 
    {
        $instance = $sharedInstance
            ? static::getInstance($pool, $maxPoolConnections, false)
            : new static($pool, $maxPoolConnections, false);

        $instance->shardServerLocation = $locationId;
        $instance->isShardFallbackOnError = $fallbackOnError;
        $instance->db = $instance->connect();

        return $instance;
    }

    /**
     * Returns the shared singleton instance of the connection class.
     *
     * Creates a new instance if one does not already exist, optionally configuring connection pooling
     * and maximum allowed connections. Settings fall back to environment values if not provided:
     * - `database.connection.pool` for connection pooling.
     * - `database.max.connections` for connection limits.
     *
     * If `$autoConnect` is true, the database connection is established immediately.
     *
     * @param bool|null $pool Enables or disables connection pooling.
     *                         Defaults to `database.connection.pool` from the environment.
     * @param int|null $maxPoolConnections Optional. Maximum number of allowed connections.
     *                     Defaults to `database.max.connections` from the environment.
     * @param bool $autoConnect Whether to auto-connect on initialization (default: `true`).
     *
     * @return static Returns the singleton instance of the connection class.
     * @throws DatabaseException If connection retries fail, max connection limit is reached, 
     *          an invalid driver is detected, or a connection error occurs.
     */
    public static function getInstance(
        ?bool $pool = null, 
        ?int $maxPoolConnections = null, 
        bool $autoConnect = true
    ): static
    {
        if (!static::$instance instanceof static) {
            static::$instance = new static($pool, $maxPoolConnections, $autoConnect);
        }

        return static::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getOpenConnections(): int
    {
        if(!$this->db instanceof DatabaseInterface){
            return 0;
        }

        return $this->db::getOpenConnections();
    }

    /**
     * Retrieve a connection from the pool.
     *
     * When `$anyFree` is `false`, removes and returns the first pooled connection
     * if it is ready for use. Otherwise, iterates through the pool and returns the
     * first connected instance, closing and discarding any invalid connections.
     *
     * @param bool $anyFree Whether to return the first connected connection from the pool.
     *
     * @return DatabaseInterface|null The selected connection, or `null` if none are available.
     */
    public function getPool(bool $anyFree = false): ?DatabaseInterface
    {
        if(self::$pools === []){
            return null;
        }

        if (!$anyFree) {
            $id = array_key_first(self::$pools);
            $connection = self::$pools[$id] ?? null;

            unset(self::$pools[$id]);

            if (!$this->isReady($connection)) {
                return null;
            }

            return $connection;
        }

        foreach (self::$pools as $idx => $connection) {
            unset(self::$pools[$idx]);

            if (!$connection instanceof DatabaseInterface) {
                continue;
            }

            if ($connection->isConnected()) {
                return $connection;
            }

            $connection->close();
        }

        self::$pools = [];
        return null;
    }

    /**
     * Retrieves the database driver connection instance.
     *
     * @return DatabaseInterface|null Return the driver connection instance, or null if not connected.
     */
    public function database(): ?DatabaseInterface
    {
        return $this->db;
    }

    /**
     * Count the number of connection pool.
     * 
     * @return int Return the number of connection pools.
     */
    public function count(): int
    {
        return count(self::$pools);
    }

    /**
     * Create a database connection from the specified configuration.
     *
     * If no configuration is provided, the default database configuration is used.
     *
     * @param CoreDatabase|null $config Database connection configuration (default: null).
     * @param bool $shared Whether to return a shared database connection instance (default: false).
     *
     * @return DatabaseInterface|null Returns the connected database driver, or `null` if the connection fails.
     *
     * @throws DatabaseException If no configuration is available, the driver is unsupported,
     *                           or a connection error occurs.
     */
    public static function newInstance(?CoreDatabase $config = null, bool $shared = false): ?DatabaseInterface
    {
        $config ??= self::getDefaultConfig();

        if (!$config instanceof CoreDatabase) {
            throw new DatabaseException(
                'No database configuration found. Define it in .env or App\\Config\\Database.',
                ErrorCode::RUNTIME_ERROR
            );
        }

        $driver = strtolower((string) $config->getValue('connection'));

        $class = match ($driver) {
            'mysqli' => MysqliDatabase::class,
            'pdo'    => PdoDatabase::class,
            default  => throw new DatabaseException(
                sprintf(
                    'Unsupported database driver "%s". Supported drivers are: mysqli and pdo.',
                    $driver
                ),
                ErrorCode::INVALID_DATABASE_DRIVER
            ),
        };

        $connection = $shared
            ? $class::getInstance($config)
            : new $class($config);

        // $connection->setDebug(!PRODUCTION);

        return $connection->connect()
            ? $connection
            : null;
    }

    /**
     * Establish or retrieve a database connection.
     *
     * Reuses a previous connection if available. Optionally retries failed connections
     * based on the retry attempt value from `.env` (`database.connection.retry`).
     *
     * @return DatabaseInterface|null Connected driver instance (MysqliDatabase or PdoDatabase),
     *                                 or null if all attempts fail.
     *
     * @throws DatabaseException If all retry attempts fail in non-production mode.
     */
    public function connect(): ?DatabaseInterface
    {
        if($this->isReady($this->db)){
            return $this->db;
        }

        $retry = (int) env('database.connection.retry', 1);

        if (!$this->shardServerLocation && Database::$connectionSharding) {
            $this->shardServerLocation = Database::getShardServerKey();
            $this->isShardFallbackOnError = Database::$shardFallbackOnError;
        }

        try{
            $connection = $this->retry($retry) ?: $this->retry(null);

            if ($connection instanceof DatabaseInterface) {
                return $connection;
            }

            $err = 'Failed all attempts to establish a database connection.';

            if (!PRODUCTION) {
                throw new DatabaseException($err, ErrorCode::FAILED_ALL_CONNECTION_ATTEMPTS);
            }

            $this->errs[] = Logger::entry('critical', $err);
        } finally {
            $this->eCritical();
        }
        
        return null;
    }

    /**
     * Frees up the statement cursor and close current database connection.
     *
     * @return true Return true if disconnected, false otherwise.
     * 
     * @see self::purge() method to close all connections including pools.
     */
    public function disconnect(): bool
    {
        if ($this->isReady($this->db)) {
            $this->db->close();
        }

        $result = ($this->db instanceof DatabaseInterface) 
            ? !$this->db->isConnected()
            : true;

        $this->db = null;

        return $result;
    }

    /**
     * Attempt to reconnect to the database, optionally falling back to backup servers.
     *
     * If `$retry` is null, it will try all available backup servers.
     * Otherwise, it will attempt reconnects based on the retry count.
     *
     * @param int|null $retry Number of retry attempts (default: 1). Pass `null` to fallback to backup servers.
     *
     * @return DatabaseInterface|null Returns a connected driver instance, or null if all attempts fail.
     *
     * @throws DatabaseException If shard server config is missing, driver is invalid, or connection fails.
     * @throws Exception If an unexpected error occurs during attempts.
     */
    public function retry(?int $retry = 1): ?DatabaseInterface
    {
        if ($this->isReady($this->db)) {
            return $this->db;
        }

        if ($this->pool && self::$pools !== []) {
            $connection = $this->getPool(true);

            if ($this->isReady($connection)) {
                return $connection;
            }

            $this->purge(true);
        }

        if ($retry === null) {
            if ($this->shardServerLocation !== null && !$this->isShardFallbackOnError) {
                return null;
            }

            foreach (Database::getServers() as $config) {
                $connection = $this->retryWithServerConfig($config);

                if ($this->isReady($connection)) {
                    return $connection;
                }
            }

            return null;
        }

        $server = null;

        if ($this->shardServerLocation !== null) {
            $servers = Database::getServers();
            $server = $servers[$this->shardServerLocation] ?? null;

            if (!$server) {
                throw new DatabaseException(
                    sprintf(
                        'Shard server "%s" not found in backup list. Check your configuration or shard mapping.',
                        $this->shardServerLocation
                    ),
                    ErrorCode::RUNTIME_ERROR
                );
            }

            $connection = $this->retryWithServerConfig($server);

            if ($this->isReady($connection) || !$this->isShardFallbackOnError) {
                return $connection;
            }
        }

        return $this->retryFromAttempts($retry, $server);
    }

    /**
     * Releases a connection back to the connection pool.
     *
     * If the pool is not full, adds the provided connection to the pool.
     * If the pool is full, closes the provided connection.
     *
     * @param DatabaseInterface $connection The connection to release.
     * @param string $id An identifier for the current connection pool.
     *
     * @return void
     * @throws DatabaseException Throws if max connections are reached.
     */
    public function release(DatabaseInterface $connection, string $id): void
    {
        if(!$connection instanceof DatabaseInterface){
            return;
        }

        if ($this->count() >= $this->maxPoolConnections) {
            $connection->close();
            $connection = null;

            throw new DatabaseException(
                'Database connection limit has reached it limit per user.',
                ErrorCode::CONNECTION_LIMIT_EXCEEDED
            );
        }

        self::$pools[$id] = $connection;
    }

    /**
     * Purges all pooled connections and optionally closes the current database connection.
     *
     * If the $conn parameter is true, the database connection will be closed; 
     * otherwise, only the pooled connections will be closed.
     *
     * @param bool $closeCurrent If true, close the current database connection also (default: false).
     *
     * @return bool Return true when connections are closed, otherwise false.
     */
    public function purge(bool $closeCurrent = false): bool
    {
        foreach (self::$pools as $connection) {
            if($connection instanceof DatabaseInterface){
                $connection->close();
                $connection = null;
            }
        }

        self::$pools = [];

        return $closeCurrent 
            ? $this->disconnect() 
            : true;
    }

    /**
     * Gets the database configuration based on environment and settings.
     *
     * @return CoreDatabase|null Return the database configuration object or null.
     */
    private static function getDefaultConfig(): ?CoreDatabase
    {
        $config = Util::getEnvDefaultConfig();

        if($config === []){
            $config = array_first(Database::getServers());

            if($config === [] || !is_array($config)){
                return null;
            }
        }

        return Database::fromArray($config);
    }

    /**
     * Retry base on connection attempts.
     * 
     * @param int $retry The number of retry attempts.
     * 
     * @return DatabaseInterface|null Return database connection object.
     */
    private function retryFromAttempts(int $retry, ?array $config = null): ?DatabaseInterface
    {
        $retry = max(1, $retry);
        $lastError = null;
        $configObj = null;

        if($config && $config !== null){
            $configObj = Database::fromArray($config);
        }

        for ($attempt = 1; $attempt <= $retry; $attempt++) {
            try {
                $connection = self::newInstance($configObj);

                if ($this->isReady($connection)) {
                    if($this->pool){
                        $this->release($connection, $this->generateConnectionId($config));
                    }
    
                    return $connection;
                }

                $this->errs[] = Logger::entry(
                    'critical', 
                    'Database connection attempt (' . $attempt . ') failed.'
                );
            } catch (Throwable $e) {
                if($this->isErrorCodeFatal($e->getCode())){
                    $lastError = $e;
                }

                $this->errs[] = Logger::entry(
                    'critical', 
                    'Attempt (' . $attempt . ') failed with error: ' . $e->getMessage()
                );
            }
        }

        if($lastError && $lastError instanceof Throwable ){
            throw $lastError;
        }

        return null;
    }

    /**
     * Connect using sharding or retry from backup databases.
     * 
     * @param array<string,mixed> $config Connection server configurations.
     * 
     * @return DatabaseInterface|null Return database connection object.
     */
    private function retryWithServerConfig(array $config): ?DatabaseInterface
    {
        try {
            $connection = self::newInstance(Database::fromArray($config));

            if ($this->isReady($connection)) {

                if($this->pool){
                    $this->release($connection, $this->generateConnectionId($config));
                }

                if($this->shardServerLocation === null && PRODUCTION){
                    Logger::dispatch('info', sprintf(
                        'Successfully connected to backup database: (%s@%s).',  
                        $config['database'],
                        $config['host']
                    ));
                }

                return $connection;
            }

            $this->errs[] = Logger::entry('critical', sprintf(
                'Backup database connection attempt failed (%s@%s).',  
                $config['database'],
                $config['host']
            ));
        } catch (Throwable $e) {
            if($this->isErrorCodeFatal($e->getCode())){
                throw $e;
            }

            $this->errs[] = Logger::entry('critical', sprintf(
                'Failed to connect to backup database (%s@%s) with error: %s',
                $config['database'],
                $config['host'],
                $e->getMessage()
            ));
        }
    
        return null;
    }

    /**
     * Generates a unique connection pool ID based on the database connection configuration.
     * 
     * The resulting hash is used as a pool identifier for database connections.
     * 
     * @param array<string,mixed>|null $config The database configuration to use for generating the pool ID. 
     *                      If null, the default configuration is used.
     * 
     * @return string Return a hashed pool ID for the database connection.
     */
    private function generateConnectionId(?array $config = null): string
    {
        $config ??= Util::getEnvDefaultConfig();

        return Luminova::hash('xxh3', implode('|', [
            static::class,
            $config['connection']   ?? 'pdo',
            $config['host']         ?? 'localhost',
            $config['port']         ?? 3306,
            $config['username']     ?? 'root',
            $config['database']     ?? 'default',
            $config['pdo_driver']  ?? 'mysql',
            $config['socket_path']  ?? '',
            $config['sqlite_path']  ?? '',
        ]));
    }

    /**
     * Offload errors.
     * 
     * This dispatch all accumulated error log messages once.
     * 
     * @return void
     */
    private function eCritical(): void
    {
        if(!$this->errs){
            return;
        }

        Logger::dispatch('critical', implode(PHP_EOL, $this->errs));
        $this->errs = [];
    }

    /**
     * Check if exception should throw immediately.
     * 
     * @param string|int $code The exception code to check.
     * 
     * @return bool Return true if should throw, false otherwise.
     */
    private function isErrorCodeFatal(string|int $code): bool 
    {
        return !PRODUCTION || in_array($code, [
            ErrorCode::DATABASE_DRIVER_NOT_AVAILABLE,
            ErrorCode::INVALID_DATABASE_DRIVER,
            ErrorCode::RUNTIME_ERROR,
            ErrorCode::VALUE_FORBIDDEN
        ]);
    }

    /**
     * Determine if object is instance of database driver and is connected.
     * 
     * @param DatabaseInterface|null $connection Object or null.
     * 
     * @return bool Return true if connected, otherwise false.
     */
    private function isReady(?DatabaseInterface $connection): bool 
    {
        return ($connection instanceof DatabaseInterface) 
            && $connection->isConnected();
    }
}