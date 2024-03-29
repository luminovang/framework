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

use \Luminova\Application\Services;
use \Luminova\Interface\ServicesInterface;

abstract class BaseServices extends Services implements ServicesInterface
{
    /**
     * @var array<string, mixed> $serviceQueue;
    */
    private static array $serviceQueue = [];

    /**
     * Add instance or class name to service shared instance
     * The last 2 argument should be boolean value to indicate whether shared instance or serialized cached 
     *
     * Usages:
     * @example static::addService(Configuration::class) as $config = service('Configuration')
     * @example static::addService('\Luminova\Config\Configuration') as $config = service('Configuration')
     * @example static::addService(Configuration::class) as $config = service('Configuration')
     * @example static::addService(new Configuration()) as $config = service('Configuration')
     * @example Services::Configuration()
     * 
     * @param string|object $classOrInstance Class name or instance of a class
     * @param arguments ...$arguments Arguments to initialize class with.
     * @param bool $shared — Whether the instance should be shared (cached) or not, default true
     * @param bool $serialize Whether the instance should be serialized and (cached) or not, default false.
     * 
     * @return object|false  Return object instance if shared, false otherwise
     * @throws RuntimeException If service already exist or unable to initiate class
     */
    protected static function addService(string|object $classOrInstance, ...$arguments): bool 
    {
        $name = get_class_name($classOrInstance);

        if(isset(static::$serviceQueue[$name])){
            return false;
        }

        static::$serviceQueue[$name] = [
            'service' => $classOrInstance,
            'arguments' => $arguments
        ];

        return true;
    }

    /**
     * Get queued services 
     * 
     * @return array<string, mixed>
    */
    public static function getServices(): array 
    {
        return static::$serviceQueue;
    }
}