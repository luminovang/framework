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
namespace Luminova\Core;

use \Luminova\Interface\RouterInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Routing\Router;
use \Luminova\Template\View;

abstract class CoreApplication implements LazyInterface
{
    public const IDLE = 0;
    public const CREATED = 1;
    public const COMPLETED = 2;

    /**
     * Utilize the View trait for handling template rendering and responses.
     *
     * @see https://luminova.ng/docs/0.0.0/templates/views
     */
    use View;

    /**
     * Singleton instance of the CoreApplication.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Instance of the Router class.
     *
     * @var RouterInterface|null $router
     */
    public ?RouterInterface $router = null;

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
     * CoreApplication constructor.
     * 
     * Initializes the router, sets the controller namespace, and sets up the template engine.
     * 
     * > Note: 
     * > if application is already created before re-initializing application instance will not call onCreate and onDestroy method again.
     */
    public function __construct() 
    {
        if(self::$lifecycle > self::IDLE){
            if((self::$instance instanceof static) && !($this->router instanceof RouterInterface)){
                $this->router = self::$instance->router;
            }

            return;
        }

        $this->onPreCreate();

        if($this->termination['isTerminated']){
            $this->__doTermination();
            return;
        }
        
        $this->onInitialized();
        $this->router ??= $this->getRouterInstance() ?? new Router($this);
        $this->router->addNamespace('\\App\\Controllers\\')
            ->addNamespace('\\App\\Modules\\Controllers\\');

        $this->onCreate();
        self::$lifecycle = self::CREATED;
    }

    /**
     * CoreApplication destruct.
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
     * class Application extends CoreApplication
     * {
     *      protected function onPreCreate(): void 
     *      {
     *          if($instance->ofSomethingIsTrue()){
     *              $this->terminate();
     *          }
     *      }
     * 
     *      protected function onTerminate(array $info): bool 
     *      {
     *          if(isset($info['foo']) && $info['foo']['bar'] === true){
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
     * - `attrFiles` (int) Number of controller files scanned for matched attributes.
     * - `cache`     (bool) Weather cached version rendered or new content.
     * - `staticCache` (bool) Weather is a static cached version (e.g, page.html) or regular cache (e.g, `page`).
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
     * Set the singleton instance to a new application instance.
     * 
     * @param CoreApplication $app The application instance to set.
     * 
     * @return static Return the new shared application instance.
     */
    public static function setInstance(CoreApplication $app): static
    {
        if((self::$instance instanceof static) && !($app->router instanceof RouterInterface)){
            $app->router = self::$instance->router;
        }

        self::$instance = $app;
        return self::$instance;
    }

    /**
     * Retrieve the singleton instance of the application.
     * 
     * @return static Return a shared application instance.
     */
    public static function getInstance(): static 
    {
        if(!self::$instance instanceof self){
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
     * @return RouterInterface<\T>|null Return instance of the routing system, or null to use default.
     */
    protected function getRouterInstance(): ?RouterInterface
    {
        return null;
    }

    /**
     * Get the current URI segments from the router.
     *
     * @return string Return the URI of the current request.
     */
    public final function getView(): string 
    {
        return $this->router->getUriSegments();
    }

    /**
     * Retrieve a protected property or dynamic attribute from template options or exported classes.
     *
     * @param string $key The property or class alias name.
     * 
     * @return mixed|null Return the property value or null if not found.
     * @ignore
     */
    public function __get(string $key): mixed
    {
        $value = self::attrGetter($key);
        return ($value === self::$KEY_NOT_FOUND) 
            ? ($this->{$key} ?? static::${$key} ?? null)
            : $value;
    }

     /**
     * Terminates the application if it is marked as terminated.
     *
     * This function checks if the application instance is marked as terminated. If it is, it triggers the 'onTerminated' 
     * event with the context (either 'CLI' or 'HTTP'), the request method, and the URI segments. Finally, it exits the script.
     *
     * @return void
     *
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