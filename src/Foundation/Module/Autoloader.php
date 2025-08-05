<?php
/**
 * Luminova Framework PSR-4 Autoloader
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Foundation\Module;

use \RuntimeException;
use \InvalidArgumentException;
use function \Luminova\Funcs\root;

final class Autoloader
{
    private static bool $registered = false;
    private static ?string $config = null;
    private static ?string $libs = null;
    private static ?array $modules = null;
    private static array $psr4 = [];

    /**
     * Register the autoloader with PHP SPL stack.
     *
     * Loads module configuration and sets up PSR-4 class resolution.
     * Should be called once during application bootstrap.
     *
     * @return bool Return true on success or false on failure. 
     * 
     * @throws RuntimeException If the module configuration file is missing.
     * @throws InvalidArgumentException If the namespace is empty or not PSR-4 compliant.
     * @throws \TypeError If spl autoload error.
     */
    public static function register(): bool
    {
        if (self::$registered) {
            return true;
        }

        self::$config ??= root('/app/Config/', 'Modules.php');

        if (!is_file(self::$config)) {
            throw new RuntimeException(sprintf(
                'Autoloader module configuration file not found: %s',
                self::$modules
            ));
        }

        self::$modules ??= include_once self::$config;

        if(self::$modules && self::$modules !== []){
            self::$psr4 = array_merge(self::$psr4, self::$modules['psr-4']);
        }

        if (self::$psr4 === []) {
            return false;
        }

        self::$libs ??= root('/libraries/libs/');

        $result = spl_autoload_register(
            [self::class, 'resolve'], 
            true, 
            true
        );

        self::$registered = true;
        self::$modules = null;

        return $result;
    }

    /**
     * Unregister the autoloader and clears all runtime mappings.
     *
     * This removes the class resolver from PHP’s SPL autoload stack and resets all
     * internal state used during registration:
     * 
     *  - `$registered` is set back to `false`
     *  - module configuration (`$modules`) is cleared
     *  - custom PSR-4 mappings (`$psr4`) are wiped
     *
     * @return bool Returns true if the autoloader was successfully removed or was not registered.
     * 
     * > **Note:**  
     * > Only call this in controlled environments such as testing, debugging 
     * > loaders, or reloading modules in long-running processes.  
     * > Removing the autoloader in a live application will break class loading.
     *
     */
    public static function unregister(): bool
    {
        if(!self::$registered){
            return true;
        }

        $result = spl_autoload_unregister([self::class, 'resolve']);

        self::$registered = false;
        self::$modules = null;
        self::$psr4 = [];

        return $result;
    }

    /**
     * Add a custom PSR-4 namespace mapping at runtime.
     *
     * PSR-4 namespace must contain only valid namespace characters and separators
     * (Letters, numbers, underscores, and backslashes. No punctuation circus.)
     * 
     * @param string $namespace Fully-qualified namespace.
     * @param string $baseDir Relative or absolute base directory for this namespace.
     * 
     * @return void
     * @throws InvalidArgumentException If the namespace is empty or not PSR-4 compliant.
     * 
     * @example - Example:
     * ```php
     * use Luminova\Foundation\Module\Autoloader;
     * 
     * Autoloader::psr4('Example\\MyNamespace\\', '/example/MyClass/');
     * 
     * Autoloader::register()
     * ```
     * > **Note:**
     * > Your class base must be located in `/libraries/libs/`.
     * > (e.g, '/libraries/libs/example/MyClass/MyNamespace.php')
     */
    public static function psr4(string $namespace, string $baseDir): void
    {
        $namespace = trim($namespace, " \\\\");
        self::assert($namespace);

        self::$psr4[$namespace] = $baseDir;
    }

    /**
     * Assert namespace.
     * 
     * @param string $namespace Fully-qualified namespace.
     * 
     * @return void
     * @throws InvalidArgumentException If the namespace is empty or not PSR-4 compliant.
     */
    private static function assert(string $namespace): void
    {
        if ($namespace === '') {
            throw new InvalidArgumentException(
                'Autoloader error: Namespace cannot be empty.'
            );
        }
        
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)*\\\\?$/u', $namespace)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Autoloader error: Invalid namespace "%s". Namespace must follow PSR-4 rules and may end with exactly two backslashes.', 
                    $namespace
                )
            );
        }
    }

    /**
     * Resolves and loads a class according to PSR-4 standard.
     *
     * @param string $class Fully qualified class name.
     * 
     * @return void
     * @throws InvalidArgumentException If the namespace is empty or not PSR-4 compliant.
     */
    public static function resolve(string $class): void
    {
        if (self::$psr4 === []) {
            return;
        }

        foreach (self::$psr4 as $namespace => $baseDir) {
            $namespace = trim($namespace, " \\\\") . '\\';
            self::assert($namespace);


            $baseDir = self::$libs . trim($baseDir, '/') . '/';

            if (!str_starts_with($class, $namespace)) {
                continue;
            }

            $relativeClass = substr($class, strlen($namespace));
            $file = $baseDir . ltrim(str_replace('\\', '/', $relativeClass), '/') . '.php';

        
            if (!is_file($file)) {
                throw new RuntimeException(sprintf(
                    'Autoloader error: File for class "%s" not found at "%s". Using namespace "%s".',
                    $class,
                    $file,
                    $namespace
                ));
            }

            include $file;
            return;
        }
    }
}