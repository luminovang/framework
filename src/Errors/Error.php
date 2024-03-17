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

use \Luminova\Base\BaseConfig;

class Error
{
    /**
     * Initializes error display
     * 
     * @return void 
    */
    public static function initialize(string $context = 'web'): void 
    {
        if($context !== 'web'){
            return;
        }

        if (PRODUCTION) {
            ini_set('display_errors', '0');
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }

        /**
         * Set the custom error handler for non-fatal errors
        */
        set_error_handler([static::class, 'handle']);

        /**
         * Register shutdown function to catch fatal errors
        */
        register_shutdown_function([static::class, 'shutdown']);
    }

    /**
     * Get error type
     * 
     * @param int $errno error code
     * 
     * @return string
    */
    public static function getName(int $errno): string 
    {
        $errors = [
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        ];
    
        return $errors[$errno] ?? 'Unknown Error';
    }

    /**
     * Display error
     * 
     * @param string $message Error message
     * @param int $code error code
     * 
     * @return void
    */
    public static function display(string $message, int $code = E_ERROR): void 
    {
        $path = path('views') . 'system_errors' . DIRECTORY_SEPARATOR . 'errors.php';
        $errors = [
            'message' => $message,
            'name' => static::getName($code)
        ];
        extract($errors);
        include $path;
        exit(0);
    }

    /**
     * Handle errors 
     * 
     * @param int $errno Error code
     * @param string $message Error message
     * @param string $errFile Error file
     * @param int $errLine
     * @param bool $shutdown handle shutdown
     * 
     * @return void
    */
    public static function handle(int $errno, string $message, string $errFile, int $errLine, bool $shutdown = false): void 
    {
        $errFile = BaseConfig::filterPath($errFile);
        $message = "Error [$errno]: $message in $errFile on line $errLine";

        if (!PRODUCTION && static::isFatal($errno)) {
            static::display($message, $errno);
        }

        logger('php_errors', $message);
    }

    /**
     * Handle shutdown errors 
     * 
     * @return void
    */
    public static function shutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && static::isFatal($error['type'])) {
            static::handle($error['type'], $error['message'], $error['file'], $error['line'], true);
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

    /**
     * Log a message at a specified log level.
     * 
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        logger($level, $message, $context);
    }
}