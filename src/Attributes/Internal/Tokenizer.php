<?php
declare(strict_types=1);
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
use \RecursiveArrayIterator;
use Luminova\Logger\Logger;
use Luminova\Routing\Router;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \RecursiveCallbackFilterIterator;
use Luminova\Base\{Command, Controller};
use Luminova\Attributes\{Group, Prefix};
use Luminova\Exceptions\RouterException;
use Luminova\Interface\RoutableInterface;
use function Luminova\Funcs\{
    root,
    pascal_case,
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
    private array $routable = [
        'T_EXTENDS'     => [],
        'T_IMPLEMENTS'  => [
            'RoutableInterface', 
            '\Luminova\Interface\RoutableInterface', 
            RoutableInterface::class
        ]
    ];

    /**
     * Routes home patterns.
     *
     * @var array $homePatterns
     */
    private static array $homePatterns = [
        '/' => true,
        '/*' => true,
        '/?' => true,
        '/.*' => true,
        '/?.*' => true,
        '/?.*?' => true,
        '/?(?:/.*)?' => true,
        '/?(.*)' => true,
        '/?(.*)?' => true,
        '/(?:/.*)?' => true,
        '/.' => true,
        '.' => true,
    ];

    /**
     * HMVC ignore pathname.
     *
     * @var array $filters
     */
    private static array $filters = [];

    /**
     * Constructor to initialize the compiler.
     *
     * @param bool $cli Flag indicating if running in CLI mode.
     * @param bool $hmvc Flag indicating if running application with hmvc module.
     */
    public function __construct(private bool $cli = false, private bool $hmvc = false)
    {
        $this->isCacheable = (bool) env('feature.route.cache.attributes', false);
     
        if($this->hmvc){
            self::$filters = [
                'Views'  => true, 
                'Models' => true
            ];
            
            self::$filters[$this->cli ? 'Http' : 'Cli'] = true;
        }

        // Throw exceptions on cli mode
        if($this->cli){
            $this->routable['T_EXTENDS'] = [
                'Command', 
                '\Luminova\Base\Command', 
                Command::class
            ];

            setenv('throw.cli.exceptions', 'true');
            return;
        }

        $this->routable['T_EXTENDS'] = [
            'Controller', 
            '\Luminova\Base\Controller', 
            Controller::class
        ];
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
        $isExcluded = false;
        $subPrefix = null;

        // HMVC module name must follow strict naming pattern
        if(
            !$this->cli 
            && $this->hmvc 
            && $prefix !== '' 
            && $prefix !== Router::CLI_URI
        ){
            $path .= pascal_case($prefix);
        }

        if (
            $this->isCacheable 
            && $this->hasCache(
                $name, 
                $prefix, 
                $uri, 
                $path,
                $isExcluded, 
                $subPrefix
            )
        ) {
            return [null, null, false, null];
        }

        $attrClass = $this->cli ? 'Group' : 'Prefix';

        foreach ($this->iterator($path, $name) as $file) {
            $fileName = $file->getBasename('.php');

            $classNamespace = $this->scanFileForMatchingClass(
                $file->getPathname(), 
                $fileName,
                $uri,
                $attrClass
            );

            if ($classNamespace === null) {
                $this->searches++;
                continue;
            }

            return [$classNamespace, $fileName, $isExcluded, $subPrefix];
        }

        return [null, null, false, null];
    }

    /**
     * Creates a recursive iterator for routable controller files.
     *
     * @param string $path Root directory path to scan.
     * @param string $name Controller namespace context.
     *
     * @return RecursiveIteratorIterator Valid controller file iterator.
     * @throws RouterException When directory iteration fails.
     */
    public function iterator(string $path, string $name): RecursiveIteratorIterator
    {
        $filepath = $path = root($path);
        $isDir = is_dir($path);

        // Resolve to root on HMVC app
        if(!$isDir && $this->hmvc){
            $path = dirname(rtrim($path, '/')) . '/';
            $isDir = is_dir($path);
        }

        if(!$isDir){
            $error = sprintf(
                'Could not resolve controller from path: %s%s',
                $filepath
            );

            self::e(new RouterException($error), ['path' => $path]);
            return new RecursiveIteratorIterator(
                new RecursiveArrayIterator()
            );
        }

        $isHmvc = $this->hmvc;

        try{
            return new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator(
                        $path, 
                        FilesystemIterator::SKIP_DOTS
                    ),
                    static fn(SplFileInfo $entry) => self::isValidEntry($entry, $name, $isHmvc)
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
        }catch(Throwable $e){
            self::e($e, ['path' => $path]);
            return new RecursiveIteratorIterator(
                new RecursiveArrayIterator()
            );
        }
    }

    /**
     * Check if module URI prefix namespace exists.
     *
     * @param string $path The module prefix path.
     * 
     * @return bool
     */
    private function hasModule(string $path): bool 
    {
        if(!$this->hmvc){
            return false;
        }

        return is_dir(root($path));
    }

    /**
     * Get cache path.
     *
     * @param string $suffix
     * @return string
     */
    private static function getCachePath(string $suffix = ''): string 
    {
        return root("/writeable/caches/routes/{$suffix}");
    }

    /**
     * Handle error.
     *
     * @param Throwable $e
     * @param array $context
     * 
     * @return void
     */
    private static function e(Throwable $e, array $context = []): void 
    {
        if(PRODUCTION){
            Logger::tryDispatch('critical', $e->getMessage(), $context);
            return;
        }

        if(!$e instanceof RouterException){
            $e = new RouterException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        throw $e;
    }

    /**
     * Cache compiled routes to disk for faster bootstrapping.
     *
     * This method serializes the already-built route table and stores it
     * under a context-specific cache file. If HTTP context is used and
     * sorting is enabled, routes are sorted by priority before caching.
     *
     * Sorting is applied ONLY during cache generation and does not affect
     * runtime route registration state.
     *
     * @param string $context Cache context (e.g. "http", "cli").
     * @param string $prefix Cache file prefix or base name.
     * @param bool $isExcluded Whether to override prefix using subPrefix.
     * @param string|null $subPrefix Optional override prefix.
     *
     * @return bool True on successful cache write, false otherwise.
     */
    public function cache(
        string $context,
        string $prefix = 'cli',
        bool $isExcluded = false,
        ?string $subPrefix = null
    ): bool 
    {
        if (!$this->isCacheable || $this->routes === []) {
            return false;
        }

        try {
            $path = self::getCachePath($context);

            if (!make_dir($path)) {
                return false;
            }

            $prefix = $this->resolveCachePrefix(
                $prefix, 
                $isExcluded, 
                $subPrefix
            );

            if(PRODUCTION){
                $ext = '.php';
                $routes = "<?php\n";
                $routes .= "return ";
                $routes .=  var_export($this->routes, true);
                $routes .= ";";
            } else{
                $ext = '.json';
                $routes = json_encode(
                    $this->routes, 
                    JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT
                );
            }

            if (empty($routes)) {
                return false;
            }

            $filename = $path.$prefix.$ext;

            return file_put_contents(
                $filename, 
                $routes
            ) !== false;
        } catch (Throwable $e) {
            self::e($e, [
                'info' => 'Route cache failed'
            ]);
        }

        return false;
    }

    /**
     * Resolve cache prefix for route.
     *
     * @param string $prefix
     * @param bool $isExcluded
     * @param string|null $subPrefix
     * 
     * @return string
     */
    private function resolveCachePrefix(
        string $prefix,
        bool $isExcluded,
        ?string $subPrefix
    ): string 
    {
        return match (true) {
            $isExcluded && $subPrefix => $subPrefix,
            $prefix === 'cli' || $this->cli => $prefix ?: 'cli',
            default => $this->getPrefix() ?? ($prefix ?: 'web'),
        };
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
        $tokens = PhpToken::tokenize((string) file_get_contents($filePath));

        if ($tokens === []) {
            return null;
        }

        $namespace = '';
        $attributes = [];
        $mode = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->isIgnorable()) {
                continue;
            }

            if ($token->is([T_CLASS])) {
                $next = $tokens[$i + 2] ?? null;

                if ($next instanceof PhpToken && $next->is(T_STRING) && $next->text === $className) {
                    continue;
                }

                return null;
            }

            if ($token->is([T_EXTENDS, T_IMPLEMENTS])) {
                $mode = $token->getTokenName();
                continue;
            }

            if ($token->is(T_NAMESPACE)) {
                [$namespace, $i] = $this->parseNamespace($tokens, $i, $count);
            }

            if ($token->is(T_ATTRIBUTE)) {
                $thisAttr = (string) ($tokens[$i + 1] ?? '');
       
                if(!str_contains($thisAttr, $attrClass)){
                    continue;
                }

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
            
            if(in_array($token->text, $this->routable[$mode], true)){
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
                        return "{$namespace}\\{$className}";
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
     * Build a regex that matches a path under a given prefix,
     * while preventing specific FIRST path segments after that prefix.
     *
     * Behavior rules:
     *
     * 1) The pattern always matches the prefix itself.
     *    Example: '/blog' matches even if exclusions exist.
     *
     * 2) If the path continues after the prefix, the first segment
     *    immediately following the prefix must NOT match any value
     *    in $excludes.
     *
     * 3) Exclusions are checked only at the first segment boundary
     *    after the prefix — not anywhere else in the path.
     *
     * 4) When prefix is '/', exclusions apply to top-level paths.
     *
     * 5) Known router placeholders and
     *    trailing optional segment patterns are removed from the prefix
     *    before building the regex.
     *
     * 6) Returned pattern is NOT delimited or anchored. Anchoring is
     *    the caller’s responsibility if full-string matching is required.
     *
     * @param string $prefix Base path to allow (root or sub-path) (e.g, '/', '/blog', '/api').
     * @param array<int,string> $excludes Path segment names to block immediately after the prefix.
     *                                    Leading/trailing slashes are ignored.
     *
     * @return string Regex pattern to use for route matching.
     *
     * @example - Root prefix blocks top-level segments
     * ```
     * Tokenizer::excluder('/', ['api', 'docs', 'forum'])
     * 
     * → '/(?!api(?:/|$)|docs(?:/|$)|forum(?:/|$)).*'
     * ```
     *
     * @example - Sub-prefix blocks only the first segment after it
     * ```
     * Tokenizer::excluder('/blog', ['api', 'docs'])
     * 
     * → '/blog(?:/(?!api(?:/|$)|docs(?:/|$)).*)?'
     *
     * Matches:
     *   /blog
     *   /blog/post
     *
     * Does NOT match:
     *   /blog/api
     *   /blog/docs/v1
     * ```
     *
     * @example - Exclusions are normalized to segment names
     * ```
     * Tokenizer::excluder('/', ['/api/', 'docs/', '/forum'])
     * 
     * → '/(?!api(?:/|$)|docs(?:/|$)|forum(?:/|$)).*'
     * ```
     *
     * @example - No exclusions → simple prefix match
     * ```
     * Tokenizer::excluder('/api', [])
     * 
     * → '/api(?:/.*)?'
     * ```
     */
    public static function excluder(string $prefix, array $excludes): string
    {
        $prefix = '/' . trim($prefix, '/');

        if ($prefix !== '/') {
            // Remove known root and base placeholders
            $pattern = '\(:root\)|\(:base\)|';

            // Always remove trailing optional segment pattern
            $pattern .= '\?\(\?:/(?:\[\^/\]\.)?\.\*\)\?';

            // Also remove any trailing wildcard patterns 
            // to prevent interference with exclusion logic
            $pattern .= '|(?:\.\*)+';
            $prefix = preg_replace("#(?:{$pattern})$#", '', $prefix);

            $prefix = '/' . trim($prefix, '/');
        }

        if ($excludes === []) {
            return ($prefix === '/')
                ? '/(?:.*)?'
                : $prefix . '(?:/.*)?';
        }

        $excludes = array_map(
            static fn ($path) => preg_quote(trim($path, '/'), '/'),
            $excludes
        );

        $ignores = '(?!' . implode('(?:/|$)|', $excludes) . '(?:/|$))';

        if ($prefix === '/') {
            return '/' . $ignores . '.*';
        }

        return $prefix . '(?:/' . $ignores . '.*)?';
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

        return self::isControllerPrefix(
            self::excluder($pattern, $excluders), 
            $url
        );
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
        $normalize = ($normalize === false) 
            ? $pattern 
            : Router::toPatterns($pattern);

        if('/' === $uri && self::isHome($normalize)){
            return true;
        }

        $pattern = $isRoot 
            ? "{$normalize}(\/.*)?" 
            : $normalize;

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
    private function parseNamespace(array $tokens, int $index, int $count): array
    {
        $namespace = '';

        for ($i = $index + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->isIgnorable()) {
                continue;
            }

            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $tokenString = $token->text;

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
                $namespace .= $token->text;
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

            if ($token->isIgnorable()) {
                continue;
            }

            $tokenString = $token->text;

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
     * @param bool &$isExcluded 
     * @param string|null $subPrefix 
     * 
     * @return bool Returns true if a valid cache is found, otherwise false.
     */
    private function hasCache(
        string $name, 
        string $prefix, 
        string $uri,
        string $path,
        bool &$isExcluded = false,
        ?string &$subPrefix = null,
    ): bool 
    {
        $lock = self::getCachePath($name);
        $filename = $prefix ?: 'web';
        $isSub = false;
        $excluded = false;

        $this->read($lock . $filename);
        
        if($this->findCache($uri, $excluded, $subPrefix, $isSub)){
            return true;
        }

        if($prefix !== '' && $excluded){
            $isExcluded = true;

            if($isSub){
                return $this->read($lock . $subPrefix)
                    ->findCache($uri);
            }

            $subPrefix = $prefix 
                ? "{$prefix}.{$subPrefix}" 
                : $subPrefix;

            return $this->hasCache(
                $name,
                $subPrefix,
                $uri,
                $path,
                $isExcluded,
                $subPrefix
            );
        }

        if($filename === 'web' || $this->hasModule($path)){
            return false;
        }

        return $this->read($lock . 'web')
            ->findCache($uri);
    }

    /**
     * Read cached routes.
     *
     * @param string $file The file name.
     * 
     * @return self Return instance of class.
     */
    private function read(string $file): self 
    {
        $this->routes = [];

        $this->routes = PRODUCTION 
            ? self::readPhp($file)
            : self::readJson($file);

        return $this;
    }

    /**
     * Read from PHP array file.
     *
     * @param string $file
     * @return array
     */
    private static function readPhp(string $file): array 
    {
        $filename = $file . '.php';

        if(!is_file($filename)){
            return [];
        }

        $routes = include $filename;

        return (empty($routes) || !is_array($routes)) 
            ? [] 
            : $routes;
    }

    /**
     * Read from JSON file.
     *
     * @param string $file
     * @return array
     */
    private static function readJson(string $file): array
    {
        $filename = $file . '.json';

        if(!is_file($filename)){
            return [];
        }

        $routes = file_get_contents($filename);

        if($routes === false){
            return [];
        }

        return json_decode($routes, true) ?: [];
    }

    /**
     * Check if the loaded routes match the given URI or Command.
     *
     * @param string $uri The request URI or command group name to match.
     * @param bool &$isExcluded A reference variable that will be set to true 
     *      if the URI is excluded by the route pattern, false otherwise.
     * @param string|null &$subPrefix A reference variable that will be set to the sub prefix 
     *      if the URI is excluded, or null if not excluded.
     * 
     * @return bool Returns true if the URI match, otherwise false.
     */
    private function findCache(
        string $uri, 
        bool &$isExcluded = false, 
        ?string &$subPrefix = null,
        bool &$isSub = false
    ): bool
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
            $pattern = self::excluder(
                $this->routes['rawBasePattern'] ?? $pattern, 
                $excluders
            );
        }

        if(
            $uri === $pattern 
            || self::isControllerPrefix($pattern, $uri, isRoot: $isRoot)
        ){
            return true;
        }

        $this->routes = [];

        if($excluders !== []){
            [$next, $isSub] = self::getSubExcluder(
                $uri, 
                $pattern, 
                $excluders, 
                $subPrefix
            );

            if ($next !== null) {
                $isExcluded = true;
                $subPrefix = $next;
            }
        }

        return false;
    }

    /**
     * Check if the URI is excluded by the pattern and return the sub-prefix if it is.
     *
     * @param string $uri The URI to check against the pattern.
     * @param string $pattern The route pattern to compare against the URI.
     * @param array<int,string> $excluders List of prefixes or command group to exclude.
     * @param string|null $current 
     * 
     * @return array The sub-prefix if the URI is excluded, or null if not excluded.
     */
    private static function getSubExcluder(
        string $uri, 
        string $pattern, 
        array $excluders, 
        ?string $current = null
    ): array
    {
        $path = trim($uri, '/');

        if (
            $path === '' 
            || !str_contains($path, '/') 
            || preg_match("#^{$pattern}$#", $uri)
        ) 
        {
            return [null, false];
        }

        // Always skip base prefix
        $index = 1;
        $current ??= '';
        $parts = explode('/', $path);

        if($parts === []){
            return [null, false];
        }

        if($current){
            $index += substr_count($current, '.');
        }

        $segment = $parts[$index] ?? null;

        if (!in_array($segment, $excluders, true)) {
            return [null, false];
        }

        return [
            $current ? "{$current}.{$segment}" : $segment,
            !empty($current)
        ];
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
        return isset(self::$homePatterns[$pattern]);
    }

    /**
     * Check if a directory entry is a valid routable controller file.
     *
     * Valid entries must:
     * - Be a PHP file.
     * - Not be hidden or contain spaces.
     * - Exist directly inside the requested Controllers context.
     * - Match the current MVC/HMVC structure.
     *
     * @param SplFileInfo $entry File system entry.
     * @param string $name Controller context name (e.g. Http, Cli).
     * @param bool $isHmvc Wether is HMVC application.
     *
     * @return bool True when the entry can be scanned as a controller.
     */
    private static function isValidEntry(SplFileInfo $entry, string $name, bool $isHmvc): bool
    {
        if (!$entry->isFile()) {
            return !isset(self::$filters[$entry->getBasename()]); 
        }

        $name = ucfirst($name);

        if($name === 'Export'){
            return false;
        }

        $filename = $entry->getFilename();

        if(
            $entry->getExtension() !== 'php'
            || str_starts_with($filename, '.')
            || str_contains($filename, ' ')
        ){
            return false;
        }

        $path = str_replace('\\', '/', $entry->getPath());

        if (!str_ends_with($path, "/Controllers/{$name}")) {
            return false;
        }

        if($isHmvc && !str_contains($path, '/app/Modules/')){
            return false;
        }

        return true;
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