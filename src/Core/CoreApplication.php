<?php
/**
 * Luminova Framework core application class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Core;

use \Luminova\Interface\LazyInterface;
use \Luminova\Routing\Router;
use \Luminova\Template\View;

abstract class CoreApplication implements LazyInterface
{
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
     * @var Router|null $router
     */
    public ?Router $router = null;

    /**
     * Application is lifecycle state counter.
     *
     * @var int $lifecycle
     */
    private static int $lifecycle = 0;

    /**
     * CoreApplication constructor.
     * Initializes the router, sets the controller namespace, and sets up the template engine.
     * 
     * > Note: 
     * > if application is already created before re-initializing application instance will not call onCreate and onDestroy method again.
     */
    public function __construct() 
    {
        if(self::$lifecycle > 0){
            if((self::$instance instanceof static) && !($this->router instanceof Router)){
                $this->router = self::$instance->router;
            }

            return;
        }

        $this->onInitialized();
        $this->router ??= new Router($this);
        $this->router->addNamespace('\\App\\Controllers\\')
            ->addNamespace('\\App\\Modules\\Controllers\\');

        $this->onCreate();
        self::$lifecycle = 1;
    }

    /**
     * CoreApplication destruct.
     */
    public function __destruct()
    {
        if(self::$lifecycle > 1){
            return;
        }

        self::$lifecycle = 2;
        $this->onDestroy();
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
     * Lifecycle onCreate hook: Triggered once on object creation.
     * Override in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Lifecycle onDestroy hook: Triggered once on object destruction.
     * Override in subclasses for custom cleanup.
     * 
     * @return void
     * > Optionally Add `gc_collect_cycles()` to your onDestroy hook to forces collection of any existing garbage cycles.
     */
    protected function onDestroy(): void 
    {
        gc_collect_cycles();
    }

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
     * Triggered after a command controller is called.
     * 
     * @param array $options The presented command options.
     * 
     * @return void 
     */
    protected function onCommandPresent(array $options): void {}

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
     * Set the singleton instance to a new application instance.
     * 
     * @param CoreApplication $app The application instance to set.
     * 
     * @return static Return the new shared application instance.
     */
    public static function setInstance(CoreApplication $app): static
    {
        if((self::$instance instanceof static) && !($app->router instanceof Router)){
            $app->router = self::$instance->router;
        }

        self::$instance = $app;
        return self::$instance;
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
}