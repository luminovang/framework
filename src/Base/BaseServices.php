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

use \Luminova\Interface\ServicesInterface;
use \Luminova\Exceptions\RuntimeException;

abstract class BaseServices implements ServicesInterface
{
    /**
     * @var array<string,array> $serviceQueue;
    */
    private static array $serviceQueue = [];

    /**
     * Add a service class to the service autoloading.
     *
     * Usage:
     *     - static::newService(Configuration::class) as $config = service('Configuration')
     *     - static::newService('\Luminova\Config\Configuration') as $config = service('Configuration')
     *     - static::newService(Configuration:class, 'config') as $config = service('config')
     *     - Services::Configuration()
     *     - Services::config()
     *
     * @param class-string $class The class name to add to service.
     * @param string|null $alias Service class name alias. Defaults to class name.
     * @param bool $shared Whether the instance should be shared. Defaults to true.
     * @param bool $serialize Whether the instance should be serialized and stored in cache. Defaults to false.
     * @param array<int,mixed> $arguments Optional arguments to initialize the class with.
     *
     * @return bool Returns true if the service was added successfully, otherwise throws an exception.
     * @throws RuntimeException If the service already exists or class argument is not an array list.
     */
    protected static final function newService(
        string $class, 
        ?string $alias = null, 
        bool $shared = true, 
        bool $serialize = false, 
        array $arguments = []
    ): true 
    {
        $alias ??= get_class_name($class);

        if(isset(static::$serviceQueue[$alias])){
            throw new RuntimeException(sprintf('Error: Service "%s" is already queued with the same name alias "%s"', $class, $alias));
        }

        if($arguments !== [] && !array_is_list($arguments)){
            throw new RuntimeException('Invlaid argument, class arguments expected array to be list.');
        }

        static::$serviceQueue[$alias] = [
            'service' => $class,
            'shared' => $shared,
            'serialize' => $serialize,
            'arguments' => $arguments
        ];

        return true;
    }

    /**
     * Get queued services.
     * 
     * @return array<string,array> Return queued services.
     * @internal 
    */
    public static final function getServices(): array 
    {
        return static::$serviceQueue;
    }
}