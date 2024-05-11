<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Base;

use \Luminova\Functions\Escaper;
use \Luminova\Functions\IPAddress;
use \Luminova\Storages\FileManager;
use \Luminova\Functions\TorDetector;
use \Luminova\Functions\Maths;
use \Luminova\Functions\Normalizer;
use \Luminova\Exceptions\InvalidArgumentException;

abstract class BaseFunction extends Normalizer
{
    /**
     * @var array $instance method instances
    */
    private static array $instances = [];

    /**
     * Initalize or return a shared an instance of the IP address class.
     *
     * @return IPAddress Returns a ip address class instance
    */
    public static final function ip(): IPAddress
    {
        return static::$instances['ip'] ??= new IPAddress();
    }

    /**
     * Initalize or return a shared an instance of the Files class.
     *
     * @return FileManager Returns a file class instance
    */
    public static final function files(): FileManager
    {
        return static::$instances['files'] ??= new FileManager();
    }

    /**
     * Initalize or return a shared an instance of the tor detector class.
     *
     * @return TorDetector Returns a tor detector class instance
    */
    public static final function tor(): TorDetector
    {
        return static::$instances['tor'] ??= new TorDetector();
    }

     /**
     * Initalize or return a shared an instance of the math class.
     *
     * @return Maths Returns a math class instance
    */
    public static final function math(): Maths
    {
        return static::$instances['math'] ??= new Maths();
    }

    /**
     * Escapes a string or array of strings based on the specified context.
     *
     * @param string|array $input The string or array of strings to be escaped.
     *  - @example @var array<string, string> - Use the key as the context.
     *  - @example @var array<int, string> Use the default context fall all values.
     * @param string $context The context in which the escaping should be performed.
     *                        Possible values: 'html', 'js', 'css', 'url', 'attr', 'raw'.
     * @param string|null $encoding The character encoding to use. Defaults to null.
     * 
     * @return array|string The escaped string or array of strings.
     * @throws InvalidArgumentException|Exception When an invalid escape context is provided.
     * @throws BadMethodCallException When the called method does not exist.
     * @throws RuntimeException When the string is not valid UTF-8 or cannot be converted.
     */
    public static final function escape(string|array $input, string $context = 'html', ?string $encoding = null): array|string
    {
        if (is_array($input)) {
            array_walk_recursive($input, function (&$value, $key) use ($context, $encoding) {
                $context = is_string($key) ? $key : $context;
                $value = static::escape($value, $context, $encoding);
            });
        } elseif (is_string($input)) {
            $context = strtolower($context);

            if ($context === 'raw') {
                return $input;
            }

            if (!in_array($context, ['html', 'js', 'css', 'url', 'attr'], true)) {
                throw new InvalidArgumentException('Invalid escape context provided.');
            }

            $method = $context === 'attr' ? 'escapeHtmlAttr' : 'escape' . ucfirst($context);
            static $escaper;

            if (!$escaper || ($encoding && $escaper->getEncoding() !== $encoding)) {
                $escaper = new Escaper($encoding);
            }

            $input = $escaper->{$method}($input);
        }

        return $input;
    }
}