<?php
/**
 * Luminova Framework Base controller class, for handing web, api's rendering.
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
     * Initialize the BaseController instance 
     * and pre-initialize classes `$this->app`, `$this->validate` and `$this->request` to make them accessible instantly within controller class.
    */
    public function __construct()
    {
        $this->app();
        $this->validate();
        $this->request();
        $this->onCreate();
    }

    /**
     * Clean up the controller instance.
     * 
     * @ignore 
    */
    public function __destruct() 
    {
        $this->onDestroy();
    }
    
    /**
     * Property getter.
     *
     * @param string $key The property key.
     * 
     * @return mixed|null Return the property value, or null if not found.
     * 
     * @ignore 
    */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
     /**
     * Check if a property is set.
     *
     * @param string $key The property key.
     * 
     * @return bool Return true if the property is set, otherwise false.
     * 
     * @ignore 
    */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }

    /**
     * Initialize the HTTP request instance.
     * 
     * @return Request Return the HTTP request instance.
    */
    protected final function request(): Request
    {
        if (!$this->request instanceof Request) {
            $this->request = new Request();
        }

        return $this->request;
    }

    /**
     * Initialize the input validation instance.
     * 
     * @return Validation Return the input validation instance.
    */
    protected final function validate(): Validation
    {
        if (!$this->validate instanceof Validation) {
            $this->validate = new Validation();
        }
        
        return $this->validate;
    }

    /**
     * Initialize the application instance.
     * 
     * @return Application Return the application instance.
    */
    protected final function app(): Application
    {
        if (!$this->app instanceof Application) {
            $this->app = Application::getInstance();
        }
        
        return $this->app;
    }

    /**
     * Render a view within the controller.
     *
     * @param string $view The view file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional data to pass to the view.
     * @param string $type The content type (default: `html`).
     * 
     * @return int Return STATUS_SUCCESS on success, otherwise STATUS_ERROR.
     * 
     * Supported content types:
     * 
     * - html: HTML content
     * - json: JSON content
     * - text: Plain text content
     * - xml: XML content
     * - js: JavaScript content
     * - css: CSS content
     * - rdf: RDF content
     * - atom: Atom feed content
     * - rss: RSS feed content
     * 
     * This method is equivalent to:
     * 
     * ```
     * $this->app->view('view-name', 'html')->render([...]);
     * ```
    */
    protected final function view(
        string $view, 
        array $options = [], 
        string $type = 'html'
    ): int
    {
        return $this->app->view($view, $type)->render($options);
    }

    /**
     * Respond with view content as a string.
     *
     * @param string $view The view file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional data to pass to the view.
     * @param string $type The content type (default: `html`).
     * 
     * @return string Return the rendered view content.
     * 
     * Supported content types:
     * - html: HTML content
     * - json: JSON content
     * - text: Plain text content
     * - xml: XML content
     * - js: JavaScript content
     * - css: CSS content
     * - rdf: RDF content
     * - atom: Atom feed content
     * - rss: RSS feed content
     * 
     * This method is equivalent to:
     * 
     * ```
     * $this->app->view('view-name', 'html')->respond([...]);
     * ```
    */
    protected final function respond(
        string $view, 
        array $options = [], 
        string $type = 'html'
    ): string
    {
        return $this->app->view($view, $type)->respond($options);
    }

    /**
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * onDestroy method that gets triggered on object destruction, 
     * designed to be overridden in subclasses for custom destruction.
     * 
     * @return void
     */
    protected function onDestroy(): void {}
}