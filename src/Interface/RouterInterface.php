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
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Routing\{Prefix, Segments};
use \Luminova\Exceptions\RouterException;
use \Luminova\Interface\ViewResponseInterface;
use \Luminova\Foundation\Core\Application as CoreApplication;

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
     * @param string $pattern The URI segment patterns or view path to match (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function get(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP POST requests.
     *
     * @param string $pattern The URI segment patterns or view path to match (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function post(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP PATCH requests.
     *
     * @param string $pattern The URI segment patterns or view path to match (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function patch(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP DELETE requests.
     *
     * @param string $pattern The URI segment patterns or view path to match (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function delete(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP PUT requests.
     *
     * @param string $pattern The URI segment patterns or view path to match (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g, `MyController::methodName`, `fn() => handle()`).
     * 
     * @return void
     */
    public function put(string $pattern, Closure|string $callback): void;

    /**
     * Route to handle HTTP OPTIONS requests.
     *
     * @param string $pattern The URI segment patterns or view path to match (e.g, `/`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g, `MyController::methodName`, `fn() => handle()`).
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
     * @return static Returns the router instance.
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
     * @param string $pattern The URI segment patterns or view path to match (e.g., `{segment}`, `(:type)`, `/.*`, `/home`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g., `ControllerClass::methodName`).
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
     * @param string $pattern The URI segment patterns or view path to match (e.g., `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g., `ControllerClass::methodName`).
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
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string|null $callback The callback handler or controller handler to execute (e.g., `ControllerClass::methodName`).
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
     * @param string $pattern The URI segment patterns or view path to match name (e.g., `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9]+)`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g., `ControllerClass::methodName`).
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
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute (e.g., `ControllerClass::methodName`).
     * 
     * @return void
     */
    public function command(string $command, Closure|string $callback): void;

    /**
     * Capture and handle requests for any HTTP method.
     * 
     * This method leverages `Router::ANY_METHOD` to match and handle requests for any HTTP method.
     * It is a convenient way to define routes that respond to all HTTP methods without explicitly specifying them.
     *
     * @param string $pattern The URI segment patterns or view path to match 
     *          (e.g., `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|string $callback The callback handler or controller handler to execute 
     *                  (e.g., `ControllerClass::methodName`).
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
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int) $callback The closure containing the route definitions for the group.
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
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int) $callback A callback function that defines the commands for the group.
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
     * Trigger an HTTP error response and immediately halt route processing.
     *
     * This method is called when no matching route is found, or when a request 
     * must return a specific HTTP status code (e.g., 404, 500). It attempts to 
     * delegate error handling in the following order:
     * 
     * 1. If `ErrorController::onTrigger()` exists, it is called directly.
     * 2. If a matching route-specific error handler is registered, that handler is executed.
     * 3. If a global (`'/'`) error handler is registered, that handler is executed.
     * 4. If no handler is found, a default error page is displayed.
     *
     * @param int $status HTTP status code to trigger (default: 404).
     *
     * @return void
     */
    public static function trigger(int $status = 404): void;

    /**
     * Register a custom route placeholder with an optional grouping mode.
     *
     * Allows you to replace placeholders like `(:slug)` with your own regex.
     * You may choose whether the pattern should be raw, non-capturing, or capturing.
     * 
     * This method makes your routing clean, by providing alias to a long pattern or repeatable patterns.
     *
     * @param string $name Placeholder name (e.g. "slug").
     * @param string $pattern Regular expression pattern.
     * @param int|null $group Grouping mode:
     *                        - null: use pattern as-is
     *                        - 0: wrap as non-capturing group (?:pattern)
     *                        - 1: wrap as capturing group (pattern)
     *                        If the pattern already starts with '(', no extra wrapping is applied.
     *
     * @return void
     * @throws RouterException If empty placeholder name was provided or reserved names (e.g, `root`, `(:root)`, `base`, `(:base)`) was used as placeholder name.
     * 
     * @see toPatterns() - To convert placeholder to valid pattern.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-placeholder
     * @since 3.6.8.
     * 
     * @example - Examples:
     * 
     * ```php
     * Router::pattern('slug', '[a-z0-9-]+');         // raw pattern
     * Router::pattern('slug', '[a-z0-9-]+', 0);      // non-capturing (?:[a-z0-9-]+)
     * Router::pattern('slug', '[a-z0-9-]+', 1);      // capturing ([a-z0-9-]+)
     * Router::pattern('slug', '([a-z]+)-(\d+)', 1);  // stays unchanged
     * ```
     * 
     * **Attribute Usage:**
     * 
     * ```php
     * #[Luminova\Attributes\Route('/blog/(:slug)', methods: ['GET'])]
     * public function blog(string $slug): int {
     *      // Implement
     * }
     * ```
     * 
     * **Method Usage:**
     * ```
     * $router->get('/blog/(:slug)', 'BlogController::view');
     * ```
     * > **Important:** 
     * > Do not manually include outer start, ending or modifier delimiter characters (e.g, `^`, `$`, `#`, `/`, `~`, etc.) 
     * > the engine appends them as needed when building the final route regex.
     */
    public static function pattern(string $name, string $pattern, ?int $group = null): void;

    /**
     * Execute application routes and handle incoming requests.
     *
     * This method processes all defined routes and dispatches incoming HTTP or CLI requests to the appropriate 
     * controller methods. It also finalize application profiling, ensuring computed profiling data is sent to the 
     * UI for debugging and triggers the `onFinish` application event before termination.
     *
     * @return void
     * @throws RouterException Thrown if an error occurs during request processing or route resolution.
     * 
     * **Note:** This method is typically invoked once in the `/public/index.php` file, which serves as the application front controller.
     */
    public function run(): void;

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
     * @return static Returns the instance of the router class.
     * @throws RouterException If the namespace is empty or contains invalid characters.
     * 
     * **Note:** The base controllers for MVC and HMVC applications are predefined in the `Luminova\Foundation\CoreApplication` class.
     */
    public function addNamespace(string $namespace): self;

    /**
     * Set a custom error handler for a specific route or globally.
     * 
     * You can assign either a callable array handler, controller handler or a closure as the error handler.
     * 
     * **$pattern**
     * 
     * - `$pattern` If specifying a URI, provide a string pattern or a controller callback `[ControllerClass::class, 'method']`.
     * - `$pattern` If no URI is needed (global), provide only a closure or controller callback.
     *
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|array<int,string>|string $pattern A global error handler or URI patterns to register with `$handler`.
     *           For global error handler set callback or controller for error handler.
     * @param (Closure(mixed ...$args):ViewResponseInterface|ResponseInterface|int)|array<int,string>|string|null $handler An error callback handler or controller handler.
     *  
     * @return void
     * @throws RouterException if $handler is provided but $pattern is not a valid segment pattern.
     * 
     * @example - Examples:
     * ```php
     * // Global error handler
     * $router->onError([ErrorController::class, 'onWeError']);
     * 
     * // Specific URI error handler
     * $router->onError('/users/', [ErrorController::class, 'onWeError']);
     * 
     * // Using a closure
     * $router->onError('/admin', function($request) {
     *     // handle error
     * });
     * ```
     */
    public function onError(Closure|array|string $pattern, Closure|array|string|null $handler = null): void;

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