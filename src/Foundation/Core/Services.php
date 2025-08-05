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
namespace Luminova\Foundation\Core;

use \Luminova\Interface\LazyObjectInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\ServicesInterface;
use function \Luminova\Funcs\get_class_name;

abstract class Services implements ServicesInterface, LazyObjectInterface
{
    /**
     * Service queue.
     *
     * @var array<string,array> $serviceQueue
     */
    private static array $serviceQueue = [];

    /**
     * Add a service class to the service auto-loader.
     *
     * @param class-string $class The fully qualified class name of the service.
     * @param string|null $alias An optional alias for the service, NULL will defaults to the class name.
     * @param bool $shared Whether the service instance should be shared (default: true).
     * @param bool $serialize Whether the instance should be serialized and cached (default: false).
     * @param array<int,mixed> $arguments Optional arguments to pass when initializing the service class.
     *
     * @return bool Returns true if the service was successfully added, or throws an exception.
     *
     * @throws RuntimeException If the service is already queued or if the `arguments` parameter is not a list.
     * 
     * @example - Usage examples:
     *     - self::newService(Configuration::class) // access via service('Configuration')
     *     - self::newService('\Luminova\Config\Configuration') // access via service('Configuration')
     *     - self::newService(Configuration::class, 'config') // access via service('config')
     *     - Services::Configuration() // shorthand method to access the service
     *     - Services::config() // access via the alias 'config'
     */
    protected static final function newService(
        string $class, 
        ?string $alias = null, 
        bool $shared = true, 
        bool $serialize = false, 
        array $arguments = []
    ): bool 
    {
        $alias ??= get_class_name($class);

        if (isset(self::$serviceQueue[$alias])) {
            throw new RuntimeException(sprintf('Error: Service "%s" is already queued with the alias "%s".', $class, $alias));
        }

        if ($arguments !== [] && !array_is_list($arguments)) {
            throw new RuntimeException('Invalid argument: Expected a list array for class arguments.');
        }

        self::$serviceQueue[$alias] = [
            'service' => $class,
            'shared' => $shared,
            'serialize' => $serialize,
            'arguments' => $arguments
        ];

        return true;
    }

    /**
     * Get all queued services.
     * 
     * @return array<string,array> Returns an array of queued services.
     * 
     * @internal
     */
    public static final function getServices(): array 
    {
        return self::$serviceQueue;
    }
}