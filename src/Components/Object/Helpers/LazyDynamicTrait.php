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
namespace Luminova\Components\Object\Helpers;

use \ReflectionClass;
use \JsonSerializable;
use \ReflectionMethod;
use \ReflectionFunction;
use \ReflectionNamedType;
use \Luminova\Interface\Arrayable;
use \Luminova\Exceptions\{LogicException, RuntimeException};

trait LazyDynamicTrait
{
    /**
     * Lazy mode when cleated.
     * 
     * @var int LAZY_OBJECT_CREATED
     */
    public static int $LAZY_OBJECT_CREATED = 1;

    /**
     * Lazy mode when object is hydrated.
     * 
     * @var int LAZY_OBJECT_HYDRATED
     */
    public static int $LAZY_OBJECT_HYDRATED = 2;

    /**
     * Lazy object mode.
     * 
     * @var int $lazyObjectState
     */
    private int $lazyObjectState = 0;

    /** 
     * Callback used to build/initialize the object.
     * 
     * @var (callable(mixed ...$arguments):object|null|void)|null $lazyInitializer
     */
    private mixed $lazyInitializer = null;

    /** 
     * Optional callback to provide arguments for the initializer
     * 
     * @var (callable():array)|null $lazyArguments
     */
    private mixed $lazyArguments = null;

    /**
     * The lazily instantiated object instance.
     * 
     * @var object<\T>|null $lazyObject
     */
    private ?object $lazyObject = null;

    /**
     * Lazy ghost and proxy reflector.
     * 
     * @var string|null $lazyClassNamespace
     */
    private ?string $lazyClassNamespace = null;

    /**
     * Magic method for accessing object properties.
     * 
     * @param string $property The name of the property.
     * 
     * @return mixed Return the value of the property.
     */
    public function __get(string $property): mixed
    {
        return $this->getHydratedLazyObject(true)?->{$property} ?? null;
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
        if(!$this->__hasProperty($property) && !$this->__hasMethod('__set')){
            throw new LogicException(sprintf(
                'Cannot set property "%s" on %s; property does not exist and __set is not defined.',
                $property,
                $this->__className()
            ));
        }

        $this->getHydratedLazyObject(true)
            ->{$property} = $value;
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
        return $this->getHydratedLazyObject(true)
            ->{$method}(...$arguments);
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
        $object = $this->getHydratedLazyObject();

        if ($object === null) {
            return false;
        }

        return isset($object->{$property});
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
        if($this->__hasMethod('__unset')){
            $this->getHydratedLazyObject(true);
            unset($this->lazyObject->{$property});
        }
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): mixed
    {
        $object = $this->getHydratedLazyObject(true);

        if (($object instanceof JsonSerializable) || ($object instanceof Arrayable)) {
            return $object->jsonSerialize();
        }

        return null;
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
        $this->onHydrateLazyObject(__FUNCTION__);

        if($this->isLazySelf()){
           $this->lazyObject = clone $this;
           return;
        }

        $this->lazyObject = clone $this->lazyObject;
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
        $this->onHydrateLazyObject(__FUNCTION__);

        if($this->isLazySelf()){
            return (string) $this;
        }

        return (string) $this->lazyObject;
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
        $this->onHydrateLazyObject(__FUNCTION__);

        if($this->isLazySelf()){
            return ['data' => $this];
        }

        return ['data' => $this->lazyObject];
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
    // public function __unserialize(array $data): void
    // {
    //    $this->onHydrateLazyObject('__unserialize');
    //    $this->lazyObject->__unserialize($data['data'] ?? $data);
    // }

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
        $this->onHydrateLazyObject(__FUNCTION__);

        if($this->isLazySelf()){
            return [$this];
        }

        return [$this->lazyObject];
    }

    /**
     * Destructor for the lazy-loaded instance.
     *
     * This method is called when the object is destroyed. If the lazy-loaded instance exists and has a destructor,
     * it will be invoked before destroying the object.
     * 
     * @return void
     */
    // public function __destruct()
    // {
    //   if ($this->isLazySelf()) {
    //        return;
    //    }
    //
    //    if (method_exists($this->lazyObject, '__destruct')) {
    //        $this->lazyObject->__destruct();
    //    }
    // }

    /**
     * Check if the lazy-loaded object matches a given class or interface.
     *
     * Verifies whether the hydrated lazy object is an instance of the specified
     * class, implements the specified interface, or matches the given object.
     *
     * @param string|object $class Fully qualified class/interface name or an object to check against.
     *
     * @return bool Returns true if the lazy object matches the class/interface or object; false otherwise.
     *
     * @example - Example:
     * ```php
     * $object->isLazyInstanceof(Example::class);
     * $object->isLazyInstanceof($someObject);
     * ```
     */
    public final function isLazyInstanceof(string|object $class): bool
    {
        $lazy = $this->getHydratedLazyObject();

        if ($lazy === null) {
            return false;
        }

        if(is_string($class)){
            return ($lazy instanceof $class);
        }

        return ($lazy === $class);
    }

    /**
     * Check lazy-loaded instance of self class or null.
     * 
     * @return bool 
     */
    private function isLazySelf(): bool 
    {
        return ($this->lazyObject === null || $this->lazyObject === $this);
    }

    /**
     * Creates and return the lazy-loaded class object.
     * 
     * @return class-object<\T>|LazyObjectInterface<\T>|null Return lazy-loaded instance of the specified class or null.
     * @throws RuntimeException If the class does not exist or error occurs.
     */
    private function getHydratedLazyObject(bool $throw = false): ?object
    {
        if($this->lazyObject !== null){
            return $this->lazyObject;
        }

        //if(
        //    $this->lazyObject === $this || 
        //    ($this?->lazyClassNamespace !== null && 
        //    ($this->lazyObject instanceof $this->lazyClassNamespace))
        //){
        //    if($this->lazyObject->lazyObjectState === static::$LAZY_OBJECT_HYDRATED){
        //        return $this->lazyObject;
        //    }
        //}

        $this->onHydrateLazyObject(__FUNCTION__);

        if($throw && $this->lazyObject === null){
            throw new RuntimeException('Failed to hydrate lazy object.');
        }

        return $this->lazyObject;
    }

    /**
     * Check whether the hydrated lazy object defines a given method.
     *
     * This performs a safe existence check after hydration and does
     * not trigger method execution.
     *
     * @param string $method The method name to check.
     *
     * @return bool True if the method exists on the hydrated object.
     */
    public function __hasMethod(string $method): bool
    {
        $class = $this->lazyClassNamespace ?? $this->getHydratedLazyObject();

        if ($class === null) {
            return false;
        }

        //try {
        //    return (new ReflectionClass($class))
        //        ->hasMethod($method);
        //} catch (Throwable) {
        //    return false;
        //}

        return method_exists($class, $method);
    }

    /**
     * Check whether the hydrated lazy object defines a given property.
     *
     * This verifies the presence of a property after hydration and
     * does not access or modify its value.
     *
     * @param string $property The property name to check.
     *
     * @return bool True if the property exists on the hydrated object.
     */
    public function __hasProperty(string $property): bool
    {
        $class = $this->lazyClassNamespace 
            ?? $this->getHydratedLazyObject();

        if ($class === null) {
            return false;
        }

        return property_exists($class, $property);
    }

    /**
     * Get the class name of the lazy-loaded instance.
     * 
     * @return string Returns the class name.
     */
    public function __className(): ?string
    {
       
        if ($this->lazyClassNamespace !== null) {
            return $this->lazyClassNamespace;
        }

        $class = $this->getHydratedLazyObject();

        if($class === null){
            return null;
        }

        return get_class($class);
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
    private function assertLazyImplements(?string $method): void 
    {
        if (
            $method === null || 
            ($this->lazyObject && method_exists($this->lazyObject, $method))
        ) {
            return;
        }

        throw new LogicException(sprintf(
            'The lazy object: "%s" does not implement "%s".',
            $this->lazyObject::class,
            $method
        ));
    }

    /**
     * Get dynamic arguments for the lazy initializer.
     * 
     * Builds the argument list for the initializer. If $newThis is true, it will 
     * look through the initializer's parameters and inject a "seed" instance of 
     * the lazy class wherever the type matches (not restricted to position 0).
     * 
     * @param bool $newThis Whether to inject a new uninitialized instance.
     * 
     * @return array Arguments for the initializer callable.
     */
    private function getLazyArguments(bool $newThis = false): array 
    {
        // If no seed injection is needed, just evaluate arguments as-is
        if (!$newThis || $this->lazyClassNamespace === null) {
            return $this->lazyArguments 
                ? (array) ($this->lazyArguments)() 
                : [];
        }

        // Start with user-supplied arguments (if any)
        $args = $this->lazyArguments ? (array) ($this->lazyArguments)() : [];

        // Reflect initializer to find where to inject
        $reflector = (is_array($this->lazyInitializer) && count($this->lazyInitializer) === 2) 
            ? new ReflectionMethod($this->lazyInitializer[0], $this->lazyInitializer[1])
            : new ReflectionFunction($this->lazyInitializer);

        // If no parameters are expected, just return args as-is
        if ($reflector->getNumberOfParameters() === 0) {
            return $args;
        }

        $params = $reflector->getParameters();
        $seed = (new ReflectionClass($this->lazyClassNamespace))->newInstanceWithoutConstructor();

        // If no explicit arguments were given, pad with nulls to match parameter count
        if ($args === []) {
            $args = array_fill(0, count($params), null);
        }

        // Find first parameter type-hint matching lazy class and inject seed there
        foreach ($params as $index => $param) {
            if (
                $param->hasType() &&
                ($type = $param->getType()) instanceof ReflectionNamedType &&
                is_a($this->lazyClassNamespace, $type->getName(), true)
            ) {
                $args[$index] = $seed;
                break;
            }
        }

        return $args;
    }
}