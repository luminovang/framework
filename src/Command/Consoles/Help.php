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
use \Luminova\Command\Consoles\Commands;

class Help extends Console 
{
    /**
     * {@inheritdoc}
     */
    protected string $group = 'help';

    /**
     * {@inheritdoc}
     */
    protected string $name = 'Helps';

    /**
     * {@inheritdoc}
     */
    protected array|string $usages = [
        'php novakit --help'
    ];

    /**
     * {@inheritdoc}
     */
    public function run(?array $options = []): int
    {
        $this->term->perse($options);

        $command = $this->term->getArgument(1);
        $all = $this->term->getOption('all', false);
        $helps = $all 
            ? Commands::getCommands() 
            : Commands::get($command ?? 'help');

        if($helps === []){
            return $this->term->oops($command);
        }

        if($all){
            unset($helps['help']);
        }else{
            $helps['examples'] = Commands::getGlobalHelps();
        }

        $this->term->helper($helps, $all);
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