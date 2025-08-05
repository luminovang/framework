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

class ClassException extends AppException
{
    /**
     * Constructor for ClassException.
     *
     * @param string $class  The exception class.
     * @param string|int   $code      The exception code (default: ErrorCode::CLASS_NOT_FOUND).
     * @param Throwable|null $previous  The previous exception if applicable (default: null).
     */
    public function __construct(
        string $class, 
        string|int $code = ErrorCode::CLASS_NOT_FOUND, 
        ?Throwable $previous = null
    )
    {
        parent::__construct(
            sprintf(
                'Invalid class name: (%s) of type: (%s) was not found.', 
                $class,  
                gettype($class)
            ), 
            $code, 
            $previous
        );
    }
}