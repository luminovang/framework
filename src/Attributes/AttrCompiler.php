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
use \Luminova\Interface\RouterInterface;
use \Luminova\Exceptions\RouterException;
use \WeakMap;
use \ReflectionClass;
use \ReflectionMethod;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RecursiveCallbackFilterIterator;
use \FilesystemIterator;
use \SplFileInfo;
use \Throwable;

final class AttrCompiler
{
    /**
     * Extracted routes from attributes.
     * 
     * @var array<string,array|string> $routes
     */
    private array $routes = [];

    /**
     * Weather routing attributes cache is enabled.
     * 
     * @var bool $cache
     */
    private static bool $cache = false;

    /**
     * Loaded controller files.
     * 
     * @var WeakMap|null $weak
     */
    private static ?WeakMap $weak = null;

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
        self::$weak = new WeakMap();
        // Throw an exceptions error on cli
        if($this->cli){
            setenv('throw.cli.exceptions', 'true');
        }
    }

    /**
     * Forces collection of any existing garbage cycles.
     */
    public function __destruct() 
    {
        gc_collect_cycles();
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

        $count = 0;

        foreach ($files as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
            $count++;

            if (!$fileName) {
                continue;
            }

            try{
                [$namespace] = $this->getNamespace($file);
                self::$weak[$file] = new ReflectionClass("{$namespace}{$fileName}");
            }catch(Throwable $e){
                throw new RouterException($e->getMessage(), $e->getCode(), $e);
            }

            if(
                !$this->isValidClass(self::$weak[$file]) || 
                !$this->isClassUriPrefix(self::$weak[$file], $uri, $context)
            ){
                continue;
            }

            Router::setClassInfo('filename', $fileName);

            /**
             * Handle context attributes and register error handlers.
            */
            $this->addErrorHandlers(self::$weak[$file], $context);

            /**
             * Handle method attributes and create routes.
            */
            foreach (self::$weak[$file]->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $callback = $fileName . '::' . $method->getName();

                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $attr = $attribute->newInstance();

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
                        $to = ($attr->middleware === Route::BEFORE_MIDDLEWARE) 
                            ? 'routes_middleware' 
                            : (($attr->middleware === Route::AFTER_MIDDLEWARE) ? 'routes_after' : 'routes');

                        $this->routes['controllers'][$to][$httpMethod][] = [
                            'pattern' => $pattern,
                            'callback' => $callback,
                            'middleware' => $attr->middleware === Route::BEFORE_MIDDLEWARE
                        ];
                    }
                    
                }
            }
        }

        Router::setClassInfo('attrFiles', $count?: 1);
        $this->cache('http', $context);
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

        $count = 0;
        foreach ($files as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
            $count++;

            if (!$fileName) {
                continue;
            }
        
            try{
                [$namespace] = $this->getNamespace($file, 'Cli');
                self::$weak[$file] = new ReflectionClass("{$namespace}{$fileName}");
            }catch(Throwable $e){
                throw new RouterException($e->getMessage(), $e->getCode(), $e);
            }

            if (!(self::$weak[$file]->isInstantiable() && !self::$weak[$file]->isAbstract() && (
                self::$weak[$file]->isSubclassOf(BaseCommand::class)))) {
                continue;
            }

            Router::setClassInfo('filename', $fileName);

            foreach (self::$weak[$file]->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $callback = $fileName . '::' . $method->getName();

                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $attr = $attribute->newInstance();

                    if(!$this->cli || $attr->group === null){
                        continue;
                    }

                    $group = trim($attr->group, '/');

                    if($attr->middleware !== null){
                        $security = ($attr->middleware === Route::GLOBAL_MIDDLEWARE) ? $attr->middleware : $group;
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

        Router::setClassInfo('attrFiles', $count?: 1);
        $this->cache('cli');
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

            [$namespace, $module] = $this->getNamespace($file, null);
            try {
                self::$weak[$file] = new ReflectionClass("{$namespace}Http\\{$fileName}");
            } catch (Throwable $e) {
                try {
                    self::$weak[$file] = new ReflectionClass("{$namespace}Cli\\{$fileName}");
                } catch (Throwable $e) {
                    throw new RouterException($e->getMessage(), $e->getCode(), $e);
                }
            }     

            if (!(self::$weak[$file]->isInstantiable() && !self::$weak[$file]->isAbstract() && (
                self::$weak[$file]->isSubclassOf(BaseCommand::class) || 
                self::$weak[$file]->isSubclassOf(BaseController::class) ||
                self::$weak[$file]->implementsInterface(RouterInterface::class)))) {
                continue;
            }

            foreach (self::$weak[$file]->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $callback = $fileName . '::' . $method->getName();

                foreach ($method->getAttributes(Route::class) as $attribute) {
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
        string $prefix = Router::CLI_URI, 
        string $uri = '/'
    ): RecursiveIteratorIterator|bool
    {
        if (self::$cache && $name !== 'export' && $this->hasCache($name, $prefix, $uri)) {
            return true;
        }

        try{
            return self::$weak[new static()] = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(
                        root($path), 
                        FilesystemIterator::SKIP_DOTS|FilesystemIterator::FOLLOW_SYMLINKS
                    ),
                    fn(SplFileInfo $entry) => $this->isValidEntry($entry, $name)
                )
            );
        }catch(Throwable $e){
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
     * Determines the namespace for a given file based on the application structure.
     *
     * This function generates the appropriate namespace for a controller file,
     * taking into account whether the application uses HMVC (Hierarchical Model-View-Controller)
     * or standard MVC architecture.
     *
     * @param SplFileInfo $file The file object representing the controller file.
     * @param string|null $suffix The suffix to append to the namespace, typically 'Http' or 'Cli'. Defaults to 'Http'.
     *
     * @return array An array containing two elements:
     *               - The full namespace string for the controller.
     *               - The module name (for HMVC) or the parent directory name (for MVC).
     *
     * @throws RouterException If an invalid HMVC module namespace is detected.
     */
    private function getNamespace($file, string|null $suffix = 'Http'): array 
    {
        $suffix = ($suffix === null) ? '' : $suffix . '\\';
        if (!$this->hmvc) {
            return [
                "\\App\\Controllers\\{$suffix}", 
                basename(dirname($file->getPathname(), 2))
            ];
        }
        $matches = [];

        if(preg_match('~/app/Modules/([^/]+)/~', $file->getPathname(), $matches)){
            $module = $matches[1] ?? 'Controllers';
            return [
                '\\App\Modules\\' . ($module === 'Controllers' ? '' : $module . '\\') . "Controllers\\{$suffix}",
                $module
            ];
        }

        throw new RouterException('Invalid HMVC module namespace, make sure controllers are placed in the correct directory.');
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
        }catch(Throwable $e){
            logger('error', 'Failed to Cache Attributes. ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Extract the first segment (prefix) from a prefix pattern after a leading slash.
     * 
     * @return string|null The first segment (prefix), or null if no valid prefix is found.
     */
    private function getPrefix(): ?string
    {
        $pattern = $this->routes['basePattern'] ?? null;

        if($pattern === null){
            return null;
        }

        if(self::isHome($pattern)){
            return 'web';
        }
        
        // Remove all regex patterns
        $pattern = preg_replace('/^#|#$|\/\*\?|\(\?[^\)]+\)/', '', $pattern);

        if(self::isHome($pattern)){
            return 'web';
        }

        // Remove all remaining regex patterns
        $pattern = preg_replace('/\([^\)]+\)|#|\\\|^\^|^\//', '', $pattern);
        $pattern = '/' . trim($pattern, '/');

        if(self::isHome($pattern)){
            return 'web';
        }

        $matches = [];
        preg_match('/^\/([a-zA-Z0-9_.-]+)/', $pattern, $matches);
        return $matches[1] ?? 'web';
    }

    /**
     * Checks if pattern represents a home path.
     *
     * A home path may be defined as one of the following:
     * - The root path ("/")
     * - A wildcard path ("/*" or "/.*")
     * - A path that matches any character or segment ("/?", "/.", "/-", "/_")
     *
     * @param string $pattern The route pattern to check.
     * 
     * @return bool True if the pattern is a home path, false otherwise.
     */
    private static function isHome(string $pattern): bool 
    {
        return (
            '/' === $pattern || 
            '/*' === $pattern || 
            '/?' === $pattern || 
            '/.*' === $pattern || 
            '/?.*' === $pattern || 
            '/?.*?' === $pattern || 
            '/?(.*)' === $pattern || 
            '/?(.*)?' === $pattern || 
            '/.' === $pattern || 
            '/-' === $pattern || 
            '/_' === $pattern ||
            '.' === $pattern || 
            '-' === $pattern || 
            '_' === $pattern
        );
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
        if (!$entry->isFile()) {
            return !in_array($entry->getBasename(), ['Views', 'Models', '.gitkeep', '.DS_Store']); 
        }

        if($entry->getExtension() !== 'php'){
            return false;
        }

        $name = ucfirst($name);
        return ($this->hmvc 
            ? str_contains($entry->getPathname(), '/Controllers/') 
            : $entry->getBasename() !== 'Application.php'
        ) && ($name !== 'Export' && basename(dirname($entry->getPathname())) === $name);
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

            $this->routes['basePattern'] = $normalize ?? Router::normalizePatterns($pattern);
            
            if($instance->onError !== null){
                $this->routes['controllers']['errors'][$this->routes['basePattern']] = $instance->onError;
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

        return ('/' === $uri && self::isHome($normalize)) 
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
            ($class->isSubclassOf(BaseController::class) ||
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