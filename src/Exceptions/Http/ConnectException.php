<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions\Http;

use \Luminova\Exceptions\AppException;
use \Throwable;

class ConnectException extends AppException
{
    /**
     * Constructor for ConnectException.
     *
     * @param string  $message The exception message.
     * @param string|int $code The exception code (default: 4975).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = self::HTTP_CONNECTION_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
