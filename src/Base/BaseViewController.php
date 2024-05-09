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
     * 
     * @var Request $request 
    */
    protected ?Request $request = null;
 
    /**
     * Input validation object 
     * 
     * @var InputValidator $validate
    */
    protected ?InputValidator $validate = null;
 
    /**
     * Application instance
     * 
     * @var Application $app 
    */
    protected ?Application $app = null;
 
    /**
     * Importer instance
     * 
     * @var Importer $library 
    */
    protected ?Importer $library = null;

    /**
     * Initialize BaseViewController class instance and make $this->app available to controller classes.
    */
    public function __construct()
    {
        $this->app = $this->app();
        $this->onCreate();
    }

    /**
     * Uninitialized controller instance
     * @ignore 
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
     * @ignore 
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
     * @ignore 
    */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * Initializes the http request class instance
     * 
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
     * Initializes the input validator class instance.
     * 
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
     * Initializes the application class instance.
     * 
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
     * Initializes the application class instance.
     * 
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
     * Shorthand to render view in controller class.
     *
     * @param string $view view name
     * @param array $options Optional options to be passed to view template.
     * 
     * @return int Return STATUS_SUCCESS on success, otherwise STATUS_ERROR failure.
    */
    protected function view(string $view, array $options = []): int
    {
        return $this->app->view($view)->render($options);
    }

    /**
     * Shorthand to respond view contents in controller class.
     *
     * @param string $view view name
     * @param array $options Optional options to be passed to view template.
     * 
     * @return string Return view contents which is ready to be rendered.
    */
    protected function respond(string $view, array $options = []): string
    {
        return $this->app->view($view)->respond($options);
    }

    /**
     * Controller onCreate method an alternative to __construct
     * 
     * @overridable #[\Override]
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * Controller onDestroy method an alternative to __distruct 
     * 
     * @overridable #[\Override]
     * 
     * @return void 
    */
    protected function onDestroy(): void {}
}