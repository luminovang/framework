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

use \App\Application;
use \Luminova\Http\Request;
use \Luminova\Security\Validation;

abstract class BaseController
{
    /**
     * HTTP request object.
     * 
     * @var Request|null $request 
    */
    protected ?Request $request = null;
 
    /**
     * Input validation object.
     * 
     * @var Validation|null $validate
    */
    protected ?Validation $validate = null;
 
    /**
     * Application instance.
     * 
     * @var Application|null $app 
    */
    protected ?Application $app = null;

    /**
     * Initialize BaseController class instance and make $this->app available to controller classes.
    */
    public function __construct()
    {
        $this->app();
        $this->validate();
        $this->request();
        $this->onCreate();
    }

    /**
     * Uninitialized controller instance.
     * @ignore 
    */
    public function __destruct() 
    {
        $this->onDestroy();
    }
    
    /**
     * Property getter.
     *
     * @param string $key property key.
     * 
     * @return ?mixed Return property value, otherwise null.
     * @ignore 
    */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
     /**
     * Check if property is set.
     *
     * @param string $key The property key,
     * 
     * @return bool Return true if property is set, otherwise false.
     * @ignore 
    */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * Initializes the http request class instance.
     * 
     * @return Request Return http request instance. 
    */
    protected final function request(): Request
    {
        if($this->request === null){
            $this->request = new Request();
        }

        return $this->request;
    }

    /**
     * Initializes the input validator class instance.
     * 
     * @return Validation Return input validation instance.
    */
    protected final function validate(): Validation
    {
        if($this->validate === null){
            $this->validate = new Validation();
        }
        
        return $this->validate;
    }

    /**
     * Initializes the application class instance.
     * 
     * @return Application Return application instance.
    */
    protected final function app(): Application
    {
        if($this->app === null){
            $this->app = Application::getInstance();
        }
        
        return $this->app;
    }

    /**
     * Shorthand to render view in controller class.
     *
     * @param string $view The view file name without extension type like: [`.php`, `.tpl`, `.twg`],  (e.g, `index`).
     * @param array<string,mixed> $options Optional options to be passed to view template.
     * @param string $type The view content extension type (default: `html`).
     * 
     * @return int Return STATUS_SUCCESS on success, otherwise STATUS_ERROR failure.
     * 
     * View Types: 
     * 
     * - html Html content.
     * - json Json content.
     * - text Plain text content.
     * - xml  Xml content.
     * - js   JavaScript content.
     * - css  CSS content.
     * - rdf  RDF content.
     * - atom Atom content.
     * - rss  RSS feed content.
    */
    protected final function view(string $view, array $options = [], string $type = 'html'): int
    {
        return $this->app->view($view, $type)->render($options);
    }

    /**
     * Shorthand to respond view contents in controller class.
     *
     * @param string $view The view file name without extension type like: [`.php`, `.tpl`, `.twg`],  (e.g, `index`).
     * @param array<string,mixed> $options Optional options to be passed to view template.
     * @param string $type The view content extension type (default: `html`).
     * 
     * @return string Return view contents which is ready to be rendered.
     * 
     * View Types: 
     * 
     * - html Html content.
     * - json Json content.
     * - text Plain text content.
     * - xml  Xml content.
     * - js   JavaScript content.
     * - css  CSS content.
     * - rdf  RDF content.
     * - atom Atom content.
     * - rss  RSS feed content.
    */
    protected final function respond(string $view, array $options = [], string $type = 'html'): string
    {
        return $this->app->view($view, $type)->respond($options);
    }

    /**
     * Controller onCreate method an alternative to __construct.
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * Controller onDestroy method an alternative to __distruct.
     * 
     * @return void 
    */
    protected function onDestroy(): void {}
}