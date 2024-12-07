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
use \Luminova\Interface\LazyInterface;
use \Luminova\Security\Validation;
use \Luminova\Utils\LazyObject;

/**
 * @deprecated since version 3.4.0 Use Luminova\Base\BaseController instead.
 */
abstract class BaseViewController
{
    /**
     * HTTP request object.
     * 
     * @var Request|null
     */
    protected Request|LazyInterface|null $request = null;
 
    /**
     * Input validation object.
     * 
     * @var Validation|null
     */
    protected Validation|LazyInterface|null $validate = null;
 
    /**
     * Application instance.
     * 
     * @var Application|null
     */
    protected Application|LazyInterface|null $app = null;

    /**
     * Initialize the BaseViewController instance 
     * and pre-initialize class `$this->app` to make it accessible instantly within controller class.
     * 
     * @deprecated since version 3.4.0 Use Luminova\Base\BaseController instead.
     */
    public function __construct()
    {
        trigger_error(
            'Luminova\Base\BaseViewController is deprecated since version 3.4.0. Use Luminova\Base\BaseController instead.',
            E_USER_DEPRECATED
        );

        $this->app = LazyObject::newObject(fn() => Application::getInstance());
        $this->validate = LazyObject::newObject(Validation::class);
        $this->request = LazyObject::newObject(Request::class);
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
     * Render a view within the controller.
     *
     * @param string $view The view file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional data to pass to the view.
     * @param string $type The content type (default: `html`).
     * @param int $status HTTP status code (default: 200 OK).
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
     * ```php
     * $this->app->view('view-name', 'html')->render([...]);
     * ```
     */
    protected final function view(
        string $view, 
        array $options = [], 
        string $type = 'html',
        int $status = 200
    ): int
    {
        return $this->app->view($view, $type)->render($options, $status);
    }

    /**
     * Respond with view content as a string.
     *
     * @param string $view The view file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional data to pass to the view.
     * @param string $type The content type (default: `html`).
     * @param int $status HTTP status code (default: 200 OK).
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
        string $type = 'html',
        int $status = 200
    ): string
    {
        return $this->app->view($view, $type)->respond($options, $status);
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