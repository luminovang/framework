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
namespace Luminova\Base;

use \Luminova\Boot;
use \Luminova\Command\Input;
use \Luminova\Command\Terminal;
use \Luminova\Foundation\Core\Application;
use \Luminova\Interface\LazyObjectInterface;

/**
 * A class to extend when building a console CLI controller for Novakit commands.
 * It specialize with handling commands using Luminova novakit command-line helper.
 * All commands must be register in `/bin/.novakit-console.php` 
 * and execute with `php novakit <command>` to run registered commands.
 * 
 * @see https://luminova.ng/docs/0.0.0/controllers/cli-novakit-controller
 */
abstract class Console
{
    /**
     * Command group name for the current controller class.
     * 
     * @var string $group
     * The group name is used for registering command routes.
     * Only methods within the same group are registered together.
     * Example: `php index.php <command-group-name> <command> <arguments>`.
     */
    protected string $group = '';

    /**
     * Command name for the current controller class.
     * 
     * @var string $name
     * The command name is used internally to generate command information, 
     * such as for displaying help.
     */
    protected string $name = '';

    /**
     * Optional command aliases.
     * 
     * @var string[] $aliases
     */
    protected array $aliases = [];

    /**
     * Command usage instructions.
     * Keys represent the command usage, and values describe the usage.
     * 
     * @var array<string|int,string>|string $usages
     */
    protected array|string $usages = '';

    /**
     * Available command options.
     * Keys represent the options (e.g., `'-f, --foo' => 'Foo description'`).
     * 
     * @var array<string|int,string> $options
     */
    protected array $options = [];

    /**
     * Examples demonstrating command usage.
     * 
     * @var array<string|int,string> $examples
     */
    protected array $examples = [];

    /**
     * Full description of the command.
     * Provides detailed information about the command's purpose.
     * 
     * @var string $description
     */
    protected string $description = '';

    /**
     * List of allowed system users.
     * 
     * @var array<int,string> $users
     * 
     * @example - Users:
     * 
     * Only users listed here can execute this command, leave empty array to allow all users.
     * 
     * ```php
     * ['www-data', 'ubuntu', 'admin']
     * ```
     */
    protected array $users = [];

    /**
     * Authenticate configuration for password or private/public login.
     * 
     * @var array<string,mixed>|null $authentication
     * 
     * @example - Auth from database:
     * ```php
     * [
     *      'storage' => 'database',
     *      'tableName' => 'foo_cli_users'
     * ]
     * ```
     * @example - Auth from filesystem:
     * ```php
     * [
     *      'storage' => 'filesystem',
     *      'storagePath' => 'writeable/auth/cli_users.php'
     * ]
     * ```
     * @example - Supported Array keys Or Database column names:
     * 
     * - **auth:** `(string)` - Authentication type (e.g, `password` or `key`).
     * - **content:** `(string)` - Password hash or empty for not password login, public key content or public key file.
     * - **sessions:** `(json-string|array)` - Activate authenticated system users.
     * - **updated_at:** `datetime` - Last authenticated datetime.
     */
    protected ?array $authentication = null;

    /**
     * Lazy loaded application instance.
     * 
     * @var \T<Application>|Application<Application> $app
     */
    protected readonly LazyObjectInterface $app;

    /**
     * Command input. 
     * 
     * @var Input $input
     */
    protected readonly Input $input;
    
    /**
     * Initialize console command, register lazy objects and onCreate method hook.
     */
    public function __construct()
    {
        Terminal::init();

        $this->app = Boot::application();
        $this->onCreate();
    }

    /**
     * Parse command input.
     * 
     * This parses and processes command-line arguments and options, making them accessible in console controllers.
     * 
     * @param array<string,mixed> $command Command arguments, options, and flags extracted from CLI execution.
     * 
     * @return static Returns instance of console.
     */
    public final function parse(Input|array $command): self
    {
        if(isset($this->input)){
            $this->input->replace(
                ($command instanceof Input) ? $command->getArray() : $command
            );

            return $this;
        }

        $this->input = ($command instanceof Input) 
            ? $command 
            : new Input($command);

        if($this->options !== []){
            $this->input->setKnownOptions(array_keys($this->options));
        }

        return $this;
    }

    /**
     * Override the default help display.
     * Implement custom help display, returning STATUS_SUCCESS on success, otherwise return STATUS_ERROR.
     *
     * @param array<string,mixed> $helps Information about the command:
     *      - class: The class name (may not be available if extending BaseConsole).
     *      - group: The group name.
     *      - name: The command name.
     *      - description: The command description.
     *      - usages: Command usage examples.
     *      - options: Available command options.
     *      - examples: Command usage examples.
     * 
     * @return int Return STATUS_SUCCESS when custom help is implemented, STATUS_ERROR when using default implementation.
     */
    abstract public function help(array $helps): int;

    /**
     * Handle running and execution of command in console controller class.
     *
     * @param array<string,mixed>|null $params Command arguments and parameters.
     * 
     * @return int Return exit code STATUS_SUCCESS on success else STATUS_ERROR.
     */
    abstract public function run(?array $params = null): int;

    /**
     * Called immediately after object creation.
     *
     * Override to customize setup or initialize dependencies.
     */
    protected function onCreate(): void {}

    /**
     * Getter for protected properties.
     *
     * @param string $key The property name.
     * 
     * @return mixed Return the property value, or null if it doesn't exist.
     * @ignore
     */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
    /**
     * Checks if a property is set.
     *
     * @param string $property The property name.
     * 
     * @return bool Return true if the property exists, otherwise false.
     * @ignore
     */
    public function __isset(string $property): bool
    {
        return isset($this->{$property});
    }
}