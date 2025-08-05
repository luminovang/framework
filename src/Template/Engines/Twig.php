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

use \Twig\Environment;
use \Twig\TwigFunction;
use \Twig\Loader\FilesystemLoader;
use \Luminova\Exceptions\RuntimeException;
use \App\Config\Template as TemplateConfig;
use \Luminova\Template\Extensions\TwigExtension;
use \Luminova\Exceptions\BadMethodCallException;

final class Twig 
{
    /**
     * @var Environment $twig
     */
    private ?Environment $twig = null;

    /**
     * Static instance.
     * 
     * @var self $instance
     */
    private static ?self $instance = null;

    /**
     * Initializes the Twig
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param string $filepath Template view directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @throws RuntimeException
     */
    public function __construct(
        TemplateConfig $config, 
        string $root, 
        string $filepath, 
        array $options = []
    )
    {
        if(!class_exists(Environment::class)){
            throw new RuntimeException(
                'Twig is not available, run composer command "composer require twig/twig:^3.0" 
                if you want to use Twig template'
            );
        }

        $options['cache'] = $options['caching'] 
            ? $root . '/writeable/caches/templates/twig' 
            : false;

        $this->twig = new Environment(new FilesystemLoader($filepath), $options);
        $this->twig->addExtension(new TwigExtension());
        $this->twig->registerUndefinedFunctionCallback(static function ($name) {
            if (is_callable($name)) {
                return new TwigFunction($name, $name);
            }

            $callable = "\\Luminova\\Funcs\\{$name}";
            if (is_callable($callable)) {
               return new TwigFunction($name, $callable);
            }

            throw new BadMethodCallException(
                sprintf('Call to undefined function %s()', $name)
            );
        });
    }

    /**
     * Get Twig singleton instance
     * 
     * @param TemplateConfig $config Template configuration.
     * @param string $root framework root directory.
     * @param string $filepath Template view directory.
     * @param array $options Filesystem loader configuration.
     * 
     * @return static static instance 
     * @throws RuntimeException
    */
    public static function getInstance(
        TemplateConfig $config, 
        string $root, 
        string $filepath, 
        array $options = []
    ): static
    {
        if(!self::$instance instanceof self){
            self::$instance = new self($config, $root, $filepath, $options);
        }

        return self::$instance;
    }

    /**
     * Initialize twig template directories
     * 
     * @param bool $cache allow caching template
     * @param int $expiry Cache expiration ttl.
     * 
     * @return void 
     */
    public function caching(bool $cache, int $expiry): void 
    {}

    /**
     * Initialize Twig template directories
     *
     * @param string $filepath smarty template directory
     * 
     * @return self $this Luminova Twig class instance
     */
    public function setPath(string $filepath): self 
    {
        $this->twig->setLoader(new FilesystemLoader($filepath));

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
            if($aliases === 'self'){
                continue;
            }

            $this->twig->addGlobal($aliases, $class);
        }

        return $this;
    }

    /**
     * Render twig template.
     * 
     * @param string $view The template view file.
     * @param Proxy|null $proxy Options to be passed.
     * 
     * @return string|null
     */
    public function display(string $view, ?Proxy $proxy = null): ?string
    {
        return $this->twig->load($view)
            ->render([
                'self' => $proxy
            ]) ?: null;
    }
}