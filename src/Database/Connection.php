<?php 
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
use \Throwable;
use \Exception;
use \App\Config\Database;
use \Luminova\Logger\Logger;
use \Luminova\Core\CoreDatabase;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Database\Drivers\{PdoDriver, MysqliDriver};
use \Luminova\Interface\{LazyInterface, DatabaseInterface};

class Connection implements LazyInterface, Countable
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
     * @var string $logEntry
     */
    private static string $logEntry = '';

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

        if ($this->autoConnect) {
            $this->db = $this->connect();
        }
    }

    /**
     * Prevents un-serialization of the singleton instance.
     *
     * @return array Return the serializable array of database connection.
     * @ignore
     */
    public function __serialize(): array
    {
        return [];
    }

    /**
     * Restores the connection after un-serialization.
     *
     * @param array $data Un-serialized data.
     *
     * @return void
     * @ignore
     */
    public function __unserialize(array $data): void
    {
        $this->db ??= $this->connect();
    }

    /**
     * Initialize a database connection for a specific shard server.
     *
     * This static initializer creates (or reuses) an instance of the connection class,
     * optionally assigning it to a specific shard server identified by `$locationId`.
     * If the selected shard is unreachable, it can fallback to available backup servers.
     *
     * @param string  $locationId         Shard identifier (e.g., region name or server key).
     * @param bool    $fallbackOnError    Fallback to a backup server if shard server connection is unavailable.
     * @param ?bool   $pool               Enable connection pooling (if applicable).
     * @param ?int    $maxPoolConnections     Maximum number of connections allowed in the pool.
     * @param bool    $sharedInstance     Reuse a shared static instance if set to true.
     * 
     * @return Connection Returns an initialized database connection instance.
     * @throws DatabaseException If connection retries fail, max connection limit is reached, an invalid driver is detected, or a connection error occurs.
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
            ? self::getInstance($pool, $maxPoolConnections, false)
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
     * @throws DatabaseException If connection retries fail, max connection limit is reached, an invalid driver is detected, or a connection error occurs.
     */
    public static function getInstance(
        ?bool $pool = null, 
        ?int $maxPoolConnections = null, 
        bool $autoConnect = true
    ): static
    {
        if (!self::$instance instanceof static) {
            self::$instance = new static($pool, $maxPoolConnections, $autoConnect);
        }

        return self::$instance;
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
     * Retrieves a free connection from the pool. Optionally fetches the first available valid connection.
     * 
     * If `$anyFree` is set to `true`, the method returns the first free connection that is connected and valid, removing it from the pool. 
     * Otherwise, it fetches the first connection in the pool and returns it if valid, or `null` if no valid connection exists.
     *
     * @param bool $anyFree If `true`, returns the first valid connection from the pool (default: `false`).
     *
     * @return DatabaseInterface|null Return the first valid connection from the pool or `null` if none are available.
     */
    public function getPool(bool $anyFree = false): ?DatabaseInterface
    {
        if($anyFree){
            foreach (self::$pools as $idx => $connection) {
                if($connection instanceof DatabaseInterface ){
                    if($connection->isConnected()){
                        unset(self::$pools[$idx]);
                        return $connection;
                    }
                    
                    $connection->close();
                    $connection = null;
                }
               
                unset(self::$pools[$idx]);
            }
    
            self::$pools = [];
            return null;
        }

        $id = array_key_first(self::$pools);

        if ($id === null) {
            return null;
        }

        $connection = self::$pools[$id]; 
        unset(self::$pools[$id]);
        return $this->isReady($connection) ? $connection : null;
    }

    /**
     * Retrieves a new database driver instance based on the provided configuration.
     *
     * If no configuration is provided, the default configuration will be used.
     *
     * @param CoreDatabase|null $config Database configuration (default: null).
     *
     * @return DatabaseInterface|null Return the database driver instance, or null if connection fails.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public static function newInstance(?CoreDatabase $config = null): ?DatabaseInterface
    {
        $config ??= self::getDefaultConfig();

        if (!($config instanceof CoreDatabase)) {
            throw new DatabaseException(
                'Invalid connection: no configuration defined. Set connection info in the .env file or App\\Config\\Database class.',
                DatabaseException::RUNTIME_ERROR
            );
        }        

        $drivers = [
            'mysqli' => MysqliDriver::class,
            'pdo' => PdoDriver::class
        ];

        $driver = $drivers[$config->getValue('connection')] ?? null;

        if ($driver === null) {
            throw new DatabaseException(
                sprintf('Invalid database connection driver: "%s", use (mysql or pdo).', $config->getValue('connection')),
                DatabaseException::INVALID_DATABASE_DRIVER
            );
        }

        $connection = new $driver($config);

        if (!$connection instanceof DatabaseInterface) {
            throw new DatabaseException(
                sprintf('The selected driver class: "%s" does not implement: %s.', $driver, DatabaseInterface::class), 
                DatabaseException::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }
        
        $connection->setDebug(!PRODUCTION);
        
        if($connection->connect()){
            return $connection;
        }

        $connection = null;

        return null;
    }

    /**
     * Establish a database connection.
     * 
     * This either returns a connection instance or reusing a previous connection from the pool if available.
     * Optionally it retries failed connections based on the retry attempt value set in the .env file (`database.connection.retry`).
     *
     * @param int|null $retry Number of retry attempts (default: 1).
     *
     * @return DatabaseInterface|null Return the database driver instance (either MysqliDriver or PdoDriver), or null if connection fails.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public function connect(): ?DatabaseInterface
    {
        if(!$this->shardServerLocation && Database::$connectionSharding){
            $this->shardServerLocation = Database::getShardServerKey();
            $this->isShardFallbackOnError = Database::$shardFallbackOnError;
        }

        self::$logEntry = '';
        $connection = $this->retry((int) env('database.connection.retry', 1)) ?: $this->retry(null);

        if ($connection instanceof DatabaseInterface) {
            self::eCritical();
            return $connection;
        }
    
        $err = 'Failed all attempts to establish a database connection.';

        if(PRODUCTION){
            if(!self::$logEntry){
                Logger::dispatch('critical', $err);
                return null;
            }

            self::$logEntry .= Logger::entry('critical', $err);
            self::eCritical();
            return null;
        }

        throw new DatabaseException($err, DatabaseException::FAILED_ALL_CONNECTION_ATTEMPTS);
    }

    /**
     * Frees up the statement cursor and close current database connection.
     *
     * @return true Return true if disconnected, false otherwise.
     * 
     * @see purge() method to close all connections including pools.
     */
    public function disconnect(): bool
    {
        if ($this->isReady($this->db)) {
            $this->db->close();
        }

        return !$this->db->isConnected();
    }

    /**
     * Attempts to reconnect to the database with optional fallback to backup servers.
     * 
     * If `$retry` is set to `null`, the method will attempt to connect using backup databases (if available).
     * Otherwise, it will attempt to reconnect based on the specified retry count.
     * 
     * @param int|null $retry The number of retry attempts (default: 1). 
     *              Pass `null` to attempt fallback to backup servers.
     * 
     * @return DatabaseInterface|null Returns a database connection if successful, or `null` if all attempts fail.
     * 
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, or an error occurs during connection.
     * @throws Exception If any unexpected error occurs during the connection attempts.
     */
    public function retry(int|null $retry = 1): ?DatabaseInterface
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
            if($this->shardServerLocation !== null && !$this->isShardFallbackOnError){
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

        if($this->shardServerLocation !== null){
            $server = Database::getServers()[$this->shardServerLocation] ?? null;

            if(!$server){
                throw new DatabaseException(sprintf(
                    'Shard server location "%s" not found in backup list. Check your configuration or shard mapping.',
                    $this->shardServerLocation
                ), DatabaseException::RUNTIME_ERROR);
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
                DatabaseException::CONNECTION_LIMIT_EXCEEDED
            );
        }

        self::$pools[$id] = $connection;
    }

    /**
     * Purges all pooled connections and optionally closes the current database connection.
     *
     * If the $conn parameter is true, the database connection will be closed; otherwise, only the pooled connections will be closed.
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
        $config = self::getEnvDefaultConfig();

        if($config === []){
            $configs = Database::getServers();
            $config = reset($configs);
        }

        return (!$config || $config === []) ? null : self::newConfig($config);
    }

    /**
     * Retrieves the configuration settings for the database connection from the environment.
     * 
     * @return array Return an associative array containing database connection settings.
     */
    private static function getEnvDefaultConfig(): array
    {
        $host = env('database.hostname');
        $socketPath = env('database.mysql.socket.path', '');

        if(!$host && !$socketPath){
            return [];
        }

        $var = (PRODUCTION ? 'database' : 'database.development');
        $sqlite = env("{$var}.sqlite.path", '');
        $sqlite = ($sqlite !== '') ? APP_ROOT . trim($sqlite, TRIM_DS) : null;
        
        return [
            'port' => env('database.port'),
            'host' => $host,
            'pdo_version' => env('database.pdo.version', 'mysql'),
            'connection' => strtolower(env('database.connection', 'pdo')),
            'charset' => env('database.charset', ''),
            'persistent' => (bool) env('database.persistent.connection', true),
            'emulate_prepares' => (bool) env('database.emulate.prepares', false),
            'sqlite_path' => $sqlite,
            'socket' => (bool) env('database.mysql.socket', false),
            'timeout' => (int) env('database.timeout', 0),
            'socket_path' => $socketPath,
            'production' => PRODUCTION,
            'username' => env("{$var}.username"),
            'password' => env("{$var}.password"),
            'database' => env("{$var}.name")
        ];
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
        for ($attempt = 1; $attempt <= max(1, $retry); $attempt++) {
            try {
                $connection = self::newInstance(($config === null) ? null : self::newConfig($config));

                if ($this->isReady($connection)) {
                    
                    if($this->pool){
                        $this->release($connection, $this->generatePoolId($config));
                    }
    
                    return $connection;
                }

                self::$logEntry .= Logger::entry(
                    'critical', 
                    'Database connection attempt (' . $attempt . ') failed.'
                );
            } catch (Throwable $e) {
                if($this->shouldThrow($e->getCode())){
                    throw $e;
                }

                self::$logEntry .= Logger::entry(
                    'critical', 
                    'Attempt (' . $attempt . ') failed with error: ' . $e->getMessage()
                );
            }
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
            $connection = self::newInstance(self::newConfig($config));

            if ($this->isReady($connection)) {

                if($this->pool){
                    $this->release($connection, $this->generatePoolId($config));
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

            self::$logEntry .= Logger::entry('critical', sprintf(
                'Backup database connection attempt failed (%s@%s).',  
                $config['database'],
                $config['host']
            ));
        } catch (Throwable $e) {
            if($this->shouldThrow($e->getCode())){
                throw $e;
            }

            self::$logEntry .= Logger::entry('critical', sprintf(
                'Failed to connect to backup database (%s@%s) with error: %s',
                $config['database'],
                $config['host'],
                $e->getMessage()
            ));
        }
    
        return null;
    }

    /**
     * Generates a unique pool ID based on the database connection configuration.
     * 
     * The resulting hash is used as a pool identifier for database connections.
     * 
     * @param array<string,mixed>|null $config The database configuration to use for generating the pool ID. 
     *                      If null, the default configuration is used.
     * 
     * @return string Return a hashed pool ID for the database connection.
     */
    private function generatePoolId(?array $config = null): string
    {
        $config ??= self::getEnvDefaultConfig();
        return md5(sprintf(
            '%s%s%s%d%s%s%s%s',
            $config['username'] ?? 'root',
            $config['connection'] ?? 'pdo',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? 'default',
            $config['socket_path'] ?? '',
            $config['pdo_version'] ?? 'mysql',
            $config['sqlite_path'] ?? ''
        ));
    }

    /**
     * Anonymizes class to extend base database.
     * 
     * @param array<string,mixed> $config Database configuration.
     * 
     * @return CoreDatabase<\T> Return based database instance with loaded configuration
     */
    private static function newConfig(array $config): CoreDatabase
    {
        return new class($config) extends CoreDatabase 
        { 
            public function __construct(array $config) 
            {
                parent::__construct($config);
            }
        };
    }

    /**
     * Dispatch all accumulated log messages.
     * 
     * return void
     */
    private static function eCritical(): void
    {
        if(!self::$logEntry){
            return;
        }

        Logger::dispatch('critical', self::$logEntry);
        self::$logEntry = '';
    }

    /**
     * Check if exception should throw immediately.
     * 
     * @param string|int The exception code to check.
     * 
     * @return bool Return true if should throw, false otherwise.
    */
    private function shouldThrow(string|int $code): bool 
    {
        return in_array($code, [
            DatabaseException::DATABASE_DRIVER_NOT_AVAILABLE,
            DatabaseException::INVALID_DATABASE_DRIVER,
            DatabaseException::RUNTIME_ERROR
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
        return ($connection instanceof DatabaseInterface && $connection->isConnected());
    }
}