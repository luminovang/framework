<?php
/**
 * Luminova Framework error handler class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Errors;

use \Throwable;
use \Luminova\Exceptions\ErrorException;

final class ErrorHandler
{
    /**
     * Constructor for the error handler class.
     * 
     * @param string $message The error message.
     * @param string|int $code The error code.
     * @param Throwable|null $previous Optional previous exception.
     * @param string $file The file where the error occurred.
     * @param int $line The line number where the error occurred.
     * @param string $name A custom name for the error.
     */
    public function __construct(
        protected string $message, 
        protected string|int $code = 0, 
        private ?Throwable $previous = null,
        protected mixed $file = '',
        protected int $line = 0,
        protected mixed $name = 'ERROR'
    ) {}

    /**
     * Set the last debug backtrace to the shared error context to be accessed any where when called `ErrorHandler::getBacktrace()`.
     * This method sets the backtrace to shared variable `__ERROR_DEBUG_BACKTRACE__`.
     * 
     * @param array $backtrace The array of backtrace information.
     * 
     * @return bool Return true if debug backtrace was set, false otherwise.
     */
    public static function setBacktrace(array $backtrace): bool 
    {
        if(!defined('IS_UP')){
            return false;
        }

        shared('__ERROR_DEBUG_BACKTRACE__', $backtrace);
        return true;
    }

    /**
     * Triggers an error by throwing an ErrorException with the specified message and level.
     *
     * @param string $message The error message to be included in the exception.
     * @param int $error_level The level of the error (default: `ErrorException::USER_NOTICE`).
     * @param string|null $file Optional. The file where the error occurred.
     * @param int|null $line Optional. The line number where the error occurred.
     *
     * @return never Always throws an ErrorException, so this method does not return a value.
     * @throws ErrorException When an error is triggered.
     */
    public static function trigger(
        string $message, 
        int $error_level = ErrorException::USER_NOTICE,
        ?string $file = null,
        ?int $line = null
    ): void 
    {
        $e = new ErrorException($message, $error_level);
        
        if($file){
            $e->setFile($file);
        }

        if($line){
            $e->setLine($line);
        }

        throw $e;
    }

    /** 
     * Outputs a basic error message when no error handler is available.
     * 
     * @param bool $is_cli Whether the error occurred in a CLI environment.
     * @param int $retry_after Optional number of seconds after which the client should retry (default: 60).
     * 
     * @return void
     */
    public static function notify(bool $is_cli, int $retry_after = 60): void 
    {
        $error = 'An error has prevented the application from running correctly.';
        
        if ($is_cli) {
            echo $error;
            return;
        } 

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Retry-After: ' . $retry_after);
        }

        echo sprintf(
            '<html><head><title>Error Occurred</title></head><body><h1>Error Occurred</h1><p>%s</p></body></html>',
            $error
        );
    }

    /**
     * Gets the error code.
     * 
     * @return string|int Return the error code.
     */
    public function getCode(): string|int
    {
        return $this->code;
    }

    /**
     * Gets the line number where the error occurred.
     * 
     * @return int Return the line number where the error occurred.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Gets the file where the error occurred.
     * 
     * @return string Return the file where the error occurred.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Gets the display name
     * 
     * @return string Return the error display name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the error message.
     * 
     * @return string Return the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Gets filtered error message without the file.
     * 
     * @return string Return the filtered error message.
     */
    public function getFilteredMessage(): string
    {
        $message = str_contains($this->message, APP_ROOT) 
            ? str_replace(APP_ROOT, '/', $this->message) 
            : $this->message;

        return trim($message, ' in');
    }

    /**
     * Get previous error.
     * 
     * @return Throwable Return the the previous error.
     * @ignore
    */
    public function getPrevious(): ?Throwable 
    {
        return $this->previous;
    }

    /**
     * Retrieves the last debug backtrace from the shared error context.
     * 
     * This method accesses a shared variable `__ERROR_DEBUG_BACKTRACE__` to retrieve
     * the stored debug backtrace. If the backtrace is not set, it returns an empty array.
     * 
     * @return array Return the debug backtrace or an empty array if not available.
     */
    public static function getBacktrace(): array 
    {
        return defined('IS_UP') ? 
            (shared('__ERROR_DEBUG_BACKTRACE__', null, []) ?? []) : 
            [];
    }

    /**
     * Extracts the error code from the given error message.
     * 
     * This method searches for a specific pattern in the error message to retrieve the associated error code.
     * If the code is not found, it returns the provided default error code. 
     * Additionally, it returns a specific code (8790) for "Call to undefined" errors.
     *
     * @param string $message The error message from which to extract the code.
     * @param string|int $default The default error code to return if no specific code is found (default is E_ERROR).
     * 
     * @return string|int Returns the extracted error code or the default code if not found.
     */
    public static function getErrorCode(
        string $message, 
        string|int $default = E_ERROR
    ): string|int
    {
        if (preg_match('/^Uncaught \w+:\s*\((\d+)\)/', $message, $matches)) {
            $code = $matches[1] ?? null;
            return ($code === null) ? $default : (int) $code;
        }

        if (preg_match('/^Uncaught \w+:\s*Call to undefined/i', $message)) {
            return 8790;
        }

        return $default; 
    }
    
    /**
     * Error name by the error.
     * 
     * @param string|int $code The error code.
     * 
     * @return string Return Error name by error code.
    */
    public static function getErrorName(string|int $code): string 
    {
        return match ($code) {
            // PHP Error Types
            E_ERROR => 'ERROR',
            E_PARSE => 'PARSE ERROR',
            E_CORE_ERROR => 'CORE ERROR',
            E_COMPILE_ERROR => 'COMPILE ERROR',
            
            E_WARNING => 'WARNING',
            E_CORE_WARNING => 'CORE WARNING',
            E_COMPILE_WARNING => 'COMPILE WARNING',
            E_USER_WARNING => 'USER WARNING',
            
            E_NOTICE => 'NOTICE',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT NOTICE',
            
            E_USER_ERROR => 'USER ERROR',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED',
            
            // PDO SQLSTATE Codes
            23000, '23000' => 'INTEGRITY CONSTRAINT VIOLATION',
            
            // MySQL Error Codes
            1044 => 'ACCESS DENIED FOR USER',
            1045 => 'ACCESS DENIED INVALID PASSWORD',
            1064, 42000, '42000' => 'SQL SYNTAX ERROR',
            1146 => 'TABLE DOES NOT EXIST',
            
            // PostgreSQL Error Codes
            28000, '28000' => 'INVALID AUTHORIZATION SPECIFICATION',
            '3D000' => 'INVALID CATALOG NAME',
            
            // Join Error Codes
            '08001', 1500 => 'DATABASE UNABLE TO CONNECT',
            '08004', 1503 => 'FAILED ALL CONNECTION ATTEMPTS',
            1509, 5 => 'CONNECTION LIMIT EXCEEDED',
            1406 => 'UNKNOWN DATABASE DRIVER',
            1049, 1501 => 'DATABASE DRIVER NOT AVAILABLE',
            1417 => 'DATABASE TRANSACTION READONLY FAILED',
            1420 => 'DATABASE TRANSACTION FAILED',
            1418 => 'TRANSACTION SAVEPOINT FAILED',
            1419 => 'FAILED TO ROLLBACK TRANSACTION',
            1499 => 'NO STATEMENT TO EXECUTE',
            1403 => 'VALUE FORBIDDEN',
            1001 => 'INVALID ARGUMENTS',
            1002 => 'INVALID',
            5001 => 'RUNTIME ERROR',
            5011 => 'CLASS NOT FOUND',
            5079 => 'STORAGE ERROR',
            404 => 'VIEW NOT FOUND',
            4070 => 'INPUT VALIDATION ERROR',
            4161 => 'ROUTING ERROR',
            4040 => 'NOT FOUND',
            4051 => 'BAD METHOD CALL',
            5071 => 'CACHE ERROR',
            6204 => 'FILESYSTEM ERROR',
            4961 => 'COOKIE ERROR',
            2306 => 'DATETIME ERROR',
            3423 => 'CRYPTOGRAPHY ERROR',
            6205 => 'WRITE PERMISSION DENIED',
            6206 => 'READ PERMISSION DENIED',
            6209 => 'READ WRITE PERMISSION DENIED',
            6207 => 'CREATE DIR FAILED',
            6208 => 'SET PERMISSION FAILED',
            4180 => 'JSON ERROR',
            4973 => 'SECURITY ISSUE',
            449 => 'MAILER ERROR',
            1003 => 'INVALID CONTROLLER',
            4052 => 'INVALID METHOD',
            4053 => 'INVALID REQUEST METHOD',
            4061 => 'NOT ALLOWED',
            4062 => 'NOT SUPPORTED',
            4229 => 'LOGIC ERROR',
            4974 => 'HTTP CLIENT ERROR',
            4975 => 'HTTP CONNECTION ERROR',
            4976 => 'HTTP REQUEST ERROR',
            4977 => 'HTTP SERVER ERROR',
            1200 => 'TERMINATED',
            1201 => 'ERROR TIMEOUT',
            1202 => 'PROCESS ERROR',
            
            
            // SQLite Error Codes
            6 => 'DATABASE LOCKED',
            14 => 'CANNOT OPEN DATABASE FILE',
            
            // Unknown or unsupported error codes
            8790 => 'UNDEFINED',
            default => 'UNKNOWN ERROR',
        };
    }
}