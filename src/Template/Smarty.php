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

use \Luminova\Luminova;
use \Smarty\Smarty as SmartyTemplate;
use \App\Config\Template as TemplateConfig;
use \App\Config\Templates\Smarty\Classes;
use \App\Config\Templates\Smarty\Modifiers;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Optimization\Minification;
use \Exception;
use \SmartyException;
use function \Luminova\Funcs\make_dir;

class Smarty 
{
    /**
     * @var SmartyTemplate $smarty
     */
    private ?SmartyTemplate $smarty = null;

    /**
     * @var TemplateConfig $config
     */
    private static TemplateConfig $config;

    /**
     * Page minification instance.
     * 
     * @var ?Minification $min
     */
    private static ?Minification $min = null;

    /**
     * @var self $instance static instance 
     */
    private static ?self $instance = null;

    /**
     * framework root directory.
     * 
     * @var string $root
     */
    private static string $root = '';

    /**
      * Minification options.

      * @var array $minifyOptions
     */
    private array $minifyOptions = [];

    /**
     * Minification flag.
     * 
     * @var bool $minify 
     */
    private bool $minify = false;

    /**
     * view type 
     * 
     * @var string $view
     */
    private string $viewType = 'html';

    /**
     * @var array<string,mixed> $headers
     */
    private array $headers = [];


    /**
     * Initializes the Smarty
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @throws RuntimeException
    */
    public function __construct(TemplateConfig $config, string $root, array $options = [])
    {
        self::$root = $root;
        self::$config = $config;

        if(class_exists(SmartyTemplate::class)){
            $this->smarty = new SmartyTemplate();
            self::makeDirs();
        }else{
            throw new RuntimeException('Smarty is not available, run composer command "composer require smarty/smarty" if you want to use smarty template', 1991);
        }

        $suffix = DIRECTORY_SEPARATOR . 'smarty';

        $this->smarty->setCompileDir($root . Luminova::bothTrim(self::$config->compileFolder) . $suffix);
        $this->smarty->setConfigDir($root . Luminova::bothTrim(self::$config->configFolder) . $suffix);
        $this->smarty->setCacheDir($root . Luminova::bothTrim(self::$config->cacheFolder) . $suffix);
        $this->smarty->addExtension(new Modifiers());

        if(PRODUCTION){
            $this->smarty->setCompileCheck(SmartyTemplate::COMPILECHECK_OFF);
        }
    }

    /**
     * Get smarty singleton instance
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @return static static instance 
     * @throws RuntimeException
    */
    public static function getInstance(TemplateConfig $config, string $root, array $options = []): static
    {
        if(self::$instance === null){
            self::$instance = new self($config, $root, $options);
        }

        return self::$instance;
    }

    /**
     * Get smarty instance 
     * 
     * @return SmartyTemplate new instance 
     * @throws RuntimeException
    */
    public function getClient(): SmartyTemplate
    {
        return $this->smarty;
    }

    /**
     * Initialize smarty template directories
     * 
     * @param string $viewPath smarty template directory
     * 
     * @return self $this Luminova smarty class instance
    */
    public function setPath(string $viewPath): self 
    {
        $this->smarty->setTemplateDir($viewPath);
       
        return $this;
    }

    /**
     * Initialize smarty template directories
     * 
     * @param array $options assign options to smarty
     * @param bool $nocache if true any output of this variable will be not cached
     * 
     * @return void 
    */
    public function assignOptions(array $options, bool $nocache = false): void 
    {
        $this->viewType = ($options['viewType'] ?? 'html');

        foreach($options as $k => $v){
            $this->smarty->assign($k, $v, $nocache);
        }
    }

    /**
     * Get smarty environment instance.
     * 
     * @param array<string, mixed> $classes Protected and Public classes registered in application controller.
     * 
     * @return self Luminova smarty class instance.
    */
    public function assignClasses(array $classes = []): self
    {
        $instance = new Classes();

        if(($_classes = $instance->registerObjects()) !== []){
            $classes = array_merge($classes, $_classes);
        }

        foreach ($classes as $aliases => $class) {
            if(is_string($class)){
                $this->smarty->registerClass($aliases, $class);
            }else{
                $this->smarty->registerObject($aliases, $class);
            }
        }

        if(($objects = $instance->registerClasses()) !== []){
            foreach ($objects as $aliases => $classString) {
                $this->smarty->registerClass($aliases, $classString);
            }
        }

        $instance = null;
        return $this;
    }

    /**
     * Initialize smarty template directories
     * 
     * @param bool $cache allow caching template
     * @param int $expiry Cache expiration ttl.
     * 
     * @return void 
    */
    public function caching(bool $cache, int $expiry): void 
    {
        if($cache){
            $this->smarty->setCaching(SmartyTemplate::CACHING_LIFETIME_CURRENT);
            $this->smarty->setCacheLifetime($expiry);
            return;
        }

        $this->smarty->setCaching(SmartyTemplate::CACHING_OFF);
    }

    /**
     * Determine if template was checked.
     * 
     * @param string $view Template view name.
     * 
     * @return bool Return true if cached false otherwise.
    */
    public function isCached(string $view): bool
    {
        return $this->smarty->isCached($view, Luminova::getCacheId());
    }

    /**
     * Minify template output
     * 
     * @param bool $minify Should the template be minified.
     * @param array $options Minification options.
     * 
     * @return self Luminova smarty template instance.
    */
    public function minify(bool $minify, array $options): self 
    {
        $this->minify = $minify;
        $this->minifyOptions = $options;

        return $this;
    }

    /**
     * Set response header.
     *
     * @param array<string,mixed> $headers The headers key-pair.
     * 
     * @return self Luminova smarty template instance.
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Test install
     *
     * @param array $errors — array to push results into rather than outputting them
     * 
     * @return void
    */
    public function testInstall(&$errors = null): void
    {
        $this->smarty->testInstall($errors);
    }

     /**
     * Check template for modifications?
     *
     * @param int $mode compile check mode
     * 
     * @return void
    */
    public function compileCheck(int $mode): void
    {
        $this->smarty->compile_check = $mode;
    }

    /**
     * displays a Smarty template
     *
     * @param string $view   the resource handle of the template file or template object
     *
     * @return bool|string 
     * @throws RuntimeException
    */
    public function display(string $view, bool $return = false): bool|string
    {
        try{
            $content = $this->smarty->fetch($view, Luminova::getCacheId());

            if($this->minify){
                self::$min ??= new Minification();
                self::$min->codeblocks($this->minifyOptions['codeblock']);
                self::$min->copyable($this->minifyOptions['copyable']);
                $content = self::$min->compress($content, $this->viewType)->getContent();
            }

            if($return){
                return $content;
            }

            echo $content;

            return true;
        }catch(Exception | SmartyException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create smarty directories if they don't exist
     * 
     * @return void
     */
    private static function makeDirs(): void 
    {
        $directories = [
            self::$config->compileFolder,
            self::$config->configFolder,
            self::$config->cacheFolder
        ];

        foreach ($directories as $dir) {
            $path = self::$root . Luminova::bothTrim($dir) . DIRECTORY_SEPARATOR . 'smarty';
            
            if (!file_exists($path)) {
                make_dir(self::$root . $dir);
            }
        }
    }
}