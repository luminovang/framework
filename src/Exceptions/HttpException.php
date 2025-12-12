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
use Luminova\Http\HttpStatus;
use Luminova\Exceptions\LuminovaException;

/**
 * Exception representing an HTTP error response.
 *
 * The exception code contains the HTTP status code.
 */
class HttpException extends LuminovaException
{
    /**
     * Create an HTTP exception.
     *
     * @param string $message The exception message.
     * @param int $status The HTTP status code.
     * @param Throwable|null $previous The previous exception, if any.
     */
    public function __construct(
        string $message,
        int $status = 500,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            HttpStatus::isValid($status) ? $status : 500,
            $previous
        );
    }

    /**
     * Get the HTTP status code.
     *
     * @return int The HTTP status code.
     */
    public function getStatus(): int
    {
        return $this->getCode();
    }
}