<?php
/**
 * Luminova Framework Routes Attributes Compiler
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Attributes;

use \Luminova\Attributes\Route;
use \Luminova\Attributes\Error;
use \Luminova\Attributes\Prefix;
use \Luminova\Routing\Router;
use \Luminova\Base\BaseCommand;
use \Luminova\Base\BaseController;
use \Luminova\Base\BaseViewController;
use \Luminova\Interface\RouterInterface;
use \Luminova\Exceptions\RouterException;
use \Luminova\Exceptions\AppException;
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionException;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RecursiveCallbackFilterIterator;
use \FilesystemIterator;
use \SplFileInfo;
use \UnexpectedValueException;
use \Exception;

final class AttrCompiler
{
    /**
     * Extracted routes from attributes.
     * 
     * @var array<string,array|string> $routes
     */
    private array $routes = [];

    /**
     * Waether routing attributes cache is enabled.
     * 
     * @var bool $cache
     */
    private static bool $cache = false;

    /**
     * Loaded controller files.
     * 
     * @var array<string,RecursiveIteratorIterator> $files
     */
    private static array $files = [];

    /**
     * Constructor to initialize the compiler.
     *
     * @param string $baseGroup Base group for route patterns.
     * @param bool $cli Flag indicating if running in CLI mode.
     * @param bool $hmvc Flag indicating if running application with hmvc module.
     */
    public function __construct(
        private string $baseGroup = '', 
        private bool $cli = false,
        private bool $hmvc = false
    )
    {
        self::$cache = (bool) env('feature.route.cache.attributes', false);

        // Throw an exceptions error on cli
        if($this->cli){
            setenv('throw.cli.exceptions', 'true');
        }
    }

    /**
     * Get the collected routes.
     *
     * @return array Return array of collected routes.
     */
    public function getRoutes(): array
    {
        return $this->routes['controllers'] ?? [];
    }

    /**
     * Install HTTP routes from the given path.
     *
     * @param string $path Path to the directory containing HTTP controller classes.
     * @param string $context The request URI prefix, which is the first segment of request URL.
     * @param string $prefix The full request URL paths.
     * 
     * @return void
     * @throws RouterException Throws if error occurs while exporting controller routes.
     */
    public function getAttrFromHttp(string $path, string $context = '', string $uri = '/'): void
    {
        if($this->cli){
            return;
        }

        $files = $this->load($path . ($this->hmvc ? '' : 'Http'), 'http', $context, $uri);
        if($files === true){
            return;
        }
  
        foreach ($files as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
           
            if (!$fileName) {
                continue;
            }

            try{
                $module = $this->hmvc ? basename(dirname($file->getPathname(), 2)) : '';
                $namespace = $this->hmvc 
                    ? '\\App\Modules\\' . ($module === 'Controllers' ? '' : uppercase_words($module) . '\\') . 'Controllers\\Http\\'
                    : '\\App\\Controllers\\Http\\';

                $class = new ReflectionClass("{$namespace}{$fileName}");
            }catch(ReflectionException $e){
                throw new RouterException($e->getMessage(), $e->getCode(), $e);
            }

            if(
                !$this->isValidClass($class) || 
                !$this->isClassUriPrefix($class, $uri, $context)
            ){
                continue;
            }

            /**
             * Handle context attributes and register error handlers.
            */
            $this->addErrorHandlers($class, $context);

            /**
             * Handle method attributes and create routes.
            */
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class);
                $callback = $fileName . '::' . $method->getName();

                foreach ($attributes as $attribute) {
                    $attr = $attribute->newInstance();
                    //$callback = $fileName . '::' . $method->getName();

                    // If group is not null, then we need to skip immediately as it for cli
                    if($attr->group !== null){
                        return;
                    }

                    // If the route is an error handler, register it and skip. 
                    if($attr->error){
                        $this->routes['controllers']['errors'][Router::normalizePatterns($attr->pattern)] = $callback;
                        continue;
                    }

                    $pattern = $this->baseGroup . '/' . trim($attr->pattern, '/');
                    $pattern = ($this->baseGroup !== '') ? rtrim($pattern, '/') : $pattern;

                    /**
                     * Process the matched context against patterns (e.g., `/foo/(:placeholder)`).
                     * If the middleware and prefix are not empty, and the pattern is the base, skip processing.
                     */
                    if (
                        $context !== '' && 
                        !str_starts_with(ltrim($pattern, '/'), $context) &&
                        ($attr->middleware !== null && $attr->pattern === '/')
                    ) {
                        continue;
                    }

                    $pattern = Router::normalizePatterns($pattern);

                    foreach($attr->methods as $httpMethod){
                        $to = ($attr->middleware === 'before') 
                            ? 'routes_middleware' 
                            : (($attr->middleware === 'after') ? 'routes_after' : 'routes');

                        $this->routes['controllers'][$to][$httpMethod][] = [
                            'pattern' => $pattern,
                            'callback' => $callback,
                            'middleware' => $attr->middleware === 'before'
                        ];
                    }
                    
                }
            }
        }

        $this->cache('http', $context);
        gc_mem_caches();
    }

    /**
     * Install CLI commands from the given path.
     *
     * @param string $path Path to the directory containing command controller classes.
     * 
     * @return void
     * @throws RouterException Throws if error occurs while exporting controller routes.
     */
    public function getAttrFromCli(string $path): void
    {
        if(!$this->cli){
            return;
        }

        $files = $this->load($path . ($this->hmvc ? '' : 'Cli'), 'cli');
        if($files === true){
            return;
        }

        foreach ($files as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
           
            if (!$fileName) {
                continue;
            }
        
            try{
                $module = $this->hmvc ? basename(dirname($file->getPathname(), 2)) : '';
                $namespace = $this->hmvc 
                    ? '\\App\Modules\\' . ($module === 'Controllers' ? '' : uppercase_words($module) . '\\') . 'Controllers\\Cli\\'
                    : '\\App\\Controllers\\Cli\\';

                $class = new ReflectionClass("{$namespace}{$fileName}");
            }catch(ReflectionException $e){
                throw new RouterException($e->getMessage(), $e->getCode(), $e);
            }

            if (!($class->isInstantiable() && !$class->isAbstract() && (
                $class->isSubclassOf(BaseCommand::class)))) {
                continue;
            }

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class);
                $callback = $fileName . '::' . $method->getName();

                foreach ($attributes as $attribute) {
                    $attr = $attribute->newInstance();

                    if(!$this->cli || $attr->group === null){
                        continue;
                    }

                    $group = trim($attr->group, '/');

                    if($attr->middleware !== null){
                        $security = ($attr->middleware === 'global') ? 'global' : $group;
                        $this->routes['controllers']['cli_middleware']['CLI'][$security][] = [
                            'callback' => $callback,
                            'pattern' => $group,
                            'middleware' => true
                        ];
                        continue;
                    }

                    $this->routes['controllers']['cli_groups'][$group][] = static fn(\Luminova\Routing\Router $router) => $router->command(Router::normalizePatterns($attr->pattern), $callback);
                }
            }
        }

        $this->cache('cli');
        gc_mem_caches();
    }

    /**
     * Extract and export all routes attributes 
     * and convert them back to standard php routes using methods.
     * 
     * @param string $path The path to controller classes.
     * 
     * @return self Return instance of Attribute Generator.
     * @throws RouterException Throws if error occurs while exporting controller routes.
     */
    public function export(string $path): self
    {
        $files = $this->load($path, 'export');

        foreach ($files as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
           
            if (!$fileName) {
                continue;
            }

            $module = basename(dirname($file->getPathname(), 2));
            $namespace = $this->hmvc 
                ? '\\App\\Modules\\' . ($module === 'Controllers' ? '' : uppercase_words($module) . '\\') . 'Controllers\\'
                : '\\App\\Controllers\\';

            try {
                $class = new ReflectionClass("{$namespace}Http\\{$fileName}");
            } catch (ReflectionException $e) {
                try {
                    $class = new ReflectionClass("{$namespace}Cli\\{$fileName}");
                } catch (ReflectionException $e) {
                    throw new RouterException($e->getMessage(), $e->getCode(), $e);
                }
            }                

            if (!($class->isInstantiable() && !$class->isAbstract() && (
                $class->isSubclassOf(BaseCommand::class) || 
                $class->isSubclassOf(BaseViewController::class) ||
                $class->isSubclassOf(BaseController::class) ||
                $class->implementsInterface(RouterInterface::class)))) {
                continue;
            }

            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class);
                $callback = $fileName . '::' . $method->getName();

                foreach ($attributes as $attribute) {
                    $attr = $attribute->newInstance();
                    
                    if($attr->group !== null){
                        $group = trim($attr->group, '/');
                        $this->routes['controllers']['cli'][$module][$group][] = [
                            'group' => $group,
                            'callback' => $callback,
                            'pattern' => $attr->pattern,
                            'middleware' => $attr->middleware
                        ];
                    }else{
                        $bind = ($attr->pattern === '/') ? '/' : trim($attr->pattern, '/');
                        $list = ($bind !== '/' && str_contains($bind, '/')) ? explode('/', $bind) : [];
                        $bind = (($list === []) ? $bind :
                            (($list[0] === 'api') ? $list[1] : $list[0]));

                        $context = (str_starts_with($attr->pattern, '/api') || str_starts_with($attr->pattern, 'api')) ? 'api' : 'http';

                        $this->routes['controllers'][$context][$module][$bind][] = [
                            'bind' => $bind,
                            'callback' => $callback,
                            'methods' => $attr->methods,
                            'pattern' => $attr->pattern,
                            'middleware' => $attr->middleware
                        ];
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Load routing files from a specified path or cached version if cache is enabled.
     *
     * @param string $path The directory path to search for routing files.
     * @param string $name The name of the routing file or group to load (e.g., 'cli', 'web').
     * @param string $prefix The URI prefix for route group (e.g., 'api', 'web').
     * @param string $uri The request URI to match against loaded routes.
     * 
     * @return RecursiveIteratorIterator|true Returns a RecursiveIteratorIterator if no cache is found, 
     *                                        otherwise returns true on successful cache load.
     * @throws RouterException Throws if error occurs while loading controllers.
     */
    protected function load(
        string $path, 
        string $name, 
        string $prefix = '__cli__', 
        string $uri = '/'
    ): RecursiveIteratorIterator|bool
    {
        if (self::$cache && $name !== 'export' && $this->hasCache($name, $prefix, $uri)) {
            return true;
        }

        try{
            return static::$files[$path] ??= new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(
                        root($path), 
                        FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
                    ),
                    fn(SplFileInfo $entry) => $this->isValidEntry($entry, $name)
                )
            );
        }catch(UnexpectedValueException|Exception $e){
            throw new RouterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Check if there is a valid cache for the given route name and URI.
     *
     * @param string $name The name of the routing file or group to check for caching.
     * @param string $prefix The route group prefix (e.g., 'api', 'web').
     * @param string $uri The request URI to match against cached routes.
     * 
     * @return bool Returns true if a valid cache is found, otherwise false.
     */
    private function hasCache(
        string $name, 
        string $prefix, 
        string $uri
    ): bool {
        $lock = root("/writeable/caches/routes/{$name}");
        $file = ($prefix === '' ? 'web' : $prefix) . '.php';
        $this->routes = file_exists($lock . $file) ? include_once $lock . $file : [];
        
        if(!$this->findCache($name, $uri)){
            if($file !== 'web.php'){
                $this->routes = file_exists($lock . 'web.php') ? include_once $lock . 'web.php' : [];
                return $this->findCache($name, $uri);
            }

            return false;
        }

        return true;
    }

    /**
     * Check if the loaded routes match the given URI.
     *
     * @param string $name The name of the routing group.
     * @param string $uri  The request URI to match.
     * 
     * @return bool Returns true if the routes match the URI, otherwise false.
     */
    private function findCache(string $name, string $uri): bool
    {
        if($this->routes === []){
            return false;
        }

        if($name === 'cli'){
            return true;
        }

        $pattern = $this->routes['basePattern'] ?? '/';
        $normalize = false;

        if($uri === $pattern || self::isRootPrefix($pattern, $uri, $normalize)){
            return true;
        }

        $this->routes = [];
        return false;
    }

    /**
     * Stores the current routes to a cache file.
     * 
     * @param string $name The context used for caching.
     * @param string $context The context used for caching.
     * 
     * @return bool Return true on success, false on failure.
     */
    protected function cache(string $name, string $context = 'cli'): bool
    {
        if(!self::$cache || $this->routes === []){
            return false;
        }

        $lock = root("/writeable/caches/routes/{$name}/");
        $context = ($context === '') ? 'web' : $context;
        $context = ($context !== 'cli') 
            ? ($this->getPrefix() ?? $context)
            : $context;

        try{
            if(make_dir($lock) && ($routes = var_export($this->routes, true)) !== null){
                $returnRoutes = <<<PHP
                <?php
                /**
                 * Luminova Framework $name Routes at $context
                 *
                 * @package Luminova
                 * @author Ujah Chigozie Peter
                 * @copyright (c) Nanoblock Technology Ltd
                 * @license See LICENSE file
                 */
                return $routes;
                PHP;

                return write_content($lock . $context . '.php', $returnRoutes);
            }
        }catch(AppException|Exception $e){
            logger('error', 'Failed to Cache Attributes: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Extract the first segment (prefix) from a dynamic pattern.
     * 
     * @return string|null The first segment (prefix), or null if no valid prefix is found.
     */
    private function getPrefix(): ?string
    {
        $pattern = $this->routes['basePattern'] ?? null;

        if($pattern === null){
            return null;
        }

        if($pattern === '/'){
            return 'web';
        }
        
        $matches = [];
        preg_match('/^\/([a-zA-Z0-9_-.]+)(?:\/|$)/', $pattern, $matches);
        return $matches[1] ?? 'web';
    }

    /**
     * Check if the entry is a valid file based on the current context (HMVC or MVC).
     *
     * @param SplFileInfo $entry The file entry to validate.
     * @param string $name The namespace suffix name (e.g., `App\Controller\Http` as `Http`).
     * 
     * @return bool Return true if valid, false otherwise.
     */
    private function isValidEntry(SplFileInfo $entry, string $name): bool
    {
        $name = ucfirst($name);
        if (!$entry->isFile() || $entry->getExtension() !== 'php') {
            $allowed = ($name === 'Export')
                ? ['Controllers', 'Http', 'Cli']
                : ['Controllers', $name];

            return in_array($entry->getBasename(), $allowed); 
        }

        $module = basename(dirname($entry->getPathname()));
        return ($this->hmvc 
            ? str_contains($entry->getPathname(), '/Controllers/') 
            : $entry->getBasename() !== 'Application.php'
        ) && ($name !== 'Export' && $module === $name);
    }

    /**
     * Verify if the class has a valid URI prefix for handling requests.
     *
     * @param ReflectionClass $class The class object containing the prefix attribute to validate.
     * @param string $uri The request URI path that is being checked against the prefix.
     * @param string $context The current request context to validate class.
     * 
     * @return bool Returns true if the class prefix is valid and allowed to handle the request;
     *               returns false if the prefix does not match, indicating to skip the current loop.
     * @throws RouterException If more than one prefix is defined within the same class, 
     *                         indicating a configuration error.
     */
    private function isClassUriPrefix(ReflectionClass $class, string $uri, string $context = ''): string|bool 
    {
        $prefix = $class->getAttributes(Prefix::class);

        if($prefix !== []){
            if (count($prefix) > 1) {
                throw new RouterException(sprintf(
                    'Only one Attribute "%s" is allowed per class in class: %s.', 
                    Prefix::class,
                    $class->getName(),
                ));
            }

            $instance = $prefix[0]->newInstance();
            $normalize = null;
            $pattern = '/' . trim($instance->pattern, '/');

            if($uri !== $instance->pattern && !self::isRootPrefix($pattern, $uri, $normalize)){
                return false;
            }

            $normalize ??= Router::normalizePatterns($pattern);
            $this->routes['basePattern'] = $normalize;
            
            if($instance->onError !== null){
                $this->routes['controllers']['errors'][$normalize] = $instance->onError;
            }
        }

        return true;
    }

    /**
     * Checks if the given URI matches a root prefix pattern.
     *
     * @param string $pattern The route pattern to compare against the URI. It can contain dynamic segments.
     * @param string $uri The URI to be checked against the pattern.
     * @param string|false|null &$normalize A reference to the normalized pattern after processing. 
     * 
     * @return bool Returns true if the pattern matches the root of the URI, false otherwise.
     */
    private static function isRootPrefix(string $pattern, string $uri, string|bool|null &$normalize): bool
    {
        $normalize = ($normalize === false) 
            ? $pattern 
            : Router::normalizePatterns($pattern);

        return ('/' === $normalize && '/' === $uri) 
            ? true 
            : preg_match("#^{$normalize}(\/.*)?$#", $uri) === 1;
    }

    /**
     * Validate the class for instantiation and inheritance.
     * 
     * @param ReflectionClass $class The class object containing the prefix attribute to validate.
     * 
     * @return bool Return true if class is value, otherwise false.
     */
    private function isValidClass(ReflectionClass $class): bool
    {
        return $class->isInstantiable() && !$class->isAbstract() &&
            ($class->isSubclassOf(BaseViewController::class) ||
            $class->isSubclassOf(BaseController::class) ||
            $class->implementsInterface(RouterInterface::class));
    }

    /**
     * Add error handlers for the given class based on its attributes.
     *
     * @param ReflectionClass $class The class object containing error attributes to register.
     * @param string $context The current request context to validate against the error attributes.
     * 
     * @return void
     */
    private function addErrorHandlers(ReflectionClass $class, string $context): void 
    {
        foreach ($class->getAttributes(Error::class) as $error) {
            $instance = $error->newInstance();
            if(
                $instance->onError === null || 
                !($instance->context === $context || $instance->context === 'web')
            ){
                continue;
            }

            $this->routes['controllers']['errors'][Router::normalizePatterns($instance->pattern)] = $instance->onError;
        }
    }
}