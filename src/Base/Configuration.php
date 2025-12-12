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

use \Luminova\Luminova;
use \Psr\Log\AbstractLogger;
use \Luminova\Interface\{RequestInterface, LazyObjectInterface};

abstract class Configuration implements LazyObjectInterface
{
    /**
     * Constructor to initialize the class and trigger onCreate hook.
     */
    public function __construct()
    {
        $this->onCreate();
    }

    /**
     * Non-static property getter.
     *
     * @param string $key The property key.
     * 
     * @return mixed|null Return the property value, or null if not found.
     * 
     * @ignore 
     */
    public function __get(string $key): mixed
    {
        return property_exists($this, $key)
            ? $this->{$key}
            : null;
    }

    /**
     * Static property getter.
     *
     * @param string $key The property key.
     * 
     * @return mixed|null Return the property value, or null if not found.
     * 
     * @ignore 
     * @internal
     */
    public static function __getStatic(string $key, mixed $default = null): mixed
    {
        return Luminova::isPropertyExists(static::class, $key)
            ? static::${$key}
            : $default;
    }

    /**
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Retrieve environment configuration variables with optional type casting.
     *
     * @param string $key  The environment variable key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @param string|null $return The expected return type. Can be one of:
     *                     - 'bool', 'int', 'float', 'double', 'nullable', or 'string'.
     * 
     * @return mixed  Returns the environment variable cast to the specified type, or default if not found.
     */
    public static final function getEnv(string $key, mixed $default = null, ?string $return = null): mixed 
    {
        $value = env($key, $default);

        if ($return === null || !is_string($value)) {
            return $value;
        }

        return match (strtolower($return)) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'double' => (double) $value,
            'nullable' => ($value === '') ? null : $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Customize and generate an HTML email template for logging system notifications.
     *
     * @param RequestInterface $request The HTTP request object containing information about the request.
     * @param AbstractLogger|\Luminova\Logger\NovaLogger $logger The instance of logger class.
     * @param string $message The log message.
     * @param string $level The log level (e.g., 'info', 'warning', 'error').
     * @param array<string|int,mixed> $context Additional context information for the log message.
     *
     * @return string|null Return the HTML email template or null to use default.
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
}