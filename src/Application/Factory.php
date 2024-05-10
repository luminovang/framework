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
use \Luminova\Application\Functions;
use \Luminova\Sessions\Session;
use \Luminova\Library\Modules;
use \Luminova\Languages\Translator;
use \Luminova\Application\FileSystem;
use \Luminova\Http\Request;
use \App\Controllers\Config\Services;
use \Luminova\Template\ViewResponse;
use \Luminova\Logger\Logger;
use \Luminova\Security\InputValidator;
use \Luminova\Exceptions\RuntimeException;
use \Exception;
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
 * @method static Modules             import(...$params, bool $shared = true)               @return Modules
 * @method static Translator          language($locale, bool $shared = true)                @return Translator
 * @method static Logger              logger(string $extension = '.log', $shared = true)    @return Logger
 * @method static FileSystem          files($shared = true)                                 @return FileSystem
 * @method static InputValidator      validate($shared = true)                              @return InputValidator
 * @method static Services            services($shared = true)                              @return Services
 * @method static ViewResponse        response(int $status, $shared = true)                 @return ViewResponse
 * @method static Request             request($shared = true)                               @return Request
*/

class Factory 
{
    /**
     * Cached instances of factories classes.
     *
     * @var array<string,object> $instances
     */
    private static array $instances = [];

    /**
     * Get the fully qualified class name of the factory based on the provided context.
     *
     * @param string $aliases The class name aliases
     * 
     * @return string|null The fully qualified class name
    */
    private static function locator(string $aliases): ?string
    {
        $aliases = strtolower($aliases);
        $classes = [
            'task' => Task::class,
            'session' => Session::class,
            'functions' => Functions::class,
            'modules' => Modules::class,
            'language' => Translator::class,
            'logger' => Logger::class,
            'files' => FileSystem::class,
            'validate' => InputValidator::class,
            'response' => ViewResponse::class,
            'services' => Services::class,
            'request' => Request::class
        ];

        return $classes[$aliases] ?? null;
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $aliases The class name aliases
     * @param array ...$arguments Parameters to pass to the factory constructor.
     *      - The Last parameter to pass to the factory constructor indicate if it should return a shared instance
     * 
     * @example Factory::method('foo', 'bar', false)
     * @example Factory::method(false)
     * 
     * @return object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     * 
     * @ignore 
     */
    public static function __callStatic(string $aliases, $arguments): ?object
    {
        $shared = true; 
        $class = static::locator($aliases);

        if ($class === null) {
            throw new RuntimeException("Factory with method name '$aliases' does not exist.");
        }

        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        return static::create($class, $shared, ...$arguments);
    }

    /**
     * Finds and call an entry of the container by its identifier.
     *
     * @param string $aliases Identifier of the entry to look for.
     *
     * @throws RuntimeException No method was found for identifier.
     * @throws RuntimeException Error while instantiating the method class.
     *
     * @return mixed Instance of called method.
    */
    public static function call(string $aliases): mixed 
    {
        $class = static::locator($aliases);

        if ($class === null) {
            throw new RuntimeException("Factory with method name '$aliases' does not exist.");
        }

        return static::create($class);
    }

    /**
     * Create an instance of the specified factory class.
     *
     * @param string $class The class class
     * @param bool $shared Whether the instance should be shared or not.
     * @param mixed ...$arguments Parameters to pass to the factory constructor.
     * 
     * @return object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function create(string $class, bool $shared = true, ...$arguments): ?object
    {
        $aliases = strtolower($class);

        if ($shared && isset(static::$instances[$aliases])) {
            return static::$instances[$aliases];
        }
  
        $instance = null;

        try {
            $instance = new $class(...$arguments);

            if ($shared) {
                static::$instances[$aliases] = $instance;
            }
        } catch (Throwable | Exception $e) {
            throw new RuntimeException("Failed to instantiate factory method '$class', " . $e->getMessage(), $e->getCode(), $e);
        }

        return $instance;
    }

    /**
     * Check if class is available in factories
     *
     * @param string $aliases The context or class name.
     * 
     * @return bool 
     */
    public static function has(string $aliases): bool
    {
        $aliases = get_class_name($aliases);

        return static::locator($aliases) !== null;
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
     * Initialize and Register queued services.
     * 
     * @return void 
     * @ignore
    */
    public static function register(): void 
    {
        $instance = static::create(Services::class);

        $instance->bootstrap();
        $queues = $instance->getServices();
     
        foreach($queues as $name => $service){
            try{
                $added = $instance->addInstance($service['service'], true, ...$service['arguments']);
                if($added === false){
                    logger('critical', 'Unable to register service "' . $name . '"');
                }
            }catch(RuntimeException $e){
                logger('critical', 'Error occurred while registering service "' . $name . '". Exception: ' . $e->getMessage());
            }
        }
    }

    /**
     * Call all public methods within a given class.
     * 
     * @param string|object $classInstance class name or instance of a class.
     * @param bool $return return type.
     * 
     * @return int|array<string, mixed> 
     * @throws RuntimeException If failed to instantiate class.
    */
    public static function callAll(string|object $classInstance, bool $return = false): int|array
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
        }catch(RuntimeException | ReflectionException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }
}