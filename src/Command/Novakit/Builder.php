<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Command\Novakit;

use \Luminova\Base\BaseConsole;
use \Luminova\Composer\Builder as AppBuilder;

class Builder extends BaseConsole 
{
    /**
     * {@inheritdoc}
    */
    protected string $group = 'System';

    /**
     * {@inheritdoc}
    */
    protected string $name = 'build:project';

    /**
    * {@inheritdoc}
    */
    protected string|array $usages = [
        "Usage: php novakit build:project --type <build/zip>",
        "  - Build and package the application for production.",
        "  - Example: php novakit build:project --type build",
        "  - Archive the application into a zip file.",
        "  - Example: php novakit build:project --type zip",
    ];

    /**
     * {@inheritdoc}
    */
    protected array $options = [
        '--type'  => 'Specify type of build',
    ];

    /**
     * {@inheritdoc}
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $type = $this->getOption('type');

        if($type === false){ 
            foreach ($this->usages as $line) {
                $this->writeln($line);
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