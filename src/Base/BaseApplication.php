<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Base;

use \Luminova\Routing\Router;
use \Luminova\Template\TemplateView;

abstract class BaseApplication
{
    /**
     * Adds helper class for handling view template rendering and response.
     *
     * @see https://luminova.ng/docs/0.0.0/templates/views
    */
    use TemplateView;

    /**
     * Base Application instance
     *
     * @var static|null $instance
    */
    private static ?self $instance = null;

    /**
     * @var Router $router Router class instance
    */
    public ?Router $router = null;

    /**
     * Initialize the base application constructor
     */
    public function __construct() 
    {
        // Initialize the router instance
        $this->router ??= new Router($this);

        // Set application controller class namespace
        $this->router->addNamespace('\App\Controllers');

        // Initialize the template engine
        $this->initialize();

        // Initialize onCreate method
        $this->onCreate();
    }

     /**
     * Application on create method, an alternative method to __construct()
     * 
     * @overridable #[\Override]
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * Get the base application instance shared singleton class instance.
     * 
     * @return static Application shared instance
     */
    public static final function getInstance(): static 
    {
        return self::$instance ??= new static();
    }

    /**
     * Get the current segments relative uri
     *
     * @return string URI of the current request 
     */
    public final function getView(): string 
    {
        return $this->router->getUriSegments();
    }

    /**
     * Get protected property from template class static::$publicOptions or static::$publicClasses
     *
     * @param string $key property or attribute key
     * 
     * @return ?mixed return property otherwise null if not found.
     * @ignore
    */
    public function __get(string $key): mixed
    {
        $value = static::attrGetter($key);

        if($value === static::KEY_NOT_FOUND) {
            return null;
        }

        return $value;
    }
}