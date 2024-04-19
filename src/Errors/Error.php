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

use \Luminova\Errors\ErrorStack;
use \Luminova\Http\Request;

final class Error
{
    /**
     * Stack cached errors
     * 
     * @var array<int,ErrorStack> $errors
    */
    private static array $errors = [];

    /**
     * Initializes error display
     * 
     * @param string $envirnment The application environment context.
     * 
     * @return void 
    */
    public static function initialize(string $envirnment = 'http'): void 
    {
        static::$errors = [];
        if ($envirnment !== 'http') {
            return;
        }

        $display = (!PRODUCTION && env('debug.display.errors', false)) ? '1' : '0';
        $reporting = PRODUCTION 
            ? E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED 
            : E_ALL;

        error_reporting($reporting);
        ini_set('display_errors', $display);
        set_error_handler([static::class, 'handle']);
        register_shutdown_function([static::class, 'shutdown']);
    }

    /**
     * Get error type
     * 
     * @param int $errno error code
     * 
     * @return string Return Error name by error code.
    */
    public static function getName(int $errno): string 
    {
        $errors = [
            E_ERROR             => 'ERROR',
            E_PARSE             => 'PARSE ERROR',
            E_CORE_ERROR        => 'CORE ERROR',
            E_COMPILE_ERROR     => 'COMPILE ERROR',

            E_WARNING           => 'WARNING',
            E_CORE_WARNING      => 'CORE WARNING',
            E_COMPILE_WARNING   => 'COMPILE WARNING',
            E_USER_WARNING      => 'USER WARNING',

            E_NOTICE            => 'NOTICE',
            E_USER_NOTICE       => 'USER NOTICE',
            E_STRICT            => 'STRICT NOTICE',

            E_USER_ERROR        => 'USER ERROR',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',

            E_DEPRECATED        => 'DEPRECATED',
            E_USER_DEPRECATED   => 'USER DEPRECATED'
        ];

        return $errors[$errno] ?? 'UNKNOWN ERROR';
    }

    /**
     * Get error logging level
     * 
     * @param int $errno Error code
     * 
     * @return string Return error log level by error code.
    */
    public static function  getLevel(int $errno): string 
    {
        return match($errno){
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING  => 'warning',
            E_NOTICE, E_USER_DEPRECATED, E_DEPRECATED, E_STRICT  => 'notice',
            E_NOTICE, E_STRICT  => 'debug',
            E_USER_ERROR, E_RECOVERABLE_ERROR  => 'error',
            default => 'critical',
        };
    }

    /**
     * Display system errors based on the given ErrorStack.
     *
     * This method includes an appropriate error view based on the environment and request type.
     *
     * @param ErrorStack $stack The error stack containing errors to display.
     * @param string $name The name of the error view to display (default: 'ERROR').
     * @return void
     */
    private static function display(ErrorStack $stack, string $name = 'ERROR'): void 
    {
        $path = path('views') . 'system_errors' . DIRECTORY_SEPARATOR;

        if (is_command()) {
            $path .= 'cli.php';
        } elseif(Request::isApi()) {
            $path .= 'api.php';
        } else {
            $path .= 'errors.php';
        }
        
        include_once $path;
    }

    /**
     * Get stacked errors.
     * 
     * @return array<int,ErrorStack> Error instance.
    */
    public function getErrors(): array 
    {
        return static::$errors;
    }

    /**
     * Handle errors 
     * 
     * @param int $errno Error code
     * @param string $message Error message
     * @param string $errFile Error file
     * @param int $errLine Error line number
     * @param bool $shutdown handle shutdown
     * 
     * @return void
    */
    public static function handle(int $errno, string $errstr, string $errfile, string $errline): void 
    {
        if (error_reporting() === 0) {
            return;
        }

        $stack = new ErrorStack($errstr, $errno);
        $stack->setFile(filter_paths($errfile));
        $stack->setLine($errline);
       
        self::$errors[] = $stack;

        return;
    }

    /**
     * Handle shutdown errors 
     * 
     * @return void
    */
    public static function shutdown(): void 
    {
        $error = error_get_last();
        if ($error !== null && isset($error['type']) && static::isFatal($error['type'])) {
            $stack = new ErrorStack($error['message'], $error['type']);
            $stack->setFile($error['file']);
            $stack->setLine($error['line']);
   
            if(!PRODUCTION && static::isFatal($stack->getCode())){
                static::display($stack, static::getName($stack->getCode()));
            }else{
                static::$errors[] = $stack;
            }
        }

        foreach (static::$errors as $err) {
            if(!static::isFatal($err->getCode())){
                $name = static::getName($err->getCode());
                $message = "[{$name} ({$err->getCode()})] {$err->getMessage()} File: {$err->getFile()} Line: {$err->getLine()}";
                logger(static::getLevel($err->getCode()), $message);
            }
        }
    }

    /**
     * Check if error is fatal
     * 
     * @param int $errno Error code
     * 
     * @return bool
    */
    private static function isFatal(int $errno): bool 
    {
        return in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]);
    }
}