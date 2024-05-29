<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Template;

use \Luminova\Storages\FileManager;
use \Luminova\Template\Smarty;
use \Luminova\Template\Twig;
use \Luminova\Http\Header;
use \Luminova\Exceptions\AppException; 
use \Luminova\Interface\ExceptionInterface; 
use \Luminova\Exceptions\ViewNotFoundException; 
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Time\Time;
use \Luminova\Time\Timestamp;
use \Luminova\Template\Helper;
use \Luminova\Cache\PageViewCache;
use \App\Controllers\Config\Template as TemplateConfig;
use \DateTimeInterface;
use \Luminova\Debugger\Performance;

trait TemplateView
{ 
    /**
     * Template configuration.
     * 
     * @var TemplateConfig $config
    */
    private static ?TemplateConfig $config = null;

    /**
     * Flag for key not found.
     * 
     * @var string KEY_NOT_FOUND
    */
    protected static string $KEY_NOT_FOUND = '__nothing__';

    /** 
     * Holds the project document root
     * 
     * @var string $documentRoot
    */
    private static string $documentRoot = '';

    /** 
     * Holds the project template filename
     * 
     * @var string $templateFile 
    */
    private string $templateFile = '';

    /**
     * Holds the project template directory
     * 
     * @var string $templateDir 
    */
    private string $templateDir = '';

    /**
     * Type of view content
     * 
     * @var string $viewType 
    */
    private string $viewType = 'html';

    /** 
     * The project view file directory.
     * 
     * @var string $viewFolder 
    */
    private static string $viewFolder = 'resources/views';

    /** 
     * The project sub view directory
     * 
     * @var string $subViewFolder 
    */
    private string $subViewFolder = '';

    /** 
     * Holds the router active page name
     * 
     * @var string $activeView 
    */
    private string $activeView = '';

    /** 
     * Holds the array attributes
     * 
     * @var array $publicOptions 
    */
    private static array $publicOptions = [];

    /** 
     * Holds the array classes
     * 
     * @var array<string,mixed> $publicClasses 
    */
    private static array $publicClasses = [];

    /** 
     * Ignore view optimization
     * 
     * @var array $cacheIgnores
    */
    private array $cacheIgnores = [];

    /**
     * Force use of cache response.
     * 
     * @var bool $forceCache 
    */
    private bool $forceCache = false;

    /**
     * Response cache expiry ttl
     * 
     * @var DateTimeInterface|int|null $cacheExpiry 
    */
    private DateTimeInterface|int|null $cacheExpiry = 0;

     /**
     * Default cache path
     * 
     * @var string $cacheFolder 
    */
    private static string $cacheFolder = 'writeable/caches/default';

    /**
     * Minify page content 
     * 
     * @var bool $minifyContent 
    */
    private static bool $minifyContent = false;

    /**
     * Should cache view base
     * 
     * @var bool $cacheView
    */
    private static bool $cacheView = false;

    /**
     * Set base context caching mode
     * 
     * @var bool $contextCaching
    */
    private bool $contextCaching = true;

    /**
     * Should minify codeblock tags
     * 
     * @var bool $minifyCodeblocks 
    */
    private bool $minifyCodeblocks = false;

    /**
     * Allow copy codeblock
     * 
     * @var bool $codeblockButton 
    */
    private bool $codeblockButton = false;

    /** 
     * Initialize template
     *
     * @return void
     * @internal 
    */
    protected final function initialize(): void
    {
        self::$config = new TemplateConfig();
        self::$documentRoot = root();
        self::$minifyContent = (bool) env('page.minification', false);
        self::$cacheView = (bool) env('page.caching', false);
        self::$cacheFolder = self::withViewFolder(self::trimDir(self::$config->cacheFolder) . 'default');
        $this->cacheExpiry = (int) env('page.cache.expiry', 0);
    }

    /** 
     * Get property from self::$publicOptions or self::$publicClasses
     *
     * @param string $key property name 
     *
     * @return mixed Return option or class object.
     * @internal 
    */
    protected static final function attrGetter(string $key): mixed 
    {
        if (array_key_exists($key, self::$publicOptions)) {
            return self::$publicOptions[$key];
        }

        return self::$publicClasses[$key] ?? static::$KEY_NOT_FOUND;
    }

    /** 
     * Set if HTML codeblock tags should be ignore during page minification.
     *
     * @param bool $minify Indicate if codeblocks should be minified (default: false)
     * @param bool $button Indicate if codeblock tags should include a copy button (default: false).
     *
     * @return self $this
    */
    public final function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }

    /** 
     * Set sub view folder name to look for template file within the `resources/views/`.
     *
     * @param string $path folder name to search for view.
     *
     * @return self $this Instance of self.
    */
    public final function setFolder(string $path): self
    {
        $this->subViewFolder = trim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    /** 
     * Add a view to page cache ignore list.
     *
     * @param string|array<int, string> $viewName view name or array of view names.
     *
     * @return self $this Instance of self.
    */
    public final function noCaching(array|string $viewName): self
    {
        if(is_string($viewName)){
            $this->cacheIgnores[] = $viewName;
        }else{
            $this->cacheIgnores = $viewName;
        }
        
        return $this;
    }

    /**
     * Export / Register a class instance to make it accessible within the view template.
     *
     * @param class-string<\T>|class-object<\T> $class The class name or instance of a class to register.
     * @param string|null $alias Optional class alias.
     * @param bool $initialize Whether to initialize class-string or leave it as static class (default: true).
     * 
     * @return true true on success, false on failure
     * @throws RuntimeException If the class does not exist or failed.
     * @throws RuntimeException If there is an error during registration.
    */
    public final function export(string|object $class, ?string $alias = null, bool $initialize = true): bool 
    {
        if ($class === '' || $alias === '') {
            throw new RuntimeException('Invalid arguments provided, arguments expected a non-blank string.');
        }

        $alias ??= get_class_name($class);

        if (isset(self::$publicClasses[$alias])) {
            throw new RuntimeException("Class with the same name: '{$alias}' already exists.");
        }

        if (is_string($class) && $initialize) {
            self::$publicClasses[$alias] = new $class();
            return true;
        }
        
        self::$publicClasses[$alias] = $class;

        return true;
    }

    /** 
     * Cache and store response to reuse on next request to same content.
     * 
     * @param DateTimeInterface|int|null $expiry Cache expiration default, set to null to use default expiration from .env file.
     * 
     * @example Usage example with cache 
     * ```
     * $cache = $this-app->cache(60); 
     * //Check if already cached before caching again.
     * if($cache->expired()){
     *      $heavy = $db->doHeavyProcess();
     *      $cache->view('foo')->render(['data' => $heavy]);
     * }else{
     *      $cache->reuse();
     * }```
     * @return self $this Instance of self.
    */
    public final function cache(DateTimeInterface|int|null $expiry = null): self 
    {
        $this->forceCache = true;

        if($expiry !== null){
            $this->cacheExpiry = $expiry;
        }

        return $this;
    }

    /**
     * Check if page cache has expired.
     * Note: the expiration check we use the time used while saving cache.
     * 
     * @return bool Returns true if cache doesn't exist or expired.
    */
    public final function expired(): bool
    {
        return Helper::getCache(self::$cacheFolder)->expired();
    }

    /**
     * Render cached content if cache exist.
     * 
     * @return int Returns status code success if cache exist and rendered else return error.
     * @throws RuntimeException Throws if called without calling `cache` method or if cache file os not found.
    */
    public final function reuse(): int
    {
        if (!$this->forceCache) {
            throw new RuntimeException('Cannot call reuse method with first calling cache() method');
        }

        $this->forceCache = false;
        $cache = Helper::getCache(self::$cacheFolder, $this->cacheExpiry);

        if ($cache->read()) {
            return STATUS_SUCCESS;
        }

        throw new RuntimeException('No cache not found to reuse.');
    }

    /** 
     * Redirect to view url
     *
     * @param string $view view name
     * @param int $response_code response status code
     *
     * @return void
    */
    public final function redirect(string $view, int $response_code = 0): void 
    {
        $view = start_url($view);
        header("Location: $view", true, $response_code);
        exit(STATUS_SUCCESS);
    }

    /** 
     * render render template view
     *
     * @param string $viewName view name
     * @param string $viewType Type of content not same as header Content-Type
     * - html Html content.
     * - json Json content.
     * - text Plain text content.
     * - xml  Xml content.
     * - js   JavaScript content.
     * - css  CSS content.
     * - rdf  RDF content.
     * - atom Atom content.
     * - rss  RSS feed content.
     *
     * @return self $this
    */
    public final function view(string $viewName, string $viewType = 'html'): self 
    {
        if(!PRODUCTION && (bool) env('debug.show.performance.profiling', false)){
            Performance::start();
        }

        $viewName = trim($viewName, '/');
        $viewType = strtolower($viewType);
    
        if(!in_array($viewType, ['html', 'json', 'text', 'xml', 'js', 'css', 'rdf', 'atom', 'rss'])){
            throw new RuntimeException(sprintf('Invalid argument, "%s" required types (html, json, text, xml, js, css, rdf, atom, rss). To render other formats use helper function `response()->render()`', $viewType));
        }

        $this->templateDir = self::withViewFolder(self::$viewFolder);

        if($this->subViewFolder !== ''){
            $this->templateDir .= $this->subViewFolder . DIRECTORY_SEPARATOR;
        }

        $this->templateFile = $this->templateDir . $viewName . self::dot();

        if (!file_exists($this->templateFile) && PRODUCTION) {
            $viewName = '404';
            $this->templateFile = $this->templateDir . $viewName . self::dot();
        }

        $this->viewType = $viewType;
        $this->activeView = $viewName;

        return $this;
    }

    /**
     * Render view content with additional options available as globals within the template view.
     *
     * @param array<string, mixed> $options Additional parameters to pass in the template file.
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @example `$this->app->view('name')->render([])` Display your template view with options.
     * 
     * @return int The HTTP status code.
     * @throws RuntimeException If the view rendering fails.
     */
    public final function render(array $options = [], int $status = 200): int 
    {
        return ($this->call($options, $status) ? STATUS_SUCCESS : STATUS_ERROR);
    }

    /**
     * Get the rendered contents of a view.
     *
     * @param array<string, mixed> $options Additional parameters to pass in the template file.
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @example `$content = $this->app->view('name')->respond([])` Display your template view or send as an email.
     * 
     * @return string The rendered view contents.
     * @throws RuntimeException If the view rendering fails.
     */
    public final function respond(array $options = [], int $status = 200): string
    {
        return $this->call($options, $status, true);
    }

    /**
     * Retrieves information about a view file.
     *
     * @return array An associative array containing information about the view file:
     *    -  'location': The full path to the view file.
     *    -  'engine': The template engine.
     *    -  'size': The size of the view file in bytes.
     *    -  'timestamp': The last modified timestamp of the view file.
     *    -  'modified': The last modified date and time of the view file (formatted as 'Y-m-d H:i:s').
     *    -  'dirname': The directory name of the view file.
     *    -  'extension': The extension of the view file.
     *    -  'filename': The filename (without extension) of the view file.
    */
    public final function viewInfo(): array 
    {
        $viewPath = root(self::$viewFolder) . $this->activeView . self::dot();
        clearstatcache(true, $viewPath);
        $info = [
            'location' => $viewPath,
            'engine' => self::engine(),
            'size' => 0,
            'timestamp' => 0,
            'modified' => '',
            'dirname' => null,
            'extension' => null,
            'filename' => null,
        ];

        if (is_file($viewPath)) {
            $info['size'] = filesize($viewPath);

            $timestamp = filemtime($viewPath);
            $info['timestamp'] = $timestamp;
            $info['modified'] = Time::fromTimestamp((int) $timestamp)->format('Y-m-d H:i:s');

            $pathInfo = pathinfo($viewPath);
            $info['dirname'] = $pathInfo['dirname'] ?? null;
            $info['extension'] = $pathInfo['extension'] ?? null;
            $info['filename'] = $pathInfo['filename'] ?? null;
        }

        return $info;
    }

    /**
     * Create a public link to of a file, directory, or a view.
     * 
     * @param string $filename Filename to prepend to base.
     * 
     * @return string Return a public url of file.
    */
    public static final function link(string $filename = ''): string 
    {
        $base = PRODUCTION ? '/' : Helper::relativeLevel() . ((NOVAKIT_ENV === null) ? 'public/' : '/');
        
        if($filename === ''){
            return $base;
        }

        return $base . ltrim($filename, '/');
    }

    /** 
     * Set if view base context should be cached.
     * Useful in api context to manually handle caching.
     *
     * @param bool $allow true or false
     *
     * @return self $this
    */
    public final function cacheable(bool $allow): self
    {
        $this->contextCaching = $allow;

        return $this;
    }

    /** 
     * Get view root folder
     *
     * @return string root
    */
    private static function viewRoot(): string
    {
        if(self::$documentRoot === ''){
            self::$documentRoot = APP_ROOT;
        }

        return rtrim(self::$documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get template engine file extension.
     *
     * @return string Returns extension type.
    */
    private static function dot(): string
    {
        $engine = self::engine();

        if($engine === 'smarty'){
            return '.tpl';
        }

        if($engine === 'twig'){
            return '.twig';
        }

        return '.php';
    }

    /** 
     * Get template engine type
     *
     * @return string Return template engine name.
    */
    private static function engine(): string 
    {
        return strtolower(self::$config->templateEngine ?? 'default');
    }

    /** 
     * Creates and Render template by including the accessible global variable within the template file.
     *
     * @param array $options additional parameters to pass in the template file
     * @param int $status HTTP status code (default: 200 OK)
     *
     * @return bool Return true on success, false on failure.
     * @throws ViewNotFoundException
    */
    private function call(array $options = [], int $status = 200, bool $return = false): bool|string 
    {
        $options = $this->parseOptions($options);
        try {
            if($this->assertSetup($status)){
                $cacheable = $this->shouldCache();
                $engine = self::engine();

                if ($engine === 'smarty' || $engine === 'twig') {
                    return self::{'render' . $engine}(
                        $this->activeView . self::dot(), 
                        $this->templateDir, 
                        $options, 
                        $cacheable,
                        Timestamp::ttlToSeconds($this->cacheExpiry),
                        $this->minifyCodeblocks, 
                        $this->codeblockButton,
                        $return 
                    );
                }

                $cache = null;

                if ($cacheable) {
                    $cache = Helper::getCache(self::$cacheFolder, $this->cacheExpiry);
                    if (!$cache->expired()) {
                        return $return ? $cache->get() : $cache->read();
                    }
                }

                if(self::$config->templateIsolation){
                    return self::renderIsolation(
                        $this->templateFile, 
                        $options,
                        $cache,
                        $this->minifyCodeblocks, 
                        $this->codeblockButton,
                        $return 
                    );
                }

                return $this->renderDefault($options, $cache, $return);
            }
        } catch (ExceptionInterface $e) {
            self::handleException($e, $options);
        }

        return false;
    }

    /**
     * Initialize rendering setup
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @return bool 
     * @throws ViewNotFoundException
     * @throws RuntimeException
    */
    private function assertSetup(int $status = 200): bool
    {
        defined('ALLOW_ACCESS') || define('ALLOW_ACCESS', true);

        if (MAINTENANCE) {
            Header::headerNoCache(503);
            header('Retry-After: ' . env('app.maintenance.retry', '3600'));
            include self::getErrorFolder('maintenance');

            return false;
        }

        if (!file_exists($this->templateFile)) {
            Header::headerNoCache(404);
            throw new ViewNotFoundException(sprintf(
                'The view "%s" could not be found in the view directory "%s".', 
                $this->activeView, 
                filter_paths($this->templateDir)
            ), 404);
        } 

        FileManager::permission('rw');
        http_response_code($status);
        
        return true;
    }

    /**
     * Render with smarty
     * 
     * @param string $view View file name.
     * @param string $templateDir View template directory.
     * @param array $options View options
     * @param bool $caching Should cache page contents
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private static function rendersmarty(
        string $view, 
        string $templateDir, 
        array $options = [], 
        bool $caching = false,
        int $cacheExpiry = 3600,
        bool $minify = false,
        bool $copy = false,
        bool $return = false
    ): bool|string
    {
        static $instance = null;

        if($instance === null){
            $instance = Smarty::getInstance(self::$config, self::viewRoot());
        }

        try{
            $instance->setPath($templateDir);
            $instance->minify(self::$minifyContent, [
                'codeblock' => $minify,
                'copyable' => $copy,
                'encode' => false,
            ]);
            $instance->caching($caching, $cacheExpiry);

            if (!$instance->isCached($view)) {
                $instance->assignOptions($options);
                $instance->assignClasses(self::$publicClasses);
            }

            return $instance->display($view, $return);
        }catch(AppException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Render with smarty
     * 
     * @param string $view View file name.
     * @param string $templateDir View template directory.
     * @param array $options View options
     * @param bool $shouldCache Should cache page contents
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private static function rendertwig(
        string $view, 
        string $templateDir, 
        array $options = [], 
        bool $shouldCache = false,
        int $cacheExpiry = 3600,
        bool $minify = false,
        bool $copy = false,
        bool $return = false
    ): bool|string
    {
        static $instance = null;

        if($instance === null){
            $instance = Twig::getInstance(self::$config, self::viewRoot(), $templateDir, [
                'caching' => $shouldCache,
                'charset' => env('app.charset', 'utf-8'),
                'strict_variables' => true,
                'autoescape' => 'html'
            ]);
        }

        try{
            $instance->setPath($templateDir);
            $instance->minify(self::$minifyContent, [
                'codeblock' => $minify,
                'copyable' => $copy,
                'encode' => false,
            ]);
            $instance->assignClasses(self::$publicClasses);

            return $instance->display($view, $options, $return);
        }catch(AppException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Render without smarty using default .php template engine.
     * 
     * @param array $options View options
     * @param PageViewCache|null $_lmv_cache Cache instance if should cache page contents
     * @param bool $_lmv_return Should return view contents.
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private function renderDefault(array $options, ?PageViewCache $_lmv_cache = null, bool $_lmv_return = false): bool|string
    {
        if(self::$config->variablePrefixing !== null){
            self::extract($options);
        }

        include_once $this->templateFile;
        $_lmv_contents = ob_get_clean();     
        self::inlineErrors($_lmv_contents);

        [$_lmv_headers, $_lmv_contents] = self::assertMinifyAndSaveCache(
            $_lmv_contents,
            $options['viewType'],
            $this->minifyCodeblocks, 
            $this->codeblockButton,
            $_lmv_cache
        );

        if($_lmv_return){
            return $_lmv_contents;
        }

        Header::parseHeaders($_lmv_headers);
        echo $_lmv_contents;
       if(!PRODUCTION && (bool) env('debug.show.performance.profiling', false)){
            Performance::stop();
        }
        return true;
    }

    /**
     * Render without in isolation mode
     * 
     * @param string $_lmv_viewfile View template file 
     * @param array $options View options
     * @param PageViewCache|null $_lmv_cache Cache instance if should cache page contents
     * @param bool $_lmv_ignore Ignore html codeblock during minimizing
     * @param bool $_lmv_copy Allow copy on html code tag or not
     * @param bool $_lmv_return Should return view contents.
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private static function renderIsolation(
        string $_lmv_viewfile, 
        array $options,
        ?PageViewCache $_lmv_cache = null,
        bool $_lmv_ignore = true, 
        bool $_lmv_copy = false,
        bool $_lmv_return = false
    ): bool|string
    {
        $self = self::newSelfInstance();
        self::$publicClasses = [];

        if(($_lmv_prefix = self::$config->variablePrefixing) !== null){
            if($_lmv_prefix && isset($options['self'])){
                throw new RuntimeException('Reserved Error: The "self" keyword is not allowed in your view options without variable prefixing.', E_ERROR);
            }
            extract($options, ($_lmv_prefix ? EXTR_PREFIX_ALL : EXTR_OVERWRITE), '');
        }

        include_once $_lmv_viewfile;
        $_lmv_contents = ob_get_clean();
        self::inlineErrors($_lmv_contents);

        [$_lmv_headers, $_lmv_contents] = self::assertMinifyAndSaveCache(
            $_lmv_contents,
            $options['viewType'],
            $_lmv_ignore, 
            $_lmv_copy,
            $_lmv_cache
        );

        if($_lmv_return){
            return $_lmv_contents;
        }

        Header::parseHeaders($_lmv_headers);
        echo $_lmv_contents;

        return true;
    }

    /**
     * Initalize self class keyword.
     * 
     * @return object self classes.
    */
    private static function newSelfInstance(): object 
    {
        return new class(self::$publicClasses) {
            /**
             * @var array<string,mixed> $classes
            */
            private static array $classes = [];

            /**
             * @var array<string,mixed> $classes
            */
            public function __construct(array $classes = [])
            {
                self::$classes = $classes;
            }

            /**
             * @var string $class
             * @return object|string|null
            */
            public function __get(string $class): mixed 
            {
                return self::$classes[$class] ?? null;
            }
        };
    }

    /**
     * Minify content if possible and store cache if cacheable.
     * 
     * @param string|false $content View contents.
     * @param string $type The view content type.
     * @param bool $ignore Ignore codeblocks.
     * @param bool $copy Add copy button to codeblocks.
     * @param PageViewCache|null $cache Cache instance.
     * 
     * @return array<int,mixed> Return contents.
    */
    private static function assertMinifyAndSaveCache(
        string|false $content, 
        string $type, 
        bool $ignore, 
        bool $copy, 
        ?PageViewCache $cache = null
    ): array 
    {
        if ($content !== false && $content !== '') {
            $headers = null;
            if (self::$minifyContent) {
                $minifier = Helper::getMinifier(
                    $content, 
                    $type, 
                    $ignore, 
                    $copy
                );
                $content = $minifier->getContent();
                $headers = $minifier->getHeaders();
            }else{
                $headers = ['Content-Type' => Header::getContentTypes($type)];
            }

            $headers ??= Header::requestHeaders();

            if ($cache !== null) {
                $cache->saveCache($content, $headers, $type);
            }
        }else{
            $headers = Header::requestHeaders();
        }

        $headers['default_headers'] = true;
        return [$headers, $content];
    }
    

    /** 
     * Check if view should be optimized page caching or not
     *
     * @return bool 
    */
    private function shouldCache(): bool 
    {
        if($this->forceCache){
            return true;
        }

        if (!$this->contextCaching || !self::$cacheView || $this->emptyTtl()) {
            return false;
        }

        return !in_array($this->activeView, $this->cacheIgnores, true);
    }

    /** 
     * Check if cache expiry is empty 
     *
     * @return bool 
    */
    private function emptyTtl(): bool 
    {
        return $this->cacheExpiry === null || (is_int($this->cacheExpiry) && $this->cacheExpiry < 1);
    }

    /** 
     * Handle exceptions
     *
     * @param ExceptionInterface $exception
     * @param array $options view options
     *
     * @return void 
    */
    private static function handleException(ExceptionInterface $exception, array $options = []): void 
    {
        $view = self::getErrorFolder('view.error');
        $trace = SHOW_DEBUG_BACKTRACE ? debug_backtrace() : [];
  
        extract($options);
        unset($options);

        include_once $view;
        $exception->log();
        exit(STATUS_SUCCESS);
    }

    /**
     * Sets project template options.
     * 
     * @param  array<string, mixed> $attributes
     * 
     * @return int Number of registered options
     * @throws RuntimeException If there is an error setting the attributes.
    */
    private static function extract(array $attributes): void
    {
        if (self::$config->variablePrefixing === null) {
            return;
        }

        $prefix = (self::$config->variablePrefixing ? '_' : '');
        foreach ($attributes as $name => $value) {
            self::assertValidKey($name);           
            $key = is_int($name) ? '_' . $name : $prefix . $name;
            if ($key === '_' || $key === '') {
                throw new RuntimeException("Invalid option key: '{$key}'. View option key must be non-empty strings.");
            }

            if (isset(self::$publicClasses[$key])) {
                throw new RuntimeException("Class with the same option name: '{$key}' already exists. Use a different name or enable variable prefixing to retain the name.");
            }

            self::$publicOptions[$key] = $value;
        }
    }

    /**
     * Check if option keys is a valid PHP variable key.
     * 
     * @param string $key key name to check.
     * @throws RuntimeException Throws if key is not a valid PHP variable key.
    */
    private static function assertValidKey(string $key): void 
    {
        if (preg_match('/[^a-zA-Z0-9_]/', $key)) {
            throw new RuntimeException("Invalid option key: '{$key}'. Only letters, numbers, and underscores are allowed in variable names.");
        }
    }

    /**
     * Detect inline PHP errors in the view contents.
     *
     * This method checks for inline PHP errors within the provided content
     * and throws a RuntimeException if an error is detected. Error detection
     * is disabled in production or if 'debug.catch.inline.errors' is set to false.
     *
     * @param string $contents The content to check for inline PHP errors.
     * @throws RuntimeException if an inline PHP error is detected.
     */
    private static function inlineErrors(string $contents): void
    {
        if (PRODUCTION || !(bool) env('debug.catch.inline.errors', false)) {
            return;
        }

        if (preg_match('/error<\/b>:(.+?) in <b>(.+?)<\/b> on line <b>(\d+)<\/b>/', $contents, $matches)) {
            throw new RuntimeException(sprintf(
                "PHP Inline Error: %s in %s on line %d",
                trim($matches[1]),
                filter_paths($matches[2]),
                $matches[3]
            ), E_PARSE);
        }
    }

    /**
     * Parse user template options
     * 
     * @param array<string,mixed> $options The template options.
     * 
     * @return array<string,mixed> The parsed options.
    */
    private function parseOptions(array $options = []): array 
    {
        $options['viewType'] = $this->viewType;
        $options['href'] = static::link();
        $options['asset'] = $options['href'] . 'assets/';
        $options['active'] = $this->activeView;
        
        if(isset($options['nocache']) && !$options['nocache']){
            $this->cacheIgnores[] = $this->activeView;
        }

        if(!isset($options['title'])){
            $options['title'] = Helper::toTitle($options['active'], true);
        }

        if(!isset($options['subtitle'])){
            $options['subtitle'] = Helper::toTitle($options['active']);
        }

        return $options;
    }

    /** 
     * Get base view file directory
     *
     * @param string path
     *
     * @return string path
    */
    private static function trimDir(string $path): string 
    {
        return  rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get base view file directory
     *
     * @param string path
     *
     * @return string path
    */
    private static function withViewFolder(string $path): string 
    {
        return self::viewRoot() . trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get error file from directory
     *
     * @param string $filename file name
     *
     * @return string path
    */
    private static function getErrorFolder(string $filename): string 
    {
        return self::withViewFolder(self::$viewFolder) . 'system_errors' . DIRECTORY_SEPARATOR . $filename . '.php';
    }
}