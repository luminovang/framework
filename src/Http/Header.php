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

use \Countable;
use \Luminova\Luminova;
use \App\Config\Security;
use \Luminova\Logger\Logger;
use \Luminova\Http\HttpStatus;
use \Luminova\Utility\Helpers;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\LazyObjectInterface;
use \Luminova\Exceptions\InvalidArgumentException;

class Header implements LazyObjectInterface, Countable
{
    /**
     * Check apache_request_headers function.
     * 
     * @var $isApache
     */
    private static ?bool $isApache = null;

    /**
     * Request security configuration.
     * 
     * @var Security $security
     */
    private static ?Security $security = null;

    /**
     * Initializes the header constructor.
     * 
     * @param array<string,mixed>|null $variables The header variables key-pair.
     * @param Security<Luminova\Base\Configuration>|null $security Optional request security configuration object.
     */
    public function __construct(protected ?array $variables = null, ?Security $security = null)
    {
        if($security instanceof Security){
            self::$security = $security;
        }

        $this->variables = ($this->variables !== null)
            ? self::getFromGlobal($this->variables) 
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
        return !$name
            ? $this->variables 
            : ($this->has($name) ? $this->variables[$name] : $default);
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
        $this->variables[$key] = $value;
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
        unset($this->variables[$key]);
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
        return array_key_exists($key, $this->variables);
    }

    /**
     * Get the total number of server variables.
     * 
     * @return int Return total number of server variables.
     */
    public function count(): int
    {
        return count($this->variables);
    }

    /**
     * Extract all request headers from apache_request_headers or _SERVER variables.
     *
     * @return array<string,string> Return the request headers.
     */
    public static function getHeaders(): array
    {
        self::$isApache ??= function_exists('apache_request_headers');

        if (!self::$isApache) {
            return self::getFromGlobal();
        }

        return apache_request_headers() ?: self::getFromGlobal();
    }

    /**
     * Parse _SERVER variables and extract headers from it.
     *
     * @param array<string,mixed> $server An optional custom server variable.
     * 
     * @return array<string,string> Return the parsed headers.
     */
    public static function getFromGlobal(?array $server = null): array
    {
        $server ??= $_SERVER;
        $headers = [];
        
        foreach ($server as $name => $value) {
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
     * Get default response headers.
     *
     * @return array<string,string> Return the defined default headers.
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
            'Connection' => 'keep-alive',
            'X-Powered-By' => Luminova::copyright()
        ];
    }

    /**
     * Send no-cache HTTP headers.
     * 
     * This method sends HTTP headers to disable caching and optionally set content type and retry behavior.
     *
     * @param int $status HTTP status code to send (default: 200).
     * @param string|bool|null $contentType Optional content type (default: 'text/html').
     * @param string|int|null $retry Optional value for Retry-After header.
     *
     * @return void
     * > Used by router and template rendering to prevent caching.
     */
    public static function sendNoCacheHeaders(
        int $status = 200, 
        string|bool|null $contentType = null, 
        string|int|null $retry = null
    ): void 
    {
        $headers = [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Expires' => '0',
            'Content-Type' => $contentType ?? 'text/html'
        ];

        if($retry !== null){
            $headers['Retry-After'] = $retry;
        }

        self::send($headers, true, false, $status, false);
    }

    /**
     * Normalize headers without sending them.
     *
     * Behavior:
     * - Applies system default headers when `X-System-Default-Headers` is present.
     * - Optionally validates REST / API headers.
     * - Appends charset to Content-Type when missing.
     * - Returns an empty array when headers are already valid or unchanged.
     *
     * @param array<string,mixed> $headers Headers to normalize.
     * @param bool $validateRequestHeaders Enable REST request header validation (default: false).
     *
     * @return array<string,mixed> Returns the normalized headers, or empty array if no changes are required.
     */
    public static function parse(array $headers, bool $validateRequestHeaders = false): array
    {
        if ($validateRequestHeaders && !self::isValidHeaders($headers, terminateOnFailure: false)) {
            return [];
        }

        return self::parseHeaders($headers, true, false);
    }

    /**
     * Send HTTP headers to the client.
     *
     * Handles safe header dispatch with optional REST validation and CORS checks.
     * Prevents duplicate header output and provides clear diagnostics when headers
     * were already sent.
     *
     * Behavior:
     * - Optionally aborts if headers were already sent.
     * - Sends HTTP status code when provided.
     * - Removes entity headers for 204 / 304 responses.
     * - Appends charset to Content-Type when missing (optional).
     * - Optionally validates headers for HTTP and API requests.
     *
     * @param array<string,string|int|float> $headers Headers to send.
     * @param bool $ifNotSent Skip sending if headers are already sent (default: true).
     * @param bool $charset Append charset to Content-Type if missing (default: false).
     * @param int|null $status HTTP status code to send (default: null).
     * @param bool|null $validateRequestHeaders Enable REST header validation (auto-detect if null).
     *
     * @return void
     *
     * @throws RuntimeException When headers are already sent and debugging is enabled.
     * @see self::parse() For header normalization without sending.
     */
    public static function send(
        array $headers,
        bool $ifNotSent = true, 
        bool $charset = false,
        ?int $status = null,
        ?bool $validateRequestHeaders = null
    ): void 
    {
        $file = null;
        $line = null;

        if (headers_sent($file, $line)) {
            if ($ifNotSent) {
                return;
            }

            $message = sprintf(
                'Headers already sent in %s on line %d. Cannot send additional headers. %s',
                $file,
                $line,
                'Set $ifNotSent to true to prevent this error.'
            );

            if (PRODUCTION) {
                Logger::error($message);
                return;
            }

            if (!env('debug.display.errors', false)) {
                throw new RuntimeException($message);
            }

            echo $message;
            return;
        }
        
        self::initRequestSecurityConfig();
        $validateRequestHeaders ??= Luminova::isApiPrefix();

        if (
            (self::$security->enforceApiSecurityOnHttp || $validateRequestHeaders) && 
            !self::isValidHeaders($headers)
        ) {
            return;
        }

        if ($status !== null) {
            if (in_array($status, [204, 304], true)) {
                // RFC-compliant: no entity headers for responses without body
                unset($headers['Content-Type'], $headers['Content-Length']);
            }

            self::sendStatus($status);
        }

        self::parseHeaders($headers, $charset);
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
        if ($code < 100 || $code > 599) {
            return false;
        }

        http_response_code($code);
        $_SERVER['REDIRECT_STATUS'] = $code;
        return true;
    }

    /**
     * Starts an output buffer with the appropriate content-encoding handler.
     *
     * This method wraps `ob_start()` and chooses the output handler based on configuration:
     * - If a user-defined handler (`script.output.handler`) is set, it is used.
     * - If global zlib compression is active, a normal buffer is started.
     * - Otherwise, `ob_gzhandler` is applied for gzip compression if supported.
     *
     * Existing buffers can optionally be cleared before starting a new one.
     *
     * @param bool $clearIfSet Whether to clear existing buffers if one is active (default: false).
     * @param bool $withHandler Whether to apply a compression/encoding handler (default: true).
     *
     * @return bool Return true if a new buffer is started, 
     *      false if buffering was already active or headers sent.
     * @throws InvalidArgumentException If a user-defined handler is not callable.
     */
    public static function setOutputHandler(bool $clearIfSet = false, bool $withHandler = true): bool
    {
        if (headers_sent()) {
            return false;
        }

        if(ob_get_level() > 0){
            if($clearIfSet){
                return false;
            }

            self::clearOutputBuffers('all');
        }

        if (!$withHandler || !env('enable.encoding', false)) {
            return ob_start();
        }

        $handler = env('script.output.handler', null);

        if($handler){
            if (!is_callable($handler)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid output handler "%s". Handler "%s" must be callable.',
                    $handler,
                    'script.output.handler'
                ));
            }

            return ob_start($handler);
        }

        if ((int) ini_get('zlib.output_compression') === 1) {
            return ob_start();
        }

        return ob_start('ob_gzhandler');
    }

    /**
     * Clears or flushes PHP output buffers with flexible modes.
     *
     * Modes:
     * - auto (default): Clears all but the base buffer if multiple exist; otherwise clears the top buffer.
     * - all: Clears every active buffer down to the specified minimum level (default 0 for all).
     * - top: Clears only the top-most buffer.
     * - flush: Flushes the top-most buffer without clearing it.
     *
     * This method safely handles CLI environments by doing nothing in CLI or PHPDBG.
     *
     * @param string $mode How to handle buffers: 'auto', 'all', 'top', or 'flush'.
     * @param int $limit Minimum buffer level to preserve (0 clears everything).
     *
     * @return bool Return true if any buffer was cleared or flushed; false if no buffers existed.
     */
    public static function clearOutputBuffers(string $mode = 'auto', int $limit = 0): bool
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return false;
        }

        $level = ob_get_level();

        if ($level === 0) {
            return false;
        }

        $cleared = false;

        switch ($mode) {
            case 'top':
                return (bool) @ob_end_clean();

            case 'flush':
                $cleared = (bool) @ob_flush();
                flush();
                return $cleared;

            case 'all':
            case 'auto':
                $stopAt = ($mode === 'all') ? $limit : max($limit, 1);

                while (ob_get_level() > $stopAt) {
                    $cleared = (bool) @ob_end_clean() || $cleared;
                }

                return $cleared;

            default:
                return (bool) @ob_end_clean();
        }
    }

    /**
     * Determine whether a given origin is allowed based on configuration.
     *
     * @param string|null $origin Optional origin to check. Defaults to `$_SERVER['HTTP_ORIGIN']`.
     *
     * @return bool Returns true if the origin is allowed, false otherwise.
     */
    public static function isAllowedOrigin(?string $origin = null): bool
    {
        return self::getAllowedOrigin($origin) !== null;
    }
    
    /**
     * Retrieve the allowed origin based on configuration.
     *
     * - Returns `*` if all origins are allowed.
     * - Returns the matching origin if it is explicitly allowed.
     * - Returns `null` if the origin is forbidden or missing when `forbidEmptyOrigin` is enabled.
     *
     * @param string|null $origin Optional origin to check. Defaults to `$_SERVER['HTTP_ORIGIN']`.
     *
     * @return string|null Returns the allowed origin, `*` for all, or `null` if forbidden.
     */
    public static function getAllowedOrigin(?string $origin = null): ?string
    {
        $origin ??= $_SERVER['HTTP_ORIGIN'] ?? '';
        self::initRequestSecurityConfig();

        if(!$origin && self::$security->forbidEmptyOrigin){
            return null;
        }

        $accept = self::$security->allowOrigins;

        if ($accept === [] || $accept === '*' || $accept === 'null') {
            return '*';
        }

        if ($accept === $origin) {
            return $origin;
        }

        if($origin){
            $accept = (array) $accept;

            foreach ([$origin, Helpers::mainDomain($origin)] as $from) {
                if ($accept === $from || in_array($from, $accept, true)) {
                    return $from;
                }
            }
        }

        return null;
    }

    /**
     * Validates request headers against allowed headers.
     * 
     * @param array<string,string|int|float>|null $headers The request headers to validate.
     * @param string|null $match The matched header that is not allowed.
     *
     * @return bool Return true if all headers are valid, false otherwise.
     */
    public static function isAllowedHeaders(?array $headers = null, ?string &$match = null): bool
    {
        self::initRequestSecurityConfig();
        static $allows = null;

        if(self::$security->allowHeaders === []){
            return true;
        }

        if($allows === null){
            $allows = array_map('strtolower', self::$security->allowHeaders);
        }

        foreach ($headers ?? self::getHeaders() as $name => $value) {
            if (!in_array(strtolower($name), $allows)) {
                $match = $name;
                return false;
            }
        }

        return true;
    }

    /**
     * Terminates the request by sending a status and formatted message.
     *
     * Responds according to the `Accept` header:
     * - `application/json` → JSON response
     * - `application/xml` / `text/xml` → XML response
     * - `text/html` → HTML page
     * - fallback → plain text
     *
     * @param int $status HTTP status code.
     * @param string $message Termination message.
     * @param string|null $title Optional error title.
     * @param int $retry Optional cache retry duration in seconds (default: 3600).
     *
     * @return void
     */
    public static function terminate(
        int $status, 
        string $message, 
        ?string $title = null,
        int $retry = 3600
    ): void
    {
        $output = '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '*/*';
        $type = 'text/plain; charset=utf-8';
        $title ??= HttpStatus::phrase($status, 'Termination Error');

        if ($accept === '*/*' || str_contains($accept, 'application/json') || (!$accept && Luminova::isApiPrefix())) {
            $type = 'application/json; charset=utf-8';
            $output = json_encode(
                ['status' => $status, 'error' => $title, 'message' => $message], 
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );
        } elseif ($accept && str_contains($accept, 'text/html')) {
            $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $message = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            $type = 'text/html; charset=utf-8';

            $output = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>{$title}</title></head><body>";
            $output .= "<h1>{$status} {$title}</h1><p>{$message}</p>";
            $output .= "</body></html>";
        } elseif ($accept && str_contains($accept, 'xml')) {
            $type = 'application/xml; charset=utf-8';
            $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
            $output .= "<response>\n";
            $output .= "  <status>{$status}</status>\n";
            if($title){
                $output .= "  <error>" . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</error>\n";
            }
            $output .= "  <message>" . htmlspecialchars($message, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</message>\n";
            $output .= "</response>";
        } else {
            $output = sprintf('(%d) [%s] %s', $status, $title, $message);
        }

        self::sendNoCacheHeaders($status, $type, $retry);
        self::clearOutputBuffers('all');

        echo $output;
        exit(STATUS_ERROR);
    }

    /**
     * Normalize and validate multiple HTTP header values.
     *
     * - Trims leading and trailing spaces and tabs.
     * - Validates each value against RFC 7230 rules.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2.4
     *
     * @param array|mixed $values Header values or key-value to process.
     *
     * @return array<string,mixed>|string[] Return an array of normalized headers or key-value.
     * @throws InvalidArgumentException If any value is non-scalar or null.
     */
    public static function normalize(mixed $values, bool $withName = false): array
    {
        if (!is_array($values)) {
            $values = [$values];
        }

        if ($values === []) {
            throw new InvalidArgumentException('Header value cannot be an empty array.');
        }

        $normalized = [];

        foreach ($values as $name => $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Header value must be scalar or null; %s provided.',
                    is_object($value) ? get_class($value) : gettype($value)
                ));
            }

            $value = trim((string) $value, " \t");
            self::assert($value, true);

            if($withName){
                self::assert($name, false);
            }

            $normalized[$name] = $value;
        }

        return $withName 
            ? $normalized
            : array_values($normalized);
    }

    /**
     * Validate an HTTP header name or value.
     *
     * Notes:
     * - Header values do NOT support obs-fold (RFC 7230 §3.2).
     * - Header names must be non-empty ASCII tokens.
     *
     * @param mixed $value Header name or value to validate.
     * @param bool $isValue True to validate a header value, false for a header name.
     *
     * @throws InvalidArgumentException When the header name or value is invalid.
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2
     */
    public static function assert(mixed $value, bool $isValue = true): void
    {
        if($isValue){
            if (!preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D', (string) $value)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid header value: "%s".',
                    $value
                ));
            }
            return;
        }

        if ($value === '' || !is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Header name must be a non-empty string; %s provided.',
                is_object($value) ? get_class($value) : gettype($value)
            ));
        }

        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $value)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid header name: "%s".',
                $value
            ));
        }
    }

    /**
     * Initializes request security configuration.
     * 
     * @return void
     */
    private static function initRequestSecurityConfig(): void
    {
        if(!self::$security instanceof Security){
            self::$security = new Security();
        }
    }

    /**
     * Validate and prepare REST API request headers for CORS compliance.
     * 
     * This method inspects the incoming request when it targets an API endpoint
     * and ensures that the request origin, headers, and credentials adhere to
     * the configured CORS policy.
     * 
     * Behavior:
     * - Checks the `Origin` header:
     *   - Terminates the request if the origin is missing and `forbidEmptyOrigin` is enabled.
     *   - Validates the origin against `allowOrigins` and sets `Access-Control-Allow-Origin`.
     * - Validates request headers against `allowHeaders` and sets `Access-Control-Allow-Headers`.
     * - Sets `Access-Control-Allow-Credentials` based on configuration.
     * 
     * If any validation fails, the request is terminated immediately with
     * an appropriate HTTP status and message.
     * 
     * @param array<string,string|int|float> &$headers Headers array to be modified with CORS response headers.
     * @param array<string,string|int|float>|null $requestHeaders Optional request headers to validate.
     * @param bool $terminateOnFailure Whether to terminate the request on validation failure (default: true).
     * 
     * @return bool Returns true if the request passes all validations; false if terminated.
     */
    private static function isValidHeaders(
        array &$headers, 
        ?array $requestHeaders = null,
        bool $terminateOnFailure = true
    ): bool 
    {
        self::initRequestSecurityConfig();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if(!$origin && self::$security->forbidEmptyOrigin){
            if($terminateOnFailure){
                self::terminate(
                    400, 
                    'Invalid request: missing origin.', 
                    PRODUCTION ? null : '\App\Config\Security::forbidEmptyOrigin'
                );
            }
            return false;
        }

        if ($origin && self::$security->allowOrigins) {
            $allowed = self::getAllowedOrigin($origin);
            
            if ($allowed === null) {
                if($terminateOnFailure){
                    self::terminate(
                        403, 
                        'Access denied: request origin not allowed.', 
                        PRODUCTION ? null : '\App\Config\Security:::allowOrigins'
                    );
                }
                return false;
            }
    
            $headers['Access-Control-Allow-Origin'] = $allowed;
        }
        
        if (self::$security->allowHeaders !== []) {
            $match = null;

            if (!self::isAllowedHeaders($requestHeaders, $match)) {
                if($terminateOnFailure){
                    self::terminate(
                        400, 
                        "Invalid header: {$match} found in the request.", 
                        PRODUCTION ? null : '\App\Config\Security:::allowHeaders'
                    );
                }
                return false;
            }

            $headers['Access-Control-Allow-Headers'] = implode(', ', self::$security->allowHeaders);
        }

        $headers['Access-Control-Allow-Credentials'] = self::$security->allowCredentials ? 'true' : 'false';

        return true;
    }

    /**
     * Normalize and optionally send HTTP headers to the client.
     *
     * - Removes invalid or empty headers.
     * - Conditionally appends charset to `Content-Type`.
     * - Can either send headers immediately or return them as an array.
     *
     * @param array<string,string|int|float> $headers Associative array of headers to process.
     * @param bool $withCharset Append the default charset from env to `Content-Type` if missing (default: false).
     * @param bool $isSend If true, headers are sent using `header()`. 
     *                     If false, an array of normalized headers is returned. (default: true)
     * 
     * @return array<string,mixed> Processed headers when `$isSend` is false, otherwise an empty array.
     */
    private static function parseHeaders(array $headers, bool $withCharset = false, bool $isSend = true): array 
    {
        $normalized = [];
        $xPowered = env('x.powered', true);
        $charset = env('app.charset', 'utf-8');

        if (isset($headers['X-System-Default-Headers'])) {
            $headers = array_replace(self::getDefault(), $headers);
        }elseif($xPowered){
            $headers['X-Powered-By'] = Luminova::copyright();
        }

        foreach ($headers as $header => $values) {
            if (
                !$header ||
                $values === '' ||
                $values === [] ||
                ($header === 'X-Powered-By' && !$xPowered) ||
                ($header === 'X-System-Default-Headers') ||
                ($header === 'Content-Encoding' && $values === false)
            ) {
                continue;
            }

            $values = is_array($values) ? array_unique($values) : [$values];

            $parsed = [];
            foreach ($values as $value) {
                if($withCharset && $header === 'Content-Type' && !str_contains($value, 'charset')){
                    $value = "{$value}; charset={$charset}";
                }

                if (!$isSend) {
                    $parsed[] = $value;
                    continue;
                }
                
                header("{$header}: {$value}");
            }

            if ($isSend || $parsed === []) {
                continue;
            }

            $normalized[$header] = implode(', ', $parsed);
        }

        return $normalized;
    }
}