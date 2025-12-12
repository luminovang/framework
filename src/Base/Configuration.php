<?php
/**
 * Luminova Framework abstract Config class for managing application configurations.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;


use \ReflectionClass;
use \JsonSerializable;
use \Luminova\Luminova;
use \ReflectionProperty;
use \Psr\Log\AbstractLogger;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Interface\{RequestInterface, LazyObjectInterface};

abstract class Configuration implements LazyObjectInterface, JsonSerializable
{
    /**
     * Initialize the configuration instance and trigger the creation hook.
     */
    public function __construct()
    {
        $this->onCreate();
    }

    /**
     * Hook executed after object construction.
     * 
     * Override in subclasses to perform custom setup or initialization.
     */
    protected function onCreate(): void {}

    /**
     * Magic getter for accessing defined properties.
     *
     * Returns the property value if it exists on the object,
     * otherwise returns null.
     *
     * @param string $key Property name.
     * @return mixed|null Return property value or null.
     */
    public function __get(string $key): mixed
    {
        return property_exists($this, $key)
            ? $this->{$key}
            : null;
    }

    /**
     * Magic setter for defining properties.
     *
     * @param string $property Property name.
     * @param mixed $value Property value.
     * 
     * @return mixed|null Return property value or null.
     */
    public function __set(string $property, mixed $value): void
    {
        $this->{$property} = $value;
    }

    /**
     * Magic method caller for instance context.
     *
     * Calls the method if it exists on the object,
     * otherwise returns null instead of throwing an error.
     *
     * @param string $method Method name.
     * @param array $arguments Method arguments.
     * 
     * @return mixed|null Return method result or null.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if(!method_exists($this, $method)){
            return null;
        }

        return $this->{$method}(...$arguments);
    }

    /**
     * Magic method caller for static context.
     *
     * Calls the static method if it exists on the class,
     * otherwise returns null instead of throwing an error.
     *
     * @param string $method Method name.
     * @param array $arguments Method arguments.
     * 
     * @return mixed|null Return method result or null.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if(!method_exists(static::class, $method)){
            return null;
        }

        return static::class::${$method}(...$arguments);
    }

    /**
     * Get all object properties (declared + dynamic) as an associative array.
     *
     * @return array<string,mixed> Return properties.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Static property accessor.
     *
     * Returns the static property value if it exists on the class,
     * otherwise returns the given default value.
     *
     * @param string $key Property name.
     * @param mixed $default Fallback value if property is not defined.
     * @return mixed
     *
     * @ignore
     * @internal
     * @deprecated
     */
    public static function __getStatic(string $key, mixed $default = null): mixed
    {
        return Luminova::isPropertyExists(static::class, $key)
            ? static::${$key}
            : $default;
    }

    /**
     * Ensure the current instance matches the expected configuration class.
     *
     * Throws if the instance is not of the expected type.
     *
     * @param class-string<Configuration> $expected Fully qualified class name to validate against.
     * 
     * @return void
     * @throws InvalidArgumentException If the instance is not of the expected type.
     */
    public final function assertInstanceOf(string $expected): void
    {
        if (!is_a($expected, Configuration::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid expected class: "%s" must extend "%s".',
                $expected,
                Configuration::class
            ));
        }

        if($this instanceof $expected){
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid configuration instance: expected "%s", got "%s".',
            $expected,
            static::class
        ));
    }

    /**
     * Check if the current instance matches the expected configuration class.
     *
     * Validates that $expected is itself a subclass of Configuration,
     * then checks if $this is an instance of it.
     *
     * @param class-string<Configuration> $expected Fully qualified class name.
     * 
     * @return bool Returns true if instance matches, otherwise false.
     */
    public final function isInstanceOf(string $expected): bool
    {
        return $this instanceof $expected;
    }

    /**
     * Retrieve an environment variable with optional type casting.
     *
     * If a type is provided, the value is cast only when it is a string.
     * Otherwise, the raw value is returned.
     *
     * Supported types:
     * - bool
     * - int
     * - float / double
     * - string
     * - nullable (empty string becomes null)
     *
     * @param string $key Environment variable name.
     * @param mixed $default Default value if not set.
     * @param string|null $cast Cast value to expected type (e.g, `nullable`, `float`).
     * 
     * @return mixed Returns the environment variable cast to the specified type, or default if not found.
     */
    protected final function getEnv(string $key, mixed $default = null, ?string $cast = null): mixed
    {
        $value = env($key, $default);

        if ($cast === null || !is_string($value)) {
            return $value;
        }

        return match (strtolower($cast)) {
            'bool'      => (bool) $value,
            'int'       => (int) $value,
            'float'     => (float) $value,
            'double'    => (double) $value,
            'nullable'  => ($value === '') ? null : $value,
            'string'    => (string) $value,
            default     => $value,
        };
    }

    /**
     * Build a custom HTML template for log email notifications.
     *
     * Return a string to override the default template,
     * or null to fall back to the built-in template.
     *
     * @param RequestInterface $request Current request instance.
     * @param AbstractLogger $logger Logger instance.
     * @param string $message Log message.
     * @param string $level Log level (e.g. info, warning, error).
     * @param array<string|int,mixed> $context Additional log data.
     *
     * @return string|null Return the HTML email template or null to use default.
     * @see App\Config\Logger
     */
    public static function getEmailLogTemplate(
        RequestInterface $request,
        AbstractLogger $logger,
        string $message,
        string $level,
        array $context
    ): ?string
    {
        return null;
    }

    /**
     * Get object properties as an associative array.
     *
     * By default, only public (including dynamic) properties are returned.
     * Set $includeProtected to true to also include protected properties.
     *
     * @param bool $includeProtected Include protected properties.
     * 
     * @return array<string,mixed> Return associative array of property name-value.
     */
    public final function toArray(bool $includeProtected = false): array
    {
        $data = get_object_vars($this);

        if (!$includeProtected) {
            return $data;
        }

        $ref = new ReflectionClass($this);

        foreach ($ref->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $data)) {
                continue;
            }

            if (!$property->isInitialized($this)) {
                continue;
            }

            $data[$name] = $property->getValue($this);
        }

        return $data;
    }
}