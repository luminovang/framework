<?php
/**
 * Luminova Framework process executor.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility\Promise\Helpers;

use \Throwable;
use \Luminova\Exceptions\LogicException;
use \Luminova\Interface\{PromiseInterface, InvokableInterface};

final class Queue implements InvokableInterface
{
    /**
     * Initialize a new promise Queue instance.
     *
     * Uses constructor property promotion to declare and initialize properties.
     *
     * @param array $queues  Initial queue items (optional).
     * @param bool  $invoked Whether the queue has been invoked (default: false).
     */
    public function __construct(private array $queues = [], private bool $invoked = false) {}

    /**
     * Static factory method to create a new Queue instance.
     *
     * @return self Returns a new Queue instance.
     */
    public static function queue(): self 
    {
        return new self();
    }

    /**
     * Invoke the queue to trigger dequeue operation.
     *
     * This method can only be invoked once per instance. Subsequent calls are ignored.
     *
     * @internal
     * @inheritdoc
     */
    public function __invoke(): void
    {
        if ($this->invoked) {
            return;
        }

        $this->invoked = true;
        $this->dequeue();
    }

    /**
     * Add a valid item to the queue.
     *
     * Only accepts objects implementing `PromiseInterface` or objects that define
     * both `then()` and `cancel()` methods. Invalid values are ignored.
     *
     * If the queue has already been invoked and this is the first item added,
     * it immediately triggers the dequeue process.
     *
     * @param mixed $promise The promise to add to the queue.
     * 
     * @return void
     */
    public function push(mixed $promise): void
    {
        if (
            !($promise instanceof PromiseInterface) &&
            (
                !is_object($promise) || 
                !method_exists($promise, 'then') || 
                !method_exists($promise, 'cancel')
            )
        ) {
            return;
        }

        $length = count($this->queues);
        $this->queues[] = $promise;

        if ($this->invoked && $length === 0) {
            $this->dequeue();
        }
    }

    /**
     * Cancel and remove all items from the queue.
     *
     * Iterates through queued items and attempts to call `cancel()` on each. If
     * an item does not support cancellation, a `LogicException` is thrown.
     * After processing, the queue is cleared.
     *
     * @return void
     * @throws Throwable If a promise's `cancel()` method throws an exception.
     *
     * @internal
     * @ignore
     */
    private function dequeue(): void
    {
        foreach ($this->queues as $key => $promise) {
            if (!method_exists($promise, 'cancel')) {
                throw new LogicException(sprintf('Promise at key "%s" does not support cancellation.', $key));
            }

            try {
                $promise->cancel();
            } catch (Throwable $e) {
                $this->remove($key);
                throw $e;
            }

            $this->remove($key);
        }

        $this->queues = [];
    }

    /**
     * Remove promise from queue.
     * 
     * @param int $key The promise key to remove.
     * 
     * @return void 
     */
    private function remove(int $key): void 
    {
        unset($this->queues[$key]);
    }
}