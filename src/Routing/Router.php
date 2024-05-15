<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Routing;

use \Luminova\Http\Header;
use \Luminova\Command\Terminal;
use \Luminova\Routing\Context;
use \Luminova\Routing\Segments;
use \Luminova\Base\BaseApplication;
use \App\Controllers\Application;
use \Luminova\Base\BaseViewController;
use \Luminova\Application\Factory;
use \Luminova\Exceptions\RouterException;
use \Luminova\Interface\ErrorHandlerInterface;
use \ReflectionMethod;
use \ReflectionFunction;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionException;
use \ReflectionClass;
use \Closure;


/**
 * Router shorthand methods for capture, to handle http methods by it name.
 *
 * @method static void get(string $pattern, Closure|string $callback) Handle "GET" requests.
 * @method static void post(string $pattern, Closure|string $callback) Handle "POST" requests.
 * @method static void patch(string $pattern, Closure|string $callback) Handle "PATCH" requests.
 * @method static void delete(string $pattern, Closure|string $callback) Handle "DELETE" requests.
 * @method static void put(string $pattern, Closure|string $callback) Handle "PUT" requests.
 * @method static void options(string $pattern, Closure|string $callback) Handle "OPTIONS" requests.
*/
final class Router 
{
    /**
     * Route patterns and handling functions
     * 
     * @var array<string, array> $controllers
    */
    private static array $controllers = [
        'routes' =>             [], 
        'routes_after' =>       [], 
        'routes_middleware' =>  [], 
        'cli_routes' =>         [], 
        'cli_middleware' =>     [], 
        'errors' =>             []
    ];

    /**
     * All allowed HTTP request methods
     * 
     * @var array<string,string> $httpMethods
    */
    private static $httpMethods = [
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
     * Current route base group, used for (sub)route mounting
     * 
     * @var string $baseGroup
    */
    private string $baseGroup = '';

    /**
     * HTTP Request Method 
     * 
     * @var string $method
    */
    private static string $method = '';

    /**
     * Server base path for router
     * 
     * @var ?string $base
    */
    private static string|null $base = null;

    /**
     * Application registered controllers namespace
     * 
     * @var array $namespace
    */
    private static array $namespace = [];

    /**
     * Command router groups
     * 
     * @var array $groups
    */
    private static array $groups = [];

    /**
     * @var Terminal $term 
    */
    private static ?Terminal $term = null;

    /**
     * @var bool $isCli 
    */
    private static bool $isCli = false;

    /**
     * @var BaseApplication $application 
    */
    private static ?BaseApplication $application = null;

    /**
     * Initialize router class.
     * 
     * @param BaseApplication $application Your application instance.
    */
    public function __construct(BaseApplication $application)
    {
        static::$application = $application;
    }

    /**
     * A shorthand for route capture https methods to handle "METHOD" request method.
     *
     * @param string $name Method to call.
     * @param array $arguments Method arguments.
     * 
     * Expected arguments
     *  - string $pattern A route pattern or template view name.
     *  - Closure|string $callback Handle callback for router.
     * 
     * @return mixed Return value of method.
     * @throws RouterException If method does not exist.
    */
    public function __call(string $name, array $arguments): mixed
    {
        $method = strtoupper($name);

        if ($method !== 'CLI' && isset(static::$httpMethods[$method])) {
            return $this->capture($method, ...$arguments);
        }

        throw new RouterException("Call to undefined or inaccessible method " . static::class . "::" . $name);
    }

     /**
     * Initialize application routing with proper context [web, cli, api, console etc...]
     * 
     * @param Context ...$contexts Arguments containing routing context
     * 
     * @return self Return router instance.
    */
    public function context(Context ...$contexts): self 
    {
        static::$isCli = is_command();
        static::$method  = static::getRoutingMethod();

        if (isset(static::$httpMethods[static::$method])) {
            $firstSegment = $this->getFirst();
            $instances = Context::getInstances();
            $current = $this->baseGroup;

            foreach ($contexts as $context) {
                $name = $context->getName();

                if ($name !== '') {
                    static::reset();

                    if($firstSegment === $name) {
                        if ($name === Context::CLI){
                            defined('CLI_ENVIRONMENT') || define('CLI_ENVIRONMENT', env('cli.environment.mood', 'testing'));

                            if(!static::$isCli) {
                                return $this;
                            }
                        }elseif(($eHandler = $context->getErrorHandler()) !== null){
                            $this->setErrorListener($eHandler);
                        }
                   
                        if (isset($instances[$name])) {  
                            $this->baseGroup .= '/' . $name;
                        }
                    
                        static::bootContext($name, $this, static::$application);
                        break;
                    }elseif(!isset($instances[$firstSegment]) && static::isWeContext($name, $firstSegment)) {
                        if(($eHandler = $context->getErrorHandler()) !== null){
                            $this->setErrorListener($eHandler);
                        }

                        static::bootContext($name, $this, static::$application);
                        break;
                    }
                }
            }

            $this->baseGroup = $current;
        }

        return $this;
    }

    /**
     * Before HTTP middleware, it captures the front controller request method and patterns to handle middleware authentication before executing other routes.
     * If middleware callback returns "STATUS_ERROR" the execution will be terminated 
     * resulting the following to be ignored as a result of failed authentication else if it return "STATUS_SUCCESS" the following routes will be executed.
     *
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param Closure|string $callback Callback function to execute
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
    */
    public function middleware(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            return;
        }

        $this->authentication('routes_middleware', $methods, $pattern, $callback, true);
    }

    /**
     * After middleware route, executes the callback function after request was executed successfully.
     *
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param Closure|string $callback Callback function to execute
     * 
     * @return void
    */
    public function after(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            return;
        }

        $this->authentication('routes_after', $methods, $pattern, $callback);
    }

    /**
     * Before CLI middleware, it captures the front controller request method and patterns to handle middleware authentication before executing other routes.
     * If middleware callback returns "STATUS_ERROR" the execution will be terminated 
     * resulting the following to be ignored as a result of failed authentication else if it return "STATUS_SUCCESS" the following routes will be executed.
     *
     * @param string $group Command middleware group name or `any` for global middleware.
     * @param Closure|string $callback Callback controller handler.
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
    */
    public function before(string $group, Closure|string $callback = null): void
    {
        if(!static::$isCli){
            RouterException::throwWith('invalid_cli_middleware');
        }

        $group = trim($group, '/');
        static::$controllers['cli_middleware']['CLI'][$group][] = [
            'callback' => $callback,
            'pattern' => $group,
            'middleware' => true
        ];
    }

    /**
     * Capture front controller request method based on pattern and execute the callback.
     *
     * @param string $methods Allowed methods, can be separated with | pipe symbol
     * @param string $pattern A route pattern or template view name
     * @param Closure|string $callback Callback function to execute
     * 
     * @return void
    */
    public function capture(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            return;
        }

        $pattern = $this->baseGroup . '/' . trim($pattern, '/');
        $pattern = $this->baseGroup ? rtrim($pattern, '/') : $pattern;
        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            static::$controllers['routes'][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => false
            ];
        }
    }

    /**
     * Capture front controller command request names and execute callback
     *
     * @param string $pattern Allowed command pattern or script name.
     * @param Closure|string $callback Callback function to execute.
     * 
     * @return void
    */
    public function command(string $pattern, Closure|string $callback): void
    {
        static::$controllers['cli_routes']["CLI"][] = [
            'callback' => $callback,
            'pattern' => static::parsePatternValue(trim($pattern, '/')),
            'middleware' => false
        ];
    }

    /**
     * A shorthand for route capture method to handle any type of request method.
     *
     * @param string $pattern A route pattern or template view name
     * @param Closure|string $callback Handle callback for router
     * 
     * @return void
    */
    public function any(string $pattern, Closure|string $callback): void
    {
        $this->capture('GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD', $pattern, $callback);
    }

    /**
     * Binds a collection of routes segment in a single base route.
     *
     * @param string $group The binding group pattern.
     * @param Closure $callback Callback group function to handle binds
     * 
     * @return void
     * @throws RouterException If invalid callback is provided
    */
    public function bind(string $group, Closure $callback): void
    {
        if ($callback instanceof Closure) {
            $current = $this->baseGroup;
            $this->baseGroup .= $group;

            $callback(...static::noneParamInjection($callback));

            $this->baseGroup = $current;
            return;
        }
        
        RouterException::throwWith('invalid_argument', 0, null, '$callback', 'callable', gettype($callback));
    }

    /**
     * Binds a command route group.
     *
     * @param string $group The binding group name.
     * @param Closure $callback Callback command function to handle group
     * 
     * @return void
     * @throws RouterException If invalid callback is provided
    */
    public function group(string $group, Closure $callback): void
    {
        if ($callback instanceof Closure) {
            static::$groups[$group][] = $callback;
            return;
        }

        RouterException::throwWith('invalid_argument', 0, null, '$callback', 'callable', gettype($callback));
    }

    /**
     * Boot route context.
     *
     * @param string $context Route context name
     * @param Router $router  Make router instance available in route
     * @param BaseApplication $app Make application instance available in route
     * 
     * @return void
     * @throws RouterException
    */
    private static function bootContext(string $context, Router $router, BaseApplication $app): void 
    {
        if (is_file(APP_ROOT . "/routes/{$context}.php")) {
            require_once APP_ROOT . "/routes/{$context}.php";
            return;
        } 

        static::printError(
            '500 Internal Server Error', 
            RouterException::withMessage('invalid_context', $context), 
            500
        );
    }

    /**
     * Register a controller class namespace to use across the application routing
     *
     * @param string $namespace Class namespace string
     * 
     * @return void
     * @throws RouterException If namespace string is empty or contains invalid namespace characters.
    */
    public function addNamespace(string $namespace): void
    {
        if($namespace === '') {
            RouterException::throwWith('empty_argument', 0, null, '$namespace');

            return;
        }

        $namespace = '\\' . ltrim($namespace, '\\') . '\\';

        if(strpos($namespace, '\App\Controllers\\') !== 0) {
            RouterException::throwWith('invalid_namespace');

            return;
        }

        static::$namespace[] = $namespace;
    }

    /**
     * If the controller already contains a namespace, use it directly
     * If not, loop through registered namespaces to find the correct class
     * 
     * @param string $controller Controller class base name.
     * 
     * @return string $className
    */
    private static function getControllerClass(string $controller): string
    {
        if (class_exists($controller)) {
            return $controller;
        }

        foreach (static::$namespace as $namespace) {
            if(class_exists($namespace . $controller)) {
                return $namespace . $controller;
            }
        }

        return '';
    }

    /**
     * Run application routes, loop all defined routing methods to call controller 
     * if method matches view  or command name.
     * 
     * @return void
     * @throws RouterException Throw if encountered error while executing controller callback
    */
    public function run(): void
    {
        if(static::$method === 'CLI'){
            static::terminal();
            exit(static::runAsCommand());
        }

        static::runAsHttp();

        if (static::$method === 'HEAD') {
            ob_end_clean();
        }

        exit(0);
    }

    /**
     * Enable encoding of response.
     * 
     * @param string|null $encoding
     * 
     * @return bool
     */
    private static function outputEncoding(?string $encoding = null): void
    {
        if ($encoding === null || $encoding === '') {
            ob_start();
            return;
        }

        if (strpos($encoding, 'x-gzip') !== false || strpos($encoding, 'gzip') !== false) {
            ob_start('ob_gzhandler');
            return;
        }

        $handler = env('script.output.handler', null);
        ob_start($handler === '' ? null : $handler);
    }

    /**
     * Get the request method for routing, considering overrides.
     *
     * @return string The request method for routing.
     * @internal
     */
    public static function getRoutingMethod(): string
    {
        $method = ($_SERVER['REQUEST_METHOD'] ?? null);

        if($method === null && static::$isCli){
            return 'CLI';
        }
  
        if($method === 'HEAD'){
            ob_start();
            return 'GET';
        }

        static::outputEncoding($_SERVER['HTTP_ACCEPT_ENCODING'] ?? null);

        if($method === 'POST'){
            $headers = Header::getHeaders();
            $overriders = ['PUT' => true,'DELETE' => true, 'PATCH' => true];
            if (isset($headers['X-HTTP-Method-Override']) && isset($overriders[$headers['X-HTTP-Method-Override']])) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }
        
        return strtoupper($method ?? '');
    }

    /**
     * Set an error listener callback function.
     *
     * @param Closure|string|array<int,string> $match Matching route pattern
     * @param Closure|array<int,string>|null $callback Optional error callback handler function
     *  
     * @return void
    */
    public function setErrorListener(Closure|string|array $match, Closure|array|null $callback = null): void
    {
        if ($callback === null) {
            static::$controllers['errors']['/'] = $match;
        } else {
            static::$controllers['errors'][$match] = $callback;
        }
    }

    /**
     * Cause triggers an error response
     *
     * @param int $status HTTP response status code (default: 404)
     * 
     * @return void
    */
    public static function triggerError(int $status = 404): void
    {
        foreach (static::$controllers['errors'] as $pattern => $callable) {
            if (static::uriCapture($pattern, static::getUriSegments(), $matches)) {
                if (static::call($callable, $matches, true)) {
                    return;
                }
            }
        }

        if (($error = static::$controllers['errors']['/'] ?? null) !== null && static::call($error, [], true)) {
            return;
        }

        static::printError('Error file not found', null, $status);
    }

    /**
     * Show error message with proper header and status code 
     * 
     * @param string $header Header Title of error message
     * @param string|null Optional message body to display 
     * @param int $status http status code
     * 
     * @return void
     * 
    */
    private static function printError(string $header, ?string $message = null, int $status = 404): void 
    {
        Header::headerNoCache($status);
        if($message){
            echo "<html><title>{$header}</title><body><h1>{$header}</h1><p>{$message}</p></body></html>";
        }

        exit(STATUS_ERROR);
    }

    /**
     * Get list of registered controller namespaces
     *
     * @return array<int,string> Registered namespaces
     * @internal
    */
    public static function getNamespaces(): array
    {
        return static::$namespace;
    }

    /**
     * Return server base path.
     *
     * @return string Application router base path
    */
    public static function getBase(): string
    {
        if (static::$base === null) {
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $script = $_SERVER['SCRIPT_NAME'];

                if (($last = strrpos($script, '/')) !== false && $last > 0) {
                    static::$base = substr($script, 0, $last) . '/';
                    return static::$base;
                }
            }

            static::$base = '/';
        }

        return static::$base;
    }

    /**
     * Get the current segment relative URI.
     * 
     * @return string Relative paths
    */
    public static function getUriSegments(): string
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), mb_strlen(static::getBase()));

            if (false !== ($pos = strpos($uri, '?'))) {
                $uri = substr($uri, 0, $pos);
            }

            return '/' . trim($uri, '/');
        } 

        if (static::$isCli) {
            return '/cli';
        }

        return '/';
    }

    /**
     * Get segment class instance 
     * 
     * @return Segments Segments instance
    */
    public function getSegment(): Segments 
    {
        return new Segments($this->getSegments());
    }

    /**
     * Is context a web instance
     *
     * @param string $result context name.
     * @param string $first First url segment.
     * 
     * @return bool
    */
    private static function isWeContext(string $result, ?string $first = null): bool 
    {
        return ($first === null || $first === '' || Context::WEB) && $result !== Context::CLI && $result !== Context::API;
    }

    /**
     * Get terminal instance 
     * 
     * @return Terminal Return instance of Terminal class.
    */
    private static function terminal(): Terminal
    {
        if(static::$term === null){
            static::$term = new Terminal();
        }

        return static::$term;
    }

    /**
     * Register a middleware authentication
     *
     * @param string  $to group name
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param Closure|string $callback Callback function to execute
     * @param bool $terminate Terminate if it before middleware.
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
    */
    private function authentication(
        string $to, 
        string $methods, 
        string $pattern, 
        Closure|string $callback, 
        bool $terminate = false
    ): void
    {
        if(static::$isCli){
            RouterException::throwWith('invalid_middleware');
        }

        $pattern = $this->baseGroup . '/' . trim($pattern, '/');
        $pattern = $this->baseGroup ? rtrim($pattern, '/') : $pattern;
        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            static::$controllers[$to][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => $terminate
            ];
        }
    }

    /**
     * Run the CLI router and application, Loop all defined CLI routes
     * 
     * @return int Return status success or failure.
     * @throws RouterException
    */
    private static function runAsCommand(): int
    {
        $group = static::getArgument();

        if(static::$term->isHelp($group)){
            static::$term->helper(null, true);
            return STATUS_SUCCESS;
        }

        $command = static::getArgument(2);

        if (($global = static::$controllers['cli_middleware'][static::$method]['any']??null) !== null) {
            if(!static::handleCommand($global, $command)){
                return STATUS_ERROR;
            }
        }

        if(($groups = static::$groups[$group] ?? null) !== null){
            foreach($groups as $groupCallback){
                $groupCallback(...static::noneParamInjection($groupCallback));
            }

            if (($middleware = static::$controllers['cli_middleware'][static::$method][$group] ?? null) !== null) {
                if(!static::handleCommand($middleware, $command, $group)){
                    return STATUS_ERROR;
                }
            }
    
            $routes = static::$controllers['cli_routes'][static::$method] ?? null;
            if ($routes !== null && static::handleCommand($routes, $command, $group)) {
                return STATUS_SUCCESS;
            }
        }

        static::$term->print('Unknown command ' . static::$term->color("'{$group} {$command}'", 'red') . ' not found', null);
        return STATUS_ERROR;
    }

    /**
     * Run the HTTP router and application: 
     * Loop all defined HTTP request method and view routes
     *
     * @return bool Return true on success, false on failure.
     * @throws RouterException
    */
    private static function runAsHttp(): bool
    {
        $uri = static::getUriSegments();

        if (($middleware = static::$controllers['routes_middleware'][static::$method] ?? null) !== null) {
            if(!static::handleWebsite($middleware, $uri)){
                return false;
            }
        }

        if (($routes = static::$controllers['routes'][static::$method] ?? null) !== null) {
            if(static::handleWebsite($routes, $uri)) {
                if(($after = static::$controllers['routes_after'][static::$method]) !== null){
                    static::handleWebsite($after, $uri);
                }
                return true;
            }
        }

        static::triggerError();
        return false;
    }
    
    /**
     * Handle a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes  Collection of route patterns and their handling functions
     * @param string $uri  View URI
     *
     * @return bool $error error status [0 => true, 1 => false]
     * @throws RouterException if method is not callable or doesn't exist
    */
    private static function handleWebsite(array $routes, string $uri): bool
    {
        $passed = false;
        foreach ($routes as $route) {
            if (static::uriCapture($route['pattern'], $uri, $matches)) {
                $passed = static::call($route['callback'], static::matchesToArray((array) $matches));

                if (!$route['middleware'] || (!$passed && $route['middleware'])) {
                    return $passed;
                }
            }
        }
       
        return $passed;
    }

    /**
    * Handle C=command router CLI callback class method with the given parameters 
    * using instance callback or reflection class
    *
    * @param array $routes Command name array values.
    *
    * @return bool Return true on success or false on failure.
    * @throws RouterException if method is not callable or doesn't exist
    */
    private static function handleCommand(array $routes): bool
    {
        $commands = static::$term->parseCommands($_SERVER['argv'] ?? [], true);
        $queries = static::getArguments();
        $isHelp = static::$term->isHelp(static::getArgument(2));
        
        foreach ($routes as $route) {
            if($route['middleware']){
                return static::call($route['callback'], $commands);
            }
            
            if(static::uriCapture($route['pattern'], $queries['view'], $matches)) {
                $commands['params'] = static::matchesToArray((array) $matches);
                return static::call($route['callback'], $commands);
            } elseif($queries['view'] === $route['pattern'] || $isHelp) {
                return static::call($route['callback'], $commands);
            }
        }

        return false;
    }

    /**
     * Extract matched parameters from request
     *
     * @param array<int,array> $array Matched url parameters
     * 
     * @return array<int,string> Return matched parameters.
    */
    private static function matchesToArray(array $array): array
    {
        $params = [];

        foreach ($array as $match) {
            if (isset($match[0][0]) && $match[0][1] !== -1) {
                $params[] = trim($match[0][0], '/');
            } else {
                $params[] = null;
            }
        }
        
        return array_slice($params, 1);
    }

    /**
    * Dependency injection and parameter casting.
    *
    * @param Closure|callable-string $caller Class method or callback closure.
    * @param array<int,string> $arguments Method arguments to pass to callback method.
    * @param bool $injection Force use of dependency injection.
    *
    * @return array<int,mixed> Return method params and arguments-value pairs.
    * @internal 
    */
    private static function injection(Closure|ReflectionMethod|string $caller, array $arguments = [], bool $injection = false): array
    {
        if (!($injection || (bool) env('feature.route.dependency.injection', false))) {
            return $arguments;
        }

        try {
            $parameters = [];
            $caller = (($caller instanceof ReflectionMethod) ? $caller : new ReflectionFunction($caller));

            if ($caller->getNumberOfParameters() === 0 && ($found = count($arguments)) > 0) {
                $method = ($caller->isClosure() ? $caller->getName() : $caller->getDeclaringClass()->getName() . '->' . $caller->getName());
                throw new RouterException(sprintf(
                    "Method '%s()' does not accept any arguments, but %d were provided in router patterns called in %s, line: %d",
                    $method,
                    $found,
                    filter_paths($caller->getFileName()),
                    $caller->getStartLine()
                ), E_COMPILE_ERROR);
            }
            
            foreach ($caller->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType) {
                    if($type->isBuiltin()) {
                        if(!empty($arguments)) {
                            $parameters[] = static::typeCasting($type->getName(),  array_shift($arguments));
                        }
                    }else{
                        $parameters[] = static::newInstance($type->getName());
                    }
                } elseif($type instanceof ReflectionUnionType) {
                    $types = static::getUnionTypes($type->getTypes());

                    if((isset($types['builtin']))){
                        if(!empty($arguments)) {
                            $parameters[] = static::typeCasting($types['builtin'], array_shift($arguments));
                        }
                    }else{
                        $parameters[] = static::newInstance($types['inject']);
                    }
                }
            }

            return array_merge($parameters, $arguments);
        } catch (ReflectionException $e) {
            return $arguments;
        }
    }

    /**
     * Dependency injection for closures that doesn't expect url parameters.
     * 
     * @param Closure $closure A closure to inject.
     * 
     * @return array<int,mixed> An array of parameters.
    */
    public static function noneParamInjection(Closure $callback): array 
    {
        $params = (new ReflectionFunction($callback))->getParameters();
        $classNames = [];
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $classNames[] = static::newInstance($type->getName());
            }
        }
        return $classNames;
    }

    /**
     * Create a new instance of a class.
     *
     * @param string $class The class name.
     * @return object|null The new instance of the class, or null if the class is not found.
     * 
     * @throws Exception Throws if the class does not exist or requires arguments to initialize.
     */
    private static function newInstance(string $class): ?object 
    {
        if ($class === Application::class || $class === BaseApplication::class) {
            return static::$application ?? null;
        }

        if ($class === self::class) {
            return static::$application?->router ?? null;
        }

        if ($class === Factory::class) {
            return factory();
        }

        if('Closure' === $class){
            return fn(mixed ...$arguments):mixed => null;
        }

        return new $class();
    }

    /**
     * Get union types as array or string.
     *
     * @param ReflectionNamedType[]|ReflectionIntersectionType[] $unions The union types.
     * 
     * @return array The union types.
     */
    private static function getUnionTypes(array $unions): array
    {
        $types = [];
        foreach ($unions as $type) {
            if (!$type->isBuiltin()) {
                return ['inject' => $type->getName()];
            } elseif ($type->allowsNull()) {
                return ['builtin' => 'null'];
            } else{
                $types[$type->getName()] = $type->getName();
            }
        }

        return [
            'builtin' => $types['string'] ?? 'mixed'
        ];
    }

    /**
     * Cast a value to a specific type.
     *
     * @param string $type The type to cast to.
     * @param mixed $value The value to cast.
     * @return mixed The casted value.
     */
    private static function typeCasting(string $type, mixed $value): mixed 
    {
        switch ($type) {
            case 'bool':
                return (bool) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'double':
                return (double) $value;
            case 'null':
                return null;
            case 'false':
                return false;
            case 'true':
                return true;
            case 'string':
                return (string) $value;
            case 'array':
                return (array) $value;
            case 'object':
                return (object) $value;
            case 'callable':
                return fn(mixed ...$arguments):mixed => $value;
            case 'mixed':
            default:
                return $value;
        }
    }

    /**
    * Execute router HTTP callback class method with the given parameters using instance callback or reflection class
    *
    * @param Closure|string|array<int,string> $callback Class public callback method eg: UserController:update
    * @param array $arguments Method arguments to pass to callback method.
    * @param bool $injection Force use dependency injection. Default is false.
    *
    * @return bool 
    * @throws RouterException if method is not callable or doesn't exist
    */
    private static function call(Closure|string|array $callback, array $arguments = [], bool $injection = false): bool
    {
        if ($callback instanceof Closure) {
            $arguments = ((isset($arguments['command']) && static::$isCli) ?  ($arguments['params'] ?? []) : $arguments);
            return status_code(call_user_func_array(
                $callback, 
                static::injection($callback, $arguments, $injection)
            ), false);
        }

        if (is_array($callback)) {
            // It probably static implementation of error handler.
            return static::reflection($callback[0], $callback[1], $arguments, $injection);
        }

        if (stripos($callback, '::') !== false) {
            [$controller, $method] = explode('::', $callback);

            return static::reflection(
                static::getControllerClass($controller), 
                $method, 
                $arguments, 
                $injection
            );
        }

        return false;
    }

    /**
     * Execute class using reflection method
     *
     * @param class-string $className Controller class name.
     * @param string $method Controller class method name.
     * @param array $arguments Optional arguments to pass to the method
     * @param bool $injection Force use dependency injection. Default is false.
     *
     * @return bool If method was called successfully
     * @throws RouterException if method is not callable or doesn't exist
    */
    private static function reflection(string $className, string $method, array $arguments = [], bool $injection = false): bool 
    {
        if ($className === '') {
            RouterException::throwWith('invalid_class', -1, null, $className);
            return false;
        }

        try {
            $class = new ReflectionClass($className);
 
            if (!($class->isInstantiable() && (
                $class->isSubclassOf(Terminal::class) || 
                $class->isSubclassOf(BaseViewController::class) ||
                $class->isSubclassOf(BaseApplication::class)))) {
                RouterException::throwWith('invalid_controller', 1, null, $className);
                return false;
            }

            $caller = $class->getMethod($method);

            if ($caller->isPublic() && !$caller->isAbstract() && (!$caller->isStatic() || $class->isSubclassOf(ErrorHandlerInterface::class))) {
                $result = false;
                if (isset($arguments['command']) && static::$isCli) {;
                    $class = new $className();
                    if(isset($class->group) && $class->group === static::getArgument(1)) {
                        $arguments['classMethod'] = $method;
                        $result = static::invokeCommandArgs($class, $arguments, $className, $caller);
                    }
                } else {
                    $result = $caller->invokeArgs(new $className(), static::injection($caller, $arguments, $injection));
                }

                $class = null;
                $caller = null;

                return status_code($result, false);
            }

            RouterException::throwWith('invalid_method', 1, null, $method);
        } catch (ReflectionException $e) {
            if($e->getCode() === 1){
                throw new RouterException($e->getMessage(), 1, $e);
            }

            RouterException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Invoke class using reflection method
     *
     * @param object $instance Class instance
     * @param array $arguments Pass arguments to reflection method
     * @param string $className Invoking class name
     * @param ReflectionMethod $caller Controller class method
     *
     * @return int Return result from command controller method.
    */
    private static function invokeCommandArgs(
        object $instance, 
        array $arguments, 
        string $className, 
        ReflectionMethod $caller
    ): int
    {
        $id = '_about_' . $instance->name;
        $arguments[$id] = [
            'class' => $className, 
            'group' => $instance->group,
            'name' => $instance->name,
            'description' => $instance->description,
            'usages' => $instance->usages,
            'options' => $instance->options
        ];

        if(static::$term->isHelp($arguments['command'])){
            if($instance->help($arguments[$id]) === STATUS_ERROR){
                static::$term->helper($arguments[$id]);
            }

            return STATUS_SUCCESS;
        }

        $instance->explain($arguments);
        return $caller->invokeArgs($instance, static::injection($caller, $arguments['params']??[]));
    }
    
    /**
    * Replace all curly braces matches {} into word patterns (like Laravel)
    * Convert pattern to a regex pattern  & Checks if there is a routing match
    *
    * @param string $pattern Url router pattern.
    * @param string $uri Request uri.
    * @param mixed &$matches Url matches.
    *
    * @return bool is match true or false
    */
    private static function uriCapture(string $pattern, string $uri, mixed &$matches): bool
    {
        return (bool) preg_match_all('#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#', $uri, $matches, PREG_OFFSET_CAPTURE);
    }

    /**
     * Replace command script pattern values match (:value) and replace with (pattern)
     *
     * @param string $input command script pattern
     * 
     * @return string $output If match return replaced string.
    */
    private static function parsePatternValue(string $input): string
    {
        $patterns = [
            '/\(:value\)/' => '([^/]+)',
            '/\(:optional\)/' => '?([^/]*)',
            '/\(:int\)/' => '(\d+)',
            '/\(:float\)/' => '([+-]?\d+\.\d+)',
            '/\(:string\)/' => '([a-zA-Z0-9_-]+)',
            '/\(:alphabet\)/' => '([a-zA-Z]+)',
            '/\(:path\)/' => '"((.+)\/([^\/]+)+)"',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $input = preg_replace($pattern, $replacement, $input);
        }

        if (strpos($input, '/') !== 0) {
            $input = '/' . $input;
        }

        return $input;
    }

    /**
     * Gets request command name
     *
     * @return string
    */
    private static function getArgument(int $index = 1): string 
    {
        if(isset($_SERVER['argv'])){
            return $_SERVER['argv'][$index] ?? '';
        }

        return '';
    }

    /**
     * Get the current command controller views
     * 
     * @return array $views
    */
    public static function getArguments(): array
    {
        $views = [
            'view' => '',
            'options' => [],
        ];
       
        if (isset($_SERVER['argv'][2])) {
            $result = static::$term->extract(array_slice($_SERVER['argv'], 2), true);
            $views['view'] = '/' . implode('/', $result['arguments']);
            $views['options'] = $result['options'];
        }

        return $views;
    }
    
    /**
     * Reset register routes to avoid conflicts
     * 
     * @return void
    */
    private static function reset(): void
    {
        static::$controllers['routes'] = [];
        static::$controllers['routes_after'] = [];
        static::$controllers['routes_middleware'] = [];
        static::$controllers['cli_routes'] = [];
        static::$controllers['cli_middleware'] = [];
        static::$controllers['errors'] = [];
        static::$groups = [];
    }

    /**
     * Get the current view segments as array.
     * 
     * @return array<int,string> Array list of url segments
    */
    private function getSegments(): array
    {
        $segments = explode('/', trim(static::getUriSegments(), '/'));
        $public = array_search('public', $segments);

        if ($public !== false) {
            array_splice($segments, $public, 1);
        }

        return $segments;
    }

    /**
     * Get first segment of current view uri.
     * 
     * @return string First url segment
    */
    private function getFirst(): string
    {
        $segments = $this->getSegments();

        return reset($segments);
    }
}