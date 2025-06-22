<?php 
/**
 * Luminova Framework Database connection configuration.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Core;

use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\JsonException;
use stdClass;

abstract class CoreDatabase implements LazyInterface
{
    /**
     * Database connection properties.
     * 
     * @var array<string,mixed> $immutables
     * @internal
     */
    private array $immutables = [
        'port'              => 3306,
        'host'              => 'localhost',
        'connection'        => 'pdo',
        'pdo_version'       => 'mysql',
        'charset'           => 'utf8mb4',
        'sqlite_path'       => null,
        'production'        => false,
        'username'          => 'root',
        'password'          => '',
        'database'          => '',
        'socket'            => false,
        'socket_path'       => '',
        'persistent'        => true,
        'timeout'           => 0,
        'emulate_prepares'  => true
    ];

    /**
     * Whether to fallback to an available backup server if the selected shard is unreachable.
     * 
     * When enabled, the system will attempt to use a backup configuration from `$databaseServers`
     * if the main shard server fails or is offline.
     * 
     * This is useful in distributed environments where high availability is required.
     *
     * @var bool $shardFallbackOnError
     */
    public static bool $shardFallbackOnError = false;

    /**
     * Enable or disable connection-level database sharding.
     *
     * When set to true, the application will attempt to route database connections 
     * based on shard configurations defined by the shard location identifier `getShardServerKey`. 
     * This allows for distributing database load across multiple shard servers 
     * for better scalability and isolation (e.g., multi-tenant or geo-based setups).
     *
     * If false, all connections will be made to the default primary database configuration.
     *
     * @var bool $connectionSharding
     */
    public static bool $connectionSharding = false;

    /**
     * List of database connection servers.
     * 
     * This serves two purposes:
     * - Provides alternative connections for **sharded environments** when a shard is unavailable.
     * - Acts as a **failover mechanism** for non-sharded environments when the main database fails.
     * 
     * Each server should follow the same structure as the primary configuration.
     *
     * @var array<string|int,array<string, mixed>> $databaseServers
     *
     * @example
     * Database::$databaseServers = [
     *     'NG' => [
     *         'host' => 'ng.db.server',
     *         'port' => 3306,
     *         'database' => 'my_db_ng',
     *         'connection' => 'pdo', 
     *         'pdo_version' => 'mysql',
     *         'username' => 'user_ng',
     *         'password' => 'secret',
     *         'charset' => 'utf8mb4',
     *         'version' => 'mysql',
     *         'persistent' => true,
     *         'emulate_prepares' => true,
     *         'socket' => false,
     *         'socket_path' => '',
     *         'sqlite_path' => '', // Only used if version is sqlite
     *     ],
     *     'US' => [
     *         'host' => 'us.db.server',
     *         ...
     *     ]
     * ];
     */
    protected static array $databaseServers = [];

    /**
     * Initialize database configuration with backup connection details.
     * 
     * @param array<string,mixed> $config The database configuration.
     */
    public function __construct(array $config = [])
    {
        if($config !== []){
            $this->immutables = array_replace($this->immutables, $config);
        }
    }

    /**
     * Retrieve all database connection servers.
     *
     * @return array<string|int,mixed> Return database connection servers.
     * @internal 
     */
    public static final function getServers(): array
    {
        return static::$databaseServers;
    }

    /**
     * Get database configuration property value.
     * 
     * @param string $key The property configuration key.
     * @param mixed $default The default value.
     * 
     * @return mixed Return database connection property value.
     */
    public final function getValue(string $key, mixed $default = null): mixed 
    {
        return $this->immutables[$key] ?? $default;
    }

    /**
     * Check if SQL query is DDL.
     * 
     * @param string $query The SQL query to check.
     * 
     * @return bool Return true if the query is DDL, false otherwise.
     */
    public static final function isDDLQuery(string $query): bool 
    {
        return preg_match(
            '/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME|COMMENT|GRANT|REVOKE|ANALYZE|DISCARD|CLUSTER|VACUUM)\b/i', 
            $query
        ) === 1;
    }

    /**
     * Checks if the given SQL query starts with a specific SQL command type.
     *
     * @param string $query The raw SQL query string.
     * @param string $type  The SQL command type to check for (default is 'SELECT').
     * 
     * @return bool Returns true if the query starts with the specified type, false otherwise.
     */
    public static function isSqlQuery(string $query, string $type = 'SELECT'): bool 
    {
        return str_starts_with(ltrim(strtoupper($query)), $type);
    }

     /**
     * Converts a mixed value to an object with optimal JSON handling
     * 
     * @param mixed $response Input data to convert (array, string, object, etc.).
     *
     * @return object Always returns an object representation
     */
    public static function toResultObject(mixed $response): object
    {
        if (!$response || empty((array) $response)) {
            return new stdClass() ;
        }

        try {
            if (is_array($response) || is_object($response)) {
                return (object) json_decode(
                    json_encode($response, JSON_THROW_ON_ERROR), 
                    false, 
                    512, 
                    JSON_THROW_ON_ERROR
                );
            }
        } catch (JsonException) {
            return (object) $response;
        }

        return (object) $response;
    }

    /**
     * Get the target shard location identifier.
     * 
     * This method determines the shard key used to route the database connection.
     * It supports static values (e.g., a region code like 'NG') or can be overridden
     * to return the appropriate shard dynamically based on runtime conditions.
     *
     * Typical use cases include:
     * - Geo-based sharding
     * - Load-balanced regional databases
     * - Multi-tenant database separation
     *
     * @return string The shard identifier key (e.g., 'NG', 'EU').
     *
     * @example Static shard mapping:
     * ```php
     * public static function getShardServerKey(): string 
     * {
     *     return 'NG';
     * }
     * ```
     *
     * @example Dynamic shard resolution:
     * ```php
     * public static function getShardServerKey(): string 
     * {
     *     return resolveUserRegion();
     * }
     * ```
     */
    public static function getShardServerKey(): string
    {
        return '';
    }
}