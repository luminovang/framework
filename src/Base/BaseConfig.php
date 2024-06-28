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
     * Initializer
    */
    public function __construct(){}

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
    public static final function getEnv(string $key, mixed $default = null, ?string $return = null): mixed 
    {
        $value = env($key, $default);
        
        if($return === null || !is_string($value)){
            return $value;
        }

        return match (strtolower($return ?? '')) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'double' => (double) $value,
            'nullable' => ($value === '') ? null : $value,
            'string' => (string) $value,
            default => $value
        };
    }
}