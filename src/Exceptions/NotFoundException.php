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

class NotFoundException extends AppException
{
    /**
     * Constructor for NotFoundException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: 4040).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = self::NOT_FOUND, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}