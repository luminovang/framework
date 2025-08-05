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
namespace Luminova\Exceptions;

use \Throwable;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Exceptions\AppException;

class ErrorException extends AppException
{
    /**
     * Constructor for ErrorException.
     * 
     * @param string $message The exception message.
     * @param string|int $code  The exception code (default: `ErrorCode::ERROR`).
     * @param int $severity The error type/code (e.g., `ErrorCode::ERROR`).
     * @param string $file The file where the error occurred.
     * @param int|null $line The line number where the error occurred.
     * @param Throwable|null $previous Optional The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::ERROR, 
        protected int $severity = 1,
        ?string $file = null,
        ?int $line = null,
        ?Throwable $previous = null
    ) 
    {
        parent::__construct($message, $code, $previous);

        if($file !== null){
            $this->setFile($file);
        }

        if($line !== null){
            $this->setLine($line);
        }
    }

    /**
     * Get the error severity.
     * 
     * @return int Return the error severity.
     * @since 3.6.8
     */
    public function getSeverity(): int 
    {
        return $this->severity;
    }
}