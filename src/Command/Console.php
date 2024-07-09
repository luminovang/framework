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
        if(self::$instance === null){
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
        $command = trim($terminal::getCommand());
        /**
         * @var class-string<BaseConsole> $newCommand
        */
        $newCommand = match($command){
            '-h', '--help' => SystemHelp::class,
            'create:controller','create:view','create:class', 'create:model', => Generators::class,
            'list' => Lists::class,
            'db:drop','db:truncate','db:seed','db:migrate', 'db:alter', 'db:clear' => Database::class,
            'server', 'serve' => Server::class,
            'generate:key','generate:sitemap','env:add','env:remove' => System::class,
            'build:project' => Builder::class,
            'context' => Context::class,
            'cron:create', 'cron:run' => CronJobs::class,
            default => ''
        };

        if ($newCommand === '') {
            return $terminal::oops($command);
        } 

        if($terminal::isHelp($options['options'])){
            $info = self::getCommand($command);
            
            if(!array_key_exists('no-header', $options['options'])){
                $terminal::header();
            }

            if((new $newCommand())->help($info) === STATUS_ERROR){
                $terminal->helper($info);
            }

            return STATUS_SUCCESS;
        }

        return (int) (new $newCommand())->run($options);
    }

    /**
     * Get command information.
     * 
     * @param string $command command name.
     * 
     * @return array<string>mixed> Return array of command information.
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
        $isCommand = self::getCommand($command);

        return isset($isCommand[$key]) ? $isCommand[$key] : null;
    }
}