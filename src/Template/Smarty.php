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

use \Smarty\Smarty as SmartyTemplate;
use \App\Controllers\Config\Template as TemplateConfig;
use \App\Controllers\Config\Templates\Smarty\Classes;
use \App\Controllers\Config\Templates\Smarty\Modifiers;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Template\Helper;
use \Exception;
use \SmartyException;

class Smarty 
{
    /**
     * @var SmartyTemplate $smarty
    */
    public ?SmartyTemplate $smarty = null;

    /**
     * @var self $instance static instance 
    */
    public static ?self $instance = null;

    /**
     * @var string $root framework root directory
    */
    public static string $root = '';

    /**
      * Minification options.
      * @var array $minifyOptions
    */
    public array $minifyOptions = [];

    /**
     * Minification flag 
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
     * Initializes the Smarty
     * 
     * @param string $root framework root directory
     * 
     * @throws RuntimeException
    */
    public function __construct(string $root, array $configs = [])
    {
        static::$root = $root;

        if(class_exists(SmartyTemplate::class)){
            $this->smarty = new SmartyTemplate();
            static::makedirs();
        }else{
            throw new RuntimeException('Smarty is not available, run composer command "composer require smarty/smarty" if you want to use smarty template', 1991);
        }

        $sufix = DIRECTORY_SEPARATOR . 'smarty';

        $this->smarty->setCompileDir($root . Helper::bothtrim(TemplateConfig::$compileFolder) . $sufix);
        $this->smarty->setConfigDir($root . Helper::bothtrim(TemplateConfig::$configFolder) . $sufix);
        $this->smarty->setCacheDir($root . Helper::bothtrim(TemplateConfig::$cacheFolder) . $sufix);
        $this->smarty->addExtension(new Modifiers());

        if(PRODUCTION){
            $this->smarty->setCompileCheck(SmartyTemplate::COMPILECHECK_OFF);
        }
    }

    /**
     * Get smarty singleton instance
     * 
     * @param string $root framework root directory
     * @param array $configs Filesystem loader configuration.
     * 
     * @return static static instance 
     * @throws RuntimeException
    */
    public static function getInstance(string $root, array $configs = []): static
    {
        if(static::$instance === null){
            static::$instance = new static($root, $configs);
        }

        return static::$instance;
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
        $getClasses = (new Classes())->getClass();

        if($getClasses !== []){
            $classes = array_merge($classes, $getClasses);
        }

        foreach ($classes as $aliases => $class) {
            $this->smarty->registerObject($aliases, $class);
        }

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
            //ACHING_LIFETIME_SAVED
            $this->smarty->setCaching(SmartyTemplate::CACHING_LIFETIME_CURRENT);
            $this->smarty->setCacheLifetime($expiry);
            return;
        }

        $this->smarty->setCaching(SmartyTemplate::CACHING_OFF);
    }

    /**
     * Determin if template was checked.
     * 
     * @param string $view Template view name.
     * 
     * @return bool Return true if cached false otherwise.
    */
    public function isCached(string $view): bool
    {
        return $this->smarty->isCached($view, Helper::cachekey());
    }

    /**
     * Minify template ouput
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
     * @return void 
     * @throws RuntimeException
    */
    public function display(string $view): void
    {
        $cache_id = Helper::cachekey();
        try{
            if($this->minify){
                //http_response_code(200);
                //ob_start(BaseConfig::getEnv('script.ob.handler', null, 'nullable'));
    
                $content = $this->smarty->fetch($view, $cache_id);
                $minifierInstance = Helper::getMinifier(
                    $content, 
                    $this->viewType, 
                    $this->minifyOptions['codeblock'], 
                    $this->minifyOptions['copyable']
                );
                $content = $minifierInstance->getMinified();
                exit($content);
            }

            $this->smarty->display($view, $cache_id);
        }catch(Exception | SmartyException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create smarty directories if they don't exist
     * 
     * @return void
     */
    private static function makedirs(): void 
    {
        $dirs = [
            TemplateConfig::$compileFolder, 
            TemplateConfig::$configFolder, 
            TemplateConfig::$cacheFolder
        ];

        $notFounds = array_filter($dirs, function ($dir) {
            return !file_exists(static::$root .  Helper::bothtrim($dir) . DIRECTORY_SEPARATOR . 'smarty'); 
        });

        foreach ($notFounds as $dir) {
            make_dir(static::$root . $dir);
        }
    }
}