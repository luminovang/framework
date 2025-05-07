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
use \Luminova\Command\Consoles\Commands;

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
        $helps = $all ? Commands::getCommands() :  Commands::get($command ?? 'help');

        if($helps === []){
            return $this->term->oops($command);
        }

        if($all){
            unset($helps['help']);
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