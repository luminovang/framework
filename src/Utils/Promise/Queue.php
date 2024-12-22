<?php
/**
 * Luminova Framework process executor.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils\Promise;

use Luminova\Interface\PromiseInterface;
use \Throwable;

final class Queue 
{
    /**
     * Constructor property promotion simplifies property declarations
     */
    public function __construct(private array $queues = [], private $invoked = false){}

    /**
     * Static factory method
     */
    public static function queue(): self 
    {
        return new self();
    }

    /**
     * __invoke method to trigger dequeue operation if not already invoked
     * 
     * @ignore
     * @internal
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
     * Enqueue method to add an item to the queue if valid
     */
    public function push(mixed $value): void
    {
        if (
            !($value instanceof PromiseInterface) &&
            (
                !is_object($value) || 
                !method_exists($value, 'then') || 
                !method_exists($value, 'cancel')
            )
        ) {
            return;
        }

        $length = count($this->queues);
        $this->queues[] = $value;

        if ($this->invoked && $length === 0) {
            $this->dequeue();
        }
    }

    /**
     * Dequeue method to cancel all items in the queue.
     * @ignore
     * @internal
     */
    private function dequeue(): void
    {
        foreach ($this->queues as $key => $promise) {
            assert(method_exists($promise, 'cancel'));

            $e = null;

            try {
                $promise->cancel();
            } catch (Throwable $e) {}

            unset($this->queues[$key]);

            if ($e) {
                throw $e;
            }
        }
        
        $this->queues = [];
    }
}