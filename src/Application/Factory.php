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

use \Luminova\Config\Configuration;
use \Luminova\Time\Task;
use \Luminova\Functions\Functions;
use \Luminova\Sessions\Session;
use \Luminova\Library\Importer;
use \Luminova\Languages\Translator;
use \Luminova\Application\Paths;
use \Luminova\Logger\NovaLogger;
use \Luminova\Security\InputValidator;
use \Luminova\Exceptions\RuntimeException;
use \Throwable;

/**
 * Factory methods classes.
 *
 * @method static Functions           functions(bool $shared = true)                        @return Functions
 * @method static Configuration       config(...$params, bool $shared = true)               @return Configuration
 * @method static Session             session(...$params, bool $shared = true)              @return Session
 * @method static Task                task(...$params, bool $shared = true)                 @return Task
 * @method static Importer            import(...$params, bool $shared = true)               @return Importer
 * @method static Translator          language($locale, bool $shared = true)                @return Translator
 * @method static NovaLogger          logger(string $extension = '.log', $shared = true)    @return NovaLogger
 * @method static Paths               paths($shared = true)                                 @return Paths
 * @method static InputValidator      validate($shared = true)                              @return InputValidator
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
     * @param string $context The context or name of the factory.
     * 
     * @return string|null The fully qualified class name of the factory, or null if not found.
     */
    private static function get(string $context): ?string
    {
        $context = strtolower($context);

        return match($context) {
            'task' => Task::class,
            'config' => Configuration::class,
            'session' => Session::class,
            'functions' => Functions::class,
            'import' => Importer::class,
            'language' => Translator::class,
            'logger' => NovaLogger::class,
            'paths' => Paths::class,
            'validate' => InputValidator::class,
            default => static::$factories[$context] ?? null
        };
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $context The context or name of the factory.
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
    public static function __callStatic(string $context, $arguments): ?object
    {
        $shared = true; 

        if (static::get($context) === null) {
            throw new RuntimeException("Factory with method name '$context' not found.");
        }

        // If the last argument is provided and it's a boolean, use it as the shared flag
        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        return static::create($context, $shared, ...$arguments);
    }

    /**
     * Create an instance of the specified factory class.
     *
     * @param string $context The context or name of the factory.
     * @param bool $shared Whether the instance should be shared (cached) or not.
     * @param mixed ...$params Parameters to pass to the factory constructor.
     * 
     * @return object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    public static function create(string $context, bool $shared = true, ...$params): ?object
    {
        $className = static::get($context);
        $instance = null;

        if ($className === null) {
            return null;
        }

        if ($shared && isset(static::$instances[$className])) {
            return static::$instances[$className];
        }
  
        try {
            $instance = new $className(...$params);

            if ($shared) {
                static::$instances[$className] = $instance;
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to instantiate factory method '$context'. Error: " . $e->getMessage());
        }

        return $instance;
    }

    /**
     * Check if class is available in factories
     *
     * @param string $context The context or name of the factory.
     * 
     * @return bool 
     */
    private static function has(string $context): bool
    {
        $context = static::get($context);

        return $context !== null;
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
        $factory = strtolower($factory);
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
     * Clear cached instances of factory classes.
     *
     * @param string $className Class name to add to factory
     * @param string $name Public identifier name to load the factory
     *          If name is null or empty we use the class name as identifier
     *          Name will be converted to lowercase 
     * 
     * @return bool
     * @throws RuntimeException
     */
    public static function add(string $className, ?string $name = null): bool
    {
        if ($name === null || $name === '') {
            $name = substr($className, strrpos($className, '\\') + 1);
        }

        $name = strtolower($name);

        if (static::has($name)) {
            throw new RuntimeException("Failed to add method to factory, a factory method with '$name'. already exist");
        }

        static::$factories[$name] = $className;

        return isset(static::$factories[$name]);
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
}