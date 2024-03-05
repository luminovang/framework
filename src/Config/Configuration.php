<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Config;

abstract class Configuration 
{
    public const DS = DIRECTORY_SEPARATOR; 
    /**
    * @var string $version version name
    */
    public static $version = '2.6.0';

    /**
    * @var int $versionCode version code
    */
    public static $versionCode = 260;

    /**
     * Minimum required php version
    * @var string MIN_PHP_VERSION 
    */
    public const MIN_PHP_VERSION = '8.0';

    /**
    * @var array allowPreviews allow previews
    */
    private static array $allowPreviews = ['system', 'app', 'resources', 'writable', 'libraries'];

    /**
     * Magic method to retrieve session properties.
     *
     * @param string $propertyName The name of the property to retrieve.
     * @return mixed
     */
    public function __get(string $propertyName): mixed 
    {
        $data = env($propertyName);

        if ($data === null) {
            $data = env(self::variableToNotation($propertyName, ".")) ?? env(self::variableToNotation($propertyName, "_")) ?? "";
        }

        return $data;
    }


    /**
     * Get the application name.
     *
     * @return string
     */
    public static function appName(): string 
    {
        return self::getString("app.name");
    }

    /**
     * Get the host name.
     *
     * @return string
     */
    public static function hostName(): string 
    {
        return self::getString("app.hostname");
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public static function baseUrl(): string 
    {
        return self::getString("app.base.url");
    }

    /**
     * Get the base www URL.
     *
     * @return string
     */
    public static function baseWwwUrl(): string 
    {
        return self::getString("app.base.www.url");
    }

    /**
     * Get the application version.
     *
     * @return string
     */
    public static function appVersion(): string 
    {
        return self::getString("app.version");
    }

    /**
     * Get the file version.
     *
     * @return string
     */
    public static function fileVersion(): string 
    {
        return self::getString("app.file.version");
    }

    /**
     * Check if minification is enabled.
     *
     * @return int
     */
    public static function shouldMinify(): int 
    {
        return self::getInt("build.minify");
    }

    /**
     * Get the URL protocol (http or https).
     *
     * @return string
     */
    public static function urlProtocol(): string 
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    }

    /**
     * Get the full URL.
     *
     * @return string
     */
    public static function getFullUrl(): string {
        return self::urlProtocol() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * Get the request host.
     *
     * @return string
     */
    public static function getRequestHost(): string {
        return self::urlProtocol() . $_SERVER['HTTP_HOST'];
    }

    /**
     * Get development environment
     *
     * @return string
    */
    public static function getEnvironment(): string
    {
        return self::getString("app.environment.mood");
    }

    /**
     * Check if app is on maintenance
     *
     * @return bool
    */
    public static function isMaintenance(): bool
    {
        return self::getBoolean("app.maintenance.mood", false);
    }

    /**
     * Check if the application is in production mode.
     *
     * @return bool
     */
    public static function isProduction(): bool
    {
        return (self::getEnvironment() === "production");
    }

   /**
     * Check if the application is running locally.
     *
     * @return bool
     */
    public static function isLocal(): bool
    {
        $isCliServer = isset($_SERVER['LOCAL_SERVER_INSTANCE']);
        $host = $_SERVER['SERVER_NAME'] ?? '';
        $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;

        return $isCliServer || $isLocal;
    }

    /**
     * Check if the application is running on local server.
     *
     * @return bool
    */
    public static function isLocalServer(): bool
    {
        return isset($_SERVER['LOCAL_SERVER_INSTANCE']);
    }

    /**
     * Check if the application should use custom public as path 
     * If the local server is not running and not on production server
     * If the document root is not changed to "public", manually enable the app to use "public" as the default
     *
     * @return bool
    */
    public static function usePublic(): bool
    {
        return !self::isLocalServer() && !self::isProduction();
    }

    /**
     * Get the root directory.
     *
     * @param string $directory The directory to start searching for composer.json or system directory.
     * 
     * @return string
     */
    public static function root(string $directory = __DIR__, string $suffix = ''): string
    {
        return root($directory, $suffix);
    }

     /**
     * Get the root directory.
     *
     * @param string $directory The directory to start searching for composer.json.
     * 
     * @deprecated This method has been deprecated use root($directory, $suffix) instead
     * @return string|null
     */
    public static function getRootDirectory(string $directory): ?string
    {
        return self::root($directory);
    }


    /**
     * Filter the path to match to allowed in error directories preview.
     *
     * @param string $path The path to be filtered.
     * 
     * @return string
     */
    public static function filterPath(string $path): string 
    {
        $matching = '';

        foreach (self::$allowPreviews as $directory) {
            $separator = $directory . DIRECTORY_SEPARATOR; 
            if (strpos($path, $separator) !== false) {
                $matching = $separator;
                break;
            }
        }

        if ($matching !== '') {
            $filter = substr($path, strpos($path, $matching));

            return $filter;
        } else {
            return basename($path);
        }
    }

    /**
     * Get environment configuration variables.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * 
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed 
    {
        return env($key, $default);
    }

    /**
     * Set an environment variable if it doesn't already exist.
     *
     * @param string $name The name of the environment variable.
     * @param string $value The value of the environment variable.
     * 
     * @return void
     */
    public static function set(string $name, string $value): void
    {
        setenv($name, $value);
    }

    /**
     * Get environment configuration variables.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * 
     * @deprecated This method will be removed in the next major release use get instead
     * @return mixed
     */
    public static function getVariables(string $key, mixed $default = null): mixed 
    {
        return env($key, $default);
    }

    /**
     * Get environment configuration variables.
     *
     * @param string $key The key to retrieve.
     * @param string $default The default value to return if the key is not found.
     * 
     * @return string
     */
    public static function getString(string $key, string $default = ''): string 
    {
        $value = env($key, $default);
        
        if( $value === null){
            return '';
        }

        return (string) $value;
    }

    /**
     * Get environment variable as integer value
     *
     * @param string $key variable name
     * @param int $default fallback to default
     * @return bool
    */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = env($key, $default);
        return (int) $value;
    }

    /**
     * Get environment variable as boolean
     *
     * @param string $key variable name
     * @param bool $default fallback to default
     * @return bool
    */
    public static function getBoolean(string $key, bool $default = false): bool
    {
        $value = env($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        $value = strtolower($value);
        return $value === 'true' || $value === '1';
    }

    /**
     * Get environment variable as default null
     *
     * @param string $key variable name
     * @param mixed|null $default fallback to default
     * @return bool
    */
    public static function getMixedNull(string $key, mixed $default = null): mixed
    {
        $value = env($key, $default);

        if ($value === '' || $value === []) {
            return null;
        }

        //return ($value != 0 && empty($value) ? null : $value);
        return $value;
    }


    /**
     * Convert variable to dot or underscore notation.
     *
     * @param string $input The input string .
     * @param string $notation The conversion notion
     * @return string
    */

    public static function variableToNotation(string $input, string $notation = "."): string 
    {
        if ($notation === ".") {
            $output = str_replace('_', '.', $input);
        } elseif ($notation === "_") {
            $output = str_replace('.', '_', $input);
        } else {
            return $input; 
        }
    
        if ($notation === ".") {
            $output = preg_replace('/([a-z0-9])([A-Z])/', '$1.$2', $output);
        } elseif ($notation === "_") {
            $output = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $output);
        }
    
        // Remove leading dot or underscore (if any)
        $output = ltrim($output, $notation);
    
        return $notation === "_" ? strtoupper($output) : strtolower($output);
    }

    /**
     * Get the framework copyright information
     *
     * @return string
     */
    public static function copyright(): string
    {
        return 'PHP Luminova (' . self::$version . ')';
    }

    /**
     * Get the framework version number
     *
     * @return string
     */
    public static function version(): string
    {
        return self::$version;
    }
}
