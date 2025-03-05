<?php
/**
 * Luminova Framework base exception class.
 * The defines constants for various error types,
 * including PHP error types, SQLSTATE codes, and custom database error codes.
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
use \Stringable;
use \Exception;
use \Throwable;

abstract class AppException extends Exception implements ExceptionInterface, Stringable
{
    /** @var int ERROR */
    public const ERROR = E_ERROR;
    /** @var int PARSE_ERROR */
    public const PARSE_ERROR = E_PARSE;
    /** @var int CORE_ERROR */
    public const CORE_ERROR = E_CORE_ERROR;
    /** @var int COMPILE_ERROR */
    public const COMPILE_ERROR = E_COMPILE_ERROR;

    /** @var int WARNING */
    public const WARNING = E_WARNING;
    /** @var int CORE_WARNING */
    public const CORE_WARNING = E_CORE_WARNING;
    /** @var int COMPILE_WARNING */
    public const COMPILE_WARNING = E_COMPILE_WARNING;
    /** @var int USER_WARNING */
    public const USER_WARNING = E_USER_WARNING;

    /** @var int NOTICE */
    public const NOTICE = E_NOTICE;
    /** @var int USER_NOTICE */
    public const USER_NOTICE = E_USER_NOTICE;

    /** @var int USER_ERROR */
    public const USER_ERROR = E_USER_ERROR;
    /** @var int RECOVERABLE_ERROR */
    public const RECOVERABLE_ERROR = E_RECOVERABLE_ERROR;

    /** @var int DEPRECATED */
    public const DEPRECATED = E_DEPRECATED;
    /** @var int USER_DEPRECATED */
    public const USER_DEPRECATED = E_USER_DEPRECATED;

    // PDO SQLSTATE Codes
    /** @var string UNABLE_TO_CONNECT */
    public const UNABLE_TO_CONNECT = '08001';
    /** @var string CONNECTION_DENIED */
    public const CONNECTION_DENIED = '08004';
    /** @var string INTEGRITY_CONSTRAINT_VIOLATION */
    public const INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    /** @var string SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION */
    public const SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION = '42000';

    // MySQL Error Codes
    /** @var int ACCESS_DENIED_FOR_USER */
    public const ACCESS_DENIED_FOR_USER = 1044;
    /** @var int ACCESS_DENIED_INVALID_PASSWORD */
    public const ACCESS_DENIED_INVALID_PASSWORD = 1045;
    /** @var int UNKNOWN_DATABASE */
    public const UNKNOWN_DATABASE = 1049;
    /** @var int SYNTAX_ERROR_IN_SQL_STATEMENT */
    public const SYNTAX_ERROR_IN_SQL_STATEMENT = 1064;
    /** @var int TABLE_DOES_NOT_EXIST */
    public const TABLE_DOES_NOT_EXIST = 1146;

    // PostgreSQL Error Codes
    /** @var string INVALID_AUTHORIZATION_SPECIFICATION */
    public const INVALID_AUTHORIZATION_SPECIFICATION = '28000';
    /** @var string INVALID_CATALOG_NAME */
    public const INVALID_CATALOG_NAME = '3D000';

    // Custom Luminova Error Codes
    /** @var int DATABASE_ERROR */
    public const DATABASE_ERROR = 1500;
    /** @var int FAILED_ALL_CONNECTION_ATTEMPTS */
    public const FAILED_ALL_CONNECTION_ATTEMPTS = 1503;
    /** @var int CONNECTION_LIMIT_EXCEEDED */
    public const CONNECTION_LIMIT_EXCEEDED = 1509;
    /** @var int INVALID_DATABASE_DRIVER */
    public const INVALID_DATABASE_DRIVER = 1406;
    /** @var int DATABASE_DRIVER_NOT_AVAILABLE */
    public const DATABASE_DRIVER_NOT_AVAILABLE = 1501;
    /** @var int DATABASE_TRANSACTION_READONLY_FAILED */
    public const DATABASE_TRANSACTION_READONLY_FAILED = 1417;
    /** @var int DATABASE_TRANSACTION_FAILED */
    public const DATABASE_TRANSACTION_FAILED = 1420;
    /** @var int TRANSACTION_SAVEPOINT_FAILED */
    public const TRANSACTION_SAVEPOINT_FAILED = 1418;
    /** @var int FAILED_TO_ROLLBACK_TRANSACTION */
    public const FAILED_TO_ROLLBACK_TRANSACTION = 1419;
    /** @var int NO_STATEMENT_TO_EXECUTE */
    public const NO_STATEMENT_TO_EXECUTE = 1499;
    /** @var int VALUE_FORBIDDEN */
    public const VALUE_FORBIDDEN = 1403;
    /** @var int INVALID_ARGUMENTS */
    public const INVALID_ARGUMENTS = 1001;
    /** @var int INVALID */
    public const INVALID = 1002;
    /** @var int RUNTIME_ERROR */
    public const RUNTIME_ERROR = 5001;
    /** @var int CLASS_NOT_FOUND */
    public const CLASS_NOT_FOUND = 5011;
    /** @var int STORAGE_ERROR */
    public const STORAGE_ERROR = 5079;
    /** @var int VIEW_NOT_FOUND */
    public const VIEW_NOT_FOUND = 404;
    /** @var int INPUT_VALIDATION_ERROR */
    public const INPUT_VALIDATION_ERROR = 4070;
    /** @var int ROUTING_ERROR */
    public const ROUTING_ERROR = 4161;
    /** @var int NOT_FOUND */
    public const NOT_FOUND = 4040;
    /** @var int BAD_METHOD_CALL */
    public const BAD_METHOD_CALL = 4051;
    /** @var int CACHE_ERROR */
    public const CACHE_ERROR = 5071;
    /** @var int FILESYSTEM_ERROR */
    public const FILESYSTEM_ERROR = 6204;
    /** @var int COOKIE_ERROR */
    public const COOKIE_ERROR = 4961;
    /** @var int DATETIME_ERROR */
    public const DATETIME_ERROR = 2306;
    /** @var int CRYPTOGRAPHY_ERROR */
    public const CRYPTOGRAPHY_ERROR = 3423;
    /** @var int WRITE_PERMISSION_DENIED */
    public const WRITE_PERMISSION_DENIED = 6205;
    /** @var int READ_PERMISSION_DENIED */
    public const READ_PERMISSION_DENIED = 6206;
    /** @var int READ_WRITE_PERMISSION_DENIED */
    public const READ_WRITE_PERMISSION_DENIED = 6209;
    /** @var int CREATE_DIR_FAILED */
    public const CREATE_DIR_FAILED = 6207;
    /** @var int SET_PERMISSION_FAILED */
    public const SET_PERMISSION_FAILED = 6208;
    /** @var int JSON_ERROR */
    public const JSON_ERROR = 4180;
    /** @var int SECURITY_ISSUE */
    public const SECURITY_ISSUE = 4973;
    /** @var int MAILER_ERROR */
    public const MAILER_ERROR = 449;
    /** @var int INVALID_CONTROLLER */
    public const INVALID_CONTROLLER = 1003;
    /** @var int INVALID_METHOD */
    public const INVALID_METHOD = 4052;
    /** @var int INVALID_REQUEST_METHOD */
    public const INVALID_REQUEST_METHOD = 4053;
    /** @var int NOT_ALLOWED */
    public const NOT_ALLOWED = 4061;
    /** @var int NOT_ALLOWED */
    public const NOT_SUPPORTED = 4062;
    /** @var int LOGIC_ERROR */
    public const LOGIC_ERROR = 4229;
    /** @var int UNDEFINED */
    public const UNDEFINED = 8790;

    /** @var int HTTP_CLIENT_ERROR */
    public const HTTP_CLIENT_ERROR = 4974;
    /** @var int HTTP_CONNECTION_ERROR */
    public const HTTP_CONNECTION_ERROR = 4975;
    /** @var int HTTP_REQUEST_ERROR */
    public const HTTP_REQUEST_ERROR = 4976;
    /** @var int HTTP_SERVER_ERROR */
    public const HTTP_SERVER_ERROR = 4977;

    /** @var int TERMINATE */
    public const TERMINATE = 1200;
    /** @var int TIMEOUT */
    public const TIMEOUT_ERROR = 1201;
    /** @var int PROCESS_ERROR */
    public const PROCESS_ERROR = 1202;
    
    // SQLite Error Codes
    /** @var int DATABASE_IS_FULL */
    public const DATABASE_IS_FULL = 5;
    /** @var int DATABASE_LOCKED */
    public const DATABASE_LOCKED = 6;
    /** @var int CANNOT_OPEN_DATABASE_FILE */
    public const CANNOT_OPEN_DATABASE_FILE = 14;

    /**
     * String error code.
     * 
     * @var string|int|null $strCode
     */
    protected string|int|null $strCode = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $message, string|int $code = 0, ?Throwable $previous = null)
    {
        // Only pass integer error code to parent constructor.
        parent::__construct($message, is_numeric($code) ? (int) $code : 0, $previous);

        // Set the code directly after parent initialized in case if it's a string error code.
        $this->setCode($code);

        // If debug tracing is enabled then store it in shared memory
        $this->setBacktrace($previous);
    }

    /**
     * {@inheritdoc}
     */
    public function setCode(string|int $code): self
    {
        // Set core last error code
        ErrorHandler::setLastErrorCode($code);

        if(!is_numeric($code)){
            $this->strCode = $code;
            return $this;
        }
        
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
    public function getErrorCode(): string|int
    {
        return $this->strCode ?? $this->code;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return ErrorHandler::getErrorName($this->getErrorCode());
    }

    /**
     * {@inheritdoc}
     */
    public function getFilteredMessage(): string
    {
        return ErrorHandler::filterMessage($this->message);
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
        return $this->message;
    }
    
    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf(
            'Exception: (%s) %s in %s on line %d',
            (string) $this->getErrorCode(),
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
        self::safeHandler($this);
    }

    /**
     * {@inheritdoc}
     */
    public function log(string $dispatch = 'exception'): void
    {
        Logger::dispatch($dispatch, $this->toString());
    }

    /**
     * {@inheritdoc}
     */
    public static function throwException(string $message, string|int $code = 0, ?Throwable $previous = null): void
    {
        if($previous instanceof self){
            $previous->handle();
            return;
        }

        $e = new static($message, $code, $previous);
        
        if($previous instanceof Throwable){
            $e->setFile($previous->getFile());
            $e->setLine($previous->getLine());
        }

        $e->handle();
    }

    /**
     * {@inheritdoc}
     */
    public static function throwAs(Throwable $e, ?string $class = null): void
    {
        if($e instanceof self){
            $e->handle();
            return;
        }

        $class ??= static::class;
        $new = new $class($e->getMessage(), $e->getCode(), $e->getPrevious() ?? $e);

        if($new instanceof self){
            $new->setFile($e->getFile());
            $new->setLine($e->getLine());
            $new->handle();
            return;
        }

        self::safeHandler($e);
    }

    /**
     * Set backtrace if debug tracing is enabled, store it in shared memory
     * in other to access it when error handler is called, since there is no other way to access trace information.
     * 
     * @param Throwable|null $previous The previous exception.
     * 
     * @return void
     */
    private function setBacktrace(?Throwable $previous = null): void
    {
        if(!SHOW_DEBUG_BACKTRACE){
            return;
        }

        $tracer = (($previous instanceof Throwable) && $previous->getTrace())
            ? $previous->getTrace()
            : $this->getTrace();
        
        if($tracer){
            ErrorHandler::setBacktrace($tracer, false);
        }
    }

    /**
     * Safely handles exceptions based on the application environment and exception type.
     *
     * This method determines how to handle exceptions based on whether the application
     * is running in CLI mode, in production, and whether the exception is considered fatal.
     * It may log the exception, display it, or re-throw it depending on the circumstances.
     *
     * @param Throwable $exception The exception to be handled.
     *
     * @return void This method doesn't return a value, but may exit the script or throw an exception.
     *
     * @throws Throwable Re-throws the exception if not in production or if the exception is fatal.
     */
    private static function safeHandler(Throwable $exception): void
    {
        if (Foundation::isCommand()) {
            if (env('throw.cli.exceptions', false)) {
                throw $exception;
            }

            include_once root('/resources/Views/system_errors/') . 'cli.php';
            exit(STATUS_ERROR);
        }

        if (PRODUCTION && !Foundation::isFatal($exception->getCode())) {
            if($exception instanceof self){
                $exception->log();
                return;
            }

            Logger::dispatch('exception', (string) $exception);
            return;
        }

        throw $exception;
    }
}