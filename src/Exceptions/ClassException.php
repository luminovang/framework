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
     * @param int        $code      The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
     */
    public function __construct(string $class = 'error', int $code = 500, Throwable $previous = null)
    {
        $message = sprintf('Invalid class name: (%s) of type: (%s) was not found.', $class,  gettype($class));
        parent::__construct($message, $code, $previous);
    }
}