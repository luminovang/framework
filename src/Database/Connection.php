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
use Luminova\Config\Env;
use \App\Config\Database;
use Luminova\Logger\Logger;
use Luminova\Database\Helpers\Util;
use Luminova\Config\DatabaseConfig;
use Luminova\Database\Driver\{PdoDriver, MysqliDriver};
use Luminova\Exceptions\{ErrorCode, DatabaseException, InvalidArgumentException};
use Luminova\Interface\{LazyObjectInterface, DatabaseDriverInterface, DriverConnectionInterface};

class Connection implements LazyObjectInterface, Countable
{
    /**
     * Database connection driver instance.
     *
     * @var DatabaseDriverInterface|null $db
     */
    protected ?DatabaseDriverInterface $db = null;

    /**
     * Connections pools.
     *
     * @var DatabaseDriverInterface[] $pools
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
     * @var string|int|null $shardIdentifier
     */
    private string|int|null $shardIdentifier = null;

    /**
     * Determines whether to fallback to available backup servers 
     * if the selected shard is unavailable.
     *
     * @var bool $isShardFallbackOnError
     */
    private bool $isShardFallbackOnError = false;

    /**
     * Max pool connections.
     *
     * @var int $maxPoolConnections
     */
    private int $maxPoolConnections = 0;

    /**
     * Creates a new database connection instance.
     *
     * Configures connection pooling and the maximum number of pooled connections.
     * If these values are not provided, they are loaded from environment variables:
     * - `database.connection.pool` to enable or disable connection pooling.
     * - `database.max.connections` to set the maximum number of pooled connections (default: 3).
     *
     * When `$autoConnect` is true, the database connection is established during instantiation.
     *
     * @param bool|null $connectionPooling Whether to enable connection pooling. Overrides the
     *                       `database.connection.pool` environment setting if provided.
     * @param int|null $maxPoolConnections Maximum number of pooled connections.
     *                                      Must be greater than zero if provided.
     *                                      Overrides `database.max.connections` if provided.
     * @param bool $autoConnect Whether to establish the database connection immediately
     *                          (default: true).
     *
     * @throws DatabaseException If connection retries fail, the connection limit is exceeded,
     *                           an invalid driver is specified, or a connection error occurs.
     *
     * @see self::with()
     */
    public function __construct(
        private ?bool $connectionPooling = null, 
        ?int $maxPoolConnections = null, 
        private bool $autoConnect = true
    )
    {
        $this->errs = [];
        $this->connectionPooling ??= (bool) Env::get('database.connection.pool', false);

        if($this->connectionPooling){
            $this->setMaxPoolConnections(
                $maxPoolConnections ?? (int) Env::get('database.max.connections', 3)
            );
        }

        if ($this->autoConnect) {
            $this->db = $this->connect();
        }
    }

    /**
     * Enables or disables database connection pooling.
     *
     * @param bool $enabled Whether to enable connection pooling (default: true).
     *
     * @return self Returns the current instance for method chaining.
     * 
     * @see self::setMaxPoolConnections()
     */
    public function enableConnectionPool(bool $enabled = true): self
    {
        $this->connectionPooling = $enabled;
        return $this;
    }

    /**
     * Sets the maximum number of pooled database connections.
     *
     * @param int $connections Maximum number of pooled connections. Must be greater than zero.
     *
     * @return self Returns the current instance for method chaining.
     *
     * @throws InvalidArgumentException If the maximum number of connections is less than one.
     * 
     *  @see self::enableConnectionPool()
     */
    public function setMaxPoolConnections(int $connections): self
    {
        if ($connections < 1) {
            throw new InvalidArgumentException(
                'Maximum pool connections must be greater than zero.'
            );
        }

        $this->maxPoolConnections = $connections;
        return $this;
    }

    /**
     * Sets the identifier of the database shard to connect to.
     *
     * @param string|int $identifier Shard identifier, such as a region name or server key.
     *                               Must not be empty or a negative integer.
     *
     * @return self Returns the current instance for method chaining.
     *
     * @throws InvalidArgumentException If the identifier is empty or a negative integer.
     * 
     * @see self::shard()
     * @see self::enableShardErrorFallback()
     */
    public function setShardIdentifier(string|int $identifier): self
    {
        $isInteger = is_int($identifier);
        $identifier = $isInteger ? $identifier : trim($identifier);

        if ($identifier === '' || ($isInteger && $identifier < 0)) {
            throw new InvalidArgumentException(
                'Shard identifier cannot be empty or negative.'
            );
        }

        $this->shardIdentifier = $identifier;

        return $this;
    }

    /**
     * Enables or disables fallback to a backup server when a shard connection fails.
     *
     * @param bool $enabled Whether to enable shard error fallback (default: false).
     *
     * @return static Returns the current instance for method chaining.
     * 
     * @see self::shard()
     * @see self::setShardIdentifier()
     */
    public function enableShardErrorFallback(bool $enabled = false): static
    {
        $this->isShardFallbackOnError = $enabled;

        return $this;
    }

    /**
     * Database serialization.
     *
     * @return array Return the configuration array of database connection.
     */
    public function __serialize(): array
    {
        return ($this->db instanceof DatabaseDriverInterface) 
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
     * Create a database connection from the specified configuration.
     *
     * If no configuration is provided, the default database configuration is used.
     *
     * @param DatabaseConfig $config Database connection configuration.
     *
     * @return DatabaseDriverInterface|null Returns a new connected database driver object, 
     *                                      or `null` if the connection fails.
     *
     * @throws DatabaseException If connection error occurs.
     */
    public static function with(DatabaseConfig $config): ?DatabaseDriverInterface
    {
        return self::newInstance($config);
    }

    /**
     * Creates a database connection for a specific shard server.
     *
     * Initializes a new connection instance using the specified shard identifier
     * and establishes the connection. If enabled, it can fall back to a backup
     * server when the selected shard connection fails.
     *
     * @param string|int $identifier Shard identifier, such as a region name or server key.
     *                               Must not be empty or a negative integer.
     * @param bool $fallbackOnError Whether to fall back to a backup server if the
     *                              selected shard connection fails (default: false).
     * @param bool|null $connectionPooling Whether to enable connection pooling.
     *                                     If null, uses the configured default.
     *
     * @return static Returns the initialized database connection instance.
     *
     * @throws InvalidArgumentException If the shard identifier is empty or negative.
     * @throws DatabaseException If connection retries fail, the connection limit is
     *                           exceeded, an invalid driver is specified, or a
     *                           connection error occurs.
     *
     * @see self::with()
     * @see self::setShardIdentifier()
     * @see self::enableShardErrorFallback()
     */
    public static function shard(
        string|int $identifier,
        bool $fallbackOnError = false,
        ?bool $connectionPooling = null
    ): static 
    {
        $instance = new static($connectionPooling, autoConnect: false);
        $instance->db = $instance->setShardIdentifier($identifier)
            ->enableShardErrorFallback($fallbackOnError)
            ->connect();

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
     * @return static Returns the singleton instance of the connection class.
     * @throws DatabaseException If connection retries fail, max connection limit is reached, 
     *          an invalid driver is detected, or a connection error occurs.
     * 
     * @see self::with()
     */
    public static function getInstance(): static
    {
        if (!static::$instance instanceof static) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Get number of active open connections.
     * 
     * @return int Return number of open connections.
     */
    public function getOpenConnections(): int
    {
        if(!$this->db instanceof DatabaseDriverInterface){
            return 0;
        }

        return $this->db::getOpenConnections();
    }

    /**
     * Returns the underlying database driver connection wrapper.
     *
     * The returned object provides access to the raw PDO or MySQLi connection
     * through its proxy methods and `getConn()` method.
     *
     * @return DriverConnectionInterface|null Returns the driver connection wrapper,
     *                                        or null if the database driver is unavailable.
     * 
     * @see DatabaseDriverInterface
     * @see Luminova\Database\Driver\DriverConnection
     * @see DriverConnectionInterface::close()
     * @see DriverConnectionInterface::getConn()
     * @see DriverConnectionInterface::isConnected()
     * @see DriverConnectionInterface::isMysqli()
     * @see DriverConnectionInterface::isPdo()
     */
    public function getDriverConn(): ?DriverConnectionInterface
    {
        if (!$this->db instanceof DatabaseDriverInterface) {
            return null;
        }

        return $this->db->getConn();
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
     * @return DatabaseDriverInterface|null The selected connection, or `null` if none are available.
     */
    public function getPool(bool $anyFree = false): ?DatabaseDriverInterface
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

            if (!$connection instanceof DatabaseDriverInterface) {
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
     * @return DatabaseDriverInterface|null Return the driver connection instance, or null if not connected.
     */
    public function database(): ?DatabaseDriverInterface
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
     * Establish or retrieve a database connection.
     *
     * Reuses a previous connection if available. Optionally retries failed connections
     * based on the retry attempt value from `.env` (`database.connection.retry`).
     *
     * @return DatabaseDriverInterface|null Connected driver instance (MysqliDriver or PdoDriver),
     *                                 or null if all attempts fail.
     *
     * @throws DatabaseException If all retry attempts fail in non-production mode.
     */
    public function connect(): ?DatabaseDriverInterface
    {
        if($this->isReady($this->db)){
            return $this->db;
        }

        $retry = (int) Env::get('database.connection.retry', 1);

        if (
            Database::$connectionSharding
            && !$this->hasShard()
        ) {
            $this->shardIdentifier = Database::getShardServerIdentifier();
            $this->isShardFallbackOnError = Database::$shardFallbackOnError;
        }

        try{
            $connection = $this->retry($retry) ?: $this->retry(null);

            if ($connection instanceof DatabaseDriverInterface) {
                return $connection;
            }

            $err = 'Failed all attempts to establish a database connection.';

            if (!PRODUCTION) {
                throw new DatabaseException(
                    $err, 
                    ErrorCode::FAILED_ALL_CONNECTION_ATTEMPTS
                );
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

        $result = ($this->db instanceof DatabaseDriverInterface) 
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
     * @return DatabaseDriverInterface|null Returns a connected driver instance, or null if all attempts fail.
     *
     * @throws DatabaseException If shard server config is missing, driver is invalid, or connection fails.
     * @throws Exception If an unexpected error occurs during attempts.
     */
    public function retry(?int $retry = 1): ?DatabaseDriverInterface
    {
        if ($this->isReady($this->db)) {
            return $this->db;
        }

        if ($this->connectionPooling && self::$pools !== []) {
            $connection = $this->getPool(true);

            if ($this->isReady($connection)) {
                return $connection;
            }

            $this->purge(true);
        }

        if ($retry === null) {
            if ($this->hasShard() && !$this->isShardFallbackOnError) {
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

        if ($this->hasShard()) {
            $server = Database::getServers()[$this->shardIdentifier] ?? null;

            if (!$server) {
                throw new DatabaseException(
                    sprintf(
                        'Shard server "%s" not found in backup list. Check your configuration or shard mapping.',
                        $this->shardIdentifier
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
     * @param DatabaseDriverInterface $connection The connection to release.
     * @param string $id An identifier for the current connection pool.
     *
     * @return void
     * @throws DatabaseException Throws if max connections are reached.
     */
    public function release(DatabaseDriverInterface $connection, string $id): void
    {
        if(!$connection instanceof DatabaseDriverInterface){
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
            if($connection instanceof DatabaseDriverInterface){
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
     * Check if database shard location key exist.
     *
     * @return bool Return true if exists, otherwise false.
     */
    public function hasShard(): bool 
    {
        return $this->shardIdentifier !== null 
            && $this->shardIdentifier !== '';
    }

    /**
     * Create a database connection from the specified configuration.
     *
     * If no configuration is provided, the default database configuration is used.
     *
     * @param DatabaseConfig|null $config Database connection configuration (default: null).
     *
     * @return DatabaseDriverInterface|null Returns the connected database driver, 
     *                                      or `null` if the connection fails.
     *
     * @throws DatabaseException If no configuration is available, the driver is unsupported,
     *                           or a connection error occurs.
     */
    private static function newInstance(?DatabaseConfig $config): ?DatabaseDriverInterface
    {
        $config ??= self::getDefaultConfig();

        if (!$config instanceof DatabaseConfig) {
            return null;
        }

        // if (!$config instanceof DatabaseConfig) {
        //     throw new DatabaseException(
        //         sprintf(
        //             'Invalid database configuration found. Define it in %s or App\\Config\\Database.',
        //             Env::file()
        //         ),
        //         ErrorCode::RUNTIME_ERROR
        //     );
        // }

        $driver = strtolower((string) $config->getValue('connection'));

        $class = match ($driver) {
            'mysqli' => MysqliDriver::class,
            'pdo'    => PdoDriver::class,
            default  => throw new DatabaseException(
                sprintf(
                    'Unsupported database driver "%s".'
                    . ' Supported drivers are: "mysqli" or "pdo".',
                    $driver
                ),
                ErrorCode::INVALID_DATABASE_DRIVER
            ),
        };

        $connection = new $class($config);
        // $connection->setDebug(!PRODUCTION);

        return $connection->connect()
            ? $connection
            : null;
    }

    /**
     * Gets the database configuration based on environment and settings.
     *
     * @return DatabaseConfig|null Return the database configuration object or null.
     */
    private static function getDefaultConfig(): ?DatabaseConfig
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
     * @return DatabaseDriverInterface|null Return database connection object.
     */
    private function retryFromAttempts(int $retry, ?array $config = null): ?DatabaseDriverInterface
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
                    if($this->connectionPooling){
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
     * @return DatabaseDriverInterface|null Return database connection object.
     */
    private function retryWithServerConfig(array $config): ?DatabaseDriverInterface
    {
        try {
            $connection = self::newInstance(Database::fromArray($config));

            if ($this->isReady($connection)) {

                if($this->connectionPooling){
                    $this->release($connection, $this->generateConnectionId($config));
                }

                if(PRODUCTION && !$this->hasShard()){
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
            $config['pdo_driver']   ?? 'mysql',
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

        Logger::tryDispatch(
            'critical', 
            implode(PHP_EOL, $this->errs)
        );

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
            ErrorCode::RUNTIME_ERROR,
            ErrorCode::VALUE_FORBIDDEN,
            ErrorCode::CONNECTION_ERROR,
            ErrorCode::INVALID_DATABASE_DRIVER,
            ErrorCode::DATABASE_DRIVER_NOT_AVAILABLE,
        ]);
    }

    /**
     * Determine if object is instance of database driver and is connected.
     * 
     * @param DatabaseDriverInterface|null $connection Object or null.
     * 
     * @return bool Return true if connected, otherwise false.
     */
    private function isReady(?DatabaseDriverInterface $connection): bool 
    {
        return ($connection instanceof DatabaseDriverInterface) 
            && $connection->isConnected();
    }
}