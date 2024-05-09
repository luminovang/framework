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
use \Luminova\Exceptions\RuntimeException;

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
     * @param mixed|arguments ...$arguments Arguments to initialize class with.
     * @param bool $shared — Whether the instance should be shared (cached) or not, default true
     * @param bool $serialize Whether the instance should be serialized and (cached) or not, default false.
     * 
     * @return true Return true service was added, otherwise throw an excption.
     * @throws RuntimeException If service already exist or unable to initiate class
     */
    protected static function addService(string|object $classOrInstance, mixed ...$arguments): true 
    {
        $name = get_class_name($classOrInstance);

        if(isset(static::$serviceQueue[$name])){
            throw new RuntimeException('Error: service is already queued with same name "' . $name . '"');
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
     * @internal 
    */
    public static function getServices(): array 
    {
        return static::$serviceQueue;
    }
}