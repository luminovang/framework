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
use \Luminova\Application\Foundation; 
use \Luminova\Exceptions\AppException; 
use \Luminova\Interface\ExceptionInterface; 
use \Luminova\Exceptions\ViewNotFoundException; 
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Time\Time;
use \Luminova\Time\Timestamp;
use \Luminova\Template\Helper;
use \Luminova\Cache\ViewCache;
use \App\Config\Template as TemplateConfig;
use \DateTimeInterface;

trait View
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
     * @var string|null $documentRoot
    */
    private static ?string $documentRoot = null;

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
     * Ignore or allow view optimization
     * 
     * @var array<string,array> $cacheOption
    */
    private array $cacheOption = [];

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
     * Default cache path.
     * 
     * @var string $cacheFolder 
    */
    private static string $cacheFolder = 'writeable/caches/default';

    /**
     * Minify page content.
     * 
     * @var bool $minifyContent 
    */
    private static bool $minifyContent = false;

    /**
     * Should cache view base.
     * 
     * @var bool $cacheView
    */
    private bool $cacheView = false;

    /**
     * Should minify codeblock tags.
     * 
     * @var bool $minifyCodeblocks 
    */
    private bool $minifyCodeblocks = false;

    /**
     * Allow copy codeblock.
     * 
     * @var bool $codeblockButton 
    */
    private bool $codeblockButton = false;

    /**
     * Supported view types.
     * 
     * @var string[] $supportedTypes
    */
    private static array $supportedTypes = ['html', 'json', 'text', 'txt', 'xml', 'js', 'css', 'rdf', 'atom', 'rss'];

    /**
     * View headers.
     * 
     * @var array<string,mixed> $headers
    */
    private array $headers = [];

    /** 
     * Initialize template view configuration.
     *
     * @return void
     * @internal 
    */
    protected final function initialize(): void
    {
        self::$config ??= new TemplateConfig();
        self::$documentRoot ??= root();
        self::$minifyContent = (bool) env('page.minification', false);
        self::$cacheFolder = self::withViewFolder(self::trimDir(self::$config->cacheFolder) . 'default');
        $this->cacheView = (bool) env('page.caching', false);
        $this->cacheExpiry = (int) env('page.cache.expiry', 0);
    }

    /** 
     * Get property from self::$publicOptions or self::$publicClasses
     *
     * @param string $key The property name.
     *
     * @return mixed Return option value or class object.
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
     * @return self Returns the instance of the View class or BaseApplication, depending on where it's called.
    */
    public final function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }

    /** 
     * Set sub view folder name to look for view file within the `resources/views/`.
     *
     * @param string $path folder name to search for view.
     *
     * @return self Returns the instance of the View class or BaseApplication, depending on where it's called.
     * 
     * - If called in controller `onCreate` or `__construct` method, the entire controller view will be searched in the specified folder.
     * - If called in application `onCreate` or `__construct` method, the entire application view will be searched in the specified folder.
     * - If called with the controller method before rendering view, only the method view will be searched in the specified folder.
    */
    public final function setFolder(string $path): self
    {
        $this->subViewFolder = trim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    /** 
     * Adds a view to the list of views that should not be cached.
     *
     * @param string|string[] $viewName A single view name or an array of view names to exclude from caching.
     *
     * @return self Returns the instance of the View class or BaseApplication, depending on where it's called.
     * 
     * > It is recommended to use this method within the `onCreate` or `__construct` methods of your application.
    */
    public final function noCaching(array|string $viewName): self
    {
        if(is_string($viewName)){
            $this->cacheOption['ignore'][] = $viewName;
        }else{
            $this->cacheOption['ignore'] = $viewName;
        }
        
        return $this;
    }

    /** 
     * Specifies views that should exclusively be cached.
     *
     * @param string|string[] $viewName A single view name or an array of view names to cache.
     *
     * @return self Returns the instance of the View class or BaseApplication, depending on where it's called.
     * 
     * > It is recommended to invoke this method within the `onCreate` or `__construct` methods of your application.
     */
    public final function cacheOnly(array|string $viewName): self
    {
        if(is_string($viewName)){
            $this->cacheOption['only'][] = $viewName;
        }else{
            $this->cacheOption['only'] = $viewName;
        }
        
        return $this;
    }

    /** 
     * Set if view base context should be cached.
     * Useful in api context to manually handle caching.
     *
     * @param bool $allow Weather to allow caching of views.
     *
     * @return self Return instance of View or BaseApplication depending on where its called,
     * 
     * - If set in controller `onCreate` or `__construct`, the entire views within the controller will not be cached.
     * - If set in application class, the entire application views will not be cached.
    */
    public final function cacheable(bool $allow): self
    {
        $this->cacheView = $allow;

        return $this;
    }

    /**
     * Export / Register a class instance to make it accessible within the view template.
     *
     * @param class-string<\T>|class-object<\T> $class The class name or instance of a class to register.
     * @param string|null $alias Optional class alias to use in accessing class object (default: null).
     * @param bool $initialize Whether to initialize class-string or leave it as static class (default: true).
     * 
     * @return true Return true on success, false on failure.
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
     * @return self Returns the instance of the View class or BaseApplication, depending on where it's called.
     * 
     * @example - Usage example with cache.
     * ```
     * $cache = $this-app->cache(60); 
     * //Check if already cached before caching again.
     * if($cache->expired()){
     *      $heavy = $db->doHeavyProcess();
     *      $cache->view('foo')->render(['data' => $heavy]);
     * }else{
     *      $cache->reuse();
     * }```
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
     * @param string|null $viewType The view content extension type (default: `html`).
     * 
     * @return bool Returns true if cache doesn't exist or expired.
     * @throws RuntimeException Throw if the cached version doesn't match with the current view type.
    */
    public final function expired(string|null $viewType = 'html'): bool
    {
        $expired = Helper::getCache(self::$cacheFolder, Foundation::cacheKey())->expired($viewType);

        if($expired === 404){
            throw new RuntimeException('Invalid mismatch view type: ' . $viewType);
        }

        return $expired;
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
        $cache = Helper::getCache(self::$cacheFolder, Foundation::cacheKey(), $this->cacheExpiry);

        if ($cache->read()) {
            return STATUS_SUCCESS;
        }

        throw new RuntimeException('No cache not found to reuse.');
    }

    /**
     * Set response header.
     *
     * @param string $key The header key.
     * @param mixed $value The header value for key.
     * 
     * @return self Return instance of View or BaseApplication depending on where its called.
     */
    public function header(string $key, mixed $value): self 
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Set response header.
     *
     * @param array<string,mixed> $headers The headers key-pair.
     * 
     * @return self Return instance of View or BaseApplication depending on where its called.
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /** 
     * Render template view file withing the `resources/views` directory.
     * Do not include the extension type (e.g, `.php`, `.tpl`, `.twg`), only the file name.
     *
     * @param string $viewName The view file name without extension type (e.g, `index`).
     * @param string $viewType The view content extension type (default: `html`).
     * 
     * Supported View Types: 
     * 
     * - html Html content.
     * - json Json content.
     * - text|txt Plain text content.
     * - xml  Xml content.
     * - js   JavaScript content.
     * - css  CSS content.
     * - rdf  RDF content.
     * - atom Atom content.
     * - rss  RSS feed content.
     *
     * @return self Return instance of View or BaseApplication depending on where its called.
     * @throws RuntimeException Throw if invalid or unsupported view type specified.
    */
    public final function view(string $viewName, string $viewType = 'html'): self 
    {
        $viewName = trim($viewName, '/');
        $viewType = strtolower($viewType);
    
        if(!in_array($viewType, static::$supportedTypes)){
            $supported = implode(', ', static::$supportedTypes);
            throw new RuntimeException(sprintf(
                'Invalid argument, unsupported view type: "%s" for view: "%s", supported types (%s). To render other formats use helper function `response()->render()`', 
                $viewType, 
                $viewName,
                $supported
            ));
        }

        $this->templateDir = self::withViewFolder(self::$viewFolder);

        if($this->subViewFolder !== ''){
            $this->templateDir .= $this->subViewFolder . DIRECTORY_SEPARATOR;
        }

        $this->templateFile = $this->templateDir . $viewName . self::dot();

        if (PRODUCTION && !file_exists($this->templateFile)) {
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
     * @param array<string,mixed> $options Additional parameters to pass in the template file.
     * @param int $status The HTTP status code (default: 200 OK).
     * 
     * @return int Return status code STATUS_SUCCESS or STATUS_ERROR on failure.
     * @throws RuntimeException If the view rendering fails.
     * 
     * @example - Display your template view with options.
     * 
     * ```php
     * $this->app->view('name')->render([...])
     * ```
     */
    public final function render(array $options = [], int $status = 200): int 
    {
        return ($this->call($options, $status) ? STATUS_SUCCESS : STATUS_ERROR);
    }

    /**
     * Get the rendered contents of a view.
     *
     * @param array<string,mixed> $options Additional parameters to pass in the template file.
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return string Return the compiled view contents.
     * @throws RuntimeException If the view rendering fails.
     * 
     * @example - Display your template view or send as an email.
     * 
     * ```php
     * $content = $this->app->view('name', 'html')->respond(['foo' => 'bar']);
     * ```
     */
    public final function respond(array $options = [], int $status = 200): string
    {
        return $this->call($options, $status, true);
    }

     /** 
     * Redirect to another view url.
     *
     * @param string $view The view name or view path to redirect to.
     * @param int $response_code The redirect response status code (default: 0).
     *
     * @return void
    */
    public final function redirect(string $view, int $response_code = 0): void 
    {
        $view = start_url($view);
        header("Location: {$view}", true, $response_code);
        exit(STATUS_SUCCESS);
    }

    /**
     * Retrieves information about a view file.
     *
     * @return array<string,mixed> Return an associative array containing information about the view file.
     * 
     * Return Keys:
     * 
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
     * Create a relative url to view or file ensuring the url starts from public root directory.
     * 
     * @param string $filename Optional view, path or file to prepend to root URL.
     * 
     * @return string Return full url to view or file.
    */
    public static final function link(string $filename = ''): string 
    {
        $base = (PRODUCTION ? '/' : Helper::relativeLevel());
        
        if($filename === ''){
            return $base;
        }

        return $base . ltrim($filename, '/');
    }

    /** 
     * Get application root folder.
     *
     * @return string Return the application root directory.
    */
    private static function viewRoot(): string
    {
        if(self::$documentRoot === null){
            self::$documentRoot = APP_ROOT;
        }

        return self::$documentRoot;
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
     * Get template engine type.
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
     * @param array $options additional parameters to pass in the template file.
     * @param int $status HTTP status code (default: 200 OK).
     * @param bool $return Weather to return content instead.
     *
     * @return bool|string  Return true on success, false on failure.
     * @throws ViewNotFoundException Throw if view file is not found.
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
                        $return,
                        $this->headers
                    );
                }

                $cache = null;

                if ($cacheable) {
                    $cache = Helper::getCache(self::$cacheFolder, Foundation::cacheKey(), $this->cacheExpiry);
   
                    if ($cache->expired($options['viewType']) === false) {
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
                        $return,
                        $this->headers
                    );
                }

                return $this->renderDefault($options, $cache, $return, $this->headers);
            }
        } catch (ExceptionInterface $e) {
            self::handleException($e, $options);
        }

        return false;
    }

    /**
     * Initialize rendering setup.
     * 
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return bool Return true if setup is ready.
     * @throws ViewNotFoundException Throw if view file is not found.
     * @throws RuntimeException Throw of error occurred during rendering.
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
     * Render with smarty engine.
     * 
     * @param string $view View file name.
     * @param string $templateDir View template directory.
     * @param array $options View options.
     * @param bool $caching Should cache page contents.
     * @param int $cacheExpiry Cache expiration.
     * @param bool $minify Should minify.
     * @param bool $copy Should include code copy button.
     * @param bool $return Should return content instead of render.
     * @param array $customHeaders Additional headers.
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
        bool $return = false,
        array $customHeaders = []
    ): bool|string
    {
        static $instance = null;

        if($instance === null){
            $instance = Smarty::getInstance(self::$config, self::viewRoot());
        }

        try{
            $instance->headers($customHeaders);
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
     * Render with twig engine.
     * 
     * @param string $view View file name.
     * @param string $templateDir View template directory.
     * @param array $options View options.
     * @param bool $shouldCache Should cache page contents.
     * @param int $cacheExpiry Cache expiration.
     * @param bool $minify Should minify.
     * @param bool $copy Should include code copy button.
     * @param bool $return Should return content instead of render.
     * @param array $customHeaders Additional headers.
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
        bool $return = false,
        array $customHeaders = []
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
            $instance->headers($customHeaders);
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
     * @param array $options View options.
     * @param ViewCache|null $_lmv_cache Cache instance if should cache page contents.
     * @param bool $_lmv_return Should return view contents.
     * @param array $customHeaders Additional headers.
     * 
     * @return bool|string Return true on success, false on failure.
    */
    private function renderDefault(
        array $options, 
        ?ViewCache $_lmv_cache = null, 
        bool $_lmv_return = false,
        array $customHeaders = []
    ): bool|string
    {
        $lmv_view_type = $options['viewType']??'html';
        if(self::$config->variablePrefixing !== null){
            self::extract($options);
        }

        include_once $this->templateFile;
        $_lmv_contents = ob_get_clean();     
        self::inlineErrors($_lmv_contents);

        [$_lmv_headers, $_lmv_contents] = self::assertMinifyAndSaveCache(
            $_lmv_contents,
            $lmv_view_type,
            $this->minifyCodeblocks, 
            $this->codeblockButton,
            $_lmv_cache,
            $customHeaders
        );

        if($_lmv_return){
            return $_lmv_contents;
        }

        Header::parseHeaders($_lmv_headers);
        echo $_lmv_contents;

        return true;
    }

    /**
     * Render without in isolation mode.
     * 
     * @param string $_lmv_viewfile View template file.
     * @param array $options View options.
     * @param ViewCache|null $_lmv_cache Cache instance if should cache page contents.
     * @param bool $_lmv_ignore Ignore html codeblock during minimizing.
     * @param bool $_lmv_copy Allow copy on html code tag or not.
     * @param bool $_lmv_return Should return view contents.
     * @param array $customHeaders Additional headers.
     * 
     * @return bool|string Return true on success, false on failure.
     * @throws RuntimeException Throw if error occurred.
    */
    private static function renderIsolation(
        string $_lmv_viewfile, 
        array $options,
        ?ViewCache $_lmv_cache = null,
        bool $_lmv_ignore = true, 
        bool $_lmv_copy = false,
        bool $_lmv_return = false,
        array $customHeaders = []
    ): bool|string
    {
        $self = self::newSelfInstance();
        self::$publicClasses = [];
        $lmv_view_type = $options['viewType']??'html';
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
            $lmv_view_type,
            $_lmv_ignore, 
            $_lmv_copy,
            $_lmv_cache,
            $customHeaders
        );

        if($_lmv_return){
            return $_lmv_contents;
        }

        Header::parseHeaders($_lmv_headers);
        echo $_lmv_contents;

        return true;
    }

    /**
     * Initialize self class keyword.
     * 
     * @return class-object Return new instance of anonymous classes.
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
     * @param ViewCache|null $cache Cache instance.
     * @param array $customHeaders Additional headers.
     * 
     * @return array<int,mixed> Return array of contents and headers.
    */
    private function assertMinifyAndSaveCache(
        string|false $content, 
        string $type, 
        bool $ignore, 
        bool $copy, 
        ?ViewCache $cache = null,
        array $customHeaders = []
    ): array 
    {
        if ($content !== false && $content !== '') {
            $headers = null;
            if (self::$minifyContent && $type === 'html') {
                $minify = Helper::getMinification(
                    $content, 
                    $type, 
                    $ignore, 
                    $copy
                );
                $content = $minify->getContent();
                $headers = $minify->getHeaders();
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
        $headers = array_merge($customHeaders, $headers);
        return [$headers, $content];
    }
    
    /** 
     * Check if view should be optimized page caching or not.
     *
     * @return bool Return true if view should be cached, otherwise false.
    */
    private function shouldCache(): bool
    {
        if ($this->forceCache) {
            return true;
        }

        if (!$this->cacheView || $this->emptyTtl()) {
            return false;
        }

        if($this->cacheOption === []){
            return true;
        }

        // Check if the view is in the 'only' list
        if (isset($this->cacheOption['only'])) {
            return in_array($this->activeView, $this->cacheOption['only'], true);
        }

        // Check if the view is in the 'ignore' list
        return !in_array($this->activeView, $this->cacheOption['ignore'] ?? [], true);
    }

    /** 
     * Check if cache expiry is empty.
     *
     * @return bool Return true if no cache expiration found.
    */
    private function emptyTtl(): bool 
    {
        return ($this->cacheExpiry === null || (is_int($this->cacheExpiry) && $this->cacheExpiry < 1));
    }

    /** 
     * Handle exceptions.
     *
     * @param ExceptionInterface $exception The exception interface thrown.
     * @param array<string,mixed> $options The view options.
     *
     * @return void 
    */
    private static function handleException(ExceptionInterface $exception, array $options = []): void 
    {
        if(PRODUCTION){
            $view = self::getErrorFolder('404');
        }else{
            $view = self::getErrorFolder('view.error');
            $trace = SHOW_DEBUG_BACKTRACE ? debug_backtrace() : [];
        }

        extract($options);
        unset($options);

        include_once $view;
        $exception->log();
        exit(STATUS_SUCCESS);
    }

    /**
     * Sets project template options.
     * 
     * @param array<string,mixed> $attributes The attributes to set.
     * 
     * @return void
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
            $key = (is_int($name) ? '_' . $name : $prefix . $name);

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
     * 
     * @return void
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
     * Parse user template options.
     * 
     * @param array<string,mixed> $options The template options.
     * 
     * @return array<string,mixed> Return the parsed options.
    */
    private function parseOptions(array $options = []): array 
    {
        $options['viewType'] = $this->viewType;
        $options['href'] = static::link();
        $options['asset'] = $options['href'] . 'assets/';
        $options['active'] = $this->activeView;
        
        if(isset($options['nocache']) && !$options['nocache']){
            $this->cacheOption['ignore'][] = $this->activeView;
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
     * Get base view file directory.
     *
     * @param string Path to trim.
     *
     * @return string Return trimmed directory path.
    */
    private static function trimDir(string $path): string 
    {
        return  rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get base view file directory.
     *
     * @param string The view directory path. 
     *
     * @return string Return view file directory.
    */
    private static function withViewFolder(string $path): string 
    {
        return self::viewRoot() . trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get error file from directory.
     *
     * @param string $filename file name.
     *
     * @return string Return error directory.
    */
    private static function getErrorFolder(string $filename): string 
    {
        return self::withViewFolder(self::$viewFolder) . 'system_errors' . DIRECTORY_SEPARATOR . $filename . '.php';
    }
}