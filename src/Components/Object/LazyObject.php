<?php
/**
 * Luminova Framework Lazy Object Wrapper.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Object;

use \Closure;
use \Throwable;
use \Stringable;
use \ReflectionClass;
use \JsonSerializable;
use \ReflectionProperty;
use \Luminova\Interface\LazyObjectInterface;
use \Luminova\Components\Object\Helpers\LazyDynamicTrait;
use \Luminova\Exceptions\{AppException, LogicException, RuntimeException};

/**
 * @mixin LazyDynamicTrait
 */
class LazyObject implements LazyObjectInterface, Stringable, JsonSerializable
{
    /**
     * Lazy ghost and proxy reflector.
     * 
     * @var ReflectionClass|null $reflector
     */
    private static ?ReflectionClass $reflector = null;

    /**
     * Indicate Whether lazy-ghost is supported.
     * 
     * @var bool|null $isLazyGhost
     */
    private static ?bool $isLazyGhost = null;

    /**
     * Lazy dynamic proxy helpers.
     */
    use LazyDynamicTrait;

    /**
     * Constructs a new LazyObject instance.
     *
     * For PHP 8.4+ it uses `ReflectionClass::newLazyGhost` to create a lazy-loaded object. 
     * For earlier versions, it falls back to a custom initializer.
     *
     * @param (Closure(mixed ...$args): object)|class-string<T> $initializer A class name or closure used to create the object.
     * @param (callable(): array)|null $arguments Optional callable returning constructor arguments.
     *
     * @return LazyObjectInterface<T> Lazy wrapper around the specified class instance.
     * @throws RuntimeException If object creation fails or the class is invalid.
     * 
     * @see newLazyGhost()
     * @see newLazyProxy()
     * @see newObject()
     *
     * @example - Class name initializer:
     * 
     * ```php
     * $person = new LazyObject(Person::class);
     * echo $person->getAge();
     * ```
     * 
     * @example - Custom closure initializer:
     * ```php
     * $person = new LazyObject(fn(): Person => new Person(33));
     * echo $person->getAge();
     * ```
     */
    public function __construct(Closure|string $initializer, ?callable $arguments = null) 
    {
        $this->lazyArguments = $arguments;
        $this->lazyClassNamespace = null;

        if($initializer instanceof Closure){
            $this->lazyInitializer = $initializer;
            return;
        }

        $this->lazyClassNamespace = $initializer;
        //$this->lazyInitializer = fn(mixed ...$args) => new ($initializer)(...$args);
    }

    /**
     * Creates a lazy-loaded object instance.
     *
     * For PHP 8.4+ this uses `ReflectionClass::newLazyGhost` to lazily initialize 
     * the object on first access. For earlier versions, it falls back to a custom initializer.
     *
     * @param (Closure(mixed ...$args): object)|class-string<T> $initializer A class name or closure used to create the object.
     * @param (callable(): array)|null $arguments Optional callable returning constructor arguments.
     *
     * @return object|LazyObjectInterface<T> Lazy wrapper around the specified class instance.
     * @throws RuntimeException If instantiation fails or the class is invalid.
     * @see newLazyGhost() For PHP 8.4+ specific implementation.
     * @see newLazyGhost() For PHP 8.4+ specific implementation.
     *
     * @example - Class initializer with arguments:
     * 
     * ```php
     * $person = LazyObject::newObject(Person::class, fn(): array => [33]);
     * echo $person->getName();
     * ```
     */
    public static function newObject(Closure|string $initializer, ?callable $arguments = null): object
    {
        self::$isLazyGhost ??= version_compare(PHP_VERSION, '8.4.0', '>=');

        if(self::$isLazyGhost && !($initializer instanceof Closure)){
            return self::newLazyGhost($initializer, $arguments);
        }
        
        return new self($initializer, $arguments);
    }

    /**
     * Creates a lazy-loaded ghost object for the given class.
     *
     * Uses PHP 8.4+ `ReflectionClass::newLazyGhost` to delay instantiation 
     * until the object is first accessed. Constructor arguments can be provided 
     * via a callable returning an array.
     *
     * @param class-string<T> $class Fully qualified class name.
     * @param (callable(): array)|null $arguments Optional callable that returns 
     *        constructor arguments.
     *
     * @return object Lazy ghost instance of the given class.
     * @throws RuntimeException If object creation fails.
     * @see newObject() For PHP versions below 8.4.
     *
     * @example - Example:
     * 
     * ```php
     * $person = LazyObject::newLazyGhost(
     *      Person::class, 
     *      fn(): array => [33, 'Peter', 'Nigeria']
     * );
     * 
     * echo $person->getName();
     * ```
     */
    public static function newLazyGhost(string $class, ?callable $arguments = null): object
    {
        return self::coreLazyInitializer($class, fn (object $object) => $object->__construct(
            ...($arguments && is_callable($arguments)) ? $arguments() : []
        ));
    }

    /**
     * Creates a lazy-loaded proxy object for the given class.
     *
     * Uses PHP 8.4+ `ReflectionClass::newLazyProxy` to delay instantiation 
     * until the object is first accessed. Unlike lazy ghosts, proxies 
     * call the constructor immediately on first use via `new $class`.
     *
     * @param class-string<T> $class Fully qualified class name.
     * @param (callable(): array)|null $arguments Optional callable that returns 
     *        constructor arguments.
     *
     * @return object Lazy proxy instance of the given class.
     * @throws RuntimeException If object creation fails.
     * 
     * @see newObject() For PHP versions below 8.4.
     *
     * @example - Example:
     * ```php
     * $person = LazyObject::newLazyProxy(
     *      Person::class, 
     *      fn(): array => [33, 'Peter', 'Nigeria']
     * );
     * 
     * echo $person->getName();
     * ```
     */
    public static function newLazyProxy(string $class, ?callable $arguments = null): object
    {
        return self::coreLazyInitializer($class, fn (object $object) => new $class(
            ...($arguments && is_callable($arguments)) ? $arguments() : []
        ), true);
    }

    /**
     * Creates and return the lazy-loaded class object.
     * 
     * @return class-object<\T>|LazyObjectInterface<\T>|null Return lazy-loaded instance of the specified class or null.
     * @throws RuntimeException If the class does not exist or error occurs.
     */
    public function getLazyObject(): ?object
    {
        $this->onHydrateLazyObject(__FUNCTION__);
        return $this->lazyObject;
    }

    /**
     * Retrieves the reflection instance used for lazy object creation.
     *
     * Returns either the cached `ReflectionClass` for the target class 
     * or a specific `ReflectionProperty` if a property name is given.
     *
     * @param string|null $property Optional property name to fetch.
     *
     * @return ReflectionClass|ReflectionProperty|null 
     *         Returns the class reflector, a property reflector, 
     *         or null if not available.
     * 
     * > **Note:** 
     * > This is only available in PHP 8.4+ when use `newLazyGhost`, `newLazyProxy` or `newObject` with class namespace.
     */
    public function getLazyReflector(?string $property = null): ReflectionClass|ReflectionProperty|null
    {
        if($property === null){
            return self::$reflector;
        }

        return self::$reflector->getProperty($property);
    }

    /**
     * Override the current lazy-loaded instance with a new class object.
     * 
     * @param class-object<\T> $instance The new class object to replace the existing lazy-loaded instance.
     * 
     * @return object<\T> Return new object.
     */
    public function replaceLazyObject(object $instance): object
    {
        return $this->lazyObject = $instance;
    }

    /**
     * Hydrates (instantiates) the lazy-loaded object on demand.
     *
     * This method forces creation of the underlying object if it hasn't been 
     * instantiated yet. It executes the stored initializer callback or, if a 
     * class namespace is set, constructs the object directly. After hydration, 
     * it optionally verifies that the object implements or extends a required type.
     *
     * @param string|null $fn Optional method name used for error reporting or context.
     * @param string|null $assert Optional class or interface name to validate against.
     * @param array|null $arguments Optional arguments to pass to the initializer (overrides defaults).
     *
     * @throws RuntimeException If instantiation fails or the initializer throws any non-AppException error.
     * @throws LogicException If the initializer does not return an object.
     * @throws AppException If the initializer callback explicitly throws an AppException.
     */
    private function onHydrateLazyObject(?string $fn = null, ?string $assert = null, ?array $arguments = null): void
    {
        if ($this->lazyObject !== null) {
            $this->assertLazyImplements($assert);
            return;
        }

        try {
            $this->lazyObject = ($this->lazyClassNamespace === null)
                ? ($this->lazyInitializer)(...$this->getLazyArguments())
                : new ($this->lazyClassNamespace)(...$this->getLazyArguments());
        } catch (Throwable $e) {
            [$file, $line] = AppException::trace(3);
            
            if(!$e instanceof AppException){
                $e = new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            if($file){
                $e->setLine($line)->setFile($file);
            }

            throw $e;
        }

        if (!is_object($this->lazyObject)) {
            throw new LogicException(sprintf(
                'Invalid initializer return type: expected object, got %s.',
                gettype($this->lazyObject)
            ));
        }

        $this->assertLazyImplements($assert);
        $this->lazyInitializer = null;
        $this->lazyArguments = null;
    }

    /**
     * Internal helper to create a lazy ghost or proxy instance.
     *
     * Handles reflection setup, object creation, and error handling.
     * Throws a `RuntimeException` if the lazy object cannot be initialized.
     *
     * @param class-string<T> $class Fully qualified class name.
     * @param Closure $handler Callback to initialize the object.
     * @param bool $isProxy Whether to create a proxy instead of a ghost.
     *
     * @return object Lazy object instance.
     * @throws RuntimeException If reflection or initialization fails.
     */
    private static function coreLazyInitializer(string $class, Closure $handler, bool $isProxy = false): object
    {
        try {
            self::$reflector = new ReflectionClass($class);
            return $isProxy 
                ? self::$reflector->newLazyProxy($handler)
                : self::$reflector->newLazyGhost($handler);
        } catch (Throwable $e) {
            if($e instanceof AppException){
                throw $e;
            }

            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Creates a new instance with arguments return the class object.
     * 
     * @param mixed ...$arguments The arguments to create a new class object with.
     * 
     * @return class-object<\T>|LazyObjectInterface<\T> Return a new instance of the lazy loaded class or null.
     * @throws RuntimeException If error occurs while initializing class.
     * @deprecated Since 3.6.8 
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
            $this->lazyObject !== null && 
            $arguments === [] && 
            method_exists($this->lazyObject, '__clone')
        ){
            return clone $this->lazyObject;
        }

        try {
            if (self::$isLazyGhost && $this->lazyClassNamespace !== null) {
                return self::newLazyGhost($this->lazyClassNamespace, fn() => $arguments);
            }

            if($this->lazyInitializer instanceof Closure){
                return ($this->lazyInitializer)(...$arguments);
            }

            return new ($this->lazyInitializer)(...$arguments);
        } catch (Throwable $e) {
            if($e instanceof AppException){
                throw $e;
            }

            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}