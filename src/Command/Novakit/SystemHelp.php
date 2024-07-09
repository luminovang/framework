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
use \Luminova\Command\Novakit\Commands;

class SystemHelp extends BaseConsole 
{
    /**
     * {@inheritdoc}
    */
    protected string $group = 'Help';

    /**
     * {@inheritdoc}
    */
    protected string $name = 'help';

    /**
     * {@inheritdoc}
    */
    protected array $options = [];

    /**
     * {@inheritdoc}
    */
    public function run(?array $options = []): int
    {
        $this->explain($options);

        $command = $this->getArgument(1);
        $all = $this->getOption('all', false);
        $helps = $all ? Commands::getCommands() :  Commands::get($command ?? 'help');

        if($helps === []){
            return $this->oops($command);
        }

        if($all){
            unset($helps['help']);
        }

        $this->helper($helps, $all);
    
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