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

use \Luminova\Cache\Compress;
use \Luminova\Cache\Optimizer;
use \Luminova\Template\Smarty;
use \Luminova\Base\BaseConfig;
use \App\Controllers\Config\Template as TemplateConfig;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\AppException; 
use \Luminova\Exceptions\ViewNotFoundException; 
use \Luminova\Exceptions\RuntimeException; 

trait TemplateTrait
{ 
    /** 
     * Holds the project base directory
     * 
     * @var string $baseTemplateDir __DIR__
    */
    private string $baseTemplateDir = '';

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
    private string $templateFolder = 'resources/views';

    /** 
     * Holds the view template optimize file directory path
     * 
     * @var string $optimizerFolder 
    */
    private string $optimizerFolder = "writeable/caches/optimize";

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
     * @var array $publicClasses 
    */
    private static array $publicClasses = [];

    /** 
     * Ignore view optimization
     * 
     * @var array $ignoreCachingView
    */
    private array $ignoreCachingView = [];

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
     * @var int|null $cacheExpiry 
    */
    private ?int $cacheExpiry = null;

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
     * Should ignore codeblock minification
     * 
     * @var bool $ignoreCodeblock 
    */
    private bool $ignoreCodeblock = false;

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
    * Initialize template
    *
    * @param string $dir template base directory
    *
    * @return void
    */
    public function initialize(string $dir =__DIR__): void
    {
        $this->baseTemplateDir = root($dir);
        $this->templateEngine = TemplateConfig::ENGINE;
        $this->optimizerFolder = TemplateConfig::$optimizerFolder;
        $this->viewIsolation = TemplateConfig::$viewIsolation;
        $this->cacheExpiry = env('page.cache.expiry');
        static::$minifyContent = env('enable.page.minification', false);
        if (NOVAKIT_ENV === null && !PRODUCTION) {
            // If the document root is not changed to "public", or not on novakit development server.
            //Manually enable the app to use "public" as the default
            $this->documentRoot("public");
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

        if (array_key_exists($key, static::$publicClasses)) {
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
       return static::$publicClasses[$key] ?? null;
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
    public function shouldCacheView(bool $allow): self 
    {
        $this->cacheView = $allow;

        return $this;
    }

    /** 
    * Set if compress should ignore code block minification
    *
    * @param bool $ignore true or false
    *
    * @return self $this
    */
    public function setCompressIgnoreCodeblock(bool $ignore): self 
    {
        $this->ignoreCodeblock = $ignore;

        return $this;
    }

     /** 
    * Set if codeblock should have a copy button
    *
    * @param bool $allow true or false
    *
    * @return self $this
    */
    public function allowCopyCodeblock(bool $allow): self 
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
    public function root(): string
    {
        if($this->baseTemplateDir === ''){
            $this->baseTemplateDir = APP_ROOT; //dirname(__DIR__, 2);
        }

        return $this->baseTemplateDir;
    }
    
    /** 
    * Set the template directory path
    *
    * @param string $path the file path directory
    *
    * @return self $this
    */
    public function setTemplatePath(string $path): self
    {
        $this->templateFolder = trim( $path, '/' );

        return $this;
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
    private function typeOfTemplate(): string
    {
        $engin = $this->templateEngine === 'smarty' ? '.tpl' : '.php';

        return $engin;
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
        $this->subViewFolder =  trim( $path, '/' );

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
            $this->ignoreCachingView[] = $viewName;
        }else{
            $this->ignoreCachingView = $viewName;
        }
        
        return $this;
    }

    /** 
    * redirect to template view
    *
    * @param string $viewName view name
    * @param int $status response status code
    *
    * @return void
    */
    public function redirect(string $viewName = '', int $status = 0): void 
    {
        $to = APP_URL;

        if ($viewName !== '' && $viewName !== '/') {
            $to .= '/' . $viewName;
        }

        $this->redirectTo($to, $status);
    }

    /** 
    * redirect to url view
    *
    * @param string $url view name
    *
    * @return void 
    */
    public function moved(string $url): void 
    {
        $this->redirectTo($url, 302);
    }   
    
    /** 
    * redirect to url header location
    *
    * @param string $url view name
    * @param int $status response status code
    * @param bool $replace replace header
    *
    * @return void 
    */
    public function redirectTo(string $url, int $status = 0, bool $replace = true): void 
    {
        header("Location: $url", $replace, $status);
        exit();
    } 

    /** 
    * Set project application document root
    * public_html default
    *
    * @param string $root base directory
    *
    * @return self $this
    */
    public function documentRoot(string $root): self 
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
     * @return self $this
     * @throws RuntimeException If the class does not exist.
     * @throws InvalidArgumentException If there is an error during registration.
    */
    public function export(string|object $classOrInstance, ?string $aliases = null): self 
    {
        if (empty($classOrInstance)) {
            throw new InvalidArgumentException("Invalid class argument: '{$classOrInstance}'. Expected a non-empty string or instance of a class.");
        }

        if (is_string($classOrInstance) && class_exists($classOrInstance)) {
            $instance = new $classOrInstance();
        }elseif(is_object($classOrInstance)) {
            $instance = $classOrInstance;
        }else {
            throw new RuntimeException("Class not found: '{$classOrInstance}'");
        }

        $aliases ??= get_class_name($classOrInstance);
        
        if (isset(static::$publicClasses[$aliases])) {
            unset($instance);
            throw new RuntimeException("Class with same name: '{$aliases}' already exist.");
        }

        static::$publicClasses[$aliases] = $instance;
        
        return $this;
    }

    /**
     * Sets project template options.
     * 
     * @param  array $attributes
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
            $key = TemplateConfig::$variablePrefix ? "_{$name}" : $name;

            if (!is_string($key) && $key === '_' || $key === '') {
                throw new RuntimeException("Invalid option key: '{$name}'. View option key must be non-empty strings.");
            }

            if (isset(static::$publicClasses[$key])) {
                throw new RuntimeException("The view option name is already assigned to a class: '{$key}' use a different name. Enable variable prefixing if you want to retain the name");
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
    private function getViewFolder(): string 
    {
        return $this->root() . DIRECTORY_SEPARATOR . "{$this->templateFolder}" . DIRECTORY_SEPARATOR;
    }

    /** 
    * Get error file from directory
    *
    * @param string $filename file name
    *
    * @return string path
    */
    private function getErrorFolder(string $filename): string 
    {
        return $this->getViewFolder() . 'system_errors' . DIRECTORY_SEPARATOR . "{$filename}.php";
    }

    /** 
    * Get optimizer file directory
    *
    * @return string path
    */
    private function getCacheFolder(): string
    {
        return $this->root() . DIRECTORY_SEPARATOR . "{$this->optimizerFolder}" . DIRECTORY_SEPARATOR;
    }

    /** 
     * Cache response use before respond() method
     * 
     * @param string $cacheKey Cache key
     * @param int $expire Cache expiration
     * 
     * @return self $this
    */
    public function cache(string $cacheKey, int $expiry = 0): self 
    {
        $this->cacheKey = $cacheKey;
        $this->cacheExpiry = $expiry;
        return $this;
    }

    /** 
    * Creates and Render template by including the accessible global variable within the template file.
    *
    * @param array $options additional parameters to pass in the template file
    *
    * @return void
    * @throws ViewNotFoundException
    */
    private function renderViewContent(array $options = []): void 
    {
        try {
            if($this->iniRenderSetup()){
                $shouldCache = $this->shouldCache();
                if ($this->templateEngine === 'smarty') {
                    $this->renderSmarty($options, $shouldCache);
                } else {
                    if($this->viewIsolation ){
                        static::renderIsolation($this->templateFile, $shouldCache, $this->optimizerFile, $options, $this->ignoreCodeblock, $this->canCopyCodeblock);
                    }else{
                        $this->renderDefault($options, $shouldCache);
                    }
                }
            }
        } catch (ViewNotFoundException $e) {
            $this->handleException($e, $options);
        }

        exit(STATUS_SUCCESS);
    }

    /**
     * Initialize rendering setup
     * 
     * @return bool 
     * @throws ViewNotFoundException
     * @throws RuntimeException
    */
    private function iniRenderSetup(): bool
    {
        defined('ALLOW_ACCESS') || define('ALLOW_ACCESS', true);

        static::isReadWritePermission(path('writeable'));

        if (!file_exists($this->templateFile)) {
            throw new ViewNotFoundException($this->activeView, 404);
        }

        ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));

        if (MAINTENANCE) {
            include $this->getErrorFolder('maintenance');

            return false;
        }

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
        $smarty ??= new Smarty($this->root());
        $smarty->setDirectories(
            $this->templateDir, 
            TemplateConfig::$smartyCompileFolder,
            TemplateConfig::$smartyConfigFolder,
            TemplateConfig::$smartyCacheFolder
        );
        $smarty->assignOptions($options);
        $smarty->caching($shouldCache);
        $smarty->display($this->activeView . $this->typeOfTemplate());
    }

    /** 
     * Cache response
     * 
     * @param mixed $content Cache key
     * @param string $type Cache type [json, html, xml, text]
     * 
     * @return void
    */
    public function respond(mixed $content, string $type): void 
    {
        $shouldCache = $this->cacheKey !== null;
        static $optimizer = null;
        $result = false;
        $viewHeaderInfo = [];

        // Set output handler
        ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));

        if ($this->cacheKey !== null) {
            $folder = $this->getCacheFolder();;
            $optimizer ??= new Optimizer();
            $optimizer->setExpiry($this->cacheExpiry);
            $optimizer->setDirectory($folder);
            $optimizer->setKey($this->cacheKey);
            if ($optimizer->hasCache() && $optimizer->getCache()) {
                $this->cacheKey = null;
                $this->cacheExpiry = null;
                $shouldCache = false;
                exit(STATUS_SUCCESS);
            }
        }

        if(static::$minifyContent){
            $compress = static::newCompressed($content, $type, $this->ignoreCodeblock, $this->canCopyCodeblock);
            $result = true;
            $content = $compress->getMinified();
            $viewHeaderInfo = $compress->getInfo();
        }

        if ($shouldCache && $optimizer !== null && $content != null) {
            $optimizer->saveCache($content, null, $viewHeaderInfo);
        }

        $this->cacheKey = null;
        $this->cacheExpiry = null;
        exit($result ? STATUS_SUCCESS : $content);
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
        static $optimizer = null;
        $viewHeaderInfo = null;
        $finish = false;

        if ($shouldCache) {
            $optimizer ??= new Optimizer();
            $optimizer->setExpiry($this->cacheExpiry);
            $optimizer->setDirectory($this->optimizerFile);
            $optimizer->setKey(static::getViewUri());

            if ($optimizer->hasCache() && $optimizer->getCache()) {
                exit(STATUS_SUCCESS);
            }
        }

        static::setOptions($options);

        if (static::hasClass('Meta') && method_exists(static::getClass('Meta'), 'setTitle')) {
            static::getClass('Meta')->setTitle($options['title'] ?? '');
        }

        include $this->templateFile;
        $viewContents = ob_get_clean();
        
        if (static::$minifyContent) {
            $finish = true;
            $compress = static::newCompressed($viewContents, $options["ContentType"], $this->ignoreCodeblock, $this->canCopyCodeblock);
            $viewContents = $compress->getMinified();
            $viewHeaderInfo = $compress->getInfo();
        }

        $viewHeaderInfo ??= static::requestHeaders();

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
        $self = (object) static::$publicClasses;

        if ($shouldCache) {
            $optimizer ??= new Optimizer(env('page.cache.expiry'), $cacheFile);
            $optimizer->setKey(static::getViewUri());

            if ($optimizer->hasCache() && $optimizer->getCache()) {
                exit(STATUS_SUCCESS);
            }
        }

        extract($options);

        if (static::hasClass('Meta') && method_exists(static::getClass('Meta'), 'setTitle')) {
            static::getClass('Meta')->setTitle($title ?? '');
        }

        include $templateFile;
        $viewContents = ob_get_clean();

        if (static::$minifyContent) {
            $compress = static::newCompressed($viewContents, $ContentType, $ignore, $copy);
            $viewContents = $compress->getMinified();
            $viewHeaderInfo = $compress->getInfo();
            $finish = true;
        }

        $viewHeaderInfo ??= static::requestHeaders();

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
    * @return Compress $compress
    */
    private static function newCompressed(mixed $contents, string $type = 'html', bool $ignore = true, bool $copy = false): Compress 
    {
        static $compress = null;
        
        $compress ??= new Compress();

        // Set cache control for application cache
        $compress->setCacheControl(env('cache.control', 'no-store'));

        // Set response compression level
        $compressionLevel = BaseConfig::getInt('compression.level', 6);
        $compress->setCompressionLevel($compressionLevel);
        $compress->setIgnoreCodeblock($ignore);
        $compress->allowCopyCodeblock($copy);

        $method = match ($type) {
            'json' => 'json',
            'text' => 'text',
            'html' => 'html',
            'xml' => 'xml',
            default => 'run',
        };

        $compress->$method($contents);

        return $compress;
    }


    /** 
    * Get output headers
    * 
    * @return array $info
    */
    private static function requestHeaders(): array
    {
        $responseHeaders = headers_list();
        $info = [];

        foreach ($responseHeaders as $header) {
            // Check for Content-Type header
            if (strpos($header, 'Content-Type:') === 0) {
                $info['Content-Type'] = trim(str_replace('Content-Type:', '', $header));
            }

            // Check for Content-Length header
            if (strpos($header, 'Content-Length:') === 0) {
                $info['Content-Length'] = (int) trim(str_replace('Content-Length:', '', $header));
            }

            // Check for Content-Encoding header
            if (strpos($header, 'Content-Encoding:') === 0) {
                $info['Content-Encoding'] = trim(str_replace('Content-Encoding:', '', $header));
            }
        }

        return $info;
    }

     /** 
    * render render template view
    *
    * @param string $viewName view name
    *
    * @return self $this
    */
    public function view(string $viewName): self 
    {
        $this->templateDir = $this->getViewFolder();
        $this->optimizerFile = $this->getCacheFolder();
        if($this->subViewFolder !== ''){
            $this->templateDir .= $this->subViewFolder . DIRECTORY_SEPARATOR;
        }

        $this->templateFile = "{$this->templateDir}{$viewName}" . $this->typeOfTemplate();

        if (!file_exists($this->templateFile) && PRODUCTION) {
            $viewName = '404';
            $this->templateFile = "{$this->templateDir}{$viewName}" . $this->typeOfTemplate();
        }

        $this->activeView = $viewName;

        return $this;
    }

    /** 
     * Pass additional options to view before rendering
    * Calls after view() to display your template view and
    * Include any accessible global variable within the template file.
    *
    * @param array<string, mixed> $options additional parameters to pass in the template file $this->_myOption
    * @param int $level Optional directory relative level to fix your file location
    * 
    * @return void
    * @throws InvalidArgumentException
    */
    public function render(array $options = [], int $level = 0): void 
    {
        $level =  (int) ( $level > 0 ? $level : $this->relativeLevel);
        $relative = static::calculateLevel($level);
        $path = (PRODUCTION ? DIRECTORY_SEPARATOR : $relative);
        $base = rtrim($path . $this->rootDirectory, "/") . "/";

        if(!isset($options["active"])){
            $options["active"] = $this->activeView;
        }

        if(isset($options["optimize"])){
            if($options["optimize"]){
                if(isset($options["ContentType"])){
                    $options["ContentType"] = strtolower($options["ContentType"]);
                    if(!in_array($options["ContentType"], ['html', 'json', 'text', 'xml'], true)){
                        throw new InvalidArgumentException('Invalid argument, $options["ContentType"] required (html, json, text or xml).');
                    }
                }
            }else{
                $this->ignoreCachingView[] = $this->activeView;
            }
        }

        if(!isset($options["ContentType"])){
            $options["ContentType"] = 'html';
        }

        if(!isset($options["title"])){
            $options["title"] = static::toTitle($options["active"], true);
        }

        if(!isset($options["subtitle"])){
            $options["subtitle"] = static::toTitle($options["active"]);
        }

        if($this->activeView === '404'){
            //Set this in other to allow back to view not mater the base view 404 is triggered
            $base = $this->projectBase;
        }

        if(!isset($options["base"])){
            $options["base"] = $base;
        }

        if(!isset($options["assets"])){
            $options["assets"] = "{$base}{$this->assetsFolder}/";
        }

        $this->renderViewContent($options);
    }

    /** 
    * Check if view should be optimized page caching or not
    *
    * @return bool 
    */
    private function shouldCache(): bool 
    {
        return $this->cacheView && (bool) env('enable.page.caching', false) && !in_array($this->activeView, $this->ignoreCachingView, true);
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
    private function handleException(AppException $exception, array $options = []): void 
    {
        $exceptionView = $this->getErrorFolder('exceptions');

        if(SHOW_DEBUG_BACKTRACE){
            $trace = debug_backtrace();
        }

        include_once $exceptionView;

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