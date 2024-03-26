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

abstract class BaseServers
{
    /**
     * Optional servers to connect to when main server fails
     * An associative array with each database configuration keys and values.
     * 
     * Supported array keys 
     * @example $databaseServers = [
     *      [
     *          'port' => 0,
     *          'host' => '',
     *          'version' => 'mysql',
     *          'charset' => 'utf8',
     *          'sqlite_path' => '',
     *          'username' => 'root',
     *          'password' => '',
     *          'database' => 'db_name',
     *      ],
     *      ...
     * ]
     * 
     * @var array<int, mixed> $databaseServers
    */
    protected static array $databaseServers = [];

    /**
     * Get the value of the protected property $databaseServers
     *
     * @return array<int, mixed>
     */
    public static function getDatabaseServers(): array
    {
        return static::$databaseServers;
    }
}