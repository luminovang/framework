<?php
/**
 * Luminova Framework Routing.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Routing;

use \App\Application;
use \App\Config\Template;
use \Luminova\Http\Header;
use \Luminova\Command\Terminal;
use \Luminova\Routing\Prefix;
use \Luminova\Routing\Segments;
use \Luminova\Base\BaseCommand;
use \Luminova\Core\CoreApplication;
use \Luminova\Attributes\Generator;
use \Luminova\Base\BaseViewController;
use \Luminova\Base\BaseController;
use \Luminova\Application\Factory;
use \Luminova\Application\Foundation;
use \Luminova\Cache\ViewCache;
use \Luminova\Exceptions\RouterException;
use \Luminova\Interface\RouterInterface;
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
 * @method static void get(string $pattern, Closure|string $callback) Route to handle http `GET` requests.
 * @method static void post(string $pattern, Closure|string $callback) Route to handle http `POST` requests.
 * @method static void patch(string $pattern, Closure|string $callback) Route to handle http `PATCH` requests.
 * @method static void delete(string $pattern, Closure|string $callback) Route to handle http `DELETE` requests.
 * @method static void put(string $pattern, Closure|string $callback) Route to handle http `PUT` requests.
 * @method static void options(string $pattern, Closure|string $callback) Route to handle http `OPTIONS` requests.
*/
final class Router 
{
    /**
     * Any HTTP request methods.
     * 
     * @var string HTTP_METHODS
    */
    public const HTTP_METHODS = 'GET|POST|PUT|DELETE|OPTIONS|PATCH|HEAD';
    
    /**
     * Route patterns and handling functions.
     * 
     * @var array<string,array> $controllers
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
     * Terminal instance.
     * 
     * @var Terminal|null $term 
    */
    private static ?Terminal $term = null;

    /**
     * Weather router is running in cli mode.
     * 
     * @var bool $isCli 
    */
    private static bool $isCli = false;

    /**
     * Terminate router run when serving static content.
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
     * @var CoreApplication|null $application 
    */
    private static ?CoreApplication $application = null;

    /**
     * Initialize router class.
     * 
     * @param CoreApplication $application Instance of application class.
    */
    public function __construct(CoreApplication $application)
    {
        self::$application = $application;
        Foundation::profiling('start');
    }

    /**
     * A shorthand for route capture https methods to handle "METHOD" request method.
     *
     * @param string $name Method to call.
     * @param array $arguments Method arguments.
     * 
     * Expected arguments:
     * 
     *  - string $pattern The route URL pattern or template view name (e.g `/`, `/home`, `/user/([0-9])`).
     *  - Closure|string $callback Handle callback for router.
     * 
     * @return mixed Return value of method.
     * @throws RouterException Throw if method does not exist.
    */
    public function __call(string $name, array $arguments): mixed
    {
        $method = strtoupper($name);

        if ($method !== 'CLI' && isset(self::$httpMethods[$method])) {
            $this->capture($method, ...$arguments);
            return null;
        }

        RouterException::throwWith('no_method', RouterException::INVALID_REQUEST_METHOD, [
            self::class,
            $name,
        ]);
    }

    /**
     * Initialize application routing with proper context web, cli, api, console etc...
     * 
     * Define URI prefixes and error handlers for specific URI prefix names.
     * Ensures only required routes for handling requests are loaded based on the URI prefix.
     * 
     * @param Prefix|array<string,mixed>|null ...$contexts [, Prefix $... ] Arguments containing routing prefix object or array of arguments.
     *              Pass `NULL` only when using route attributes.
     * 
     * @return self Returns the router instance.
     * @throws RouterException Throws if not context arguments was passed and route attribute is disabled.
     */
    public function context(Prefix|array|null ...$contexts): self 
    {
        self::$isCli = is_command();
        self::$method  = self::getRoutingMethod();
        self::$uri = self::getUriSegments();

         // If application is undergoing maintenance.
         if(MAINTENANCE && self::systemMaintenance()){
            return $this;
        }

        // If the view uri ends with `.extension`, then try serving the cached static version.
        if(self::$uri !== '/cli' && self::serveStaticCache()){
            return $this;
        }

        // Application start event.
        self::$application->__on('onStart', [
            'cli' => self::$isCli ,
            'method' => self::$method,
            'uri' => self::$uri
        ]);

        // When using attribute for routes.
        if((bool) env('feature.route.attributes', false)){
            $collector = new Generator('\\App\\Controllers\\', $this->baseGroup, self::$isCli);
            if(self::$isCli){
                $collector->installCli('app/Controllers');
            }else{
                $collector->installHttp('app/Controllers', self::getFirst());
            }

            $current = $this->baseGroup;
            self::$controllers = array_merge(
                self::$controllers, 
                $collector->getRoutes()
            );
            
            $this->baseGroup = $current;

            return $this;
        }

        // When using default context manager.
        if($contexts === []){
            RouterException::throwWith('no_context', RouterException::RUNTIME_ERROR);
        }
        
        if (isset(self::$httpMethods[self::$method])) {
            $first = self::getFirst();
            $current = $this->baseGroup;
            $fromArray = !($contexts[0] instanceof Prefix);
            $prefixes = $fromArray ? self::getArrayPrefixes($contexts) : Prefix::getPrefixes();

            foreach ($contexts as $context) {
                $name = $fromArray ? ($context['prefix'] ?? '') : $context->getName();

                if($name === ''){
                    continue;
                }
                
                $eHandler = $fromArray ? ($context['error'] ?? null) : $context->getErrorHandler();

                self::reset();
                $result = $this->installContext($name, $eHandler, $first, $prefixes);

                if($result === 2){
                    return $this;
                }

                if($result === true){
                    self::bootContext($name, $this, self::$application);
                    break;
                }
            }

            $this->baseGroup = $current;
        }
        
        return $this;
    }

    /**
     * Before middleware, to handle router middleware authentication.
     * 
     * @param string  $methods  The allowed methods, can be serrated with `|` pipe symbol (e.g. `GET|POST`).
     * @param string  $pattern The route URL pattern or template view name (e.g `/.*`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback Callback function to execute.
     * 
     * @return void
     * @throws RouterException Throws when called in wrong context or if blank method is passed.
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
     * After middleware route, executes the callback function after request was executed successfully.
     *
     * @param string  $methods  The allowed methods, can be serrated with `|` pipe symbol (e.g. `GET|POST`).
     * @param string  $pattern The route URL pattern or template view name (e.g `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The callback function to execute (e.g `ClassBaseName::methodName`).
     * 
     * @return void
     * @throws RouterException Throws if blank method is passed.
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
     * Capture front controller request method based on pattern and execute the callback.
     *
     * @param string $methods The allowed methods, can be separated with `` pipe symbol (e.g `GET|POST|PUT`).
     * @param string $pattern The route URL pattern or template view name (e.g `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The callback function to execute (e.g `ClassBaseName::methodName`).
     * 
     * @return void
     * @throws RouterException Throws if blank method is passed.
    */
    public function capture(string $methods, string $pattern, Closure|string $callback): void
    {
        if ($methods === '') {
            RouterException::throwWith('empty_argument', RouterException::INVALID_ARGUMENTS, [
               '$methods'
            ]);
            return;
        }

        $this->addHttpRoute('routes', $methods, $pattern, $callback);
    }

    /**
     * An alias for route capture method to handle any type of request method.
     *
     * @param string $pattern The route URL pattern or template view name (e.g `/`, `/home`, `/user/([0-9])`).
     * @param Closure|string $callback The callback to execute (e.g `ClassBaseName::methodName`).
     * 
     * @return void
    */
    public function any(string $pattern, Closure|string $callback): void
    {
        $this->capture(self::HTTP_METHODS, $pattern, $callback);
    }

    /**
     * Capture front controller command request names and execute callback.
     *
     * @param string $command The allowed command name or command with filters (e.g `foo`, `foo/(:int)/bar/(:string)`).
     * @param Closure|string $callback The callback function to execute (e.g `ClassBaseName::methodName`).
     * 
     * @return void
    */
    public function command(string $command, Closure|string $callback): void
    {
        self::$controllers['cli_commands']["CLI"][] = [
            'callback' => $callback,
            'pattern' => self::parsePatternValue(trim($command, '/')),
            'middleware' => false
        ];
    }

    /**
     * Before middleware, for command middleware authentication.
     *
     * @param string $group The command middleware group name or `global` for global middleware.
     * @param Closure|string $callback Callback controller handler (e.g `ClassBaseName::methodName`).
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
     *The Bind method allow you to group a collection nested `URI`  together in a single base path prefix or pattern.
     *
     * @param string $prefix The path prefix name or pattern (e.g. `/blog`, `/account/([a-z])`).
     * @param Closure $callback The callback function to handle routes group binding.
     * 
     * @return void
     * @example - Example blog website binding.
     * 
     * ```
     * $router->bind('/blog', static function(Router $router){
     *      $router->get('/', 'BlogController::blogs');
     *      $router->get('/id/([aZ-Az-0-9-])', 'BlogController::blog');
     * });
     * ```
    */
    public function bind(string $prefix, Closure $callback): void
    {
        $current = $this->baseGroup;
        $this->baseGroup .= $prefix;

        $callback(...self::noneParamInjection($callback));
        $this->baseGroup = $current;
    }

    /**
     * Binds commands route within a group.
     *
     * @param string $group The command group name. 
     * @param Closure $callback Callback command function to handle group.
     * 
     * @return void
     * @example - Example blog command grouping.
     * 
     * ```
     * $router->group('blog', static function(Router $router){
     *      $router->command('list', 'BlogController::blogs');
     *      $router->command('id/(:mixed)', 'BlogController::blog');
     * });
     * ```
    */
    public function group(string $group, Closure $callback): void
    {
        self::$controllers['cli_groups'][$group][] = $callback;
    }

    /**
     * Register a controller class namespace to use across the application routing.
     *
     * @param string $namespace Class namespace string.
     * 
     * @return void
     * @throws RouterException If namespace string is empty or contains invalid namespace characters.
    */
    public function addNamespace(string $namespace): void
    {
        if($namespace === '') {
            RouterException::throwWith('empty_argument', RouterException::INVALID_ARGUMENTS, [
                '$namespace'
            ]);

            return;
        }

        $namespace = '\\' . trim($namespace, '\\') . '\\';

        if(!str_starts_with($namespace, '\\App\\Controllers\\')) {
            RouterException::throwWith('invalid_namespace', RouterException::NOT_ALLOWED);
            return;
        }

        self::$namespace[] = $namespace;
    }

    /**
     * Run application routes, loop all defined routing methods to call controller 
     * if method matches view  or command name.
     * 
     * @return void
     * @throws RouterException Throw if encountered error while executing controller callback.
    */
    public function run(): void
    {
        if(self::$terminate){
            exit(STATUS_SUCCESS);
        }

        $context = null;
        if(self::$method === 'CLI'){
            self::terminal();
            $exitCode = self::runAsCommand();
            $context = ['commands' => self::$commands];
        }else{
            $exitCode = self::runAsHttp();

            if (self::$method === 'HEAD') {
                ob_end_clean();
            }
        }

        self::$application->__on('onFinish');
        if($exitCode === STATUS_SUCCESS){
            Foundation::profiling('stop', $context);
        }

        exit($exitCode);
    }

    /**
     * Set an error listener callback function.
     *
     * @param Closure|string|array<int,string> $match Matching route pattern.
     * @param Closure|string|array<int,string>|null $callback Optional error callback handler function.
     *  
     * @return void
    */
    public function setErrorListener(
        Closure|string|array $match, 
        Closure|array|string|null $callback = null
    ): void
    {
        if ($callback === null) {
            self::$controllers['errors']['/'] = $match;
        } else {
            self::$controllers['errors'][$match] = $callback;
        }
    }

    /**
     * Cause triggers an error response.
     *
     * @param int $status HTTP response status code (default: 404).
     * 
     * @return void
    */
    public static function triggerError(int $status = 404): void
    {
     
        foreach (self::$controllers['errors'] as $pattern => $callable) {
            $matches = [];
            if (self::uriCapture($pattern, self::$uri, $matches) && self::call($callable, $matches, true)) {
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
     * Get list of registered controller namespaces.
     *
     * @return array<int,string> Return registered namespaces.
     * @internal
    */
    public static function getNamespaces(): array
    {
        return self::$namespace;
    }

     /**
     * Get the current segment relative URI.
     * 
     * @return string Return relative paths.
    */
    public static function getUriSegments(): string
    {
        if (self::$isCli) {
            return '/cli';
        }
        
        return Foundation::getUriSegments();
    }

    /**
     * Get segment class instance.
     * 
     * @return Segments Segments instance.
    */
    public function getSegment(): Segments 
    {
        return new Segments(self::$isCli ? ['cli'] : Foundation::getSegments());
    }

    /**
     * Boot route context.
     * Allow accessing router and application instance within the context.
     *
     * @param string $context Route context name.
     * @param Router $router  Make router instance available in route.
     * @param CoreApplication $app Make application instance available in route.
     * 
     * @return void
     * @throws RouterException
    */
    private static function bootContext(
        string $context, 
        Router $router, 
        CoreApplication $app
    ): void 
    {
        $__lmv_context = APP_ROOT . 'routes' . DIRECTORY_SEPARATOR . $context . '.php';
        if (file_exists($__lmv_context)) {
            require_once $__lmv_context;
            self::$application->__on('onContextInstalled', $context);
            return;
        }

        self::printError(
            '500 Internal Server Error', 
            RouterException::withMessage('invalid_context', $context), 
            500
        );
    }
    
    /**
     * If the controller already contains a namespace, use it directly.
     * If not, loop through registered namespaces to find the correct class.
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

        if($method === null){
            return '';
        }

        $method = strtoupper($method);
  
        if($method === 'HEAD'){
            ob_start();
            return 'GET';
        }

        self::outputEncoding($_SERVER['HTTP_ACCEPT_ENCODING'] ?? null);

        if($method === 'POST'){
            $headers = Header::getHeaders();
            $overrides = ['PUT' => true, 'DELETE' => true, 'PATCH' => true];
            if (isset($headers['X-HTTP-Method-Override']) && isset($overrides[$headers['X-HTTP-Method-Override']])) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }
        
        return $method;
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
    private static function printError(
        string $header, 
        ?string $message = null, 
        int $status = 404
    ): void 
    {
        Header::headerNoCache($status);
        
        if($message){
            echo "<html><title>{$header}</title><body><h1>{$header}</h1><p>{$message}</p></body></html>";
        }

        exit(STATUS_ERROR);
    }

    /**
     * Register a http route.
     *
     * @param string  $to group name.
     * @param string  $methods  Allowed methods, can be serrated with | pipe symbol.
     * @param string  $pattern The route URL pattern or template view name (e.g `/`, `/home`, `/user/([0-9])`).
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
     * Get first segment of current view uri.
     * 
     * @return string First url segment.
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
            if ($item['prefix'] === Prefix::WEB || $item['prefix'] === null || $item['prefix'] === '') {
                continue;
            }

            $prefixes[$item['prefix']] = $item['prefix'];
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
                if(!self::$isCli) {
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
    private static function isWeContext(string $result, ?string $first = null): bool 
    {
        return ($first === null || $first === '' || $result === Prefix::WEB) && $result !== Prefix::CLI && $result !== Prefix::API;
    }

    /**
     * Get terminal instance.
     * 
     * @return Terminal Return instance of Terminal class.
    */
    private static function terminal(): Terminal
    {
        return self::$term ??= new Terminal();
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
            self::$term->header();
            self::$term->helper(null, true);
            return STATUS_SUCCESS;
        }

        $command = self::getArgument(2);
        $global = (self::$controllers['cli_middleware'][self::$method]['global']??null);

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
                self::$application->__on('onCommandPresent', self::getArguments());
                return STATUS_SUCCESS;
            }
        }

        self::$term->print('Unknown command ' . self::$term->color("'{$group} {$command}'", 'red') . ' not found', null);
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
        $middleware = (self::$controllers['routes_middleware'][self::$method] ?? null);
        if ($middleware !== null && !self::handleWebsite($middleware, self::$uri)) {
            return STATUS_ERROR;
        }

        $routes = (self::$controllers['routes'][self::$method] ?? null);

        if ($routes !== null && self::handleWebsite($routes, self::$uri)) {
            $after = (self::$controllers['routes_after'][self::$method] ?? null);
            
            if($after !== null){
                self::handleWebsite($after, self::$uri);
            }

            self::$application->__on('onViewPresent', self::$uri);

            return STATUS_SUCCESS;
        }

        static::triggerError();
        return STATUS_ERROR;
    }

    /**
     * Load application is undergoing maintenance.
     * 
     * @return bool Return true.
    */
    private static function systemMaintenance(): bool 
    {
        self::$terminate = true;
        
        Header::headerNoCache(503, null, env('app.maintenance.retry', '3600'));
        if(file_exists($path = self::$application->getSystemError('maintenance'))){
            include_once $path;
        }

        return true;
    }

    /**
     * Serve static cached pages.
     * If cache is enabled and the request is not in CLI mode, check if the cache is still valid.
     * If valid, render the cache and terminate further router execution.
     *
     * @return bool Return true if cache is rendered, otherwise false.
     */
    private static function serveStaticCache(): bool
    {
        if (
            self::$isCli || 
            self::$method === 'CLI' || 
            !env('page.caching', false)
        ) {
            return false;
        }

        // Supported extension types to match.
        $types = env('page.caching.statics', false);
        static $cachePath = null;
        if ($types && $types !== '' && preg_match('/\.(' . $types . ')$/i', self::$uri, $matches)) {
            $cachePath ??= root(rtrim((new Template())->cacheFolder, TRIM_DS) . '/default/');
            $cache = (new ViewCache(0, $cachePath))->setKey(Foundation::getCacheId());

            // If expiration return mismatched int code 404 ignore and do not try to replace to actual url.
            $expired = $cache->expired($matches[1]);

            if ($expired === true) {
                // Remove the matched file extension to render the request normally
                self::$uri = substr(self::$uri, 0, -strlen($matches[0]));
            }elseif($expired === false && $cache->read() === true){
                // Render the cached content.
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
     * @return bool Return true if controller was handled, otherwise false.
     * @throws RouterException if method is not callable or doesn't exist.
    */
    private static function handleWebsite(array $routes, string $uri): bool
    {
        $match = false;

        foreach ($routes as $route) {
            $matches = [];
            $match = self::uriCapture($route['pattern'], $uri, $matches);

            if ($match) {
                $passed = self::call($route['callback'], self::matchesToArray((array) $matches));
            }

            if ((!$match && $route['middleware']) || ($match && $passed)) {
                return true;
            }
        }
       
        return false;
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
                return self::call($route['callback'], self::$commands);
            }

            $matches = [];

            if (self::uriCapture($route['pattern'], $queries['view'], $matches)) {
                self::$commands['params'] = self::matchesToArray((array) $matches);
                return self::call($route['callback'], self::$commands);
            } 
            
            if ($queries['view'] === $route['pattern'] || $isHelp) {
                return self::call($route['callback'], self::$commands);
            }
        }

        return false;
    }

    /**
     * Extract matched parameters from request.
     *
     * @param array<int,array> $array Matched url parameters.
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
    private static function injection(
        Closure|ReflectionMethod|string $caller, 
        array $arguments = [], 
        bool $injection = false
    ): array
    {
        if (!$injection && !(bool) env('feature.route.dependency.injection', false)) {
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
    * Execute router HTTP callback class method with the given parameters using instance callback or reflection class.
    *
    * @param Closure|string|array<int,string> $callback Class public callback method eg: UserController:update.
    * @param array $arguments Method arguments to pass to callback method.
    * @param bool $injection Force use dependency injection. Default is false.
    *
    * @return bool Return true if controller method was executed successfully, false otherwise.
    * @throws RouterException if method is not callable or doesn't exist.
    */
    private static function call(
        Closure|string|array $callback, 
        array $arguments = [], 
        bool $injection = false
    ): bool
    {
        if ($callback instanceof Closure) {
            $arguments = ((self::$isCli && isset($arguments['command'])) ? ($arguments['params'] ?? []) : $arguments);
            
            return status_code($callback(...self::injection($callback, $arguments, $injection)), false);
        }

        if (is_array($callback)) {
            // It probably static implementation of error handler.
            return self::reflection($callback[0], $callback[1], $arguments, $injection);
        }

        if (str_contains($callback, '::')) {
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
     * Execute class using reflection method.
     *
     * @param string $className Controller class name.
     * @param string $method Controller class method name.
     * @param array $arguments Optional arguments to pass to the method.
     * @param bool $injection Force use dependency injection. Default is false.
     *
     * @return bool If method was called successfully.
     * @throws RouterException if method is not callable or doesn't exist.
     */
    private static function reflection(
        string $className, 
        string $method, 
        array $arguments = [], 
        bool $injection = false
    ): bool 
    {
        if ($className === '') {
            RouterException::throwWith('invalid_class', RouterException::CLASS_NOT_FOUND, [
                $className
            ]);
            return false;
        }

        try {
            $class = new ReflectionClass($className);

            if (!($class->isInstantiable() && (
                $class->isSubclassOf(BaseCommand::class) || 
                $class->isSubclassOf(BaseViewController::class) ||
                $class->isSubclassOf(BaseController::class) ||
                $class->implementsInterface(RouterInterface::class)))) {
                RouterException::throwWith('invalid_controller', RouterException::INVALID_CONTROLLER, [
                    $className
                ]);
                return false;
            }

            $caller = $class->getMethod($method);
            
            if ($caller->isPublic() && !$caller->isAbstract() && 
                (!$caller->isStatic() || $class->implementsInterface(ErrorHandlerInterface::class) || $class->implementsInterface(RouterInterface::class))
            ) {
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
        } catch (ReflectionException $e) {
            if($e->getCode() === RouterException::INVALID_CONTROLLER){
                throw new RouterException($e->getMessage(), $e->getCode(), $e);
            }

            RouterException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }

        RouterException::throwWith('invalid_method', RouterException::INVALID_METHOD, [$method]);
        return false;
    }

    /**
     * Invoke class using reflection method.
     *
     * @param array $arguments Pass arguments to reflection method.
     * @param string $className Invoking class name.
     * @param ReflectionMethod $caller Controller class method.
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
            'options' => $instance->options,
            'examples' => $instance->examples,
        ];

        // Check command string to determine if it has help arguments.
        if(self::$term->isHelp($arguments['command'])){
            
            if(!array_key_exists('no-header', $arguments['options'])){
                self::$term->header();
            }

            if($instance->help($arguments[$id]) === STATUS_ERROR){
                // Fallback to default help information if dev does not implement help.
                self::$term->helper($arguments[$id]);
            }

            return STATUS_SUCCESS;
        }

        // Make the command available through get options.
        $instance->explain($arguments);

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
    * @param mixed &$matches Url matches passed by reference.
    *
    * @return bool Return true if is match, otherwise false.
    */
    private static function uriCapture(string $pattern, string $uri, mixed &$matches): bool
    {
        $matches = [];
        $pattern = '#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#';
        $result = (bool) preg_match_all($pattern, $uri, $matches, PREG_OFFSET_CAPTURE);
    
        if (!$result || preg_last_error() !== PREG_NO_ERROR) {
            return false;
        }

        return $result;
    }

    /**
     * Create a new instance of a class.
     *
     * @param class-string<\T> $class The class name to inject.
     * 
     * @return class-object<\T>|null The new instance of the class, or null if the class is not found.
     * @throws Exception|AppException Throws if the class does not exist or requires arguments to initialize.
     */
    private static function newInstance(string $class): ?object 
    {
        return match ($class) {
            Application::class => self::$application ?? null,
            self::class => self::$application?->router ?? null,
            Terminal::class => self::terminal(),
            Factory::class => factory(),
            Closure::class => fn(mixed ...$arguments): mixed => null,
            default => new $class()
        };
    }

    /**
     * Get union types as array or string.
     *
     * @param ReflectionNamedType[]|ReflectionIntersectionType[] $unions The union types.
     * 
     * @return array Return the union types.
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
     * 
     * @return mixed Return the casted value.
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
     * Replace command script filter values match (:value) and replace with actual (pattern).
     *
     * @param string $input command script pattern.
     * 
     * @return string $output If match return replaced string.
    */
    private static function parsePatternValue(string $input): string
    {
        $patterns = [
            '(:mixed)' => '([^/]+)',
            '(:optional)' => '?([^/]*)',
            '(:int)' => '(\d+)',
            '(:float)' => '([+-]?\d+\.\d+)',
            '(:string)' => '([a-zA-Z0-9_-]+)',
            '(:alphabet)' => '([a-zA-Z]+)',
            '(:path)' => '((.+)/([^/]+)+)',
        ];

        $input = str_replace(array_keys($patterns), array_values($patterns), $input);

        return '/' . ltrim($input, '/');
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
     * Get the current command controller views.
     * 
     * @return array<string,mixed> $views Return array of command routes parameters as URI.
    */
    public static function getArguments(): array
    {
        $views = [
            'view' => '',
            'options' => [],
        ];

        if (!isset($_SERVER['argv'][2])) {
            return $views;
        }

        $result = self::$term->extract(array_slice($_SERVER['argv'], 2), true);
        $views['view'] = '/' . implode('/', $result['arguments']);
        $views['options'] = $result['options'];

        return $views;
    }
    
    /**
     * Reset register routes to avoid conflicts.
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