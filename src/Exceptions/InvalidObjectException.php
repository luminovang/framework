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

use \Luminova\Exceptions\AppException;
use \Throwable;

class InvalidObjectException extends AppException
{
    /**
     * Constructor for InvalidObjectException.
     *
     * @param string     $key   The exception key as message
     * @param int        $code      The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
     */
    public function __construct(string $key, int $code = 500, Throwable $previous = null)
    {
        parent::__construct(
            sprintf('Invalid argument type: "%s". A valid object is expected.', gettype($key)), 
            $code, 
            $previous
        );
    }
}