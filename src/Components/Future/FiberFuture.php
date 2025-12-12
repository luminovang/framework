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
namespace Luminova\Components\Future;

use \Fiber;
use \Throwable;
use \Luminova\Interface\Awaitable;
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

final class FiberFuture implements Awaitable
{
    private Fiber $fiber;
    private bool $started = false;
    private bool $completed = false;
    private mixed $result = null;
    private ?Throwable $exception = null;

    /**
     * Initialize a Future with a callable or Fiber.
     *
     * @param Fiber|callable $task A Fiber instance or a callable task to run asynchronously.
     */
    public function __construct(Fiber|callable $task)
    {
        $this->fiber = ($task instanceof Fiber) ? $task : new Fiber($task);
    }

    /**
     * Create a Future for an asynchronous task.
     *
     * The task will not start until start() or await() is called.
     *
     * @param Fiber|callable $task The task to run asynchronously.
     * 
     * @return static Return new instance of awaitable FiberFuture.
     * @throws InvalidArgumentException If $task is not a callable or Fiber.
     */
    public static function async(mixed $task): static
    {
        if (is_callable($task)) {
            $task = new Fiber($task);
        }

        if (!$task instanceof Fiber) {
            throw new InvalidArgumentException(sprintf(
                'Invalid task: expected callable or Fiber, got %s.',
                get_debug_type($task)
            ));
        }

        return new static($task);
    }

    /**
     * Check if the Future has completed (successfully or with exception).
     * 
     * @return bool True if the is completed.
     */
    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * Check if the Future can be awaited or resumed.
     *
     * @return bool True if the Future is not completed and can still run.
     */
    public function isWaitable(): bool
    {
        return !$this->completed;
    }

    /**
     * Check if the Future has started.
     */
    public function isStarted(): bool
    {
        return $this->fiber->isStarted();
    }

    /**
     * Check if the Future is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->fiber->isSuspended();
    }

    /**
     * Check if the Future has terminated.
     */
    public function isTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    /**
     * Start the underlying Fiber.
     *
     * This method ensures the Fiber is started only once. Any exceptions
     * thrown during execution are captured and mark the Future as completed.
     */
    public function start(): void
    {
        if ($this->started || $this->fiber->isStarted()) {
            return;
        }

        $this->started = true;

        try {
            $this->fiber->start();
        } catch (Throwable $e) {
            $this->exception = $e;
            $this->completed = true;
        }
    }

    /**
     * Resume execution if the Fiber is suspended.
     *
     * @param mixed $value Optional value to send into the Fiber.
     * @return mixed The value returned by the resumed Fiber, or null if not resumable.
     */
    public function resume(mixed $value = null): mixed
    {
        return $this->fiber->isSuspended() 
            ? $this->fiber->resume($value) 
            : null;
    }

    /**
     * Advance execution of the Fiber.
     *
     * If the Fiber is suspended, it will be resumed. Completion or exceptions
     * are captured automatically.
     */
    public function tick(): void
    {
        if ($this->completed) {
            return;
        }

        try {
            if ($this->fiber->isSuspended()) {
                $this->fiber->resume();
            }

            if ($this->fiber->isTerminated()) {
                $this->completed = true;
                $this->result = $this->fiber->getReturn();
            }
        } catch (Throwable $e) {
            $this->exception = $e;
            $this->completed = true;
        }
    }

    /**
     * Get the result of the Future.
     *
     * @return mixed The value returned by the Fiber.
     * @throws RuntimeException If the Future is not yet completed.
     * @throws Throwable If the Fiber threw an exception during execution.
     */
    public function value(): mixed
    {
        if (!$this->completed) {
            throw new RuntimeException('Future is not completed.');
        }

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->result;
    }

    /**
     * Wait for the Future to complete and return its result.
     *
     * This method blocks until the task is finished, throwing an exception
     * if the maximum wait time is exceeded.
     * 
     * @param int $timeout Maximum time in seconds to wait for completion.
     * @param float $delay Pause in seconds between tick attempts.
     *
     * @return mixed The result returned by the task.
     *
     * @throws RuntimeException If the task does not complete in $timeout seconds.
     * @throws Throwable If the task throws an exception during execution.
     */
    public function await(int $timeout = 0, float $delay = 0.1): mixed
    {
        $sleep = max(1_000, (int) ($delay * 1_000_000));
        $start = microtime(true);
        $this->start();

        while (!$this->isComplete()) {
            $this->tick();

            if ($this->isComplete()) {
                break;
            }

            if ($timeout > 0 && (microtime(true) - $start) >= $timeout) {
                throw new RuntimeException(
                    "Future did not complete within {$timeout} seconds."
                );
            }

            usleep($sleep);

            Fiber::suspend();
        }

        return $this->value();
    }
}