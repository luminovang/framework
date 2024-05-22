<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command;

use \Luminova\Application\Foundation;
use \Luminova\Command\Terminal;
use \Luminova\Command\Executor;

final class Console 
{
    /**
     * Static terminal instance
     * 
     * @var Terminal $instance 
    */
    private static ?Terminal $instance = null;

    /**
     * Initialize console instance
     * 
    */
    public function __construct(){ }

    /**
     * Get novakit static CLI instance 
     * 
     * @return Terminal
    */
    public static function getTerminal(): Terminal
    {
        if(static::$instance === null){
            static::$instance = new Terminal();
        }
        return static::$instance;
    }

    /**
     * Run CLI.
     * 
     * @param array $commands commands to execute
     * 
     * @return void
    */
    public function run(array $commands): void
    {
        $terminal = static::getTerminal();
        $commands = $terminal::parseCommands($commands);

        $terminal::explain($commands);
        $command = $terminal::getCommand();

        if('--version' === $command){
            $terminal::header();
            $terminal::writeln('Novakit Command Line Tool');
            $terminal::writeln('version: ' . Foundation::NOVAKIT_VERSION, 'green');

            exit(STATUS_SUCCESS);
        }

        $params = array_merge($terminal::getArguments(), $terminal::getQueries());
        
        $result = Executor::call($terminal, $params);

        exit($result);
    }
}