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
use \Luminova\Command\Novakit\AvailableCommands;

class Help extends BaseCommand 
{
    /**
     * @var string $group command group
    */
    protected string $group = 'Help';

    /**
     * @var string $name command name
    */
    protected string $name = 'help';

    /**
     * Options
     *
     * @var array<string, string> $options
    */
    protected array $options = [];

    /**
     * @param array $options command options
     * 
     * @return int 
    */
    public function run(?array $options = []): int
    {

        $this->printHelp(AvailableCommands::get('help'));
    
        return STATUS_SUCCESS;
    }
}