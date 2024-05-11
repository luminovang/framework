<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Base;

abstract class BaseDatabase
{
     /**
     * The port to connect to the database.
     * 
     * @var int|null $port 
     */
    private ?int $port = 3306;

    /**
     * The hostname or IP address of the database server.
     * 
     * @var string $host [localhost, 127.0.0.1]
     */
    private string $host = 'localhost'; 

    /**
     * Database connection driver type
     * 
     * @var string $connection [pdo, mysqli]
     */
    private string $connection = 'pdo'; 

    /**
     * The PDO database driver.
     * 
     * @var string|null $pdo_driver 
     */
    private ?string $pdo_driver = 'mysql';

    /**
     * The character set used for the database connection.
     * 
     * @var string $charset 
     */
    private string $charset = 'utf8mb4';

    /**
     * The path to the SQLite database file if applicable.
     * 
     * @var string|null $sqlite_path 
     */
    private ?string $sqlite_path = null;

    /**
     * Indicates if this configuration is for a production environment.
     * 
     * @var bool $production 
     */
    private bool $production = false;

    /**
     * The username for the database connection.
     * 
     * @var string $username 
     */
    private string $username = 'root';

    /**
     * The password for the database connection.
     * 
     * @var string $password 
     */
    private string $password = '';

    /**
     * The name of the database to connect to.
     * 
     * @var string $database 
    */
    private string $database = '';

    /**
     * Database force socket connection
     * 
     * @var bool $socket 
    */
    private bool $socket = false;

    /**
     * Database connection socket path
     * 
     * @var string $socket 
    */
    private string $socket_path = '';

    /**
     * persistent database connection
     * 
     * @var bool $persistent 
    */
    private bool $persistent = true;

    /**
     * emulate preparse statment
     * 
     * @var bool $emulate_preparse 
    */
    private bool $emulate_preparse = false;

    /**
     * Optional servers to connect to when main server fails
     * An associative array with each database configuration keys and values.
     * 
     * @example 
     * protected static array $databaseBackups = [
     *      [
     *          'port' => 0,
     *          'host' => '',
     *          'pdo_driver' => 'mysql',
     *          'connection' => 'pdo',
     *          'charset' => 'utf8',
     *          'persistent' => true,
     *          'emulate_preparse' => false,
     *          'sqlite_path' => null,
     *          'username' => 'root',
     *          'password' => '',
     *          'database' => 'db_name',
     *          'socket' => false,
     *          'socket_path' => ''
     *      ],
     *      ...
     * ]
     * 
     * @var array<int,mixed> $databaseBackups
    */
    protected static array $databaseBackups = [];

    /**
     * Initialize database config 
     * 
     * @param array<string,mixed> $config Database configuration.
    */
    public function __construct(array $config = [])
    {
        if($config !== []){
            $this->configure($config);
        }
    }

    /**
     * Since we don't want devs to think they can change the database properties.
     * We made then private and create a magic getter method to publicly access them.
     * 
     * @param string $property Property name.
     * 
     * @return mixed Return property value if exists, otherwise return null.
    */
    public function __get(string $property): mixed 
    {
        return $this->{$property} ?? null;
    }

    /**
     * Get the value of the protected property $databaseBackups
     *
     * @return array<int,mixed> Return database backups.
     * @internal 
     */
    public static final function getBackups(): array
    {
        return static::$databaseBackups;
    }

    /**
     * Set database configuration properties
     * 
     * @param array<string,mixed> $config Database configuration.
     * 
     * @return void 
    */
    private final function configure(array $config): void 
    {
        $this->port = $config['port'] ?? 3306;
        $this->host = $config['host'] ?? 'localhost';
        $this->connection = $config['connection'] ?? 'pdo';
        $this->pdo_driver = $config['pdo_driver'] ?? 'mysql';
        $this->charset = $config['charset'] ?? 'utf8mb4';
        $this->sqlite_path = $config['sqlite_path'] ?? null;
        $this->production = $config['production'] ?? false;
        $this->username = $config['username'] ?? 'root';
        $this->password = $config['password'] ?? '';
        $this->database = $config['database'] ?? '';
        $this->persistent = $config['persistent'] ?? true;
        $this->socket = $config['socket'] ?? false;
        $this->socket_path = $config['socket_path'] ?? '';
        $this->emulate_preparse = $config['emulate_preparse'] ?? false;
    }
}