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

use \Luminova\Exceptions\AppException;
use \Throwable;

class BadMethodCallException extends AppException
{
    /**
     * Constructor for BadMethodCallException.
     *
     * @param string  $message The exception message.
     * @param string|int $code The exception code (default: 4051).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = self::BAD_METHOD_CALL, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}