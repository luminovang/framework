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

use Luminova\Exceptions\RuntimeException;

class Layout
{
    /** 
     * @var array<string, string> $sections
    */
    protected array $sections = [];
    
    /** 
     * @var string|null $current
    */
    protected ?string $current = null;
    
    /** 
     * @var bool $nesting
    */
    protected bool $nesting = false;
    
    /** 
     * @var bool $process
    */
    protected bool $process = true;
    
    /** 
     * @var string $file
    */
    protected static string $file = '';

    /** 
     * @var self|null $instance
    */
    private static ?self $instance = null;

    /**
     * Get the singleton instance of Layout.
     * 
     * @return static Return the instance of Layout class.
     */
    public static function getInstance(): static 
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    /**
     * Import a layout file file or section into another layout file.
     *
     * @param string $layout File name without extension (.php)
     * @example import(foo) or import(foo/bar).
     * 
     * @return static Return the instance of Layout class.
     */
    protected static function import(string $layout): static 
    {
        return (new static())->layout($layout);;
    }

    /**
     * Set the layout file name to extend.
     *
     * @param string $layout File name without extension (.php).
     * @example import(foo) or import(foo/bar)
     * 
     * @return self Return the instance of Layout class.
     * @throws RuntimeException Throws when layout file is not found.
     */
    public function layout(string $layout): self
    {
        static::$file = root('/resources/views/layouts/') . trim($layout, '/') . '.php';

        if (!file_exists(static::$file)) {
            throw new RuntimeException('Layout not found: ' . filter_paths(static::$file));
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
        require_once static::$file;
        ob_end_clean();
    }

    /**
     * Begin a new layout section.
     *
     * @param string $name Section name to begin.
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
     * @return void
     * @throws RuntimeException Throws when no section to end.
     */
    protected function end(?string $name = null): void 
    {
        if (!$this->process) {
            return;
        }
        
        $name = $name ?? $this->current;

        if ($name === null) {
            throw new RuntimeException('No active section to end');
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
     * @example extend('foo') or nested extend extend('foo.bar').
     * 
     * @return string Return the extended or inherited layout contents.
     */
    public function extend(?string $section = null): string
    {
        if ($section === null) {
            return $this->get();;
        }

        $this->include($section);

        if (isset($this->sections[$section])) {
            return $this->sections[$section];
        }

        return '';
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
        require_once static::$file;
        $content = ob_get_clean();

        if($content === false){
            return '';
        }

        return $content;
    }
}