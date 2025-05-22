<?php
declare(strict_types=1);
/**
 * Luminova Framework Base controller class for HTTP requests view rendering.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Luminova\Interface\RoutableInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Interface\ValidationInterface;
use \Luminova\Interface\HttpRequestInterface;
use \Luminova\Core\CoreApplication;
use \Luminova\Http\Request;
use \Luminova\Security\Validation;
use \Luminova\Utils\LazyObject;
use \App\Application;

abstract class BaseController implements RoutableInterface
{
    /**
     * When rendering HTML contents.
     *
     * @var string HTML
     */
    public const HTML = 'html';

    /**
     * When rendering data as JSON.
     *
     * @var string JSON
     */
    public const JSON = 'json';

    /**
     * When rendering plain text content.
     *
     * @var string TEXT
     */
    public const TEXT = 'txt';

    /**
     * When rendering XML content.
     *
     * @var string
     */
    public const XML = 'xml';

    /**
     * When rendering JavaScript (.js) content.
     *
     * @var string
     */
    public const JS = 'js';

    /**
     * When rendering Cascading Style Sheets (.css).
     *
     * @var string
     */
    public const CSS = 'css';

    /**
     * When rendering RDF (Resource Description Framework) data.
     *
     * @var string
     */
    public const RDF = 'rdf';

    /**
     * When rendering Atom feeds.
     *
     * @var string
     */
    public const ATOM = 'atom';

    /**
     * When rendering RSS (Really Simple Syndication) feeds.
     *
     * @var string
     */
    public const RSS = 'rss';

    /**
     * Lazy loaded HTTP request object.
     * 
     * @var Request<HttpRequestInterface,LazyInterface>|null $request
     */
    protected ?LazyInterface $request = null;
 
    /**
     * Lazy loaded input validation object.
     * 
     * @var Validation<ValidationInterface,LazyInterface>|null $validate
     */
    protected ?LazyInterface $validate = null;
 
    /**
     * Lazy loaded application instance.
     * 
     * @var Application<CoreApplication,LazyInterface>|null $app
     */
    protected ?LazyInterface $app = null;

    /**
     * Initialize the BaseController instance and pre-initialize classes 
     * `$this->app`, `$this->validate` and `$this->request` to make them accessible instantly within controller class.
     */
    public function __construct()
    {
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
     * @ignore 
     */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }

    /**
     * Render a view within the controller.
     * 
     *  Supported content types:
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
     * @param string $view The view file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional data to pass to the view.
     * @param string $type The content type (default: `html`).
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return int Return one of the following status codes:  
     * - `STATUS_SUCCESS` if the view is handled successfully,  
     * - `STATUS_SILENCE` if failed, silently terminate without error page allowing you to manually handle the state.
     * 
     * @example - This examples are equivalent:
     * 
     * ```php
     * public function fooView(): int
     * {
     *      return $this->view('view-name', [...], parent::HTML, 200);
     *      // Same as 
     *      return $this->app->view('view-name', parent::HTML)->render([...], 200);
     * }
     * ```
     */
    protected final function view(
        string $view, 
        array $options = [], 
        string $type = self::HTML,
        int $status = 200
    ): int
    {
        return $this->app->view($view, $type)->render($options, $status);
    }

    /**
     * Respond with view content as a string.
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
     * @param string $view The view file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional data to pass to the view.
     * @param string $type The content type (default: `html`).
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return string Return the rendered view content.
     * 
     * @example - This examples are equivalent:
     * 
     * ```php
     * public function fooView()
     * {
     *      $content = $this->respond('view-name', [...], parent::HTML, 200);
     *      // Same as 
     *      $content = $this->app->view('view-name', parent::HTML)->respond([...], 200);
     * }
     * ```
     */
    protected final function respond(
        string $view, 
        array $options = [], 
        string $type = self::HTML,
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

    /**
     * Handles the failure of the `middleware` check in the controller.  
     * Invoked when the `middleware` method returns `STATUS_ERROR`.
     * 
     * Use this method to render a view or display an error message.
     *  
     * @param string $uri The request URI, useful for logging or triggering an error view.  
     * @param array<string,mixed> $classInfo Information about the class where the middleware check failed.  
     *  
     * @return void 
     * 
     * @example - Render View on Middleware Failure:
     * 
     * ```php
     * namespace App\Controllers\Http;
     * 
     * class AccountController extends BaseController
     * {
     *      #[Route('/account/(:root)', methods: ['ANY'], middleware: Route::BEFORE_MIDDLEWARE)]
     *      public function middleware(): int
     *      {
     *          return $this->app->session->online() ? STATUS_SUCCESS : STATUS_ERROR;
     *      }
     * 
     *      protected function onMiddlewareFailure(string $uri, array $classInfo): void 
     *      {
     *          $this->view('login');
     *      }
     * }
     * ```
     */
    protected function onMiddlewareFailure(string $uri, array $classInfo): void {}
}