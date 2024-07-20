<?php 
/**
 * Luminova Framework
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
use \Luminova\Base\BaseDatabase;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Exceptions\DatabaseLimitException;
use \App\Controllers\Config\Database;
use \Exception;

class Connection
{
  /**
   * Database connection driver instance.
   * 
   * @var DatabaseInterface|null $db
  */
  protected ?DatabaseInterface $db = null;

  /** 
   * Database connection static instance.
   * 
   * @var ?self $instance
  */
  private static ?self $instance = null;

  /**
   * Use a connections pool.
   * 
   * @var bool $pool
  */
  private bool $pool = false;

  /**
   * Connections pools.
   * 
   * @var array $pools
  */
  private array $pools = [];

  /**
   * Maximum number of open database connections.
   * 
   * @var int $maxConnections 
  */
  private int $maxConnections = 0;

  /**
   * Initializes the Connection class constructor based on configuration in the .env file.
   * 
   * Initializes the maxConnections and pool properties based on values from the .env file.
   * Establishes the database connection.
   * 
   * @throws DatabaseException If all retry attempts fail or an error occurs during connection.
   * @throws DatabaseLimitException When the maximum connection limit is reached.
   * @throws DatabaseException If an invalid connection configuration or driver is passed.
   */
  public final function __construct()
  {
    $this->maxConnections = (int) env('database.max.connections', 3);
    $this->pool = (bool) env('database.connection.pool', false);
    $this->db ??= $this->connect();
  }

  /**
   * Prevent un-serialization of the singleton instance
   * @ignore
  */
  public function __serialize(): array
  {
    return [];
  }

  /**
   * Restore connection after un-serialization
   * @param array $data un-serialized data
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
   * @return DatabaseInterface|null Return driver connection instance..
  */
  public function database(): ?DatabaseInterface
  {
    return $this->db;
  }

  /**
   * Retrieves the shared singleton instance of the Connection class.
   *
   * @return static Connection class instance.
   * 
   * @throws DatabaseException If all retry attempts fail or an error occurs during connection.
   * @throws DatabaseLimitException When the maximum connection limit is reached.
   * @throws DatabaseException If an invalid connection configuration or driver is passed.
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
   * @param BaseDatabase|null $config Database configuration (default: null).
   *
   * @return DatabaseInterface|null Database driver instance.
   * 
   * @throws DatabaseException If all retry attempts fail, an error occurs during connection, or an invalid driver interface is detected.
   * @throws DatabaseLimitException When the maximum connection limit is reached.
   * @throws DatabaseException If an invalid database driver is provided.
  */
  public static function newInstance(BaseDatabase|null $config = null): ?DatabaseInterface
  {
    $config ??= self::getDefaultConfig();
    $drivers = [
      'mysqli' => MySqliDriver::class,
      'pdo' => PdoDriver::class
    ];

    $driver = $drivers[$config->connection] ?? null;

    if($driver === null){
      throw new DatabaseException("Invalid database connection driver: '{$config->connection}', use [mysql or pdo].");
    }

    $connection = new $driver($config);

    if (!$connection instanceof DatabaseInterface) {
      throw new DatabaseException("Driver class '{$driver}' does not implement DatabaseInterface.");
    }
    
    $connection->setDebug(!PRODUCTION);

    return $connection;
  }

  /**
   * Connects to the database, either returning a connection instance or reusing a previous connection from the pool if available.
   * Optionally retries failed connections based on the retry attempt value set in the .env file (`database.connection.retry`).
   *
   * @param int|null $retry Number of retry attempts (default: 1).
   * 
   * @return DatabaseInterface|null Database driver instance (either MySqliDriver or PdoDriver).
   * @throws DatabaseException If all retry attempts fail or an error occurs during connection.
   * @throws DatabaseLimitException When the maximum connection limit is reached.
   * @throws DatabaseException If an invalid connection configuration or driver is passed.
  */
  public function connect(): ?DatabaseInterface
  {
    $connection = $this->retry((int) env('database.connection.retry', 1));

    if($connection === null){
      $connection = $this->retry(null);

      if($connection === null){
        throw new DatabaseException('Failed all attempts to establish a database connection');
      }
    }

    return $connection;
  }

  /**
   * Retries the database connection with optional backup server fallback.
   * 
   * If the retry parameter is set to null, retries the connection with backup servers if available.
   * 
   * @param int|null $retry Number of retry attempts (default: 1).
   * 
   * @return DatabaseInterface|null Connection instance or null if all retry attempts fail.
   * 
   * @throws DatabaseException If all retry attempts fail or an error occurs during connection.
   * @throws DatabaseLimitException When the maximum connection limit is reached.
   * @throws DatabaseException If an invalid connection configuration or driver is passed.
  */
  public function retry(int|null $retry = 1): ?DatabaseInterface
  {
    if(isset($this->db) && $this->db->isConnected()){
      return $this->db;
    }

    if ($this->pool) {
      if (count($this->pools) >= $this->maxConnections) {
        throw new DatabaseLimitException("Database connection limit has reached it limit per user.");
      }

      if (!empty($this->pools)) {
        $connection = array_pop($this->pools);

        if (isset($connection) && $connection->isConnected()) {
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
          $connection = static::newInstance(self::newConfig($config));
        } catch (DatabaseException|Exception $e) {
          logger('error', 'Failed to connect to backup database: ' . $e->getMessage(), [
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database']
          ]);
        }

        if (isset($connection) && $connection->isConnected()) {
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
          $connection = static::newInstance();
      } catch (DatabaseException|Exception $e) {
        logger('error', 'Attempt (' . $attempt . '), failed to connect to database: ' . $e->getMessage());
      }

      if (isset($connection) && $connection->isConnected()) {
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
   * If the connection pool is not full, adds the provided connection to the pool.
   * If the connection pool is full, closes the provided connection.
   * 
   * @param DatabaseInterface|null $connection The connection to release.
   * 
   * @return void
  */
  public function release(DatabaseInterface|null $connection): void
  {
    if (count($this->pools) < $this->maxConnections) {
      $this->pools[] = $connection;
      return;
    }
    
    $connection?->close();
    $connection = null;
  }

  /**
   * Purges all stacked pool connections and optionally closes the database connection.
   * 
   * If the conn parameter is true, the database connection will be closed; otherwise, only the pool connections will be closed.
   * 
   * @param bool $conn If true, close the database connection. Default is false.
   * 
   * @return void 
  */
  public function purge(bool $conn = false): void
  {
    foreach ($this->pools as $connection) {
      $connection?->close();
      $connection = null;
    }

    $this->pools = [];

    if($conn){
      $this->db?->close();
    }
  }

  /**
    * Get the database configuration based on environment and settings.
    *
    * @return BaseDatabase Database configuration object.
  */
  private static function getDefaultConfig(): BaseDatabase
  {
    $var = (PRODUCTION ? 'database' : 'database.development');
    $sqlite = env("{$var}.sqlite.path", '');
    $sqlite = ($sqlite !== '') ? APP_ROOT . trim($sqlite, DIRECTORY_SEPARATOR) : null;
 
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
   * @return BaseDatabase Return based database instance with loaded configuration
  */
  private static function newConfig(array $config): BaseDatabase
  {
    return new class($config) extends BaseDatabase { 
      public function __construct(array $config) {
        parent::__construct($config);
      }
    };
  }
}