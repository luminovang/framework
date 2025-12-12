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
namespace Luminova\Promise\Helpers;

use \Throwable;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionIntersectionType;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Promise\{Promise, Rejected, Fulfilled};
use \Luminova\Exceptions\{ErrorCode, AppException, ErrorException, LogicException};

final class Helper 
{
    /**
     * Resolves a value into a promise.
     * 
     * @param mixed $value The value to resolve.
     * @param bool $raw Whether to return the raw value if it's not a promise.
     * 
     * @return PromiseInterface A promise representing the resolved value.
     */
    public static function resolve(mixed $value, bool $raw = false): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        $value = self::fromThirdPartyPromise($value);
        return ($raw || ($value instanceof PromiseInterface))
            ? $value 
            : new Fulfilled($value);
    }

    /**
     * Creates a rejected promise with a given reason.
     * 
     * @param Throwable $reason The reason for rejection.
     * 
     * @return PromiseInterface A rejected promise.
     */
    public static function reject(Throwable $reason): PromiseInterface
    {
        return new Rejected($reason);
    }

    /**
     * Ensure promise rejection is always Throwable.
     * 
     * @var mixed $reason The rejection reason.
     * 
     * @return Throwable Returns instance of exception object.
     */
    public static function reason(mixed $reason): Throwable
    {
        if ($reason instanceof Throwable) {
            return $reason;
        }

        if ($reason !== '' && is_string($reason)) {
            return new ErrorException($reason);
        }
        
        if (!is_null($reason)) {
            $type = gettype($reason);
            return new ErrorException("Promise rejected with non-exception value of type: {$type}");
        }

        return new ErrorException('Promise rejected with an undefined reason.');
    }

    /**
     * Executes a callback when the promise is settled, regardless of success or failure.
     * 
     * @param callable $onFinally A callback to execute when the promise is settled.
     * @param mixed $value The current value of the promise.
     * @param bool $raw Whether to return the raw value or wrap it into a `Rejected` if the promise fails.
     * 
     * @return PromiseInterface A promise that resolves after executing `$onFinally`.
     * @ignore
     * @internal
     */
    public static function finally(callable $onFinally, mixed $value, bool $raw = true): PromiseInterface
    {
        try {
            return self::resolve($onFinally($value))->then(
                fn(): mixed  => ($raw ? $value : new Rejected($value))
            );
        } catch (Throwable $e) {
            return new Rejected($e);
        }
    }

    /**
     * Handle third-party promises into the luminova promise system.
     * 
     * @param mixed $value The third-party promise or value to adapt.
     * 
     * @return mixed A compatible promise or the original value if it is not a promise.
     * @ignore
     * @internal
     */
    public static function fromThirdPartyPromise(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'then')) {
            return new Promise(function($resolve, $reject) use($value): void {
                $value->then(
                    $resolve,
                    function($error) use($reject, $value): void {
                        $reject(self::reason($error)); 
                        if(method_exists($value, 'cancel')){
                            try{$value->cancel();}
                            catch(Throwable){}
                        } 
                    }
                );
            });
        }

        return $value;
    }

    /**
     * Promise rejection handling.
     * 
     * Handles the rejection of a promise by invoking an error handler or throwing an exception.
     *
     * If `$error` is not a `Throwable`, it is wrapped into an `ErrorException` or `LogicException`
     * depending on the provided `$code`. If no `$onError` handler is provided and `$throw` is `true`,
     * the error is thrown with an extra hint message.
     *
     * @param (callable(Throwable $e): void)|null $onError Optional error handler.
     * @param mixed $error The error to information.
     * @param string|int $code Error code or severity (default: `ErrorCode::ERROR`).
     * @param bool $throw Whether to throw the error if no handler is provided (default: `true`).
     *
     * @return void
     * @internal
     */
    public static function rejection(
        ?callable $onError,
        mixed $error,
        string|int $code = ErrorCode::ERROR,
        bool $throw = true
    ): void 
    {
        if (!$error instanceof Throwable) {
            $error = self::getRejectionHandler($error, $code);
        }

        if ($onError !== null) {
            $onError($error);
            return;
        }

        if ($throw) {
            $msg = "\nRegister method `\$promise->canceled(function(Throwable \$e){echo (string) \$e})` to handle exceptions without throwing.";

            if ($error instanceof Throwable) {
                $class = $error::class;
                throw new $class(
                    message: $error->getMessage() . $msg,
                    code: $error->getCode(),
                    previous: $error->getPrevious()
                );
            }

            $error = (string) $error;
            throw self::getRejectionHandler($error . $msg, $code);
        }
    }

    /**
     * Get exception object.
     * 
     * @param mixed         $error   The error to handle.
     * @param string|int    $code    Error code or severity. Defaults to `ErrorCode::ERROR`.
     *
     * 
     * @return AppException<\T> Return exception object.
     */
    private static function getRejectionHandler(mixed $error, string|int $code): AppException 
    {
        return ($code === ErrorCode::LOGIC_ERROR) 
            ? new LogicException((string) $error, $code)
            : new ErrorException(message: (string) $error, code: $code);
    }

    /**
     * Determines if a callback is a **direct value handler** rather than an executor-style.
     *
     * A direct value handler expects a resolved value:
     * ```php
     * new Promise(fn($value) => echo $value); // Direct
     * ```
     *
     * An executor-style callback receives one or two functions (`callable` or `Closure`):
     * ```php
     * new Promise(fn(callable $resolve) => $resolve('OK')); // Executor
     * new Promise(fn(Closure $resolve) => $resolve('OK'));  // Executor
     * ```
     *
     * @param \ReflectionParameter[] $parameters The callback's parameters to inspect.
     * @param bool $strict Check deeply in union/intersection types for `callable` or `Closure`.
     *
     * @return bool True if the callback is a direct value handler, false if executor-style.
     */
    public static function isDirectValue(array $hints, bool $strict = false): bool
    {
        $type = ($hints === []) ? null : $hints[0]->getType();

        if (!$type) {
            return true;
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            return $name !== 'callable' && $name !== 'Closure';
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            if($strict){
                foreach ($type->getTypes() as $hint) {
                    if ($hint instanceof ReflectionNamedType) {
                        $name = $hint->getName();

                        if ($name === 'callable' || $name === 'Closure') {
                            return false;
                        }
                    }
                }
            }

            return true;
        }

        return false;
    }
}