<?php 
/**
 * Luminova Framework background queue model.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Models;

use \App\Tasks\TaskQueue;
use \Luminova\Base\Queue;
use \Luminova\Exceptions\{DatabaseException, InvalidArgumentException};
use function \Luminova\Funcs\camel_case;

class Task
{
    /**
     * This task ID.
     * 
     * @var int $id
     */
    public int $id = 0;

    /**
     * Task priority for execution order (0 = high to 100 = lowest).
     * 
     * @var int $priority
     */
    public int $priority = 0;

    /**
     * Number of task execution attempts.
     * 
     * @var int $retries
     */
    public int $attempts = 0;

    /**
     * Maximin retries attempts when task failed.
     * 
     * @var int $retries
     */
    public int $retries = 0;

    /**
     * Forever task run interval in minutes.
     * 
     * @var int|null $forever
     */
    public ?int $forever = null;

    /**
     * Task current execution status.
     * 
     * @var string $status
     */
    public string $status = 'pending';

    /**
     * Task group name.
     * 
     * @var string $group_name
     */
    public string $group_name = '';

    /**
     * Task executable handler.
     * 
     * Semi callable. Need extracting before calling.
     * 
     * @var string $handler
     */
    public string $handler = '';

    /**
     * Task callable handler arguments.
     * 
     * @var array<int,mixed>|string|null $arguments
     * 
     * > To ensure array is always returned use `getArguments()`.
     */
    public mixed $arguments = [];

    /**
     * Task callable handler signature (MD5).
     * 
     * @var string $signature
     */
    public string $signature = '';

    /**
     * Task execution outputs and result/response JSON.
     * 
     * @var array{response:mixed,output:string}|string|null $outputs
     * 
     * > Return decoded output as array ['response' => mixed, 'output' => string].
     * > To ensure array is always returned use `getOutputs()`.
     */
    public mixed $outputs = null;

    /**
     * Task delay execution till (datetime).
     * 
     * @var string|null $scheduled_at
     */
    public ?string $scheduled_at = null;

    /**
     * Task enqueue date (datetime).
     * 
     * @var string $created_at
     */
    public string $created_at = '';

    /**
     * Task last execution/update date (datetime).
     * 
     * @var string|null $updated_at
     */
    public ?string $updated_at = null;

    /**
     * Automatically delete task on completion.
     * 
     * @var int $auto_delete
     */
    public int $auto_delete = 0;

    /**
     * Initialize task with optional array and system queue.
     *
     * @param array<string,mixed>|null $task An optional array to initialize task with.
     * @param Queue<\T>|null $system An optional task queue system for managing task (default: `App\Tasks\TaskQueue`).
     */
    public function __construct(?array $task = null, private ?Queue $system = null) 
    {
        if ($task) {
            $this->fromArray($task);
        }

        $this->toArguments();
        $this->toOutputs();
    }

    /**
     * Magic property setter with fallback to camelCase property.
     */
    public function __set(string $property, mixed $value): void 
    {
        $this->setter($property, $value);
    }

    /**
     * Magic property getter with fallback to camelCase property.
     */
    public function __get(string $property): mixed 
    {
        return $this->getter($property);
    }

    /**
     * Set internal task queue system handler.
     * 
     * @param Queue<\T> $system A task queue system for managing task.
     * 
     * @return self Return instance of this task model.
     */
    public function setSystem(Queue $system): self
    {
        $this->system = $system;
        return $this;
    }

    /**
     * Enqueue the current task into the system.
     * 
     * @return bool Return true if task was added to queue successfully, otherwise false.
     * @throws DatabaseException If a database error occurs.
     * @throws InvalidArgumentException If an invalid forever interval is provided.
     */
    public function enqueue(): bool
    {
        $this->ensureSystem();

        $taskId = $this->system->enqueue(
            $this->handler,
            $this->getArguments(),
            $this->schedule_at,
            $this->priority,
            $this->forever,
            $this->retries
        );

        if ($taskId > 0) {
            $this->id = $taskId;
            return true;
        }

        return false;
    }

    /**
     * Retry this task via system handler.
     * 
     * @return bool Return true if task was marked as retryable successfully, otherwise false.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function retry(): bool
    {
        if($this->id === 0){
            return false;
        }

        $this->ensureSystem();
        if($this->system->retry($this->id)){
            $this->status = Queue::PENDING;
            return true;
        }

        return false;
    }

    /**
     * Pause this task.
     * 
     * @return bool Return true if task was marked as paused successfully, otherwise false.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function pause(): bool
    {
        if($this->id === 0){
            return false;
        }

        $this->ensureSystem();
        if($this->system->pause($this->id)){
            $this->status = Queue::PAUSED;
            return true;
        }

        return false;
    }

    /**
     * Delete this task from the system.
     * 
     * @return bool Return true if task was delete successfully, otherwise false.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function delete(): bool
    {
        if($this->id === 0){
            return false;
        }

        $this->ensureSystem();
        if($this->system->delete($this->id)){
            $this->status = Queue::PENDING;
            return true;
        }

        return false;
    }

    /**
     * Populate task object from associative array.
     *
     * @param array<string,mixed> $task An array of task to populate.
     * 
     * @return self Return instance of this task model.
     */
    public function fromArray(array $task): self 
    {
        foreach ($task as $key => $value) {
            $this->setter($key, $value);
        }

        return $this;
    }

    /**
     * Get task handler response (if available).
     * 
     * @return mixed Return task execution result.
     */
    public function getResponse(): mixed
    {
        $this->toOutputs();
        return $this->outputs['response'] ?? null;
    }

    /**
     * Get task raw output (if available).
     * 
     * @return mixed Return task execution output/error.
     */
    public function getOutput(): mixed
    {
        $this->toOutputs();
        return $this->outputs['output'] ?? null;
    }

    /**
     * Returns the task execution output as an array.
     *
     * @return array Return array of handler execution output (result and output).
     * > **Note:** If `$this->outputs` was previously a string, it will be converted to an array before returning.
     */
    public function getOutputs(): array
    {
        $this->toOutputs();
        return $this->outputs;
    }

    /**
     * Returns the task handler arguments as an array.
     *
     * @return array Return array of handlers arguments.
     * > **Note:** If `$this->arguments` was previously a string, it will be converted to an array before returning.
     */
    public function getArguments(): array
    {
        $this->toArguments();
        return $this->arguments;
    }

    /**
     * Check if task is due for execution based on schedule.
     * 
     * @return bool Return true if task is due for execution.
     */
    public function isDue(): bool
    {
        if($this->id < 1 || !$this->isExecutable()){
            return false;
        }

        return ($this->scheduled_at === null || strtotime($this->scheduled_at) <= time());
    }

    /**
     * Checks if the task will be deleted automatically when completed.
     * 
     * @return bool Return true if task auto delete is enabled, otherwise false.
     */
    public function isAutoDelete(): bool
    {
        return $this->auto_delete === 1;
    }

    /**
     * Checks if the task handler is a serialized Opis\Closure.
     * 
     * @return bool Return true if the handler appears to be an Opis closure, false otherwise.
     */
    public function isOpisClosure(): bool
    {
        return $this->handler && Queue::isClosure($this->handler);
    }

    /**
     * Check if current task status matches any of the given ones.
     *
     * @param string|string[] $status An array of statuses or a single status.
     * 
     * @return bool Return true if task status matches the giving status.
     */
    public function isStatus(array|string $status): bool
    {
        return is_array($status)
            ? in_array($this->status, $status, true)
            : $this->status === $status;
    }

    /**
     * Determine if task can be retried.
     * 
     * @return bool Return true if task is retryable, false otherwise.
     */
    public function isRetryable(): bool
    {
        if($this->id < 1 || !$this->isExecutable()){
            return false;
        }

        return $this->id > 0 && $this->attempts < $this->retries;
    }

    /**
     * Determine if task is executable now.
     * 
     * @return bool Return true if task is executable, false otherwise.
     */
    public function isExecutable(): bool
    {
        if($this->id < 1){
            return false;
        }

        $now = time();
        $scheduled = $this->scheduled_at ? strtotime($this->scheduled_at) : null;

        if ($this->status === Queue::PENDING) {
            return $scheduled === null || $scheduled <= $now;
        }

        if (
            $this->forever !== null &&
            in_array($this->status, [Queue::PENDING, Queue::FAILED, Queue::COMPLETED], true)
        ) {
            $updated = $this->updated_at ? strtotime($this->updated_at) : null;
            $timeOk = $updated === null || (
                $this->forever > 0 && $updated <= strtotime("-{$this->forever} minutes", $now)
            );

            return $timeOk && ($scheduled === null || $scheduled <= $now);
        }

        if (
            $this->status === Queue::FAILED &&
            ($this->retries > 0 && $this->retries >= $this->attempts)
        ) {
            return $scheduled === null || $scheduled <= $now;
        }

        return false;
    }

    /**
     * Get a normalized property key.
     * 
     * @var string $property The setter or getter property key.
     * 
     * @return string|null Return normalized property key if it exists, otherwise null.
     */
    protected function getProperty(string $property): ?string
    {
        if(property_exists($this, $property)){
            return $property;
        }

        $camel = camel_case($property);

        if (property_exists($this, $camel)) {
            return $camel;
        }

        return null;
    }

    /**
     * Set a property (supports snake_case and fallback to camelCase).
     * 
     * @param string $property The property name. 
     * @param mixed $value The property value. 
     * 
     * @return void
     */
    protected function setter(string $property, mixed $value): void 
    {
        $property = $this->getProperty($property);

        if ($property) {
            $this->{$property} = $value;
        }

        if($property === 'arguments'){
            $this->toArguments();
        }

        if($property === 'output'){
            $this->toOutputs();
        }
    }

    /**
     * Get a property value (supports snake_case and fallback to camelCase).
     * 
     * @param string $property The property name to get. 
     * 
     * @return mixed Return The property value. 
     */
    protected function getter(string $property): mixed 
    {
        $property = $this->getProperty($property);

        if ($property) {
            return $this->{$property};
        }

        return null;
    }

    /**
     * Converts a string or array argument into an array.
     *
     * @return void
     */
    protected function toArguments(): void
    {
        if (!$this->arguments) {
            $this->arguments = [];
            return;
        }

        if (is_array($this->arguments)) {
            return;
        }

        if ($this->arguments[0] === '[' || $this->arguments[0] === '{') {
            $this->arguments = json_decode($this->arguments, true);
        }
    }

    /**
     * Get decoded task output as array.
     * 
     * @return void
     */
    protected function toOutputs(): void
    {
        $default = ['response' => null, 'output' => null];

        if (!$this->outputs) {
            $this->outputs = $default;
            return;
        }

        if (is_array($this->output)) {
            return;
        }

        if ($this->outputs[0] === '[' || $this->outputs[0] === '{') {
            $outputs = json_decode($this->outputs, true) ?: $default;

            $output = $outputs['output'] ?? null;
            $response = $outputs['response'] ?? null;

            if($outputs === [] || (!$output && !$response)){
                $this->outputs = $default;
                return;
            }

            $this->outputs = ['output' => $output, 'response' => $response];
        }
    }

    /**
     * Ensure the internal system handler is initialized.
     * 
     * @return void
     */
    private function ensureSystem(): void
    {
        if (!$this->system instanceof Queue) {
            $this->system = new TaskQueue();
        }
    }
}