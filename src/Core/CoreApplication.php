<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Core;

use \Luminova\Routing\Router;
use \Luminova\Template\View;

abstract class CoreApplication
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
     * CoreApplication constructor.
     * Initializes the router, sets the controller namespace, and sets up the template engine.
     */
    public function __construct() 
    {
        $this->router ??= new Router($this);
        $this->router->addNamespace('\\App\\Controllers\\');
        $this->initialize();
        $this->onCreate();
    }

    /**
     * Trigger an application event listener.
     * 
     * @param string $event The event method name to trigger.
     * @param mixed ...$arguments Optional arguments for the event method.
     * 
     * @return void
    */
    public final function __on(string $event, mixed ...$arguments): void 
    {
        $this->{$event}(...$arguments);
    }

    /**
     * Method called during object creation, alternative to __construct().
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * Called when the application starts handling a request, regardless of success or failure.
     * 
     * @param array<string,mixed> $info The request state information.
     * 
     * @return void 
    */
    protected function onStart(array $info): void {}

    /**
     * Called after the application finishes handling a request, regardless of success or failure.
     * 
     * @return void 
    */
    protected function onFinish(): void {}

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
     * @return static The shared application instance.
     */
    public static final function getInstance(): static 
    {
        return static::$instance ??= new static();
    }

    /**
     * Get the current URI segments.
     *
     * @return string The URI of the current request.
     */
    public final function getView(): string 
    {
        return $this->router->getUriSegments();
    }

    /**
     * Retrieve a protected property from template options or exported classes.
     *
     * @param string $key The property or class alias name.
     * 
     * @return mixed|null The property value or null if not found.
     * @ignore
    */
    public function __get(string $key): mixed
    {
        $value = static::attrGetter($key);

        if ($value === static::$KEY_NOT_FOUND) {
            return $this->{$key} ?? static::${$key} ?? null;
        }

        return $value;
    }
}