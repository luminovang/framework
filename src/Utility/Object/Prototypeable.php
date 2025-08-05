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
namespace Luminova\Utility\Object;

use \Closure;
use \Luminova\Exceptions\BadMethodCallException;

/**
 * Trait Prototypeable
 *
 * Provides a prototypal inheritance–like mechanism in PHP.
 * Inspired by JavaScript's prototype model, it lets you dynamically 
 * add properties and methods to an object at runtime.
 * 
 * @mixin Luminova\Interface\PrototypeableInterface
 * 
 * @example - Usage:
 * ```
 * use Luminova\Interface\PrototypeableInterface;
 * use Luminova\Utility\Object\Prototypeable;
 * 
 * class User implements PrototypeableInterface {
 *      use Prototypeable;
 * }
 * 
 * $user = new User();
 * $user->prototype('nickname', 'Johnny');
 * $user->prototype('sayHello', fn() => "Hello from {$this->nickname}");
 * 
 * echo $user->nickname;    // "Johnny"
 * echo $user->sayHello();  // "Hello from Johnny"
 * ```
 */
trait Prototypeable
{
    /**
     * Prototype registry key for dynamically added properties.
     *
     * Used internally to group all user-defined properties within the prototype system.
     *
     * @var string
     */
    public const PROTO_PROPERTY = 'properties';

    /**
     * Prototype registry key for dynamically added methods.
     *
     * Used internally to group all user-defined methods within the prototype system.
     *
     * @var string
     */
    public const PROTO_METHOD = 'methods';

    /**
     * Storage for dynamic properties and methods.
     *
     * @var array{properties:array<string,mixed>,methods:array<string,callable>} $_prototypes_
     */
    private array $_prototypes_ = [
        self::PROTO_PROPERTY => [],
        self::PROTO_METHOD => []
    ];

    /**
     * Add or override a dynamic method for the current instance.
     *
     * This method allows you to dynamically attach a callable (closure or method)
     * to the object at runtime. The callable will be bound to the current instance
     * context, giving it access to private and protected members.
     *
     * @param string $prototype The prototype method name to register or override.
     * @param callable $fn The function or closure to bind to this instance.
     * 
     * @return static<\T> Returns the current instance for method chaining.
     * 
     * @template \T of object
     * 
     * @example - Example:
     * ```php
     * use Luminova\Utility\String\Str;
     * 
     * $str = new Str('Hello');
     * 
     * // Add a new method dynamically
     * $str->extend('greet', fn($name) => "{$this->valueOf}, {$name}!");
     * 
     * echo $str->greet('Peter'); // Output: Hello, Peter!
     * ```
     */
    public function extend(string $prototype, callable $fn): static
    {
        $this->_prototypes_[self::PROTO_METHOD][$prototype] = Closure::fromCallable($fn)
                ->bindTo($this, static::class);
        return $this;
    }

    /**
     * Add a dynamic method or property to the current instance.
     * 
     * If `$valueOf` is a callable, it is registered as a method using {@see extend()}.
     * Otherwise, it is stored as a dynamic property.
     *
     * @param string $prototype The prototype method or property name to register.
     * @param mixed $valueOf A callable (method) or any value (property).
     * 
     * @return static<\T> Returns the current instance for method chaining.
     * 
     * @template \T of object
     * @see extend() To explicitly add methods only.
     * 
     * @example - Example:
     * ```php
     * $str = new Str('World');
     * 
     * // Add a new property
     * $str->prototype('language', 'English');
     * echo $str->language; // Output: English
     * 
     * // Add a new method
     * $str->prototype('greet', fn() => "Hello {$this->valueOf}");
     * echo $str->greet(); // Output: Hello World
     * 
     * // Prototype a new array method
     * $str->prototype('toArray', fn() => explode('l', $this->valueOf));
     * $str->toArray();
     * ```
     */
    public function prototype(string $prototype, mixed $valueOf): static
    {
        if (is_callable($valueOf)) {
            return $this->extend($prototype, $valueOf);
        }

        $this->_prototypes_[self::PROTO_PROPERTY][$prototype] = $valueOf;
        return $this;
    }

    /**
     * Retrieve all dynamically added methods and properties, or a specific type.
     *
     * If no type is specified, the entire prototype registry is returned.
     * If a type is specified (`T::PROTO_METHOD` or `T::PROTO_PROPERTY`),
     * only that subset will be returned.
     *
     * @param string|null $of Optional. Specify self::PROTO_METHOD or T::PROTO_PROPERTY to fetch a specific set.
     * 
     * @return array{properties:array<string,mixed>,methods:array<string,callable>}|array<string,mixed>|null
     *         Returns the full registry if `$of` is null, 
     *         the specific subset if `$o`f is valid, or null if the type does not exist.
     * 
     * @template \T of object
     */
    public function getPrototypes(?string $of = null): ?array 
    {
        if ($of === null) {
            return $this->_prototypes_;
        }

        return $this->_prototypes_[$of] ?? null;
    }

    /**
     * Get a single dynamically added method or property.
     *
     * If the prototype type is not provided, it defaults to methods.
     * Use `\T::PROTO_METHOD` or `\T::PROTO_PROPERTY` to specify what to retrieve.
     *
     * @param string $prototype The prototype property or method name to retrieve.
     * @param string $of The type of prototype to fetch (default: `T::PROTO_METHOD`).
     *
     * @return mixed Return the stored callable or value, or null if not found
     * @template \T of object
     */
    public function getPrototype(string $prototype, string $of = self::PROTO_METHOD): mixed 
    {
        return $this->_prototypes_[$of][$prototype] ?? null;
    }

    /**
     * Check if a dynamic prototype method or property exists.
     *
     * When `$type` is specified, the check is limited to that prototype category — 
     * either {@see \T::PROTO_METHOD} or {@see \T::PROTO_PROPERTY}.
     * If no type is given, both methods and properties are checked.
     *
     * @param string $prototype The name of the dynamic method or property.
     * @param string|null $type Optional. The prototype type to check 
     *                          (`\T::PROTO_METHOD` or `\T::PROTO_PROPERTY`).
     * 
     * @return bool Returns true if the prototype entry exists, false otherwise.
     * @template \T of object
     * 
     * @example - Example:
     * ```php
     * $str = new Str('Luminova');
     * 
     * $str->prototype('greet', fn() => "Hello {$this->value}");
     * 
     * $str->hasPrototype('greet'); // true
     * $str->hasPrototype('greet', Str::PROTO_METHOD); // true
     * $str->hasPrototype('greet', Str::PROTO_PROPERTY); // false
     * ```
     */
    public function hasPrototype(string $prototype, ?string $type = null): bool
    {
        return $type
            ? isset($this->_prototypes_[$type][$prototype])
            : (isset($this->_prototypes_[self::PROTO_METHOD][$prototype])
                || isset($this->_prototypes_[self::PROTO_PROPERTY][$prototype]));
    }

    /**
     * Remove a dynamically registered prototype method or property.
     *
     * If no `$type` is specified, the method attempts to remove the entry 
     * from both prototype collections (methods and properties).
     * If `$type` is provided, it must be either {@see \T::PROTO_METHOD} 
     * or {@see \T::PROTO_PROPERTY}.
     *
     * @param string $prototype The name of the prototype method or property to remove.
     * @param string|null $type Optional. The prototype type to target 
     *                          (`\T::PROTO_METHOD` or `\T::PROTO_PROPERTY`).
     * 
     * @return bool Returns true if the entry was removed or attempted successfully, 
     *              false if `$type` is invalid.
     * @template \T of object
     * 
     * @example - Example:
     * ```php
     * $str = new Str('Hello');
     * 
     * $str->prototype('greet', fn() => "Hello {$this->value}");
     * 
     * $str->hasPrototype('greet'); // true
     * $str->unprototype('greet');  // removes method
     * $str->hasPrototype('greet'); // false
     * ```
     */
    public function unprototype(string $prototype, ?string $type = null): bool
    {
        if ($type === null) {
            unset(
                $this->_prototypes_[self::PROTO_METHOD][$prototype],
                $this->_prototypes_[self::PROTO_PROPERTY][$prototype]
            );
            return true;
        }

        if ($type === self::PROTO_METHOD || $type === self::PROTO_PROPERTY) {
            unset($this->_prototypes_[$type][$prototype]);
            return true;
        }

        return false;
    }

    /**
     * Magic setter for dynamic properties.
     *
     * @param string $prototype The property name to set.
     * @param mixed $value The prototype property value to set.
     */
    public function __set(string $prototype, mixed $value): void
    {
        $this->_prototypes_[self::PROTO_PROPERTY][$prototype] = $value;
    }

    /**
     * Magic getter for dynamic properties.
     *
     * @param string $prototype The prototype property name to get.
     * 
     * @return mixed|null Returns the property value or null if not set
     */
    public function __get(string $prototype): mixed
    {
        if (array_key_exists($prototype, $this->_prototypes_[self::PROTO_PROPERTY])) {
            return $this->_prototypes_[self::PROTO_PROPERTY][$prototype];
        }

        if (property_exists($this, $prototype)) {
            return $this->{$prototype};
        }

        return null;
    }

    /**
     * Magic isset to check dynamic properties.
     *
     * @param string $prototype The prototype property name to check.
     * 
     * @return bool Return true if isset, otherwise false.
     */
    public function __isset(string $prototype): bool
    {
        return array_key_exists($prototype, $this->_prototypes_[self::PROTO_PROPERTY]) || 
            property_exists($this, $prototype);
    }

    /**
     * Magic unset to remove dynamic properties.
     *
     * @param string $prototype The prototype property name to unset.
     */
    public function __unset(string $prototype): void
    {
        $this->unprototype($prototype, self::PROTO_PROPERTY);
    }

    /**
     * Magic call to invoke dynamically added methods.
     *
     * @param string $prototype The prototype method name to call.
     * @param array $args An optional arguments to pass to prototype method.
     * 
     * @return mixed Returns the prototype method result.
     * @throws BadMethodCallException If the prototype method does not exist.
     */
    public function __call(string $prototype, array $args): mixed
    {
        $method = $this->_prototypes_[self::PROTO_METHOD][$prototype] ?? null;

        if ($method !== null && is_callable($method)) {
            return $method(...$args);
        }

        throw new BadMethodCallException("Method '{$prototype}' not found in " . static::class);
    }
}