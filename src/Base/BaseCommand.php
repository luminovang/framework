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
     * @var string|array $usage command usages
    */
    protected string|array $usage = '';

    /**
     * @var array<string, mixed> $options command options
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
     * @param string $method method name to call
     * @param array $arguments arguments to pass to method
     * 
     * @return mixed
     * @ignore 
    */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if (method_exists(static::class, $method)) {
            return forward_static_call_array([static::class, $method], $arguments);
        }

        return null;
    }

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

    /**
     * Handle execution of command in command controller class.
     *
     * @param array<string, mixed> $params Command arguments and parameters
     * 
     * @return int status code STATUS_SUCCESS on success else STATUS_ERROR
    */
    abstract public function run(?array $params = []): int;
}
