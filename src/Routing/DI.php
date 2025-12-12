<?php
declare(strict_types=1);
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
use Luminova\Luminova;
use Luminova\Http\Request;
use \Psr\Log\LoggerInterface;
use Luminova\Sessions\Session;
use Luminova\Template\Response;
use Luminova\Http\Client\Novio;
use Luminova\Logger\NovaLogger;
use Luminova\Exceptions\ClassException;
use Luminova\Cookies\{Cookie, FileJar};
use Luminova\Interface\ResponseInterface;
use Luminova\Http\Message\Response as Message;
use Luminova\Components\Email\Clients\NovaMailer;
use \Psr\Http\Message\RequestInterface as PsrRequestInterface;
use \Psr\Http\Message\ResponseInterface as MessageInterface;
use Luminova\Interface\{
    ClientInterface,
    CookieInterface,
    SessionInterface,
    MailerInterface,
    CookieJarInterface,
    InvokableInterface,
    RequestInterface,
    ContentResponseInterface,
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
     * The class exposes a public static `getInstance()`.
     * 
     * @var int T_SINGLETON
     */
    public const T_SINGLETON = 1;
 
    /**
     * The class can be created with `new`.
     * 
     * @var int T_INSTANTIATABLE 
     */
    public const T_INSTANTIATABLE = 2;

    /**
     * User-defined bindings.
     * 
     * @var array<class-string,array{class:class-string,singleton:bool,instance:object}> $bindings
     */
    private static array $bindings = [];

    /**
     * Register a transient class or interface binding in the Dependency Injection (DI) container.
     *
     * A new instance of the resolved dependency is created each time the binding
     * is requested from the container.
     *
     * This binding allows Luminova's DI system to automatically resolve and provide
     * the registered implementation to routable controller methods or closures.
     *
     * @template T of object
     *
     * @param class-string<T> $abstract The class or interface name to bind.
     * @param (Closure():T)|class-string<T> $resolver The dependency resolver, either:
     *        - A class name for automatic instantiation, or
     *        - A callable that returns an object.
     *
     * @return void
     * @throws ClassException If the abstract class or interface does not exist.
     * @throws ClassException If the abstract or resolver is invalid.
     * 
     * @see singleton() For registering a shared singleton instance.
     * @see unbind() For removing a registered binding.
     *
     * @example - Defining a dependency:
     *
     * ```php
     * DI::bind(\App\Utils\Test::class, function () {
     *     return new \App\Utils\Test('Hello world!');
     * });
     * ```
     *
     * > **Note:**
     * > Use `bind()` when a new instance should be created each time the dependency
     * > is resolved. Prefer class names for simple dependencies, and use closures
     * > or invokable objects when custom initialization is required.
     */
    public static function bind(string $abstract, Closure|string $resolver): void
    {
        self::assertAbstract($abstract);
        self::assertResolver($resolver);

        self::$bindings[$abstract] = [
            'singleton' => false,
            'class'     => $resolver
        ];
    }

    /**
     * Register a singleton class or interface binding in the Dependency Injection (DI) container.
     *
     * The dependency is created once when first resolved and the same instance is
     * returned for all subsequent resolutions during the application's lifecycle.
     *
     * This is useful for shared services that should maintain a single instance,
     * such as configuration managers, service managers, or other application-wide
     * dependencies.
     *
     * @template T of object
     *
     * @param class-string<T> $abstract The class or interface name to bind.
     * @param InvokableInterface|(Closure():T)|class-string<T> $resolver The dependency resolver, either:
     *        - A class name for automatic instantiation, or
     *        - A callable that returns an object.
     *
     * @return void
     * @throws ClassException If the abstract class or interface does not exist.
     * @throws ClassException If the abstract or resolver is invalid.
     * 
     * @see bind() For registering a dependency that creates a new instance when resolved.
     * @see unbind() For removing a registered binding.
     *
     * @example - Defining a singleton dependency:
     *
     * ```php
     * DI::singleton(\App\Services\ExampleService::class, function () {
     *     return new \App\Services\ExampleService();
     * });
     * ```
     *
     * > **Note:**
     * > Use `singleton()` when the same dependency instance should be shared across
     * > all resolutions. Avoid using it for services that require a fresh instance
     * > for each use.
     */
    public static function singleton(string $abstract, Closure|string $resolver): void
    {
        self::assertAbstract($abstract);
        self::assertResolver($resolver);

        self::$bindings[$abstract] = [
            'singleton' => true,
            'class'     => $resolver
        ];
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
     * @see self::bind()
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
     * @param class-string $abstract Fully qualified class or interface name.
     * 
     * @return bool Return true if the class can be resolved (either bound or has a default mapping),
     *              false if it cannot be resolved.
     */
    public static function has(string $abstract): bool 
    {
        return self::isBound($abstract) 
            || self::getDefault($abstract) !== null;
    }    

    /**
     * Determine if a class or interface is explicitly registered in the DI system.
     *
     * Unlike {@see self::has()}, this method only checks if the class
     * was manually bound using DI::bind() or the application helper method.
     * It does NOT check for any default mappings.
     *
     * @param class-string $abstract Fully qualified class or interface name.
     * 
     * @return bool Return true if the class is explicitly registered (bound),
     *              false otherwise.
     */
    public static function isBound(string $abstract): bool 
    {
        return isset(self::$bindings[$abstract]);
    } 

    /**
     * Resolve and create a new instance of a class or its interface binding.
     *
     * This method attempts to:
     * 1. Retrieve the class or factory callable registered in the DI container.
     * 2. Fall back to a default implementation if no explicit binding exists.
     * 3. Instantiate the resolved class or execute the factory to obtain an object.
     *
     * @param class-string $abstract Fully qualified class or interface name.
     * 
     * @return object|null Return the resolved object instance, or null if it cannot be resolved.
     */
    public static final function resolve(string $abstract): ?object
    {
        $class = null;
        $isSingleton = false;

        if(self::isBound($abstract)){
            $isSingleton = (self::$bindings[$abstract]['singleton'] ?? false);

            if($isSingleton && isset(self::$bindings[$abstract]['instance'])){
                return self::$bindings[$abstract]['instance'];
            }

            $class = self::$bindings[$abstract]['class'];
        }

        $class ??= self::getDefault($abstract);

        if($class === null){
            return null;
        }

        if(!$isSingleton){
            return self::instantiate($class);
        }

        return self::$bindings[$abstract]['instance'] = self::instantiate($class);
    }

    /**
     * Check whether a class can be instantiated.
     *
     * This method determines if a class supports:
     *
     * 1. **Direct instantiation** using `new Foo()`.
     * 2. **Singleton access** through a public and static `getInstance()` method.
     *
     * It also reports an instantiation mode:
     * - `T_INSTANTIATABLE` — the class can be created with `new`.
     * - `T_SINGLETON` — the class exposes a public static `getInstance()`.
     *
     * If instantiation is not possible, an error describing the problem is provided.
     *
     * @param string $class  Fully-qualified class name.
     * @param int|null &$mode Output instantiation mode ("1", "2", or null).
     * @param Throwable|null &$error Filled with a ClassException explaining why instantiation failed.
     *
     * @return bool  Return true if the class can be instantiated or obtained through its singleton method.
     *
     * @example - Example:
     *   $type = null;
     *   $error = null;
     *
     *   if (DI::isInstantiable(App\Services\Mailer::class, $type, $error)) {
     *       if ($type === DI::T_INSTANTIATABLE) {
     *           $mailer = new App\Services\Mailer();
     *       } elseif ($type === DI::T_SINGLETON) {
     *           $mailer = App\Services\Mailer::getInstance();
     *       }
     *   } else {
     *       // handle failure
     *       echo $error->getMessage();
     *   }
     */
    public static function isInstantiable(
        string $class,
        ?int &$mode = null,
        ?Throwable &$error = null
    ): bool 
    {
        $mode = null;
        $err = null;

        try {
            $ref = new ReflectionClass($class);

            if ($ref->isInterface()) {
                $err = 'Class %s is an interface and requires a registered implementation.';
            } elseif ($ref->isTrait()) {
                $err = 'Class %s is a trait and cannot be resolved.';
            } else{
                if ($ref->isInstantiable()) {
                    $mode = self::T_INSTANTIATABLE;
                    return true;
                }

                if ($ref->hasMethod('getInstance')) {
                    $method = $ref->getMethod('getInstance');

                    if ($method->isStatic() && $method->isPublic()) {
                        $mode = self::T_SINGLETON;
                        return true;
                    }

                    $err = 'Class %s cannot be instantiated, ';
                    $err .= 'nor implement a static getInstance() for singleton object.';
                }
                elseif ($ref->hasMethod('__construct')) {
                    $ctor = $ref->getConstructor();

                    if ($ctor instanceof \ReflectionMethod && $ctor->isPrivate()) {
                        $err = 'Class %s constructor is not public.';
                    }
                }

                $err ??= 'Class %s cannot be instantiated.';
            }
            $error = new ClassException(sprintf($err, $class));
        } catch (Throwable $e) {
            $error = new ClassException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Resolve and initialize class.
     *
     * @param mixed $resolver
     * 
     * @return object|null
     */
    private static function instantiate(mixed $resolver): ?object
    {
        if ($resolver === null) {
            return null;
        }

        if(is_object($resolver)){
            return $resolver;
        }

        if($resolver instanceof Closure){
            $result = $resolver();

            if(is_object($result)){
                return $result;
            }

            return null;
        }

        return class_exists($resolver) 
            ? new $resolver() 
            : null;
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
            MessageInterface::class,
            ResponseInterface::class            => Message::class,
            RequestInterface::class, 
            PsrRequestInterface::class          => Request::class,
            ClientInterface::class              => Novio::class,
            SessionInterface::class             => Session::class,
            MailerInterface::class              => NovaMailer::class,
            LoggerInterface::class              => NovaLogger::class,
            ContentResponseInterface::class     => Response::class,
            CookieInterface::class              => fn(): CookieInterface => new Cookie('_default'),
            CookieJarInterface::class           => fn(): CookieJarInterface => new FileJar(
                Luminova::root('/writeable/temp/', 'cookies.txt')
            ),
            default => null,
        };
    }

    /**
     * Assert dependency resolver.
     *
     * @template T of object
     * 
     * @param (Closure():T)|class-string<T> $resolver The concrete class or closure that resolves the dependency.
     *
     * @return void
     * @throws ClassException If the abstract or resolver is invalid.
     */
    private static function assertResolver(Closure|string $resolver): void
    {
        if($resolver instanceof Closure || class_exists($resolver)){
            return;
        }

        throw new ClassException(
            "DI binding resolver [{$resolver}] does not exist."
        );
    }

    /**
     * Assert class or interface binding container.
     *
     * @param class-string $abstract The class or interface name to unbind.
     *
     * @return void
     * @throws ClassException If the abstract class or interface does not exist.
     */
    private static function assertAbstract(string $abstract): void
    {
        if (!class_exists($abstract) && !interface_exists($abstract)) {
            throw new ClassException(
                "DI binding target [{$abstract}] does not exist."
            );
        }
    }
}