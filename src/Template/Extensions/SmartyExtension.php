<?php 
/**
 * Luminova Framework Smarty helper template functions.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template\Extensions;

use \Throwable;
use \Luminova\Boot;
use \Smarty\Template;
use \Luminova\Luminova;
use \Luminova\Time\Time;
use \Luminova\Exceptions\ClassException;
use \Luminova\Exceptions\RuntimeException;
use \App\Config\Templates\Smarty\Extensions;
use \Luminova\Exceptions\BadMethodCallException;
use \Smarty\FunctionHandler\FunctionHandlerInterface;

final class SmartyExtension
{
    /**
     * Default classes.
     * 
     * @var array<string,class-string<\T>> $defaults.
     */
    private static array $defaults = [
        'Luminova' => Luminova::class,
        'Boot'     => Boot::class,
    ];

     /**
     * Customer registered classes via `Extensions::registerClasses`
     * 
     * @var array<string,array<string,class-string<\T>> $classes.
     */
    private static array $classes = [];

    /**
     * Luminova Smarty extension bridge.
     *
     * @internal - This class provides shared callbacks used by the template engine
     * to resolve dynamic functions, static access, constants, and object construction.
     */
    private function __construct(){}

    /**
     * Resolve a function name that Smarty could not map directly.
     *
     * The resolver checks:
     * 1. A direct callable.
     * 2. A namespaced Luminova function under Luminova\Funcs.
     * 3. Special keywords handled internally.
     *
     * @param string $name Function name requested from the template.
     *
     * @return callable|null Return fully-qualified callable name or null.
     * @throws BadMethodCallException If the function cannot be resolved.
     */
    public static function resolveUndefinedFunctionCallback(string $name): ?callable
    {
        if (is_callable($name)) {
            return $name;
        }

        $function = "\\Luminova\\Funcs\\{$name}";

        if (is_callable($function)) {
            return $function;
        }

        $function =  match ($name) {
            'now'                  => [Time::class, 'now'],
            'new'                  => [self::class, '___call'],
            'const'                => [self::class, '___callConstant'],
            'fn', 'func', 'function' => [self::class, '___callFunction'],
            'static'               => [self::class, '___callStatic'],
            default                => null
        };

        if($function !== null){
            return $function;
        }

        $fn = self::findObject($name);

        if ($fn instanceof SmartyFunction) {
            return [$fn, 'resolver'];
        }

        throw new BadMethodCallException(sprintf('Call to undefined function %s()', $name));
    }

    /**
     * Resolve a function name Smarty.
     * 
     * @param string $name Function name requested from the template.
     *
     * @return FunctionHandlerInterface Return Function Handler Interface.
     */
    public static function resolveFunctionHandler(string $name): FunctionHandlerInterface
    {
        return new class($name) implements FunctionHandlerInterface
        {
            public function __construct(private string $name) {}
            public function handle($params, Template $template) {}
            public function isCacheable(): bool {
                return true;
            }
        };
    }

    /**
     * Get a list of registered Smarty plugins or classes by group name.
     *
     * This method loads all plugin groups on first call, caching them
     * for later use. It initializes:
     *   - `classes`    → Core Luminova template classes + user-registered classes
     *   - `extensions` → All registered objects, functions, and modifiers
     *   - `globals`    → Global template variables
     *
     * Once cached, the values are returned directly without rebuilding.
     *
     * @param string $name The plugin group to return. Expected values:
     *                     `classes`, `extensions`, or `globals`.
     *
     * @return array The requested plugin group, or an empty array if undefined.
     */
    public static function getPlugins(string $name): array
    {
        if (self::$classes === []) {
            $extensions = new Extensions();

            self::$classes['classes'] = array_merge(
                self::$defaults,
                $extensions->registerClasses()
            );

            self::$classes['extensions'] = array_merge(
                $extensions->registerObjects(),
                $extensions->registerFunctions(),
                $extensions->registerModifiers()
            );

            self::$classes['globals'] = $extensions->registerGlobals();
        }

        return self::$classes[$name] ?? [];
    }

    /**
     * Find the first registered Smarty function object that matches the given name.
     *
     * This method scans the registered extension objects and returns the first
     * `SmartyFunction` instance that:
     *   - defines the given function name via `has($name)`, and
     *   - matches the requested plugin type.
     *
     * Plugin type matching works as follows:
     *   - When `$type` is `null`, only callable plugin types (non-object types) are allowed,
     *     which is checked through `isPlugin()`.
     *   - When `$type` is a string, the object must explicitly match that type via `is($type)`.
     *
     * It returns `null` when no matching object is found.
     *
     * @param string $name The function/plugin name to look up.
     * @param string|null $type Plugin type to find. `null` allows all plugin types except `object`.
     *
     * @return SmartyFunction|null The matching function object, or null if none found.
     */
    public static function findObject(string $name, ?string $type = null): ?SmartyFunction
    {
        foreach (self::getPlugins('extensions') ?? [] as $obj) {
            if (
                $obj instanceof SmartyFunction &&
                $obj->has($name) &&
                (($type === null && $obj->isPlugin()) || $obj->is($type))
            ) {
                return $obj;
            }
        }

        return null;
    }

    /**
     * Return the value of a PHP constant.
     *
     * @param string $name Fully-qualified constant name.
     *
     * @return mixed Return the constant value.
     * @throws BadMethodCallException If the constant does not exist.
     */
    public static function ___callConstant(string $name): mixed
    {
        if (defined($name)) {
            return constant($name);
        }

        throw new BadMethodCallException(
            sprintf('Constant "%s" is not defined.', $name)
        );
    }

    /**
     * Call a PHP function with the given arguments.
     *
     * @param string $function Function name.
     * @param mixed  ...$args Arguments to pass.
     *
     * @return mixed Return the result returned by the function.
     * @throws BadMethodCallException If the function is not callable.
     * 
     * @example - Example:
     * ```twig
     * {{ fn('functionName', arg1, arg2) }}
     * ```
     */
    public static function ___callFunction(string $function, mixed ...$args): mixed
    {
        if (is_callable($function)) {
            return $function(...$args);
        }

        $fn = self::findObject($function);

        if ($fn instanceof SmartyFunction) {
            return $fn->resolver($args);
        }

        throw new BadMethodCallException(
            sprintf('Call to undefined function %s()', $function)
        );
    }

    /**
     * Create a new instance of a class, supporting registered aliases.
     *
     * @param string $class Class name or alias.
     * @param mixed  ...$args Constructor arguments.
     *
     * @return object Return the Instantiated object.
     * @throws ClassException If the class does not exist.
     * @throws RuntimeException If the class cannot be instantiated.
     */
    public static function ___call(string $class, mixed ...$args): object
    {
        $class = self::getPlugins('classes')[$class] ?? $class;

        if (!$class || !class_exists($class)) {
            throw new ClassException(sprintf('Class "%s" does not exist.', $class));
        }

        try {
            return new $class(...$args);
        } catch (Throwable $e) {
            throw new RuntimeException(
                sprintf('Unable to instantiate "%s": %s', $class, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Access a static property or call a static method on a class.
     * Supports alias-to-class mapping.
     *
     * @param string $class Class name or alias.
     * @param string $member Static method or property name.
     * @param mixed ...$args Arguments for method calls.
     *
     * @return mixed Return the property value or method result.
     *
     * @throws ClassException If the class does not exist.
     * @throws BadMethodCallException If the member does not exist.
     */
    public static function ___callStatic(string $class, string $member, mixed ...$args): mixed
    {
        $class = self::getPlugins('classes')[$class] ?? $class;

        if (!$class || !class_exists($class)) {
            throw new ClassException(sprintf('Class "%s" does not exist.', $class));
        }

        if (property_exists($class, $member)) {
            return $class::${$member};
        }
        
        if (method_exists($class, $member)) {
            return $class::{$member}(...$args);
        }

        throw new BadMethodCallException(sprintf(
            'Method or property "%s" does not exist in class "%s".',
            $member,
            $class
        ));
    }
}