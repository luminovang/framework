<?php
declare(strict_types=1);
/**
 * Luminova Framework core application class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Foundation\Core;

use \Closure;
use \Throwable;
use App\Kernel;
use Luminova\Runtime;
use Luminova\Luminova;
use Luminova\Routing\{Router, DI};
use Luminova\Foundation\Error\Error;
use Luminova\Interface\{RouterInterface, LazyObjectInterface};
use Luminova\Exceptions\{ClassException, RuntimeException, BadMethodCallException};

/**
 * Base class for the application.
 *
 * Extend this class once to define your application's core behavior.
 * 
 * The extended implementation must be located at `/app/Application.php`.
 * 
 * @see Kernel::getApplication()
 * 
 * @phpstan-type Events 
 *      'onStart'|
 *      'onFinish'|
 *      'onDestroy'|
 *      'onShutdown'|
 *      'onTerminated'|
 *      'onRouteResolved'
 * 
 * @phpstan-type CoreKernelServices
 *     'http.client'|
 *     'logger'|
 *     'mailer'|
 *     'session'|
 *     'routing'|
 *     'application'|
 *     'memcached'|
 *     'redis'|
 *     'cache'|
 *     'cache.id'|
 *     'app.keys'|
 *     class-string<ClientInterface>|
 *     class-string<HttpClientInterface>|
 *     class-string<LoggerInterface>|
 *     class-string<MailerInterface>|
 *     class-string<SessionInterface>|
 *     class-string<RouterInterface>|
 *     class-string<Application>|
 *     class-string<Memcached>|
 *     class-string<Redis>|
 *     class-string<BaseCache>
 */
abstract class Application implements LazyObjectInterface
{
    /**
     * Application boot state idle.
     * 
     * @var int IDLE 
     */
    public final const IDLE = 0;

    /**
     * Application boot state initialized.
     * 
     * @var int CREATED 
     */
    public final const CREATED = 1;

    /**
     * Application boot state completed.
     * 
     * @var int COMPLETED 
     */
    public final const COMPLETED = 2;

    /**
     * Application boot state terminated.
     * 
     * @var int TERMINATED 
     */
    public final const TERMINATED = 3;

    /**
     * Instance of the Router class.
     *
     * @var RouterInterface $router
     */
    public readonly RouterInterface $router;

    /**
     * Allows direct PHP include/require statements in this class.
     *
     * When enabled, the debugger will skip include/require enforcement.
     *
     * @var bool $allowIncludes
     * @see #[AllowIncludes] Class level attribute
     */
    protected bool $allowIncludes = false;

    /**
     * Singleton instance of Application.
     *
     * @var static|null $instance
     */
    private static ?self $instance = null;

    /**
     * Application is state lifecycle.
     *
     * @var int $lifecycle
     */
    private static int $lifecycle = self::IDLE;

    /**
     * Application hooks that can be triggered more than once.
     *
     * @var array<string,true> $hooks
     */
    private static array $hooks = [
        'onStart'           => true,
        'onFinish'          => true,
        'onDestroy'         => true,
        'onShutdown'        => true,
        'onTerminated'      => true,
        'onRouteResolved'   => true,
    ];

    /**
     * Initialize the core application.
     *
     * The application lifecycle is executed only once by default. Subsequent
     * application instances skip lifecycle initialization when the application
     * has already progressed beyond the idle state.
     *
     * When `$recreate` is enabled, the full application lifecycle is executed
     * again, allowing the application to be rebuilt when required.
     *
     * The lifecycle includes bootstrapping, router initialization, and the
     * application creation hooks.
     *
     * @param bool $rebuilt Whether to force the application lifecycle to run
     *        again, even if the application has already been initialized.
     *
     * @note When an application instance has already been initialized, its
     *       lifecycle hooks are not executed again unless `$recreate` is `true`.
     * 
     * @see self::onCreate()
     * @see self::onPreCreate()
     */
    public function __construct(private bool $rebuilt = false) 
    {
        try {
            if (!$this->rebuilt && self::$lifecycle > self::IDLE) {
                // Prevent the routing system and other bootstrapping logic from
                // being initialized again when another application instance is
                // created after the application has already booted.
                return;
            }

            $this->onBoot();
        } finally {
            if (
                PRODUCTION 
                || !$this->allowIncludes
                || self::$lifecycle === self::TERMINATED
            ) {
                return;
            }

            // Enforce the include-file coding standard during development.
            \Luminova\Debugger\Tracer::assertNoIncludes($this);
        }
    }

    /**
     * Core application destruct.
     * 
     * @return void 
     */
    public function __destruct()
    {
        if(self::$lifecycle > self::CREATED){
            return;
        }

        self::$lifecycle = self::COMPLETED;
        $this->onDestroy();
    }

    /**
     * Bind a class or interface to a resolver for dependency injection (DI).
     *
     * The binding allows Luminova's DI system to resolve the specified
     * implementation when the abstract class or interface is requested.
     * A new instance is created each time the dependency is resolved.
     *
     * @template T of object
     *
     * @param class-string<T> $abstract The class or interface name to bind.
     * @param (Closure():T)|class-string<T> $resolver The concrete class or closure that resolves the dependency.
     *
     * @return self Returns the application instance.
     * @throws ClassException If the abstract class or interface does not exist.
     * @throws ClassException If the abstract or resolver is invalid.
     *
     * @see DI
     * @see self::singleton() For registering a shared dependency instance.
     * @see self::unbind() For removing a registered binding.
     * @link https://luminova.ng/docs/0.0.0/routing/dependency-injection
     *
     * @example - Binding an interface:
     * ```php
     * $this->bind(MyInterface::class, MyConcreteClass::class);
     * ```
     *
     * @example - Binding with custom initialization:
     * ```php
     * $this->bind(\Psr\Log\LoggerInterface::class, function () {
     *     return new \MyApp\Log\FileLogger('/writable/logs/app.log');
     * });
     * ```
     *
     * > **Note:**
     * > Prefer class names for simple bindings. Use closures when the dependency
     * > requires custom initialization or configuration.
     * >
     * > **Recommended:** Register in `onCreate()` or `onPreCreate()` method.
     */
    protected final function bind(string $abstract, Closure|string $resolver): self
    {
        DI::bind($abstract, $resolver);

        return $this;
    }

    /**
     * Register a class or interface as a singleton dependency.
     *
     * The dependency is created once when first resolved and the same instance
     * is returned for subsequent resolutions during the application's lifecycle.
     *
     * @template T of object
     *
     * @param class-string<T> $abstract The class or interface name to bind.
     * @param (Closure():T)|class-string<T> $resolver The concrete class or closure that resolves the dependency.
     *
     * @return self Returns the application instance.
     * @throws ClassException If the abstract class or interface does not exist.
     * @throws ClassException If the abstract or resolver is invalid.
     *
     * @see DI
     * @see self::bind() For registering a dependency that creates a new instance when resolved.
     * @see self::unbind() For removing a registered binding.
     *
     * @example - Binding a singleton:
     * ```php
     * $this->singleton(
     *     \App\Services\ExampleService::class,
     *     \App\Services\ExampleService::class
     * );
     * ```
     *
     * @example - Binding with custom initialization:
     * ```php
     * $this->singleton(\App\Services\ExampleService::class, function () {
     *     return new \App\Services\ExampleService();
     * });
     * ```
     *
     * > **Note:**
     * > Use `singleton()` when the same dependency instance should be shared
     * > across multiple resolutions.
     * >
     * > **Recommended:** Register in `onCreate()` or `onPreCreate()` method.
     */
    protected final function singleton(string $abstract, Closure|string $resolver): self
    {
        DI::singleton($abstract, $resolver);

        return $this;
    }

    /**
     * Remove a class or interface binding from the Dependency Injection (DI) container.
     *
     * After unbinding, the dependency will no longer use the registered binding
     * when resolved through the DI system.
     *
     * @param class-string $abstract The class or interface name to unbind.
     *
     * @return void
     *
     * @see DI
     * @see self::bind() For registering a transient dependency.
     * @see self::singleton() For registering a shared dependency instance.
     *
     * @example - Removing a binding:
     * ```php
     * $this->unbind(\App\Services\ExampleService::class);
     * ```
     *
     * > **Note:**
     * > Calling this method has no effect when the binding does not exist.
     */
    protected final function unbind(string $abstract): void
    {
        DI::unbind($abstract);
    }

    /**
     * Trigger protected application lifecycle hooks.
     *
     * This method calls the matching `on*` method if it is supported. 
     * Unknown hooks throws an exception.
     * 
     * **Hooks:**
     * 
     * - `onStart` - {@see self::onStart()}
     * - `onFinish` - {@see self::onFinish()}
     * - `onDestroy` - {@see self::onDestroy()}
     * - `onShutdown` - {@see self::onShutdown()}
     * - `onTerminated` - {@see self::onTerminated()}
     * - `onRouteResolved` - {@see self::onRouteResolved()}
     *
     * @param Events|string $hook Hook method name 
     *                      (e.g. `onStart`, `onDestroy`).
     * @param mixed ...$arguments Optional arguments passed to the hook.
     * 
     * @return mixed Return result of triggered hook if any.
     * @throws BadMethodCallException If invalid event hook is provided.
     */
    public final function trigger(string $hook, mixed ...$arguments): mixed
    {
        if (!isset(self::$hooks[$hook])) {
            throw new BadMethodCallException("Hook: '{$hook}' is not allowed.");
        }

        $isTermination = false;

        if ($hook === 'onTerminated') {
            $isTermination = true;
            $info = (array) ($arguments[0] ?? []);

            $arguments[0] = $info + [
                'uri'     => Router::getUriPath(),
                'context' => Runtime::isCommand() ? 'CLI' : 'HTTP',
            ];
        }

        try{
            $result = $this->{$hook}(...$arguments);
        } finally {
            if($isTermination){
                self::$lifecycle = self::TERMINATED;
            }
        }

        return $result;
    }

    /**
     * Resolve a service through the application kernel or return the kernel instance.
     *
     * When `$service` is `null`, the kernel instance is returned. Otherwise, the
     * service is resolved using the application kernel.
     *
     * **Service Name {@see Kernel::SERVICE_*}:**
     *
     * - `http.client`  — HTTP client service.
     * - `logger`       — Logger service.
     * - `mailer`       — Mailer service.
     * - `session`      — Session client service.
     * - `routing`      — Routing system service.
     * - `application`  — Application service.
     * - `memcached`    — Memcached service.
     * - `redis`        — Redis service.
     * - `cache`        — Cache service.
     *
     * @param CoreKernelServices|string|null $service The service identifier,
     *        abstract-class/interface name, or `null` to return the kernel instance.
     * @param bool $shared Whether to reuse a shared instance when supported.
     * @param mixed ...$arguments Arguments passed to the service resolver.
     *
     * @return Kernel|mixed The resolved service or kernel result.
     *
     * @throws RuntimeException If the requested service cannot be resolved.
     * @throws ClassException If the service/abstract is not available.
     *
     * @see Kernel::create() To create or retrieve the kernel instance.
     * @see Kernel::resolve() To resolve a service through the kernel.
     * @see Kernel::has() To check whether a service is registered.
     * @see Kernel::get() To retrieve a registered service.
     *
     * @example - Get the HTTP client:
     * ```php
     * $http = $app->make('http.client');
     * ```
     *
     * @example - Get the logger:
     * ```php
     * $logger = $app->make('logger');
     * ```
     *
     * @example - Get the kernel:
     * ```php
     * $kernel = $app->make();
     * ```
     *
     * @link https://luminova.ng/docs/0.0.0/foundation/kernel
     */
    public final function make(
        ?string $service = null,
        bool $shared = true,
        mixed ...$arguments
    ): mixed 
    {
        if ($service === null) {
            return Kernel::create($shared);
        }

        return Kernel::resolve(
            $service,
            $shared,
            ...$arguments
        );
    }

    /**
     * Application pre create lifecycle hook.
     * 
     * The onPreCreate hook is triggered once, immediately after application class is initialized 
     * before routing system runs. This allows you to override or create 
     * a custom initialization logic before routing system starts.
     * 
     * @return void
     * @see self::onCreate()
     * @see self::onStart()
     * @see self::onFinish()
     * 
     * @example - Example using Luminova Rate Limiter:
     * 
     * ```php
     * use Luminova\Security\RateLimiter;
     * protected function onPreCreate(): void 
     * {
     *      $rate = new RateLimiter();
     *      if(!$rate->check('key')->isAllowed()){
     *          $rate->respond();
     *          Luminova::terminate(429, 'Too many request'); // Optionally terminate application.
     *      }
     * }
     * ```
     * 
     * > **Note**
     * > Throwing exceptions here will cause application to terminate.
     */
    protected function onPreCreate(): void {}

    /**
     * Application post create lifecycle hook.
     * 
     * The onCreate hook is triggered once, after application class is initialized and routing system initialized.
     * This allows you to override properties and initialize other function requires in application.
     * 
     * @return void
     * @see self::onPreCreate()
     * @see self::onStart()
     * @see self::onFinish()
     * 
     * > **Note**
     * > Throwing exceptions here will cause application to terminate.
     */
    protected function onCreate(): void {}

    /**
     * Application destruction lifecycle hook.
     * 
     * The onDestroy hook is triggered once on object destruction.
     * Override in subclasses for custom cleanup or logging.
     * 
     * @return void
     * @example - Example:
     * 
     * Optionally Add `gc_collect_cycles()` to your onDestroy hook to forces collection of any existing garbage cycles.
     * 
     * ```
     * protected function onDestroy(): void 
     * {
     *      gc_collect_cycles();
     * }
     * ```
     */
    protected function onDestroy(): void {}

    /**
     * Application pre-request lifecycle hook.
     *
     * The onStart lifecycle hook is triggered before the application begins
     * handling an incoming request. It provides an opportunity to inspect the
     * initial request state before routing, middleware, and controller execution.
     * 
     * **Possible Info keys:**
     * - `context` - The application context (CLI or HTTP).
     * - `method`  - The HTTP request method.
     * - `uri`     - The request URI.
     * - `module`  - The HMVC URI module (same as prefix). 
     * - `prefix`  - The URI prefix. 
     *
     * @param array{
     *     context:string,
     *     method:?string,
     *     uri:string,
     *     module?:string,
     *     prefix?:string
     * } $info Request state information.
     *
     * @return void
     */
    protected function onStart(array $info): void {}

    /**
     * Application post-request lifecycle hook.
     *
     * The onFinish lifecycle hook is triggered after the request has been fully
     * handled, regardless of whether the request completed successfully or failed.
     * **Possible Info keys:**
     *
     * - `filename`      (string|null) Optional controller class file name.
     * - `namespace`     (string|null) Optional controller class namespace.
     * - `method`        (string|null) Optional controller class method name.
     * - `command`       (array|null) Optional executed command information for CLI.
     * - `controllers`   (int) Number of controller files scanned while parsing attributes.
     * - `isCache`       (bool) Whether a cached response was rendered instead of new content.
     * - `isStaticCache` (bool) Whether a static cached response was served
     *                      (e.g. `page.html`) instead of a regular cache entry
     *                      (e.g. `page`).
     *
     * @param array{
     *     filename:?string,
     *     namespace:?string,
     *     method:?string,
     *     command:?array,
     *     controllers:int,
     *     isCache:bool,
     *     isStaticCache:bool
     * } $info Information about the handled request, controller, or command execution.
     *
     * @return void
     *
     * @note
     * - This hook is called after request processing is complete.
     * - The response has already been sent or finalized and cannot be modified.
     */
    protected function onFinish(array $info): void {}

    /**
     * Application method-based routes lifecycle hook.
     * 
     * The onRouteResolved lifecycle hook is triggered after a method-based route context is resolved 
     * via (`/routes/`), based on URI prefix or CLI.
     *  
     * @param string $context The resolved prefix or context name loaded.
     * 
     * @return void 
     * @see ../../../routes/
     */
    protected function onRouteResolved(string $context): void {}

    /**
     * Application termination lifecycle hook.
     * 
     * The onTerminated lifecycle hook is triggered after the application terminates.
     * Use it for final cleanup, logging, or notifications.
     *
     * **Info array keys:**
     * - `status`  (int)    Termination status (HTTP or exit code)
     * - `message` (string) Termination message
     * - `title`   (string|null) Optional title
     * - `uri`     (string|null) Optional URI
     * - `context` (string) Execution context (`http` or `cli`)
     *
     * @param array{
     *      status: int,
     *      message: string,
     *      title: ?string,
     *      url: ?string,
     *      context: string
     * } $info Additional termination information.
     *
     * @example - Terminate:
     * 
     * ```php
     * if ($this->instance->ofSomethingIsTrue()) {
     *    Luminova::terminate(500, 'Error...');
     * }
     * ```
     * @example - Handle Termination:
     * ```php
     * namespace App;
     *
     * class Application extends Luminova\Foundation\Core\Application
     * {
     *     protected function onTerminated(array $info): void
     *     {
     *         Logger::debug('Application terminated', $info);
     *     }
     * }
     * ```
     *
     * > **Note:** 
     * >
     * > Triggered whenever `Luminova::terminate()` is called.
     */
    protected function onTerminated(array $info): void {}

    /**
     * Application error shutdown lifecycle hook.
     *
     * The onShutdown hook is called during application shutdown when the script
     * terminates because of an error. It provides the application with the final
     * opportunity to inspect the shutdown state and decide whether the framework
     * should continue its default error handling.
     *
     * Returning `true` allows the framework to continue processing the shutdown
     * error using its default error handling flow. Returning `false` stops the
     * framework error handling, allowing the application to take full control
     * (for example, custom logging, error reporting, or rendering a custom response).
     *
     * @param array{
     *     type:int,
     *     force?:bool,
     *     message:string,
     *     file:string,
     *     line:int
     * } $error Shutdown error information containing details about the error that
     * occurred before termination.
     *
     * @return bool Return `true` to continue with the framework shutdown handling,
     *              or `false` to take over shutdown handling.
     *
     * > **Note:**
     * > - This hook is only called when shutdown is caused by an error.
     * > - It is not triggered during normal application termination.
     * > - It does not run when `Luminova::terminate()` is called unless the
     * >  termination results in an error that triggers shutdown.
     */
    public function onShutdown(array $error): bool
    {
        return true;
    }

    /**
     * Set or replace the singleton instance with a new application object.
     * 
     * @param Application $new The new application object.
     * 
     * @return static Return the updated shared application instance.
     */
    public function setInstance(Application $new): static
    {
        return static::$instance = $new;
    }

    /**
     * Retrieve the shared application instance.
     *
     * If the Application has already been created, this method
     * will not trigger `onPreCreate` or `onCreate` creation lifecycle again.
     * 
     * This guarantees that the core services come from the initial Application object, 
     * while additional instances simply reuse them.
     * 
     * @param bool $rebuilt Wether to forces a full rebuild (default: false).
     *
     * @return static Return a shared application instance.
     */
    public static function getInstance(bool $rebuilt = false): static 
    {
        if(!static::$instance instanceof static){
            static::$instance = new static($rebuilt);
        }
       
        return static::$instance;
    }

    /**
     * Get application lifecycle state.
     * 
     * - `IDLE = 0`
     * - `CREATED = 1`
     * - `COMPLETED = 2`
     * - `TERMINATED = 3`
     *
     * @return int Return application lifecycle state.
     */
    public final static function getState(): int
    {
        return self::$lifecycle;
    }

    /**
     * Clear references when the Application instance is cloned.
     *
     * @internal Ensures clones start without internal bindings.
     */
    public function __clone() {}

    /**
     * @deprecated Use trigger instead.
     *
     * @param string $event
     * @param mixed ...$arguments
     * 
     * @return void
     */
    public final function __on(string $event, mixed ...$arguments): void
    {
        $this->trigger($event, $arguments);
    }

    /**
     * @deprecated Use {@see Router::getUriPath()}, {@see \Luminova\Http\Request::getUri()}
     * 
     * Get the current request URI.
     *
     * @return string|null Return the current request URI paths.
     * 
     * > **Note:**
     * > Return `null` if called in CLI environment.
     */
    public function getUri(): ?string 
    {
        return Runtime::isCommand() 
            ? null 
            : Router::getUriPath();
    }

    /**
     * Initialization and register application boot hooks.
     *
     * @return void
     */
    private function onBoot(): void 
    {
        try{
            $this->onPreCreate();

            if(isset($this->router)){
                $this->router->setApplication($this);
            } else {
                Runtime::permission('rw', silent: false);

                $this->router = $this->make(Kernel::SERVICE_ROUTING, true, $this);
            }

            $this->router->addNamespace(Runtime::isHmvc()
                ? '\\App\\Modules\\Controllers\\' 
                : '\\App\\Controllers\\'
            );

            $this->onCreate();
            self::$lifecycle = self::CREATED;
        }catch(Throwable $e){
            self::$lifecycle = self::TERMINATED;
            Error::shutdown([
                'type'    => E_ERROR,
                'force'   => true,
                'message' => sprintf(
                    'Application failed to initialize. ' . 
                    'An exception may have been thrown during onPreCreate or onCreate, ' . 
                    'or a file permission issue occurred. Code: %s, Error: <highlight color="red">%s</highlight>', 
                    $e->getCode(),
                    $e->getMessage()
                ),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}