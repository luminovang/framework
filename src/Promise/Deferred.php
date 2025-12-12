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

use \Luminova\Promise\Promise;
use \Luminova\Interface\PromiseInterface;

/**
 * The Deferred class provides a controllable Promise object.
 * 
 * A deferred promise separates the creation of a promise from its resolution or rejection.
 * This is useful when the result of an operation isn’t immediately available — 
 * for example, when waiting for asynchronous tasks or callbacks.
 *
 * @example - Example:
 * ```php
 * use Luminova\Promise\Deferred;
 * 
 * $deferred = new Deferred();
 * 
 * // Access the promise object
 * $deferred->promise()
 *     ->then(function ($result) {
 *         echo "Done: $result";
 *     })
 *     ->catch(function ($error) {
 *         echo "Failed: " . $error->getMessage();
 *     });
 * 
 * // Later in your code...
 * $deferred->resolve('Operation successful');
 * // or
 * // $deferred->reject(new Exception('Something went wrong'));
 * ```
 */
final class Deferred
{
    /**
     * The underlying promise instance.
     * 
     * @var PromiseInterface|null $promise
     */
    private ?PromiseInterface $promise = null;

    /**
     * Create a new Deferred instance with a pending promise state.
     */
    public function __construct()
    {
        $this->promise = new Promise();
    }

    /**
     * Resolve the deferred promise with a given value.
     *
     * @param mixed $value The value or result to resolve the promise with.
     * 
     * @return void
     */
    public function resolve(mixed $value): void
    {
        $this->promise->resolve($value);
    }

    /**
     * Reject the deferred promise with a reason or exception.
     *
     * @param mixed $reason The error or reason why the promise is rejected.
     * 
     * @return void
     */
    public function reject(mixed $reason): void
    {
        $this->promise->reject($reason);
    }

    /**
     * Retrieve the promise instance associated with this deferred object.
     *
     * Use this promise to attach `then()`, `catch()`, or `finally()` handlers.
     *
     * @return PromiseInterface Return instance of promise object.
     */
    public function promise(): PromiseInterface
    {
        return $this->promise;
    }
}