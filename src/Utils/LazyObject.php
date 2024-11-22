<?php
/**
 * Luminova Framework foundation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\RuntimeException;
use \Closure;
use \ReflectionClass;
use \Throwable;

class LazyObject implements LazyInterface
{
    /**
     * The lazily instantiated object instance.
     * 
     * @var \class-object<\T>|null $instance
     */
    private ?object $instance = null;

    /**
     * The constructor logic to create the actual object instance.
     * 
     * @var Closure|object|null $initializer
     */
    private mixed $initializer = null;

    /**
     * Indicate weather lazy-ghost is supported.
     * 
     * @var bool|null $isLazySupported
     */
    private static ?bool $isLazySupported = null;

    /**
     * Initializes a new instance of the LazyObject.
     * 
     * This method checks the PHP version and uses `ReflectionClass::newLazyGhost` for PHP 8.4.0
     * and later versions to lazily initialize an object. For earlier versions, it falls back to
     * a custom lazy initialization.
     * 
     * @param Closure|class-string<\T> $callback A class string or closure that creates the lazily initialized object.
     * @param mixed ...$arguments Optional arguments to pass to the class constructor.
     * @throws RuntimeException If the class does not exist or error occurs.
     * 
     * @example - Custom Closure Initialization.
     * 
     * ```php
     * $person = new LazyObject(fn() => new Person(33));
     * echo $person->getAge();
     * ```
     */
    public function __construct(Closure|string $initializer,  mixed ...$arguments) 
    {
        if($initializer instanceof Closure){
            $this->initializer = $initializer;
            return;
        }

        self::$isLazySupported ??= version_compare(PHP_VERSION, '8.4.0', '>=');

        if (self::$isLazySupported) {
            $this->instance = self::newLazyGhost($initializer, ...$arguments);
            return;
        }

        try {
            $this->initializer = fn() => new $initializer(...$arguments);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Failed to initialize the lazy object. Error: %s', $e->getMessage()), 
                $e->getCode(), 
                $e
            );
        }
    }

    /**
     * Creates a lazy-loaded instance of a class.
     * 
     * This method checks the PHP version and uses `ReflectionClass::newLazyGhost` for PHP 8.4.0
     * and later versions to lazily initialize an object for early return. For earlier versions, it falls back to
     * a custom lazy initialization.
     *
     * @param Closure|\class-string<\T> $initializer A class string or closure that creates the lazily initialized object.
     * @param mixed ...$arguments Optional arguments to pass to the class constructor.
     * 
     * @return class-object<\T>|LazyInterface<\T> Return lazy-loaded instance of the specified class.
     * @throws RuntimeException If the class does not exist or error occurs.
     * 
     * @example Class Name Initialization:
     * 
     * ```php
     * $person = LazyObject::newObject(Person::class, 33);
     * echo $person->getName();
     * ```
     */
    public static function newObject(
        Closure|string $initializer, 
        mixed ...$arguments
    ): object
    {
        self::$isLazySupported ??= version_compare(PHP_VERSION, '8.4.0', '>=');

        if (self::$isLazySupported && !($initializer instanceof Closure)) {
            return self::newLazyGhost($initializer, ...$arguments);
        }
    
        return new self(fn() => new $initializer(...$arguments));
    }

    /**
     * Creates a new lazy ghost object for the specified class.
     *
     * This method uses PHP 8.4+'s ReflectionClass::newLazyGhost to create a lazy-loaded instance
     * of the specified class. It defers the actual instantiation until the object is first used.
     *
     * @param class-string<\T> $class The fully qualified class name to create a lazy ghost for.
     * @param mixed ...$arguments Optional arguments to pass to the class constructor.
     *
     * @return object Return a lazy ghost object of the specified class.
     * @throws RuntimeException If the lazy ghost creation fails for any reason.
     * 
     * @example Only Class Name Initialization:
     * 
     * ```php
     * $person = LazyObject::newObject(Person::class, 33);
     * echo $person->getName();
     * ```
     */
    public static function newLazyGhost(
        string $class, 
        mixed ...$arguments
    ): object 
    {
        try{
            return (new ReflectionClass($class))->newLazyGhost(
                fn(object $object) => $object->__construct(...$arguments)
            );
        }catch(Throwable $e){
            throw new RuntimeException(
                sprintf('Failed to initialize the lazy ghost object. Error: %s', $e->getMessage()), 
                $e->getCode(), 
                $e
            );
        }
    }

    /**
     * Creates a lazy-loaded instance of a class fallback.
     * 
     * @return class-object<\T>|LazyInterface<\T>|null Return lazy-loaded instance of the specified class or null.
     * @throws RuntimeException If the class does not exist or error occurs.
     */
    private function getInstance(): ?object
    {
        if ($this->instance === null) {
            try{
                $this->instance = ($this->initializer)();
            }catch(Throwable $e){
                if(PRODUCTION){
                    logger('critical', sprintf('Failed to initialize object: %s', $e->getMessage()));
                    return null;
                }

                throw new RuntimeException(
                    sprintf('Failed to initialize the lazy object. Error: %s', $e->getMessage()), 
                    $e->getCode(), 
                    $e
                );
            }

            if (!is_object($this->instance)) {
                throw new RuntimeException(
                    sprintf(
                        'Invalid closure implementation: expected an object, but %s was returned.',
                        gettype($this->instance)
                    )
                );
            }
        }

        return $this->instance;
    }

    /**
     * Magic method for accessing object properties.
     * 
     * @param string $property The name of the property.
     * 
     * @return mixed Return the value of the property.
     */
    public function __get(string $property): mixed
    {
        return $this->getInstance()->{$property} ?? null;
    }

    /**
     * Magic method for setting object properties.
     * 
     * @param string $property The name of the property.
     * @param mixed $value The value to set the property to.
     * 
     * @return void
     */
    public function __set(string $property, mixed $value): void
    {
        $this->getInstance()->{$property} = $value;
    }

    /**
     * Magic method for calling object methods.
     * 
     * @param string $method The name of the method.
     * @param array $arguments Optional arguments to pass to the method.
     * 
     * @return mixed Return the result of the method call.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->getInstance()->{$method}(...$arguments);
    }

    /**
     * Magic method for calling object methods.
     * 
     * @param string $method The name of the method.
     * 
     * @return mixed Return the result of the method call.
     */
    public function __isset(string $property): bool
    {
        return property_exists($this->getInstance(), $property);
    }
}