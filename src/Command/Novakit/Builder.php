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

use \Luminova\Base\BaseCommand;
use \Luminova\Composer\Builder as AppBuilder;

class Builder extends BaseCommand 
{
    /**
     * @var string $group command group
    */
    protected string $group = 'System';

    /**
     * @var string $name command name
    */
    protected string $name = 'build:project';

    /**
     * @var string|array $usage command usages
    */
    protected string|array $usages = [
        "Usage: php novakit build:project --type <build/zip>",
        "  - Build and package the application for production.",
        "  - Example: php novakit build:project --type build",
        "  - Archive the application into a zip file.",
        "  - Example: php novakit build:project --type zip",
    ];

    /**
     * Options
     *
     * @var array<string, string> $options
    */
    protected array $options = [
        '--type'  => 'Specify type of build',
    ];

    /**
     * @param array $options command options
     * 
     * @return int 
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $type = $this->getOption('type');

        if(empty($type)){ 
            foreach ($this->usages as $line) {
                $this->writeln($line);
            }

            return STATUS_ERROR;
        }

        if($type === 'build'){
            AppBuilder::export('builds');
        }elseif($type === 'zip'){
            AppBuilder::archive('my_project.zip');
        }

        return STATUS_SUCCESS;
    }
}