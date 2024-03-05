<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Config;

/**
 * Database Configuration
 *
 * This class represents the configuration for a database connection.
 */
class Database
{
    /**
     * The port to connect to the database.
     * @var int|null $port 
     */
    public ?int $port = 3306;

    /**
     * The hostname or IP address of the database server.
     * @var string $host [localhost, 127.0.0.1]
     */
    public string $host = 'localhost'; 

    /**
     * The version of the database server.
     * @var string|null $version 
     */
    public ?string $version = 'mysql';

    /**
     * The character set used for the database connection.
     * @var string $charset 
     */
    public string $charset = 'utf8';

    /**
     * The path to the SQLite database file if applicable.
     * @var string|null $sqlite_path 
     */
    public ?string $sqlite_path = '';

    /**
     * Indicates if this configuration is for a production environment.
     * @var bool $production 
     */
    public bool $production = false;

    /**
     * The username for the database connection.
     * @var string $username 
     */
    public string $username = '';

    /**
     * The password for the database connection.
     * @var string $password 
     */
    public string $password = '';

    /**
     * The name of the database to connect to.
     * @var string $database 
    */
    public string $database = '';

    /**
     * persistent database connection
     * @var bool $persistent 
    */
    public bool $persistent = true;
}