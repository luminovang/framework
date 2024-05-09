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

use \Luminova\Application\FileSystem;
use \Luminova\Template\Smarty;
use \Luminova\Template\Twig;
use \Luminova\Base\BaseConfig;
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

trait TemplateTrait
{ 
    /**
     * Flag for key not found.
     * 
     * @var string KEY_NOT_FOUND
    */
    protected const KEY_NOT_FOUND = '__nothing__';

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
     * Encode page content 
     * 
     * @var bool $encodeContent 
    */
    private static bool $encodeContent = true;

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
     * @param string $dir template base directory
     *
     * @return void
     * @internal 
    */
    protected function initialize(string $dir =__DIR__): void
    {
        static::$documentRoot = root($dir);
        static::$minifyContent = (bool) env('page.minification', false);
        static::$encodeContent = (bool) env('enable.encoding', true);
        static::$cacheView = (bool) env('page.caching', false);
        static::$cacheFolder = static::withViewFolder(static::trimDir(TemplateConfig::$cacheFolder) . 'default');
        $this->cacheExpiry = (int) env('page.cache.expiry', 0);
    }

    /** 
     * Get property from static::$publicOptions or static::$publicClasses
     *
     * @param string $key property name 
     *
     * @return mixed 
     * @internal 
    */
    protected static function attrGetter(string $key): mixed 
    {
        if (array_key_exists($key, static::$publicOptions)) {
            return static::$publicOptions[$key];
        }

        return static::$publicClasses[$key] ?? static::KEY_NOT_FOUND;
    }

    /** 
     * Set if HTML codeblock tags should be ignore during page minification.
     *
     * @param bool $minify Indicate if codeblocks should be minified (default: false)
     * @param bool $button Indicate if codeblock tags should include a copy button (default: false).
     *
     * @return self $this
    */
    public function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }
   
    /** 
     * Get view root folder
     *
     * @return string root
    */
    private static function viewRoot(): string
    {
        if(static::$documentRoot === ''){
            static::$documentRoot = APP_ROOT;
        }

        return rtrim(static::$documentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get template engine file extention.
     *
     * @return string Returns extension type.
    */
    private static function dot(): string
    {
        $engine = static::engine();

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
        return strtolower(TemplateConfig::$templateEngine ?? 'default');
    }

    /** 
     * Set sub view folder name to look for template file within the `resources/views/`.
     *
     * @param string $path folder name to search for view.
     *
     * @return self $this Instance of self.
    */
    public function setFolder(string $path): self
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
    public function noCaching(array|string $viewName): self
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
     * @param string|object $classOrInstance The class name or instance to register.
     * @param string|null $aliases Optional class aliases
     * 
     * @return bool true on success, false on failure
     * @throws RuntimeException If the class does not exist or failed.
     * @throws RuntimeException If there is an error during registration.
    */
    public function export(string|object $classOrInstance, ?string $aliases = null): bool 
    {
        if ($classOrInstance === '' || $aliases === '') {
            throw new RuntimeException('Invalid arguments provided, arguments expected a non-blank string.');
        }

        $aliases ??= get_class_name($classOrInstance);

        if (isset(static::$publicClasses[$aliases])) {
            throw new RuntimeException("Class with the same name: '{$aliases}' already exists.");
        }

        if (is_string($classOrInstance)) {
            static::$publicClasses[$aliases] = new $classOrInstance();
            return true;
        }
        
        if (is_object($classOrInstance)) {
            static::$publicClasses[$aliases] = $classOrInstance;
            return true;
        }

        throw new RuntimeException('Failed to instantiate class ' . $aliases);
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
        if (TemplateConfig::$variablePrefixing === null) {
            return;
        }

        $prefix = (TemplateConfig::$variablePrefixing ? '_' : '');
        foreach ($attributes as $name => $value) {
            static::assertValidKey($name);           

            $key = is_integer($name) ? '_' . $name : $prefix . $name;
            if ($key === '_' || $key === '') {
                throw new RuntimeException("Invalid option key: '{$key}'. View option key must be non-empty strings.");
            }

            if (isset(static::$publicClasses[$key])) {
                throw new RuntimeException("Class with the same option name: '{$key}' already exists. Use a different name or enable variable prefixing to retain the name.");
            }

            static::$publicOptions[$key] = $value;
        }
    }

    /**
     * Check if option keys is a valid PHP virable key.
     * 
     * @param string $key key name to check.
     * @throws RuntimeException Throws if key is not a valid PHP virable key.
    */
    private static function assertValidKey(string $key): void 
    {
        if (preg_match('/[^a-zA-Z0-9_]/', $key)) {
            throw new RuntimeException("Invalid option key: '{$key}'. Only letters, numbers, and underscores are allowed in variable names.");
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
            $options['title'] = static::toTitle($options['active'], true);
        }

        if(!isset($options['subtitle'])){
            $options['subtitle'] = static::toTitle($options['active']);
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
        return static::viewRoot() . trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
        return static::withViewFolder(static::$viewFolder) . 'system_errors' . DIRECTORY_SEPARATOR . $filename . '.php';
    }

    /** 
     * Cache and store response to reuse on next request to same content.
     * 
     * @param DateTimeInterface|int|null $expire Cache expiration default, set to null to use default expiration from .env file.
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
    public function cache(DateTimeInterface|int|null $expiry = null): self 
    {
        $this->forceCache = true;

        if($expiry !== null){
            $this->cacheExpiry = $expiry;
        }

        return $this;
    }

    /**
     * Check if page cache has expired 
     * 
     * @return bool Returns true if cache doesn't exist or expired.
    */
    public function expired(): bool
    {
        return Helper::expired(static::$cacheFolder);
    }

    /**
     * Render cached content if cache exist.
     * 
     * @return int Returns status code success if cache exist and rendered else return error.
     * @throws RuntimeException Throws if called without calling `cache` method or if cache file os not found.
    */
    public function reuse(): int
    {
        if (!$this->forceCache) {
            throw new RuntimeException('Cannot call reuse method with first calling cache method');
        }

        $this->forceCache = false;
        $cache = Helper::getCache(static::$cacheFolder, $this->cacheExpiry);

        if ($cache->read()) {
            return STATUS_SUCCESS;
        }

        throw new RuntimeException('Cache not found');
    }

    /** 
     * Redirect to view url
     *
     * @param string $view view name
     * @param int $response_code response status code
     *
     * @return void
    */
    public function redirect(string $view, int $response_code = 0): void 
    {
        $to = APP_URL;

        if ($view !== '' && $view !== '/') {
            $to .= '/' . $view;
        }

        header("Location: $to", true, $response_code);
        exit(STATUS_SUCCESS);
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
                $cachable = $this->shouldCache();
                $engine = static::engine();

                if ($engine === 'smarty' || $engine === 'twig') {
                    $method = 'render' . $engine;
                    return static::$method(
                        $this->activeView . static::dot(), 
                        $this->templateDir, 
                        $options, 
                        $cachable,
                        Timestamp::ttlToSeconds($this->cacheExpiry),
                        $this->minifyCodeblocks, 
                        $this->codeblockButton,
                        $return 
                    );
                }

                $cache = null;

                if ($cachable) {
                    $cache = Helper::getCache(static::$cacheFolder, $this->cacheExpiry);
                    $cache->setType($options['viewType']);
        
                    if ($cache->has()) {
                        if($return){
                            return $cache->get();
                        }
        
                        return $cache->read();
                    }
                }

                if(TemplateConfig::$templateIsolation){
                    return static::renderIsolation(
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
            static::handleException($e, $options);
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
            include static::getErrorFolder('maintenance');

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

        FileSystem::permission('rw');
        http_response_code($status);
        ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));

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
            $instance = Smarty::getInstance(static::viewRoot());
        }

        try{
            $instance->setPath($templateDir);
            $instance->minify(static::$minifyContent, [
                'codeblock' => $minify,
                'copyable' => $copy
            ]);
            $instance->caching($caching, $cacheExpiry);

            if (!$instance->isCached($view)) {
                $instance->assignOptions($options);
                $instance->assignClasses(static::$publicClasses);
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
            $instance = Twig::getInstance(static::viewRoot(), $templateDir, [
                'caching' => $shouldCache,
                'charset' => env('app.charset', 'utf-8'),
                'strict_variables' => true,
                'autoescape' => 'html'
            ]);
        }

        try{
            $instance->setPath($templateDir);
            $instance->minify(static::$minifyContent, [
                'codeblock' => $minify,
                'copyable' => $copy
            ]);
            $instance->assignClasses(static::$publicClasses);

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
     * @param PageViewCache|null $__cache Cache instance if should cache page contents
     * @param bool $__return Should return view contents.
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private function renderDefault(array $options, ?PageViewCache $__cache = null, bool $__return = false): bool|string
    {
        $__viewType = $options['viewType'];

        if(TemplateConfig::$variablePrefixing !== null){
            static::extract($options);
            $options = null;
        }

        include_once $this->templateFile;
        $__contents = ob_get_clean();

        [$__headers, $__contents] = static::assertMinifyAndSaveCache(
            $__contents,
            $__viewType,
            $this->minifyCodeblocks, 
            $this->codeblockButton,
            $__cache
        );

        if($__return){
            return $__contents;
        }

        Header::parseHeaders($__headers);

        echo $__contents;

        if (ob_get_length() > 0) {
            ob_end_flush();
        }

        return true;
    }

    /**
     * Render without in isolation mode
     * 
     * @param string $__templateFile View template file 
     * @param array $options View options
     * @param PageViewCache|null $__cache Cache instance if should cache page contents
     * @param bool $__ignore Ignore html codeblock during minimizing
     * @param bool $__copy Allow copy on html code tag or not
     * @param bool $__return Should return view contents.
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private static function renderIsolation(
        string $__templateFile, 
        array $options,
        ?PageViewCache $__cache = null,
        bool $__ignore = true, 
        bool $__copy = false,
        bool $__return = false
    ): bool|string
    {
        $self = new class(static::$publicClasses) {
            /**
             * @var array<string,mixed> $classes
            */
            private static array $classes = [];

            /**
             * @var array<string,mixed> $classes
            */
            public function __construct(array $classes = [])
            {
                static::$classes = $classes;
            }

            /**
             * @var string $class
             * @return object|string|null
            */
            public function __get(string $class): mixed 
            {
                return static::$classes[$class] ?? null;
            }
        };

        static::$publicClasses = [];
        $__viewType = $options['viewType'];
        if(($__prefix = TemplateConfig::$variablePrefixing) && $__prefix !== null){
            $__prefix = ($__prefix ? '_' : '');

            foreach ($options as $__k => $__v) {
                static::assertValidKey($__k);   
                $__prefix = is_integer($__k) ? '_' . $__k : $__prefix . $__k; 
                ${$__prefix} = $__v;
            }
            $__k = null;
            $__v = null;
            $__prefix = null;
            $options = null;
        }

        include_once $__templateFile;
        $__contents = ob_get_clean();
        [$__headers, $__contents] = static::assertMinifyAndSaveCache(
            $__contents,
            $__viewType,
            $__ignore, 
            $__copy,
            $__cache
        );

        if($__return){
            return $__contents;
        }

        Header::parseHeaders($__headers);

        echo $__contents;

        if (ob_get_length() > 0) {
            ob_end_flush();
        }

        return true;
    }

    /**
     * Minifiy content if possible and store cache if cachable.
     * 
     * @param string|false $content
     * @param string $type
     * @param bool $ignor Ignore codeblocks 
     * @param bool $copy Add copy button to codeblocks 
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
        if (!empty($content)) {
            $headers = null;
            if (static::$minifyContent || static::$encodeContent) {
                $minifier = Helper::getMinifier(
                    $content, 
                    $type, 
                    $ignore, 
                    $copy,
                    static::$minifyContent,
                    static::$encodeContent
                );
                $content = $minifier->getContent();
                $headers = $minifier->getHeaders();
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
     * render render template view
     *
     * @param string $viewName view name
     * @param string $viewType Type of content not same as header Content-Type
     * - html
     * - json
     * - text
     * - xml
     *
     * @return self $this
    */
    public function view(string $viewName, string $viewType = 'html'): self 
    {
        $viewName = trim($viewName, '/');
        $viewType = strtolower($viewType);
    
        if(!in_array($viewType, ['html', 'json', 'text', 'xml'])){
            throw new RuntimeException(sprintf('Invalid argument, "%s" required types (html, json, text or xml). To render other formats use helper function `response()->render()`', $viewType));
        }

        $this->templateDir = static::withViewFolder(static::$viewFolder);

        if($this->subViewFolder !== ''){
            $this->templateDir .= $this->subViewFolder . DIRECTORY_SEPARATOR;
        }

        $this->templateFile = $this->templateDir . $viewName . static::dot();

        if (!file_exists($this->templateFile) && PRODUCTION) {
            $viewName = '404';
            $this->templateFile = $this->templateDir . $viewName . static::dot();
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
    public function render(array $options = [], int $status = 200): int 
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
    public function respond(array $options = [], int $status = 200): string
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
    public function viewInfo(): array 
    {
        $viewPath = root(__DIR__, static::$viewFolder) . $this->activeView . static::dot();
        clearstatcache(true, $viewPath);
        $info = [
            'location' => $viewPath,
            'engine' => static::engine(),
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
    public static function link(string $filename = ''): string 
    {
        $path = PRODUCTION ? '/' : static::calculateLevel(0);
        $root = (NOVAKIT_ENV === null && !PRODUCTION) ? 'public' : '';
        $base = rtrim($path . $root, '/') . '/';

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
    public function cacheable(bool $allow): self
    {
        $this->contextCaching = $allow;

        return $this;
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

        if (!$this->contextCaching || !static::$cacheView || $this->emptyTtl()) {
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
        if ($this->cacheExpiry === null || (is_int($this->cacheExpiry) && $this->cacheExpiry < 1)) {
            return true;
        }

        return false;
    }

    /** 
     * Fixes the broken css,image & links when added additional slash(/) at the router link
     * The function will add the appropriate relative base based on how many invalid link detected.
     *
     * @param int $level the directory level from base directory controller/foo(1) controller/foo/bar(2)
     *
     * @return string relative path 
    */
    private static function calculateLevel(int $level = 0): string 
    {
        if ($level === 0) {
            $uri = static::getViewUri();

            if (($pos = strpos($uri, '/public')) !== false) {
                $uri = substr($uri, $pos + 7);
            }

            $level = substr_count($uri, '/');
        }

        return str_repeat(($level >= 1 ? '../' : './'), $level);
    }

    /** 
     * Get template base view segments
     *
     * @return string template view segments
    */
    private static function getViewUri(): string
    {
        if(isset($_SERVER['REQUEST_URI'])){
            $basePath = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';

            $url = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen($basePath));

            if (($pos = strpos($url, '?')) !== false) {
                $url = substr($url, 0, $pos);
            }

            return '/' . trim($url, '/');
        }

        return '/';
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
        $view = static::getErrorFolder('exceptions');
        $trace = SHOW_DEBUG_BACKTRACE ? debug_backtrace() : [];
  
        extract($options);
        unset($options);

        include_once $view;
        $exception->log();
        exit(0);
    }

    /**
     * Convert view name to title and add suffix if specified
     *
     * @param string $view    View name
     * @param bool   $suffix  Whether to add suffix
     *
     * @return string View title
    */
    private static function toTitle(string $view, bool $suffix = false): string 
    {
        $view = str_replace(['_', '-', ','], [' ', ' ', ''], $view);
        $view = ucwords($view);

        if ($suffix) {
            if (!str_contains($view, '- ' . APP_NAME)) {
                $view .= ' - ' . APP_NAME;
            }
        }

        return trim($view);
    }
}