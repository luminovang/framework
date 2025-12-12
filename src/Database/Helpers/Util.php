<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Helpers;

use \stdClass;
use Luminova\Exceptions\JsonException;
use Luminova\Exceptions\InvalidArgumentException;

final class Util 
{
    /**
     * Default connection details from .env
     *
     * @var array<string,mixed>|null $default
     */
    private static ?array $default = null;

    /**
     * Get the MySQLi parameter type from a value.
     *
     * @param mixed $value The value to determine the type for.
     * @param bool $forInt Whether to return the integer constant for the type (true) 
     *          or the character representation (false).
     * @return string|int The MySQLi parameter type as a character ('s', 'i', 'd', 'b') 
     *          or an integer constant (PARAM_STR, PARAM_INT, etc.).
     */
    public static function getMySqliTypeFromValue(mixed $value, bool $forInt = false): string|int  
    {
        return match (true) {
            is_null($value) => $forInt ? PARAM_NULL : 's',
            is_float($value),
            (is_string($value) && is_numeric($value) && str_contains($value, '.'))
                => $forInt ? PARAM_FLOAT : 'd',
            is_int($value),
            is_bool($value)
                => $forInt ? PARAM_BOOL : 'i',
            is_resource($value),
            (is_string($value) && preg_match('~[^\x09\x0A\x0D\x20-\x7E]~', $value))
                => $forInt ? PARAM_LOB : 'b',
            default => $forInt ? PARAM_STR : 's',
        };
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
     * Validate and normalize a numeric coordinate.
     *
     * Ensures the coordinate is a finite numeric value within the specified
     * range and returns it as a normalized decimal string suitable for SQL.
     *
     * @param string|float $value Coordinate value.
     * @param string $name Coordinate name used in exception messages.
     * @param float $min Minimum allowed value.
     * @param float $max Maximum allowed value.
     *
     * @return string Normalized coordinate value.
     *
     * @throws InvalidArgumentException If the coordinate is invalid or out of range.
     */
    public static function normalizeCoordinate(
        string|float $value,
        string $name,
        float $min,
        float $max
    ): string
    {
        if (
            is_string($value)
            && $value !== ''
            && $value[0] === ':'
            && preg_match('/^:[a-zA-Z_][a-zA-Z0-9_.]*$/', $value)
        ) {
            return $value;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s: expected a finite number or a named placeholder, got "%s".',
                $name,
                $value
            ));
        }

        $numeric = (float) $value;

        if (!is_finite($numeric)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s: expected a finite number, got "%s".',
                $name,
                $value
            ));
        }

        if ($numeric < $min || $numeric > $max) {
            throw new InvalidArgumentException(sprintf(
                'Invalid %s: %s is out of range [%s, %s].',
                $name,
                $numeric,
                $min,
                $max
            ));
        }

        return sprintf('%.8F', $numeric);
    }

    /**
     * Retrieves the configuration settings for the database connection from the environment.
     * 
     * @return array Return an associative array containing database connection settings.
     */
    public static function getEnvDefaultConfig(): array
    {
        if(self::$default !== null){
            return self::$default;
        }

        $host = env('database.hostname');
        $socketPath = env('database.socket.path', null) 
            ?? env('database.mysql.socket.path', '');

        if(empty($host) && empty($socketPath)){
            return [];
        }

        $sqlite = env('database.sqlite.path', null);
        self::$default = [
            'port'        => env('database.port'),
            'host'        => $host,
            'username'    => env('database.username'),
            'password'    => env('database.password', ''),
            'database'    => env('database.name'),
            'commands'    => env('database.commands'),
            'charset'     => env('database.charset', ''),
            'socket'      => (bool) (env('database.socket.connection') ?? env('database.mysql.socket', false)),
            'timeout'     => (int) env('database.timeout', 0),
            'production'  => PRODUCTION,
            'sqlite_path' => null,
            'socket_path' => $socketPath,
            'connection'  => env('database.connection', 'pdo'),
            'pdo_driver' => env('database.pdo.driver') 
                ?? env('database.pdo.version', 'mysql'),
            'persistent'  => (bool) env('database.persistent.connection', false),
            'buffered_query'   => (bool) env('database.mysql.buffered.query', false),
            'emulate_prepares' => (bool) env('database.emulate.prepares', false),
        ];

        if($sqlite && !is_file($sqlite) ){
            self::$default['sqlite_path'] = APP_ROOT . ltrim($sqlite, TRIM_DS);
        }

        return self::$default;
    }
}