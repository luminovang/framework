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

class DateTimeException extends AppException
{
    /**
     * Constructor for DateTimeException.
     *
     * @param string  $message The exception message.
     * @param string|int $code The exception code (default: `ErrorCode::DATETIME_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::DATETIME_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}