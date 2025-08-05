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
use \Luminova\Security\JWT;
use \Luminova\Cache\RedisCache;
use \Luminova\Http\Client\Novio;
use \Luminova\Sessions\Session;
use \Luminova\Template\Response;
use function \Luminova\Funcs\root;
use \Luminova\Component\Seo\Schema;
use \Luminova\Utility\Email\Mailer;
use \Luminova\Http\{Request, Network};
use \Luminova\Utility\Collections\Arr;
use \Luminova\Foundation\Module\Factory;
use \Luminova\Component\Languages\Translator;
use \Luminova\Cache\{FileCache, MemoryCache};
use \Luminova\Cookies\{Cookie, CookieFileJar};
use \Luminova\Notifications\Firebase\Notification;
use \Luminova\Security\Encryption\{Sodium, Openssl};
use \Luminova\Interface\{
    ClientInterface,
    CookieInterface,
    NetworkInterface,
    CookieJarInterface,
    InvokableInterface,
    HttpRequestInterface,
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
     * Default resolver mappings for core classes/interfaces.
     *
     * @param class-string $class The class or interface name.
     * 
     * @return Closure|class-string|null Return class name or closure that resolves to class object.
     */
    private static function getDefault(string $class): Closure|string|null
    {
        return match ($class) {
            Request::class, HttpRequestInterface::class      => Request::class,
            Network::class, NetworkInterface::class          => Network::class,
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
            CookieFileJar::class, CookieJarInterface::class  => fn(): CookieJarInterface => new CookieFileJar(
                root('/writeable/temp/', 'cookies.txt')
            ),
            default => null,
        };
    }
}