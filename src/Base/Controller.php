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

use \Throwable;
use \Luminova\Boot;
use \Luminova\Http\Request;
use \Luminova\Template\View;
use \Luminova\Template\Response;
use \Luminova\Security\Validation;
use \Luminova\Exceptions\JsonException;
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
    protected readonly LazyObjectInterface $request;
 
    /**
     * Lazy loaded input validation object.
     * 
     * @var Validation<InputValidationInterface,LazyObjectInterface> $input
     */
    protected readonly LazyObjectInterface $input;
 
    /**
     * Lazy loaded application instance.
     * 
     * @var Application<LazyObjectInterface>|\App\Application<Application> $app
     */
    protected readonly LazyObjectInterface $app;

    /**
     * Lazy loaded template view object.
     * 
     * @var View<LazyObjectInterface> $tpl
     * @see https://luminova.ng/docs/0.0.0/templates/views
     */
    protected readonly LazyObjectInterface $tpl;

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
        $this->app     = Boot::application();
        $this->tpl     = LazyObject::newObject(fn(): View => new View($this->app));
        $this->request = LazyObject::newObject(fn(): Request => Request::getInstance());
        $this->input   = LazyObject::newObject(
            fn(): Validation => (new Validation)->setRequest($this->request)
        );

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
        return isset($this->{$property});
    }

    /**
     * Render a template and send it to the client from a controller.
     *
     * This method resolves a template, renders it with the provided data,
     * and writes the output directly to the HTTP response.
     *
     * Supported content types:
     * - html : HTML output (default)
     * - json : JSON output
     * - text | txt : Plain text output
     * - xml  : XML output
     * - js   : JavaScript output
     * - css  : CSS output
     * - rdf  : RDF feed
     * - atom : Atom feed
     * - rss  : RSS feed
     *
     * Execution flow:
     * 1. Resolve template by name (without extension)
     * 2. Bind data into template scope
     * 3. Render output using the selected content type
     * 4. Send response with HTTP status code
     *
     * @param string $template Template name without file extension (e.g. `index`).
     * @param array<string,mixed> $options Data available inside the template scope.
     * @param string $type Response content type (default: View::HTML).
     * @param int $status HTTP status code (default: 200).
     *
     * @return int Response status code:
     *      - STATUS_SUCCESS: View rendered successfully
     *      - STATUS_SILENCE: Rendering failed or output suppressed
     *
     * @see View For template class.
     * @see self::send() For sending contents.
     * @see self::contents() For reading template contents.
     * @link https://luminova.ng/docs/0.0.0/templates/views
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     return $this->view('template-name', [
     *         'title' => 'Home'
     *     ], View::HTML, 200);
     *
     *     // Equivalent fluent usage:
     *     return $this->tpl->view('template-name', View::HTML)
     *         ->render(['title' => 'Home'], 200);
     *
     *     // Global helper:
     *     return \Luminova\Funcs\view('template-name')
     *         ->render(['title' => 'Home'], 200);
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
     * Send a response from a controller.
     *
     * Accepts array, object, or string payloads. Non-string values are automatically
     * encoded to JSON using a safe encoding strategy.
     *
     * JSON encoding rules:
     * - Throws an exception on encoding failure
     * - Preserves Unicode characters
     * - Preserves slashes without escaping
     *
     * @param object|array|string $body Response payload (auto-encoded if not string)
     * @param array $headers Response headers
     * @param int $status HTTP status code (default: 200)
     *
     * @return int Return response status code.
     * @throws JsonException When JSON encoding fails.
     * 
     * @see Response For response class.
     * @see self::contents() For reading template contents.
     * @see self::view() For rendering templates.
     * @see Luminova\Funcs\response() Global helper function.
     * 
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     return $this->send(
     *          ['status' => 'ok', 'message' => 'Account created'],
     *          ['Content-Type' => 'application/json'], 
     *          200
     *      );
     * }
     * ```
     */
    protected final function send(
        object|array|string $body,
        array $headers = [],
        int $status = 200
    ): int 
    {
        if (!is_string($body)) {
            $headers['Content-Type'] ??= 'application/json';
            
            try {
                $body = json_encode(
                    $body,
                    JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES |
                    JSON_BIGINT_AS_STRING
                );
            } catch (Throwable $e) {
                throw new JsonException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return Response::getInstance()
            ->setStatus($status)
            ->render($body, $headers);
    }

    /**
     * Render a template and return the output as a string.
     *
     * This method renders a template without sending it to the HTTP response.
     * The result is returned as a raw string for further processing, modification,
     * or manual response handling.
     *
     * Supported content types:
     * - html : HTML output
     * - json : JSON output
     * - text | txt : Plain text output
     * - xml  : XML output
     * - js   : JavaScript output
     * - css  : CSS output
     * - rdf  : RDF feed
     * - atom : Atom feed
     * - rss  : RSS feed
     *
     * Execution flow:
     * 1. Resolve template by name (without extension)
     * 2. Bind provided data into template scope
     * 3. Render output using selected content type
     * 4. Return rendered content as string (no response output is sent)
     *
     * @param string $template Template name without file extension (e.g. `index`).
     * @param array<string,mixed> $options Data available inside the template scope.
     * @param string $type Response content type (default: View::HTML).
     * @param int $status HTTP status code used during rendering (default: 200).
     *
     * @return string|null Rendered template content or null if rendering fails or produces no output.
     *
     * @see View For template class.
     * @see self::send() For sending contents.
     * @see self::view() For rendering templates.
     * @link https://luminova.ng/docs/0.0.0/templates/views
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     $content = $this->contents('view-name', [
     *         'title' => 'Home'
     *     ], View::HTML, 200);
     *
     *     // Equivalent fluent usage:
     *     $content = $this->tpl->view('view-name', View::HTML)
     *         ->contents(['title' => 'Home'], 200);
     *
     *     // Global helper:
     *     return \Luminova\Funcs\view('template-name')
     *         ->contents(['title' => 'Home'], 200);
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
     * Called immediately after object creation.
     *
     * Override to customize setup or initialize dependencies.
     * 
     * > Lifecycle hook called immediately after object creation.
     */
    protected function onCreate(): void {}

    /**
     * Lifecycle hook called when the instance is destroyed.
     *
     * Override to perform cleanup or teardown logic.
     * 
     * > Lifecycle hook called when the instance is destroyed.
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