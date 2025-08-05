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
namespace Luminova\Template\Extensions;

use \Smarty\Template;
use \Luminova\Luminova;
use \Luminova\Exceptions\RuntimeException;

/**
 * Wrapper for registering callable Smarty functions.
 *
 * This class defines a single Smarty plugin (function, modifier, block, etc.).
 * It stores the plugin name, handler callback, plugin type, cache rules, and
 * any allowed method/property constraints you want to enforce.
 *
 * Typical use cases:
 * - Register a simple helper `{hello}` in Smarty
 * - Wrap a PHP method so Smarty can call it
 * - Add a reusable block handler
 */
final class SmartyFunction
{
    /**
     * Standard Smarty function plugin.
     * 
     * @var string FUNCTION
     */
    public const FUNCTION = 'function';

    /**
     * Smarty modifier, used like {$var|modifier}.
     * 
     * @var string MODIFIER
     */
    public const MODIFIER = 'modifier';

    /**
     * Smarty compiler plugin.
     * 
     * @var string COMPILER
     */
    public const COMPILER = 'compiler';

    /**
     * Smarty block plugin that wraps content between opening and closing tags.
     * 
     * @var string BLOCK
     */
    public const BLOCK = 'block';

    /**
     * Luminova-specific type for Smarty registerObject,
     * exposes an object’s methods and properties in templates.
     * 
     * @var string OBJECT
     */
    public const OBJECT = 'object';

    /**
     * Valid plugin types for SmartyFunction.
     * 
     * @var string[] TYPES
     */
    private const TYPES = [
        self::FUNCTION, self::MODIFIER, self::COMPILER, self::BLOCK, self::OBJECT
    ];

    /**
     * Creates a new Smarty function wrapper for plugins and object.
     * 
     * This method allows you to define the type of the plugin and handler. 
     * 
     * **Valid Types:**
     * - `function`  - Standard Smarty function plugin.
     * - `block`     - Block-style plugin that wraps content between opening and closing tags.
     * - `compiler`  - Smarty compiler plugin.
     * - `modifier`  - Smarty modifier (used like `{$self.var|modifier}`).
     * - `object"`   - Luminova type for `registerObject`, exposes an object’s methods and properties in templates.
     *
     * @param string $name The plugin name to used in templates.
     * @param callable|object|string $handler PHP callable, object, or function name, depending on `$type`.
     * @param string $type Plugin type (e.g, `function`, `modifier`, `compiler`, `block`, or `object`).
     * @param array<string> $allowedMethodsProperties List of allowed public methods/properties for (type: `object`).
     * @param bool $positional Whether arguments should pass in positional parameters for (type: `non-object`).
     * @param bool $cacheable Whether plugin output is cacheable for (type: `non-object`).
     * @param array<string> $blockMethods Allowed class methods for (type: `object`).
     *
     * @throws RuntimeException If the `$type` is invalid or the `$handler` does not match the expected type.
     *
     * @example - Basic function:
     * ```php
     * $f = new SmartyFunction('hello', fn() => 'Hello World');
     * ```
     *
     * @example - Function with formatted parameters:
     * ```php
     * new SmartyFunction(
     *      'greet', 
     *      fn($p) => 'Hi ' . $p['name'],
     *      positional: true
     * );
     * ```
     * @example - Function without formatted  parameters:
     * ```php
     * new SmartyFunction(
     *      'greet', 
     *      fn(string $name) => 'Hi ' . $name,
     *      positional: false
     * );
     * ``
     *
     * @example - Block function:
     * ```php
     * new SmartyFunction(
     *     'bold',
     *     fn($p, $content) => '<strong>' . $content . '</strong>',
     *     SmartyFunction::BLOCK
     * );
     * ```
     *
     * @example - Modifier:
     * ```php
     * new SmartyFunction('upper', 'strtoupper', SmartyFunction::MODIFIER);
     * ```
     * 
     * @example - Object registration:
     * ```php
     * new SmartyFunction(
     *     'example',
     *     new App\Utils\Example('Peter', 32),
     *     SmartyFunction::OBJECT,
     *     allowedMethodsProperties: ['getName', 'getAge']
     * );
     * ```
     */
    public function __construct(
        private string $name,
        private mixed $handler,
        private string $type = self::FUNCTION,
        private array $allowedMethodsProperties = [],
        private bool $positional = true,
        private bool $cacheable = true,
        private array $blockMethods = []
    ) {
        if (!in_array($this->type, self::TYPES, true)) {
            throw new RuntimeException(sprintf(
                'Unsupported plugin type "%s". Expected one of: %s.',
                $this->type,
                implode(', ', self::TYPES)
            ));
        }

        if ($this->type === self::OBJECT) {
            if (!is_object($this->handler)) {
                throw new RuntimeException(sprintf(
                    'Type "object" expects a non-callable object. Got: %s.',
                    gettype($this->handler)
                ));
            }
        }else{
             if (!Luminova::isCallable($this->handler)) {
                throw new RuntimeException(sprintf(
                    'Type "%s" expects a callable or function name. Got: %s.',
                    $this->type,
                    gettype($this->handler)
                ));
            }
        }
    }

    /**
     * Get the list of allowed public methods or properties for object-type plugins.
     *
     * @return array<string> List of allowed methods/properties.
     */
    public function getAllowedMethodsProperties(): array
    {
        return $this->allowedMethodsProperties;
    }

    /**
     * Get the list of allowed block methods for block-type plugins.
     *
     * @return array<string> Returns list of block methods.
     */
    public function getBlockMethods(): array
    {
        return $this->blockMethods;
    }

    /**
     * Get the handler for this plugin.
     *
     * @return callable|object Returns the PHP callback or object instance.
     */
    public function getHandler(): callable|object
    {
        return $this->handler;
    }

    /**
     * Get the name used for this plugin in templates.
     *
     * @return string Returns the plugin name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the plugin type.
     *
     * @return string Returns one of 'function', 'block', 'compiler', 'modifier', or 'object'.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Check if this plugin name matches the given name.
     *
     * @param string $plugin Plugin name to check.
     * 
     * @return bool Returns true if matches, false otherwise.
     */
    public function has(string $name): bool
    {
        return $this->name === $name;
    }

    /**
     * Check if this plugin matches a given type.
     *
     * @param string $plugin Plugin type to check.
     * 
     * @return bool Returns true if matches, false otherwise.
     */
    public function is(string $plugin): bool
    {
        return $this->type === $plugin;
    }

    /**
     * Determine whether Smarty should pass formatted parameters to the handler.
     *
     * @return bool Returns true if formatted parameters are enabled, false otherwise.
     */
    public function isPositional(): bool
    {
        return $this->positional;
    }

    /**
     * Determine if this plugin is a standard Smarty plugin (not an object).
     *
     * @return bool Returns true if standard plugin, false if object-type.
     */
    public function isPlugin(): bool
    {
        return !$this->is(self::OBJECT) && !$this->isObject();
    }

    /**
     * Determine if this plugin is an object-type plugin.
     *
     * @return bool Returns true if object-type plugin, false otherwise.
     */
    public function isObject(): bool
    {
        return $this->is(self::OBJECT) && is_object($this->handler);
    }

    /**
     * Check whether this plugin is cacheable.
     *
     * @return bool Returns true if cacheable, false otherwise.
     */
    public function isCacheable(): bool
    {
        return $this->cacheable;
    }

    /**
     * Resolve and execute the function plugin.
     * 
     * Depending on the `$positional` flag, arguments can be passed as:
     * - `false` **Associative:** a single array of key-value pairs is passed.
     * - `true` **Positional:** each parameter is passed as a separate argument.
     *
     * @param mixed ...$params Parameters passed from the template.
     * 
     * @return mixed Returns the result of the handler execution.
     */
    public function resolver(mixed ...$params): mixed
    {
        $args = (($params[1] ?? null) instanceof Template) 
            ? $params[0] 
            : $params;

        return $this->positional 
            ? ($this->handler)(...array_values($args))
            : ($this->handler)($args);
    }
}