<?php
/**
 * Luminova Framework process executor.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils\Promise;

use \Luminova\Utils\Promise\Promise;
use \Luminova\Utils\Promise\FulfilledPromise;
use \Luminova\Utils\Promise\RejectedPromise;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\ErrorException;
use \Luminova\Exceptions\LogicException;
use \ReflectionNamedType;
use \ReflectionUnionType;
use \ReflectionIntersectionType;
use \Throwable;

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
        return ($raw || ($value instanceof PromiseInterface) 
            ? $value 
            : new FulfilledPromise($value));
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
        return new RejectedPromise($reason);
    }

    /**
     * Executes a callback when the promise is settled, regardless of success or failure.
     * 
     * @param callable $onFinally A callback to execute when the promise is settled.
     * @param mixed $value The current value of the promise.
     * @param bool $raw Whether to return the raw value or wrap it into a `RejectedPromise` if the promise fails.
     * 
     * @return PromiseInterface A promise that resolves after executing `$onFinally`.
     * @ignore
     * @internal
     */
    public static function finally(callable $onFinally, mixed $value, bool $raw = true): PromiseInterface
    {
        try {
            return self::resolve($onFinally($value))->then(
                fn(): mixed  => $raw ? $value : new RejectedPromise($value)
            );
        } catch (Throwable $e) {
            return new RejectedPromise($e);
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
                        $reject($error); 
                        if(method_exists($value, 'cancel')){
                            try{$value->cancel();}catch(Throwable){}
                        } 
                    }
                );
            });
        }

        return $value;
    }

    /**
     * Handles the rejection of a promise by invoking an error handler or throwing an exception.
     *
     * @param mixed  $error The error to handle. Can be a `Throwable` or any other value that will be wrapped 
     *               in an `ErrorException`.
     * @param string|int  $code  The error code for the exception if the `$error` is not a `Throwable`. Defaults to 
     *                           `ErrorException::ERROR`.
     * @param bool $throw Whether to throw the error as an exception if no error handler is available. Defaults to `true`.
     *
     * @return void
     * @ignore
     * @internal
     */
    public static function rejection(
        ?callable $onError,
        mixed $error, 
        string|int $code = ErrorException::ERROR, 
        bool $throw = true,
    ): void
    {
        $class = ($code === ErrorException::LOGIC_ERROR) 
            ? LogicException::class : ErrorException::class;

        $error = ($error instanceof Throwable) ? $error : new $class($error, $code);

        if($onError){
            ($onError)($error);
            return;
        }

        if($throw){
            $error .= "\nRegister method `->canceled(function(Throwable \$e){echo (string) \$e})` to handle exceptions without throwing.";
            throw $error;
        }
    }

    /**
     * @ignore
     * @internal
     */
    /**
     * Determines if a given type is strictly direct (not containing 'callable').
     *
     * @param ReflectionUnionType|ReflectionIntersectionType|null $type The type to check.
     *                                                                  Can be a union type,
     *                                                                  intersection type, or null.
     *
     * @return bool Returns true if the type is strictly direct (does not contain
     *              'callable'), false otherwise.
     */
    public static function isStrictlyDirect(ReflectionUnionType|ReflectionIntersectionType|null $type): bool
    {
        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() === 'callable') {
                    return false;
                }
            }

            return true;
        }

        if ($type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $intersection) {
                if (($intersection instanceof ReflectionNamedType) && $intersection->getName() === 'callable') {
                    return false;
                }
            }
        }

        return true;
    }
}