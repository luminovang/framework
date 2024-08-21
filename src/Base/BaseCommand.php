<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Base;

use \Luminova\Command\Terminal;

abstract class BaseCommand extends Terminal 
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
     * @var string|array<string|int,string> $usage
     */
    protected string|array $usage = '';

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
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();
        $this->onCreate();
    }

    /**
     * Allows access to protected static methods.
     *
     * @param string $method The method name to call.
     * @param array<int,mixed> $arguments The arguments to pass to the method.
     * 
     * @return mixed The return value of the method, or null if the method doesn't exist.
     * @ignore 
    */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return method_exists(static::class, $method) ? static::{$method}(...$arguments) : null;
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
     * An alternative to the __construct method, called during object creation.
     * 
     * @return void 
    */
    protected function onCreate(): void {}

    /**
     * Getter for protected properties.
     *
     * @param string $key The property name.
     * 
     * @return mixed The property value, or null if it doesn't exist.
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
     * @return bool True if the property exists, otherwise false.
     * @ignore
    */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }
}