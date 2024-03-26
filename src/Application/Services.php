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

use \Luminova\Functions\Files;
use \RuntimeException;
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
     * Cached instances of service classes.
     *
     * @var array $instances
    */
    private static array $instances = [];

    /**
     * Dynamically create an instance of the specified service class.
     * Get shared instance or re-imitate stored instance with a new parameters 
     *
     * @param string $service The context or name of the service.
     * @param array $arguments Param arguments to instigate class with 
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     *      - defaults false
     * 
     * @example Services::method('foo')
     * 
     * @return object|null An instance of the service class, or null if not found.
     * @throws RuntimeException If failed to instantiate the service.
     * @ignore 
    */
    public static function __callStatic(string $service, $arguments): ?object
    {
        $cloneArgument = $arguments;
        $shared = static::isShared($arguments);
        $serialize = static::isSerialize($arguments);

        if (empty($arguments)) {
            $name = get_class_name($service);
            $shared = $serialize && !$shared ? true : $shared;

            if($shared || $serialize){
                $instance = static::getInstance($name);
            }
           
            if($instance === null){
                throw new RuntimeException("Failed to instantiate service '$name'. Service not found ");
            }

            return $instance;
        }

        $newInstance = static::newInstance($service, ...$cloneArgument);

        return $newInstance;;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public static function get(string $id): mixed 
    {
        $name = get_class_name($id);
        $instance = static::getInstance($name);
        
        if($instance === null){
            throw new RuntimeException("Failed to instantiate service '$id'. Service not found ");
        }

        return $instance;
    }

    /**
     * Check if service has a cached instance of class
     *
     * @param string $service The service context or name of the service.
     * 
     * @return bool 
    */
    public static function has(string $service): bool
    {
        $name = get_class_name($service);
        $path = path('services') . $name . static::$suffix;

        return file_exists($path) || isset(static::$instances[$name]);
    }

    /**
     * Is shared instance 
     * 
     * @param array $arguments
     * 
     * @return bool
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
     * @param array $arguments
     * 
     * @return bool
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
     * Delete a service and it cached instances
     *
     * @param string $service Your service public name 
     * 
     * @return bool
    */
    public static function delete(string $service): bool
    {
        $name = get_class_name($service);
        $path = path('services') . $name . static::$suffix;

        if(isset(static::$instances[$name])){
            unset(static::$instances[$name]);
        }

        return file_exists($path) ? unlink($path) : false;
    }

    /**
     * Clear all service and cached instance
     *
     * @param string $service Your service public name 
     * 
     * @return bool
    */
    public static function clear(): bool
    {
        $servicePath = path('services');

        static::$instances = [];

        return is_dir($servicePath) ? Files::remove($servicePath) : false;
    }

    /**
     * Get shared cached instance from stored file 
     *
     * @param string $name The context name
     * 
     * @return mixed 
    */
    private static function getCachedInstance(string $name): mixed
    {
        $path = path('services') . $name . static::$suffix;

        if (file_exists($path)) {
            $stringInstance = file_get_contents($path);

            if ($stringInstance !== false) {
                return unserialize($stringInstance);
            }
        }

        return null;
    }

    /**
     * Get method instance 
     * 
     * @param string $name
     * 
     * @return ?object $instance Instance or null
    */
    public static function getInstance(string $name): ?object
    {
        if(isset(static::$instances[$name])){
            $instance = static::$instances[$name];
        }else{
            $instance = static::getCachedInstance($name);
        }

        return $instance;
    }

    /**
     * Get shared instance 
     * 
     * @param string $name
     * 
     * @return ?object $instance Instance or null
    */
    public static function getSharedInstance(string $name): ?object
    {
        if(isset(static::$instances[$name])){
            return  static::$instances[$name];
        }

        return null;
    }

    /**
     * Add service instance.
     *
     * @param string|object $service Class name or instance of a class 
     * @example \Namespace\Utils\MyClass, MyClass::class or new MyClass()
     * @param arguments ...$arguments Arguments to initialize class with
     * The last param argument should be boolean value to indicate whether shared cached or not
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     *      - defaults false
     * 
     * @return object|false Return object instance if shared, false otherwise
     * @throws RuntimeException If service already exist or unable to initiate class 
    */
    public static function add(string|object $service, ...$arguments): object|false
    {
        return static::addInstance($service, false, ...$arguments);
    }

    /**
     * Reinstate instance with new contractor arguments
     *
     * @param string $service Service name or class namespace
     * @example \Namespace\Utils\MyClass, MyClass or MyClass::class
     * @param arguments ...$arguments Arguments to initialize class with
     * The last param argument should be boolean value to indicate whether shared cached or not
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     *      - defaults false
     * 
     * @return object Return updated class instance
     * @throws RuntimeException If service does not exist or unable to initiate class
     * @throws Throwable 
     */
    public static function newInstance(string $service, ...$arguments): object
    { 
        $name = get_class_name($service);
        $instance = static::has($name) ? static::getInstance($name) : null;
            
        if($instance === null){
            throw new RuntimeException("Service not found '$service'. only existing service can be reinstated");
        }

        $shared = static::isShared($arguments);
        $serialize = static::isSerialize($arguments);
        $shared = $serialize && !$shared ? true : $shared;

        if (!empty($arguments)) {
            try{
                $reflection = new ReflectionClass($instance);
                $instance = $reflection->newInstance(...$arguments);
            } catch (ReflectionException $e) {
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
            } catch (Throwable $e) {
                throw new RuntimeException($e->getMessage());
            }
        }

        static::prepareInstance($name, $instance, $shared, $serialize);

        return $instance;
    }

    /**
     * Update service instance
     *
     * @param string|object $service Class name or instance of a class 
     * @example \Namespace\Utils\MyClass, MyClass::class or new MyClass()
     * @param bool $initializing Don't throw exception if service already exist
     * @param arguments ...$arguments Arguments to initialize class with
     * The last param argument should be boolean value to indicate whether shared cached or not
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param bool $serialize Whether the instance should be serialized and (cached) or not.
     *      - defaults false
     * 
     * @return object|bool Return object instance if shared, false otherwise
     * @throws RuntimeException If service already exist or unable to initiate class 
    */
    public static function addInstance(string|object $service, bool $initializing = false, ...$arguments): object|bool
    {
        $name = get_class_name($service);
   
        if (static::has($name)) {
            if($initializing){
                return true;
            }
            
            throw new RuntimeException("Failed to add service, service with '$name'. already exist remove service before adding it again");

            return false;
        }

        try{
            $shared = static::isShared($arguments);
            $serialize = static::isSerialize($arguments);
            $shared = $serialize && !$shared ? true : $shared;

            if (empty($arguments)) {
                $instance = is_string($service) ? new $service() : $service;
            } else {
                $reflection = new ReflectionClass($service);
                $instance = $reflection->newInstance(...$arguments);
                //$instance = $reflection->newInstanceArgs(...$arguments);
            }

            static::prepareInstance($name, $instance, $shared, $serialize);

            return $instance;
        } catch (Throwable $e) {
            //"Failed to instantiate service '$name'. Error: " .
            throw new RuntimeException($e->getMessage());
        } catch (ReflectionException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return false;
    }

    /**
     * Prepare Instance to save on serialized object or shared instance 
     * 
     * @param string $name service name 
     * @param object $instance object instance 
     * @param bool $shared Should share instance 
     * @param bool $serialize Should serialize instance 
     * 
     * @return void 
     * @throws Throwable
    */
    private static function prepareInstance(
        string $name, 
        object $instance, 
        bool $shared = true, 
        bool $serialize = false
    ): void 
    {
        if($shared){
            static::$instances[$name] = $instance;
        }

        if($serialize){
            static::cacheInstance($name, $instance);
        }
    }

    /**
     * Save shared service instance 
     * 
     * @param string $name Service class name
     * @param object $instance Instance of service class 
     * 
     * @return bool 
     * @throws Throwable
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
            throw $e; 
        }

        return false;
    }
}