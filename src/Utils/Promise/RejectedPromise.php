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
use \Luminova\Utils\Promise\{Helper, Promise};
use \Throwable;

final class RejectedPromise implements PromiseInterface
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
     * @param Throwable $reason
     */
    public function __construct(private Throwable $reason){}

    /**
     * {@inheritdoc}
     */
    public function then(?callable $onResolved = null, ?callable $onRejected = null): PromiseInterface
    {
        if (null === $onRejected || $this->isError) {
            return $this;
        }

        try {
            return Helper::resolve($onRejected($this->reason));
        } catch (Throwable $e) {
            return new RejectedPromise($e);
        }
    }


    /**
     * {@inheritdoc}
     */
    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->then(null, $onRejected);
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
            fn (Throwable $reason): PromiseInterface => Helper::finally($onFinally, $reason, false), 
            fn (Throwable $reason): PromiseInterface => Helper::finally($onFinally, $reason, false),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function wait(int $timeout = 1000): mixed
    {
        return $this->reason;
    }

    /**
     * {@inheritdoc}
     */
    public function is(string $state = Promise::REJECTED): bool
    {
        return $this->getState() === $state;
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(mixed $value): void
    {
        Helper::rejection($this->onError, 'Cannot resolve a rejected promise');
    }

    /**
     * {@inheritdoc}
     */
    public function reject(mixed $reason): void
    {
        if ($reason !== $this->reason) {
            Helper::rejection($this->onError, 'Cannot reject a rejected promise');
        }
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
        return Promise::REJECTED;
    }
}