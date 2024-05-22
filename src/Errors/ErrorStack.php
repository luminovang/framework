<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Errors;

use \Throwable;

final class ErrorStack
{
    /** 
     * The file where the error occurred.
     * 
     * @var mixed $file
    */
    private mixed $file = '';

    /** 
     * The line number where the error occurred.
     * 
     * @var int  
    */
    private int $line = 0;

    /** 
     * The error message.
     * 
     * @var mixed $message
     * 
    */
    private mixed $message = '';

    /** 
     * The error name.
     * 
     * @var mixed $name
     * 
    */
    private mixed $name = 'ERROR';

    /** 
     * The error code.
     * 
     * @var int $code
     * 
    */
    private int $code = 0;

     /** 
     * The previous error exception.
     * 
     * @var Throwable|null $previous
     * 
    */
    private ?Throwable $previous = null;

    /**
     * Error constructor.
     * @param string $message The error message.
     * @param int $code The error code (default: 0).
     * @param Throwable|null $previous Register previous error exception.
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        $this->message = $message;
        $this->code = $code;
        $this->previous = $previous;
    }

    /**
     * Sets the file where the error occurred.
     * 
     * @param string $file The file where the error occurred.
     */
    public function setFile(string $file): void
    {
        $this->file = $file;
    }

    /**
     * Sets the line number where the error occurred.
     * 
     * @param int $line The line number where the error occurred.
     */
    public function setLine(int $line): void
    {
        $this->line = $line;
    }

    /**
     * Sets the error name
     * 
     * @param string $name The error display name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Gets the error code.
     * 
     * @return int The error code.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Gets the line number where the error occurred.
     * 
     * @return int The line number where the error occurred.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Gets the file where the error occurred.
     * 
     * @return string The file where the error occurred.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Gets the display name
     * 
     * @return string The error display name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the error message.
     * 
     * @return string The error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get previous error.
     * 
     * @return Throwable
    */
    public function getPrevious(): ?Throwable 
    {
        return $this->previous;
    }
}