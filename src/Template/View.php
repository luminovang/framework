<?php 
/**
 * Luminova Framework template view class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template;

use \Closure;
use \Throwable;
use \DateTimeZone;
use \DateTimeInterface;
use \DateTimeImmutable;
use \Luminova\Boot;
use \Luminova\Luminova; 
use \Luminova\Time\Time;
use \Luminova\Http\Header;
use \Luminova\Logger\Logger;
use \Luminova\Http\HttpCode;
use \Luminova\Cache\StaticCache;
use \Luminova\Component\Seo\Minifier;
use \Luminova\Utility\Promise\Promise;
use \Luminova\Foundation\Core\Application;
use \App\Config\Template as TemplateConfig;
use \Luminova\Interface\{LazyObjectInterface, ExceptionInterface, PromiseInterface}; 
use \Luminova\Template\{Response, Engines\Layout, Engines\Scope, Engines\Twig, Engines\Smarty, Engines\Proxy};
use \Luminova\Exceptions\{
    ErrorCode,
    ErrorException,
    AppException,
    RuntimeException, 
    ViewNotFoundException, 
    Http\ResponseException,
    BadMethodCallException, 
    InvalidArgumentException
}; 
use function \Luminova\Funcs\{root, filter_paths, get_class_name};

/**
 * Template view helper. 
 * 
 * @category View
 * @property-read Application|null $app
 * @property-read \Luminova\Template\Engines\Scope<\Luminova\Template\View,Application> $self
 * 
 * @property-read string $href  Relative path from entry URI (When Prefixing Disabled).
 * @property-read string $asset Relative path from entry URI to asset directory (When Prefixing Disabled).
 * 
 * @property-read string $_href Relative path from entry URI (When Prefixing Enabled).
 * @property-read string $_asset Relative path from entry URI to asset directory (When Prefixing Enabled).
 */
final class View implements LazyObjectInterface
{ 
    /**
     * When rendering HTML contents.
     *
     * @var string HTML
     */
    public const HTML = 'html';

    /**
     * When rendering data as JSON.
     *
     * @var string JSON
     */
    public const JSON = 'json';

    /**
     * When rendering plain text content.
     *
     * @var string TEXT
     */
    public const TEXT = 'txt';

    /**
     * When rendering XML content.
     *
     * @var string
     */
    public const XML = 'xml';

    /**
     * When rendering JavaScript (.js) content.
     *
     * @var string
     */
    public const JS = 'js';

    /**
     * When rendering Cascading Style Sheets (.css).
     *
     * @var string
     */
    public const CSS = 'css';

    /**
     * When rendering RDF (Resource Description Framework) data.
     *
     * @var string
     */
    public const RDF = 'rdf';

    /**
     * When rendering Atom feeds.
     *
     * @var string
     */
    public const ATOM = 'atom';

    /**
     * When rendering RSS (Really Simple Syndication) feeds.
     *
     * @var string
     */
    public const RSS = 'rss';

    /**
     * Supported view types.
     *
     * @var string[] SUPPORTED_TYPES
     */
    private const SUPPORTED_TYPES = [
        self::HTML, self::JSON, 'text', 
        self::TEXT, self::XML, self::JS, 'bin',
        self::CSS, self::RDF, self::ATOM, self::RSS
    ];

    /**
     * Flag for key not found.
     * 
     * @var string KEY_NOT_FOUND
     */
    public const KEY_NOT_FOUND = '__nothing__';

    /** 
     * Framework project document root.
     * 
     * @var string|null $root
     */
    private static ?string $root = null;

     /**
     * View template resolved root directory of template.
     * 
     * @var string $pathname 
     */
    private string $pathname = '';

    /** 
     * View template full filename (pathname+filename+extension).
     * 
     * @var string $filepath 
     */
    private string $filepath = '';

    /** 
     * The resolved template filename (filename without path nor extension).
     * 
     * @var string $filename 
     */
    private string $filename = '';

    /** 
     * The resolved template basename (filename with extension).
     * 
     * @var string $basename 
     */
    private string $basename = '';


    /** 
     * The original template name.
     * 
     * @var string $template 
     */
    private string $template = '';

    /**
     * The rendering template content type.
     * 
     * @var string $type 
     */
    private string $type = self::HTML;

    /** 
     * Template views root folder (HMVC and MVC).
     * 
     * @var string $folder 
     */
    private static string $folder = 'resources/Views';

    /** 
     * Template views subfolder (HMVC and MVC).
     * 
     * @var string $subfolder 
     */
    private string $subfolder = '';

    /** 
     * The HMVC module/directory name.
     * 
     * @var string $module 
     */
    private string $module = '';

    /** 
     * Holds the array attributes.
     * 
     * @var array<string,mixed> $options 
     */
    private static array $options = [];

    /** 
     * Ignore or allow view optimization.
     * 
     * @var array<string,array> $cacheConfig
     */
    private array $cacheConfig = [];

    /**
     * Force use of cache response.
     * 
     * @var bool $forceCache 
     */
    private bool $forceCache = false;

    /**
     * Force use of cache response.
     * 
     * @var bool $immutable 
     */
    private ?bool $immutable = null;

    /**
     * Minify page content.
     * 
     * @var array<string,bool> $minification 
     */
    private array $minification = [
        'minifiable'  => false,
        'codeblocks'  => false,
        'copyable'    => false
    ];

    /**
     * Should cache view base.
     * 
     * @var bool $cacheable
     */
    private bool $cacheable = false;

    /**
     * Whether its HMVC or MVC module.
     * 
     * @var bool $isHmvcModule 
     */
    private static bool $isHmvcModule = false;

    /**
     * Mark isolation object.
     * 
     * @var bool $isIsolationObject 
     */
    public bool $isIsolationObject = false;

    /**
     * View headers.
     * 
     * @var array<string,mixed> $headers
     */
    private array $headers = [];

    /**
     * View exports.
     * 
     * @var array<string,mixed> $exports
     */
    private static array $exports = [];

    /**
     * Holds relative assets parent level.
     * 
     * @var int $uriPathDepth 
     */
    private static int $uriPathDepth = 0;

    /**
     * Holds HTTP status code.
     * 
     * @var int $status 
     */
    private int $status = 0;

    /**
     * Response cache expiry ttl.
     * 
     * @var DateTimeInterface|int|null $expiration 
     */
    private DateTimeInterface|int|null $expiration = 0;

    /**
     * Cache burst limit.
     * 
     * @var DateTimeInterface|int|null $maxBurst
     */
    private DateTimeInterface|int|null $maxBurst = null;

    /**
     * Template configuration.
     * 
     * @var TemplateConfig|null $config
     */
    private static ?TemplateConfig $config = null;

    /**
     * Template engine type and file extension.
     * 
     * @var array|null $engine
     */
    private static ?array $engine = null;

    /**
     * Luminova default template layout object.
     * 
     * @var Layout|null $layout
     */
    public ?Layout $layout = null;

    /**
     * Instance of application object.
     * 
     * Without circular reference to (view)
     * 
     * @var Application|null $app
     */
    public ?Application $app = null;

    /**
     * Initialize the View object.
     * 
     * This constructor sets up template configuration for view management, and loads environment-based options.
     * 
     * @param Application|null $app Optional application object. 
     * @throws RuntimeException If `$app` is not null and not an instance of Application class.
     * 
     * > **Note:** 
     * > If `$app` is null, templates will not have access to the application instance via (`$this->app` or `$self->app`).
     */
    public function __construct(?Application $app = null)
    {
        self::$config ??= new TemplateConfig();
        self::$root ??= root();
        self::$exports = [];
        self::$uriPathDepth = 0;

        // Feature flags from .env or runtime config
        $this->minification['minifiable'] = (bool) env('page.minification', false);
        self::$isHmvcModule = env('feature.app.hmvc', false);

        $this->cacheable = (bool) env('page.caching', false);
        $this->expiration = (int) env('page.cache.expiry', 0);

        if($app instanceof Application){
            $this->app = clone $app;
        }

        $app = null;
    }

    /**
     * Set application object for template view class.
     * 
     * @param Application $app The application object. 
     * 
     * @return self Returns instance of view class.
     * @throws RuntimeException If `$app` is not null and not an instance of Application class.
     * 
     * > **Note:** 
     * > This clones the application object, ensure no circler reference.
     * 
     * @see Luminova\Foundation\Core\Application::__clone()
     */
    public function setApplication(Application $app): self 
    {
        if (!$app instanceof Application) {
            throw new RuntimeException(sprintf(
                'View expected an instance of App\Application<Luminova\Foundation\Core\Application>, %s given.',
                $app::class
            ));
        }

        $this->app = clone $app;
        return $this;
    }

    /**
     * Retrieve a protected property or dynamic attribute from template options or exported classes.
     *
     * @param string $property The property or class alias name.
     * 
     * @return mixed|null Return the property value or null if not found.
     * @ignore
     */
    public function __get(string $property): mixed
    {
        $result = $this->getProperty($property, true, false);

        if($result === self::KEY_NOT_FOUND){
            return $this->__log($property);
        }

        return $result;
    }

    /**
     * Handle dynamic calls to instance methods.
     *
     * @param string $method The name of the method being called.
     * @param array  $arguments The arguments passed to the method.
     *
     * @return mixed Return the result of the called method, or null if method not found.
     * @ignore
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->__fromExport($method, $arguments);
    }

    /**
     * Handle dynamic setting of template options within or outside the view.
     *
     * @param string $name The option name.
     * @param array  $value The value to be assigned name.
     *
     * @return void
     * @ignore
     */
    public function __set(string $name, mixed $value): void 
    {
        self::assertOptionKey($name);
        self::$options[$name] = $value;
    }

    /**
     * Break the circular reference to `$app` object.
     * 
     * So templates can't get the view via $self->view->app
     * 
     * @internal Used in scope isolation.
     */
    public function __clone()
    {
        $this->app = null;
    }

    /**
     * When checking if property isset.
     * 
     * @param string $property The property as option name or export alias.
     * 
     * @return bool Return true if property isset.
     */
    public function __isset(string $property): bool
    {
        if(property_exists($this, $property)){
            return true;
        }

        return $this->hasOption($property) || $this->isExported($property);
    }

    /**
     * When unsetting a property.
     * 
     * @param string $property The property as option name or export alias.
     * 
     * @return void
     */
    public function __unset(string $property): void 
    {
        if($this->isExported($property)){
            unset(self::$exports[$property]);
            return;
        }

        unset(self::$options[$property]);
    }

    /**
     * Checks if a method exists in the view object.
     *
     * @param string $method The method name to check for.
     *
     * @return bool Return true if the method exists in the target object; otherwise, false.
     * @internal Allow lazy initialization in Application to execute object.
     */
    public final function hasMethod(string $method): mixed
    {
        return method_exists($this, $method);
    }

    /**
     * Checks if a given name exists as a view content option.
     *
     * Useful for determining whether a value was set in the current view
     * context via configuration options.
     *
     * @param string $name The option name to check.
     *
     * @return bool Returns `true` if the option exists, otherwise `false`.
     */
    public final function hasOption(string $name): bool 
    {
        if(self::$options === []){
            return false;
        }

        if(self::$config->variablePrefixing){
            $name = str_starts_with($name, '_') ? $name : "_{$name}";
        }

        return array_key_exists($name, self::$options);
    }

    /**
     * Checks if a given property exists in template exported object.
     *
     * Useful for determining whether a value was made available to the
     * current view via the `export()` method.
     *
     * @param string $name The export name or alias to check.
     *
     * @return bool Returns `true` if the export exists, otherwise `false`.
     */
    public final function isExported(string $name): bool 
    {
        if(self::$exports === []){
            return false;
        }

        return array_key_exists($name, self::$exports);
    }

    /**
     * Retrieves a property exported from the application context to template scope.
     *
     * @param string $name The export property class name or alias used when exporting.
     * 
     * @return mixed|null Return the export value, or null if not found.
     */
    public final function getExport(string $name): mixed 
    {
        $value = $this->getProperty($name, false, false);

        return ($value === self::KEY_NOT_FOUND) ? null : $value;
    }

    /**
     * Retrieves a value from the view's context options.
     *
     * If the key does not exist, the method also checks for the same key prefixed with `_`
     * to support backward compatibility when `$variablePrefixing` is not null.
     *
     * @param string $key The option name.
     *
     * @return mixed|null Return the option value if found; otherwise, `null`.
     */
    public final function getOption(string $key): mixed 
    {
        if(self::$options === []){
            return null;
        }

        if(self::$config->variablePrefixing){
            $key = str_starts_with($key, '_') ? $key : "_{$key}";
        }

        return self::$options[$key] 
            ?? self::$options["_$key"]
            ?? null;
    }

    /**
     * Check if the current view file matches one or more template names.
     *
     * Accepts a single template name or an array of names and returns true
     * if the active template filename is an exact match.
     *
     * @param string|array $template One or more template filenames to compare against.
     *
     * @return bool Returns true if the current template matches; otherwise false.
     * @see self::inTemplate()
     * 
     * @example - Use Case:
     * ```php
     * <a href="about" <?= $this->isTemplate('about') ? ' class="active"' : ''; ?>>About Us</a>
     * ```
     */
    public final function isTemplate(string|array $template): bool 
    {
        return (
            $this->filename === $template ||
            in_array($this->filename, (array) $template, true)
        );
    }

    /**
     * Return a value or run a callback only when the current template matches.
     *
     * When the given template name matches the active template:
     * - If $value is callable, it is executed and its return value is used.
     * - Otherwise, $value is returned as-is.
     *
     * When the template does not match:
     * - The $default value is used instead (callable or scalar).
     *
     * @param string|array $template Template name(s) to match against.
     * @param callable(View $view):mixed|scalar $value Value or callback used when matched.
     * @param callable(View $view):mixed|scalar $default Value or callback used when not matched.
     *
     * @return mixed Returns the evaluated result from $value or $default.
     * @see self::isTemplate()
     *
     * @example - Use Case:
     * ```php
     * <a href="about" <?= $this->inTemplate('about', 'class="active"', ''); ?>>About Us</a>
     * ```
     *
     * @example - Use Cases:
     * ```php
     * $states = ['class="active"', ''];
     *
     * <a href="about" <?= $this->inTemplate('about', ...$states); ?>>About Us</a>
     * <a href="service" <?= $this->inTemplate(['service','services'], ...$states); ?>>Our Service</a>
     * ```
     */
    public function inTemplate(string|array $template, mixed $value, mixed $default = null): mixed
    {
        if (!$this->isTemplate($template)) {
            $value = $default;
        }

        return is_callable($value) ? $value($this) : $value;
    }

    /**
     * Returns all context options available to the view.
     *
     * This includes any keys with or without prefixing depending on the `$variablePrefixing` configuration.
     *
     * @return array<string,mixed> Return an associative array of all view context options.
     */
    public final function getExports(): array 
    {
        return self::$exports;
    }

    /**
     * Returns all template context options extracted for the view.
     * 
     * This will return option key value if template variable prefixing 
     * configuration `$variablePrefixing` is not set to null.
     *
     * @return array<string,mixed> Return an associative array of view context options.
     */
    public final function getOptions(): array 
    {
        return self::$options;
    }

    /**
     * Get the template filename.
     * 
     * By default, returns the resolved template name (which may differ from the original
     * if a fallback like `404` was used).  
     * Pass `false` to get the original template name exactly as specified in the controller.
     *
     * @param bool $resolved Whether to return the resolved filename (true) or the original (false).
     * 
     * @return string Return the current view template file basename.
     */
    public final function getTemplate(bool $resolved = true): string 
    {
        return $resolved ? $this->filename : $this->template;
    }

    /**
     * Get the current HTTP status code used in rendering template.
     *
     * Useful when you need to access the same status code that was used 
     * to render the template directly from inside the template itself.
     *
     * @return int Return the HTTP status code (e.g., 200, 404, 500).
     */
    public final function getStatusCode(): int 
    {
        return $this->status;
    }

    /**
     * Sets the view subfolder used to locate view files within the application template view directory.
     * 
     * Valid base locations include:
     * - `resources/Views/` - MVC View directory.
     * - `app/Modules/Views/` - HMVC root view directory.
     * - `app/Modules/<Module>/Views/` - HMVC custom module view directory.
     *
     * @param string $path Subfolder name to look for views.
     * 
     * @return self Return instance of template view class.
     *
     * > **Notes:**
     * > - When used in a controller's `onCreate` or `__construct`, all views for that controller will be searched in this folder.
     * > - When used in the application's `onCreate` or `__construct`, it sets the default folder for all views.
     * > - When used in a controller method before rendering, only that method's view lookup is affected.
     */
    public final function setFolder(string $path): self
    {
        $this->subfolder = trim($path, TRIM_DS);
        return $this;
    }

    /**
     * Sets the HMVC module name for the current controller class.
     *
     * This identifies the module that the controller belongs to, typically matching
     * the folder name under `app/Modules/<Module>`. For example, a controller located at
     * `app/Modules/Blog/Controllers/PostController.php` should use `'Blog'` as the module name.
     *
     * Use an empty string if the controller is not part of root module (i.e., global scope).
     *
     * @param string $module The module name (e.g., 'Blog'). Must not contain slashes or backslashes.
     *
     * @return self Return instance of template view class.
     * @throws RuntimeException If the module name contains invalid characters (e.g., slashes).
     *
     * > **Note:** 
     * > This method is intended for HMVC usage only, and should be called once in the
     *       controller’s `__construct` or `onCreate` method—before rendering any views.
     */
    public final function setModule(string $module = ''): self
    {
        $module = trim($module);
        self::isModule($module);

        $this->module = $module;
        return $this;
    }

    /**
     * Check if HMVC module name is valid.
     * 
     * @param string $module The module name to check.
     * @param bool $assert Wether to throw an exception if not valid.
     * 
     * @return bool Return true if valid, otherwise false or throw exception if `$assert`.
     * @throws RuntimeException If not valid.
     * @internal Used internally for validation.
     */
    public static function isModule(string $module, bool $assert = true): bool
    {
        if ($module !== '' && strpbrk($module, '/\\') !== false) {
            if(!$assert){
                return false;
            }

            throw new RuntimeException(
                sprintf('Invalid module name: %s. Only alphanumeric characters and underscores are allowed.', $module),
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        return true;
    }

    /**
     * Set URI relative parent directories depth.
     * 
     * This method allows you to manually set how many parent directories (`../`) to prepend to asset and link paths.
     *
     * It overrides Luminova's default auto-detection based on the current URI depth.
     * Use it when working with nested views or custom routes that require explicit relative path control.
     *
     * Example depth values:
     * - `1` → `../`
     * - `2` → `../../`
     *
     * @param int $depth Number of `../` segments to prepend.
     * @return self Return instance of template view class.
     *
     * @example Usage:
     * ```php
     * // Global functions
     * asset('images/logo.png'); // → ../images/logo.png
     * href('about');            // → ../about
     *
     * // In template (non-isolated)
     * $this->_asset . 'images/logo.png';
     * $this->_href  . 'about';
     * $this->link('about');
     *
     * // In template (isolated)
     * $self->_asset . 'images/logo.png';
     * $self->_href  . 'about';
     * $self->link('about');
     * ```
     */
    public final function setUriPathDepth(int $depth): self
    {
        self::$uriPathDepth = $depth;
        return $this;
    }

    /**
     * Set link parent level.
     * 
     * @param int $level Number of `../` segments to prepend.
     * 
     * @return self Return instance of template view class.
     * @deprecated This method has been deprecated since 3.6.8, use `setUriPathDepth` instead.
     */
    public final function setAssetDepth(int $depth): self
    {
        return $this->setAssetPathDepth($depth);
    }

    /**
     * Configure HTML <code> block behavior in templates.
     * 
     * This method allows you to configure whether HTML `<code>` blocks should be excluded from minification 
     * and optionally display a copy button.
     *
     * @param bool $minify Whether to skip minifying `<code>` blocks.
     * @param bool $button Whether to show a "copy" button inside code blocks (default: false).
     *
     * @return self Return instance of template view class.
     * @deprecated  Use `minify()` instead. Will be removed in a future version.
     */
    public final function codeblock(bool $minify, bool $button = false): self 
    {
        return $this->minify(true, $minify, $button);
    }

    /**
     * Configure HTML content minification for the template.
     * 
     * This method sets whether the template content should be minified, and how <code> blocks
     * within the content are handled, including optional copy buttons.
     *
     * @param bool $minifiable Whether to minify the template content.
     * @param bool $minifyCodeblocks Whether `<code>` blocks should be minified (default: false).
     * @param bool $codeCopyButton Whether to display a "copy" button inside `<code>` blocks (default: false).
     *
     * @return self Return instance of template view class.
     */
    public final function minify(
        bool $minifiable, 
        bool $minifyCodeblocks = false,
        bool $codeCopyButton = false
    ): self 
    {
        $this->minification = [
            'minifiable'  => $minifiable,
            'codeblocks'  => $minifyCodeblocks,
            'copyable'    => $codeCopyButton
        ];

        return $this;
    }

    /**
     * Exclude specific templates from being cached.
     * 
     * This method allows you to exclude one or more templates name from caching it rendered content.
     *
     * @param string|string[] $template A single template name or an array of template names to ignore from caching.
     *
     * @return self Return instance of template view class.
     * 
     * @see self::cacheOnly()
     * @see self::cacheable()
     * @see self::noCaching() Alias for  cache exclusion.
     *
     * > Recommended to call in `onCreate()` or `__construct()` of the controller or application.
     */
    public final function cacheExclude(array|string $template): self
    {
        if(is_string($template)){
            $this->cacheConfig['ignore'][] = $template;
            return $this;
        }

        $this->cacheConfig['ignore'] = $template;
        return $this;
    }

    /**
     * Exclude specific templates from being cached.
     * 
     * This method allows you to exclude one or more templates name from caching it rendered content.
     *
     * @param string|string[] $template A single template name or an array of template names to ignore from caching.
     *
     * @return self Return instance of template view class.
     */
    public final function noCaching(array|string $template): self
    {
        return $this->cacheExclude($template);
    }

    /**
     * Specify templates that should be cached exclusively.
     * 
     * This method allows you to explicitly add one or more templates that should be cached.
     * When this is used instead of `cacheExclude` all other templates will not be cached except the listed templates here.
     *
     * @param string|string[] $template A single template name or an array of template names to allow for caching.
     *
     * @return self Return instance of template view class.
     * 
     * @see self::cacheExclude() or self::noCaching()
     * @see self::cacheable()
     *
     * > Recommended to call in `onCreate()` or `__construct()` of the controller or application.
     */
    public final function cacheOnly(array|string $template): self
    {
        if(is_string($template)){
            $this->cacheConfig['only'][] = $template;
            return $this;
        }

        $this->cacheConfig['only'] = $template;
        return $this;
    }

    /**
     * Enable or disable view caching at the controller or application level.
     *
     * This setting overrides the `env(page.caching)` mode.
     *
     * **Usage:**
     * - When called in a controller’s `onCreate()` or `__construct()`, all templates
     *   handled by that controller use this caching mode.
     * - When called in the application class `onCreate()` or `__construct()`, it
     *   applies globally to all views and controllers.
     * - When called inside a routable controller method before rendering, the mode
     *   applies only to the current view.
     *
     * @param bool $cacheable Whether to enable caching for the view.
     *
     * @return self Return the current view instance.
     *
     * @see self::cacheExclude()
     * @see self::cacheOnly()
     *
     * > Useful in API contexts where caching must be controlled manually.
     */
    public final function cacheable(bool $cacheable = true): self
    {
        $this->cacheable = $cacheable;

        return $this;
    }

    /**
     * Export a class instance or fully qualified name for later access in templates.
     * 
     * This allows registering services, classes, or any custom object 
     * so that it can be accessed in the template via its alias.
     *
     * @param object|string $target  The class name, object instance to expose.
     * @param string|null $alias Optional alias for reference (Defaults to class class/object name).
     * @param bool $shared If true and `$target` is class name, the same instance will be reused.
     *
     * @return true Returns `true` if imported, otherwise throw error.
     * @throws RuntimeException If arguments are invalid or alias already exists.
     * 
     * @see self::getExports()
     * @see self::getExport(...)
     * 
     * > **Note:** 
     * > If `$target` is not an object, it treated as a class name to be instantiated later.
     * 
     * @example - Usages:
     * ```php
     * class Application extends \Luminova\Foundation\Core\Application
     * {
     *      protected ?Session $session = null;
     *      protected function onCreate(): void 
     *      {
     *          $this->session = new Session();
     *          $this->session->setStorage('users');
     *          $this->session->start();
     * 
     *          $this->view->export($this->session, 'session');
     *      }
     * } 
     * ```
     */
    public final function export(object|string $target, ?string $alias = null, bool $shared = false): bool
    {
        if ($target === '' || $alias === '') {
            throw new InvalidArgumentException(
                'Invalid export arguments: "$target" and "$alias" must be non-empty.'
            );
        }

        $alias ??= get_class_name($target);

        if (isset(self::$exports[$alias])) {
            throw new RuntimeException("Export alias '{$alias}' already exists.");
        }

        self::$exports[$alias] = [
            'target'    => $target,
            'lazy'      => is_string($target),
            'shared'    => is_string($target) && $shared,
            'instance'  => null,
            'exists'    => null
        ];

        return true;
    }

    /**
     * Enables view response caching for reuse on future requests.
     *
     * When called, this method marks the response to be cached. You can optionally 
     * specify a custom expiration time; otherwise, the default from the `.env` config will be used.
     *
     * @param DateTimeInterface|int|null $expiry Optional cache expiration. Accepts:
     *        - `DateTimeInterface` For specific expiration date/time.
     *        - `int` For duration in seconds.
     *        - `null` Use the default expiration from (`env(page.cache.expiry)`).
     * @param bool|null $immutable Set whether cache content is immutable (default: null).
     *              - `true`  Cached content will not change and can be safely reused.
     *              - `false` Content may update dynamically.
     *              - `null`  Use default configuration from (`env(page.caching.immutable)`).
     *
     * @return self Return instance of template view class.
     * 
     * @see self::expired()
     * @see self::reuse()
     * @see self::onExpired()
     * @see self::delete()
     * @see self::clear()
     *
     * @example - Basic usage with conditional caching:
     * ```php
     * public function fooView(): int 
     * {
     *     $cache = $this->tpl->cache(60); // Cache for 60 seconds
     *
     *     if ($cache->expired()) {
     *         $heavy = $model->doHeavyProcess();
     *         return $cache->view('foo')->render(['data' => $heavy]);
     *     }
     *
     *     return $cache->reuse(); // Reuse the previously cached response
     * }
     * ```
     */
    public final function cache(
        DateTimeInterface|int|null $expiry = null,
        ?bool $immutable = null
    ): self
    {
        $this->forceCache = true;
        $this->immutable = $immutable;

        if ($expiry !== null) {
            $this->expiration = $expiry;
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
    public final function delete(?string $version = null): bool 
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
    public final function clear(?string $version = null): int 
    {
        return self::getCache()->clear($version);
    }

    /**
     * Check if page cache has expired.
     * 
     * @param string|null $type The view content type (default: `self::HTML`).
     * 
     * @return bool Returns true if cache doesn't exist or expired.
     * @throws RuntimeException Throw if the cached version doesn't match with the current view type.
     * 
     * @see self::reuse()
     * @see self::onExpired()
     * @see self::cache()
     * 
     * > **Note:**
     * > The expiration check we use the time used while saving cache.
     */
    public final function expired(?string $type = self::HTML): bool
    {
        $expired = self::getCache()
            ->burst($this->maxBurst)
            ->expired($type);

        if($expired === 404){
            throw new RuntimeException(
                sprintf('Invalid mismatch template view type: %s', $type)
            );
        }

        return $expired;
    }

    /**
     * Enable template cache burst for a limited duration.
     * 
     * This method temporarily disables browser caching by sending headers that force the client
     * to treat the response as always fresh until removed or the given time or duration expires.
     *
     * @param DateTimeInterface|int|null $maxTime Duration in seconds or a future time
     *                                             when burst mode should stop.
     *                                              Set to `null` to stop bursting.
     *
     * @return self Returns instance of the view class.
     *
     * @example - Usage:
     * ```php
     * public function homepage(): int
     * {
     *     return $this->tpl->view('home')
     *         ->burst(3600)   // Browser will bypass its cache for 1 hour
     *         ->render(['data' => 'foo']);
     * }
     * ```
     *
     * @example - Using controller view helper method:
     * ```php
     * public function homepage(): int
     * {
     *     $this->tpl->burst(new DateTime('+5 minutes')); Burst for 5 minutes
     *     return $this->view('home', ['data' => 'foo']);
     * }
     * ```
     */
    public function burst(DateTimeInterface|int|null $maxTime): self
    {
        $this->maxBurst = $maxTime;
        return $this;
    }

    /**
     * Reuse previously cached view content if available.
     *
     * @return int Returns one of the following status codes:
     * - `STATUS_SUCCESS` if cache was found and successfully reused,
     * - `STATUS_SILENCE` if no valid cache was found — silent exit, allowing manual fallback logic.
     *
     * @throws RuntimeException If called without first calling `cache()` method.
     * 
     * @see self::expired()
     * @see self::onExpired()
     * @see self::cache()
     *
     * @example - Usage:
     * ```php
     * public function homepage(): int
     * {
     *     $this->tpl->cache(120); // Enable caching for 2 minutes
     *     
     *     if ($this->tpl->reuse() === STATUS_SUCCESS) {
     *         return STATUS_SUCCESS; // Cache hit, response already sent
     *     }
     *     
     *     $data = $model->getHomepageData();
     *     return $this->tpl->view('home')->render(['data' => $data]);
     * }
     * ```
     */
    public final function reuse(): int
    {
        if (!$this->forceCache) {
            throw new RuntimeException('Cannot call ->reuse() without first calling ->cache().');
        }

        $this->forceCache = false;
        return self::getCache($this->expiration)
            ->burst($this->maxBurst)
            ->read() ? STATUS_SUCCESS : STATUS_SILENCE;
    }

    /**
     * Conditionally renew cached view if expired, otherwise reuse it.
     *
     * @param Closure $onRenew Callback to execute if cache has expired. 
     * @param array $options Optional options to pass to the `$onRenew` callback argument.
     * @param string $type The template content type to check cache for, (e.g. `View::HTML`, `View::JSON`).
     *          Should return `STATUS_SUCCESS` or `STATUS_SILENCE`.
     *
     * @return int Return the status code:
     *      - Status code from the callback if cache is expired or bypassed,
     *      - Otherwise, status code from `reuse()` if valid cache is used.
     * 
     * @see self::reuse()
     * @see self::cache()
     * @see self::expired()
     *
     * @example - Example:
     * ```php
     * public function profile(): int
     * {
     *      return $this->tpl->cache(300)->onExpired(function (array $options) {
     *          $data = $model->getProfileData($options['id']);
     *          return $this->view('user/profile')
     *                ->render(['user' => $data]);
     *          },
     *          ['id' => 100]
     *      );
     * }
     * ```
     */
    public final function onExpired(Closure $onRenew, array $options = [], string $type = self::HTML): int
    {
        if ($this->isCacheable() && !$this->expired($type)) {
            $this->forceCache = true;
            return $this->reuse();
        }

        return $onRenew($options);
    }

    /**
     * Set a single response header.
     *
     * @param string $key The header key.
     * @param mixed $value The header value for key.
     * 
     * @return self Return instance of template view class.
     */
    public final function header(string $key, mixed $value): self 
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Set multiple response headers at once.
     *
     * @param array<string,mixed> $headers Associative array of headers key-pair.
     * 
     * @return self Return instance of template view class.
     */
    public final function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Sets the view template and its content type for rendering.
     *
     * It resolves the template path and prepares it for rendering or later access.
     * 
     * Call this method to specify which view file to use, before any of these 
     * methods {@see (`render()`, `contents()`, `promise()`, `exists()` or `info()`)} are called. 
     *
     * **Search Paths:**
     * - `/resources/Views/` — MVC view directory.
     * - `/app/Modules/Views/` — HMVC root view directory.
     * - `/app/Modules/<Module>/Views/` — HMVC module-specific views.
     *
     * **Common Types:**
     * - `html`, `json`, `text|txt`, `xml`, `js`, `css`, `rdf`, `atom`, `rss`
     *
     * > The `$template` must exclude file extensions (`.php`, `.tpl`, `.twg`, etc).
     * > For unsupported types, use `response(...)->render(...)` for manual handling.
     *
     * @param string $template View filename without extension (e.g., `dashboard/index`).
     * @param string $type The rendering template content type (default: `View::HTML`).
     *
     * @return self Return instance of template view class.
     * @throws InvalidArgumentException If `$type` is not a supported view type.
     *
     * @see self::render()
     * @see self::contents()
     * @see self::promise()
     * @see self::exists()
     * @see self::info()
     * @see self::header()
     * @see self::headers()
     *
     * @example - Direct Usage:
     * 
     * ```php
     * $view = new View(application);
     * 
     * // Render the view and return the HTTP status code
     * $status = $view->view('profile', View::HTML)->render(['name' => 'John']);
     * 
     * // Render the view and return the content as string
     * $html = $view->view('dashboard', View::HTML)->response(['user' => $user]);
     * 
     * // Render the view and return a promise object for async handling
     * $promise = $view->view('report', View::HTML)->promise(['data' => $data]);
     * ```
     * 
     * @example - Usage in Controller:
     * 
     * ```php
     * // Render view and return status
     * $status = $this->tpl->view('profile', View::HTML)->render(['id' => 1]);
     * 
     * // Render view and return content
     * $content = $this->tpl->view('settings', View::HTML)->response(['tab' => 'privacy']);
     * 
     * // Render view and return promise object
     * $promise = $this->tpl->view('invoice', View::HTML)->promise(['orderId' => 101]);
     * ```
     */
    public final function view(string $template, string $type = self::HTML): self 
    {
        $template = trim($template, TRIM_DS);
        $ext = self::getTemplateEngine()[1];

        if (str_ends_with($template, $ext)) {
            $template = substr($template, 0, -strlen($ext));
        }

        $type = strtolower($type);

        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            self::__throw(new InvalidArgumentException(sprintf(
                'Unsupported template view type "%s" for template "%s". Supported: [%s]. For custom types, use response()->render(...).',
                $type, 
                $template, 
                implode(', ', self::SUPPORTED_TYPES)
            )), 2, true);
        }

        $this->template = $template;
        $this->type = $type;
        $this->resolve($template);

        return $this;
    }

    /**
     * Check if a template view file exists (without rendering).
     *
     * @return bool Return true if the template view file exists, false otherwise.
     *
     * @example - Example:
     * 
     * ```php
     * $this->tpl->view('admin')->exists();
     * ```
     */
    public final function exists(): bool
    {
        if($this->template !== $this->filename){
            return false;
        }
        
        return is_file($this->filepath);
    }

    /**
     * Render and immediately send the view output.
     * 
     * This method renders view content with an optional parameters to make globally available 
     * within the template view file.
     *
     * @param array<string,mixed> $options Additional parameters to pass in the template (available inside view).
     * @param int $status The HTTP status code (default: 200 OK).
     * 
     * @return int Return one of the following status codes:  
     *      - `STATUS_SUCCESS` if the view is handled successfully,  
     *      - `STATUS_SILENCE` if failed, silently terminate without error page allowing you to manually handle the state.
     * @throws RuntimeException If the view rendering fails.
     * 
     * @see self::contents()
     * @see self::promise()
     * 
     * @example - Display template view with options:
     * 
     * ```php
     * public function fooView(): int 
     * {
     *      return $this->tpl->view('name')->render([...], 200);
     * }
     * ```
     */
    public final function render(array $options = [], int $status = 200): int 
    {
        return $this->send($options, $status) 
            ? STATUS_SUCCESS 
            : STATUS_SILENCE;
    }

    /**
     * Render the view and return the output as a string.
     * 
     * This method renders selected template view and return the rendered contents string.
     *
     * @param array<string,mixed> $options Additional parameters to pass in the template (available inside view).
     * @param int $status The HTTP response status code (default: 200 OK).
     * 
     * @return string|null Return the compiled view contents or null if no content.
     * @throws RuntimeException If the view rendering fails.
     * 
     * @see self::render()
     * @see self::promise()
     * 
     * @example - Display your template view or send as an email:
     * 
     * ```php
     * public function fooView(): int 
     * {
     *      $content = $this->tpl->view('name', View::HTML)
     *          ->contents(['foo' => 'bar'], 200);
     * 
     *      Mailer::to('peter@example.com')->send($content);
     * }
     * ```
     */
    public final function contents(array $options = [], int $status = 200): ?string
    {
        return $this->send($options, $status, true) ?: null;
    }

    /**
     * Render the view and return the output as a string.
     * 
     * @deprecated Use contents() instead. This wrapper will be removed in a future release.
     *
     * @param array<string,mixed> $options Additional parameters to pass in the template (available inside view).
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return string|null Return the compiled view contents or null if no content.
     * @throws RuntimeException If the view rendering fails.
     */
    public final function respond(array $options = [], int $status = 200): ?string
    {
        return $this->contents($options, $status);
    }

    /**
     * Return a promise that resolves with rendered view contents.
     * 
     * Renders the template view file and returns a promise that resolves with
     * the rendered contents, or rejects if the template is missing or rendering fails.
     *
     * @param array<string,mixed> $options Additional parameters to pass in the template (available inside view).
     * @param int $status HTTP status code (default: 200 OK).
     * 
     * @return PromiseInterface Return a promise that resolves with rendered view contents or rejects with an error.
     * 
     * @see self::render()
     * @see self::contents()
     * @see PromiseInterface
     * 
     * @example - Display your template view or send as an email:
     * 
     * ```php
     * public function fooView(): int 
     * {
     *      $content = $this->tpl->view('name', View::HTML)
     *          ->promise(['foo' => 'bar'])
     *          ->then(function(string $content, array $options) {
     *              echo $content;
     *          })->catch(function(Exception $e) {
     *              echo $e->getMessage();
     *          });
     * }
     * ```
     */
    public final function promise(array $options = [], int $status = 200): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use($options, $status){
            try{
                $content = $this->send($options, $status, true, true);
                if($content === false){
                    $reject(new ResponseException(
                        sprintf('View "%s" failed to render.', $this->template)
                    ));
                    return;
                }

                $resolve($content, $options);
            }catch(Throwable $e){
                $reject($e);
            }
        });
    }

    /**
     * Returns metadata about the specified template file without rendering.
     *
     * Provides useful diagnostic and contextual information about a template file without rendering.
     * 
     * @param string|null $key Optional key to retrieve a specific value.
     *
     * @return array<string,mixed>|mixed Return the value for that key (null if not found), 
     *    Otherwise return metadata array: {
     *     @type string  $location   Full path to the view file.
     *     @type string  $type       Content type (e.g., html, json).
     *     @type string  $template   The view filename (without extension).
     *     @type string  $engine     Template engine in use (e.g., default(PHP), twig, smarty).
     *     @type string|null $module     HMVC module name (or `root` if not in custom context).
     *     @type int     $size       File size in bytes (0 if missing).
     *     @type int     $timestamp  Last modified time (UNIX timestamp).
     *     @type string  $modified   Last modified datetime (`Y-m-d H:i:s`).
     *     @type string|null $dirname    Directory containing the file.
     *     @type string|null $extension  File extension (e.g., php, twig).
     *     @type string|null $filename   Filename without extension.
     * }
     *
     * @example - Example:
     * ```php
     * $info = $this->tpl->view('dashboard')->info();
     * 
     * // Get the modified
     * $modified = $this->tpl->view('dashboard')->info('modified');
     * ```
     */
    public final function info(?string $key = null): mixed
    {
        clearstatcache(true, $this->filepath);
        [$engine, $ext] = self::getTemplateEngine();

        $metadata = [
            'location'  => $this->filepath,
            'type'      => $this->type,
            'template'  => $this->template,
            'engine'    => $engine,
            'size'      => 0,
            'timestamp' => 0,
            'modified'  => '',
            'module'    => null,
            'dirname'   => null,
            'extension' => $ext,
            'filename'  => null,
        ];

        if ($this->template !== $this->filename || !is_file($this->filepath)) {
            return ($key === null) ? $metadata : ($metadata[$key] ?? null);
        }

        $metadata['module'] = self::$isHmvcModule ? ($this->module ?: 'root') : null;

        if (
            $key && 
            in_array($key, ['location', 'module', 'type', 'template', 'engine', 'extension'], true)
        ) {
            return $metadata[$key];
        }

        if ($key === null || in_array($key, ['size', 'timestamp', 'modified'], true)) {
            $metadata['size'] = filesize($this->filepath);
            $metadata['timestamp'] = $timestamp = filemtime($this->filepath);
            $metadata['modified']  = Time::fromTimestamp($timestamp)->format('Y-m-d H:i:s');

            if($key){
                return $metadata[$key];
            }
        }

        $info = pathinfo($this->filepath);
        $metadata['dirname']   = $info['dirname'] ?? null;
        $metadata['extension'] = $info['extension'] ?? null;
        $metadata['filename']  = $info['filename'] ?? null;

       return ($key === null) ? $metadata : ($metadata[$key] ?? null);
    }

    /** 
     * Redirect to a different URI or route.
     *
     * @param string $uri The target URI or route.
     * @param int $status The HTTP redirect status code (default: 302).
     *
     * @return void
     * @see \Luminova\Funcs\redirect()
     * 
     * @example - Usage:
     * ```php
     * $this->tpl->redirect('/dashboard');   // absolute path
     * $this->tpl->redirect('user/profile'); // relative path
     * ```
     */
    public final function redirect(string $uri, int $status = 302): void 
    {
        Response::getInstance($status)
            ->setStatus($status)
            ->redirect($uri);
        exit(STATUS_SUCCESS);
    }

    /**
     * Generate a relative URI from the public root directory.
     * 
     * This method creates a relative path for routes or public assets (e.g., CSS, JS, images)
     * starting from the controller’s public directory. In production, it returns a root-relative path.
     * In development, it calculates the relative path based on URI segments.
     * 
     * @param string $route Optional route or file path to append after the base path.
     * @param int|null $depth Optional depth to parent directory (used in development mode only).
     *                         If null, the method auto-detects the depth.
     * 
     * @return string Return a relative or root-based URL to the file or route.
     * 
     * @see \Luminova\Funcs\href()
     * @see \Luminova\Funcs\asset()
     * 
     * @example - Usage:
     * ```php
     * <link href="<?= $this->link('assets/css/main.css') ?>" rel="stylesheet">
     * <a href="<?= $this->link('about') ?>">About Us</a>
     * ```
     */
    public static final function link(string $route = '', ?int $depth = null): string 
    {
        $base = (PRODUCTION ? '/' : self::toRelativeLevel($depth));

        if($route === '' || $route === '/'){
            return $base;
        }

        return $base . ltrim($route, '/');
    }

    /**
     * Converts a view template name to a formatted page title.
     *
     * Replaces underscores, dashes, hyphens, and commas with spaces, capitalizes words,
     * and optionally appends the application name as a suffix.
     * 
     * @param bool $suffix Whether to append the app name as a suffix (default: false).
     *
     * @return string Return the formatted page title.
     */
    public final function toTitle(bool $suffix = false): string 
    {
        $template = ucwords(strtr($this->filename, ['_' => ' ', '-' => ' ', ',' => '']));

        if ($suffix && !str_contains($template, ' - ' . APP_NAME)) {
            $template .= ' - ' . APP_NAME;
        }

        return $template;
    }

    /**
     * Get the full path to a system error file.
     *
     * @param string $filename The error file name without extension.
     *
     * @return string Return the absolute path to the system error file.
     * @internal Used internally to locate default error views.
     */
    private static function getSystemError(string $filename): string 
    {
        return sprintf(
            '%s%s%s%s%s%s%s%s',
            self::getSystemRoot(), 'app',
            DIRECTORY_SEPARATOR, 'Errors',
            DIRECTORY_SEPARATOR, 'Defaults',
            DIRECTORY_SEPARATOR, "{$filename}.php"
        );        
    }

    /**
     * Retrieves a value from view options or from exported application properties.
     * 
     * Resolves exported classes if requested.
     *
     * @param string $name The property name.
     * @param bool $any Whether to also check public view options.
     * @param bool $resolve Whether to resolve exports or return the raw target.
     * 
     * @return mixed Return the value from options or exports, or `KEY_NOT_FOUND` if not found.
     * @internal Used in core application and scope class to resolve exports.
     */
    public final function getProperty(string $name, bool $any = true, bool $resolve = true): mixed 
    {
        if ($any && $this->hasOption($name)) {
            if($this->isIsolationObject && self::$config->variablePrefixing === null){
                return null;
            }
            
            return $this->getOption($name);
        }

        if((self::$exports[$name] ?? null) === null){
            return self::KEY_NOT_FOUND;
        }

        if(!$resolve){
            return self::$exports[$name]['target'];
        }

        $export = &self::$exports[$name];

        return self::__exportResolver($export);
    }

    /**
     * Resolve an exported alias into its actual value.
     *
     * @param array $export The exported class by reference.
     * @return mixed
     *
     * @throws RuntimeException If target class does not exist.
     */
    private static function __exportResolver(array &$export): mixed
    {
        if(!$export){
            return null;
        }

        if (!$export['lazy']) {
            return $export['target'];
        }

        if ($export['shared'] && $export['instance']) {
            return $export['instance'];
        }

        $export['exists'] ??= class_exists($export['target']);

        if(!$export['exists']){
            throw new RuntimeException("Class '{$export['target']}' does not exist.");
        }

        if ($export['shared']) {
            $export['instance'] = new $export['target']();
        }

        return new $export['target']();
    }

     /**
     * Calls an exported method from the internal weak reference map.
     *
     * @param string $method The name of the exported method to call.
     * @param array $arguments The arguments to pass to the method.
     * @param bool $throwable Whether throw exception immediately if not found.
     *
     * @return mixed Return the result of the method call.
     *
     * @throws BadMethodCallException If the method is not defined or not callable.
     * @internal Used in view and isolation self keyword class.
     */
    public final function __fromExport(
        string $method, 
        array $arguments,
        bool $throwable = false
    ): mixed 
    {
        if((self::$exports[$method] ?? null) !== null){
            $export = &self::$exports[$method];

            $callable = self::__exportResolver($export);

            if ($callable && is_callable($callable)) {
                return $callable(...$arguments);
            }
        }

        $e = new BadMethodCallException(sprintf(
            'Method "%s" does not exist in "%s" or is not exported.', 
            $method, 
            self::class
        ));

        if($throwable){
            throw $e;
        }

        self::__throw($e, 2);
        return null;
    }

    /**
     * Get the template engine type in lowercase and extension (.php, .twig, .tpl)
     *
     * @return array<int,string> Return the template engine type and extension.
     */
    private static function getTemplateEngine(): array 
    {
        if(self::$engine === null){
            $type = strtolower(self::$config->templateEngine ?? 'default');
            $ext = match ($type) {
                'smarty' => '.tpl',
                'twig'   => '.twig',
                default  => '.php',
            };

            return self::$engine = [$type, $ext];
        }

        return self::$engine;
    }

    /** 
     * Render template and send output.
     * 
     * Handles accessible global variable within the template file.
     *
     * @param array $options additional parameters to pass in the template file.
     * @param int $status HTTP status code (default: 200 OK).
     * @param bool $returnable Whether to return content instead.
     * @param bool $async Whether is promise async.
     *
     * @return string|bool  Return true on success, false on failure.
     * @throws ViewNotFoundException Throw if view file is not found.
     */
    private function send(
        array $options = [], 
        int $status = 200, 
        bool $returnable = false,
        bool $async = false
    ): string|bool
    {
        ob_start();
        $this->status = $status;
        $options = $this->parseOptions($options);

        try {
            $cacheable = $this->isCacheable();
            $engine = self::getTemplateEngine()[0];
            $cache = null;

            if ($cacheable) {
                $cache = self::getCache($this->expiration)
                    ->isImmutable($this->immutable)
                    ->burst($this->maxBurst);
        
                if ($cache->expired($this->type) === false) {
                    return $returnable 
                        ? $cache->get($this->type) 
                        : $cache->read($this->type);
                }
            }
            
            if(!$this->isSetupComplete($async)){
                return false;
            }

            if($this->filename === '4xx' || $this->filename === '5xx'){
                $options['details'] ??= ['code' => $status];
            }

            if ($engine === 'default') {
                return $this->onCompleteRendering(
                    $this->defaultTemplate($options),
                    $status,
                    $returnable,
                    $cache
                );
            }

            return $this->thirdPartyTemplate(
                $options,
                $engine,
                $status,
                $returnable,
                $cache
            );
        } catch (Throwable $e) {
            $e = $e->getPrevious() ?? $e;
            if (self::$config->templateIsolation) {
                $msg = $e->getMessage();
                $selfAccess = str_contains($msg, 'access "self" when no class');

                if ($selfAccess || str_contains($msg, 'Using $this when not in')) {
                    $e = new ErrorException(
                        sprintf(
                            "%s. Use '\$self'%s.",
                            $msg,
                            $selfAccess
                                ? " as a dedicated keyword for isolation rendering."
                                : " or disable 'templateIsolation' in template configuration to access '\$this'."
                        ),
                        file: $e->getFile(),
                        line: $e->getLine(),
                        previous: $e->getPrevious()
                    );
                }
            }

            if ($returnable) {
                throw $e;
            }

            self::__exception($e, $options, $this->status);
        }

        return false;
    }

    /**
     * Creates and returns the guard object used when template isolation is disabled.
     *
     * The exception includes the file and line where `$self` was accessed, making it
     * easier to spot improper usage during development.
     *
     * @return object Guard instance that traps all `$self` interactions.
     *
     * @throws RuntimeException When `$self` is accessed in non-isolation mode.
     */
    private static function newSelfGuard(): object
    {
        return new class {
            public function __construct(private int $id = 0) {$this->id = spl_object_id($this);}
            public function __id():int { return $this->id; }
            public function __is(int $id):bool { return $this->id === $id; }
            public function __get(string $p) { $this->e(); }
            public function __call(string $p, array $args) { $this->e(); }
            public function __set(string $p, mixed $v){ $this->e(); }
            public function __toString() { $this->e(); }

            private function e(): void
            {
                [$file, $line] = RuntimeException::trace(2);
                $e = new RuntimeException(
                    'Using "$self" is not available in non-isolation mode. ' .
                    'Enable "templateIsolation" in template configuration to use "$self" keyword.'
                );

                if($file){
                    $e->setLine($line)->setFile($file);
                }

                throw $e;
            }
        };
    }

    /**
     * Initialize rendering setup.
     * 
     * @param bool $async Whether is promise async.
     * 
     * @return bool Return true if setup is ready.
     * @throws ViewNotFoundException Throw if view file is not found.
     * @throws RuntimeException Throw of error occurred during rendering.
     */
    private function isSetupComplete(bool $async = false): bool
    {
        if (!is_file($this->filepath)) {
            Header::headerNoCache(404);
            self::__throw(
                new ViewNotFoundException(sprintf(
                    'Template "%s" could not be found in the view directory "%s".', 
                    $this->template . self::getTemplateEngine()[1], 
                    filter_paths($this->pathname)
                )), 
                $async ? 6 : 3
            );
        } 

        defined('ALLOW_ACCESS') || define('ALLOW_ACCESS', true);

        return true;
    }

    /**
     * Render view template in isolation mode or directly with full context.
     *
     * When in isolation, `$this` is not accessible in the view file, but `$self` and global
     * options (prefixed or unprefixed) are still available. Isolation mode also
     * disables reference to internal class members from within templates.
     * 
     * @param array|null $options View options to pass to template.
     * 
     * @return mixed Return rendered contents.
     * @throws RuntimeException On error during template processing.
     * 
     * @example Non-Isolation Mode
     * ```php
     * // $this is available, $self is guarded
     * echo $this->_title;
     * $self->_active; // Throws RuntimeException
     * ```
     *
     * @example Isolation Mode
     * ```php
     * // $self is available, $this is not
     * echo $self->_title;
     * $self->app->session->isOnline();
     * ```
     */
    private function defaultTemplate(?array $options): mixed
    {
        self::extractOptions($options);

        $tpl = function(object $self, ?array $options, string $_VIEW_TYPE, string $_VIEW_FILEPATH): mixed {
            $___fingerprint___ = Boot::set('__IN_TEMPLATE_CONTEXT__', $self->__id());

            /** 
             * @var \Luminova\Template\View $this None isolation mode.
             * @var \Luminova\Template\Engines\Scope<\Luminova\Template\View> $self Isolation mode.
             */
            ob_start();
            $returned = include $_VIEW_FILEPATH;

            if(!PRODUCTION){
                $isError = false;
                try{
                    $isError = !$self->__is($___fingerprint___);
                }catch(Throwable){ $isError = true; }

                if($isError){
                    throw new RuntimeException(sprintf(
                        'Template "%s" attempted to override "$self". The "$self" variable is reserved and cannot be changed',
                        filter_paths($_VIEW_FILEPATH)
                    ));
                }
            }

            Boot::remove('__IN_TEMPLATE_CONTEXT__');

            if($returned === 1){
                return ob_get_clean() ?: '';
            }

            return $returned;
        };

        if(self::$config->enableDefaultTemplateLayout){
            $this->layout = new Layout(
                $this->subfolder, 
                $this->module,
                $this,
                self::$config->templateIsolation
            );
        }

        if(!self::$config->templateIsolation){
            return $tpl->bindTo($this, null)(
                self::newSelfGuard(),
                $options, 
                $this->type, 
                $this->filepath
            );
        }

        return $tpl->bindTo(null, null)(
            new Scope($this), 
            $options, 
            $this->type, 
            $this->filepath
        );
    }

    /**
     * Converts mixed content into a string suitable for output.
     *
     * - Strings and scalar values are cast directly.
     * - Objects implementing __toString() are converted to string.
     * - Arrays and other objects are JSON-encoded (with pretty print).
     * - If JSON encoding fails, a descriptive marker ([object], [array], [unprintable content]) is returned.
     * - Automatically sets Content-Type header to application/json when JSON is used.
     *
     * @param mixed $contents The content to convert.
     * @param array $headers Reference to response headers array (Content-Type may be modified).
     * 
     * @return string Return the converted string output.
     */
    private static function toOutput(
        mixed $contents, 
        array &$headers
    ): string
    {
        if ($contents === '' || $contents === null) {
            return '';
        }

        if (is_scalar($contents)) {
            return (string) $contents;
        }

        if ($contents instanceof \SimpleXMLElement) {
            $headers['Content-Type'] ??= 'application/xml';

            return (string) $contents->asXML();
        }

        if ($contents instanceof \DOMDocument) {
            $headers['Content-Type'] ??= 'application/xml';

            return (string) $contents->saveXML();
        }

        if ($contents instanceof \Stringable) {
            return (string) $contents;
        }

        if (is_callable($contents)) {
            return (string) self::toOutput($contents(), $headers);
        }

        $isObject = is_object($contents);

        if ($isObject) {
            if(method_exists($contents, '__toString')){
                return $contents->__toString();
            }
            
            if(method_exists($contents, 'toString')){
                return (string) $contents->toString();
            }
        }

        if ($isObject || is_array($contents)) {
            $headers['Content-Type'] ??= 'application/json';

            try {
                return (string) json_encode(
                    $contents,
                    JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
                );
            } catch (Throwable $e) {
                unset($headers['Content-Type']);

                throw new RuntimeException(
                    sprintf('Failed to encode output to JSON: %s', $e->getMessage()),
                    previous: $e
                );
            }
        }

        unset($headers['Content-Type']);

        throw new RuntimeException(
            sprintf('Unsupported content type for output: %s', 
            get_debug_type($contents))
        );
    }

    /**
     * Determines if the content is empty or only contains placeholder markers.
     *
     * @param mixed $contents The content to check.
     * 
     * @return bool Return true if the content is empty or not meaningful for HTML output.
     */
    private static function isEmpty(mixed $contents): bool
    {
        if (!$contents) {
            return true;
        }

        return is_string($contents) 
            && in_array($contents, ['[object]', '[array]', '[unprintable content]'], true);
    }

    /**
     * Finalizes the rendering of a view by processing output, applying headers, 
     * and optionally caching the result.
     *
     * This method handles inline error rendering, content minification (based on output type and flags), 
     * response headers, and caching of the rendered content using a `StaticCache` instance if provided.
     *
     * @param mixed $contents The final rendered content.
     * @param int $status The HTTP status code.
     * @param bool $returnable If true, return the content as a string instead of outputting.
     * @param StaticCache|null $cache Template cache object.
     *
     * @return string|bool Returns the content as a string 
     *      if `$returnable` is true, or `true` on successful rendering.
     */
    private function onCompleteRendering(
        mixed $contents,
        int $status,
        bool $returnable = false,
        ?StaticCache $cache = null
    ): string|bool
    {
        $isEmptyContent = empty($contents);
        $this->headers['X-System-Default-Headers'] = true;

        Header::clearOutputBuffers('all');

        if(
            !$returnable && 
            ($isEmptyContent || $status === 204 || $status === 304 || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD')
        ){
            Header::validate($this->headers, $isEmptyContent ? 204 : $status);
            
            return true;
        }

        $contents = self::toOutput($contents, $this->headers);

        [$headers, $_contents, $cacheable] = $this->minifier($contents);

        // if(!PRODUCTION && $contents){
        //    self::__catchInlineErrors($contents);
        // }

        if($returnable){
            $cache = null;
            return $_contents;
        }

        Header::validate($headers, $isEmptyContent ? 204 : $status);

        if($isEmptyContent){
            $cache = null;
            return true;
        }
        
        Header::setOutputHandler(true);
        echo $_contents;
        
        if($contents && $cacheable){
           $this->writeCache($cache, $_contents, $headers);
        }

        $_contents = $cache = null;
        return true;
    }

    /**
     * Render with twig or smarty engine.
     * 
     * @param array $options View options.
     * @param string $engine The third-party template engine.
     * @param int $status Http status code.
     * @param bool $cacheable Should cache page contents.
     * @param StaticCache|null $cache Template cache object.
     * 
     * @return string|bool Return true on success, false on failure.
     */
    private function thirdPartyTemplate(
        array $options,
        string $engine,
        int $status,
        bool $returnable = false,
        ?StaticCache $cache = null
    ): string|bool
    {
        $contents = null;
        self::$options = $options;
        $instance = self::getTemplateEngineInstance($engine, $this->pathname);

        $instance->setPath($this->pathname);

        if ($instance instanceof Smarty) {
            if (!$instance->isCached($this->basename)) {
                $instance->setProxy(
                    new Proxy($this, array_merge(self::$exports, $options))
                );
            }

            $contents = $instance->display($this->filepath);
        }else{
            $contents = $instance->display(
                $this->basename,
                new Proxy($this, array_merge(self::$exports, $options), false)
            );
        }
    
        Header::clearOutputBuffers('all');

        $isEmptyContent = empty($contents);
        $this->headers['X-System-Default-Headers'] = true;

        if(
            !$returnable && 
            ($isEmptyContent || $status === 204 || $status === 304 || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD')
        ){
            Header::validate(
                $this->headers, 
                $isEmptyContent ? 204 : $status
            );
            return true;
        }

        $this->headers['Content-Type'] ??= 'text/html';

        [$headers, $_contents, $cacheable] = $this->minifier($contents);

        if($returnable){
            $cache = null;
            return $_contents;
        }

        Header::validate($headers, $isEmptyContent ? 204 : $status);

        if($isEmptyContent){
            $cache = null;
            return true;
        }

        Header::setOutputHandler(true);
        echo $_contents;

        if($contents && $cacheable){
            $this->writeCache($cache, $_contents, $headers);
        }

        $_contents = $cache = null;
        return true;
    }

    /**
     * Write contents to cache.
     * 
     * @param StaticCache|null $cache 
     * @param string $contents 
     * @param array $headers
     * 
     * @return void
     */
    private function writeCache(?StaticCache $cache, string $contents, array $headers): void 
    {
        if($cache instanceof StaticCache){
            try{
                $cache->setFile($this->filepath)
                    ->saveCache($contents, $headers, $this->type);
            }catch(Throwable $e){
                Logger::alert(sprintf(
                    'Failed to cache template: %s (%s). Reason: %s',
                    $this->basename,
                    $this->type,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Extracts and registers template options for use within the view.
     * 
     * Behavior depends on config:
     * - true: Keys are prefixed with `_` and validated.
     * - false: Keys used as-is, but 'self' is restricted in isolation mode.
     * - null: Raw options passed without any transformation.
     *
     * @param array<string,mixed> $options Options to extract and expose to the view.
     *
     * @return void
     * @throws RuntimeException If 'self' is used without prefixing in isolation mode.
     */
    private static function extractOptions(array &$options): void
    {
        $prefixing = self::$config->variablePrefixing;

        if($prefixing === null){
            self::$options = $options;
            return;
        }

        if ($prefixing === false) {
            self::assertSelf($options);
            
            self::$options = $options;
            $options = null;
            return;
        }

        foreach ($options as $name => $value) {
            $key = str_starts_with($name, '_') ? $name : '_' . $name;

            self::assertOptionKey($key);
            self::$options[$key] = $value;
        }

        $options = null;
    }

    /**
     * Resolves the full file path of a given view template and sets internal properties.
     *
     * If the specified template does not exist in production mode, it falls back to a default `404` template.
     *
     * @param string $template The view template name without extension.
     * 
     * @return void
     */
    private function resolve(string $template): void 
    {
        $this->pathname = $this->getTemplatePath();
        $extension = self::getTemplateEngine()[1];
        $filepath = $this->pathname . $template . $extension;

        if (PRODUCTION && !is_file($filepath)) {
            $template = '4xx';
            $filepath = $this->pathname . $template . $extension;
        }

        $this->filepath = $filepath;
        $this->filename = $template;
        $this->basename = $template . $extension;
    }

    /**
     * Minifies and prepares template output content for delivery.
     *
     * @param string|bool $content The rendered content, or false if none.
     *
     * @return array{array,string,bool}  Return array of contents and headers
     */
    private function minifier(string|bool $content): array 
    {
        $cacheable = false;
        $headers = null;

        if (!self::isEmpty($content)) {
            if ($this->minification['minifiable'] && $this->type === self::HTML) {
                $minify = self::getMinifier(
                    $content, 
                    $this->type,  
                    $this->minification['codeblocks'], 
                    $this->minification['copyable']
                );

                $content = $minify->getContent();
                $headers = $minify->getHeaders();
            }else{
                $headers = ['Content-Type' => Header::getContentTypes($this->type)];
            }

            $cacheable = ($content !== '');
        }

        $headers ??= Header::getSentHeaders();

        return [$this->headers + $headers, $content, $cacheable];
    }
    
    /** 
     * Check if view should be optimized page caching or not.
     *
     * @return bool Return true if view should be cached, otherwise false.
     * > Keep the validation order its important.
     */
    private function isCacheable(): bool
    {
        if ($this->forceCache) {
            return true;
        }

        if (
            !$this->cacheable || 
            ($this->template === '4xx' || $this->template === '5xx') ||
            ($this->filename === '4xx' || $this->filename === '5xx') || 
            $this->isTtlExpired()
        ) {
            return false;
        }

        if($this->cacheConfig === []){
            return true;
        }

        // Check if the template is in the 'only' list
        // Always check only first
        if(!empty($this->cacheConfig['only'])){
            return in_array($this->template, $this->cacheConfig['only'], true);
        }

        // Check if the template is in the 'ignore' list
        return !in_array($this->template, $this->cacheConfig['ignore'] ?? [], true);
    }

    /**
     * Check if the cache expiration (TTL) is empty or expired.
     *
     * @return bool Returns true if no cache expiration is found or if the TTL has expired.
     */
    private function isTtlExpired(): bool 
    {
        if ($this->expiration === null) {
            return true;
        }

        if ($this->expiration instanceof DateTimeInterface) {
            return $this->expiration < new DateTimeImmutable(
                'now', 
                new DateTimeZone(date_default_timezone_get())
            );
        }

        return false;
    }

    /**
     * Logs a critical error when trying to access an undefined property in a view.
     *
     * @param string $property The name of the property being accessed.
     *
     * @return null Always returns null after logging the error.
     * @internal Also used in Scope class.
     */
    public final function __log(string $property) 
    {
        [$file, $line] = AppException::trace(2);

        Logger::critical(sprintf(
            'Access to undefined property $%s. In view: %s%s.',
            $property, 
            ($file !== null) ? $file : '',
            " on line {$line}"
        ));

        return null;
    }

    /** 
     * Re-throw or handle an exceptions.
     *
     * @param Throwable $exception The exception to manage.
     * @param array<string,mixed>|null $options Optional options for view error.
     * @param int|null $status Optional HTTP status code.
     *
     * @return void 
     * @throws RuntimeException
     */
    private static function __exception(Throwable $e, ?array $options = null, ?int $status = null): void 
    {
        if($e instanceof ExceptionInterface){
            if($options === null){
                throw $e;
            }
        
            self::__error($e, $options, $status);
            return;
        }

        if($options === null){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        RuntimeException::throwException($e->getMessage(), $e->getCode(), $e);
    }

    /**
     * Handle exceptions by loading the appropriate system error view.
     *
     * In production, a 404 page is shown and the error is logged.
     * In development, a detailed error view is shown instead.
     *
     * @param ExceptionInterface $error The thrown exception.
     * @param array<string,mixed> $options View options to extract as local variables.
     *
     * @return void
     */
    private static function __error(Throwable $error, array $options = [], ?int $status = null): void 
    {
        Header::clearOutputBuffers('all');
        $e = function(
            array $options, 
            string $fullPath, 
            bool $isNotFound,
            ?int $status, 
            Throwable $error
        ): void 
        {
            if ($options !== []) {
                extract($options, EXTR_PREFIX_SAME, '_');
            }

            $status ??= 500;
            $title ??= APP_NAME;
            $status = $isNotFound ? 404 : (($status === 200) ? 500 : $status);

            $message = $error->getMessage();
            $description = "{$status} " . HttpCode::phrase($status);

            if(PRODUCTION){
                $message = $isNotFound 
                    ? 'The template file you requested could not be found.' 
                    : 'Something went wrong.';
            }

            Header::sendStatus($status);

            /** 
             * @internal $message
             * @internal $description 
             */
            include $fullPath;
        };

        $isNotFound = ($error instanceof ViewNotFoundException);
        $fullPath = self::getSystemError(PRODUCTION  
            ? 'xxx' 
            : ($isNotFound ? 'view.error' : 'errors')
        );
        $e->bindTo(null, null)($options, $fullPath, $isNotFound, $status, $error);

        if(PRODUCTION && ($error instanceof ExceptionInterface)){
            $error->log();
        }

        exit(STATUS_ERROR);
    }

    /**
     * Throws the given exception after updating its file and line number from the call trace.
     *
     * @param ExceptionInterface $e The exception to throw.
     * @param int $trace The number of stack frames to skip to locate the caller.
     * @param bool $render If true present error details view. 
     *
     * @throws Throwable<ExceptionInterface> Always throw an exception.
     */
    private static function __throw(ExceptionInterface $e, int $trace, bool $render = false): void 
    {
        [$file, $line] = AppException::trace($trace + 1);

        if($file){
            $e->setLine($line)->setFile($file);
        }

        if(!$render){
            throw $e;
        }

        self::__error($e, []);
    }

    /**
     * Ensures the "self" key is not used in the options array when template isolation is enabled
     * and variable prefixing is disabled.
     *
     * @param array $options The array of options passed to the view renderer.
     *
     * @throws RuntimeException If the key "self" is present in the options while templateIsolation is enabled.
     */
    private static function assertSelf(array $options): void 
    {
        if (self::$config->templateIsolation && array_key_exists('self', $options)) {
            self::__throw(
                new RuntimeException(
                    'The template option key "self" is reserved. Enable variable prefixing to use it.'
                ), 
                5
            );
        }
    }

    /**
     * Validates that a view option key is a proper PHP variable name.
     *
     * @param string $key The option key to validate.
     * @throws RuntimeException If the key is invalid or already used.
     *
     * @return void
     */
    private static function assertOptionKey(string $key): void 
    {
        if ($key === '') {
            self::__throw(new RuntimeException('Template option key cannot be an empty string.'), 5);
        }

        if (array_key_exists($key, self::$exports)) {
            self::__throw(
                new RuntimeException(sprintf(
                   'Duplicate template option key "%s". Already defined in object exports. Use a unique name or a prefix.',
                    $key
                )), 
                5
            );
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/u', $key)) {
            self::__throw(
                new RuntimeException(sprintf(
                    'Invalid template option key "%s". Must start with a letter or underscore and contain only letters, digits, and underscores.',
                    $key
                )), 
                5
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
    // private static function __catchInlineErrors(string $contents): void
    // {
    //    if (!env('debug.display.errors', false) || !env('debug.catch.inline.errors', false)) {
    //        return;
    //    }

    //   $pattern = '/
    //       ^.*?<b>(?<type>Fatal\serror|Parse\serror|Uncaught\sError|Warning|Notice|Deprecated|Strict\sstandards|Error|Exception)<\/b>:\s*
    //       (?<message>.*?)
    //       \s+in\s+<b>(?<file>.*?)<\/b>\s+
    //        on\s+line\s+<b>(?<line>\d+)<\/b>
    //   /isx';

    //   $matches = [];

    //   if (preg_match($pattern, $contents, $matches)) {
    //        $e = new RuntimeException(sprintf(
    //            'Hidden error detected: %s: %s in %s on line %d',
    //            $matches['type'],
    //            trim($matches['message']),
    //            filter_paths($matches['file']),
    //            $matches['line']
    //        ), E_USER_WARNING);
    //        $e->setLine((int) $matches['line']);
    //        $e->setFile($matches['file']);

    //        throw $e;
    //    }
    // }

    /**
     * Parse user template options.
     * 
     * @param array<string,mixed> $options The template options.
     * 
     * @return array<string,mixed> Return the parsed options.
     * @throws InvalidArgumentException If options is list array
     */
    private function parseOptions(array $options = []): array 
    {
        if ($options !== [] && array_is_list($options)) {
            throw new InvalidArgumentException(
                "Template options expects associative array for \$options, list array given."
            );
        }

        $options['viewType'] = $this->type;
        $options['href'] = self::link();
        $options['asset'] = $options['href'] . 'assets/';
        $options['active'] = $this->filename;
        $options['noCache'] = (bool) ($options['noCache'] ?? false);
        
        if($options['noCache']){
            $this->cacheConfig['ignore'][] = $this->template;
        }

        if(!isset($options['title'])){
            $options['title'] = $this->toTitle($options['active'], true);
        }

        if(!isset($options['subtitle'])){
            $options['subtitle'] = $this->toTitle($options['active']);
        }

        return $options;
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
    private function getTemplatePath(): string 
    {
        $module = self::$isHmvcModule 
            ? '/app/Modules/' . ($this->module === ''? '' : $this->module . '/') . 'Views/'
            : self::$folder . '/';
    
        return self::getSystemPath($module . $this->subfolder);
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
     * Convert route segments to relative parent directory level.
     * 
     * This method fixes the broken assets and links when added additional slash(/) at the route URI. 
     * By adding the appropriate parent level to URIs.
     *
     * @return string Return relative path.
     */
    private static function toRelativeLevel(?int $level = null): string 
    {
        $level ??= self::$uriPathDepth;
        
        if($level === 0 && !empty($_SERVER['REQUEST_URI'])){
            $url = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen(Luminova::getBase()));

            if (($pos = strpos($url, '?')) !== false) {
                $url = substr($url, 0, $pos);
            }

            $level = substr_count('/' . trim($url, '/'), '/');
        }

        $relative = (($level === 0) ? './' : str_repeat('../', $level));

        return $relative . ((NOVAKIT_ENV === null) ? 'public/' : '');
    }

    /**
     * Returns an instance of the Smarty or Twig template engine.
     *
     * @param string $engine Template engine name ('smarty' or 'twig').
     * @param string $filepath View template directory (used only for Twig).
     *
     * @return Smarty|Twig Return instance of the selected template engine.
     *
     * @throws RuntimeException If an unsupported engine is specified.
     */
    private static function getTemplateEngineInstance(string $engine, string $filepath): Smarty|Twig
    {
        $root = self::getSystemRoot();

        return match ($engine) {
            'twig' => Twig::getInstance(self::$config, $root, $filepath, [
                'caching' => false,
                'cache' => false, 
                'charset' => env('app.charset', 'utf-8'),
                'strict_variables' => !PRODUCTION,
                'auto_reload' => !PRODUCTION,
                'debug' => !PRODUCTION,
                'autoescape' => 'html',
            ]),
            'smarty' => Smarty::getInstance(self::$config, $root, [
                'caching' => false,
                'compile_check' => !PRODUCTION,
                'debugging' => !PRODUCTION,
                'escape_html' => true,
            ]),
            default => throw new RuntimeException(sprintf(
                "Template engine '%s' is not supported. Use 'default' (PHP), 'twig' or 'smarty'.",
                $engine
            )),
        };
    }

    /** 
     * Initialize minification instance.
     *
     * @param mixed $contents view contents output buffer.
     * @param string $type The rendering template content type.
     * @param bool $ignore Whether to ignore code blocks minification.
     * @param bool $copy Whether to include code block copy button.
     *
     * @return Minifier Return minified instance.
     * @throws RuntimeException If array or object content and json error occurs.
     */
    private static function getMinifier(
        mixed $contents, 
        string $type = self::HTML, 
        bool $ignore = true, 
        bool $copy = false,
    ): Minifier
    {
        return (new Minifier())
            ->isHtml($type === self::HTML)
            ->codeblocks($ignore)
            ->copyable($copy)
            ->compress($contents, $type);
    }

    /** 
     * Get page view cache instance.
     *
     * @param DateTimeInterface|int|null $expiry  Cache expiration ttl (default: 0).
     *
     * @return StaticCache Return page view cache instance.
     */
    private static function getCache(DateTimeInterface|int|null $expiry = 0): StaticCache
    {
        return (new StaticCache())
            ->setExpiry($expiry)
            ->setDirectory(self::getSystemPath(
                '/writeable/caches/templates/'
            ))
            ->setKey(Luminova::getCacheId())
            ->setUri(Luminova::getUriSegments());
    }
}