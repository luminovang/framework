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
     * @var string $group command group
    */
    protected string $group = '';

    /**
     * @var string $name command name
    */
    protected string $name = '';

    /**
     * Use the array key for command usage and the value for description.
     * 
     * @var string|array<string|int,string> $usage command usages.
    */
    protected string|array $usage = '';

    /**
     * @var array<string|int,string> $options command options
    */
    protected array $options = [];

    /**
     * @var string $description command description
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
     * @param array $arguments arguments to pass to method.
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
     * Override the default help implementation
     *
     * @param array $helps Helps information
     * 
     * @return int return STATUS_SUCCESS if you implemented your own help else return STATUS_ERROR.
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
