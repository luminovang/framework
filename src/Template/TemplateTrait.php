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
use \Luminova\Cache\PageMinifier;
use \Luminova\Cache\PageViewCache;
use \Luminova\Template\Smarty;
use \Luminova\Base\BaseConfig;
use \Luminova\Http\Header;
use \App\Controllers\Config\Template as TemplateConfig;
use \Luminova\Exceptions\AppException; 
use \Luminova\Exceptions\ViewNotFoundException; 
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Time\Time;
use \DateTimeInterface;

trait TemplateTrait
{ 
    /**
     * Flag for key not found.
     * 
     * @var string KEY_NOT_FOUND
    */
    public const KEY_NOT_FOUND = '__nothing__';

    /** 
     * Holds the project base directory
     * 
     * @var string $baseTemplateDir
    */
    private static string $baseTemplateDir = '';

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
     * Holds the template engin file extension 
     * 
     * @var string $templateEngine 
    */
    private string $templateEngine = 'default';

    /** 
     * Holds the project template file directory path
     * 
     * @var string $templateFolder 
    */
    private static string $templateFolder = 'resources/views';

    /** 
     * Holds template assets folder
     * 
     * @var string $assetsFolder 
    */
    private string $assetsFolder = 'assets';

    /** 
     * Holds the sub view template directory path
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
     * @var object $publicClasses 
    */
    private static array $publicClasses = [];

    /** 
     * Ignore view optimization
     * 
     * @var array $cacheIgnores
    */
    private array $cacheIgnores = [];

    /**
     * Holds relative file position depth 
     * 
     * @var int $relativeLevel 
    */
    private int $relativeLevel = 0;

    /**
     * Holds project current view base
     * 
     * @var string $projectBase 
    */
    private static string $projectBase = '/';

    /**
     * Response cache key
     * 
     * @var string|null $cacheKey 
    */
    private ?string $cacheKey = null;

    /**
     * Response cache expiry ttl
     * 
     * @var DateTimeInterface|int|null $cacheExpiry 
    */
    private DateTimeInterface|int|null $cacheExpiry = 0;

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
     * @var bool $canCopyCodeblock 
    */
    private bool $canCopyCodeblock = false;

    /**
     * View cache instance 
     * 
     * @var PageViewCache $viewCache 
    */
    private static ?PageViewCache $viewCache  = null;

    /** 
     * Initialize template
     *
     * @param string $dir template base directory
     *
     * @return void
     * @internal 
    */
    public function initialize(string $dir =__DIR__): void
    {
        static::$baseTemplateDir = root($dir);
        static::$minifyContent = env('page.minification', false);
        static::$cacheView = (bool) env('page.caching', false);
        $this->templateEngine = TemplateConfig::$templateEngine;
        $this->cacheExpiry = (int) env('page.cache.expiry', 0);
    }

    /** 
     * Get protected property or static::$publicOptions or static::$publicClasses
     *
     * @param string $key property name 
     *
     * @return mixed 
     * @internal 
    */
    public function __get(string $key): mixed 
    {
        $value = static::attrGetter($key) ?? null;

        if($value === static::KEY_NOT_FOUND){
            return $this->{$key} ?? null;
        }

        return $value;
    }

    /** 
     * Get property from static::$publicOptions or static::$publicClasses
     *
     * @param string $key property name 
     *
     * @return mixed 
     * @internal 
    */
    public static function attrGetter(string $key): mixed 
    {
        if (array_key_exists($key, static::$publicOptions)) {
            return static::$publicOptions[$key];
        }

        if (isset(static::$publicClasses[$key])) {
            return static::$publicClasses[$key];
        } 

        return static::KEY_NOT_FOUND;
    }

    /** 
     * Get registered class object static::$publicClasses
     *
     * @param string $key object class name 
     *
     * @return object|null 
     * @internal 
    */
    public static function getClass(string $key): ?object 
    {
        if (isset(static::$publicClasses[$key])) {
            return static::$publicClasses[$key];
        } 

        return null;
    }

    /** 
     * Get registered option
     *
     * @param string $key option key name 
     *
     * @return mixed
     * @internal 
    */
    public static function getOption(string $key): mixed 
    {
        $key = TemplateConfig::$useVariablePrefix ? "_{$key}" : $key;

        if (array_key_exists($key, static::$publicOptions)) {
            return static::$publicOptions[$key];
        } 

        return static::KEY_NOT_FOUND;
    }

    /** 
     * Check if class registered 
     *
     * @param string $class object class name 
     *
     * @return bool If class is registered
     * @internal 
    */
    public static function hasClass(string $class): bool 
    {
        return isset(static::$publicClasses[$class]);
    }

    /** 
     * Set if HTML codeblock tags should be ignore during page minification.
     *
     * @param bool $ignore true or false
     *
     * @return self $this
    */
    public function minifyCodeblock(bool $ignore): self 
    {
        $this->minifyCodeblocks = $ignore;

        return $this;
    }

    /** 
     * Set if HTML codeblock tags should include a copy button option.
     *
     * @param bool $allow true or false
     *
     * @return self $this
    */
    public function copyCodeblock(bool $allow): self 
    {
        $this->canCopyCodeblock = $allow;

        return $this;
    }

    /** 
     * Set project base folder
     *
     * @param string $base the base directory
     *
     * @return self $this
     * @internal 
    */
    public function setProjectBase(string $base): self
    {
        static::$projectBase = $base;

        return $this;
    }
   
    /** 
     * Get view root folder
     *
     * @return string root
    */
    private static function viewRoot(): string
    {
        if(static::$baseTemplateDir === ''){
            static::$baseTemplateDir = APP_ROOT;
        }

        return rtrim(static::$baseTemplateDir, '/') . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get template engine 
     *
     * @return string $$engin template extension
    */
    private function typeOfEngine(): string
    {
        return $this->templateEngine === 'smarty' ? '.tpl' : '.php';
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
        $this->subViewFolder = trim($path, '/');

        return $this;
    }

    /** 
     * Add a view to page cache ignore list.
     *
     * @param string|array<int, string> $viewName view name or array of view names.
     *
     * @return self $this Instance of self.
    */
    public function addCacheIgnore(array|string $viewName): self
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
            throw new RuntimeException('Invalid arguments provided, arguments expected a non-empty string');
        }

        $aliases ??= get_class_name($classOrInstance);

        if (isset(static::$publicClasses[$aliases])) {
            throw new RuntimeException("Class with the same name: '{$aliases}' already exists.");
        }

        if (is_string($classOrInstance)) {
            $instance = new $classOrInstance();
        } elseif (is_object($classOrInstance)) {
            $instance = $classOrInstance;
        } else {
            throw new RuntimeException('Failed to instantiate class ' . $aliases);
        }

        static::$publicClasses[$aliases] = $instance;
        
        return true;
    }

    /**
     * Sets project template options.
     * 
     * @param  array<string, mixed> $attributes
     * 
     * @return int Number of registered options
     * @throws RuntimeException If there is an error setting the attributes.
    */
    private static function setOptions(array $attributes): int
    {
        if (!is_array($attributes)) {
            throw new RuntimeException("Invalid attributes: '{$attributes}'. Expected an array.");
        }

        $count = 0;
        foreach ($attributes as $name => $value) {
            $key = TemplateConfig::$useVariablePrefix ? "_{$name}" : $name;

            if (!is_string($key) || $key === '_' || $key === '') {
                throw new RuntimeException("Invalid option key: '{$key}'. View option key must be non-empty strings.");
            }

            if (isset(static::$publicClasses[$key])) {
                throw new RuntimeException("Class with the same name: '{$key}' already exists. use a different name or enable variable prefixing to retain the name");
            }

            static::$publicOptions[$key] = $value;
            $count++;
        }

        return $count;
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
        return static::viewRoot() . trim($path, '/') . DIRECTORY_SEPARATOR;
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
        return static::withViewFolder(static::$templateFolder) . 'system_errors' . DIRECTORY_SEPARATOR . $filename . '.php';
    }

    /** 
     * Cache and store response to reuse on next request to same content.
     * 
     * @param string $key Cache content key
     * @param DateTimeInterface|int|null $expire Cache expiration default, set to null to use default expiration from .env file.
     * 
     * @example $this-app->cache('key', 60)->respond('contents'); Check if already cached before caching again.
     * 
     * @return self $this Instance of self.
    */
    public function cache(string $key, DateTimeInterface|int|null $expiry = null): self 
    {
        $this->cacheKey = $key;

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
        if($this->cacheKey === ''){
            return false;
        }

        return !PageViewCache::expired($this->cacheKey, static::withViewFolder(TemplateConfig::$pageCacheFolder));
    }

    /**
     * Render cached content if cache exist.
     * 
     * @return bool Returns true if cache exist and rendered else return false.
    */
    public function renderCache(): bool
    {
        if ($this->cacheKey === '') {
            return false;
        }

        $cache = static::getCacheInstance($this->cacheKey, $this->cacheExpiry);

        if ($cache->readContent()) {
            return true;
        }
    }

    /** 
     * Render a response to view, suitable for api or cli rendering.
     * If cache is enable and it returned cached content instead.
     * 
     * @param mixed $content Content to ender.
     * @param string $type Type of content passed [json, html, xml, text]
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @example Usage example with cache. ```
     * $cache = $this-app->cache('key', 60); 
     * if($cache->expired()){
     *      $cache->respond('contents');
     * }else{
     *      $cache->renderCache();
     * }```
     * 
     * @return void
    */
    public function respond(mixed $content, string $type = 'json', int $status = 200): void 
    {
        $cacheInstance = null;
        $viewHeaderInfo = [];

        http_response_code($status);
        ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));

        if(static::$minifyContent){
            $minifierInstance = static::newCompressed($content, $type, $this->minifyCodeblocks, $this->canCopyCodeblock);
            $content = $minifierInstance->getMinified();
            $viewHeaderInfo = $minifierInstance->getHeaderInfo();
        }

        if ($this->contextCaching && static::$cacheView && $this->cacheKey !== null && !$this->emptyTtl() && $content != null) {
            $cacheInstance = static::getCacheInstance(
                $this->cacheKey, 
                $this->cacheExpiry
            );
            $cacheInstance->setType($type);
            $cacheInstance->saveCache($content, $viewHeaderInfo);
        }

        $this->cacheKey = null;
        //$this->cacheExpiry = 0;

        exit(static::$minifyContent ? STATUS_SUCCESS : $content);
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
     * @return void
     * @throws ViewNotFoundException
    */
    private function renderViewContent(array $options = [], int $status = 200): void 
    {
        try {
            if($this->iniRenderSetup($status)){
                $shouldCache = $this->shouldCache();
                if ($this->templateEngine === 'smarty') {
                    $this->renderSmarty($options, $shouldCache);
                } else {
                    if(TemplateConfig::$viewIsolation){
                        static::renderIsolation($this->templateFile, $shouldCache, $this->cacheExpiry, $options, $this->minifyCodeblocks, $this->canCopyCodeblock);
                    }else{
                        $this->renderDefault($options, $shouldCache);
                    }
                }
            }
        } catch (ViewNotFoundException|AppException $e) {
            static::handleException($e, $options);
        }

        exit(STATUS_SUCCESS);
    }

    /**
     * Initialize rendering setup
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @return bool 
     * @throws ViewNotFoundException
     * @throws RuntimeException
    */
    private function iniRenderSetup(int $status = 200): bool
    {
        defined('ALLOW_ACCESS') || define('ALLOW_ACCESS', true);

        if (MAINTENANCE) {
            Header::headerNoCache(503);
            header("Retry-After: 3600");
            include static::getErrorFolder('maintenance');

            return false;
        }

        if (!file_exists($this->templateFile)) {
            Header::headerNoCache(404);
            throw new ViewNotFoundException($this->activeView, 404);
        } 

        FileSystem::permission('rw');
        http_response_code($status);
        ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));

        return true;
    }

    /**
     * Render with smarty
     * 
     * @param array $options View options
     * @param bool $shouldCache Should cache page contents
     * 
     * @return void 
    */
    private function renderSmarty(array $options, bool $shouldCache): void
    {
        static $smarty = null;
        $smarty ??= new Smarty(static::viewRoot());
        try{
            $smarty->setDirectories(
                $this->templateDir, 
                TemplateConfig::$smartyCompileFolder,
                TemplateConfig::$smartyConfigFolder,
                TemplateConfig::$smartyCacheFolder
            );
            $smarty->assignOptions($options);
            $smarty->caching($shouldCache);
            $smarty->display($this->activeView . $this->typeOfEngine());
        }catch(AppException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Render without smarty using default .php template engine.
     * 
     * @param array $options View options
     * @param bool $shouldCache Should cache page contents
     * 
     * @return void 
    */
    private function renderDefault(array $options, bool $shouldCache): void
    {
        $cacheInstance = null;

        if ($shouldCache) {
         
            $cacheInstance = static::getCacheInstance(static::generateKey(), $this->cacheExpiry);
            $cacheInstance->setType($options["viewType"]);

            if ($cacheInstance->hasCache() && $cacheInstance->readContent()) {
                exit(STATUS_SUCCESS);
            }
        }

        static::setOptions($options);
        unset($options);

        include $this->templateFile;
        $viewContents = ob_get_clean();
        $viewHeaderInfo = null;
        
        if (static::$minifyContent) {
            $minifierInstance = static::newCompressed($viewContents, static::getOption('viewType'), $this->minifyCodeblocks, $this->canCopyCodeblock);
            $viewContents = $minifierInstance->getMinified();
            $viewHeaderInfo = $minifierInstance->getHeaderInfo();
        }

        if ($shouldCache && $cacheInstance !== null) {
            $viewHeaderInfo ??= static::requestInfo();
            $cacheInstance->saveCache($viewContents, $viewHeaderInfo);

            if(static::$minifyContent){
                exit(STATUS_SUCCESS);
            }
        }

        unset($cacheInstance, $viewHeaderInfo);

        exit($viewContents);
    }

    /**
     * Render without in isolation mode
     * 
     * @param string $templateFile View template file 
     * @param bool $shouldCache Should cache page contents
     * @param DateTimeInterface|int|null $cacheExpiry Cache expiry
     * @param array $options View options
     * @param bool $ignore Ignore html codeblock during minimizing
     * @param bool $copy Allow copy on html code tag or not
     * 
     * @return void 
    */
    private static function renderIsolation(
        string $templateFile, 
        bool $shouldCache, 
        DateTimeInterface|int|null $cacheExpiry,
        array $options, 
        bool $ignore = true, 
        bool $copy = false
    ): void
    {
        $cacheInstance = null;
        //$cacheExpiry = (int) env('page.cache.expiry', 0);
        $self = (object) static::$publicClasses;

        if ($shouldCache) {
            $cacheInstance = static::getCacheInstance(static::generateKey(), $cacheExpiry);
            $cacheInstance->setType($options['viewType']);

            if ($cacheInstance->hasCache() && $cacheInstance->readContent()) {
                exit(STATUS_SUCCESS);
            }
        }

        extract($options);
        unset($options);

        include $templateFile;
        $viewContents = ob_get_clean();
        $viewHeaderInfo = null;

        if (static::$minifyContent) {
            $minifierInstance = static::newCompressed($viewContents, $viewType, $ignore, $copy);
            $viewContents = $minifierInstance->getMinified();
            $viewHeaderInfo = $minifierInstance->getHeaderInfo();
        }

        if ($shouldCache && $cacheInstance !== null) {
            $viewHeaderInfo ??= static::requestInfo();
            $cacheInstance->saveCache($viewContents, $viewHeaderInfo);

            if(static::$minifyContent){
                exit(STATUS_SUCCESS);
            }
        }

        exit($viewContents);
    }

    /** 
     * Render minification
     *
     * @param mixed $contents view contents output buffer
     * @param string $type content type
     * @param bool $ignore
     * @param bool $copy 
     *
     * @return PageMinifier $compress
    */
    private static function newCompressed(mixed $contents, string $type = 'html', bool $ignore = true, bool $copy = false): PageMinifier 
    {
        static $minifier = null;
        
        $minifier ??= new PageMinifier();
        $minifier->minifyCodeblock($ignore);
        $minifier->allowCopyCodeblock($copy);

        $methods = [
            'json' => 'json',
            'text' => 'text',
            'html' => 'html',
            'xml' => 'xml',
        ];
        
        $method = $methods[$type] ?? 'run';
        $minifier->$method($contents);        

        return $minifier;
    }

    /** 
     * render render template view
     *
     * @param string $viewName view name
     * @param string $viewType Type of content not same as Content-Type
     *
     * @return self $this
    */
    public function view(string $viewName, string $viewType = 'html'): self 
    {
        $viewName = trim($viewName, '/');
        $viewType = strtolower($viewType);
    
        if(!in_array($viewType, ['html', 'json', 'text', 'xml'], true)){
            throw new RuntimeException('Invalid argument, ' . $viewType. ' required (html, json, text or xml).');
        }

        $this->templateDir = static::withViewFolder(static::$templateFolder);

        if($this->subViewFolder !== ''){
            $this->templateDir .= $this->subViewFolder . DIRECTORY_SEPARATOR;
        }

        $this->templateFile = $this->templateDir . $viewName . $this->typeOfEngine();

        if (!file_exists($this->templateFile) && PRODUCTION) {
            $viewName = '404';
            $this->templateFile = $this->templateDir . $viewName . $this->typeOfEngine();
        }

        $this->viewType = $viewType;
        $this->activeView = $viewName;

        return $this;
    }

    /** 
     * Rnder view content with and additional options as global to view, which can be accessed within the template view.
     *
     * @param array<string, mixed> $options additional parameters to pass in the template file $this->_myOption
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @example `$this->app->view('name')->render([])` Display your template view with options.
     * 
     * @return void
     * @throws RuntimeException
    */
    public function render(array $options = [], int $status = 200): void 
    {
        $options['viewType'] = $this->viewType;
        //$options['base'] = static::link();
        //$options['assets'] = static::link('assets/);
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

        $this->renderViewContent($options, $status);
    }

    /**
     * Retrieves information about a view file.
     *
     * @return array An associative array containing information about the view file:
     *               - 'view': The full path to the view file.
     *               - 'size': The size of the view file in bytes.
     *               - 'timestamp': The last modified timestamp of the view file.
     *               - 'modified': The last modified date and time of the view file (formatted as 'Y-m-d H:i:s').
     *               - 'dirname': The directory name of the view file.
     *               - 'extension': The extension of the view file.
     *               - 'filename': The filename (without extension) of the view file.
    */
    public function viewinfo(): array 
    {
        $viewPath = root(__DIR__, 'resources/views') . $this->activeView . $this->typeOfEngine();
        $info = [
            'view' => $viewPath,
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
        //$base = $this->activeView === '404' ? static::$projectBase : rtrim($path . $root, '/') . '/';
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
    public function setContextCaching(bool $allow): self 
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
     * Generate cache file key
    */
    private static function generateKey(?string $url = null): string 
    {
        $url ??= ($_SERVER['REQUEST_URI']??'index');
        $key = str_replace(['/', '?', '&', '=', '#'], ['-', '-', '-', '-', '-'], $url);
        $key = preg_replace('/-+/', '-', $key);
        $key = trim($key, '-');

        return $key;
    }

    /** 
    * Get page view cache instance
    *
    * @param string $key
    * @param string $file 
    * @param DateTimeInterface|int|null $expiry 
    *
    * @return PageViewCache
    */
    private static function getCacheInstance(string $key, DateTimeInterface|int|null $expiry = 0): PageViewCache
    {
        if(static::$viewCache === null){
            static::$viewCache = new PageViewCache();
        }

        static::$viewCache->setExpiry($expiry);
        static::$viewCache->setDirectory(static::withViewFolder(TemplateConfig::$pageCacheFolder));
        static::$viewCache->setKey($key);

        return static::$viewCache;
    }

    /** 
     * Get output headers
     * 
     * @return array<string, mixed> $info
    */
    private static function requestInfo(): array
    {
        $responseHeaders = headers_list();
        $info = [];

        foreach ($responseHeaders as $header) {
            [$name, $value] = explode(':', $header, 2);

            $name = trim($name);
            $value = trim($value);

            switch ($name) {
                case 'Content-Type':
                    $info['Content-Type'] = $value;
                    break;
                case 'Content-Length':
                    $info['Content-Length'] = (int) $value;
                    break;
                case 'Content-Encoding':
                    $info['Content-Encoding'] = $value;
                    break;
            }
        }

        return $info;
    }

    /** 
     * Handle exceptions
     *
     * @param AppException $exception
     * @param array $options view options
     *
     * @return void 
    */
    private static function handleException(AppException $exception, array $options = []): void 
    {
        $view = static::getErrorFolder('exceptions');
        $trace = SHOW_DEBUG_BACKTRACE ? debug_backtrace() : [];
  
        extract($options);
        unset($options);

        include_once $view;

        $exception->logMessage();
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
            if (!str_contains($view, '| ' . APP_NAME)) {
                $view .= ' | ' . APP_NAME;
            }
        }

        return trim($view);
    }
}