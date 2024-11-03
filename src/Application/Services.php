<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Application;

use \Luminova\Storages\FileManager;
use \Luminova\Exceptions\RuntimeException;
use \Throwable;
use \ReflectionClass;
use \ReflectionException;

final class Services 
{
    /**
     * Class storage suffix 
     * 
     * @var string $suffix;
    */
    private static string $suffix = '.class.txt';
    
    /**
     * Autoload services.
     *
     * @var array<string,array> $services
    */
    private static array $services = [];

    /**
     * Cached instances of service classes.
     *
     * @var array<string,object> $instances
    */
    private static array $instances = [];

    /**
     * Dynamically create an instance of the specified service class.
     * Get shared instance or re-imitate stored instance with a new parameters 
     *
     * @param class-string<\T>|string $service The class name or class name alias of the service.
     * @param array $arguments Param arguments to instigate class with 
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     * 
     * @example Services::method('foo')
     * 
     * @return class-object<\T>|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
     * @ignore 
    */
    public static function __callStatic(string $service, array $arguments): object
    {
        return self::call($service, $arguments);
    }

    /**
     * Dynamically create an instance of the specified service class.
     * Get shared instance or re-imitate stored instance with a new parameters 
     *
     * @param class-string<\T>|string $service The class name or class name alias of the service.
     * @param array $arguments Param arguments to instigate class with 
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     * 
     * @example Services::method('foo')
     * 
     * @return class-object<\T>|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
     * @ignore 
    */
    public function __call(string $service, array $arguments): object
    {
        return self::call($service, $arguments);
    }

    /**
     * Check if service has a cached instance of class
     *
     * @param class-string<\T>|string $service The service class name or class name alias.
     * 
     * @return bool Return true if service class exists, false otherwise.
    */
    public static function has(string $service): bool
    {
        $alias = get_class_name($service);

        if(isset(self::$services[$alias]) || isset(self::$instances[$alias])){
            return true;
        }

        $path = path('services') . $alias . self::$suffix;
        return file_exists($path);
    }

    /**
     * Delete a service and it cached instances
     *
     * @param class-string<\T>|string $service The service class name or alias.
     * 
     * @return bool Return true if cached service was deleted, false otherwise.
    */
    public static function delete(string $service): bool
    {
        $alias = get_class_name($service);

        if(isset(self::$instances[$alias])){
            unset(self::$instances[$alias]);
        }

        if(isset(self::$services[$alias])){
            unset(self::$services[$alias]);
        }


        $path = path('services') . $alias . self::$suffix;
        return file_exists($path) && unlink($path);
    }

    /**
     * Clear all service and cached instance.
     * 
     * @return bool Return true if cached services was cleared, false otherwise.
    */
    public static function clear(): bool
    {
        $path = path('services');
        self::$instances = [];
        self::$services = [];

        return is_dir($path) ? FileManager::remove($path) : false;
    }

    /**
     * Get method instance 
     * 
     * @param string $alias The service class name alias.
     * 
     * @return class-object<\T>|null $instance Instance or null
    */
    private static function getInstance(string $alias): ?object
    {
        if(isset(self::$instances[$alias])){
            return self::$instances[$alias];
        }

        try{
            return self::getCachedInstance($alias);
        }catch(Throwable $e){
            throw new RuntimeException("Failed to instantiate service '$alias'.", $e->getCode(), $e);
        }
    }

    /**
     * Add service instance.
     *
     * @param class-string<\T>|class-object<\T> $service Class name or instance of a class.
     * @example \Namespace\Utils\MyClass, MyClass::class or new MyClass()
     * @param mixed $arguments [, mixed $... ] Arguments to initialize class with.
     *  -   The last 2 argument should be boolean values to indicate whether to shared instance or cached.
     * @param bool $shared Whether the instance should be shared (default: true).
     * @param bool $serialize Whether the instance should be serialized and (default: false).
     * 
     * @return class-object<\T> Return object instance service class, null otherwise.
     * @throws RuntimeException If service already exist or unable to initiate class.
    */
    public static function add(string|object $service, mixed ...$arguments): object
    {
        $alias = get_class_name($service);
   
        if (self::has($alias)) {
            throw new RuntimeException("Failed to add service, service with '$alias'. already exist remove service before adding it again or call with new arguments.");
        }

        try{
            $shared = self::isShared($arguments);
            $serialize = self::isSerialize($arguments);
            $shared = ($serialize && !$shared) ? true : $shared;

            if (empty($arguments)) {
                $instance = is_string($service) ? new $service() : $service;
            } else {
                $reflection = new ReflectionClass($service);
                if (!$reflection->isInstantiable()) {
                    throw new ReflectionException("Service class: '{$service}' is not instantiable.");
                }

                $instance = $reflection->newInstance(...$arguments);
            }

            if($shared || $serialize){
                self::storeInstance($alias, $instance, $shared, $serialize);
            }

            return $instance;
        } catch (ReflectionException | Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Call class instance.
     *
     * @param class-string<\T>|string $service The class name or class name alias of the service.
     * @param array $arguments Arguments to instigate class with.
     * 
     * @return class-object<\T>|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
    */
    private static function call(string $service, array $arguments): object
    {
        $alias = get_class_name($service);
        $instance = self::getInstance($alias);

        if($instance === null){
            throw new RuntimeException("Failed to instantiate service '$service'. Service not found, only existing service can be initialized.");
        }

        $shared = self::isShared($arguments);
        $serialize = self::isSerialize($arguments);
        $shared = ($serialize && !$shared) ? true : $shared;

        if (empty($arguments) && ($shared || $serialize)) {
            return $instance;
        }

        try{
            $reflection = new ReflectionClass($instance);

            if (!$reflection->isInstantiable()) {
                throw new ReflectionException("Service class: '{$service}' is not instantiable.");
            }

            $instance = $reflection->newInstance(...$arguments);
            self::storeInstance($alias, $instance, $shared, $serialize);
            return $instance;
        } catch (ReflectionException|Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Autoload application services.
     * 
     * @param array $services Queued services.
     * 
     * @return true Return true.
     * @internal
    */
    public static function queService(array $services): bool
    {
        self::$services = $services;

        return true;
    }

    /**
     * Is shared instance 
     * 
     * @param array $arguments Class arguments.
     * 
     * @return bool  Return true if instance should be shared, false otherwise.
    */
    private static function isShared(array &$arguments): bool
    {
        if ($arguments === []) {
            return true;
        }

        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            return array_pop($arguments);
        }

        return true;
    }

    /**
     * Is shared instance should be serialize
     * 
     * @param array $arguments Class arguments.
     * 
     * @return bool Return true if instance should be serialized, false otherwise.
    */
    private static function isSerialize(array &$arguments): bool
    {
        if ($arguments === []) {
            return false;
        }

        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            return array_pop($arguments);
        }

        return false;
    }

    /**
     * Prepare Instance to save on serialized object or shared instance 
     * 
     * @param string $alias Service class name  alias.
     * @param class-object<\T>|null $instance object instance 
     * @param bool $shared Should share instance 
     * @param bool $serialize Should serialize instance 
     * 
     * @return void 
     * @throws Throwable
    */
    private static function storeInstance(
        string $alias, 
        ?object $instance = null, 
        bool $shared = true, 
        bool $serialize = false
    ): void 
    {
        if($instance === null){
            return;
        }

        if($shared || $serialize){
            self::$instances[$alias] = $instance;
        }

        if($serialize){
            $stringInstance = serialize($instance);
            if($stringInstance === ''){
                throw new RuntimeException("Failed to serialize class '$alias'.");
            }

            try {
                $path = path('services');
                make_dir($path);
                write_content($path . $alias . self::$suffix, $stringInstance);
            } catch (Throwable $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * Get shared cached instance from stored file 
     *
     * @param string $alias The class name alias.
     * 
     * @return class-object<\T>|null Return class object or null.
    */
    private static function getCachedInstance(string $alias): ?object
    {
        $path = path('services') . $alias . self::$suffix;

        if (file_exists($path)) {
            $content = get_content($path);

            if ($content !== false) {
                return unserialize($content);
            }
        }

        if(isset(self::$services[$alias])){
            $service = self::$services[$alias];
            $instance = new $service['service'](...$service['arguments']);

            if($service['shared'] || $service['serialize']){
                self::storeInstance($alias, $instance, $service['shared'], $service['serialize']);
            }

            return $instance;
        }

        return null;
    }
}