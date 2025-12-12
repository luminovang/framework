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
use \Luminova\Boot;
use \SplFileObject;
use \Luminova\Exceptions\RuntimeException;

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
     * @var string KEY_NOT_FOUND
     */
    private const KEY_NOT_FOUND = '__ENV_KEY_EMPTY_OR_NOT_FOUND__';

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
     * @param string $value The value to assign.
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
    public static function set(string $key, string $value, bool $persist = false): bool
    {
        $key = trim($key);

        if ($key === '') {
            return false;
        }

        $value = trim($value);
        $isComment = self::isComment($key);

        if($isComment){
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }else{
            $_SERVER[$key] = $value;

            if (!getenv($key, true)) {
                putenv("{$key}={$value}");
            }

            if(self::isLocal()){
                $_ENV[$key] = $value;
            }
        }

        if (!$persist) {
            return true;
        }

        return self::save($key, $value, false, $isComment);
    }

    /**
     * Remove an environment variable.
     *
     * The variable is removed from runtime memory and optionally deleted
     * from the active env file.
     *
     * @param string $key The environment variable name.
     * @param bool $persist Whether to remove the variable from the env file.
     *
     * @return bool Returns true on success, otherwise false.
     *
     * @example Remove a runtime env variable.
     * ```php
     * Env::remove('APP_DEBUG');
     * ```
     *
     * @example Remove a variable from runtime and env file.
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

        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);

        if (!$persist) {
            return true;
        }

        return self::save($key, '', true, false);
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

        return self::save($key, $value, false, $isComment);
    }

    /**
     * Retrieve an environment variable value.
     *
     * Values are automatically converted into their proper PHP types.
     *
     * Supported conversions:
     * - `true` / `false` to boolean
     * - `null` to null
     * - Numeric values to int or float
     * - `[a,b,c]` to array
     * - `blank` to empty string
     *
     * Lookup order:
     * - `$_SERVER`
     * - `$_ENV`
     * - `getenv()`
     *
     * @param string $key The environment variable name.
     * @param mixed $default Default value returned when the key does not exist.
     *
     * @return mixed Returns the resolved environment value.
     *
     * @example Get a string value.
     * ```php
     * $name = Env::get('APP_NAME');
     * ```
     *
     * @example Get a boolean value.
     * ```php
     * $debug = Env::get('APP_DEBUG', false);
     * ```
     *
     * @example Get an array value.
     * ```php
     * $hosts = Env::get('DB_HOSTS', []);
     * ```
     */
    public static function get(string $key, mixed $default = null): mixed 
    {
        $key = trim($key);

        if ($key === '') {
            return '';
        }

        $value = $_SERVER[$key] 
            ?? $_ENV[$key] 
            ?? getenv($key) ?: self::KEY_NOT_FOUND;

        // if ($value === false) {
        //    $value = self::tryNotation($key);
        // }

        if ($value === self::KEY_NOT_FOUND) {
            return $default;
        }

        if (!$value || $value === true || !is_string($value)) {
            return $value;
        }

        return self::toType($key, $value, $default);
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
     * Write, update, or remove an env entry from the active environment file.
     *
     * Existing entries are automatically replaced when a matching key is found.
     * When `$isRemove` is enabled, the matching entry is removed instead.
     *
     * In production mode, the env cache is automatically refreshed after
     * successful updates.
     *
     * @param string $key The environment variable name.
     * @param string $value The value to write.
     * @param bool $isRemove Whether the matching entry should be removed.
     * @param bool|null $isComment Whether the entry should be treated as a comment.
     *
     * @return bool Returns true if the operation completed successfully,
     *              otherwise false.
     *
     * @internal Environment helper method.
     *
     * @example Add or update an env entry.
     * ```php
     * self::save('APP_NAME', 'Luminova');
     * ```
     *
     * @example Store a commented env entry.
     * ```php
     * self::save(';APP_DEBUG', 'true');
     * ```
     *
     * @example Remove an env entry.
     * ```php
     * self::save('APP_DEBUG', '', true);
     * ```
     */
    private static function save(
        string $key,
        string $value,
        bool $isRemove = false,
        ?bool $isComment = null
    ): bool
    {
        $isComment ??= str_starts_with($key, ';');
        $path = self::file();

        try {
            $file = new SplFileObject($path, 'a+');
            $file->seek(0);

            $lines = '';
            $found = false;
            $pattern = preg_quote($isComment ? trim($key, "; \t") : $key, '/');
            $pattern = '/^[;]*\s*' . $pattern . '\s*=\s*(.*)$/mi';

            while (!$file->eof()) {
                $line = $file->fgets();
                
                if ($line && preg_match($pattern, $line)) {
                    $found = true;
                    $lines .= ($isRemove ? "\n" : "{$key}={$value}\n");
                }else{
                    $lines .= $line;
                }
            }

            if (!$found && !$isRemove) {
                $lines .= "\n{$key}={$value}";
            }

            $saved = (new SplFileObject($path, 'w'))->fwrite($lines) !== false;

            if($saved && !self::isLocal() && is_file(self::CACHE_FILE)){
                self::loadFromCache();

                if($isRemove){
                    unset(self::$cache[$key]);
                }else{
                    self::$cache[$key] = $value;
                }
        
                self::cache(self::$cache);
            }

            $lines = null;

            return $saved;
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
     * Convert raw env string values into their matching PHP types.
     *
     * Supported conversions include:
     * - Boolean values
     * - Numeric values
     * - Null values
     * - Arrays
     * - Empty string aliases
     *
     * @param string $key The environment variable key.
     * @param string $value The raw string value.
     * @param mixed $default Optional fallback value.
     *
     * @return mixed Returns the converted PHP value.
     *
     * @internal Environment helper method.
     */
    private static function toType(string $key, string $value, mixed $default): mixed
    {
        $value = trim($value);

        if ($value === '[]' || is_numeric($value)) {
            $value = ($value === '[]') 
                ? (is_array($default) ? $default : [])
                : to_numeric($value, true);

            $_SERVER[$key] = $value;

            if(self::isLocal()){
                $_ENV[$key] = $value;
            }

            return $value;
        }

        $type = match (strtolower($value)) {
            'true', 'enable'   => true,
            'false', 'disable' => false,
            'null'             => null,
            'blank'            => '',
            default            => '__ENV_CONTINUE_SEARCH__'
        };

        if($type === null){
            return $type;
        }

        if ($type !== '__ENV_CONTINUE_SEARCH__') {
            $_SERVER[$key] = $type;

            if(self::isLocal()){
                $_ENV[$key] = $type;
            }

            return $type;
        }

        $value = self::normalize($value);

        if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
            $value = self::toArray($value);
            $_SERVER[$key] = $value;

            if(self::isLocal()){
                $_ENV[$key] = $value;
            }
        }

        return $value;
    }

    /**
     * Attempt to resolve an environment key using alternative notation formats.
     *
     * This method searches both dot and underscore notation variants.
     *
     * @param string $key The environment key to resolve.
     *
     * @return mixed Returns the resolved value or internal empty marker.
     *
     * @internal Environment helper method.
     */
    private static function tryNotation(string $key): mixed 
    {
        $keys = [str_replace('_', '.', $key), str_replace('.', '_', $key)];

        foreach ($keys as $notation) {
            $value = $_SERVER[$notation] 
                ?? $_ENV[$notation] 
                ?? getenv($notation);

            if ($value !== false) {
                return $value;
            }
        }

        return self::KEY_NOT_FOUND;
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
                '/\$\{([_a-zA-Z][\w\.]*)\}/',
                function ($matches) use ($name): mixed {
                    $key = $matches[1];
                    $env = self::get($key, self::KEY_NOT_FOUND);

                    if ($env !== self::KEY_NOT_FOUND) {
                        return $env;
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