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
use \Twig\Loader\FilesystemLoader;
use \Twig\Environment;
use \Twig\Error\RuntimeError;
use \Twig\Error\SyntaxError;
use \App\Config\Template as TemplateConfig;
use \App\Config\Templates\Twig\Extensions;
use \Luminova\Optimization\Minification;
use \Luminova\Exceptions\RuntimeException;

class Twig 
{
    /**
     * @var Environment $twig
     */
    private ?Environment $twig = null;

    /**
     * Page minification instance.
     * 
     * @var ?Minification $min
     */
    private static ?Minification $min = null;

    /**
     * Static instance.
     * 
     * @var self $instance
     */
    private static ?self $instance = null;

    /**
     * Framework root directory
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
     * Minification flag 
     * 
     * @var bool $minify 
     */
    private bool $minify = false;

    /**
     * @var array<string,mixed> $headers
     */
    private array $headers = [];

    /**
     * Initializes the Twig
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param string $viewPath Template view directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @throws RuntimeException
     */
    public function __construct(
        TemplateConfig $config, 
        string $root, 
        string $viewPath, 
        array $options = []
    )
    {
        self::$root = $root;

        if($options['caching']){
            $suffix = DIRECTORY_SEPARATOR . 'twig';
            $options['cache'] = $root . Luminova::bothTrim($config->cacheFolder) . $suffix;
        }else{
            $options['cache'] = false;
        }

        if(class_exists(Environment::class)){
            $this->twig = new Environment(new FilesystemLoader($viewPath), $options);
            $this->twig->addExtension(new Extensions());
        }else{
            throw new RuntimeException('Twig is not available, run composer command "composer require "twig/twig:^3.0" if you want to use Twig template', 1991);
        }
    }

    /**
     * Get Twig singleton instance
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param string $viewPath Template view directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @return static static instance 
     * @throws RuntimeException
    */
    public static function getInstance(TemplateConfig $config, string $root, string $viewPath, array $options = []): static
    {
        if(self::$instance === null){
            self::$instance = new self($config, $root, $viewPath, $options);
        }

        return self::$instance;
    }

     /**
     * Initialize Twig template directories
     *
     * @param string $viewPath smarty template directory
     * 
     * @return self $this Luminova Twig class instance
    */
    public function setPath(string $viewPath): self 
    {
        $this->twig->setLoader(new FilesystemLoader($viewPath));

        return $this;
    }

    /**
     * Get twig environment instance.
     * 
     * @return Environment Twig environment instance.
    */
    public function getClient(): Environment
    {
        return $this->twig;
    }

    /**
     * Get twig environment instance.
     * 
     * @param array<string, mixed> $classes Protected and Public classes registered in application controller.
     * 
     * @return Environment Twig environment instance.
    */
    public function assignClasses(array $classes = []): self
    {
        foreach ($classes as $aliases => $class) {
            $this->twig->addGlobal($aliases, $class);
        }

        return $this;
    }

    /**
     * Minify template output
     * 
     * @param bool $minify Should the template be minified.
     * @param array $options Minification options.
     * 
     * @return self Luminova twig template instance.
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
     * @return self Luminova twig template instance.
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Render twig template.
     * 
     * @param string $view The template view file.
     * @param array<mixed,mixed> $options Options to be passed.
     * 
     * @return bool|string 
    */
    public function display(string $view, array $options = [], bool $return = false): bool|string
    {
        try{
            $template = $this->twig->load($view);
            $content = $template->render($options);
            
            if($this->minify){
                self::$min ??= new Minification();
                self::$min->codeblocks($this->minifyOptions['codeblock']);
                self::$min->copyable($this->minifyOptions['copyable']);
                $content = self::$min->compress($content, $options['viewType'])->getContent();
            }

            if($return){
                return $content;
            }

            echo $content;

            return true;
        }catch(RuntimeError|SyntaxError $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}