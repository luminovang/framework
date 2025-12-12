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
use \Luminova\Command\Terminal;
use \Luminova\Components\Seo\Sitemap;

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
        $name = trim($this->input->getName());

        if ($name !== 'sitemap') {
            return Terminal::oops($name);
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
        $config->maxScan = $this->input->getAnyOption('limit', 'l', $config->maxScan);
        $config->scanSpeed = $this->input->getAnyOption('delay', 'd', $config->scanSpeed);
        $config->scanUrlPrefix = $this->input->getAnyOption('prefix', 'p', $config->scanUrlPrefix);
        $config->changeFrequently = $this->input->getAnyOption('change', 'c', $config->changeFrequently);
        $config->maxExecutionTime = $this->input->getAnyOption('max-execution', 'e', $config->maxExecutionTime);
        $config->includeStaticHtml = (bool) $this->input->getAnyOption('html', 's', $config->includeStaticHtml);
        $config->linkTreeDescriptionSelector = $this->input->getAnyOption('desc-xpath', 'dx', $config->linkTreeDescriptionSelector);

        $url = $this->input->getAnyOption('url', 'u', null);
        $basename = $this->input->getAnyOption('basename', 'f', 'sitemap.xml');

        $options = [
            'mode' => match(true) {
                $this->input->hasOption('broken', 'b') =>
                    Sitemap::GENERATE_BROKEN_LINKS,
                $this->input->hasOption('link-tree', 't') => 
                    Sitemap::GENERATE_LINK_TREE,
                default => Sitemap::GENERATE_SITEMAP
            },
            'treeTemplate' => $this->input->getOption('format', null) ?: null, 
            'verbose' => $this->input->getVerbose(default: 3),
            'isDryRun' => $this->input->hasOption('dry-run', 'n'),
            'ignoreAssets' => $this->input->hasOption('ignore-asset', 'a')
        ];

        if(Sitemap::generate($url, $basename, $config, $options)){
            return STATUS_SUCCESS;
        }

        Terminal::beeps();
        Terminal::newLine();
        Terminal::error('Sitemap creation failed');
    
        return STATUS_ERROR;
    }
}