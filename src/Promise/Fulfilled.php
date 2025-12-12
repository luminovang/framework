<?php
/**
 * Luminova Framework fulfilled promise object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Promise;

use \Throwable;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Promise\{Promise, Helpers\Helper};

final class Fulfilled implements PromiseInterface
{
    /**
     * @var callable|null $onError
     */
    private mixed $onError = null;

    /**
     * @var bool $isError
     */
    private bool $isError = false;

    /**
     * Creates a promise that is already fulfilled (resolved).
     *
     * This class is useful when you want to return a promise 
     * that is immediately considered successful, without needing to call `resolve()` later.
     *
     * @param mixed $value The value the promise is fulfilled with.
     *
     * @throws RuntimeException If you try to pass another promise as the value.
     *
     * @example - Example:
     * ```php
     * use Luminova\Promise\Fulfilled;
     * 
     * $promise = new Fulfilled(42);
     * 
     * $promise->then(function ($value) {
     *     echo "Promise resolved with: " . $value; // Outputs: 42
     * });
     * ```
     */
    public function __construct(private mixed $value)
    {
        if ($this->value instanceof PromiseInterface) {
            $this->isError = true;

            Helper::rejection($this->onError, new RuntimeException(
                'You cannot create a Fulfilled with a promise.'
            ));
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function then(?callable $onResolved = null, ?callable $onRejected = null): PromiseInterface
    {
        if (null === $onResolved || $this->isError) {
            return $this;
        }

        try {
            return Helper::resolve($onResolved($this->value));
        } catch (Throwable $e) {
            return new Rejected($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function catch(callable $onRejected): PromiseInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function canceled(callable $onCancelled): PromiseInterface 
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function finally(callable $onFinally): PromiseInterface
    {
        $resolved = fn (mixed $value): PromiseInterface => Helper::finally($onFinally, $value);
        return $this->then($resolved, $resolved);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(int $timeout = 1000): mixed
    {
        return $this->value;
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
    public function is(string $state = Promise::FULFILLED): bool
    {
        return $this->state() === $state;
    }

    /**
     * {@inheritdoc}
     */
    public function state(): string
    {
        return Promise::FULFILLED;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(mixed $value): void
    {
        if ($value !== $this->value) {
            $this->isError = true;
            Helper::rejection($this->onError, 'Cannot resolve a fulfilled promise');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reject(mixed $reason): void
    {
       $this->isError = true;
       Helper::rejection($this->onError, 'Cannot reject a fulfilled promise');
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(mixed $reason = null): void {}

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
    public function getState(): string
    {
        return $this->state();
    }
}