<?php
declare(strict_types=1);
/**
 * Luminova Framework Routing system.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Routing;

use \Closure;
use \Exception;
use \Throwable;
use \Luminova\Boot;
use \ReflectionClass;
use \ReflectionMethod;
use \Luminova\Luminova;
use \ReflectionFunction;
use \ReflectionUnionType;
use \ReflectionException;
use \ReflectionNamedType;
use \Luminova\Http\Header;
use \Luminova\Base\Command;
use \Luminova\Http\HttpStatus;
use \Luminova\Command\Terminal;
use \Luminova\Template\Response;
use \ReflectionIntersectionType;
use \Luminova\Command\Utils\Color;
use \Luminova\Debugger\Performance;
use \Luminova\Command\Consoles\Commands;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Foundation\Core\Application;
use \Luminova\Attributes\Internal\Compiler;
use \App\Errors\Controllers\ErrorController;
use \Luminova\Routing\{DI, Prefix, Segments};
use function \Luminova\Funcs\{filter_paths};
use \Luminova\Exceptions\{ErrorCode, AppException, RouterException};
use \Luminova\Interface\{
    RoutableInterface, 
    RouterInterface, 
    ErrorHandlerInterface, 
    ViewResponseInterface,
    ResponseInterface as HttpResponseInterface
};

final class Router implements RouterInterface
{
    /**
     * Accept any incoming HTTP request methods.
     * 
     * @var string ANY_METHOD
     */
    public const ANY_METHOD = 'ANY';

    /**
     * Custom CLI URI.
     * 
     * @var string CLI_URI
     * @internal
     */
    public const CLI_URI = '__cli__';
    
    /**
     * All allowed HTTP request methods.
     * 
     * @var array<string,string> $httpMethods
     */
    private static array $httpMethods = [
        'GET'       => 'GET', 
        'POST'      => 'POST', 
        'PATCH'     => 'PATCH', 
        'DELETE'    => 'DELETE', 
        'PUT'       => 'PUT', 
        'OPTIONS'   => 'OPTIONS', 
        'HEAD'      => 'HEAD',
        'CLI'       => 'CLI' //Fake a request method for cli
    ];
    
    /**
     * Current route base group, used for (sub) route mounting.
     * 
     * @var string $base
     */
    private static string $base = '';

    /**
     * The current request method.
     * 
     * @var string $method
     */
    private static string $method = '';

    /**
     * The current request Uri. 
     * 
     * @var string $uri
     */
    private static string $uri = '';

    /**
     * The normalized static Uri version. 
     * 
     * @var string|null $staticCacheUri
     */
    public static ?string $staticCacheUri = null;

    /**
     * Application registered controllers namespace.
     * 
     * @var array $namespace
     */
    private static array $namespace = [];

    /**
     * Custom placeholder pattern.
     * 
     * @var array<string,string> $placeholders 
     */
    private static array $placeholders = [];

    /**
     * Whether router is running in cli mode.
     * 
     * @var bool $isCommand
     */
    private static bool $isCommand = false;

    /**
     * Allow Dependency injection.
     * 
     * @var bool $isDIEnabled 
     */
    private static bool $isDIEnabled = false;

    /**
     * Flag to terminate router run immediately.
     * 
     * @var bool $terminate 
     */
    private static bool $terminate = false;

    /**
     * Information about command execution.
     * 
     * @var array $commands
     */
    private static array $commands = [];

    /**
     * All registered routes.
     * 
     * @var array $routes
     */
    private static array $routes = [];

    /**
     * Application is HMVC.
     * 
     * @var bool $isHmvcModule 
     */
    private bool $isHmvcModule = false;

    /**
     * Application object.
     * 
     * @var Application|null $app
     */
    private static ?Application $app = null;

    /**
     * Initializes the Router class and sets up default properties.
     * 
     * @param Application|null $app Instance of core application class.
     */
    public function __construct(?Application $app = null)
    {
        self::$isCommand = false;
        self::$isDIEnabled = env('feature.route.dependency.injection', false);
        self::$app = $app ?? Boot::application();

        $this->isHmvcModule = self::$app::$isHmvcModule 
            ?? env('feature.app.hmvc', false);

        if(Luminova::isCommand()){
            self::$isCommand = true;
            Terminal::init();
        }

        self::reset(true);
        Luminova::profiling('start');
    }

    /**
     * {@inheritdoc}
     */
    public function context(Prefix|array ...$contexts): self 
    {
        self::onInitialized();
        self::$uri = self::getUriSegments();

        $prefix = self::getPrefix();

        // Application start event.
        self::$app->__on('onStart', [
            'cli' => self::$isCommand ,
            'method' => self::$method,
            'uri' => self::$uri,
            'module' => $prefix
        ]);

        // When using attribute for routes.
        if(env('feature.route.attributes', false)){
           return $this->withAttributes($prefix);
        }

        // When using default context manager.
        if($contexts === null || $contexts === []){
           RouterException::rethrow('no.context', ErrorCode::RUNTIME_ERROR);
        }
        
        if (isset(self::$httpMethods[self::$method])) {
           return $this->withMethods($prefix, $contexts);
        }
        
        RouterException::rethrow('no.route.handler', ErrorCode::RUNTIME_ERROR);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function get(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'GET', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function post(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'POST', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function patch(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'PATCH', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function delete(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'DELETE', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function put(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'PUT', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function options(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'OPTIONS', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function any(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', self::ANY_METHOD, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function middleware(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
                '$methods'
            ]);
            return;
        }

        self::http('http.middleware', $methods, $pattern, $callback, true);
    }

    /**
     * {@inheritdoc}
     */
    public static function after(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
                '$methods'
            ]);
            return;
        }

        self::http('http.after', $methods, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function guard(string $group, Closure|string $callback): void
    {
        if(!self::$isCommand){
            RouterException::rethrow('invalid.middleware.cli');
        }

        $group = trim($group, '/');

        if (!$group || !preg_match('/^[a-z][a-z0-9_:-]*$/u', $group)) {
            RouterException::rethrow('invalid.cli.group', ErrorCode::INVALID_ARGUMENTS, [$group]);
        }

        self::$routes['cli.middleware']['CLI'][$group][] = [
            'callback' => $callback,
            'pattern' => $group,
            'middleware' => true
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function capture(string $methods, string $pattern, Closure|string $callback): void
    {
        if (!$methods) {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
               '$methods'
            ]);
            return;
        }

        self::http('http.routes', $methods, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public static function command(string $command, Closure|string $callback): void
    {
        self::$routes['cli.commands']['CLI'][] = [
            'callback' => $callback,
            'pattern' => self::toPatterns(trim($command, '/'), true),
            'middleware' => false
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function bind(string $prefix, Closure $callback): void
    {
        $current = self::$base;
        self::$base .= rtrim($prefix, '/');

        $callback(...self::noneParamInjection($callback));
        self::$base = $current;
    }

    /**
     * {@inheritdoc}
     */
    public static function group(string $group, Closure $callback): void
    {
        self::$routes['cli.groups'][$group][] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public static function onError(Closure|array|string $pattern, Closure|array|string|null $handler = null): void
    {
        $isPatternString = is_string($pattern);
        $message = 'a callable (closure or [Controller::class, method])';

        if ($handler === null) {
            if ($isPatternString && str_contains($pattern, '/')) {
                throw new RouterException(
                    "Invalid arguments: when defining a global error handler, '\$pattern' must be {$message}, not a URI.",
                    ErrorCode::INVALID_ARGUMENTS
                );
            }

            self::$routes['http.errors']['/'] = $pattern;
            return;
        }

        if (!$isPatternString) {
            throw new RouterException(
                'Invalid arguments: "$pattern" must be a URI string when a callback is provided.',
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        if (is_string($handler) && str_contains($handler, '/')) {
            throw new RouterException(
                "Invalid arguments: '\$handler' cannot be a URI string. Provide {$message}.",
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        self::$routes['http.errors'][self::toPatterns($pattern)] = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace): self
    {
        $namespace = trim($namespace, " \\\\");
        
        if($namespace === '') {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
                '$namespace'
            ]);

            return $this;
        }

        if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*)(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
            RouterException::rethrow(
                'invalid.namespace',
                ErrorCode::NOT_ALLOWED,
                [$namespace]
            );
            return $this;
        }

        $namespace = "\\{$namespace}\\";

        if (!$this->isNamespace($namespace)) {
            return $this;
        }

        self::$namespace[] = $namespace;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        if((self::$isCommand || self::$method === 'CLI') && self::hasCommand('no-profiling')){
            Performance::disable();
        }
        
        if(self::$terminate){
            Luminova::profiling('stop');
            exit(STATUS_SUCCESS);
        }

        $isCommand = self::$method === 'CLI';
        $exitCode = STATUS_ERROR;

        if($isCommand && !self::$isCommand){
            RouterException::rethrow('invalid.request.method', ErrorCode::INVALID_REQUEST_METHOD, [
                self::$method,
                'CLI'
            ]);
        }

        if(!$isCommand && self::$isCommand){
            RouterException::rethrow('invalid.request.method', ErrorCode::INVALID_REQUEST_METHOD, [
                self::$method,
                'HTTP'
            ]);
        }

        try{
            $exitCode = $isCommand 
                ? $this->runAsCommand() 
                : $this->runAsHttp();

            Luminova::profiling('stop', $isCommand ? self::$commands : null);
        }catch(Throwable $e){
            if(PRODUCTION){
                RouterException::throwException($e->getMessage(), $e->getCode(), $e);
                return;
            }

            throw $e;
        }

        ob_start();
        self::$app->__on('onFinish', Boot::get('__CLASS_METADATA__'));
        Boot::tips();
        ob_end_flush();
        exit($exitCode);
    }

    /**
     * {@inheritdoc}
     */
    public static function trigger(int $status = 404): void
    {
        self::onTriggerError($status, true);
    }

    /**
     * {@inheritdoc}
     */
    public static function pattern(string $name, string $pattern, ?int $group = null): void
    {
        $name = trim($name);

        if ($name === '' || $pattern === '') {
            throw new RouterException(
                ($name === '') 
                    ? 'Placeholder name cannot be empty.' 
                    : 'Placeholder pattern cannot be empty.',
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        if (!preg_match('/^(?:\(\?:|\(:)?([A-Za-z][A-Za-z0-9._-]*)(?:\))?$/', $name)) {
            throw new RouterException(
                sprintf(
                    'Invalid placeholder name "%s". Must start with a letter and contain ' . 
                    'only letters, numbers, dot, underscore, or hyphen.',
                    $name
                ),
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        if (!str_starts_with($name, '(:')) {
            $name = "(:$name)";
        }

        static $forbidden = [
            '(:root)' => true,
            '(:base)' => true,
        ];

        if (isset($forbidden[$name])) {
            throw new RouterException(
                sprintf('The placeholder name "%s" is reserved and cannot be override.', $name),
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        if ($group !== null && !str_starts_with($pattern, '(')) {
            if ($group === 0) {
                $pattern = '(?:' . $pattern . ')';
            } elseif ($group === 1) {
                $pattern = '(' . $pattern . ')';
            }
        }

        self::$placeholders[$name] = $pattern;
    }

    /**
     * {@inheritdoc}
     */
    public static function getNamespaces(): array
    {
        return self::$namespace;
    }

    /**
     * {@inheritdoc}
     */
    public static function getUriSegments(): string
    {
        return self::$isCommand 
            ? self::CLI_URI 
            : (self::$staticCacheUri ?? Luminova::getUriSegments());
    }

    /**
     * {@inheritdoc}
     */
    public function getSegment(): Segments 
    {
        return new Segments(self::$isCommand ? [self::CLI_URI] : Luminova::getSegments());
    }

    /**
     * Check if the current request URI starts with the given prefix.
     *
     * Useful for route matching or highlighting navigation items based on URI segments.
     *
     * @param string $prefix The URI prefix to check against. Can be a partial path like "/admin".
     *
     * @return bool Returns `true` if the current URI starts with the specified prefix, otherwise `false`.
     *
     * @example - Example:
     * ```php
     * if (Router::isPrefix('/admin')) {
     *     // Current page is under /admin section
     * }
     *
     * if (Router::isPrefix('')) {
     *     // Current page is root "/"
     * }
     * ```
     */
    public static function isPrefix(string $prefix): bool
    {
        $prefix = trim($prefix, ' /');
        $segments = trim(Luminova::getSegments()[0] ?? '', ' /');

        return ($segments === $prefix || ($segments === '/' && $prefix === ''));
    }

    /**
     * Load required route context only.
     * 
     * Load the route URI context prefix and make router/application available
     * as global variables inside the context file.
     *
     * @param string $context Route URI context prefix name.
     * 
     * @return void
     * @throws RouterException
     */
    private function onContext(string $context): void 
    {
        $path = APP_ROOT . 'routes' . DIRECTORY_SEPARATOR . $context . '.php';

        if (!is_file($path)) {
            self::ePrint(
                message: RouterException::getInformation('invalid.context', $context), 
                status: 500
            );
        }

        Closure::bind(
            static function (string $context, string $path, RouterInterface $router, Application $app): void {
                require_once $path;
            }, 
            null, 
            null
        )($context, $path, $this, self::$app);
    }

    /**
     * Triggers an HTTP error response and terminates route execution.
     *
     * This method is invoked when no route matches or when a specific 
     * HTTP error code must be returned. If `$global` is true, it prioritizes 
     * the controller's `onTrigger` handler before falling back to custom 
     * error routes or default error output.
     *
     * @param int  $status HTTP status code to send (default: 404).
     * @param bool $global Whether to invoke the global error handler first.
     *                     If true, calls `ErrorController::onTrigger()` before checking other handlers.
     *
     * @return void
     */
    private static function onTriggerError(
        int $status = 404, 
        bool $global = false
    ): void
    {
        Header::clearOutputBuffers('all');

        if($global && method_exists(ErrorController::class, 'onTrigger')){
            ErrorController::onTrigger(self::$app, $status, Luminova::getSegments());
            exit;
        }

        if(self::handleErrors()){
            exit;
        }

        if(!$global && method_exists(ErrorController::class, 'onTrigger')){
            ErrorController::onTrigger(self::$app, $status, Luminova::getSegments());
            exit;
        }

        self::ePrint(
            message: PRODUCTION 
                ? 'The requested resource could not be found on the server.'
                : "An error occurred:\n\n" . 
                "- No controller is registered to handle the requested URL.\n" . 
                "- Alternatively, a custom error handler is missing for this URL prefix in the controller.\n" . 
                "- Additionally, check your Controller class's prefix pattern to ensure it doesn't exclude the URL.",
            status: $status
        );
        exit;
    }

    /**
     * Handle route errors.
     * 
     * @return bool Return true if error was handled, otherwise false.
     */
    private static function handleErrors(): bool
    {
        foreach (self::$routes['http.errors'] as $pattern => $callable) {
            $matches = [];

            if(!self::uriCapture($pattern, self::$uri, $matches)){
                continue;
            }

            $status = self::call($callable, self::urisToArgs($matches), true);

            if ($status === STATUS_SUCCESS || $status === STATUS_SILENCE) {
                return true;
            }
        }
    
        $root = self::$routes['http.errors']['/'] ?? null;

        if(!$root){
            return false;
        }

        $status = self::call($root, [], true);
        return ($status === STATUS_SUCCESS || $status === STATUS_SILENCE);
    }

    /**
     * Normalize a callback into a [class, method] array format.
     *
     * Supports different notations:
     * - ['ClassName', 'method'] (standard array callable)
     * - 'ClassName::method' (static callable string)
     * - 'ClassName@method' (Annotation callable string-style)
     * - 'ClassName' (fallback to __invoke)
     *
     * @param array|string $callback The callback to normalize.
     * 
     * @return array{string:?namespace,string:?method} Returns a [class, method] pair if invalid.
     */
    private static function getClassHandler(array|string $callback): array
    {
        if (is_array($callback)) {
            return $callback + [null, null];
        }

        $annotation = str_contains($callback, '::') 
            ? '::' 
            : (str_contains($callback, '@') ? '@' : null);

        if ($annotation === null) {
            return $callback 
                ? [$callback, '__invoke'] 
                : [null, null];
        }

        [$class, $method] = explode($annotation, $callback, 2);

        return [self::getClassNamespace($class), $method];
    }
    
    /**
     * If the controller already contains a namespace, use it directly.
     * 
     * If not, loop through registered namespaces to find the correct class.
     * 
     * @param string $className Controller class base name.
     * 
     * @return class-string<RoutableInterface> Return full qualify class namespace.
     */
    private static function getClassNamespace(string $className): string
    {
        if (str_contains($className, '\\') || class_exists($className)) {
            return $className;
        }

        $prefix = self::$isCommand ? 'Cli\\' : 'Http\\';

        foreach (self::$namespace as $namespace) {
            $class = "{$namespace}{$prefix}{$className}";

            if (class_exists($class)) {
                return $class;
            }
        }

        if(self::$isCommand){
            return '';
        }

        $class = '\\App\\Errors\\Controllers\\' . $className;
        
        return class_exists($class) ? $class : '';
    }

    /**
     * Validate controller namespace 
     * 
     * @param string $namespace The namespace.
     * 
     * @return bool Return true if valid, otherwise false or throw exception.
     * @throws RouterException If on development
     */
    private function isNamespace(string $namespace): bool
    {
        $design = 'MVC';

        if($this->isHmvcModule){
            $design = 'HMVC';
            if (!str_starts_with($namespace, '\\App\\Modules\\')) {
                RouterException::rethrow(
                    'invalid.namespace.root',
                    ErrorCode::NOT_ALLOWED,
                    ['HMVC', $namespace, '\\App\\Modules\\', ', (e.g., "\App\Modules\<Module>\Controllers\")']
                );
                return false;
            }
        }elseif (!str_starts_with($namespace, '\\App\\') || str_starts_with($namespace, '\\App\\Modules\\')) {
            RouterException::rethrow(
                'invalid.namespace.root',
                ErrorCode::NOT_ALLOWED,
                ['MVC', $namespace, '\\App\\', ', (e.g., "\App\Controllers\")']
            );
            return false;
        }

        if (!str_ends_with($namespace, '\\Controllers\\')) {
            RouterException::rethrow(
                'invalid.namespace.end',
                ErrorCode::NOT_ALLOWED,
                [$design, $namespace]
            );
            return false;
        }

        return true;
    }

    /**
     * Initialize routing system to handle incoming requests.
     * 
     * Register the request method, considering method overrides and set proper output handler.
     * 
     * @return void
     */
    private static function onInitialized(): void
    {
        if(self::$isCommand){
            self::$method = 'CLI';
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        Header::clearOutputBuffers('all');

        if($method === 'HEAD'){
            self::$method = $method;
            return;
        }

        if($method === 'POST'){
            $override = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ''));
            
            if ($override && in_array($override, ['PUT', 'DELETE', 'PATCH', 'OPTIONS'], true)) {
                self::$method = $override;
                return;
            }
        }
        
        self::$method = $method;
        return;
    }
    
    /**
     * Show error message with proper header and status code.
     * 
     * @param string|null $header Header title of the error message.
     * @param string|null $message Optional message body to display.
     * @param int $status HTTP status code.
     * 
     * @return void
     */
    private static function ePrint(?string $header = null, ?string $message = null, int $status = 404): void 
    {
        $header ??= HttpStatus::phrase($status);
        $message ??= $header;

        if (self::$isCommand) {
            Header::clearOutputBuffers('all');
            Terminal::error(sprintf('(%d) [%s] %s', $status, $header, $message));
            exit(STATUS_ERROR);
        }

        Header::terminate($status, $message, $header);
        exit(STATUS_ERROR);
    }

    /**
     * Register a http route.
     *
     * @param string $to The routing group name to add this route.
     * @param string $methods  Allowed methods, can be serrated with | pipe symbol.
     * @param string $pattern The route URL pattern or template view name (e.g, `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
     * @param Closure|string $callback Callback function to execute.
     * @param bool $terminate Terminate if it before middleware.
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
     */
    private static function http(
        string $to, 
        string $methods, 
        string $pattern, 
        Closure|string $callback, 
        bool $terminate = false
    ): void
    {
        if(self::$isCommand){
            RouterException::rethrow('invalid.middleware.http');
        }

        $pattern = self::$base . '/' . trim($pattern, '/');
        $pattern = self::toPatterns((self::$base !== '') ? rtrim($pattern, '/') : $pattern);

        foreach (explode('|', $methods) as $method) {
            self::$routes[$to][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => $terminate
            ];
        }
    }

    /**
     * Get view segment URI prefix.
     * 
     * @return string Return the URI segment prefix.
     */
    private static function getPrefix(): string
    {
        if(self::$isCommand){
            return self::CLI_URI;
        }

        return Luminova::getSegments()[0] ?? '';
    }

    /**
     * Extract context prefix from array context arguments.
     * 
     * @param array<int,array> $contexts The context arguments as an array.
     * 
     * @return array<string,string> Return array of context prefixes.
     */
    private function getArrayPrefixes(array $contexts): array 
    {
        $prefixes = [];

        foreach ($contexts as $item) {
            $prefix = $item['prefix'] ?? null;

            if ($prefix === Prefix::WEB || $prefix === null || $prefix === '') {
                continue;
            }

            $prefixes[$prefix] = $prefix;
        }

        return $prefixes;
    }

    /**
     * Setup error handlers if any to handle request.
     * 
     * @param string $name The context prefix name.
     * @param Closure|array|null $onError Context error handler.
     * @param string $prefix The request URI prefix.
     * @param array<string,string> $prefixes List of context prefix names without web context as web is default.
     * 
     * @return bool|int{0} Return true if context match was found, 
     *      0 if method is CLI but not in CLI mode, otherwise false.
     */
    private static function setErrorHandler(
        string $name, 
        Closure|array|null $onError, 
        string $prefix, 
        array $prefixes
    ): bool|int
    {
        if($prefix === $name) {
            if ($name === Prefix::CLI){
                if(self::$isCommand) {
                    defined('CLI_ENVIRONMENT') || define('CLI_ENVIRONMENT', env('cli.environment.mood', 'testing'));
                    return true;
                }

                return 0;
            }
            
            if($onError !== null){
                self::onError($onError);
            }
        
            if (isset($prefixes[$name])) {  
                self::$base .= '/' . $name;
            }

            return true;
        }
        
        if(!isset($prefixes[$prefix]) && self::isWeContext($name, $prefix)) {
            if($onError !== null){
                self::onError($onError);
            }

            return true;
        }
        
        return false;
    }

    /**
     * Is context a web instance.
     *
     * @param string $result The context name.
     * @param string $prefix The first uri prefix.
     * 
     * @return bool Return true if the context is a web instance, otherwise false.
     */
    private static function isWeContext(string $result, string $prefix): bool 
    {
        return (
            $prefix === '' || 
            $result === Prefix::WEB
        ) && $result !== Prefix::CLI && $result !== Prefix::API && !Luminova::isApiPrefix();
    }

    /**
     * Run the CLI router and application, Loop all defined CLI routes
     * 
     * @return int Return status success or failure.
     * @throws RouterException Throws if an error occurs while running cli routes.
     */
    private function runAsCommand(): int
    {
        $group = self::getArgument();
        $command = self::getArgument(2);

        if(!$group || !$command || ($isHelp = Terminal::isHelp($group))){
            Terminal::header();

            if($isHelp || Terminal::isHelp()){
                Terminal::helper(Commands::get('help'));
            }

            return STATUS_SUCCESS;
        }

        $global = (self::$routes['cli.middleware'][self::$method]['global']??null);

        if($global !== null && !self::handleCommand($global)){
            return STATUS_ERROR;
        }
        
        $groups = (self::$routes['cli.groups'][$group] ?? null);
        
        if($groups !== null){
            foreach($groups as $group){
                if(isset($group['callback'])){
                    self::command($group['pattern'], $group['callback']);
                    continue;
                }

                $group(...self::noneParamInjection($group));
            }

            $middleware = (self::$routes['cli.middleware'][self::$method][$group] ?? null);

            if($middleware !== null && !self::handleCommand($middleware)){
                return STATUS_ERROR;
            }
            
            $routes = self::$routes['cli.commands'][self::$method] ?? null;

            if ($routes !== null && self::handleCommand($routes)) {
                self::$app->__on('onCommandPresent', self::getCommandSegment());
                return STATUS_SUCCESS;
            }
        }

        $command = Color::style("'{$group} {$command}'", 'red');
        Terminal::fwrite('Unknown command ' . $command . ' not found', Terminal::STD_ERR);

        return STATUS_ERROR;
    }

    /**
     * Run the HTTP router and application.
     * Loop all defined HTTP request method and view routes.
     *
     * @return int Return status success, status error on failure.
     * @throws RouterException Throws if any error occurs while running HTTP routes.
     */
    private function runAsHttp(): int
    {
        $middleware = self::getRoutes('http.middleware'); 

        if ($middleware !== [] && self::handleWebsite($middleware, self::$uri) !== STATUS_SUCCESS) {
            return STATUS_ERROR;
        }

        $error = null;
        $routes = self::getRoutes('http.routes', $error);

        if($routes === []){
            self::ePrint(
                message: sprintf($error 
                    ? 'The requested resource could not be located or is unavailable.'
                    : 'Request method "%s" is not allowed.', 
                    self::$method
                ), 
                status: $error ?? 405
            );
            return STATUS_ERROR;
        }

        $status = self::handleWebsite($routes, self::$uri);

        if ($status === STATUS_SILENCE) {
            return STATUS_ERROR;
        }

        if ($status === STATUS_SUCCESS) {
            ob_start();
            $after = self::getRoutes('http.after');
            
            if($after !== []){
                self::handleWebsite($after, self::$uri);
            }

            self::$app->__on('onViewPresent', self::$uri);
            ob_end_clean(); 
            return STATUS_SUCCESS;
        }

        self::onTriggerError();
        return STATUS_ERROR;
    }

    /**
     * Retrieve the registered HTTP routes for a specific controller.
     * 
     * @param string $from The name of the controller for which to retrieve the routes.
     * 
     * @return array Return an array of routes registered for the given controller 
     * and HTTP method, or an empty array if none are found.
     */
    private static function getRoutes(string $from, ?int &$error = null): array 
    {
        if(!(self::$routes[$from] ?? null)){
            $error = 404;
            return [];
        }

        return array_merge(
            self::$routes[$from][self::$method] ?? [], 
            self::$routes[$from][self::ANY_METHOD] ?? []
        );
    }
    
    /**
     * Handle a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes Collection of route patterns and their handling functions.
     * @param string $uri The view request URI path.
     *
     * @return int Return status code.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function handleWebsite(array $routes, string $uri): int
    {
        foreach ($routes as $route) {
            $matches = [];
            $match = self::uriCapture($route['pattern'], $uri, $matches);
            $passed = $match 
                ? self::call(
                    $route['callback'], 
                    self::urisToArgs($matches), 
                    isHttpMiddleware: $route['middleware']
                 )
                : STATUS_ERROR;
      
            if ((!$match && $route['middleware']) || ($match && $passed === STATUS_SUCCESS)) {
                return STATUS_SUCCESS;
            }

            if($match && $passed === STATUS_SILENCE){
                return STATUS_SILENCE;
            }
        }
       
        return STATUS_ERROR;
    }

    /**
     * Handle C=command router CLI callback class method with the given parameters 
     * using instance callback or reflection class.
     *
     * @param array $routes Command name array values.
     *
     * @return bool Return true on success or false on failure.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function handleCommand(array $routes): bool
    {
        self::$commands = Terminal::parseCommands($_SERVER['argv'] ?? [], true);
        
        $queries = self::getCommandSegment();
        $isHelp = Terminal::isHelp();
        
        foreach ($routes as $route) {
            if($route['middleware']){
                return self::call(
                    $route['callback'], 
                    self::$commands, 
                    isCliMiddleware: true
                ) === STATUS_SUCCESS;
            }

            $matches = [];

            if (self::uriCapture($route['pattern'], $queries['view'], $matches)) {
                self::$commands['params'] = self::urisToArgs($matches);

                return self::call($route['callback'], self::$commands) === STATUS_SUCCESS;
            } 
            
            if ($queries['view'] === $route['pattern'] || $isHelp) {
                return self::call($route['callback'], self::$commands) === STATUS_SUCCESS;
            }
        }

        return false;
    }

    /**
     * Convert matched URI segments into trimmed method arguments.
     *
     * @param array<int,array> $uris Matched URI path segments from regex.
     * 
     * @return string[] Return array of trimmed matched parameters (excluding full match).
     */
    private static function urisToArgs(array $uris): array
    {
        $params = [];
        foreach ($uris as $match) {
            $params[] = (isset($match[0][0]) && $match[0][1] !== -1)
                ? trim($match[0][0], " \t\n\r\0\x0B/")
                : '';
        }
        return array_slice($params, 1);
    }

    /**
     * Check if a request URI matches a given route pattern and capture parameters.
     * 
     * Converts the route pattern into a regex and checks for matches.
     *
     * @param string $pattern The route regex pattern (e.g., `/api/users/([0-9-.]+)`).
     * @param string $uri The incoming request URI (e.g., `/api/users/123456/`).
     * @param array &$matches Reference to store regex match segments.
     *
     * @return bool Return true if the URI matches the pattern, false otherwise.
     */
    private static function uriCapture(string $pattern, string $uri, array &$matches): bool
    {
        if (!preg_match_all("#^{$pattern}$#x", $uri, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        
        return preg_last_error() === PREG_NO_ERROR;
    }

    /**
     * Normalizes placeholders to a valid regex patterns.
     * 
     * Examples: 
     * - `/(:root)` to `/?(?:/[^/].*)?`
     * - `/{name}` to `/(.*?)`
     * 
     * It also ensures that root placeholders comes after `/` (e.g, `users(:root)` to `users/(:root)`).
     *
     * @param string $input The input containing placeholders to normalize.
     * @param bool $cli Optional. If true, trim the output and append '/'.
     * 
     * @return string Return normalized regular expression patterns.
     * 
     * @internal Used in routing system and attribute compiling
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-placeholder
     */
    public static function toPatterns(string $input, bool $cli = false): string
    {
        if(!$input || $input === '/'){
            return '/';
        }

        // Predefined placeholders like '/(:int)/(:string)'
        if (str_contains($input, '(:')) {
            $placeholders = self::getPlaceholders();
            
            // Ensure '/(:root)' always has a leading slash
            //$input = preg_replace('/(?<!\/)\(:root\)/', '/(:root)', $input);
            
            // Ensure '/(:root)' and '/(:base)' always have a leading slash
            $input = preg_replace('/(?<!\/)\(:root\)|(?<!\/)\(:base\)/', '/$0', $input);

            // Replace placeholders with their corresponding patterns
            $input = str_replace(
                array_keys($placeholders), 
                array_values($placeholders), 
                $input
            );
        }

        // Named placeholders like '/{name}/{id}' → '/(.*?)'
        $input = preg_replace('/\/{(.*?)}/', '/(.*?)', $input);

        return $cli ? '/' . ltrim($input, '/') : $input;
    }

    /**
     * Dependency injection and parameter casting.
     *
     * @param ReflectionMethod|callable $caller Class method or callback closure.
     * @param string[] $arguments Method arguments to pass to callback method.
     * @param bool $forceInjection Force use of dependency injection.
     *
     * @return array<int,mixed> Return method params and arguments-value pairs.
     * @internal 
     */
    private static function injection(
        ReflectionMethod|callable $caller, 
        array $arguments = [], 
        bool $forceInjection = false
    ): array
    {
        self::$isDIEnabled = self::$isDIEnabled || $forceInjection;
        
        if (!self::$isDIEnabled && $arguments === []) {
            return $arguments;
        }

        try {
            $parameters = [];
            $caller = ($caller instanceof ReflectionMethod) ? $caller : new ReflectionFunction($caller);

            if ($caller->getNumberOfParameters() === 0 && ($found = count($arguments)) > 0) {
                RouterException::rethrow('bad.method', ErrorCode::BAD_METHOD_CALL, [
                    ($caller->isClosure() ? $caller->getName() : $caller->getDeclaringClass()->getName() . '->' . $caller->getName()),
                    $found,
                    filter_paths($caller->getFileName()),
                    $caller->getStartLine()
                ]);

                return $arguments;
            }

            $supported = ['string', 'int', 'float', 'double', 'bool'];

            foreach ($caller->getParameters() as $parameter) {
                $type = $parameter->getType();
                $default = '__no_default__';
                $hint = null;
                $isUnion = false;

                if($type instanceof ReflectionUnionType){
                    $isUnion = true;
                    [$hint, $nullable, $builtin] = self::getUnionTypes($type->getTypes(), $supported);
                }elseif($type instanceof ReflectionNamedType){
                    $hint = $type->getName();
                    $nullable = $type->allowsNull();
                    $builtin = $type->isBuiltin();
                }
                
                if($hint === null){
                    continue;
                }

                if($builtin && $arguments === []){
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    $default = $parameter->getDefaultValue();
                }

                if(!self::$isDIEnabled){
                    if(!$builtin){
                        continue;
                    }

                    $parameters[] = self::typeCasting(
                        $hint, 
                        array_shift($arguments), 
                        $nullable, 
                        $default, 
                        $isUnion
                    );
                }
                
                $parameters[] = $builtin 
                    ? self::typeCasting($hint, array_shift($arguments), $nullable, $default, $isUnion)
                    : self::newInstance($hint, $nullable, $default);
            }

            return array_merge(
                $parameters, 
                $arguments // Merge the remaining if any
            );
        } catch (ReflectionException) {
            return $arguments;
        }
    }

    /**
     * Dependency injection for closures that doesn't expect url parameters.
     * 
     * @param Closure $callback A closure to inject.
     * 
     * @return array<int,mixed> An array of parameters.
     */
    private static function noneParamInjection(Closure $callback): array 
    {
        $injections = [];

        foreach ((new ReflectionFunction($callback))->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $injections[] = self::newInstance(
                    $type->getName(), 
                    $type->allowsNull()
                );
            }
        }
        
        return $injections;
    }

    /**
     * Execute router HTTP callback class method with the given parameters using instance callback or reflection class.
     *
     * @param Closure|array{0:class-string<RoutableInterface>,1:string}|string $callback Class public callback method (e.g, UserController:update).
     * @param array $arguments Method arguments to pass to callback method.
     * @param bool $forceInjection Force use dependency injection (default: false).
     * @param bool $isCliMiddleware Indicate Whether caller is CLI middleware (default: false).
     * @param bool $isHttpMiddleware Indicate Whether caller is HTTP middleware (default: false).
     *
     * @return int Return status if controller method was executed successfully, error or silent otherwise.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function call(
        Closure|string|array $callback, 
        array $arguments = [], 
        bool $forceInjection = false,
        bool $isCliMiddleware = false,
        bool $isHttpMiddleware = false
    ): int
    {
        if ($callback instanceof Closure) {
            $isCommand = self::$isCommand && isset($arguments['name']);
            self::assertReturnTypes($callback, isCommand: $isCommand);

            $arguments = $isCommand
                ? ($arguments['params'] ?? []) 
                : $arguments;
            
            Boot::add('__CLASS_METADATA__', 'namespace', '\\Closure');
            Boot::add('__CLASS_METADATA__', 'method', 'function');

            return self::send(
                $callback(...self::injection(
                    $callback, 
                    $arguments, 
                    $forceInjection
                )),
                $isHttpMiddleware 
            );
        }

        [$namespace, $method] = self::getClassHandler($callback);

        if(!$namespace || !$method){
            return STATUS_ERROR;
        }

        return self::respond(
            $namespace, 
            $method, 
            $arguments, 
            $forceInjection,
            $isCliMiddleware,
            $isHttpMiddleware
        );
    }

    /**
     * Execute controller using reflection method and send response to client or terminal.
     * 
     * @param class-string<RoutableInterface> $namespace Controller class namespace.
     * @param string $method Controller class routable method name.
     * @param array $arguments Optional arguments to pass to the method.
     * @param bool $forceInjection Force use dependency injection. Default is false.
     * @param bool $isCliMiddleware Indicate Whether caller is cli middleware (default: false).
     * @param bool $isHttpMiddleware Indicate Whether caller is HTTP middleware (default: false).
     *
     * @return int Return status code.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function respond(
        string $namespace, 
        string $method, 
        array $arguments = [], 
        bool $forceInjection = false,
        bool $isCliMiddleware = false,
        bool $isHttpMiddleware = false
    ): int 
    {
        if ($namespace === '') {
            RouterException::rethrow('invalid.class', ErrorCode::CLASS_NOT_FOUND, [
                $namespace, 
                implode(',  ', self::$namespace)
            ]);

            return STATUS_ERROR;
        }

        Boot::add('__CLASS_METADATA__', 'namespace', $namespace);
        Boot::add('__CLASS_METADATA__', 'method', $method);
        Boot::add('__CLASS_METADATA__', 'uri', self::$uri);
        

        try {
            $class = new ReflectionClass($namespace);

            if (!($class->isInstantiable() && $class->implementsInterface(RoutableInterface::class))) {
                RouterException::rethrow('invalid.controller', ErrorCode::INVALID_CONTROLLER, [
                    $namespace
                ]);
                
                return STATUS_ERROR;
            }

            $isCommand = self::$isCommand && isset($arguments['name']);
            $caller = $class->getMethod($method);
            
            self::assertReturnTypes($caller, $namespace, $isCommand);
            
            if ($caller->isPublic() && !$caller->isAbstract() && 
                (
                    !$caller->isStatic() || 
                    ($caller->isStatic() && $class->implementsInterface(ErrorHandlerInterface::class))
                )
            ) {
                if ($isCommand) {
                    $controllerGroup = $class->getProperty('group')->getDefaultValue();
                    
                    if($controllerGroup === self::getArgument(1)) {
                        $arguments['classMethod'] = $method;

                        return self::invokeCommandArgs(
                            $class->newInstance(), 
                            $arguments, 
                            $namespace, 
                            $caller,
                            $isCliMiddleware
                        );
                    }

                    Terminal::error(sprintf(
                        'Command group "%s" does not match the expected controller group "%s".',
                        self::getArgument(1),
                        $controllerGroup
                    ));

                    return STATUS_SUCCESS;
                } 

                $instance = $isHttpMiddleware 
                    ? $class->newInstance()
                    : ($caller->isStatic() ? null: $class->newInstance());

                $result = self::send(
                    $caller->invokeArgs($instance, self::injection($caller, $arguments, $forceInjection)),
                    $isHttpMiddleware 
                );
                
                if($isHttpMiddleware && $result !== STATUS_SUCCESS){
                    $class->getMethod('onMiddlewareFailure')
                        ->invokeArgs($instance, [self::$uri, Boot::get('__CLASS_METADATA__')]);
                }

                return $result;
            }
        } catch (Throwable $e) {
            if(str_contains($e->getMessage(), 'Too few arguments')){
                (new RouterException(
                    sprintf(
                        '%s. Ensure that routing dependency injection is enabled in env "%s"%s. See %s', 
                        $e->getMessage(),
                        '<highlight>feature.route.dependency.injection</highlight>',
                        ', or remove arguments method signature',
                        '<link>https://luminova.ng/docs/0.0.0/routing/dependency-injection</link>'
                    ),
                    $e->getCode(),
                    $e
                ))
                ->setFile($e->getFile())
                ->setLine($e->getLine())
                ->handle();
            }

            if($e instanceof AppException){
                $e->handle();
                return STATUS_ERROR;
            }

            RouterException::throwException($e->getMessage(), $e->getCode(), $e);
            return STATUS_ERROR;
        }

        RouterException::rethrow('invalid.method', ErrorCode::INVALID_METHOD, [$method]);
        return STATUS_ERROR;
    }
 
    /**
     * Sends an HTTP response or outputs a view response.
     *
     * This method handles different response types from the routing system:
     * - If a ViewResponseInterface is given, it directly calls its output method.
     * - If a PSR-7 ResponseInterface is given, it sends headers, outputs the body,
     *   and returns a status code.
     * - If an integer is given, it is treated as an immediate status code return.
     *
     * @param ViewResponseInterface|ResponseInterface|int $response
     *     The response object or status code from the routed action.
     *
     * @return int
     *     STATUS_SUCCESS if output was sent,
     *     STATUS_SILENCE if no content,
     *     or any integer code passed directly.
     */
    private static function send(ViewResponseInterface|ResponseInterface|int $response, bool $isHttpMiddleware): int
    {
        if($response instanceof ViewResponseInterface){
            return $response->output();
        }

        if(!$response instanceof ResponseInterface){
            return (int) $response;
        }

        $status = $response->getStatusCode();
        $contents = '';

        if(self::$method !== 'HEAD'){
            $contents = (string) $response->getBody()->getContents();
            
            if ($contents === '' && $status !== 204 && $status !== 304) {
                $status = 204;
            }
        }

        Header::send($response->getHeaders(), status: $status);
        Header::clearOutputBuffers('all');
        $isFailedMiddleware = ($isHttpMiddleware && ($status === 500 || $status === 401));

        if ($contents === '' || $status === 204 || $status === 304) {
            return $isFailedMiddleware
                ? STATUS_ERROR 
                : STATUS_SILENCE;
        }

        Header::setOutputHandler(true);
        echo $contents;
        return $isFailedMiddleware
            ? STATUS_ERROR 
            : STATUS_SUCCESS;
    }

    /**
     * Ensure a controller method or closure declares a valid return type for routing.
     *
     * Routable methods must return either an integer status code (`STATUS_SUCCESS`, 
     * `STATUS_ERROR`, `STATUS_SILENCE`) or, optionally, a Response object type.
     *
     * @param ReflectionMethod|Closure $method The method or closure to check.
     * @param string|null $namespace Optional controller namespace for error context.
     * @param bool $isCommand If true, only `int` is allowed as a return type (default: false).
     *
     * @return void
     * @throws RouterException If the return type does not match the allowed types.
     */
    private static function assertReturnTypes(
        ReflectionMethod|Closure $method, 
        ?string $namespace = null,
        bool $isCommand = false
    ): void 
    {
        // Development Only
        if(PRODUCTION && !$isCommand){
            return;
        }

        $types = ['void'];
        $name = 'closure';
        $isUnion = false;

        try {
            if(!$method instanceof ReflectionMethod){
                $method = new ReflectionFunction($method);
            }

            $result = $method->getReturnType();
            $name =  $method->getName() ?: 'callable';
            $types = ['mixed'];

            if($result instanceof ReflectionUnionType){
                $isUnion = true;
                $types = array_map(fn($t) => $t->getName(), $result->getTypes());
            } elseif ($result instanceof ReflectionNamedType) {
                $types = [$result->getName()];
                if ($result->allowsNull()) {
                    $isUnion = true;
                    $types[] = 'null';
                }
            }
        } catch (Throwable) {}

        $unmap = [
            'string', 'int', 'float', 'double', 'mixed', 'callable',
            'bool', 'array', 'object', 'void', 'never', 'null'
        ];
        $expected = 'int (STATUS_SUCCESS, STATUS_ERROR, STATUS_SILENCE)';
        $allowed = $isCommand ? ['int'] : [
            'int', 
            'mixed',
            Response::class, 
            ViewResponseInterface::class,
            ResponseInterface::class, 
            HttpResponseInterface::class,
        ];

        foreach ($types as $t) {
            if(in_array($t, $allowed, true)){
                if($isUnion){
                    continue;
                }

                if(!$isCommand){
                    return;
                }

                if($t === 'int' && !in_array('mixed', $allowed, true)){
                    return;
                }
            }

            if(!$isCommand){
                foreach ($allowed as $cls) {
                    if (!in_array($cls, $unmap, true) && is_a($t, $cls, true)) {
                        continue 2; 
                    }
                }
            }

            throw new RouterException(
                sprintf(
                    'Routable handler "%s" returned unsupported type "%s"%s%s%s',
                    $namespace ? "{$namespace}::{$name}" : $name,
                    implode('|', $types),
                    $isUnion ? ', union types must satisfy expected types: ' : '. Expected: ',
                    $expected,
                    $isCommand ? '' : sprintf(
                        ', %s, or a class implementing: %s, %s, or %s',
                        Response::class,
                        ViewResponseInterface::class,
                        ResponseInterface::class, 
                        HttpResponseInterface::class
                    )
                ),
                ErrorCode::INVALID_METHOD
            );
        }
    }

    /**
     * Register HTTP methods to handle request.
     * 
     * This registers routes when using attributes based routing instead of method-based.
     * 
     * @param string $prefix The application url first prefix.
     * 
     * @return self Return router instance.
     */
    private function withAttributes(string $prefix): self 
    {
        $path = $this->isHmvcModule ? 'app/Modules/' : 'app/Controllers/';
        $attr = new Compiler(
            self::$base, 
            self::$isCommand, 
            $this->isHmvcModule
        );

        if(self::$isCommand){
            $attr->forCli($path, self::getArgument(1));
        }else{
            $attr->forHttp($path, $prefix, self::$uri);
        }

        $current = self::$base;
        self::$routes = array_merge(self::$routes, $attr->getRoutes());
        
        self::$base = $current;
        return $this;
    }

    /**
     * Register HTTP methods to handle request.
     * 
     * This registers routes when using method-based routing instead of attribute.
     * 
     * @param string $prefix The application url first prefix.
     * @param Prefix[]|array<int,array<string,mixed>> $contexts The application prefix contexts.
     * 
     * @return self Return router instance.
     */
    private function withMethods(string $prefix, array $contexts): self  
    {
        $current = self::$base;
        $isArrayConfig = !($contexts[0] instanceof Prefix);
        $prefixes = $isArrayConfig ? $this->getArrayPrefixes($contexts) : Prefix::getPrefixes();

        foreach ($contexts as $context) {
            $name = $isArrayConfig ? ($context['prefix'] ?? '') : $context->getPrefix();

            if($name === ''){
                continue;
            }

            self::reset();

            $result = self::setErrorHandler(
                $name, 
                $isArrayConfig 
                    ? ($context['error'] ?? null) 
                    : $context->getErrorHandler(), 
                $prefix, 
                $prefixes
            );

            if($result === 0){
                return $this;
            }

            if($result === true){
                $this->onContext($name);
                self::$app->__on('onContextInstalled', $name);
                break;
            }
        }

        self::$base = $current;
        return $this;
    }

    /**
     * Invoke class using reflection method.
     *
     * @param Command $instance Command controller object.
     * @param array $arguments Pass arguments to reflection method.
     * @param string $className Invoking class name.
     * @param ReflectionMethod $caller Controller class method.
     * @param bool $isMiddleware Indicate Whether caller is cli middleware (default: false).
     *
     * @return int Return result from command controller method.
     */
    private static function invokeCommandArgs(
        Command $instance,
        array $arguments, 
        string $className, 
        ReflectionMethod $caller,
        bool $isMiddleware = false
    ): int
    {
        $id = '_about_' . $instance->name;
        $arguments[$id] = [
            'class' => $className, 
            'group' => $instance->group,
            'name' => $instance->name,
            'description' => $instance->description,
            'usages' => $instance->usages,
            'options' => $instance->options,
            'examples' => $instance->examples,
            'users' => $instance->users,
            'authentication' => $instance->authentication,
        ];

        // Make the command available through get options.
        $isHelp = $instance->parse($arguments)->isHelp();

        // Check command string to determine if it has help arguments.
        if(!$isMiddleware && $isHelp){
            Terminal::header();

            if($instance->help($arguments[$id]) === STATUS_ERROR){
                // Fallback to default help information if dev does not implement help.
                Terminal::helper($arguments[$id]);
            }

            return STATUS_SUCCESS;
        }

        if($instance->users !== []){
            $user = Terminal::whoami();

            if(!in_array($user, $instance->users, true)){
                Terminal::error("User '{$user}' is not allowed to run this command.");
                return STATUS_ERROR;
            }
        }

        return (int) $caller->invokeArgs(
            $instance, 
            self::injection($caller, $arguments['params']??[])
        );
    }

    /**
     * Resolve URI placeholder patterns.
     * 
     * @return array<string,string> $placeholders Return URI patterns.
     */
    private static function getPlaceholders(): array 
    {
        return array_merge([
            //'(:root)'       => '?(?:/(?:[^/].*)?)?',
            '(:base)'         => '?(?:/.*)?',
            '(:root)'         => '?(?:/[^/].*)?',
            '(:any)'          => '(.*)',
            '(:int)'          => '(\d+)',
            '(:integer)'      => '(\d+)',         
            '(:mixed)'        => '([^/]*?)',
            '(:string)'       => '([^/]+?)',
            '(:optional)'     => '?(?:/([^/]*))?',
            '(:alphabet)'     => '([a-zA-Z]+)',
            '(:alphanumeric)' => '([a-zA-Z0-9]+)',
            '(:username)'     => '([a-zA-Z0-9._-]+)',
            '(:number)'       => '([+-]?\d+(?:\.\d+)?)',
            '(:version)'      => '(\d+(?:\.\d+)+)',
            '(:double)'       => '([+-]?\d+(\.\d+)?)',
            '(:float)'        => '([+-]?\d+\.\d+)',
            '(:path)'         => '((.+)/([^/]+)+)',
            '(:uuid)'         => '([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})',

            // Optional datatype
            '(:?int)'         => '?(?:/(\d+))?',
            '(:?integer)'     => '?(?:/(\d+))?',
            '(:?string)'      => '?(?:/([^/]+))?',
            '(:?number)'      => '?(?:/([+-]?\d+(?:\.\d+)?))?',
            '(:?double)'      => '?(?:/([+-]?\d+(?:\.\d+)?))?',
            '(:?float)'       => '?(?:/([+-]?\d+\.\d+))?',
        ], self::$placeholders);
    }

    /**
     * Create a new instance of the given class or return a default object based on the class type.
     *
     * @param class-string<\T> $class The class name to inject.
     * @param bool $nullable If true, returns null when the class does not exist (default: false).
     * 
     * @return object<\T>|null The new instance of the class, or null if the class is not found.
     * @throws Exception|AppException Throws if the class does not exist or requires arguments to initialize.
     */
    private static function newInstance(
        string $class, 
        bool $nullable = false,
        mixed $default = '__no_default__'
    ): ?object 
    {
        $instance = match ($class) {
            \App\Application::class, Application::class => self::$app,
            RouterInterface::class, Router::class => self::$app->router,
            Segments::class => new Segments(self::$isCommand ? [self::CLI_URI] : Luminova::getSegments()),
            Closure::class => fn(mixed ...$arguments): mixed => null,
            default => DI::isBound($class) ? DI::resolve($class) : null
        };

        if($instance === null){
            $e = null;
            $type = null;

            if(DI::isInstantiable($class, $type, $e)){
                return ($type === 'instantiate') 
                    ? new $class() 
                    : $class::getInstance();
            }

            if($nullable !== '__no_default__'){
                $e = null;
                return $default;
            }

            if($e instanceof Throwable){
                throw $e;
            }

            throw new RouterException(
                sprintf('Class "%s" does not exist or cannot be autoloaded.', $class),
                ErrorCode::CLASS_NOT_FOUND
            );
        }

        return $instance;
    }

    /**
     * Get union types as array or string.
     *
     * @param ReflectionNamedType[]|ReflectionIntersectionType[] $unions The union types.
     * 
     * @return array<mixed> Return the union types.
     */
    private static function getUnionTypes(array $unions, array $supported): array
    {
        foreach ($unions as $type) {
            if (self::$isDIEnabled && !$type->isBuiltin()) {
                return [
                    $type->getName(),
                    $type->allowsNull(),
                    false
                ];
            }

            if(in_array($type->getName(), $supported, true)){
                return [
                    $type->getName(),
                    $type->allowsNull(),
                    true
                ];
            }
        }

        return ['mixed', false, true];
    }

    /**
     * Cast a value based on typeof value method hint type.
     *
     * @param string $type The type to cast to.
     * @param mixed $value The value to cast.
     * 
     * @return mixed Return the casted value.
     */
    private static function typeCasting(
        string $type, 
        mixed $value,
        bool $nullable = false, 
        mixed $default = '__no_default__',
        bool $isUnion = false
    ): mixed 
    {
        $isNoDefault = $default === '__no_default__';
        $value = trim((string) $value);

        if ($nullable && ($value === null ||  $value === '')) {
            return $isNoDefault ? null : $default;
        }

        if (!$isNoDefault && $value !== 0 && $value !== '0' && empty($value)) {
            return $default;
        }

        if($type === 'mixed'){
            return  $value;
        }

        if($isUnion){
            return match(true){
                is_int($value)    => (int) $value,
                is_float($value)  => (float) $value,
                is_double($value) => (double) $value,
                default => self::getHintValue(
                    $type, 
                    $value, 
                    ($isNoDefault || $value !== null) ? (string) $value  : $default
                ) 
            };
        }

        return self::getHintValue(
            $type, 
            $value, 
           ($isNoDefault || $value !== null) ? $value  : $default
        );
    }

    /**
     * Cast a value to a based on specific type.
     *
     * @param string $type The type to cast to.
     * @param mixed $value The value to cast.
     * @param mixed $default The default value to cast.
     * 
     * @return mixed Return the casted value.
     */
    private static function getHintValue(
        string $type, 
        mixed $value, 
        mixed $default
    ): mixed 
    {
        return match ($type) {
            'bool'      => ((float) $value > 0 || strtolower($value) === 'true'),
            'int'       => (int) $value,
            'float'     => (float) $value,
            'double'    => (double) $value,
            'string'    => (string) $value,
            'null'      => null,
            'false'     => false,
            'true'      => true,
            default     => $default
        };
    }

    /**
     * Get the current command controller views.
     * 
     * @return array<string,mixed> $views Return array of command routes parameters as URI.
     */
    private static function getCommandSegment(): array 
    {
        $views = [
            'view' => '',
            'options' => [],
        ];

        if (!isset($_SERVER['argv'][2])) {
            return $views;
        }

        $result = Terminal::extract(array_slice($_SERVER['argv'], 2), true);

        $views['view'] = '/' . implode('/', $result['arguments']);
        $views['options'] = $result['options'];

        return $views;
    }

    /**
     * Get a CLI argument by index, defaulting to the last argument.
     *
     * Supports negative indexes:
     *   -1 => last argument
     *   -2 => second last, etc.
     *
     * @param int|nul $index Index of the argument to retrieve (0-based). 
     *                   Negative indexes count from the end.
     * 
     * @return array|string Returns the argument, or empty string if not found.
     */
    private static function getArgument(?int $index = 1): array|string
    {
        $argv = $_SERVER['argv'] ?? [];

        if($index === null){
            return $argv;
        }

        if ($argv === []) {
            return '';
        }

        if ($index < 0) {
            $index = count($argv) + $index;
        }

        return $argv[$index] ?? '';
    }

    /**
     * Determines if a specific CLI flag is present.
     *
     * Supports both short (-f) and long (--flag) forms.
     *
     * @param string $flag The flag to search for (with or without leading dashes).
     *
     * @return bool True if the flag exists, false otherwise.
     */
    private static function hasCommand(string $flag): bool
    {
        $options = self::$commands['options'] ?? [];
        $normalized = ltrim($flag, '-');

        if ($options) {
            return array_key_exists($normalized, $options);
        }

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (ltrim($arg, '-') === $normalized) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Reset register routes to avoid conflicts.
     * 
     * @return void
     */
    private static function reset(bool $init = false): void
    {
        self::$routes = [
            'http.routes'       =>  [], 
            'http.errors'       =>  [],
            'http.after'        =>  [], 
            'http.middleware'   =>  [], 
            'cli.commands'      =>  [], 
            'cli.middleware'    =>  [],
            'cli.groups'        =>  []
        ];

        if(!$init){
            return;
        }

        Boot::set('__CLASS_METADATA__', [
            'filename'    => null,
            'uri'         => null,
            'namespace'   => null,
            'method'      => null,
            'controllers' => 0,
            'cache'       => false,
            'staticCache' => false,
        ]);
    }
}