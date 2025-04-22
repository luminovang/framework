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
namespace Luminova\Command;

use \Luminova\Base\BaseConsole;
use \Luminova\Command\Terminal;
use \Luminova\Application\Foundation;
use \Luminova\Command\Novakit\Server;
use \Luminova\Command\Novakit\SystemHelp;
use \Luminova\Command\Novakit\Lists;
use \Luminova\Command\Novakit\Database;
use \Luminova\Command\Novakit\Generators;
use \Luminova\Command\Novakit\System;
use \Luminova\Command\Novakit\Builder;
use \Luminova\Command\Novakit\Context;
use \Luminova\Command\Novakit\Commands;
use \Luminova\Command\Novakit\CronJobs;
use \Luminova\Command\Novakit\Logs;
use \Luminova\Command\Novakit\ClearWritable;

final class Console 
{
    /**
     * Static terminal instance.
     * 
     * @var Terminal $instance 
     */
    private static ?Terminal $instance = null;

    /**
     * Initialize novakit console instance.
     */
    public function __construct()
    { 
        if(!self::$instance instanceof Terminal){
            self::$instance = new Terminal();
        }
    }

    /**
     * Run novakit command.
     * 
     * @param array<string,mixed> $commands command arguments to execute.
     * 
     * @return void
     */
    public function run(array $commands): void
    {
        $commands = self::$instance::parseCommands($commands);
        self::$instance::explain($commands);
        $command = self::$instance::getCommand();

        if(!$command){
            self::$instance::header();
            exit(STATUS_ERROR);
        }

        if('--version' === $command || '--v' === $command){
            self::$instance::writeln('Novakit Command Line Tool');
            self::$instance::writeln(sprintf('Framework Version: %s', Foundation::VERSION), 'green');
            self::$instance::writeln(sprintf('Novakit Version: %s', Foundation::NOVAKIT_VERSION), 'green');

            exit(STATUS_SUCCESS);
        }

        $result = self::execute(self::$instance, array_merge(
            self::$instance::getArguments(), 
            self::$instance::getQueries()
        ));

        exit($result);
    }

    /**
     * Execute novakit command.
     * 
     * @param Terminal $terminal novakit cli instance.
     * @param array $options Command options.
     * 
     * @return int Return status code.
     */
    public static function execute(Terminal $terminal, array $options): int
    {
        $command = trim($terminal::getCommand() ?? '');
        $newCommand = self::find($command);

        if ($newCommand === '') {
            $terminal::oops($command);
            if(($suggest = Commands::suggest($command)) !== ''){
                $terminal->fwrite($suggest, Terminal::STD_ERR);
            }
            return STATUS_ERROR;
        } 

        /**
         * @var BaseConsole $newCommand
         */
        $newCommand = new $newCommand();

        if($terminal::isHelp($options['options'])){
            $info = self::getCommand($command);

            $terminal::header();

            if($newCommand->help($info) === STATUS_ERROR){
                $terminal->helper($info);
            }

            return STATUS_SUCCESS;
        }

        return (int) $newCommand->run($options);
    }

    /**
     * Maps a given command string to its corresponding handler class.
     *
     * @param string $command The command string provided (e.g., 'create:controller', 'db:migrate').
     * 
     * @return class-string<BaseCommand> The fully qualified class name of the handler for the command, or an 
     *                empty string if no matching class is found.
     */
    public static function find(string $command): string 
    {
        $pos = strpos($command, ':');
        return match(($pos !== false) ? substr($command, 0, $pos) : $command){
            '-h', '--help' => SystemHelp::class,
            'create', => Generators::class,
            'list' => Lists::class,
            'db', => Database::class,
            'server', 'serve' => Server::class,
            'generate', 'env' => System::class,
            'build' => Builder::class,
            'context' => Context::class,
            'log' => Logs::class,
            'clear' => ClearWritable::class,
            'cron' => CronJobs::class,
            default => ''
        };
    }

    /**
     * Get command information.
     * 
     * @param string $command command name.
     * 
     * @return array<string,mixed> Return array of command information.
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
     * @return bool Return true if command exist, false otherwise.
     */
    public static function has(string $command): bool
    {
        return Commands::has($command) || Terminal::isHelp($command);
    }

    /**
     * Get command info if exist.
     * 
     * @param string $command command name.
     * @param string $key command key to retrieve.
     * 
     * @return mixed Return information details by its key if command exist.
     */
    public static function get(string $command, string $key): mixed
    {
        return self::getCommand($command)[$key] ?? null;
    }
}