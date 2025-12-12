<?php
declare(strict_types=1);
/**
 * Luminova Framework Routes Attributes Compiler
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes\Internal;

use \Throwable;
use \SplFileInfo;
use Luminova\Boot;
use \ReflectionClass;
use \ReflectionMethod;
use Luminova\Luminova;
use Luminova\Routing\Router;
use Luminova\Exceptions\RouterException;
use Luminova\Interface\RoutableInterface;
use Luminova\Attributes\Internal\Tokenizer;
use Luminova\Attributes\{Route, Error, Prefix};

final class Compiler
{
    /**
     * Loaded controller files.
     * 
     * @var Tokenizer|null $parser
     */
    private static ?Tokenizer $parser = null;

    /**
     * Optimize and sort routes.
     *
     * @var bool|null $isOptimizable
     */
    private static ?bool $isOptimizable = null;

    /**
     * Sortables route context.
     *
     * @var array $sortables
     */
    private static array $sortables = [
        'http.routes'       => true,
        'http.after'        => true,
    ];

    /**
     * Max base segment weight.
     * 
     * @var int BASE_WEIGHT
     */
    private const BASE_WEIGHT = 1000000;

    /**
     * Maximum segment weight.
     * 
     * @var int MAX_WEIGHT
     */
    private const MAX_WEIGHT = 950000;

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
     * @param string $uri The full request URL paths.
     * 
     * @return void
     * @throws RouterException Throws if error occurs while exporting controller routes.
     */
    public function forHttp(string $path, string $context = '', string $uri = '/'): void
    {
        if($this->cli){
            return;
        }

        [$namespace, $fileName, $isExcluded, $subPrefix] = self::$parser->load(
            $path . ($this->hmvc ? '' : 'Http'), 
            'http', 
            $context, 
            $uri
        );
        
        Boot::add(Boot::CLASS_METADATA, 'controllers', self::$parser->searches);

        if($namespace === null){
            return;
        }

        try{
            $instance = new ReflectionClass($namespace);
        }catch(Throwable $e){
            throw new RouterException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        if(
            !$this->isValidClass($instance) 
            || !$this->isClassUriPrefix($instance, $uri)
        ){
            return;
        }

        self::$isOptimizable ??= (bool) env('route.optimize.attributes', true);

        Boot::add(Boot::CLASS_METADATA, 'filename', $fileName);

        /**
         * Handle context attributes and register error handlers.
         */
        $this->addErrors($instance, $context);

        /**
         * Handle method attributes and create routes.
         */
        foreach ($instance->getMethods(ReflectionMethod::IS_PUBLIC) as $handler) {
            $callback = $fileName . '::' . $handler->getName();

            foreach ($handler->getAttributes(Route::class) as $attribute) {
                $attr = $attribute->newInstance();

                // If group is not null, then we need to skip immediately as it for cli
                if($attr->group !== null){
                    return;
                }

                // If the route is an error handler, register it and skip. 
                if($attr->error){
                    $this->addError($attr->pattern, $callback);

                    if($attr->aliases){
                        $this->addAliases($attr, $callback);
                    }

                    continue;
                }

                $pattern = '';

                /**
                 * Process the matched context against patterns (e.g., `/foo/(:placeholder)`).
                 * If the middleware and prefix are not empty, and the pattern is the base, skip processing.
                 */
                if(!$this->isPatternValid($attr->pattern, $context, $attr->middleware, $pattern)){
                    continue;
                }

                $pattern = Router::toPatterns($pattern);

                foreach($attr->methods as $method){
                    $this->addHandler($callback, $attr->middleware, $pattern, $method);

                    if($attr->aliases){
                        $this->addAliases($attr, $callback, $context, $method);
                    }
                }
            }
        }

        if(self::$isOptimizable){
            self::sort();
        }

        self::$parser->cache('http', $context, $isExcluded, $subPrefix);
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

        [$namespace, $fileName, $isExcluded, $subPrefix] = self::$parser->load(
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

        Boot::add(Boot::CLASS_METADATA, 'filename', $fileName);

        foreach ($instance->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $callback = $fileName . '::' . $method->getName();

            foreach ($method->getAttributes(Route::class) as $attribute) {
                $attr = $attribute->newInstance();

                if(!$this->cli || $attr->group === null){
                    continue;
                }

                $group = trim($attr->group, '/');

                if($group === '' || $group === '/'){
                    continue;
                }

                if($attr->middleware !== null){
                    $this->addHandler($callback, $attr->middleware, group: $group);

                    if($attr->aliases){
                        foreach($attr->aliases as $middleware){
                            $this->addHandler($callback, $middleware, group: $group);
                        }
                    }
                    continue;
                }

                $this->addHandler($callback, pattern: $attr->pattern, group: $group);

                if($attr->aliases){
                    foreach($attr->aliases as $pattern){
                        $this->addHandler($callback, pattern: $pattern, group: $group);
                    }
                }
            }
        }
    
        self::$parser->cache('cli', $command, $isExcluded, $subPrefix);
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
        $api = Luminova::getApiPrefix();

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
                        continue;
                    }

                    $this->addExportRouteHandler($attr, $attr->pattern, $callback, $module, $api);

                    if($attr->aliases){
                        foreach($attr->aliases as $pattern){
                            $this->addExportRouteHandler($attr, $pattern, $callback, $module, $api);
                        }
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Validate and normalize a route pattern for use with a given context.
     *
     * This method builds the normalized pattern using the base group and verifies
     * whether it should be processed based on the current context and middleware.
     *
     * @param string $pattern The raw route pattern (alias or path).
     * @param string $prefix The current URI prefix (e.g., first path name).
     * @param mixed  $middleware The middleware assigned to the route, if any.
     * @param string &$normalized The resulting normalized pattern (output parameter).
     *
     * @return bool Returns true if the pattern is valid for this context, otherwise false.
     */
    private function isPatternValid(
        string $pattern, 
        string $prefix, 
        mixed $middleware,
        string &$normalized
    ): bool 
    {
        $pattern = '/' . trim($pattern, '/');
        $normalized = $this->baseGroup 
            ? rtrim($this->baseGroup, '/') . $pattern
            : $pattern;

        if($prefix === '' || $middleware === null){
            return true;
        }

        if ($normalized === '/' && !str_starts_with(ltrim($normalized, '/'), $prefix)) {
            return false;
        }

        //if ($pattern === '/' && !str_starts_with(ltrim($normalized, '/'), $prefix)) {
        //    return false;
        //}

        return true;
    }

    /**
     * Register additional route aliases for a controller method.
     *
     * > Aliases can be direct error routes (when $context is null) or normal routes.
     *
     * @param Route       $attr     The route attribute object containing metadata.
     * @param string      $callback The controller class and method reference.
     * @param string|null $context  Optional routing context (e.g., group prefix).
     * @param string|null $method   Optional HTTP method (GET, POST, etc.).
     *
     * @return void
     */
    private function addAliases(Route $attr, string $callback, ?string $context = null, ?string $method = null)
    {
        foreach($attr->aliases as $alias){
            if($context === null){
                $this->addError($alias, $callback);
                continue;
            }

            $pattern = '';

            if(!$this->isPatternValid($alias, $context, $attr->middleware, $pattern)){
                continue;
            }

            $this->addHandler(
                $callback,
                $attr->middleware, 
                Router::toPatterns($pattern), 
                $method
            );
        }
    }

    /**
     * Add a normalized route pattern to the routing handlers.
     *
     * @param string $callback The controller class and method reference.
     * @param string|null $middleware The route middleware handler.
     * @param string|null $pattern The normalized URI pattern.
     * @param string|null $method The HTTP method (GET, POST, etc.) 
     * @param string|null $group CLI Group name.
     *
     * @return void
     */
    private function addHandler(
        string $callback, 
        ?string $middleware = null, 
        ?string $pattern = null, 
        ?string $method = null,
        ?string $group = null
    ): void 
    {
        if($group === null || !$this->cli){
            $context = match (true) {
                $middleware === Route::HTTP_BEFORE_MIDDLEWARE => 'http.middleware',
                $middleware === Route::HTTP_AFTER_MIDDLEWARE => 'http.after',
                default => 'http.routes'
            };

            self::$parser->routes['controllers'][$context][$method][] = [
                'pattern'       => $pattern,
                'callback'      => $callback,
                'middleware'    => $middleware === Route::HTTP_BEFORE_MIDDLEWARE,
                'score'         => self::score($pattern, $context)
            ];

            return;
        }

        if($middleware === null){
            self::$parser->routes['controllers']['cli.groups'][$group][] = [
                'pattern'   => Router::toPatterns($pattern),
                'callback'  => $callback
            ];
            return;
        }

        $context = ($middleware === Route::CLI_GLOBAL_MIDDLEWARE) 
            ? $middleware
            : $group;

        self::$parser->routes['controllers']['cli.middleware']['CLI'][$context][] = [
            'callback'      => $callback,
            'pattern'       => $group,
            'middleware'    => true
        ];
    }

    /**
     * Add a normalized route pattern to the routing errors.
     *
     * @param string $pattern
     * @param mixed $callback
     * @param bool $normalize
     * 
     * @return void
     */
    private function addError(
        string $pattern, 
        mixed $callback, 
        bool $normalize = true
    ): void 
    {
        $pattern = $normalize 
            ? Router::toPatterns($pattern) 
            : $pattern;

        self::$parser->routes['controllers']['http.errors'][$pattern] = $callback;
    }

    /**
     * Compute a route score based on its structure and context.
     *
     * Higher score means higher priority in matching.
     * Score is cached per route string to avoid recomputation during sorting.
     *
     * @param string $route   Route pattern (e.g. /user/{id})
     * @param string $context Route context group (e.g. http.routes)
     *
     * @return int Computed priority score
     */
    private static function score(string $route, string $context): int
    {
        if (!self::$isOptimizable || !isset(self::$sortables[$context])) {
            return 0;
        }

        static $cache = [];

        if (isset($cache[$route])) {
            return $cache[$route];
        }

        $route = trim($route, '/');

        if ($route === '') {
            // Root /
            return $cache[$route] = self::BASE_WEIGHT;
        }

        $segments = self::toSegments($route);

        $score = count($segments) * 10;

        foreach ($segments as $segment) {
            $score += self::weight($segment);
        }

        return $cache[$route] = min(self::MAX_WEIGHT, $score);
    }

    /**
     * Split segments to array.
     *
     * @param string $route
     * 
     * @return array
     */
    private static function toSegments(string $route): array
    {
        $segments = [];
        $buffer = '';
        $length = strlen($route);
        $depth = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $route[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === '/' && $depth === 0) {
                if ($buffer !== '') {
                    $segments[] = $buffer;
                    $buffer = '';
                }

                continue;
            }

            $buffer .= $char;
        }

        if ($buffer !== '') {
            $segments[] = $buffer;
        }

        return $segments;
    }

    /**
     * Sort all registered routes by priority score.
     *
     * Sorting rules:
     *  1. Higher score first
     *  2. Longer pattern first (tie-breaker)
     *
     * @return void
     */
    private static function sort(): void
    {
        if (!self::$isOptimizable) {
            return;
        }

        foreach (self::$sortables as $type => $_) {
            if (empty(self::$parser->routes['controllers'][$type])) {
                continue;
            }

            foreach (self::$parser->routes['controllers'][$type] as &$routes) {

                usort($routes, static function ($a, $b) {
                    $cmp = $b['score'] <=> $a['score'];
                    if ($cmp !== 0) {
                        return $cmp;
                    }

                    return strlen($b['pattern'] ?? '') <=> strlen($a['pattern'] ?? '');
                });

                unset($routes);
            }
        }
    }

    /**
     * Compute weight of a single route segment.
     *
     * Weight rules:
     *  - static segment = highest priority
     *  - placeholders lower priority
     *  - wildcards lowest priority
     *
     * @param string $segment Route segment
     * @return int Weight score
     */
    private static function weight(string $segment): int
    {
        // Root /
        if ($segment === '' || $segment === '/') {
            return self::BASE_WEIGHT;
        }

        // Wildcard / catch-all
        if ($segment === '*' || $segment === '.*' || $segment === '(.*)') {
            return 0;
        }

        $first = $segment[0];

        // Static segment
        if ($first !== '(' && $first !== '{') {
            return 100;
        }

        // Placeholder parameter: {id}
        if ($first === '{') {
            return 60;
        }

        // Optional regex pattern
        if ($first === '?' || str_starts_with($segment, '?(?:')) {
            return 20;
        }

        // Generic regex group
        if ($first !== '(') {
            return 50;
        }

        // Named route patterns
        if (str_starts_with($segment, '(?:')) {
            return 45;
        }

        // Custom optional groups
        if (str_starts_with($segment, '(:')) {
            return match (true) {
                str_ends_with($segment, ':optional)'),
                str_ends_with($segment, ':base)'),
                str_ends_with($segment, ':root)') => 25,
                default => 50,
            };
        }

        $pipes = self::countGroupPipes($segment);

        // Regex
        if ($pipes === null) {
            return 70;
        }

        return min(self::MAX_WEIGHT - 100, 15 + ($pipes * 5));
    }

    /**
     * Count number of pipes in group segment pattern.
     *
     * @param string $segment
     * @return int|null
     */
    private static function countGroupPipes(string $segment): ?int
    {
        if ($segment[-1] !== ')') {
            return null;
        }

        if(str_contains($segment, '|')){
            return substr_count($segment, '|');
        }

        if(strlen($segment) > 2){
            return 1;
        }

        return null;
    }

    /**
     * Add an exported route pattern to the controller routing table.
     *
     * This method prepares a bind key from the pattern and determines whether
     * the route belongs to an API prefix or a standard HTTP context. The route
     * is then stored in the export routing table, grouped by module and bind key.
     *
     * @param Route  $attr     The route attribute object containing metadata.
     * @param string $pattern  The normalized URI pattern.
     * @param string $callback The controller class and method reference.
     * @param string $module   The MVC or HMVC module name.
     * @param string $api      The API prefix name to check against the bind key.
     *
     * @return void
     */
    private function addExportRouteHandler(
        Route $attr, 
        string $pattern, 
        string $callback, 
        string $module, 
        string $api
    ): void 
    {
        $bind = ($pattern === '/') ? '/' : trim($pattern, '/');
        $list = ($bind !== '/' && str_contains($bind, '/')) ? explode('/', $bind) : [];
        $bind = ($list === []) 
            ? $bind :
            (($list[0] === $api) ? $list[1] : $list[0]);

        $context = ($bind !== '/' && str_starts_with($bind, $api)) ? $api : 'http';

        self::$parser->routes['controllers'][$context][$module][$bind][] = [
            'bind'          => $bind,
            'callback'      => $callback,
            'methods'       => $attr->methods,
            'pattern'       => $pattern,
            'middleware'    => $attr->middleware
        ];
    }

    /**
     * Resolve controller namespace based on filesystem structure.
     *
     * Supports both MVC and HMVC layouts:
     * - MVC: App\Controllers\{Http|Cli}
     * - HMVC: App\Modules\{Module}\Controllers\{Http|Cli}
     *
     * @param SplFileInfo $file   Controller file reference
     * @param string|null $suffix Namespace suffix (Http, Cli, etc.)
     *
     * @return array{0: string, 1: string} [namespace, module]
     *
     * @throws RouterException If HMVC structure is invalid or unsupported.
     */
    private function getNamespace(SplFileInfo $file, ?string $suffix = 'Http'): array
    {
        $suffix = $suffix ? $suffix . '\\' : '';
        $path   = $file->getPathname();

        if (!$this->hmvc) {
            return [
                "\\App\\Controllers\\{$suffix}",
                basename(dirname($path, 2))
            ];
        }

        $module = $this->extractModuleName($path);

        if ($module === null) {
            throw new RouterException(sprintf(
                'Invalid HMVC module structure. Controller must be inside: %s/Controllers/%s',
                '/app/Modules/{Module}',
                $this->cli ? 'Cli' : 'Http'
            ));
        }

        $namespace = ($module === 'Controllers') ? '' : $module . '\\';

        return [
            "\\App\\Modules\\{$namespace}Controllers\\{$suffix}",
            $module
        ];
    }

    /**
     * Extract module name from HMVC path.
     *
     * Expected structure:
     * /app/Modules/{Module}/Controllers/...
     *
     * @param string $path
     * @return string|null
     */
    private function extractModuleName(string $path): ?string
    {
        // $matches = [];
        // if(preg_match('~/app/Modules/([^/]+)/~', $path, $matches)){
        //    return $matches[1] ?? 'Controllers';
        // }
        // return null;

        $prefix = '/app/Modules/';
        $pos = strpos($path, $prefix);

        if ($pos === false) {
            return null;
        }

        $offset = $pos + strlen($prefix);
        $nextSlash = strpos($path, '/', $offset);

        if ($nextSlash === false) {
            return null;
        }

        return substr($path, $offset, $nextSlash - $offset);
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

        if($prefix === []){
            return true;
        }

        if (count($prefix) > 1) {
            throw new RouterException(sprintf(
                'Only one Attribute "%s" is allowed per class in class: %s.', 
                Prefix::class,
                $class->getName(),
            ));
        }

        $instance = $prefix[0]->newInstance();
        $normalize = null;
        $pattern = $instance->mergeExcluders 
            ? Tokenizer::excluder($instance->pattern, $instance->exclude)
            : '/' . trim($instance->pattern, '/');

        if(
            $uri !== $instance->pattern && 
            $uri !== $pattern && 
            !Tokenizer::isControllerPrefix($pattern, $uri, $normalize, !$instance->mergeExcluders)
        ){
            return false;
        }

        $pattern = $normalize ?? Router::toPatterns($pattern);
    
        self::$parser->routes['basePattern'] = $pattern;
        self::$parser->routes['rawBasePattern'] = $instance->pattern;
        self::$parser->routes['excluders'] = $instance->mergeExcluders ? [] : $instance->exclude;
        
        if($instance->onError !== null){
            $this->addError($pattern, $instance->onError, false);
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
    private function addErrors(ReflectionClass $class, string $context): void 
    {
        foreach ($class->getAttributes(Error::class) as $error) {
            $instance = $error->newInstance();

            if($instance->context === $context || $instance->context === 'web'){
                continue;
            }

            $this->addError($instance->pattern, $instance->onError);
        }
    }
}