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
use \Luminova\Command\Novakit\Server;
use \Luminova\Command\Novakit\Help;
use \Luminova\Command\Novakit\Lists;
use \Luminova\Command\Novakit\Database;
use \Luminova\Command\Novakit\Generators;
use \Luminova\Command\Novakit\System;
use \Luminova\Command\Novakit\Builder;
use \Luminova\Command\Novakit\Commands;

class Executor
{
    /**
     * Run console command
     * 
     * @param Terminal $terminal novakit cli instance
     * @param array $options Command options
     * 
     * @return int
    */
    public static function call(Terminal $terminal, array $options): int
    {
        $command = trim($terminal->getCommand());
   
        $findGroup = match($command){
            'help', '-help', '--help' => Help::class,
            'create:controller','create:view','create:class' => Generators::class,
            'list' => Lists::class,
            'db:create','db:update','db:insert','db:delete','db:drop','db:truncate','db:select' => Database::class,
            'server', 'serve' => Server::class,
            'generate:key' => System::class,
            'build:project' => Builder::class,
            default => null
        };

        if ($findGroup === null) {
            $terminal::error('Unknown command ' . $terminal::color("'$command'", 'red') . ' not found', null);

            return STATUS_ERROR;
        } 
        
        $execute = new $findGroup();
        
        return (int) $execute->run($options);
    }

    /**
     * Get command information
     * 
     * @param string $command command name 
     * 
     * @return array
    */
    public static function getCommand(string $command): array
    {
        return Commands::get($command);
    }

    /**
     * Check if command exist
     * 
     * @param string $command command name 
     * 
     * @return bool
    */
    public static function has(string $command): bool
    {
        return Commands::has($command) || $command === '-help';
    }

    /**
     * Get command info if exist
     * 
     * @param string $command command name 
     * @param string $key command key to retrieve 
     * 
     * @return mixed
    */
    public static function get(string $command, string $key): mixed
    {
        $isCommand = self::getCommand($command);

        return isset($isCommand[$key]) ? $isCommand[$key] : null;
    }
}