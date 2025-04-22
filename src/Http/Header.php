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
namespace Luminova\Http;

use \Luminova\Application\Foundation;
use \Luminova\Interface\LazyInterface;
use \Luminova\Functions\Func;
use \App\Config\Apis;
use \Countable;

class Header implements LazyInterface, Countable
{
    /**
     * Content types for view rendering.
     * 
     * @var array VIEW_CONTENT_TYPES
     */
    private const VIEW_CONTENT_TYPES = [
        'html'   => ['text/html', 'application/xhtml+xml'],
        'text'   => ['text/plain'],
        'js'     => ['application/javascript', 'application/x-javascript', 'text/javascript'],
        'css'    => ['text/css'],
        'json'   => ['application/json', 'application/x-json'],
        'jsonld' => ['application/ld+json'],
        'xml'    => ['application/xml', 'text/xml', 'application/x-xml'],
        'rdf'    => ['application/rdf+xml'],
        'atom'   => ['application/atom+xml'],
        'rss'    => ['application/rss+xml'],
        'form'   => ['application/x-www-form-urlencoded', 'multipart/form-data']
    ];

    /**
     * Header variables.
     * 
     * @var array<string,mixed> $variables
     */
    protected static array $variables = [];

    /**
     * REST API configuration.
     * 
     * @var Apis $config
     */
    private static ?Apis $config = null;

    /**
     * Initializes the header constructor.
     * 
     * @param array<string,mixed>|null $variables The header variables key-pair.
     */
    public function __construct(?array $variables = null)
    {
        self::$variables = $variables 
            ? array_replace(self::$variables, self::getFromGlobal($variables))
            : self::getHeaders();
    }

    /**
     * Get header variables.
     *
     * @param string|null $name Optional name of the server variable.
     * @param mixed $default Default value for the server key.
     *
     * @return mixed|array|string|null The value of the specified server variable, or all server variables if $name is null.
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        return ($name === null || $name === '') 
            ? self::$variables 
            : ($this->has($name) ? self::$variables[$name] : $default);
    }

    /**
     * Set server variable.
     * 
     * @param string $key The server variable key to set.
     * @param mixed $value The server variable value.
     * 
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        self::$variables[$key] = $value;
    }

    /**
     * Removes a server variable by key
     * 
     * @param string $key The key to remove.
     * 
     * @return void 
     */
    public function remove(string $key): void
    {
        unset(self::$variables[$key]);
    }

    /**
     * Check if request header key exist.
     * 
     * @param string $key Header key to check.
     * 
     * @return bool Return true if key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, self::$variables);
    }

    /**
     * Check if request header key exist and has a valid value.
     * 
     * @param string $key Header key to check.
     * @param string|null $server Optionally check the PHP global server variable.
     * 
     * @return bool Return true if key exists, false otherwise.
     */
    public function exist(string $key, ?string $server = null): bool
    {
        if(isset(self::$variables[$key])){
            return true;
        }

        return $server && isset($_SERVER[$server]);
    }

    /**
     * Get the total number of server variables.
     * 
     * @return int Return total number of server variables.
     */
    public function count(): int
    {
        return count(self::$variables);
    }

    /**
     * Retrieve a server variable by key.
     *
     * @param string $key The key to retrieve from internal variables or Apache request headers.
     * @param string|null $server Optional alternative key to access the PHP server variable.
     *
     * @return mixed Return the server variable value, or null if not set.
     * @internal
     */
    public static function server(string $key, ?string $server = null): mixed
    {
        return self::$variables[$key] 
            ?? $_SERVER[$server ?? $key] 
            ?? null;
    }

    /**
     * Extract all request headers from apache_request_headers or _SERVER variables.
     *
     * @return array<string,string> Return the request headers.
     */
    public static function getHeaders(): array
    {
        if (function_exists('apache_request_headers') && ($headers = apache_request_headers()) !== false) {
            return array_replace(self::$variables, $headers);
        }

        // If PHP function apache_request_headers() is not available or went wrong: manually extract headers
        return array_replace(self::$variables, self::getFromGlobal());
    }

    /**
     * Parse _SERVER variables and extract headers from it.
     *
     * @param array<string,mixed> $server An optional custom server variable.
     * 
     * @return array<string,string> Return the request headers.
     */
    public static function getFromGlobal(?array $server = null): array
    {
        $headers = [];
        foreach ($server ?? $_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_') || $name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH') {
                $header = str_replace(
                    [' ', 'Http'], 
                    ['-', 'HTTP'], 
                    ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))
                );
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Retrieves specific HTTP `Content-Type`, `Content-Encoding` and `Content-Length` headers from sent headers.
     * 
     * @return array Return n associative array containing 'Content-Type', 
     *              'Content-Length', and 'Content-Encoding' headers.
     * @internal
     */
    public static function getSentHeaders(): array
    {
        $headers = headers_list();
        $info = [];

        foreach ($headers as $header) {
            $header = trim($header);

            if (!str_starts_with($header, 'Content-')) {
                continue;
            }

            [$name, $value] = explode(':', $header, 2);
            $key = trim($name);

            if ($key === 'Content-Type' || $key === 'Content-Encoding') {
                $info[$key] = trim($value);
            } elseif($key === 'Content-Length') {
                $info[$key] = (int) trim($value);
            }
        }

        return $info;
    }

    /**
     * Get default system headers.
     *
     * @return array<string,string> The system headers.
     * @ignore
     */
    public static function getDefault(): array
    {
        return [
            'Content-Type'  => 'text/html',
            'Cache-Control' => env('default.cache.control', 'no-store, max-age=0, no-cache'),
            'Content-Language' => env('app.locale', 'en'), 
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'X-Firefox-Spdy' => 'h2',
            'Vary' => 'Accept-Encoding',
            'Connection' => ((self::server('Connection', 'HTTP_CONNECTION') ?? 'keep-alive') === 'keep-alive' 
                ? 'keep-alive' 
                : 'close'
            ),
            'X-Powered-By' => Foundation::copyright()
        ];
    }

    /**
     * Sends HTTP headers to disable caching and optionally set content type and retry behavior.
     *
     * @param int $status HTTP status code to send (default: 200).
     * @param string|bool|null $contentType Optional content type (default: 'text/html').
     * @param string|int|null $retry Optional value for Retry-After header.
     *
     * @return void
     * @internal Used by router and template rendering to prevent caching.
     */
    public static function headerNoCache(
        int $status = 200, 
        string|bool|null $contentType = null, 
        string|int|null $retry = null
    ): void 
    {
        $headers = [
            'X-Powered-By' => Foundation::copyright(),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Expires' => '0',
            'Content-Type' => $contentType ?? 'text/html'
        ];

        if($retry !== null){
            $headers['Retry-After'] = $retry;
        }

        self::validate($headers, $status);
    }

    /**
     * Validate, modifies and send HTTP headers to client, ensuring necessary headers are set.
     *
     * @param array $headers An associative array of headers to send.
     * @param int|null $status HTTP response code (default: NULL)
     * 
     * @return void
     * @internal
     */
    public static function validate(array $headers, ?int $status = null): void
    {
        if (headers_sent()) {
            return;
        }

        if (isset($headers['default_headers'])) {
            $headers = array_replace(self::getDefault(), $headers);
        }

        if(self::isValidRestFullHeaders($headers)){
            if ($status !== null) {
                self::sendStatus($status);
            }

            self::send($headers, false, true);
        }
    }

    /**
     * Parse and validate REST API headers.
     * 
     * @param array<string,mixed> &$headers Headers passed by reference.
     * 
     * @return bool Return true if request origin is valid, false otherwise.
     */
    public static function isValidRestFullHeaders(array &$headers): bool 
    {
        if (!Foundation::isApiPrefix()) {
            return true;
        }

        self::$config ??= new Apis();
        $origin = self::server('HTTP_ORIGIN');

        if(!$origin && self::$config->forbidEmptyOrigin){
            self::terminateRequest(400, 'Invalid request: missing origin.', 'forbidEmptyOrigin');
            return false;
        }

        if ($origin && self::$config->allowOrigins) {
            $allowed = self::isAllowedOrigin($origin);
            
            if (!$allowed) {
                self::terminateRequest(403, 'Access denied: origin not allowed.', 'allowOrigins');
                return false;
            }
    
            $headers['Access-Control-Allow-Origin'] = $allowed;
        }
        
        if (self::$config->allowHeaders !== []) {
            $allowed = self::isAllowedHeaders();

            if ($allowed !== true) {
                self::terminateRequest(400, "Invalid header: {$allowed} found in the request.", 'allowHeaders');
                return false;
            }

            $headers['Access-Control-Allow-Headers'] = implode(', ', self::$config->allowHeaders);
        }

        $headers['Access-Control-Allow-Credentials'] = (self::$config->allowCredentials ? 'true' : 'false');
        return true;
    }

    /**
     * Sends HTTP headers to the client.
     *
     * @param array<string,mixed> $headers An associative array of headers to send.
     * @param bool $ifNotSent Weather to send headers if headers is not already sent (default: true).
     * @param bool $charset Weather to append default charset from env to `Content-Type` 
     *              if it doesn't contain it (default: false).
     * 
     * @return void
     */
    public static function send(array $headers, bool $ifNotSent = true, bool $charset = false): void 
    {
        if ($ifNotSent && headers_sent()) {
            return;
        }

        $xPowered = env('x.powered', true);

        foreach ($headers as $header => $value) {
            if (
                !$header || 
                $value === '' ||
                ($header === 'X-Powered-By' && !$xPowered) ||
                ($header === 'default_headers' || ($header === 'Content-Encoding' && $value === false))
            ) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $val) {
                    header("{$header}: {$val}");
                }
            }else{
                $value = ($charset && $header === 'Content-Type' && !str_contains($value, 'charset')) 
                    ? "{$value}; charset=" . env('app.charset', 'utf-8') 
                    : $value;
                header("{$header}: {$value}");
            }
        }
    }

    /**
     * Get the content type based on file extension and charset.
     *
     * @param string $extension The file extension.
     * @param string $charset The character set.
     *
     * @return string Return the content type and optional charset.
     */
    public static function getContentType(string $extension = 'html', ?string $charset = null): string
    {
        $charset ??= env('app.charset', 'utf-8');

        return self::getContentTypes($extension, 0) . ($charset === '' ?: '; charset=' . $charset);
    }

    /**
     * Get content types by name 
     * 
     * @param string $type Type of content types to retrieve.
     * @param int|null $index The index of content type to return.
     * 
     * @return array<int,array>|string|null Return array, string of content types or null if not found.
     */
    public static function getContentTypes(string $type, int|null $index = 0): array|string|null
    {
        $type = ($type === 'txt') ? 'text' : $type;
        return ($index === null) 
            ? (self::VIEW_CONTENT_TYPES[$type] ?? null)
            : (self::VIEW_CONTENT_TYPES[$type][$index] ?? 'application/octet-stream');
    }

    /**
     * Sends HTTP response status code if it is valid.
     *
     * @param int $code The HTTP response status code to send.
     * 
     * @return bool Return true if status code is valid and set, false otherwise.
     */
    public static function sendStatus(int $code): bool 
    {
        if ($code >= 100 && $code < 600) {
            http_response_code($code);
            $_SERVER["REDIRECT_STATUS"] = $code;
            self::$variables['Redirect-Status'] = $code;
            return true;
        }

        return false;
    }

    /**
     * Initializes the output buffer with the appropriate content encoding handler.
     *
     * This method detects supported content encodings (such as `gzip` or `deflate`)
     * from client headers and applies the corresponding output handler for compression,
     * if enabled and supported. Falls back to a custom or default handler when no match is found.
     * 
     * If output buffering is already active, it will not be restarted.
     * 
     * @param bool $clearIfSet Weather to clear output buffers if already set (default: false).
     *
     * @return bool Returns true if output buffering is successfully started; false otherwise.
     * @internal This method is intended for internal framework use only.
     */
    public static function setOutputHandler(bool $clearIfSet = false): bool
    {
        if (ob_get_level() > 0) {
            if(!$clearIfSet){
                return false;
            }

            self::clearOutputBuffers();
        }

        if (!env('enable.encoding', true)) {
            return ob_start();
        }

        $handler = $_SERVER['HTTP_ACCEPT_ENCODING'] 
            ?? $_SERVER['HTTP_CONTENT_ENCODING'] 
            ?? null;

        if ($handler) {
            if (str_contains($handler, 'gzip')) {
                if (ini_get('zlib.output_compression') != '1') {
                    return ob_start('ob_gzhandler');
                }

                return ob_start();
            }

            if (str_contains($handler, 'deflate')) {
                if (function_exists('ini_set')) {
                    ini_set('zlib.output_compression', 'On');
                    ini_set('zlib.output_handler', 'deflate');
                }

                return ob_start();
            }
        }

        return ob_start(env('script.output.handler', null) ?: null);
    }

    /**
     * Clears output buffers based on the selected action.
     *
     * Supported actions:
     * - 'auto' (default): Clears all buffers if more than one is active; otherwise, clears only the top buffer.
     * - 'all': Clears all active output buffers.
     * - 'top': Clears only the top buffer.
     *
     * @param string $action Determines how buffers are cleared: 'auto', 'all', or 'top'.
     * 
     * @return bool Returns true if at least one buffer was cleared successfully, false otherwise.
     */
    public static function clearOutputBuffers(string $action = 'auto'): bool
    {
        $level = ob_get_level();

        if ($level === 0) {
            return false;
        }

        if (($action === 'auto' || $action === 'all') && $level > 1) {
            $cleared = false;

            while (ob_get_level() > 0) {
                $cleared = ob_end_clean() || $cleared;
            }
            return $cleared;
        }

        return ob_end_clean();
    }

    /**
     * Get the allowed origin based on the config.
     * 
     * @param string $origin The origin to check.
     * 
     * @return string|null Return the allowed origin or null if not allowed.
     */
    private static function isAllowedOrigin(string $origin): ?string
    {
        $accept = self::$config->allowOrigins;

        if ($accept === '*' || $accept === 'null') {
            return '*';
        }

        if ($accept === $origin) {
            return $origin;
        }

        foreach ([$origin, Func::mainDomain($origin)] as $from) {
            if ($accept === $from || in_array($from, (array) $accept)) {
                return $from;
            }
        }

        return null;
    }

    /**
     * Validates request headers against allowed headers.
     *
     * @return string|true Return true if all headers are valid, false otherwise.
     */
    private static function isAllowedHeaders(): string|bool
    {
        foreach (self::getHeaders() as $name => $value) {
            if (!in_array($name, self::$config->allowHeaders)) {
                return $name;
            }
        }

        return true;
    }

    /**
     * Terminates the request by sending the status and exiting.
     *
     * @param int $status HTTP status code.
     * @param string $message Termination message.
     * @param string $var The configuration variable.
     *
     * @return void
     */
    private static function terminateRequest(int $status, string $message, string $var): void
    {
        self::sendStatus($status);

        if (!PRODUCTION) {
            $message .= "\nCaused by API configuration in '/app/Config/Apis.php' at \App\Config\Apis::\${$var}.";
        }

        exit($message);
    }
}