<?php
/**
 * Luminova Framework Dependency Injection System.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Routing;

use \Closure;
use \Throwable;
use \ReflectionClass;
use \Luminova\Security\JWT;
use \Luminova\Http\Request;
use \Luminova\Cache\RedisCache;
use \Luminova\Http\Client\Novio;
use \Luminova\Sessions\Session;
use \Luminova\Template\Response;
use function \Luminova\Funcs\root;
use \Luminova\Components\Seo\Schema;
use \Luminova\Components\Email\Mailer;
use \Luminova\Components\Collections\Arr;
use \Luminova\Foundation\Module\Factory;
use \Luminova\Cookies\{Cookie, FileJar};
use \Luminova\Exceptions\ClassException;
use \Luminova\Components\Languages\Translator;
use \Luminova\Cache\{FileCache, MemoryCache};
use \Luminova\Notifications\Firebase\Notification;
use \Luminova\Security\Encryption\{Sodium, Openssl};
use \Psr\Http\Message\RequestInterface as PsrRequestInterface;
use \Luminova\Interface\{
    ClientInterface,
    CookieInterface,
    CookieJarInterface,
    InvokableInterface,
    RequestInterface,
    ViewResponseInterface,
};

/**
 * Dependency Injection Manager
 * 
 * @see https://luminova.ng/docs/0.0.0/routing/dependency-injection
 * 
 * @example - Defining dependencies:
 * ```php
 * namespace App;
 * 
 * class Application extends Luminova\Foundation\Core\Application
 * {
 *     protected function onPreCreate(): void 
 *     {
 *         // Using the application helper method
 *         $this->bind(\App\Utils\Test::class, function () {
 *             return new \App\Utils\Test('Hello world!');
 *         });
 *     }
 * }
 * ```
 * 
 * @example Usage inside a controller:
 * 
 * ```php
 * #[Route('/test', methods: ['GET'])]
 * public function testCase(\App\Utils\Test $test): int
 * {
 *     echo $test->getValue();
 *     return STATUS_SUCCESS;
 * }
 * ```
 */
class DI
{
    /**
     * User-defined bindings.
     * 
     * @var array<class-string,Closure|string> $bindings
     */
    private static array $bindings = [];

    /**
     * Register a class or interface binding in the Dependency Injection (DI) container.
     *
     * This binding allows Luminova's DI system to automatically provide the correct 
     * implementation to your routable controller methods or closures.
     *
     * @template T of object
     * 
     * @param class-string<T> $abstract The class or interface name to bind.
     * @param (callable():T)|InvokableInterface|class-string<T> $resolver Abstract resolver, either:
     *        - A class name (simple instantiation), or
     *        - A callable/Invokable object (for custom initialization logic).
     * 
     * @return void
     * @see unbind().
     * 
     * @example - Defining dependencies:
     * 
     * ```php
     * DI::bind(\App\Utils\Test::class, function () {
     *      return new \App\Utils\Test('Hello world!');
     * });
     * ```
     * 
     * > **Note:** 
     * > Prefer binding by class name for simple, stateless objects. 
     * > Use closures or Invokable objects when additional constructor logic or configuration is required.
     */
    public static function bind(string $abstract, callable|string $resolver): void 
    {
        self::$bindings[$abstract] = $resolver;
    }

    /**
     * Remove a class or interface binding from the Dependency Injection (DI) container.
     *
     * After unbinding, the class or interface will no longer be resolved 
     * through the DI system unless it has a default mapping defined.
     *
     * @param class-string $abstract The class or interface name to unbind.
     * 
     * @return void
     * @see bind()
     * 
     * @example - Unbinding a service:
     * ```php
     * use Luminova\Routing\DI;
     * 
     * // Remove a specific binding
     * DI::unbind(\App\Utils\Test::class);
     * 
     * // Attempting to resolve now will return null
     * $service = DI::resolve(\App\Utils\Test::class); // null
     * ```
     * 
     * > **Note:** This method will silently do nothing if the binding does not exist. 
     * > It is safe to call repeatedly without additional checks.
     */
    public static function unbind(string $abstract): void 
    {
        unset(self::$bindings[$abstract]);
    }

    /**
     * Determine if a class or interface can be resolved by the DI system.
     *
     * This method checks whether the class is explicitly bound 
     * or if Luminova can provide a default implementation automatically.
     *
     * @param class-string $class Fully qualified class or interface name.
     * 
     * @return bool Return true if the class can be resolved (either bound or has a default mapping),
     *              false if it cannot be resolved.
     */
    public static function has(string $class): bool 
    {
        return isset(self::$bindings[$class]) || self::getDefault($class) !== null;
    }    

    /**
     * Determine if a class or interface is explicitly registered in the DI system.
     *
     * Unlike {@see self::has()}, this method only checks if the class
     * was manually bound using DI::bind() or the application helper method.
     * It does NOT check for any default mappings.
     *
     * @param class-string $class Fully qualified class or interface name.
     * 
     * @return bool Return true if the class is explicitly registered (bound),
     *              false otherwise.
     */
    public static function isBound(string $class): bool 
    {
        return isset(self::$bindings[$class]);
    }    

    /**
     * Resolve and create a new instance of a class or its interface binding.
     *
     * This method attempts to:
     * 1. Retrieve the class or factory callable registered in the DI container.
     * 2. Fall back to a default implementation if no explicit binding exists.
     * 3. Instantiate the resolved class or execute the factory to obtain an object.
     *
     * @template T of object
     * @param class-string<T> $abstract Fully qualified class or interface name.
     * 
     * @return \T|null Return the resolved object instance, or null if it cannot be resolved.
     */
    public static final function resolve(string $abstract): ?object
    {
        $resolver = self::$bindings[$abstract] ?? self::getDefault($abstract);

        if ($resolver === null) {
            return null;
        }

        if(is_callable($resolver)){
            return $resolver();
        }

        return class_exists($resolver) ? new $resolver() : null;
    }

    /**
     * Check whether a class can be instantiated.
     *
     * This method determines if a class supports:
     *
     * 1. **Direct instantiation** using `new Foo()`.
     * 2. **Singleton access** through a public and static `getInstance()` method.
     *
     * It also reports a type identifier:
     * - `"instantiate"` — the class can be created with `new`.
     * - `"singleton"` — the class exposes a public static `getInstance()`.
     *
     * If instantiation is not possible, an error describing the problem is provided.
     *
     * @param string $class  Fully-qualified class name.
     * @param string|null &$type Output type identifier ("instantiate", "singleton", or null).
     * @param Throwable|null &$error Filled with a ClassException explaining why instantiation failed.
     *
     * @return bool  Return true if the class can be instantiated or obtained through its singleton method.
     *
     * @example - Example:
     *   $type = null;
     *   $error = null;
     *
     *   if (DI::isInstantiable(App\Services\Mailer::class, $type, $error)) {
     *       if ($type === 'instantiate') {
     *           $mailer = new App\Services\Mailer();
     *       } elseif ($type === 'singleton') {
     *           $mailer = App\Services\Mailer::getInstance();
     *       }
     *   } else {
     *       // handle failure
     *       echo $error->getMessage();
     *   }
     */
    public static function isInstantiable(
        string $class,
        ?string &$type = null,
        ?Throwable &$error = null
    ): bool 
    {
        $type = null;
        $err = null;

        try {
            $ref = new ReflectionClass($class);

            if ($ref->isInstantiable()) {
                $type = 'instantiate';
                return true;
            }

            if ($ref->hasMethod('getInstance')) {
                $method = $ref->getMethod('getInstance');

                if ($method->isStatic() && $method->isPublic()) {
                    $type = 'singleton';
                    return true;
                }

                $err = 'Class %s has getInstance() but it is not public or not static.';
            }
            elseif ($ref->hasMethod('__construct')) {
                $ctor = $ref->getConstructor();

                if ($ctor && !$ctor->isPublic()) {
                    $err = 'Class %s constructor is not public.';
                }
            }

            $err ??= 'Class %s cannot be instantiated.';
            $error = new ClassException(sprintf($err, $class));

        } catch (Throwable $e) {
            $error = new ClassException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Default resolver mappings for core classes/interfaces.
     *
     * @param class-string $class The class or interface name.
     * 
     * @return Closure|class-string|null Return class name or closure that resolves to class object.
     */
    private static function getDefault(string $class): Closure|string|null
    {
        return match ($class) {
            Request::class, RequestInterface::class, PsrRequestInterface::class      => Request::class,
            Novio::class, ClientInterface::class              => Novio::class,
            Response::class, ViewResponseInterface::class    => Response::class,
            Session::class       => Session::class,
            Factory::class       => Factory::class,
            Schema::class        => Schema::class,
            Mailer::class        => Mailer::class,
            FileCache::class     => FileCache::class,
            MemoryCache::class   => MemoryCache::class,
            RedisCache::class    => RedisCache::class,
            Openssl::class       => Openssl::class,
            Arr::class           => Arr::class,
            Sodium::class        => Sodium::class,
            JWT::class           => JWT::class,
            Translator::class    => Translator::class,
            Notification::class  => Notification::class,
            Cookie::class, CookieInterface::class            => fn(): CookieInterface => new Cookie('_default'),
            FileJar::class, CookieJarInterface::class  => fn(): CookieJarInterface => new FileJar(
                root('/writeable/temp/', 'cookies.txt')
            ),
            default => null,
        };
    }
}