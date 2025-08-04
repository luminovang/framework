<?php 
/**
 * Luminova Framework callbacks and closures base class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Closure;
use \Luminova\Exceptions\RuntimeException;

abstract class BaseCallable
{
    /**
     * Resisted callback.
     *  
     * @var callable|null $callback
     */
    protected mixed $callback = null;

    /**
     * Constructor to initialize the callback.
     *
     * @param callable|null $callback The closure or callable to be executed (default: null).
     * 
     * @example - Extending the base callback class:
     * 
     * ```php
     * // /app/Callable/CustomCallback.php
     * 
     * namespace App\Callable;
     * 
     * use Luminova\Base\BaseCallable;
     * class CustomCallback extends BaseCallable
     * {
     *    public function __construct()
     *    {
     *        // Set a custom callback during instantiation
     *        parent::__construct(function (array $array): void {
     *           foreach ($array as $value) {
     *                echo $value * 2 . PHP_EOL; 
     *           }
     *        });
     *    }
     * }
     * ``` 
     * 
     * Now to pass your callback to any method:
     * 
     * ```php
     * use App\Callable\CustomCallback;
     * (new Foo())->feedMeCallback(new CustomCallback());
     * ```
     */
    public function __construct(?callable $callback = null)
    {
        $this->callback = $callback;
    }

    /**
     * Implement your logic to enable the class to be invoked like a function.
     *
     * @param mixed ...$values The arguments passed during invocation.
     * 
     * @return mixed Return the result of the invocation.
     */
    abstract public function __invoke(mixed ...$values): mixed;

    /**
     * Execute the stored callback with the provided arguments.
     *
     * @param mixed ...$values The arguments to pass to the callback function.
     * 
     * @return mixed Return the result of the callback execution.
     * @throws RuntimeException If no callback has been set.
     */
    public function invoke(mixed ...$values): mixed
    {
        if ($this->callback === null) {
            throw new RuntimeException('No callable function has been set to execute.');
        }

        return ($this->callback)(...$values);
    }

    /**
     * Set the callback function.
     *
     * @param callable $callback The closure or callable to be executed.
     * @return void
     * 
     * > The set method does not support chaining of multiple callbacks.
     */
    public function set(callable $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * Get the stored callback function.
     *
     * @return ?callable|null The closure or callable function.
     */
    public function get(): ?callable
    {
        return $this->callback;
    }

    /**
     * Checks if a valid callback function is set and callable.
     *
     * This method verifies if the `$callback` property is set and if it is a callable. It checks whether
     * the callback is an instance of `Closure` or if it is otherwise callable.
     *
     * @return bool Returns true if the callback is set and callable; false otherwise.
     */
    public function has(): bool
    {
        if($this->callback === null){
            return false;
        }

        if($this->callback instanceof Closure){
            return true;
        }

        return is_callable($this->callback);
    }
}