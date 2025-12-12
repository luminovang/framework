<?php 
declare(strict_types=1);
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
use \App\Kernel;
use \DateTimeZone;
use Luminova\Runtime;
use Luminova\Luminova; 
use \DateTimeImmutable;
use \DateTimeInterface;
use Luminova\Time\Time;
use Luminova\Config\Env;
use Luminova\Http\Header;
use Luminova\Utility\Mime;
use Luminova\Logger\Logger;
use Luminova\Routing\Router;
use Luminova\Http\HttpStatus;
use Luminova\Debugger\Tracer;
use Luminova\Promise\Promise;
use Luminova\Cache\ViewCache;
use Luminova\Template\Response;
use Luminova\Template\Minifier;
use Luminova\Foundation\Core\Application;
use Luminova\Components\Object\LazyObject;
use \App\Config\Template as TemplateConfig;
use function Luminova\Funcs\get_class_name;
use Luminova\Template\Engines\{NoScope, Layout, Scope, Twig, Smarty, Proxy};
use Luminova\Interface\{LazyObjectInterface, ExceptionInterface, PromiseInterface}; 
use Luminova\Exceptions\{
    ErrorException,
    RuntimeException, 
    ViewNotFoundException, 
    Http\ResponseException,
    BadMethodCallException, 
    InvalidArgumentException
};

/**
 * Template view helper. 
 * 
 * @category View
 * @property-read Application|null $app
 * @property-read \Luminova\Template\Engines\Scope<\Luminova\Template\View,Application> $self
 * 
 * @property-read string $href Relative path from entry URI (When Prefixing Disabled).
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
    public final const HTML = 'html';

    /**
     * When rendering strict HTML contents.
     *
     * @var string XHTML
     */
    public final const XHTML = 'xhtml';

    /**
     * When rendering data as JSON.
     *
     * @var string JSON
     */
    public final const JSON = 'json';

    /**
     * When rendering plain text content.
     *
     * @var string TEXT
     */
    public final const TEXT = 'txt';

    /**
     * When rendering XML content.
     *
     * @var string
     */
    public final const XML = 'xml';

    /**
     * When rendering JavaScript (.js) content.
     *
     * @var string
     */
    public final const JS = 'js';

    /**
     * When rendering Cascading Style Sheets (.css).
     *
     * @var string
     */
    public final const CSS = 'css';

    /**
     * When rendering RDF (Resource Description Framework) data.
     *
     * @var string
     */
    public final const RDF = 'rdf';

    /**
     * When rendering Atom feeds.
     *
     * @var string
     */
    public final const ATOM = 'atom';

    /**
     * When rendering RSS (Really Simple Syndication) feeds.
     *
     * @var string
     */
    public final const RSS = 'rss';

    /**
     * Binary inline-safe content. (application/octet-stream).
     *
     * @var string
     */
    public final const BIN = 'bin';

    /**
     * PNG images.
     */
    public final const PNG = 'png';

    /**
     * JPEG images.
     */
    public final const JPEG = 'jpeg';

    /**
     * GIF images.
     */
    public final const GIF = 'gif';

    /**
     * WebP images.
     */
    public final const WEBP = 'webp';

    /**
     * SVG images.
     */
    public final const SVG = 'svg';

    /**
     * AVIF images.
     */
    public final const AVIF = 'avif';
    /**
     * MP3 audio.
     */
    public final const MP3 = 'mp3';

    /**
     * OGG audio.
     */
    public final const OGG = 'ogg';

    /**
     * WebM audio or video format.
     *
     * @example - Set correct header:
     * ```
     * $this->tpl->header('Content-Type', 'audio/webm');
     * ```
     * > **Note:**
     * - Must be served with either `audio/webm` or `video/webm`
     * - Using the wrong header may prevent inline playback
     * 
     */
    public final const WEBM = 'webm';

    /**
     * MP4 video.
     */
    public final const MP4 = 'mp4';

    /**
     * Flag for key not found.
     * 
     * @var string KEY_NOT_FOUND
     */
    public final const KEY_NOT_FOUND = '__nothing__';

    /**
     * Supported view types.
     *
     * @var array<string,true> SUPPORTED_TYPES
     */
    private const SUPPORTED_TYPES = [
        self::HTML => true, self::XHTML => true, self::JSON  => true, 
        self::TEXT => true, self::XML   => true, self::JS    => true, self::BIN  => true,
        self::CSS  => true, self::RDF   => true, self::ATOM  => true, self::RSS  => true,
        self::PNG  => true, self::JPEG  => true, self::GIF   => true, self::WEBP => true,
        self::SVG  => true, self::AVIF  => true, self::MP3   => true, self::OGG  => true, 
        self::WEBM => true, self::MP4   => true, 'text' => true,
    ];

    /**
     * Reserved immutable options
     * 
     * @var array<string,array<string,true>> RESERVED_OPTIONS
     */
    private const RESERVED_OPTIONS = [
        'plain' => [
            'href'      => true,
            'self'      => true,
            'asset'     => true,
            'active'    => true,
            'tplType'   => true,
        ],
        'prefixed' => [
            '_href'     => true,
            '_self'     => true,
            '_asset'    => true,
            '_active'   => true,
            '_tplType'  => true,
        ]
    ];

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
     * @var string|null $module 
     */
    private ?string $module = null;

    /** 
     * Controller class name.
     * 
     * @var string|null $controller 
     */
    private ?string $controller = null;

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
     * @var bool $forceCacheEnable 
     */
    private bool $forceCacheEnable = false;

    /**
     * Force use of cache response.
     * 
     * @var bool $immutable 
     */
    private ?bool $immutable = null;

    /**
     * Minify HTML content.
     * 
     * @var array{minifiable:bool,preserve:array,buttons:array} $minification 
     */
    private array $minification = [
        'minifiable'  => false,
        'codeblocks'  => false,
        'buttons'     => []
    ];

    /**
     * Should cache view base.
     * 
     * @var bool $cacheable
     */
    private bool $cacheable = false;

    /**
     * Mark isolation object.
     * 
     * @var bool $isIsolationObject 
     * @ignore
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
     * @var int|null $uriDepth 
     */
    private ?int $uriDepth = null;

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
     * @var Application<LazyObjectInterface> $app
     */
    public readonly LazyObjectInterface $app;

    /**
     * Instance template cache object.
     * 
     * @var ViewCache|null $cache
     */
    private ?ViewCache $cache = null;

    /**
     * Initialize the View object.
     * 
     * This constructor sets up template configuration for view management, 
     * and loads environment-based options.
     * 
     * @param Application<LazyObjectInterface>|null $app Optional application object. 
     * @throws RuntimeException If `$app` is not null and not an instance of Application class.
     * 
     * > **Note:** 
     * > If `$app` is null, templates will not have access to 
     * > the application instance via (`$this->app` or `$self->app`).
     */
    public function __construct(
        Application|LazyObjectInterface|null $app = null
    )
    {
        self::$config ??= new TemplateConfig();
        self::$exports = [];

        // Feature flags from .env or runtime config
        $this->htmlMinification(
            (bool) Env::get('output.minify.html', false),
            (array) Env::get('output.minify.preserve.tags', ['TEXTAREA', 'CODE']),
            (array) Env::get('output.minify.codeblock.buttons', [])
        );
        
        $this->cacheable = (bool) Env::get('page.caching', false);
        $this->expiration = (int) Env::get('page.cache.expiry', 0);

        if($app === null){
            return;
        }

        $this->setApplication($app);
    }

    /**
     * Set application object for template view class.
     * 
     * @param Application<LazyObjectInterface> $app The application object. 
     * 
     * @return self Returns instance of view class.
     * @throws RuntimeException If `$app` is not null and not an instance of Application class.
     */
    public function setApplication(Application&LazyObjectInterface $app): self 
    {
        if(isset($this->app)){
            return $this;
        }

        if (
            !($app instanceof Application)
            && ($app instanceof LazyObject)
            && !$app->isLazyInstanceof(Application::class)
        ) {
            throw new RuntimeException(sprintf(
                'View expected an instance of T<Luminova\Foundation\Core\Application>, %s given.',
                get_class($app)
            ));
        }

        $this->app = $app;
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
        if (
            isset(self::RESERVED_OPTIONS['plain'][$name])
            || isset(self::RESERVED_OPTIONS['prefixed'][$name])
        ) {
            self::__throw(new RuntimeException(
                sprintf('Immutable option property "$%s" is read-only and cannot be modified.', $name)
            ), 1);
        }

        self::$options[$name] = $value;
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
        // if(isset($this->{$property})){
        //    return true;
        // }

        return $this->hasOption($property) 
            || $this->isExported($property);
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

        if(
            self::$config->variablePrefixing 
            && !str_starts_with($name, '_')
        ){
            $name = "_{$name}";
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

        if (
            self::$config->variablePrefixing 
            && !str_starts_with($key, '_')
        ) {
            $key = "_{$key}";
        }

        return self::$options[$key]
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
     * This includes any keys with or without prefixing depending 
     * on the `$variablePrefixing` configuration.
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
        return $resolved 
            ? $this->filename 
            : $this->template;
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
     * Get template response headers.
     *
     * Returns the prepared headers before rendering.
     * If called after rendering, returns the final headers that were sent.
     *
     * @return array<string,mixed> Returns template headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the subfolder used to resolve controller templates.
     *
     * The folder is resolved relative to the configured view directory and can
     * be used to organize templates by feature, section, or module.
     *
     * Supported view roots include:
     * - `/resources/Views/`
     * - `/app/Modules/Views/`
     * - `/app/Modules/<Module>/Views/`
     *
     * @param string $folder View subfolder name or path.
     *
     * @return self The current template view instance.
     *
     * @see self::setModule() for HMVC module view directory configuration.
     *
     * > **Notes:**
     * > - When used in a controller's `onCreate` or `__construct`, all views 
     * > for that controller will be searched in this folder.
     * > - When used in a controller method before rendering, only that method's view lookup is affected.

     * @example - Set subfolder in controller constructor:
     * ```php
     * public function __construct()
     * {
     *     $this->tpl->setFolder('Admin');
     * }
     * ```
     *
     * @example - Set subfolder in controllers onCreate method:
     * ```php
     * public function onCreate()
     * {
     *     $this->tpl->setFolder('Public');
     * }
     * ```
     *
     * @example - Set subfolder in controller method:
     * ```php
     * public function show()
     * {
     *     $this->tpl->setFolder('Pages')->view('about')->render();
     * }
     * ```
     * 
     * @example - Set subfolder in error controllers:
     * ```php
     * public function onExampleError()
     * {
     *     view('4xx')->setFolder('Examples')->render();
     * }
     * ```
     */
    public final function setFolder(string $folder): self
    {
        $this->subfolder = trim($folder, TRIM_DS);

        if ($this->template !== '') {
            $this->resolve($this->template);
        }

        return $this;
    }

    /**
     * Set the HMVC module name for the current controller.
     *
     * The module name typically corresponds to the directory under
     * `app/Modules/<Module>`, such as `Blog` for
     * `app/Modules/Blog/Controllers/PostController.php`.
     *
     * An empty string indicates that the controller belongs to the root scope.
     *
     * @param string $module The module name. Must not contain `/` or `\`.
     *
     * @return self The current template view instance.
     * @throws RuntimeException If the module name contains a path separator.
     *
     * @see self::setFolder() For organizing template sub-directories
     */
    public final function setModule(string $module = '/'): self
    {
        $module = trim($module, '/');

        if (!self::isModule($module)) {
            throw new RuntimeException(sprintf(
                'Invalid module name: %s. Module names cannot contain path separators.', 
                $module
            ));
        }

        $this->module = $module;

        return $this;
    }

    /**
     * Set the controller class name used by the view.
     *
     * @param string $controller Fully qualified controller class name.
     *
     * @return self The current template view instance.
     * @see self::setModule() - To explicitly set HMVC module name.
     */
    public final function setController(string $controller): self
    {
        $this->controller = $controller;

        return $this;
    }

    /**
     * Check whether an HMVC module name is valid.
     *
     * @param string $module The module name to validate.
     *
     * @return bool `true` if valid, otherwise `false`.
     *
     * @internal Used internally for module validation.
     */
    public static function isModule(string $module): bool
    {
        return $module !== '' 
            && strpbrk($module, '/\\') === false;
    }

    /**
     * Set the relative URI directory depth.
     *
     * Sets the number of parent directory levels (`../`) used when generating
     * relative asset and link URLs. Passing `null` restores automatic depth
     * detection based on the current request URI.
     *
     * @param int|null $depth The number of parent directory levels to use,
     *                        or null to enable automatic depth detection.
     *
     * @return self The template view instance.
     *
     * @example - Example:
     * ```php
     * $this->setRelativeDepth(1);
     *
     * asset('images/logo.png'); // ../images/logo.png
     * href('about');            // ../about
     *
     * $this->setRelativeDepth(2);
     *
     * asset('images/logo.png'); // ../../images/logo.png
     *
     * $this->setRelativeDepth(null);
     * // Restore automatic depth detection.
     * ```
     */
    public final function setRelativeDepth(?int $depth): self
    {
        $this->uriDepth = $depth;
        return $this;
    }

    /**
     * Configure HTML template minification.
     * 
     * Controls template HTML content minification and which HTML tags should
     * have their contents preserved during minification process. 
     *
     * @param bool $enable Whether to minify the template content.
     * @param string[]|null $preserveTags HTML tags whose content should be preserved,
     *        such as `PRE`, `CODE`, `SCRIPT`, and `STYLE`.
     *        If `null`, the current configured tags are used.
     * @param string[]|null $codeBlockButtons Code block actions to enable,
     *        such as `copy`, `ai`, and `run`.
     *        If `null`, the current configured buttons are used.
     *
     * @return self The current template view instance.
     */
    public final function htmlMinification(
        bool $enable,
        ?array $preserveTags = null,
        ?array $codeBlockButtons = null
    ): self 
    {
        $this->minification = [
            'minifiable' => $enable,
            'preserve'   => $preserveTags ?? ($this->minification['preserve'] ?? []),
            'buttons'    => $codeBlockButtons ?? ($this->minification['buttons'] ?? []),
        ];

        return $this;
    }

    /**
     * Exclude one or more templates from caching.
     * 
     * This method allows you to exclude one or more templates name from caching it rendered content.
     *
     * @param string|string[] $template Template name or names to exclude from caching.
     *
     * @return self Return instance of template view class.
     * 
     * @see self::cacheOnly()
     * @see self::cacheable()
     *
     * > **Recommended:** 
     * > Call in `onCreate()` or `__construct()` of the controller or application.
     */
    public final function cacheExclude(array|string $template): self
    {
        return $this->cacheWithTemplate('ignore', $template);
    }

    /**
     * Cache only the specified templates.
     *
     * When configured, templates not listed here will not be cached.
     *
     * @param string|string[] $template Template name or names to cache exclusively.
     *
     * @return self The current template view instance.
     *
     * @see self::cacheExclude()
     * @see self::cacheable()
     * 
     * > **Recommended:** 
     * > Call in `onCreate()` or `__construct()` of the controller or application.
     */
    public final function cacheOnly(array|string $template): self
    {
        return $this->cacheWithTemplate('only', $template);
    }

    /**
     * Enable or disable view caching.
     *
     * This setting overrides the `env(page.caching)` configuration.
     *
     * When configured in a controller's `onCreate()` or constructor, it applies
     * to all templates handled by that controller. When configured in the
     * application class, it applies globally. When called before rendering
     * inside a routable controller method, it applies to the current view only.
     *
     * @param bool $enable Whether to enable view caching.
     *
     * @return self The current view instance.
     *
     * @see self::cacheExclude()
     * @see self::cacheOnly()
     */
    public final function cacheable(bool $enable = true): self
    {
        $this->cacheable = $enable;

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
     * 
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
    public final function export(
        object|string $target, 
        ?string $alias = null, 
        bool $shared = false
    ): bool
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
     * @example - Basic usage:
     * ```php
     * public function fooView(): int 
     * {
     *     return $cache->view('foo')
     *          ->cache(expiry: 60)
     *          ->render(['data' => '...']);
     * }
     * ```
     * @example - With conditional caching:
     * ```php
     * public function fooView(User $user): int 
     * {
     *     // Init Cache system
     *     $tpl = $this->tpl->cache(expiry: 60); // Cache for 60 seconds
     *
     *     if ($tpl->expired()) {
     *         $heavy = $user->doHeavyProcess();
     *         return $tpl->view('foo')
     *              ->render(['data' => $heavy]);
     *     }
     *
     *     return $user->reuse(); // Reuse the previously cached response
     * }
     * ```
     */
    public final function cache(
        DateTimeInterface|int|null $expiry = null,
        ?bool $immutable = null
    ): self
    {
        $this->forceCacheEnable = true;
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
        $this->setTemplateType($type ?? self::HTML);

        $expired = self::getCache()
            ->burst($this->maxBurst)
            ->expired($this->type);

        if($expired === null){
            throw new RuntimeException(
                sprintf('Invalid mismatch template view type: %s', $this->type)
            );
        }

        return $expired;
    }

    /**
     * Temporarily bypass browser caching for the rendered view.
     *
     * The cache burst remains active until explicitly disabled or the specified
     * duration or expiration time is reached.
     *
     * @param DateTimeInterface|int|null $maxTime Duration in seconds or a future
     *                                            expiration time. `null` disables
     *                                            the cache burst.
     *
     * @return self Returns instance of the view class.
     *
     * @example - Usage:
     * 
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
     * 
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
     * 
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
        if (!$this->forceCacheEnable) {
            throw new RuntimeException(
                'Cannot call ->reuse() without first calling ->cache().'
            );
        }

        $this->forceCacheEnable = false;

        return self::getCache($this->expiration)
            ->burst($this->maxBurst)
            ->read() ? STATUS_SUCCESS : STATUS_SILENCE;
    }

    /**
     * Reuse the cached view when valid; otherwise renew it using the callback.
     *
     * @param Closure $onRenew Callback invoked when the cache is unavailable or expired.
     * @param array<string,mixed> $options Options passed to the renewal callback.
     * @param string|null $type Template content type to check, such as `View::HTML` or `View::JSON`.
     *
     * @return int `reuse()` status when a valid cache is reused, otherwise the callback status.
     *
     * @see self::reuse()
     * @see self::cache()
     * @see self::expired()
     *
     * @example - Example:
     * 
     * ```php
     * public function profile(): int
     * {
     *     return $this->tpl->cache(300)->onExpired(
     *         function (array $options): int {
     *             $data = $model->getProfileData($options['id']);
     *
     *             return $this->view('user/profile')
     *                 ->render(['user' => $data]);
     *         },
     *         ['id' => 100]
     *     );
     * }
     * ```
     */
    public final function onExpired(
        Closure $onRenew, 
        array $options = [], 
        ?string $type = self::HTML
    ): int
    {
        if ($this->isCacheable() && !$this->expired($type)) {
            $this->forceCacheEnable = true;
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
     * @see self::getHeaders()
     * 
     * @example - Example:
     * ```php
     * $this->tpl->header('Content-Type', 'application/json');
     * ```
     */
    public final function header(string $key, mixed $value): self 
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set multiple HTTP headers for the response.
     *
     * @param array<string,mixed> $headers Associative array of headers where key is the header name
     *                                      and value is the header value.
     * 
     * @return self Return instance of template view class.
     * @throws InvalidArgumentException If non-empty list array is provided.
     * @see self::getHeaders()
     * 
     * @example - Example:
     * ```php
     * $this->tpl->headers([
     *      'Content-Type' => 'application/json'
     * ]);
     * ```
     */
    public final function headers(array $headers): self 
    {
        if($headers !== [] && array_is_list($headers)){
            throw new InvalidArgumentException(
                'Headers must be an associative array with header names as keys.'
            );
        }

        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the view template and content type for rendering.
     *
     * The template is resolved against the configured view directories.
     * File extensions are optional and removed automatically when provided.
     *
     * Supported view types include `html`, `json`, `text`, `xml`, `js`, `css`,
     * `rdf`, `atom`, and `rss`.
     *
     * @param string $template View template name or path without extension,
     *                         such as `dashboard/index`.
     * @param string $type View content type. Defaults to `View::HTML`.
     *
     * @return self Returns this view instance.
     * @throws InvalidArgumentException If the template name is empty or the
     *                                  content type is unsupported.
     *
     * @see self::render() Render the view and send the response.
     * @see self::contents() Render the view and return its contents.
     * @see self::promise() Render the view asynchronously.
     * @see self::exists() Check whether the resolved view template exists.
     * @see self::info() Get information about the resolved view template.
     *
     * @example - Direct Usage:
     * 
     * ```php
     * $tpl = new View(application);
     * 
     * // Render the view and return the HTTP status code
     * $status = $tpl->view('profile', View::HTML)->render(['name' => 'John']);
     * 
     * // Render the view and return the content as string
     * $html = $tpl->view('dashboard', View::HTML)->response(['user' => User::find(100)]);
     * 
     * // Render the view and return a promise object for async handling
     * $promise = $tpl->view('report', View::HTML)->promise(['data' => $data]);
     * ```
     * 
     * @example - Usage in Controller:
     * 
     * ```php
     * // /app/Controllers/Http/
     * // /app/Modules/Controllers/Http/
     * 
     * // Render view and return status
     * $status = $this->tpl->view('profile', View::HTML)->render(['id' => 1]);
     * ```
     */
    public final function view(string $template, string $type = self::HTML): self 
    {
        $template = trim($template, TRIM_DS);

        if($template === ''){
            throw new InvalidArgumentException('Template name is required, cannot be an empty-string.');
        }

        $ext = self::getTemplateEngine()[1];

        if (str_ends_with($template, $ext)) {
            $template = substr($template, 0, -strlen($ext));
        }

        $this->setTemplateType($type, $template);

        $this->template = $template;
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
     * Render the view and send its output to the client.
     *
     * @param array<string,mixed> $options Parameters made available to the view template.
     * @param int $status HTTP response status code. Defaults to `200`.
     *
     * @return int `STATUS_SUCCESS` if the view was sent successfully, or
     *             `STATUS_SILENCE` if the response was silently suppressed.
     * @throws RuntimeException If view rendering fails.
     *
     * @see self::send() Render and send the view output.
     * @see self::contents() Render the view and return its contents.
     * @see self::promise() Render the view asynchronously.
     *
     * @example - Display template view with options:
     * 
     * ```php
     * public function fooView(): int
     * {
     *     return $this->tpl->view('name')->render(['name' => 'John']);
     * }
     * ```
     *
     * @example - Caching Configuration:
     * 
     * ```php
     * public function fooView(): int
     * {
     *     return $this->tpl->view('name')
     *         ->cache(expire: 50, immutable: true)
     *         ->render(['name' => 'John']);
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
     * Render the view and return its output as a string.
     *
     * @param array<string,mixed> $options Parameters made available to the view template.
     * @param int $status HTTP response status code. Defaults to `200`.
     *
     * @return string|null The rendered view contents, or `null` if the output is empty.
     * @throws RuntimeException If view rendering fails.
     *
     * @see self::render() Render the view and send its output to the client.
     * @see self::promise() Render the view asynchronously.
     *
     * @example - Display your template view or send as an email:
     * 
     * ```php
     * public function sendWelcomeEmail(): int
     * {
     *     $content = $this->tpl->view('userWelcome')
     *         ->contents(['name' => 'Peter']);
     *
     *     return Mailer::to('peter@example.com')->send($content)
     *         ? STATUS_SUCCESS
     *         : STATUS_ERROR;
     * }
     * ```
     *
     * @example - Cache Content:
     * 
     * ```php
     * public function page(): int
     * {
     *     echo $this->tpl->view('page')
     *         ->cache(60)
     *         ->contents() ?? '';
     * 
     *      return STATUS_SUCCESS;
     * }
     * ```
     */
    public final function contents(array $options = [], int $status = 200): ?string
    {
        return $this->send($options, $status, true) ?: null;
    }

    /**
     * Return a promise that resolves with the rendered view contents.
     *
     * The promise resolves with the rendered contents and the view options,
     * or rejects if rendering fails.
     *
     * @param array<string,mixed> $options Parameters made available to the view template.
     * @param int $status HTTP response status code. Defaults to `200`.
     *
     * @return PromiseInterface A promise that resolves with the rendered contents
     *                          and view options, or rejects with an error.
     *
     * @see self::render() Render the view and send its output to the client.
     * @see self::contents() Render the view and return its output as a string.
     * @see PromiseInterface
     *
     * @example - Display your template view or send as an email:
     * ```php
     * public function fooView(): int
     * {
     *     $this->tpl->view('name')
     *         ->promise(['foo' => 'bar'])
     *         ->then(function (string $content, array $options): void {
     *             echo $content;
     *         })
     *         ->catch(function (Throwable $e): void {
     *             echo $e->getMessage();
     *         });
     *
     *     return STATUS_SUCCESS;
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
     * Return metadata for the resolved view template without rendering it.
     *
     * The metadata includes the template location, content type, template name,
     * engine, module, file size, modification time, directory, extension, and filename.
     *
     * @param string|null $key Optional metadata key to retrieve. Returns `null`
     *                         when the key does not exist.
     *
     * @return mixed|array{
     *     location: string,
     *     type: string,
     *     template: string,
     *     engine: string,
     *     size: int,
     *     timestamp: int,
     *     modified: string,
     *     module: ?string,
     *     dirname: ?string,
     *     extension: ?string,
     *     filename: ?string
     * } The requested metadata value, or the complete metadata array when `$key` is `null`.
     *
     * @example - Example:
     * ```php
     * $info = $this->tpl->view('dashboard')->info();
     *
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

        $this->module ??= self::resolveModule($this->controller);

        $metadata['module'] = Runtime::isHmvc() ? ($this->module ?: 'root') : null;

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

        return ($key === null) 
            ? $metadata 
            : ($metadata[$key] ?? null);
    }

    /** 
     * Redirect to a different URI or route.
     *
     * @param string $uri The target URI or route.
     * @param int $status The HTTP redirect status code (default: 302).
     *
     * @return never
     * @see \Luminova\Funcs\redirect()
     * 
     * @example - Usage:
     * ```php
     * $this->tpl->redirect('/dashboard');   // absolute path
     * $this->tpl->redirect('user/profile'); // relative path
     * ```
     */
    public final function redirect(string $uri, int $status = 302): never 
    {
        Response::getInstance($status)
            ->setStatus($status)
            ->redirect($uri);
            
        exit(STATUS_SUCCESS);
    }

    /**
     * Generate a relative URI from the public root.
     *
     * Builds a path to routes or public assets (CSS, JS, images)
     * relative to the current request.
     *
     * - In production, always returns a root-based path.
     * - In development, calculates a relative path using URI depth.
     *
     * @param string $route Optional route or asset path to append.
     *
     * @return string Relative or root-based URI.
     *
     * @see \Luminova\Funcs\href()
     * @see \Luminova\Funcs\asset()
     *
     * @example - Example:
     * ```php
     * <link href="<?= $this->link('assets/css/main.css') ?>" rel="stylesheet">
     * <a href="<?= $this->link('about') ?>">About Us</a>
     * ```
     */
    public final function link(string $route = '/'): string 
    {
        return self::relativePath($route, $this->uriDepth);
    }

    /**
     * Return the current view's formatted page title.
     *
     * Converts underscores and hyphens to spaces, removes commas, capitalizes
     * each word, and optionally appends a suffix.
     *
     * @param string|null $suffix Suffix to append to the title. Defaults to
     *                            ` - ` followed by the application name.
     *
     * @return string The formatted page title.
     * 
     * @example - Example:
     * ```php
     * <title><?= $this->title() ?></title> 
     * <title><?= $this->title('My App') ?></title>
     * ```
     */
    public final function title(?string $suffix = null): string
    {
        $title = ucwords(strtr($this->filename, [
            '_' => ' ',
            '-' => ' ',
            ',' => '',
        ]));

        return $title . ($suffix ?? ' - ' . APP_NAME);
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
     * 
     * @codeCoverageIgnore
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
            Luminova::appRoot(), 'app',
            DIRECTORY_SEPARATOR, 'Errors',
            DIRECTORY_SEPARATOR, 'Defaults',
            DIRECTORY_SEPARATOR, "{$filename}.php"
        );        
    }

    /**
     * Resolve HMVC model name/prefix from controller
     *
     * @param string|null $controller
     * 
     * @return string|null Return HMVC module name
     */
    private static function resolveModule(?string $controller): ?string
    {
        if ($controller === null || !Runtime::isHmvc()) {
            return null;
        }

        $prefix = 'App\\Modules\\';
        $controller = trim($controller, '\\');

        if (!str_starts_with($controller, $prefix)) {
            return '';
        }

        $module = strtok(substr($controller, strlen($prefix)), '\\');

        return ($module === 'Controllers') ? '' : ($module ?: '');
    }

    /**
     * Specify templates that should be cached or excluded.
     *
     * @param string $context The cache config context.
     * @param string|string[] $template A single template.
     *
     * @return self Return instance of template view class.
     */
    private function cacheWithTemplate(string $context, array|string $template): self
    {
        $templates = is_array($template) ? $template : [$template];

        $this->cacheConfig[$context] = array_values(array_unique([
            ...($this->cacheConfig[$context] ?? []),
            ...$templates,
        ]));

        return $this;
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
     * 
     * @codeCoverageIgnore
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
     * Generate a URI relative to the application public root.
     *
     * In development, the URI is adjusted based on the current request path
     * and the specified directory depth. When the depth is null, it is derived
     * from the current request URI. When running outside the application
     * container, the `public/` directory is included in the generated URI.
     *
     * @param string $uri Optional route or asset path to append.
     * @param int|null $depth Optional parent directory depth. If null, the depth
     *                        is derived from the current request URI.
     *
     * @return string The generated relative URI.
     *
     * @see \Luminova\Funcs\href()
     * @see \Luminova\Funcs\asset()
     *
     * @example - Example:
     * ```php
     * <link href="<?= View::relativePath('assets/css/main.css') ?>" rel="stylesheet">
     * <a href="<?= View::relativePath('about') ?>">About Us</a>
     * ```
     * 
     * @codeCoverageIgnore
     */
    public static final function relativePath(string $uri = '/', ?int $depth = null): string
    {
        if (PRODUCTION) {
            return ($uri === '/' || $uri === '')
                ? '/'
                : '/' . ltrim($uri, '/');
        }

        if ($depth === null) {
            $path = Router::getUriPath();
            $depth = ($path === '') ? 0 : substr_count($path, '/');
        }

        $base = ($depth > 0)
            ? str_repeat('../', $depth)
            : './';

        if (Runtime::isOutsideContainer()) {
            $base .= 'public/';
        }

        return ($uri === '/' || $uri === '')
            ? $base
            : $base . ltrim($uri, '/');
    }

    /**
     * Set the relative URI directory depth.
     *
     * @param int|null $depth The number of parent directory levels to use,
     *                        or null to restore automatic depth detection.
     *
     * @return self The template view instance.
     *
     * @deprecated 4.0.0 Use {@see setRelativeDepth()} instead.
     * @codeCoverageIgnore
     */
    public final function setUriPathDepth(?int $depth): self
    {
        return $this->setRelativeDepth($depth);
    }

    /**
     * Exclude one or more templates from caching.
     *
     * @param string|string[] $template Template name or names to exclude from caching.
     *
     * @return self The current template view instance.
     *
     * @deprecated Use {@see self::cacheExclude()} instead.
     * @codeCoverageIgnore
     */
    public final function noCaching(array|string $template): self
    {
        return $this->cacheExclude($template);
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
     * Set and validate the template content type.
     *
     * @param string $type The template content type or extension (e.g. "html", "json", "rss", "webm").
     * @param string $template The template name or identifier, used only for error reporting.
     *
     * @return void
     *
     * @throws InvalidArgumentException When the template type is not supported.
     */
    private function setTemplateType(string $type, string $template = ':file'): void 
    {
        $type = strtolower($type);

        if (!isset(self::SUPPORTED_TYPES[$type])) {
            self::__throw(new InvalidArgumentException(sprintf(
                'Unsupported template view type "%s" for template "%s". Supported: [%s]. '. 
                'For custom types, use "render" method in Luminova\Funcs\response() or Luminova\Template\Response class.',
                $type, 
                $template, 
                implode(', ', array_keys(self::SUPPORTED_TYPES))
            )), 2, true);
        }

        $this->type = $type;
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
        Header::setOutputHandler(true, false);

        $this->module ??= self::resolveModule($this->controller);
        $this->status = $status;
        $options = $this->parseOptions($options);

        try {
            $cacheable = $this->isCacheable();
            $engine = self::getTemplateEngine()[0];
            $this->cache = null;

            if ($cacheable) {
                $this->cache = self::getCache($this->expiration)
                    ->isImmutable($this->immutable)
                    ->burst($this->maxBurst);
        
                if ($this->cache->expired($this->type) === false) {
                    return $returnable 
                        ? $this->cache->get($this->type) 
                        : $this->cache->read($this->type);
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
                    $returnable
                );
            }

            return $this->thirdPartyTemplate(
                $options,
                $engine,
                $status,
                $returnable
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
            Header::sendNoCacheHeaders(404);
            self::__throw(
                new ViewNotFoundException(sprintf(
                    'Template "%s" could not be found in the view directory "%s".', 
                    $this->template . self::getTemplateEngine()[1], 
                    Luminova::toDisplayPath($this->pathname)
                )), 
                $async ? 6 : 4
            );
        } 

        /**
         * Indicates that a template view file is being loaded by the view rendering engine.
         *
         * This constant allows a template view file to prevent direct access outside
         * the rendering engine.
         *
         * @var bool
         */
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
        $tpl = function(object $self, ?array $options, string $_VIEW_TYPE, string $_VIEW_FILEPATH): mixed {
            Runtime::set(Runtime::TEMPLATE_CONTEXT, $self->__id());

            /** 
             * @var \Luminova\Template\View $this None isolation mode.
             * @var \Luminova\Template\Engines\Scope<\Luminova\Template\View> $self Isolation mode.
             */
            Header::setOutputHandler(true, false);
            $returned = include $_VIEW_FILEPATH;
            $isValidSignature = false;

            try{
                $isValidSignature = $self->__is(Runtime::get(Runtime::TEMPLATE_CONTEXT));
            } catch(Throwable){
                $isValidSignature = false;
            } finally {
                Runtime::remove(Runtime::TEMPLATE_CONTEXT, false);
            }

            if(!$isValidSignature){
                throw new RuntimeException(sprintf(
                    'Template "%s" attempted to override "$self". ' 
                    . 'The "$self" variable is reserved and cannot be changed',
                    Luminova::toDisplayPath($_VIEW_FILEPATH)
                ));
            }

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

        self::extractOptions($options);
        
        if(!self::$config->templateIsolation){
            return $tpl->bindTo($this, null)(
                new NoScope(),
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
     * 
     * @return string Return the converted string output.
     */
    private function toOutput(mixed $contents): string
    {
        if ($contents === '' || $contents === null) {
            return '';
        }

        if (is_scalar($contents)) {
            return (string) $contents;
        }

        if ($contents instanceof \SimpleXMLElement) {
            $this->headers['Content-Type'] ??= 'application/xml';

            return (string) $contents->asXML();
        }

        if ($contents instanceof \DOMDocument) {
            $this->headers['Content-Type'] ??= 'application/xml';

            return (string) $contents->saveXML();
        }

        if ($contents instanceof \Stringable) {
            return (string) $contents;
        }

        if (is_callable($contents)) {
            return (string) $this->toOutput($contents());
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
            $this->headers['Content-Type'] ??= 'application/json';

            try {
                return (string) json_encode(
                    $contents,
                    JSON_THROW_ON_ERROR
                        | JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                );
            } catch (Throwable $e) {
                unset($this->headers['Content-Type']);

                throw new RuntimeException(
                    sprintf('Failed to encode output to JSON: %s', $e->getMessage()),
                    previous: $e
                );
            }
        }

        unset($this->headers['Content-Type']);

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
     * response headers, and caching of the rendered content using a `ViewCache` instance if provided.
     *
     * @param mixed $contents The final rendered content.
     * @param int $status The HTTP status code.
     * @param bool $returnable If true, return the content as a string instead of outputting.
     *
     * @return string|bool Returns the content as a string 
     *      if `$returnable` is true, or `true` on successful rendering.
     */
    private function onCompleteRendering(
        mixed $contents,
        int $status,
        bool $returnable = false
    ): string|bool
    {
        $this->headers['X-System-Default-Headers'] = true;

        Header::clearOutputBuffers('all');
        $isNoContent = ($status === 204 || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD');

        if(!$returnable && (empty($contents) || $isNoContent)){
            Header::setOutputHandler(true);
            Header::send($this->headers, status: $isNoContent ? 204 : $status);
            return true;
        }
  
        [$_contents, $cacheable] = $this->minifier(
            $this->toOutput($contents)
        );

        $isEmptyContent = empty($_contents);

        // if(!PRODUCTION && $contents){
        //    self::__catchInlineErrors($contents);
        // }

        if(!$returnable){
            Header::setOutputHandler(true);
            Header::send($this->headers, status: $isEmptyContent ? 204 : $status);

            if($isEmptyContent){
                return true;
            }
            
            echo $_contents;
        }
        
        if(!$isEmptyContent && $cacheable){
            $this->writeCache($_contents);
        }

        return $returnable ? $_contents : true;
    }

    /**
     * Render with twig or smarty engine.
     * 
     * @param array $options View options.
     * @param string $engine The third-party template engine.
     * @param int $status Http status code.
     * @param bool $returnable Should template contents return instead or rendering.
     * 
     * @return string|bool Return true on success, false on failure.
     */
    private function thirdPartyTemplate(
        array $options,
        string $engine,
        int $status,
        bool $returnable = false
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

        $isNoContent = ($status === 204 || strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD');
        $this->headers['X-System-Default-Headers'] = true;

        if(!$returnable && ($isNoContent || empty($contents))){
            Header::setOutputHandler(true);
            Header::send(
                $this->headers, 
                status: $isNoContent ? 204 : $status
            );
            return true;
        }

        $this->headers['Content-Type'] ??= 'text/html';

        [$_contents, $cacheable] = $this->minifier($contents);
        $isEmptyContent = empty($_contents);

        if(!$returnable){
            Header::setOutputHandler(true);
            Header::send(
                $this->headers, 
                status: $isEmptyContent ? 204 : $status
            );

            if($isEmptyContent){
                return true;
            }

            echo $_contents;
        }

        if(!$isEmptyContent && $cacheable){
            $this->writeCache($_contents);
        }

        return $returnable ? $_contents : true;
    }

    /**
     * Writes the rendered output to cache storage.
     *
     * This method stores the given content as a cache file when caching is enabled.
     *
     * @param string $contents The content to be cached.
     *
     * @return void
     */
    private function writeCache(string $contents): void 
    {
        if(!$this->cache instanceof ViewCache){
            return;
        }

        Runtime::setExecutionTime(0);

        try{
            ob_start();
            $this->cache->setFile($this->filepath)
                ->saveCache($contents, $this->headers, $this->type);
            ob_end_clean(); 
        }catch(Throwable $e){
            Logger::alert(sprintf(
                'Failed to cache template: %s (%s). Reason: %s',
                $this->basename,
                $this->type,
                $e->getMessage()
            ));
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
            $key = str_replace('-', '_', $name);
            $key = str_starts_with($key, '_') ? $key : "_{$key}";

            self::assertOptionName($key);
            self::$options[$key] = $value;
        }

        $options = null;
    }

    /**
     * Resolves the full file path of a given view template and sets internal properties.
     *
     * If the specified template does not exist in production mode, 
     * it falls back to a default `404` template.
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
     * @return array{string,bool} Return array of contents and headers
     */
    private function minifier(string|bool $content): array 
    {
        $cacheable = false;
        $headers = null;

        if (!self::isEmpty($content)) {
            if (
                $this->minification['minifiable'] 
                && in_array($this->type, [self::HTML, self::XHTML], true)
            ) {
                $minify = (new Minifier(preserveHtmlTags: $this->minification['preserve']))
                    ->codeBlockButtons($this->minification['buttons'] ?? [])
                    ->minify($content, $this->type);

                $content = $minify->getContent();
                $headers = $minify->getHeaders();
            }else{
                $headers = ['Content-Type' => Mime::findType($this->type)];
            }

            $cacheable = ($content !== '');
        }

        $this->headers += $headers ?? self::getContentHeaders();

        return [$content, $cacheable];
    }

    /**
     * Retrieves specific HTTP.
     * 
     * `Content-Type`, 
     * `Content-Encoding`  
     * `Content-Length` headers from sent headers.
     * 
     * @return array Return n associative array containing 'Content-Type', 
     *              'Content-Length', and 'Content-Encoding' headers.
     */
    private static function getContentHeaders(): array
    {
        $headers = headers_list();
        $info = [];

        foreach ($headers as $header) {
            $header = trim($header);

            if (!str_starts_with($header, 'Content-')) {
                continue;
            }
            
            [$name, $value] = explode(':', $header, 2);
            $key = trim($name);

            if ($key === 'Content-Type' || $key === 'Content-Encoding') {
                $info[$key] = trim($value);
            } elseif($key === 'Content-Length') {
                $info[$key] = (int) trim($value);
            }
        }

        return $info;
    }
    
    /** 
     * Check if view should be optimized page caching or not.
     *
     * @return bool Return true if view should be cached, otherwise false.
     * > Keep the validation order its important.
     */
    private function isCacheable(): bool
    {
        if ($this->forceCacheEnable) {
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
            $tz = Env::get('app.timezone') ?: null;
            return $this->expiration < new DateTimeImmutable(
                'now', 
                new DateTimeZone($tz)
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
     * 
     * @codeCoverageIgnore
     */
    public final function __log(string $property) 
    {
        [$file, $line] = Tracer::trace(2);

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
     * @param Throwable $e The exception to manage.
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

        RuntimeException::handleException($e->getMessage(), $e->getCode(), $e);
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
     * @return never
     */
    private static function __error(Throwable $error, array $options = [], ?int $status = null): never 
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
            $description = "{$status} " . HttpStatus::phrase($status);

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
     * @return never
     * @throws Throwable<ExceptionInterface> Always throw an exception.
     */
    private static function __throw(ExceptionInterface $e, int $trace, bool $render = false): never 
    {
        [$file, $line] = Tracer::trace($trace + 1);

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
     * @throws RuntimeException If the key "self" is present in the options 
     *      while templateIsolation is enabled.
     */
    private static function assertSelf(array $options): void 
    {
        if (self::$config->templateIsolation && array_key_exists('self', $options)) {
            self::__throw(
                new RuntimeException(
                    'The template option key "self" is reserved. 
                    Enable variable prefixing to use it.'
                ), 
                5
            );
        }
    }

    /**
     * Validates that a view option key is a proper PHP variable name.
     *
     * @param string $key The option key to validate.
     *
     * @return void
     * @throws RuntimeException If the key is invalid or already used.
     */
    private static function assertOptionName(string $key): void 
    {
        if ($key === '') {
            self::__throw(new RuntimeException('Template option key cannot be an empty string.'), 5);
        }

        if (array_key_exists($key, self::$exports)) {
            self::__throw(
                new RuntimeException(sprintf(
                   'Duplicate template option key "%s". Already defined in object exports. ' . 
                   'Use a unique name or a prefix.',
                    $key
                )), 
                5
            );
        }

        //if (!preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $key)) {
        if (!preg_match('/^[\p{L}_][\p{L}\p{N}_]*$/u', $key)) {
            self::__throw(
                new RuntimeException(sprintf(
                    'Invalid template option key "%s". Must start with a Unicode letter or underscore and contain only Unicode letters, digits, or underscores.',
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
    //    if (!Env::get('debug.display.errors', false) 
    //      || !Env::get('debug.catch.inline.errors', false)) {
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
    //            Luminova::toDisplayPath($matches['file']),
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
            self::__throw(new RuntimeException(
                'Template "$options" expects an associative array, list array given.'
            ), 4);
        }

        $prefix = self::$config->variablePrefixing ? '_' : '';

        foreach (self::RESERVED_OPTIONS['plain'] as $name => $_) {
            if (array_key_exists($name, $options) || array_key_exists($prefix . $name, $options)) {
                self::__throw(new RuntimeException(sprintf(
                    'Immutable option "%s" is read-only and cannot be modified.',
                    $name
                )), 4);
                break;
            }
        }

        $options['href']    = self::relativePath(depth: $this->uriDepth);
        $options['asset']   = $options['href'] . 'assets/';
        $options['active']  = $this->filename;
        $options['tplType'] = $this->type;
        $options['noCache'] = (bool) ($options['noCache'] ?? false);
        
        if($options['noCache']){
            $this->cacheConfig['ignore'][] = $this->template;
        }

        if(!isset($options['title'])){
            $options['title'] = $this->title();
        }

        if(!isset($options['subtitle'])){
            $options['subtitle'] = $this->title('');
        }

        return $options;
    }

    /**
     * Get the application view directory.
     *
     * @return string The view directory for the current application or HMVC module.
     */
    private function getTemplatePath(): string
    {
        $this->module ??= self::resolveModule($this->controller);

        $path = Runtime::isHmvc()
            ? '/app/Modules/' . ($this->module !== '' ? $this->module . '/' : '') . 'Views/'
            : self::$folder . '/';

        return Luminova::root($path . $this->subfolder);
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
        return match ($engine) {
            'twig' => Twig::getInstance(self::$config, Luminova::appRoot(), $filepath, [
                'caching'           => false,
                'cache'             => false, 
                'charset'           => Env::get('app.charset', 'utf-8'),
                'strict_variables'  => !PRODUCTION,
                'auto_reload'       => !PRODUCTION,
                'debug'             => !PRODUCTION,
                'autoescape'        => 'html',
            ]),
            'smarty' => Smarty::getInstance(self::$config, Luminova::appRoot(), [
                'caching'           => false,
                'compile_check'     => !PRODUCTION,
                'debugging'         => !PRODUCTION,
                'escape_html'       => true,
            ]),
            default => throw new RuntimeException(sprintf(
                "Template engine '%s' is not supported. Use 'default' (PHP), 'twig' or 'smarty'.",
                $engine
            )),
        };
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
        return (new ViewCache())
            ->setExpiry($expiry)
            ->setDirectory(Luminova::root(
                '/writeable/caches/templates/'
            ))
            ->setKey(Kernel::getCacheId())
            ->setUri(Router::getUriPath());
    }
}