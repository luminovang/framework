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

use \Luminova\Boot;
use \Luminova\Http\Request;
use \Luminova\Template\View;
use \Luminova\Security\Validation;
use \Luminova\Components\Object\LazyObject;
use \Luminova\Foundation\Core\Application;
use \Luminova\Interface\{
    RoutableInterface, 
    LazyObjectInterface, 
    InputValidationInterface, 
    RequestInterface
};

/**
 * Base class for building HTTP controllers for APIs or websites.
 *
 * - Provides custom rendering, request handling, and input validation.
 * - Use this class as a foundation for routable controller methods.
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
 *      // return $this->tpl->view('template-name')->respond();
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
 * 
 * @example - Register global classes for the template lifecycle:
 * 
 * - Exported objects are available in template files as `$this->foo`.
 * - Use `export()` when the property is not accessible (not public or protected),
 *   or when view isolation is enabled.
 * 
 * @see View::export()
 *
 * ```php 
 * protected function onCreate(): void 
 * {
 *      $this->tpl->export($object, 'foo');
 *      $this->tpl->export(MyClass::class);
 *      $this->tpl->export(new MyClass(arguments));
 *      $this->tpl->export(new MyClass(arguments), 'MyClass');
 * }
 * ```
 */
abstract class Controller implements RoutableInterface
{
    /**
     * Lazy loaded HTTP request object.
     * 
     * @var Request<RequestInterface,LazyObjectInterface> $request
     */
    protected ?LazyObjectInterface $request = null;
 
    /**
     * Lazy loaded input validation object.
     * 
     * @var Validation<InputValidationInterface,LazyObjectInterface> $input
     */
    protected ?LazyObjectInterface $input = null;
 
    /**
     * Lazy loaded application instance.
     * 
     * @var Application<LazyObjectInterface>|\App\Application<Application> $app
     */
    protected ?LazyObjectInterface $app = null;

    /**
     * Lazy loaded template view object.
     * 
     * @var View<LazyObjectInterface> $tpl
     * @see https://luminova.ng/docs/0.0.0/templates/views
     */
    protected ?LazyObjectInterface $tpl = null;

    /**
     * Initialize controller class.
     *
     * Automatically lazily initializes commonly used objects so they are immediately
     * available within the controller when needed:
     *  - `$this->app`      : The main Application instance
     *  - `$this->input`    : Input Validation object
     *  - `$this->request`  : Incoming Request object
     *  - `$this->tpl`      : View Template object
     *
     * Calls `$this->onCreate()` after initialization for further setup.
     */
    public function __construct()
    {
        $this->app     = LazyObject::newObject(fn(): Application => Boot::application());
        $this->tpl     = LazyObject::newObject(fn() :View => new View($this->app));
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
        return property_exists($this, $property);
    }

    /**
     * Render a template and send the output.
     *
     * Renders the given template and writes the result to the response output
     * (browser or other runtime).
     *
     * Common content types:
     * - html  : HTML content
     * - json  : JSON content
     * - text | txt : Plain text
     * - xml   : XML content
     * - js    : JavaScript
     * - css   : CSS
     * - rdf   : RDF
     * - atom  : Atom feed
     * - rss   : RSS feed
     *
     * @param string $template Template name without extension (e.g. `index`).
     * @param array<string,mixed> $options  Data passed to the template scope.
     * @param string $type Template content type (default: View::HTML).
     * @param int $status HTTP status code (default: 200).
     *
     * @return int Returns response code:
     *      - STATUS_SUCCESS when the view is rendered successfully.
     *      - STATUS_SILENCE when rendering fails and execution ends silently.
     *
     * @see https://luminova.ng/docs/0.0.0/templates/views
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     return $this->view('template-name', [...], View::HTML, 200);
     *
     *     // Equivalent to:
     *     return $this->tpl->view('template-name', View::HTML)
     *         ->render([...], 200);
     *
     *     // And the global helper:
     *     return \Luminova\Funcs\view('template-name')
     *         ->render([...], 200);
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
        return $this->tpl->view($template, $type)
            ->render($options, $status);
    }

    /**
     * Render a template and return the output.
     *
     * Renders the given template and returns the generated content
     * instead of sending it to the response output.
     *
     * Supported content types:
     * - html  : HTML content
     * - json  : JSON content
     * - text | txt : Plain text
     * - xml   : XML content
     * - js    : JavaScript
     * - css   : CSS
     * - rdf   : RDF
     * - atom  : Atom feed
     * - rss   : RSS feed
     *
     * @param string $template Template name without extension (e.g. `index`).
     * @param array<string,mixed> $options Data passed to the template scope.
     * @param string $type Template content type (default: View::HTML).
     * @param int $status HTTP status code (default: 200).
     *
     * @return string|null Returns the rendered template content, or null if nothing is produced.
     *
     * @see https://luminova.ng/docs/0.0.0/templates/views
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     $content = $this->contents('view-name', [...], View::HTML, 200);
     *
     *     // Equivalent to:
     *     $content = $this->tpl->view('view-name', View::HTML)
     *         ->contents([...], 200);
     * 
     *     // And the global helper:
     *     return \Luminova\Funcs\view('template-name')
     *         ->contents([...], 200);
     * }
     * ```
     */
    protected final function contents(
        string $template,
        array $options = [],
        string $type = View::HTML,
        int $status = 200
    ): ?string 
    {
        return $this->tpl->view($template, $type)
            ->contents($options, $status);
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