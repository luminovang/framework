<?php 
/**
 * Luminova Framework Twig helper template functions.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template\Extensions;

use \Throwable;
use \DateTimeZone;
use \Luminova\Boot;
use \Luminova\Luminova;
use \Twig\TwigFunction;
use \Luminova\Time\Time;
use \Twig\Extension\GlobalsInterface;
use \Twig\Extension\AbstractExtension;
use \Twig\Extension\ExtensionInterface;
use \Luminova\Exceptions\ClassException;
use \Luminova\Exceptions\RuntimeException;
use \App\Config\Templates\Twig\Extensions;
use \Luminova\Exceptions\BadMethodCallException;

class TwigExtension extends AbstractExtension implements GlobalsInterface, ExtensionInterface
{
    /**
     * Flag if classes has been mapped.
     * 
     * @var bool $isClassedMapped
     */
    private static bool $isClassedMapped = false;

    /**
     * Customer registered classes via `Extensions::registerClasses`
     * 
     * @var array<string,class-string<\T>> $classes.
     */
    private static array $classes = [
        'Luminova' => Luminova::class,
        'Boot' => Boot::class
    ];

    use Extensions;

    /**
     * Register functions accessible in Twig templates.
     *
     * @return array<int,TwigFunction> Functions.
     */
    public final function getFunctions(): array
    {
        $this->initRegisteredClasses();

        return array_merge(
            $this->registerCoreFunctions(), 
            $this->registerFunctions()
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
    private static function callFunctions(string $function, mixed ...$args): mixed 
    {
        if (is_callable($function)) {
            return $function(...$args);
        }

        $fn = "\\Luminova\\Funcs\\{$function}";

        if (is_callable($fn)) {
            return $fn(...$args);
        }

        throw new BadMethodCallException(
            sprintf('Call to undefined function %s()', $function)
        );
    }

    /**
     * Register functions accessible in Twig templates.
     * 
     * @return void
     */
    private function initRegisteredClasses(): void
    {
        if(self::$isClassedMapped){
            return;
        }

        self::$classes = array_merge(
            self::$classes, 
            $this->registerClasses()
        );
        self::$isClassedMapped = true;
    }

    /**
     * Register Twig functions accessible in Twig templates.
     *
     * @return TwigFunction[] Return array containing Twig functions.
     */
    private function registerCoreFunctions(): array
    {
        return [
            new TwigFunction(
                'now',
                static fn(DateTimeZone|string|null $timezone = null): \DateTimeImmutable => Time::now($timezone)
            ),

            new TwigFunction(
                'fn', 
                static fn (string $fn, mixed ...$args): mixed => self::callFunctions($fn, ...$args)
            ),
            new TwigFunction(
                'func', 
                static fn (string $fn, mixed ...$args): mixed => self::callFunctions($fn, ...$args)
            ),
            new TwigFunction(
                'function', static fn (string $fn, mixed ...$args): mixed => self::callFunctions($fn, ...$args)
            ),

            new TwigFunction('const', static function (string $constant): mixed {
                if (defined($constant)) {
                    return constant($constant);
                }

                throw new BadMethodCallException(sprintf('Constant "%s" is not defined.', $constant));
            }),

            new TwigFunction('static', static function (string $class, string $method, mixed ...$arguments): mixed {
                $class = self::$classes[$class] ?? $class;

                if (!class_exists($class)) {
                    throw new ClassException(sprintf('Class "%s" does not exist.', $class));
                }

                if (property_exists($class, $method)) {
                    return $class::${$method};
                }

                if (method_exists($class, $method)) {
                    return $class::{$method}(...$arguments);
                }

                throw new BadMethodCallException(sprintf(
                    'Method or property "%s" does not exist in class "%s".', 
                    $method, 
                    $class
                ));
            }),

            new TwigFunction('new', static function (string $class, mixed ...$arguments): ?object {
                $class = self::$classes[$class] ?? $class;

                if (!class_exists($class)) {
                    throw new ClassException(sprintf('Class "%s" does not exist.', $class));
                }

                try{
                    if (class_exists($class)) {
                        return new $class(...$arguments);
                    }
                }catch(Throwable $e){
                    throw new RuntimeException(sprintf(
                        'Unable to instantiate class "%s": %s', 
                        $class, 
                        $e->getMessage()
                    ), $e->getCode(), $e);
                }

                return null;
            })
        ];
    }
}