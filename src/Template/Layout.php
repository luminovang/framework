<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template;

use \Luminova\Exceptions\RuntimeException;
use function \Luminova\Funcs\{
    root,
    filter_paths
};

final class Layout
{
    /** 
     * @var array<string,string> $sections
     */
    private array $sections = [];
    
    /** 
     * @var string|null $current
     */
    private ?string $current = null;
    
    /** 
     * @var bool $nesting
     */
    private bool $nesting = false;
    
    /** 
     * @var bool $process
     */
    private bool $process = true;
    
    /** 
     * @var string $file
     */
    private static string $file = '';

    /** 
     * @var string|null $layouts
     */
    private static ?string $layouts = null;

    /** 
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Initialize instance of Layout class.
     */
    public function __construct() {}

    /**
     * Get the singleton instance of Layout.
     * 
     * @return static Return the instance of Layout class.
     */
    public static function getInstance(): static 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Import a layout file file or section into another layout file.
     *
     * @param string $layout File name without extension (e.g, `.php`).
     * @param string $module The HMVC custom module name (e.g, `Blog`, `User`).
     * 
     * @return static Return the instance of Layout class.
     * 
     * @example - Example:
     * 
     * ```php 
     * layout(...)->import('foo');
     * // or 
     * layout(...)->import('foo/bar');
     * ```
     */
    protected static function import(string $layout, string $module = ''): self 
    {
        return (new self())->layout($layout, $module);
    }

    /**
     * Set the layout file name to extend.
     *
     * @param string $layout File name without extension (e.g, `.php`).
     * @param string $module The HMVC custom module name (e.g, `Blog`, `User`).
     * 
     * @return self Return the instance of Layout class.
     * @throws RuntimeException Throws when layout file is not found.
     * 
     * @example - Example:
     * 
     * ```php 
     * layout('main')->import('foo');
     * // or 
     * layout('main')->import('foo/bar');
     * ```
     */
    public function layout(string $layout, string $module = ''): self
    {
        self::$layouts ??= root(env('feature.app.hmvc', false) 
            ? 'app/Modules/'. ($module === '' ? '' : $module . '/'). 'Views/layouts'
            : '/resources/Views/layouts/');
        self::$file = self::$layouts . trim($layout, TRIM_DS) . '.php';

        if (!file_exists(self::$file)) {
            throw new RuntimeException('Layout not found: ' . filter_paths(self::$file));
        }

        return $this;
    }

    /**
     * Include the layout file,
     * 
     * @return void
     */
    private function include(): void
    {
        $this->process = true;
        ob_start();
        require_once self::$file;
        
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Begin a new layout section.
     *
     * @param string $name Section name to begin.
     * 
     * @return void
     */
    protected function begin(string $name): void
    {
        if (!$this->process) {
            return;
        }

        if ($this->current !== null) {
            $this->nesting = true;
            $name = $this->current . '.' . $name;
        }
        
        ob_start();
        $this->current = $name;
    }

    /**
     * End the current layout section.
     *
     * @param string|null $name Optional section name to end.
     * 
     * @return void
     * @throws RuntimeException Throws when no section to end.
     */
    protected function end(?string $name = null): void 
    {
        if (!$this->process) {
            return;
        }

        $name ??= $this->current;

        if ($name === null) {
            throw new RuntimeException('No active layout section to end');
        }

        $content = ob_get_clean();

        if ($this->nesting) {
            $this->sections[$this->current] = $content;
            $this->current = substr($name, 0, strrpos($name, '.'));
        } else {
            $this->sections[$name] = $content;
            $this->current = null;
        }

        $this->nesting = false;
    }

    /**
     * Process and extend a section of layout or get all layout sections by passing null.
     *
     * @param string|null $section Section name to extend or pass null to load all sections.
     * 
     * @return string Return the extended or inherited layout contents.
     * 
     * @example - Example:
     * ```php 
     * layout(...)->extend('foo');
     * // or nested extend  
     * layout(...)->import('foo.bar');
     * ```
     */
    public function extend(?string $section = null): string
    {
        if ($section === null) {
            return $this->get();
        }

        $this->include();
        return $this->sections[$section] ?? '';
    }

    /**
     * Get the layout sections contents without processing.
     *
     * @return string Return the inherited layout contents.
     */
    public function get(): string
    {
        $this->process = false;
        ob_start();
        require_once self::$file;
        $content = ob_get_clean();

        return ($content === false) ? '' : $content;
    }
}