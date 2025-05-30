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

use \Luminova\Interface\LazyInterface;
use \Luminova\Core\CoreApplication;
use \Luminova\Command\Terminal;
use \Luminova\Utils\LazyObject;
use \App\Application;

abstract class BaseConsole
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
     * @var Application<CoreApplication,LazyInterface>|null $app
     */
    protected ?LazyInterface $app = null;

    /**
     * Lazy loaded terminal instance.
     * 
     * @var Terminal<LazyInterface>|null $term
     */
    protected ?LazyInterface $term = null;

    /**
     * Initialize console command, register lazy objects and onCreate method hook.
     */
    public function __construct()
    {
        $this->term = LazyObject::newObject(Terminal::class);
        $this->app = LazyObject::newObject(fn() => Application::getInstance());

        $this->onCreate();
    }

    /**
     * Allows access to protected static methods.
     *
     * @param string $method The method name to call.
     * @param array<int,mixed> $arguments The arguments to pass to the method.
     * 
     * @return mixed Return the value of the method, or null if the method doesn't exist.
     * @ignore 
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return method_exists(static::class, $method) 
            ? static::{$method}(...$arguments) 
            : null;
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
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
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
     * @param string $key The property name.
     * 
     * @return bool Return true if the property exists, otherwise false.
     * @ignore
     */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }
}