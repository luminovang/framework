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

class Help extends BaseConsole 
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
        $helps = [];
        $all = false;
        if(empty($command) || $command === null){
            $all = true;
            $helps = Commands::getCommands();
        }else{
            $helps = Commands::get($command);
        }

        if($helps === []){
            return $this->oops($command);
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