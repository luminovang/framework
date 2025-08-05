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

class IOException extends AppException
{
    /**
     * Constructor for IOException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: `ErrorCode::IO_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::IO_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}