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
use \App\Application;
use \ReflectionClass;
use \ReflectionMethod;
use \Luminova\Luminova;
use \ReflectionFunction;
use \App\Config\Template;
use \ReflectionException;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \Luminova\Http\Header;
use \Luminova\Http\HttpCode;
use \Luminova\Base\Command;
use \Luminova\Command\Terminal;
use \Luminova\Template\Response;
use \ReflectionIntersectionType;
use \Luminova\Cache\TemplateCache;
use \Luminova\Command\Utils\Color;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Attributes\Internal\Compiler;
use \App\Errors\Controllers\ErrorController;
use \Luminova\Routing\{DI, Prefix, Segments};
use function \Luminova\Funcs\{root, import, filter_paths};
use \Luminova\Foundation\Core\Application as CoreApplication;
use \Luminova\Exceptions\{ErrorCode, AppException, RouterException};
use \Luminova\Interface\{RoutableInterface, RouterInterface, ErrorHandlerInterface, ViewResponseInterface};

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
     * @var string $baseGroup
     */
    private string $baseGroup = '';

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
     * @var bool $di 
     */
    private static bool $di = false;

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
     * Application instance.
     * 
     * @var Application<CoreApplication>|null $app 
     */
    private static ?CoreApplication $app = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(CoreApplication $app)
    {
        self::$isCommand = false;
        self::$app = $app;
        self::$di = env('feature.route.dependency.injection', false);

        if(Luminova::isCommand()){
            self::$isCommand = true;
            Terminal::init();
        }

        self::reset(true);
        Luminova::profiling('start');
        $app = null;
    }

    /**
     * {@inheritdoc}
     */
    public function context(Prefix|array ...$contexts): self 
    {
        self::onInitialized();
        self::$uri = self::getUriSegments();

        // If application is undergoing maintenance.
        if(MAINTENANCE && self::systemMaintenance()){
            return $this;
        }

        // If the view uri ends with `.extension`, then try serving the cached static version.
        if(!self::$isCommand && self::$uri !== self::CLI_URI && $this->serveStaticCache()){
            return $this;
        }

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
    public function get(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', 'GET', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', 'POST', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', 'PATCH', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', 'DELETE', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', 'PUT', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', 'OPTIONS', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $pattern, Closure|string $callback): void
    {
        $this->http('http.routes', self::ANY_METHOD, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function middleware(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
                '$methods'
            ]);
            return;
        }

        $this->http('http.middleware', $methods, $pattern, $callback, true);
    }

    /**
     * {@inheritdoc}
     */
    public function after(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
                '$methods'
            ]);
            return;
        }

        $this->http('http.after', $methods, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function guard(string $group, Closure|string $callback): void
    {
        if(!self::$isCommand){
            RouterException::rethrow('invalid.middleware.cli');
        }

        $group = trim($group, '/');

        if (!$group || !preg_match('/^[a-z][a-z0-9_:-]*$/', $group)) {
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
    public function capture(string $methods, string $pattern, Closure|string $callback): void
    {
        if (!$methods) {
            RouterException::rethrow('argument.empty', ErrorCode::INVALID_ARGUMENTS, [
               '$methods'
            ]);
            return;
        }

        $this->http('http.routes', $methods, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function command(string $command, Closure|string $callback): void
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
    public function bind(string $prefix, Closure $callback): void
    {
        $current = $this->baseGroup;
        $this->baseGroup .= rtrim($prefix, '/');

        $callback(...self::noneParamInjection($callback));
        $this->baseGroup = $current;
    }

    /**
     * {@inheritdoc}
     */
    public function group(string $group, Closure $callback): void
    {
        self::$routes['cli.groups'][$group][] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace): self
    {
        self::$app::$isHmvcModule ??= env('feature.app.hmvc', false);
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
        if(self::$terminate){
            Luminova::profiling('stop');
            exit(STATUS_SUCCESS);
        }

        $context = null;
        $exitCode = STATUS_ERROR;

        if(self::$method === 'CLI' && !self::$isCommand){
            RouterException::rethrow('invalid.request.method', ErrorCode::INVALID_REQUEST_METHOD, [
                self::$method,
                'CLI'
            ]);
        }

        if(self::$method !== 'CLI' && self::$isCommand){
            RouterException::rethrow('invalid.request.method', ErrorCode::INVALID_REQUEST_METHOD, [
                self::$method,
                'HTTP'
            ]);
        }

        try{
            if(self::$method === 'CLI'){
                $exitCode = self::runAsCommand();
                $context = ['commands' => self::$commands];
            }else{
                $exitCode = self::runAsHttp();
            }

            self::$app->__on('onFinish', Luminova::getClassMetadata());
            Luminova::profiling('stop', $context);
        }catch(Throwable $e){
            if(PRODUCTION){
                RouterException::throwException($e->getMessage(), $e->getCode(), $e);
                return;
            }

            throw $e;
        }

        exit($exitCode);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(Closure|array|string $pattern, Closure|array|string|null $handler = null): void
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
     * This method is maintained for backward compatibility and will be removed in a future release.
     * 
     * @deprecated Use onError() instead.
     */
    public function setErrorListener(Closure|array|string $match, Closure|array|string|null $callback = null): void
    {
        \Luminova\Foundation\Error\Guard::deprecate(
            'router->setErrorListener() is deprecated. Use router->onError() instead.',
            '3.6.8'
        );
        
        $this->onError($match, $callback);
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

        if (
            $name === '' || 
            $name === 'root' || 
            $name === '(:root)' ||
            $name === 'base' || 
            $name === '(:base)'
        ) {
            throw new RouterException(
                ($name === '') 
                    ? 'Placeholder name cannot be empty.'
                    : sprintf('The placeholder name "%s" is reserved and cannot be override.', $name),
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        $name = str_starts_with($name, '(:') ? $name : "(:$name)";

        if($group !== null && !str_starts_with($pattern, '(')){
            $pattern = ($group === 0) 
                ? '(?:' . $pattern . ')' 
                : (($group === 1) ? '(' . $pattern . ')' : $pattern);
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
            : Luminova::getUriSegments();
    }

    /**
     * {@inheritdoc}
     */
    public function getSegment(): Segments 
    {
        return new Segments(self::$isCommand ? [self::CLI_URI] : Luminova::getSegments());
    }

    /**
     * Load required route context only.
     * 
     * Load the route URI context prefix and make router/application available
     * as global variables inside the context file.
     *
     * @param string $context Route URI context prefix name.
     * 
     * @global RouterInterface<\T> $router Router instance in context file.
     * @global CoreApplication $app Application instance in context file.
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

        $route = static function (string $context, string $path, RouterInterface $router, CoreApplication $app) {
            require_once $path;
        };

        $unbound = $route->bindTo(null, null);
        $unbound($context, $path, $this, self::$app);
        
        self::$app->__on('onContextInstalled', $context);
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
    private static function onTriggerError(int $status = 404, bool $global = false): void
    {
        Header::clearOutputBuffers();

        if($global && method_exists(ErrorController::class, 'onTrigger')){
            ErrorController::onTrigger(self::$app, $status, Luminova::getSegments());
            exit;
        }

        if(self::handleErrors()){
            exit;
        }

        if(!$global && method_exists(ErrorController::class, 'onTrigger')){
            Header::clearOutputBuffers();
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
     * @return bool Return true if valid, otherwise false or throw execption.
     * @throws RouterException If on development
     */
    private function isNamespace(string $namespace): bool
    {
        self::$app::$isHmvcModule ??= env('feature.app.hmvc', false);
        $design = 'MVC';

        if(self::$app::$isHmvcModule){
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
        Header::clearOutputBuffers();

        if($method === 'HEAD'){
            ob_start();
            self::$method = $method;
            return;
        }

        Header::setOutputHandler(true);

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
        Header::clearOutputBuffers();
        $header ??= HttpCode::phrase($status);
        $message ??= $header;

        if (self::$isCommand) {
            Terminal::error(sprintf('(%d) [%s] %s', $status, $header, $message));
            exit(STATUS_ERROR);
        }

        if (Luminova::isApiPrefix()) {
            Header::headerNoCache($status, 'application/json; charset=utf-8');
            echo json_encode([
                'status'  => $status,
                'error'   => $header,
                'message' => $message
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit(STATUS_ERROR);
        }

        Header::headerNoCache($status);
        printf(
            '<html><title>%d %s</title><body><h1>%s</h1><p>%s</p></body></html>',
            $status,
            $header,
            $header,
            nl2br($message)
        );
        
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
    private function http(
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

        $pattern = $this->baseGroup . '/' . trim($pattern, '/');
        $pattern = self::toPatterns(($this->baseGroup !== '') ? rtrim($pattern, '/') : $pattern);

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

        $segments = Luminova::getSegments();
        return reset($segments) ?: '';
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
    private function setErrorHandler(string $name, Closure|array|null $onError, string $prefix, array $prefixes): bool|int
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
                $this->onError($onError);
            }
        
            if (isset($prefixes[$name])) {  
                $this->baseGroup .= '/' . $name;
            }

            return true;
        }
        
        if(!isset($prefixes[$prefix]) && self::isWeContext($name, $prefix)) {
            if($onError !== null){
                $this->onError($onError);
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
    private static function runAsCommand(): int
    {
        $group = self::getArgument();
        $command = self::getArgument(2);

        if(!$group || !$command){
            Terminal::header();
            return STATUS_SUCCESS;
        }

        if(Terminal::isHelp($group)){
            Terminal::header();
            Terminal::helper(null, true);
            return STATUS_SUCCESS;
        }

        $global = (self::$routes['cli.middleware'][self::$method]['global']??null);

        if($global !== null && !self::handleCommand($global)){
            return STATUS_ERROR;
        }
        
        $groups = (self::$routes['cli.groups'][$group] ?? null);
        
        if($groups !== null){
            foreach($groups as $group){
                $callback = isset($group['callback']) 
                    ? static fn(Router $router) => $router->command(
                        $group['pattern'], 
                        $group['callback']
                     )
                    : $group;

                $callback(...self::noneParamInjection($callback));
            }

            $middleware = (self::$routes['cli.middleware'][self::$method][$group] ?? null);

            if($middleware !== null && !self::handleCommand($middleware)){
                return STATUS_ERROR;
            }
            
            $routes = self::$routes['cli.commands'][self::$method] ?? null;

            if ($routes !== null && self::handleCommand($routes)) {
                self::$app->__on('onCommandPresent', self::getCommandArguments());
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
    private static function runAsHttp(): int
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
            $after = self::getRoutes('http.after');
            
            if($after !== []){
                self::handleWebsite($after, self::$uri);
            }

            self::$app->__on('onViewPresent', self::$uri);

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
     * Load application is undergoing maintenance.
     * 
     * @return bool Return true.
     */
    private static function systemMaintenance(): bool 
    {
        self::$terminate = true;
        $err = 'Error: (503) System undergoing maintenance!';

        if(self::$isCommand || self::$uri === self::CLI_URI){
            Terminal::error($err);
            return true;
        }

        Header::headerNoCache(503, null, env('app.maintenance.retry', '3600'));
        
        try{
            import(path: self::$app->view->getSystemError('maintenance'), once: true, throw: true);
        }catch(Throwable){
            echo $err;
        }

        return true;
    }

    /**
     * Serve static cached pages.
     * 
     * If cache is enabled and the request is not in CLI mode, check if the cache is still valid.
     * If valid, render the cache and terminate further router execution.
     *
     * @return bool Return true if cache is rendered, otherwise false.
     */
    private function serveStaticCache(): bool
    {
        if (self::$method === 'CLI' || !env('page.caching', false)) {
            return false;
        }

        // Supported extension types to match.
        $types = env('page.caching.statics', false);

        if ($types && $types !== '' && preg_match('/\.(' . $types . ')$/i', self::$uri, $matches)) {
            $cache = new TemplateCache(0, root(rtrim((new Template())->cacheFolder, TRIM_DS) . '/default/'));

            // If expiration return mismatched int code 404 ignore and do not try to replace to actual url.
            $expired = $cache->setKey(Luminova::getCacheId())
                ->setUri(self::$uri)
                ->expired($matches[1]);

            if ($expired === true) {
                // Remove the matched file extension to render the request normally
                self::$uri = substr(self::$uri, 0, -strlen($matches[0]));
            }elseif($expired === false && $cache->read() === true){
                Luminova::setClassMetadata([
                    'staticCache' => true, 
                    'cache' => true
                ]);
                
                // Render performance profiling content.
                Luminova::profiling('stop');

                // Terminate router run method to ensure other unwanted modules are loaded.
                self::$terminate = true;
                return true;
            }
        }
        
        return false;
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
                ? self::call($route['callback'], self::urisToArgs($matches), false, false, $route['middleware'])
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
        $queries = self::getCommandArguments();
        $isHelp = Terminal::isHelp(self::getArgument(2));
        
        foreach ($routes as $route) {
            if($route['middleware']){
                return self::call($route['callback'], self::$commands, false, true) === STATUS_SUCCESS;
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
     * @param bool $injection Force use of dependency injection.
     *
     * @return array<int,mixed> Return method params and arguments-value pairs.
     * @internal 
     */
    private static function injection(
        ReflectionMethod|callable $caller, 
        array $arguments = [], 
        bool $injection = false
    ): array
    {
        if (!$injection && !self::$di) {
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

                return [];
            }
            
            foreach ($caller->getParameters() as $parameter) {
                $type = $parameter->getType();
                if($type instanceof ReflectionUnionType) {
                    $types = self::getUnionTypes($type->getTypes());
                    $builtin = $types['builtin'] ?? null;
                    $nullable = $types['nullable'] ?? false;

                    if($builtin !== null){
                        if($arguments !== []) {
                            $parameters[] = self::typeCasting(
                                $builtin, 
                                $nullable,
                                array_shift($arguments)
                            );
                        }
                    }else{
                        $parameters[] = self::newInstance($types['inject'], $nullable);
                    }
                }elseif($type instanceof ReflectionNamedType) {
                    if($type->isBuiltin()) {
                        if($arguments !== []) {
                            $parameters[] = self::typeCasting(
                                $type->getName(), 
                                $type->allowsNull(),
                                array_shift($arguments)
                            );
                        }
                    }else{
                        $parameters[] = self::newInstance($type->getName(), $type->allowsNull());
                    }
                }
            }

            return array_merge($parameters, $arguments);
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
        $classNames = [];

        foreach ((new ReflectionFunction($callback))->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $classNames[] = self::newInstance($type->getName(), $type->allowsNull());
            }
        }
        
        return $classNames;
    }

    /**
     * Execute router HTTP callback class method with the given parameters using instance callback or reflection class.
     *
     * @param Closure|array{0:class-string<RoutableInterface>,1:string}|string $callback Class public callback method (e.g, UserController:update).
     * @param array $arguments Method arguments to pass to callback method.
     * @param bool $injection Force use dependency injection (default: false).
     * @param bool $isCliMiddleware Indicate Whether caller is CLI middleware (default: false).
     * @param bool $isHttpMiddleware Indicate Whether caller is HTTP middleware (default: false).
     *
     * @return int Return status if controller method was executed successfully, error or silent otherwise.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function call(
        Closure|string|array $callback, 
        array $arguments = [], 
        bool $injection = false,
        bool $isCliMiddleware = false,
        bool $isHttpMiddleware = false
    ): int
    {
        if ($callback instanceof Closure) {
            self::assertReturnInt($callback);
            $arguments = (self::$isCommand && isset($arguments['command']))
                ? ($arguments['params'] ?? []) 
                : $arguments;
            
            Luminova::setClassMetadata([
                'namespace' => '\\Closure', 
                'method' => 'function'
            ]);

            return $callback(...self::injection(
                $callback, 
                $arguments, 
                $injection
            ));
        }

        [$namespace, $method] = self::getClassHandler($callback);

        if(!$namespace || !$method){
            return STATUS_ERROR;
        }

        return self::respond(
            $namespace, 
            $method, 
            $arguments, 
            $injection,
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
     * @param bool $injection Force use dependency injection. Default is false.
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
        bool $injection = false,
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

        Luminova::setClassMetadata([
            'namespace' => $namespace, 
            'method' => $method,
            'uri' => self::$uri
        ]);

        try {
            $class = new ReflectionClass($namespace);

            if (!($class->isInstantiable() && $class->implementsInterface(RoutableInterface::class))) {
                RouterException::rethrow('invalid.controller', ErrorCode::INVALID_CONTROLLER, [
                    $namespace
                ]);
                
                return STATUS_ERROR;
            }

            $isCommand = self::$isCommand && isset($arguments['command']);
            $caller = $class->getMethod($method);
            
            self::assertReturnInt($caller, $namespace, $isCommand);
            
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
                    $caller->invokeArgs($instance, self::injection($caller, $arguments, $injection)),
                    $isHttpMiddleware 
                );
                
                if($isHttpMiddleware && $result !== STATUS_SUCCESS){
                    $failed = $class->getMethod('onMiddlewareFailure');
                    $failed->setAccessible(true);
                    $failed->invokeArgs($instance, [self::$uri, Luminova::getClassMetadata()]);
                }

                return $result;
            }
        } catch (Throwable $e) {
            if(str_contains($e->getMessage(), 'Too few arguments')){
                (new RouterException(
                    sprintf(
                        '%s. Ensure that routing dependency injection is enabled in env "%s", See %s', 
                        $e->getMessage(),
                        '<highlight>feature.route.dependency.injection</highlight>',
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
            return $response;
        }

        $status = $response->getStatusCode();
        $contents = '';

        if(self::$method !== 'HEAD'){
            $contents = (string) $response->getBody()->getContents();
            
            if ($contents === '' && $status !== 204 && $status !== 304) {
                $status = 204;
            }
        }

        Header::validate($response->getHeaders(), $status);
        Header::clearOutputBuffers();
        $isFailedMiddleware = ($isHttpMiddleware && ($status === 500 || $status === 401));

        if ($contents === '' || $status === 204 || $status === 304) {
            return $isFailedMiddleware
                ? STATUS_ERROR 
                : STATUS_SILENCE;
        }

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
     * @param bool $intOnly If true, only `int` is allowed as a return type (default: false).
     *
     * @return void
     * @throws RouterException If the return type does not match the allowed types.
     */
    private static function assertReturnInt(
        ReflectionMethod|Closure $method, 
        ?string $namespace = null,
        bool $intOnly = false
    ): void 
    {
        $type = 'void';

        try {
            $result = $method instanceof ReflectionMethod
                ? $method->getReturnType()
                : (new ReflectionFunction($method))->getReturnType();

            $type = $result ? $result->getName() : 'void';
        } catch (Throwable) {}

        $isAllowed = $intOnly
            ? ($type === 'int')
            : in_array($type, ['int', Response::class, ViewResponseInterface::class], true);

        if ($isAllowed) {
            return;
        }

        $name = $method instanceof ReflectionMethod ? $method->getName() : 'closure';
        $identifier = $namespace ? sprintf('%s::%s', $namespace, $name) : $name;
        $expected = 'int (STATUS_SUCCESS, STATUS_ERROR, STATUS_SILENCE)';

        if(!$intOnly){
            $expected .= ', ' . Response::class . ' or class that implements ' . ViewResponseInterface::class;
        }

        throw new RouterException(sprintf(
            'Invalid return type for routable "%s": expected %s, got %s.',
            $identifier,
            $expected,
            $type
        ), ErrorCode::INVALID_METHOD);
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
        self::$app::$isHmvcModule ??= env('feature.app.hmvc', false);

        $path = self::$app::$isHmvcModule ? 'app/Modules/' : 'app/Controllers/';
        $attr = new Compiler(
            $this->baseGroup, 
            self::$isCommand, 
            self::$app::$isHmvcModule
        );

        if(self::$isCommand){
            $attr->forCli($path, self::getArgument(1));
        }else{
            $attr->forHttp($path, $prefix, self::$uri);
        }

        $current = $this->baseGroup;
        self::$routes = array_merge(self::$routes, $attr->getRoutes());
        
        $this->baseGroup = $current;
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
        $current = $this->baseGroup;
        $isArrayConfig = !($contexts[0] instanceof Prefix);
        $prefixes = $isArrayConfig ? $this->getArrayPrefixes($contexts) : Prefix::getPrefixes();

        foreach ($contexts as $context) {
            $name = $isArrayConfig ? ($context['prefix'] ?? '') : $context->getPrefix();

            if($name === ''){
                continue;
            }

            self::reset();

            $result = $this->setErrorHandler(
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
                break;
            }
        }

        $this->baseGroup = $current;
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

        // Check command string to determine if it has help arguments.
        if(!$isMiddleware && Terminal::isHelp($arguments['command'])){
            
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

        // Make the command available through get options.
        $instance->perse($arguments);

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
        return [
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
            ...self::$placeholders
        ];
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
    private static function newInstance(string $class, bool $nullable = false): ?object 
    {
        $instance = match ($class) {
            Application::class, CoreApplication::class => self::$app,
            RouterInterface::class, Router::class => self::$app->router,
            Segments::class => new Segments(self::$isCommand ? [self::CLI_URI] : Luminova::getSegments()),
            Closure::class => fn(mixed ...$arguments): mixed => null,
            default => DI::isBound($class) 
                ? DI::resolve($class) 
                : (class_exists($class) ? new $class() : null)
        };

        if($instance === null && !$nullable){
            throw new RouterException(sprintf('Class: %s does not exist.', $class), ErrorCode::CLASS_NOT_FOUND);
        }

        return $instance;
    }

    /**
     * Get union types as array or string.
     *
     * @param ReflectionNamedType[]|ReflectionIntersectionType[] $unions The union types.
     * 
     * @return array<string,mixed> Return the union types.
     */
    private static function getUnionTypes(array $unions): array
    {
        $types = ['string', 'int', 'float', 'double', 'bool', 'array', 'object', 'mixed'];

        foreach ($unions as $type) {
            if (!$type->isBuiltin()) {
                return [
                    'inject' => $type->getName(),
                    'nullable' => $type->allowsNull()
                ];
            }

            if(in_array($type->getName(), $types)){
                return [
                    'builtin' => $type->getName(),
                    'nullable' => $type->allowsNull()
                ];
            }
        }

        return ['builtin' => 'string', 'nullable' => false];
    }

    /**
     * Cast a value to a specific type.
     *
     * @param string $type The type to cast to.
     * @param mixed $value The value to cast.
     * 
     * @return mixed Return the casted value.
     */
    private static function typeCasting(string $type, bool $nullable, mixed $value): mixed 
    {
        $lower = is_string($value) ? strtolower($value) : $value;

        if($nullable && ($lower === '' || $lower === 'null')){
            return null;
        }

        return match ($type) {
            'bool'      => ($lower === '1' || 'true' === $lower) ? true : (bool) $value,
            'int'       => (int) $value,
            'float'     => (float) $value,
            'double'    => (double) $value,
            'string'    => (string) $value,
            'array'     => (array) [$value],
            'object'    => (object) [$value],
            'callable'  => fn(mixed ...$arguments): mixed => $value,
            'null'      => null,
            'false'     => false,
            'true'      => true,
            default     => $value
        };
    }

    /**
     * Get the current command controller views.
     * 
     * @return array<string,mixed> $views Return array of command routes parameters as URI.
     */
    private static function getCommandArguments(): array 
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
     * Gets request command name.
     *
     * @return string Return command argument index.
     */
    private static function getArgument(int $index = 1): string 
    {
        if(isset($_SERVER['argv'])){
            return $_SERVER['argv'][$index] ?? '';
        }

        return '';
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

        Luminova::setClassMetadata([
            'filename'    => null,
            'uri'         => null,
            'namespace'   => null,
            'method'      => null,
            'cache'       => false,
            'staticCache' => false,
        ]);
    }
}