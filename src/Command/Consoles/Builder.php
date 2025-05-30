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
namespace Luminova\Command\Consoles;

use \Luminova\Base\BaseConsole;
use \Luminova\Composer\Builder as AppBuilder;

class Builder extends BaseConsole 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'builder';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Builder';

    /**
     * {@inheritdoc}
     */
    protected string|array $usages = [
        "php novakit build:project --help"
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->perse($options);
        $type = $this->term->getAnyOption('type', 't');

        if($type === false){ 
            foreach ($this->usages as $line) {
                $this->term->writeln($line);
            }

            return STATUS_ERROR;
        }

        if($type === 'build'){
            AppBuilder::export('builds');
        }elseif($type === 'zip'){
            AppBuilder::archive('project.zip');
        }

        return STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function help(array $helps): int
    {
        return STATUS_ERROR;
    }
}