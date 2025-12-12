<?php 
/**
 * Luminova Framework Proxy helper.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Network;

use \Throwable;
use \Luminova\Components\Async;
use \Luminova\Http\Client\Novio;
use \Luminova\Interface\ResponseInterface;

final class Proxy
{
    /**
     * List of proxy types.
     * 
     * @var array $types
     */
    private static array $types = ['http', 'https', 'socks4', 'socks5'];

    /**
     * Validate a proxy string and confirm it follows supported formats.
     *
     * Supported formats:
     * - ip:port
     * - user:pass@ip:port
     * - [ipv6]:port
     *
     * The method checks:
     * - Host (IPv4 or IPv6)
     * - Optional authentication
     * - Optional proxy type (must match self::$types)
     * - Valid port number
     *
     * @param string $proxy Proxy string to validate.
     *
     * @return bool Returns bool True when valid, false when invalid.
     *
     * @example - Example IPv4:
     * ```php
     * Proxy::validate('192.168.0.10:8080');
     * // true
     * ```
     *
     * @example - Example Auth IPv4:
     * ```php
     * Proxy::validate('user:pass@8.8.8.8:3128');
     * // true
     * ```
     *
     * @example - Example IPv6:
     * ```php
     * Proxy::validate('[2001:db8::1]:8080');
     * // true
     * ```
     *
     * @example - Example Invalid:
     * ```php
     * Proxy::validate('invalid:host');
     * // false
     * ```
     */
    public static function validate(string $proxy): bool
    {
        [$host, $port,, $type] = self::parts(self::normalize($proxy));

        if (!$host || ($type !== null && !in_array($type, self::$types, true))) {
            return false;
        }

        $version = FILTER_FLAG_IPV4;

        if ($host[0] === '[' && substr($host, -1) === ']') {
            $host = substr($host, 1, -1);
            $version = FILTER_FLAG_IPV6;
        }
        
        if (!filter_var($host, FILTER_VALIDATE_IP, $version)) {
            return false;
        }

        return self::isPort($port);
    }

    /**
     * Normalize a proxy string into a consistent and predictable format.
     *
     * The normalization process:
     * - Removes all whitespace.
     * - Ensures IPv6 hosts are wrapped in brackets.
     * - Preserves authentication (user:pass@).
     * - Preserves proxy type prefixes (e.g. http://).
     * - Always returns the proxy in a uniform structure for parsing.
     *
     * @param string $proxy Raw proxy string.
     *
     * @return string Returns string Normalized proxy in a clean, consistent format.
     *
     * @example - Example IPv4:
     * ```php
     * Proxy::normalize(' 192.168.0.10 : 8080 ');
     * // "192.168.0.10:8080"
     * ```
     *
     * @example - Example Auth:
     * ```php
     * Proxy::normalize('user:pass@8.8.8.8:3128');
     * // "user:pass@8.8.8.8:3128"
     * ```
     *
     * @example - Example IPv6:
     * ```php
     * Proxy::normalize('2001:db8::1:8080');
     * // "[2001:db8::1]:8080"
     * ```
     */
    public static function normalize(string $proxy): string
    {
        [$host, $port, $auth, $type] = self::parts($proxy);

        $isIpv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if ($isIpv6) {
            $host = '[' . $host . ']';
        }

        $normalized = $host . ':' . $port;

        if ($auth !== null) {
            $normalized = $auth . '@' . $normalized;
        }

        if ($type !== null) {
            $normalized = $type . '://' . $normalized;
        }

        return $normalized;
    }

    /**
     * Break a proxy string into its core components.
     *
     * Extracts and returns:
     * - Host (IPv4, IPv6, or hostname)
     * - Port
     * - Authentication section (user:pass), if present
     * - Proxy type prefix (http, socks5, etc), if present
     *
     * All whitespace is stripped automatically.
     *
     * @param string $proxy Raw proxy string.
     *
     * @return array Returns array [host, port, auth, type].
     *
     * @example - Example IPv4:
     * ```php
     * Proxy::parts('8.8.8.8:8080');
     * // ['8.8.8.8', 8080, null, null]
     * ```
     *
     * @example - Example Auth + Type:
     * ```php
     * Proxy::parts('http://user:pass@1.1.1.1:3128');
     * // ['1.1.1.1', 3128, 'user:pass', 'http']
     * ```
     *
     * @example - Example IPv6:
     * ```php
     * Proxy::parts('[2001:db8::1]:8080');
     * // ['2001:db8::1', 8080, null, null]
     * ```
     */
    public static function parts(string $proxy): array 
    {
        $proxy = preg_replace('/\s+/', '', trim($proxy));
        $parts = $proxy;
        $auth = null;
        $type = self::type($proxy);

        if ($type !== null) {
            $parts = substr($proxy, strlen("{$type}://"));
        }

        if (self::isAuthenticated($proxy)) {
            [$auth, $parts] = array_map('trim', explode('@', $proxy, 2));
        }

        $host = '';
        $port = '';

        if (preg_match('/^\[(.+)\]:(\d+)$/', $parts, $m)) {
            $host = trim($m[1]);
            $port = (int) $m[2];
        } elseif (preg_match('/^(.+):(\d+)$/', $parts, $m)) {
            $host = trim($m[1]);
            $port = (int) $m[2];
        }

        return [strtolower($host), $port, $auth, $type];
    }

    /**
     * Parse a proxy string into its components.
     *
     * Extracted parts include:
     * - host
     * - port
     * - username (if authentication is provided)
     * - password (if authentication is provided)
     * - type (optional, e.g., http, socks5)
     *
     * @param string $proxy Raw proxy string.
     *
     * @return array<string,mixed> Returns array Associative array with keys: host, port, username, password, type.
     *
     * @example - Example IPv4 with auth:
     * ```php
     * Proxy::parse("john:pass@10.0.0.1:8080");
     * // ['host' => '10.0.0.1', 'port' => 8080, 'username' => 'john', 'password' => 'pass', 'type' => null]
     * ```
     *
     * @example - Example IPv6 without auth:
     * ```php
     * Proxy::parse("[2001:db8::1]:8080");
     * // ['host' => '2001:db8::1', 'port' => 8080, 'username' => null, 'password' => null, 'type' => null]
     * ```
     */
    public static function parse(string $proxy): array
    {
        [$host, $port, $auth, $type] = self::parts($proxy);

        $username = null;
        $password = null;

        if ($auth !== null) {
            $username = $auth;
            if (str_contains($auth, ':')) {
                [$username, $password] = explode(':', $auth, 2);
                $password = trim($password);
            }

            $username = trim($username);
        }

        return [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $password,
            'type' => $type,
        ];
    }

    /**
     * Extract the port number from a proxy string.
     *
     * Supports IPv4, IPv6 (bracketed), and hostname formats.
     * Returns null if no valid port is found.
     *
     * @param string $proxy Raw proxy string.
     *
     * @return int|null Returns int|null Port number if detected, otherwise null.
     *
     * @example - Example IPv4:
     * ```php
     * Proxy::port("10.0.0.1:8080");
     * // 8080
     * ```
     *
     * @example - Example IPv6:
     * ```php
     * Proxy::port("[2001:db8::1]:3128");
     * // 3128
     * ```
     *
     * @example - Example no port:
     * ```php
     * Proxy::port("10.0.0.1");
     * // null
     * ```
     */
    public static function port(string $proxy): ?int 
    {
        $proxy = preg_replace('/\s+/', '', trim($proxy));

        if (preg_match('/^\[(.+)\]:(\d+)$/', $proxy, $m)) {
            return (int) $m[2];
        } 
        
        if (preg_match('/^(.+):(\d+)$/', $proxy, $m)) {
            return (int) $m[2];
        }

        return null;
    }

    /**
     * Format proxy parts into a normalized string representation.
     * 
     * Accepts array format from {@see parse()} or {@see parts()}.
     *
     * Converts an array of proxy components into a standardized proxy string:
     * - Adds type prefix if present (http://, socks5://)
     * - Adds authentication (username:password@) if present
     * - Wraps IPv6 addresses in brackets
     * - Appends port if present
     *
     * @param array<string,mixed>|array<int,mixed> $parts List or associative array with keys: host, port, username, password, type.
     *
     * @return string Returns string Formatted proxy string suitable for usage or storage.
     *
     * @example - List array example:
     * ```php
     * Proxy::format([
     *     '10.0.0.1',
     *     8080,
     *     'john:pass',
     *     null
     * ]);
     * // "john:pass@10.0.0.1:8080"
     * ```
     * 
     * @example - Associative array example IPv4 with auth:
     * ```php
     * Proxy::format([
     *     'host' => '10.0.0.1',
     *     'port' => 8080,
     *     'username' => 'john',
     *     'password' => 'pass',
     *     'type' => null
     * ]);
     * // "john:pass@10.0.0.1:8080"
     * ```
     *
     * @example - Associative array example IPv6 with type:
     * ```php
     * Proxy::format([
     *     'host' => '2001:db8::1',
     *     'port' => 8080,
     *     'username' => null,
     *     'password' => null,
     *     'type' => 'http'
     * ]);
     * // "http://[2001:db8::1]:8080"
     * ```
     */
    public static function format(array $parts): string
    {
        [$host, $port, $auth, $type] = self::extract($parts);
        $host = trim($host);

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        $portString = $port ? ':' . $port : '';
        $prefix = $type ? strtolower(trim($type)) . '://' : '';

        return $prefix . ($auth ? $auth . '@' : '') . $host . $portString;
    }

    /**
     * Extract the proxy type from a proxy string.
     *
     * Supported types: http, https, socks4, socks5.
     * If no type is detected, returns null.
     *
     * @param string $proxy Raw proxy string.
     *
     * @return string|null Returns string|null Detected proxy type or null if none.
     *
     * @example - Example HTTP:
     * ```php
     * Proxy::type("http://10.0.0.1:8080");
     * // "http"
     * ```
     *
     * @example - Example SOCKS5:
     * ```php
     * Proxy::type("socks5://user:pass@1.2.3.4:1080");
     * // "socks5"
     * ```
     *
     * @example - Example no type:
     * ```php
     * Proxy::type("10.0.0.1:8080");
     * // null
     * ```
     */
    public static function type(string $proxy): ?string
    {
        if ($proxy && preg_match('#^([a-z0-9]+)://#i', $proxy, $m)) {
           return strtolower($m[1]);
        }
        
        return null;
    }

    /**
     * Check if a proxy string matches a given type.
     *
     * Comparison is case-insensitive.
     *
     * @param string $proxy Proxy string.
     * @param string $type Expected proxy type (e.g., http, socks5).
     *
     * @return bool Returns bool True if proxy matches type, false otherwise.
     *
     * @example - Example match:
     * ```php
     * Proxy::is("http://10.0.0.1:8080", "http");
     * // true
     * ```
     *
     * @example - Example no match:
     * ```php
     * Proxy::is("socks5://1.2.3.4:1080", "http");
     * // false
     * ```
     */
    public static function is(string $proxy, string $type): bool
    {
        return trim(strtolower($type)) === self::type($proxy);
    }

    /**
     * Validate a TCP/UDP port number.
     *
     * Checks that the port is numeric and within 1–65535.
     *
     * @param string|int $port Port to validate.
     *
     * @return bool Returns bool True if valid, false if invalid.
     *
     * @example - Example valid port:
     * ```php
     * Proxy::isPort(8080);
     * // true
     * ```
     *
     * @example - Example invalid port:
     * ```php
     * Proxy::isPort(70000);
     * // false
     * ```
     */
    public static function isPort(string|int $port): bool
    {
        $port = trim((string) $port);

        if ($port === '' || !ctype_digit($port)) {
            return false;
        }

        $port = (int) $port;
        return $port >= 1 && $port <= 65535;
    }

    /**
     * Check if a proxy string contains authentication credentials.
     *
     * Detects the presence of a username and/or password (user:pass@).
     *
     * @param string $proxy Raw proxy string.
     *
     * @return bool Returns bool True if proxy contains authentication, false otherwise.
     *
     * @example - Example with auth:
     * ```php
     * Proxy::isAuthenticated("john:pass@10.0.0.1:8080");
     * // true
     * ```
     *
     * @example - Example without auth:
     * ```php
     * Proxy::isAuthenticated("10.0.0.1:8080");
     * // false
     * ```
     */
    public static function isAuthenticated(string $proxy): bool
    {
        return str_contains($proxy, '@');
    }

    /**
     * Check if a proxy is blocked, unreachable, or cannot access the given endpoint.
     *
     * This method uses Google to sends a simple GET request through the proxy.  
     * A proxy is considered **blocked** when:
     * - the request fails to connect,
     * - the endpoint returns a non-200 response,
     * - the response body is empty.
     *
     * @param string $proxy Proxy address in the format "ip:port" or "user:pass@ip:port".
     * @param int $timeout Maximum request and connection timeout in seconds (default: `10`).
     * @param string|null $useragent User agent string. Defaults to a basic browser UA.
     *
     * @return bool Returns true when the proxy is blocked or unreachable, false when it works.
     *
     * @example - Example:
     * ```php
     * // Basic usage with default Google test
     * $isBlocked = Proxy::isBlocked('123.45.67.89:8080');
     * if ($isBlocked) {
     *     echo "Proxy failed.";
     * } else {
     *     echo "Proxy works.";
     * }
     * ```
     *
     * @example - Using a custom userAgent string:
     * ```php
     * $isBlocked = Proxy::isBlocked(
     *     '123.45.67.89:8080',
     *     10,
     *     'Mozilla/5.0'
     * );
     * ```
     *
     * @example - Proxy with authentication:
     * ```php
     * Proxy::isBlocked('john:pass123@192.168.1.22:1080');
     * ```
     */
    public static function isBlocked(
        string $proxy,
        int $timeout = 10,
        ?string $useragent = null
    ): bool 
    {
        $useragent = $useragent ?: 'Mozilla/5.0';

        try {
            $response = self::request('https://www.google.com/', null, options: [
                'proxy' => $proxy,
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'allow_redirects' => true,
                'headers' => [
                    'User-Agent' => $useragent
                ]
            ]);
        } catch (Throwable) {
            return true;
        }

        if ($response->getStatusCode() !== 200 || $response->getLength() < 1) {
            return true;
        }

        return false;
    }

    /**
     * Perform a full proxy test and return detailed results.
     * 
     * This method uses `https://api.ipify.org` to test proxy.
     *
     * The returned array includes:
     * - proxy      (string) The proxy tested.
     * - blocked    (bool) True if the proxy is blocked or unreachable.
     * - latency    (float|null) Response time in seconds, or null on failure.
     * - status     (int|null) HTTP status code, or null on failure.
     * - body_size  (int|null) Size of the response body in bytes, or null on failure.
     * - body       (array|null) Decoded JSON response body, or null on failure.
     * - error      (string|null) Error message if the request failed.
     *
     * @param string $proxy The proxy string to test.
     * @param int $timeout Maximum connection timeout in seconds (default: 0 for no limit).
     *
     * @return array<string,mixed> Returns array Detailed results of the proxy test.
     *
     * @example - Example successful test:
     * ```php
     * Proxy::check("10.0.0.1:8080");
     * // [
     * //     'proxy' => '10.0.0.1:8080',
     * //     'blocked' => false,
     * //     'latency' => 0.234,
     * //     'status' => 200,
     * //     'body_size' => 15,
     * //     'body' => ['ip' => '10.0.0.1'],
     * //     'error' => null
     * // ]
     * ```
     *
     * @example - Example blocked or failed proxy:
     * ```php
     * Proxy::check("10.0.0.2:8080");
     * // [
     * //     'proxy' => '10.0.0.2:8080',
     * //     'blocked' => true,
     * //     'latency' => null,
     * //     'status' => null,
     * //     'body_size' => null,
     * //     'body' => null,
     * //     'error' => 'Connection timed out'
     * // ]
     * ```
     */
    public static function check(
        string $proxy,
        int $timeout = 0
    ): array 
    {
        $start = microtime(true);

        try {
            $response = self::request('https://api.ipify.org', $proxy, isJson: true, options: [
                'timeout' => $timeout,
                'connect_timeout' => $timeout
            ]);
            $latency = microtime(true) - $start;
            $json = json_decode($response->getContents(), true);

            return [
                'proxy' => $proxy,
                'blocked' => $response->getStatusCode() !== 200,
                'latency' => $latency,
                'status' => $response->getStatusCode(),
                'body_size' => $response->getLength(),
                'body' => $json,
            ];
        } catch (Throwable $e) {
            return [
                'proxy' => $proxy,
                'blocked' => true,
                'latency' => null,
                'status' => null,
                'body_size' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the public IP exposed by a proxy.
     *
     * Uses `https://api.ipify.org` to detect the IP seen when routing through the proxy.
     * Useful to confirm the proxy is actually applied.
     *
     * @param string $proxy The proxy string to check.
     *
     * @return string|null Returns string|null The IP address seen through the proxy, or null on failure.
     *
     * @example - Example:
     * ```php
     * Proxy::resolve("123.45.67.89:8080");
     * // "123.45.67.89"
     * ```
     */
    public static function resolve(string $proxy): ?string
    {
        try {
            $response = self::request('https://api.ipify.org', $proxy, isJson: true);

            if($response->getLength() < 1){
                return null;
            }

            $json = json_decode($response->getContents(), true);

            return $json['ip'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Measure proxy latency using a quick HEAD request.
     *
     * Sends a HEAD request to `https://www.google.com/` and measures response time.
     *
     * @param string $proxy Proxy string to test.
     * @param int $timeout Maximum timeout in seconds (default: 10).
     * @param string|null $useragent Optional User-Agent header to use.
     *
     * @return float|null Returns float|null Latency in seconds, or null if the request failed.
     *
     * @example - Example successful ping:
     * ```php
     * Proxy::ping("123.45.67.89:8080");
     * // 0.234
     * ```
     *
     * @example - Example failed ping:
     * ```php
     * Proxy::ping("10.0.0.1:8080");
     * // null
     * ```
     */
    public static function ping(string $proxy, int $timeout = 10, ?string $useragent = null): ?float
    {
        $start = microtime(true);
        $useragent = $useragent ?: 'Mozilla/5.0';

        try {
            self::request('https://www.google.com/', null, 'HEAD', options: [
                'proxy' => $proxy,
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
                'headers' => [
                    'User-Agent' => $useragent
                ]
            ]);

            return microtime(true) - $start;
        } catch (Throwable) {
            return null;
        }
    }
    
    /**
     * Check if a proxy hides the client IP.
     *
     * Sends a request to `https://httpbin.org/get` and inspects common leak headers:
     * - X-Forwarded-For
     * - Via
     * - Forwarded
     *
     * @param string $proxy Proxy string to test.
     * @param int $timeout Maximum connection timeout in seconds (default: 0 for no limit).
     * @param array|null &$headers Optional reference to capture response headers in lowercase.
     *
     * @return bool Returns bool True if the proxy does not leak the client IP, false otherwise.
     *
     * @example - Example anonymous proxy:
     * ```php
     * $headers = [];
     * Proxy::isAnonymous("123.45.67.89:8080", 5, $headers);
     * // true
     * ```
     *
     * @example - Example non-anonymous proxy:
     * ```php
     * $headers = [];
     * Proxy::isAnonymous("10.0.0.1:8080", 5, $headers);
     * // false
     * ```
     */
    public static function isAnonymous(string $proxy, int $timeout = 0, ?array &$headers = null): bool
    {
        try {
            $response = self::request('https://httpbin.org/get', $proxy, isJson: true, options: [
                'timeout'         => $timeout,
                'connect_timeout' => $timeout,
            ]);

            if($response->getLength() < 1){
                return false;
            }

            $json = json_decode($response->getContents(), true);
            $headers = array_change_key_case($json['headers'] ?? [], CASE_LOWER);

            return !isset($headers['x-forwarded-for'])
                && !isset($headers['via'])
                && !isset($headers['forwarded']);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Convert parsed proxy parts into a formatted proxy string.
     *
     * Alias of {@see format()}.
     *
     * @param array<string,mixed> $parts Associative array with keys: host, port, username, password, type.
     *
     * @return string Returns string Formatted proxy string.
     *
     * @example - Example:
     * ```php
     * Proxy::toString([
     *     'host' => '10.0.0.1',
     *     'port' => 8080,
     *     'username' => 'john',
     *     'password' => 'pass',
     *     'type' => 'http'
     * ]);
     * // "http://john:pass@10.0.0.1:8080"
     * ```
     */
    public static function toString(array $parts): string
    {
        return self::format($parts);
    }

    /**
     * Send an HTTP request with optional proxy support.
     *
     * @param string $url The URL to request.
     * @param string|null $proxy Optional proxy string to route the request through.
     * @param string $method HTTP method to use (default: "GET").
     * @param bool $isJson Whether to request a JSON response (default: false).
     * @param array $options Additional options to pass to the HTTP client.
     *
     * @return ResponseInterface Returns ResponseInterface The response object from the HTTP client.
     */
    private static function request(
        string $url,
        ?string $proxy = null, 
        string $method = 'GET',
        bool $isJson = false,
        array $options = []
    ): ResponseInterface
    {
        if($proxy){
            $options['query']['proxy'] = $proxy;
        }

        if($isJson){
            $options['query']['format'] = 'json';
        }

        return Async::await(fn() => (new Novio())->request($method, $url, $options));
    }

    /**
     * Normalize proxy parts array into a standard 4-element format.
     *
     * Accepts arrays from `parse` or `parts` methods and returns a consistent format:
     * - [host, port, auth, type]
     *
     * Handles both associative arrays (with keys: host, port, username, password, type)
     * and list-style arrays.
     *
     * @param array $parts Input array containing proxy information.
     *
     * @return array Returns array Standardized array: [host, port, auth, type].
     */
    private static function extract(array $parts): array
    {
        if (array_is_list($parts)) {
            [$host, $port, $auth, $type] = array_pad($parts, 4, null);

            return [
                $host ?? '', 
                $port ?? null, 
                $auth ?? null, 
                $type ?? null
            ];
        }

        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;

        $auth = null;
        $username = $parts['username'] ?? null;
        $password = $parts['password'] ?? null;

        if ($username !== null) {
            $auth = $username;
            if ($password !== null) {
                $auth .= ':' . $password;
            }
        }

        $type = $parts['type'] ?? null;

        return [$host, $port, $auth, $type];
    }
}