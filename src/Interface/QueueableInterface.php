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

/**
 * Contract for tasks that can be queued and executed in the background.
 *
 * Implementing this interface allows a class to be dispatched to a queue system
 * and executed asynchronously. The class must be invokable and may define
 * whether it should be automatically removed after successful completion.
 */
interface QueueableInterface extends InvokableInterface
{
    /**
     * Determine whether the task should be deleted after completion.
     *
     * @return bool True if the task should be removed once completed successfully.
     */
    public function deleteOnCompletion(): bool;
}