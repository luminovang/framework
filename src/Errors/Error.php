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

/**
 * If composer didn't load correctly manually load our configuration needed for error message.
*/
if(!defined('APP_ROOT')){
    require_once __DIR__ . '/../../system/Config/DotEnv.php';
    require_once __DIR__ . '/../../libraries/sys/constants.php';
    require_once __DIR__ . '/../../libraries/sys/functions.php';
    require_once __DIR__ . '/ErrorStack.php';
}

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
     * @param string $environment The application environment context.
     * 
     * @return void 
    */
    public static function initialize(string $environment = 'http'): void 
    {
        if ($environment !== 'http') {
            return;
        }

        static::$errors = [];
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
     * @return void
     */
    private static function display(ErrorStack $stack): void 
    {
        $path = __DIR__ . '/../../resources/views/system_errors/';

        if (is_command()) {
            $path .= 'cli.php';
        } elseif(static::isApi()) {
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
     * Check if the request URL indicates an API endpoint.
     *
     * This method checks if the URL path starts with '/api' or 'public/api'.
     * 
     * @return bool Returns true if the URL indicates an API endpoint, false otherwise.
    */
    public static function isApi(): bool
    {
        $url = ($_SERVER['REQUEST_URI']??'');

        if($url === ''){
            return false;
        }

        $segments = explode('/', trim($url, '/'));

        // Check if the URL path starts with '/api' or 'public/api'
        if (!empty($segments) && ($segments[0] === 'api' || ($segments[0] === 'public' && isset($segments[1]) && $segments[1] === 'api'))) {
            return true;
        }

        // Additional check for custom project structure like '/my-project/api'
        if (basename(root(__DIR__)) === $segments[0] && isset($segments[2]) && $segments[2] === 'api') {
            return true;
        }

        return false;
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
        $stack->setName(static::getName($errno));
       
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
            $stack->setName(static::getName($error['type']));
            static::$errors[] = $stack;

            if(static::isFatal($stack->getCode()) && !ini_get('display_errors')){
                static::display($stack);
            }
        }

        foreach (static::$errors as $err) {
            if(!static::isFatal($err->getCode())){
                $message = "[{$err->getName()} ({$err->getCode()})] {$err->getMessage()} File: {$err->getFile()} Line: {$err->getLine()}";
                static::log(static::getLevel($err->getCode()), $message);
            }
        }
    }

    /**
     * Log an error message 
     * 
     * @param string $level Error level
     * @param string $message Error message
     * 
     * @return void
    */
    private static function log(string $level, string $message): void 
    {
        if(function_exists('logger')){
            logger($level, $message);
            return;
        }

        $time = date('Y-m-d\TH:i:sP');
        $message = "[{$time}]: {$message}\n";

        $log = __DIR__ . "/../../writeable/log/{$level}.log";

        if (@file_put_contents($log, $message, FILE_APPEND | LOCK_EX) === false) {
            @chmod($log, 0666);
        }
    }

    /**
     * Check if error is fatal
     * 
     * @param int $errno Error code
     * 
     *@return bool Return true if is fatal, otherwise false.
    */
    private static function isFatal(int $errno): bool 
    {
        return in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]);
    }
}