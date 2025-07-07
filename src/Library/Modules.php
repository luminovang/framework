<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Library;

use function \Luminova\Funcs\{
    root,
    path
};

final class Modules
{
    /**
     * Register the autoload with PHP's SPL autoload stack.
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
        if (file_exists($modules = root('/app/Config/', 'Modules.php'))) {

            $config = require_once $modules;

            if (isset($config['psr-4'])) {
                foreach ($config['psr-4'] as $namespace => $baseDir) {
                    $namespace = rtrim($namespace, '\\') . '\\';
                    $baseDir = path('library') . trim($baseDir, '/') . '/';

                    if (str_starts_with($class, $namespace)) {
                        $relativeClass = substr($class, strlen($namespace));
                        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

                        if (file_exists($file)) {
                            require_once $file;
                            return;
                        }
                    }
                }
            }
        }
    }
}