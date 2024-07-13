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
     * The group name for current command controller class.
     * 
     * @var string $group Command group name.
     * > The group name will be used in registering command routes.
     * > Only methods that belong to same group will be registered in one group method.
     */
    protected string $group = '';

    /**
     * The execution command name for current controller class.
     * 
     * @var string $name Command name.
     * > The command name will be used called methods belonging to same group
     * > E.g `php index.php <command-name> <method> <arguments>`.
     */
    protected string $name = '';

    /**
     * The command usages. 
     * Use the array key for command usage and the value for description.
     * 
     * @var string|array<string|int,string> $usage command usages.
     */
    protected string|array $usage = '';

    /**
     * The command available options.
     * Use the key for the options (e.g ['-f, --foo' => 'Foo description']).
     * 
     * @var array<string|int,string> $options command options.
     */
    protected array $options = [];

    /**
     * The examples on how to use command.
     * Show full example on how commands can be used.
     * 
     * @var array<string|int,string> $examples show command examples.
     */
    protected array $examples = [];

    /**
     * The full command description.
     * Tell more information what the command does.
     * 
     * @var string $description command description.
     */
    protected string $description = '';

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Allow access to protected static methods
     *
     * @param string $method method name to call.
     * @param array<int,mixed> $arguments arguments to pass to method.
     * 
     * @return mixed Return value of method.
     * @ignore 
    */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if (method_exists(static::class, $method)) {
            return static::{$method}(...$arguments);
        }

        return null;
    }

    /**
     * Override the default help display implementation.
     * Return STATUS_SUCCESS success if you implemented your own help display otherwise return STATUS_ERROR.
     *
     * @param array<string,mixed> $helps Helps information about command:
     *      - class: The class name of the command (Note: this key may not be available if you extend BaseConsole).
     *      - group :The group name of the command.
     *      - name: The name of the command.
     *      - description: The description of the command.
     *      - usages: The usages of the command.
     *      - options: The available options for the command.
     *      - examples: The examples of the command.
     * 
     * @return int Return status code.
    */
    abstract public function help(array $helps): int;

    /**
     * Property getter
     *
     * @param string $key property key
     * 
     * @return mixed return property else null
     * @ignore
    */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
    /**
     * Check if property is set
     *
     * @param string $key property key
     * 
     * @return bool 
     * @ignore
    */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }
}
