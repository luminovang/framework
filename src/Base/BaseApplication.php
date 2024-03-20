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
use \Luminova\Template\TemplateTrait;

abstract class BaseApplication
{
    /**
     * Include TemplateTrait for template method
     * 
     * @uses TemplateTrait
    */
    use TemplateTrait;

    /**
     * Base Application instance
     *
     * @var ?BaseApplication $instance
    */
    private static ?BaseApplication $instance = null;

    /**
     * Router class instance
     *
     * @var Router $router
     */
    public ?Router $router = null;

    /**
     * Initialize the base application constructor
     */
    public function __construct() 
    {
        // Initialize the router instance
        $this->router ??= new Router();

        // Set application controller class namespace
        $this->router->addNamespace('\App\Controllers');

        // Initialize the template engine
        $this->initialize(__DIR__);

        // Set the project base path
        $this->setProjectBase($this->router->getBase());

        // Initialize onCreate method
        $this->onCreate();
    }

    /**
     * Get the base application instance as a singleton.
     * 
     * @return static
     */
    public static function getInstance(): static 
    {
        return static::$instance ??= new static();
    }

    /**
     * Get the current view paths, segments uri
     *
     * @return string
     */
    public function getView(): string 
    {
        return $this->router->getView();
    }

    /**
     * Get application base path from router.
     *
     * @return string
     */
    public function getBase(): string 
    {
        return $this->router->getBase();
    }
    
    /**
     * Magic method getter
     * Get properties from template class 
     *
     * @param string $key property or attribute key
     * 
     * @return ?mixed return property else null
    */
    public function __get(string $key): mixed
    {
        $value = self::attrGetter($key);

        if($value === '__nothing__') {
            return $this->{$key} ?? null;
        }

        return $value;
    }

    /**
     * On create method 
     * 
     * @overridable #[\Override]
     * 
     * @return void 
    */
    protected function onCreate(): void {}
}