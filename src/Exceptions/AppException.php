<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

use \Luminova\Interface\ExceptionInterface;
use \Exception;
use \Throwable;

abstract class AppException extends Exception implements ExceptionInterface
{
    /**
      * {@inheritdoc}
    */
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
      * {@inheritdoc}
    */
    public function __toString(): string
    {
        return "Exception: ({$this->code}) {$this->message} File: {$this->file}, Line: {$this->line}";
    }

    /**
      * {@inheritdoc}
    */
    public function handle(): void
    {
        if(is_command()){
            $display = function($exception): int {
                include_once path('views') . 'system_errors' . DIRECTORY_SEPARATOR . 'cli.php';
                return STATUS_ERROR;
            };

            exit($display($this));
        }

        if (PRODUCTION && !static::isFatal($this->code)) {
            $this->log();
            return;
        }

        throw $this;
    }

    /**
      * {@inheritdoc}
    */
    public static function throwException(string $message, int $code = 0, Throwable $previous = null): void
    {
        (new static($message, $code, $previous))->handle();
    }

    /**
      * {@inheritdoc}
    */
    public function log(string $level = 'exception'): void
    {
        logger($level, $this->__toString());
    }

    /**
     * Check if exception code is fatal error.
     * 
     * @param int $errno Error code
     * 
     * @return bool Return true if is fatal, otherwise false
    */
    protected static function isFatal(int $errno): bool 
    {
        return in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]);
    }
}