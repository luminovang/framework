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
use \stdClass;
use \Throwable;
use \App\Kernel;
use \ReflectionType;
use Luminova\Runtime;
use \ReflectionClass;
use \ReflectionMethod;
use Luminova\Luminova;
use Luminova\Config\Env;
use \ReflectionFunction;
use \ReflectionUnionType;
use \ReflectionNamedType;
use Luminova\Http\Header;
use Luminova\Base\Command;
use Luminova\Logger\Logger;
use Luminova\Template\View;
use Luminova\Command\Terminal;
use Luminova\Template\Response;
use \ReflectionIntersectionType;
use Luminova\Command\Utils\Color;
use Luminova\Debugger\Performance;
use \App\Errors\Controllers\AppError;
use Luminova\Command\Consoles\Commands;
use \Psr\Http\Message\ResponseInterface;
use Luminova\Foundation\Core\Application;
use Luminova\Attributes\Internal\Compiler;
use Luminova\Routing\{DI, Prefix, Segments};
use Luminova\Exceptions\{ErrorCode, LuminovaException, RouterException, ClassException};
use Luminova\Interface\{
    RoutableInterface, 
    RouterInterface, 
    ErrorControllerInterface, 
    ContentResponseInterface,
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
     * Flag for DI no default value
     * 
     * @var string NO_DEFAULT_VALUE
     */
    private const NO_DEFAULT_VALUE = '__DI_NO_DEFAULT_VALUE__';
    
    /**
     * All allowed HTTP request methods.
     * 
     * @var array<string,string> HTTP_METHODS
     */
    private const HTTP_METHODS = [
        'GET'       => true,
        'PUT'       => true,
        'POST'      => true,
        'HEAD'      => true,
        'QUERY'     => true,
        'PATCH'     => true,
        'DELETE'    => true,
        'OPTIONS'   => true,
        'CLI'       => true, //Fake a request method for cli
    ];

    /**
     * Supported handles response classes.
     * 
     * @var array<class-string,true> RESPONSES
     */
    private const RESPONSES = [
        Response::class => true,
        ResponseInterface::class => true,
        HttpResponseInterface::class => true,
        ContentResponseInterface::class => true,
        \Luminova\Http\Message\Response::class => true,
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
     * The normalized static Uri path. 
     * 
     * @var string|null $uriPath
     */
    public static ?string $uriPath = null;

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
     * Allow Dependency injection.
     * 
     * @var bool|null $useDependencyInjection 
     */
    private static ?bool $useDependencyInjection = null;

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
     * Undocumented variable
     *
     * @var Application|null
     */
    private static ?Application $app = null;

    /**
     * Initializes the Router class and sets up default properties.
     * 
     * @param Application|null $app Instance of core application class.
     */
    public function __construct(?Application $app = null)
    {
        self::$useDependencyInjection ??= Env::get('feature.route.dependency.injection', false);

        if($app instanceof Application){
            self::$app = $app;
        }

        if(Runtime::isCommand()){
            Terminal::init();
        }

        self::reset(true);
    }

    /**
     * {@inheritdoc}
     */
    public function setApplication(Application $app): self 
    {
        self::$app = $app;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function context(?array $contexts = null): self 
    {
        self::onInitialized();
        self::$uri = self::getUri();

        $prefix = self::getPrefix();
        $isCommand = Runtime::isCommand();

        $info = [
            'context' => $isCommand ? 'CLI' : 'HTTP',
            'method'  => self::$method,
            'uri'     => self::$uri
        ];

        $info[Runtime::isHmvc() ? 'module' : 'prefix'] = $prefix;

        // When using attribute for routes.
        if(Env::get('feature.route.attributes', false)){
            self::getApp()->trigger('onStart', $info);

            return $this->withAttributes($prefix);
        }

        if($contexts === null){
            if ($isCommand){
                $contexts = ['cli' => ['prefix' => 'cli', 'isCommand' => true]];
            } else{
                $api = Luminova::apiPrefix();
                $contexts = [
                    'web' => ['prefix' => 'web', 'onError' => null],
                    $api  => ['prefix' => $api, 'onError' => null]
                ];
            }
        }

        // When using default context manager.
        if($contexts === []){
           RouterException::rethrow('no.context', ErrorCode::RUNTIME_ERROR);
        }
        
        if (isset(self::HTTP_METHODS[self::$method])) {
            self::getApp()->trigger('onStart', $info);

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
    public static function query(string $pattern, Closure|string $callback): void
    {
        self::http('http.routes', 'QUERY', $pattern, $callback);
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
        if (!Runtime::isCommand()) {
            RouterException::rethrow('invalid.middleware.cli');
        }

        $group = trim($group, '/');

        if (
            $group === ''
            || preg_match('/^[\p{L}][\p{L}\p{N}_:-]*$/u', $group) !== 1
        ) {
            RouterException::rethrow(
                'invalid.cli.group',
                ErrorCode::INVALID_ARGUMENTS,
                [$group]
            );
        }

        self::$routes['cli.middleware']['CLI'][$group][] = [
            'callback'   => $callback,
            'pattern'    => $group,
            'middleware' => true,
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
            'callback'   => $callback,
            'pattern'    => self::toPatterns(trim($command, '/'), true),
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

        $callback(...self::builtinInjection($callback));
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
    public static function onError(
        Closure|array $handler,
        string $pattern = '/'
    ): void
    {
        if (!Runtime::isCallable($handler)) {
            throw new RouterException(
                "Invalid error handler: '\$handler' must be a valid callable " .
                "(closure, callable string, or [Controller::class, method]).",
                ErrorCode::INVALID_ARGUMENTS
            );
        }
        
        $pattern = trim($pattern);
        $pattern = ($pattern === '/' || $pattern === '') 
            ? '/' 
            : self::toPatterns($pattern);

        self::$routes['http.errors'][$pattern] = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace): self
    {
        $namespace = trim($namespace, " \\");

        self::assertRootNamespace($namespace);

        self::$namespace[] = "\\{$namespace}\\";

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        Runtime::clearLastError();
        
        if(
            (self::$method === 'CLI' || Runtime::isCommand()) 
            && self::hasCommand('no-profiling')
        ){
            Performance::disable();
        }
        
        if(self::$terminate){
            Runtime::profiling('stop');
            exit(STATUS_SUCCESS);
        }

        $isCommand = self::$method === 'CLI';
        $exitCode = STATUS_ERROR;

        if($isCommand && !Runtime::isCommand()){
            RouterException::rethrow('invalid.request.method', ErrorCode::INVALID_REQUEST_METHOD, [
                self::$method,
                'CLI'
            ]);
        }

        if(!$isCommand && Runtime::isCommand()){
            RouterException::rethrow('invalid.request.method', ErrorCode::INVALID_REQUEST_METHOD, [
                self::$method,
                'HTTP'
            ]);
        }

        try{
            $exitCode = $isCommand 
                ? $this->runAsCommand() 
                : $this->runAsHttp();

            Runtime::profiling('stop', $isCommand ? self::$commands : null);
            Runtime::tips();
        }catch(Throwable $e){
            if($e instanceof LuminovaException){
                $e->handle();
                return;
            }

            RouterException::handleException($e->getMessage(), $e->getCode(), $e);
        } finally {
            ob_start();
            try{
                self::getApp()->trigger('onFinish', Runtime::get(Runtime::CLASS_METADATA));
            } catch(Throwable $e){
                Logger::exception($e);
            }
            ob_end_flush();
        }

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

        if (preg_match('/^(?:\(\:)?[\p{L}_][\p{L}\p{N}._-]*(?:\))?$/u', $name) !== 1) {
            throw new RouterException(
                sprintf(
                    'Invalid placeholder name "%s". Must start with a letter or underscore and contain only letters, numbers, dot, underscore, or hyphen.',
                    $name
                ),
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        if (!str_starts_with($name, '(:')) {
            $name = "(:{$name})";
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
    public static function getUriPath(): string
    {
        if (self::$uriPath !== null) {
            return self::$uriPath;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if ($requestUri === '') {
            return self::$uriPath = '/';
        }

        $uri = rawurldecode($requestUri);

        if (($position = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $position);
        }

        $base = Luminova::documentRootUri();

        if ($base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        return self::$uriPath = '/' . ltrim($uri, '/');
    }

    /**
     * {@inheritdoc}
     */
    public static function getUriSegments(): array
    {
        $path = trim(self::getUriPath(), '/');

        if ($path === '' || $path === 'public') {
            return [];
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return ($path === '') 
            ? [] 
            : explode('/', $path);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSegment(): Segments
    {
        return new Segments(
            Runtime::isCommand()
                ? [self::CLI_URI]
                : self::getUriSegments()
        );
    }

    /**
     * {@inheritdoc}
     */
    public static function isSegment(string $name, int $position = 0): bool
    {
        $segments = self::getUriSegments();

        return isset($segments[$position])
            && $segments[$position] === trim($name, '/');
    }

    /**
     * {@inheritdoc}
     */
    public static function isUriPrefix(array|string $prefix): bool
    {
        $segment = self::getUriSegments()[0] ?? null;

        if ($segment === null) {
            return false;
        }

        foreach ((array) $prefix as $value) {
            if ($segment === trim($value, '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public static function isApiPrefix(): bool
    {
        return self::isSegment(Luminova::apiPrefix());
    }

    /**
     * {@inheritdoc}
     */
    public static function isApiRequest(?bool $ajaxAsApi = null): bool
    {
        if (self::isApiPrefix()) {
            return true;
        }

        $ajaxAsApi ??= Env::get('app.validate.ajax.asapi', false);

        return $ajaxAsApi
            && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0;
    }

    /**
     * Determine whether the first URI segment matches any given prefix.
     *
     * This checks only the first segment of the current request URI,
     * making it useful for simple route grouping (e.g. `/admin`, `/api`).
     *
     * @param array|string $prefix One or more URI prefixes (e.g. "/admin", "api").
     *
     * @return bool Returns true if the first URI segment matches any prefix.
     *
     * @deprecated  Use {@see self::isUriPrefix()}
     */
    public static function isPrefix(array|string $prefix): bool
    {
        return self::isUriPrefix($prefix);
    }

    /**
     * Get the request url segments as relative.
     * 
     * Resolves the request URI as a relative path, without query string or base path.
     *
     * @return string Return the normalized URI segment path (e.g., `/products/view/10`)
     */
    private static function getUri(): string
    {
        return Runtime::isCommand() 
            ? self::CLI_URI 
            : '/' . trim(self::getUriPath(), '/');
    }

    /**
     * Load required route context only.
     * 
     * Load the route URI context prefix and make router/application available
     * as global variables inside the context file.
     *
     * @param string $prefix Route URI context prefix name.
     * 
     * @return void
     * @throws RouterException
     */
    private function onPrefixContext(string $prefix): void 
    {
        $filepath = Luminova::root('routes', "{$prefix}.php");

        if (!is_file($filepath)) {
            Luminova::terminate(
                500, 
                RouterException::getInformation('invalid.context', $prefix, $prefix),
                httpOutput: self::isApiPrefix() ? 'json' : 'html'
            );
        }

        try{
            Closure::bind(
                static function (
                    string $prefix, 
                    string $filepath, 
                    RouterInterface $router, 
                    Application $app
                ): void {
                    require_once $filepath;
                }, 
                null, 
                null
            )($prefix, $filepath, $this, self::getApp());
        } finally {
            self::getApp()->trigger('onRouteResolved', $prefix);
        }
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
     *                     If true, calls `AppError::onTrigger()` before checking other handlers.
     *
     * @return never
     */
    private static function onTriggerError(
        int $status = 404, 
        bool $global = false
    ): never
    {
        Header::clearOutputBuffers('all');
  
        if($global && method_exists(AppError::class, 'onTrigger')){
            AppError::onTrigger($status, self::getSegment());

            exit;
        }

        if(self::handleErrors($status)){
            exit;
        }

        if(!$global && method_exists(AppError::class, 'onTrigger')){
            AppError::onTrigger($status, self::getSegment());

            exit;
        }

        if(self::$method === 'OPTIONS'){
            header('Access-Control-Max-Age: 86400');
            Luminova::terminate(204, '');
            exit;
        }

        Luminova::terminate(
            $status,
            match ($status) {
            404 => 'The requested resource could not be found.',
            405 => 'Request method "' . self::$method . '" is not allowed.',
            401 => 'Authentication required but missing/invalid',
            400 => 'The request is invalid or could not be processed.',
            default => PRODUCTION
                ? 'An error occurred while processing your request.'
                : "An error occurred:\n\n"
                    . "- No controller is registered for the requested URL.\n"
                    . "- No custom error handler is registered for this URL or its prefix.\n"
                    . "- Check the controller's prefix pattern to ensure it does not exclude the requested URL.",
            },
            httpOutput: self::isApiPrefix() ? 'json' : 'html'
        );
        exit;
    }

    /**
     * Handle route errors.
     *
     * @param int $status HTTP status code.
     * 
     * @return bool Return true if error was handled, otherwise false.
     */
    private static function handleErrors(int $status): bool
    {
        foreach (self::$routes['http.errors'] as $pattern => $callable) {
            $matches = [];

            if(!self::matchUri($pattern, self::$uri, matches: $matches)){
                continue;
            }

            $result = self::call(
                $callable, 
                [$status, ...self::routeMatchesToArgs($matches)],
                forceInjection: true
            );

            if ($result === STATUS_SUCCESS || $result === STATUS_SILENCE) {
                return true;
            }
        }
    
        $root = self::$routes['http.errors']['/'] ?? null;

        if(!$root){
            return false;
        }

        $result = self::call($root, [$status], forceInjection: true);

        return $result === STATUS_SUCCESS 
            || $result === STATUS_SILENCE;
    }

    /**
     * Application object.
     *
     * @return Application
     */
    private static function getApp(): Application
    {
        if(!self::$app instanceof Application){
            self::$app = Kernel::resolve(Kernel::SERVICE_APPLICATION, shared: true);
        }   

        return self::$app;
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
     * @return array{0:?string,1:?string} Returns a [class, method] pair if invalid.
     */
    private static function getClassHandler(array|string $callback): array
    {
        if (is_array($callback)) {
            return $callback + [null, null];
        }

        $annotation = match(true) {
            str_contains($callback, '::')  => '::',
            str_contains($callback, '@')   => '@',
            default => null
        };

        if ($annotation === null) {
            return $callback 
                ? [$callback, '__invoke'] 
                : [null, null];
        }

        [$class, $method] = explode(
            $annotation, 
            $callback, 
            2
        );

        return [self::findClassNamespace($class), $method];
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
    private static function findClassNamespace(string $className): string
    {
        if (str_contains($className, '\\') || class_exists($className)) {
            return $className;
        }

        $prefix = Runtime::isCommand() ? 'Cli\\' : 'Http\\';

        foreach (self::$namespace as $namespace) {
            $class = "{$namespace}{$prefix}{$className}";

            if (class_exists($class)) {
                return $class;
            }
        }

        if(Runtime::isCommand()){
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
     */
    private function isNamespace(string $namespace): bool
    {
        $pattern = Runtime::isHmvc()
            ? '/^App\\\\{1,2}Modules\\\\{1,2}(?:Controllers|[\p{L}_][\p{L}\p{N}_]*\\\\{1,2}Controllers)\\\\{0,2}$/u'
            : '/^App\\\\{1,2}Controllers\\\\{0,2}$/';

        return preg_match($pattern, $namespace) === 1;
    }

    /**
     * Validate controller namespace 
     * 
     * @param string $namespace The namespace.
     * 
     * @return void
     * @throws RouterException If on development
     */
    private function assertRootNamespace(string $namespace): void
    {
        if ($namespace === '' || $namespace === '\\') {
            RouterException::rethrow(
                'argument.empty',
                ErrorCode::INVALID_ARGUMENTS,
                ['$namespace']
            );

            return;
        }

        if (!str_starts_with($namespace, 'App\\')) {
            RouterException::rethrow(
                'invalid.namespace',
                ErrorCode::NOT_ALLOWED,
                [$namespace]
            );

            return;
        }

        if (!str_ends_with($namespace, '\\Controllers')) {
            RouterException::rethrow(
                'invalid.namespace.end',
                ErrorCode::NOT_ALLOWED,
                [$namespace]
            );

            return;
        }

        if (self::isNamespace($namespace)) {
            return;
        }

        RouterException::rethrow(
            'invalid.namespace.root',
            ErrorCode::NOT_ALLOWED,
            Runtime::isHmvc()
                ? [
                    'HMVC',
                    $namespace,
                    '\\App\\Modules\\',
                    ', (e.g., "\App\Modules\<Module>\Controllers\")',
                ]
                : [
                    'MVC',
                    $namespace,
                    '\\App\\',
                    ', (e.g., "\App\Controllers\")',
                ]
        );
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
        if(Runtime::isCommand()){
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
     * Register a http route.
     *
     * @param string $to The routing group name to add this route.
     * @param string $methods  Allowed methods, can be serrated with | pipe symbol.
     * @param string $pattern The route URL pattern or template view name
     *               (e.g, `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
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
        if(Runtime::isCommand()){
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
        if(Runtime::isCommand()){
            return self::CLI_URI;
        }

        return self::getUriSegments()[0] ?? '';
    }

    /**
     * Is context a web instance.
     *
     * @param string $name The context name.
     * @param string $prefix The first URI prefix.
     * 
     * @return bool Return true if the context is a web instance, otherwise false.
     */
    private static function isWeContext(string $name, string $prefix): bool 
    {
        return (
            $prefix === '' || 
            $name === Prefix::WEB
        )   && $name !== Prefix::CLI 
            && $name !== Prefix::API 
            && !self::isApiPrefix();
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
        Runtime::add(Runtime::CLASS_METADATA, 'command', self::getCommandSegment());
        
        $isHelp = false;
        $needHelp = !$group 
            || !$command
            || ($isHelp = Terminal::isHelp($group));

        if($needHelp){
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

                $group(...self::builtinInjection($group));
            }

            $middleware = (self::$routes['cli.middleware'][self::$method][$group] ?? null);

            if($middleware !== null && !self::handleCommand($middleware)){
                return STATUS_ERROR;
            }
            
            $routes = self::$routes['cli.commands'][self::$method] ?? null;

            if ($routes !== null && self::handleCommand($routes)) {
                return STATUS_SUCCESS;
            }
        }

        $isArray = is_array($group);
        Terminal::oops(($isArray ? '' : "'{$group} ") . $command);

        if($isArray){
            $suggestion = Color::style(
                "{$group['pattern']} {$command}", 
                'cyan'
            );
            
            Terminal::writeln(
                "Do you mean: '{$suggestion}'?", 
                stream: Terminal::STD_ERR
            );

        }

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

        if (
            $middleware !== []
            && self::handleWebsite($middleware, self::$uri, true) !== STATUS_SUCCESS
        ) {
            return STATUS_ERROR;
        }

        $error = 404;
        $routes = self::getRoutes('http.routes', $error);

        if($routes !== []){
            $status = self::handleWebsite($routes, self::$uri);

            if ($status === STATUS_SILENCE) {
                return STATUS_ERROR;
            }

            if ($status === STATUS_SUCCESS) {
                $after = self::getRoutes('http.after');
                
                if($after === []){
                    return STATUS_SUCCESS;
                }
                
                ob_start();
                self::handleWebsite($after, self::$uri);
                ob_end_clean(); 

                return STATUS_SUCCESS;
            }
        }

        self::onTriggerError($error);
        return STATUS_ERROR;
    }

    /**
     * Retrieve the registered HTTP routes for a specific controller.
     * 
     * @param string $from The name of the controller for which to retrieve the routes.
     * @param int $error
     * 
     * @return array Return an array of routes registered for the given controller 
     * and HTTP method, or an empty array if none are found.
     */
    private static function getRoutes(string $from, int &$error = 404): array 
    {
        if(!(self::$routes[$from] ?? null)){
            $error = 500;
            return [];
        }

        $routes = array_merge(
            self::$routes[$from][self::$method] ?? [], 
            self::$routes[$from][self::ANY_METHOD] ?? []
        );

        if($routes === []){
            $error = 405;
            return [];
        }

        return $routes;
    }
    
    /**
     * Handle a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array<string,mixed> $routes Collection of route patterns and their handling functions.
     * @param string $uri The view request URI path.
     * @param boolean $isMiddleware
     *
     * @return int Return status code.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function handleWebsite(
        array $routes,
        string $uri,
        bool $isMiddleware = false
    ): int 
    {
        foreach ($routes as $route) {
            $matches = [];

            if (!self::matchUri($route['pattern'], $uri, matches: $matches)) {
                continue;
            }

            return self::call(
                $route['callback'],
                self::routeMatchesToArgs($matches),
                isHttpMiddleware: ($route['middleware'] ?? false)
            );
        }

        return $isMiddleware
            ? STATUS_SUCCESS
            : STATUS_ERROR;
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
        self::$commands = Terminal::parseCommands(
            $_SERVER['argv'] ?? [],
            true
        );

        $queries = self::getCommandSegment();
        $isHelp = Terminal::isHelp();

        foreach ($routes as $route) {
            $isMatch = false;
            $isHelpRoute = ($isHelp || $queries['view'] === $route['pattern']);
            $isMiddleware = !$isHelpRoute && ($route['middleware'] ?? false);
            $matches = [];

            if (
                !$isHelpRoute 
                && !$isMiddleware
                && self::matchUri($route['pattern'], $queries['view'], matches: $matches)
            ) {
                $isMatch = true;
                self::$commands['params'] = self::routeMatchesToArgs($matches);
            }

            if ($isMatch || $isMiddleware || $isHelpRoute) {
                return self::call(
                    $route['callback'],
                    self::$commands,
                    isCliMiddleware: $isMiddleware
                ) === STATUS_SUCCESS;
            }
        }

        return false;
    }

    /**
     * Convert captured URI parameters into trimmed method arguments.
     *
     * Supports match results from both {@see preg_match()} and
     * {@see preg_match_all()} when using {@see PREG_OFFSET_CAPTURE}.
     * The complete URI match is excluded from the returned arguments.
     *
     * Unmatched optional parameters are returned as empty strings.
     *
     * @param array<int,array> $matches Regex matches returned with {@see self::matchUri()}.
     *
     * @return string[] The captured and trimmed route arguments.
     */
    private static function routeMatchesToArgs(array $matches): array
    {
        $params = [];

        foreach (array_slice($matches, 1) as $match) {
            if (isset($match[0][0]) && is_array($match[0])) {
                $match = $match[0];
            }

            if(($match[1] ?? -1) === -1){
                $params[] = '';
                continue;
            }

            $params[] = trim($match[0] ?? '', " \t\n\r/");
        }

        return $params;
    }

    /**
     * Match a request URI against a route pattern and capture its parameters.
     *
     * The pattern is matched against the complete URI using extended regular
     * expression mode. Route matching is case-sensitive.
     *
     * When `$matchAll` is true, all occurrences of the pattern are captured
     * using {@see preg_match_all()}; otherwise, only the first match is captured
     * using {@see preg_match()}.
     *
     * @param string $pattern The route regular expression pattern.
     * @param string $uri The request URI to match.
     * @param bool $matchAll Whether to capture all pattern matches.
     * @param array &$matches Reference to store the matched values and offsets.
     *
     * @return bool True if the pattern matches the URI, otherwise false.
     *
     * @throws RouterException If the regular expression is invalid and the
     *                         application is not running in production.
     *
     * @see self::routeMatchesToArgs()
     */
    private static function matchUri(
        string $pattern,
        string $uri,
        bool $matchAll = false,
        array &$matches = []
    ): bool 
    {
        $regex = "#^{$pattern}$#x";
        $result = $matchAll
            ? preg_match_all($regex, $uri, $matches, PREG_OFFSET_CAPTURE)
            : preg_match($regex, $uri, $matches, PREG_OFFSET_CAPTURE);

        if ($result === false) {
            RouterException::handleException(sprintf(
                'Invalid route pattern "%s": %s',
                $pattern,
                Runtime::lastError()['message'] ?? preg_last_error_msg()
            ), ErrorCode::ROUTING_ERROR);

            return false;
        }

        return $result > 0;
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
            $placeholders = self::mergePlaceholders();
            
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
     * Resolve dependencies and cast arguments to the callable's parameter types.
     *
     * Inspects the callable parameters and resolves class dependencies through
     * dependency injection when enabled. Built-in parameters are cast from the
     * supplied arguments, while nullable and default values are handled according
     * to their parameter declarations.
     *
     * Intersection types are resolved as a single dependency that must satisfy all
     * declared types. Union types are resolved using the supported route parameter
     * types and dependency injection rules.
     *
     * Any arguments not consumed by the callable parameters are preserved and
     * appended to the returned argument list.
     *
     * @param ReflectionMethod|callable $caller Method or callable to inspect.
     * @param array<int,mixed> $arguments Arguments supplied to the callable.
     * @param bool $forceInjection Force use dependency injection.
     *
     * @return array<int,mixed> Resolved arguments ready to be passed to the callable.
     */
    private static function injection(
        ReflectionMethod|callable $caller,
        array $arguments = [],
        bool $forceInjection = false
    ): array 
    {
        $useDi = self::$useDependencyInjection 
            || $forceInjection;

        if (!$useDi && $arguments === []) {
            return $arguments;
        }

        static $supported = [
            'int'    => true,
            'bool'   => true,
            'true'   => true,
            'false'  => true,
            'string' => true,
            'float'  => true,
            'double' => true,
        ];

        $parameters = self::newReflection($caller, $arguments);

        if ($parameters === []) {
            return [];
        }

        $injections = [];
        $argumentCount = count($arguments);
        $argumentIndex = 0;

        foreach ($parameters as $parameter) {
            $default = self::NO_DEFAULT_VALUE;

            [$type, $isNullable, $isBuiltin, $kind] = self::getNamedTypeParam(
                $parameter->getType(),
                $supported,
                useDi: $useDi
            );

            if (
                $type === null
                || ($isBuiltin && $argumentIndex >= $argumentCount)
                || (!$useDi  && !$isBuiltin)
            ) {
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
            }

            if (!$isBuiltin) {
                $injections[] = ($kind === 'intersection') 
                    ? self::newIntersection($type, $default)
                    : self::newInstance(
                        $type,
                        $isNullable,
                        $default
                     );

                continue;
            }

            $injections[] = self::typeCasting(
                $type,
                $arguments[$argumentIndex++] ?? null,
                $isNullable,
                $default,
                $kind === 'union'
            );
        }

        return array_merge(
            $injections,
            array_slice($arguments, $argumentIndex)
        );
    }

    /**
     * Resolve the callable's reflection parameters.
     *
     * @param ReflectionMethod|callable $caller Method or callable to inspect.
     * @param array<int,mixed> $arguments Supplied callable arguments.
     *
     * @return \ReflectionParameter[] Reflected callable parameters,
     *         or an empty array when the callable has no parameters.
     * 
     * @throws RouterException If the callable declares no parameters but
     *                         arguments are supplied outside production.
     */
    private static function newReflection(
        ReflectionMethod|callable $caller,
        array $arguments = []
    ): array
    {
        if (!$caller instanceof ReflectionMethod) {
            $caller = new ReflectionFunction($caller);
        }

        if ($caller->getNumberOfParameters() > 0) {
            return $caller->getParameters();
        }

        if (PRODUCTION || $arguments === []) {
            return [];
        }

        RouterException::rethrow('bad.method', ErrorCode::BAD_METHOD_CALL, [
            $caller->isClosure()
                ? $caller->getName()
                : $caller->getDeclaringClass()->getName() . '->' . $caller->getName(),
            count($arguments),
            Luminova::toDisplayPath($caller->getFileName()),
            $caller->getStartLine()
        ]);

        return [];
    }

    /**
     * Resolve parameter type metadata.
     *
     * Supports named, union, and intersection reflection types and normalizes
     * their metadata into a consistent tuple. Intersection types are only
     * resolved when dependency injection is enabled.
     *
     * @param ReflectionType|null $type Parameter reflection type to resolve.
     * @param array<string,bool> $supported Supported built-in parameter types.
     * @param bool $useDi Whether dependency injection is enabled.
     *
     * @return array{0:string|array<int,string>|null,1:bool,2:bool,3:string}
     *         A tuple containing the resolved type, nullability, built-in flag,
     *         and type kind (`named`, `union`, `intersection`, or `unknown`).
     */
    private static function getNamedTypeParam(
        ?ReflectionType $type,
        array $supported = [],
        bool $useDi = false
    ): array
    {
        if ($type instanceof ReflectionNamedType) {
            return [
                $type->getName(),
                $type->allowsNull(),
                $type->isBuiltin(),
                'named',
            ];
        }

        if ($type instanceof ReflectionUnionType) {
            $param = self::getUnionType(
                $type->getTypes(),
                $supported,
                $useDi
            );

            $param[] = 'union';

            return $param;
        }

        if (
            !$useDi
            || !($type instanceof ReflectionIntersectionType)
        ) {
            return [null, false, false, 'unknown'];
        }

        $hints = [];

        foreach ($type->getTypes() as $param) {
            if ($param instanceof ReflectionNamedType) {
                $hints[] = $param->getName();
            }
        }

        return [
            $hints ?: null,
            false,
            false,
            'intersection',
        ];
    }

    /**
     * Resolve dependencies without consuming URI arguments.
     *
     * Inspects the callable parameters and resolves only non-built-in types
     * through dependency injection. Built-in parameters are ignored so that
     * URI arguments are never consumed or mixed into the injected arguments.
     *
     * Supports named and intersection dependency types.
     *
     * @param ReflectionMethod|callable $caller Method or callable to resolve.
     *
     * @return array<int,mixed> Resolved dependency instances in parameter order.
     */
    private static function builtinInjection(
        ReflectionMethod|callable $caller
    ): array 
    {
        $parameters = self::newReflection($caller);

        if ($parameters === []) {
            return [];
        }

        $injections = [];

        foreach ($parameters as $parameter) {
            [$type, $isNullable, $isBuiltin, $kind] = self::getNamedTypeParam(
                $parameter->getType(),
                useDi: true
            );

            if ($isBuiltin || $type === null) {
                continue;
            }

            $default = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : self::NO_DEFAULT_VALUE;

            $injections[] = ($kind === 'intersection')
                ? self::newIntersection($type, $default)
                : self::newInstance(
                    $type,
                    $isNullable,
                    $default
                );
        }

        return $injections;
    }

    /**
     * Execute a router callback with the given arguments.
     *
     * Executes closures directly or resolves and invokes controller callbacks.
     * Arguments are resolved and cast according to the callback parameter types,
     * with optional dependency injection.
     *
     * @param Closure|array{0:class-string<RoutableInterface>,1:string}|string $callback
     *        Router callback to execute. Accepts a closure, controller callback
     *        array, or callable class method.
     * @param array<int|string,mixed> $arguments Arguments to pass to the callback.
     * @param bool $forceInjection Force use dependency injection.
     * @param bool $isCliMiddleware Whether the callback is CLI middleware
     *        (default: false).
     * @param bool $isHttpMiddleware Whether the callback is HTTP middleware
     *        (default: false).
     *
     * @return int Response status returned by the executed callback.
     *
     * @throws RouterException If the callback or controller method is invalid
     *                         or cannot be executed.
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
            $isCommand = Runtime::isCommand() && isset($arguments['name']);

            self::assertReturnTypes(
                $callback,
                isCommand: $isCommand
            );

            $arguments = $isCommand
                ? ($arguments['params'] ?? [])
                : $arguments;

            Runtime::add(
                Runtime::CLASS_METADATA,
                'namespace',
                '\\Closure'
            );

            Runtime::add(
                Runtime::CLASS_METADATA,
                'method',
                'function'
            );

            return self::send(
                $callback(...self::injection(
                    $callback,
                    $arguments,
                    forceInjection: $forceInjection
                )),
                $isHttpMiddleware
            );
        }

        [$namespace, $method] = self::getClassHandler($callback);

        if (!$namespace || !$method) {
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
     * @param bool $forceInjection Force use dependency injection.
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

        Runtime::add(Runtime::CLASS_METADATA, 'namespace', $namespace);
        Runtime::add(Runtime::CLASS_METADATA, 'method', $method);
        Runtime::add(Runtime::CLASS_METADATA, 'uri', self::$uri);
        

        try {
            $class = new ReflectionClass($namespace);

            if (!($class->isInstantiable() && $class->implementsInterface(RoutableInterface::class))) {
                RouterException::rethrow('invalid.controller', ErrorCode::INVALID_CONTROLLER, [
                    $namespace
                ]);
                
                return STATUS_ERROR;
            }

            $isCommand = Runtime::isCommand() && isset($arguments['name']);
            $caller = $class->getMethod($method);
            
            self::assertReturnTypes($caller, $namespace, $isCommand);
            
            if ($caller->isPublic() && !$caller->isAbstract() && 
                (
                    !$caller->isStatic() || 
                    ($caller->isStatic() && $class->implementsInterface(ErrorControllerInterface::class))
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
                    $caller->invokeArgs($instance, self::injection(
                        $caller, 
                        $arguments, 
                        $forceInjection
                    )),
                    $isHttpMiddleware 
                );
                
                if($isHttpMiddleware && $result === STATUS_ERROR){
                    $class->getMethod('onMiddlewareFailure')
                        ->invokeArgs($instance, [self::$uri, Runtime::get(Runtime::CLASS_METADATA)]);
                }

                return $result;
            }
        } catch (Throwable $e) {
            $isFewArgs = str_contains($e->getMessage(), 'Too few arguments');

            if($isFewArgs || !($e instanceof LuminovaException)){
                $message = $isFewArgs 
                    ? sprintf(
                        '%s. Ensure that routing dependency injection is enabled in env "%s"%s. See %s', 
                        $e->getMessage(),
                        '<highlight>feature.route.dependency.injection</highlight>',
                        ', or remove arguments method signature',
                        '<link>https://luminova.ng/docs/0.0.0/routing/dependency-injection</link>'
                    ) : $e->getMessage();

                (new RouterException($message, $e->getCode(), $e))
                    ->setFile($e->getFile())
                    ->setLine($e->getLine())
                    ->handle();

                return STATUS_ERROR;

            }

            $e->handle();
            return STATUS_ERROR;
        }

        RouterException::rethrow('invalid.method', ErrorCode::INVALID_METHOD, [$method]);
        return STATUS_ERROR;
    }
 
    /**
     * Sends an HTTP response or outputs a view response.
     *
     * This method handles different response types from the routing system:
     * - If a ContentResponseInterface is given, it directly calls its output method.
     * - If a PSR-7 ResponseInterface is given, it sends headers, outputs the body,
     *   and returns a status code.
     * - If an integer is given, it is treated as an immediate status code return.
     *
     * @param ContentResponseInterface|ResponseInterface|int $response
     *     The response object or status code from the routed action.
     *
     * @return int
     *     STATUS_SUCCESS if output was sent,
     *     STATUS_SILENCE if no content,
     *     or any integer code passed directly.
     */
    private static function send(
        mixed $response, 
        bool $isHttpMiddleware
    ): int
    {
        if($response instanceof ContentResponseInterface){
            return $response->output();
        }

        if(!$response instanceof ResponseInterface){
            if(is_int($response)){
                return (int) $response;
            }

            if (PRODUCTION) {
                return STATUS_ERROR;
            }

            self::throwUnsupportedReturnType(
                get_debug_type($response),
                handler: null,
                isUnion: false,
                isCommand: Runtime::isCommand()
            );
        }

        $status = $response->getStatusCode();
        $contents = '';

        if(self::$method !== 'HEAD'){
            $contents = (string) $response->getBody()->getContents();
            
            if ($contents === '' && $status !== 204 && $status !== 304) {
                $status = 204;
            }
        }

        Header::clearOutputBuffers('all');
        Header::setOutputHandler(true);
        Header::send($response->getHeaders(), status: $status);
    
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
        if (PRODUCTION) {
            return;
        }

        try {
            $reflection = ($method instanceof ReflectionMethod)
                ? $method
                : new ReflectionFunction($method);

            $returnType = $reflection->getReturnType();
            $name = $reflection->getName() ?: 'callable';
        } catch (Throwable) {
            return;
        }

        $isUnion = ($returnType instanceof ReflectionUnionType);
        $isIntersection = ($returnType instanceof ReflectionIntersectionType);

        $types = match (true) {
            $isUnion,
            $isIntersection
                => $returnType->getTypes(),

            $returnType instanceof ReflectionNamedType
                => [$returnType],

            default => [],
        };

        if($types === []){
            self::throwUnsupportedReturnType(
                'mixed',
                $namespace ? "{$namespace}::{$name}" : $name,
                isCommand: $isCommand
            );
        }

        $typeNames = array_map(
            static fn(ReflectionNamedType $type): string => $type->getName(),
            $types
        );

        if (
            !$isUnion 
            && ($returnType instanceof ReflectionNamedType)
            && $returnType->allowsNull()
            && !in_array('mixed', $typeNames, true)
        ) {
            $typeNames[] = 'null';
            $isUnion = true;
        }

        static $builtins = [
            'string'   => true,
            'int'      => true,
            'float'    => true,
            'double'   => true,
            'mixed'    => true,
            'callable' => true,
            'bool'     => true,
            'true'     => true,
            'false'    => true,
            'array'    => true,
            'object'   => true,
            'void'     => true,
            'never'    => true,
            'null'     => true,
        ];
    
        $allowed = ['int' => true];

        foreach ($typeNames as $type) {
            if (isset($allowed[$type])) {
                continue;
            }

            if (!$isCommand) {
                if (isset(self::RESPONSES[$type])) {
                    continue;
                }

                if (!isset($builtins[$type])) {
                    foreach (self::RESPONSES as $response => $_) {
                        if (is_a($type, $response, true)) {
                            continue 2;
                        }
                    }
                }
            }

            self::throwUnsupportedReturnType(
                implode('|', $typeNames),
                $namespace ? "{$namespace}::{$name}" : $name,
                isUnion: $isUnion || $isIntersection,
                isCommand: $isCommand
            );
        }
    }

    /**
     * Throw Unsupported type exception.
     *
     * @param string $type
     * @param string|null $handler
     * @param boolean $isUnion
     * @param boolean $isCommand
     * 
     * @return never
     * @throws RouterException
     */
    private static function throwUnsupportedReturnType(
        string $type,
        ?string $handler = null,
        bool $isUnion = false,
        bool $isCommand = false
    ): never 
    {
        $expected = 'int (STATUS_SUCCESS, STATUS_ERROR, STATUS_SILENCE)';

        if (!$isCommand) {
            $expected .= sprintf(
                ', or a response type implementing: %s',
                implode(', ', array_keys(self::RESPONSES))
            );
        }

        throw new RouterException(
            sprintf(
                '%sreturned unsupported type "%s"%s%s',
                ($handler !== null)
                    ? sprintf('Routable handler "%s" ', $handler)
                    : 'Handler ',
                $type,
                $isUnion
                    ? ', union/intersection types must satisfy expected types: '
                    : '. Expected: ',
                $expected
            ),
            ErrorCode::LOGIC_ERROR
        );
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
        $isHmvc = Runtime::isHmvc();
        $path = $isHmvc ? 'app/Modules/' : 'app/Controllers/';

        $attr = new Compiler(
            self::$base, 
            Runtime::isCommand(), 
            $isHmvc
        );

        if(Runtime::isCommand()){
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
     * Resolve and register routes for the current request.
     *
     * Registers routes when using method-based routing instead of route attributes.
     *
     * @param string $uriPrefix The request URI prefix.
     * @param Prefix[]|array<string,array> $contexts Application prefix contexts.
     *
     * @return self The router instance.
     */
    private function withMethods(string $uriPrefix, array $contexts): self
    {
        $request = [];
        $websites = [];
        $current = self::$base;
        $isCommand = Runtime::isCommand();

        foreach ($contexts as $name => $context) {
            $prefix = ($context instanceof Prefix)
                ? $context->getPrefix()
                : ($context['prefix'] ?? $name);

            if($prefix === '' || is_int($prefix)){
                continue;
            }

            if ($isCommand) {
                if ($uriPrefix === $prefix && $this->withCommandRoute($context, $prefix)) {
                    break;
                }

                continue;
            }

            if ($uriPrefix === $prefix) {
                $request = [$context, $prefix];
                break;
            }

            if ($websites === [] && self::isWeContext($prefix, $uriPrefix)) {
                $websites = [$context, $prefix];
            }
        }

        if($isCommand || ($request === [] && $websites === [])){
            return $this;
        }

        $this->withHttpRoute($request, $websites);

        self::$base = $current;

        return $this;
    }

    /**
     * Resolve and register an HTTP route context.
     *
     * Resets the router state, registers the context error handler, resolves the
     * context routes, and triggers the route-resolved application event.
     *
     * @param array{0:Prefix|array,1:string} $match Custom URI prefix matched.
     * @param array{0:Prefix|array,1:string} $websites Registered WEB URIs context found.
     *
     * @return void
     */
    private function withHttpRoute(
        array $match,
        array $websites
    ): void 
    {
        self::reset();

        $isMatchPrefix = $match !== [];
        $handler = $match + $websites;
        $context = $handler[0];
        $prefix = $handler[1];
        $pattern = '/';

        $onError = ($context instanceof Prefix)
            ? $context->getErrorHandler()
            : ($context['onError'] ?? null);

        if ($isMatchPrefix) {
            $pattern = "/{$prefix}(?:/[^/].*)?/?";

            self::$base .= $pattern;
        }

        if ($onError !== null) {
            self::onError(pattern: $pattern, handler: $onError);
        }

        $this->onPrefixContext($prefix);
    }

    /**
     * Resolve and register a CLI route context.
     *
     * Verifies that the context is configured for CLI commands before resolving
     * its routes and triggering the route-resolved application event.
     *
     * @param Prefix|array $context Application prefix context.
     * @param string $prefix The resolved context prefix.
     *
     * @return bool `true` if the command context was resolved, otherwise `false`.
     */
    private function withCommandRoute(
        array|Prefix $context,
        string $prefix
    ): bool 
    {
        $isCommand = ($context instanceof Prefix)
            ? $context->isCommand()
            : (bool) ($context['isCommand'] ?? true);

        if (!$isCommand) {
            return false;
        }

        self::reset();

        defined('CLI_ENVIRONMENT')
            || define('CLI_ENVIRONMENT', Env::get('cli.environment.mood', 'testing'));

        $this->onPrefixContext($prefix);

        return true;
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
            'aliases' => [],
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
     * Merge the default and custom route placeholders.
     *
     * Optional variants are automatically generated for typed placeholders using
     * the `(:?type)` syntax. Structural placeholders are excluded from automatic
     * optional generation because they already define their own route structure.
     *
     * @return array<string,string> Return the compiled route placeholder patterns.
     */
    private static function mergePlaceholders(): array
    {
        static $isCompliedPlaceholders;

        if($isCompliedPlaceholders){
            return self::$placeholders;
        }

        $defaults = [
            '(:base)'         => '?(?:/.*)?',
            '(:root)'         => '?(?:/[^/].*)?',
            '(:group)'        => '(.*)',

            '(:int)'          => '(\d+)',
            '(:integer)'      => '(\d+)',
            '(:mixed)'        => '([^/]*)',
            '(:string)'       => '([^/]+)',
            '(:optional)'     => '?(?:/([^/]*))?',

            '(:alphabet)'     => '([a-zA-Z]+)',
            '(:alphanumeric)' => '([a-zA-Z0-9]+)',
            '(:username)'     => '(@?[a-zA-Z0-9._-]+)',

            '(:number)'       => '([+-]?\d+(?:\.\d+)?)',
            '(:numeric)'      => '([-]?\d+(?:\.\d+)?)',
            '(:version)'      => '(\d+(?:\.\d+)+)',
            '(:double)'       => '([+-]?\d+(?:\.\d+)?)',
            '(:float)'        => '([+-]?\d+\.\d+)',

            '(:file)'         => '([^/]+\.[^/]+)',
            '(:filepath)'     => '((?:[^/]+/)*[^/]+\.[^/]+)',
            '(:path)'         => '([^/]+(?:/[^/]+)+)',

            '(:uuid)'         => '([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})',
            '(:ulid)'         => '([0-7][0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{25})',
        ];

        $placeholders = [];
        $excludeOptional = [
            '(:base)'       => true, 
            '(:root)'       => true, 
            '(:group)'      => true, 
            '(:optional)'   => true,
        ];

        foreach ($defaults as $name => $pattern) {
            $placeholders[$name] = $pattern;

            if(isset($excludeOptional[$name])){
                continue;
            }

            $type = substr($name, 2, -1);
            $placeholders["(:?{$type})"] = "?(?:/{$pattern})?";
        }

        $isCompliedPlaceholders = true;

        return self::$placeholders = array_merge(
            $placeholders, 
            self::$placeholders
        );
    }

    /**
     * Resolve or create an instance of the given class.
     *
     * Resolves framework-specific dependencies first, then attempts to resolve
     * the class through the dependency injector. If the class cannot be resolved,
     * it may be instantiated directly or resolved through its singleton instance.
     *
     * @param class-string $class The class name to resolve.
     * @param bool $nullable Whether to return null when the class cannot be resolved.
     * @param object|null $default Default instance to return when resolution fails.
     *
     * @return object|null The resolved instance, default instance, or null.
     *
     * @throws Throwable If class resolution or instantiation fails.
     * @throws ClassException If the class cannot be resolved.
     */
    private static function newInstance(
        string $class,
        bool $nullable = false,
        mixed $default = self::NO_DEFAULT_VALUE
    ): ?object 
    {
        if(DI::isBound($class)){
            return DI::resolve($class);
        }

        $instance = match ($class) {
            View::class             => new View(self::getApp()),
            Router::class           => self::getApp()->router,
            Application::class      => self::getApp(),
            \App\Application::class => \App\Application::getInstance(),
            RouterInterface::class  => Kernel::resolve(
                Kernel::SERVICE_ROUTING, 
                true,
                self::getApp()
            ),
            Segments::class         => self::getSegment(),
            stdClass::class         => new stdClass(),
            Closure::class          => self::getClosure(),
            default                 => class_exists($class) 
                ? new $class() 
                : self::tryServices($class)
        };

        if($instance !== null){
            return $instance;
        }
        
        if ($default !== self::NO_DEFAULT_VALUE && is_object($default)) {
            return $default;
        }

        if ($nullable) {
            return null;
        }

        throw new ClassException(sprintf(
            'Class "%s" does not exist or cannot be injected.',
            $class
        ));
    }

    /**
     * Resolve class interface from kernel service or default DI.
     *
     * @param class-string $class The class name to resolve.
     *
     * @return object|null The resolved instance, default instance, or null.
     */
    private static function tryServices(string $class): ?object
    {
        if(!interface_exists($class)){
            return null;
        }

        try{
            $result = Kernel::resolve($class, false);

            if(is_object($result)){
                return $result;
            }
        } catch(Throwable){}

        if(DI::has($class)){
            try{
                $result = DI::resolve($class);

                if(is_object($result)){
                    return $result;
                }
            } catch(Throwable){}
        }

        return null;
    }

    /**
     * Resolve an instance for an intersection type.
     *
     * Attempts to resolve each type and verifies that the resulting instance
     * satisfies all types declared in the intersection. The first instance that
     * satisfies every required type is returned.
     *
     * If no matching instance can be resolved, an object default value is
     * returned when provided. Otherwise, a routing exception is thrown.
     *
     * @param string[] $types Intersection type names that the instance must satisfy.
     * @param mixed $default Default value to return when resolution fails.
     *
     * @return object|null An instance satisfying all intersection types.
     *
     * @throws ClassException If no instance satisfies all required types and
     *                         no valid object default is available.
     */
    private static function newIntersection(
        array $types,
        mixed $default,
    ): ?object 
    {
        foreach ($types as $type) {
            try {
                $instance = self::newInstance($type);
            } catch (Throwable) {
                continue;
            }

            if ($instance === null) {
                continue;
            }

            foreach ($types as $required) {
                if (!is_a($instance, $required)) {
                    continue 2;
                }
            }

            return $instance;
        }

        if ($default !== self::NO_DEFAULT_VALUE) {
            return $default;
        }

        throw new ClassException(sprintf(
            'Unable to resolve intersection type: %s.',
            implode(' & ', $types)
        ));
    }

    /**
     * Create a default closure for resolving Closure dependencies.
     *
     * The returned closure accepts any number of arguments and evaluates nested
     * closures before returning the resolved values. If no arguments are provided,
     * `null` is returned. A single resolved argument is returned directly, while
     * multiple resolved arguments are returned as an array.
     *
     * @return Closure A default closure that resolves and returns its arguments.
     */
    private static function getClosure(): Closure
    {
        return static function (mixed ...$arguments): mixed {
            if ($arguments === []) {
                return null;
            }

            $results = [];

            foreach ($arguments as $argument) {
                if ($argument instanceof Closure) {
                    $results[] = $argument();
                    continue;
                }

                $results[] = $argument;
            }

            return (count($results) > 1)
                ? $results
                : $results[0];
        };
    }

    /**
     * Resolve a usable type from a union type declaration.
     *
     * Prefers a class type when dependency injection is enabled. Otherwise,
     * selects the first supported built-in type. If no supported type is found,
     * falls back to `mixed`.
     *
     * @param ReflectionNamedType[]|ReflectionIntersectionType[] $unions Types declared in the union.
     * @param array<string,bool> $supported Supported built-in parameter types.
     * @param bool $useDi Whether to use dependency injection.
     *
     * @return array{0:string,1:bool,2:bool} A tuple containing the resolved
     *         type name, whether it allows `null`, and whether it is a built-in
     *         type.
     */
    private static function getUnionType(
        array $unions,
        array $supported = [],
        bool $useDi = false
    ): array 
    {
        foreach ($unions as $type) {
            if ($useDi && !$type->isBuiltin()) {
                return [
                    $type->getName(),
                    $type->allowsNull(),
                    false,
                ];
            }

            if (isset($supported[$type->getName()])) {
                return [
                    $type->getName(),
                    $type->allowsNull(),
                    true,
                ];
            }
        }

        return ['mixed', false, true];
    }

    /**
     * Cast a value according to a parameter type declaration.
     *
     * Handles nullable parameters, default values, and union types before
     * converting the value to the requested type.
     *
     * @param string $type Parameter type to cast the value to.
     * @param mixed $value Value to cast.
     * @param bool $isNullable Whether the parameter allows `null`.
     * @param mixed $default Default value to use when no value is provided.
     * @param bool $isUnion Whether the parameter uses a union type.
     *
     * @return mixed The casted value, default value, or `null`.
     */
    private static function typeCasting(
        string $type,
        mixed $value,
        bool $isNullable = false,
        mixed $default = self::NO_DEFAULT_VALUE,
        bool $isUnion = false
    ): mixed
    {
        $hasDefaultValue = $default !== self::NO_DEFAULT_VALUE;
        $strValue = trim((string) $value);
        $isNull = ($value === null || $strValue === '');

        if ($isNull && ($isNullable || $hasDefaultValue)) {
            return ($isNullable && !$hasDefaultValue)
                ? null
                : $default;
        }

        if($type === 'mixed'){
            return $value;
        }

        return match(true){
            $isUnion && is_int($value)    => (int) $strValue,
            $isUnion && is_float($value), is_double($value)  => (float) $strValue,
            default => self::toTypedValue(
                $type, 
                $value, 
                $hasDefaultValue ? $default : $strValue
            ) 
        };
    }

    /**
     * Convert a value to a supported parameter type.
     *
     * @param string $type Parameter type to convert the value to.
     * @param mixed $value Value to convert.
     * @param mixed $default Default value returned when the type is unsupported.
     *
     * @return mixed The converted value or the supplied default value.
     */
    private static function toTypedValue(
        string $type,
        mixed $value,
        mixed $default
    ): mixed 
    {
        return match ($type) {
            //'bool'        => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'bool'        => strtolower((string) $value) === 'true' || $value === '1',
            'float', 'double' => (float) $value,
            'int'         => (int) $value,
            'string'      => (string) $value,
            'null'        => null,
            'false'       => false,
            'true'        => true,
            default       => $default,
        };
    }

    /**
     * Get the current command controller views.
     * 
     * @return array{view:string,options:array} $views Return array of command routes parameters as URI.
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
     * @param int|null $index Index of the argument to retrieve (0-based). 
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

        Runtime::set(Runtime::CLASS_METADATA, [
            'filename'    => null,
            'uri'         => null,
            'namespace'   => null,
            'method'      => null,
            'controllers' => 0,
            'command'     => null,
            'isCache'     => false,
            'isStaticCache' => false,
        ]);
    }
}