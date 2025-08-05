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

class DatabaseLimitException extends AppException
{
    /**
     * Constructor for DatabaseLimitException.
     *
     * @param string  $message The exception message.
     * @param string|int $code The exception code (default: `ErrorCode::CONNECTION_LIMIT_EXCEEDED`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::CONNECTION_LIMIT_EXCEEDED, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}