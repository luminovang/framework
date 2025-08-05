<?php
/**
 * Luminova Framework, Promise class interface.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Throwable;
use \Luminova\Exceptions\RuntimeException;

/**
 * Interface representing a promise that resolves or rejects asynchronously
 *
 * @see https://luminova.ng/docs/0.0.0/utilities/promise-object - for more details on promises.
 */
interface PromiseInterface
{
    /**
     * Run code after the promise finishes, whether it succeeds or fails.
     *
     * @param (callable(mixed $value):mixed)|null $onResolve Called if the promise is successful 
     *                                                  to receive the resolved value.
     * @param (callable(mixed $reason):mixed)|null $onReject Called if the promise fails, 
     *                                                  to receive the reject error reason.
     *
     * @return PromiseInterface Return a new promise that contains whatever your handler returns.
     *
     * @example - Example:
     * ```php
     * $promise->then(
     *     function ($value) {
     *         echo "Success: " . $value;
     *     },
     *     function ($reason) {
     *         echo "Failed: " . $reason;
     *     }
     * );
     * ```
     */
    public function then(?callable $onResolve = null, ?callable $onReject = null): PromiseInterface;

    /**
     * Run handler when the promise finishes, no matter if it succeeded or failed.
     *
     * @param (callable():void) $onAlways Called once the promise is done (settled).
     *
     * @return PromiseInterface Return a new promise that passes through the original result.
     *
     * @example - Example:
     * ```php
     * $promise->finally(function () {
     *     echo "Promise is finished.";
     * });
     * ```
     * > **Note:**
     * > This method does not change the result, unless it throws an error.
     */
    public function finally(callable $onAlways): PromiseInterface;

    /**
     * Handle promise errors in one place.
     * 
     * This method registers an error handler that will be called when promise is rejected.
     * The handler receives an error object (Throwable).
     *
     * @param (callable(Throwable $reason):mixed) $onCatch Called if the promise fails.
     *
     * @return PromiseInterface Return a new promise with your error handler’s result,
     *                          or the original value if no error occurred.
     *
     * @example - Example:
     * ```php
     * $promise->then(...)
     *      ->catch(function (\Throwable $reason) {
     *          echo "Something went wrong: " . $reason->getMessage();
     *      });
     * ```
     */
    public function catch(callable $onCatch): PromiseInterface;

    /**
     * Run handler if the promise is cancelled.
     * 
     * This method registers a cancellation handler that get called when promise `cancel` is invoked.
     *
     * @param (callable(Throwable $reason):void) $onCancelled Called when `cancel()` is triggered.
     *
     * @return self Return an instance of the promise.
     *
     * @example - Example:
     * ```php
     * $promise->canceled(function (\Throwable $reason) 
     * {
     *     echo "Promise was cancelled: " . $reason->getMessage();
     * });
     * ```
     */
    public function canceled(callable $onCancelled): self;

    /**
     * Run handler to capture errors if there’s an unexpected error inside the promise.
     * 
     * This method registers a global error handler that will be called if an exception occurs 
     * within the promise due to logic error.
     *
     * @param (callable(Throwable $e):void) $onError Called if the promise logic itself throws an error.
     *
     * @return self The same promise instance.
     *
     * @example - Example:
     * ```php
     * $promise->error(function (\Throwable $e) 
     * {
     *     echo "Unexpected error: " . $e->getMessage();
     * });
     * ```
     */
    public function error(callable $onError): self;

    /**
     * Retrieves the current state of the promise.
     *
     * @return string Return the current state of the promise.
     * @see is() - To check the current promise lifecycle state.
     *
     * @example - Example:
     * 
     * ```php
     * $state = $promise->state();
     * 
     * echo "Current state: " . $state;
     * ```
     */
    public function state(): string;

    /**
     * Get the promise reject or fulfilled value.
     * 
     * @return mixed Return the promise value, otherwise null.
     */
    public function value(): mixed;

    /**
     * Check the current state of the promise.
     *
     * A promise can be in one of three states:
     * - `pending`   → still running (`Promise::PENDING`)
     * - `fulfilled` → finished successfully (`Promise::FULFILLED`)
     * - `rejected`  → finished with an error (`Promise::REJECTED`)
     *
     * @param string $state The state to compare with.
     * 
     * @return bool Return true if the promise is in the given state, false otherwise.
     * @see state() - To return the current promise lifecycle state.
     *
     * @example _ Example:
     * ```php
     * if ($promise->is(Promise::PENDING)) {
     *     echo "Still running...";
     * }
     * ```
     */
    public function is(string $state): bool;

    /**
     * Mark the promise as successful with a value.
     *
     * Once resolved, the promise cannot be changed again.
     *
     * @param mixed $value The value to pass to `then()` callbacks.
     *
     * @return void
     * @throws RuntimeException If the promise was already settled.
     *
     * @example _ Example:
     * ```php
     * $promise->resolve('Success');
     * 
     * $promise->then(function ($value) {
     *     echo $value; // "Success"
     * });
     * ```
     */
    public function resolve(mixed $value): void;

    /**
     * Mark the promise as failed with a reason (error).
     *
     * Once rejected, the promise cannot be changed again.
     *
     * @param mixed $reason Why the promise failed (can be an Exception or string).
     *
     * @return void
     * @throws RuntimeException If the promise was already settled.
     *
     * @example - Example:
     * ```php
     * $promise->reject('Something went wrong');
     * 
     * $promise->catch(function (Throwable $e) {
     *     echo $e->getMessage(); // "Something went wrong"
     * });
     * ```
     * > **Note:**
     * > Rejection reason are converted to throwable object if not already.
     */
    public function reject(mixed $reason): void;

    /**
     * Cancel a running promise.
     *
     * This stops the promise from continuing. 
     * Useful if the task is no longer needed (e.g., user left the page).
     *
     * @param mixed $reason Optional reason for canceling.
     *
     * @return void
     * @example - Example:
     * ```php
     * $promise->cancel('No longer needed');
     * ```
     */
    public function cancel(mixed $reason = null): void;

    /**
     * Block and wait until the promise finishes.
     *
     * Returns the result if fulfilled, or throws an error if rejected.
     * This is mostly used in synchronous code or testing.
     *
     * @param int $timeout How long to wait in milliseconds (default: 1000).
     *
     * @return mixed Return the resolved value or rejection reason.
     * @throws RuntimeException If the promise does not finish within the time window.
     *
     * @example - Example:
     * ```php
     * try {
     *     $promise = new Promise();
     *     $promise->then(fn($num) => $num + 10);
     * 
     *     $promise->resolve(10);
     *     $result = $promise->wait(); 
     *     echo "Got: $result"; // 20
     * } catch (RuntimeException $e) {
     *     echo "Error: " . $e->getMessage();
     * }
     * ```
     * 
     * @example - Example (Handle Exceptions):
     * ```php
     * $promise = new Promise();
     * $promise->then(fn($num) => $num + 10)
     * $promise->error(function(Throwable $e){
     *      echo $e->getMessage();
     * });
     * 
     * $promise->resolve(10);
     * $result = $promise->wait(); 
     * echo "Got: $result"; // 20
     * ```
     *
     * > **Note:** 
     * > Use `cancel()` if you want to stop the promise before it finishes.
     * > Use `error()` method to handle exceptions that may throw.
     */
    public function wait(int $timeout = 1000): mixed;

    /**
     * Retrieves the current state of the promise.
     * 
     * Alias {@see state()}
     *
     * @return string Return the current state of the promise.
     */
    public function getState(): string;
}