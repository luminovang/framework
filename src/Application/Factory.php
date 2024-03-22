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

use \Luminova\Time\Task;
use \Luminova\Functions\Functions;
use \Luminova\Sessions\Session;
use \Luminova\Library\Importer;
use \Luminova\Languages\Translator;
use \Luminova\Application\Paths;
use \App\Controllers\Config\Services;
use \Luminova\Template\ViewResponse;
use \Luminova\Logger\NovaLogger;
use \Luminova\Security\InputValidator;
use \Luminova\Exceptions\RuntimeException;
use \Throwable;
use \ReflectionClass;
use \ReflectionException;
use \ReflectionMethod;

/**
 * Factory methods classes.
 *
 * @method static Functions           functions(bool $shared = true)                        @return Functions
 * @method static Session             session(...$params, bool $shared = true)              @return Session
 * @method static Task                task(...$params, bool $shared = true)                 @return Task
 * @method static Importer            import(...$params, bool $shared = true)               @return Importer
 * @method static Translator          language($locale, bool $shared = true)                @return Translator
 * @method static NovaLogger          logger(string $extension = '.log', $shared = true)    @return NovaLogger
 * @method static Paths               paths($shared = true)                                 @return Paths
 * @method static InputValidator      validate($shared = true)                              @return InputValidator
 * @method static Services            services($shared = true)                              @return Services
 * @method static ViewResponse        response(int $status, $shared = true)                 @return ViewResponse
 */

class Factory 
{
    /**
     * Cached instances of factories classes.
     *
     * @var array
     */
    private static $instances = [];

    /**
     * Cached instances of factories method classes.
     *
     * @var array $factories
     */
    private static $factories = [];

    /**
     * Get the fully qualified class name of the factory based on the provided context.
     *
     * @param string $aliasis The class name aliasis
     * 
     * @return string|null The fully qualified class name
    */
    private static function get(string $aliasis): ?string
    {
        $classes = [
            'task' => Task::class,
            'session' => Session::class,
            'functions' => Functions::class,
            'import' => Importer::class,
            'language' => Translator::class,
            'logger' => NovaLogger::class,
            'paths' => Paths::class,
            'validate' => InputValidator::class,
            'response' => ViewResponse::class,
        ];

        $aliasis = strtolower($aliasis);

        if (isset($classes[$aliasis])) {
            return $classes[$aliasis];
        }

        return static::$factories[$aliasis] ?? null;
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $aliasis The class contect aliasis
     * @param array $arguments Parameters to pass to the factory constructor.
     * @param bool $shared The Last parameter to pass to the factory constructor 
     * indicate if it should return a shared instance
     * 
     * @example Factory::method('foo', 'bar', false)
     * @example Factory::method(false)
     * 
     * @return object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    public static function __callStatic(string $aliasis, $arguments): ?object
    {
        $shared = true; 
        $class = static::get($aliasis);

        if ($class === null) {
            throw new RuntimeException("Factory with method name '$aliasis' does not exist.");
        }

        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        return static::call($class, $shared, ...$arguments);
    }

    /**
     * Create an instance of the specified factory class.
     *
     * @param string $class The class class
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param mixed ...$arguments Parameters to pass to the factory constructor.
     * 
     * @return object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function call(string $class, bool $shared = true, ...$arguments): ?object
    {
        $aliasis = strtolower($class);

        if ($shared && isset(static::$instances[$aliasis])) {
            return static::$instances[$aliasis];
        }
  
        $instance = null;

        try {
            $reflection = new ReflectionClass($class);
            $instance = $reflection->newInstance(...$arguments);

            if ($shared) {
                static::$instances[$aliasis] = $instance;
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to instantiate factory method '$class'. Error: " . $e->getMessage());
        }catch(ReflectionException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $instance;
    }

    /**
     * Check if class is available in factories
     *
     * @param string $aliasis The context or name of the factory.
     * 
     * @return bool 
     */
    public static function has(string $aliasis): bool
    {
        $aliasis = get_class_name($aliasis);

        return static::get($aliasis) !== null;
    }

    /**
     * Delete a factory and it cached instances
     *
     * @param string $factory Your factory public name 
     * 
     * @return bool
     */
    public static function delete(string $factory): bool
    {
        $factory = strtolower(get_class_name($factory));
        $count = 0;

        if (isset(static::$factories[$factory])) {
            unset(static::$factories[$factory]);
            $count++;
        }

        if (isset(static::$instances[$factory])) {
            unset(static::$instances[$factory]);
            $count++;
        }

        return $count > 0;
    }

    /**
     * Clear all cached instances of factory classes.
     *
     * @return bool
     */
    public static function clear(): bool
    {
        static::$instances = [];

        return static::$instances === [];
    }

    /**
     * Get all classes that extends a base class 
     * 
     * @param string $baseClass The base class to check 
     * 
     * @return array 
    */
    public static function extenders(string $baseClass): array 
    {
        $subClasses = [];
        $allClasses = get_declared_classes();

        foreach ($allClasses as $name) {
            if (is_subclass_of($name, $baseClass)) {
                $subClasses[] = $name;
            }
        }

        return $subClasses;
    }

     /**
     * Get services instance
     * 
     * @return Services 
    */
    public static function services(bool $shared = true): Services 
    {
        if($shared && isset(static::$instances['services'])){
            return static::$instances['services'];
        }

        $instance = new Services();

        if ($shared) {
            static::$instances['services'] = $instance;
        }

        return $instance;
    }

     /**
     * initialize and Register queued services 
     * 
     * @return void 
    */
    public static function initializeServices(): void 
    {
        $newService = static::services();

        $newService->bootstrap();
        $queues = $newService->getServices();
     
        foreach($queues as $name => $service){
            try{
                $added = $newService->addInstance($service['service'], true, ...$service['arguments']);
                if($added === false){
                    logger('critical', 'Unable to register service "' . $name . '"');
                }
            }catch(RuntimeException $e){
                logger('critical', 'Error occurred while registering service "' . $name . '". Exception: ' . $e->getMessage());
            }
        }
    }

    /**
     * Call all public methods within a class 
     * 
     * @param string|object $classInstance
     * @param bool $return return method output
     * 
     * @return array|int 
    */
    public static function callAll(string|object $classInstance, bool $return = false): array|int
    {
        try{
            $reflectionClass = new ReflectionClass($classInstance);
            $methods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        
            $calls = [];
            $count = 0;

            foreach ($methods as $method) {
                if ($method->class === get_class($classInstance)) {
                    $name = $method->name;
                    if($return){
                        $calls[$name] = $classInstance->$name();
                    }else{
                        $classInstance->$name();
                        $count++;
                    }
                }
            }

            if($return){
                return $calls;
            }

            return $count;
        }catch(RuntimeException $e){
            throw new RuntimeException($e->getMessage());
        }

        return 0;
    }
}