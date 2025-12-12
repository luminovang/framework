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
namespace Luminova\Interface;

interface Awaitable
{
    /**
     * Create a Future for an existing asynchronous task.
     *
     * @param mixed $task A Fiber, callable, PID, or other async task identifier.
     * @return self
     */
    public static function async(mixed $task): self;

    /**
     * Check if the task has completed (successfully or with exception).
     *
     * @return bool True if completed.
     */
    public function isComplete(): bool;

    /**
     * Check if the Future has started.
     */
    public function isStarted(): bool;

    /**
     * Check if the task can be awaited or resumed.
     *
     * @return bool True if not completed, not terminated, and can still run.
     */
    public function isWaitable(): bool;

    /**
     * Check if the task is currently suspended.
     *
     * @return bool True if suspended.
     */
    public function isSuspended(): bool;

    /**
     * Check if the task has been terminated.
     *
     * @return bool True if terminated.
     */
    public function isTerminated(): bool;

    /**
     * Poll for task completion without blocking.
     *
     * For Fiber-based tasks, this advances execution.
     * For PID-based tasks, this checks for output/result.
     */
    public function tick(): void;

    /**
     * Get the result of the task.
     *
     * @return mixed The result produced by the task.
     *
     * @throws RuntimeException If the task is not yet completed.
     * @throws Throwable If the task threw an exception during execution.
     */
    public function value(): mixed;

    /**
     * Wait for the task to complete and return its result.
     *
     * Blocks until completion or timeout.
     *
     * @param int $timeout Maximum time in seconds to wait (0 = no timeout).
     * @param float $delay Delay in seconds between polling ticks.
     *
     * @return mixed The result produced by the task.
     *
     * @throws RuntimeException If the task does not complete in time.
     * @throws RuntimeException If the task is suspended or terminated.
     * @throws Throwable If the task threw an exception during execution.
     */
    public function await(int $timeout = 0, float $delay = 0.1): mixed;
}