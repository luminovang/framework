<?php
/**
 * Luminova Framework Interface for creating routing system.
 * 
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \App\Application;
use \Luminova\Core\CoreApplication;
use \Luminova\Routing\{Prefix, Segments};
use \Luminova\Exceptions\RouterException;
use \Luminova\Interface\ErrorHandlerInterface;
use \Closure;

interface RouterInterface 
{
    /**
     * Initializes the Router class and sets up default properties.
     * 
     * @param Application<CoreApplication> $app Instance of core application class.
     */
    public function __construct(CoreApplication $app);

    /**
     * Route to handle HTTP GET requests.
     *
     * @param string $pattern The route URI pattern or template view name (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The route callback handler (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function get(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP POST requests.
     *
     * @param string $pattern The route URI pattern or template view name (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The route callback handler (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function post(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP PATCH requests.
     *
     * @param string $pattern The route URI pattern or template view name (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The route callback handler (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function patch(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP DELETE requests.
     *
     * @param string $pattern The route URI pattern or template view name (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The route callback handler (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function delete(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP PUT requests.
     *
     * @param string $pattern The route URI pattern or template view name (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The route callback handler (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function put(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP OPTIONS requests.
     *
     * @param string $pattern The route URI pattern or template view name (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The route callback handler (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function options(string $pattern, Closure|string $callback): void;

    /**
     * Initialize application routing with supported context URI prefixing `web`, `cli`, `api`, `console` etc...
     * 
     * Define URI prefixes and error handlers for specific URI prefix names.
     * Ensures only required routes for handling requests are loaded based on the URI prefix.
     * 
     * @param Prefix|array<string,mixed> ...$contexts [, Prefix $... ] URI prefixes for non-attribute routing 
     *                  containing prefix object or array of prefix.
     * 
     * @return static<RouterInterface> Returns the router instance.
     * @throws RouterException Throws if not context arguments was passed and route attribute is disabled.
     */
    public function context(Prefix|array ...$contexts): self;

    /**
     * Registers an HTTP "before" middleware to authenticate requests before handling controllers.
     * 
     * This method allows you to apply middleware logic that executes prior to any associated controllers. 
     * If the middleware callback returns `STATUS_ERROR`, the routing process will terminate, preventing further execution.
     * 
     * @param string $methods The allowed HTTP methods, separated by a `|` pipe symbol (e.g., `GET|POST`).
     * @param string $pattern The route URL pattern or template (e.g., `{segment}`, `(:type)`, `/.*`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The callback function or controller method to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     * @throws RouterException Thrown if the method is called in an invalid context or the `$methods` parameter is empty.
     */
    public function middleware(string $methods, string $pattern, Closure|string $callback): void;

    /**
     * Registers an HTTP "after" middleware to execute logic after a controller has been handled.
     * 
     * This method applies middleware logic that runs after a controller processes a request. 
     * It is typically used for tasks such as cleanup or additional post-processing.
     * 
     * @param string $methods The allowed HTTP methods, separated by a `|` pipe symbol (e.g., `GET|POST`).
     * @param string $pattern The route URL pattern or template (e.g., `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
     * @param Closure|string $callback The callback function or controller method to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     * @throws RouterException Thrown if the `$methods` parameter is empty.
     */
    public function after(string $methods, string $pattern, Closure|string $callback): void;

    /**
     * Registers a CLI "before" middleware guard to authenticate commands within a specific group.
     * 
     * This method applies middleware logic to CLI commands within a specified group. The middleware is executed 
     * before any command in the group. If the middleware callback returns `STATUS_ERROR`, the routing process will 
     * terminate, preventing further commands from executing.
     * 
     * @param string $group The command group name or default `global` for middleware that applies to all commands.
     * @param Closure|string|null $callback The callback function or controller method to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     * @throws RouterException Thrown if the method is called outside a CLI context.
     */
    public function guard(string $group, Closure|string $callback): void;

    /**
     * Registers HTTP request methods, URI patterns, and corresponding callback or controller methods.
     * 
     * This method allows you to define routes by specifying supported HTTP methods, a URL pattern, 
     * and the callback or controller method to execute when the pattern matches a client request.
     * Multiple HTTP methods can be specified using the pipe (`|`) symbol.
     * 
     * @param string $methods The allowed HTTP methods, separated by the pipe symbol (e.g., `GET|POST|PUT` or `ANY`).
     * @param string $pattern The route URL pattern or template name (e.g., `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9]+)`).
     * @param Closure|string $callback The callback function or controller method to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     * 
     * @throws RouterException Thrown if an empty method string is provided.
     */
    public function capture(string $methods, string $pattern, Closure|string $callback): void;

    /**
     * Registers a CLI command and its corresponding callback or controller method.
     * 
     * This method is used to define CLI commands, specifying the command name and the function 
     * or controller method to execute when the command is run in the terminal. 
     * Unlike HTTP routes, CLI commands are defined using this method specifically within the `group` method.
     * 
     * @param string $command The name of the command or a command pattern with filters (e.g., `foo`, `foo/(:int)/bar/(:string)`).
     * @param Closure|string $callback The callback function or controller method to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     */
    public function command(string $command, Closure|string $callback): void;

    /**
     * Capture and handle requests for any HTTP method.
     * 
     * This method leverages `Router::ANY_METHODS` to match and handle requests for any HTTP method.
     * It is a convenient way to define routes that respond to all HTTP methods without explicitly specifying them.
     *
     * @param string $pattern The route URL pattern or template name (e.g., `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
     * @param Closure|string $callback The callback function or controller method to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     */
    public function any(string $pattern, Closure|string $callback): void;

    /**
     * Binds a URI prefix to a group of routes under the specified prefix.
     * 
     * This method allows you to organize and group related routes under a common base path or pattern. 
     * It simplifies route management by associating multiple nested `URI` patterns with a shared prefix.
     * 
     * @param string $prefix The base path or URI pattern (e.g., `/blog`, `{segment}`, `(:type)`, `/account/([a-z])`).
     * @param Closure $callback The closure containing the route definitions for the group.
     * 
     * @return void
     * 
     * @example - Example of grouping routes under a `/blog/` prefix:
     * ```php
     * $router->bind('/blog/', static function(Router $router) {
     *     $router->get('/', 'BlogController::blogs');
     *     $router->get('/id/([a-zA-Z0-9-]+)', 'BlogController::blog');
     * });
     * ```
     */
    public function bind(string $prefix, Closure $callback): void;

    /**
     * Binds a group of CLI commands under a specific group name.
     * 
     * Similar to the HTTP `bind` method, this method organizes CLI commands into groups, 
     * making it easier to manage commands related to the same functionality or controller class.
     * 
     * @param string $group The name of the command group (e.g., `blog`, `user`).
     * @param Closure $callback A callback function that defines the commands for the group.
     * 
     * @return void
     * 
     * @example - Example of grouping CLI commands under a `blog` group:
     * ```php
     * $router->group('blog', static function(Router $router) {
     *     $router->command('list', 'BlogController::blogs');
     *     $router->command('id/(:int)', 'BlogController::blog');
     * });
     * ```
     * **Usage in CLI:**
     * ```bash
     * php index.php blog list
     * php index.php blog id=4
     * ```
     */
    public function group(string $group, Closure $callback): void;

    /**
     * Registers MVC controllers or HMVC module controller class namespace group for use in application routing.
     *
     * This method allows you to register new routable namespaces for both HMVC and MVC applications.
     * 
     * **Namespace Pattern:**
     * - **HMVC**: Register a namespace up to the controller path, omitting the `Http` and `Cli` suffixes.  
     *   Example: `\App\Modules\FooModule\Controllers\` instead of `\App\Modules\FooModule\Controllers\Http`. 
     *   This captures both `Http` and `Cli` namespaces.
     *
     * @param string $namespace The namespace to register (e.g., `\App\Controllers\`, `\App\Modules\FooModule\Controllers\`).
     *
     * @return static<RouterInterface> Returns the instance of the router class.
     * @throws RouterException If the namespace is empty or contains invalid characters.
     * 
     * **Note:** The base controllers for MVC and HMVC applications are predefined in the `Luminova\Core\CoreApplication` class.
     */
    public function addNamespace(string $namespace): self;

    /**
     * Execute application routes and handle incoming requests.
     *
     * This method processes all defined routes and dispatches incoming HTTP or CLI requests to the appropriate 
     * controller methods. It also manages application profiling, ensuring computed profiling data is sent to the UI for rendering. 
     * Additionally, it triggers the `onFinish` application event before termination.
     *
     * @return void
     * @throws RouterException Thrown if an error occurs during request processing or route resolution.
     * 
     * **Note:** This method is typically invoked once in the `/public/index.php` file, which serves as the application front controller.
     */
    public function run(): void;

    /**
     * Set an error listener callback function.
     *
     * @param Closure|array{0:class-string<ErrorHandlerInterface>,1:string}|string $match Matching route callback or segment pattern for error handling.
     * @param Closure|array{0:class-string<ErrorHandlerInterface>,1:string}|string|null $callback Optional error callback handler function.
     *  
     * @return void
     * @throws RouterException Throws if callback is specified and `$match` is not a segment pattern.
     */
    public function setErrorListener(
        Closure|array|string $match, 
        Closure|array|string|null $callback = null
    ): void;

    /**
     * Cause triggers an error response.
     *
     * @param int $status HTTP response status code (default: 404).
     * 
     * @return void
     */
    public static function triggerError(int $status = 404): void;

    /**
     * Get list of registered controller namespaces.
     *
     * @return string[] Return registered namespaces.
     * @internal
     */
    public static function getNamespaces(): array;

    /**
     * Get the current segment URI.
     * 
     * @return string Return relative paths.
     */
    public static function getUriSegments(): string;

    /**
     * Get request URI segments object.
     * 
     * @return Segments Return instance of segments with URI info.
     */
    public function getSegment(): Segments;
}