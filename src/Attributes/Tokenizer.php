<?php
/**
 * Luminova Framework Routes Attributes Tokenization
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes;

use \Luminova\Routing\Router;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\RouterException;
use \Luminova\Base\BaseController;
use \Luminova\Base\BaseCommand;
use \Luminova\Interface\RoutableInterface;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RecursiveCallbackFilterIterator;
use \FilesystemIterator;
use \SplFileInfo;
use \PhpToken;
use \Throwable;

final class Tokenizer
{
    /**
     * Extracted routes from attributes.
     * 
     * @var array<string,array|string> $routes
     */
    public array $routes = [];

    /**
     * Weather routing attributes cache is enabled.
     * 
     * @var bool $isCacheable
     */
    private bool $isCacheable = false;

    /**
     * Allowed routable controller classes.
     * 
     * @var array $routable
     */
    private array  $routable = [
        'T_EXTENDS' => [],
        'T_IMPLEMENTS' => ['RoutableInterface', '\Luminova\Interface\RoutableInterface', RoutableInterface::class]
    ];

    /**
     * Constructor to initialize the compiler.
     *
     * @param bool $cli Flag indicating if running in CLI mode.
     * @param bool $hmvc Flag indicating if running application with hmvc module.
     */
    public function __construct(private bool $cli = false, private bool $hmvc = false)
    {
        $this->isCacheable = (bool) env('feature.route.cache.attributes', false);
     
        // Throw exceptions on cli mode
        if($this->cli){
            $this->routable['T_EXTENDS'] = ['BaseCommand', '\Luminova\Base\BaseCommand', BaseCommand::class];

            setenv('throw.cli.exceptions', 'true');
            return;
        }

        $this->routable['T_EXTENDS'] = ['BaseController', '\Luminova\Base\BaseController', BaseController::class];
    }

    /**
     * Get the collected routable controllers.
     *
     * @return array Return array of collected routes.
     */
    public function getRoutes(): array
    {
        return $this->routes['controllers'] ?? [];
    }

    /**
     * Set if attributes should be cached for later use or not.
     * 
     * @param bool $cache Weather cache is supported or not.
     *
     * @return self Return instance of tokenizer.
     */
    public function cacheable(bool $cache = true): self
    {
        $this->isCacheable = $cache;
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
     * @return array Returns an array of class-namespace and file name if no cache is found, 
     *                  otherwise returns array of null values on successful or failure.
     * @throws RouterException Throws if error occurs while loading controllers.
     */
    public function load(
        string $path, 
        string $name, 
        string $prefix = Router::CLI_URI, 
        string $uri = '/'
    ): array
    {
        if ($this->isCacheable && $this->hasCache($name, $prefix, $uri)) {
            return [null, null];
        }

        // HMVC module name must follow strict naming pattern
        if(!$this->cli && $this->hmvc && $prefix && $prefix !== '' && $prefix !== Router::CLI_URI){
            $path .= pascal_case($prefix);
        }

        $attrClass = $this->cli ? 'Group' : 'Prefix';

        foreach ($this->iterator($path, $name) as $file) {
            $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);

            $classNamespace = $this->scanFileForMatchingClass(
                $file->getPathname(), 
                $fileName,
                $uri,
                $attrClass
            );

            if ($classNamespace !== null) {
                return [$classNamespace, $fileName];
            }
        }
        exit();

        return [null, null];
    }

    /**
     * Creates a recursive iterator for routable controller directory path.
     *
     * @param string $path The root path to scan.
     * @param string $name The class name used for entry validation.
     * 
     * @return RecursiveIteratorIterator The iterator over valid entries.
     * @throws RouterException If an error occurs during directory iteration.
     */
    public function iterator(string $path, string $name): RecursiveIteratorIterator
    {
        try{
            return new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(
                        root($path), 
                        FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
                    ),
                    fn(SplFileInfo $entry) => $this->isValidEntry($entry, $name)
                )
            );
        }catch(Throwable $e){
            throw new RouterException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Stores the current routes to a cache file.
     * 
     * @param string $context The cache context name (e.g, `cli` or `http`).
     * @param string $prefix The URI prefix or context-prefix.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function cache(string $context, string $prefix = 'cli'): bool
    {
        if(!$this->isCacheable || $this->routes === []){
            return false;
        }

        $lock = root("/writeable/caches/routes/{$context}/");
        $prefix = ($prefix === 'cli' || $this->cli) 
            ? ($prefix ?: 'cli')
            : ($this->getPrefix() ?? ($prefix ?: 'web'));

        try{
            if (make_dir($lock) && ($routes = $this->varMinifyExport($this->routes, true)) !== null) {
                $now = date('F j, Y • g:i A');
                $contents = <<<PHP
                <?php
                /**
                 * Auto-generated Luminova Route Attributes.
                 * Context: {$context}
                 * Prefix: {$prefix}
                 * Generated at: {$now}
                 *
                 * @package Luminova
                 * @author Ujah Chigozie Peter
                 * @copyright (c) Nanoblock Technology Ltd
                 * @license See LICENSE file
                 * @link https://luminova.ng
                 *
                 * > Note: This file is automatically generated.
                 * > You can delete it if necessary to refresh cached routes.
                 * > Frequent deletion may impact performance.
                 */
                return {$routes};
                PHP;
                
                return write_content($lock . $prefix . '.php', $contents);
            }            
        }catch(Throwable $e){
            Logger::dispatch('error', 'Failed to cache controllers attributes. ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Minify array before caching.
     * 
     * @param array The routes to minify.
     * 
     * @return string|null Return minified array string or null.
     */
    public function varMinifyExport(array $data): ?string
    {
        $data = var_export($data, true);

        if ($data === null) {
            return null;
        }

        return preg_replace(
            ['/\s+=>\s+/', '/,\s+/', '/\s+\)/', '/array\s+\(\s+/', '/\n+/',], 
            ['=>', ',', ')', 'array(', '',], 
            $data
        );
    }

    /**
     * Scans a file to find a class with a matching 'Prefix' attribute.
     *
     * @param string $filePath  Full path to the PHP file.
     * @param string $className The expected class name.
     * @param string $url The Retuest URL to match.
     * @param string $attrClass The attribute class to match.
     * 
     * @return string|null Fully-qualified class name if found, or null.
     */
    private function scanFileForMatchingClass(
        string $filePath, 
        string $className, 
        string $url,
        string $attrClass
    ): ?string
    {
        $tokens = PhpToken::tokenize(file_get_contents($filePath) ?: '');

        if ($tokens === []) {
            return null;
        }

        $namespace = '';
        $attributes = [];
        $mode = null;

        for ($i = 0, $c = count($tokens); $i < $c; $i++) {
            $token = $tokens[$i];

            if (!$token instanceof PhpToken || $token->isIgnorable()) {
                continue;
            }

            if ($token->is([T_EXTENDS, T_IMPLEMENTS])) {
                $mode = $token->getTokenName();
                continue;
            }

            if ($token->is(T_NAMESPACE)) {
                [$namespace, $i] = $this->parseNamespace($tokens, $i);
            }

            if ($token->is(T_ATTRIBUTE)) {
                [$attribute, $class, $i] = $this->parseAttribute($tokens, $attrClass, $i);

                if($attribute && $this->equals($attrClass, $class)){
                    $attributes[] = $attribute;
                }
            }

            if($mode === null){
                continue;
            }
            
            if(in_array((string) $token, $this->routable[$mode], true)){
                // Already end of class metadata search.
                // Only match classes with 'Prefix' attribute
                if($attributes === [] || !$namespace || !$this->equals($attrClass, $class)){
                    return $mode = null;
                }

                foreach ($attributes as $attr) {
                    if($attr === ''){
                        continue;
                    }

                    if ($this->isMatch($attr, $url)) {
                        return "$namespace\\$className";
                    }
                }

                $attributes = [];
                return $mode = null;
            }

            // End of class search
            if ($token->is('{')) {
                return $mode = null;
            }
        }

        return $mode = null;
    }
 
    /**
     * Checks if the attribute pattern matches the starting URL.
     *
     * @param string $pattern  The attribute value to check.
     * @param string $url The Request URL to match against.
     * 
     * @return bool Return true if pattern matches the start URL, false otherwise.
     */
    private function isMatch(string $pattern, string $url): bool
    {
        $pattern = trim($pattern, "/,' ");
        $prefix = $this->cli ? 'name' : 'pattern';

        if(str_starts_with($pattern, $prefix)){
            $pattern = substr($pattern, strlen($prefix));
        }

        $pattern = '/' . trim($pattern, "/,' ");
        $normalize = null;

        if($this->cli){
            return $url === $pattern;
        }

        return self::isRootPrefix($pattern, $url, $normalize);
    }

    /**
     * Checks if the attribute class matches the extracted attribute constructor.
     *
     * @param string $attr Attribute class name.
     * @param string $value Extracted attributed class name or qualified name.
     * 
     * @return bool Return true if matches, false otherwise.
     */
    private function equals(string $attr, string $value): bool 
    {
        if($attr === $value){
            return true;
        }

        $attr = $this->cli 
            ? Group::class 
            : Prefix::class;

        return $value === $attr;
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
    public static function isRootPrefix(string $pattern, string $uri, string|bool|null &$normalize): bool
    {
        $normalize = ($normalize === false) ? $pattern : Router::normalizePatterns($pattern);

        if('/' === $uri && self::isHome($normalize)){
            return true;
        }

        return preg_match("#^{$normalize}(\/.*)?$#", $uri) === 1;
    }

    /**
     * Parses the namespace declaration from tokens.
     *
     * @param PhpToken[] $tokens Tokenized PHP source.
     * @param int $index  Current position in the token array.
     * 
     * @return array Return an array of [string $namespace, int $newIndex].
     */
    private function parseNamespace(array $tokens, int $index): array
    {
        $namespace = '';

        for ($i = $index + 1, $c = count($tokens); $i < $c; $i++) {
            $token = $tokens[$i];

            if (!($token instanceof PhpToken) || $token->isIgnorable()) {
                continue;
            }

            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $tokenString = (string) $token;

                // Class can only contain one namespace.
                // So return as this is not a controller class
                if(!str_contains($tokenString, 'Controllers')){
                    return ['', $i];
                }

                // If next token is ';', namespace ended
                $next = $tokens[$i + 1] ?? null;

                if ($next instanceof PhpToken && $next->is(';')) {
                    return [$tokenString, $i + 1];
                }

                continue;
            }

            // Probably chunk namespace, build it
            if ($token->is([T_STRING, T_NS_SEPARATOR])) {
                $namespace .= (string) $token;
            }

            if ($token->is(';')) {
                break;
            }
        }

        if(!str_contains($namespace, 'Controllers')){
            $namespace = '';
        }

        return [$namespace, $i];
    }

    /**
     * Parses an attribute block and captures its arguments if it's a 'Prefix' attribute.
     *
     * @param PhpToken[] $tokens Tokenized PHP source.
     * @param int &$index Current index, passed by reference.
     * 
     * @return array Return an array [string $arguments, string $className, int $newIndex]
     */
    private function parseAttribute(array $tokens, string $attrClass, int &$index): array
    {
        $class = '';
        $arguments = '';
        $skipError = false;
        $isAllowed = false;
        $captureBlock = false;

        for ($i = $index + 1, $c = count($tokens); $i < $c; $i++) {
            $token = $tokens[$i];
            $last = $tokens[$i - 1] ?? null;

            if (!($token instanceof PhpToken) || $token->isIgnorable()) {
                continue;
            }

            $tokenString = (string) $token;

            if(!$isAllowed && $token->is($attrClass)){
                $isAllowed = true;
                $class = $tokenString;
                continue;
            }

            // Finish search
            if ($isAllowed && $token->is(')')) {
                $captureBlock = false;
                $isAllowed = false;
                break;
            }

            if(!$skipError &&  (
                $token->is('onError') || 
                $token->is([T_CALLABLE, T_FN, T_FUNCTION]) ||
                ($token->is(T_CLASS) && ($last instanceof PhpToken) && $last->is(T_DOUBLE_COLON))
            )){
                $skipError = true;
                continue;
            }

            // If skip and is end of error closure attribute
            // Stop skipping to allow next attribute if any
            if ($skipError && $token->is(']')) {
                $skipError = false;
                continue;
            }

            if ($skipError) {
                continue;
            }

            if ($isAllowed) {
                if ($token->is('(')) {
                    $captureBlock = true;
                    continue;
                }

                // If not a named param and already allowed.
                // If the value is start of error closure attribute then it's start skipping
                if($token->is('[')){
                    $skipError = true;
                    continue;
                }

                if ($token->is(T_CLASS)) {
                    if($captureBlock && $skipError){
                        continue;
                    }
                }

                if ($captureBlock && $token->is(T_CONSTANT_ENCAPSED_STRING)) {
                    $arguments .= $tokenString;
                }
            }
        }

        return [$arguments, $class, $i];
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
    ): bool 
    {
        $lock = root("/writeable/caches/routes/{$name}");
        $file = ($prefix ?: 'web') . '.php';

        $this->routes = file_exists($lock . $file) ? include_once $lock . $file : [];
        
        if($this->findCache($uri)){
            return true;
        }

        // If the search is already for root context, return false.
        if($file === 'web.php'){
            return false;
        }

        $this->routes = file_exists($lock . 'web.php') ? include_once $lock . 'web.php' : [];
 
        return $this->findCache($uri);
    }

    /**
     * Check if the loaded routes match the given URI or Command.
     *
     * @param string $uri The request URI or command group name to match.
     * 
     * @return bool Returns true if the URI match, otherwise false.
     */
    private function findCache(string $uri): bool
    {
        if($this->routes === []){
            return false;
        }

        // if($name === 'cli'){
        //    return true;
        // }

        $pattern = $this->routes['basePattern'] ?? '/';
        $normalize = false;

        if($uri === $pattern || self::isRootPrefix($pattern, $uri, $normalize)){
            return true;
        }

        $this->routes = [];
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
    public static function isHome(string $pattern): bool
    {
        return in_array($pattern, [
            '/', '/*', '/?', 
            '/.*', '/?.*', '/?.*?', 
            '/?(.*)', '/?(.*)?', 
            '/.', '/-', '/_', '.', '-', '_'
        ], true);
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
        $filename = $entry->getBasename();

        if(str_starts_with($filename, '.')){
            return false;
        }

        if (!$entry->isFile()) {
            $filters = ['Views', 'Models'];

            if($this->hmvc){
                $filters[] = $this->cli ? 'Http' : 'Cli';
            }

            return !in_array($filename, $filters, true); 
        }

        if($entry->getExtension() !== 'php'){
            return false;
        }

        $pathname = $entry->getPathname();
        $name = ucfirst($name);

        if($name === 'Export' || ($this->hmvc && !str_contains($pathname, '/Controllers/'))){
            return false;
        }

        return basename(dirname($pathname)) === $name;
    }
}