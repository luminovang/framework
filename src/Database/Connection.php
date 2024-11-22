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

use \Luminova\Database\Drivers\MySqliDriver;
use \Luminova\Database\Drivers\PdoDriver;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Core\CoreDatabase;
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
    private array $pools = [];

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
     * Initializes the Connection class based on the configuration in the .env file.
     * 
     * Sets maxConnections and pool properties from .env values.
     * Establishes the initial database connection.
     *
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public function __construct()
    {
        $this->maxConnections = (int) env('database.max.connections', 3);
        $this->pool = (bool) env('database.connection.pool', false);
        $this->db ??= $this->connect();
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
        return count($this->pools);
    }

    /**
     * Retrieves the shared singleton instance of the Connection class.
     *
     * @return static Return the singleton instance of the Connection class.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public static function getInstance(): static 
    {
        return self::$instance ??= new static();
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
            'mysqli' => MySqliDriver::class,
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
     * @return DatabaseInterface|null Return the database driver instance (either MySqliDriver or PdoDriver), or null if connection fails.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public function connect(): ?DatabaseInterface
    {
        $connection = $this->retry((int) env('database.connection.retry', 1)) 
            ?: $this->retry(null);

        if (!$connection instanceof DatabaseInterface) {
            throw new DatabaseException(
                'Failed all attempts to establish a database connection', 
                DatabaseException::FAILED_ALL_CONNECTION_ATTEMPTS
            );
        }

        return $connection;
    }

    /**
     * Frees up the statement cursor and close current database connection.
     *
     * @return true Always return true.
     * @see purge() method to close all connections including pools.
     */
    public function disconnect(): bool
    {
        if($this->db instanceof DatabaseInterface && $this->db->isConnected()){
            $this->db->close();
        }

        return true;
    }

    /**
     * Retries the database connection with optional backup server fallback.
     *
     * If the retry parameter is set to null, retries the connection with backup servers if available.
     *
     * @param int|null $retry Number of retry attempts (default: 1).
     *
     * @return DatabaseInterface|null Return connection instance or null if all retry attempts fail.
     * @throws DatabaseException If all retry attempts fail, the maximum connection limit is reached, an invalid database driver is provided, an error occurs during connection, or an invalid driver interface is detected.
     */
    public function retry(int|null $retry = 1): ?DatabaseInterface
    {
        if($this->db instanceof DatabaseInterface && $this->db->isConnected()){
            return $this->db;
        }

        if ($this->pool) {
            if (count($this->pools) >= $this->maxConnections) {
                throw new DatabaseException(
                    'Database connection limit has reached it limit per user.',
                    DatabaseException::CONNECTION_LIMIT_EXCEEDED
                );
            }

            if ($this->pools !== []) {
                $connection = array_pop($this->pools);

                if ($connection instanceof DatabaseInterface && $connection->isConnected()) {
                    return $connection;
                }

                $this->purge(true);
            }
        }

        $connection = null;

        if ($retry === null) {
            $servers = Database::getBackups();
    
            foreach ($servers as $config) {
                try {
                    $connection = self::newInstance(self::newConfig($config));
                } catch (DatabaseException|Exception $e) {
                    logger('critical', 'Failed to connect to backup database: ' . $e->getMessage(), [
                        'host' => $config['host'],
                        'port' => $config['port'],
                        'database' => $config['database'],
                        'errorCode' => $e->getCode()
                    ]);
                }

                if ($connection instanceof DatabaseInterface && $connection->isConnected()) {
                    if($this->pool){
                        $this->release($connection);
                    }

                    logger('info', 'Successfully connected to backup database: (' . $config['database'] . ')');
                    return $connection;
                }
            }

            return $connection;
        }

        $maxAttempts = max(1, $retry);
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $connection = self::newInstance();
            } catch (DatabaseException|Exception $e) {
                logger('critical', 'Attempt (' . $attempt . '), failed to connect to database: ' . $e->getMessage());
            }

            if ($connection instanceof DatabaseInterface && $connection->isConnected()) {
                if($this->pool){
                    $this->release($connection);
                }

                return $connection;
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
     * @param DatabaseInterface|null $connection The connection to release.
     *
     * @return void
     */
    public function release(DatabaseInterface|null $connection): void
    {
        if(!$connection instanceof DatabaseInterface){
            return;
        }

        if (count($this->pools) < $this->maxConnections) {
            $this->pools[] = $connection;
            return;
        }
        
        $connection->close();
        $connection = null;
    }

    /**
     * Purges all pooled connections and optionally closes the current database connection.
     *
     * If the $conn parameter is true, the database connection will be closed; otherwise, only the pooled connections will be closed.
     *
     * @param bool $close_current If true, close the current database connection also (default: false).
     *
     * @return bool Always true when connection are closed.
     */
    public function purge(bool $close_current = false): bool
    {
        foreach ($this->pools as $connection) {
            if($connection instanceof DatabaseInterface){
                $connection->close();
                $connection = null;
            }
        }

        $this->pools = [];

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

        return self::newConfig([
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
        ]);
    }

   /**
    * Anonymizes class to extend base database.
    * 
    * @param array<string,mixed> $config Database configuration.
    * 
    * @return CoreDatabase<\anonymous> Return based database instance with loaded configuration
    */
   private static function newConfig(array $config): CoreDatabase
   {
        return new class($config) extends CoreDatabase 
        { 
            public function __construct(array $config) {
                parent::__construct($config);
            }
        };
   }
}