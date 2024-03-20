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

use \Luminova\Command\Terminal;
use \Luminova\Command\Executor;

class Console 
{
    /**
     * Static terminal instance
     * 
     * @var Terminal $instance 
    */
    private static ?Terminal $instance = null;

    /**
     * Is header suppressed?
     * 
     * @var bool $noHeader 
    */
    private bool $noHeader = false;

    /**
     * Initialize console instance
     * 
     * @param bool $noHeader Suppress header if no header is detected
    */
    public function __construct(bool $noHeader)
    {
        $this->noHeader = $noHeader;
    }

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
     * Run CLI
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
     
        if (!$this->noHeader) {
            $terminal::header($terminal::$version);
        }

        if('--version' === $command){
            $terminal::writeln('Novakit Command Line Tool');
            $terminal::writeln('version: ' . $terminal::$version, 'green');

            exit(STATUS_SUCCESS);
        }

        $params  = array_merge($terminal::getArguments(), $terminal::getQueries());
        
        $result = Executor::call($terminal, $params);

        exit($result);
    }
}
