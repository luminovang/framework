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
use \Luminova\Database\Drivers\DriversInterface;
use \Luminova\Config\Database;
use \App\Controllers\Config\Servers;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Exceptions\DatabaseLimitException;
use \Exception;

class Connection
{
  /** 
   * Database connection instance 
   * 
   * @var DriversInterface|null $db
  */
  protected ?DriversInterface $db = null;

  /** 
   * @var ?Connection $instance
   */
  private static ?self $instance = null;

  /**
   * Pool connections 
   * 
   * @var array $pool
  */
  private array $pool = [];

  /**
   * Maximum number of connections
   * 
   * @var int $maxConnections 
  */
  private int $maxConnections = 0;

  /**
  * Connection constructor.
  *
  * Initializes the database connection based on configuration.
  * @throws DatabaseException
  * @throws DatabaseLimitException
  * @throws InvalidArgumentException
  * @throws Exception
  */
  final public function __construct()
  {
    $this->maxConnections = (int) env('database.max.connections', 0);
    $this->db ??= $this->reuse();

    if((bool) env('database.connection.pool', false) && $this->maxConnections > 0){
      $this->release($this->db);
    }
  }

  /**
    * Create or return connection instance from pool
    * If there is no maximum connection limit or the pool is empty, create a new instance
    * Check if the total number of connections in the pool has reached the maximum limit
    * Else reuse an existing connection from the pool
    *
    * @return object Database driver instance (either MySqliDriver or PdoDriver).
    * @throws DatabaseException
    * @throws DatabaseLimitException
    * @throws InvalidArgumentException
    * @throws Exception
  */
  private function reuse(): object
  {
    if ($this->maxConnections === 0 || empty($this->pool)) {
      return static::newInstance();
    }

    if (count($this->pool) >= $this->maxConnections) {
      $servers = Servers::getDatabaseServers();

      if($servers === []){
        throw new DatabaseLimitException("Database connection limit has reached it limit per user.");
      }

      return $this->retry($servers);
    }

    return array_pop($this->pool);
  }

  /**
   * Get the singleton instance of Connection.
   *
   * @return self Connection instance
   * @throws DatabaseException
   * @throws InvalidArgumentException
   * @throws Exception
  */
  public static function getInstance(): self 
  {
    return static::$instance ??= new static();
  }

  /**
   * Get new database connection instance
   * No shared instance connection
   * 
   * @param Database $config 
   *
   * @return DriversInterface Database driver instance
   * @throws DatabaseException
   * @throws InvalidArgumentException
   * @throws Exception
  */
  public static function newInstance(Database $config = null): DriversInterface 
  {
    static $connection = null;
    
    if ($connection === null) {
        $driver = strtolower(env('database.connection', 'PDO'));
        $config ??= static::getConfig();

        $connection = match ($driver) {
            'mysqli' => new MySqliDriver($config),
            default => new PdoDriver($config)
        };

        $connection->setDebug(!PRODUCTION);
    }

    return $connection;
  }


  /**
   * Release connection back to pool 
   * 
   * @param DriversInterface $connection
   * 
   * @return void
  */
  public function release(DriversInterface $connection): void
  {
    if (count($this->pool) < $this->maxConnections) {
      $this->pool[] = $connection;
    } else {
      $connection = null;
    }
  }

  /**
   * Close all stacked pool connection
   * 
   * @return void 
  */
  public function closeAll(): void
  {
    foreach ($this->pool as $connection) {
      $connection = null;
    }

    $this->pool = [];
  }

  /**
    * Get the database configuration based on environment and settings.
    *
    * @return Database Database configuration object.
  */
  private static function getConfig(): Database
  {
    $var = PRODUCTION ? 'database' : 'database.development';
    return new Database([
      'port' => env('database.port'),
      'host' => env('database.hostname'),
      'pdo_driver' => env('database.pdo.driver'),
      'charset' => env('database.charset', ''),
      'persistent' => (bool) env('database.persistent.connection', true),
      'emulate_preparse' => (bool) env('database.emulate.preparse', false),
      'sqlite_path' => env('database.sqlite.path'),
      'production' => PRODUCTION,
      'username' => env("{$var}.username"),
      'password' => env("{$var}.password"),
      'database' => env("{$var}.name")
    ]);
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
    $this->db ??= $this->reuse();
  }

  /**
   * Retry database connection
   * 
   * @param array $service array of service to try to connect
   * 
   * @return DriversInterface|bool 
  */
  public function retry(array $servers): DriversInterface|bool
  {
    $maxAttempts = count($servers);
    $attemptCount = 0;
    $connection = false;

    while (!$connection && $attemptCount < $maxAttempts) {
      $config = $servers[$attemptCount];

      try {
        $connection = $this->db->isConnected() ? $this->db : $this->newInstance(new Database($config));
      } catch (DatabaseException | Exception $e) {
          logger('error', 'Failed to connect to database: ' . $e->getMessage());
      }

      $attemptCount++;
    }

    if (!$connection) {
        throw new DatabaseLimitException('Failed all attempts to establish a database connection');
    }

    return $connection;
  }
}
 