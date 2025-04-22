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

class InvalidObjectException extends AppException
{
    /**
     * Constructor for InvalidObjectException.
     * >>>
     * 
     * @param string     $key   The exception key as message
     * @param string|int        $code      The exception code (default: 500).
     * @param Throwable|null $previous  The previous exception if applicable (default: null).
     */
    public function __construct(
        string $key, 
        string|int $code = 500, 
        ?Throwable $previous = null
    )
    {
        parent::__construct(
            sprintf('Invalid argument type: "%s". A valid object is expected.', gettype($key)), 
            $code, 
            $previous
        );
    }
}