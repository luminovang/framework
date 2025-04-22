<?php
/**
 * Luminova Framework pipeline value transformation
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utils;

use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\RuntimeException;
use \Fiber;
use \Throwable;

final class Pipeline implements LazyInterface
{
    /**
     * Indicates the pipeline should stop execution.
     * 
     * @var string STOP
     */
    public const STOP = '__PIPELINE_STOP__';

    /**
     * Indicates the pipeline should continue with the last state.
     * 
     * @var string LAST_STATE
     */
    public const LAST_STATE = '__PIPELINE_LAST_STATE__';

    /**
     * The current value being processed through the pipeline.
     *
     * @var mixed $result
     */
    private mixed $result = null;

    /**
     * Handler for errors during the pipeline execution.
     *
     * @var callable $onError
     */
    private mixed $onError = null;

    /**
     * Indicate weather to stop pipeline execution.
     *
     * @var bool $stop
     */
    private bool $stop = false;

    /**
     * Indicate available async executor to use.
     *
     * @var bool|null $isFiber
     */
    private static ?bool $isFiber = null;

    /**
     * Initializes the pipeline with an optional initial value.
     * 
     * If `$async` is `true` and the handler is a callable, the handler will execute in a background thread (if supported).
     * It is invoked in a Fiber (PHP 8.1+). If Fiber is available, otherwise, it will throw an exception.
     * 
     * @param mixed $initializer The initial value to be processed through the pipeline.
     * @param bool $async Whether to execute the handler asynchronously in a background thread (default: false).
     * 
     * @example - Example with callable transformations
     * ```php
     * $result = (new Pipeline(10))
     *      ->pipe(fn($x) => $x + 5)      // Add 5
     *      ->pipe(fn($x) => $x * 2)      // Multiply by 2
     *      ->pipe(fn($x) => $x - 4)      // Subtract 4
     *      ->getResult();
     * 
     * echo $result; // Output: 26
     * ```
     */
    public function __construct(mixed $initializer = null, private bool $async = false) 
    {
        $this->result = $initializer;
        $initializer = null;
    }

    /**
     * Creates a new instance of the Pipeline class with the provided initial value.
     *
     * This method is used to start a new pipeline chain with an initial value.
     * The initial value can be any type of data, including objects, arrays, or primitive values.
     *
     * @param mixed $initializer The initial value to begin the pipeline with.
     * @param bool $async Whether to execute the handler asynchronously in a background thread (default: false).
     * 
     * @return self Return a new instance of the Pipeline.
     * 
     * @example - Example with mixed types:
     * ```php
     * $result = Pipeline::chain('Hello')
     *      ->pipe(fn($x) => strtoupper($x))  // Convert to uppercase
     *      ->pipe(fn($x) => $x . ' World')   // Append " World"
     *      ->pipe(fn($x) => trim($x))        // Trim whitespace
     *      ->getResult();
     * 
     * echo $result; // Output: HELLO World
     * ```
     */
    public static function chain(mixed $initializer = null, bool $async = false): self
    {
        return new self($initializer, $async);
    }

    /**
     * Adds a value or callable to the pipeline.
     *
     * If the provided value is callable, it will be invoked with the current value, 
     * and its result will be stored. Otherwise, the value itself replaces the current value.
     *
     * @param callable|mixed $handler A callable or a static value to process.
     *                                Callable signature should be: `function(mixed $result): mixed`.
     * 
     * @return self Returns the current Pipeline instance.
     * @throws RuntimeException If a callable throws an exception during execution.
     *                          For asynchronous operations, if `Fiber` is not available.
     * 
     * > **Note:** The pipe method can be chained multiple times for different transformations.
     */
    public function pipe(mixed $handler): self
    {
        if($this->async && is_callable($handler)){
            // Check if Fibers is supported only once
            self::$isFiber = (self::$isFiber === null) ? class_exists('Fiber') : self::$isFiber;
            $this->async($handler);
            return $this;
        }

        $this->execute($handler);
        return $this;
    }

    /**
     * Adds an error handler to handle exceptions during pipeline execution.
     *
     * - If an exception occurs, the error handler determines the pipeline's behavior:
     *   - Returning `Pipeline::STOP` stops execution, and `getResult` will return `null`.
     *   - Returning `Pipeline::LAST_STATE` stops execution, and `getResult` will return the last valid result.
     *   - Returning any other value allows the pipeline to continue execution with that value as the new result.
     * - If no error handler is defined, the exception is rethrown.
     *
     * @param callable $onError A callable to handle the error. 
     *                          Signature: `function(Throwable $e, mixed $result): mixed`.
     * 
     * @return self Returns the current Pipeline instance for chaining.
     * 
     * > **Note:** The error handler must always return a value: `Pipeline::STOP`, `Pipeline::LAST_STATE`, or any other value to continue execution.
     * > The `catch` method can only be invoked once and does not support multiple calls or exception type-hinting.
     * > The `catch` method should be called before invoking the `pipe` method.
     * 
     * @example - Example with error handling:
     * ```php
     * $result = Pipeline::chain(random_int(1, 10))
     *      ->catch(function(Throwable $e, mixed $result): mixed {
     *          echo "Error occurred: {$e->getMessage()} with value {$result}";
     *          return Pipeline::STOP; // Stops the pipeline execution.
     *      })
     *      ->pipe(fn($x) => ($x > 5) ? ($x + 1) : throw new Error("{$x} must be greater than 5"))
     *      ->getResult();
     * 
     * echo $result;
     * ```
     */
    public function catch(callable $onError): self
    {
        $this->onError = $onError;
        return $this;
    }

    /**
     * Retrieves the final result after processing through the pipeline.
     *
     * @return mixed Return the final processed result or `null` if the pipeline execution was stopped.
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Executes a handler (callable or static value) on the current value.
     *
     * - If the handler is a callable, it will be executed with the current value.
     * - If not callable, the handler itself will replace the current value.
     *
     * @param callable|mixed $handler The handler to process.
     * @return void
     *
     * @throws RuntimeException If a callable throws an exception during execution.
     */
    private function execute(mixed $handler): void
    {
        if($this->stop){
            return;
        }

        if (is_callable($handler)) {
            try {
                $this->call($handler, false, $this->result);
            } catch (Throwable $e) {
                $this->error($e);
            }
            return;
        }

        $this->result = $handler;
    }

    /**
     * Executes the handler asynchronously using Fibers.
     *
     * @param callable $handler The handler to be executed.
     * 
     * @return void
     */
    private function async(callable $handler): void
    {
        if($this->stop){
            return;
        }

        if(!self::$isFiber){
            throw new RuntimeException('PHP Fiber is not supported in this PHP environment');
        }

        $fiber = new Fiber(fn (mixed $value) => $handler($value));

        try {
            $fiber->start($this->result);
            while (!$fiber->isTerminated()) {
                Fiber::suspend($this->result);
            }

            $this->result = $fiber->getReturn();
        } catch (Throwable $e) {
            $this->error($e);
        }
    }

    /**
     * Handles any error that occurs during pipeline execution.
     *
     * @param Throwable $e The error that occurred during execution.
     * 
     * @return void
     */
    private function error(Throwable $e): void 
    {
        $error = ($e instanceof RuntimeException) ? $e : new RuntimeException(
            'Error in ' . ($this->async ? 'async ' : '') . 'pipeline execution: ' . $e->getMessage(),
            $e->getCode(),
            $e
        );

        if($this->onError){
            $this->call($this->onError, true, $error, $this->result);
            return;
        }

        throw $error;
    }

    /**
     * Execute the callback handler.
     *
     * @param callable $callable The handler to be executed.
     * @param bool $error Weather is an error handler.
     * @param mixed ...$args Additional arguments to be passed to the handler.
     * 
     * @return void
     */
    private function call(callable $callable, bool $error = false, mixed ...$args): void
    {
        $result = $callable(...$args);
        if($result === self::LAST_STATE){
            return;
        }

        if($result === self::STOP){
            $this->stop = true;
            $this->result = $error ? null : $this->result;
            return;
        }

        $this->stop = false;
        $this->result = $result;
        $result = null;
    }
}