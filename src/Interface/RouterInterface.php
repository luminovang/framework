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

use \Closure;
use \Psr\Http\Message\ResponseInterface;
use Luminova\Exceptions\RouterException;
use Luminova\Routing\{Prefix, Segments};
use Luminova\Foundation\Core\Application;
use Luminova\Interface\ContentResponseInterface;

/**
 * Luminova Routing.
 * 
 * @template T of ContentResponseInterface|ResponseInterface
 *
 * Route callback:
 * `(Closure(mixed ...$args):T|int)|string`
 */
interface RouterInterface
{
    /**
     * Set application object.
     *
     * @param Application $app The application object to assign.
     * 
     * @return self The router instance.
     */
    public function setApplication(Application $app): self;

    /**
     * Register a controller namespace for application routing.
     *
     * Supports both MVC and HMVC applications. For HMVC modules, register
     * the namespace up to the `Controllers` segment without the `Http` or
     * `Cli` suffix so that both controller contexts are included.
     *
     * @param string $namespace The controller namespace to register.
     *                           Examples:
     *                           `\App\Controllers\`
     *                           `\App\Modules\FooModule\Controllers\`
     *
     * @return self The router instance.
     *
     * @throws RouterException If the namespace is empty or contains invalid
     *                         characters.
     *
     * @note The root controller namespaces for MVC and HMVC applications are
     *       predefined by {@see Luminova\Foundation\Core\Application}.
     */
    public function addNamespace(string $namespace): self; 
    
    /**
     * Configure application routing contexts.
     *
     * Routing contexts define URI prefixes and their associated configuration
     * for method-based routing. Each context may specify an error handler and,
     * for CLI routing, whether the context handles commands.
     *
     * Pass `null` to use attribute-based routing. When an array is provided,
     * the router uses the supplied contexts for method-based route resolution.
     *
     * @param Prefix[]|array<string|int,array>|null $contexts Routing context definitions
     *        for method-based routing, or `null` to use attribute-based routing.
     *
     * @return self The router instance.
     * @throws RouterException If `$contexts` is `null` and attribute-based
     *                         routing is disabled or unavailable.
     * 
     * @example - Attribute-based routing:
     * ```php
     * Boot::http()->router->context()->run();
     * ```
     * 
     * @example - Method-based Array contexts:
     * ```php
     * Boot::http()->router->context([
     *     Prefix::with(Prefix::WEB, [AppError::class, 'onTrigger']),
     *     Prefix::with(Prefix::API, [AppError::class, 'onTrigger']),
     *     Prefix::with('admin', [AppError::class, 'onTrigger']),
     *     Prefix::with(Prefix::CLI),
     * ])->run();
     * ```
     *
     * @example - Method-based Prefix objects:
     * ```php
     * use Luminova\Boot;
     * use Luminova\Routing\Prefix;
     *
     * Boot::http()->router->context([
     *     new Prefix(Prefix::WEB, [AppError::class, 'onTrigger']),
     *     new Prefix(Prefix::API, [AppError::class, 'onTrigger']),
     *     new Prefix('admin', [AppError::class, 'onTrigger']),
     *     new Prefix(Prefix::CLI),
     * ])->run();
     * ```
     *
     * @see Prefix For method-based routing contexts.
     * @see ../../public/index.php For method-based routing setup.
     * @see ../../routes/ For method-based route definitions.
     */
    public function context(?array $contexts = null): self;
    
    /**
     * Execute application routes and dispatch the current request.
     *
     * Resolves the incoming HTTP or CLI request against the defined routes
     * and dispatches it to the appropriate controller or handler. Finalizes
     * application profiling, sends profiling data to the debugging UI, and
     * triggers the `onFinish` application event before the request ends.
     *
     * @return void
     *
     * @throws RouterException If an error occurs during request processing
     *                         or route resolution.
     *
     * @note This method is typically invoked once from {@see public/index.php},
     *       which serves as the application's front controller.
     */
    public function run(): void;

    /**
     * Register a route handler for HTTP GET requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function get(string $pattern, Closure|string $callback): void;

    /**
     * Register a route handler for HTTP QUERY requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/search`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function query(string $pattern, Closure|string $callback): void;

    /**
     * Register a route handler for HTTP POST requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function post(string $pattern, Closure|string $callback): void;

    /**
     * Register a route handler for HTTP PATCH requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function patch(string $pattern, Closure|string $callback): void;

    /**
     * Register a route handler for HTTP DELETE requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function delete(string $pattern, Closure|string $callback): void;

    /**
     * Register a route handler for HTTP PUT requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function put(string $pattern, Closure|string $callback): void;

    /**
     * Register a route handler for HTTP OPTIONS requests.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     */
    public static function options(string $pattern, Closure|string $callback): void;

    /**
     * Register a route middleware handler.
     *
     * The middleware executes before the matched route handler. If the
     * middleware returns `STATUS_ERROR`, route execution is stopped and the
     * request is not dispatched to the controller.
     *
     * @param string $methods The HTTP methods this middleware applies to,
     *                        separated by `|`, such as `GET|POST`.
     * @param string $pattern The URI pattern to match, such as `{segment}`,
     *                        `(:type)`, `/.*`, `/home`, or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The middleware
     *        handler or controller action to execute.
     *
     * @return void
     * @throws RouterException If the middleware is registered for an invalid
     *                         context or `$methods` is empty.
     */
    public static function middleware(
        string $methods,
        string $pattern,
        Closure|string $callback
    ): void;

    /**
     * Register an HTTP "after" middleware handler.
     *
     * The middleware executes after the matched route handler completes and
     * can be used for post-processing, cleanup, logging, or modifying the
     * final response.
     *
     * @param string $methods The HTTP methods this middleware applies to,
     *                        separated by `|`, such as `GET|POST`.
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        `{segment}`, `(:type)`, or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The middleware
     *        handler or controller action to execute.
     *
     * @return void
     * @throws RouterException If the `$methods` parameter is empty.
     */
    public static function after(
        string $methods,
        string $pattern,
        Closure|string $callback
    ): void;

    /**
     * Register a CLI middleware guard for a command group.
     *
     * The guard executes before commands in the specified group. If the guard
     * returns `STATUS_ERROR`, command execution is stopped.
     *
     * Use `global` as the group name to apply the guard to all CLI commands.
     *
     * @param string|'global' $group The command group name, or `global` to apply the
     *                      guard to all commands.
     * @param (Closure(mixed ...$args):int)|string $callback The middleware
     *        handler or controller action to execute.
     *
     * @return void
     * @throws RouterException If called outside a CLI context.
     */
    public static function guard(string $group, Closure|string $callback): void;

    /**
     * Register an HTTP route with one or more supported methods.
     *
     * Defines a URI pattern and the callback or controller action to execute
     * when the route matches. Multiple HTTP methods can be specified using
     * the `|` separator.
     *
     * @param string $methods The HTTP methods supported by the route, separated
     *                        by `|`, such as `GET|POST|PUT` or `ANY`.
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        `{segment}`, `(:type)`, or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler
     *        or controller action to execute.
     *
     * @return void
     * @throws RouterException If the HTTP method string is empty.
     */
    public static function capture(
        string $methods,
        string $pattern,
        Closure|string $callback
    ): void;

    /**
     * Register a CLI command with its handler.
     *
     * Defines a command name or pattern and the callback or controller action
     * to execute when the command is invoked from the terminal.
     *
     * @param string $command The command name or pattern, optionally containing
     *                        route placeholders such as `foo` or
     *                        `foo/(:int)/bar/(:string)`.
     * @param (Closure(mixed ...$args):int)|string $callback The command
     *        handler or controller action to execute.
     *
     * @return void
     */
    public static function command(string $command, Closure|string $callback): void;

    /**
     * Register a route that accepts any supported HTTP method.
     *
     * The route matches the specified URI pattern regardless of the HTTP
     * request method.
     *
     * @param string $pattern The URI pattern to match, such as `/`, `/home`,
     *                        `{segment}`, `(:type)`, or `/user/([0-9]+)`.
     * @param (Closure(mixed ...$args):T|int)|string $callback The route handler or controller action.
     *
     * @return void
     */
    public static function any(string $pattern, Closure|string $callback): void;

    /**
     * Register a group HTTP routes under a shared URI prefix.
     *
     * The callback defines the routes belonging to the group, allowing
     * related routes to share a common base path or URI pattern.
     *
     * @param string $prefix The base URI path or pattern, such as `/blog`,
     *                       `{segment}`, `(:type)`, or `/account/([a-z])`.
     * @param Closure(object ...$di):void $callback A callback that registers the
     *                                      routes for the group.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Router::bind('/blog/', static function (Request $request) {
     *     Router::get('/', 'BlogController::blogs');
     *     Router::get('/id/([a-zA-Z0-9-]+)', 'BlogController::blog');
     * });
     * ```
     */
    public static function bind(string $prefix, Closure $callback): void;

    /**
     * Register a group of CLI commands under a shared command prefix.
     *
     * The callback defines the commands belonging to the group, allowing
     * related commands to be organized under a common group name.
     *
     * @param string $group The command group name, such as `blog` or `user`.
     * @param Closure(object ...$di):void $callback A callback that registers the
     *                                      commands for the group.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Router::group('blog', static function (Request $request) {
     *     Router::command('list', 'BlogController::blogs');
     *     Router::command('id/(:int)', 'BlogController::blog');
     * });
     * ```
     *
     * **CLI Usage:**
     * ```bash
     * php index.php blog list
     * php index.php blog id=4
     * ```
     */
    public static function group(string $group, Closure $callback): void;

    /**
     * Trigger an HTTP error response and stop route processing.
     *
     * Error handling is resolved in the following order:
     *
     * 1. Call `AppError::onTrigger()` when available.
     * 2. Execute a matching route-specific error handler, if registered.
     * 3. Execute the global (`/`) error handler, if registered.
     * 4. Display the default error page when no custom handler is available.
     *
     * @param int $status The HTTP status code to trigger.
     *
     * @return void
     */
    public static function trigger(int $status = 404): void;

    /**
     * Register a custom route placeholder pattern.
     *
     * Allows placeholders such as `(:slug)` to be mapped to custom regular
     * expression patterns, providing reusable aliases for complex or
     * frequently used route patterns.
     *
     * The pattern can optionally be wrapped as a capturing or non-capturing
     * group.
     *
     * @param string $name The placeholder name, such as `slug`.
     * @param string $pattern The regular expression pattern.
     * @param int|null $group The grouping mode:
     *                        - `null`: Use the pattern as provided.
     *                        - `0`: Wrap the pattern as a non-capturing group.
     *                        - `1`: Wrap the pattern as a capturing group.
     *                        If the pattern already starts with `(`, no
     *                        additional grouping is applied.
     *
     * @return void
     * @throws RouterException If the placeholder name is empty or uses a
     *                         reserved placeholder name such as `root` or
     *                         `base`.
     *
     * @see toPatterns() To convert a route placeholder to a regular expression.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-placeholder
     *
     * @since 3.6.8
     *
     * @example - Examples:
     * ```php
     * Router::pattern('slug', '[a-z0-9-]+');        // Raw pattern.
     * Router::pattern('slug', '[a-z0-9-]+', 0);     // Non-capturing group.
     * Router::pattern('slug', '[a-z0-9-]+', 1);     // Capturing group.
     * Router::pattern('slug', '([a-z]+)-(\d+)', 1); // Already grouped.
     * ```
     *
     * **Attribute Usage:**
     * ```php
     * #[Luminova\Attributes\Route('/blog/(:slug)', methods: ['GET'])]
     * public function blog(string $slug): int
     * {
     *     // Implement.
     * }
     * ```
     *
     * **Method Usage:**
     * ```php
     * Router::get('/blog/(:slug)', 'BlogController::view');
     * ```
     *
     * > **Important:** Do not include outer regex delimiters or anchors such
     * > as `^`, `$`, `#`, `/`, or `~`. The router adds them when constructing
     * > the final route expression.
     */
    public static function pattern(string $name, string $pattern, ?int $group = null): void;

    /**
     * Register a custom error handler.
     *
     * Registers the handler globally by default, or for a specific URI pattern
     * when `$pattern` is provided. If a handler is already registered for the
     * pattern, it is replaced by the new handler.
     *
     * @param (Closure(mixed ...$di):int)|array{0:class-string,1:string} $handler Error handler to register.
     * @param string $pattern URI pattern to associate with the handler (default: '/' global).
     *
     * @return void
     *
     * @throws RouterException If the handler is not a valid callable.
     *
     * @example - Examples:
     * 
     * The handler may be a closure, a controller callback such as
     * `[ControllerClass::class, 'method']`, or a callable class name.
     * 
     * ```php
     * // Global error handler.
     * Router::onError([AppError::class, 'onError']);
     *
     * // Error handler for a specific URI.
     * Router::onError([AppError::class, 'onError'], '/users/');
     *
     * // Using a closure.
     * Router::onError(function (int $status, Segment $segments, Application $app): int {
     *     // Handle the error.
     * }, '/admin');
     * ```
     */
    public static function onError(
        Closure|array $handler,
        string $pattern = '/',
    ): void;

    /**
     * Get the registered controller namespaces.
     *
     * @return array<int,string> The registered controller namespaces.
     */
    public static function getNamespaces(): array;

    /**
     * Get the current request URI path.
     *
     * The path is normalized with a leading `/` and without a trailing `/`.
     * The query string is excluded.
     *
     * @return string The normalized URI path.
     *
     * @example - Example:
     * `https://example.com/products/view/10` → `/products/view/10`
     * `https://example.com/products/view/10?foo=bar` → `/products/view/10`
     * `https://example.com` → `/`
     */
    public static function getUriPath(): string;

    /**
     * Get the URI segments as an array.
     *
     * Splits the current request URI path into individual segments and removes
     * the `public` prefix when it appears as the first segment.
     *
     * @return array<int,string> The URI segments.
     * 
     * @see self::getSegment()
     *
     * @example - Examples:
     * `/public/foo/bar` → `['foo', 'bar']`
     * `/public`         → `['']`
     * `/products/view/10` → `['products', 'view', '10']`
     * `/`               → `['']`
     */
    public static function getUriSegments(): array;

    /**
     * Get the current request URI segments.
     *
     * @return Segments The URI segments for the current request.
     *
     * @see Segments
     *
     * @example
     * ```php
     * $segments = Router::getSegment();
     *
     * $segments->position(0);
     * $segments->toString();
     * ```
     */
    public static function getSegment(): Segments;

    /**
     * Determine whether a URI segment matches the given name.
     *
     * @param string $name The URI segment name to match.
     * @param int $position The zero-based segment position.
     *
     * @return bool True if the segment at the given position matches the name.
     *
     * @example - Prefix:
     * ```php
     * if (Router::isSegment('admin')) {
     *     // Matches: /admin or /admin/users
     * }
     * ```
     *
     * @example - Position:
     * ```php
     * if (Router::isSegment('users', 1)) {
     *     // Matches: /admin/users or /api/users
     * }
     * ```
     */
    public static function isSegment(string $name, int $position = 0): bool;

    /**
     * Determine whether the first URI segment matches any given prefix.
     *
     * @param array|string $prefix One or more URI prefixes to match.
     *
     * @return bool True if the first URI segment matches any given prefix.
     *
     * @see self::isApiPrefix()
     * @see self::isApiRequest()
     *
     * @example - Single Prefix:
     * ```php
     * if (Router::isUriPrefix('admin')) {
     *     // Matches: /admin or /admin/users
     * }
     * ```
     *
     * @example - Multiple Prefixes:
     * ```php
     * if (Router::isUriPrefix(['api', 'webhook'])) {
     *     // Matches: /api/* or /webhook/*
     * }
     * ```
     */
    public static function isUriPrefix(array|string $prefix): bool;

    /**
     * Determine whether the current request uses the configured API URI prefix.
     *
     * The request matches when its first URI segment equals the configured API
     * prefix, such as `/api` or a custom prefix defined by `app.api.prefix`.
     *
     * @return bool True if the first URI segment matches the configured API prefix.
     *
     * @see self::isApiRequest()
     * @see self::isUriPrefix()
     * @see Luminova::apiPrefix()
     */
    public static function isApiPrefix(): bool;

    /**
     * Determine whether the current request should be treated as an API request.
     *
     * A request is considered an API request when its first URI segment matches
     * the configured API prefix, or when AJAX requests are configured to qualify
     * as API requests.
     *
     * When `$ajaxAsApi` is null, the value is resolved from
     * `app.validate.ajax.asapi`.
     *
     * @param bool|null $ajaxAsApi Whether to treat AJAX requests as API requests.
     *                             When null, uses the application configuration.
     *
     * @return bool True if the request matches the API prefix or qualifies as AJAX.
     *
     * @see self::isApiPrefix()
     * @see self::isUriPrefix()
     */
    public static function isApiRequest(?bool $ajaxAsApi = null): bool;
}