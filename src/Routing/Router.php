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

use \App\Controllers\Config\ViewErrors;
use \Luminova\Http\Header;
use \Luminova\Command\Terminal;
use \Luminova\Routing\Bootstrap;
use \Luminova\Routing\Segments;
use \Luminova\Base\BaseApplication;
use \Luminova\Base\BaseViewController;
use \Luminova\Base\BaseConfig;
use \Luminova\Exceptions\RouterException;
use \ReflectionMethod;
use \ReflectionFunction;
use \ReflectionNamedType;
use \ReflectionException;
use \ReflectionClass;
use \Closure;

final class Router 
{
    /**
     * Route patterns and handling functions
     * 
     * @var array<string, array> $controllers
    */
    private static array $controllers = [
        'routes' => [], 'routes_after' => [], 'routes_middleware' => [], 
        'cli_routes' => [], 'cli_middleware' => [], 'errors' => []
    ];
    
    /**
     * Current base route, used for (sub)route mounting
     * 
     * @var string $routeBase
    */
    private string $routeBase = '';

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
     * All allowed HTTP request methods
     * 
     * @var string $httpStrMethods
    */
    private static $httpStrMethods = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';

    /**
     * @var Terminal $terminal 
    */
    private static ?Terminal $terminal = null;

    /**
     * @var BaseApplication $application 
    */
    private static ?BaseApplication $application = null;

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
        if(is_command()){
            RouterException::throwWith('invalid_middleware');
        }

        if ($methods === '') {
            return;
        }

        $this->authentication('routes_middleware', $methods, $pattern, $callback, true);
    }

    /**
     * Before CLI middleware, it captures the front controller request method and patterns to handle middleware authentication before executing other routes.
     * If middleware callback returns "STATUS_ERROR" the execution will be terminated 
     * resulting the following to be ignored as a result of failed authentication else if it return "STATUS_SUCCESS" the following routes will be executed.
     *
     * @param Closure|string $pattern Allowed command pattern, script name or callback function
     * @param Closure|string $callback Callback function to execute
     * @param array $options Optional options
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
    */
    public function before(Closure|string $pattern, Closure|string $callback = null, array $options = []): void
    {
        if(!is_command()){
            RouterException::throwWith('invalid_cli_middleware');
        }

        if($pattern instanceof Closure) {
            $callback = $pattern;
            $parsedPattern = 'before';
            $isController = false;
        }else{
            $build_pattern = static::parsePatternValue($pattern);
            $isController = ($build_pattern !== false);
            $parsedPattern = $isController ? $build_pattern : trim($pattern, '/');
        }
    
        static::$controllers['cli_middleware']['CLI'][] = [
            'callback' => $callback,
            'pattern' => $parsedPattern,
            'options' => $options,
            'controller' => $isController,
            'middleware' => true
        ];
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

        $pattern = $this->routeBase . '/' . trim($pattern, '/');
        $pattern = $this->routeBase ? rtrim($pattern, '/') : $pattern;
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
        $build_pattern = static::parsePatternValue($pattern);
        $isController = ($build_pattern !== false);
        $parsedPattern = $isController ? $build_pattern : trim($pattern, '/');
       
        static::$controllers['cli_routes']["CLI"][] = [
            'callback' => $callback,
            'pattern' => $parsedPattern,
            //'options' => $options,
            'controller' => $isController,
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
        $this->capture(static::$httpStrMethods, $pattern, $callback);
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
            $default = $this->routeBase;
            $this->routeBase .= $group;

            $callback($this, static::$application);

            $this->routeBase = $default;
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
     * A shorthand for route capture https methods to handle "METHOD" request method.
     *
     * @param string  $pattern A route pattern or template view name
     * @param Closure|string $callback Handle callback for router
     * 
     * @return mixed
     * @throws RouterException If method does not exist.
    */
    public function __call(string $name, mixed $arguments): mixed
    {
        $method = strtoupper($name);

        if (in_array($method, Header::$httpMethods, true)) {
            return $this->capture($method, ...$arguments);
        }

        if(method_exists($this, $name)){
            return $this->{$name}(...$arguments);
        }

        throw new RouterException("Method $name does not exist");
    }

    /**
     * Bootstrap application routing context like [web, cli, api, console etc...]
     *
     * @param BaseApplication $application Your application instance.
     * @param Bootstrap ...$callbacks Routing context callback arguments.
     * 
     * @return void
     * 
     * @see /bootstrap/Bootstrap.php
     * @see /public/index.php
    */
    public function bootstraps(BaseApplication $application, Bootstrap ...$callbacks): void 
    {
        $methods = Header::$httpMethods;
        $methods[] = 'CLI'; //Fake a request method for cli
        static::$method  = Header::getRoutingMethod();
        static::$application = $application;

        if (in_array(static::$method , $methods)) {
            $firstSegment = $this->getFirst();
            $routeInstances = Bootstrap::getInstances();
            $current = $this->routeBase;

            foreach ($callbacks as $bootstrap) {
                $name = $bootstrap->getName();

                if ($name !== '') {
                    $errorHandler = $bootstrap->getErrorHandler();
                    static::reset();

                    if($firstSegment === $name) {
                        if ($name === Bootstrap::CLI){
                            defined('CLI_ENVIRONMENT') || define('CLI_ENVIRONMENT', env('cli.environment.mood', 'testing'));

                            if(!is_command()) {
                                return;
                            }
                        }elseif($errorHandler !== null){
                            $this->setErrorListener($errorHandler);
                        }
                   
                        if (in_array($name, $routeInstances)) {  
                            $this->routeBase .= '/' . $name;
                        }
                    
                        static::boot($name, $this, $application);
                        break;
                    }elseif (!in_array($firstSegment, $routeInstances) && static::isWeContext($name, $firstSegment)) {
                        if($errorHandler !== null){
                            $this->setErrorListener($errorHandler);
                        }

                        static::boot($name, $this, $application);
                        break;
                    }
                }
            }

            $this->routeBase = $current;
        }
    }

     /**
     * Boot route context
     *
     * @param string $context bootstrap route context name
     * @param Router $router  Make router instance available in route
     * @param BaseApplication $app Make application instance available in route
     * 
     * @return void
     * @throws RouterException
    */
    private static function boot(string $context, Router $router, BaseApplication $app): void 
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
     * @param string $controller Controller class name
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
            exit(static::runAsCommand($this));
        }

        static::outputEncoding($_SERVER['HTTP_ACCEPT_ENCODING'] ?? null);
        static::runAsHttp();

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD') {
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
    private static function outputEncoding(?string $encoding = null): bool
    {
        ob_end_clean();

        if ($encoding === null || $encoding === '') {
            return ob_start();
        }

        if (strpos($encoding, 'x-gzip') !== false || strpos($encoding, 'gzip') !== false) {
            if (!ob_start('ob_gzhandler')) {
                return ob_start();
            }

            return true;
        }

        if (!ob_start(BaseConfig::getEnv('script.output.handler', null, 'nullable'))) {
            return ob_start();
        }

        return true;
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
        $result = false;
        foreach (static::$controllers['errors'] as $pattern => $callable) {
            if (static::uriCapture($pattern, static::getUriSegments(), $matches)) {
                $result = static::call($callable, [], true);
                break;
            }
        }

        if (!$result && isset(static::$controllers['errors']['/'])) {
            $result = static::call(static::$controllers['errors']['/'], [], true);
        }

        if (!$result) {
            static::printError('Error file not found', null, $status);
        }
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
            echo "<html><body><h1>{$header}</h1><p>{$message}</p></body></html>";
        }

        exit(STATUS_ERROR);
    }

    /**
     * Get list of registered controller namespaces
     *
     * @return array<int, string> Registered namespaces
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
            $uri = rawurldecode($_SERVER['REQUEST_URI']);
            $uri = substr($uri, mb_strlen(static::getBase()));

            if (false !== ($pos = strpos($uri, '?'))) {
                $uri = substr($uri, 0, $pos);
            }

            return '/' . trim($uri, '/');
        } 

        if (is_command()) {
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
     * Is bootstrap a web instance
     *
     * @param string $result bootstrap result
     * @param string $first First url segment
     * 
     * @return bool
    */
    private static function isWeContext(string $result, ?string $first = null): bool 
    {
        return ($first === null || $first === '' || Bootstrap::WEB) && $result !== Bootstrap::CLI && $result !== Bootstrap::API;
    }

    /**
     * Get terminal instance 
     * 
     * @return Terminal Return instance of Terminal class.
    */
    private static function terminal(): Terminal
    {
        if(static::$terminal === null){
            static::$terminal = new Terminal();
        }

        return static::$terminal;
    }

    /**
     * Register a middleware authentication
     *
     * @param string  $to group name
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param Closure|string $callback Callback function to execute
     * @param bool $exit is before middleware
     * 
     * @return void
    */
    private function authentication(
        string $to, 
        string $methods, 
        string $pattern, 
        Closure|string $callback, 
        bool $exit = false
    ): void
    {
        $pattern = $this->routeBase . '/' . trim($pattern, '/');
        $pattern = $this->routeBase ? rtrim($pattern, '/') : $pattern;
        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            static::$controllers[$to][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => $exit
            ];
        }
    }

    /**
     * Run the CLI router and application, Loop all defined CLI routes
     *
     * @param self $self
     * 
     * @return int
     * @throws RouterException
    */
    private static function runAsCommand(self $self): int
    {
        $command = static::getArgument(2);
        $group = static::getArgument();

        if(static::terminal()->isHelp($group)){
            static::terminal()->helper(null, true);

            return true;
        }

        if (isset(static::$controllers['cli_middleware'][static::$method])) {
            if(!static::handleCommand(static::$controllers['cli_middleware'][static::$method], $command)){
                return false;
            }
        }

        $result = false;
        if(isset(static::$groups[$group])){
            $groups = static::$groups[$group];

            foreach($groups as $register){
                $register($self, static::$application);
            }

            $routes = static::$controllers['cli_routes'][static::$method] ?? null;

            if ($routes !== null) {
                $result = static::handleCommand($routes, $command);
            }
        }

        if (!$result) {
            static::terminal()->error('Unknown command ' . static::terminal()->color("'{$command}'", 'red') . ' not found', null);
        }

        return $result ? STATUS_SUCCESS : STATUS_ERROR;
    }

    /**
     * Run the HTTP router and application: 
     * Loop all defined HTTP request method and view routes
     *
     * @return bool
     * @throws RouterException
    */
    private static function runAsHttp(): bool
    {
        $result = true;
        $uri = static::getUriSegments();

        if (isset(static::$controllers['routes_middleware'][static::$method])) {
            $result = static::handleWebsite(static::$controllers['routes_middleware'][static::$method], $uri);
        }

        if($result){
            $result = false;
            $routes = static::$controllers['routes'][static::$method] ?? null;

            if ($routes !== null) {
                $result = static::handleWebsite($routes, $uri);
                if($result && isset(static::$controllers['routes_after'][static::$method])) {
                    static::handleWebsite(static::$controllers['routes_after'][static::$method], $uri);
                }
            }

            if(!$result){
                static::triggerError();
            }
        }

        return $result;
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
        $error = false;
        foreach ($routes as $route) {
            if (static::uriCapture($route['pattern'], $uri, $matches)) {
                $error = static::call($route['callback'], static::matchesToArray($matches));

                if (!$route['middleware'] || (!$error && $route['middleware'])) {
                    break;
                }
            }
        }
       
        return $error;
    }

    /**
    * Handle C=command router CLI callback class method with the given parameters 
    * using instance callback or reflection class
    *
    * @param array $routes Command name array values
    * @param string $command Command string
    *
    * @return void $error error status [0 => true, 1 => false]
    *
    * @throws RouterException if method is not callable or doesn't exist
    */
    private static function handleCommand(array $routes, string $command): bool
    {
        $passed = false;
        $commands = static::terminal()->parseCommands($_SERVER['argv'] ?? [], true);
        $queries = static::getArguments();
        $argument = static::getArgument(2);

        foreach ($routes as $route) {
            if($route['controller']) {
                $isMatch = static::uriCapture($route['pattern'], $queries['view'], $matches);

                if($isMatch || $queries['view'] === $route['pattern']) {

                    if($isMatch){
                        $commands['params'] = static::matchesToArray($matches);
                    }

                    $passed = static::call($route['callback'], $commands);

                    if (!$route['middleware'] || (!$passed && $route['middleware'])) {
                        break;
                    }
                }
            } elseif($command === $route['pattern'] || $route['middleware'] || static::terminal()->isHelp($argument)) {
                $passed = static::call($route['callback'], $commands);

                if (!$route['middleware'] || (!$passed && $route['middleware'])) {
                    break;
                }
            }else{
                //Call framework command from controller class
                if(static::terminal()->call($command, $commands)){
                   $passed = true;
                   break;
                }
            }
        }

        return $passed;
    }

    /**
     * Extract matched parameters from request
     *
     * @param array $array Matches
     * 
     * @return array $params
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
    * Dependency injection
    *
    * @param Closure|string|array $callback Class public callback or an extracted array params method eg: UserController:update
    * @param array $arguments Method arguments to pass to callback method
    * @param bool $injection Force use dependency injection. Default is false
    *
    * @return array 
    * @internal 
    */
    private static function injection(Closure|string|array $callback, array $arguments = [], bool $injection = false): array
    {
        $injection = $injection || (bool) env('feature.route.dependency.injection', false);

        if (!$injection) {
            return $arguments;
        }

        try {
            //$isParams = (is_array($callback) && !is_callable(!$callback));
            $parameters = is_array($callback) ? $callback : (new ReflectionFunction($callback))->getParameters();
            $instances = [];

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();
               
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $class = $type->getName();

                    if(is_subclass_of($class, BaseApplication::class)) {
                        $instances[] = (static::$application ?? app());
                    }elseif(is_subclass_of($class, Router::class)) {
                        $instances[] = (static::$application?->router ?? app()->router);
                    }else{
                        $instances[] = new $class();
                    }
                } elseif (!empty($arguments)) {
                    $instances[] = array_shift($arguments);
                }
            }

            return array_merge($instances, $arguments);
        } catch (ReflectionException $e) {
            return $arguments;
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
            $isCommand = isset($arguments['command']) && is_command();
            $arguments = $isCommand ? ($arguments['params'] ?? []) : static::injection($callback, $arguments, $injection);

            return status_code(call_user_func_array($callback, $arguments), false);
        }

        if (is_array($callback)) {
            return static::reflection($callback[0], $callback[1], $arguments, $injection);
        }

        if (stripos($callback, '::') !== false) {
            [$controller, $method] = explode('::', $callback);
            $class = static::getControllerClass($controller);

            return static::reflection($class, $method, $arguments, $injection);
        }

        return false;
    }

    /**
     * Execute class using reflection method
     *
     * @param string $className Controller class name.
     * @param string $method Controller class method name.
     * @param array $arguments Optional arguments to pass to the method
     * @param bool $injection Force use dependency injection. Default is false.
     *
     * @return bool If method was called successfully
     * @throws RouterException if method is not callable or doesn't exist
    */
    private static function reflection(string $className, string $method, array $arguments = [], bool $injection = false): bool 
    {
        $throw = true;

        if ($className === '') {
            RouterException::throwWith('invalid_class', -1, null, $className);
            return false;
        }

        try {
            $class = new ReflectionClass($className);
            $isErrorClass = ($class->getName() === ViewErrors::class);

            if (!($class->isInstantiable() && (
                $class->isSubclassOf(Terminal::class) || 
                $class->isSubclassOf(BaseViewController::class) ||
                $isErrorClass ||
                $class->isSubclassOf(BaseApplication::class)))) {
                RouterException::throwWith('invalid_controller', 1, null, $className);
            }

            $caller = $class->getMethod($method);

            if ($caller->isPublic() && !$caller->isAbstract() && (!$caller->isStatic() || $isErrorClass)) {
                if (isset($arguments['command']) && is_command()) {
                    $instance = new $className();
                    if(isset($instance->group) && $instance->group === static::getArgument(1)) {
                        $arguments['classMethod'] = $method;
                        [$throw, $result] = static::invokeCommandArgs($instance, $arguments, $className, $caller);
                    }else{
                        unset($instance);
                        return false;
                    }
                } else {
                    $arguments = static::injection($caller->getParameters(), $arguments, $injection);
                    $result = $caller->invokeArgs(new $className(), $arguments);
                }

                return status_code($result, false);
            }

            RouterException::throwWith('invalid_method', 1, null, $method);
        } catch (ReflectionException $e) {
            if ($throw) {
                if($e->getCode() === 1){
                    throw $e;
                }

                RouterException::throwException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return false;
    }

    /**
     * Invoke class using reflection method
     *
     * @param object $instance Class instance
     * @param array $arguments Pass arguments to reflection method
     * @param string $className Invoking class name
     * @param ReflectionMethod $method Controller class method
     *
     * @return array<int, bool> 
    */
    private static function invokeCommandArgs(
        object $instance, 
        array $arguments, 
        string $className, 
        ReflectionMethod $method
    ): array
    {
        $commandId = '_about_' . $instance->name;
        $arguments[$commandId] = [
            'class' => $className, 
            'group' => $instance->group,
            'name' => $instance->name,
            'description' => $instance->description,
            'usages' => $instance->usages,
            'options' => $instance->options
        ];

        if(static::terminal()->isHelp($arguments['command'])){
            if($instance->help($arguments[$commandId]) === STATUS_ERROR){
                static::terminal()->helper($arguments[$commandId]);
            }

            return [false, true];
        }

        $instance->explain($arguments);

        $result = $method->invokeArgs($instance, $arguments['params']??[]);

        return [true, $result];
    }
    
    /**
    * Replace all curly braces matches {} into word patterns (like Laravel)
    * Convert pattern to a regex pattern  & Checks if there is a routing match
    *
    * @param string $pattern
    * @param string $uri
    * @param mixed &$matches
    *
    * @return bool is match true or false
    */
    private static function uriCapture(string $pattern, string $uri, mixed &$matches): bool
    {
        $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);
        $matched = preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE);

        return (bool) $matched;
    }

    /**
     * Replace command script pattern values match (:value) and replace with (pattern)
     *
     * @param string $input command script pattern
     * 
     * @return string|bool $output If match return replaced string else return false
    */
    private static function parsePatternValue(string $input): string|false
    {
        $input = trim($input, '/');

        if (strpos($input, '(:value)') !== false) {
            $pattern = '/\(:value\)/';
            $replacement = '([^/]+)';

            $output = preg_replace($pattern, $replacement, $input);

            return '/' . $output;
        }

        if (strstr($input, '/')) {
            return $input;
        }
        
        return false;
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
            $arguments = array_slice($_SERVER['argv'], 2);
            $result = static::terminal()->extract($arguments, true);

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
     * @return array<int, string> Array list of url segments
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