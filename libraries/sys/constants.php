<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
/**
 * Check php requirements 
*/
if (version_compare(PHP_VERSION, 8.0, '<')) {
    $err = 'Your PHP version must be 8.0 or higher to run PHP Luminova framework. Current version: %s' . PHP_VERSION;
    if (!ini_get('display_errors')) {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            fwrite(STDERR, $err);
        } elseif (!headers_sent()) {
            echo $err;
        }
    }
    trigger_error($err, E_USER_ERROR);
    exit(1);
}

/**
 * @var string APP_ROOT system root 2 levels back
*/
defined('APP_ROOT') || define('APP_ROOT', dirname(__DIR__, 2));

if (!function_exists('root')) {
    /**
     * Return to the root directory of your project.
     *
     * @param string $directory The directory to start searching for .env
     * @param string $suffix Prepend a path to root directory.
     * 
     * @return string $path + $suffix
     */
    function root(string $directory = __DIR__, string $suffix = ''): string
    {
        $suffix = trim($suffix, DIRECTORY_SEPARATOR) . ($suffix ? DIRECTORY_SEPARATOR : '');

        if (file_exists(APP_ROOT . DIRECTORY_SEPARATOR . '.env')) {
           return APP_ROOT . DIRECTORY_SEPARATOR . $suffix;
        }

        $path = realpath($directory);

        if ($path === false) {
            return $suffix; 
        }

        if (file_exists($path . DIRECTORY_SEPARATOR . '.env')) {
            return $path . DIRECTORY_SEPARATOR . $suffix;
        }

        while ($path !== DIRECTORY_SEPARATOR && !file_exists($path . DIRECTORY_SEPARATOR . '.env')) {
            $path = dirname($path);
        }

        return ($path !== DIRECTORY_SEPARATOR) ? $path . DIRECTORY_SEPARATOR . $suffix : $suffix;
    }

}

if(!function_exists('env')){
    /**
     * Get environment variables.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * 
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed 
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', 'enable' => true,
            'false', 'disable' => false,
            'blank', => '',
            'null' => null,
            default => $value
        };
    }
}

if(!function_exists('setenv')){
    /**
     * Set an environment variable if it doesn't already exist.
     *
     * @param string $key The key of the environment variable.
     * @param string $value The value of the environment variable.
     * @param bool $add_to_env Save or update to .env file 
     * 
     * @return bool true on success or false on failure.
     */
    function setenv(string $key, string $value, bool $add_to_env = false): bool
    {
        $count = 0;
        if (!getenv($key, true)) {
            putenv("{$key}={$value}");
            $count++;
        }
    
        if (empty($_ENV[$key])) {
            $_ENV[$key] = $value;
            $count++;
        }
    
        if (empty($_SERVER[$key])) {
            $_SERVER[$key] = $value;
            $count++;
        }
    
        if ($count > 0 && $add_to_env) {
            $envFile = APP_ROOT . DIRECTORY_SEPARATOR . '.env';
            $envContents = file_get_contents($envFile);
            if($envContents === false){
                return false;
            }
            $keyExists = (strpos($envContents, "$key=") !== false || strpos($envContents, "$key =") !== false);
            //$keyValueExists = preg_match('/^' . preg_quote($key, '/') . '\s*=\s*.*$/m', $envContents);
    
            if (!$keyExists) {
                file_put_contents($envFile, "\n$key=$value", FILE_APPEND);
            } else {
                $newContents = preg_replace_callback('/(' . preg_quote($key, '/') . ')\s*=\s*(.*)/',
                    function($match) use ($value) {
                        return $match[1] . '=' . $value;
                    },
                    $envContents
                );
                file_put_contents($envFile, $newContents);
            }
        }

        return $count > 0;
    }
}

/**
 * Initialize and load the environment variables
*/
\Luminova\Config\DotEnv::register(APP_ROOT . DIRECTORY_SEPARATOR . '.env');

/**
 * Set default timezone
*/
date_default_timezone_set(env("app.timezone", 'UTC'));

/**
 * Limits the maximum execution time
*/
set_time_limit((int) env("script.execution.limit", 30));

/**
 * Set whether a client disconnect should abort script execution
*/
ignore_user_abort((bool) env('script.ignore.abort', true));

/**
 * @var int STATUS_OK success status code
 * @deprecated use STATUS_SUCCESS instead
*/
defined('STATUS_OK') || define('STATUS_OK', 0);

/**
 * @var int STATUS_OK success status code
*/
defined('STATUS_SUCCESS') || define('STATUS_SUCCESS', 0);

/**
 * @var int STATUS_ERROR error status code
*/
defined('STATUS_ERROR') || define('STATUS_ERROR', 1);

/**
 * @var string APP_VERSION application version
*/
defined('APP_VERSION') || define('APP_VERSION', env('app.version', '1.0.0'));

/**
 * @var string APP_NAME application name
*/
defined('APP_NAME') || define('APP_NAME', env('app.name', ''));

/**
 * @var string ENVIRONMENT application development state
*/
defined('ENVIRONMENT') || define('ENVIRONMENT', env('app.environment.mood', 'development'));

/**
 * @var bool PRODUCTION check if on production
*/
defined('PRODUCTION') || define('PRODUCTION', ENVIRONMENT === 'production');

/**
 * @var bool MAINTENANCE check if on maintenance mode
*/
defined('MAINTENANCE') || define('MAINTENANCE', (bool) env('app.maintenance.mood', false));

/**
 * @var string URL_PROTOCOL get request url protocol http or https 
*/
defined('URL_PROTOCOL') || define('URL_PROTOCOL', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://");

/**
 * @var string SERVER_PROTOCOL get request server protocol HTTP/1.1
*/
defined('SERVER_PROTOCOL') || define('SERVER_PROTOCOL', isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');

/**
 * @var string APP_HOSTNAME get application hostname example.com
*/
defined('APP_HOSTNAME') || define('APP_HOSTNAME', env('app.hostname', 'example.com'));

/**
 * @var string APP_URL get application www hostname http://example.com
*/
defined('APP_URL') || define('APP_URL', URL_PROTOCOL . APP_HOSTNAME);

/**
 * @var string APP_WWW_HOSTNAME get application url www.example.com
*/
defined('APP_WWW_HOSTNAME') || define('APP_WWW_HOSTNAME', 'www.' . APP_HOSTNAME);

/**
 * @var string APP_WWW_URL get application www url https://www.example.com
*/
defined('APP_WWW_URL') || define('APP_WWW_URL', URL_PROTOCOL . APP_WWW_HOSTNAME);

/**
 * @var string REQUEST_HOSTNAME get application current request hostname https://www.example.com
*/
defined('REQUEST_HOSTNAME') || define('REQUEST_HOSTNAME', URL_PROTOCOL . isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : APP_HOSTNAME);

/**
 * @var string REQUEST_URL get application current request url https://www.example.com/path/?query=
*/
defined('REQUEST_URL') || define('REQUEST_URL', REQUEST_HOSTNAME . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));

/**
 * @var bool SHOW_DEBUG_BACKTRACE show debug tracer
*/
defined('SHOW_DEBUG_BACKTRACE') || define('SHOW_DEBUG_BACKTRACE', (bool) env("show.debug.tracer", false));

/**
 * @var bool NOVAKIT_ENV show debug tracer
*/
defined('NOVAKIT_ENV') || define('NOVAKIT_ENV', isset($_SERVER['NOVAKIT_EXECUTION_ENV']) ? $_SERVER['NOVAKIT_EXECUTION_ENV'] : null);