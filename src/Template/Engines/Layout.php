<?php 
declare(strict_types=1);
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template\Engines;

use \Throwable;
use \Stringable;
use \Luminova\Http\Header;
use \Luminova\Template\View;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\BadMethodCallException;
use function \Luminova\Funcs\{root, filter_paths};

/**
 * Layout
 *
 * Responsible for layout composition: sections, nesting, template selection and rendering.
 * 
 * @property self $layout Instance layout object, to allow access to protected methods.
 * @property \Luminova\Foundation\Core\Application $app Instance of Application class object (e.g, $this->app->appMethod()).
 * 
 * @mixin \Luminova\Template\View Instance of view class object (e.g, $this->getOptions()).
 * @mixin \Luminova\Foundation\Core\Application Instance of Application class object (e.g, $this->app->appMethod()).
 */
final class Layout implements Stringable
{
    /** 
     * Captured section contents keyed by section name.
     * 
     * @var array<string,string> $sections
     * 
     */
    private array $sections = [];

    /**
     * Stack of active section names for proper nesting. 
     * 
     *  @var string[] $keys
     * 
     */
    private array $keys = [];

    /** 
     * Whether the layout is in "processing" mode (template include should collect sections).
     * 
     * @var bool $process
     */
    private bool $process = true;

    /** 
     * HMVC feature enabled flag (resolved from env on construct).
     * 
     * @var bool|null $isHmvc
     */
    private ?bool $isHmvc = null;

    /** 
     * Path to the selected layout file (absolute).
     * 
     * @var string $file
     */
    private string $file = '';

    /** 
     * Base folder (absolute) where templates are located.
     * 
     * @var string $base
     */
    private string $base = '';

    /**
     * Root views folder (absolute).
     * 
     * @var string $root
     */
    private string $root = '';

    /** 
     * Whether the template has been rendered and sections collected.
     * 
     * @var bool $rendered
     */
    private bool $rendered = false;

    /** 
     * Current working section.
     * 
     * @var string|null $current
     */
    private ?string $current = null;

    /** 
     * Full resolved contents.
     * 
     * @var string|null $contents
     */
    private ?string $contents = null;

    /** 
     * The last resolved file.
     * 
     * @var string|null $last
     */
    private ?string $last = null;

    /**
     * When true, only sections are collected; top-level output is discarded.
     * 
     * @var bool $collector
     */
    private bool $collector = true;

    /** 
     * Shared instance of layout.
     * 
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Create a new Layout instance.
     *
     * @param string|null $base Optional base folder under the views root (e.g. 'layouts').
     * @param string|null $module Optional module name (when HMVC is enabled).
     * @param View|null $view Optional View instance; stored by reference (not cloned).
     * @param bool $isolation Whether template isolation is enabled. If `true`, the `$self` variable will hold the view object, otherwise, `$this` is used directly in templates.
     * 
     * @throws RuntimeException When the module view root is not found.
     * @throws RuntimeException When the base folder does not exist.
     *
     * @example - Example:
     * ```php
     * $layout = new Layout('layouts', view: $view);
     * 
     * $layout->template('/scaffolding');
     * echo $layout->extend('section');
     * ```
     */
    public function __construct(
        ?string $base = null, 
        ?string $module = null, 
        private ?View $view = null,
        private bool $isolation = false
    )
    {
        $this->root = root('/resources/Views/');
        $this->isHmvc ??= (bool) env('feature.app.hmvc', false);

        if ($module !== null) {
            $this->module($module);
        }

        if($base){
            $this->base($base);
        }

        $view = null;
    }

    /**
     * Singleton method that returns a shared Layout instance.
     *
     * @param string|null $base Optional base folder under the views root.
     * @param string|null $module Optional module name when HMVC is enabled.
     * @param View|null $view Optional View instance.
     * @param bool $isolation Whether template isolation is enabled. If `true`, the `$self` variable will hold the view object; otherwise, `$this` is used directly in templates.
     *
     * @return self Returns a shared Layout instance configured for the provided base/module/view.
     * @throws RuntimeException When the module view root is not found.
     * @throws RuntimeException When the base folder does not exist.
     * 
     * @example - Example:
     * ```php
     * $layout = Layout::of('layouts', 'Blog', $view);
     * 
     * echo $layout->template('/scaffolding')->extend('section');
     * ```
     */
    public static function of(
        ?string $base = null, 
        ?string $module = null, 
        ?View $view = null,
        bool $isolation = false
    ): self
    {
        if(!self::$instance instanceof self){
            self::$instance = new self($base, $module, $view, $isolation);
        }

        return self::$instance;
    }

    /**
     * Import a layout file quickly (convenience wrapper).
     *
     * This creates a new instance (module may be provided) and selects the template immediately.
     *
     * @param string $layout Template path relative to base 
     *          (e.g. '/layouts/scaffolding' or 'layouts/scaffolding.php').
     * @param string|null $module Optional module name when HMVC is enabled.
     *
     * @return self Returns a prepared Layout instance with the template selected.
     * @throws RuntimeException When the resolved layout file does not exist.
     *
     * @example - Example:
     * ```php
     * // Immediately prepare the layout and then render a section
     * 
     * echo $this->layout->import('/layouts/card')->extend();
     * 
     * // app/resources/Views/layouts/card.php
     * ```
     * @example - HMVC Example:
     * ```php
     * echo $this->layout->import('/layouts/card', 'Admin')->extend();
     * 
     * // app/Modules/Admin/Views/layouts/card.php
     * ```
     * 
     * > **Note:**
     * > To change base, you must specify call `base()` before calling `extend` or `all`.
     */
    public function import(string $layout, ?string $module = null): self
    {
        return (new self(module: $module, view: $this->view, isolation: $this->isolation))
            ->template($layout);
    }

    /**
     * Select the template file to render.
     *
     * The filepath may end with ".php" or not. The method resolves the absolute file path
     * using the configured root and base. The file must exist.
     *
     * @param string $layout Template path relative to base 
     *              (e.g. '/layouts/scaffolding' or 'layouts/scaffolding.php').
     *
     * @return self Returns this Layout instance.
     * @throws RuntimeException When the resolved layout file does not exist.
     *
     * @example - Example:
     * ```php
     * $this->layout->template('/scaffolding')->extend('head');
     * ```
     * 
     * @example - Custom Example:
     * ```php
     * $layout = new Layout('layouts', null, $view);
     * $layout->template('/scaffolding');
     * echo $layout->extend('head');
     * ```
     */
    public function template(string $layout): self
    {
        $layout = (substr($layout, -4) === '.php') ? substr($layout, 0, -4) : $layout;
        $layout = trim($layout, TRIM_DS);

        $filename = $this->root 
            . $this->base 
            . trim($layout, TRIM_DS) . '.php';

        if (!is_file($filename)) {
            throw new RuntimeException(sprintf(
                'Layout not found: %s', 
                filter_paths($filename)
            ));
        }

        $this->file = $filename;
        $this->rendered = false;
        $this->sections = [];
        $this->keys = [];
        //$this->current = null;

        return $this;
    }

    /**
     * Set the base folder under the views root.
     * 
     * This method defines which directory under your view root should be treated as the layout container.
     *
     * @param string $base Folder name inside the views root (e.g. 'layouts' or 'partials').
     *
     * @return self Returns this Layout instance.
     * @throws RuntimeException When the base folder does not exist.
     *
     * @example - Example:
     * ```php
     * $this->layout->base('layouts');
     * 
     * // MVC → /resources/Views/layouts/
     * // HMVC → /app/Modules/<Module>/Views/layouts/
     * ```
     */
    public function base(string $base): self
    {
        $basepath = $this->root . trim($base, TRIM_DS) . DIRECTORY_SEPARATOR;

        if (!is_dir($basepath)) {
            throw new RuntimeException(sprintf(
                'Layout base: %s not found in : %s',
                $base,
                filter_paths($this->root)
            ));
        }

        $this->base = $basepath;
        return $this;
    }

    /**
     * Configure module-specific views root when HMVC is enabled.
     *
     * This method switches the Layout system to load templates from a specific module.
     * If HMVC is disabled, the method does nothing and simply returns the current instance.
     *
     * @param string $module The HMVC custom module name (e.g, `Admin`, `Post`).
     *
     * @return self Returns this Layout instance.
     * @throws RuntimeException When the module view root is not found.
     *
     * @example - Example:
     * ```php
     * $this->layout->module('Admin')
     *      ->template('/layouts/scaffolding');
     * ```
     */
    public function module(string $module = ''): self
    {
        if (!$this->isHmvc) {
            return $this;
        }

        $module = trim($module);
        View::isModule($module);

        $ctx = ($module === '') ? '' : $module . '/'; 
        $root = root("/app/Modules/{$ctx}Views/");

        if (!is_dir($root)) {
            throw new RuntimeException(sprintf('Layout base module: %s not found: %s', $module, $root));
        }

        $this->root = $root;
        return $this;
    }

    /**
     * Begin capturing a layout section.
     *
     * This marks the start of a named section. The section name is pushed onto
     * the stack, and a new output buffer is started to capture everything printed
     * until `end()` is called.
     *
     * @param string $name The section name (must not be empty).
     *
     * @return void
     * @throws RuntimeException If the section name is empty.
     * 
     * @see self::end() Used to close the section and store its output.
     *
     * @example - In Layout:
     * ```php
     * <?php $this->layout->begin('content'); ?>
     *  <p>Hi</p>
     * <?php $this->layout->end(); ?>
     * ```
     */
    protected function begin(string $name): void
    {
        if (!$this->process) {
            return;
        }

        if(!$name){
            throw new RuntimeException('Section name must not be empty.');
        }

        $this->keys[] = $name;
        //$this->current = end($this->keys) ?: null;
        $this->start();
    }

    /**
     * Close the current layout section and store its buffered output.
     *
     * This method ends the most recently opened section. Sections must close
     * in the same order they were opened. If `$name` is provided, it must match
     * the section currently being closed. This helps catch mistakes in large
     * templates where nested sections can be hard to track.
     *
     * @param string|null $name Expected section name for validation, or null to skip name checks.
     *
     * @return void
     * @throws RuntimeException If there is no active section to close,
     *                          or if `$name` does not match the active section.
     * 
     * @see begin() Use to start a section capturing.
     *
     * @example - In Layout:
     * ```php
     * <?php $this->layout->begin('title'); ?>
     *     <title>My Page</title>
     * <?php $this->layout->end('title'); ?>
     * ```
     */
    protected function end(?string $name = null): void
    {
        if (!$this->process) {
            return;
        }

        if ($this->keys === []) {
            throw new RuntimeException('No active layout section to end');
        }

        $current = array_pop($this->keys);
        //$this->current = end($this->keys) ?: null;

        if ($name !== null && $name !== $current) {
            throw new RuntimeException(sprintf(
                'Mismatched end() call. Expected section "%s", got "%s".', 
                $name, 
                $current
            ));
        }

        $this->collect($current);
    }

    /**
     * Get the content of a specific layout section.
     *
     * This method ensures the template is rendered once and returns the output
     * of the requested section. If the section does not exist, an empty string
     * is returned.
     *
     * @param string $section The name of the section to retrieve.
     * @param array<string,mixed> $options Optional data to pass into the layout scope.
     * @param bool $autoDelimiter Whether to automatically replace all `{{ key }}`
     *                            placeholders within the section output.
     *
     * @return string Returns the content of the requested section, or an empty string if not found.
     * @throws RuntimeException If the template file cannot be loaded or resolved.
     *
     * @example - Example:
     * ```php
     * // Render template and get the 'footer' section
     * echo $this->layout->extend('footer', ['year' => 2025]);
     * ```
     * > **Note:**
     * > The `$options` array is also used to replace any matching `{{ key }}` 
     * > placeholders inside the resolved layout sections. 
     * >
     * > Each key in `$options` is also extracted as a variable within the layout scope.
     */
    public function extend(string $section, array $options = [], bool $autoDelimiter = true): string
    {
        if(!isset($this->sections[$section])){
            $this->compile($options);
        }

        if($autoDelimiter){
            $this->replaceAll(array_merge(
                $options, 
                $this->view?->getOptions() ?? []
            ), $section);
        }

        return $this->sections[$section] ?? '';
    }

    /**
     * Return all captured sections.
     * 
     * @param array<string,mixed> $options Optional data to pass into the layout scope.
     * @param bool $autoDelimiter Whether to automatically replace all `{{ key }}`
     *                            placeholders within the section output.
     *
     * @return array<string,string> Returns an associative array of section name => content.
     * @throws RuntimeException When the template file cannot be loaded.
     * 
     * > **Note:**
     * > The `$options` array is also used to replace any matching `{{ key }}` 
     * > placeholders inside the resolved layout sections. 
     * >
     * > Each key in `$options` is also extracted as a variable within the layout scope.
     */
    public function sections(array $options = [], bool $autoDelimiter = true): array
    {
        if($this->sections === []){
            $this->compile($options); 
        }

        if($autoDelimiter){
            $this->replaceAll(array_merge(
                $options, 
                $this->view?->getOptions() ?? []
            ));
        }

        return $this->sections;
    }

    /**
     * Render the selected layout template and return its full output.
     *
     * This method renders the layout file and returns the final combined output.
     * It does not perform section mapping; it simply executes the template and
     * captures the output from top to bottom. When the object is cast to a string,
     * `__toString()` will call this method.
     *
     * @param array<string,mixed> $options Optional data passed into the template scope.
     * @param bool $autoDelimiter Whether to automatically replace all `{{ key }}`
     *                            placeholders within the section output.
     *
     * @return string Returns the rendered layout output.
     * @throws RuntimeException If the template file cannot be loaded or resolved.
     * 
     * @see self::extend() Use to extract a portion of named section.
     * @see self::resolve() Performs resolve **without** placeholder replacement.
     *
     * @example - Example:
     * ```php
     * // Direct render
     * $this->layout->template('/layouts/scaffolding')->render();
     *
     * // Using import
     * $this->layout->import('/layouts/scaffolding')->render();
     *
     * // String casting
     * echo $this->layout->template('/layouts/scaffolding');
     * echo $this->layout->import('/layouts/scaffolding');
     * ```
     * > **Note:**
     * > The `$options` array is also used to replace any matching `{{ key }}` 
     * > placeholders inside the resolved layout sections. 
     * >
     * > Each key in `$options` is also extracted as a variable within the layout scope.
     */
    public function render(array $options = [], bool $autoDelimiter = true): string
    {
        $this->resolve(options: $options);

        if($autoDelimiter){
            $this->replaceAll(array_merge(
                $options, 
                $this->view?->getOptions() ?? []
            ));
        }

        return $this->contents;
    }

    /**
     * Resolve the layout without performing placeholder replacement.
     *
     * This method renders the layout and populates all sections, but it does **not**
     * apply any `{{ key }}` delimiter replacements. This allows you to manually call
     * `replace()` or `replaceAll()` afterward if you need full control over the
     * substitution process.
     *
     * @param array<string,mixed> $options Optional data passed into the layout scope.
     *
     * @return self Returns the Layout instance.
     * @throws RuntimeException If the template file cannot be loaded or resolved.
     *
     * @see self::render() Performs resolve **with** placeholder replacement.
     */
    public function resolve(array $options = []): self
    {
        $this->collector = false;
        $this->compiler(options: $options);

        return $this;
    }

    /**
     * Check if a named section exists in the layout.
     *
     * This method determines whether the given section has been defined and captured.
     * If the section has not been resolved yet, it attempts to render the layout to populate sections.
     *
     * @param string $section The name of the section to check.
     *
     * @return bool Returns true if the section exists, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($this->layout->exists('footer')) {
     *     echo $this->layout->extend('footer');
     * }
     * ```
     */
    public function exists(string $section): bool
    {
        if (isset($this->sections[$section])) {
            return true;
        }

        try {
            $this->compile();
        } catch (Throwable) {
            return false;
        }

        return isset($this->sections[$section]);
    }

    /**
     * Prepend content to a section.
     *
     * @param string $section Section name to prepend content.
     * @param string $content The content to prepend.
     *
     * @return self Returns the Layout instance.
     * @throws RuntimeException When the template file cannot be loaded.
     */
    public function prepend(string $section, string $content): self
    {
        return $this->concat($section, $content);
    }

    /**
     * Append content to a section.
     *
     * @param string $section Section name to append content.
     * @param string $content The content to append.
     *
     * @return self Returns the Layout instance.
     * @throws RuntimeException When the template file cannot be loaded.
     */
    public function append(string $section, string $content): self
    {
        return $this->concat($section, $content, 'end');
    }

    /**
     * Replace a named placeholder in content with the provided value.
     *
     * Named placeholders are defined with double curly braces, e.g. `{{placeholder}}`.
     * Optional spaces around the placeholder name are allowed (`{{ name }}`).
     *
     * @param string $name Placeholder name without curly braces.
     * @param string $value Replacement content.
     * @param string|null $section Optional specific section to apply the replacement. 
     *          If null, all sections are processed.
     *
     * @return self Returns the Layout instance.
     *
     * @example - Example:
     * ```php
     * // Replace in all sections:
     * $this->replace('data', 'World');
     *
     * // Replace in a specific section:
     * $this->replace('title', 'My Page', 'head');
     * ```
     */
    public function replace(string $name, string $value, ?string $section = null): self
    {
        $name = trim($name, " {}");

        if(!$name || ($this->sections === [] && empty($this->contents))){
            return $this;
        }

        return $this->replacement(
            '/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/',
            $value,
            $section
        );
    }

    /**
     * Replace multiple placeholders in layout sections using an associative array.
     *
     * Each array key represents the placeholder name (without braces), 
     * and the value is the replacement content.
     *
     * - Optionally, a specific `$section` can be targeted.
     *
     * @param array<string,string> $replacements Associative array of placeholder => value.
     * @param string|null $section Optional section to replace in. If null, all sections are processed.
     *
     * @return self Returns the Layout instance.
     *
     * @example - Example:
     * ```php
     * // Replace multiple placeholders
     * $this->replaceAll([
     *     'title' => 'My Page',
     *     'content' => 'Hello World'
     * ]);
     * ```
     */
    public function replaceAll(array $replacements, ?string $section = null): self
    {
        if (!$replacements || ($this->sections === [] && empty($this->contents))) {
            return $this;
        }

        $patterns = [];
        $values = [];
        foreach ($replacements as $name => $value) {
            $name = trim($name, " {}"); 
            if ($name === '') continue;

            $patterns[] = '/\{\{\s*' . preg_quote($name, '/') . '\s*\}\}/';
            $values[] = $value;
        }

        if ($patterns === []) {
            return $this;
        }

        return $this->replacement(
            $patterns,
            $values,
            $section
        );
    }

    /**
     * Set default content for a section if not defined.
     *
     * @param string $section The section name to set default template.
     * @param string $content The default section content.
     *
     * @return self Returns the Layout instance.
     */
    public function setDefault(string $section, string $content): self
    {
        if ($section && (!isset($this->sections[$section]) || $this->sections[$section] === '')) {
            $this->sections[$section] = $content;
        }

        return $this;
    }

    /**
     * Ensure the template has been rendered once and sections are available.
     * 
     * @param array $options An optional template options.
     *
     * @return void
     * @throws RuntimeException When the template file cannot be loaded.
     */
    private function compile(array $options = [], bool $replace = true): void
    {
        if ($this->rendered) {
            return;
        }

        if(
            $this->last === $this->file && 
            (
                (!$this->collector && $this->contents !== null) ||
                ($this->collector && $this->sections !== [])
            )
        ){
            return;
        }

        $this->collector = true;
        $this->rendered = true;
        $this->sections = [];
        $this->keys = [];
        //$this->current = null;

        $this->compiler($options, $replace);
    }

    /**
     * Perform the actual template include and return captured output.
     *
     * When $collector is true the method will only execute the template to allow
     * section collectors (begin/end) to run; it discards the top-level output.
     *
     * This method is careful to restore output buffering levels in case of exceptions.
     *
     * @param array $options An optional template options.
     *
     * @return void
     * @throws RuntimeException When the template file cannot be included.
     */
    private function compiler(array $options = []): void
    {
        if ($this->file === '' || !is_file($this->file)) {
            throw new RuntimeException('Layout file not available for render.');
        }

        $__initialLevel = ob_get_level();
        $this->process = true;

        try {
            ob_start();
            $self = $this->isolation ? $this->view : null;

            extract($options, EXTR_SKIP);
            require $this->file;
            $this->last = $this->file;

            if ($this->collector) {
                Header::clearOutputBuffers('top');
                $this->contents = null;
                return;
            }

            $this->contents = ob_get_clean() ?: '';
        } catch (Throwable $e) {
            Header::clearOutputBuffers('all', $__initialLevel);
            $this->process = false;
            throw $e;
        } finally {
            $this->process = false;
        }
    }

    /**
     * Start capturing output for the current section.
     *
     * This opens a new output buffer so anything echoed between
     * `begin()` and `end()` can be stored instead of sent to the browser.
     * 
     * If section collection is disabled, no buffer is started.
     *
     * @return bool Returns true when a buffer is started, false otherwise.
     */
    private function start(): bool 
    {
        return $this->collector ? ob_start() : false;
    }

    /**
     * Stop capturing output and save it under the given section name.
     *
     * This method closes the active output buffer created by `start()`
     * and stores the captured content into the `$sections` array.
     *
     * If section collection is disabled, nothing is captured.
     *
     * @param string $section The name of the section being collected.
     * 
     * @return bool Returns true when the content is stored successfully,
     *              false when no collection is active.
     */
    private function collect(string $section): bool 
    {
        if ($this->collector) {
            $this->sections[$section] = ob_get_clean() ?: '';
            return true;
        }

        return false;
    }

    /**
     * Perform a regex-based replacement on layout content or sections.
     *
     * @param string|array $pattern Regex pattern(s) to search for.
     * @param string|array $value Replacement string(s) corresponding to the pattern(s).
     * @param string|null $section Optional section name to apply the replacement.
     *
     * @return self Returns the Layout instance .
     */
    private function replacement(
        string|array $pattern, 
        string|array $value, 
        ?string $section = null
    ): self
    {
        if($this->sections === []){
            $this->contents = preg_replace($pattern, $value, (string) $this->contents);
            return $this;
        }

        if($section !== null){
            if (isset($this->sections[$section])) {
                $this->sections[$section] = preg_replace($pattern, $value, $this->sections[$section]);
            }

            return $this;
        }

        foreach ($this->sections as $key => &$content) {
            $content = preg_replace($pattern, $value, $content);
        }

        unset($content);
        return $this;
    }

    /**
     * Concatenate content to a layout section.
     *
     * This method adds content to a named section either at the beginning or the end.
     * It ensures the section exists by resolving the layout if needed.
     *
     * @param string $section The section name to which the content will be added.
     * @param string $content The content to add to the section.
     * @param string $position Position to insert content: 
     *              `'start'` (prepend) or `'end'` (append). Default is `'start'`.
     *
     * @return self Returns the Layout instance.
     * @throws RuntimeException When the template file cannot be loaded.
     *
     * @example - Example:
     * ```php
     * $this->concat('header', '<meta name="robots" content="noindex">', 'start');
     * $this->concat('footer', '<script src="/js/footer.js"></script>', 'end');
     * ```
     */
    protected function concat(
        string $section, 
        string $content, 
        string $position = 'start'
    ): self
    {
        if (!isset($this->sections[$section])) {
            $this->compile(); 

            if (!isset($this->sections[$section])) {
                $this->sections[$section] = '';
            }
        }

        if($position === 'start'){
            $this->sections[$section] = $content . $this->sections[$section];
        }else{
            $this->sections[$section] = $this->sections[$section] . $content;
        }

        return $this;
    }

    /**
     * Magic getter to forward property access to the associated View instance when available.
     *
     * If property is 'layout' returns this instance. If View is not set returns null.
     *
     * @param string $property Property name.
     *
     * @return mixed Returns the requested property from the View or this Layout when property is 'layout'.
     *
     * @example - Allows:
     * ```php
     * echo $this->title;
     * 
     * // Or
     * echo $this->layout->title;
     * ```
     */
    protected function __get(string $property): mixed
    {
        if ($property === 'layout') {
            return $this;
        }

        if (!$this->view instanceof View) {
            return null;
        }

        if ($property === 'app') {
            return $this->view->app;
        }

        $result = $this->view->getProperty($property, true, false);

        if($result === View::KEY_NOT_FOUND){
            return $this->view->__log($property);
        }

        return $result;
    }

    /**
     * Forward unknown instance method calls to the attached View instance.
     *
     * @param string $method Method name.
     * @param array $arguments Call arguments.
     *
     * @return mixed Returns the forwarded method result.
     * @throws BadMethodCallException When View is not attached or method cannot be resolved.
     * @throws Throwable When the underlying call throws.
     *
     * @example - Allows:
     * ```php
     * echo $this->asset('/css/style.css');
     * 
     * // Or
     * echo $this->layout->asset('/css/style.css');
     * ```
     */
    protected function __call(string $method, array $arguments): mixed
    {
        if (!$this->view instanceof View) {
            throw new BadMethodCallException(sprintf('Unable to call "%s": no View instance attached.', $method));
        }

        try{
            return $this->view->__fromExport($method, $arguments, true);
        }catch(Throwable $e){
            if(($e instanceof BadMethodCallException) && method_exists($this->view, $method)){
                return $this->view->{$method}(...$arguments);
            }

            throw $e;
        }
    }

    /**
     * Render the selected template layout and return all the output.
     *
     * @return string Return all the layout output.
     */
    public function __toString(): string 
    {
        return $this->contents ?? $this->render();
    }
}