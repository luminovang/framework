<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Attributes;

use \Luminova\Attributes\Route;
use \Luminova\Attributes\Error;
use \Luminova\Routing\Router;
use \Luminova\Base\BaseCommand;
use \Luminova\Base\BaseController;
use \Luminova\Base\BaseViewController;
use \Luminova\Interface\RouterInterface;
use \ReflectionClass;
use \ReflectionMethod;
use \ReflectionException;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RecursiveCallbackFilterIterator;
use \FilesystemIterator;
use \SplFileInfo;
use \Luminova\Exceptions\RouterException;
use \Exception;

final class Generator
{
    /**
     * @var array<string,array> $routes
    */
    private array $routes = [];

    /**
     * @var string $namespace
    */
    private string $namespace;

    /**
     * @var bool $cli
    */
    private bool $cli = false;

    /**
     * @var bool $cache
    */
    private static bool $cache = false;

    /**
     * @var string $baseGroup
    */
    private string $baseGroup;

    /**
     * @var array<string,RecursiveIteratorIterator> $files
    */
    private static array $files = [];

    /**
     * Constructor to initialize the Generator.
     *
     * @param string $namespace Namespace for the classes.
     * @param string $baseGroup Base group for route patterns.
     * @param bool $cli Flag indicating if running in CLI mode.
     */
    public function __construct(string $namespace, string $baseGroup = '', bool $cli = false)
    {
        $this->namespace = $namespace;
        $this->baseGroup = $baseGroup;
        $this->cli = $cli;
        self::$cache = (bool) env('feature.route.cache.attributes', false);
    }

    /**
     * Install HTTP routes from the given path.
     *
     * @param string $path Path to the directory containing HTTP controller classes.
     * @param string $prefix Optional prefix for route patterns.
     * @return void
     */
    public function installHttp(string $path, string $prefix = ''): void
    {
        if($this->cli){
            return;
        }

        $files = $this->load($path, 'http');
        if($files === true){
            return;
        }

        foreach ($files as $file) {
            $fileName = $file->getBasename();
            $fileName = pathinfo($fileName, PATHINFO_FILENAME);
           
            if ($fileName) {
                try{
                    $class = new ReflectionClass("{$this->namespace}{$fileName}");
 
                    if (!($class->isInstantiable() && !$class->isAbstract() && ( 
                        $class->isSubclassOf(BaseViewController::class) ||
                        $class->isSubclassOf(BaseController::class) ||
                        $class->implementsInterface(RouterInterface::class)))) {
                        continue;
                    }
    
                    /**
                     * Handle context attributes and register error handlers.
                    */
                    foreach ($class->getAttributes(Error::class) as $context) {
                        $ctx = $context->newInstance();
                        if($ctx->onError === null || !($ctx->context === $prefix || $ctx->context === 'web')){
                            continue;
                        }

                        $this->routes['errors'][$ctx->pattern] = $ctx->onError;
                    }

                    /**
                     * Handle method attributes and create routes.
                    */
                    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                        $attributes = $method->getAttributes(Route::class);
        
                        foreach ($attributes as $attribute) {
                            $attr = $attribute->newInstance();
                            $callback = $fileName . '::' . $method->getName();
        
                            if($attr->group !== null){
                                return;
                            }

                            $pattern = $this->baseGroup . '/' . trim($attr->pattern, '/');
                            $pattern = ($this->baseGroup !== '') ? rtrim($pattern, '/') : $pattern;

                            /**
                             * Only process matched prefix.
                             * If middle and prefix is not empty and pattern is base skip.
                            */
                            if(
                                ($prefix !== '' && !str_starts_with(ltrim($pattern, '/'), $prefix)) &&
                                ($attr->middleware !== null && $prefix !== '' &&  $attr->pattern === '/')
                            ){
                                continue;
                            }

                            foreach($attr->methods as $httpMethod){
                                if($attr->error){
                                    $this->routes['errors'][$attr->pattern] = $callback;
                                }else{
                                    $to = (($attr->middleware === 'before') ? 'routes_middleware' : 
                                        (($attr->middleware === 'after') ? 'routes_after' : 'routes'));

                                    $this->routes[$to][$httpMethod][] = [
                                        'pattern' => $pattern,
                                        'callback' => $callback,
                                        'middleware' => $attr->middleware === 'before'
                                    ];
                                }
                            }
                            
                        }
                    }
                }catch(ReflectionException $e){
                    RouterException::throwException($e->getMessage(), $e->getCode(), $e);
                }
            }
        }

        $this->cache('http');
        gc_mem_caches();
    }

    /**
     * Install CLI commands from the given path.
     *
     * @param string $path Path to the directory containing command controller classes.
     * @return void
     */
    public function installCli(string $path): void
    {
        if(!$this->cli){
            return;
        }

        $files = $this->load($path, 'cli');
        if($files === true){
            return;
        }

        foreach ($files as $file) {
            $fileName = $file->getBasename();
            $fileName = pathinfo($fileName, PATHINFO_FILENAME);
           
            if ($fileName) {
                try{
                    $class = new ReflectionClass("{$this->namespace}{$fileName}");
 
                    if (!($class->isInstantiable() && !$class->isAbstract() && (
                        $class->isSubclassOf(BaseCommand::class)))) {
                        continue;
                    }

                    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                        $attributes = $method->getAttributes(Route::class);
        
                        foreach ($attributes as $attribute) {
                            $attr = $attribute->newInstance();

                            if(!$this->cli || $attr->group === null){
                                continue;
                            }

                            $callback = $fileName . '::' . $method->getName();
                            $group = trim($attr->group, '/');

                            if($attr->middleware !== null){
                                $security = ($attr->middleware === 'before' || $attr->middleware === 'global') ? 'global' : $group;
                                $this->routes['cli_middleware']['CLI'][$security][] = [
                                    'callback' => $callback,
                                    'pattern' => $group,
                                    'middleware' => true
                                ];
                            }else{
                                $this->routes['cli_groups'][$group][] = static fn(Router $router) => $router->command($attr->pattern, $callback);
                            }
                        }
                    }
                }catch(ReflectionException $e){
                    RouterException::throwException($e->getMessage(), $e->getCode(), $e);
                }
            }
        }
        $this->cache('cli');
        gc_mem_caches();
    }


    /**
     * Extract abd export all routes attributes.
     * 
     * @param string $path The path to controller classes.
     * 
     * @return self Return instance of AttributeCollector.
    */
    public function export(string $path): self
    {
        $files = $this->load($path, 'export');
        foreach ($files as $file) {
            $fileName = $file->getBasename();
            $fileName = pathinfo($fileName, PATHINFO_FILENAME);
           
            if ($fileName) {
                try{
                    $class = new ReflectionClass("{$this->namespace}{$fileName}");
 
                    if (!($class->isInstantiable() && !$class->isAbstract() && (
                        $class->isSubclassOf(BaseCommand::class) || 
                        $class->isSubclassOf(BaseViewController::class) ||
                        $class->isSubclassOf(BaseController::class) ||
                        $class->implementsInterface(RouterInterface::class)))) {
                         continue;
                    }

                    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                        $attributes = $method->getAttributes(Route::class);
        
                        foreach ($attributes as $attribute) {
                            $attr = $attribute->newInstance();
                            $callback = $fileName . '::' . $method->getName();
        
                            if($attr->group !== null){
                                $group = trim($attr->group, '/');
                                $this->routes['cli'][$group][] = [
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

                                $this->routes[$context][$bind][] = [
                                    'bind' => $bind,
                                    'callback' => $callback,
                                    'methods' => $attr->methods,
                                    'pattern' => $attr->pattern,
                                    'middleware' => $attr->middleware
                                ];
                            }
                        }
                    }
                }catch(ReflectionException $e){
                    RouterException::throwException($e->getMessage(), $e->getCode(), $e);
                }
            }
        }

        return $this;
    }

    /**
     * Loads route files from the specified path and context.
     *
     * This method attempts to load cached routes from a pre-defined cache directory
     * if the context is not 'export'. If the cached file exists, it loads the routes
     * from the file. Otherwise, it iterates through the directory to find and return
     * PHP route files.
     *
     * @param string $path The directory path to search for route files.
     * @param string $context The context used for caching.
     * 
     * @return RecursiveIteratorIterator|bool An iterator for route files or true if cached routes are loaded.
     */
    protected function load(string $path, string $context): RecursiveIteratorIterator|bool
    {
        if (self::$cache && $context !== 'export' && $lock = root('/writeable/caches/routes/')) {
            if (file_exists($file = $lock . $context . '.php')) {
                $this->routes = include_once $file;
                return true;
            }
        }

        static::$files[$path] ??= new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator(root($path), FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
                fn (SplFileInfo $entry) => $entry->isFile() && $entry->getExtension() === 'php' && $entry->getBasename() !== 'Application.php'
            )
        );

        return static::$files[$path];
    }

    /**
     * Stores the current routes to a cache file.
     *
     * This method saves the current state of routes to a PHP file in a pre-defined
     * cache directory. The routes are serialized and written to the file, which can
     * be used for faster loading in future requests.
     *
     * @param string $context The context used for caching.
     * @return bool True on success, false on failure.
     */
    protected function cache(string $context): bool
    {
        if(!self::$cache || $this->routes === []){
            return false;
        }

        $lock = root('/writeable/caches/routes/');
        try{
            if($this->cli){
                // Catch error on cli
                setenv('throw.cli.exceptions', true);
            }

            if(make_dir($lock) && $routes = var_export($this->routes, true)){
                $returnRoutes = <<<PHP
                <?php
                /**
                 * Luminova Framework
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
        }catch(Exception $e){
            logger('error', 'Attribute Cache Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Get the collected routes.
     *
     * @return array Array of collected routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}