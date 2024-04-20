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

use \Smarty as SmartyTemplate;
use \Exception;
use \SmartyException;
use \Luminova\Exceptions\RuntimeException;

class Smarty 
{
    /**
     * @var SmartyTemplate $smarty
    */
    public ?SmartyTemplate $smarty = null;

    /**
     * @var SmartyTemplate $instance static instance 
    */
    public static $instance = null;

    /**
     * @var string $root framework root directory
    */
    public string $root = '';

    /**
     * Initializes the Smarty
     * 
     * @param string $root framework root directory
     * 
     * @throws RuntimeException
    */
    public function __construct(string $root)
    {
        $this->root = $root . DIRECTORY_SEPARATOR;
        $this->smarty = static::getSmarty();
    }

    /**
     * Get Smarty singleton instance
     * 
     * @return SmartyTemplate static::$instance static instance 
     * @throws RuntimeException
    */
    public static function getInstance(): SmartyTemplate
    {
        if(static::$instance === null){
            static::$instance = static::getSmarty();
        }

        return static::$instance;
    }

    /**
     * Get smarty instance 
     * 
     * @return SmartyTemplate new instance 
     * @throws RuntimeException
    */
    public static function getSmarty(): SmartyTemplate
    {
        if(class_exists(SmartyTemplate::class)){
            return new SmartyTemplate();
        }
       
        throw new RuntimeException('Smarty is not available, run composer command "composer require smarty/smarty" if you want to use smarty template', 1991);
    }

    /**
     * Initialize smarty template directories
     * 
     * @param string $templateFolder smarty template directory
     * @param string $compileFolder smarty template complied directory
     * @param string $configFolder smarty template config directory
     * @param string $cacheFolder smarty template cache directory
     * 
     * @return self $this Luminova smarty class instance
    */
    public function setDirectories(string $templateFolder, string $compileFolder, string $configFolder, string $cacheFolder): self 
    {
        $this->makeDir([$compileFolder, $configFolder, $cacheFolder]);
        $this->smarty->setTemplateDir($templateFolder);
        $this->smarty->setCompileDir($this->root . $compileFolder);
        $this->smarty->setConfigDir($this->root . $configFolder);
        $this->smarty->setCacheDir($this->root . $cacheFolder);

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
        foreach($options as $k => $v){
            $this->smarty->assign($k, $v, $nocache);
        }
    }

    /**
     * Initialize smarty template directories
     * 
     * @param bool $cache allow caching template
     * @param int $mode Caching modes
     * 
     * @return void 
    */
    public function caching(bool $cache, int $mode = SmartyTemplate::CACHING_LIFETIME_CURRENT): void 
    {
        if($cache){
            $this->smarty->caching = $mode;
            return;
        }
        $this->smarty->caching = SmartyTemplate::CACHING_OFF;
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
     * @param string $template   the resource handle of the template file or template object
     * @param mixed  $cache_id   cache id to be used with this template
     * @param mixed  $compile_id compile id to be used with this template
     * @param object $parent     next higher level of Smarty variables
     *
     * @return void 
     * @throws RuntimeException
     */
    public function display(?string $template = null, mixed $cache_id = null, mixed $compile_id = null, ?object $parent = null): void
    {
        try{
            $this->smarty->display($template, $cache_id, $compile_id, $parent);
        }catch(Exception | SmartyException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create smarty directories if they don't exist
     *
     * @param array $dirs Directories
     * 
     * @return void
     */
    private function makeDir(array $dirs): void 
    {
        $notFounds = array_filter($dirs, function ($dir) {
            return !file_exists($this->root . $dir); 
        });

        foreach ($notFounds as $dir) {
            make_dir($this->root . $dir);
        }
    }
}