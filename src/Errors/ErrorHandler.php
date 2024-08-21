<?php
/**
 * Luminova Framework error handler class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Errors;

use \Throwable;

final class ErrorHandler
{
    /**
     * Constructor for the error handler class.
     * 
     * @param string $message The error message.
     * @param string|int $code The error code.
     * @param Throwable|null $previous Optional previous exception.
     * @param string $file The file where the error occurred.
     * @param int $line The line number where the error occurred.
     * @param string $name A custom name for the error.
     */
    public function __construct(
        protected string $message, 
        protected string|int $code = 0, 
        private ?Throwable $previous = null,
        protected mixed $file = '',
        protected int $line = 0,
        protected mixed $name = 'ERROR'
    ) {}

    /**
     * Gets the error code.
     * 
     * @return string|int Return the error code.
     */
    public function getCode(): string|int
    {
        return $this->code;
    }

    /**
     * Gets the line number where the error occurred.
     * 
     * @return int Return the line number where the error occurred.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Gets the file where the error occurred.
     * 
     * @return string Return the file where the error occurred.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Gets the display name
     * 
     * @return string Return the error display name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the error message.
     * 
     * @return string Return the error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Gets filtered error message without the file.
     * 
     * @return string Return the filtered error message.
     */
    public function getFilteredMessage(): string
    {
        $position = strpos($this->message, APP_ROOT);
        $message = ($position !== false) ? 
            substr($this->message, 0, $position) : 
                $this->message;

        // Remove path
        //$message = preg_replace('/"([^"]*\/[^"]*)"/', '', $message);
        return trim($message, ' in');
    }

    /**
     * Get previous error.
     * 
     * @return Throwable Return the the previous error.
     * @ignore
    */
    public function getPrevious(): ?Throwable 
    {
        return $this->previous;
    }

    /**
     * Get the debug trace.
     * 
     * @return array Return array with debug information.
    */
    public function getDebugTrace(): array 
    {
       return defined('IS_UP') ? shared('__ERROR_DEBUG_BACKTRACE__', null, []) : [];
    }
}