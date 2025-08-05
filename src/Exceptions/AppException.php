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
use \Exception;
use \Stringable;
use \Luminova\Luminova;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Foundation\Error\Guard;
use \Luminova\Interface\ExceptionInterface;
use function \Luminova\Funcs\import;

abstract class AppException extends Exception implements ExceptionInterface, Stringable
{
    /**
     * String error code.
     * 
     * @var string|int|null $strCode
     */
    protected string|int|null $strCode = null;

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
        Guard::setLastErrorCode($code);

        if(!is_numeric($code)){
            $this->strCode = $code;
            return $this;
        }
        
        $this->strCode = null;
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
        return ErrorCode::getName($this->getErrorCode());
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return Guard::sanitizeMessage($this->message);
    }

    /**
     * Get filtered error message.
     * 
     * @deprecated Since 3.6.8, Use getDescription() instead.
     */
    public function getFilteredMessage(): string 
    {
        return $this->getDescription();
    }

    /**
     * {@inheritdoc}
     */
    public function getBacktrace(): array 
    {
        return $this->getTrace() ?: Guard::getBacktrace();
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
        self::logging($this->toString(), $dispatch);
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
        [$file, $line] = ($previous instanceof Throwable) 
            ? [$previous->getFile(), $previous->getLine()]
            : self::trace(2);
        
        if($file){
            $e->setFile($file)->setLine($line);
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
            $new->setFile($e->getFile())
                ->setLine($e->getLine())
                ->handle();
            return;
        }

        self::safeHandler($e);
    }

    /**
     * {@inheritdoc}
     */
    public static function trace(int $depth, int $options = DEBUG_BACKTRACE_IGNORE_ARGS): array 
    {
        $limit = $depth + 1;
        $debug = debug_backtrace($options, $limit);
        $trace = $debug[$depth] ?? $debug[$limit] ?? [];

        return [
            $trace['file'] ?? null,
            (int) ($trace['line'] ?? 1),
        ];
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
            Guard::setBacktrace($tracer, false);
        }
    }

    /**
     * Handles the exception based on the environment and error severity.
     *
     * @param Throwable $error The exception to be handled.
     * 
     * @return never
     * @throws ExceptionInterface<Throwable> Re-throws the exception if not in production or if the exception is fatal.
     */
    private static function safeHandler(Throwable $error): void
    {
        if (self::$isHandling) {
            error_log('[Recursive Exception Prevented]: ' . $error->getMessage());
            return;
        }

        self::$isHandling = true;

        try {
            if (Luminova::isCommand()) {
                if (env('throw.cli.exceptions', false)) {
                    throw $error;
                }

                import('app:Errors/Defaults/cli.php', throw: false, once: true, scope: ['error' => $error]);
                exit(STATUS_ERROR);
            }

            if (PRODUCTION) {
                if ($error instanceof self) {
                    $error->log();
                }else{
                    self::logging((string) $error);
                }

                if (!ErrorCode::isFatal($error->getCode())) {
                    return;
                }

                import('app:Errors/Defaults/errors.php', throw: false, once: true, scope: ['error' => $error]);
                exit(STATUS_ERROR);
            }

            throw $error;
        } finally {
            self::$isHandling = false;
        }
    }

    /**
     * Logs an exception message to the application log.
     *
     * @param string $exception The exception message to be logged.
     * @param string $level The log level at which the exception message should be dispatched.
     *                       The default log level is 'exception'.
     *
     * @return void 
     */
    private static function logging(string $exception, string $level = 'exception'): void 
    {
        try{
            Logger::dispatch($level, (string) $exception);
        }catch(Throwable $e){
            error_log('[Exception Logging Failed]: ' . $e->getMessage());
        }
    }
}