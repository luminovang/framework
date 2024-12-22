<?php
/**
 * Luminova Framework non-blocking Fiber Interval asynchronous execution.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Fiber;
use \Luminova\Exceptions\RuntimeException;
use \Exception;

final class Interval
{
    /**
     * Fiber instance managing the interval.
     * 
     * @var Fiber|null $fiber
     */
    private ?Fiber $fiber = null;

    /**
     * Indicates whether the interval is currently running.
     * 
     * @var bool $running
     */
    private bool $running = true;

    /**
     * Last value passed.
     * 
     * @var mixed $value
     */
    private mixed $value = null;

    /**
     * Interval constructor.
     *
     * Initializes the interval and starts the execution of the given callback at specified intervals.
     *
     * @param callable $callback The callback function to execute at each interval.
     * @param int $milliseconds The interval duration in milliseconds.
     * 
     * @throws RuntimeException Throws if error occurs during execution.
     * 
     * @example - Usage Example:
     * 
     * ```php
     * $interval = new Interval(function () {
     *     echo "Executing at: " . date('H:i:s') . "\n";
     * }, 1000);
     * 
     * for ($i = 0; $i < 5; $i++) {
     *     usleep(500_000);
     *     $interval->tick();
     * }
     * 
     * $interval->clear();
     * echo "Interval cleared.\n";
     * ```
     */
    public function __construct(callable $callback, int $milliseconds)
    {
        $this->fiber = new Fiber(fn() => $this->listen($callback, $milliseconds));
        $this->fiber->start();
    }

    /**
     * Creates and returns an `Interval` instance, allowing a callback to execute
     * repeatedly asynchronously at the specified interval in milliseconds.
     *
     * @param callable $callback The callback function to execute on each interval.
     * @param int $milliseconds The interval duration in milliseconds.
     *
     * @return Interval Return new created interval instance.
     *
     * @throws RuntimeException Throws if an error occurs during execution.
     * 
     * @example - Usage Example:
     * 
     * ```php
     * $interval = Interval::setInterval(function () {
     *    echo "Interval executed at: " . date('H:i:s') . "\n";
     * }, 2000);
     * 
     * if($foo === $bar) $interval->clear();
     * ```
     */
    public static function setInterval(callable $callback, int $milliseconds): self
    {
       $interval = new self($callback, $milliseconds);
       $interval->run();
       return $interval;
    }

    /**
     * Starts or resumes the execution of the interval.
     * 
     * @param mixed $value An optional value to pass to the callback (default: null).
     *
     * @return void
     */
    public function run(mixed $value = null): void
    {
        $this->running = true;
        if ($this->fiber->isSuspended()) {
            $this->fiber->resume($value);
            return;
        }

        $this->value = $value;
    }

    /**
     * Stops the interval and clears any further execution.
     * 
     * @param mixed $value An optional value to pass to the callback during the clear operation (default: null).
     * 
     * @return void 
     */
    public function clear(mixed $value = null): void
    {
        $this->running = false;
        if ($this->fiber->isSuspended()) {
            $this->fiber->resume($value); 
            return;
        }

        $this->value = null;
    }

    /**
     * Resumes the interval execution manually.
     * 
     * @param mixed $value An optional value to pass to the callback when resuming (default: null).
     * 
     * @return bool Return true if interval is running and invocation succeed, false otherwise.
     */
    public function tick(mixed $value = null): bool
    {
        if ($this->running) {
            $this->fiber->resume($value);
            return true;
        }

        return false;
    }

    /**
     * Checks if the interval is currently running.
     *
     * @return bool Return `true` if the interval is running, otherwise `false`.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

     /**
     * Listen to interval timeout and execute callback.
     *
     * @param callable $callback The callback function to execute at each interval.
     * @param int $milliseconds The interval duration in milliseconds.
     * 
     * @return void 
     * @throws RuntimeException Throws if error occurs during execution.
     */
    private function listen(callable $callback, int $milliseconds): void
    {
        while ($this->running) {
            $value = Fiber::suspend();
            usleep($milliseconds * 1_000);

            if ($this->running) {
                try{
                    $callback($value ?? $this->value);
                    $this->value = null;
                }catch(Exception $e){
                    throw new RuntimeException(
                        'Failure while executing callback: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    );
                }
            }
        }
    }
}