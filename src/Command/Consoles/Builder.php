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

use \Luminova\Base\Console;
use \Luminova\Command\Terminal;
use \Luminova\Composer\Builder as AppBuilder;

class Builder extends Console 
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
        $type = $this->input->getAnyOption('type', 't');

        if($type === false){ 
            foreach ($this->usages as $line) {
                Terminal::writeln($line);
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