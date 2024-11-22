<?php 
/**
 * Luminova Framework template view class.
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
use \Luminova\Interface\ExceptionInterface; 
use \Luminova\Exceptions\AppException; 
use \Luminova\Exceptions\ViewNotFoundException; 
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Time\Time;
use \Luminova\Time\Timestamp;
use \Luminova\Optimization\Minification;
use \Luminova\Utils\WeakReference;
use \Luminova\Cache\ViewCache;
use \App\Config\Template as TemplateConfig;
use \DateTimeInterface;
use \DateTimeImmutable;
use \DateTimeZone;
use \Closure;
use \stdClass;
use \WeakMap;
use \Exception;

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
     * Framework project document root.
     * 
     * @var string|null $root
     */
    private static ?string $root = null;

    /** 
     * View template full filename.
     * 
     * @var string $filepath 
     */
    private string $filepath = '';

    /**
     * View template directory.
     * 
     * @var string $viewsDirectory 
     */
    private string $viewsDirectory = '';

    /**
     * Type of view content.
     * 
     * @var string $viewType 
     */
    private string $viewType = 'html';

    /** 
     * The project view file directory.
     * 
     * @var string $viewFolder 
     */
    private static string $viewFolder = 'resources/Views';

    /** 
     * The view sub directory.
     * 
     * @var string $subfolder 
     */
    private string $subfolder = '';

    /** 
     * The HMVC module directory name.
     * 
     * @var string $moduleName 
     */
    private string $moduleName = '';

    /** 
     * Holds the router active page name.
     * 
     * @var string $activeView 
     */
    private string $activeView = '';

    /** 
     * Holds the array attributes.
     * 
     * @var array $publicOptions 
     */
    private static array $publicOptions = [];

    /** 
     * Ignore or allow view optimization.
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
     * Response cache expiry ttl.
     * 
     * @var DateTimeInterface|int|null $cacheExpiry 
     */
    private DateTimeInterface|int|null $cacheExpiry = 0;

    /**
     * Default cache path.
     * 
     * @var string|null $cacheFolder 
     */
    private static ?string $cacheFolder = null;

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
     * Weather its HMVC or MVC module.
     * 
     * @var bool $useHmvcModule 
     */
    private static bool $useHmvcModule = false;

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
    private static array $supportedTypes = [
        'html', 'json', 'text', 
        'txt', 'xml', 'js', 
        'css', 'rdf', 'atom', 'rss'
    ];

    /**
     * View headers.
     * 
     * @var array<string,mixed> $headers
     */
    private array $headers = [];

    /**
     * Weak object reference.
     * 
     * @var WeakMap|null $weak
     */
    private static ?WeakMap $weak = null;

    /**
     * Exported object reference.
     * 
     * @var WeakReference|null $reference
     */
    private static ?WeakReference $reference = null;

    /** 
     * Initialize template view configuration.
     *
     * @return void
     * @internal 
     */
    protected final function onInitialized(): void
    {
        self::$config ??= new TemplateConfig();
        self::$weak ??= new WeakMap();
        self::$reference ??= new WeakReference(); // Public and Protected Classes

        self::$weak[self::$reference] = [];
        self::$root ??= root();
        self::$minifyContent = (bool) env('page.minification', false);
        self::$useHmvcModule = env('feature.app.hmvc', false);
        self::$cacheFolder = self::getSystemPath(self::trimDir(self::$config->cacheFolder) . 'default');
        $this->cacheView = (bool) env('page.caching', false);
        $this->cacheExpiry = (int) env('page.cache.expiry', 0);
    }

    /** 
     * Get property from view options or application exported, public and protected properties.
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

        return self::$weak[self::$reference][$key] ?? self::$KEY_NOT_FOUND;
    }

    /** 
     * Set if HTML codeblock tags should be ignore during page minification.
     *
     * @param bool $minify Indicate if codeblocks should be minified.
     * @param bool $button Indicate if codeblock tags should include a copy button (default: false).
     *
     * @return self Returns the instance of the View class or CoreApplication, depending on where it's called.
     */
    public final function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }

    /** 
     * Set the view directory or subfolder within the application to search for view files in one of the following locations:
     * - `resources/Views/`
     * - `app/Modules/Views/`
     * - `app/Modules/<Module>/Views/`
     *
     * @param string $path The folder name to search for the view.
     *
     * @return self Returns the instance of the `View` class or `CoreApplication`, depending on the context.
     * 
     * - If called in a controller's `onCreate` or `__construct` method, the entire controller's views will be searched in the specified folder.
     * - If called in the application's `onCreate` or `__construct` method, the application's views will be searched in the specified folder.
     * - If called within a specific controller method before rendering, only that method's view will be searched in the specified folder.
     */
    public final function setFolder(string $path): self
    {
        $this->subfolder = trim($path, TRIM_DS);
        return $this;
    }

    /** 
     * Set the module directory name that contains the controller class. This is essential for identifying each HMVC module.
     *
     * @param string $module The name of the module folder (e.g., `Blog`).
     *                    Use a blank string for global controller without a specific module name prefix.
     *
     * @return self Returns the instance of the `View` class or `CoreApplication`, depending on the context.
     * @throws RuntimeException Throws if an invalid module name is specified.
     * 
     * > **Note:** This method is HMVC specify feature and should only be called once in the controller's `onCreate` or `__construct` method, before rendering any views.
     */
    public final function setModule(string $module): self
    {
        $module = trim($module);

        if ($module !== '' && strpbrk($module, '/\\') !== false) {
            throw new RuntimeException(
                sprintf('Invalid module name: %s. Only alphanumeric characters and underscores are allowed.', $module),
                RuntimeException::INVALID_ARGUMENTS
            );
        }

        $this->moduleName = $module;
        return $this;
    }

    /** 
     * Adds a view to the list of views that should not be cached.
     *
     * @param string|string[] $viewName A single view name or an array of view names to exclude from caching.
     *
     * @return self Returns the instance of the View class or CoreApplication, depending on where it's called.
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
     * @return self Returns the instance of the View class or CoreApplication, depending on where it's called.
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
     * @return self Return instance of View or CoreApplication depending on where its called,
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
     * @throws RuntimeException If the class does not exist, failed or an error during registration.
     */
    public final function export(string|object $class, ?string $alias = null, bool $initialize = true): bool 
    {
        if ($class === '' || $alias === '') {
            throw new RuntimeException(
                'Invalid arguments provided, arguments "$class or $alias" expected a non-blank string.',
                RuntimeException::INVALID_ARGUMENTS
            );
        }

        $alias ??= get_class_name($class);

        if (isset(self::$weak[self::$reference][$alias])) {
            throw new RuntimeException("Exported class with the same name or alias: '{$alias}' already exists.");
        }

        if ($initialize && is_string($class)) {
            self::$weak[self::$reference][$alias] = new $class();
            return true;
        }
        
        self::$weak[self::$reference][$alias] = $class;
        return true;
    }

    /** 
     * Cache and store response to reuse on next request to same content.
     * 
     * @param DateTimeInterface|int|null $expiry Cache expiration default, set to null to use default expiration from .env file.
     * 
     * @return self Returns the instance of the View class or CoreApplication, depending on where it's called.
     * 
     * @example - Usage example with cache.
     * ```php
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
     * Deletes the cache entry for the current request view.
     *
     * @param string|null $version Optional. Specify the application version to delete (default: null).
     * 
     * @return bool Return true if the cache entry was deleted; false otherwise.
     */
    public function delete(?string $version = null): bool 
    {
        return self::getCache()->delete($version);
    }

    /**
     * Clears all view cache entries.
     *
     * @param string|null $version Optional. Specify the application version to clear (default: null).
     * 
     * @return int Return the number of deleted cache entries.
     */
    public function clear(?string $version = null): int 
    {
        return self::getCache()->clear($version);
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
        $expired = self::getCache()->expired($viewType);

        if($expired === 404){
            throw new RuntimeException('Invalid mismatch view type: ' . $viewType);
        }

        return $expired;
    }

    /**
     * Render cached content if cache exist.
     * 
     * @return int Returns status code success if cache exist and rendered else return error.
     * @throws RuntimeException Throws if called without calling `cache` method.
     */
    public final function reuse(): int
    {
        if (!$this->forceCache) {
            throw new RuntimeException('Cannot call reuse method with first calling cache() method');
        }

        $this->forceCache = false;
        $cache = self::getCache($this->cacheExpiry);

        return $cache->read() ? STATUS_SUCCESS : STATUS_ERROR;
    }

    /**
     * Handle cache expiration and renewal.
     *
     * @param string $viewType The type of view content to check, such as 'html' or 'json'.
     * @param Closure $renew A callback function that will be executed if the cached 
     *                            content has expired. This function should return in (`0` or `1`).
     * @param mixed ...$arguments Optional arguments to pass to the callback function, none dependency injection supported.
     *
     * @return int Return the status code of the cache renewal callback if the cache has expired, 
     *             otherwise the status code from reusing the existing cache.
     */
    public final function onExpired(string $viewType, Closure $renew, mixed ...$arguments): int
    {
        if ($this->shouldLmvCache() && !$this->expired($viewType)) {
            $this->forceCache = true;
            return $this->reuse();
        }

        return $renew(...$arguments);
    }

    /**
     * Set response header.
     *
     * @param string $key The header key.
     * @param mixed $value The header value for key.
     * 
     * @return self Return instance of View or CoreApplication depending on where its called.
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
     * @return self Return instance of View or CoreApplication depending on where its called.
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /** 
     * Render template view file withing the `resources/Views` directory.
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
     * @return self Return instance of View or CoreApplication depending on where its called.
     * @throws RuntimeException Throw if invalid or unsupported view type specified.
     */
    public final function view(string $viewName, string $viewType = 'html'): self 
    {
        $viewName = trim($viewName, '/');
        $viewType = strtolower($viewType);
    
        if(!in_array($viewType, self::$supportedTypes)){
            $supported = implode(', ', self::$supportedTypes);
            throw new RuntimeException(sprintf(
                'Invalid argument, unsupported view type: "%s" for view: "%s", supported types (%s). To render other formats use helper function `response()->render()`', 
                $viewType, 
                $viewName,
                $supported
            ), RuntimeException::INVALID_ARGUMENTS);
        }

        $this->viewsDirectory = $this->getViewPath();
        $this->filepath = $this->viewsDirectory . $viewName . self::lmvTplExe();

        if (PRODUCTION && !file_exists($this->filepath)) {
            $viewName = '404';
            $this->filepath = $this->viewsDirectory . $viewName . self::lmvTplExe();
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
        return $this->callLmv($options, $status) 
            ? STATUS_SUCCESS 
            : STATUS_ERROR;
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
        return $this->callLmv($options, $status, true);
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
        $viewPath = root($this->getViewPath()) . $this->activeView . self::lmvTplExe();
        $info = [
            'location' => $viewPath,
            'engine' => self::lmvEngine(),
            'size' => 0,
            'timestamp' => 0,
            'modified' => '',
            'dirname' => null,
            'extension' => null,
            'filename' => null,
        ];

        if (file_exists($viewPath)) {
            clearstatcache(true, $viewPath);

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
        $base = (PRODUCTION ? '/' : self::toRelativeLevel());
        return ($filename === '') 
            ? $base 
            : $base . ltrim($filename, '/');
    }

    /** 
     * Get template engine file extension.
     *
     * @return string Returns extension type.
     */
    private static function lmvTplExe(): string
    {
        $engine = self::lmvEngine();
        return ($engine === 'smarty' 
            ? '.tpl' 
            : ($engine === 'twig' ? '.twig' : '.php')
        );
    }

    /** 
     * Get template engine type.
     *
     * @return string Return template engine name.
     */
    private static function lmvEngine(): string 
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
    private function callLmv(
        array $options = [], 
        int $status = 200, 
        bool $return = false
    ): string|bool
    {
        try {
            $cacheable = $this->shouldLmvCache();
            $engine = self::lmvEngine();
            $cache = null;

            if ($cacheable && $engine === 'default') {
                $cache = self::getCache($this->cacheExpiry);
        
                if ($cache->expired($this->viewType) === false) {
                    return $return ? $cache->get() : $cache->read();
                }
            }
            
            if($this->lmvSetup($status)){
                if ($engine === 'smarty' || $engine === 'twig') {
                    return self::{$engine . 'Lmv'}(
                        $this->activeView . self::lmvTplExe(), 
                        $this->viewsDirectory, 
                        $this->lmvViewOptions($options), 
                        $cacheable,
                        (($this->cacheExpiry instanceof DateTimeInterface) 
                            ? Timestamp::ttlToSeconds($this->cacheExpiry) 
                            : $this->cacheExpiry
                        ),
                        $this->minifyCodeblocks, 
                        $this->codeblockButton,
                        $return,
                        $this->headers
                    );
                }

                if(self::$config->templateIsolation){
                    return self::isolateLmv(
                        $this->filepath, 
                        $this->lmvViewOptions($options),
                        $cache,
                        $this->minifyCodeblocks, 
                        $this->codeblockButton,
                        $return,
                        $this->headers
                    );
                }

                return $this->defaultLmv(
                    $this->lmvViewOptions($options), 
                    $cache, 
                    $return, 
                    $this->headers
                );
            }
        } catch (ExceptionInterface|Exception $e) {
            if($e instanceof ExceptionInterface){
                self::handleLmvException($e, $this->lmvViewOptions($options));
                return false;
            }

            RuntimeException::throwException($e->getMessage(), $e->getCode(), $e);
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
    private function lmvSetup(int $status = 200): bool
    {
        if (!file_exists($this->filepath)) {
            Header::headerNoCache(404);
            throw new ViewNotFoundException(sprintf(
                'The view "%s" could not be found in the view directory "%s".', 
                $this->activeView, 
                filter_paths($this->viewsDirectory)
            ));
        } 

        FileManager::permission('rw');
        Header::sendStatus($status);
        defined('ALLOW_ACCESS') || define('ALLOW_ACCESS', true);

        return true;
    }

    /**
     * Render with smarty engine.
     * 
     * @param string $view View file name.
     * @param string $viewsDirectory View template directory.
     * @param array $options View options.
     * @param bool $caching Should cache page contents.
     * @param int|null $cacheExpiry Cache expiration.
     * @param bool $minify Should minify.
     * @param bool $copy Should include code copy button.
     * @param bool $return Should return content instead of render.
     * @param array $customHeaders Additional headers.
     * 
     * @return bool|string Return true on success, false on failure.
     */
    private static function smartyLmv(
        string $view, 
        string $viewsDirectory, 
        array $options = [], 
        bool $caching = false,
        int|null $cacheExpiry = 3600,
        bool $minify = false,
        bool $copy = false,
        bool $return = false,
        array $customHeaders = []
    ): bool|string
    {
        static $instance = null;

        if($instance === null){
            $instance = Smarty::getInstance(self::$config, self::getSystemRoot());
        }

        try{
            $instance->headers($customHeaders);
            $instance->setPath($viewsDirectory);
            $instance->minify(self::$minifyContent, [
                'codeblock' => $minify,
                'copyable' => $copy,
                'encode' => false,
            ]);
            $instance->caching($caching, $cacheExpiry);

            if (!$instance->isCached($view)) {
                $instance->assignOptions($options);
                $instance->assignClasses(self::$weak[self::$reference]);
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
     * @param string $viewsDirectory View template directory.
     * @param array $options View options.
     * @param bool $shouldCache Should cache page contents.
     * @param int|null $cacheExpiry Cache expiration.
     * @param bool $minify Should minify.
     * @param bool $copy Should include code copy button.
     * @param bool $return Should return content instead of render.
     * @param array $customHeaders Additional headers.
     * 
     * @return bool|string Return true on success, false on failure.
     */
    private static function twigLmv(
        string $view, 
        string $viewsDirectory, 
        array $options = [], 
        bool $shouldCache = false,
        int|null $cacheExpiry = 3600,
        bool $minify = false,
        bool $copy = false,
        bool $return = false,
        array $customHeaders = []
    ): bool|string
    {
        static $instance = null;

        if($instance === null){
            $instance = Twig::getInstance(self::$config, self::getSystemRoot(), $viewsDirectory, [
                'caching' => $shouldCache,
                'charset' => env('app.charset', 'utf-8'),
                'strict_variables' => true,
                'autoescape' => 'html'
            ]);
        }

        try{
            $instance->headers($customHeaders);
            $instance->setPath($viewsDirectory);
            $instance->minify(self::$minifyContent, [
                'codeblock' => $minify,
                'copyable' => $copy,
                'encode' => false,
            ]);
            $instance->assignClasses(self::$weak[self::$reference]);

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
    private function defaultLmv(
        array $options, 
        ?ViewCache $_lmv_cache = null, 
        bool $_lmv_return = false,
        array $customHeaders = []
    ): bool|string
    {
        $lmv_view_type = $options['viewType']??'html';
        if(self::$config->variablePrefixing !== null){
            self::lmvExtractOptions($options);
        }

        include_once $this->filepath;
        $_lmv_contents = ob_get_clean();     
        self::lmvInlineErrors($_lmv_contents);

        [$_lmv_headers, $_lmv_contents, $_lmv_is_cacheable] = self::lmvMinification(
            $_lmv_contents,
            $lmv_view_type,
            $this->minifyCodeblocks, 
            $this->codeblockButton,
            $customHeaders
        );

        if($_lmv_return){
            return $_lmv_contents;
        }

        $_lmv_headers['default_headers'] = true;
        Header::validate($_lmv_headers);
        echo $_lmv_contents;

        if($_lmv_is_cacheable && $_lmv_cache instanceof ViewCache){
            $_lmv_cache->setFile($this->filepath);
            $_lmv_cache->saveCache($_lmv_contents, $_lmv_headers, $lmv_view_type);
        }

        return true;
    }

    /**
     * Render without in isolation mode.
     * 
     * @param string $_lmv_view_file View template file.
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
    private static function isolateLmv(
        string $_lmv_view_file, 
        array $options,
        ?ViewCache $_lmv_cache = null,
        bool $_lmv_ignore = true, 
        bool $_lmv_copy = false,
        bool $_lmv_return = false,
        array $customHeaders = []
    ): bool|string
    {
        $self = self::lmvSelf();
        self::$weak[$self] = $self;
        self::$weak->offsetUnset(self::$reference);

        $lmv_view_type = $options['viewType']??'html';
        if(($_lmv_prefix = self::$config->variablePrefixing) !== null){
            if($_lmv_prefix && isset($options['self'])){
                throw new RuntimeException('Reserved Error: The "self" keyword is not allowed in your view options without variable prefixing.', E_ERROR);
            }
            extract($options, ($_lmv_prefix ? EXTR_PREFIX_ALL : EXTR_OVERWRITE), '');
        }

        include_once $_lmv_view_file;
        $_lmv_contents = ob_get_clean();
        self::lmvInlineErrors($_lmv_contents);

        [$_lmv_headers, $_lmv_contents, $_lmv_is_cacheable] = self::lmvMinification(
            $_lmv_contents,
            $lmv_view_type,
            $_lmv_ignore, 
            $_lmv_copy,
            $customHeaders
        );

        if($_lmv_return){
            return $_lmv_contents;
        }

        $_lmv_headers['default_headers'] = true;
        Header::validate($_lmv_headers);
        echo $_lmv_contents;
        
        if($_lmv_is_cacheable && $_lmv_cache instanceof ViewCache){
            $_lmv_cache->setFile($_lmv_view_file);
            $_lmv_cache->saveCache($_lmv_contents, $_lmv_headers, $lmv_view_type);
        }

        return true;
    }

    /**
     * Initialize self class keyword.
     * 
     * @return class-object Return new instance of anonymous classes.
     */
    private static function lmvSelf(): object 
    {
        return new class(self::$weak[self::$reference]) {
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
    private function lmvMinification(
        string|false $content, 
        string $type, 
        bool $ignore, 
        bool $copy, 
        array $customHeaders = []
    ): array 
    {
        $canCacheContent = false;
        $headers = null;

        if ($content !== false && $content !== '') {
            if (self::$minifyContent && $type === 'html') {
                $minify = self::getMinification(
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

            $canCacheContent = $content !== '';
        }

        $headers ??= Header::getSentHeaders();

        return [
            $customHeaders + $headers, 
            $content,
            $canCacheContent,
        ];
    }
    
    /** 
     * Check if view should be optimized page caching or not.
     *
     * @return bool Return true if view should be cached, otherwise false.
     * > Keep the validation order its important.
     */
    private function shouldLmvCache(): bool
    {
        if ($this->forceCache) {
            return true;
        }

        if (!$this->cacheView || $this->isTtlExpired()) {
            return false;
        }

        if($this->cacheOption === []){
            return true;
        }

        // Check if the view is in the 'only' list
        return isset($this->cacheOption['only']) 
            ? in_array($this->activeView, $this->cacheOption['only'], true)
            : !in_array($this->activeView, $this->cacheOption['ignore'] ?? [], true);
            // Check if the view is in the 'ignore' list
    }

    /**
     * Check if the cache expiration (TTL) is empty or expired.
     *
     * @return bool Returns true if no cache expiration is found or if the TTL has expired.
     */
    private function isTtlExpired(): bool 
    {
        if ($this->cacheExpiry === null) {
            return true;
        }

        if ($this->cacheExpiry instanceof DateTimeInterface) {
            return $this->cacheExpiry < new DateTimeImmutable(
                'now', 
                new DateTimeZone(date_default_timezone_get())
            );
        }

        return false;
    }

    /** 
     * Handle exceptions.
     *
     * @param ExceptionInterface $exception The exception interface thrown.
     * @param array<string,mixed> $options The view options.
     *
     * @return void 
     */
    private static function handleLmvException(
        ExceptionInterface $exception, 
        array $options = []
    ): void 
    {
        extract($options);
        unset($options);

        include_once PRODUCTION ? self::getSystemError('404') : self::getSystemError('view.error');
        if(PRODUCTION){
            $exception->log();
        }
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
    private static function lmvExtractOptions(array $attributes): void
    {
        if (self::$config->variablePrefixing === null) {
            return;
        }

        $prefix = (self::$config->variablePrefixing ? '_' : '');

        foreach ($attributes as $name => $value) {
            $key = (is_int($name) ? '_' . $name : $prefix . $name);
            self::assertValidOptionKey($key);           
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
    private static function assertValidOptionKey(string $key): void 
    {
        if ($key === '_' || $key === '') {
            throw new RuntimeException(
                sprintf('Invalid option key: "%s".  View option key must be non-empty strings.', $key)
            );
        }

        if (preg_match('/[^a-zA-Z0-9_]/', $key)) {
            throw new RuntimeException(
                sprintf('Invalid option key: "%s". Only letters, numbers, and underscores are allowed in variable names.', $key)
            );
        }

        if (isset(self::$weak[self::$reference][$key])) {
            throw new RuntimeException(
                sprintf('Class with the same option name: "%s". already exists. Use a different name or enable variable prefixing to retain the name.', $key)
            );
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
    private static function lmvInlineErrors(string $contents): void
    {
        if (PRODUCTION || !env('debug.catch.inline.errors', false)) {
            return;
        }

        if (preg_match('/error<\/b>:(.+?) in <b>(.+?)<\/b> on line <b>(\d+)<\/b>/', $contents, $matches)) {
            throw new RuntimeException(sprintf(
                'DocumentInlineError: %s in %s on line %d',
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
    private function lmvViewOptions(array $options = []): array 
    {
        $options['viewType'] = $this->viewType;
        $options['href'] = self::link();
        $options['asset'] = $options['href'] . 'assets/';
        $options['active'] = $this->activeView;
        
        if(isset($options['nocache']) && !$options['nocache']){
            $this->cacheOption['ignore'][] = $this->activeView;
        }

        if(!isset($options['title'])){
            $options['title'] = self::toTitle($options['active'], true);
        }

        if(!isset($options['subtitle'])){
            $options['subtitle'] = self::toTitle($options['active']);
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
        return rtrim($path, TRIM_DS) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get base view file directory.
     *
     * @param string The view directory path. 
     *
     * @return string Return view file directory.
     */
    private static function getSystemPath(string $path): string 
    {
        return self::getSystemRoot() . trim($path, TRIM_DS) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Get application view directory.
     * 
     * @return string Return view file directory for default or HMVC module.
     */
    private function getViewPath(): string 
    {
        $module = self::$useHmvcModule 
            ? '/app/Modules/' . ($this->moduleName === ''? '' : $this->moduleName . '/') . 'Views/'
            : self::$viewFolder . '/';
    
        return self::getSystemPath($module . ($this->subfolder !== '' ? $this->subfolder : ''));
    }

    /** 
     * Get application root folder.
     *
     * @return string Return the application root directory.
     */
    private static function getSystemRoot(): string
    {
        if(self::$root === null){
            self::$root = APP_ROOT;
        }

        return self::$root;
    }

    /** 
     * Get error file from directory.
     *
     * @param string $filename file name.
     *
     * @return string Return error directory.
     * @internal
     */
    public static function getSystemError(string $filename): string 
    {
        return self::getSystemPath(self::$viewFolder) . 'system_errors' . DIRECTORY_SEPARATOR . $filename . '.php';
    }

    /**
     * Convert view name to title and add suffix if specified.
     *
     * @param string $view  The view name.
     * @param bool   $suffix Whether to add suffix.
     *
     * @return string Return view page title.
     */
    public static function toTitle(string $view, bool $suffix = false): string 
    {
        $view = strtr($view, ['_' => ' ', '-' => ' ', ',' => '']);
        $view = ucwords($view);

        if ($suffix && !str_contains($view, ' - ' . APP_NAME)) {
            $view .= ' - ' . APP_NAME;
        }

        return $view;
    }

    /** 
     * Fixes the broken css,image & links when added additional slash(/) at the router link
     * The function will add the appropriate relative base based on how many invalid link detected.
     *
     * @return string Return relative path.
     */
    private static function toRelativeLevel(): string 
    {
        $level = 0;
        if(isset($_SERVER['REQUEST_URI'])){
            $url = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen(Foundation::getBase()));

            if (($pos = strpos($url, '?')) !== false) {
                $url = substr($url, 0, $pos);
            }

            $level = substr_count('/' . trim($url, '/'), '/');
        }

        $relative = (($level === 0) ? './' : str_repeat('../', $level));
        $relative .= (NOVAKIT_ENV === null) ? 'public/' : '';
        
        return $relative;
    }

    /** 
     * Initialize minification instance.
     *
     * @param mixed $contents view contents output buffer.
     * @param string $type The content type.
     * @param bool $ignore Weather to ignore code blocks minification.
     * @param bool $copy Weather to include code block copy button.
     *
     * @return Minification Return minified instance.
     */
    private static function getMinification(
        mixed $contents, 
        string $type = 'html', 
        bool $ignore = true, 
        bool $copy = false,
    ): Minification
    {
        return self::$weak[new stdClass()] ??= (new Minification())
            ->codeblocks($ignore)
            ->copyable($copy)
            ->compress($contents, $type);
    }

    /** 
     * Get page view cache instance.
     *
     * @param DateTimeInterface|int|null $expiry  Cache expiration ttl (default: 0).
     *
     * @return ViewCache Return page view cache instance.
     */
    private static function getCache(DateTimeInterface|int|null $expiry = 0): ViewCache
    {
        return self::$weak[new stdClass()] ??= (new ViewCache())
            ->setExpiry($expiry)
            ->setDirectory(self::$cacheFolder)
            ->setKey(Foundation::getCacheId())
            ->setUri(Foundation::getUriSegments());
    }
}