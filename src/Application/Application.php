<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Application;

use \Luminova\Routing\Router;
use \Luminova\Template\TemplateTrait;

class Application 
{
    /**
     * Include TemplateTrait for template method
     * 
     * @method TemplateTrait
    */
    use TemplateTrait;

    /**
     * Base Application instance
     *
     * @var ?static $instance
    */
    private static $instance = null;

    /**
     * Router class instance
     *
     * @var Router $router
     */
    public ?Router $router = null;

    /**
     * Initialize the base application constructor
     *
     * @param string $dir The project root directory
     */
    public function __construct() {
       
        // Initialize the router instance
        $this->router ??= new Router();

        // Set application controller class namespace
        $this->router->addNamespace('\App\Controllers');

        // Initialize the template engine
        $this->initialize(__DIR__);

        // Set the project base path
        $this->setProjectBase($this->router->getBase());
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
     * Get the base application instance as a singleton.
     *
     * @param string $dir The project root directory
     * 
     * @return static
     */
    public static function getInstance(): static 
    {
        return static::$instance ??= new static();
    }
}