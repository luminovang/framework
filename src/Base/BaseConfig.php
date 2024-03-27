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
    public static $version = '2.9.5';

    /**
    * @var int $versionCode version code
    */
    public static $versionCode = 295;

    /**
     * Minimum required php version
    * @var string MIN_PHP_VERSION 
    */
    public const MIN_PHP_VERSION = '8.0';

    /**
     * Check if minification is enabled.
     *
     * @return int
    */
    public static function shouldMinify(): int 
    {
        return static::getEnv('build.minify', 0, 'int');
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
     * Get the framework copyright information
     *
     * @return string
     * @ignore
    */
    public static function copyright(): string
    {
        return 'PHP Luminova (' . static::$version . ')';
    }

    /**
     * Get the framework version name or code.
     * 
     * @param bool $integer Return version code or version name (default: name).
     * 
     * @return string|int
    */
    public static function version(bool $integer = false): string|int
    {
        return $integer ? static::$versionCode : static::$version;
    }
}