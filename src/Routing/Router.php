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
use \Luminova\Exceptions\ErrorException;
use \Luminova\Routing\Bootstrap;
use \ReflectionMethod;
use \ReflectionException;
use \ReflectionClass;
use \Luminova\Command\Terminal;
use \Luminova\Base\BaseCommand;
use \Luminova\Controllers\ViewController;
use \Luminova\Base\BaseApplication;

final class Router 
{
    /**
     * Route patterns and handling functions
     * 
     * @var array<string, array<string, mixed>> $controllers
     *      - @var array<string, mixed> $controllers['routes']
     *      - @var array<string, mixed> $controllers['routes_after]
     *      - @var array<string, mixed> $controllers['routes_middleware']
     *      - @var array<string, mixed> $controllers['cli_routes']
     *      - @var array<string, mixed> $controllers['cli_middleware']
     *      - @var array<string, mixed> $controllers['errors']
    */
    private array $controllers = [
        'routes' => [], 'routes_after' => [], 'routes_middleware' => [], 
        'routes_always' => [], 'cli_routes' => [], 'cli_middleware' => [], 'errors' => []
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
    private string $method = '';

    /**
     * CLI request command name
     * 
     * @var string $commandName 
    */
    private string $commandName = '';

    /**
     * Server base path for router
     * 
     * @var ?string $base
    */
    private string|null $base = null;

    /**
     * Application registered controllers namespace
     * 
     * @var array $namespace
    */
    private static array $namespace = [];

    /**
     * All allowed HTTP request methods
     * 
     * @var string ALL_METHODS
    */
    private const ALL_METHODS = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';

    /**
     * Before middleware route, executes the callback function before other routing will be executed
     *
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function before(string $methods, string $pattern, callable|string $callback): void
    {
        if ($methods === '') {
            return;
        }

        $this->addMiddleWare('routes_middleware', $methods, $pattern, $callback, true);
    }

    /**
     * After middleware route, executes the callback function after request was executed successfully.
     *
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function after(string $methods, string $pattern, callable|string $callback): void
    {
        if ($methods === '') {
            return;
        }

        $this->addMiddleWare('routes_after', $methods, $pattern, $callback);
    }

    /**
     * Always middleware route, executes the callback function no matter if request was successful or not
     *
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function always(string $methods, string $pattern, callable|string $callback): void
    {
        if ($methods === '') {
            return;
        }
        
        $this->addMiddleWare('routes_always', $methods, $pattern, $callback);
    }

    /**
     * Capture front controller request method and pattern and execute callback
     *
     * @param string  $methods Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function capture(string $methods, string $pattern, callable|string $callback): void
    {
        if ($methods === '') {
            return;
        }

        $pattern = $this->routeBase . '/' . trim($pattern, '/');
        $pattern = $this->routeBase ? rtrim($pattern, '/') : $pattern;
        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            $this->controllers['routes'][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => false
            ];
        }
    }

    /**
     * Capture front controller command middleware security and execute callback
     *
     * @param callable|string $pattern Allowed command pattern, script name or callback function
     * @param callable|string $callback Callback function to execute
     * @param array $options Optional options
     * 
     * @return void
    */
    public function authenticate(callable|string $pattern, callable|string $callback = null, array $options = []): void
    {
        if(is_callable($pattern)){
            $callback = $pattern;
            $parsedPattern = 'before';
            $isController = false;
        }else{
            $build_pattern = static::parsePatternValue($pattern);
            $isController = ($build_pattern !== false);
            $parsedPattern = $isController ? $build_pattern : trim($pattern, '/');
        }
    
        $this->controllers['cli_middleware']["CLI"][] = [
            'callback' => $callback,
            'pattern' => $parsedPattern,
            'options' => $options,
            'controller' => $isController,
            'middleware' => true
        ];
    }

    /**
     * Capture front controller command request names and execute callback
     *
     * @param string $pattern Allowed command pattern or script name
     * @param callable|string $callback Callback function to execute
     * @param array $options Optional options
     * 
     * @return void
    */
    public function command(string $pattern, callable|string $callback, ?array $options = []): void
    {
        $build_pattern = static::parsePatternValue($pattern);
        $isController = ($build_pattern !== false);
        $parsedPattern = $isController ? $build_pattern : trim($pattern, '/');
       
        $this->controllers['cli_routes']["CLI"][] = [
            'callback' => $callback,
            'pattern' => $parsedPattern,
            'options' => $options,
            'controller' => $isController,
            'middleware' => false
        ];
    }

    /**
     * Capture any method
     *
     * @param string $pattern A route pattern or template view name
     * @param callable|string $callback Handle callback for router
     * 
     * @return void
    */
    public function any(string $pattern, callable|string $callback): void
    {
        $this->capture(self::ALL_METHODS, $pattern, $callback);
    }

    /**
     * Shorthand for a route accessed using GET.
     *
     * @param string pattern A route pattern or template view name
     * @param callable|string $callback  Handle callback for router
     * 
     * @return void
    */
    public function get(string $pattern, callable|string $callback): void
    {
        $this->capture('GET', $pattern, $callback);
    }

    /**
     * Post shorthand for a route capture
     *
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function post(string $pattern, callable|string $callback): void
    {
        $this->capture('POST', $pattern, $callback);
    }

    /**
     * Patch shorthand for a route capture
     *
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Handle callback for router
     * 
     * @return void
    */
    public function patch(string $pattern, callable|string $callback): void
    {
        $this->capture('PATCH', $pattern, $callback);
    }

    /**
     * Delete shorthand for a route capture
     *
     * @param string $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function delete(string $pattern, callable|string $callback): void
    {
        $this->capture('DELETE', $pattern, $callback);
    }

    /**
     * Put shorthand for a route capture
     *
     * @param string $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function put(string $pattern, callable|string $callback): void
    {
        $this->capture('PUT', $pattern, $callback);
    }

    /**
     * Options shorthand for a route capture
     *
     * @param string $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * 
     * @return void
    */
    public function options(string $pattern, callable|string $callback): void
    {
        $this->capture('OPTIONS', $pattern, $callback);
    }

    /**
     * Binds a collection of routes in a single base route.
     *
     * @param string  $group The route group pattern to bind the callbacks on
     * @param callable $callback Callback function to execute
     * 
     * @return void
    */
    public function bind(string $group, callable $callback): void
    {
        if(is_callable($callback)){
            $default = $this->routeBase;
            $this->routeBase .= $group;

            $callback();

            $this->routeBase = $default;
            return;
        }
        
        ErrorException::throwException('Invalid argument $callback: requires callable function, ' . gettype($callback) . ', is given instead.');
    }

    /**
     * Bootstrap a group 
     *
     * @param BaseApplication $application application instance
     * @param Bootstrap $callbacks callable arguments
     * 
     * @return void
    */
    public function bootstraps(BaseApplication $application, Bootstrap ...$callbacks): void 
    {
        $methods = explode('|', self::ALL_METHODS);
        $methods[] = 'CLI'; //Fake a request method for cli
        $method = Header::getRoutingMethod();

        if (in_array($method, $methods)) {
            $firstSegment = $this->getFirstView();
            $routeInstances = Bootstrap::getInstances();
            $current = $this->routeBase;

            foreach ($callbacks as $bootstrap) {
                $name = $bootstrap->getName();
                if ($name !== '') {
                    $errorHandler = $bootstrap->getErrorHandler();
                    $withError = ($errorHandler !== null && is_callable($errorHandler));
                    $this->reset();

                    if($firstSegment === $name) {
                        if ($name === Bootstrap::CLI){
                            /**
                             * @var string CLI_ENVIRONMENT application cli development state
                            */
                            defined('CLI_ENVIRONMENT') || define('CLI_ENVIRONMENT', env('cli.environment.mood', 'testing'));

                            /**
                             * @var string STDOUT if it's not already defined
                            */
                            defined('STDOUT') || define('STDOUT', 'php://output');

                            /**
                             * @var string STDIN if it's not already defined
                            */
                            defined('STDIN') || define('STDIN', 'php://stdin');

                            /**
                             * @var string STDERR if it's not already defined
                            */
                            defined('STDERR') || define('STDERR', 'php://stderr');

                            if(!Terminal::isCommandLine()) {
                                return;
                            }
                        }elseif($withError){
                            $this->setErrorHandler($errorHandler);
                        }
                   
                        if (in_array($name, $routeInstances)) {  
                            $this->routeBase .= '/' . $name;
                        }
                    
                        static::discover($name, $this, $application);
                        break;
                    }elseif (!in_array($firstSegment, $routeInstances) && self::isWebInstance($name, $firstSegment)) {
                        if($withError){
                            $this->setErrorHandler($errorHandler);
                        }

                        static::discover($name, $this, $application);
                        break;
                    }
                }
            }
            $this->routeBase = $current;
        }
    }

    /**
     * Register a class namespace to use across the application
     *
     * @param string $namespace Class namespace
     * 
     * @return void
     * @throws ErrorException
    */
    public function addNamespace(string $namespace): void
    {
        if ($namespace === '') {
            ErrorException::throwException('Invalid argument $namespace: cannot be empty string.');
            return;
        }

        static::$namespace[] = $namespace;
    }

    /**
     * Run the router and application: 
     * Loop all defined CLI and HTTP before middleware's, after routes and command routes
     * Execute callback function if method matches view  or command name.
     *
     * @param ?callable $callback Optional final callback function to execute after run
     * 
     * @return void
    */
    public function run(?callable $callback = null): void
    {
        $this->method = Header::getRoutingMethod();
        $status = ($this->method === 'CLI' ? $this->runAsCli() : $this->runAsHttp());

        if ($status && $callback && is_callable($callback)) {
            $callback();
        }

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }

        exit($status === true ? STATUS_OK : STATUS_ERROR);
    }

    /**
     * Set the error handling function.
     *
     * @param callable $match_callback Matching callback function to be executed
     * @param callable $callback The function to be executed
     * 
     * @return void
    */
    public function setErrorHandler(mixed $match_callback, mixed $callback = null): void
    {
        if ($callback === null) {
            $this->controllers['errors']['/'] = $match_callback;
        } else {
            $this->controllers['errors'][$match_callback] = $callback;
        }
    }
    
    /**
     * Triggers error response
     *
     * @param string $match A route pattern or template view name
     * @param string $status Status code
     * 
     * @return void
    */
    public function triggerError(?array $match = null, int $status = 404): void
    {
        $status = false;

        if (count($this->controllers['errors']) > 0)
        {
            foreach ($this->controllers['errors'] as $route_pattern => $route_callable) {
                $isMatch = static::capturePattern($route_pattern, $this->getView(), $matches);
                if ($isMatch) {
                    //$params = self::extractFoundMatches($matches);
                    static::execute($route_callable);
                    $status = true;
                }
            }
        }

        if(!$status){
            if(isset($this->controllers['errors']['/'])){
                static::execute($this->controllers['errors']['/']);
            }elseif(isset($_SERVER['SERVER_PROTOCOL'])) {
                header($_SERVER['SERVER_PROTOCOL'] . ' Error file not found', true, $status);
                //header($_SERVER['SERVER_PROTOCOL'] . ' ' . lang('Router.notFound'));
            }
        }
    }

    /**
     * Get list of registered namespace
     *
     * @return array List of registered namespaces
    */
    public static function getNamespaces(): array
    {
        return static::$namespace;
    }

    /**
     * Return server base Path, and define it if isn't defined.
     *
     * @return string
    */
    public function getBase(): string
    {
        if ($this->base === null) {
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $this->base = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
            } else {
                $this->base = '/';
            }
        }

        return $this->base;
    }

    /**
     * Get the current view relative URI.
     * 
     * @return string
    */
    public function getView(): string
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = rawurldecode($_SERVER['REQUEST_URI']);
            $baseLength = mb_strlen($this->getBase());
            $uri = substr($uri, $baseLength);

            if (false !== ($pos = strpos($uri, '?'))) {
                $uri = substr($uri, 0, $pos);
            }

            return '/' . trim($uri, '/');
        } 

        if (Terminal::isCommandLine()) {
            return '/cli';
        }

        return '/';
    }

    /**
     * Get the current view array of segment.
     * 
     * @return array
    */
    public function getViews(): array
    {
        $baseView = trim($this->getView(), '/');
        $segments = explode('/', $baseView);
        $public = array_search('public', $segments);

        if ($public !== false) {
            array_splice($segments, $public, 1);
        }

        return $segments;
    }

    /**
     * Get the current view segment by position index.
     * 
     * @param int $index position index
     * 
     * @return string view segment
    */
    public function getViewPosition(int $index = 0): string
    {
        $segments = $this->getViews();

        return $segments[$index] ?? '';
    }

    /**
     * Get the current view first segment.
     * 
     * @return string
    */
    public function getFirstView(): string
    {
        $segments = $this->getViews();

        return reset($segments);
    }
    
    /**
     * Get the current view last segment.
     * 
     * @return string
    */
    public function getLastView(): string 
    {
        $segments = $this->getViews();

        return end($segments);
    }

    /**
     * Get the current view segment before last segment.
     * 
     * @return string
    */
    public function getSecondToLastView(): string 
    {
        $segments = $this->getViews();
        if (count($segments) > 1) {
            $secondToLastSegment = $segments[count($segments) - 2];

            return $secondToLastSegment;
        }

        return '';
    }
    
    /**
     * Set application router base path
     * 
     * @param string $base
     * 
     * @return void
    */
    public function setBasePath(string $base): void
    {
        $this->base = $base;
    }

    /**
     * Discover route
     *
     * @param string $name bootstrap route name
     * @param Router $router
     * @param BaseApplication $app
     * 
     * @return bool
    */
    private static function discover(string $name, Router $router, BaseApplication $app): void 
    {
        /**
         * @var Router $router Make router instance available in route
         * @var BaseApplication $app Make application instance available in route
        */
        require_once dirname(__DIR__, 2) . "/routes/{$name}.php";
    }

    /**
     * Is bootstrap a web instance
     *
     * @param string $result bootstrap result
     * @param string $first First url segment
     * 
     * @return bool
    */
    private static function isWebInstance(string $result, ?string $first = null): bool 
    {
        return ($first === null || $first === '' || Bootstrap::WEB) && $result !== Bootstrap::CLI && $result !== Bootstrap::API;
    }

    /**
     * Run the CLI router and application: 
     * Loop all defined CLI routes
     *
     * @return bool
    */
    private function runAsCli(): bool
    {
        if (isset($this->controllers['cli_middleware'][$this->method])) {
            if(!$this->handleCommand($this->controllers['cli_middleware'][$this->method])){
                return false;
            }
        }

        $result = false;
        $routes = $this->controllers['cli_routes'][$this->method] ?? null;

        if ($routes !== null) {
            $this->commandName = static::getCommandName();
            $result = $this->handleCommand($routes);
        }

        if (!$result) {
            Terminal::error('Unknown command ' . Terminal::color("'{$this->commandName}'", 'red') . ' not found', null);
        }

        return $result;
    }

     /**
     * Register a middleware
     *
     * @param string  $to group name
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param callable|string $callback Callback function to execute
     * @param bool $exit is before middleware
     * 
     * @return void
    */
    private function addMiddleWare(string $to, string $methods, string $pattern, callable|string $callback, bool $exit = false): void
    {
        $pattern = $this->routeBase . '/' . trim($pattern, '/');
        $pattern = $this->routeBase ? rtrim($pattern, '/') : $pattern;
        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            $this->controllers[$to][$method][] = [
                'pattern' => $pattern,
                'callback' => $callback,
                'middleware' => $exit
            ];
        }
    }

    /**
     * Run the HTTP router and application: 
     * Loop all defined HTTP request method and view routes
     *
     * @return bool
    */
    private function runAsHttp(): bool
    {
        $result = true;

        if (isset($this->controllers['routes_middleware'][$this->method])) {
            $result = $this->handleWebsite($this->controllers['routes_middleware'][$this->method]);
        }

        if($result){
            $result = false;
            $routes = $this->controllers['routes'][$this->method] ?? null;
            
            if ($routes === null) {
                $this->triggerError();
            }else {
                $result = $this->handleWebsite($routes);
                if($result){
                    if (isset($this->controllers['routes_after'][$this->method])) {
                        $this->handleWebsite($this->controllers['routes_after'][$this->method]);
                    }
                }else{
                    $this->triggerError($routes);
                }
            }
        }
        
        if (isset($this->controllers['routes_always'][$this->method])) {
            $this->handleWebsite($this->controllers['routes_always'][$this->method]);
        }

        return $result;
    }
    
    /**
     * Handle a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes  Collection of route patterns and their handling functions
     *
     * @return bool $error error status [0 => true, 1 => false]
     * @throws ErrorException if method is not callable or doesn't exist
    */
    private function handleWebsite(array $routes): bool
    {
        $error = false;
        $uri = $this->getView();

        foreach ($routes as $route) {
            $isMatch = static::capturePattern($route['pattern'], $uri, $matches);

            if ($isMatch) {
                $error = static::execute($route['callback'], self::extractFoundMatches($matches));

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
    * @param array $routes Command name array values
    * @return void $error error status [0 => true, 1 => false]
    *
    * @throws ErrorException if method is not callable or doesn't exist
    */
    private function handleCommand(array $routes): bool
    {
        $error = false;
        $commands = Terminal::parseCommands($_SERVER['argv'] ?? []);

        foreach ($routes as $route) {
            if ($route['controller']) {
                $queries = Terminal::getRequestCommands();
                $controllerView = trim($queries['view'], '/');
                $isMatch = static::capturePattern($route['pattern'], $queries['view'], $matches);

                if ($isMatch || $controllerView === $route['pattern']) {

                    $parameter = $isMatch ? self::extractFoundMatches($matches) : [$commands];
                    $error = static::execute($route['callback'], $parameter);

                    if (!$route['middleware'] || (!$error && $route['middleware'])) {
                        break;
                    }
                }
            } elseif ($this->commandName === $route['pattern'] || $route['middleware']) {
                $error = static::execute($route['callback'], [$commands]);

                if (!$route['middleware'] || (!$error && $route['middleware'])) {
                    break;
                }
            }else {
                if(Terminal::hasCommand($this->commandName, $commands)){
                   $error = true;
                   break;
                }
            }
        }

        return $error;
    }

    /**
     * Extract matched parameters from request
     *
     * @param array $array Matches
     * 
     * @return array $params
    */
    private static function extractFoundMatches(array $array): array
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
    /*private static function extractFoundMatchesXX(array $array): array
    {
        $params = [];
        foreach ($array as $index => $match) {
            if (isset($match[0]) && is_array($match[0]) && isset($match[0][0]) && $match[0][1] != -1) {
                $param = trim($match[0][0], '/');
                if (isset($array[$index + 1]) && isset($array[$index + 1][0]) && is_array($array[$index + 1][0])) {
                    if ($array[$index + 1][0][1] > -1) {
                        $nextIndex = $array[$index + 1][0][1];
                        $param = substr($param, 0, $nextIndex - $match[0][1]);
                    }
                }
                $params[] = $param;
            } else {
                $params[] = null;
            }
        }

        return $params;
    }*/

    /*private static function extractFoundMatchesX(array $array): array
    {
        $array = array_slice($array, 1);
        $params = array_map(function ($match, $index) use ($array) {
            if (isset($array[$index + 1]) && isset($array[$index + 1][0]) && is_array($array[$index + 1][0])) {
                if ($array[$index + 1][0][1] > -1) {
                    return trim(substr($match[0][0], 0, $array[$index + 1][0][1] - $match[0][1]), '/');
                }
            } 
            return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
        }, $array, array_keys($array));

        return $params;
    }*/

    /**
    * Execute router HTTP callback class method with the given parameters using instance callback or reflection class
    *
    * @param callable|string $callback Class public callback method eg: UserController:update
    * @param array $arguments Method arguments to pass to callback method
    *
    * @return bool 
    * @throws ErrorException if method is not callable or doesn't exist
    */
    private static function execute(callable|string $callback, array $arguments = []): bool
    {
        $result = true;

        if(is_callable($callback)) {
            $result = call_user_func_array($callback, $arguments);
        } elseif (stripos($callback, '::') !== false) {
            [$controller, $method] = explode('::', $callback);
            $result = static::reflectionClassLoader($controller, $method, $arguments);
        }
      
        return self::toStatusBool($result);
    }

    /**
     * Execute class using reflection method
     *
     * @param string $controller Controller class name
     * @param string $method class method to execute
     * @param array $arguments Optional arguments to pass to the method
     *
     * @return bool If method was called successfully
     * @throws ErrorException if method is not callable or doesn't exist
    */
    private static function reflectionClassLoader(string $controller, string $method, array $arguments = []): bool 
    {
        $throw = true;
        $isCommand = isset($arguments[0]['command']) && Terminal::isCommandLine();
        $method = ($isCommand ? 'run' : $method); // Only call run method for CLI

        foreach (static::$namespace as $namespace) {
            $className = $namespace . '\\' . $controller;

            try {
                $checkClass = new ReflectionClass($className);
              
                if (!$checkClass->isInstantiable() || 
                    !($checkClass->isSubclassOf(BaseCommand::class) || 
                        $checkClass->isSubclassOf(ViewController::class) ||
                        $checkClass->isSubclassOf(BaseApplication::class))) {
                    continue;
                }
                
                $checkMethod = new ReflectionMethod($className, $method);
                if ($checkMethod->isPublic() && !$checkMethod->isAbstract()) {
                    if($checkMethod->isStatic()) {
                        ErrorException::throwException("Static method is not allowed in controller, please make '$method' none static.");
                        return false;
                    }

                    $newClass = new $className();

                    if($isCommand && $newClass !== null) {
                        [$throw, $result] = static::invokeCommandArgs($newClass, $arguments, $className, $checkMethod);
                    }else{
                        $result = $checkMethod->invokeArgs($newClass, $arguments);
                    }
                    
                    unset($newClass);
                    
                    return self::toStatusBool($result);
                }
            } catch (ReflectionException $e) {
                continue;
            }
        }
    
        if ($throw) {
            ErrorException::throwException("The method '$method' is not callable in registered namespaces.");
        }
    
        return false;
    }

    /**
     * Invoke class using reflection method
     *
     * @param object $newClass Class instance
     * @param array $arguments Pass arguments to reflection method
     * @param string $className Invoking class name
     * @param ReflectionMethod $method Controller class method
     *
     * @return array<bool, bool> 
    */
    private static function invokeCommandArgs(
        object $newClass, 
        array $arguments, 
        string $className, 
        ReflectionMethod $method
    ): array
    {
        $result = false;
        $throw = true;

        if (method_exists($newClass, 'registerCommands')) {
            $commands = $arguments[0]??[];
            $commandId = '_about_';
            if(isset($newClass->group)) {
                $commandId .= $newClass->name;
                $commands[$commandId] = [
                    'class' => $className, 
                    'group' => $newClass->group,
                    'name' => $newClass->name,
                    'options' => $newClass->options,
                    'usages' => $newClass->usages,
                    'description' => $newClass->description
                ];
            }
           
            $code = $newClass->registerCommands($commands);
            if($code === STATUS_OK) {
                if (array_key_exists('help', $commands['options'])) {
                    $result = true;
                    Terminal::printHelp($commands[$commandId]);
                }else{
                    $result = $method->invokeArgs($newClass, $arguments);
                }
            } elseif($code === STATUS_ERROR) {
                $throw = false;
            }
        }

        unset($newClass);
        
        return [$throw, $result];
    }

    /**
     * Convert status to bool, return run status based on result
     * In cli 0 is considered as success while 1 is failure.
     * In few occasion void or null may be returned so we treat it as success
     * 
     * @param void|bool|null|int $result response from callback function
     * 
     * @return bool
    */
    private static function toStatusBool(mixed $result = null): bool
    {
        if ($result === false || (is_int($result) && (int) $result === STATUS_ERROR)) {
            return false;
        }

        return true;
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
    private static function capturePattern(string $pattern, string $uri, mixed &$matches): bool
    {
        // Convert pattern to a regex pattern
        $pattern = str_replace(['/', '{', '}'], ['\/', '(.*?)', ''], $pattern);
        //$pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

        $matched = preg_match_all('/^' . $pattern . '$/', $uri, $matches, PREG_OFFSET_CAPTURE);
        //$matched = preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE);

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
    private static function getCommandName(): string 
    {
        if(isset($_SERVER['argv'])){
            return $_SERVER['argv'][1] ?? '';
        }

        return '';
    }
    
    /**
     * Reset register routes to avoid conflicts
     * 
     * @return void
    */
    private function reset(): void
    {
        $this->controllers['routes'] = [];
        $this->controllers['routes_after'] = [];
        $this->controllers['routes_middleware'] = [];
        $this->controllers['routes_always'] = [];
        $this->controllers['cli_routes'] = [];
        $this->controllers['cli_middleware'] = [];
        $this->controllers['errors'] = [];
        
    }
}