<?php 
/**
 * Luminova Framework Sitemap Generator
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command\Consoles;

use \Luminova\Base\Console;
use \Luminova\Component\Seo\Sitemap;

class Sitemaps extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'sitemap';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Sitemap';

    /**
     * {@inheritdoc}
     */
    protected string|array $usages  = [
        'php novakit sitemap --help',
        'php novakit sitemap',
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->perse($options);
        $command = trim($this->term->getCommand());

        if ($command !== 'sitemap') {
            return $this->term->oops($command);
        } 

        return $this->__generate();
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }

    /**
     * Generates sitemap 
     * 
     * @return int Status code 
     */
    private function __generate(): int 
    {
        $config = new \App\Config\Sitemap();
        $config->maxScan = $this->term->getAnyOption('limit', 'l', $config->maxScan);
        $config->scanSpeed = $this->term->getAnyOption('delay', 'd', $config->scanSpeed);
        $config->scanUrlPrefix = $this->term->getAnyOption('prefix', 'p', $config->scanUrlPrefix);
        $config->changeFrequently = $this->term->getAnyOption('change', 'c', $config->changeFrequently);
        $config->maxExecutionTime = $this->term->getAnyOption('max-execution', 'e', $config->maxExecutionTime);
        $config->includeStaticHtml = (bool) $this->term->getAnyOption('html', 's', $config->includeStaticHtml);

        $url = $this->term->getAnyOption('url', 'u', null);
        $basename = $this->term->getAnyOption('basename', 'f', 'sitemap.xml');

        $options = [
            'isBroken' => (bool) $this->term->getAnyOption('broken', 'b', false), 
            'isLinkTree' => (bool) $this->term->getAnyOption('link-tree', 't', false), 
            'treeFormat' => $this->term->getOption('format', null) ?: null, 
            'verbose' => $this->term->getVerbose(default: 3),
            'isDryRun' => (bool) $this->term->getAnyOption('dry-run', 'n', false),
            'ignoreAssets' => (bool) $this->term->getAnyOption('ignore-asset', 'a', true)
        ];

        if(Sitemap::generate($url, $this->term, $basename, $config, $options)){
            return STATUS_SUCCESS;
        }

        $this->term->beeps();
        $this->term->newLine();
        $this->term->error('Sitemap creation failed');
    
        return STATUS_ERROR;
    }
}