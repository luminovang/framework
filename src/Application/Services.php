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

class Services 
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
     * @var array<array,object> $instances
    */
    private static array $instances = [];

    /**
     * Dynamically create an instance of the specified service class.
     * Get shared instance or re-imitate stored instance with a new parameters 
     *
     * @param class-string|string $service The class name or class name alias of the service.
     * @param array $arguments Param arguments to instigate class with 
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     * 
     * @example Services::method('foo')
     * 
     * @return class-object|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
     * @ignore 
    */
    public static function __callStatic(string $service, array $arguments): ?object
    {
        return static::call($service, $arguments, $arguments);
    }

    /**
     * Dynamically create an instance of the specified service class.
     * Get shared instance or re-imitate stored instance with a new parameters 
     *
     * @param class-string|string $service The class name or class name alias of the service.
     * @param array $arguments Param arguments to instigate class with 
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     * 
     * @example Services::method('foo')
     * 
     * @return class-object|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
     * @ignore 
    */
    public function __call(string $service, array $arguments): ?object
    {
        return static::call($service, $arguments, $arguments);
    }

    /**
     * Check if service has a cached instance of class
     *
     * @param class-string|string $service The service class name or class name alias.
     * 
     * @return bool Return true if service class exists, false otherwise.
    */
    public static function has(string $service): bool
    {
        $alias = get_class_name($service);

        if(isset(static::$services[$alias]) || isset(static::$instances[$alias])){
            return true;
        }

        $path = path('services') . $alias . static::$suffix;
        return file_exists($path);
    }

    /**
     * Delete a service and it cached instances
     *
     * @param class-string|string $service The ervice class name or alias.
     * 
     * @return bool Return true if cached service was deleted, false otherwise.
    */
    public static function delete(string $service): bool
    {
        $alias = get_class_name($service);

        if(isset(static::$instances[$alias])){
            unset(static::$instances[$alias]);
        }

        if(isset(static::$services[$alias])){
            unset(static::$services[$alias]);
        }


        $path = path('services') . $alias . static::$suffix;
        return file_exists($path) ? unlink($path) : false;
    }

    /**
     * Clear all service and cached instance.
     * 
     * @return bool Return true if cached services was cleared, false otherwise.
    */
    public static function clear(): bool
    {
        $path = path('services');
        static::$instances = [];
        static::$services = [];

        return is_dir($path) ? FileManager::remove($path) : false;
    }

    /**
     * Get method instance 
     * 
     * @param string $alias The service class name alias.
     * 
     * @return class-object|null $instance Instance or null
    */
    public static function getInstance(string $alias): ?object
    {
        if(isset(static::$instances[$alias])){
            return static::$instances[$alias];
        }

        return static::getCachedInstance($alias);
    }

    /**
     * Add service instance.
     *
     * @param class-string|class-object $service Class name or instance of a class.
     * @example \Namespace\Utils\MyClass, MyClass::class or new MyClass()
     * @param arguments ...$arguments Arguments to initialize class with.
     *  -   The last 2 argument should be boolean values to indicate whether to shared instance or cached.
     * @param bool $shared Whether the instance should be shared (default: true).
     * @param bool $serialize Whether the instance should be serialized and (default: false).
     * 
     * @return class-object|bool Return object instance service class, false otherwise.
     * @throws RuntimeException If service already exist or unable to initiate class.
    */
    public static function add(string|object $service, mixed ...$arguments): object|bool
    {
        $alias = get_class_name($service);
   
        if (static::has($alias)) {
            throw new RuntimeException("Failed to add service, service with '$alias'. already exist remove service before adding it again");
        }

        try{
            $shared = static::isShared($arguments);
            $serialize = static::isSerialize($arguments);
            $shared = ($serialize && !$shared) ? true : $shared;

            if (empty($arguments)) {
                $instance = is_string($service) ? new $service() : $service;
            } else {
                $instance = (new ReflectionClass($service))->newInstance(...$arguments);
            }

            if($shared || $serialize){
                static::prepareInstance($alias, $instance, $shared, $serialize);
            }

            return $instance;
        } catch (ReflectionException | Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Reinstate instance with new contractor arguments
     *
     * @param class-string|string $service The class name or class name alias of the service.
     * @example \Namespace\Utils\MyClass, MyClass::class or MyClass
     * @param mixed ...$arguments Arguments to initialize class with
     * The last param argument should be boolean value to indicate whether shared cached or not
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     *      - defaults false
     * 
     * @return class-object Return updated class instance.
     * @throws RuntimeException If service does not exist or unable to initiate class.
     */
    public static function renew(string $service, mixed ...$arguments): object
    { 
        $alias = get_class_name($service);
        $instance = static::getInstance($alias);
            
        if($instance === null){
            throw new RuntimeException("Service not found '$service'. only existing service can be reinstated");
        }

        $shared = static::isShared($arguments);
        $serialize = static::isSerialize($arguments);
        $shared = ($serialize && !$shared) ? true : $shared;

        if (!empty($arguments)) {
            try{
                $reflection = new ReflectionClass($instance);
                $instance = $reflection->newInstance(...$arguments);
            } catch (ReflectionException|Throwable $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
        }

        static::prepareInstance($alias, $instance, $shared, $serialize);

        return $instance;
    }

    /**
     * Autoload application services.
     * 
     * @param array $services Queued services.
     * 
     * @return true Return true.
     * @internal
    */
    public static function queuService(array $services): true
    {
        static::$services = $services;

        return true;
    }

    /**
     * Call class instance.
     *
     * @param class-string|string $service The class name or class name alias of the service.
     * @param array $arguments Arguments to instigate class with.
     * @param array $clone Clone a copy of arguments to instigate class with.
     * 
     * @return class-object|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
    */
    private static function call(string $service, array $arguments, array $clone = []): ?object
    {
        $shared = static::isShared($arguments);
        $serialize = static::isSerialize($arguments);

        if (empty($arguments)) {
            $shared = ($serialize && !$shared) ? true : $shared;

            if($shared || $serialize){
                $instance = static::getInstance(get_class_name($service));
            }
           
            if($instance === null){
                throw new RuntimeException("Failed to instantiate service '$service'. Service not found ");
            }

            return $instance;
        }

        return static::renew($service, ...$clone);
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
        if (empty($arguments)) {
            return true;
        }

        $shared = true; 
        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        return $shared;
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
        if (empty($arguments)) {
            return false;
        }

        $serialize = false; 
        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $serialize = array_pop($arguments);
        }

        return $serialize;
    }

    /**
     * Prepare Instance to save on serialized object or shared instance 
     * 
     * @param string $name Service class name  alias.
     * @param class-object|null $instance object instance 
     * @param bool $shared Should share instance 
     * @param bool $serialize Should serialize instance 
     * 
     * @return void 
     * @throws Throwable
    */
    private static function prepareInstance(
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
            static::$instances[$alias] = $instance;
        }

        if($serialize){
            static::cacheInstance($alias, $instance);
        }
    }

    /**
     * Save shared service instance 
     * 
     * @param string $name Service class name
     * @param object $instance Instance of service class 
     * 
     * @return bool 
     * @throws RuntimeException
    */
    private static function cacheInstance(string $name, object $instance): bool 
    {
        $stringInstance = serialize($instance);
        $path = path('services');

        try {
            make_dir($path);
            $saved = write_content($path . $name . static::$suffix, $stringInstance);
           
            if ($saved === false) {
                return false;
            }

            return true;
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Get shared cached instance from stored file 
     *
     * @param string $name The class name alias.
     * 
     * @return class-object|null Return class object or null.
    */
    private static function getCachedInstance(string $alias): mixed
    {
        $path = path('services') . $alias . static::$suffix;

        if (file_exists($path)) {
            $content = file_get_contents($path);

            if ($content !== false) {
                return unserialize($content);
            }
        }

        if(isset(static::$services[$alias])){
            $service = static::$services[$alias];
            $instance = new $service['service'](...$service['arguments']);

            if($service['shared'] || $service['serialize']){
                static::prepareInstance($alias, $instance, $service['shared'], $service['serialize']);
            }

            return $instance;
        }

        return null;
    }
}