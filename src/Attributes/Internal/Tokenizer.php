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
namespace Luminova\Attributes\Internal;

use \PhpToken;
use \Throwable;
use \SplFileInfo;
use \FilesystemIterator;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \RecursiveCallbackFilterIterator;
use \Luminova\Logger\Logger;
use \Luminova\Routing\Router;
use \Luminova\Utility\Collections\Arr;
use \Luminova\Exceptions\RouterException;
use \Luminova\Interface\RoutableInterface;
use \Luminova\Base\{Command, Controller};
use \Luminova\Attributes\{Group, Prefix};
use function \Luminova\Funcs\{
    root,
    pascal_case,
    write_content,
    make_dir
};

final class Tokenizer
{
    /**
     * Extracted routes from attributes.
     * 
     * @var array<string,array|string> $routes
     */
    public array $routes = [];

    /**
     * Whether routing attributes cache is enabled.
     * 
     * @var bool $isCacheable
     */
    private bool $isCacheable = false;

    /**
     * Number of searched controllers.
     * 
     * @var int $searches
     */
    public int $searches = 0;

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
            $this->routable['T_EXTENDS'] = ['Command', '\Luminova\Base\Command', Command::class];

            setenv('throw.cli.exceptions', 'true');
            return;
        }

        $this->routable['T_EXTENDS'] = ['Controller', '\Luminova\Base\Controller', Controller::class];
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
     * @param bool $cache Whether cache is supported or not.
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
        $this->searches = 0;

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

            $this->searches++;
        }

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
            if (make_dir($lock)) {
                $routes = !PRODUCTION 
                    ? Arr::of($this->routes)->minify() 
                    : var_export($this->routes, true);

                if($routes === null){
                    return false;
                }

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
                 * > **Note:** 
                 * > This file is automatically generated.
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
     * Scans a file to find a class with a matching 'Prefix' attribute.
     *
     * @param string $filePath  Full path to the PHP file.
     * @param string $className The expected class name.
     * @param string $url The Request URL to match.
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
                [$attribute, $class, $i, $exclude] = $this->parseAttribute($tokens, $attrClass, $i);

                if($attribute && $this->equals($attrClass, $class)){
                    $attributes[] = [
                        'exclude' => $exclude,
                        'attr' => $attribute
                    ];
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

                foreach ($attributes as $options) {
                    $attr = $options['attr'];

                    if($attr === ''){
                        continue;
                    }

                    if ($this->isMatch($attr, $url, $options['exclude'])) {
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
     * Build a regex pattern for a prefix while excluding specific paths.
     *
     * The negative lookahead ensures that the excluded paths (from root)
     * are not matched. The prefix can be root `/` or a sub-path.
     *
     * @param string $prefix The allowed base path prefix, e.g., '/', '/blog', '/api'.
     * @param array<int,string> $excludes List of prefixes to exclude from matching.
     *
     * @return string Regex pattern to use for route matching.
     *
     * @example - Examples:
     * ```
     * Tokenizer::excluder('/', ['api', 'docs', 'forum']);
     * // '/(?!api(?:/|$)|docs(?:/|$)|forum(?:/|$)).*'
     *
     * Tokenizer::excluder('/api', ['/api/admin', 'docs']);
     * // '/api(?:/.*)?(?!api/admin(?:/|$)|docs(?:/|$)).*'
     *
     * Tokenizer::excluder('/blog', ['api', 'docs', 'forum']);
     * // '/blog(?:/.*)?(?!api(?:/|$)|docs(?:/|$)|forum(?:/|$)).*'
     * 
     * Tokenizer::excluder('/', ['/api', '/docs', '/forum']);
     * // '/(?!/api(?:/|$)|/docs(?:/|$)|/forum(?:/|$)).*'
     * 
     * Tokenizer::excluder('/', ['/api/', '/docs/', '/forum/']);
     * // '/(?!/api/(?:/|$)|/docs/(?:/|$)|/forum/(?:/|$)).*'
     * 
     * Tokenizer::excluder('/', ['api/', 'docs/', 'forum/']);
     * // '/(?!api/(?:/|$)|docs/(?:/|$)|forum/(?:/|$)).*'
     * ```
     */
    public static function excluder(string $prefix, array $excludes): string
    {
        $prefix = '/' . trim($prefix, '/');

        // Remove known placeholders or trailing optional patterns
        if ($prefix !== '/' && preg_match('#(?:\(:root\)|\(:base\)|\?\(\?:/(?:\[\^/\]\.)?\.\*\)\?)$#', $prefix)) {
            $prefix = preg_replace('#(?:\(:root\)|\(:base\)|\?\(\?:/(?:\[\^/\]\.)?\.\*\)\?)$#', '', $prefix);
        }

        if ($prefix !== '/') {
            $prefix = preg_replace('#(\.\*)+$#', '', $prefix);
            $prefix = '/' . trim($prefix, '/');
        }

        if ($excludes === []) {
            return ($prefix === '/') ? '/(?:/.*)?' : "{$prefix}?(?:/.*)?";
        }

        $excludes = array_map(static fn($path) => preg_quote($path, '/'), $excludes);
        $ignores = '(?!' . implode('(?:/|$)|', $excludes) . '(?:/|$))';

        if ($prefix === '/') {
            return "/{$ignores}.*";
        }

        return "{$prefix}(?:/.*)?{$ignores}.*";
    }

    /**
     * Checks if the attribute pattern matches the starting URL.
     *
     * @param string $pattern  The attribute value to check.
     * @param string $url The Request URL to match against.
     * @param array $excluders List of prefixes or command group to exclude.
     * 
     * @return bool Return true if pattern matches the start URL, false otherwise.
     */
    private function isMatch(string $pattern, string $url, array $excluders = []): bool
    {
        $pattern = trim($pattern, "/,' ");
        $prefix = $this->cli ? 'name' : 'pattern';

        if(str_starts_with($pattern, $prefix)){
            $pattern = substr($pattern, strlen($prefix));
            $pattern = trim($pattern, "/,' ");
        }

        if($this->cli){
            $url = trim($url, '/');
            $this->assertCommandGroup($pattern);

            return $pattern && $url === $pattern;
        }

        return self::isControllerPrefix(self::excluder($pattern, $excluders), $url);
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
            ? [Group::class, 'Group']
            : [Prefix::class, 'Prefix'];

        return in_array($value, $attr, true);
    }

    /**
     * Checks if the given URI matches a root prefix pattern.
     *
     * @param string $pattern The route pattern to compare against the URI. It can contain dynamic segments.
     * @param string $uri The URI to be checked against the pattern.
     * @param string|false|null &$normalize A reference to the normalized pattern after processing. 
     * @param bool $isRoot If true add root `(\/.*)?` suffix to pattern.
     * 
     * @return bool Returns true if the pattern matches the root of the URI, false otherwise.
     */
    public static function isControllerPrefix(
        string $pattern, 
        string $uri, 
        string|bool|null &$normalize = null,
        bool $isRoot = false
    ): bool
    {
        $normalize = ($normalize === false) ? $pattern : Router::toPatterns($pattern);

        if('/' === $uri && self::isHome($normalize)){
            return true;
        }

       $pattern = $isRoot ? "{$normalize}(\/.*)?" : $normalize;

        return preg_match("#^{$pattern}$#x", $uri) === 1;
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
        $exclude = [];
        $length = count($tokens);

        $isError = false;
        $isAllowed = false;
        $isExcluder = false;
        $isBlock = false;

        for ($i = $index + 1; $i < $length; $i++) {
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
                $isBlock = false;
                $isAllowed = false;
                break;
            }

            if(!$isError &&  (
                $token->is('onError') || 
                $token->is([T_CALLABLE, T_FN, T_FUNCTION]) ||
                ($token->is(T_CLASS) && ($last instanceof PhpToken) && $last->is(T_DOUBLE_COLON))
            )){
                $isError = true;
                continue;
            }

            if(
                !$isExcluder && $this->equals($attrClass, 'Prefix') && 
                ($token->is('exclude') || (!$isError && $token->is(T_ARRAY)))
            ){
                $isExcluder = true;
                continue;
            }

            // If skip and is end of error closure attribute
            // Stop skipping to allow next attribute if any
            if (($isError || $isExcluder) && $token->is(']')) {
                $isError = false;
                $isExcluder = false;
                continue;
            }

            if ($isError) {
                continue;
            }

            if ($isAllowed) {

                if ($token->is('(') || ($isExcluder && $token->is('['))) {
                    $isBlock = true;
                    continue;
                }

                // If not a named param and already allowed.
                // If the value is start of error closure attribute then it's start skipping
                if($token->is('[')){
                    $isError = true;
                    continue;
                }

                if ($isBlock && $isError && $token->is(T_CLASS)) {
                    continue;
                }

                if ($isBlock && $token->is(T_CONSTANT_ENCAPSED_STRING)) {
                    if($isExcluder){
                        $exclude[] = trim($tokenString, " \t\n\r\0\x0B'\"");
                    }else{
                        $arguments .= $tokenString;
                    }
                }
            }
        }

        return [trim($arguments, " \t\n\r\0\x0B'\""), $class, $i, $exclude];
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
        $excluders = $this->routes['excluders'] ?? [];
        $isRoot = true;

        if($excluders !== []){
            $isRoot = false;
            $pattern = self::excluder($pattern, $excluders);
        }

        if($uri === $pattern || self::isControllerPrefix($pattern, $uri, isRoot: $isRoot)){
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
            '/.*', '/?.*', '/?.*?', '/?(?:/.*)?',
            '/?(.*)', '/?(.*)?', '/(?:/.*)?',
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

    /**
     * Assert command group name
     * 
     * @param string $name
     */
    private function assertCommandGroup(string $name): void
    {
        if ($name && !preg_match('/^[a-z][a-z0-9_:-]*$/', $name)) {
            throw new RouterException(sprintf(
                'Invalid CLI group name "%s". A valid command group must start with a letter and can contain only lowercase letters, numbers, hyphens (-), underscores (_), or colons (:).',
                $name
            ));
        }
    }
}