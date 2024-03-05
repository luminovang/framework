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

use Luminova\Database\Drivers\MySqlDriver;
use Luminova\Database\Drivers\PdoDriver;
use Luminova\Config\Database;

class Connection
{
    /** 
      * Database connection instance 
      * @var MySqlDriver|PdoDriver|null $db
    */
    protected MySqlDriver|PdoDriver|null $db = null;
 
    /** 
     * @var Connection|null $instance
     */
    private static ?Connection $instance = null;

    /** 
     * @var bool $production
     * 
     */
    private static bool $production = false;

    /**
    * Connection constructor.
    *
    * Initializes the database connection based on configuration.
    * @throws DatabaseException|InvalidException If fails
    */
    public function __construct()
    {
      self::$production = env('app.environment.mood', 'development') === 'production';
      $this->db ??= self::createDatabaseInstance();
      $this->db->setDebug(!self::$production);
    }
 
    /**
     * Get the singleton instance of Connection.
     *
     * @return self Database connection instance.
     * @throws DatabaseException|InvalidException If fails
    */
    public static function newInstance(): self 
    {
      return self::$instance ??= new static();
    }
 
    /**
      * Create an instance of the database driver based on configuration.
      *
      * @return object Database driver instance (either MySqlDriver or PdoDriver).
      * @throws DatabaseException|InvalidException If fails
    */
    private static function createDatabaseInstance(): object
    {
      $driver = env("database.driver", 'PDO');
      $config = self::getDatabaseConfig();

      return match ($driver) {
        "MYSQLI" => new MySqlDriver($config),
        default => new PdoDriver($config)
      };
    }
 
    /**
      * Get the database configuration based on environment and settings.
      *
      * @return Database Database configuration object.
    */
    private static function getDatabaseConfig(): Database
    {
      $config = new Database();
      $config->port = env("database.port");
      $config->host = env("database.hostname");
      $config->version = env("database.version");
      $config->charset = env("database.charset");
      $config->sqlite_path = env("database.sqlite.path");
      $config->production = self::$production;
      $config->username = self::$production ? env("database.username") : env("database.development.username");
      $config->password = self::$production ? env("database.password") : env("database.development.password");
      $config->database = self::$production ? env("database.name") : env("database.development.name");

      return $config;
    }
}
 