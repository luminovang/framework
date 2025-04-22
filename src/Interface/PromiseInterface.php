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

use \Luminova\Exceptions\RuntimeException;

/**
 * Interface representing a promise that resolves or rejects asynchronously
 *
 * @see https://luminova.ng/docs/0.0.0/utils/promise for more details on promises.
 * 
 * @property string PENDING  The promise is still in progress.
 * @property string FULFILLED The promise has been resolved.
 * @property string REJECTED The promise has been rejected.
 */
interface PromiseInterface
{
    /**
     * Attaches fulfillment and rejection handlers to the promise.
     *
     * @param callable|null $onResolve Invoked when the promise fulfills.
     *                                    Receives the resolved value.
     * @param callable|null $onReject  Invoked when the promise is rejected.
     *                                    Receives the rejection reason.
     *
     * @return PromiseInterface Return a new promise resolved with the handler's return value.
     * 
     * @example
     * ```
     * $promise->then(
     *     function ($value) {
     *         echo "Fulfilled with: " . $value;
     *     },
     *     function ($reason) {
     *         echo "Rejected with: " . $reason;
     *     }
     * );
     * ```
     */
    public function then(
        ?callable $onResolve = null,
        ?callable $onReject = null
    ): PromiseInterface;

    /**
     * Appends a handler to be executed regardless of the promise's outcome.
     *
     * @param callable $onAlways Invoked when the promise is either fulfilled or rejected.
     *                            Receives the fulfillment or rejection value.
     *
     * @return PromiseInterface Return a new promise resolved with the handler's return value.
     *
     * @example - Example Usage:
     * 
     * ```php
     * $promise->finally(function ($result) {
     *     echo "Promise has settled with: " . $result;
     * });
     * ```
     */
    public function finally(callable $onAlways): PromiseInterface;

    /**
     * Registers an error handler that will be called to handle promise rejection.
     *
     * @param callable $onCatch Invoked when the promise is rejected.
     *                             Receives the rejection reason.
     *
     * @return PromiseInterface Return a new promise resolved with either the rejection handler's value 
     *                          or the original value if the promise was fulfilled.
     *
     * @example - Example Usage:
     * 
     * ```php
     * $promise->catch(function (\Throwable $reason) {
     *     echo "Error occurred: " . $reason->getMessage();
     * });
     * ```
     */
    public function catch(callable $onCatch): PromiseInterface;

    /**
     * Registers an cancellation handler that will be called when promise `cancel` is invoked.
     *
     * @param callable $onCanceled Invoked when the promise is `cancel` method is trigged.
     *
     * @return self Return a instance of the promise.
     *
     * @example - Example Usage:
     * 
     * ```php
     * $promise->canceled(function (\Throwable $reason) {
     *     echo "Error occurred: " . $reason->getMessage();
     * });
     * ```
     */
    public function canceled(callable $onCancelled): self;

    /**
     * Registers a global error handler that will be called if an exception occurs within the promise due to logic error.
     *
     * @param callable $onError Invoked when the an error is encountered.
     *
     * @return self Return a instance of the promise.
     *
     * @example - Example Usage:
     * 
     * ```php
     * $promise->error(function (\Throwable $reason) {
     *     echo "Error occurred: " . $reason->getMessage();
     * });
     * ```
     */
    public function error(callable $onError): self;

    /**
     * Retrieves the current state of the promise.
     *
     * @return string The current state of the promise.
     *
     * @example - Example:
     * 
     * ```php
     * $state = $promise->getState();
     * echo "Current state: " . $state;
     * ```
     */
    public function getState(): string;

    /**
     * Check if promise state has fulfilled rejected or pending state.
     * 
     * @param string $state The state to check against promise state.
     * 
     * @return bool Return true if promise current state is same as passed state, otherwise false.
     */
    public function is(string $state): bool;

    /**
     * Resolves the promise with a given value.
     *
     * @param mixed $value The value to resolve the promise with.
     *
     * @throws RuntimeException if the promise has already been resolved.
     *
     * @example - Example:
     * 
     * ```php
     * $promise->resolve('Success');
     * ```
     */
    public function resolve(mixed $value): void;

    /**
     * Rejects the promise with a given reason.
     * 
     * @param mixed $reason The reason for rejection.
     *
     * @throws RuntimeException if the promise has already been resolved.
     *
     * @example - Example:
     * 
     * ```php
     * $promise->reject('Failure');
     * ```
     */
    public function reject(mixed $reason): void;

    /**
     * Cancels running promise.
     * 
     * @param mixed $reason An optional value to pass to the canceled callback handler.
     * @example - Example:
     * 
     * ```php
     * $promise->cancel();
     * ```
     */
    public function cancel(mixed $reason = null): void;

    /**
     * Waits for the promise to complete and returns the result or throws an error.
     *
     * @param int $timeout The timeout in milliseconds to wait for the promise to fulfill or reject (default: `1000`).
     *
     * @return mixed Return the resolved value or the rejection reason.
     *
     * @throws RuntimeException if the promise cannot settle after waiting or lacks a `wait` function.
     *
     * @example - Example:
     * 
     * ```php
     * try {
     *     $result = $promise->wait(true);
     *     echo "Promise resolved with: " . $result;
     * } catch (RuntimeException $e) {
     *     echo "Promise rejected with: " . $e->getMessage();
     * }
     * ```
     */
    public function wait(int $timeout = 1000): mixed;
}