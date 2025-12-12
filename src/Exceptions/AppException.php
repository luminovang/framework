<?php
/**
 * Luminova Framework base exception class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Exceptions;

use \Throwable;
use \Stringable;
use \Luminova\Interface\ExceptionInterface;

/**
 * @deprecated Use Luminova\Exceptions\LuminovaException
 */
abstract class AppException extends LuminovaException implements ExceptionInterface, Stringable
{
    /**
     * {@inheritDoc
     * 
     * @deprecated Use LuminovaException
     */
    public function __construct(string $message, string|int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, is_numeric($code) ? (int) $code : 0, $previous);
    }
}