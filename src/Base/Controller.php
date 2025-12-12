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

use \App\Kernel;
use Luminova\Luminova;
use Luminova\Http\Request;
use Luminova\Template\View;
use Luminova\Template\Response;
use Luminova\Security\Validation;
use Luminova\Exceptions\JsonException;
use Luminova\Foundation\Core\Application;
use Luminova\Components\Object\LazyObject;
use Luminova\Interface\{
    RoutableInterface, 
    LazyObjectInterface, 
    InputValidationInterface, 
    RequestInterface
};

/**
 * Base controller for handling HTTP requests in web applications and APIs.
 *
 * Provides common controller services for request handling, input validation,
 * view rendering, middleware lifecycle, and HTTP responses. Extend this class
 * to create routable HTTP controllers.
 *
 * @link https://luminova.ng/docs/0.0.0/controllers/http-controller
 * @link https://luminova.ng/docs/0.0.0/templates/views
 *
 * @example Return a response status:
 * ```php
 * public function foo(): int
 * {
 *     return $this->view('template-name');
 * }
 * ```
 *
 * @example Return a response object:
 * ```php
 * public function foo(): ContentResponseInterface
 * {
 *     return new Response(
 *         content: ['status' => 'OK'],
 *         status: 200
 *     );
 * }
 * ```
 *
 * @example Handle middleware response:
 * ```php
 * public function secure(): ContentResponseInterface
 * {
 *     return (new Response(['status' => 'OK'], 200))
 *         ->failed(!$this->app->session->isOnline());
 * }
 * ```
 *
 * @example Export objects to the view:
 * ```php
 * protected function onCreate(): void
 * {
 *     $this->tpl->export($object, 'foo');
 *     $this->tpl->export(MyClass::class);
 *     $this->tpl->export(new MyClass(arguments));
 *     $this->tpl->export(new MyClass(arguments), 'MyClass');
 * }
 * ```
 *
 * Exported objects are available in templates through `$this->foo`.
 * Use {@see View::export()} when the object is not publicly accessible or
 * when view isolation is enabled.
 */
abstract class Controller implements RoutableInterface
 {
    /**
     * Allow direct PHP include and require statements in this controller.
     *
     * When enabled, the debugger skips direct include/require enforcement.
     *
     * @var bool $allowIncludes
     * @see AllowIncludes Allows direct includes at the class level.
     */
    protected bool $allowIncludes = false;

    /**
     * Lazily loaded HTTP request object.
     *
     * @var Request<RequestInterface, LazyObjectInterface>
     */
    protected readonly LazyObjectInterface $request;

    /**
     * Lazily loaded input validation object.
     *
     * @var Validation<InputValidationInterface, LazyObjectInterface>
     */
    protected readonly LazyObjectInterface $input;

    /**
     * Lazily loaded application instance.
     *
     * @var Application<LazyObjectInterface>|\App\Application<Application>
     */
    protected readonly LazyObjectInterface $app;

    /**
     * Lazily loaded template view object.
     *
     * @var View<LazyObjectInterface>
     * @see https://luminova.ng/docs/0.0.0/templates/views
     */
    protected readonly LazyObjectInterface $tpl;

    /**
     * Initialize the HTTP controller.
     *
     * Creates the application, request, input validation, and template view
     * dependencies as lazy objects, then calls {@see self::onCreate()} for
     * additional controller initialization.
     *
     * In development, direct PHP include/require statements are checked unless
     * {@see $allowIncludes} is enabled.
     */
    public function __construct()
    {
        $this->app     = Kernel::resolve(Kernel::SERVICE_APPLICATION, shared: true);
        $this->tpl     = LazyObject::newObject(
            fn(): View => (new View($this->app))->setController(static::class)
        );
        $this->request = LazyObject::newObject(fn(): Request => Request::capture());
        $this->input   = LazyObject::newObject(
            fn(): Validation => (new Validation)->setRequest($this->request)
        );

        $this->onCreate();

        // Enforce coding standard for include files
        if (!PRODUCTION && !$this->allowIncludes) {
            \Luminova\Debugger\Tracer::assertNoIncludes($this);
        }
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
     * Retrieve a protected or private property.
     *
     * @param string $property The property name.
     *
     * @return mixed|null The property value, or `null` if the property does not exist.
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
     * Determine whether a property is set.
     *
     * @param string $property The property name.
     *
     * @return bool `true` if the property is set, otherwise `false`.
     *
     * @ignore
     */
    public function __isset(string $property): bool
    {
        return isset($this->{$property});
    }

    /**
     * Render a view template and send its output to the client.
     *
     * Resolves the template, makes the provided options available to the view,
     * and sends the rendered output with the specified content type and status.
     *
     * @param string $template View template name or path without extension,
     *                         such as `index` or `user/profile`.
     * @param array<string,mixed> $options Parameters made available to the view template.
     * @param string $type View content type. Defaults to `View::HTML`.
     * @param int $status HTTP response status code. Defaults to `200`.
     *
     * @return int `STATUS_SUCCESS` if the view is rendered successfully, or
     *             `STATUS_SILENCE` if rendering is suppressed or fails silently.
     *
     * @see View::render() Render and send the view output.
     * @see View::view() Set the view template and content type.
     * @link https://luminova.ng/docs/0.0.0/templates/views
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     return $this->view('template-name', [
     *         'title' => 'Home',
     *     ]);
     * }
     * ```
     *
     * @example - Same As:
     * ```php
     * return $this->tpl->view('template-name')
     *     ->render(['title' => 'Home']);
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
     * Accepts string, array, or object payloads. Non-string values are automatically
     * encoded to JSON using safe encoding options.
     *
     * JSON encoding rules:
     * - Throws an exception on encoding failure.
     * - Preserves Unicode characters.
     * - Preserves forward slashes.
     * - Converts large integers to strings.
     *
     * Response processing:
     * - Supports optional HTML minification.
     * - Supports optional response compression.
     *
     * @param object|array|string $body Response payload. Non-string values are JSON encoded.
     * @param array<string,mixed> $headers Response headers.
     * @param int $status HTTP status code (default: 200).
     * @param bool $minify Enable response content minification.
     * @param bool $compress Enable response compression.
     *
     * @return int HTTP response status code.
     * @throws JsonException When JSON encoding fails.
     *
     * @see Response For response handling.
     * @see self::contents() For reading template contents.
     * @see self::view() For rendering templates.
     * @see Luminova\Funcs\response() Global response helper.
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     return $this->send(
     *         ['status' => 'ok', 'message' => 'Account created'],
     *         ['Content-Type' => 'application/json'],
     *         200
     *     );
     * }
     * ```
     */
    protected final function send(
        object|array|string $body,
        array $headers = [],
        int $status = 200,
        bool $minify = false,
        bool $compress = false
    ): int 
    {
        if (!is_string($body)) {
            $headers['Content-Type'] ??= 'application/json';
        }

        return (new Response(
            content: $body,
            status: $status,
            headers: $headers,
            compress: $compress,
            minify: $minify
        ))->output();
    }

    /**
     * Render a view template and return its output as a string.
     *
     * Unlike {@see self::view()}, this method does not send the rendered output
     * to the HTTP response, allowing it to be processed or sent manually.
     *
     * @param string $template View template name or path without extension,
     *                         such as `index` or `user/profile`.
     * @param array<string,mixed> $options Parameters made available to the view template.
     * @param string $type View content type. Defaults to `View::HTML`.
     * @param int $status HTTP response status code used during rendering. Defaults to `200`.
     *
     * @return string|null The rendered view contents, or `null` if the output is empty.
     *
     * @see View::contents() Render the view and return its output.
     * @see self::view() Render the view and send its output to the client.
     * @link https://luminova.ng/docs/0.0.0/templates/views
     *
     * @example - Example:
     * ```php
     * #[Route('/foo')]
     * public function fooView(): int
     * {
     *     $content = $this->contents('view-name', [
     *         'title' => 'Home',
     *     ]);
     *
     *     // Process or send the rendered content manually.
     *     return $content !== null
     *         ? STATUS_SUCCESS
     *         : STATUS_SILENCE;
     * }
     * ```
     *
     * @example - Same As:
     * ```php
     * $content = $this->tpl->view('view-name')
     *     ->contents(['title' => 'Home']);
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
     * Called after the controller is initialized.
     * 
     * Perform custom initialization after the controller dependencies are created.
     *
     * Override this method to configure controller state, initialize additional dependencies, 
     * or perform setup that requires these objects.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Called when the controller is destroyed.
     * 
     * Perform custom cleanup before the controller instance is destroyed.
     * 
     * @return void
     */
    protected function onDestroy(): void {}

    /**
     * Handle a failed controller middleware check.
     *
     * Called automatically when {@see self::middleware()} returns
     * `STATUS_ERROR`. Override this method to handle the failure, such as
     * rendering a view, redirecting the request, displaying an error, or
     * recording it in a log.
     *
     * @param string $uri The request URI that triggered the middleware failure.
     * @param array<string,mixed> $metadata Metadata about the controller or route.
     *
     * @example - Example:
     * ```php
     * namespace App\Controllers\Http;
     *
     * class AccountController extends \Luminova\Base\Controller
     * {
     *     #[Route(
     *         '/account/(:root)',
     *         methods: ['ANY'],
     *         middleware: Route::BEFORE_MIDDLEWARE
     *     )]
     *     public function middleware(): int
     *     {
     *         return $this->app->session->isOnline()
     *             ? STATUS_SUCCESS
     *             : STATUS_ERROR;
     *     }
     *
     *     protected function onMiddlewareFailure(
     *         string $uri,
     *         array $metadata
     *     ): void {
     *         $this->view('login');
     *     }
     * }
     * ```
     */
    protected function onMiddlewareFailure(
        string $uri,
        array $metadata
    ): void {}
}