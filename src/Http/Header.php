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

use \App\Config\Apis;
use \Luminova\Luminova;
use \Luminova\Common\Helpers;
use \Luminova\Interface\LazyObjectInterface;
use \Countable;

class Header implements LazyObjectInterface, Countable
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
     * Proxy headers.
     * 
     * @var array $proxyHeaders
     */
    private static array $proxyHeaders = [
        'X-Forwarded-For'       => 'HTTP_X_FORWARDED_FOR',
        'X-Forwarded-For-Ip'    => 'HTTP_FORWARDED_FOR_IP',
        'X-Real-Ip'             => 'HTTP_X_REAL_IP',
        'Via'                   => 'HTTP_VIA',
        'Forwarded'             => 'HTTP_FORWARDED',
        'Proxy-Connection'      => 'HTTP_PROXY_CONNECTION'
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
            'X-Powered-By' => Luminova::copyright()
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
            'X-Powered-By' => Luminova::copyright(),
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
     * Validates and sends HTTP headers to the client.
     *
     * - Replaces headers with defaults if `X-System-Default-Headers` key is present.
     * - Sends status code if provided.
     * - Ensures required RESTful headers are present before sending.
     * - Always append charset if missing.
     *
     * @param array<string,mixed> $headers Associative array of headers to send.
     * @param int|null $status Optional HTTP response code to send.
     * @param bool $ifNotSent Only send headers if no headers have been sent yet (default: true).
     * 
     * @return void
     * @internal
     */
    public static function validate(array $headers, ?int $status = null, bool $ifNotSent = true): void
    {
        if ($ifNotSent && headers_sent()) {
            return;
        }

        if (!self::isValidRestFullHeaders($headers)) {
            return;
        }

        if ($status !== null) {
            if ($status === 204 || $status === 304) {
                unset($headers['Content-Type'], $headers['Content-Length']);
            }

            self::sendStatus($status);
        }

        self::send($headers, false, true);
    }

    /**
     * Processes response headers and returns them normalized (without sending).
     *
     * - Replaces headers with defaults if `X-System-Default-Headers` key is present.
     * - Ensures required RESTful headers are present.
     * - Returns an empty array if headers are already complete/valid.
     * - Always append charset if missing.
     *
     * @param array<string,mixed> $headers Associative array of headers to process.
     * 
     * @return array<string,mixed>|null Normalized headers ready to send, or an empty array if no changes needed.
     * @internal Response class
     */
    public static function response(array $headers): ?array
    {
        if (!self::isValidRestFullHeaders($headers)) {
            return [];
        }

        return self::parse($headers, true, false);
    }

    /**
     * Determine of a request if from proxy. 
     * 
     * This method check command headers if present then request is considered likely a proxy.
     * 
     * @return bool Return true if found matched header, false otherwise.
     */
    public function isProxy(): bool
    {
        foreach (self::$proxyHeaders as $head => $server) {
            if ($this->exist($head, $server)){
                return true;
            }
        }
        
        return false;
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
        if (!Luminova::isApiPrefix()) {
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
     * @param array<string,mixed> $headers Associative array of headers to send.
     * @param bool $ifNotSent Only send headers if no headers have been sent yet (default: true).
     * @param bool $charset Append the default charset from env to `Content-Type` 
     *                      if missing (default: false).
     * 
     * @return void
     */
    public static function send(array $headers, bool $ifNotSent = true, bool $charset = false): void 
    {
        if ($ifNotSent && headers_sent()) {
            return;
        }

        self::parse($headers, $charset);
    }

    /**
     * Normalize and optionally send HTTP headers to the client.
     *
     * - Removes invalid or empty headers.
     * - Conditionally appends charset to `Content-Type`.
     * - Can either send headers immediately or return them as an array.
     *
     * @param array<string,mixed> $headers Associative array of headers to process.
     * @param bool $withCharset Append the default charset from env to `Content-Type` if missing (default: false).
     * @param bool $isSend If true, headers are sent using `header()`. 
     *                     If false, an array of normalized headers is returned. (default: true)
     * 
     * @return array<string,mixed> Processed headers when `$isSend` is false, otherwise an empty array.
     */
    private static function parse(array $headers, bool $withCharset = false, bool $isSend = true): array 
    {
        $normalized = [];
        $xPowered = env('x.powered', true);
        $charset = env('app.charset', 'utf-8');

        if (isset($headers['X-System-Default-Headers'])) {
            $headers = array_replace(self::getDefault(), $headers);
        }elseif($xPowered){
            $headers['X-Powered-By'] = Luminova::copyright();
        }

        foreach ($headers as $header => $value) {
            if (
                !$header ||
                $value === '' ||
                ($header === 'X-Powered-By' && !$xPowered) ||
                ($header === 'X-System-Default-Headers') ||
                ($header === 'Content-Encoding' && $value === false)
            ) {
                continue;
            }

            $values = [];
            foreach ((array) $value as $val) {
                $finalVal = ($withCharset && $header === 'Content-Type' && !str_contains($val, 'charset'))
                    ? "{$val}; charset={$charset}"
                    : $val;

                if ($isSend) {
                    header("{$header}: {$finalVal}");
                } else {
                    $values[] = $finalVal;
                }
            }

            if (!$isSend && $values !== []) {
                $normalized[$header] = implode(', ', $values);
            }
        }

        return $normalized;
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

        return self::getContentTypes($extension, 0) . (($charset === '') ? '' : '; charset=' . $charset);
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
     * Initializes the output buffer with the appropriate content-encoding handler.
     *
     * This method is a wrapper around `ob_start()`. It detects supported encodings
     * (such as `gzip` or `deflate`) from the client’s request headers and applies
     * the proper output handler when compression is enabled and supported. If no
     * matching encoding is found, it falls back to a custom or default handler.
     *
     * If output buffering is already active, it will not be restarted.
     *
     * @param bool $clearIfSet Whether to clear existing output buffers when one is already active (default: false).
     * @param bool $withHandler Whether to apply an output handler (default: true).
     *
     * @return bool Returns true if a new output buffer is started, false otherwise.
     * @internal For internal framework use only.
     */
    public static function setOutputHandler(bool $clearIfSet = false, bool $withHandler = true): bool
    {
        if(!$clearIfSet && ob_get_level() > 0){
            return false;
        }

        if($clearIfSet){
            self::clearOutputBuffers();
        }

        if (!$withHandler || !env('enable.encoding', true)) {
            return ob_start();
        }

        $handler = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? null;

        if ($handler) {
            if (str_contains($handler, 'gzip')) {
                if (ini_get('zlib.output_compression') !== '1') {
                    return ob_start('ob_gzhandler');
                }

                return ob_start();
            }

            if (str_contains($handler, 'deflate')) {
                if (function_exists('ini_set')) {
                    ini_set('zlib.output_compression', 'On');
                    //ini_set('zlib.output_handler', 'deflate');
                }

                return ob_start();
            }
        }

        return ob_start(env('script.output.handler', null) ?: null);
    }

    /**
     * Clear PHP output buffers using one of three modes.
     *
     * **Modes:**
     * - auto (default): If multiple buffers exist, clear all except the base buffer.
     *                   If only one exists, clear only the top buffer.
     * - all:  Clear every active buffer down to level 0 or a specified limit.
     * - top:  Clear only the top-most buffer.
     *
     * @param string $mode Determines how buffers are cleared, (e.g, `auto`, `all`, or `top`).
     * @param int $limit Minimum buffer level to stop at (use 0 to clear everything).
     *
     * @return bool Returns true if any buffer was cleared, otherwise false.
     */
    public static function clearOutputBuffers(string $mode = 'auto', int $limit = 0): bool
    {
        $level = ob_get_level();

        if ($level === 0) {
            return false;
        }

        if ($mode === 'top') {
            return ob_end_clean();
        }

        if ($mode === 'all' || ($mode === 'auto' && $level > 1)) {
            $cleared = false;

            while (ob_get_level() > $limit) {
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

        foreach ([$origin, Helpers::mainDomain($origin)] as $from) {
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