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

abstract class BaseConfig
{
    /**
    * @var string $version version name
    */
    public static $version = '2.9.0';

    /**
    * @var int $versionCode version code
    */
    public static $versionCode = 290;

    /**
     * Minimum required php version
    * @var string MIN_PHP_VERSION 
    */
    public const MIN_PHP_VERSION = '8.0';

    /**
     * Get the file version.
     *
     * @return string
     */
    public static function fileVersion(): string 
    {
        return static::getString("app.file.version");
    }

    /**
     * Check if minification is enabled.
     *
     * @return int
     */
    public static function shouldMinify(): int 
    {
        return static::getInt("build.minify");
    }

    /**
     * Get environment configuration variables.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @param string|null $return Optional return types 
     *      - [bool, int, float, double, nullable, string]
     * 
     * @return mixed
     */
    public static function getEnv(string $key, mixed $default = null, ?string $return = null): mixed 
    {
        $value = env($key, $default);
    
        switch (strtolower($return)) {
            case 'bool':
                return (bool) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'double':
                return (double) $value;
            case 'nullable':
                return $value === '' ? null : $value;
            case 'string':
                return (string) $value;
            default:
                return $value;
        }
    }

    /**
     * Get environment variable as integer value
     *
     * @param string $key variable name
     * @param int $default fallback to default
     * @return bool
    */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = env($key, $default);

        return (int) $value;
    }

    /**
     * Get the framework copyright information
     *
     * @return string
    */
    public static function copyright(): string
    {
        return 'PHP Luminova (' . static::$version . ')';
    }

    /**
     * Get the framework version number
     *
     * @return string
    */
    public static function version(): string
    {
        return static::$version;
    }
}