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
use \Luminova\Luminova;
use \Luminova\Template\View;
use \Luminova\Routing\{Router, DI};
use \Luminova\Utility\Object\LazyObject;
use \Luminova\Exceptions\BadMethodCallException;
use \Luminova\Interface\{RouterInterface, LazyObjectInterface};

/**
 * Base class for the application.
 *
 * Extend this class once to define your application's core behavior.
 * 
 * The extended implementation must be located at `/app/Application.php`.
 * 
 * @mixin View
 */
abstract class Application implements LazyObjectInterface
{
    /**
     * Application boot state idle.
     * 
     * @var int IDLE 
     */
    public const IDLE = 0;

    /**
     * Application boot state initialized.
     * 
     * @var int CREATED 
     */
    public const CREATED = 1;

    /**
     * Application bool state completed.
     * 
     * @var int COMPLETED 
     */
    public const COMPLETED = 2;

    /**
     * Instance of the Router class.
     *
     * @var RouterInterface|null $router
     */
    public ?RouterInterface $router = null;

    /**
     * Lazy loaded template view object.
     * 
     * @var View<LazyObjectInterface>|null $view
     * @see https://luminova.ng/docs/0.0.0/templates/views
     */
    public ?LazyObjectInterface $view = null;

    /**
     * Singleton instance of Application.
     *
     * @var static|null $instance
     */
    private static ?self $instance = null;

    /**
     * Application is lifecycle state counter.
     *
     * @var int $lifecycle
     */
    private static int $lifecycle = self::IDLE;

    /**
     * Is application terminated.
     * 
     * @var array<string,mixed> $termination
     */
    public array $termination = ['isTerminated' => false, 'info' => []];

    /**
     * Flag for application architecture.
     * 
     * @var bool|null $isHmvcModule
     */
    public static ?bool $isHmvcModule = null;

    /**
     * Core application constructor.
     *
     * Initializes the router, sets the controller namespace, and sets up the template engine.
     *
     * > **Note:**
     * > If an application instance already exists, onCreate and onDestroy won't be called again.
     */
    public function __construct() 
    {
        self::$isHmvcModule ??= env('feature.app.hmvc', false);

        if(self::$lifecycle > self::IDLE){
            if((self::$instance instanceof static) && !($this->router instanceof RouterInterface)){
                $this->router = self::$instance?->router;
            }

            return;
        }

        $this->onPreCreate();

        if($this->termination['isTerminated']){
            $this->__doTermination();
            return;
        }

        $this->router ??= $this->getRouterInstance() ?? new Router($this);
        $this->router->addNamespace(self::$isHmvcModule
            ? '\\App\\Modules\\Controllers\\' 
            : '\\App\\Controllers\\'
        );

        $this->view ??= LazyObject::newObject(fn() :View => new View($this));

        $this->onCreate();
        self::$lifecycle = self::CREATED;
    }

    /**
     * Core application destruct.
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
     * Trigger application early termination.
     *
     * This method allows you to terminate the application execution early.
     *
     * @param bool $terminate (optional) If set to true, the application will be terminated.
     *                        If set to false (default), the application will continue to run.
     * @param array $info Additional termination information to be included in `onterminated` event hook.
     *
     * @return void
     *
     * @example - Terminates application:
     * 
     * ```php
     * namespace App;
     * 
     * class Application extends Luminova\Foundation\Core\Application
     * {
     *      protected function onPreCreate(): void 
     *      {
     *          if($this->instance->ofSomethingIsTrue()){
     *              $this->terminate([...]);
     *          }
     * 
     *          \Luminova\Routing\DI::bind(MyInterface::class, MyConcreteClass::class);
     *      }
     * 
     *      protected function onTerminate(array $info): bool 
     *      {
     *          if($info['foo']['bar'] === true){
     *              // Allow termination
     *              return true;
     *          }
     * 
     *          // Deny Termination
     *          return false;
     *      }
     * 
     *      protected function onTerminated(array $info): void 
     *      {
     *          Logger::debug('Application was terminated', $info);
     *      }
     * }
     * ```
     * > **Note:** The `terminate` method should be call before object creation.
     */
    protected final function terminate(array $info = []): void 
    {
        $info += ['uri' => $this->router->getUriSegments()];

        $this->termination = [
            'isTerminated' => $this->onTerminate($info),
            'info' => $info
        ];
    }

    /**
     * Bind a class or interface for dependency injection (DI) in controller methods.
     * 
     * This lets you map an interface or class name to a concrete implementation or a closure.
     * When a controller method type-hints a dependency, this binding ensures the right object is injected.
     *
     * @param class-string $abstract The interface or class name to bind.
     * @param (Closure():T)|class-string $resolver The concrete class or closure returning the instance.
     * 
     * @return static Return instance of application class.
     * @see https://luminova.ng/docs/0.0.0/routing/dependency-injection
     * 
     * @example Simple and advanced bindings:
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
     * > Prefer class names for simple bindings. Use closures when instantiation requires logic.
     */
    protected final function bind(string $abstract, Closure|string $resolver): self 
    {
        DI::bind($abstract, $resolver);
        return $this;
    }

    /**
     * Trigger an application event or lifecycle hook.
     * 
     * @param string $event The event or hook method name to trigger.
     * @param mixed ...$arguments Optional arguments for the event method.
     * 
     * @return void
     */
    public final function __on(string $event, mixed ...$arguments): void 
    {
        $this->{$event}(...$arguments);
    }

    /**
     * Lifecycle onPreCreate hook: Triggers once before application object creation.
     * 
     * This allows you to override or create a custom initialization logic before routing system is initialized.
     * 
     * @return void
     * @example - Example using Luminova Rate Limiter:
     * 
     * ```php
     * use Luminova\Security\RateLimiter;
     * protected function onPreCreate(): void 
     * {
     *      $rate = new RateLimiter();
     *      if(!$rate->check('key')->isAllowed()){
     *          $rate->respond();
     *          $this->terminate(); // Optionally terminate application.
     *      }
     * }
     * ```
     */
    protected function onPreCreate(): void {}

    /**
     * Lifecycle onCreate hook: Triggers once before application object creation.
     * 
     * This allows you to override properties and initialize other function requires in application.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Lifecycle onDestroy hook: Triggered once on object destruction.
     * Override in subclasses for custom cleanup.
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
     * Lifecycle onStart hook: Triggered when the application starts handling a request.
     *
     * @param array<string,mixed> $info Request state information.
     * 
     * @return void
     */
    protected function onStart(array $info): void {}

    /**
     * Lifecycle onFinish hook: Triggered after a request is handled, regardless of success or failure.
     * 
     * @param array<string,mixed> $info Request controller information.
     * 
     * **Class Info keys:**
     * 
     * - `filename`  (string|null) Optional controller class file name.
     * - `namespace` (string|null) Optional controller class namespace.
     * - `method`    (string|null) Optional controller class method name.
     * - `controllers` (int) Number of controller files scanned for matched attributes.
     * - `cache`     (bool) Whether cached version rendered or new content.
     * - `staticCache` (bool) Whether a static cached version was served (e.g, page.html) or regular cache (e.g, `page`).
     * 
     * @return void 
     */
    protected function onFinish(array $info): void {}

    /**
     * Triggered after a route context is successfully registered.
     *  
     * @param string $context The name of the registered context.
     * 
     * @return void 
     */
    protected function onContextInstalled(string $context): void {}

    /**
     * Triggered after a view controller method is called.
     * 
     * @param string $uri The URI of the presented view.
     * 
     * @return void 
     */
    protected function onViewPresent(string $uri): void {}
   
    /**
     * Called before the application is allowed to terminate.
     *
     * This method is invoked internally after `terminate()` is called. It determines
     * whether the application termination should proceed or be canceled.
     * 
     * You can override this method to inspect the `$info` payload and return
     * `true` to allow termination or `false` to prevent it. If termination is allowed,
     * the `onTerminated()` method will be triggered.
     *
     * @param array $info Additional termination context data passed from `terminate()`.
     *
     * @return bool Return `true` to allow termination or `false` to cancel it.
     */
    protected function onTerminate(array $info): bool 
    {
       return true;
    }

    /**
     * Triggered after the application has been terminated.
     *
     * This method is called only if `onTerminate()` returns `true`, indicating that 
     * the application is allowed to terminate. Use this hook to perform any final 
     * cleanup, logging, or notification logic after termination.
     *
     * @param array $info Contextual information related to the termination request.
     *
     * @return void
     */
    protected function onTerminated(array $info): void {}

    /**
     * Triggered after a command controller is called.
     * 
     * @param array $options The presented command options.
     * 
     * @return void 
     */
    protected function onCommandPresent(array $options): void {}

    /**
     * Called after script shutdown triggered by a fatal error or forced termination.
     * 
     * This hook gives the application a final chance to inspect the shutdown state.
     * If it returns `false`, the framework will skip further error handling—allowing
     * the application to take full control (e.g., for custom logging or rendering).
     * 
     * Returning `true` lets the framework continue with its default error page or logging flow.
     *
     * @param array<string,mixed> $error The last recorded error before shutdown (if any).
     * 
     * @return bool Return `false` to take over shutdown handling, `true` to let the framework proceed.
     * 
     * > **Note:** 
     * > This hook only get called if shutting down because of an error.
     */
    public static function onShutdown(array $error): bool 
    {
        return true;
    }

    /**
     * Set the singleton instance to a new application instance.
     * 
     * @param \App\Application|Application $app The core application instance to set.
     * 
     * @return \App\Application|static Return the new shared application instance.
     */
    public static function setInstance(Application $app): static
    {
        if((self::$instance instanceof static) && !($app->router instanceof RouterInterface)){
            $app->router = self::$instance?->router;
        }

        self::$instance = $app;
        return self::$instance;
    }

    /**
     * Retrieve the singleton instance of the application.
     * 
     * @return \App\Application|static Return a shared application instance.
     */
    public static function getInstance(): static 
    {
        if(!self::$instance instanceof static){
            self::$instance = new static();
        }
       
        return self::$instance;
    }

    /**
     * Returns an instance of the application routing system.
     *
     * You may override this method in your application class to return a custom implementation
     * of the routing system by extending or replacing the default router.
     *
     * @return \T<RouterInterface>|null Return instance of the routing system, or null to use default.
     */
    protected function getRouterInstance(): ?RouterInterface
    {
        return null;
    }

    /**
     * Get the current request URI from the router.
     * 
     * Alias {@see Luminova\Routing\Router::getUriSegments()}
     *
     * @return string Return the current request URI paths.
     */
    public final function getUri(): string 
    {
        return $this->router->getUriSegments();
    }

    /**
     * Get the current view instance.
     *
     * Returns either a `View` object or a lazy-loaded wrapper implementing 
     * `LazyObjectInterface`. If no view has been initialized, this method creates 
     * a new `View` instance without assigning it to `$this->view`.
     *
     * @return View|View<LazyObjectInterface> Return the current view instance or a newly created one.
     *
     * > **Note:** 
     * > Calling this method does **not** update the `$view` property if it is null. 
     * > This means `$app->getView()` and `$app->view` may not reference the same object.
     */
    public final function getView(): View|LazyObjectInterface
    {
        if (!($this->view instanceof View) && !($this->view instanceof LazyObjectInterface)) {
            return new View($this);
        }

        return $this->view;
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
        $result = ($this->view instanceof LazyObjectInterface) 
            ? $this->view->getProperty($property, false, false)
            : View::KEY_NOT_FOUND;

        if($result === View::KEY_NOT_FOUND) {
            return Luminova::isPropertyExists(static::class, $property, true) 
                ? static::${$property} 
                : ($this->{$property} ?? null);
        }

        return $result;
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

        // Will be remove in the future
        // To access view method use `$this->view`
        if ($this->view instanceof LazyObjectInterface && $this->view->hasMethod($method)) {
            return $this->view->{$method}(...$arguments);
        }

        throw new BadMethodCallException(sprintf(
            "Method %s does not exist in %s or %s.",
            $method,
            static::class,
            View::class 
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
     * Break circular reference for `$view`.
     * 
     * Disallow templates direct access to view via (`$this->app->view` or `$self->app->view`).
     * 
     * @reference {
     *      Luminova\Templates\View, 
     *      Luminova\Templates\Engines\Scope
     * }
     * 
     * @internal Reset view reference
     */
    public function __clone()
    {
        $this->view = null;
    }

    /**
     * Terminates the application if it is marked as terminated.
     *
     * This function checks if the application instance is marked as terminated. 
     * If it is, it triggers the 'onTerminated' event with the context (either 'CLI' or 'HTTP'), 
     * the request method, and the URI segments. Finally, it exits the script.
     *
     * @return void
     * @throws RouterException If the application instance is not provided.
     */
    private function __doTermination(): void 
    {
        if(!$this->termination['isTerminated']){
            return;
        }

        $this->onTerminated($this->termination['info']);
        exit(0);
    }
}