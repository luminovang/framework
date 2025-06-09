<?php
/**
 * Luminova Framework Lazy Object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utils;

use \Closure;
use \Throwable;
use \Stringable;
use \ReflectionClass;
use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\LogicException;
use \Luminova\Exceptions\RuntimeException;

class LazyObject implements LazyInterface, Stringable
{
    /**
     * The lazily instantiated object instance.
     * 
     * @var class-object<\T>|null $lazyInstance
     */
    private ?object $lazyInstance = null;

    /**
     * The constructor logic to create the actual object instance.
     * 
     * @var Closure|object|null $lazyInitializer
     */
    private mixed $lazyInitializer = null;

    /**
     * The constructor arguments.
     * 
     * @var callable|null $lazyArguments
     */
    private mixed $lazyArguments = null;

    /**
     * Indicate Whether lazy-ghost is supported.
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
     * @param callable|null $arguments Optional arguments to pass to the class constructor or closure initializer argument.
     *              Must be a callable that returns a list array of arguments to pass to the constructor.
     * 
     * @return LazyInterface<\T> Return instance of lazy object that wraps the given class object.
     * @throws RuntimeException If the class does not exist or error occurs.
     * 
     * @example - Custom Closure Initialization.
     * 
     * ```php
     * $person = new LazyObject(fn(): Person => new Person(33));
     * echo $person->getAge();
     * ```
     */
    public function __construct(private Closure|string $initializer, ?callable $arguments = null) 
    {
        $this->lazyArguments = $arguments;

        if($this->initializer instanceof Closure){
            $this->lazyInitializer = $this->initializer;
            return;
        }

        self::$isLazySupported = (self::$isLazySupported === null) 
            ? version_compare(PHP_VERSION, '8.4.0', '>=')
            : self::$isLazySupported;

        if (self::$isLazySupported) {
            $this->lazyInstance = self::newLazyGhost($this->initializer, $arguments);
            return;
        }

        $this->lazyInitializer = fn(mixed ...$args) => new ($this->initializer)(...$args);
    }

    /**
     * Creates a lazy-loaded instance of a class.
     * 
     * This method checks the PHP version and uses `ReflectionClass::newLazyGhost` for PHP 8.4.0
     * and later versions to lazily initialize an object for early return. For earlier versions, it falls back to
     * a custom lazy initialization.
     *
     * @param Closure|\class-string<\T> $initializer A class string or closure that creates the lazily initialized object.
     * @param callable|null $arguments Optional arguments to pass to the class constructor or closure initializer argument.
     *               Must be a callable that returns a list array of arguments to pass to the constructor.
     * 
     * @return class-object<\T>|LazyInterface<\T> Return lazy-loaded instance that wraps the given class object.
     * @throws RuntimeException If the class does not exist or error occurs.
     * 
     * @example - Class Name Initialization:
     * 
     * ```php
     * $person = LazyObject::newObject(Person::class, 33);
     * echo $person->getName();
     * ```
     */
    public static function newObject(Closure|string $initializer, ?callable $arguments = null): object
    {
        self::$isLazySupported = (self::$isLazySupported === null) 
            ? version_compare(PHP_VERSION, '8.4.0', '>=')
            : self::$isLazySupported;

        return (self::$isLazySupported && !($initializer instanceof Closure))
            ? self::newLazyGhost($initializer, $arguments)
            : new self($initializer, $arguments);
    }

    /**
     * Creates a new lazy ghost object for the specified class.
     *
     * This method uses PHP 8.4+'s ReflectionClass::newLazyGhost to create a lazy-loaded instance
     * of the specified class. It defers the actual instantiation until the object is first used.
     *
     * @param class-string<\T> $class The fully qualified class name to create a lazy ghost for.
     * @param callable|null $arguments Optional arguments to pass to the class constructor.
     *               Must be a callable that returns a list array of arguments to pass to the constructor.
     *
     * @return object Return a lazy ghost object of the specified class.
     * @throws RuntimeException If the lazy ghost creation fails for any reason.
     * 
     * @example - Only Class Name Initialization:
     * 
     * ```php
     * $person = LazyObject::newLazyGhost(Person::class, fn(): array => [33, 'Peter', 'Nigeria']);
     * echo $person->getName();
     * ```
     */
    public static function newLazyGhost(string $class, ?callable $arguments = null): object
    {
        try {
            return (new ReflectionClass($class))->newLazyGhost(fn (object $object) => $object->__construct(
                ...($arguments && is_callable($arguments)) ? $arguments() : []
            ));
        } catch (Throwable $e) {
            if($e instanceof AppException){
                throw $e;
            }

            throw new RuntimeException(
                sprintf('Failed to initialize object. %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Creates a new instance with arguments return the class object.
     * 
     * @param mixed ...$arguments The arguments to create a new class object with.
     * 
     * @return class-object<\T>|LazyInterface<\T> Return a new instance of the lazy loaded class or null.
     * @throws RuntimeException If error occurs while initializing class.
     * 
     * > **Note:** This doesn't override the parent lazy loaded class object.
     * 
     * @example - Creating a new instance with arguments:
     * 
     * ```php 
     * $parent = LazyObject::newObject(Person::class, fn(): array => [33]);
     * echo $parent->getAge() // Outputs: 33
     * 
     * $child = $parent->newLazyInstance(34);
     * echo $child->getAge() // Outputs: 34
     * ```
     * 
     * @example - Creating a new instance with arguments from a callable initializer:
     * 
     * ```php 
     * $parent = LazyObject::newObject(
     *      fn(mixed ...$arguments): Person => new Person(...$arguments), 
     *      fn(): array => [33]
     * );
     * 
     * echo $parent->getAge() // Outputs: 33
     * 
     * $child = $parent->newLazyInstance(34);
     * echo $child->getAge() // Outputs: 34
     * ```
     */
    public function newLazyInstance(mixed ...$arguments): object
    {
        if(
            $this->lazyInstance !== null && 
            empty($arguments) && 
            method_exists($this->lazyInstance, '__clone')
        ){
            return clone $this->lazyInstance;
        }

        try {
            if (self::$isLazySupported) {
                return self::newLazyGhost(
                    $this->initializer, 
                    fn() => $arguments
                );
            }

            if($this->initializer instanceof Closure){
                return ($this->initializer)(...$arguments);
            }

            return new ($this->initializer)(...$arguments);
        } catch (Throwable $e) {
            if($e instanceof AppException){
                throw $e;
            }

            throw new RuntimeException(
                sprintf('Failed to initialize object. %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

     /**
     * Creates and return the lazy-loaded class object.
     * 
     * @return class-object<\T>|LazyInterface<\T>|null Return lazy-loaded instance of the specified class or null.
     * @throws RuntimeException If the class does not exist or error occurs.
     */
    public function getLazyInstance(): ?object
    {
        $this->newLazyClassObject();
        return $this->lazyInstance;
    }

    /**
     * Override the current lazy-loaded instance with a new class object.
     * 
     * @param class-object<\T> $instance The new class object to replace the existing lazy-loaded instance.
     * 
     * @return void
     */
    public function setLazyInstance(object $instance): void
    {
        $this->lazyInstance = $instance;
    }

    /**
     * Checks if the lazy-loaded instance is of a specific class type.
     *
     * This method verifies whether the lazily instantiated object is an instance
     * of the specified class or implements the specified interface.
     *
     * @param class-string<\T> $class The fully qualified class or interface name to check against.
     *
     * @return bool Returns true if the lazy-loaded instance is of the specified class type, false otherwise.
     * @example - Check if the lazy-loaded instance Example:
     * 
     * ```php
     * $object->isInstanceof(Example::class);
     * ```
     */
    public function isInstanceof(string $class): bool 
    {
        return ($this->getLazyInstance() instanceof $class);
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
        return $this->getLazyInstance()->{$property} ?? null;
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
        $this->getLazyInstance()->{$property} = $value;
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
        return $this->getLazyInstance()->{$method}(...$arguments);
    }

    /**
     * Clones the lazy-loaded instance.
     *
     * This method is called when attempting to clone the object. It ensures that the lazy-loaded instance
     * is properly cloned, if necessary, and handles any specific cloning behavior for the lazy-loaded object.
     * 
     * @return void
     * @throws RuntimeException If the lazy-loaded instance cannot be cloned or if cloning is not supported.
     */
    public function __clone(): void
    {
        $this->newLazyClassObject();
        $this->assertImplement('__clone');

        $this->lazyInstance = clone $this->lazyInstance;
    }

    /**
     * Magic method for checking if object property exist.
     * 
     * @param string $method The name of the method.
     * 
     * @return mixed Return the result of the method call.
     */
    public function __isset(string $property): bool
    {
        return isset($this->getLazyInstance()->{$property});
    }

    /**
     * Handles the unset operation for properties of the lazy-loaded instance.
     *
     * This method is triggered when attempting to unset a property of the lazy-loaded object.
     * It delegates the unset operation to the underlying object.
     * 
     * @param string $property The name of the property to unset.
     * @return void
     */
    public function __unset(string $property): void
    {
        unset($this->getLazyInstance()->{$property});
    }

    /**
     * Converts the lazy-loaded instance to a string.
     *
     * This method is called when attempting to convert the lazy-loaded object to a string.
     * It ensures that the lazy class instance is initialized and implements the __toString() method,
     * then returns the string representation of the instance.
     * 
     * @return string Return the string representation of the lazy-loaded instance.
     * @throws RuntimeException If the __toString() method is not implemented in the lazy-loaded instance.
     */
    public function __toString(): string
    {
        $this->newLazyClassObject();
        $this->assertImplement('__toString');

        return (string) $this->lazyInstance;
    }

    /**
     * Serializes the lazy-loaded instance.
     *
     * This method is called when serializing the lazy-loaded object. It ensures that the instance is initialized
     * and implements the __serialize() method before returning the serialized data.
     * 
     * @return array Return an array of serialized data of the lazy-loaded instance.
     * @throws RuntimeException If the __serialize() method is not implemented in the lazy-loaded instance.
     */
    public function __serialize(): array
    {
        $this->newLazyClassObject();
        $this->assertImplement('__serialize');

        return ['data' => $this->lazyInstance->__serialize()];
    }

    /**
     * Unserialize the lazy-loaded instance.
     *
     * This method is called when deserializing the lazy-loaded object. It ensures that the instance is initialized
     * and implements the __unserialize() method before applying the unserialization data.
     * 
     * @param array $data The data to unserialize.
     * @return void
     * @throws RuntimeException If the __unserialize() method is not implemented in the lazy-loaded instance.
     */
    public function __unserialize(array $data): void
    {
        $this->newLazyClassObject();
        $this->assertImplement('__unserialize');

        $this->lazyInstance->__unserialize($data['data'] ?? $data);
    }

    /**
     * Returns debug information for the lazy-loaded instance.
     *
     * This method is called when using var_dump() or similar debugging tools. It ensures that the lazy-loaded
     * instance is initialized and attempts to retrieve the debug info using the __debugInfo() method, if it exists.
     * If not, it falls back to a default debug info format.
     * 
     * @return array Return an array of debug information of the lazy-loaded instance.
     */
    public function __debugInfo(): array
    {
        $this->newLazyClassObject();

        return method_exists($this->lazyInstance, '__debugInfo')
            ? $this->lazyInstance->__debugInfo()
            : $this->getLazyDebugInfo();
    }

    /**
     * Destructor for the lazy-loaded instance.
     *
     * This method is called when the object is destroyed. If the lazy-loaded instance exists and has a destructor,
     * it will be invoked before destroying the object.
     * 
     * @return void
     */
    public function __destruct()
    {
        if ($this->lazyInstance !== null && method_exists($this->lazyInstance, '__destruct')) {
            $this->lazyInstance->__destruct();
        }
    }

    /**
     * Returns debug information for the lazy-loaded instance.
     * 
     * @return array Return an array of debug information of the lazy-loaded instance.
     */
    private function getLazyDebugInfo(): array
    {
        ob_start();
        var_dump($this->lazyInstance);
        return [ob_get_clean()];
    }

    /**
     * Creates a lazy-loaded instance of a class fallback.
     * 
     * @throws RuntimeException If the class does not exist or error occurs.
     */
    private function newLazyClassObject(): void
    {
        if ($this->lazyInstance !== null) {
            return;
        }

        try {
            $this->lazyInstance = ($this->lazyInitializer)(...($this->lazyArguments ? ($this->lazyArguments)() : []));
        } catch (Throwable $e) {
            if($e instanceof AppException){
                throw $e;
            }
            
            throw new RuntimeException(
                sprintf('Failed to initialize object. %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        if (!is_object($this->lazyInstance)) {
            throw new LogicException(sprintf(
                'Invalid initializer return type: expected object, got %s.',
                gettype($this->lazyInstance)
            ));
        }

        $this->lazyArguments = null;
    }

    /**
     * Asserts that the lazy-loaded instance implements a specific method.
     *
     * This method checks if the lazy-loaded instance has a specific method implemented.
     * If the method does not exist, it throws a RuntimeException.
     *
     * @param string $method The name of the method to check for implementation.
     *
     * @return never
     * @throws RuntimeException If the specified method is not implemented in the lazy-loaded class.
     */
    private function assertImplement(string $method): void 
    {
        if (method_exists($this->lazyInstance, $method)) {
            return;
        }

        throw new LogicException(sprintf(
            'The lazy loaded class: "%s" does not implement "%s".',
            $this->lazyInstance::class,
            $method
        ));
    }
}