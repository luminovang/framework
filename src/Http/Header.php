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
use Luminova\Luminova;
use \App\Config\Security;
use Luminova\Logger\Logger;
use Luminova\Utility\Encoder;
use Luminova\Interface\LazyObjectInterface;
use function Luminova\Funcs\http_status_header;
use Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

class Header implements LazyObjectInterface, Countable
{
    /**
     * The header variables key-pair.
     *
     * @var array|null $variables
     */
    protected ?array $variables = null;

    /**
     * Initialize a header collection.
     *
     * Uses the provided headers when available, otherwise extracts headers from
     * the current request environment.
     *
     * @param array<string,mixed>|null $headers Optional header values to initialize with.
     */
    public function __construct(?array $headers = null)
    {
        $this->variables = ($headers === null || $headers === [])
            ? self::getHeaders()
            : self::extractHeaders($headers);
    }

    /**
     * Retrieve a header value or all headers.
     *
     * @param string|null $name Optional header name to retrieve.
     * @param mixed $default Default value returned when the header does not exist.
     *
     * @return mixed Returns the header value, the default value when not found,
     *               or all headers when `$name` is null.
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        if(!$name){
            return $this->variables;
        }

        return $this->has($name) 
            ? $this->variables[$name]
            : $default;
    }

    /**
     * Set a header value.
     *
     * @param string $key Header name.
     * @param mixed $value Header value.
     *
     * @return self Return instance of header class.
     */
    public function set(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Remove a header by name.
     *
     * @param string $key Header name to remove.
     *
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->variables[$key]);
    }

    /**
     * Determine whether a header exists.
     *
     * @param string $key Header name to check.
     *
     * @return bool Returns `true` if the header exists, otherwise `false`.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables);
    }

    /**
     * Get the number of stored headers.
     *
     * @return int The total number of headers.
     */
    public function count(): int
    {
        return count($this->variables);
    }

    /**
     * Retrieve request headers from the server environment.
     *
     * Uses `apache_request_headers()` when available and falls back to parsing
     * `$_SERVER` variables when it is unavailable or returns no headers.
     *
     * @return array<string,string> The extracted request headers.
     */
    public static function getHeaders(): array
    {
        static $apache = null;

        $apache ??= function_exists('apache_request_headers');

        if (!$apache) {
            return self::extractHeaders($_SERVER);
        }

        return apache_request_headers() 
            ?: self::extractHeaders($_SERVER);
    }

    /**
     * Extract HTTP headers from server variables.
     *
     * Converts `$_SERVER` header entries (`HTTP_*`, `CONTENT_TYPE`, and
     * `CONTENT_LENGTH`) into standard HTTP header names similar to
     * `apache_request_headers()`.
     *
     * @param array<string,mixed>|null $variables Server variables to parse.
     *        Defaults to `$_SERVER`.
     *
     * @return array<string,string> Parsed HTTP headers.
     */
    public static function extractHeaders(array $variables): array
    {
        $headers = [];

        foreach ($variables as $name => $value) {
            if (
                !self::isHeaderName($name)
                || !is_scalar($value)
            ) {
                continue;
            }

            $headers[self::toHeaderName($name)] = (string) $value;
        }

        return $headers;
    }

    /**
     * Convert a server header key into a standard HTTP header name.
     *
     * Example:
     * `HTTP_X_REQUEST_ID` becomes `X-Request-Id`.
     *
     * @param string $name Server variable name.
     *
     * @return string HTTP header name.
     */
    public static function toHeaderName(string $name): string
    {
        if ($name === 'CONTENT_TYPE') {
            return 'Content-Type';
        }

        if ($name === 'CONTENT_LENGTH') {
            return 'Content-Length';
        }

        $name = substr($name, 5);

        return str_replace(
            '_',
            '-',
            ucwords(strtolower($name), '_')
        );
    }

    /**
     * Determine whether a server variable represents an HTTP header.
     *
     * @param string $name Server variable name.
     *
     * @return bool Returns true for HTTP header variables.
     */
    public static function isHeaderName(string $name): bool
    {
        $name = strtoupper($name);

        return str_starts_with($name, 'HTTP_')
            || $name === 'CONTENT_TYPE'
            || $name === 'CONTENT_LENGTH'
            || $name === 'REDIRECT_HTTP_AUTHORIZATION';
    }

    /**
     * Get default response headers.
     *
     * @return array<string,string> Return the defined default headers.
     */
    public static function getDefault(): array
    {
        return [
            'Vary'              => 'Accept-Encoding',
            'Content-Type'      => 'text/html',
            'Cache-Control'     => env('default.cache.control', 'no-store, max-age=0, no-cache'),
            'Content-Language'  => env('app.locale', 'en'), 
            'X-Frame-Options'   => 'SAMEORIGIN',
            'Referrer-Policy'   => 'strict-origin-when-cross-origin',
            'X-Powered-By'      => Luminova::copyright(),
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * Send HTTP headers that prevent client and proxy caching.
     *
     * Sets standard no-cache headers, an optional content type, and an optional
     * `Retry-After` header before sending the response status.
     *
     * @param int $status HTTP response status code (default: 200).
     * @param string|bool|null $contentType Response content type. Defaults to `text/html`.
     *                                      Set to `false` to omit the `Content-Type` header.
     * @param string|int|null $retry Optional `Retry-After` header value.
     *
     * @return void
     */
    public static function sendNoCacheHeaders(
        int $status = 200, 
        string|bool|null $contentType = null, 
        string|int|null $retry = null
    ): void 
    {
        self::sendErrorHeaders($status, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Expires'       => '0',
            'Content-Type'  => $contentType ?? 'text/html'
        ], $retry);
    }

    /**
     * Send a 204 (No Content) response with standard no-cache headers.
     *
     * Removes any buffered output and sends headers required for an empty response,
     * optionally including a `Retry-After` header.
     *
     * @param string|int|null $retry Optional `Retry-After` header value.
     *
     * @return void
     */
    public static function sendNoContentHeaders(string|int|null $retry = null): void 
    {
        header_remove('Content-Type'); 
        self::sendErrorHeaders(204, [
            'Cache-Control'          => 'no-cache, no-store, must-revalidate',
            'Content-Length'         => '0',
            'X-Content-Type-Options' => 'nosniff',
        ], $retry);
    }

    /**
     * Normalize HTTP headers without sending them.
     *
     * Applies framework header normalization, optionally validates the configured
     * CORS policy, and appends a charset to the `Content-Type` header when missing.
     *
     * If CORS validation fails, any CORS response headers are omitted while all
     * other headers remain unchanged.
     *
     * @param array<string,mixed> $headers Headers to normalize.
     * @param bool $applyCors Whether to validate the configured CORS policy and apply to headers.
     *
     * @return array<string,mixed> Returns the normalized headers.
     */
    public static function parse(array $headers, bool $applyCors = false): array
    {
        if ($applyCors) {
            self::validateCorsPolicy(
                $headers, 
                terminateOnFailure: false
            );
        }

        return self::normalizeHeaders($headers, true, false);
    }

    /**
     * Send HTTP headers to the client.
     *
     * Safely dispatches HTTP headers, optionally validates the configured CORS
     * policy, sends the HTTP status code, removes entity headers for no-content
     * responses (204, 205, and 304), and appends a charset to `Content-Type`
     * when enabled.
     *
     * If CORS validation fails, the request is terminated unless validation is
     * explicitly disabled.
     *
     * @param array<string,string|int|float> $headers Headers to send.
     * @param bool $ifNotSent Skip sending if headers have already been sent.
     * @param bool $charset Append a charset to `Content-Type` when missing.
     * @param int|null $status HTTP status code to send.
     * @param bool|null $enforceCors Whether to enforce the configured CORS policy.
     *
     * @return void
     *
     * @throws RuntimeException If headers have already been sent and `$ifNotSent` is `false`.
     * @see self::parse() Normalize headers without sending them.
     */
    public static function send(
        array $headers,
        bool $ifNotSent = true, 
        bool $charset = false,
        ?int $status = null,
        ?bool $enforceCors = null
    ): void 
    {
        $_SERVER['HTTP_LMV_SENT_CONTENT_TYPE'] = $headers['Content-Type'] 
            ?? 'text/html';

        if(self::ifHeadersSent($ifNotSent)){
            return;
        }
        
        $enforceCors ??= self::shouldEnforceCors();

        if ($enforceCors && !self::validateCorsPolicy($headers)) {
            return;
        }

        if ($status !== null) {
            // NO_CONTENT, RESET_CONTENT, NOT_MODIFIED
            // RFC-compliant: no entity headers for responses without body
            if (HttpStatus::isNoContent($status)) {
                unset($headers['Content-Type'], $headers['Content-Length']);
            }

            http_status_header($status);
        }

        $xPowered = (bool) env('x.powered', true);

        self::normalizeHeaders(
            $headers, 
            $charset, 
            isSend: true,
            xPowered: $xPowered
        );

        if (!$xPowered) {
            header_remove('X-Powered-By');
        }
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
        return http_status_header($code);
    }

    /**
     * Starts an output buffer with an optional compression or encoding handler.
     *
     * This method starts a new output buffer using `ob_start()`. When compression
     * is enabled, it selects the output handler based on configuration:
     * - Uses a custom handler defined by `output.compression.handler` when available.
     * - Falls back to `ob_gzhandler` for gzip compression when no custom handler is set.
     * - Starts a normal output buffer when compression is disabled.
     *
     * Existing output buffers can optionally be cleared before creating a new one.
     * A custom output handler must be callable.
     *
     * @param bool $clearIfSet Whether to clear existing output buffers before starting a new one (default: false).
     * @param bool $useCompressionHandler Whether to apply the configured output handler (default: true).
     *
     * @return bool True if the output buffer was started successfully, false if
     *              headers were already sent or an active buffer prevented creation.
     *
     * @throws InvalidArgumentException If the configured output handler is not callable.
     */
    public static function setOutputHandler(
        bool $clearIfSet = false, 
        bool $useCompressionHandler = true
    ): bool
    {
        if (headers_sent()) {
            return false;
        }

        if(ob_get_level() > 0){
            if(!$clearIfSet){
                return false;
            }
            //    if($clearIfSet){
            //        return false;
            //    }

            self::clearOutputBuffers('all');
        }

        if (!Encoder::isOutputCompressionEnabled()) {
            return ob_start();
        }

        if (!$useCompressionHandler && !env('output.compression.enable', false)) {
            return ob_start();
        }

        $handler = env('output.compression.handler', null);

        if(!$handler){
            return ob_start('ob_gzhandler');
        }

        if (is_callable($handler)) {
            return ob_start($handler);
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid output handler "%s". Handler "%s" must be callable.',
            $handler,
            'output.compression.handler'
        ));
    }

    /**
     * Clears or flushes PHP output buffers using the selected mode.
     *
     * Supported modes:
     * - `auto` (default): Clears active buffers while preserving the base buffer level.
     * - `all`: Clears all active buffers until the specified minimum level is reached.
     * - `top`: Clears only the current top-most output buffer.
     * - `flush`: Flushes the current top-most buffer without ending it.
     *
     * This method is ignored in CLI and PHPDBG environments where output buffering
     * cleanup is not required.
     *
     * @param string $mode Buffer handling mode: `auto`, `all`, `top`, or `flush`.
     * @param int $limit Minimum output buffer level to preserve when using `all` mode.
     *                    A value of 0 clears all active buffers.
     *
     * @return bool True if an output buffer was cleared or flushed successfully,
     *              false if no active buffers exist or the operation was not performed.
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
     * @see self::findAllowedOriginDomain()
     */
    public static function isOriginAllowed(?string $origin = null): bool
    {
        return self::findAllowedOriginDomain($origin) !== null;
    }
    
    /**
     * Retrieve the allowed CORS origin.
     *
     * Returns:
     * - `*` when wildcard origins are allowed.
     * - The request origin when explicitly trusted.
     * - `null` when the origin is missing or not allowed.
     *
     * @param string|null $origin Origin header value. Defaults to the request origin.
     *
     * @return string|null Allowed origin or null when rejected.
     * @see self::isOriginAllowed()
     */
    public static function findAllowedOriginDomain(?string $origin = null): ?string
    {
        return self::findOriginDomain(
            $origin, 
            rejectEmptyOrigin: true
        );
    }


    /**
     * Retrieve the allowed CORS origin.
     *
     * Returns:
     * - `*` when wildcard origins are allowed.
     * - The request origin when explicitly trusted.
     * - `null` when the origin is missing or not allowed.
     *
     * @param string|null $origin Origin header value. Defaults to the request origin.
     * @param bool $rejectEmptyOrigin
     *
     * @return string|null Allowed origin or null when rejected.
     * @see self::isOriginAllowed()
     */
    private static function findOriginDomain(
        ?string $origin = null,
        bool $rejectEmptyOrigin = false
    ): ?string 
    {
        $origin ??= $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = Security::$allowOrigins;

        // No Origin header.
        // For CORS headers, wildcard can still be returned.
        // For strict validation, reject empty origin.
        if ($origin === '') {
            if (!$rejectEmptyOrigin && $allowed === '*') {
                return '*';
            }

            return null;
        }

        $whitelist = is_array($allowed) ? $allowed : [$allowed];

        // Allow literal "null" origin.
        if ($origin === 'null') {
            $whitelist = array_flip($whitelist);

            return isset($whitelist['null']) ? 'null' : null;
        }

        // Wildcard allows any valid origin.
        if ($allowed === '*') {
            return '*';
        }

        $host = parse_url($origin, PHP_URL_HOST);

        if (!$host) {
            return null;
        }

        foreach ($whitelist as $domain) {
            if ($domain === $origin) {
                return $origin;
            }

            if ($domain === 'self') {
                if ($host === APP_HOSTNAME  || str_ends_with($host, '.' . APP_HOSTNAME)) {
                    return $origin;
                }

                continue;
            }

            if (
                str_starts_with($domain, '.')
                && ($host === substr($domain, 1) || str_ends_with($host, $domain))
            ) {
                return $origin;
            }
        }

        return null;
    }

    /**
     * Check whether all request headers are allowed by the configured CORS policy.
     *
     * If an unapproved header is found, its name is assigned to `$match`.
     *
     * @param array<string,string|int|float>|null $headers Request headers to validate.
     *        Defaults to the current request headers.
     * @param string|null $match Receives the first header name that is not allowed.
     *
     * @return bool Returns `true` if all headers are allowed, or `false` otherwise.
     */
    public static function isHeadersAllowed(
        ?array $headers = null,
        ?string &$match = null
    ): bool 
    {
        if (Security::$allowHeaders === []) {
            return true;
        }

        static $allowed = [];

        if ($allowed === []) {
            foreach (Security::$allowHeaders as $header) {
                $allowed[strtolower($header)] = true;
            }
        }

        $headers ??= self::getHeaders();

        foreach ($headers as $name => $_) {
            $name = strtolower($name);

            if (!isset($allowed[$name])) {
                $match = $name;
                return false;
            }
        }

        return true;
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
            throw new InvalidArgumentException(
                'Header value cannot be an empty array.'
            );
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
     * Validate and prepare request headers for CORS compliance.
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
     * @param array<string,string|int|float>|null $reqHeaders Optional request headers to validate.
     * @param bool $terminateOnFailure Whether to terminate the request on validation failure (default: true).
     * 
     * @return bool Returns true if the request passes all validations; false if terminated.
     */
    private static function validateCorsPolicy(
        array &$headers,
        ?array $reqHeaders = null,
        bool $terminateOnFailure = true
    ): bool 
    {
        $origin = self::findOriginDomain();

        if ($origin === null) {
            self::removeCorsHeaders($headers);

            if (!$terminateOnFailure) {
                return false;
            }

            $error = [
                403,
                'Access denied: request origin not allowed.',
                PRODUCTION ? null : '\App\Config\Security::allowOrigins'
            ];

            if (Security::$forbidEmptyOrigin && empty($_SERVER['HTTP_ORIGIN'])) {
                $error = [
                    400,
                    'Invalid request: missing origin.',
                    PRODUCTION ? null : '\App\Config\Security::forbidEmptyOrigin'
                ];
            }

            Luminova::terminate(...$error);
            return false;
        }

        // Credentials cannot be combined with wildcard origin.
        $headers['Access-Control-Allow-Credentials'] = ($origin !== '*' && Security::$allowCredentials) 
            ? 'true' 
            : 'false';
        $headers['Access-Control-Allow-Origin'] = $origin;

        if ($origin !== '*') {
            $headers['Vary'] = isset($headers['Vary'])
                ? $headers['Vary'] . ', Origin'
                : 'Origin';
        }

        if (Security::$exposeHeaders !== []) {
            $headers['Access-Control-Expose-Headers'] =
                implode(', ', Security::$exposeHeaders);
        }

        if (Security::$allowHeaders === []) {
            return true;
        }

        $header = null;

        if (self::isHeadersAllowed($reqHeaders, $header)) {
            $headers['Access-Control-Allow-Headers'] =
                implode(', ', Security::$allowHeaders);

            return true;
        }

        self::removeCorsHeaders($headers);

        if (!$terminateOnFailure) {
            return false;
        }

        Luminova::terminate(
            400,
            "Invalid header: {$header} found in the request.",
            PRODUCTION ? null : '\App\Config\Security::allowHeaders'
        );

        return false;
    }

    /**
     * Normalize and optionally send HTTP headers.
     *
     * Removes invalid headers, applies framework default headers, optionally adds
     * the `X-Powered-By` header, and appends the configured charset to
     * `Content-Type` when missing.
     *
     * When `$isSend` is true, normalized headers are sent immediately using
     * `header()`. Otherwise, the normalized headers are returned as an array.
     *
     * @param array<string,mixed> $headers Headers to process.
     * @param bool $withCharset Append charset to `Content-Type` when missing.
     * @param bool $isSend Send headers instead of returning them.
     * @param bool $xPowered Include the `X-Powered-By` header when enabled.
     *
     * @return array<string,string> Normalized headers when `$isSend` is false,
     *                              otherwise an empty array.
     */
    private static function normalizeHeaders(
        array $headers, 
        bool $withCharset = false, 
        bool $isSend = true,
        bool $xPowered = false
    ): array 
    {
        $charset = $withCharset
            ? env('app.charset', 'utf-8')
            : null;

        if (isset($headers['X-System-Default-Headers'])) {
            $headers = array_replace(self::getDefault(), $headers);
        }

        if ($xPowered) {
            $headers['X-Powered-By'] ??= Luminova::copyright();
        } else {
            unset($headers['X-Powered-By']);
        }

        unset($headers['X-System-Default-Headers']);

        if($withCharset){
            $type = $headers['Content-Type'] 
                ?? $headers['content-type'] 
                ?? null;

            if($type !== null && !str_contains(strtolower((string) $type), 'charset=')){
                $charset =  env('app.charset', 'utf-8');
                $headers['Content-Type'] = "{$type}; charset={$charset}";
            }
        }

        $normalized = [];

        foreach ($headers as $header => $values) {
            if (
                $header === '' 
                || $values === false 
                || $values === null 
                || !is_string($header)
            ) {
                continue;
            }

            if ($values === []) {
                $values = '';
            }

            $parsed = [];
            $values = is_array($values) ? array_unique($values) : [$values];

            foreach ($values as $value) {
                if(!is_scalar($value)){
                    continue;
                }

                if ($isSend) {
                    header("{$header}: {$value}");
                    continue;
                }
                
                $parsed[] = $value;
            }

            if (!$isSend) {
                $normalized[$header] = implode(', ', $parsed);
            }
        }

        return $normalized;
    }

    /**
     * Check if headers are already sent.
     *
     * @param boolean $ifNotSentIgnore If true return `true` otherwise throw or log in production.
     * 
     * @return bool Return false if not sent, true if sent and `$ifNotSentIgnore` is true, otherwise error.
     * @throws RuntimeException in development mode.
     */
    private static function ifHeadersSent(bool $ifNotSentIgnore): bool 
    {
        $file = null;
        $line = null;

        if (!headers_sent($file, $line)) {
            return false;
        }

        if ($ifNotSentIgnore) {
            return true;
        }

        $message = 'Headers have already been sent. 
            Set $ifNotSent to true to skip sending instead of';

        if (PRODUCTION) {
            Logger::tryLog('warning', "{$message} logging this warning.", [
                'file' => $file,
                'line' => $line
            ]);
            return true;
        }

        $e = new RuntimeException("{$message} throwing this exception.");
        $e->setFile($file);
        $e->setLine($line);

        throw $e;
    }

    /**
     * Check if CORS can be automatically enforced.
     *
     * @return bool
     */
    private static function shouldEnforceCors(): bool 
    {
        return match(Security::$httpCorsPolicyMode) {
            'none'  => false,
            'all'   => true,
            'api'   => Luminova::isApiRequest(),
            'web'   => !Luminova::isApiRequest(),
            default => Luminova::isUriPrefix(Security::$httpCorsPolicyMode)
        };
    }

    /**
     * Remove CORS headers.
     *
     * @param array $headers
     * 
     * @return void
     */
    private static function removeCorsHeaders(array &$headers): void
    {
        unset(
            $headers['Access-Control-Allow-Origin'],
            $headers['Access-Control-Allow-Headers'],
            $headers['Access-Control-Allow-Credentials'],
            $headers['Access-Control-Allow-Methods'],
            $headers['Access-Control-Expose-Headers']
        );
    }

    /**
     * Send HTTP error headers.
     *
     * @param int $status HTTP status code to send.
     * @param array $headers Headers to send.
     * @param string|int|null $retry Optional value for Retry-After header.
     *
     * @return void
     */
    private static function sendErrorHeaders(
        int $status,
        array $headers, 
        string|int|null $retry = null
    ): void 
    {
        if($retry !== null){
            $headers['Retry-After'] = $retry;
        }

        self::send(
            headers:     $headers, 
            ifNotSent:   true, 
            charset:     false,
            status:      $status, 
            enforceCors: false
        );
    }
}