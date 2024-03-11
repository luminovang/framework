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

use \Luminova\Command\Terminal;
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
     * @var array<string, string>
    */
    protected array $options = [
        '--php'  => 'The PHP Binary [default: "PHP_BINARY"]',
        '--host' => 'The HTTP Host [default: "localhost"]',
        '--port' => 'The HTTP Host Port [default: "8080"]',
    ];

    /**
     * @param array $params terminal options
     * 
     * @return int 
    */
    public function run(?array $params = []): int
    {

        Terminal::printHelp(AvailableCommands::get('help'));
    
        return STATUS_OK;
    }
}