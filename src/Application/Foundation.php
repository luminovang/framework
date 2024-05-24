<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Application;

use \Luminova\Errors\ErrorStack;

final class Foundation 
{
    /**
     * Framework version name.
     * 
    * @var string VERSION
    */
    public const VERSION = '3.0.2';

    /**
     * Minimum required php version.
     * 
    * @var string MIN_PHP_VERSION 
    */
    public const MIN_PHP_VERSION = '8.0';

    /**
     * Command line tool version
     * 
     * @var string NOVAKIT_VERSION
    */
    public const NOVAKIT_VERSION = '2.9.7';

    /**
     * Server base path for router
     * 
     * @var ?string $base
    */
    private static string|null $base = null;

    /**
     * Stack cached errors
     * 
     * @var array<int,ErrorStack> $errors
    */
    private static array $errors = [];

    /**
     * Error codes
     * 
     * @var array<int,string> $errorsNames
    */
    private static array $errorsNames = [
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

    /**
     * System paths
     * 
     * @var array<int,string> $systemPaths
    */
    private static array $systemPaths = [
        'system',  
        'bootstrap',
        'resources', 
        'writeable', 
        'libraries', 
        'routes', 
        'builds',
        'app'
    ];

    /**
     * Get the framework copyright information
     *
     * @return string Return framework copyright message.
     * @internal
    */
    public static final function copyright(): string
    {
        return 'PHP Luminova (' . self::VERSION . ')';
    }

    /**
     * Get the framework version name or code.
     * 
     * @param bool $integer Return version code or version name (default: name).
     * 
     * @return string|int Return version name or code.
    */
    public static final function version(bool $integer = false): string|int
    {
        return $integer ? (int) strict(self::VERSION, 'int') : self::VERSION;
    }

    /**
     * Initializes error display
     * 
     * @return void 
    */
    public static function initialize(): void 
    {
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
        return static::$errorsNames[$errno] ?? 'UNKNOWN ERROR';
    }

    /**
     * Get error logging level
     * 
     * @param int $errno Error code
     * 
     * @return string Return error log level by error code.
    */
    public static function getLevel(int $errno): string 
    {
        return match($errno){
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING  => 'warning',
            E_NOTICE, E_USER_DEPRECATED, E_DEPRECATED, E_STRICT  => 'notice',
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
        if (static::isCommand()) {
            $view = 'cli.php';
        } elseif (static::isApiContext()) {
            $view = 'api.php';
        } else {
            $view = 'errors.php';
        }

        include_once APP_ROOT . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'system_errors' . DIRECTORY_SEPARATOR . $view;
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
     * Return server base path.
     *
     * @return string Application router base path
    */
    public static function getBase(): string
    {
        if (static::$base !== null) {
            return static::$base;
        }

        if (isset($_SERVER['SCRIPT_NAME'])) {
            $script = $_SERVER['SCRIPT_NAME'];

            if (($last = strrpos($script, '/')) !== false && $last > 0) {
                static::$base = substr($script, 0, $last) . '/';
                return static::$base;
            }
        }

        static::$base = '/';
        return static::$base;
    }

    /**
     * Convert relative path to absolute url.
     *
     * @param string $path Path to convert to absolute url.
     * 
     * @return string Return full url without system path.
    */
    public static function toAbsoluteUrl(string $path): string
    {
        $base = rtrim(static::getBase(), 'public/');
        
        if (($basePos = strpos($path, $base)) !== false) {
            $path = trim(substr($path, $basePos + strlen($base)), '/');
        }

        if(str_starts_with($path, 'public/')){
            $path = ltrim($path, 'public/');
        }
  
        if(PRODUCTION){
            return APP_URL . '/' . $path;
        }

        $hostname = $_SERVER['HTTP_HOST'] 
            ?? $_SERVER['HOST'] 
            ?? $_SERVER['SERVER_NAME'] 
            ?? $_SERVER['SERVER_ADDR'] 
            ?? '';

        if (!str_contains($hostname, ':')) {
            $hostname .= PROJECT_ID;
        }

        return URL_SCHEME . '://' . $hostname . '/' . $path;
    }

    /**
     * Get the current segment relative URI.
     * 
     * @return string Relative url segment paths.
    */
    public static function getUriSegments(): string
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), mb_strlen(static::getBase()));

            if (false !== ($pos = strpos($uri, '?'))) {
                $uri = substr($uri, 0, $pos);
            }

            return '/' . trim($uri, '/');
        }

        return '/';
    }

    /**
     * Get the current view segments as array.
     * 
     * @return array<int,string> Array list of url segments
    */
    public static function getSegments(): array
    {
        $segments = explode('/', trim(static::getUriSegments(), '/'));
   
        if (($public = array_search('public', $segments)) !== false) {
            array_splice($segments, $public, 1);
        }

        return $segments;
    }

    /**
     * Check if the request URL indicates an API endpoint.
     * This method checks if the URL path starts with '/api' or 'public/api'.
     * 
     * @return bool Returns true if the URL indicates an API endpoint, false otherwise.
    */
    public static function isApiContext(): bool
    {
        $segments = static::getSegments();
        return reset($segments) === 'api';
    }

    /**
     * Find whether the application is running in CLI mode.
     *
     * @return bool Return true if the request is made in CLI mode, false otherwise.
    */
    public static function isCommand(): bool
    {
        if(isset($_SERVER['REMOTE_ADDR'])){
            return false;
        }

        if(defined('STDIN') || php_sapi_name() === 'cli'){
            return true;
        }

        return (
            (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && isset($_SERVER['argv'])) ||
            array_key_exists('SHELL', $_ENV)
        );
    }

    /**
     * Filter the display path, to remove private directory paths before previewing to users.
     *
     * @param string $path The path to be filtered.
     * 
     * @return string Return the filtered path.
    */
    public static function filterPath(string $path): string 
    {
        $matching = '';
        foreach (static::$systemPaths as $directory) {
            $separator = $directory . DIRECTORY_SEPARATOR; 
            if (str_contains($path, $separator)) {
                $matching = $separator;
                break;
            }
        }

        if ($matching === '') {
            return $path;
        }

        return substr($path, strpos($path, $matching));
    }

    /**
     * Handle errors.
     * 
     * @param int $errno Error code.
     * @param string $errstr Error message.
     * @param string $errFile Error file.
     * @param int $errLine Error line number.
     * 
     * @return bool Return true if error was handled by framework, false otherwise.
    */
    public static function handle(int $errno, string $errstr, string $errfile, int $errline): bool 
    {
        if (error_reporting() === 0) {
            return false;
        }

        $stack = new ErrorStack($errstr, $errno);
        $stack->setFile(static::filterPath($errfile));
        $stack->setLine($errline);
        $stack->setName(static::getName($errno));
       
        self::$errors[] = $stack;
        return true;
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
     * Log an error message.
     * 
     * @param string $level Error level.
     * @param string $message Error message.
     * 
     * @return void
    */
    private static function log(string $level, string $message): void 
    {
        if(defined('IS_UP')){
            logger($level, $message);
            return;
        }

        $message =  "[" . date('Y-m-d\TH:i:sP') . "]: {$message}\n";
        $log =  APP_ROOT. 'writeable' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR. "{$level}.log";

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
    public static function isFatal(int $errno): bool 
    {
        return in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR]);
    }
}