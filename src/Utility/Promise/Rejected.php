<?php
/**
 * Luminova Framework rejected promise object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility\Promise;

use \Throwable;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Utility\Promise\{Promise, Helpers\Helper};

final class Rejected implements PromiseInterface
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
     * Creates a promise that is already rejected.
     *
     * This class is useful when you need to return a promise that is 
     * immediately in a "rejected" state instead of creating one and 
     * calling `reject()` later.
     *
     * @param Throwable $reason The error or exception that caused the rejection.
     *
     * @example - Example:
     * ```php
     * use Luminova\Utility\Promise\Rejected;
     * 
     * $promise = new Rejected(new \Exception("Something went wrong"));
     * 
     * $promise->catch(function (\Throwable $e) {
     *     echo "Promise rejected: " . $e->getMessage();
     * });
     * ```
     */
    public function __construct(private Throwable $reason) {}

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
            return new Rejected($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function catch(callable $onRejected): PromiseInterface
    {
        return $this->then(onRejected: $onRejected);
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
        $reason = fn (Throwable $reason): PromiseInterface => Helper::finally($onFinally, $reason, false);
        return $this->then($reason, $reason);
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
    public function value(): mixed 
    {
        return $this->reason;
    }

    /**
     * {@inheritdoc}
     */
    public function is(string $state = Promise::REJECTED): bool
    {
        return $this->state() === $state;
    }

    /**
     * {@inheritdoc}
     */
    public function state(): string
    {
        return Promise::REJECTED;
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
        return $this->state();
    }
}