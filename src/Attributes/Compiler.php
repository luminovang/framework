<?php
/**
 * Luminova Framework Routes Attributes Compiler
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes;

use \Luminova\Luminova;
use \Luminova\Attributes\Tokenizer;
use \Luminova\Attributes\Route;
use \Luminova\Attributes\Error;
use \Luminova\Attributes\Prefix;
use \Luminova\Routing\Router;
use \Luminova\Interface\RoutableInterface;
use \Luminova\Exceptions\RouterException;
use \ReflectionClass;
use \ReflectionMethod;
use \SplFileInfo;
use \Throwable;

final class Compiler
{
    /**
     * Loaded controller files.
     * 
     * @var Tokenizer|null $parser
     */
    private static ?Tokenizer $parser = null;

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
        self::$parser ??= new Tokenizer($this->cli, $this->hmvc);
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
        return self::$parser->getRoutes();
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
    public function forHttp(string $path, string $context = '', string $uri = '/'): void
    {
        if($this->cli){
            return;
        }

        [$namespace, $fileName] = self::$parser->load(
            $path . ($this->hmvc ? '' : 'Http'), 
            'http', $context, $uri
        );
        
        if($namespace === null){
            return;
        }

        try{
            $instance = new ReflectionClass($namespace);
        }catch(Throwable $e){
            throw new RouterException($e->getMessage(), $e->getCode(), $e);
        }

        if(!$this->isValidClass($instance) || !$this->isClassUriPrefix($instance, $uri)){
            return;
        }

        Luminova::addClassInfo('filename', $fileName);

        /**
         * Handle context attributes and register error handlers.
        */
        $this->addErrorHandlers($instance, $context);

        /**
         * Handle method attributes and create routes.
        */
        foreach ($instance->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $callback = $fileName . '::' . $method->getName();

            foreach ($method->getAttributes(Route::class) as $attribute) {
                $attr = $attribute->newInstance();

                // If group is not null, then we need to skip immediately as it for cli
                if($attr->group !== null){
                    return;
                }

                // If the route is an error handler, register it and skip. 
                if($attr->error){
                    self::$parser->routes['controllers']['errors'][Router::normalizePatterns($attr->pattern)] = $callback;
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
                    ($attr->middleware !== null && $attr->pattern === '/') &&
                    !str_starts_with(ltrim($pattern, '/'), $context)
                ) {
                    continue;
                }

                $pattern = Router::normalizePatterns($pattern);

                foreach($attr->methods as $httpMethod){
                    $to = ($attr->middleware === Route::BEFORE_MIDDLEWARE) 
                        ? 'routes_middleware' 
                        : (($attr->middleware === Route::AFTER_MIDDLEWARE) ? 'routes_after' : 'routes');

                    self::$parser->routes['controllers'][$to][$httpMethod][] = [
                        'pattern' => $pattern,
                        'callback' => $callback,
                        'middleware' => $attr->middleware === Route::BEFORE_MIDDLEWARE
                    ];
                }
                
            }
        }

        self::$parser->cache('http', $context);
    }

    /**
     * Install CLI commands from the given path.
     *
     * @param string $path Path to the directory containing command controller classes.
     * 
     * @return void
     * @throws RouterException Throws if error occurs while exporting controller routes.
     */
    public function forCli(string $path, string $command): void
    {
        if(!$this->cli){
            return;
        }

        [$namespace, $fileName] = self::$parser->load(
            $path . ($this->hmvc ? '' : 'Cli'), 
            'cli', 
            $command,
            "/$command"
        );
     
        if($namespace === null){
            return;
        }

        try{
            $instance = new ReflectionClass($namespace);
        }catch(Throwable $e){
            throw new RouterException($e->getMessage(), $e->getCode(), $e);
        }

        if(!$this->isValidClass($instance)){
            return;
        }

        Luminova::addClassInfo('filename', $fileName);

        foreach ($instance->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $callback = $fileName . '::' . $method->getName();

            foreach ($method->getAttributes(Route::class) as $attribute) {
                $attr = $attribute->newInstance();

                if(!$this->cli || $attr->group === null){
                    continue;
                }

                $group = trim($attr->group, '/');

                if($attr->middleware !== null){
                    $security = ($attr->middleware === Route::GLOBAL_MIDDLEWARE) ? $attr->middleware : $group;
                    self::$parser->routes['controllers']['cli_middleware']['CLI'][$security][] = [
                        'callback' => $callback,
                        'pattern' => $group,
                        'middleware' => true
                    ];
                    continue;
                }

                self::$parser->routes['controllers']['cli_groups'][$group][] = [
                    'pattern' => Router::normalizePatterns($attr->pattern),
                    'callback' => $callback
                ];
            }
        }
    
        self::$parser->cache('cli', $command);
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
        $files = self::$parser->iterator($path, 'export');

        foreach ($files as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
           
            if (!$fileName) {
                continue;
            }

            [$namespace, $module] = $this->getNamespace($file, null);
            try {
                $instance = new ReflectionClass("{$namespace}Http\\{$fileName}");
            } catch (Throwable $e) {
                try {
                    $instance = new ReflectionClass("{$namespace}Cli\\{$fileName}");
                } catch (Throwable $e) {
                    throw new RouterException($e->getMessage(), $e->getCode(), $e);
                }
            }     

            if (!(
                $instance->isInstantiable() && 
                !$instance->isAbstract() && 
                $instance->implementsInterface(RoutableInterface::class)
            )) {
                continue;
            }

            foreach ($instance->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $callback = $fileName . '::' . $method->getName();

                foreach ($method->getAttributes(Route::class) as $attribute) {
                    $attr = $attribute->newInstance();
                    
                    if($attr->group !== null){
                        $group = trim($attr->group, '/');
                        self::$parser->routes['controllers']['cli'][$module][$group][] = [
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

                        self::$parser->routes['controllers'][$context][$module][$bind][] = [
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
    private function getNamespace(SplFileInfo $file, string|null $suffix = 'Http'): array 
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
     * Verify if the class has a valid URI prefix for handling requests.
     *
     * @param ReflectionClass $class The class object containing the prefix attribute to validate.
     * @param string $uri The request URI path that is being checked against the prefix.
     * 
     * @return bool Returns true if the class prefix is valid and allowed to handle the request;
     *               returns false if the prefix does not match, indicating to skip the current loop.
     * @throws RouterException If more than one prefix is defined within the same class, 
     *                         indicating a configuration error.
     */
    private function isClassUriPrefix(ReflectionClass $class, string $uri): string|bool 
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

            if($uri !== $instance->pattern && !Tokenizer::isRootPrefix($pattern, $uri, $normalize)){
                return false;
            }

            self::$parser->routes['basePattern'] = $normalize ?? Router::normalizePatterns($pattern);
            
            if($instance->onError !== null){
                self::$parser->routes['controllers']['errors'][self::$parser->routes['basePattern']] = $instance->onError;
            }
        }

        return true;
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
        return $class->isInstantiable() 
            && !$class->isAbstract() 
            && $class->implementsInterface(RoutableInterface::class);
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

            self::$parser->routes['controllers']['errors'][Router::normalizePatterns($instance->pattern)] = $instance->onError;
        }
    }
}