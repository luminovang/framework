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
use \Luminova\Luminova;
use \Luminova\Routing\{Router, DI};
use \Luminova\Foundation\Error\Error;
use \Luminova\Exceptions\BadMethodCallException;
use \Luminova\Interface\{RouterInterface, LazyObjectInterface};

/**
 * Base class for the application.
 *
 * Extend this class once to define your application's core behavior.
 * 
 * The extended implementation must be located at `/app/Application.php`.
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
     * Allow only known lifecycle hooks
     *
     * @var string[] $hooks
     */
    private static array $hooks = [
        'onPreCreate',
        'onCreate',
        'onDestroy',
        'onStart',
        'onFinish',
        'onRouteResolved',
        'onTerminated',
        'onShutdown',
    ];

    /**
     * Mark object creation state.
     *
     * @var string $objectState
     */
    private string $objectState = 'new';

    /**
     * Core application constructor.
     * 
     * Builds the application only once. The first construction runs the full
     * lifecycle: onPreCreate, router setup, and onCreate. Any later
     * construction will skip all lifecycle work unless `$recreate` is true.
     * 
     * The `$recreate` flag forces a full rebuild, allowing the application to
     * run its lifecycle again when needed.
     * 
     * @param bool $recreate Wether to forces a full rebuild (default: false).
     *
     * > **Note:**
     * > If an application instance already exists, onCreate and onDestroy won't be called again.
     */
    public function __construct(private bool $recreate = false) 
    {
        if(!$this->recreate && self::$lifecycle > self::IDLE){
            // This is intentional, to prevent routing system initializing again 
            // in new application object if app class ic initialized again after boot
            return;
        }

        try{
            $this->onPreCreate();

            Luminova::permission('rw', throw: true);

            if(!isset($this->router)){
                $this->router = Luminova::kernel()->getRoutingSystem($this) 
                    ?? new Router($this);
            }

            $this->router->addNamespace(Luminova::isHmvc()
                ? '\\App\\Modules\\Controllers\\' 
                : '\\App\\Controllers\\'
            );

            $this->onCreate();
            self::$lifecycle = self::CREATED;
        }catch(Throwable $e){
            Error::shutdown([
                'type' => E_ERROR,
                'message' => sprintf(
                    'Application failed to initialize. ' . 
                    'An exception may have been thrown during onPreCreate or onCreate, ' . 
                    'or a file permission issue occurred. Code: %s, Error: "%s"', 
                    $e->getCode(),
                    $e->getMessage()
                ),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
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
     * Bind a class or interface for dependency injection (DI) in controller methods.
     * 
     * This allows you to map an interface or class name to a concrete implementation or a closure.
     * When a controller method type-hints a dependency, this binding ensures the right object is injected.
     *
     * @param class-string $abstract The interface or class name to bind.
     * @param (Closure():T)|class-string $resolver The concrete class or closure returning the instance.
     * 
     * @return static Return instance of application class.
     * @see DI
     * @see https://luminova.ng/docs/0.0.0/routing/dependency-injection
     * 
     * @example - Simple and advanced bindings:
     * ```php
     * namespace App;
     * 
     * class Application extends Luminova\Foundation\Core\Application
     * {
     *     protected function onPreCreate(): void 
     *     {
     *         // Bind interface to implementation
     *         $this->bind(MyInterface::class, MyConcreteClass::class);
     * 
     *         // Bind with custom logic (e.g., logger setup)
     *         $this->bind(\Psr\Log\LoggerInterface::class, function () {
     *             return new \MyApp\Log\FileLogger('/writable/logs/app.log');
     *         });
     *     }
     * }
     * ```
     * 
     * > **Note:** 
     * >
     * > Prefer class names for simple bindings. Use closures when instantiation requires logic.
     */
    protected final function bind(string $abstract, Closure|string $resolver): self 
    {
        DI::bind($abstract, $resolver);
        return $this;
    }

    /**
     * Trigger protected application lifecycle hooks.
     *
     * This method calls the matching `on*` method if it is supported. Unknown hooks throws an exception.
     * For `onTerminated`, the first argument is normalized with a default `uri`.
     * 
     * **Hooks**
     * - `onPreCreate`
     * - `onCreate`
     * - `onStart`
     * - `onFinish`
     * - `onRouteResolved`
     * - `onTerminated`
     * - `onShutdown`
     *
     * @param string $hook Hook method name (e.g. `onCreate`, `onDestroy`).
     * @param mixed ...$arguments Optional arguments passed to the hook.
     * 
     * @return mixed Return result of triggered hook if any.
     * @throws BadMethodCallException If invalid event hook is provided.
     */
    public final function trigger(string $hook, mixed ...$arguments): mixed
    {
        $isTerminated = false;

        if ($hook === 'onTerminated') {
            $isTerminated = true;
            $info = $arguments[0] ?? [];
            $arguments[0] = $info + ['uri' => $this->getUri()];
        }

        if (!in_array($hook, self::$hooks, true)) {
            throw new BadMethodCallException("Invalid lifecycle event: {$hook}");
        }

        $result = $this->{$hook}(...$arguments);

        if($isTerminated){
            self::$lifecycle = self::TERMINATED;
        }

        return $result;
    }

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
     * Application pre request handling lifecycle hook.
     * 
     * The onStart hook is triggered when the application routing starts handling incoming requests.
     * 
     * **Possible Info keys:**
     * - `context` - The application context (CLI or HTTP).
     * - `method`  - The HTTP request method.
     * - `uri`     - The request URI.
     * - `module`  - The HMVC URI module (same as prefix). 
     * - `prefix`  - The URI prefix. 
     * 
     *
     * @param array<string,mixed> $info Request state information.
     * 
     * @return void
     */
    protected function onStart(array $info): void {}

    /**
     * Application post request handling lifecycle hook.
     * 
     * The onFinish lifecycle hook is triggered after a request is handled, regardless of success or failure.
     * 
     * **Possible Info keys:**
     * 
     * - `filename`      (string|null) Optional controller class file name.
     * - `namespace`     (string|null) Optional controller class namespace.
     * - `method`        (string|null) Optional controller class method name.
     * - `command`       (array|null) Optional executed command for CLI.
     * - `controllers`   (int) Number of controller files scanned for matched attributes.
     * - `isCache`       (bool) Whether cached version rendered or new content.
     * - `isStaticCache` (bool) Whether a static cached version was served (e.g, page.html) 
     *                          or regular cache (e.g, `page`).
     * 
     * @param array<string,mixed> $info The handled request and controller information.
     * 
     * @return void 
     * 
     * > **Note:**
     * > after onFinish is called (too late to modify UI)
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
     * @param array<string,mixed> $info Additional termination information.
     *
     * @example - Terminate:
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
     * The onShutdown hook is triggered after script shutdown triggered by a fatal error or forced termination.
     * 
     * This hook gives the application a final chance to inspect the shutdown state.
     * If it returns `false`, the framework will skip further error handling—allowing
     * the application to take full control (e.g., for custom logging or rendering).
     *
     * @param array<string,mixed> $error The last recorded error before shutdown (if any).
     * 
     * @return bool Return `false` to take over shutdown handling, `true` to let the framework proceed.
     * 
     * > **Note:** 
     * >
     * > - Returning `true` allows the framework continue with its default error handling flow.
     * > - This hook only get called if shutting down because of an error.
     * > - It does not trigger when `Luminova::terminate()` is called, except if an error that causes shutdown.
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
     * @param bool $recreate Wether to forces a full rebuild (default: false).
     *
     * @return static Return a shared application instance.
     */
    public static function getInstance(bool $recreate = false): static 
    {
        if(!static::$instance instanceof static){
            static::$instance = new static($recreate);
            static::$instance->objectState = 'shared';
        }
       
        return static::$instance;
    }

    /**
     * Get the current request URI.
     * 
     * Alias {@see \Luminova\Luminova::getUriSegments()}
     *
     * @return string Return the current request URI paths.
     * > **Note:**
     * > Return `__cli__` if called in CLI environment.
     */
    public final function getUri(): string 
    {
        return Luminova::isCommand() ? Router::CLI_URI : Luminova::getUriSegments();
    }

    /**
     * Handle dynamic getter to instance properties.
     * 
     * Retrieve view exported properties, options or classes.
     *
     * @param string $property The property or class alias name.
     * 
     * @return mixed|null Return the property value or null if not found.
     * @ignore
     */
    public function __get(string $property): mixed
    {
        if(property_exists($this, $property)){
            return $this->{$property};
        }

        if(Luminova::isPropertyExists(static::class, $property, true) ){
           return static::${$property};
        }

        return null;
    }

    /**
     * Handle dynamic calls to instance methods.
     *
     * @param string $method    The name of the method being called.
     * @param array  $arguments The arguments passed to the method.
     *
     * @return mixed Return the result of the called method.
     * @throws BadMethodCallException if method not found.
     * @ignore
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$arguments);
        }

        throw new BadMethodCallException(sprintf(
            'Method "%s" does not exist in %s.', 
            $method, 
            static::class
        ));
    }

    /**
     * Handle dynamic static method calls.
     *
     * @param string $method    The name of the static method being called.
     * @param array  $arguments The arguments passed to the static method.
     *
     * @return mixed Return the result of the called method.
     * @throws BadMethodCallException if method not found.
     * @ignore
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if (method_exists(static::class, $method)) {
            return static::{$method}(...$arguments);
        }

        throw new BadMethodCallException(sprintf(
            "Static method %s does not exist in %s.",
            $method,
            static::class
        ));
    }

    /**
     * Clear references when the Application instance is cloned.
     *
     * @internal Ensures clones start without internal bindings.
     */
    public function __clone() {}
}