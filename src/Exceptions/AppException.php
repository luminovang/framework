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

use \Exception;
use \Throwable;

class AppException extends Exception
{
    /**
     * Constructor for AppException.
     *
     * @param string message   The exception message (default: 'Database error').
     * @param int $code  The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
    */
    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        if($caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2) && isset($caller[1])){
            $message .= isset($caller[1]['file']) ? ' File: ' .  filter_paths($caller[1]['file']) : '';
            $message .= isset($caller[1]['line']) ? ' Line: ' . $caller[1]['line'] : '';
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get a string representation of the exception.
     *
     * @return string A formatted error message.
    */
    public function __toString(): string
    {
        return "Exception: ({$this->code}) {$this->message}";
    }

    /**
     * Handle the exception based on the production environment.
     * 
     * @throws $this Exception
    */
    public function handle(): void
    {
        if(is_command()){
            $path = path('views') . 'system_errors' . DIRECTORY_SEPARATOR . 'cli.php';
            $exception = $this;
            include_once $path;
            exit(STATUS_ERROR);
        }

        if (PRODUCTION) {
            $this->logMessage();
            return;
        }

        throw $this;
    }

    /**
     * Logs an exception
     *
     * 
     * @return void
    */
    public function logMessage(): void
    {
        logger('exception', "Exception: {$this->getMessage()}");
    }

    /**
     * Create and handle a Exception.
     *
     * @param string $message he exception message.
     * @param int $code The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
     * 
     * @return void 
     * @throws $this Exception
    */
    public static function throwException(string $message, int $code = 0, Throwable $previous = null): void
    {
        $throw = new static($message, $code, $previous);

        $throw->handle();
    }
}