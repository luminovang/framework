<?php
declare(strict_types=1);
/**
 * Luminova Framework global helper functions.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 * @see https://luminova.ng/docs/0.0.0/global/functions
 */
namespace Luminova\Funcs;

use \App\Application;
use \App\Config\Files;
use \Luminova\Luminova;
use \Luminova\Logger\Logger;
use \Luminova\Arrays\Listify;
use \Luminova\Cookies\Cookie;
use \Luminova\Sessions\Session;
use \Luminova\Storages\FileManager;
use \Luminova\Functions\{IP, Func};
use \Luminova\Template\{Layout, Response};
use \Luminova\Cache\{FileCache, MemoryCache};
use \Luminova\Application\{Factory, Services};
use \Luminova\Http\{Request, HttpCode, UserAgent};
use \Luminova\Core\{CoreFunction, CoreApplication};
use \Luminova\Interface\{
    LazyInterface,
    ValidationInterface,
    HttpRequestInterface,
    ViewResponseInterface,
    SessionManagerInterface
};
use \Luminova\Exceptions\{
    AppException,
    FileException,
    ClassException,
    RuntimeException,
    InvalidArgumentException
};

/**
 * Resolve the application root directory with optional appended path.
 *
 * This function helps ensure all paths are built relative to the application’s root directory.
 * It attempts to locate the root by checking for a `.env` file in `APP_ROOT`, the parent directory,
 * or by walking up the directory tree.
 *
 * @param string|null $path Optional subdirectory path to point from project root (e.g., 'writeable/logs').
 * @param string|null $filename Optional filename to append after the path (e.g., 'debug.log').
 *
 * @return string Returns the absolute path to the root directory, with optional path and filename appended.
 *
 * @example - Usage;
 * ```php
 * $logPath = root('/writeable/logs/', 'debug.log');
 * 
 * // Returns: 
 * // Production: /var/www/example.com/writeable/logs/debug.log
 * // XAMPP: /Applications/XAMPP/xamppfiles/htdocs/your-app-root/writeable/logs/debug.log
 * // WAMP:  C:\wamp64\www\your-app-root\writeable\logs\debug.log
 * ```
 *
 * > **Note:** If you pass a filename as `$path` instead of using `$filename`, it will treat it as a folder
 * > and may produce `/root/file.txt/` — which is usually unintended.
 */
function root(?string $path = null, ?string $filename = null): string
{
    $path = $path
        ? (\trim(\str_replace(['/', '\\'], \DIRECTORY_SEPARATOR, $path), TRIM_DS) . \DIRECTORY_SEPARATOR)
        : '';
    $path .= $filename ? \ltrim($filename, TRIM_DS) : '';

    if (\file_exists(APP_ROOT . '.env')) {
        return APP_ROOT . $path;
    }

    $root = \dirname(__DIR__, 1) . \DIRECTORY_SEPARATOR;
    if (\file_exists($root . '.env')) {
        return $root . $path;
    }

    $root = \realpath(__DIR__);
    if ($root === false) {
        return $path; 
    }

    while ($root !== \DIRECTORY_SEPARATOR && !\file_exists($root . \DIRECTORY_SEPARATOR . '.env')) {
        $parent = \dirname($root);
        
        if ($parent === $root) {
            break;
        }

        $root = $parent;
    }

    return $root . ($root === \DIRECTORY_SEPARATOR ? '' : \DIRECTORY_SEPARATOR) . $path;
}

/**
 * Get an instance of the application.
 *
 * This function returns either a shared (singleton) or a new instance of the core application class.
 * By default, Luminova's `CoreApplication` doesn't accept constructor arguments, but this function
 * allows passing optional arguments to override or customize the instance.
 *
 * @param bool $shared Whether to return a shared instance (default: true).
 * @param mixed ...$arguments Optional arguments to pass to the application constructor.
 *
 * @return Application<CoreApplication,LazyInterface> Returns the shared instance if `$shared` is true,
 *         or a new instance if `$shared` is false.
 *
 * @see https://luminova.ng/docs/0.0.0/core/application
 *
 * @example Creating a new instance with arguments:
 * ```php
 * $app = app(false, 'foo', 'bar');
 * ```
 */
function app(bool $shared = true, mixed ...$arguments): Application 
{
    if ($shared) {
        return $arguments 
            ? Application::setInstance(new Application(...$arguments))
            : Application::getInstance();
    }

    return new Application(...$arguments);
}

/**
 * Retrieve an HTTP request object.
 * 
 * Returns a shared or new instance of the HTTP request handler for accessing
 * headers, query parameters, body data, and other request-specific information.
 *
 * @param bool $shared Whether to return a shared instance (default: true).
 * 
 * @return Request<HttpRequestInterface,LazyInterface> Returns instance of HTTP request class.
 * @see https://luminova.ng/docs/0.0.0/http/request
 */
function request(bool $shared = true): HttpRequestInterface 
{
    if (!$shared) {
        return new Request();
    }

    static $instance = null;
    if (!$instance instanceof Request) {
        $instance = new Request();
    }

    return $instance;
}

/**
 * Create a view response object.
 *
 * Returns a new or shared instance of the view response handler, used to send
 * JSON, HTML, Steam, Download or other content formats back to the client.
 *
 * @param int $status HTTP status code (default: 200).
 * @param array<string,mixed>|null $headers Optional response headers (default: null).
 * @param bool $shared Whether to return a shared instance (default: true).
 *
 * @return Response<ViewResponseInterface> Return instance of view response object.
 * @see https://luminova.ng/docs/0.0.0/templates/response
 *
 * @example Send a JSON response:
 * ```php
 * response()->json(['status' => 'OK', 'message' => 'Done!']);
 * ```
 */
function response(int $status = 200, ?array $headers = null,  bool $shared = true): ViewResponseInterface
{
    if (!$shared) {
        return new Response($status, $headers ?? []);
    }

    static $instance = null;
    if (!$instance instanceof Response) {
        $instance = new Response($status, $headers ?? []);
    }

    return $instance;
}

/**
 * Generate a URL to a view, route, or file path within the application.
 *
 * If `$view` is null or empty, it links to the base path.
 *
 * @param string|null $view The path or view name to link to (default: null).
 * @param bool $absolute Whether to return an absolute URL using `APP_URL` (default: false).
 *
 * @return string Return the generated URL.
 *
 * @example - Examples:
 * ```php
 * href();                         // "/"
 * href('about');                  // "/about"
 * href('admin/dashboard', true); // "https://example.com/admin/dashboard"
 * ```
 */
function href(?string $view = null, bool $absolute = false): string 
{
    $view = ($view === null) ? '' : \ltrim($view, '/');

    if($absolute){
        return \rtrim(APP_URL, '/') . '/' . $view;
    }

    static $relative = null;

    if($relative === null){
        $relative = Application::getInstance()->link();
    }

    return $relative . $view;
}

/**
 * Generate a URL to a file in the public `assets/` folder.
 *
 * If `$filename` is null, it links to the base assets directory.
 *
 * @param string|null $filename The asset path or filename (e.g., "css/app.css").
 * @param bool $absolute Whether to return an absolute URL using `APP_URL` (default: false).
 *
 * @return string Return the generated URL to the assets file or base assets folder if no filename is provided.
 *
 * @example - Examples:
 * ```php
 * asset('css/style.css');        // "/assets/css/style.css"
 * asset(null);                   // "/assets/"
 * asset('js/app.js', true);      // "https://example.com/assets/js/app.js"
 * ```
 */
function asset(?string $filename = null, bool $absolute = false): string
{
    $filename = 'assets/' . (($filename === null) ? '' : \ltrim($filename, '/'));

    return href($filename, $absolute);
}

/**
 * PHP Template layout helper class.
 * Allow you to extend and inherit a section of another template view file.
 * 
 * @param string $file Layout filename without the extension path.
 * @param string $module The HMVC custom module name (e.g, `Blog`, `User`).
 * 
 * @return Layout Returns the layout class instance.
 * @throws RuntimeException Throws if layout file is not found.
 * 
 * @example - Usage examples:
 * ```php
 * layout('foo')
 * // Or
 * layout('foo/bar/baz')
 * ```
 * 
 * > All layouts must be stored in `resources/Views/layout/` directory.
 */
function layout(string $file, string $module = ''): Layout
{
    return Layout::getInstance()->layout($file, $module);
}

/**
 * Return shared functions instance or a specific context instance.
 * 
 * If context is specified, return an instance of the specified context, 
 * otherwise return anonymous class which extends CoreFunction.
 * 
 * **Supported contexts:**
 * 
 *  -   ip: - Return instance of 'Luminova\Functions\IP'.
 *  -   document:  Return instance of 'Luminova\Functions\IP'.
 *  -   tor:  Return instance of 'Luminova\Functions\Tor'.
 *  -   math:  Return instance of 'Luminova\Functions\Maths'.
 *
 * @param string|null $context The context to return it's instance (default: null).
 * @param mixed $arguments [, mixed $... ] Optional initialization arguments based on context.
 *
 * @return CoreFunction<\T>|object<\T>|mixed Returns an instance of functions, 
 *              object string, or boolean value depending on the context, otherwise null.
 *
 * @throws AppException If an error occurs.
 * @throws RuntimeException If unable to call method.
 * @see https://luminova.ng/docs/0.0.0/core/functions
 */
function func(?string $context = null, mixed ...$arguments): mixed 
{
    if ($context === null) {
        return Factory::functions();
    }

    if (\in_array($context, ['ip', 'document', 'tor', 'math'], true)) {
        return Factory::functions()->{$context}(...$arguments);
    }

    return null;
}

/**
 * Import or load file if it exists.
 * 
 * This function uses `require` or `include` with options to use `_once` variants 
 * to load file and return it result if any.
 *
 * @param string $path    Full path to the file to import.
 * @param bool   $throw   If true, throws an exception when the file is missing (default: `false`).
 * @param bool   $once    If true, uses `*_once` variants (`require_once/include_once`) (default: `true`).
 * @param bool   $require If true, uses `require/require_once`, otherwise uses `include/include_once` (default: `true`).
 *
 * @return mixed Returns the result of the file, or null if not loaded or no result.
 * @throws RuntimeException If the file doesn't exist and $throw is true.
 * @example - Usages:
 * ```php
 * import(
 *      path: 'app/Config/text.php',
 *      throw: true, // Throw exception if file not found,
 *      once: true, // Include once,
 *      require: true // Use required instead
 * );
 * ```
 */
function import(string $path, bool $throw = false, bool $once = true, bool $require = true): mixed
{
    if (\is_file($path)) {
        return $require
            ? ($once ? require_once $path : require $path)
            : ($once ? include_once $path : include $path);
    }

    if ($throw) {
        throw new RuntimeException("Unable to import file: {$path} does not exist.");
    }

    return null;
}

/**
 * Logs a message to a specified destination using the configured PSR logger.
 * The destination can be a log level, email address, or URL endpoint. This function 
 * delegates the logging action to the dispatch method, which handles the 
 * asynchronous or synchronous execution as needed.
 *
 * Log Levels:
 * - emergency: Log urgent errors requiring immediate attention.
 * - alert: Log important alert messages.
 * - critical: Log critical issues that may disrupt application functionality.
 * - error: Log minor errors.
 * - warning: Log warning messages.
 * - notice: Log messages that require attention but are not urgent.
 * - info: Log general information.
 * - debug: Log messages for debugging purposes.
 * - exception: Log exception messages.
 * - php_errors: Log PHP-related errors.
 * - metrics: Log performance metrics for production APIs.
 *
 * @param string $to The destination for the log (e.g, log level, email address, or URL).
 * @param string $message The message to log.
 * @param array $context Additional context data (optional).
 *
 * @return void
 * @throws InvalidArgumentException Throws if an error occurs while logging or an invalid destination is provided.
 * @see https://luminova.ng/docs/0.0.0/logging/logger
 * @see https://luminova.ng/docs/0.0.0/logging/nova-logger
 * @see https://luminova.ng/docs/0.0.0/logging/levels
 */
function logger(string $to, string $message, array $context = []): void
{
    Logger::dispatch($to, $message, $context);
}

/**
 * Set locale or return locale application string.
 *
 * @param string|null $locale If locale is present it will set it else return the locale in use.
 *
 * @return string|bool Return application locale if null was passed.
 *          Or return true if new locale was passed and was successfully, otherwise false.
 */
function locale(?string $locale = null): string|bool 
{
    if(!$locale){
        return \env('app.locale', 'en');
    }

    return \setenv('app.locale', $locale, true);;
}

/**
 * Get the start URL with an optional route path.
 * 
 * This function will include hostname port (e.g, `example.com:8080`) if port available.
 * 
 * @param string $route Optional route URI path to append to the start URL (default: null).
 * 
 * @return string Return the generated start URL of your project pointing to optional route or path.
 * 
 * @example - Example:
 * 
 * Assuming your application path is like: `/Some/Path/To/htdocs/my-project-path/public/`.
 * 
 * ```php
 * echo start_url('about');
 * ```
 * 
 * It returns depending on your development environment:
 * 
 * **On Development:**
 * - http://localhost:8080/about
 * - http://localhost/my-project-path/public/about
 * - http://localhost/public/about
 * 
 * **In Production:**
 * - http://example.com:8080/about
 * - http://example.com/about
 */
function start_url(?string $route = null): string
{
    $route = ($route === null) ? '/' : '/' . \ltrim($route, '/');

    if(PRODUCTION){
        return APP_URL . $route;
    }

    $hostname = $_SERVER['HTTP_HOST'] 
        ?? $_SERVER['HOST'] 
        ?? $_SERVER['SERVER_NAME'] 
        ?? $_SERVER['SERVER_ADDR'] 
        ?? '';

    return URL_SCHEME . '://' . $hostname . '/' . CONTROLLER_SCRIPT_PATH . $route;
}

/**
 * Convert an application-relative path to an absolute URL.
 * 
 * @param string $path The relative path to convert to an absolute URL.
 * 
 * @return string Return the absolute URL of the specified path.
 * 
 * @example - Example:
 * 
 * Assuming your project path is: `/Applications/XAMPP/htdocs/project-path/public/` and `asset/files/foo.text`.
 * 
 * ```php
 * echo absolute_url('asset/files/foo.text');
 * ```
 * 
 * It returns: 
 * 
 * **On Development:**
 * http://localhost/project-path/public/asset/files/foo.text
 * 
 * **In Production:**
 * http://example.com/asset/files/foo.text
 */
function absolute_url(string $path): string
{
    return Luminova::toAbsoluteUrl($path);
}

/**
 * Sanitize a file path for user-facing display by removing internal or sensitive directory segments.
 *
 * This is useful when you want to hide full server paths (e.g., `/var/www/html/`) from end users.
 *
 * @param string $path The raw file system path to sanitize.
 *
 * @return string Return a cleaned and user-safe display path.
 *
 * @example - Example:
 * ```php
 * filter_paths('/var/www/html/example.com/writeable/storage/uploads/file.jpg');
 * // Returns: 'writeable/storage/uploads/file.jpg'
 * ```
 */
function filter_paths(string $path): string 
{
    return Luminova::filterPath($path);
}

/**
 * Convert a string to kebab-case format.
 *
 * Replaces all non-letter and non-digit characters with hyphens.
 * Optionally converts the entire string to lowercase.
 *
 * @param string $input The input string to convert.
 * @param bool $toLower Whether to convert the result to lowercase (default: true).
 *
 * @return string Return the kebab-cased version of the input string.
 * @example - Example:
 * ```php
 * echo kebab_case('hello world'); // hello-wold
 * echo kebab_case('HelLo-World'); // hello-wold
 * echo kebab_case('HelLo worlD'); // HelLo-worlD
 * ```
 */
function kebab_case(string $input, bool $toLower = true): string 
{
    if($input === ''){
        return '';
    }

    $input = \preg_replace('/[^\p{L}\p{N}]+/u', ' ', $input);
    $input = \trim(\str_replace(' ', '-', $input), '-');

    return $toLower ? \strtolower($input) : $input;
}

/**
 * Convert a string to camel case.
 *
 * @param string $input The string to convert.
 * 
 * @return string Return the string converted to camel case.
 * @example - Example:
 * ```php
 * echo camel_case('hello world'); // helloWold
 * echo camel_case('hello-world'); // helloWold
 * ```
 */
function camel_case(string $input): string
{
    $input = \str_replace(['-', ' '], '_', $input);
    $parts = \explode('_', $input);

    $camelCase = '';
    $firstPart = true;

    foreach ($parts as $part) {
        $camelCase .= $firstPart ? \strtolower($part) : \ucfirst($part);
        $firstPart = false;
    }
    
    return $camelCase;
}

/**
 * Convert a string to PascalCase format.
 *
 * Replaces spaces, underscores, and hyphens with word boundaries,
 * capitalizes each word, and removes all delimiters.
 *
 * @param string $input The input string to convert.
 *
 * @return string Return the PascalCase version of the input string.
 * @example - Example:
 * ```php
 * echo pascal_case('hello world'); // HelloWold
 * echo pascal_case('hello-world'); // HelloWold
 * ```
 */
function pascal_case(string $input): string
{
    if($input === ''){
        return '';
    }

    $input = \preg_replace('/[_\-\s]+/', ' ', \strtolower($input));
    return \str_replace(' ', '', \ucwords($input));
}

/**
 * Capitalize the first letter of each word in a string.
 *
 * Preserves underscores, hyphens, and spaces as delimiters,
 * and capitalizes the letter that follows each one.
 *
 * @param string $input The input string to convert.
 *
 * @return string Return the input string with the first character of each word capitalized.
 * @example - Example:
 * ```php
 * echo uppercase_words('hello world'); // Hello Wold
 * echo uppercase_words('hello-world'); // Hello-Wold
 * ```
 */
function uppercase_words(string $input): string
{
    if($input === ''){
        return '';
    }

    $input = \strtolower($input);
    
    if (\strpbrk($input[0], '_- ') === false) {
        $input[0] = \strtoupper($input[0]);
    }

    return \preg_replace_callback(
        '/([-_ ])+(\w)/',
        fn($matches) => $matches[1] . \strtoupper($matches[2]),
        $input
    );
}

/**
 * Generates a UUID string of the specified version such as `1, 2, 3, 4, or 5`.
 *
 * @param int $version The version of the UUID to generate (default: 4).
 * @param string|null $namespace The namespace for versions 3 and 5.
 * @param string|null $name The name for versions 3 and 5.
 * 
 * @return string Return the generated UUID string.
 * @throws InvalidArgumentException If the namespace or name is not provided for versions 3 or 5.
 * @see https://luminova.ng/docs/0.0.0/core/functions
 * 
 * @example - Example:
 * ```php
 * $version = 4;
 * 
 * $uuid = uuid($version); // uuid-string
 * 
 * // To check if UUID is valid use 
 * if(func()->isUuid($uuid, $version)){
 *      echo 'Yes';
 * }
 * ```
 */
function uuid(int $version = 4, ?string $namespace = null, ?string $name = null): string 
{
    return Func::uuid($version, $namespace, $name);
}

/**
 * Escapes a user input string or array of strings based on the specified context.
 * 
 * **Supported Context Values:**
 *
 * - html - Escape general HTML content. 
 * - js -   Escape JavaScript code. 
 * - css -  Escape CSS styles. 
 * - url -  Escape URL, 
 * - attr - Escape HTML attributes.
 * - raw -  Raw output no escaping apply.
 *
 * @param string|array $input The string or array of strings to be escaped.
 *           For array, you can optionally use the keys of the array to specify the escape context for each value.
 * @param string $context The escaper context in which the escaping should be performed (default:'html').
 * @param string $encoding The escape character encoding to use (default: 'utf-8').
 * 
 * @return array|string Return the escaped string or array of strings.
 * @throws InvalidArgumentException When an invalid, blank encoding is provided or unsupported encoding or empty string is provided.
 * @throws BadMethodCallException When an invalid context is called.
 * @see https://luminova.ng/docs/0.0.0/functions/escaper
 */
function escape(string|array $input, string $context = 'html', string $encoding = 'utf-8'): array|string
{
    if (\is_array($input)) {
        \array_walk_recursive(
            $input, 
            fn(&$value, $key) => $value = escape($value, \is_string($key) ? $key : $context, $encoding)
        );

        return $input;
    }

    $context = \strtolower($context);

    if ($context === 'raw') {
        return $input;
    }

    if($context === 'html' || $context === 'attr'){
        return \htmlspecialchars($input, \ENT_QUOTES | \ENT_SUBSTITUTE, $encoding);
    }

    if (!\in_array($context, ['html', 'js', 'css', 'url'], true)) {
        throw new InvalidArgumentException(\sprintf('Invalid escape context provided "%s".', $context));
    }

    static $escaper = null;
    $escaper ??= Factory::escaper($encoding);

    if ($encoding !== null && $escaper->getEncoding() !== $encoding) {
        $escaper = $escaper->setEncoding($encoding);
    }

    $method = 'escape' . \ucfirst($context);
    return $escaper->{$method}($input);
}

/**
 * Strictly sanitizes user input to protect against invalid characters and ensure it conforms to the expected type.
 * 
 * **Available types:**
 * 
 * - 'int'       : Only numeric characters (0-9) are allowed.
 * - 'numeric'   : Numeric characters, including negative numbers and decimals.
 * - 'key'       : Alphanumeric characters, underscores, and hyphens.
 * - 'password'  : Alphanumeric characters, and special characters (@, *, !, _, -).
 * - 'username'  : Alphanumeric characters, hyphen, underscore, and dot.
 * - 'email'     : Alphanumeric characters and characters allowed in email addresses.
 * - 'url'       : Valid URL characters (alphanumeric, ?, #, &, +, =, . , : , /, -).
 * - 'money'     : Numeric characters, including decimal and negative values.
 * - 'double'    : Floating point numbers (numeric and decimal points).
 * - 'alphabet'  : Only alphabetic characters (a-z, A-Z).
 * - 'phone'     : Numeric characters, plus sign, and hyphen (e.g., phone numbers).
 * - 'name'      : Unicode characters, spaces, and common name symbols (e.g., apostrophe).
 * - 'timezone'  : Alphanumeric characters, hyphen, slash, and colon (e.g., timezone names).
 * - 'time'      : Alphanumeric characters and colon (e.g., time format).
 * - 'date'      : Alphanumeric characters, hyphen, slash, comma, and space (e.g., date format).
 * - 'uuid'      : A valid UUID format (e.g., 8-4-4-4-12 hexadecimal characters).
 * - 'default'   : Removes HTML tags.
 *
 * @param string $string The input string to be sanitized.
 * @param string $type The expected data type (e.g., 'int', 'email', 'username').
 * @param string|null $replacement The symbol to replace disallowed characters or null to throw and exception (default: '').
 *
 * @return string|null Return the sanitized string or null if input doesn't match 
 * 			nor support replacing like `email` `url` `username` or `password`.
 * @throws InvalidArgumentException If the input contains invalid characters, or HTML tags, and no replacement is provided.
 * 
 * > **Note:** 
 * > - HTML tags (including their content) are completely removed for the 'default' type.
 * > - This method ensures secure handling of input to prevent invalid characters or unsafe content.
 * @see https://luminova.ng/docs/0.0.0/core/functions
 */
function strict(string $input, string $type = 'default', ?string $replacer = ''): ?string 
{
    return Func::strictType($input, $type, $replacer);
}

/**
 * Checks if the given IP address is a Tor exit node.
 *
 * @param string|null $ip The ip address to check, if NULL get current ip address.
 * @param int $expiration The expiration time to request for new exit nodes from tor api (default: 2592000 30 days).
 * 
 * @return bool Return true if ip address is a Tor exit node, otherwise false.
 * @throws FileException Throws if error occurs or unable to read or write to directory.
 * @see https://luminova.ng/docs/0.0.0/functions/ip
 */
function is_tor(?string $ip = null, int $expiration = 2592000): bool
{
    return IP::isTor($ip, $expiration);
}

/**
 * Get user IP address or return ip address information.
 *
 * @param bool $ipInfo Whether to true return ip address information instead (default: false).
 * @param array $options Optional data to return with IP information (default: none).
 * 
 * @return string|object|null Return client ip address or ip info, otherwise null if ip info not found.
 * @see https://luminova.ng/docs/0.0.0/functions/ip
 */
function ip_address(bool $ipInfo = false, array $options = []): string|object|null
{
    return $ipInfo ? IP::info(null, $options): IP::get();
}

/**
 * Check if any given values are considered `empty`.
 *
 * Unlike PHP's native `empty()`, this treats `0` and `'0'` as **not empty**,
 * but treats negative numbers, null, empty strings, arrays, or objects as empty.
 * 
 * This is a custom emptiness check that:
 * - Treats `0` and `'0'` as **not empty**
 * - Considers null, empty arrays, empty objects, empty strings, and negative numbers as empty
 * - Handles Countable objects (e.g., ArrayObject)
 *
 * @param mixed ...$values One or more values to evaluate.
 *
 * @return bool Returns true if any of the values are considered empty; false otherwise.
 *
 * @example - Example:
 * ```php
 * is_empty(0);            // false
 * is_empty([1]);          // false
 * is_empty(-3);           // true
 * is_empty(null);         // true
 * is_empty('');           // true
 * is_empty([]);           // true
 * is_empty((object)[]);   // true
 * ```
 */
function is_empty(mixed ...$values): bool
{
    foreach ($values as $value) {
        if (
            $value === null ||
            $value === [] ||
            $value === (object)[] ||
            (\is_string($value) && \trim($value) === '') ||
            (\is_numeric($value) && ($value + 0) < 0) ||
            (\is_object($value) && ($value instanceof \Countable) && \count($value) === 0)
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Return session data if key is present else return session instance.
 *
 * @param string $key Optional key to retrieve the data (default: null).
 * @param bool $shared Whether to use shared instance (default: true).
 * @param object<SessionManagerInterface> $manager The session manager interface to use (default: SessionManager).
 *
 * @return Session<LazyInterface>|mixed Return session instance or value if key is present.
 * @see https://luminova.ng/docs/0.0.0/sessions/session
 */
function session(?string $key = null, bool $shared = true, ?SessionManagerInterface $manager = null): mixed
{
    return ($key !== null && $key !== '') 
        ? Factory::session($manager, $shared)->get($key) 
        : Factory::session($manager, $shared);
}

/**
 * Create and return cookie instance.
 *
 * @param string $name Name of the cookie.
 * @param string $value Value of the cookie.
 * @param array  $options Options to be passed to the cookie.
 * @param bool $shared Use shared instance (default: false).
 * 
 * @return Cookie<Luminova\Interface\CookieInterface,LazyInterface> Return cookie instance.
 * @see https://luminova.ng/docs/0.0.0/cookies/cookie
 */
function cookie(string $name, string $value = '', array $options = [], bool $shared = false): Cookie
{
    return Factory::cookie($name, $value, $options, $shared);
}

/**
 * Returns a shared instance of a class in factory or factory instance if context is null.
 * 
 * @param string|null $context The factory context name. (default: null).
 * @param bool $shared Allow shared instance creation (default: true).
 * @param mixed $arguments [, mixed $... ] Optional class constructor initialization arguments.
 * 
 * **Factory Context Names:**
 * 
 * -   'task'           `\Luminova\Time\Task`
 * -   'session'        `\Luminova\Sessions\Session`
 * -   'cookie'         `\Luminova\Cookies\Cookie`
 * -   'functions'      `\Luminova\Core\CoreFunction`
 * -   'modules'        `\Luminova\Library\Modules`
 * -   'language'       `\Luminova\Languages\Translator`
 * -   'logger'         `\Luminova\Logger\Logger`
 * -   'escaper'        `\Luminova\Functions\Escape`
 * -   'network'        `\Luminova\Http\Network`
 * -   'fileManager'    `\Luminova\Storages\FileManager`
 * -   'validate'       `\Luminova\Security\Validation`
 * -   'response'       `\Luminova\Template\Response`
 * -   'request'        `\Luminova\Http\Request`
 * -   'service'        `\Luminova\Application\Services`
 * -   'notification'   `\Luminova\Notifications\Firebase\Notification`,
 * -   'caller'         `\Luminova\Application\Caller`
 * 
 * @return object<\T>|Factory|null Return instance of factory or instance of factory class, otherwise null.
 * @throws AppException Throws an exception if factory context does not exist or error occurs.
 * @example - using factory to load class like: `$config = factory('config');`.
 * 
 * Is same as:
 * 
 * ```php
 * $config = \Luminova\Application\Factory::config();
 * // Or
 * $config = new \Luminova\Config\Configuration();
 * ```
 * @see https://luminova.ng/docs/0.0.0/boot/factory
 */
function factory(?string $context = null, bool $shared = true, mixed ...$arguments): ?object
{
    if($context === null || $context === ''){
        return new Factory();
    }

    $arguments[] = $shared;

    return Factory::$context(...$arguments);
}

/**
 * Returns a shared instance of a class in services or service instance if context is null.
 * 
 * @param class-string<\T>|string|null $service The service class name or alias.
 * @param bool $shared Allow shared instance creation (default: true).
 * @param bool $serialize Allow object serialization (default: false).
 * @param mixed $arguments [, mixed $... ] Service initialization arguments.
 * 
 * @return object<\T>|Services|null Return service class instance or instance of service class.
 * @throws AppException Throws an exception if service does not exist or error occurs.
 * 
 * @example - Get config:
 * 
 * ```php
 * $config = service('Config');
 * // OR
 * $config = Services::Config();
 * ```
 * 
 * Both are Same as:
 * ```php
 * $config = new \Foo\Bar\Config();
 * ```
 * @see https://luminova.ng/docs/0.0.0/boot/service
 */
function service(?string $service = null, bool $shared = true, bool $serialize = false, mixed ...$arguments): ?object
{
    if($service === null || $service === ''){
        return Factory::service();
    }

    $arguments[] = $serialize;
    $arguments[] = $shared;

    return Factory::service()->{$service}(...$arguments);
}

/**
 * Delete a service or clear all services
 * If NULL is passed all cached services instances will be cleared.
 * Else delete a specific services instance and clear it's cached instances.
 * 
 * @param class-string<\T>|string $service The class name or alias, to delete and clear it cached.
 * 
 * @return bool Return true if the service was removed or cleared, false otherwise.
 * @see https://luminova.ng/docs/0.0.0/boot/service
 */
function remove_service(?string $service = null): bool
{
    if($service === null){
        return Factory::service()->clear();
    }

    return Factory::service()->delete($service);
}

/**
 * Tells what the user's browser is capable of.
 * 
 * Return Types: 
 * 
 * - array: - Return browser information as array.
 * - object: - Return browser information as object.
 * - instance: - Return browser information instance.
 * 
 * @param string|null $userAgent  The user agent string to analyze.
 * @param bool $return Set the return type, if `instance` return userAgent 
 *              class object otherwise return array or json object.
 * @param bool $shared Allow shared instance creation (default: true).
 * 
 * @return array<string,mixed>|object<string,mixed>|UserAgent|false Return browser information.
 * @see https://luminova.ng/docs/0.0.0/http/user-agent
 */
function browser(?string $userAgent = null, string $return = 'object', bool $shared = true): mixed
{ 
    if($return === 'instance'){
        return request($shared)->getUserAgent($userAgent);
    }

    $return = ($return === 'array');

    if (\ini_get('browscap')) {
        $browser = \get_browser($userAgent, $return);
        
        if ($browser !== false) {
            return $browser;
        }
    }

    return request($shared)->getUserAgent()->parse($userAgent, $return);
}

/**
 * Tells which operating system platform your application is running on.
 * 
 * **Predefine OS Values:**
 * 
 * - mac - For macOS.
 * - windows - For Windows OS.
 * - linux - For Linux OS.
 * - freebsd - For FreeBSD OS.
 * - openbsd - For OpenBSD OS.
 * - bsd - For BSD OS.
 * - solaris - For Solaris OS.
 * - aws - For AWS OpsWorks.
 * - azure - For Azure environment.
 * - etc.
 * 
 * @param string $os The platform name to check.
 * 
 * @return bool Return true if the platform is matching, false otherwise.
 * @example - Usage:
 * ```php
 * is_platform('windows') // Return true on window
 * ```
 */
function is_platform(string $os): bool
{ 
    $os = \strtolower($os);
    return match ($os) {
        'mac' => \PHP_OS_FAMILY === 'Darwin',
        'windows' => \PHP_OS_FAMILY === 'Windows',
        'freebsd' => \PHP_OS === 'FreeBSD',
        'openbsd' => \PHP_OS === 'OpenBSD',
        'bsd' => \PHP_OS_FAMILY === 'BSD',
        'solaris' => \PHP_OS_FAMILY === 'Solaris',
        'linux' => \PHP_OS_FAMILY === 'Linux',
        'aws' => isset($_ENV['AWS_EXECUTION_ENV']),
        'azure' => isset($_ENV['WEBSITE_INSTANCE_ID']) || isset($_ENV['AZURE_FUNCTIONS_ENVIRONMENT']),
        default => \str_contains(\php_uname('s'), $os),
    };
}

/**
 * Converts text characters in a string to HTML entities. 
 * 
 * @param string $text A string containing the text to be processed.
 * 
 * @return string Return the processed text with HTML entities.
 */
function text2html(?string $text): string
{ 
    $text ??= '';
    return (\trim($text) === '') ? '' : \htmlspecialchars($text, \ENT_QUOTES|\ENT_HTML5);
}

/**
 * Converts newline characters in a string to HTML entities. 
 * This is useful when you want to display text in an HTML textarea while preserving the original line breaks.
 * 
 * @param string|null $text A string containing the text to be processed.
 * 
 * @return string Return formatted string.
 */
function nl2html(?string $text): string
{
    $text ??= '';

    return (\trim($text) === '') 
        ? '' 
        : \str_replace(
            ["\n", "\r\n", '[br/]', '<br/>', "\t"], 
            ["&#13;&#10;", "&#13;&#10;", "&#13;&#10;", "&#13;&#10;", "&#09;"], 
            $text
        );
}

/**
 * Import a custom library from the libraries/libs directory.
 *
 * This function simplifies loading external libraries stored under `/libraries/libs/`.
 *
 * @param string $library The library path or name (e.g., 'Foo/Bar/Baz' or 'Foo/Bar/Baz.php').
 * @param bool   $throw  If true, throws an exception when the library file is not found.
 *
 * @example - Example:
 * ```php
 * import_lib('Foo/Bar/Baz');
 * // Or
 * import_lib('Foo/Bar/Baz.php');
 * ```
 *
 * @return bool Returns true if the library was successfully loaded, false otherwise.
 * @throws RuntimeException If the file is missing and $throw is true.
 */
function import_lib(string $library, bool $throw = false): bool
{
    $path = root('/libraries/libs/', \trim(\rtrim($library, '.php'), TRIM_DS) . '.php');

    try {
        import($path, true);
        return true;
    } catch (\Throwable $e) {
        if ($throw) {
            throw new RuntimeException(
                \sprintf("Failed to import library: %s from path: %s", $library, $path), 
            0, $e);
        }

        return false;
    }
}

/**
 * Translate multiple languages it supports nested array.
 * 
 * Placeholder Pattern:
 * 
 * - sing index: "Error name {0} and email {1}"
 * - Using keys: "Error name {name} and email {email}"
 *
 * @param string $lookup The language context annotation line to lookup (e.g, `App.error.foo.bar`).
 * @param string|null $default Optional fallback message or translation if not found.
 * @param string|null $locale OPtion translation locale to use. If null the default application will be used.
 * @param array<string|int,string|int> $placeholders Optional replaceable placeholders key-pir to translate in message.
 * 
 * 
 * @return string Return translated message.
 * @throws NotFoundException if translation is not found and default is not provided.
 * @see https://luminova.ng/docs/0.0.0/languages/translate
 * 
 * @example - Using index:
 * 
 * ```php 
 * echo lang('User.error.all', null, 'en', ['Peter', 'peter@foo.com]);
 * ```
 * @example - Using keys:
 * 
 * ```php
 * echo lang('User.error.all', null, 'en', [
 *      'name' => 'Peter', 
 *      'email' => 'peter@foo.com
 * ]);
 * ```
 */
function lang(string $lookup, ?string $default = null, ?string $locale = null, array $placeholders = []): string
{
    $default ??= '';
    $instance = Factory::language();

    $defaultLocal = $instance->getLocale();

    if ($locale && $locale !== $defaultLocal) {
        $instance->setLocale($locale);
    }

    $translation = $instance->get($lookup, $default, $placeholders);

    if ($locale && $locale !== $defaultLocal) {
        $instance->setLocale($defaultLocal);
    }

    return $translation;
}

/**
 * Get system or application path, converted to `unix` or `windows` directory separator style.
 * 
 * **Available Paths:**
 * 
 * - `app` - Application root directory.
 * - `system` - Luminova Framework and third-party plugins root directory.
 * - `plugins` - Third-party plugins root directory.
 * - `library` - Custom libraries root directory.
 * - `controllers` - Application controllers directory.
 * - `writable` - Application writable directory.
 * - `logs` - Application logs directory.
 * - `caches` - Application cache directory.
 * - `public` - Application public directory (front controller).
 * - `assets` - Application public assets directory.
 * - `views` - Application template views directory.
 * - `routes` - Application method-based routes directory.
 * - `languages` - Application language pack directory.
 * - `services` - Application cached services directory.
 * 
 * @param string $file Path file name to return.
 * 
 * @return string Return directory path, windows, unix or windows style path. 
 */
function path(string $name): string
{
    return Factory::fileManager()->getCompatible($name);
}

/**
 * Extract values from a specific column of an object list.
 *
 * Works like `array_column()` but for an object.
 * If `$property` is `null`, the entire object or is returned.
 *
 * @param object $from  The input collection of (objects or iterable object).
 * @param string|int|null $property The key or property to extract from each item.
 * @param string|int|null $index Optional. A key/property to use as the array index for returned values.
 *
 * @return object Returns an object of extracted values. 
 *          If `$index` is provided, it's used as the keys.
 * @see get_column()
 * 
 * @example - Example:
 * ```php
 * $objects = (object) [
 *     (object)['id' => 1, 'name' => 'Foo'],
 *     (object)['id' => 2, 'name' => 'Bar']
 * ];
 * object_column($objects, 'name'); // (object)['Foo', 'Bar']
 * object_column($objects, 'name', 'id'); // (object)[1 => 'Foo', 2 => 'Bar']
 * ```
 */
function object_column(object $from, string|int|null $property, string|int|null $index = null): object 
{
    if((array) $from === []){
        return (object)[];
    }

    if ($index === null) {
        return (object) \array_map(function ($item) use ($property) {
            return ($property === null)
                ? $item
                : (\is_object($item) ? $item->{$property} ?? null : ($item[$property] ?? null));
        }, (array) $from);
    }

    $columns = [];

    foreach ($from as $item) {
        $isObject = \is_object($item);
        $key = $isObject ? $item->{$index} ?? null : ($item[$index] ?? null);

        if ($key === null) {
            continue;
        }

        $columns[$key] = ($property === null)
            ? $item
            : ($isObject ? ($item->{$property} ?? null) : ($item[$property] ?? null));
    }

    return (object) $columns;
}

/**
 * Extract values from a specific column of an array or object list.
 *
 * Uses PHP `array_column()` or Luminova `object_column()` to support both arrays and objects as well.
 * If `$property` is `null`, the entire object or subarray is returned.
 *
 * @param array|object $from The input collection (array of arrays/objects or iterable object).
 * @param string|int|null $property The key or property to extract from each item.
 * @param string|int|null $index Optional. A key/property to use as the array index for returned values.
 *
 * @return array|object Returns an array of extracted values. 
 *          If `$index` is provided, it's used as the keys.
 * @see object_column()
 *
 * @example - Array Example:
 * ```php
 * $arrays = [
 *     ['id' => 1, 'name' => 'Alice'],
 *     ['id' => 2, 'name' => 'Bob']
 * ];
 * get_column($arrays, 'name'); // ['Alice', 'Bob']
 * get_column($arrays, 'name', 'id'); // [1 => 'Alice', 2 => 'Bob']
 *```
 * @example - Object Example:
 * ```php
 * $objects = (object) [
 *     (object)['id' => 1, 'name' => 'Foo'],
 *     (object)['id' => 2, 'name' => 'Bar']
 * ];
 * get_column($objects, 'name'); // (object)['Foo', 'Bar']
 * get_column($objects, 'name', 'id'); // (object)[1 => 'Foo', 2 => 'Bar']
 * ```
 */
function get_column(array|object $from, string|int|null $property, string|int|null $index = null): array|object 
{
    return \is_array($from) 
        ? \array_column($from, $property, $index)
        : object_column($from, $property, $index);
}

/**
 * Determine if an array is nested (contains arrays as values).
 *
 * If `$recursive` is true, it checks all levels deeply; otherwise, it checks only one level.
 *
 * @param array $array The array to check.
 * @param bool $recursive Whether to check recursively (default: false).
 * @param bool $strict Whether to require all values to be arrays (default: false).
 *
 * @return bool Return true if a nested array is found, false otherwise.
 *
 * @example - Examples:
 * ```php
 * is_nested([1, 2, 3]); // false
 * is_nested([1, [2], 3]); // true
 * is_nested(array: [1, [2], 3], strict: true); // false
 * is_nested([[1], [2, [3]]], true); // true
 * ```
 */
function is_nested(array $array, bool $recursive = false, bool $strict = false): bool 
{
    if ($array === []) {
        return false;
    }

    foreach ($array as $value) {
        if (!\is_array($value)) {
            if($strict){
                return false;
            }

            continue;
        }

        if ($recursive && !empty($value) && !is_nested($value, true, $strict)) {
            return false;
        }

        if (!$strict && !$recursive) {
            return true;
        }
    }

    return $strict || $recursive;
}

/**
 * Check if an array is associative (has non-integer or non-sequential keys).
 *
 * @param array $array The array to check.
 *
 * @return bool Return true if associative, false if indexed or empty.
 *
 * @example - Example:
 * ```php
 * is_associative(['a' => 1, 'b' => 2]); // true
 * is_associative([0 => 'a', 1 => 'b']); // false
 * is_associative([]); // false
 * ```
 */
function is_associative(array $array): bool
{
    if ($array === [] || isset($array[0])) {
        return false;
    }

    return \array_keys($array) !== \range(0, \count($array) - 1);
}

/**
 * Recursively convert an object (or mixed type) to an array.
 *
 * @param mixed $input The input to convert (object, array, or scalar).
 *
 * @return array Return the array representation.
 *
 * @example - Example:
 * ```php
 * to_array((object)['a' => 1, 'b' => (object)['c' => 2]]);
 * // ['a' => 1, 'b' => ['c' => 2]]
 * ```
 */
function to_array(mixed $input): array
{
    if (\is_string($input)) {
        return list_to_array($input) ?: [$input];
    }

    if (\is_array($input)) {
        return \array_map('to_array', $input);
    }

    if (!\is_object($input)) {
        return (array) $input;
    }

    $array = [];
    foreach ($input as $key => $value) {
        $array[$key] = (\is_object($value) || \is_array($value))
            ? to_array($value)
            : $value;
    }

    return $array;
}

/**
 * Convert an array or listify delimited string list to a standard JSON object.
 *
 * @param array|string $input Input array or listify string 
 *                  from `Luminova\Arrays\Listify` (e.g, `foo=bar,bar=2,baz=[1;2;3]`).
 *
 * @return object|false JSON object if successful, false on failure.
 * 
 * @example - Example:
 * ```php
 * to_object(['a' => 1, 'b' => 2]);
 * // (object)['a' => 1, 'b' => 2]
 *
 * String Listify
 * 
 * to_object('foo=bar,bar=2,baz=[1;2;3]');
 * // (object)[
 *       'foo' => 'bar', 
 *       'bar' => 2, 
 *       'baz' => [1, 2, 3]
 *   ]
 * ```
 */
function to_object(array|string $input): object|bool
{
    if ($input === [] || $input === '') {
        return (object)[];
    }

    if (\is_string($input)) {
        $input = \trim($input);

        if($input === ''){
            return false;
        }

        $input = list_to_array($input);

        if ($input === false) {
            return false;
        }
    }

    try {
        return \json_decode(\json_encode($input, JSON_THROW_ON_ERROR));
    } catch (\JsonException) {
        return false;
    }
}

/**
 * Convert a valid string list to an array.
 *
 * The function uses `Luminova\Arrays\Listify` to convert a string list format into an array.
 *
 * @param string $list The string to convert.
 *
 * @return array|false Returns the parsed array, or false on failure.
 * @see https://luminova.ng/docs/0.0.0/utils/list
 *
 * @example - Example:
 * ```php
 * list_to_array('a,b,c')          // ['a', 'b', 'c']
 * list_to_array('"a","b","c"')    // ['a', 'b', 'c']
 * ```
 */
function list_to_array(string $list): array|bool 
{
    if (!$list) {
        return false;
    }
    
    try{
        return Listify::toArray($list);
    }catch(\Throwable){
        return false;
    }
}

/**
 * Check if all values in a string list exist in a given array.
 *
 * This function converts the list using `list_to_array()` and verifies all items exist in the array.
 *
 * @param string $list The string list to check.
 * @param array $array The array to search for listify values in.
 *
 * @return bool Returns true if all list items exist in the array; false otherwise.
 * @see https://luminova.ng/docs/0.0.0/utils/list
 */
function list_in_array(string $list, array $array = []): bool 
{
    if(!$array && $list === ''){
        return true;
    }

    if(!$array || $list === ''){
        return false;
    }
    
    $map = is_list($list) ? list_to_array($list) : [$list];

    if($map === false){
        return false;
    }

    foreach ($map as $item) {
        if (!\in_array($item, $array)) {
            return false;
        }
    }

    return true;
}

/**
 * Check if a string is a valid Luminova listify-formatted string.
 *
 * Validates that the string matches a recognized list format used by Listify.
 *
 * @param string $input The string to validate.
 *
 * @return bool Returns true if valid; false otherwise.
 * @see https://luminova.ng/docs/0.0.0/utils/list
 */
function is_list(string $input): bool 
{
    return $input && Listify::isList($input);
}

/**
 * Write or append string contents or stream to file.
 * 
 * This function is an alternative for `file_put_contents`, it uses `SplFileObject` to write contents to file. 
 * 
 * @param string $filename Path to the file to write contents.
 * @param string|resource $content The contents to write to the file, either as a string or a stream resource.
 * @param int $flags [optional] The value of flags can be any combination of the following flags (with some restrictions), joined with the binary OR (|) operator.
 * @param resource $context [optional] A valid context resource created with stream_context_create.
 * 
 * @return bool Return true if successful, otherwise false on failure.
 * @throws FileException If unable to write file.
 */
function write_content(string $filename, mixed $content, int $flag = 0, $context = null): bool 
{
    return FileManager::write($filename, $content, $flag, $context);
}

/**
 * Reads the content of a file with options for specifying the length of data to read and the starting offset.
 * This function is an alternative for `file_get_contents`, it uses `SplFileObject` to open the file and read its contents. 
 * It can handle reading a specific number of bytes from a given offset in the file.
 * 
 * @param string $filename The path to the file to be read.
 * @param int $length The maximum number of bytes to read, if set to `0`, it read 8192 bytes at a time (default: 0).
 * @param int $offset The starting position in the file to begin reading from (default: 0).
 * @param bool $useInclude If `true`, the file will be searched in the include path (default: false). 
 * @param resource|null $context A context resource created with `stream_context_create()` (default: null).
 * 
 * @return string|false Returns the contents of the file as a string, or `false` on failure.
 * 
 * @throws FileException If an error occurs while opening or reading the file.
 */
function get_content(
    string $filename, 
    int $length = 0, 
    int $offset = 0, 
    bool $useInclude = false, 
    mixed $context = null,
    int $delay = 0
): string|bool 
{
    return FileManager::getContent($filename, $length, $offset, $useInclude, $context, $delay);
}

/**
 * Attempts to create the directory specified by pathname if not exist.
 * 
 * @param string $path Directory path to create.
 * @param int $permissions Unix file permissions (default: `App\Config\Files::$dirPermissions`).
 * @param bool $recursive Allows the creation of nested directories (default: true)
 * 
 * @return bool Return true if files already existed or was created successfully, otherwise false.
 * @throws RuntimeException If path is not readable.
 * @throws FileException If unable to create directory
 */
function make_dir(string $path, ?int $permissions = null, bool $recursive = true): bool 
{
    return FileManager::mkdir($path, $permissions ?? Files::$dirPermissions, $recursive);
}

/**
 * Retrieves the path for temporary files or generates a unique temporary file name.
 *
 * @param string|null $prefix Optional prefix for the temporary filename or a new sub-directory.
 * @param string|null $extension  Optional file extension for the temporary filename.
 * @param bool $local Indicates whether to use a local writable path (default: false).
 *
 * @return string|false Returns the path of the temporary directory, a unique temporary filename 
 *                      with the specified extension, or false on failure.
 */
function get_temp_file(?string $prefix = null, ?string $extension = null, bool $local = false): string|bool
{
    $dir = ($local 
        ? root('/writeable/temp/') 
        : \sys_get_temp_dir() . \DIRECTORY_SEPARATOR
    );

    if($local && !make_dir($dir, 0755)){
        return false;
    }

    if($extension){
        $prefix ??= 'tmp_';
        $extension = '.' . \ltrim($extension, '.');
        $file = \tempnam($dir, $prefix);
        static $ids = [];

        if($file === false){
            $id = $prefix . $extension;
            $ids[$id] ??= \uniqid($prefix, true);
            $file = $dir . $ids[$id] . $extension;

            return (!\file_exists($file) && (!\is_writable($dir) || !\touch($file)))
                ? false
                : $file;
        }

        return \rename($file, $file . $extension) 
            ? $file . $extension
            : $file;
    }

    if ($prefix) {
        $newDir = $dir . $prefix . \DIRECTORY_SEPARATOR;
        return (\is_dir($newDir) || make_dir($newDir, 0755))
            ? $newDir
            : $dir;
    }

    return $dir;
}

/**
 * Validate input fields or return validation instance.
 * 
 * If input and rules are specified, it will do the validation and return instance which you can then called method `$validation->isPassed()`
 * To check if passed or failed, or get the error information.
 *
 * @param array $inputs Input fields to validate on (e.g, `$_POST`, `$_GET` or `$this->request->getBody()`).
 * @param array $rules Validation filter rules to apply on each input field.
 * @param array $messages Validation error messages to apply on each filter on input field.
 * 
 * @return ValidationInterface Return instance of input validation object.
 * @see https://luminova.ng/docs/0.0.0/security/validation
 * 
 * @example - Validation example:
 * ```php
 * $rules = ['email' => 'required|email'];
 * $messages = [
 *   'email' => [
 *        'required' => 'email is required',
 *        'email' => 'Invalid [value] while validating [rule] on [field]'
 *    ]
 * ];
 * 
 * $input = [
 *      'email' => 'peter@example.com'
 * ];
 * 
 * $validate = validate($input, $rules, $messages);
 * if($validate->isPassed()){
 *      echo 'Success';
 * }else{
 *      $error $validate->getError();
 *      $errors $validate->getErrors();
 *      echo $error;
 *      var_dump($errors);
 * }
 * ```
 */
function validate(?array $inputs = null, ?array $rules = null, array $messages = []): ValidationInterface 
{
    $instance = Factory::validate();

    if ($inputs && $rules) {
        $instance->setRules($rules, $messages);
        $instance->validate($inputs);
    }
    
    return $instance;
}

/**
 * Get class basename from namespace or object.
 * 
 * @param string|object<\T> $from Class namespace or class object.
 * 
 * @return string Return the class basename.
 */
function get_class_name(string|object $from): string 
{
    return Luminova::getClassBaseNames(\is_string($from) ? $from : \get_class($from));
}

/**
 * Find whether application is running in cli mode.
 *
 * @return bool Return true if request is made in cli mode, false otherwise.
 */
function is_command(): bool
{
    return Luminova::isCommand();
}

/**
 * Check if the application is running locally on development server.
 *
 * @return bool Return true if is development server, false otherwise.
 */
function is_dev_server(): bool
{
    if(isset($_SERVER['NOVAKIT_EXECUTION_ENV'])){
        return true;
    }

    if(($server = ($_SERVER['SERVER_NAME'] ?? false)) !== false){
        return (
            $server === '::1' || 
            \str_contains($server, 'localhost') || 
            \str_contains($server, '127.0.0.1')
        );
    }
    
    return false;
}

/**
 * Find whether the type of a variable is blob.
 *
 * @param mixed $value Value to check.
 * 
 * @return bool Return true if the value is a blob, false otherwise.
 */
function is_blob(mixed $value): bool 
{
    return FileManager::isResource($value, 'stream');
}

/**
 * Get the PHP script executable path.
 *
 * @return string|null Return PHP executable path or null.
 */
function which_php(): ?string
{
    if (\defined('PHP_BINARY')) {
        return PHP_BINARY;
    }

    if (isset($_SERVER['_']) && \str_contains($_SERVER['_'], 'php')) {
        return $_SERVER['_'];
    }

    return null;
}

/**
 * Convert status to int, return run status based on result.
 * In CLI, 0 is considered success while 1 is failure.
 * In some occasions, void or null may be returned, treating it as success.
 * 
 * @param mixed $result The response from the callback function or method to check (e.g, `void`, `bool`, `null`, `int`).
 * @param bool $returnInt Whether to return int or bool (default: int).
 * 
 * @return int|bool Return status response as boolean or integer value.
 */
function status_code(mixed $result = null, bool $returnInt = true): int|bool
{
    if ($result === false || (\is_int($result) && $result == 1)) {
        return $returnInt ? 1 : false;
    }

    return $returnInt ? (int) $result : true;
}

/**
 * Checks if a given string is valid UTF-8.
 *
 * @param string $input The string to check for UTF-8 encoding.
 * 
 * @return bool Returns true if the string is UTF-8, false otherwise.
 */
function is_utf8(string $input): bool 
{
    if($input === ''){
        return true;
    }

    static $mbstring = null;
    $mbstring ??= \function_exists('mb_check_encoding');

    if($mbstring){
        return \mb_check_encoding($input, 'UTF-8');
    }

    return \preg_match('//u', $input) === 1;
}

/**
 * Checks if a given string contains an uppercase letter.
 *
 * @param string $string The string to check uppercase.
 * 
 * @return bool Returns true if the string has uppercase, false otherwise.
 */
function has_uppercase(string $string): bool 
{
    for ($i = 0; $i < \strlen($string); $i++) {
        if (\ctype_upper($string[$i])) {
            return true;
        }
    }

    return false;
}

/**
 * Calculate string length based on different charset.
 *
 * @param string $content The content to calculate length for.
 * @param string|null $charset The character set of the content.
 * 
 * @return int Return the calculated Content-Length.
 */
function string_length(string $content, ?string $charset = null): int 
{
    if ($content === '') {
        return 0;
    }

    $charset = \strtolower(\trim($charset ?? \env('app.charset', 'utf-8')));

    return match ($charset) {
        'utf-8', 'utf8' => \mb_strlen($content, '8bit'),
        'iso-8859-1', 'latin1', 'windows-1252' => \strlen($content),
        default => is_utf8($content) 
            ? \mb_strlen($content, '8bit') 
            : \strlen(\mb_convert_encoding($content, 'iso-8859-1', 'utf-8') ?: $content),
    };
}

/**
 * Detect the MIME type of a file or raw data.
 *
 * If the input string is a path to an existing file, it uses `\finfo->file()`,
 * otherwise it treats the input as raw binary and uses `\finfo->buffer()`.
 *
 * @param string $input File path or raw binary string to extract mime from.
 * @param string|null $magicDatabase  Optional path to a custom magic database (e.g, \path\custom.magic).
 * 
 * @return string Return the detected MIME type (e.g. "image/jpeg"), or false if detection fails.
 */
function get_mime(string $input, ?string $magicDatabase = null): string|bool
{
    if($input === ''){
        return 'text/plain';
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE, $magicDatabase);

    if ($finfo === false) {
        return false;
    }

    $mime = \is_file($input)
        ? ($finfo->file($input) ?: \mime_content_type($input))
        : $finfo->buffer($input);
    
    \finfo_close($finfo);

    return $mime;
}

/**
 * Temporarily stores and retrieves values within the same scope.
 *
 * @param string $key The key to identify the value.
 * @param mixed $value The value to store (optional).
 * @param mixed $default The default value return if key not found (default: NULL).
 * 
 * @return mixed Returns the value associated with the key, or default value if the key does not exist.
 */
function shared(string $key, mixed $value = null, mixed $default = null): mixed 
{
    static $preference = [];

    if ($value !== null) {
        $preference[$key] = $value;
        return $value;
    }

    if(\array_key_exists($key, $preference)){
        return $preference[$key];
    }

    return $default;
}

/**
 * Retrieves the configurations for the specified context.
 * This function can only be use to return configuration array stored in `app/Configs/` directory.
 * 
 * @param string $filename The configuration filename (without extension).
 * @param array|null $default The default configuration if file could not be load (default: null).
 * 
 * @return array<mixed>|null Return array of the configurations for the filename, or false if not found.
 */
function configs(string $filename, ?array $default = null): ?array 
{
    static $configs = [];
    static $path = null;

    if (isset($configs[$filename])) {
        return $configs[$filename];
    }

    if ($path === null) {
        $path = root('/app/Config/');
    }

    if (\is_readable($file = $path . $filename . '.php')) {
        $configs[$filename] = require $file;
        return $configs[$filename];
    }

    return $default;
}

/**
 * Initialize or retrieve a new instance of the cache class.
 * 
 * @param string $driver The cache driver to return instance of [filesystem or memcached](default: `filesystem`).
 * @param string|null $storage The name of the cache storage. If null, you must call the `setStorage` method later (default: null).
 * @param string|null $persistentIdOrSubfolder Optional persistent id or subfolder for storage (default: null):
 *  - For Memcached: A unique persistent connection ID. If null, the default ID from environment variables is used, or "default" if not set.
 *  - For Filesystem Cache: A subdirectory within the cache directory. If null, defaults to the base cache directory.
 * 
 * @return FileCache|MemoryCache Return new instance of instance of cache class based on specified driver.
 * @throws ClassException If unsupported driver is specified.
 * @throws CacheException If there is an issue initializing the cache.
 * @throws InvalidArgumentException If an invalid subdirectory is provided for the filesystem cache.
 */
function cache(
    string $driver = 'filesystem', 
    ?string $storage = null, 
    ?string $persistentIdOrSubfolder = null
): FileCache|MemoryCache {
    /**
     * @var array<string,FileCache|MemoryCache> $instances
     */
    static $instances = [];
    $instances[$driver] ??= match ($driver) {
        'memcached' => new MemoryCache($storage, $persistentIdOrSubfolder),
        'filesystem' => new FileCache($storage, $persistentIdOrSubfolder),
        default => throw new ClassException(
            'Invalid cache driver type specified. Supported drivers: memcached, filesystem.'
        ),
    };

    $cache = $instances[$driver];

    if ($storage !== null && $cache->getStorage() !== $storage) {
        $cache->setStorage($storage);
    }

    if ($persistentIdOrSubfolder !== null) {
        if ($driver === 'memcached' && $cache->getId() !== $persistentIdOrSubfolder) {
            $cache->setId($persistentIdOrSubfolder);
        }

        if ($driver === 'filesystem') {
            $subfolder = \trim($persistentIdOrSubfolder, TRIM_DS) . \DIRECTORY_SEPARATOR;
            if ($cache->getFolder() !== $subfolder) {
                $cache->setFolder($subfolder);
            }
        }
    }

    return $cache;
}

/**
 * Merges arrays recursively ensuring unique values in nested arrays. 
 * Unlike traditional recursive merging, it replaces duplicate values rather than appending them. 
 * When two arrays contain the same key, the value in the second array replaces the one in the first array.
 *
 * @param array<string|int,mixed> &$array1 The array to merge into, passed by reference.
 * @param array<string|int,mixed> &$array2 The array to merge from, passed by reference.
 * 
 * @return array Return the merged array with unique values.
 */
function array_merge_recursive_distinct(array &$array1, array &$array2): array
{
    foreach ($array2 as $key => $value) {
        $array1[$key] = (\is_array($value) && isset($array1[$key]) && \is_array($array1[$key])) 
            ? array_merge_recursive_distinct($array1[$key], $value)
            : $value;
    }

    return $array1;
}

/**
 * Merges multiple arrays recursively. 
 * When two arrays share the same key, values from the second array overwrite those from the first. 
 * Numeric keys are appended only if the value doesn't already exist in the array.
 *
 * @param array ...$array The arrays to be merged.
 * 
 * @return array Return the merged result array.
 */
function array_merge_recursive_replace(array ...$array): array {
    $merged = \array_shift($array);
    
    foreach ($array as $params) {
        foreach ($params as $key => $value) {
            if (\is_numeric($key) && !\in_array($value, $merged, true)) {
                $merged[] = is_array($value) 
                    ? array_merge_recursive_replace($merged[$key] ?? [], $value) 
                    : $value;
            } else {
                $merged[$key] = (isset($merged[$key]) && \is_array($value) && \is_array($merged[$key])) 
                    ? array_merge_recursive_replace($merged[$key], $value) 
                    : $value;
            }
        }
    }

    return $merged;
}

/**
 * Merges two arrays, treating the first array as the default configuration and the second as new or override values.
 * 
 * If both arrays contain nested arrays, they are merged recursively, 
 * ensuring that default values are preserved and new values are added where applicable.
 *
 * @param array $default The default options array.
 * @param array $new The new options array to merge.
 * 
 * @return array Return the merged options array with defaults preserved.
 */
function array_extend_default(array $default, array $new): array 
{
    $result = $default; 

    foreach ($new as $key => $value) {
        // If the key does not exist in the default, add it
        if (!\array_key_exists($key, $result)) {
            $result[$key] = $value;
        } elseif (\is_array($result[$key]) && \is_array($value)) {
            // If both values are arrays, merge them recursively
            $result[$key] = array_extend_default($result[$key], $value);
        }
    }

    return $result;
}

/**
 * Merges a response into the provided results variable while optionally preserving the structure of nested arrays.
 * 
 * @param mixed &$results The results variable to which the response will be merged or appended.
 *                       This variable is passed by reference and may be modified.
 * @param mixed $response The response variable to merge with results. It can be an array, string, 
 *                       or other types.
 * @param bool $preserveNested Optional. Determines whether to preserve the nested structure 
 *                               of arrays when merging (default: true).
 *
 * @return void
 * @since 3.3.4
 * @see https://luminova.ng/docs/3.3.0/global/functions#lmv-docs-array-merge-result
 */
function array_merge_result(mixed &$results, mixed $response, bool $preserveNested = true): void
{
    if ($results === null || $results === []) {
        $results = $response;
        return;
    }
    
    if (\is_array($results)) {
        if (!$preserveNested && \is_array($response)) {
            $results = \array_merge($results, $response);
            return;
        }

        $results[] = $response;
        return;
    } 
    
    if (\is_string($results)) {
        $results = \is_array($response) 
            ? \array_merge([$results], $preserveNested ? [$response] : $response) 
            : [$results, $response];

        return;
    }

    $results = [$results];

    if (!$preserveNested && \is_array($response)) {
        $results = \array_merge($results, $response);
        return;
    }

    $results[] = $response;
}

/**
 * Sets the HTTP response status code and sends appropriate headers.
 * 
 * This function sets the HTTP status code and sends the corresponding status message header.
 * If the status code is not predefined, it returns `false`. For predefined status codes, it sends headers including the status
 * message. The function determines the HTTP protocol version based on the server's protocol.
 *
 * @param int $status The HTTP status code to set (e.g., 200, 404).
 * 
 * @return bool Returns true if the status code is found in the predefined list and headers are set, otherwise false.
 */
function http_status_header(int $status): bool
{
    $message = HttpCode::$codes[$status] ?? null;

    // Check if the status code is in the predefined list
    if ($message === null) {
        return false;
    }

    // Determine the protocol version (1.0 or 1.1) based on the server's protocol
    $protocol = ($_SERVER['SERVER_PROTOCOL'] ?? '1.0');
    $protocol = ($protocol !== '1.0')
        ? (\strcasecmp($protocol, 'HTTP/1.0') ? '1.1' : '1.0') 
        : $protocol;

    // Send the HTTP header with the specified status and message
    @\header("HTTP/$protocol $status {$message}");

    // Send the 'Status' header, which is often used for compatibility with older clients
    @\header("Status: $status {$message}", true, $status);

    // Set the status code as redirect status
    $_SERVER["REDIRECT_STATUS"] = $status;
    
    return true;
}

/**
 * Checks if a function exists and caches the result to avoid repeated checks.
 * 
 * This function uses a static cache to store whether a function exists or not.
 * If the function's existence has been checked before, the cached result is returned.
 * Otherwise, it checks the function's existence using `function_exists()` and caches the result,
 * improving performance by avoiding repeated function existence checks.
 *
 * @param string $function The name of the function to check for existence.
 * 
 * @return bool Returns true if the function exists, false otherwise.
 */
function function_exists_cached(string $function): bool
{
    static $functions = [];
    $func = $functions[$function] ?? null;

    if($func === null){
        $functions[$function] = (\function_exists($function) ? 't' : 'f');
        return $functions[$function] === 't';
    }

    return $func === 't';
}

/**
 * Checks if a class exists and caches the result for improved performance.
 * 
 * This function maintains a static cache to remember whether a class has been previously checked.
 * It first checks the cache to see if the class's existence was determined before. If not, it uses
 * `class_exists()` to perform the check and then stores the result in the cache. This avoids redundant
 * checks and speeds up subsequent requests.
 *
 * @param string $class The name of the class to check for existence.
 * @param bool $autoload Optional. Whether to check for class existence with autoload (default: true).
 * 
 * @return bool Returns true if the class exists, false otherwise.
 */
function class_exists_cached(string $class, bool $autoload = true): bool
{
    static $classes = [];
    $cached = $classes[$class] ?? null;

    if($cached === null){
        $classes[$class] = (\class_exists($class, $autoload) ? 't' : 'f');
        return $classes[$class] === 't';
    }

    return $cached === 't';
}