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

use \App\Application;
use \Luminova\Http\Request;
use \Luminova\Template\View;
use \Luminova\Security\Validation;
use \Luminova\Utility\Object\LazyObject;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\{
    RoutableInterface, 
    LazyObjectInterface, 
    InputValidationInterface, 
    HttpRequestInterface
};

/**
 * Base class for building HTTP controllers for APIs or websites.
 *
 * - Provides custom rendering, request handling, and input validation.
 * - Use this class as a foundation for routable controller methods.
 *
 * @property View<LazyObjectInterface> $view Instance of the template view class.
 * @property Request<HttpRequestInterface,LazyObjectInterface>|null $request @inheritDoc
 * @property Validation<InputValidationInterface,LazyObjectInterface>|null $input @inheritDoc
 * @property \App\Application|\Luminova\Foundation\Core\Application<LazyObjectInterface>|null $app @inheritDoc
 *
 * @see https://luminova.ng/docs/0.0.0/templates/views
 * @see https://luminova.ng/docs/0.0.0/controllers/http-controller
 *
 * @example Return a status code:
 * ```php
 * public function foo(): int 
 * {
 *      return $this->view('template-name');
 *      // Alternative examples:
 *      // return $this->app->view->view('template-name')->respond();
 *      // return response()->json(['status' => 'OK']);
 * }
 * ``` 
 *
 * @example Return a Response object:
 * ```php
 * public function foo(): Luminova\Interface\ViewResponseInterface 
 * {
 *      return new Response(200, content: ['status' => 'OK']);
 *      // Alternative example:
 *      // return response()->content(['status' => 'OK']);
 * }
 * ``` 
 *
 * @example Middleware Response object:
 * ```php
 * public function secure(): Luminova\Interface\ViewResponseInterface 
 * {
 *      return (new Response(200, content: ['status' => 'OK']))
 *          ->failed(!$this->app->session->isOnline());
 * }
 * ``` 
 */
abstract class Controller implements RoutableInterface
{
    /**
     * Lazy loaded HTTP request object.
     * 
     * @var Request<HttpRequestInterface,LazyObjectInterface>|null $request
     */
    protected ?LazyObjectInterface $request = null;
 
    /**
     * Lazy loaded input validation object.
     * 
     * @var Validation<InputValidationInterface,LazyObjectInterface>|null $input
     */
    protected ?LazyObjectInterface $input = null;
 
    /**
     * Lazy loaded application instance.
     * 
     * @var \App\Application|\Luminova\Foundation\Core\Application<LazyObjectInterface>|null $app
     */
    protected ?LazyObjectInterface $app = null;

    /**
     * Controller constructor.
     *
     * Automatically lazily initializes commonly used objects so they are immediately
     * available within the controller when needed:
     *  - `$this->app`      : The main Application instance
     *  - `$this->input`    : Input Validation object
     *  - `$this->request`  : Incoming Request object
     *
     * Calls `$this->onCreate()` after initialization for further setup.
     */
    public function __construct()
    {
        $this->app     = LazyObject::newObject(fn(): Application => Application::getInstance());
        $this->input   = LazyObject::newObject(Validation::class);
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
     * Retrieve a protected or private properties.
     *
     * @param string $property The property name.
     * 
     * @return mixed|null Return the property value, or null if not found.
     * 
     * @ignore 
     */
    public function __get(string $property): mixed
    {
        if($property === 'view'){
            return $this->app->view;
        }

        if($property === 'validate'){
            if (!PRODUCTION) {
                throw new RuntimeException('Property $validate is deprecated. Use $input instead.');
            } else {
                trigger_error(
                    'Property $validate is deprecated. Please use $input instead.',
                    E_USER_DEPRECATED
                );
            }

            return $this->input;
        }

        return property_exists($this, $property)
            ? $this->{$property}
            : null;
    }
    
    /**
     * Check if a property is exists.
     *
     * @param string $property The property key.
     * 
     * @return bool Return true if the property is set, otherwise false.
     * @ignore 
     */
    public function __isset(string $property): bool
    {
        return ($property === 'view' || property_exists($this, $property));
    }

    /**
     * Render a template within the controller.
     * 
     * This method will render specified template and send the output to browser or system.
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
     * @param string $template The template file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional scope data to pass to the template.
     * @param string $type The content type (default: `View::HTML`).
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return int Return one of the following status codes:  
     * - `STATUS_SUCCESS` if the view is handled successfully,  
     * - `STATUS_SILENCE` if failed, silently terminate without error page allowing you to manually handle the state.
     * 
     * @see https://luminova.ng/docs/0.0.0/templates/views
     * 
     * @example - This examples are equivalent:
     * 
     * ```php
     * public function fooView(): int
     * {
     *      return $this->view('template-name', [...], View::HTML, 200);
     * 
     *      // Same as 
     *      return $this->app->view->view('template-name', View::HTML)->render([...], 200);
     * 
     *      // And global function
     *      return \Luminova\Funcs\view('template-name', 200, [...], View::HTML)
     * }
     * ```
     */
    protected final function view(
        string $template, 
        array $options = [], 
        string $type = View::HTML, 
        int $status = 200
    ): int
    {
        return $this->app->view->view($template, $type)
            ->render($options, $status);
    }

    /**
     * Render template and return content as a string.
     * 
     * Unlike the `view` method, the `respond` method will render specified template and 
     * return the output instead of directly sending to browser or system.
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
     * @param string $template The template file name without the extension (e.g., `index`).
     * @param array<string,mixed> $options Optional scope data to pass to the template.
     * @param string $type The content type (default: `View::HTML`).
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return string Return the rendered template content as string.
     * 
     * @see https://luminova.ng/docs/0.0.0/templates/views
     * 
     * @example - This examples are equivalent:
     * 
     * ```php
     * public function fooView()
     * {
     *      $content = $this->respond('view-name', [...], View::HTML, 200);
     *      // Same as 
     *      $content = $this->app->view->view('view-name', View::HTML)->respond([...], 200);
     * }
     * ```
     */
    protected final function respond(
        string $template, 
        array $options = [], 
        string $type = View::HTML, 
        int $status = 200
    ): string
    {
        return $this->app->view->view($template, $type)
            ->respond($options, $status);
    }

    /**
     * onCreate override hook method.
     * 
     * Called automatically when the controller instance is created.
     * Intended to be overridden in subclasses for custom initialization logic.
     */
    protected function onCreate(): void {}

    /**
     * onDestroy override hook method.
     * 
     * Called automatically when the controller instance is destroyed.
     * Intended to be overridden in subclasses for custom cleanup or teardown logic.
     */
    protected function onDestroy(): void {}

    /**
     * Triggered when a controller middleware check fails.  
     * 
     * This method is called automatically if the `middleware` method returns `STATUS_ERROR`.
     *
     * Use it to render a view, redirect, display an error message or logging.
     *
     * @param string $uri The request URI, useful for logging or handling specific error responses.
     * @param array<string,mixed> $metadata Metadata about the controller 
     *                      or route where the middleware failed.
     *
     * @return void
     *
     * @example - Render a view on middleware failure:
     *
     * ```php
     * namespace App\Controllers\Http;
     *
     * class AccountController extends \Luminova\Base\Controller
     * {
     *      #[Route('/account/(:root)', methods: ['ANY'], middleware: Route::HTTP_BEFORE_MIDDLEWARE)]
     *      public function middleware(): int
     *      {
     *          return $this->app->session->online() 
     *              ? STATUS_SUCCESS 
     *              : STATUS_ERROR;
     *      }
     *
     *      protected function onMiddlewareFailure(string $uri, array $metadata): void 
     *      {
     *          $this->view('login');
     *      }
     * }
     * ```
     */
    protected function onMiddlewareFailure(string $uri, array $metadata): void {}
}