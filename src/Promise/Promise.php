<?php
/**
 * Luminova Framework promise object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Promise;

use \Closure;
use \Throwable;
use \ReflectionMethod;
use \ReflectionFunction;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Promise\Deferred;
use \Luminova\Promise\Helpers\{Queue, Helper};
use \Luminova\Exceptions\{ErrorCode, ErrorException, RuntimeException};

final class Promise implements PromiseInterface
{
    /**
     * Promise pending state.
     * 
     * @var string PENDING
     */
    public const PENDING = 'pending';

    /**
     * Promise resolved state.
     * 
     * @var string FULFILLED
     */
    public const FULFILLED = 'fulfilled';

    /**
     * Promise rejection state.
     * 
     * @var string REJECTED
     */
    public const REJECTED = 'rejected';

    /**
     * Promise is canceled.
     * 
     * @var bool $isCanceled
     */
    private bool $isCanceled = false;

    /**
     * Promise current state.
     * 
     * @var string $state
     */
    private string $state = self::PENDING;

    /**
     * Promise result value.
     * 
     * @var mixed $value
     */
    private mixed $value = null;

    /**
     * Promise handlers.
     * 
     * @var array<int,array<string,mixed>> $handlers
     */
    protected array $handlers = [];

    /**
     * waiting Unresolved promises.
     * 
     * @var bool $waiting
     */
    private bool $waiting = false;

    /**
     * Catch promise error callback handler.
     * 
     * @var (callable(Throwable $))|null $onError
     */
    private mixed $onError = null;

    /**
     * onResolved promise callback handler.
     * 
     * @var callable|null $onResolved
     */
    private mixed $onResolved = null;

    /**
     * onRejected promise callback handler.
     * 
     * @var callable|null $onRejected
     */
    private mixed $onRejected = null;

    /**
     * Canalled promise callback handler.
     * 
     * @var callable|null $onCancelled
     */
    private mixed $onCancelled = null;

    /**
     * Create a new Promise instance.
     *
     * A Promise allows you to manage values that are not immediately available, 
     * such as results from asynchronous operations. It provides a clean way to handle 
     * success, failure, or cancellation states in an operation.
     *
     * **How it works:**
     * - If you pass a function as the first argument (`$onResolved`), it receives two functions: 
     *   `$resolve` and `$reject`. Call `$resolve($value)` when the operation succeeds, or 
     *   `$reject($reason)` when it fails.
     *
     * - If you pass simple callbacks for `$onResolved` and `$onRejected`, 
     *   they are used as handlers when you manually call `$promise->resolve()` or `$promise->reject()`.
     *
     * @param (callable(mixed $resolve, ?callable $reject = null): void)|null $onResolved 
     *        The executor or handler function to call when the promise is created or resolved.
     * @param (callable(\Throwable $error): void)|null $onRejected 
     *        The callback to call when the promise is rejected.
     * 
     * @throws RuntimeException If the provided handlers are invalid or unsupported 
     *      and no error handler was registered to handler exceptions.
     * @link https://luminova.ng/docs/0.0.0/utilities/promise-object
     * 
     * @example Using an executor
     * ```php
     * $promise = new Promise(function ($resolve, $reject) {
     *     $result = Foo::doAsync();
     *     if ($result->isGood()) {
     *         $resolve($result->getValue());
     *     } else {
     *         $reject(new Exception('Something went wrong'));
     *     }
     * });
     * 
     * $promise->then(function (mixed $value) {
     *     echo "Got value: $value";
     * })->catch(function (Throwable $e) {
     *     echo "Error: " . $e->getMessage();
     * });
     * ```
     * 
     * @example Using direct resolve/reject handlers
     * ```php
     * $promise = new Promise(
     *     function (mixed $value) {
     *         echo "Resolved with: $value";
     *     },
     *     function (Throwable $error) {
     *         echo "Rejected: " . $error->getMessage();
     *     }
     * );
     * 
     * $promise->resolve('Hello');
     * ```
     */
    public function __construct(?callable $onResolved = null, ?callable $onRejected = null)
    {
        $this->isCanceled = false;

        if($onResolved) {
            $this->onCreate($onResolved, $onRejected);
            $onRejected = null;
            return;
        }

        $this->onRejected = $onRejected;
        $onRejected = null;
    }

    /**
     * Create a new promise instance, optionally with a task to handle its resolution.
     *
     * This method is a simple static factory for creating a promise object that can be 
     * resolved or rejected later.
     *
     * @param callable(mixed $value):mixed|null $onResolved Optional callback that runs when the promise is resolved.
     *     The resolved value is passed to this callback.
     * 
     * @return PromiseInterface Returns a new, pending promise instance.
     * @see deferred() - It is useful for handling asynchronous or deferred tasks where the result is not immediately available.
     *
     * @example Basic usage:
     * ```php
     * use Luminova\Promise\Promise;
     *
     * $promise = Promise::from();
     * $promise->then(function ($result) {
     *     echo "Got: $result";
     * });
     * $promise->resolve('Hello World');
     * ```
     *
     * @example With an resolve callback:
     * ```php
     * use Luminova\Promise\Promise;
     *
     * $promise = Promise::from(function ($resolve, $reject) {
     *     echo "Result is: " . $value;
     * });
     * ```
     */
    public static function from(?callable $onResolved = null): PromiseInterface
    {
        return new self($onResolved);
    }

    /**
     * Creates and returns a controllable deferred promise instance.
     *
     * A deferred object lets you manage a promise externally, you can create the promise now 
     * and resolve or reject it later. This is useful for handling results that depend on 
     * asynchronous operations or callbacks.
     *
     * @return Deferred Returns the deferred promise controller.
     *
     * @example Basic usage:
     * ```php
     * use Luminova\Promise\Promise;
     *
     * $deferred = Promise::deferred();
     *
     * $deferred->promise()
     *     ->then(fn($result) => echo "Done: $result")
     *     ->catch(fn($error) => echo "Failed: {$error->getMessage()}");
     *
     * // Later resolve
     * $deferred->resolve('Operation successful');
     * 
     * // or reject
     * // $deferred->reject(new Exception('Something went wrong'));
     * ```
     *
     * @example Async timeout:
     * ```php
     * use Luminova\Promise\Promise;
     * use Luminova\Components\Async;
     *
     * function callAsync(): PromiseInterface {
     *     $deferred = Promise::deferred();
     *
     *     Async::setTimeout(function () use ($deferred) {
     *         $deferred->resolve('Time: ' . date('H:i:s'));
     *     }, 1000);
     *
     *     return $deferred->promise();
     * }
     *
     * callAsync()->then(fn($value) => echo $value);
     * ```
     */
    public static function deferred(): Deferred
    {
        return new Deferred();
    }

    /**
     * Create a promise that resolves after a specified delay.
     *
     * This method pauses execution for a given number of milliseconds before 
     * automatically resolving the promise. It is useful for simulating 
     * asynchronous wait times or delaying operations in sequential tasks.
     *
     * @param int $ms The number of milliseconds to wait before resolving the promise.
     * @param mixed $value An optional value to resolve after delay (default: null).
     *
     * @return PromiseInterface Returns a promise that resolves after the specified delay.
     *
     * @example Basic usage:
     * ```php
     * use Luminova\Promise\Promise;
     *
     * Promise::delay(1000)->then(function () {
     *     echo "Resolved after 1 second.";
     * });
     * 
     * // With value 
     * 
     * Promise::delay(1000, 'done')
     *      ->then(fn($v) => echo $v); // outputs "done" after 1 second
     * ```
     *
     * @example With chained promises:
     * ```php
     * use Luminova\Promise\Promise;
     *
     * Promise::delay(500)
     *     ->then(function () {
     *         echo "Half a second passed.\n";
     *         return Promise::delay(1000);
     *     })
     *     ->then(function () {
     *         echo "Another second passed.\n";
     *     });
     * ```
     *
     * @example Using inside an async operation:
     * ```php
     * use Luminova\Promise\Promise;
     * use Luminova\Components\Async;
     *
     * Async::await(function () {
     *     echo "Waiting...\n";
     *     yield Promise::delay(2000);
     *     echo "Done after 2 seconds.";
     * });
     * ```
     */
    public static function delay(int $ms, mixed $value = null): PromiseInterface
    {
        return new self(function ($resolve) use ($ms, $value) {
            usleep($ms * 1000);
            $resolve($value);
        });
    }

    /**
     * Run a function safely inside a Promise.
     *
     * This will execute your function and wrap the result in a promise.
     * If the function throws an error, the promise is rejected.
     *
     * @param (callable(): mixed) $fn A function to run. Can return any value.
     * 
     * @return PromiseInterface Return a promise that resolves with the function’s return value,
     *                          or rejects if the function throws an error.
     *
     * @example - Promise Try Example:
     * ```php
     * use \Luminova\Promise\Promise;
     * 
     * $promise = Promise::try(function(){
     *      if(auth()){
     *          return  "Success";
     *      }
     *      
     *      throw new Exception('Authentication failed');
     * });
     * 
     * $promise->then(function ($value) {
     *     echo $value; // "Success"
     * })->catch(function ($error) {
     *     echo "Error: " . $error->getMessage();
     * });
     * ```
     */
    public static function try(callable $fn): PromiseInterface
    {
        return new self(function (callable $resolve, callable $reject) use ($fn) {
            try {
                $resolve($fn());
            } catch (Throwable $e) {
                $reject($e);
            }
        });
    }

    /**
     * Create a promise that settles as soon as the first promise finishes.
     *
     * This is useful when you want the *fastest* result, 
     * no matter if it’s success or failure.
     *
     * @param PromiseInterface[] $promises A list of promises to race.
     * 
     * @return PromiseInterface Return a promise that resolves/rejects with the first one to finish.
     *
     * @example - Promise Race Example:
     * ```php
     * $promises = [
     *     Promise::try(fn() => sleep(2) || 42),
     *     Promise::try(fn() => 24),
     * ];
     * 
     * $result = Promise::race($promises);
     * $result->then(function ($value) {
     *     echo $value; // "24" (faster one wins)
     * });
     * ```
     */
    public static function race(iterable $promises): PromiseInterface
    {
        $queue = Queue::queue();

        return new self(function (callable $resolve, callable $reject) use ($promises, $queue) {
            $finish = false;

            foreach ($promises as $promise) {
                $queue->push($promise);

                $p = Helper::resolve($promise)
                    ->then($resolve, $reject)
                    ->finally(function ($value) use (&$finish, $queue): mixed {
                        $finish = true;
                        $queue();
                        return $value;
                    });

                if ($finish || !$p->is(self::PENDING)) {
                    break;
                }
            }
        }, $queue);
    }

    /**
     * Wait until *all* promises finish successfully.
     *
     * If any promise rejects, the whole thing rejects immediately.
     *
     * @param PromiseInterface[] $promises A list of promises.
     * 
     * @return PromiseInterface Return a promise that resolves with an array of results.
     *
     * @example - Promise All Example:
     * ```php
     * $promises = [
     *     Promise::try(fn() => 10),
     *     Promise::try(fn() => 20),
     * ];
     * 
     * $result = Promise::all($promises);
     * $result->then(function ($values) {
     *     print_r($values); // [10, 20]
     * });
     * ```
     */
    public static function all(iterable $promises): PromiseInterface
    {
        $queue = Queue::queue();
        return new self(function (callable $resolve, callable $reject) use ($promises, $queue) {
            $results = [];
            $remaining = count($promises);
            $finish = false;

            if ($remaining === 0) {
                $resolve([]);
                return;
            }

            foreach ($promises as $index => $promise) {
                $queue->push($promise);
                $p = $promise->then(
                    function ($value) use (&$results, &$remaining, $resolve, $index) {
                        $results[$index] = $value;
                        $remaining--;

                        if ($remaining === 0) {
                            $resolve($results);
                        }
                    },
                    function ($reason) use ($reject, $queue, &$finish){
                        $finish = false;
                        $reject(Helper::reason($reason));
                        $queue();
                    }
                );

                if ($finish || $p->is(self::REJECTED)) {
                    break;
                }
            }
        }, $queue);
    }

    /**
     * Return the first successful promise.
     *
     * If every promise fails, the whole thing rejects with all errors.
     *
     * @param PromiseInterface[] $promises A list of promises.
     * 
     * @return PromiseInterface Returns a promise that resolves with the first fulfilled value,
     *                          or rejects if all fail.
     *
     * @example - Promise Any Example:
     * ```php
     * $promises = [
     *     Promise::try(fn() => throw new Exception('Fail 1')),
     *     Promise::try(fn() => 42),
     * ];
     * 
     * $result = Promise::any($promises);
     * $result->then(function ($value) {
     *     echo $value; // "42"
     * });
     * ```
     */
    public static function any(iterable $promises): self
    {
        return new self(function (callable $resolve, callable $reject) use ($promises) {
            $errors = [];
            $remaining = count($promises);
            $finish = false;

            if ($remaining === 0) {
                $reject(new RuntimeException('No promises provided.'));
                return;
            }

            foreach ($promises as $promise) {
                $promise->then(
                    $resolve,
                    function ($reason) use (&$errors, &$remaining, $reject, &$finish) {
                        $errors[] = $reason;
                        $remaining--;

                        if ($remaining === 0) {
                            $reject(Helper::reason($reason));
                            $finish = false;
                        }
                    }
                );
            }
        });
    }

    /**
     * Wait until *all* promises finish, no matter success or failure.
     *
     * Unlike `all()`, this never rejects. Instead it gives you the outcome of each one.
     *
     * @param PromiseInterface[] $promises A list of promises.
     * 
     * @return PromiseInterface Return a promise that resolves with an array of results.
     *                          Each result contains a `status` and either `value` or `reason`.
     *
     * @example
     * ```php
     * $promises = [
     *     Promise::try(fn() => 42),
     *     Promise::try(fn() => throw new Exception('Error')),
     * ];
     * 
     * $result = Promise::allSettled($promises);
     * $result->then(function (array $results) {
     *     print_r($results);
     *     // [
     *     //   ['status' => 'fulfilled', 'value' => 42],
     *     //   ['status' => 'rejected', 'reason' => Exception]
     *     // ]
     * });
     * ```
     */
    public static function allSettled(iterable $promises): PromiseInterface
    {
        return new self(function (callable $resolve) use ($promises): void {
            $results = [];
            $remaining = count($promises);

            if ($remaining === 0) {
                $resolve([]);
                return;
            }

            foreach ($promises as $index => $promise) {
                $promise->then(
                    function ($value) use (&$results, &$remaining, $resolve, $index) {
                        $results[$index] = [
                            'status' => self::FULFILLED,
                            'value' => $value,
                        ];
                        $remaining--;

                        if ($remaining === 0) {
                            $resolve($results);
                        }
                    },
                    function ($reason) use (&$results, &$remaining, $resolve, $index) {
                        $results[$index] = [
                            'status' => self::REJECTED,
                            'reason' => $reason,
                        ];
                        $remaining--;

                        if ($remaining === 0) {
                            $resolve($results);
                        }
                    }
                );
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function then(?callable $onResolved = null, ?callable $onRejected = null): PromiseInterface
    {
        if ($this->value instanceof PromiseInterface) {
            return $this->value->then($onResolved, $onRejected);
        }

        $promise = new self();

        $this->instance($promise, $onResolved, $onRejected);

        return $promise;
    }

    /**
     * {@inheritdoc}
     */
    public function catch(callable $onCatch, ?string $type = null): PromiseInterface
    {
        return $this->instance(new self(), null, $onCatch);
    }

    /**
     * {@inheritdoc}
     */
    public function error(callable $onError): self
    {
        $this->onError = $onError;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function canceled(callable $onCancelled): self
    {
        $this->onCancelled = $onCancelled;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function finally(callable $onFinally): PromiseInterface
    {
        return $this->then(
            fn (mixed $value): mixed => $this->done($onFinally, $value), 
            fn (mixed $reason): mixed => $this->done($onFinally, $reason)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(mixed $value): void
    {
        if (!$this->is(self::PENDING)) {
            $this->isCanceled = true;
            Helper::rejection($this->onError, 'Cannot resolve a promise that is already resolved.');
            return;
        }

        $this->transition(self::FULFILLED, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(mixed $reason): void
    {
        if (!$this->is(self::PENDING)) {
            $this->isCanceled = true;
            Helper::rejection($this->onError, 'Cannot reject a promise that is already resolved.');
            return;
        }

        $this->transition(self::REJECTED, Helper::reason($reason));
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(mixed $reason = null): void
    {
        if (!$this->is(self::PENDING) || $this->isCanceled) {
            return;
        }
  
        $this->isCanceled = true;
        $this->waiting = false;
        $this->state = self::REJECTED;
        $this->value = $reason ?? new RuntimeException('Promise was canceled.');
 
        if($this->onCancelled){
            try{
                ($this->onCancelled)($this->value, $this->state);
            }catch(RuntimeException $e){
                Helper::rejection($this->onError, 
                    'onCancelled failed with error: ' . $e->getMessage(),
                    $e->getCode()
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function wait(int $timeout = 1000): mixed
    {
        if($this->waiting){
            $this->isCanceled = true;
            Helper::rejection($this->onError, 'Cannot call wait when promise is already waiting.');
            return null;
        }

        $this->waiting = true;
        $this->waitUntil(microtime(true), $timeout / 1000);

        if ($this->value instanceof self) {
            return $this->value->is(self::PENDING) 
                ? $this->value->wait($timeout) 
                : $this->value->value();
        }

        if (!$this->is(self::PENDING) || ($this->value instanceof Throwable)) {
            return $this->value;
        }

        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function is(string $state = self::FULFILLED): bool
    {
        return $this->state === $state;
    }

    /**
     * {@inheritdoc}
     */
    public function state(): string
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     */
    public function value(): mixed 
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get the last task service index id.
     * 
     * @return int Return the index id of last added task.
     */
    protected function getId(): int
    {
        return ($this->handlers === []) 
            ? 0 
            : (array_key_last($this->handlers) ?? 0);
    }

    /**
     * Set the promise reject or fulfilled value.
     * 
     * @param mixed $value The promise value.
     * 
     * @return void 
     */
    protected function setValue(mixed $value): void 
    {
        $this->value = $value;
    }

    /**
     * Defers the execution of promise handlers.
     *
     * @param PromiseInterface $promise The promise to be deferred.
     * @param (callable(mixed $input):void)|null $onResolved Callback to be executed when the promise is resolved.
     * @param (callable(mixed $input):void)|null $onRejected Callback to be executed when the promise is rejected.
     *
     * @return static Return instance of promise interface.
     */
    protected function instance(PromiseInterface $promise, ?callable $onResolved, ?callable $onRejected): self
    {
        $promise->handlers = [];
        $this->handlers[] = [
            'promise' => $promise, 
            'fn' => fn() => $this->handler($promise, $onResolved, $onRejected)
        ];

        if (!$this->is(self::PENDING)) {
            $this->execute();
        }

        return $this;
    }

    /**
     * Waits for the promise to resolve or reject until a timeout is reached.
     *
     * @param float $start The starting timestamp of the wait operation.
     * @param float $timeout The maximum time (in seconds) to wait for the promise to settle.
     *
     * @return void
     */
    private function waitUntil(float $start, float $timeout): void
    {
        while ($this->is(self::PENDING) && $this->handlers !== []) {
            if ((microtime(true) - $start) >= $timeout) {
                if($this->is(self::PENDING)){
                    $this->reject('Timeout waiting for promise resolution.');
                    break;
                }

                $this->isCanceled = true;
                Helper::rejection($this->onError, 'Timeout waiting for promise resolution.');
                break;
            }

            foreach ($this->handlers as $handler) {
                $this->call($handler);
            }

            usleep(1000);
        }

        $this->waiting = false;
    }

    /**
     * Handles the promise resolution or rejection based on the current state and provided callbacks.
     *
     * @param PromiseInterface $promise The promise to be resolved or rejected.
     * @param (callable(mixed $input):void)|null $onResolved The callback to be executed when the promise is resolved.
     * @param (callable(mixed $input):void)|null $onRejected The callback to be executed when the promise is rejected.
     *
     * @return void
     */
    private function handler(PromiseInterface $promise, ?callable $onResolved, ?callable $onRejected): void 
    {
        $result = $this->value;
        try {
            if ($onResolved && $this->is(self::FULFILLED)) {
                $result = $onResolved($this->value);
            } elseif ($onRejected && $this->is(self::REJECTED)) {
                $result = $onRejected($this->value);
            }

            if ($result instanceof PromiseInterface) {
                if($result->is(self::PENDING)){
                    $result->then(fn($v) => $promise->resolve($v), fn($e) => $promise->cancel($e));
                    //$result->then(fn($v) => $promise->resolve($v), fn($e) => $promise->reject($e));
                    return;
                }

                // Resolve external promise
                $promise->resolve($result);
            } elseif($this->is(self::REJECTED)){
                $promise->reject($result);
            }else{
                $result = Helper::fromThirdPartyPromise($result);
                $promise->resolve($result);
            }
        } catch (Throwable $e) {
            $result = $e;
            $promise->reject($e);
        }

        $this->setValue($result);
        $result = null;
    }

    /**
     * Finalizes the promise chain by executing the provided callback and handling its result.
     *
     * @param (callable(mixed $input):mixed) $onFinally The callback function to be executed in the finalization step.
     * @param mixed $value The value passed to the finalization callback.
     *
     * @return mixed Returns one of the following:
     *               - The result of the $onFinally callback if it's not a Promise or thenable.
     *               - A new Promise if the result is thenable but not a Promise instance.
     *               - The original $value if an exception occurs during execution.
     *               - A Throwable if an exception occurs and no error handler is set.
     */
    private function done(callable $onFinally, mixed $value): mixed
    {
        try {
            $value = $onFinally($value);
        } catch (Throwable $e) {
            $this->isCanceled = true;
            if($this->onError){
                Helper::rejection($this->onError, $e);
            }
            return $e;
        }

        if ($value instanceof PromiseInterface) {
            return $value;
        }

        return Helper::fromThirdPartyPromise($value);
    }

    /**
     * Transitions the promise to a new state with a given value.
     *
     * @param string $state The new state to transition to (FULFILLED or REJECTED).
     * @param mixed $value The value to set for the promise (resolution value or rejection reason).
     *
     * @return void
     *
     * @throws ErrorException If an invalid state transition is attempted or if the promise references itself.
     */
    private function transition(string $state, mixed $value): void 
    {
        if (!$this->is(self::PENDING)) {
            if ($state === $this->state && $value === $this->value) {
                return;
            }

            $this->isCanceled = true;
            Helper::rejection($this->onError, ($this->state === $state)
                ? sprintf('The promise is already %s.', $state)
                : sprintf('Cannot change a %s promise to %s', $this->state, $state)
            , ErrorCode::LOGIC_ERROR);
            return;
        }

        if ($value === $this) {
            $this->isCanceled = true;
            Helper::rejection(
                $this->onError, 
                'Cannot fulfill or reject a promise with itself', 
                ErrorCode::LOGIC_ERROR
            );
            return;
        }

        $this->state = $state;
        $this->value = $value;
        $this->execute();
    }

    /**
     * Executes a promise handler.
     *
     * @return void
     */
    private function execute(): void
    {
        if($this->handlers === []){
            if($this->onResolved && $this->is(self::FULFILLED)){
                $this->call(['promise' => $this, 'fn' => $this->onResolved]);
            }elseif($this->onRejected && $this->is(self::REJECTED)){
                $this->call(['promise' => $this, 'fn' => $this->onRejected]);
            }
    
            return;
        }

        while ($handler = array_shift($this->handlers)) {
            if($this->isCanceled){
                $this->handlers = [];
                break;
            }

            $this->call($handler);
        }

        $this->handlers = [];
    }

    /**
     * Executes a promise handler.
     *
     * @param array $handler An array containing the promise handler information.
     *                       Expected to have 'promise' and 'fn' keys.
     *
     * @return void
     */
    private function call(array $handler): void
    {
        if($handler === [] || $this->isCanceled){
            return;
        }
    
        if ($handler['promise'] instanceof PromiseInterface) {
            $promise = $handler['promise'];
            
            try {
                ($handler['fn'])($promise->value);
            } catch (Throwable $e) {
                $this->setValue($e);
                $promise->reject($e);
            }

            $this->remove($promise);
            return;
        }

        $message = 'Promise failed, no internal function.';
        if($this->onError){
            $this->isCanceled = true;
            Helper::rejection($this->onError, $message);
            return;
        }

        $this->reject($message);
    }

    /**
     * Removes a specified promise from the list of handlers.
     *
     * @param PromiseInterface|null $promise The promise instance to remove. 
     *      If null or not a valid PromiseInterface, no action is taken.
     * 
     * @return void
     */
    private function remove(?PromiseInterface $promise): void
    {
        if($this->handlers === [] || !($promise instanceof PromiseInterface)){
            return;
        }

        $this->handlers = array_values(
            array_filter($this->handlers, fn ($handler) => $handler['promise'] !== $promise)
        );
    }

    /**
     * Initializes the promise with the provided resolution and rejection callbacks.
     *
     * @param (callable(mixed $resolve, ?callable $reject = null): void)|null $onResolved 
     * @param (callable(\Throwable $error): void)|null $onRejected
     *
     * @return void
     * @throws RuntimeException If an invalid callback type is provided and not onError handler.
     * > Reference for cleanup to avoid capturing $this in closures
     */
    private function onCreate(callable $onResolved, ?callable $onRejected = null): void
    {
        $promise = &$this;

        try {
            $isClosure = $onResolved instanceof Closure;
            $ref = match (true) {
                (is_array($onResolved) && count($onResolved) === 2)
                    => new ReflectionMethod(...$onResolved),
                (!$isClosure && is_object($onResolved))
                    => new ReflectionMethod($onResolved, '__invoke'),
                ($isClosure || is_callable($onResolved))
                    => new ReflectionFunction($onResolved),
                default => null,
            };

            if ($ref === null) {
                Helper::rejection(
                    $promise->onError,
                    new RuntimeException('Invalid callback type provided.')
                );
                return;
            }
        } catch (Throwable $e) {
            Helper::rejection($promise->onError, $e);
            return;
        }

        try {
            $argCount = $ref->getNumberOfParameters();

            if ($argCount === 0) {
                $onResolved();
                return;
            }

            if ($argCount === 1 && Helper::isDirectValue($ref->getParameters())) {
                $promise->onResolved = $onResolved;
                $promise->onRejected = $onRejected;
                return;
            }

            $onResolved(
                static function (mixed $value) use (&$promise): void {
                    if ($promise === null) {
                        return;
                    }

                    $promise->resolve($value);
                    $promise = null;
                },
                static function (mixed $reason) use (&$promise): void {
                    if ($promise === null) {
                        return;
                    }

                    $promise->reject($reason);
                    $promise = null;
                }
            );
        } catch (Throwable $e) {
            $promise->reject($e);
        } finally {
            $promise = null;
        }
    }
}