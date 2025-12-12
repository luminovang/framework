<?php
/**
 * Luminova Framework ENV helper class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Config;

use \Throwable;
use \SplFileObject;
use Luminova\Runtime;
use Luminova\Luminova;
use \RuntimeException;
use Luminova\Storage\Filesystem;

/**
 * Helper function:
 * 
 * @see \env()
 * @see \setenv()
 */
final class Env
{
    /**
     * Fallback identifier for non-existing keys.
     * 
     * @var string CONTINUE_CASTING
     */
    private const CONTINUE_CASTING = '__ENV_CONTINUE_VALUE_CASTING__';

    /**
     * Checked envs.
     *
     * @var array<string,mixed> $cache
     */
    private static array $cache = [];

    /**
     * Private constructor
     */
    private function __construct(){}

    /**
     * Load and register environment variables from the active env file.
     *
     * This method reads the current environment file, parses all supported
     * key-value entries, resolves variable references, and stores the values
     * in runtime memory.
     *
     * In production mode, cached env values are loaded when available to
     * improve performance and reduce file parsing overhead.
     *
     * Supported features:
     * - Standard KEY=value entries
     * - Variable references using ${VAR_NAME}
     * - Automatic type conversion
     * - Cached env loading in non-local environments
     *
     * @return void
     *
     * @throws Throwable Throws when the environment file cannot be read
     *                          or parsed.
     *
     * @example Register environment variables during application bootstrap.
     * ```php
     * Env::register();
     * ```
     */
    public static function register(): void
    {
        if(!Runtime::isLocalhost() && is_file(self::filePath('env.cache'))){
            self::loadFromCache();

            if(self::$cache !== []){
                $_SERVER += self::$cache;
                return;
            }
        }

        $path = self::configFile(false);

        $entries = [];
        $params = [];
        $file = new SplFileObject($path, 'r');

        while (!$file->eof()) {
            $line = trim($file->fgets());

            if ($line === '' || self::isComment($line)) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);

            if(!$key){
                continue;
            }

            $value = trim($value);

            if (preg_match_all('/\$\{([_a-zA-Z][\w\.]*)\}/', $value, $matches)) {
                $params[$key] = $value;
                continue;
            }

            if(self::set($key, $value) && !Runtime::isLocalhost()){
                $entries[$key] = self::get($key);
            }
        }

        if($params !== []){
            self::registerReference($params, $entries);
        }

        if(!Runtime::isLocalhost()){
            self::writeCache($entries);
        }
    }

    /**
     * Set an environment variable at runtime.
     *
     * Optionally persists the variable into the active environment file.
     *
     * When running in local mode, the value is also stored in `$_ENV`.
     * In all environments, values are stored in `$_SERVER` and system env.
     *
     * Prefixing the key with `;` stores the variable as a commented entry
     * when persistence is enabled.
     *
     * @param string $name The environment variable key-name.
     * @param array<int,string|float|bool|int|null>|string|float|bool|int|null $value The value to assign.
     * @param bool $persist Whether to write the variable into the env file.
     *
     * @return bool Returns true on success, otherwise false.
     *
     * @example Set a temporary runtime variable.
     * ```php
     * Env::set('APP_NAME', 'Luminova');
     * ```
     *
     * @example Persist a variable into the env file.
     * ```php
     * Env::set('APP_NAME', 'Luminova', true);
     * ```
     *
     * @example Store a disabled env entry.
     * ```php
     * Env::set(';APP_DEBUG', 'true', true);
     * ```
     */
    public static function set(
        string $name, 
        array|string|float|bool|int|null $value, 
        bool $persist = false
    ): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        $isComment = false;
        
        if(is_array($value) && !array_is_list($value)){
            return false;
        }

        $value = is_string($value) 
            ? trim($value) 
            : $value;

        if(self::isComment($name)){
            $isComment = true;
            self::clear($name);
        } elseif(!self::add($name, $value)){
            return false;
        }

        if (!$persist) {
            return true;
        }

        return self::save(
            $name, 
            $value, 
            isRemove: false, 
            isComment: $isComment
        );
    }

    /**
     * Retrieve and normalize an environment variable value.
     *
     * Values are looked up in the following order:
     * 1. `$_SERVER`
     * 2. `$_ENV`
     * 3. `getenv()`
     *
     * If the key is not found, an underscore-delimited key is converted to
     * dot notation as a final fallback (e.g. `FOO_BAR` to `foo.bar`).
     *
     * String values are automatically converted to their corresponding PHP types.
     *
     * Supported conversions include:
     * - `true` / `false` to boolean
     * - `null` to null
     * - Numeric values to int or float
     * - `[a,b,c]` to array
     * - `blank` to an empty string
     *
     * @param string $name The environment variable name.
     * @param mixed $default The value to return when the key cannot be found.
     *
     * @return mixed The resolved and normalized environment value, or `$default`
     *               when the key does not exist.
     *
     * @example - Get a string value:
     * ```php
     * $name = Env::get('APP_NAME');
     * ```
     *
     * @example - Get a boolean value:
     * ```php
     * $debug = Env::get('APP_DEBUG', false);
     * ```
     *
     * @example - Get an array value:
     * ```php
     * $hosts = Env::get('APP_KEYS', []);
     * ```
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        $name = trim($name);

        if ($name === '') {
            return $default;
        }

        $exists = false;
        $value = self::value($name, $exists);

        if (!$exists && str_contains($name, '_')) {
            $fallback = strtolower(str_replace('_', '.', $name));
            $value = self::value($fallback, $exists);
        }

        if (!$exists || $value === null) {
            return $default;
        }

        $result = match(true) {
            $value === '' => $default ?? '',
            is_int($value),
            is_bool($value),
            is_float($value),
            !is_string($value) => $value,
            default            => self::toType($name, $value)
        };

        if($result === [] && is_array($default)){
            return $default;
        }

        return $result ?? $default;
    }

    /**
     * Add or update a runtime environment variable.
     *
     * The value is stored in `$_SERVER` and `getenv()`. When running locally,
     * it is also stored in `$_ENV`. Comment keys are removed instead of added.
     *
     * @param string $name The environment variable name.
     * @param array<string|int,string|int|float>|string|float|bool|int|null $value The value to set.
     *
     * @return bool Return true if variable is added or removed.
     */
    public static function add(string $name, array|string|float|bool|int|null $value): bool
    {
        if($name === ''){
            return false;
        }

        $_SERVER[$name] = $value;

        if (!getenv($name, true)) {
            $strValue = match(true){
                is_array($value) => array_is_list($value) 
                    ? '[' . implode(',', $value) . ']'
                    : (json_encode($value, JSON_BIGINT_AS_STRING) ?: ''),
                is_bool($value)  => (int) $value,
                default          => $value
            };

            putenv("{$name}={$strValue}");
        }

        if(Runtime::isLocalhost()){
            $_ENV[$name] = $value;
        }

        return true;
    }

    /**
     * Remove an environment variable from runtime memory and storage.
     *
     * By default, the variable is removed from the environment file, runtime
     * memory, and production environment cache. When `$cacheOnly` is true,
     * the environment file is preserved while the runtime value and production
     * cache are removed.
     *
     * @param string $name The environment variable name.
     * @param bool $cacheOnly Whether to preserve the variable in the environment file.
     *
     * @return bool `true` if the variable was removed successfully, otherwise `false`.
     *
     * @see self::disabled()
     * @see self::deleteCache()
     *
     * @example - Remove from the environment file, runtime, and cache:
     * ```php
     * Env::remove('APP_DEBUG');
     * ```
     *
     * @example - Remove from runtime and cache while preserving the environment file:
     * ```php
     * Env::remove('APP_DEBUG', cacheOnly: true);
     * ```
     */
    public static function remove(string $name, bool $cacheOnly = false): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        if ($cacheOnly) {
            return self::removeFromCache($name)
                && self::clear($name);
        }

        return self::save($name, isRemove: true, isComment: false)
            && self::clear($name);
    }

    /**
     * Disable an environment variable for the current runtime.
     *
     * The variable is removed from `$_ENV`, `$_SERVER`, and the process
     * environment without modifying the environment file.
     *
     * @param string $name The environment variable name.
     *
     * @return bool `true` on success, otherwise `false`.
     */
    public static function disable(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        return self::clear($name);
    }

    /**
     * Comment out an environment variable in the environment file.
     *
     * The variable remains in the file but is prefixed with the comment
     * character, preventing it from being loaded as an active variable.
     * The variable is also removed from the current runtime.
     *
     * @param string $name The environment variable name.
     *
     * @return bool `true` on success, otherwise `false`.
     */
    public static function comment(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        return self::save($name, isRemove: false, isComment: true)
            && self::clear($name);
    }

    /**
     * Write, update or comment an environment entry directly in the env file.
     *
     * Unlike `set()`, this method does not update runtime environment values.
     * It only modifies the physical env file.
     *
     * @param string $name The environment variable name.
     * @param array<int,string|float|bool|int|null>|string|float|bool|int|null $value The value to write.
     * @param bool|null $isComment Whether the entry should be written as a comment.
     *
     * @return bool Returns true if the entry was written successfully.
     *
     * @example Write a new env entry.
     * ```php
     * Env::write('APP_NAME', 'Luminova');
     * ```
     *
     * @example Write a disabled env entry.
     * ```php
     * Env::write(';APP_DEBUG', 'true');
     * ```
     */
    public static function write(
        string $name, 
        array|string|float|bool|int|null $value, 
        ?bool $isComment = null
    ): bool
    {
        if(is_array($value) && !array_is_list($value)){
            return false;
        }

        $isComment ??= self::isComment($name);

        return self::save(
            $name, 
            $value, 
            isRemove: false, 
            isComment: $isComment
        );
    }

    /**
     * Determine whether an environment variable exists.
     *
     * Checks `$_SERVER`, `$_ENV`, and `getenv()` in that order. A key is
     * considered present even when its value is `null` or another falsy value
     * in `$_SERVER` or `$_ENV`.
     *
     * @param string $name The environment variable name.
     *
     * @return bool `true` if the environment variable exists, otherwise `false`.
     */
    public static function has(string $name): bool
    {
        if (array_key_exists($name, $_SERVER)) {
            return true;
        }

        if (array_key_exists($name, $_ENV)) {
            return true;
        }

        return getenv($name) !== false;
    }

    /**
     * Retrieve an environment value without type normalization.
     *
     * Values are checked in the following order:
     * 1. `$_SERVER`
     * 2. `$_ENV`
     * 3. `getenv()`
     *
     * The `$exists` parameter distinguishes a missing key from a value that is
     * actually `false`, `null`, or otherwise falsy.
     *
     * @param string $name The environment variable name.
     * @param bool $exists Set to `true` when the key exists, or `false` otherwise.
     *
     * @return mixed The raw environment value, or `null` when the key is not found.
     */
    public static function value(string $name, bool &$exists = false): mixed
    {
        $exists = false;

        if (array_key_exists($name, $_SERVER)) {
            $exists = true;
            return $_SERVER[$name];
        }

        if (array_key_exists($name, $_ENV)) {
            $exists = true;
            return $_ENV[$name];
        }

        $value = getenv($name);

        if ($value === false) {
            return null;
        }

        $exists = true;
        return $value;
    }

    /**
     * Determine if the development environment file exists.
     *
     * This method checks for the presence of the `.dev.env` file in the
     * project root, which is used for local development environments.
     *
     * @return bool Returns true if the development env file exists, otherwise false.
     *
     * @example Check for development env file.
     * ```php
     * if (Env::devConfigExists()) {
     *     // dev env file exists
     * }
     * ```
     */
    public static function devConfigExists(): bool 
    {
        return is_file(self::filePath('env.dev'));
    }

    /**
     * Retrieve the active environment configuration file.
     *
     * The development environment file is preferred in local mode when available.
     * Otherwise, the production environment file is used.
     *
     * @return string|null The resolved environment file path, or null if none exists.
     *
     * @example
     * ```php
     * $path = Env::file();
     * ```
     */
    public static function file(): ?string
    {
        return self::configFile(false);
    }

    /**
     * Add variables to environment configuration cache file.
     *
     * Cached env values improve performance in production by avoiding repeated
     * parsing of the original env file.
     *
     * @param array<string,array|string|float|bool|int|null> $variables The environment entries to cache.
     *
     * @return bool Returns true if the cache file was created successfully.
     * 
     * @see self::deleteCache()
     *
     * @example - Cache parsed env values.
     * ```php
     * Env::cache([
     *     'APP_NAME' => 'Luminova',
     *     'APP_DEBUG' => false
     * ]);
     * ```
     */
    public static function cache(array $variables): bool 
    {
        if($variables === [] || array_is_list($variables)){
            return false;
        }

        self::loadFromCache();

        $result = self::writeCache(array_merge(
            self::$cache, 
            $variables
        ));

        if(!$result){
            return false;
        }

        foreach($variables as $name => $value){
            self::add($name, $value);
        }

        return true;
    }

    /**
     * Delete the environment variable cache.
     *
     * This invalidates the entire cached environment file. The cache will be
     * rebuilt with the updated environment variables when the application
     * initializes again.
     *
     * @return bool Return `true` if the cache was deleted, otherwise `false`.
     */
    public static function deleteCache(): bool
    {
        if (!is_file(self::filePath('env.cache'))) {
            return false;
        }

        return unlink(self::filePath('env.cache'));
    }

    /**
     * Determine whether environment cache data exists.
     *
     * Checks both the in-memory cache and the physical cache file.
     *
     * @return bool Returns true when cached env data is available.
     *
     * @example Check if env cache exists.
     * ```php
     * if (Env::isCached()) {
     *     // cache available
     * }
     * ```
     */
    public static function isCached(): bool
    {
        return self::$cache !== [] 
            || is_file(self::filePath('env.cache'));
    }

    /**
     * Resolve the file path for a framework environment file.
     *
     * @param 'env.prod'|'env.dev'|'env.cache' $context Environment file context.
     *
     * @return string|null The resolved file path, or `null` for an unknown context.
     */
    public static function filePath(string $context): ?string
    {
        static $root;
        $root ??= Luminova::appRoot();

        return match ($context) {
            'env.prod'  => $root . '.env',
            'env.dev'   => $root . '.dev.env',
            'env.cache' => $root . 'writeable/.env-cache.php',
            default     => null,
        };
    }

    /**
     * Create and store a cached environment configuration file.
     *
     * Cached env values improve performance in production by avoiding repeated
     * parsing of the original env file.
     *
     * @param array<string,mixed> $entries The environment entries to cache.
     *
     * @return bool Returns true if the cache file was created successfully.
     */
    private static function writeCache(array $entries): bool 
    {
        $file = self::filePath('env.cache');

        if(is_file($file) && !is_writable($file)){
            return false;
        }

        $code  = "<?php\n";
        $code .= "/**\n";
        $code .= " * Auto-generated environment cache.\n";
        $code .= " * Generated by Luminova on " . date(DATE_ATOM) . "\n";
        $code .= " */\n\n";
        $code .= "return ";
        $code .= var_export($entries, true);
        $code .= ";\n";

        if(Filesystem::atomicWrite($file, $code, exclusiveLock: true) !== false){
            self::$cache = $entries;
            return true;
        }

        self::$cache = [];
        return false;
    }

    /**
     * Remove an environment variable from the runtime cache.
     *
     * Loads the current cache, removes the specified variable when present, and
     * rewrites the cache with the remaining variables. Returns `true` when the
     * variable does not exist or was removed successfully.
     *
     * @param string $name The environment variable name.
     *
     * @return bool `true` if the variable was removed or did not exist, otherwise `false`.
     */
    private static function removeFromCache(string $name): bool
    {
        self::loadFromCache();

        if (
            self::$cache === []
            || !array_key_exists($name, self::$cache)
        ) {
            return true;
        }

        unset(self::$cache[$name]);

        return self::writeCache(self::$cache);
    }

    /**
     * Load cached environment values.
     *
     * Cached values are loaded into memory from the generated cache file.
     *
     * @return void
     */
    private static function loadFromCache(): void
    {
        if(self::$cache !== []){
            self::$cache;
            return;
        }

        $file = self::filePath('env.cache');

        if(!is_file($file)){
            self::$cache = [];
            return;
        }

        $cached = include $file;

        self::$cache = $cached ?: [];
    }

    /**
     * Resolve the active environment configuration file.
     *
     * In local mode, the development environment file is preferred when it exists.
     * Otherwise, the production environment file is used.
     *
     * When `$touch` is enabled, the appropriate environment file is created when
     * neither environment file exists.
     *
     * @param bool $touch Whether to create the environment file when missing.
     *
     * @return string|null The resolved environment file path, or null on failure.
     */
    private static function configFile(bool $touch = true): ?string
    {
        $isLocalhost = Runtime::isLocalhost();

        if ($isLocalhost && self::devConfigExists()) {
            return self::filePath('env.dev');
        }

        $file = self::filePath('env.prod');

        if (is_file($file)) {
            return $file;
        }

        if (!$touch) {
            return null;
        }

        if($isLocalhost){
            $file = self::filePath('env.dev');
        }

        if (Filesystem::touch($file) === false) {
            throw new RuntimeException(sprintf(
                'Failed to create environment file: "%s" in the project root.',
                basename($file)
            ));
        }

        return $file;
    }

    /**
     * Determine whether an env entry is commented.
     *
     * Supports both `;` and `#` comment prefixes.
     *
     * @param string $key The env key or line to inspect.
     *
     * @return bool Returns true if the entry is commented, otherwise false.
     *
     * @internal Environment helper method.
     *
     * @example Check if an env key is disabled.
     * ```php
     * Env::isComment(';APP_DEBUG');
     * // true
     * ```
     *
     * @example Check hash-style comments.
     * ```php
     * Env::isComment('#APP_DEBUG');
     * // true
     * ```
     */
    private static function isComment(string $key): bool
    {
        $key = trim($key);

        return $key !== '' && (
            str_starts_with($key, ';')
            || str_starts_with($key, '#')
        );
    }

    /**
     * Clear an environment variable from the current runtime.
     *
     * Removes the variable from `$_ENV`, `$_SERVER`, and the process environment.
     *
     * @param string $key The environment variable name.
     *
     * @return bool Always returns `true`.
     */
    private static function clear(string $key): bool
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);

        return true;
    }

    /**
     * Update, remove, or comment an environment variable in the environment file.
     *
     * Existing entries are replaced in place. When commenting an existing entry,
     * its current value is preserved and the entry is prefixed with `;`. When
     * removing an entry, its entire line is removed.
     *
     * If the key does not exist, a new entry is appended unless `$isRemove` is
     * enabled. Commented keys may be supplied with either `;` or `#` prefixes.
     *
     * @param string $key The environment variable name.
     * @param mixed $value The value to assign when creating or updating
     *                                the variable.
     * @param bool $isRemove Whether to remove the variable instead of saving it.
     * @param bool|null $isComment Whether to comment the variable instead of
     *                             saving it. When `null`, inferred from the key
     *                             prefix.
     *
     * @return bool `true` if the environment file was updated successfully,
     *              otherwise `false`.
     */
    private static function save(
        string $key,
        mixed $value = '',
        bool $isRemove = false,
        ?bool $isComment = null
    ): bool 
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        $isComment ??= self::isComment($key);

        $key = ltrim($key, ';# ');

        if ($key === '') {
            return false;
        }

        $path = self::configFile();

        try {
            $contents = file_get_contents($path);

            if ($contents === false) {
                return false;
            }

            $pattern = '/^[;]?\s*' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/mi';
            $found = false;
            $valueStr = self::normalizeValue($value);

            $contents = preg_replace_callback(
                $pattern,
                static function (array $match) use (
                    &$found,
                    $key,
                    $valueStr,
                    $isRemove,
                    $isComment
                ): string {
                    $found = true;

                    if ($isRemove) {
                        return '';
                    }

                    if ($isComment) {
                        return ";{$key}={$match[1]}";
                    }

                    return "{$key}={$valueStr}";
                },
                $contents
            );

            if ($contents === null) {
                return false;
            }

            if (!$found && !$isRemove) {
                $contents = rtrim($contents, "\r\n")
                    . "\n"
                    . ($isComment ? ';' : '')
                    . "{$key}={$valueStr}\n";
            }

            if (Filesystem::atomicWrite($path, $contents, exclusiveLock: true) === false) {
                return false;
            }

            if (is_file(self::filePath('env.cache'))) {
                self::loadFromCache();

                if ($isRemove || $isComment) {
                    unset(self::$cache[$key]);
                } else {
                    self::$cache[$key] = $value;
                }

                self::writeCache(self::$cache);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Normalize value.
     *
     * @param mixed $value
     * @return string|int|float
     */
    private static function normalizeValue(mixed $value): string|float|int
    {
        return match(true) {
            is_null($value)  => 'null',
            is_bool($value)  => ($value === true) ? 'true' : 'false',
            is_array($value) => '[' . implode(',', $value) . ']',
            default => $value
        };
    }

    /**
     * Convert environment key notation between dot and underscore formats.
     *
     * Examples:
     * - `database.host` → `DATABASE_HOST`
     * - `DATABASE_HOST` → `database.host`
     *
     * CamelCase segments are also normalized automatically.
     *
     * @param string $input The input key to convert.
     * @param string $notation The target notation (`.` or `_`).
     *
     * @return string Returns the converted notation string.
     *
     * @example Convert dot notation to underscore format.
     * ```php
     * echo Env::toNotation('database.host', '_');
     * // DATABASE_HOST
     * ```
     *
     * @example Convert underscore notation to dot format.
     * ```php
     * echo Env::toNotation('DATABASE_HOST', '.');
     * // database.host
     * ```
     */
    public static function toNotation(string $input, string $notation = '.'): string 
    {
        if ($notation === '.') {
            $output = str_replace('_', '.', $input);
        } elseif ($notation === '_') {
            $output = str_replace('.', '_', $input);
        } else {
            return $input; 
        }

        $pattern = '/([a-z0-9])([A-Z])/';
    
        if ($notation === '.') {
            $output = preg_replace($pattern, '$1.$2', $output);
        } elseif ($notation === '_') {
            $output = preg_replace($pattern, '$1_$2', $output);
        }
    
        // Remove leading dot or underscore (if any)
        $output = ltrim($output, $notation);
    
        return ($notation === '_') ? strtoupper($output) : strtolower($output);
    }

    /**
     * Normalize an environment variable value to its corresponding PHP type.
     *
     * Supports boolean, null, empty-string, empty-array, numeric, and bracketed
     * array values. The normalized value is cached in `$_SERVER` and, when running
     * locally, in `$_ENV`.
     * 
     * Types:
     * - Boolean values
     * - Numeric values
     * - Null values
     * - Arrays
     * - Empty string aliases
     *
     * @param string $key The environment variable name.
     * @param string $value The raw environment variable value.
     *
     * @return mixed The normalized environment value.
     */
    private static function toType(string $key, string $value): mixed
    {
        $value = trim($value);

        $normalized = match (strtolower($value)) {
            'true', 'enable'   => true,
            'false', 'disable' => false,
            'null'             => null,
            'blank'            => '',
            '[]'               => [],
            default            => self::CONTINUE_CASTING,
        };

        if ($normalized === self::CONTINUE_CASTING) {
            if (is_numeric($value)) {
                $normalized = to_numeric($value, true);
            } elseif (json_validate($value)) {
                $normalized = json_decode($value, true, flags: JSON_BIGINT_AS_STRING);
            } else {
                $value = self::normalize($value);

                $normalized = str_starts_with($value, '[') && str_ends_with($value, ']')
                    ? self::toArray($value)
                    : $value;
            }
        }

        $_SERVER[$key] = $normalized;

        if (Runtime::isLocalhost()) {
            $_ENV[$key] = $normalized;
        }

        return $normalized;
    }

    /**
     * Resolve and register referenced environment variables.
     *
     * Supports placeholder references using `${VAR_NAME}` syntax.
     *
     * @param array<string,string> $params Environment variables containing references.
     * @param array<string,mixed> $entries Cached env entries.
     *
     * @return void
     * @internal Environment helper method.
     *
     * @example Resolve referenced variables.
     * ```env
     * DB_HOST=localhost
     * DB_URL=mysql://${DB_HOST}
     * ```
     */
    private static function registerReference(array $params, array &$entries): void
    {
        foreach ($params as $name => $param) {
            $value = preg_replace_callback(
                '/\$\{([a-zA-Z_][a-zA-Z0-9_.]*)\}/',
                function ($matches) use ($name): mixed {
                    $key = $matches[1];
                    
                    if (self::has($key)) {
                        return self::get($key);
                    }

                    if(!Runtime::isLocalhost()){
                        return '';
                    }

                    throw new RuntimeException(sprintf(
                        'RuntimeError: Missing environment value for parameter "%s" in "%s"',
                        $key,
                        $name
                    ));
                },
                $param
            );

            if(!self::add($name, $value)){
                continue;
            }

            if(!Runtime::isLocalhost()){
                $entries[$name] = $value;
            }
        }
    }

    /**
     * Convert env array string syntax into a PHP array.
     *
     * Supports nested arrays and automatic type conversion.
     *
     * Example input:
     * `[foo,bar,[1,2,true]]`
     *
     * @param string $value The env array string.
     *
     * @return array Returns the parsed PHP array.
     *
     * @internal Environment helper method.
     *
     * @example Convert env string to array.
     * ```php
     * $array = Env::toArray('[1,2,true]');
     * ```
     */
    private static function toArray(string $value): array 
    {
        return array_map(function($item) {
            $item = trim($item, " \"\n\r\t\v\0");

            if (str_starts_with($item, '[') && str_ends_with($item, ']')) {
                return self::toArray($item);
            }

            return match ($item) {
                'true'  => true,
                'false' => false,
                'null'  => null,
                is_numeric($item) => to_numeric($item, true),
                default => self::normalize($item)
            };
        }, explode(',', trim($value, '[] ')));
    }

    /**
     * Normalize an environment value string.
     *
     * Removes wrapping quotes and strips escaped characters.
     *
     * @param string $value The raw env value.
     *
     * @return string Returns the normalized value.
     *
     * @internal Environment helper method.
     *
     * @example Normalize a quoted value.
     * ```php
     * $value = Env::normalize('"hello"');
     * ```
     */
    private static function normalize(string $value): string 
    {
        if($value === ''){
            return '';
        }

        if (
            (str_starts_with($value, "'") && str_ends_with($value, "'")) ||
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
        ) {
            $value = substr($value, 1, -1);
        }

        return stripslashes($value);
    }
}