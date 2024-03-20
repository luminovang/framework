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

use \App\Controllers\Application;
use \Luminova\Http\Request;
use \Luminova\Security\InputValidator;
use \Luminova\Library\Importer;

abstract class BaseViewController
{
    /**
      * HTTP request object 
      * @var Request $request 
    */
    protected ?Request $request = null;
 
    /**
      * Input validation object 
      * @var InputValidator $validate
    */
    protected ?InputValidator $validate = null;
 
    /**
      * Application instance
      * @var Application $app 
    */
    protected ?Application $app = null;
 
    /**
      * Importer instance
      * @var Importer $library 
    */
    protected ?Importer $library = null;

    /**
     * Initialize controller instance
    */
    public function __construct()
    {
        $this->app = $this->app();
        $this->onCreate();
    }

    /**
     * Uninitialized controller instance
    */
    public function __destruct() 
    {
        $this->onDestroy();
    }
    
    /**
     * Property getter
     *
     * @param string $key property key
     * 
     * @return ?mixed return property else null
    */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
     /**
     * Check if property is set
     *
     * @param string $key property key
     * 
     * @return bool 
    */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * Initializes the http request class
     * @overridable #[\Override]
     * 
     * @return Request $request http request object 
    */
    protected function request(): Request
    {
        if($this->request === null){
            $this->request = new Request();
        }

        return $this->request;
    }

    /**
     * Initializes the input validator class
     * @overridable #[\Override]
     * 
     * @return InputValidator $validate input validation object 
    */
    protected function validate(): InputValidator
    {
        if($this->validate === null){
            $this->validate = new InputValidator();
        }
        
        return $this->validate;
    }

    /**
     * Initializes the application class
     * @overridable #[\Override]
     * 
     * @return Application $app Application instance
    */
    protected function app(): Application
    {
        if($this->app === null){
            $this->app = Application::getInstance();
        }
        
        return $this->app;
    }

    /**
     * Initializes the application class
     * @overridable #[\Override]
     * 
     * @return Importer $app Application instance
    */
    protected function library(): Importer
    {
        if($this->library === null){
            $this->library = new Importer();
        }
        
        return $this->library;
    }

    /**
     * Render view
     *
     * @param string $view view name
     * @param array $options view options
     * 
     * @return int STATUS_SUCCESS
    */
    protected function view(string $view, array $options = []): int
    {
        $this->app->view($view)->render($options);

        return STATUS_SUCCESS;
    }

    /**
     * On create method 
     * 
     * @overridable #[\Override]
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * On destroy method 
     * 
     * @overridable #[\Override]
     * 
     * @return void 
    */
    protected function onDestroy(): void {}
}