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

use \WeakMap;
use \Closure;
use \Throwable;
use \Exception;
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionFunction;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionException;
use \ReflectionIntersectionType;
use \Luminova\Luminova;
use \App\Application;
use \App\Config\Template;
use \Luminova\Http\Header;
use \Luminova\Command\Terminal;
use \Luminova\Command\Utils\Color;
use \Luminova\Base\BaseCommand;
use \Luminova\Core\CoreApplication;
use \Luminova\Attributes\Compiler;
use \Luminova\Cache\TemplateCache;
use \Luminova\Utils\WeakReference;
use \Luminova\Routing\{DI, Prefix, Segments};
use \Luminova\Exceptions\{AppException, RouterException};
use \Luminova\Interface\{RoutableInterface, RouterInterface, ErrorHandlerInterface};
use function \Luminova\Funcs\{root, filter_paths, is_command};

final class Router implements RouterInterface
{
    /**
     * Accept any incoming HTTP request methods.
     * 
     * @var string ANY_METHODS
     */
    public const ANY_METHODS = 'ANY';

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
     * Terminal command instance.
     * 
     * @var Terminal|null $term 
     */
    private static ?Terminal $term = null;

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
     * Application instance.
     * 
     * @var CoreApplication|null $application 
     */
    private static ?CoreApplication $application = null;

    /**
     * Weak object reference map.
     * 
     * @var WeakMap|null $weak
     */
    private static ?WeakMap $weak = null;

    /**
     * Router object reference.
     * 
     * @var WeakReference|null $reference
     */
    private static ?WeakReference $reference = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(CoreApplication $app)
    {
        self::$weak = new WeakMap();
        self::$reference = new WeakReference();
        self::$di = env('feature.route.dependency.injection', false);
        self::$application = $app;
        self::$isCommand = is_command() && self::cmd();
        self::reset(true);
        Luminova::profiling('start');
        $app = null;
    }

    /**
     * {@inheritdoc}
     */
    public function context(Prefix|array ...$contexts): self 
    {
        self::onInitializedRouting();
        self::$uri = self::getUriSegments();

        // If application is undergoing maintenance.
        if(MAINTENANCE && self::systemMaintenance()){
            return $this;
        }

        // If the view uri ends with `.extension`, then try serving the cached static version.
        if(!self::$isCommand && self::$uri !== self::CLI_URI && $this->serveStaticCache()){
            return $this;
        }

        $prefix = self::getFirst();

        // Application start event.
        self::$application->__on('onStart', [
            'cli' => self::$isCommand ,
            'method' => self::$method,
            'uri' => self::$uri,
            'module' => $prefix
        ]);

        // When using attribute for routes.
        if(env('feature.route.attributes', false)){
           return $this->createWithAttributes($prefix);
        }

        // When using default context manager.
        if($contexts === null || $contexts === []){
           RouterException::throwWith('no_context', RouterException::RUNTIME_ERROR);
        }
        
        if (isset(self::$httpMethods[self::$method])) {
           return $this->createWithMethods($prefix, $contexts);
        }
        
        RouterException::throwWith('no_route', RouterException::RUNTIME_ERROR);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', 'GET', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function post(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', 'POST', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function patch(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', 'PATCH', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', 'DELETE', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', 'PUT', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function options(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', 'OPTIONS', $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function any(string $pattern, Closure|string $callback): void
    {
        $this->addHttpRoute('routes', self::ANY_METHODS, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function middleware(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::throwWith('empty_argument', RouterException::INVALID_ARGUMENTS, [
                '$methods'
            ]);
            return;
        }

        $this->addHttpRoute('routes_middleware', $methods, $pattern, $callback, true);
    }

    /**
     * {@inheritdoc}
     */
    public function after(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::throwWith('empty_argument', RouterException::INVALID_ARGUMENTS, [
                '$methods'
            ]);
            return;
        }

        $this->addHttpRoute('routes_after', $methods, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function guard(string $group, Closure|string $callback): void
    {
        if(!self::$isCommand){
            RouterException::throwWith('invalid_cli_middleware');
        }

        $group = trim($group, '/');
        self::$weak[self::$reference]['cli_middleware']['CLI'][$group][] = [
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
            RouterException::throwWith('empty_argument', RouterException::INVALID_ARGUMENTS, [
               '$methods'
            ]);
            return;
        }

        $this->addHttpRoute('routes', $methods, $pattern, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function command(string $command, Closure|string $callback): void
    {
        self::$weak[self::$reference]['cli_commands']['CLI'][] = [
            'callback' => $callback,
            'pattern' => self::normalizePatterns(trim($command, '/'), true),
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
        self::$weak[self::$reference]['cli_groups'][$group][] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function addNamespace(string $namespace): self
    {
        if($namespace === '') {
            RouterException::throwWith('empty_argument', RouterException::INVALID_ARGUMENTS, [
                '$namespace'
            ]);

            return $this;
        }

        $namespace = '\\' . trim($namespace, '\\') . '\\';

        if(!str_starts_with($namespace, '\\App\\') || !str_ends_with($namespace, '\\Controllers\\')){
            RouterException::throwWith(
                env('feature.app.hmvc', false)
                    ? 'invalid_module_namespace'
                    : 'invalid_namespace', 
                RouterException::NOT_ALLOWED, 
                [$namespace]
            );
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

        if(self::$method === 'CLI'){
            if(self::$isCommand){
                $exitCode = self::runAsCommand();
                $context = ['commands' => self::$commands];
            }
        }else{
            $exitCode = self::runAsHttp();
        }

        self::$application->__on('onFinish', Luminova::getClassMetadata());
        Luminova::profiling('stop', $context);
        exit($exitCode);
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorListener(Closure|array|string $match, Closure|array|string|null $callback = null): void
    {
        if ($callback === null) {
            self::$weak[self::$reference]['errors']['/'] = $match;
            return;
        } 

        if(!is_string($match)){
           throw new RouterException(
                'Invalid arguments, "$match" must be a segment pattern string.', 
                RouterException::INVALID_ARGUMENTS
            );
        }

        $match = self::normalizePatterns($match);
        self::$weak[self::$reference]['errors'][$match] = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public static function triggerError(int $status = 404): void
    {
        foreach (self::$weak[self::$reference]['errors'] as $pattern => $callable) {
            $matches = [];
            if (
                self::uriCapture($pattern, self::$uri, $matches) && 
                self::call($callable, self::matchesToArgs($matches), true) === STATUS_SUCCESS
            ) {
                return;
            }
        }
      
        $error = self::$weak[self::$reference]['errors']['/'] ?? null;
        $error = $error ? self::call($error, [], true) : STATUS_ERROR;

        if ($error === STATUS_SUCCESS || $error === STATUS_SILENCE) {
            return;
        }

        self::printError(
            'Resource Not Found', 
            PRODUCTION 
                ? 'The requested resource could not be found on the server.'
                : "An error occurred:\n\n" . 
                  "- No controller is registered to handle the requested URL.\n" . 
                  "- Alternatively, a custom error handler is missing for this URL prefix in the controller.\n" . 
                  "- Additionally, check your Controller class's prefix pattern to ensure it doesn't exclude the URL.",
            $status
        );
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
     * Normalizes predefined patterns in the given input string.
     *
     * @param string $input The input string containing placeholders for patterns to be normalized.
     * @param bool $cli Optional. If true, formats the output for CLI usage by prepending a '/' and trimming leading slashes.
     * 
     * @return string The normalized string with placeholders replaced by regular expressions.
     * @ignore
     * @see https://luminova.ng/docs/3.3.0/router/placeholders
     */
    public static function normalizePatterns(string $input, bool $cli = false): string
    {
        // Replace strict placeholders like '/(:int)/(:string)/(:foo)'
        if ($input !== '/' && str_contains($input, '(:')) {
            $placeholders = self::getStrictPlaceholders();
            $input = str_replace(
                array_keys($placeholders), 
                array_values($placeholders), 
                $input
            );
        }

        // Replace regular placeholders like '/{name}/{id}/{foo}'
        $input = ($input !== '/') 
            ? preg_replace('/\/{(.*?)}/', '/(.*?)', $input) 
            : $input;
        return $cli ? '/' . ltrim($input, '/') : $input;
    }

    /**
     * Load the route URI context prefix.
     * Allow accessing router and application instance within the context file as global variable.
     *
     * @param string $context Route URI context prefix name.
     * @param RouterInterface<\T> $router Make router instance available in context file.
     * @param CoreApplication $app Make application instance available in context file.
     * 
     * @return void
     * @throws RouterException
     */
    private static function onContext(string $context, RouterInterface $router, CoreApplication $app): void 
    {
        $_ROUTE_CONTEXT_PATH = APP_ROOT . 'routes' . DIRECTORY_SEPARATOR . $context . '.php';

        if (file_exists($_ROUTE_CONTEXT_PATH)) {
            require_once $_ROUTE_CONTEXT_PATH;
            $app->__on('onContextInstalled', $context);
            $app = $router = null;
            return;
        }

        $app = $router = null;
        self::printError(
            '500 Internal Server Error', 
            RouterException::withMessage('invalid_context', $context), 
            500
        );
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

        $annotation = str_contains($callback, '::') ? '::' : (str_contains($callback, '@') ? '@' : null);

        if ($annotation === null) {
            return $callback ? [$callback, '__invoke'] : [null, null];
        }

        [$class, $method] = explode($annotation, $callback, 2);

        return [self::getClassNamespace($class), $method];
    }
    
    /**
     * If the controller already contains a namespace, use it directly.
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
     * Initialize routing system to handle incoming requests.
     * 
     * Register the request method, considering method overrides and set proper output handler.
     * 
     * @return void
     */
    private static function onInitializedRouting(): void
    {
        if(self::$isCommand){
            self::$method = 'CLI';
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if($method === 'HEAD'){
            Header::clearOutputBuffers();
            ob_start();

            self::$method = 'GET';
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
     * @param string $header Header Title of error message.
     * @param string|null $message Optional message body to display.
     * @param int $status http status code.
     * 
     * @return void
     */
    private static function printError(string $header, ?string $message = null, int $status = 404): void 
    {
        if(self::$isCommand){
            self::$term?->error(sprintf('(%s) [%s] %s', $status, $header, $message));
            exit(STATUS_ERROR);
        }
        
        Header::headerNoCache($status);
        echo sprintf(
            '<html><title>%s</title><body><h1>%s</h1><p>%s</p></body></html>',
            $header,
            $header,
            str_replace("\n", '<br/>', $message ?? $header)
        );
        
        exit(STATUS_ERROR);
    }

    /**
     * Register a http route.
     *
     * @param string  $to The routing group name to add this route.
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol.
     * @param string  $pattern The route URL pattern or template view name (e.g, `/`, `/home`, `{segment}`, `(:type)`, `/user/([0-9])`).
     * @param Closure|string $callback Callback function to execute.
     * @param bool $terminate Terminate if it before middleware.
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
     */
    private function addHttpRoute(
        string $to, 
        string $methods, 
        string $pattern, 
        Closure|string $callback, 
        bool $terminate = false
    ): void
    {
        if(self::$isCommand){
            RouterException::throwWith('invalid_middleware');
        }

        $pattern = $this->baseGroup . '/' . trim($pattern, '/');
        $pattern = self::normalizePatterns(($this->baseGroup !== '') ? rtrim($pattern, '/') : $pattern);

        foreach (explode('|', $methods) as $method) {
            self::$weak[self::$reference][$to][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => $terminate
            ];
        }
    }

    /**
     * Get first segment of current view uri.
     * 
     * @return string Return the first URI segment.
     */
    private static function getFirst(): string
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
     * Install the appropriate context.
     * 
     * @param string $name The context prefix name.
     * @param Closure|array|null $eHandler Context error handler.
     * @param string $first The request URI first segment.
     * @param array<string,string> $prefixes List of context prefix names without web context as web is default.
     * 
     * @return bool|int<2> Return bool if context match was found or 2 if in cli but not in cli mode, otherwise false.
     */
    private function installContext(
        string $name, 
        Closure|array|null $eHandler, 
        string $first, 
        array $prefixes
    ): bool|int
    {
        if($first === $name) {
            if ($name === Prefix::CLI){
                if(!self::$isCommand) {
                    return 2;
                }
                
                defined('CLI_ENVIRONMENT') || define('CLI_ENVIRONMENT', env('cli.environment.mood', 'testing'));
                return true;
            }
            
            if($eHandler !== null){
                $this->setErrorListener($eHandler);
            }
        
            if (isset($prefixes[$name])) {  
                $this->baseGroup .= '/' . $name;
            }

            return true;
        }
        
        if(!isset($prefixes[$first]) && self::isWeContext($name, $first)) {
            if($eHandler !== null){
                $this->setErrorListener($eHandler);
            }

            return true;
        }
        
        return false;
    }

    /**
     * Is context a web instance.
     *
     * @param string $result The context name.
     * @param string $first The first uri segment.
     * 
     * @return bool Return true if the context is a web instance, otherwise false.
     */
    private static function isWeContext(string $result, string $first): bool 
    {
        return (
            $first === '' || 
            $result === Prefix::WEB
        ) && $result !== Prefix::CLI && $result !== Prefix::API;
    }

    /**
     * Get terminal instance.
     * 
     * @param bool $return Whether to return the terminal instance.
     * 
     * @return Terminal|true Return instance of terminal class or true if initialized.
     */
    private static function cmd(bool $return = false): Terminal|bool
    {
        if(!self::$term instanceof Terminal){
            self::$term = new Terminal();
        }

        return $return ? self::$term : true;
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
            self::$term->header();
            return STATUS_SUCCESS;
        }

        if(self::$term->isHelp($group)){
            self::$term->header();
            self::$term->helper(null, true);
            return STATUS_SUCCESS;
        }

        $global = (self::$weak[self::$reference]['cli_middleware'][self::$method]['global']??null);

        if($global !== null && !self::handleCommand($global)){
            return STATUS_ERROR;
        }
        
        $groups = (self::$weak[self::$reference]['cli_groups'][$group] ?? null);
        
        if($groups !== null){
            foreach($groups as $callback){
                $callback = isset($callback['callback']) 
                    ? static fn(Router $router) => $router->command(
                        $callback['pattern'], 
                        $callback['callback']
                     )
                    : $callback;

                $callback(...self::noneParamInjection($callback));
            }

            $middleware = (self::$weak[self::$reference]['cli_middleware'][self::$method][$group] ?? null);

            if($middleware !== null && !self::handleCommand($middleware)){
                return STATUS_ERROR;
            }
            
            $routes = self::$weak[self::$reference]['cli_commands'][self::$method] ?? null;

            if ($routes !== null && self::handleCommand($routes)) {
                self::$application->__on('onCommandPresent', self::getArguments());
                return STATUS_SUCCESS;
            }
        }

        $command = Color::style("'{$group} {$command}'", 'red');
        self::$term->fwrite('Unknown command ' . $command . ' not found', Terminal::STD_ERR);

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
        $middleware = self::getRoutes('routes_middleware'); 
        if (
            $middleware !== [] && 
            self::handleWebsite($middleware, self::$uri) !== STATUS_SUCCESS
        ) {
            return STATUS_ERROR;
        }

        $routes = self::getRoutes('routes');
        $status = ($routes !== []) ? self::handleWebsite($routes, self::$uri) : STATUS_ERROR;

        if ($status === STATUS_SILENCE) {
            return STATUS_ERROR;
        }

        if ($status === STATUS_SUCCESS) {
            $after = self::getRoutes('routes_after');
            
            if($after !== []){
                self::handleWebsite($after, self::$uri);
            }

            self::$application->__on('onViewPresent', self::$uri);

            return STATUS_SUCCESS;
        }

        self::triggerError();
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
    private static function getRoutes(string $from): array 
    {
        $anyRoutes = self::$weak[self::$reference][$from][self::ANY_METHODS] ?? null;

        return ($anyRoutes !== null) 
            ? array_merge(self::$weak[self::$reference][$from][self::$method] ?? [], $anyRoutes)
            : self::$weak[self::$reference][$from][self::$method] ?? [];
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
            self::$term->error($err);
            return true;
        }

        Header::headerNoCache(503, null, env('app.maintenance.retry', '3600'));
        if(file_exists($path = self::$application->getSystemError('maintenance'))){
            include_once $path;
        }

        echo $err;
        return true;
    }

    /**
     * Serve static cached pages.
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
            self::$weak[self::$application] = new TemplateCache(
                0, root(rtrim((new Template())->cacheFolder, TRIM_DS) . '/default/')
            );

            // If expiration return mismatched int code 404 ignore and do not try to replace to actual url.
            $expired = self::$weak[self::$application]->setKey(Luminova::getCacheId())
                ->setUri(self::$uri)
                ->expired($matches[1]);

            if ($expired === true) {
                // Remove the matched file extension to render the request normally
                self::$uri = substr(self::$uri, 0, -strlen($matches[0]));
            }elseif($expired === false && self::$weak[self::$application]->read() === true){
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
                ? self::call($route['callback'], self::matchesToArgs($matches), false, false, $route['middleware'])
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
        self::$commands = self::$term->parseCommands($_SERVER['argv'] ?? [], true);
        $queries = self::getArguments();
        $isHelp = self::$term->isHelp(self::getArgument(2));
        
        foreach ($routes as $route) {
            if($route['middleware']){
                return self::call($route['callback'], self::$commands, false, true) === STATUS_SUCCESS;
            }

            $matches = [];

            if (self::uriCapture($route['pattern'], $queries['view'], $matches)) {
                self::$commands['params'] = self::matchesToArgs($matches);

                return self::call($route['callback'], self::$commands) === STATUS_SUCCESS;
            } 
            
            if ($queries['view'] === $route['pattern'] || $isHelp) {
                return self::call($route['callback'], self::$commands) === STATUS_SUCCESS;
            }
        }

        return false;
    }

    /**
     * Extract matched parameters from request.
     *
     * @param array<int,array> $array Matched url parameters.
     * 
     * @return string[] Return matched parameters.
     */
    private static function matchesToArgs(array $array): array
    {
        $params = [];

        foreach ($array as $match) {
            $params[] = (isset($match[0][0]) && $match[0][1] !== -1) 
                ? trim($match[0][0], '/')
                : '';
        }

        return array_slice($params, 1);
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
            $caller = (($caller instanceof ReflectionMethod) ? $caller : new ReflectionFunction($caller));

            if ($caller->getNumberOfParameters() === 0 && ($found = count($arguments)) > 0) {
                RouterException::throwWith('bad_method', RouterException::BAD_METHOD_CALL, [
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
            if(!self::isMethodReturnInt($callback)){
                throw new RouterException(
                    sprintf(
                        'Invalid closure controller "%s" return type, routable closures must return status int: STATUS_*',
                        "function(...)"
                    ),
                    RouterException::INVALID_CONTROLLER
                );
            }

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

        return self::reflection(
            $namespace, 
            $method, 
            $arguments, 
            $injection,
            $isCliMiddleware,
            $isHttpMiddleware
        );
    }

    /**
     * Execute class using reflection method.
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
    private static function reflection(
        string $namespace, 
        string $method, 
        array $arguments = [], 
        bool $injection = false,
        bool $isCliMiddleware = false,
        bool $isHttpMiddleware = false
    ): int 
    {
        if ($namespace === '') {
            RouterException::throwWith('invalid_class', RouterException::CLASS_NOT_FOUND, [
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
                RouterException::throwWith('invalid_controller', RouterException::INVALID_CONTROLLER, [
                    $namespace
                ]);
                
                return STATUS_ERROR;
            }

            $caller = $class->getMethod($method);

            if(!self::isMethodReturnInt($caller)){
                throw new RouterException(sprintf(
                    'Invalid controller method "%s" return type, routable methods must return any of the status int: %s.',
                    "{$namespace}->{$method}(...)",
                    'STATUS_SUCCESS, STATUS_ERROR or STATUS_SILENCE'
                ),
                RouterException::INVALID_CONTROLLER);
            }
            
            if (
                $caller->isPublic() && 
                !$caller->isAbstract() && 
                (
                    !$caller->isStatic() || 
                    ($caller->isStatic() && $class->implementsInterface(ErrorHandlerInterface::class)))
                ) {
                if (isset($arguments['command']) && self::$isCommand) {
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

                    self::$term->error(sprintf(
                        'Command group "%s" does not match the expected controller group "%s".',
                        self::getArgument(1),
                        $controllerGroup
                    ));

                    return STATUS_SUCCESS;
                } 

                if($isHttpMiddleware){
                    $instance = $class->newInstance();
                    $result = $caller->invokeArgs($instance, self::injection($caller, $arguments, $injection));

                    if($result !== STATUS_SUCCESS){
                        $failed = $class->getMethod('onMiddlewareFailure');
                        $failed->setAccessible(true);
                        $failed->invokeArgs($instance, [self::$uri, Luminova::getClassMetadata()]);
                    }

                    return $result;
                }

                return $caller->invokeArgs(
                    $caller->isStatic() ? null: $class->newInstance(), 
                    self::injection($caller, $arguments, $injection)
                );
            }
        } catch (ReflectionException|Throwable $e) {
            if($e instanceof AppException){
                $e->handle();
                return STATUS_ERROR;
            }

            RouterException::throwException($e->getMessage(), $e->getCode(), $e);
            return STATUS_ERROR;
        }

        RouterException::throwWith('invalid_method', RouterException::INVALID_METHOD, [$method]);
        return STATUS_ERROR;
    }

    /**
     * Checks if a method's return type is an integer.
     *
     * This function is used to ensure that routable methods (controller methods and closures)
     * return a status code (STATUS_* constants) as required by the router.
     *
     * @param ReflectionMethod|Closure $method The method to check.
     *
     * @return bool Return true if the method's return type is an integer, false otherwise.
     */
    private static function isMethodReturnInt(ReflectionMethod|Closure $method): bool 
    {
        try {
            $returnType = ($method instanceof ReflectionMethod) 
                ? $method->getReturnType() 
                : (new ReflectionFunction($method))->getReturnType();

            return $returnType && $returnType->getName() === 'int';
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Initialize and render application routing with PHP attributes.
     * 
     * @param string $prefix The application url first prefix.
     * 
     * @return self Return router instance.
     */
    private function createWithAttributes(string $prefix): self 
    {
        $hmvc = env('feature.app.hmvc', false);
        $attr = new Compiler($this->baseGroup, self::$isCommand, $hmvc);
        $path = $hmvc ? 'app/Modules/' : 'app/Controllers/';

        if(self::$isCommand){
            $attr->forCli($path, self::getArgument(1));
        }else{
            $attr->forHttp($path, $prefix, self::$uri);
        }

        $current = $this->baseGroup;
        self::$weak[self::$reference] = array_merge(
            self::$weak[self::$reference], 
            $attr->getRoutes()
        );
        
        $this->baseGroup = $current;
        return $this;
    }

    /**
     * Initialize and render application routing with router methods.
     * 
     * @param string $prefix The application url first prefix.
     * @param Prefix[]|array<int,array<string,mixed>> $contexts The application prefix contexts.
     * 
     * @return self Return router instance.
     */
    private function createWithMethods(string $prefix, array $contexts): self  
    {
        $current = $this->baseGroup;
        $fromArray = !($contexts[0] instanceof Prefix);
        $prefixes = $fromArray ? $this->getArrayPrefixes($contexts) : Prefix::getPrefixes();

        foreach ($contexts as $context) {
            $name = $fromArray 
                ? ($context['prefix'] ?? '') 
                : $context->getName();

            if($name === ''){
                continue;
            }
            
            $eHandler = $fromArray 
                ? ($context['error'] ?? null) 
                : $context->getErrorHandler();

            self::reset();
            $result = $this->installContext($name, $eHandler, $prefix, $prefixes);

            if($result === 2){
                return $this;
            }

            if($result === true){
                self::onContext($name, $this, self::$application);
                break;
            }
        }

        $this->baseGroup = $current;
        return $this;
    }

    /**
     * Invoke class using reflection method.
     *
     * @param BaseCommand $instance Command controller object.
     * @param array $arguments Pass arguments to reflection method.
     * @param string $className Invoking class name.
     * @param ReflectionMethod $caller Controller class method.
     * @param bool $isMiddleware Indicate Whether caller is cli middleware (default: false).
     *
     * @return int Return result from command controller method.
     */
    private static function invokeCommandArgs(
        BaseCommand $instance,
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
        if(!$isMiddleware && self::$term->isHelp($arguments['command'])){
            
            self::$term->header();

            if($instance->help($arguments[$id]) === STATUS_ERROR){
                // Fallback to default help information if dev does not implement help.
                self::$term->helper($arguments[$id]);
            }

            return STATUS_SUCCESS;
        }

        if($instance->users !== []){
            $user = self::$term->whoami();

            if(!in_array($user, $instance->users, true)){
                self::$term->error("User '{$user}' is not allowed to run this command.");
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
     * Replace all curly braces matches {} into word patterns (like Laravel)
     * Convert pattern to a regex pattern  & Checks if there is a routing match.
     *
     * @param string $pattern Url current route pattern.
     * @param string $uri The current request uri path.
     * @param array &$matches URI matches passed by reference.
     *
     * @return bool Return true if is match, otherwise false.
     */
    private static function uriCapture(string $pattern, string $uri, array &$matches): bool
    {
        $result = (bool) preg_match_all("#^{$pattern}$#", $uri, $matches, PREG_OFFSET_CAPTURE);
        return (!$result || preg_last_error() !== PREG_NO_ERROR) 
            ? false 
            : $result;
    }

    /**
     * Resolve URI placeholder patterns.
     * 
     * @return array<string,string> $placeholders Return URI patterns.
     */
    private static function getStrictPlaceholders(): array 
    {
        return [
            '(:mixed)'        => '([^/]+)',
            '(:any)'          => '(.*)',
            '(:root)'         => '?.*', //?(.*)
            '(:optional)'     => '?([^/]*)?',
            '(:alphanumeric)' => '([a-zA-Z0-9-]+)',
            '(:int)'          => '(\d+)',
            '(:integer)'      => '(\d+)',
            '(:number)'       => '([0-9-.]+)',
            '(:double)'       => '([+-]?\d+(\.\d*)?)',
            '(:float)'        => '([+-]?\d+\.\d+)',
            '(:string)'       => '([a-zA-Z0-9\W_-]+)',
            '(:alphabet)'     => '([a-zA-Z]+)',
            '(:path)'         => '((.+)/([^/]+)+)'
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
            Application::class, CoreApplication::class => self::$application,
            RouterInterface::class, Router::class => self::$application->router,
            Terminal::class => self::cmd(true),
            Segments::class => new Segments(self::$isCommand ? [self::CLI_URI] : Luminova::getSegments()),
            Closure::class => fn(mixed ...$arguments): mixed => null,
            default => class_exists($class) ? new $class() : DI::newInstance($class)
        };

        if($instance === null && !$nullable){
            throw new RouterException(
                sprintf('Class: %s does not exist.', $class),
                RouterException::CLASS_NOT_FOUND
            );
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

        return [
            'builtin' => 'string',
            'nullable' => false
        ];
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
            'bool' => ($lower === '1' || 'true' === $lower) ? true : (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'double' => (double) $value,
            'string' => (string) $value,
            'array' => (array) [$value],
            'object' => (object) [$value],
            'callable' => fn(mixed ...$arguments):mixed => $value,
            'null' => null,
            'false' => false,
            'true' => true,
            default => $value
        };
    }

    /**
     * Get the current command controller views.
     * 
     * @return array<string,mixed> $views Return array of command routes parameters as URI.
     */
    private static function getArguments(): array
    {
        $views = [
            'view' => '',
            'options' => [],
        ];

        if (!isset($_SERVER['argv'][2])) {
            return $views;
        }

        $result = self::$term->extract(
            array_slice($_SERVER['argv'], 2), 
            true
        );

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
        self::$weak[self::$reference] = [
            'routes' =>             [], 
            'routes_after' =>       [], 
            'routes_middleware' =>  [], 
            'cli_commands' =>       [], 
            'cli_middleware' =>     [],
            'cli_groups' =>         [], 
            'errors' =>             []
        ];

        if(!$init){
            return;
        }

        Luminova::setClassMetadata([
            'filename'    => null,
            'uri'         => null,
            'namespace'   => null,
            'method'      => null,
            'attrFiles'   => 1,
            'cache'       => false,
            'staticCache' => false,
        ]);
    }
}