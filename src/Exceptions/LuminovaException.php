<?php
declare(strict_types=1);
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

use \Exception;
use \Throwable;
use \Stringable;
use Luminova\Boot;
use Luminova\Luminova;
use Luminova\Logger\Logger;
use Luminova\Debugger\Tracer;
use Luminova\Exceptions\ErrorCode;
use Luminova\Foundation\Error\Error;
use Luminova\Interface\ExceptionInterface;
use function Luminova\Funcs\{root, get_class_name};

abstract class LuminovaException extends Exception implements ExceptionInterface, Stringable
{
    /**
     * String error code.
     * 
     * @var string|int|null $strCode
     */
    protected string|int|null $strCode = null;

    /**
     * Formatted error message.
     * 
     * @var string|null $description
     */
    protected ?string $description = null;

    /**
     * Handling status.
     * 
     * @var bool $isHandling
     */
    private static bool $isHandling = false;

    /**
     * Create a new exception instance.
     * 
     * Accepts a message, an optional string or integer code, and an optional previous exception.
     * The created object can be thrown with `throw` or passed to methods and return types.
     *
     * @param string $message The exception message.
     * @param string|int $code The exception code as a string or integer (default: 0).
     * @param Throwable|null $previous The previous exception instance, if any (default: null).
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
    public function isCode(string|array|int $code): bool
    {
        return is_array($code) 
            ? in_array($this->getErrorCode(), $code, true) 
            : $this->getErrorCode() === $code;
    }

    /**
     * {@inheritdoc}
     */
    public function setCode(string|int $code): self
    {
        // Set core last error code
        Tracer::setLastErrorCode($code);

        if(!is_numeric($code)){
            $this->strCode = $code;
            return $this;
        }
        
        $this->strCode = null;
        $this->code = (int) $code;
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
        return ErrorCode::getName($this->getErrorCode());
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description ??= Error::sanitizeExceptionMessage(
            $this->message
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBacktrace(): array 
    {
        return $this->getTrace() ?: Tracer::getBacktrace();
    }
    
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return parent::__toString();
    }
    
    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf(
            '%s: %s in %s:%d (code: %s)',
            get_class_name(static::class),
            $this->getDescription(),
            $this->file,
            $this->line,
            (string) $this->getErrorCode()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function handle(): void
    {
        self::safeErrorHandler($this);
    }

    /**
     * {@inheritdoc}
     */
    public function log(?string $dispatch = null): void
    {
        self::tryLog($this, $dispatch);
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
        $e = new static($message, $code, $previous);

        [$file, $line] = ($previous instanceof Throwable) 
            ? [$previous->getFile(), $previous->getLine()]
            : Tracer::trace(2);
        
        if($file){
            $e->setFile($file)->setLine($line);
        }
        
        throw $e;
    }

    /**
     * {@inheritdoc}
     */
    public static function handleException(
        string $message, 
        string|int $code = 0, 
        ?Throwable $previous = null
    ): void
    {
        $e = new static($message, $code, $previous);

        [$file, $line] = ($previous instanceof Throwable) 
            ? [$previous->getFile(), $previous->getLine()]
            : Tracer::trace(2);
        
        if($file){
            $e->setFile($file)->setLine($line);
        }
        
        self::safeErrorHandler($e);
    }

    /**
     * {@inheritdoc}
     */
    public static function throwAs(Throwable $e, ?string $abstract = null): void
    {
        $class = null;

        if (
            $abstract !== null 
            && class_exists($abstract) 
            && is_a($abstract, Throwable::class, true)
        ) {
            $class = $abstract;
        }

        $class ??= static::class;
        $new = new $class($e->getMessage(), $e->getCode(), $e->getPrevious() ?? $e);

        if($new instanceof self){
            $new->setFile($e->getFile())
                ->setLine($e->getLine());
        }

        throw $new;
    }

    /**
     * {@inheritdoc}
     */
    public static function trace(int $depth, int $options = DEBUG_BACKTRACE_IGNORE_ARGS): array 
    {
        Error::deprecate(sprintf(
            'Method %s::trace() is deprecated. Use %s::trace() instead.',
            static::class,
            Tracer::class
        ));
        return [];
    }

    /**
     * Set backtrace if debug tracing is enabled.
     * 
     * stores trace in shared memory to allow access it when error handler is called, 
     * since there is no other way to access trace information.
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
            Tracer::setBacktrace($tracer, false);
        }
    }

    /**
     * Handles the exception based on the environment and error severity.
     *
     * @param Throwable $e The exception to be handled.
     * 
     * @return never
     * @throws ExceptionInterface<Throwable> Re-throws the exception if not in production 
     *      or if the exception is fatal.
     */
    private static function safeErrorHandler(Throwable $e): void
    {
        if (self::$isHandling) {
            self::tryLog($e);
            return;
        }

        $isCommand = Luminova::isCommand();

        if ($isCommand && env('throw.cli.exceptions', false)) {
            throw $e;
        }

        self::$isHandling = true;

        try{
            if (PRODUCTION && !ErrorCode::isFatal($e->getCode())) {
                return;
            }

            $template = $isCommand 
                ? 'cli.php' 
                : (Luminova::isApiPrefix() ? 'api.php' : 'errors.php');

            Boot::import(
                "app:Errors/Defaults/{$template}", 
                throw: false, 
                once:  true, 
                scope: ['error' => $e]
            );
            exit(STATUS_ERROR);
        } finally {
            self::$isHandling = false;
            self::tryLog($e);
        }
    }

    /**
     * Build a normalized log context from a throwable.
     *
     * Extracts core exception metadata and resolves the most appropriate
     * stack trace for logging or remote debugging.
     *
     * @param Throwable $e The caught exception or error.
     * @param bool $stacktrace Wether to include stacktrace.
     * 
     * @return array<string,mixed> Structured log context for tracing.
     */
    private static function getLogContext(Throwable $e, bool $stacktrace = true): array
    {
        $ctx = [
            'class' => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine()
        ];

        if($stacktrace){
            $ctx['trace'] = ($e instanceof self)
                ? $e->getBacktrace()
                : $e->getTrace();
        }

        return $ctx;
    }

    /**
     * Logs an exception message to the application log.
     *
     * @param Throwable $e The exception object.
     * @param string|null $level The log level at which the exception message should be dispatched.
     * 
     * @return void
     */
    private static function tryLog(Throwable $e, ?string $level = null): void 
    {
        $trace = self::getLogContext($e);
        $message = $e->getMessage();
        $code = $e->getCode();

        if($e instanceof self && str_contains($message, 'Stack trace')){
            $message = $e->getDescription();
        }

        if (ErrorCode::isFatal($code)) {
            $level ??= 'critical';

            Logger::tracer($trace);
        }

        $level ??= ErrorCode::getLevel($code);

        try{
            Logger::dispatch($level, $message, $trace);
        }catch(Throwable $e){
            self::tryPhpLog($e->getMessage(), $level);
        }
    }

    /**
     * Log PHP errors.
     *
     * @param string $message The exception message to be logged.
     * @param string $level The log level.
     * 
     * @return bool
     */
    private static function tryPhpLog(
        string $message, 
        string $level = 'php'
    ): bool 
    {
        $result = false;
        $filename = root('/writeable/logs/', "{$level}.log");
        $log = sprintf(
            "[%s] [%s] PHPLogging %s%s",
            date(DATE_ATOM),
            strtoupper($level),
            $message,
            PHP_EOL
        );

        try{
            $result = error_log($log, 3, $filename);

            if ($result === false && PRODUCTION) {
                return error_log($log);
            }
        } catch(Throwable){}

        return $result;
    }
}