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

use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Utils\Promise\{Helper, Promise};
use \Throwable;

final class FulfilledPromise implements PromiseInterface
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
     * @param mixed $value
     */
    public function __construct(private mixed $value)
    {
        if ($value instanceof PromiseInterface) {
            $this->isError = true;
            Helper::rejection($this->onError, new RuntimeException(
                'You cannot create a FulfilledPromise with a promise.'
            ));
            return;
        }

        $this->value = $value;
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
            return new RejectedPromise($e);
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
        return $this->then(
            fn (mixed $value): PromiseInterface => Helper::finally($onFinally, $value), 
            fn (mixed $value): PromiseInterface => Helper::finally($onFinally, $value),
        );
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
    public function is(string $state = Promise::FULFILLED): bool
    {
        return $this->getState() === $state;
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
        return Promise::FULFILLED;
    }
}