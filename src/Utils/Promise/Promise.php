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
namespace Luminova\Utils\Promise;

use \Luminova\Utils\Promise\Helper;
use \Luminova\Utils\Promise\Queue;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\ErrorException;
use \ReflectionFunction;
use \ReflectionMethod;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionIntersectionType;
use \Closure;
use \Throwable;

class Promise implements PromiseInterface
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
     * @var callable|null $onError
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
     * Initializes a new Promise object with optional callback handlers.
     *
     * - If `$onResolved` is provided and is not a callable or if the argument is not a callable type, 
     *   it is treated as a direct resolution handler. In this case, `$onResolved` and `$onRejected` are assigned directly.
     *
     * - Otherwise, if `$onResolved` is callable, it is invoked with two arguments (`resolve` and `reject`) 
     *   to allow external resolution or rejection of the promise.
     *
     * - If `$onResolved` is `null`, the constructor assigns `$onRejected` as the rejection callback.
     * 
     * - If the callback argument types do not align with the expected promise behavior, 
     *   the promise is immediately rejected with a `RuntimeException`.
     * 
     * @param callable|null $onResolved Optional callback invoked when the promise is resolved.
     * @param callable|null $onRejected Optional callback invoked when the promise is rejected.
     * 
     * @link https://luminova.ng/docs/0.0.0/utils/promise
     * 
     * @example - Using an Executor to Handle Asynchronous Operations
     * ```php
     * $promise = new Promise(function (callable $resolve, callable $reject) {
     *      $async = Foo::doAsync();
     *      if ($async) {
     *          $resolve($async->getResult());
     *      } else {
     *          $reject($async->getError());
     *      }
     * }); 
     * 
     * $promise->then(function (string $value) {
     *      echo $value;
     * })->catch(function (\Throwable $e) {
     *      echo $e->getMessage();
     * });
     * ```
     * 
     * @example - Directly Handling Resolved and Rejected States
     * ```php
     * $promise = new Promise(
     *      function (string $value) {
     *          echo "Resolved value: " . $value . "\n";
     *      },
     *      function (\Throwable $reason) {
     *          echo "Rejected with reason: " . $reason->getMessage() . "\n";
     *      }
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
     * Returns a new instance of the PromiseInterface.
     *
     * @param callable|null $executor Invoked when the promise fulfills.
     *                                    Receives the resolved value.
     * 
     * @return PromiseInterface Return a new static promise instance.
     *
     * @example Usage Example:
     * ```php
     * $deferred = Promise::deferred();
     * $deferred->then(function (string $result) {
     *     echo "Result: " . $result;
     * });
     * $deferred->resolve('Hello World');
     * ```
     * 
     * @example Another Example:
     * ```php
     * use Luminova\Utils\Async;
     * 
     * function callAsync() {
     *      $d = Promise::deferred();
     *      Async::setTimeout(function() use($d){
     *          $d->resolve('Value: ' . date('H:i:s'));
     *      }, 1000);
     *      return $d;
     * }
     * callAsync()->then(function($value) {
     *      echo $value;
     * });
     * ```
     */
    public static function deferred(?callable $executor = null): PromiseInterface 
    {
        return new self($executor);
    }

    /**
     * {@inheritdoc}
     */
    public function then(
        ?callable $onResolved = null, 
        ?callable $onRejected = null
    ): PromiseInterface
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

        $this->transition(
            self::REJECTED, 
            ($reason instanceof Throwable) 
                ? $reason 
                : (($reason && is_string($reason)) ? new ErrorException($reason) : $reason)
        );
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
     * Executes a callable within a promise.
     *
     * @param callable $fn A callable function to execute.
     * @return PromiseInterface Return a promise that resolves with the result of the callable.
     *
     * @example
     * ```php
     * $promise = Promise::try(fn() => "Success");
     * $promise->then(function ($value) {
     *     echo $value; // Outputs: Success
     * })->catch(function (\Throwable $error) {
     *    echo "Error: " . $error->getMessage();
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
     * Resolves or rejects as soon as any promise in the array resolves or rejects.
     *
     * @param PromiseInterface[] $promises An array of promises.
     * @return PromiseInterface Return a promise that resolves or rejects with the first settled promise.
     *
     * @example
     * ```php
     * $promises = [
     *     Promise::try(fn() => sleep(2) || 42),
     *     Promise::try(fn() => 24),
     * ];
     * 
     * $result = Promise::race($promises);
     * $result->then(function ($value) {
     *     echo $value; // Outputs: 24
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
     * Resolves when all promises in the array resolve.
     *
     * @param PromiseInterface[] $promises An array of promises.
     * @return PromiseInterface Return a promise that resolves with an array of values.
     *
     * @example
     * ```php
     * $promises = [
     *     Promise::try(fn() => 10),
     *     Promise::try(fn() => 20),
     * ];
     * 
     * $result = Promise::all($promises);
     * $result->then(function ($values) {
     *     print_r($values); // Outputs: [10, 20]
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
                        $reject($reason);
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
     * Resolves when all the promises in the array have settled.
     * 
     * @param PromiseInterface[] $promises An array of promises.
     * @return PromiseInterface Return a promise that resolves with an array of results.
     *
     * @example
     * ```php
     * $promises = [
     *     Promise::try(fn() => 42),
     *     Promise::try(fn() => throw new Exception('Error')),
     * ];
     * 
     * $result = Promise::allSettled($promises);
     * $result->then(function ($settledResults) {
     *     print_r($settledResults);
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
     * Resolves with the value of the first resolved promise.
     *
     * @param PromiseInterface[] $promises An array of promises.
     * @return PromiseInterface Return a promise that resolves with the first fulfilled value.
     *
     * @example
     * ```php
     * $promises = [
     *     Promise::try(fn() => throw new Exception('Error')),
     *     Promise::try(fn() => 42),
     * ];
     * 
     * $result = Promise::any($promises);
     * $result->then(function ($value) {
     *     echo $value; // Outputs: 42
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
                $reject(new RuntimeException("No promises provided."));
                return;
            }

            foreach ($promises as $promise) {
                $promise->then(
                    $resolve,
                    function ($reason) use (&$errors, &$remaining, $reject, &$finish) {
                        $errors[] = $reason;
                        $remaining--;

                        if ($remaining === 0) {
                            $reject($errors);
                            $finish = false;
                        }
                    }
                );
            }
        });
    }

    /**
     * {@inheritdoc}
     * 
     * > **Note:** Use `canceled` method to handle exceptions that may throw.
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
                : $this->value->getResult();
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
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get the promise reject or fulfilled value.
     * 
     * @return mixed Return the promise value, otherwise null.
     */
    public function getResult(): mixed 
    {
        return $this->value;
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
     * @param callable|null $onResolved Callback to be executed when the promise is resolved.
     * @param callable|null $onRejected Callback to be executed when the promise is rejected.
     *
     * @return self
     */
    protected function instance(
        PromiseInterface $promise,
        ?callable $onResolved, 
        ?callable $onRejected
    ): self
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
     * @param callable|null $onResolved The callback to be executed when the promise is resolved.
     * @param callable|null $onRejected The callback to be executed when the promise is rejected.
     *
     * @return void
     */
    private function handler(
        PromiseInterface $promise,
        ?callable $onResolved, 
        ?callable $onRejected
    ) {
        $result = $this->value;
        try {
            if ($onResolved && $this->is(self::FULFILLED)) {
                $result = $onResolved($this->value);
            } elseif ($onRejected && $this->is(self::REJECTED)) {
                $result = $onRejected($this->value);
            }

            if ($result instanceof PromiseInterface) {
                if($result->is(self::PENDING)){
                    $result->then([$promise, 'resolve'], [$promise, 'cancel']);
                    return;
                }
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
     * @param callable $onFinally The callback function to be executed in the finalization step.
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
     * 
     * > **Note:** Use `canceled` method to handle exceptions that may throw.
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
            , ErrorException::LOGIC_ERROR);
            return;
        }

        if ($value === $this) {
            $this->isCanceled = true;
            Helper::rejection(
                $this->onError, 
                'Cannot fulfill or reject a promise with itself', 
                ErrorException::LOGIC_ERROR
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
            try {
                $handler['fn']($handler['promise']->value);
            } catch (Throwable $e) {
                $this->setValue($e);
                $handler['promise']->reject($e);
            }

            $this->remove($handler['promise']);
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

        $this->handlers = array_filter($this->handlers, function ($handler) use ($promise) {
            return $handler['promise'] !== $promise;
        });

        $this->handlers = array_values($this->handlers);
    }

    /**
     * @param \ReflectionParameter[] $parameters
     * @param bool $strict
     * 
     * @return bool
     */
    private static function isDirect(array $parameters, bool $strict = false): bool
    {
        if($parameters === []){
            return false;
        }

        $type = $parameters[0]->getType();
        if (!$type) {
            return true;
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName() !== 'callable';
        }

        return $strict 
            ? Helper::isStrictlyDirect($type)
            : ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType);
    }

    /**
     * Initializes the promise with the provided resolution and rejection callbacks.
     * 
     * @param callable $onResolved The callback to be executed when the promise is resolved.
     *                             This can be an array (for object methods), an object with __invoke,
     *                             a Closure, or a string (for global functions).
     * @param callable|null $onRejected Optional. The callback to be executed when the promise is rejected.
     *
     * @return void
     * @throws RuntimeException If an invalid callback type is provided.
     */
    private function onCreate(callable $onResolved, ?callable $onRejected = null): void
    {
        $ref = match (true) {
            is_array($onResolved) => new ReflectionMethod($onResolved[0], $onResolved[1]),
            is_object($onResolved) && !($onResolved instanceof Closure) => new ReflectionMethod($onResolved, '__invoke'),
            $onResolved instanceof Closure || is_string($onResolved) => new ReflectionFunction($onResolved),
            default => null,
        };

        if($ref === null){
            $this->reject(new RuntimeException('Invalid callback type provided.'));
            return;
        }

        try {
            $arguments = $ref->getNumberOfParameters();
            if ($arguments === 0) {
                $onResolved();
                return;
            }

            $promise = &$this;

            if($arguments === 1 && self::isDirect($ref->getParameters())) {
                $promise->onResolved = $onResolved;
                $promise->onRejected = $onRejected;
                return;
            }

            $onResolved(
                static function (mixed $value) use (&$promise): void {
                    if ($promise !== null) {
                        $promise->resolve($value);
                        $promise = null;
                    }
                },
                static function (mixed $reason) use (&$promise): void {
                    if ($promise !== null) {
                        $promise->reject($reason);
                        $promise = null;
                    }
                }
            );
        } catch (Throwable $e) {
            $this->reject($e);
        }
    }
}