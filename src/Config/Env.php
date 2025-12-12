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
use \DateTimeZone;
use Luminova\Boot;
use \SplFileObject;
use Luminova\Luminova;
use Luminova\Exceptions\RuntimeException;

/**
 * Helper function:
 * 
 * @see \env()
 * @see \setenv()
 */
final class Env
{
    /**
     * Env cache filename.
     * 
     * @var string CACHE_FILE
     */
    public const CACHE_FILE = APP_ROOT . 'writeable/.env-cache.php';

    /**
     * Env production filename.
     * 
     * @var string PRODUCTION_FILE
     */
    public const PRODUCTION_FILE  = APP_ROOT . '.env';

    /**
     * Env development filename.
     * 
     * @var string DEVELOPMENT_FILE
     */
    public const DEVELOPMENT_FILE = APP_ROOT . '.dev.env';

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
     * Local environment flag.
     *
     * @var ?bool $localhost
     */
    private static ?bool $localhost = null;

    /**
     * Private constructor
     */
    private function __construct(){}

    /**
     * Determine whether the current request is from a local environment.
     *
     * This is a heuristic check based on:
     * - Local hostnames (localhost, 127.0.0.1, ::1)
     * - Private / loopback IP ranges (e.g., "127.x.x.x", "10.x.x.x", "192.168.x.x", "172.16.x.x" to "172.31.x.x").
     *
     * @return bool True if request appears to originate from a local environment.
     * @see \IS_LOCAL Constant for a global flag that can be used throughout the application.
     *
     * > **Note:**
     * > This does NOT guarantee environment type (dev/prod).
     * > It only detects local network characteristics.
     */
    public static function isLocal(): bool
    {
        if(self::$localhost !== null){
            return self::$localhost;
        }

        $host = $_SERVER['HTTP_HOST']
            ?? $_SERVER['SERVER_NAME']
            ?? '';

        if ($host !== '' && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return self::$localhost = true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if ($ip === '') {
            return self::$localhost =  false;
        }

        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return self::$localhost = true;
        }

        if (
            str_starts_with($ip, '127.') ||
            str_starts_with($ip, '10.') ||
            str_starts_with($ip, '192.168.') ||
            preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip)
        ) {
            return self::$localhost = true;
        }

        return self::$localhost = false;
    }

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
     * @throws RuntimeException Throws when the environment file cannot be read
     *                          or parsed.
     *
     * @example Register environment variables during application bootstrap.
     * ```php
     * Env::register();
     * ```
     */
    public static function register(): void
    {
        if(!self::isLocal() && is_file(self::CACHE_FILE)){
            self::loadFromCache();

            if(self::$cache !== []){
                $_SERVER += self::$cache;
                return;
            }
        }

        $path = self::file();

        try {
            $entries = [];
            $params = [];
            $file = new SplFileObject($path, 'r');

            while (!$file->eof()) {
                $line = trim($file->fgets());

                if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
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

                if(self::set($key, $value) && !self::isLocal()){
                    $entries[$key] = self::get($key);
                }
            }

            if($params !== []){
                self::registerReference($params, $entries);
            }

            if(!self::isLocal()){
                self::cache($entries);
            }

            $entries = $params = null;
        } catch (Throwable $e) {
            Boot::onError(sprintf(
                "RuntimeError: Failed to parse environment configuration.%s%s",
                self::isLocal() ? '' : ((PHP_SAPI === 'cli') ? "\n\n" : '<br/><br/>'),
                $e->getMessage()
            ));
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
     * @param string $key The environment variable name.
     * @param string|float|int $value The value to assign.
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
    public static function set(string $key, string|float|int $value, bool $persist = false): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        $value = is_string($value) 
            ? trim($value) 
            : $value;
            
        [$isComment] = self::add($key, $value);

        if (!$persist) {
            return true;
        }

        return self::save(
            $key, 
            $value, 
            isRemove: false, 
            isComment: $isComment
        );
    }

    /**
     * Sets the default PHP timezone.
     *
     * If the specified timezone is already active, no changes are made and `true`
     * is returned. The timezone may be provided as a `DateTimeZone` instance or
     * a valid timezone identifier.
     *
     * @param DateTimeZone|string $timezone The timezone to set.
     *
     * @return bool Returns `true` on success, or `false` if the timezone is empty,
     *              invalid, or `date_default_timezone_set()` is unavailable.
     * 
     * ```php
     * $timezone = 'UTC';
     * 
     * Luminova::setTimezone();
     * echo date_default_timezone_get() // UTC
     * 
     * // Optionally apply to runtime env only
     * setenv('app.timezone', $timezone);
     * echo env('app.timezone') // UTC
     * ```
     */
    public static function setTimezone(DateTimeZone|string $timezone): bool
    {
        $timezone = ($timezone instanceof DateTimeZone)
            ? $timezone->getName()
            : trim($timezone);

        if ($timezone === '') {
            return false;
        }

        if (
            date_default_timezone_get() !== $timezone &&
            !Luminova::tryFunction('date_default_timezone_set', $timezone)
        ) {
            return false;
        }

        self::add('app.timezone', $timezone);
        return true;
    }

    /**
     * Set max script execution time (seconds).
     *
     * Updates the limit only when increasing it or disabling it (0).
     * Returns false if the operation is not allowed or fails.
     *
     * @param int $seconds Time limit in seconds (0 = unlimited)
     *
     * @return bool Returns true on success, false otherwise
     */
    public static function setExecutionTimeLimit(int $seconds): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        $maxExecution = (int) ini_get('max_execution_time');

        if (
            (($maxExecution !== 0 && $seconds > $maxExecution) || ($maxExecution > 0 && $seconds === 0))
            && Luminova::tryFunction('set_time_limit', $seconds)
        ) {
            self::add('script.execution.limit', $seconds);
            return true;
        }
        

        return false;
    }

    /**
     * Set the internal multibyte encoding.
     * 
     * @param string $encoding The default internal encoding.
     *
     * @return bool Returns false if encoding is null/empty or the function is unavailable.
     */
    public static function setEncoding(string $encoding): bool
    {
        if (
            $encoding 
            && Luminova::tryFunction('mb_internal_encoding', $encoding)
        ){
            self::add('app.mb.encoding', $encoding);
            return true;
        }

        return false;
    }

    /**
     * Set whether the script should continue after client disconnect.
     *
     * @param bool $ignore Whether to ignore or continue.
     * 
     * @return bool Returns true or false if the function is unavailable.
     */
    public static function setIgnoreUserAbort(bool $ignore): bool
    {
        if(Luminova::tryFunction('ignore_user_abort', $ignore) === false){
            return false;
        }

        self::add('script.ignore.abort', $ignore);

        return true;
    }

    /**
     * Set the default locale for all categories.
     * 
     * @param string $locale The default application locale to set.
     * @param int $category The category affected by the locale setting (e.g, `LC_*`).
     *
     * @return bool Returns true applied locale string, or false on failure.
     */
    public static function setLocale(string $locale, int $category = LC_ALL): bool
    {
        if($locale === '' || $locale === '0' || $locale === []){
            return false;
        }

        if(setlocale($category, $locale) === false){
            return false;
        }

        if($category === LC_ALL){
            self::add('app.locale', $locale);
        }

        return true;
    }

    /**
     * Remove an environment variable from runtime memory.
     *
     * When persistence is enabled, the variable is also removed from the
     * environment file.
     *
     * @param string $key The environment variable name.
     * @param bool $persist Whether to remove the variable from the environment file.
     *
     * @return bool `true` on success, otherwise `false`.
     *
     * @example - Remove a variable from runtime memory:
     * ```php
     * Env::remove('APP_DEBUG');
     * ```
     *
     * @example - Remove a variable from runtime memory and the environment file:
     * ```php
     * Env::remove('APP_DEBUG', true);
     * ```
     */
    public static function remove(string $key, bool $persist = false): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        self::clear($key);

        if (!$persist) {
            return true;
        }

        return self::save($key, isRemove: true, isComment: false);
    }

    /**
     * Disable an environment variable for the current runtime.
     *
     * The variable is removed from `$_ENV`, `$_SERVER`, and the process
     * environment without modifying the environment file.
     *
     * @param string $key The environment variable name.
     *
     * @return bool `true` on success, otherwise `false`.
     */
    public static function disable(string $key): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        return self::clear($key);
    }

    /**
     * Comment out an environment variable in the environment file.
     *
     * The variable remains in the file but is prefixed with the comment
     * character, preventing it from being loaded as an active variable.
     * The variable is also removed from the current runtime.
     *
     * @param string $key The environment variable name.
     *
     * @return bool `true` on success, otherwise `false`.
     */
    public static function comment(string $key): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        return self::save($key, isRemove: false, isComment: true)
            && self::clear($key);
    }

    /**
     * Write or update an environment entry directly in the env file.
     *
     * Unlike `set()`, this method does not update runtime environment values.
     * It only modifies the physical env file.
     *
     * @param string $key The environment variable name.
     * @param string $value The value to write.
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
    public static function write(string $key, string $value, ?bool $isComment = null): bool
    {
        $isComment ??= self::isComment($key);

        return self::save(
            $key, 
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
     * @param string $key The environment variable name.
     *
     * @return bool `true` if the environment variable exists, otherwise `false`.
     */
    public static function has(string $key): bool
    {
        if (array_key_exists($key, $_SERVER)) {
            return true;
        }

        if (array_key_exists($key, $_ENV)) {
            return true;
        }

        return getenv($key) !== false;
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
     * @param string $key The environment variable name.
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
    public static function get(string $key, mixed $default = null): mixed
    {
        $key = trim($key);

        if ($key === '') {
            return '';
        }

        $found = false;
        $value = self::getValue($key, $found);

        if (!$found && str_contains($key, '_')) {
            $fallback = strtolower(str_replace('_', '.', $key));
            $value = self::getValue($fallback, $found);
        }

        if (!$found || $value === null) {
            return $default;
        }

        $result = match(true) {
            $value === '' => '',
            is_int($value),
            is_bool($value),
            is_float($value),
            !is_string($value) => $value,
            default            => self::toType($key, $value)
        };

        if($result === [] && is_array($default)){
            return $default;
        }

        return $result ?? $default;
    }

    /**
     * Retrieve an environment value without type normalization.
     *
     * Values are checked in the following order:
     * 1. `$_SERVER`
     * 2. `$_ENV`
     * 3. `getenv()`
     *
     * The `$found` parameter distinguishes a missing key from a value that is
     * actually `false`, `null`, or otherwise falsy.
     *
     * @param string $key The environment variable name.
     * @param bool $found Set to `true` when the key exists, or `false` otherwise.
     *
     * @return mixed The raw environment value, or `null` when the key is not found.
     */
    public static function getValue(string $key, bool &$found = false): mixed
    {
        if (array_key_exists($key, $_SERVER)) {
            $found = true;

            return $_SERVER[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            $found = true;

            return $_ENV[$key];
        }

        $value = getenv($key);

        if ($value !== false) {
            $found = true;

            return $value;
        }

        $found = false;

        return null;
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
     * if (Env::hasDev()) {
     *     // dev env file exists
     * }
     * ```
     */
    public static function hasDev(): bool 
    {
        return is_file(self::DEVELOPMENT_FILE);
    }

    /**
     * Resolve the active environment file path.
     *
     * The development env file is preferred in local mode when available.
     * Otherwise, the production env file is used.
     *
     * When `$touch` is enabled, the file is automatically created if missing.
     *
     * @param bool $touch Whether to create the env file when it does not exist.
     *
     * @return string|null Returns the resolved env file path or null on failure.
     *
     * @example Get the active env file path.
     * ```php
     * $path = Env::file();
     * ```
     *
     * @example Resolve path without creating the file.
     * ```php
     * $path = Env::file(false);
     * ```
     */
    public static function file(bool $touch = true): ?string 
    {
        if (self::isLocal() && self::hasDev()) {
            return self::DEVELOPMENT_FILE;
        }

        if (is_file(self::PRODUCTION_FILE)) {
            return self::PRODUCTION_FILE;
        }

        if(!$touch){
            return null;
        }

        $env = self::isLocal() 
            ? self::DEVELOPMENT_FILE 
            : self::PRODUCTION_FILE;

        if (@file_put_contents($env, '') === false) {
            Boot::onError(sprintf(
                'Failed to create environment file.%sEnsure "%s" exists in the project root.',
                (PHP_SAPI === 'cli') ? "\n\n" : '<br/><br/>',
                basename($env)
            ));
        }

        return $env;
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
     *
     * @internal Environment helper method.
     *
     * @example Cache parsed env values.
     * ```php
     * Env::cache([
     *     'APP_NAME' => 'Luminova',
     *     'APP_DEBUG' => false
     * ]);
     * ```
     */
    public static function cache(array $entries): bool 
    {
        $code  = "<?php\n";
        $code .= "/**\n";
        $code .= " * Auto-generated environment cache.\n";
        $code .= " * Generated by Luminova on " . date(DATE_ATOM) . "\n";
        $code .= " */\n\n";
        $code .= "return ";
        $code .= var_export($entries, true);
        $code .= ";\n";

        if(file_put_contents(self::CACHE_FILE, $code) !== false){
            self::$cache = $entries;
            return true;
        }

        self::$cache = [];
        return false;
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
        return self::$cache !== [] || is_file(self::CACHE_FILE);
    }

    /**
     * Load cached environment values.
     *
     * Cached values are loaded into memory from the generated cache file.
     *
     * @return array<string,mixed> Returns the cached environment entries.
     *
     * @example Load cached env values.
     * ```php
     * $cached = Env::loadFromCache();
     * ```
     */
    public static function loadFromCache(): array
    {
        if(self::$cache !== []){
            return self::$cache;
        }

        if(!is_file(self::CACHE_FILE)){
            return self::$cache = [];
        }

        $cached = include self::CACHE_FILE;

        return self::$cache = $cached ?: [];
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

        return (
            str_starts_with($key, ';') || 
            str_starts_with($key, '#')
        );
    }

    /**
     * Set variable for runtime only.
     *
     * @param string $key The environment variable name.
     * @param string|float|int $value The value to write.
     * 
     * @return array{0:int}
     */
    private static function add(string $key, string|float|int $value): array
    {
        if(self::isComment($key)){
            self::clear($key);
            return [true];
        }

        $_SERVER[$key] = $value;

        if (!getenv($key, true)) {
            putenv("{$key}={$value}");
        }

        if(self::isLocal()){
            $_ENV[$key] = $value;
        }

        return [false];
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
     * @param string|float|int $value The value to assign when creating or updating
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
        string|float|int $value = '',
        bool $isRemove = false,
        ?bool $isComment = null
    ): bool 
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        $isComment ??= str_starts_with($key, ';')
            || str_starts_with($key, '#');

        $key = ltrim($key, ';# ');

        if ($key === '') {
            return false;
        }

        $path = self::file();

        try {
            $contents = file_get_contents($path);

            if ($contents === false) {
                return false;
            }

            $pattern = '/^[;]?\s*' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/mi';
            $found = false;

            $contents = preg_replace_callback(
                $pattern,
                static function (array $match) use (
                    &$found,
                    $key,
                    $value,
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

                    return "{$key}={$value}";
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
                    . "{$key}={$value}\n";
            }

            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                return false;
            }

            if (is_file(self::CACHE_FILE)) {
                self::loadFromCache();

                if ($isRemove || $isComment) {
                    unset(self::$cache[$key]);
                } else {
                    self::$cache[$key] = $value;
                }

                self::cache(self::$cache);
            }

            return true;
        } catch (Throwable) {
            return false;
        }
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
            } else {
                $value = self::normalize($value);

                $normalized = str_starts_with($value, '[') && str_ends_with($value, ']')
                    ? self::toArray($value)
                    : $value;
            }
        }

        $_SERVER[$key] = $normalized;

        if (self::isLocal()) {
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

                    if(!self::isLocal()){
                        return '';
                    }

                    Boot::onError(sprintf(
                        'RuntimeError: Missing environment value for parameter "%s" in "%s"',
                        $key,
                        $name
                    ));
                },
                $param
            );

            if (!getenv($name, true)) {
                $strValue = is_array($value) 
                    ? '[' . implode(',', $value) . ']' 
                    : $value;

                putenv("{$name}={$strValue}");
            }

            $_SERVER[$name] = $value;

            if(!self::isLocal()){
                $entries[$name] = $value;
                continue;
            }

            $_ENV[$name] = $value;
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