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
use \Luminova\Debugger\Performance;

final class Foundation 
{
    /**
     * Framework version name.
     * 
    * @var string VERSION
    */
    public const VERSION = '3.2.3';

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
    private static ?string $base = null;

    /**
     * Request url segments.
     * 
     * @var ?string $segments
    */
    private static ?string $segments = null;

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
     * System paths for filtering.
     * 
     * @var array<int,string> $systemPaths
    */
    private static array $systemPaths = [
        'public',
        'bin',
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
     * Start or stop application profiling.
     * 
     * @param string $action The name of the action (e.g, start or stop).
     * 
     * @return void
    */
    public static final function profiling(string $action): void
    {
        if(!PRODUCTION && env('debug.show.performance.profiling', false)){
            ($action === 'start' ? Performance::start() : Performance::stop());
        }
    }

    /**
     * Initializes error display.
     * 
     * @return void 
    */
    public static function initialize(): void 
    {
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
     * Get error type.
     * 
     * @param int $errno The error code.
     * 
     * @return string Return Error name by error code.
    */
    public static function getName(int $errno): string 
    {
        return static::$errorsNames[$errno] ?? 'UNKNOWN ERROR';
    }

    /**
     * Get error logging level.
     * 
     * @param int $errno The error code.
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
     * @param ErrorStack|null $stack The error stack containing errors to display.
     * @return void
     */
    private static function display(?ErrorStack $stack = null): void 
    {
        $path = APP_ROOT . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'system_errors' . DIRECTORY_SEPARATOR;

        if(!$stack instanceof ErrorStack){
            $view = 'info.php';
        }elseif (static::isCommand()) {
            $view = 'cli.php';
        } elseif (static::isApiContext()) {
            $view = (defined('IS_UP') ? env('app.api.prefix', 'api')  . '.php' : 'api.php');
        } else {
            $view = 'errors.php';
        }

        if(file_exists($path . $view)){
            include_once $path . $view;
            return;
        }

        if($view === 'cli.php'){
            echo 'An error is stopping application from running correctly.';
            return;
        }

        echo '<h1>Application Error!</h2><p>An error is stopping application from running correctly.</p>';
    }

    /**
     * Retrieve the server public controller directory.
     * Remove the index controller file name from the scrip name.
     *
     * @return string Return public directory path.
    */
    public static function getBase(): string
    {
        if (static::$base !== null) {
            return static::$base;
        }

        static::$base = ($_SERVER['SCRIPT_NAME'] ?? DIRECTORY_SEPARATOR);

        if(static::$base === DIRECTORY_SEPARATOR){
            return static::$base;
        }

        if (($last = strrpos(static::$base, DIRECTORY_SEPARATOR)) !== false && $last > 0) {
            static::$base = substr(static::$base, 0, $last) . DIRECTORY_SEPARATOR;
            return static::$base;
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
        if(NOVAKIT_ENV === null && !PRODUCTION){
            $base = rtrim(static::getBase(), 'public' . DIRECTORY_SEPARATOR);
            
            if (($basePos = strpos($path, $base)) !== false) {
                $path = trim(substr($path, $basePos + strlen($base)), DIRECTORY_SEPARATOR);
            }
        }else{
            $path = trim(static::filterPath($path), DIRECTORY_SEPARATOR);
        }

        if(str_starts_with($path, 'public' . DIRECTORY_SEPARATOR)){
            $path = ltrim($path, 'public' . DIRECTORY_SEPARATOR);
        }
 
        if(PRODUCTION){
            return APP_URL . '/' . $path;
        }

        $hostname = $_SERVER['HTTP_HOST'] 
            ?? $_SERVER['HOST'] 
            ?? $_SERVER['SERVER_NAME'] 
            ?? $_SERVER['SERVER_ADDR'] 
            ?? '';

        return URL_SCHEME . '://' . $hostname . '/' . $path;
    }

    /**
     * Get the request url segments as relative.
     * Removes the public controller path from uri if available.
     * 
     * @return string Relative url segment paths.
    */
    public static function getUriSegments(): string
    {
        if(static::$segments === null){
            static::$segments = '/';

            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = substr(rawurldecode($_SERVER['REQUEST_URI']), strlen(static::getBase()));

                // Remove url parameters if available.
                if ($uri !== '' && false !== ($pos = strpos($uri, '?'))) {
                    $uri = substr($uri, 0, $pos);
                }

                static::$segments = '/' . trim($uri, '/');
            }
        }

        return static::$segments;
    }

    /**
     * Get the current view segments as array.
     * 
     * @return array<int,string> The array list of url segments.
    */
    public static function getSegments(): array
    {
        $segments = static::getUriSegments();

        if($segments === '/'){
            return [''];
        }

        $segments = explode('/', trim($segments, '/'));
   
        if (($public = array_search('public', $segments)) !== false) {
            array_splice($segments, $public, 1);
        }

        return $segments;
    }

    /**
     * Generate cache key for storing and serving static pages.
     * 
     * @return string Return MD5 hashed page cache key.
     */
    public static function cacheKey(): string 
    {
        $url = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $url .= $_SERVER['REQUEST_URI'] ?? 'index';
        $url = strtr($url, [
            '/' => '-', 
            '?' => '-', 
            '&' => '-', 
            '=' => '-', 
            '#' => '-'
        ]);

        // Remove static cache extension to avoid creating 2 versions of same cache.
        if($types = env('page.caching.statics', false)){
            $url = preg_replace('/\.(' . $types . ')$/i', '', $url);
        }
        
        return md5($url);
    }

    /**
     * Check if the request URL indicates an API endpoint.
     * This method checks if the URL path starts with '/api' or 'public/api'.
     * 
     * @return bool Returns true if the URL indicates an API endpoint, false otherwise.
    */
    public static function isApiContext(): bool
    {
        return static::getSegments()[0] === (defined('IS_UP') ? env('app.api.prefix', 'api') : 'api');
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
     * @param int $errno The error code.
     * @param string $errstr The error message.
     * @param string $errFile The error file.
     * @param int $errLine The error line number.
     * 
     * @return bool Return true if error was handled by framework, false otherwise.
    */
    public static function handle(int $errno, string $errstr, string $errFile, int $errLine): bool 
    {
        if (error_reporting() === 0) {
            return false;
        }

        $stack = new ErrorStack($errstr, $errno);
        $stack->setFile(static::filterPath($errFile));
        $stack->setLine($errLine);
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
        if (($error = error_get_last()) === null || !isset($error['type'])) {
            return;
        }

        $isFatal = static::isFatal($error['type']);
        $isDisplay = ini_get('display_errors');
        $errName = static::getName($error['type']);

        // If error display is not enabled or error occurred on production
        // Display custom error page.
        if(!$isDisplay || PRODUCTION){

            $stack = null;

            if($isFatal){
                $stack = new ErrorStack($error['message'], $error['type']);
                $stack->setFile($error['file']);
                $stack->setLine($error['line']);
                $stack->setName($errName);
            }

            static::display($stack);
        }

        // If message is not empty and is not fatal error or on projection
        // Log the error message.
        if(!$isFatal || PRODUCTION){
            $level = static::getLevel($error['type']);
            $message = sprintf(
                "[%s (%s)] %s File: %s Line: %s\n", 
                $errName,
                (string) $error['type'] ?? 1,
                $error['message'],
                $error['file'],
                (string) $error['line']
            );

            // If the error allowed framework to boot,
            // Then we use logger to log the error
            if(defined('IS_UP')){
                logger($level, $message);
                return;
            }

            // If not create a customer logger.
            $message = sprintf("[%s] [%s] {$message}",  $level, date('Y-m-d\TH:i:sP'));
            $log =  APP_ROOT. 'writeable' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR. "{$level}.log";

            if (@file_put_contents($log, rtrim($message, "\n"), FILE_APPEND | LOCK_EX) === false) {
                @chmod($log, 0666);
            }
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