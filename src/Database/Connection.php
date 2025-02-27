<?php 
/**
 * Luminova Framework connection class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Database;

use \Luminova\Database\Drivers\MysqliDriver;
use \Luminova\Database\Drivers\PdoDriver;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Core\CoreDatabase;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\DatabaseException;
use \App\Config\Database;
use \Countable;
use \Exception;

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
     * Indicates whether to use a connections pool.
     *
     * @var bool $pool
     */
    private bool $pool = false;

    /**
     * Maximum number of open database connections.
     *
     * @var int $maxConnections
     */
    private int $maxConnections = 0;

    /**
     * Initializes a database connection based on provided parameters or default to `.env` configuration.
     *
     * - Configures `maxConnections` and `pool` properties from the provided arguments or environment variables.
     * - If `$pool` or `$max` are provided, they override the corresponding environment variable values.
     *
     * @param bool|null $pool Optional. Weather to enables or disables connection pooling.
     *                        Defaults to the value of `database.connection.pool` from the environment.
     * @param int|null  $maxConnections  Optional. Specifies the maximum number of database connections.
     *                        Defaults to the value of `database.max.connections` from the environment.
     *
     * @throws DatabaseException If connection retries fail, the max connection limit is exceeded, 
     * an invalid driver or driver interface is detected, or a connection error occurs.
     */
    public function __construct(?bool $pool = null, ?int $maxConnections = null)
    {
        $this->maxConnections = $maxConnections ?? (int) env('database.max.connections', 3);
        $this->pool = $pool ?? (bool) env('database.connection.pool', false);
        $this->db = $this->connect();
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
     * Retrieves the shared singleton instance of the Connection class.
     *
     * @param bool|null $pool Optional. Weather to enables or disables connection pooling (default: `database.connection.pool`).
     * @param int|null  $maxConnections  Optional. Specifies the maximum number of database connections (default: `database.max.connections`).
     * 
     * @return static Return the singleton instance of the Connection class.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public static function getInstance(?bool $pool = null, ?int $maxConnections = null): static 
    {
        return self::$instance ??= new self($pool, $maxConnections);
    }

    /**
     * Retrieves a free connection from the pool. Optionally fetches the first available valid connection.
     * 
     * If `$any_free` is set to `true`, the method returns the first free connection that is connected and valid, removing it from the pool. 
     * Otherwise, it fetches the first connection in the pool and returns it if valid, or `null` if no valid connection exists.
     *
     * @param bool $any_free If `true`, returns the first valid connection from the pool (default: `false`).
     *
     * @return DatabaseInterface|null Return the first valid connection from the pool or `null` if none are available.
     */
    public function getPool(bool $any_free = false): ?DatabaseInterface
    {
        if($any_free){
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

        if ($id !== null) {
            $conn = self::$pools[$id]; 
            unset(self::$pools[$id]);
            return ($conn instanceof DatabaseInterface && $conn->isConnected()) ? $conn : null;
        }

        return null;
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
        $drivers = [
            'mysqli' => MysqliDriver::class,
            'pdo' => PdoDriver::class
        ];

        $driver = $drivers[$config->connection] ?? null;

        if ($driver === null) {
            throw new DatabaseException(
                sprintf('Invalid database connection driver: "%s", use (mysql or pdo).', $config->connection),
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

        return $connection;
    }

    /**
     * Connects to the database, returning a connection instance or reusing a previous connection from the pool if available.
     * Optionally retries failed connections based on the retry attempt value set in the .env file (`database.connection.retry`).
     *
     * @param int|null $retry Number of retry attempts (default: 1).
     *
     * @return DatabaseInterface|null Return the database driver instance (either MysqliDriver or PdoDriver), or null if connection fails.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public function connect(): ?DatabaseInterface
    {
        $connection = $this->retry((int) env('database.connection.retry', 1)) ?: $this->retry(null);

        if ($connection instanceof DatabaseInterface) {
            return $connection;
        }
    
        if(PRODUCTION){
            Logger::dispatch('critical', 'Failed all attempts to establish a database connection.');
            return null;
        }

        throw new DatabaseException(
            'Failed all attempts to establish a database connection', 
            DatabaseException::FAILED_ALL_CONNECTION_ATTEMPTS
        );
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
        if($this->db instanceof DatabaseInterface && $this->db->isConnected()){
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
     * @param int|null $retry The number of retry attempts (default: 1). Pass `null` to attempt fallback to backup servers.
     * 
     * @return DatabaseInterface|null Returns a database connection if successful, or `null` if all attempts fail.
     * 
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, or an error occurs during connection.
     * @throws Exception If any unexpected error occurs during the connection attempts.
     */
    public function retry(int|null $retry = 1): ?DatabaseInterface
    {
        if($this->db instanceof DatabaseInterface && $this->db->isConnected()){
            return $this->db;
        }

        if ($this->pool && self::$pools !== []) {
            $connection = $this->getPool(true);

            if ($connection instanceof DatabaseInterface && $connection->isConnected()) {
                return $connection;
            }

            $this->purge(true);
        }

        $connection = null;

        // Retry from backup databases
        if ($retry === null) {
            $servers = Database::getBackups();
    
            foreach ($servers as $config) {
                try {
                    $connection = self::newInstance(self::newConfig($config));

                    if ($connection instanceof DatabaseInterface && $connection->isConnected()) {
                        if($this->pool){
                            $this->release($connection, $this->generatePoolId($config));
                        }
    
                        if(PRODUCTION){
                            Logger::dispatch('critical', sprintf(
                                'Successfully connected to backup database: (%s@%s).',  
                                $config['database'],
                                $config['host']
                            ));
                        }
    
                        return $connection;
                    }

                    Logger::dispatch('critical', sprintf(
                        'Backup database connection attempt failed (%s@%s).',  
                        $config['database'],
                        $config['host']
                    ));
                } catch (DatabaseException|Exception $e) {
                    Logger::dispatch('critical', sprintf(
                        'Failed to connect to backup database (%s@%s) with error: %s',
                        $config['database'],
                        $config['host'],
                        $e->getMessage()
                    ));
                }
            }

            return $connection;
        }

        // Retry base on connection attempts
        for ($attempt = 1; $attempt <= max(1, $retry); $attempt++) {
            try {
                $connection = self::newInstance();

                if ($connection instanceof DatabaseInterface && $connection->isConnected()) {
                    if($this->pool){
                        $this->release($connection, $this->generatePoolId());
                    }
    
                    return $connection;
                }

                Logger::dispatch('critical', 'Database connection attempt (' . $attempt . ') failed.');
            } catch (DatabaseException|Exception $e) {
                Logger::dispatch('critical', 'Attempt (' . $attempt . ') failed with error: ' . $e->getMessage());
            }
        }

        return $connection;
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

        if ($this->count() >= $this->maxConnections) {

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
     * @param bool $close_current If true, close the current database connection also (default: false).
     *
     * @return bool Return true when connections are closed, otherwise false.
     */
    public function purge(bool $close_current = false): bool
    {
        foreach (self::$pools as $connection) {
            if($connection instanceof DatabaseInterface){
                $connection->close();
                $connection = null;
            }
        }

        self::$pools = [];

        return $close_current 
            ? $this->disconnect() 
            : true;
    }

    /**
     * Gets the database configuration based on environment and settings.
     *
     * @return CoreDatabase Return the database configuration object.
     */
    private static function getDefaultConfig(): CoreDatabase
    {
        $var = (PRODUCTION ? 'database' : 'database.development');
        $sqlite = env("{$var}.sqlite.path", '');
        $sqlite = ($sqlite !== '') ? APP_ROOT . trim($sqlite, TRIM_DS) : null;

        return self::newConfig(self::getArrayConfig());
    }

    /**
     * Retrieves the configuration settings for the database connection from the environment.
     * 
     * @return array Return an associative array containing database connection settings.
     */
    private static function getArrayConfig(): array
    {
        $var = (PRODUCTION ? 'database' : 'database.development');
        $sqlite = env("{$var}.sqlite.path", '');
        $sqlite = ($sqlite !== '') ? APP_ROOT . trim($sqlite, TRIM_DS) : null;

        return [
            'port' => env('database.port'),
            'host' => env('database.hostname'),
            'pdo_engine' => env('database.pdo.engine', 'mysql'),
            'connection' => strtolower(env('database.connection', 'pdo')),
            'charset' => env('database.charset', ''),
            'persistent' => (bool) env('database.persistent.connection', true),
            'emulate_preparse' => (bool) env('database.emulate.preparse', false),
            'sqlite_path' => $sqlite,
            'socket' => (bool) env('database.mysql.socket', false),
            'socket_path' => env('database.mysql.socket.path', ''),
            'production' => PRODUCTION,
            'username' => env("{$var}.username"),
            'password' => env("{$var}.password"),
            'database' => env("{$var}.name")
        ];
    }

    /**
     * Generates a unique pool ID based on the database connection configuration.
     * 
     * This method constructs a unique string by combining essential database connection parameters (username, connection type, host, port, etc.)
     * and hashes it using the SHA-256 algorithm. The resulting hash is used as a pool identifier for database connections.
     * 
     * @param array|null $config The database configuration to use for generating the pool ID. If null, the default configuration is used.
     * 
     * @return string Return a hashed pool ID for the database connection.
     */
    private function generatePoolId(?array $config = null): string
    {
        $config ??= self::getArrayConfig();
        return hash('sha256', sprintf(
            '%s%s%s%d%s%s%s%s',
            $config['username'] ?? 'default',
            $config['connection'] ?? 'pdo',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 3306,
            $config['database'] ?? 'default',
            $config['socket_path'] ?? '',
            $config['pdo_engine'] ?? 'mysql',
            $config['sqlite_path'] ?? ''
        ));
    }

   /**
    * Anonymizes class to extend base database.
    * 
    * @param array<string,mixed> $config Database configuration.
    * 
    * @return \T<CoreDatabase> Return based database instance with loaded configuration
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
}