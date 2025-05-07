<?php 
/**
 * Luminova Framework Novkit console command handler.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use \Luminova\Luminova;
use \Luminova\Base\BaseConsole;
use \Luminova\Command\Terminal;
use \Luminova\Command\Consoles\Server;
use \Luminova\Command\Consoles\SystemHelp;
use \Luminova\Command\Consoles\Lists;
use \Luminova\Command\Consoles\Database;
use \Luminova\Command\Consoles\Generators;
use \Luminova\Command\Consoles\Authenticate;
use \Luminova\Command\Consoles\System;
use \Luminova\Command\Consoles\Builder;
use \Luminova\Command\Consoles\Context;
use \Luminova\Command\Consoles\Commands;
use \Luminova\Command\Consoles\CronJobs;
use \Luminova\Command\Consoles\Logs;
use \Luminova\Command\Consoles\ClearWritable;
use \Luminova\Interface\LazyInterface;
use \ReflectionClass;
use \Throwable;

final class Novakit 
{
    /**
     * Static terminal instance.
     * 
     * @var Terminal|null $instance 
     */
    private static ?Terminal $instance = null;

    /**
     * Static instance of called command.
     * 
     * @var BaseConsole|null $newConsole 
     */
    private static ?BaseConsole $newConsole = null;

    /**
     * Is novakit system commands.
     * 
     * @var bool $isSystem 
     */
    private static bool $isSystem = true;

    /**
     * Developers console commands.
     * 
     * @var array<string,class-string<\T>>|null $consoles 
     */
    private static ?array $consoles = null;

    /**
     * Developers console controller command information.
     * 
     * @var array<string,mixed> $properties 
     */
    private static array $properties = [];

    /**
     * Initialize the Novakit console terminal instance.
     */
    public function __construct()
    { 
        if(!self::$instance instanceof Terminal){
            self::$instance = new Terminal();
        }
    }

    /**
     * Entry point for executing Novakit CLI commands.
     * 
     * @param array<string,mixed> $commands The raw command-line arguments, typically from `$_SERVER['argv']`.
     * 
     * @return void
     */
    public function run(array $commands): void
    {
        $commands = self::$instance::parseCommands($commands);

        self::$instance::perse($commands);
        $command = self::$instance::getCommand();

        if(!$command){
            self::$instance::header();
            exit(STATUS_ERROR);
        }

        if('--version' === $command || '--v' === $command){
            self::$instance::writeln('Novakit CLI - Luminova Framework Tool');
            self::$instance::writeln(sprintf(
                "Framework Version: %s\nNovakit Version: %s\nApplication Version: %s",
                Luminova::VERSION,
                Luminova::NOVAKIT_VERSION,
                APP_VERSION
            ), 'green');

            exit(STATUS_SUCCESS);
        }

        if('--system-info' === $command){
            self::$instance::writeln('System Information', 'green');
            self::$instance::about();
            exit(STATUS_SUCCESS);
        }

        exit(self::execute(self::$instance));
    }

    /**
     * Execute system or developer-defined console command outside of NovaKit CLI handler.
     * 
     * This method resolves and runs the specified command based on the provided terminal input,
     * handling help output, validation, and execution within the defined mode.
     * 
     * @param Terminal<LazyInterface> $instance The terminal instance containing parsed command and arguments.
     * @param array<string,mixed>|null $options The parsed command arguments and options 
     *                      or null to read from terminal object.
     * @param string $mode The command execution mode (`system` for core commands, `global` for user-defined).
     * 
     * @return int Returns the command's exit status code. 
     *             `STATUS_SUCCESS` on successful execution, otherwise `STATUS_ERROR`.
     * 
     * @internal
     * @example - Usages:
     * 
     * ```php
     * $term = new Terminal();
     * $result = $term->extract(array_slice($_SERVER['argv'], 2));
     * $term->perse();
     * 
     * Console::execute($term, array_merge(
     *      $term->getArguments(), 
     *      $term->getQueries()
     * ));
     * ```
     */
    public static function execute(LazyInterface $instance, ?array $options = null, string $mode = 'global'): int
    {
        self::$isSystem = true;
        $options = ($options === null) 
            ? array_merge($instance::getArguments(), $instance::getQueries())
            : $options;
        $command = trim($instance::getCommand() ?? '');
        $className = self::find($command, $mode);

        if ($className === null) {
            $instance::oops($command);

            if(($suggest = Commands::suggest($command)) !== ''){
                $instance->fwrite($suggest, Terminal::STD_ERR);
            }

            return STATUS_ERROR;
        } 
       
        if(!self::newObject($className)){
            return STATUS_ERROR;
        }

        $info = null;

        if($instance::isHelp($options['options'])){
            $info = self::getCommand($command, $mode);
            $instance::header();

            if(self::$newConsole->help($info) === STATUS_ERROR){
                $instance::helper($info);
            }

            return STATUS_SUCCESS;
        }

        if(!self::$isSystem){
            $info ??= self::getCommand($command, $mode);
            $users = $info['users'] ?? [];

            if($users !== []){
                $user = $instance::whoami();

                if(!in_array($user, $users, true)){
                    $instance::error("User '{$user}' is not allowed to run this command.");
                    return STATUS_ERROR;
                }
            }
        }

        return (int) self::$newConsole->run($options);
    }

    /**
     * Find the fully qualified controller class for a given command.
     *
     * This method attempts to resolve the command to a known controller class.
     * It first checks predefined system commands, then searches registered console commands
     * based on the provided mode (`system` or `global`).
     *
     * @param string $command The command string (e.g., `create:controller`, `db:migrate`, `foo`).
     * @param string $mode The lookup mode: `system` for internal commands, or `global` for custom/console commands.
     *
     * @return class-string<BaseConsole>|null Returns the fully qualified class name if found, or `null` if not.
     */
    public static function find(string $command, string $mode = 'global'): ?string 
    {
        $pos = strpos($command, ':');
        $novakit = ($pos === false) ? $command : substr($command, 0, $pos); 
        $controller = match($novakit){
            '-h', '--help' => SystemHelp::class,
            'auth', => Authenticate::class,
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
            default => null
        };

        if($controller !== null){
            return $controller;
        }

        if($mode === 'system'){
            return null;
        }

        self::autoload();

        if(!self::$consoles || self::$consoles === []){
            return null;
        }

        return self::$consoles[$novakit] 
            ?? self::$consoles[$command] 
            ?? null;
    }

    /**
     * Registers a new console command with an optional metadata definition.
     * 
     * This method maps a command name to its controller class and optionally stores
     * command metadata such as group, description, usage examples, options, and more.
     * 
     * @param string $name The command name (e.g., 'foo').
     * @param class-string<BaseConsole> $class The fully qualified class name that handles the command.
     * @param array $properties (optional) Additional metadata for the command based on protected properties.
     * 
     * @return bool Returns true if the command was successfully registered; false if it already exists.
     * 
     * @example - Registering a basic command:
     * ```php
     * Console::command('foo', Foo::class);
     * ```
     * 
     * @example - Registering a command with metadata:
     * ```php
     * Console::command('foo', Foo::class, [
     *     'group' => 'Bar',
     *     'description' => 'Foo command',
     *     'usages' => [
     *         'php novakit foo'
     *     ],
     *     'options' => [
     *         '-b, --bar' => 'To run bar'
     *     ],
     *     'examples' => [
     *         'php novakit foo -b="Bra"' => 'Execute bra'
     *     ]
     * ]);
     * ```
     */
    public static function command(string $name, string $class, array $properties = []): bool 
    {
        self::$isSystem = false;

        if (self::hasCommand($name, 'staging') || !class_exists($class)) {
            return false;
        }

        self::$consoles[$name] = $class;

        if ($properties !== []) {
            $properties['name'] = $name;
            $properties['class'] = $class;
            self::$properties[$name] = $properties;
        }

        return true;
    }

    /**
     * Get a specific property from a registered command's metadata.
     *
     * @param string $command The command name.
     * @param string $name The property name to retrieve (e.g., 'group', 'description').
     *
     * @return mixed Returns the value of the specified property if it exists, or `null` otherwise.
     */
    public static function get(string $command, string $name): mixed
    {
        return self::getCommand($command)[$name] ?? null;
    }

    /**
     * Retrieve full metadata for a command based on its protected controller properties.
     * 
     * The returned array may include the following keys:
     * `name`, `group`, `description`, `usages`, `options`, `examples`, etc.
     *
     * @param string $command The command name.
     * @param string $mode The command mode to check within (supported: `system` or `global`).
     *
     * @return array<string,mixed> Returns an associative array of command metadata.
     */
    public static function getCommand(string $command, string $mode = 'global'): array
    {
        self::$isSystem = true;
        $commands = Commands::get($command);

        return ($commands === [] && $mode !== 'system') 
            ? self::build($command) 
            : $commands;
    }

    /**
     * Check if a command exists globally (i.e., user-defined console command).
     *
     * @param string $command The name of the command to check.
     *
     * @return bool Returns true if the command exists globally, otherwise, false.
     */
    public static function has(string $command): bool
    {
        return self::hasCommand($command, 'global');
    }

    /**
     * Check if a command exists in NovaKit or among custom console commands,
     * based on the given execution mode.
     *
     * @param string $command The name of the command to check.
     * @param string $mode The command mode to check within. Can be one of:
     *                     `system`, `admin`, `global`, or `staging`.
     *
     * @return bool Returns true if the command exists in the specified mode, otherwise false.
     * @internal
     */
    public static function hasCommand(string $command, string $mode = 'system'): bool
    {
        if(!$mode){
            return false;
        }
        
        if(($mode !== 'admin' && Commands::has($command)) || Terminal::isHelp($command)){
            return true;
        }

        if($mode === 'system'){
            return false;
        }

        if($mode !== 'staging'){
            self::autoload();
        }

        if(!self::$consoles || self::$consoles === []){
            return false;
        }

        return isset(self::$consoles[$command]);
    }

    /**
     * Builds and caches console command metadata based on command name.
     * 
     * This method loads and caches information such as group, description,
     * usage examples, and options from the specified command controller.
     * 
     * @param string $command The command name to build metadata for.
     * 
     * @return array<string,mixed> Returns an associative array of command metadata.
     */
    private static function build(string $command): array
    {
        self::$isSystem = false;

        if(isset(self::$properties[$command])){
            return self::$properties[$command];
        }

        self::autoload();

        if(!self::$consoles || self::$consoles === []){
            return [];
        }

        $className = self::$consoles[$command] ?? null;

        if($className === null || !class_exists($className)){
            return [];
        }

        if(!self::newObject($className)){
            exit(STATUS_ERROR);
        }

        try{
            $instance = self::getProperty();

            return self::$properties[$command] = [
                'name' => $command,
                'class' => $className,
                'group' => $instance->get('group', ''),
                'description' => $instance->get('description', ''),
                'usages' => $instance->get('usages', []),
                'options' => $instance->get('options', []),
                'examples' => $instance->get('examples', []),
                'users' => $instance->get('users', []),
                'authentication' => $instance->get('authentication', null),
            ];
        }catch(Throwable $e){
            Terminal::error($e->getMessage());
            exit(STATUS_ERROR);
        }

        return [];
    }

    /**
     * Instantiate a new console command class and validate it.
     * 
     * @param class-string<BaseConsole> $className The fully qualified class name of the console command.
     * 
     * @return bool Returns `true` if the class was successfully instantiated and is valid, `false` otherwise.
     */
    private static function newObject(string $className): bool 
    {
        if(self::$newConsole instanceof BaseConsole){
            return true;
        }

        $object = new $className();

        if(!$object instanceof BaseConsole){
            Terminal::error(sprintf('Class does not extend BaseConsole: %s', $className));
            return false;
        }

        self::$newConsole = $object;
        $object = null;
        return true;
    }

    /**
     * Wraps a console instance to allow access to protected properties via reflection.
     * 
     * @return object<\T> Returns a reflection wrapper with a get() accessor method.
     */
    private static function getProperty(): object
    {
        return new class(self::$newConsole) {
            private ?ReflectionClass $ref  = null;

            public function __construct(private BaseConsole $instance) 
            {
                $this->ref = new ReflectionClass($this->instance);
            }
        
            /**
             * Retrieves a protected property value from the wrapped instance.
             * 
             * @param string $name The property name.
             * @param mixed $default Optional default value if the property is not found.
             * 
             * @return mixed Returns the property value or the default.
             */
            public function get(string $name, mixed $default = null): mixed
            {
                if ($this->ref->hasProperty($name)) {
                    $prop = $this->ref->getProperty($name);
                    $prop->setAccessible(true);

                    return $prop->getValue($this->instance);
                }

                return $default;
            }
        };
    }

    /**
     * Autoload console commands from the `/bin/.novakit-console.php` file.
     * Ensures the global state is not polluted and command structure is valid.
     * 
     * @return void
     */
    private static function autoload(): void 
    {
        if(self::$consoles !== null){
            return;
        }

        $bin = root('/bin/') . '.novakit-console.php';

        if(!file_exists($bin)){
            return;
        }

        $commands = (static function (string $file): mixed {
            return include_once $file;
        })($bin);

        if(is_array($commands)){
            self::$consoles = $commands;
        }

        $commands = null;
    }
}