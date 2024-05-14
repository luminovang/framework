<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Library;

use \Luminova\Exceptions\RuntimeException;

final class Modules
{
    /**
     * Register the autoloader with PHP's SPL autoload stack.
    */
    public static function register(): void
    {
        spl_autoload_register([static::class, 'autoloadClass']);
    }

    /**
     * Autoload the specified class according to PSR-4 standard.
     *
     * @param string $class The fully qualified class name to load.
     */
    public static function autoloadClass(string $class): void
    {
        if (file_exists($modules = path('controllers') . 'Config' . DIRECTORY_SEPARATOR . 'Modules.php')) {

            $config = require $modules;

            if (isset($config['psr-4'])) {
                foreach ($config['psr-4'] as $namespace => $baseDir) {
                    $namespace = rtrim($namespace, '\\') . '\\';
                    $baseDir = path('library') . trim($baseDir, '/') . '/';

                    if (strpos($class, $namespace) === 0) {
                        $relativeClass = substr($class, strlen($namespace));
                        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

                        if (file_exists($file)) {
                            require $file;
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * Import a custom library into your project 
     * You must place your external libraries in libraries/libs/ directory
     * 
     * @param string $library the name of the library
     * @example Foo/Bar/Baz
     * @example Foo/Bar/Baz.php
     * @example Foo.php
     * @example Foo
     * 
     * @return bool true if the library was successfully imported
     * @throws RuntimeException if library could not be found
    */
    public static function import(string $library): bool
    {
        //$library = str_replace('\\', '/', $library);
        $file = rtrim($library, '/');

        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        $filePath = path('library') . $file;

        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }

        throw new RuntimeException("Library '$library' does not exist.");
    }
}