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
use \Luminova\Command\Novakit\AvailableCommands;
use \Closure;

class Commands
{
    /**
     * Run console command
     * 
     * @param Terminal $cli novakit cli instance
     * @param array $options Command options
     * 
     * @return int
    */
    public static function run(Terminal $cli, array $options): int
    {
        $command = trim($cli->getCommand());
        // Define a command mapping
        $runCommand = match($command){
            'help', '-help', '--help' => (new Help)->run($options),
            'create:controller','create:view','create:class' => (new Generators)->run($options),
            'list' => (new Lists)->run($options),
            'db:create','db:update','db:insert','db:delete','db:drop','db:truncate','db:select' => (new Database)->run($options),
            'server', 'serve' => (new Server)->run($options),
            default => function() use($command): int {
                Terminal::error('Unknown command ' . Terminal::color("'$command'", 'red') . ' not found', null);

                return STATUS_ERROR;
            }
        };

        if ($runCommand instanceof Closure) {
            return (int) $runCommand();
        } 
            
        return (int) $runCommand;
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
        return AvailableCommands::get($command);
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
        return AvailableCommands::has($command) || $command === '-help';
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