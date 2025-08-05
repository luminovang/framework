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
namespace Luminova\Template\Engines;

use \Closure;
use \Luminova\Luminova;
use \Smarty\Extension\Base;
use \Smarty\Smarty as SmartyTemplate;
use function \Luminova\Funcs\make_dir;
use \Luminova\Exceptions\RuntimeException;
use \App\Config\Template as TemplateConfig;
use \Luminova\Template\Extensions\SmartyFunction;
use \Luminova\Template\Extensions\SmartyExtension;

final class Smarty 
{
    /**
     * Smarty template object. 
     * 
     * @var SmartyTemplate $smarty
     */
    private ?SmartyTemplate $smarty = null;

    /**
     * @var self $instance static instance 
     */
    private static ?self $instance = null;

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
        if(!class_exists(SmartyTemplate::class)){
            throw new RuntimeException(
                'Smarty is not available, run composer command "composer require smarty/smarty:^5.7" 
                if you want to use smarty template'
            );
        }

        self::makeDirs($root, [
            $config->compileFolder,
            $config->configFolder
        ]);

        SmartyTemplate::$_CHARSET = strtoupper(env('app.charset', 'UTF-8'));
        $this->smarty = new SmartyTemplate();

        $this->smarty->setCompileCheck(
            ($options['compile_check'] ?? false)
                ? SmartyTemplate::COMPILECHECK_ON
                : SmartyTemplate::COMPILECHECK_OFF
        );
        $this->smarty->setDebugging($options['debugging'] ?? false);
        $this->smarty->setCaching(SmartyTemplate::CACHING_OFF);
        $this->smarty->setEscapeHtml($options['escape_html'] ?? true);
        $this->smarty->setLeftDelimiter('{{');
        $this->smarty->setRightDelimiter('}}');
        $this->smarty->setAutoLiteral(false);
        $this->smarty->error_unassigned = true;
        $this->smarty->setCompileDir($root . self::bothTrim($config->compileFolder) . 'smarty');
        $this->smarty->setConfigDir($root . self::bothTrim($config->configFolder) . 'smarty');
        $this->smarty->addExtension(new class extends Base {
            public function getModifierCallback(string $name): ?callable 
            {
                return SmartyExtension::resolveUndefinedFunctionCallback($name);
            }

            // public function getBlockHandler(string $blockTagName): ?BlockHandlerInterface
            // {
            //     return SmartyBlockTags::has($blockTagName) 
            //        ? new SmartyBlockTags($blockTagName) 
            //         : null;
            // }

            // public function getFunctionHandler(string $name): ?FunctionHandlerInterface 
            // {
            //     return SmartyExtension::resolveFunctionHandler($name);
            // }
        });
    }

    /**
     * Get smarty singleton instance
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @return static Return a static instance.
     * @throws RuntimeException
     */
    public static function getInstance(TemplateConfig $config, string $root, array $options = []): static
    {
        if(!self::$instance instanceof self){
            self::$instance = new self($config, $root, $options);
        }

        return self::$instance;
    }

    /** 
     * Trim file directory both sides.
     *
     * @param string $path The path to trim.
     *
     * @return string Return trimmed path (e.g, `foo/bar/`).
     * @internal
     */
    private static function bothTrim(string $path): string 
    {
        return trim($path, TRIM_DS) . DIRECTORY_SEPARATOR;
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
     * @param string $filepath smarty template directory
     * 
     * @return self Luminova smarty class instance
     */
    public function setPath(string $filepath): self 
    {
        $this->smarty->setTemplateDir($filepath);
       
        return $this;
    }

    /**
     * Initialize smarty template directories
     * 
     * @param Proxy|null $options assign options to smarty
     * @param bool $nocache if true any output of this variable will be not cached
     * 
     * @return void 
     */
    public function setProxy(?Proxy $options = null, bool $nocache = false): void 
    {
        $this->registerSmartyExtensions();
        $this->smarty->assign('self', $options, $nocache);
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
        foreach ($classes as $aliases => $class) {

            if(is_string($class) && class_exists($class)){
                $this->smarty->registerClass($aliases, $class);
                continue;
            }

            if(($class instanceof Closure) || Luminova::isCallable($class, true)){
                $this->smarty->registerPlugin('function', $aliases, $class);
                continue;
            }

            if($aliases === 'self'){
                continue;
            }

            $this->smarty->registerObject($aliases, $class);
        }

        return $this;
    }

    /**
     * Get smarty environment instance.
     * 
     * @return self Luminova smarty class instance.
     */
    private function registerSmartyExtensions(): self
    {
        foreach (SmartyExtension::getPlugins('extensions') as $aliases => $class) {
    
            if(is_string($class) && is_callable($class)){
                $this->smarty->registerPlugin('modifier', $aliases, $class);
                continue;
            }

            if(($class instanceof Closure) || Luminova::isCallable($class)){
                $this->smarty->registerPlugin('function', $aliases, $class);
                continue;
            }

            if(!$class instanceof SmartyFunction){
                if($aliases === 'self'){
                    continue;
                }

                $this->smarty->registerObject($aliases, $class);
                continue;
            }

            if($class->isPlugin()){
                $this->smarty->registerPlugin(
                    $class->getType(),
                    $class->getName(), 
                    $class->is(SmartyFunction::FUNCTION) 
                        ? [$class, 'resolver']
                        : $class->getHandler(),
                    $class->isCacheable(), 
                );
                continue;
            }

            if($class->getName() === 'self'){
                continue;
            }

            $this->smarty->registerObject(
                $class->getName(), 
                $class->isObject() ? $class->getHandler() : $class,
                $class->getAllowedMethodsProperties(),
                true,
                $class->getBlockMethods()
            );
        }

        foreach (SmartyExtension::getPlugins('globals') as $var => $value) {
            if($aliases === 'self'){
                continue;
            }

            $this->smarty->assign($var, $value);
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
        $this->smarty->setCompileCheck($mode);
    }

    /**
     * displays a Smarty template
     *
     * @param string $view the resource handle of the template file or template object
     *
     * @return string|null
     * @throws RuntimeException
     */
    public function display(string $view, ?Proxy $proxy = null): ?string
    {
        return $this->smarty->fetch($view, Luminova::getCacheId()) ?: null;
    }

    /**
     * Create smarty directories if they don't exist
     * 
     * @return void
     */
    private static function makeDirs(string $root, array $directories): void 
    {
        foreach ($directories as $dir) {
            $path = $root . self::bothTrim($dir) . 'smarty';
            
            if (!file_exists($path)) {
                make_dir($root . $dir);
            }
        }
    }
}