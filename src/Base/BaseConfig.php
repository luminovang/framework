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
     * Get environment configuration variables with a specific allowed return type.
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
}