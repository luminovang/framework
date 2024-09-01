<?php

/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Closure;

class Queue
{
    /**
     * Queue to execute
     * 
     * @var array<int, Closure|mixed>
     */
    private array $jobs = [];

    /**
     * Constructor to initialize the queue with jobs if provided.
     * 
     * @param array|null $jobs Array of jobs to initialize the queue.
     */
    public function __construct(array $jobs = [])
    {
        if ($jobs !== []) {
            $this->jobs = $jobs;
        }
    }

    /**
     * Magic method to get a property.
     *
     * @param string $key The property key.
     *
     * @return mixed|null The property value.
     * @internal
     */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }

    /**
     * Magic method to check if a property is set.
     *
     * @param string $key The property key.
     *
     * @return bool True if the property is set, otherwise false.
     * @internal
     */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }

    /**
     * Push a closure or item to the queue.
     *
     * @param Closure|string|callable $item The item to enqueue.
     *
     * @return void
     */
    public function push(Closure|string|callable $item): void
    {
        $this->jobs[] = $item;
    }

    /**
     * Run the queue by executing all jobs.
     * And execute a callback function
     *
     * @param callable|null $callback Optional Callback function to execute after running the queue.
     * 
     * @return void
     */
    public function run(?callable $callback = null): void
    {
        foreach ($this->jobs as $job) {
            $this->execute($job);
        }

        $this->jobs = [];
        if ($callback !== null && is_callable($callback)) {
            $callback();
        }
    }

    /**
     * Fork a child process and execute a job.
     *
     * @param Closure|mixed $job The job to execute.
     *
     * @return void
     */
    private function execute(mixed $job): void
    {
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                // Fork failed
                $className = (is_callable($job) && is_array($job)) ? $job[0]::class : null;
                $message = 'Queue could not fork.' . ($className ? ' Class: ' . $className : '');
                logger('debug', $message);
            } elseif ($pid !== 0) {
                // Parent process
                // In the parent, we can continue without waiting for the child to finish
            } else {
                // Child process
                $this->callQueue($job);
                exit(); // Child process exits immediately
            }
        } else {
            $this->callQueue($job);
        }
    }

    /**
     * Call job callback
     *
     * @param Closure|mixed $job The job to execute.
     *
     * @return void
     */
    private function callQueue(mixed $job): void
    {
        if ($this->isCallable($job)) {
            $job($this);
        } elseif (is_string($job)) {
            echo $job . PHP_EOL;
        }
    }

    /**
     * Check if the queue is empty.
     *
     * @return bool True if the queue is empty, otherwise false.
     * @internal
     */
    public function isEmpty(): bool
    {
        return $this->jobs === [];
    }

    /**
     * Check if the queue has registered callable jobs.
     *
     * @return bool True if the queue has registered callable jobs.
     */
    public function hasQueue(): bool
    {
        return !$this->isEmpty() && array_filter($this->jobs, fn($job): bool => $this->isCallable($job)) !== [];
    }

    /**
     * Check if the queued job is closure and callable
     *
     * @param Closure|mixed $job The job to check.
     * 
     * @return bool True if the queue has registered callable jobs.
     */
    private function isCallable(mixed $job): bool
    {
        return is_callable($job) || $job instanceof Closure;
    }

    /**
     * Get the size of the queue.
     *
     * @return int The size of the queue.
     */
    public function size(): int
    {
        return count($this->jobs);
    }

    /**
     * Delete the first task from the queue
     *
     * @return void
     */
    public function delete(): void
    {
        array_shift($this->jobs);
    }

    /**
     * Remove an job from the queue.
     *
     * @param mixed $job The job name to remove.
     *
     * @return void
     */
    public function remove(mixed $job): void
    {
        $index = array_search($job, $this->jobs);

        if ($index !== false) {
            unset($this->jobs[$index]);
        }
    }

    /**
     * Free all resources by clearing the queue
     *
     * @return void
     */
    public function free(): void
    {
        $this->jobs = [];
    }

    /**
     * Return a new Queue instance.
     * 
     * @param array $job Jobs to initialize the new Queue instance.
     * 
     * @return Queue A new Queue instance.
     */
    private function returnInstance(array $job): Queue
    {
        return new self($job);
    }

    /**
     * Get the current job from queue and return a new instance.
     *
     * @param int $index Current job index.
     *
     * @return Queue A new Queue instance.
     */
    public function getInstance(int $index): Queue
    {
        return $this->returnInstance([$this->jobs[$index]]);
    }

    /**
     * Get the current job from queue and return a new instance.
     * 
     * @return Queue A new Queue instance.
     */
    public function current(): Queue
    {
        return $this->getInstance(0);
    }

    /**
     * Get the next job from queue and return a new instance.
     * 
     * @return Queue A new Queue instance.
     */
    public function next(): Queue
    {
        return $this->getInstance(1);
    }

    /**
     * Get the last job from queue and return a new instance.
     * 
     * @return Queue A new Queue instance.
     */
    public function last(): Queue
    {
        return $this->getInstance(-1);
    }
}