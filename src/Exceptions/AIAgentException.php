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
use Luminova\Exceptions\ErrorCode;
use Luminova\Exceptions\LuminovaException;

class AIAgentException extends LuminovaException
{
    /**
     * Constructor for AI agent exception.
     *
     * @param string  $message The exception message.
     * @param string|int $code The exception code (default: `ErrorCode::AI_AGENT_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::AI_AGENT_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}