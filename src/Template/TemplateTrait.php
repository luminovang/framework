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

use \Luminova\Cache\PageMinifier;
use \Luminova\Cache\PageViewCache;
use \Luminova\Template\Smarty;
use \Luminova\Base\BaseConfig;
use \App\Controllers\Config\Template as TemplateConfig;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\AppException; 
use \Luminova\Exceptions\ViewNotFoundException; 
use \Luminova\Exceptions\RuntimeException;
use stdClass;

trait TemplateTrait
{ 
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
     * Holds the view template optimize file directory path
     * 
     * @var string $pageCacheFolder 
    */
    private static string $pageCacheFolder = "writeable/caches/optimize";

    /** 
     * Holds template assets folder
     * 
     * @var string $assetsFolder 
    */
    private string $assetsFolder = 'assets';

    /** 
     * Holds the view template optimize full file directory path
     * 
     * @var string $optimizerFile 
    */
    private string $optimizerFile = '';

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
     * Holds template project root
     * 
     * @var string $rootDirectory 
    */
    private string $rootDirectory = '';

    /**
     * Holds template html content
     * 
     * @var string $templateContents 
    */
    private string $templateContents = '';

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
    private string $projectBase = '/';

    /**
     * Response cache key
     * 
     * @var string|null $cacheKey 
    */
    private ?string $cacheKey = null;

    /**
     * Response cache expiry ttl
     * 
     * @var int $cacheExpiry 
    */
    private int $cacheExpiry = 0;

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
    private bool $cacheView = true;

    /**
     * Should minify codeblock tags
     * 
     * @var bool $minifyCodeTags 
    */
    private bool $minifyCodeTags = false;

    /**
     * Should access options as variable
     * And isolate view rendering
     * 
     * @var bool $viewIsolation 
    */
    private bool $viewIsolation = false;

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
    */
    public function initialize(string $dir =__DIR__): void
    {
        static::$baseTemplateDir = root($dir);
        static::$minifyContent = env('enable.page.minification', false);
        static::$pageCacheFolder = TemplateConfig::$pageCacheFolder;
        $this->templateEngine = TemplateConfig::$templateEngine;
        $this->viewIsolation = TemplateConfig::$viewIsolation;
        $this->cacheExpiry = (int) env('page.cache.expiry', 0);

        if (NOVAKIT_ENV === null && !PRODUCTION) {
            // If the document root is not changed to "public", or not on novakit development server.
            //Manually enable the app to use "public" as the default
            $this->setPublic("public");
        }
    }

    /** 
    * Get protected property or static::$publicOptions or static::$publicClasses
    *
    * @param string $key property name 
    *
    * @return mixed 
    */
    public function __get(string $key): mixed 
    {
        $value = static::attrGetter($key) ?? null;

        if($value === '__nothing__'){
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
    */
    public static function attrGetter(string $key): mixed 
    {
        if (array_key_exists($key, static::$publicOptions)) {
            return static::$publicOptions[$key];
        }

        if (isset(static::$publicClasses[$key])) {
            return static::$publicClasses[$key];
        } 

        return '__nothing__';
    }

    /** 
    * Get registered class object static::$publicClasses
    *
    * @param string $key object class name 
    *
    * @return object|null 
    */
    public static function getClass(string $key): ?object 
    {
        if (isset(static::$publicClasses[$key])) {
            return static::$publicClasses[$key];
        } 

        return null;
    }

    /** 
    * Check if class registered 
    *
    * @param string $class object class name 
    *
    * @return bool If class is registered
    */
    public static function hasClass(string $class): bool 
    {
        return isset(static::$publicClasses[$class]);
    }

    /** 
    * Set view level 
    *
    * @param int $level level
    *
    * @return self $this
    */
    public function setLevel(int $level): self
    {
        $this->relativeLevel = $level;

        return $this;
    }

    /** 
    * Set if base template should be optimized
    *
    * @param bool $allow true or false
    *
    * @return self $this
    */
    public function enableViewCache(bool $enable): self 
    {
        $this->cacheView = $enable;

        return $this;
    }

    /** 
    * Set if compress should ignore code block minification
    *
    * @param bool $ignore true or false
    *
    * @return self $this
    */
    public function minifyCodeblock(bool $ignore): self 
    {
        $this->minifyCodeTags = $ignore;

        return $this;
    }

     /** 
    * Set if codeblock should have a copy button
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
    */
    public function setProjectBase(string $base): self
    {
        $this->projectBase = $base;

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

        return static::$baseTemplateDir;
    }

    /** 
    * Set template engine to use for rendering
    *
    * @param string $engine template engine name
    *
    * @return self $this
    */
    public function useTemplateEngine(string $engine): self
    {
        $this->templateEngine = $engine;

        return $this;
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
    * Set sub view folder
    *
    * @param string $path folder name
    *
    * @return self $this
    */
    public function setFolder(string $path): self
    {
        $this->subViewFolder =  trim($path, '/');

        return $this;
    }

    /** 
    * Set optimizer ignore view
    *
    * @param array|string $viewName view name
    *
    * @return self $this
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
    * Set project application document root
    * public_html default
    *
    * @param string $root base directory
    *
    * @return self $this
    */
    public function setPublic(string $root): self 
    {
        $this->rootDirectory = $root;

        return $this;
    }

    /**
     * Export / Register a class instance 
     * to be accessible within the template.
     *
     * @param string|object $classOrInstance The class name or instance to register.
     * @param string|null $aliases Optional class aliases
     * 
     * @return bool true on success, false on failure
     * @throws RuntimeException If the class does not exist or failed.
     * @throws InvalidArgumentException If there is an error during registration.
    */
    public function export(string|object $classOrInstance, ?string $aliases = null): bool 
    {
        if ($classOrInstance === '' || $aliases === '') {
            throw new InvalidArgumentException('Invalid arguments provided, arguments expected a non-empty string');
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
     * Get view contents 
     * 
     * @return mixed
    */
    public function getContents(): mixed
    {
        return $this->templateContents;
    }

    /** 
    * Get base view file directory
    *
    * @return string path
    */
    private static function getViewFolder(): string 
    {
        return static::viewRoot() . DIRECTORY_SEPARATOR . static::$templateFolder . DIRECTORY_SEPARATOR;
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
        return static::getViewFolder() . 'system_errors' . DIRECTORY_SEPARATOR . "{$filename}.php";
    }

    /** 
    * Get optimizer file directory
    *
    * @return string path
    */
    private static function getCacheFolder(): string
    {
        return static::viewRoot() . DIRECTORY_SEPARATOR . static::$pageCacheFolder . DIRECTORY_SEPARATOR;
    }

    /** 
     * Cache response use before cache()->respond() method
     * 
     * @param string $cacheKey Cache key
     * @param int $expire Cache expiration default is 60 minutes
     * 
     * @return self $this
    */
    public function cache(string $cacheKey, int $expiry = 60): self 
    {
        $this->cacheKey = $cacheKey;
        $this->cacheExpiry = $expiry;

        return $this;
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
                    if($this->viewIsolation ){
                        static::renderIsolation($this->templateFile, $shouldCache, $this->optimizerFile, $options, $this->minifyCodeTags, $this->canCopyCodeblock);
                    }else{
                        $this->renderDefault($options, $shouldCache);
                    }
                }
            }
        } catch (ViewNotFoundException $e) {
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
            http_response_code(503);
            header(SERVER_PROTOCOL . 'Service Unavailable');
            header("Retry-After: 3600");
            include static::getErrorFolder('maintenance');

            return false;
        }

        if (!file_exists($this->templateFile)) {
            http_response_code(404);
            header(SERVER_PROTOCOL . '404 Not Found');
            throw new ViewNotFoundException($this->activeView, 404);
        } 

        static::isReadWritePermission(path('writeable'));
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
        $smarty->setDirectories(
            $this->templateDir, 
            TemplateConfig::$smartyCompileFolder,
            TemplateConfig::$smartyConfigFolder,
            TemplateConfig::$smartyCacheFolder
        );
        $smarty->assignOptions($options);
        $smarty->caching($shouldCache);
        $smarty->display($this->activeView . $this->typeOfEngine());
    }

    /** 
     * Cache response
     * 
     * @param mixed $content Cache key
     * @param string $type Cache type [json, html, xml, text]
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @return void
    */
    public function respond(mixed $content, string $type = 'json', int $status = 200): void 
    {
        $shouldCache = $this->cacheKey !== null;
        $optimizer = null;
        $result = false;
        $viewHeaderInfo = [];

        http_response_code($status);
        ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));

        if ($this->cacheKey !== null && $this->cacheExpiry > 0) {
            $folder = static::getCacheFolder();
            $optimizer = static::getCacheInstance($this->cacheKey, $folder, $this->cacheExpiry);
            if ($optimizer->hasCache() && $optimizer->getCache()) {
                $this->cacheKey = null;
                $this->cacheExpiry = 0;
                $shouldCache = false;

                exit(STATUS_SUCCESS);
            }
        }

        if(static::$minifyContent){
            $compress = static::newCompressed($content, $type, $this->minifyCodeTags, $this->canCopyCodeblock);
            $result = true;
            $content = $compress->getMinified();
            $viewHeaderInfo = $compress->getInfo();
        }

        if ($shouldCache && $optimizer !== null && $content != null) {
            $optimizer->saveCache($content, null, $viewHeaderInfo);
        }

        $this->cacheKey = null;
        $this->cacheExpiry = 0;

        exit($result ? STATUS_SUCCESS : $content);
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
        exit();
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
        $optimizer = null;
        $viewHeaderInfo = null;
        $finish = false;

        if ($shouldCache && $this->cacheExpiry > 0) {
            $optimizer = static::getCacheInstance(static::getViewUri(), $this->optimizerFile, $this->cacheExpiry);
            if ($optimizer->hasCache() && $optimizer->getCache()) {
                exit(STATUS_SUCCESS);
            }
        }

        static::setOptions($options);

        if (isset($options['title']) && static::hasClass('Meta') && method_exists(static::getClass('Meta'), 'setTitle')) {
            static::getClass('Meta')->setTitle($options['title'] ?? '');
        }

        include $this->templateFile;
        $viewContents = ob_get_clean();
        
        if (static::$minifyContent) {
            $finish = true;
            $compress = static::newCompressed($viewContents, $options["viewType"], $this->minifyCodeTags, $this->canCopyCodeblock);
            $viewContents = $compress->getMinified();
            $viewHeaderInfo = $compress->getInfo();
        }

        $viewHeaderInfo ??= static::requestInfo();

        if ($shouldCache && $optimizer !== null) {
            $optimizer->saveCache($viewContents, BaseConfig::copyright(), $viewHeaderInfo);
            if($finish){
                exit(STATUS_SUCCESS);
            }
        }

        exit($viewContents);
    }

    /**
     * Render without in isolation mode
     * 
     * @param string $templateFile View template file 
     * @param bool $shouldCache Should cache page contents
     * @param string $cacheFile Cache storage file path
     * @param array $options View options
     * @param bool $ignore Ignore html codeblock during minimizing
     * @param bool $copy Allow copy on html code tag or not
     * 
     * @return void 
    */
    private static function renderIsolation(
        string $templateFile, 
        bool $shouldCache, 
        string $cacheFile, 
        array $options, 
        bool $ignore = true, 
        bool $copy = false
    ): void
    {
        $optimizer = null;
        $finish = false;
        $viewHeaderInfo = null;
        $cacheExpiry = (int) env('page.cache.expiry', 0);
        $self = (object) static::$publicClasses;

        if ($cacheExpiry > 0 && $shouldCache) {
            $optimizer = static::getCacheInstance(static::getViewUri(), $cacheFile, $cacheExpiry);

            if ($optimizer->hasCache() && $optimizer->getCache()) {
                exit(STATUS_SUCCESS);
            }
        }

        extract($options);

        if (isset($title) && static::hasClass('Meta') && method_exists(static::getClass('Meta'), 'setTitle')) {
            static::getClass('Meta')->setTitle($title ?? '');
        }

        include $templateFile;
        $viewContents = ob_get_clean();

        if (static::$minifyContent) {
            $compress = static::newCompressed($viewContents, $viewType, $ignore, $copy);
            $viewContents = $compress->getMinified();
            $viewHeaderInfo = $compress->getInfo();
            $finish = true;
        }

        $viewHeaderInfo ??= static::requestInfo();

        if ($shouldCache && $optimizer !== null) {
            $optimizer->saveCache($viewContents, BaseConfig::copyright(), $viewHeaderInfo);
            if($finish){
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

        // Set cache control for application cache
        $minifier->setCacheControl(env('cache.control', 'no-store'));

        // Set response compression level
        $compressionLevel = (int) env('compression.level', 6);
        $minifier->setCompressionLevel($compressionLevel);
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
    * Get page view cache instance
    *
    * @param string $key
    * @param string $file 
    * @param int $expiry 
    *
    * @return PageViewCache
    */
    private static function getCacheInstance(string $key, string $file, int $expiry = 0): PageViewCache
    {
        if(static::$viewCache === null){
            static::$viewCache = new PageViewCache();
        }

        static::$viewCache->setExpiry($expiry);
        static::$viewCache->setDirectory($file);
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
    * render render template view
    *
    * @param string $viewName view name
    * @param string $viewType Type of content not same as Content-Type
    *
    * @return self $this
    */
    public function view(string $viewName, string $viewType = 'html'): self 
    {
        $viewType = strtolower($viewType);

        if(!in_array($viewType, ['html', 'json', 'text', 'xml'], true)){
            throw new InvalidArgumentException('Invalid argument, ' . $viewType. ' required (html, json, text or xml).');
        }

        $this->templateDir = static::getViewFolder();
        $this->optimizerFile = static::getCacheFolder();

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
     * Pass additional options to view before rendering
    * Calls after view() to display your template view and
    * Include any accessible global variable within the template file.
    *
    * @param array<string, mixed> $options additional parameters to pass in the template file $this->_myOption
    * @param int $status HTTP status code (default: 200 OK)
    * @param int $level Optional directory relative level to fix your file location
    * 
    * @return void
    * @throws InvalidArgumentException
    */
    public function render(array $options = [], int $status = 200, int $level = 0): void 
    {
        $path = PRODUCTION ? 
            DIRECTORY_SEPARATOR : 
                static::calculateLevel((int) ($level > 0 ? $level : $this->relativeLevel));
        $base = $this->activeView === '404' ? 
            $this->projectBase : 
                rtrim($path . $this->rootDirectory, '/') . '/';

        $options['viewType'] = $this->viewType;
        $options['base'] = $base;
        $options['assets'] = $base . $this->assetsFolder . '/';
        
        if(!isset($options['active'])){
            $options['active'] = $this->activeView;
        }

        if(isset($options['optimize']) && !$options['optimize']){
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
    * Check if view should be optimized page caching or not
    *
    * @return bool 
    */
    private function shouldCache(): bool 
    {
        if (!$this->cacheView) {
            return false;
        }

        if (!(bool) env('enable.page.caching', false)) {
            return false;
        }

        return !in_array($this->activeView, $this->cacheIgnores, true);
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
        if($level === 0){
            $uri = static::getViewUri();
           

            if (!PRODUCTION && strpos($uri, '/public') !== false) {
                [, $uri] = explode('/public', $uri, 2);
            }

            $level = substr_count($uri, '/');

            if ($level == 1 && PRODUCTION) {
                $level = 0;
            }
        }

        return str_repeat(($level >= 2 ? '../' : ($level == 1 ? '../' : './')), $level);
    }

    /** 
    * Get template base view segments
    *
    * @return string template view segments
    */
    private static function getViewUri(): string
    {
        $url = '';
        if(isset($_SERVER['REQUEST_URI'])){
            $base = '';
            if (isset($_SERVER['SCRIPT_NAME'])) {
                $base = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';
            }
            $url = substr(rawurldecode($_SERVER['REQUEST_URI']), mb_strlen($base));
            if (strstr($url, '?')) {
                $url = substr($url, 0, strpos($url, '?'));
            }
        }

        return '/' . trim($url, '/');
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

        include_once $view;

        $exception->logException();
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

    /**
     * Check if read and write permission is granted for writeable folder
     * 
     * @param string $folder
     * 
     * @return void
    */
    private static function isReadWritePermission(string $folder): void
    {
        // Check if folder is readable
        if (!is_readable($folder)) { 
            throw new RuntimeException("Folder '{$folder}' is not readable, please grant read permission.");
        }
    
        // Check if folder is writable
        if (!is_writable($folder)) {
            throw new RuntimeException("Folder '{$folder}' is not writable, please grant read permission.");
        }
    }
}