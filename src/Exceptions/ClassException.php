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

class ClassException extends AppException
{
    /**
     * Constructor for ClassException.
     *
     * @param string     $class   The exception class
     * @param string|int        $code      The exception code (default: 500).
     * @param Throwable|null $previous  The previous exception if applicable (default: null).
     */
    public function __construct(string $class, string|int $code = 500, ?Throwable $previous = null)
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