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
use \Luminova\Base\BaseCommand;
use \Luminova\Base\BaseApplication;
use \App\Controllers\Application;
use \Luminova\Base\BaseViewController;
use \Luminova\Base\BaseController;
use \Luminova\Application\Factory;
use \Luminova\Application\Foundation;
use \Luminova\Exceptions\RouterException;
use \Luminova\Interface\ErrorHandlerInterface;
use \ReflectionMethod;
use \ReflectionFunction;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionException;
use \ReflectionIntersectionType;
use \ReflectionClass;
use \Closure;
use \Exception;

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
        'cli_commands' =>       [], 
        'cli_middleware' =>     [], 
        'cli_groups' =>         [], 
        'errors' =>             []
    ];

    /**
     * All allowed HTTP request methods
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
     * Application registered controllers namespace
     * 
     * @var array $namespace
    */
    private static array $namespace = [];

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
        self::$application = $application;
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

        if ($method !== 'CLI' && isset(self::$httpMethods[$method])) {
            return $this->capture($method, ...$arguments);
        }

        RouterException::throwWith('no_method', E_ERROR, [
            self::class,
            $name,
        ]);
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
        self::$isCli = is_command();
        self::$method  = self::getRoutingMethod();

        if (isset(self::$httpMethods[self::$method])) {
            $firstSegment = self::getFirst();
            $instances = Context::getInstances();
            $current = $this->baseGroup;

            foreach ($contexts as $context) {
                $name = $context->getName();

                if ($name !== '') {
                    self::reset();

                    if($firstSegment === $name) {
                        if ($name === Context::CLI){
                            if(!self::$isCli) {
                                return $this;
                            }
                            
                            defined('CLI_ENVIRONMENT') || define('CLI_ENVIRONMENT', env('cli.environment.mood', 'testing'));
                        }elseif(($eHandler = $context->getErrorHandler()) !== null){
                            $this->setErrorListener($eHandler);
                        }
                   
                        if (isset($instances[$name])) {  
                            $this->baseGroup .= '/' . $name;
                        }
                    
                        self::bootContext($name, $this, self::$application);
                        break;
                    }elseif(!isset($instances[$firstSegment]) && self::isWeContext($name, $firstSegment)) {
                        if(($eHandler = $context->getErrorHandler()) !== null){
                            $this->setErrorListener($eHandler);
                        }

                        self::bootContext($name, $this, self::$application);
                        break;
                    }
                }
            }

            $this->baseGroup = $current;
        }

        return $this;
    }

    /**
     * Before middleware, to handle router middleware authentication.
     * 
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol
     * @param string  $pattern A route pattern or template view name
     * @param Closure|string $callback Callback function to execute
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context or if blank method is passed.
    */
    public function middleware(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::throwWith('empty_argument', 0, [
                '$methods'
            ]);
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
     * @throws RouterException Throws if blank method is passed.
    */
    public function after(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::throwWith('empty_argument', 0, [
                '$methods'
            ]);
            return;
        }

        $this->authentication('routes_after', $methods, $pattern, $callback);
    }

    /**
     * Before middleware, for command middleware authentication.
     *
     * @param string $group Command middleware group name or `any` for global middleware.
     * @param Closure|string $callback Callback controller handler.
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context.
    */
    public function before(string $group, Closure|string $callback = null): void
    {
        if(!self::$isCli){
            RouterException::throwWith('invalid_cli_middleware');
        }

        $group = trim($group, '/');
        self::$controllers['cli_middleware']['CLI'][$group][] = [
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
     * @throws RouterException Throws if blank method is passed.
    */
    public function capture(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::throwWith('empty_argument', 0, [
               '$methods'
            ]);
            return;
        }

        $pattern = $this->baseGroup . '/' . trim($pattern, '/');
        $pattern = ($this->baseGroup !== '') ? rtrim($pattern, '/') : $pattern;
        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            self::$controllers['routes'][$method][] = [
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
        self::$controllers['cli_commands']["CLI"][] = [
            'callback' => $callback,
            'pattern' => self::parsePatternValue(trim($pattern, '/')),
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
    */
    public function bind(string $group, Closure $callback): void
    {
        $current = $this->baseGroup;
        $this->baseGroup .= $group;

        $callback(...self::noneParamInjection($callback));
        $this->baseGroup = $current;
    }

    /**
     * Binds a command route group.
     *
     * @param string $group The binding group name.
     * @param Closure $callback Callback command function to handle group
     * 
     * @return void
    */
    public function group(string $group, Closure $callback): void
    {
        self::$controllers['cli_groups'][$group][] = $callback;
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
        $app_context_file = APP_ROOT . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $context . '.php';
        if (file_exists($app_context_file)) {
            require_once $app_context_file;
            return;
        }

        self::printError(
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
            RouterException::throwWith('empty_argument', 0, [
                '$namespace'
            ]);

            return;
        }

        $namespace = '\\' . ltrim($namespace, '\\') . '\\';

        if(!str_starts_with($namespace, '\App\Controllers\\')) {
            RouterException::throwWith('invalid_namespace');
            return;
        }

        self::$namespace[] = $namespace;
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

        foreach (self::$namespace as $namespace) {
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
        if(self::$method === 'CLI'){
            self::terminal();
            exit(self::runAsCommand());
        }

        self::runAsHttp();

        if (self::$method === 'HEAD') {
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

        if (str_contains($encoding, 'x-gzip') || str_contains($encoding, 'gzip')) {
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
    private static function getRoutingMethod(): string
    {
        $method = ($_SERVER['REQUEST_METHOD'] ?? null);

        if($method === null && self::$isCli){
            return 'CLI';
        }
  
        if($method === 'HEAD'){
            ob_start();
            return 'GET';
        }

        self::outputEncoding($_SERVER['HTTP_ACCEPT_ENCODING'] ?? null);

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
     * @param Closure|string|array<int,string>|null $callback Optional error callback handler function
     *  
     * @return void
    */
    public function setErrorListener(Closure|string|array $match, Closure|array|string|null $callback = null): void
    {
        if ($callback === null) {
            self::$controllers['errors']['/'] = $match;
        } else {
            self::$controllers['errors'][$match] = $callback;
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
        foreach (self::$controllers['errors'] as $pattern => $callable) {
            if (self::uriCapture($pattern, static::getUriSegments(), $matches) && self::call($callable, $matches, true)) {
                return;
            }
        }
      
        $error = (self::$controllers['errors']['/'] ?? null);
        if ($error !== null && self::call($error, [], true)) {
            return;
        }
       
        self::printError('Error file not found', null, $status);
    }

    /**
     * Show error message with proper header and status code.
     * 
     * @param string $header Header Title of error message.
     * @param string|null $message Optional message body to display.
     * @param int $status http status code.
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
        return self::$namespace;
    }

     /**
     * Get the current segment relative URI.
     * 
     * @return string Relative paths
    */
    public static function getUriSegments(): string
    {
        if (self::$isCli) {
            return '/cli';
        }
        
        return Foundation::getUriSegments();
    }

    /**
     * Get segment class instance 
     * 
     * @return Segments Segments instance
    */
    public function getSegment(): Segments 
    {
        return new Segments(self::$isCli ? ['cli'] : Foundation::getSegments());
    }

    /**
     * Get first segment of current view uri.
     * 
     * @return string First url segment
    */
    private static function getFirst(): string
    {
        if(self::$isCli){
            return 'cli';
        }

        $segments = Foundation::getSegments();
        return reset($segments);
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
        if(self::$term === null){
            self::$term = new Terminal();
        }

        return self::$term;
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
        if(self::$isCli){
            RouterException::throwWith('invalid_middleware');
        }

        $pattern = $this->baseGroup . '/' . trim($pattern, '/');
        $pattern = ($this->baseGroup !== '') ? rtrim($pattern, '/') : $pattern;

        $pipes = explode('|', $methods);

        foreach ($pipes as $method) {
            self::$controllers[$to][$method][] = [
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
        $group = self::getArgument();

        if(self::$term->isHelp($group)){
            self::$term->helper(null, true);
            return STATUS_SUCCESS;
        }

        $command = self::getArgument(2);
        $global = (self::$controllers['cli_middleware'][self::$method]['any']??null);

        if($global !== null && !self::handleCommand($global)){
            return STATUS_ERROR;
        }
        
        $groups = (self::$controllers['cli_groups'][$group] ?? null);
        if($groups !== null){
            foreach($groups as $groupCallback){
                $groupCallback(...self::noneParamInjection($groupCallback));
            }

            $middleware = (self::$controllers['cli_middleware'][self::$method][$group] ?? null);
            if($middleware !== null && !self::handleCommand($middleware)){
                return STATUS_ERROR;
            }
            
            $routes = self::$controllers['cli_commands'][self::$method] ?? null;
            if ($routes !== null && self::handleCommand($routes)) {
                return STATUS_SUCCESS;
            }
        }

        self::$term->print('Unknown command ' . self::$term->color("'{$group} {$command}'", 'red') . ' not found', null);
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
       
        $middleware = (self::$controllers['routes_middleware'][self::$method] ?? null);
        if ($middleware !== null && !self::handleWebsite($middleware, $uri)) {
            return false;
        }

        $routes = (self::$controllers['routes'][self::$method] ?? null);
        if ($routes !== null && self::handleWebsite($routes, $uri)) {
            $after = (self::$controllers['routes_after'][self::$method] ?? null);
            if($after !== null){
                self::handleWebsite($after, $uri);
            }

            return true;
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
            if (self::uriCapture($route['pattern'], $uri, $matches)) {
                $passed = self::call($route['callback'], self::matchesToArray((array) $matches));

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
        $commands = self::$term->parseCommands($_SERVER['argv'] ?? [], true);
        $queries = self::getArguments();
        $isHelp = self::$term->isHelp(self::getArgument(2));
        
        foreach ($routes as $route) {
            if($route['middleware']){
                return self::call($route['callback'], $commands);
            }
            
            if (self::uriCapture($route['pattern'], $queries['view'], $matches)) {
                $commands['params'] = self::matchesToArray((array) $matches);
                return self::call($route['callback'], $commands);
            } 
            
            if ($queries['view'] === $route['pattern'] || $isHelp) {
                return self::call($route['callback'], $commands);
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
            $params[] = isset($match[0][0]) && $match[0][1] !== -1 ? trim($match[0][0], '/') : null;
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
        if (!$injection && !(bool) env('feature.route.dependency.injection', false)) {
            return $arguments;
        }

        try {
            $parameters = [];
            $caller = (($caller instanceof ReflectionMethod) ? $caller : new ReflectionFunction($caller));

            if ($caller->getNumberOfParameters() === 0 && ($found = count($arguments)) > 0) {
                RouterException::throwWith('bad_method', E_COMPILE_ERROR, [
                    ($caller->isClosure() ? $caller->getName() : $caller->getDeclaringClass()->getName() . '->' . $caller->getName()),
                    $found,
                    filter_paths($caller->getFileName()),
                    $caller->getStartLine()
                ]);
            }
            
            foreach ($caller->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType) {
                    if($type->isBuiltin()) {
                        if($arguments !== []) {
                            $parameters[] = self::typeCasting($type->getName(),  array_shift($arguments));
                        }
                    }else{
                        $parameters[] = self::newInstance($type->getName());
                    }
                } elseif($type instanceof ReflectionUnionType) {
                    $types = self::getUnionTypes($type->getTypes());

                    if((isset($types['builtin']))){
                        if($arguments !== []) {
                            $parameters[] = self::typeCasting($types['builtin'], array_shift($arguments));
                        }
                    }else{
                        $parameters[] = self::newInstance($types['inject']);
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
        $params = (new ReflectionFunction($callback))->getParameters();
        $classNames = [];
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $classNames[] = self::newInstance($type->getName());
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
        if ($class === Application::class) {
            return self::$application ?? null;
        }

        if ($class === self::class) {
            return self::$application?->router ?? null;
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
            }

            if ($type->allowsNull()) {
                return ['builtin' => 'null'];
            }

            $types[$type->getName()] = $type->getName();
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
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'double' => (double) $value,
            'null' => null,
            'false' => false,
            'true' => true,
            'string' => (string) $value,
            'array' => (array) $value,
            'object' => (object) $value,
            'callable' => fn(mixed ...$arguments):mixed => $value,
            default => $value,
        };
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
            $arguments = ((isset($arguments['command']) && self::$isCli) ?  ($arguments['params'] ?? []) : $arguments);
            return status_code(call_user_func_array(
                $callback, 
                self::injection($callback, $arguments, $injection)
            ), false);
        }

        if (is_array($callback)) {
            // It probably static implementation of error handler.
            return self::reflection($callback[0], $callback[1], $arguments, $injection);
        }

        if (stripos($callback, '::') !== false) {
            [$controller, $method] = explode('::', $callback);

            return self::reflection(
                self::getControllerClass($controller), 
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
     * @param string $className Controller class name.
     * @param string $method Controller class method name.
     * @param array $arguments Optional arguments to pass to the method
     * @param bool $injection Force use dependency injection. Default is false.
     *
     * @return bool If method was called successfully
     * @throws RouterException if method is not callable or doesn't exist
     */
    private static function reflection(
        string $className, 
        string $method, 
        array $arguments = [], 
        bool $injection = false
    ): bool 
    {
        if ($className === '') {
            RouterException::throwWith('invalid_class', -1, [
                $className
            ]);
            return false;
        }

        try {
            $class = new ReflectionClass($className);

            if (!($class->isInstantiable() && (
                $class->isSubclassOf(BaseCommand::class) || 
                $class->isSubclassOf(BaseViewController::class) ||
                $class->isSubclassOf(BaseController::class)))) {
                RouterException::throwWith('invalid_controller', 1, [
                    $className
                ]);
                return false;
            }

            $caller = $class->getMethod($method);
            
            if ($caller->isPublic() && !$caller->isAbstract() && (!$caller->isStatic() || $class->isSubclassOf(ErrorHandlerInterface::class))) {
                if (isset($arguments['command']) && self::$isCli) {;
                    if($class->getProperty('group')->getDefaultValue() === self::getArgument(1)) {
                        $arguments['classMethod'] = $method;
                        $result = self::invokeCommandArgs(
                            $class->newInstance(), 
                            $arguments, 
                            $className, 
                            $caller
                        );
                    }
                } else {
                    $result = $caller->invokeArgs(
                        $caller->isStatic() ? null: $class->newInstance(), 
                        self::injection($caller, $arguments, $injection)
                    );
                }

                return status_code($result, false);
            }

            RouterException::throwWith('invalid_method', 1, [
                $method
            ]);
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
     * @param array $arguments Pass arguments to reflection method
     * @param string $className Invoking class name
     * @param ReflectionMethod $caller Controller class method
     *
     * @return int Return result from command controller method.
    */
    private static function invokeCommandArgs(
        BaseCommand $instance,
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

        if(self::$term->isHelp($arguments['command'])){
            if($instance->help($arguments[$id]) === STATUS_ERROR){
                self::$term->helper($arguments[$id]);
            }

            return STATUS_SUCCESS;
        }

        $instance->explain($arguments);
        return $caller->invokeArgs(
            $instance, 
            self::injection($caller, $arguments['params']??[])
        );
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
            '(:value)' => '([^/]+)',
            '(:optional)' => '?([^/]*)',
            '(:int)' => '(\d+)',
            '(:float)' => '([+-]?\d+\.\d+)',
            '(:string)' => '([a-zA-Z0-9_-]+)',
            '(:alphabet)' => '([a-zA-Z]+)',
            '(:path)' => '((.+)/([^/]+)+)',
        ];

        $input = str_replace(array_keys($patterns), array_values($patterns), $input);

        if (!str_starts_with($input, '/')) {
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
            $result = self::$term->extract(array_slice($_SERVER['argv'], 2), true);
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
        self::$controllers['routes'] = [];
        self::$controllers['routes_after'] = [];
        self::$controllers['routes_middleware'] = [];
        self::$controllers['cli_commands'] = [];
        self::$controllers['cli_middleware'] = [];
        self::$controllers['errors'] = [];
        self::$controllers['cli_groups'] = [];
    }
}