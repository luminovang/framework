<?php
/**
 * Luminova Framework base exception class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

use \Luminova\Logger\Logger;
use \Luminova\Application\Foundation;
use \Luminova\Interface\ExceptionInterface;
use \Luminova\Errors\ErrorHandler;
use \Luminova\Config\Enums\ErrorCodes;
use \Stringable;
use \Exception;
use \Throwable;

abstract class AppException extends Exception implements ExceptionInterface, Stringable
{
    /**
     * The `ErrorCodes` class defines constants for various error types,
     * including PHP error types, SQLSTATE codes, and custom database error codes.
     */
    use ErrorCodes;

    /**
     * PSR Logger instance.
     *
     * @var Logger|null $logger 
     */
    private static ?Logger $logger = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(
        string $message, 
        string|int $code = 0, 
        ?Throwable $previous = null
    )
    {
        // Only pass integer error code to parent constructor.
        parent::__construct($message, is_numeric($code) ? (int) $code : 0, $previous);

        // Set the code directly after parent initialized in case if it's a string error code.
        $this->code = $code;

        // If debug tracing is enabled then store it in shared memory
        // In other to access it when error handler is called, since there is no way to access trace information.
        if(SHOW_DEBUG_BACKTRACE){
            ErrorHandler::setBacktrace($this->getTrace() ?: debug_backtrace());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setCode(string|int $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFile(string $file): self
    {
        $this->file = $file;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setLine(int $line): self
    {
        $this->line = $line;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return ErrorHandler::getErrorName($this->code);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilteredMessage(): string
    {
        $position = strpos($this->message, APP_ROOT);
        $message = ($position !== false) ? 
            substr($this->message, 0, $position) : 
            $this->message;

        return trim($message, ' in');
    }

    /**
     * {@inheritdoc}
     */
    public function getBacktrace(): array 
    {
        return $this->getTrace() ?: ErrorHandler::getBacktrace();
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return sprintf(
            'Exception: (%s) %s in %s on line %d',
            (string) $this->code,
            $this->message,
            $this->file,
            $this->line
        );
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        if (Foundation::isCommand()) {
            if (env('throw.cli.exceptions', false)) {
                throw $this;
            }

            exit(self::display($this));
        }

        if (PRODUCTION && !Foundation::isFatal($this->code)) {
            $this->log();
            return;
        }

        throw $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function throwException(
        string $message, 
        string|int $code = 0, 
        ?Throwable $previous = null
    ): void
    {
        (new static($message, $code, $previous))->handle();
    }

    /**
     * {@inheritdoc}
     */
    public function log(string $level = 'exception'): void
    {
        self::$logger ??= new Logger(); 
        self::$logger->log($level, $this->__toString());
    }

    /**
     * Display a custom exception info in CLI mode.
     * 
     * @param AppException<\T>|Exception $exception The current exception thrown.
     * 
     * @return int Return status code for error.
     */
    private static function display(AppException|Exception $exception): int 
    {
        include_once root('/resources/views/system_errors/') . 'cli.php';

        return STATUS_ERROR;
    }
}