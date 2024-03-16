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

use \Luminova\Base\BaseConfig;
use \Luminova\Command\Terminal;
use \Luminova\Command\Commands;

class Console 
{
    /**
     * Static terminal instance
     * @var Terminal $instance 
    */
    private static $instance = null;

    /**
     * Is header suppressed?
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
        if(self::$instance === null){
            self::$instance = new Terminal();
        }
        return self::$instance;
    }

    /**
     * Run CLI
     * @param array $commands commands to execute
     * 
     * @return void
    */
    public function run(array $commands): void
    {
        $cli = static::getTerminal();
        $commands = $cli::parseCommands($commands);
        $cli::registerCommands($commands, false);
        $command = $cli::getCommand();
        if (!$this->noHeader) {
            $cli::header(BaseConfig::$version);
        }

        if('--version' === $command){
            $cli::writeln('Novakit Command Line Tool');
            $cli::writeln('version: ' . BaseConfig::$version, 'green');
            exit(STATUS_SUCCESS);
        }

        $params  = array_merge($cli::getArguments(), $cli::getQueries());
        
        $result = Commands::run($cli, $params);

        exit($result);
    }
}
