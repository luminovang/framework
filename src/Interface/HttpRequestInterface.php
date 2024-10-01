<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \Luminova\Http\File;
use \Luminova\Http\UserAgent;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\SecurityException;

/**
 * Anonymous methods to retrieve values from HTTP request fields. 
 * 
 * @method mixed getPut(string $key, mixed $default = null)       Get a field value from HTTP PUT request.
 * @method mixed getOptions(string $key, mixed $default = null)   Get a field value from HTTP OPTIONS request.
 * @method mixed getPatch(string $key, mixed $default = null)     Get a field value from HTTP PATCH request.
 * @method mixed getHead(string $key, mixed $default = null)      Get a field value from HTTP HEAD request.
 * @method mixed getConnect(string $key, mixed $default = null)   Get a field value from HTTP CONNECT request.
 * @method mixed getTrace(string $key, mixed $default = null)     Get a field value from HTTP TRACE request.
 * @method mixed getPropfind(string $key, mixed $default = null)  Get a field value from HTTP PROPFIND request.
 * @method mixed getMkcol(string $key, mixed $default = null)     Get a field value from HTTP MKCOL request.
 * @method mixed getCopy(string $key, mixed $default = null)      Get a field value from HTTP COPY request.
 * @method mixed getMove(string $key, mixed $default = null)      Get a field value from HTTP MOVE request.
 * @method mixed getLock(string $key, mixed $default = null)      Get a field value from HTTP LOCK request.
 * @method mixed getUnlock(string $key, mixed $default = null)    Get a field value from HTTP UNLOCK request.
 * 
 * @param string $key  The field key to retrieve the value value from.
 * @param mixed $default An optional default value to return if the key is not found.
 * 
 * @return mixed Return the value from HTTP request method body based on key.
 */
interface HttpRequestInterface
{
    /**
     * Get a value from any HTTP request method.
     *
     * @param string $key HTTP request body key (e.g, `$request->getPut('field', 'default value')`).
     * @param array $arguments Arguments as the default value (default: blank string).
     * 
     * @return mixed Return value from the HTTP request if set; otherwise, return the default value.
     * @internal
     */
    public function __call(string $key, array $arguments): mixed;

    /**
     * Converts the request body to a raw string format based on the content type.
     * 
     * @return string Return the raw string representation of the request body.
     */
    public function __toString(): string;

    /**
     * Converts the request body to a raw string format based on the content type.
     *
     * **Supported Content Types:**
     * 
     * - `application/x-www-form-urlencoded`: Converts the body to a URL-encoded query string.
     * - `application/json`: Converts the body to a JSON string.
     * - `multipart/form-data`: Converts the body to a multipart/form-data format.
     *
     * @return string Return the raw string representation of the request body.
     */
    public function toString(): string;

    /**
     * Converts the request body to a multipart/form-data string format.
     *
     * @return string Return the multipart/form-data representation of the request body.
     */
    public function toMultipart(): string;

    /**
     * Get a field value from HTTP GET request.
     *
     * @param string $key The field key to retrieve the value value from.
     * @param mixed $default An optional default value to return if the key is not found (default: null).
     * 
     * @return mixed Return the value from HTTP request method body based on key.
     */
    public function getGet(string $key, mixed $default = null): mixed;

    /**
     * Get a field value from HTTP POST request.
     *
     * @param string $key The field key to retrieve the value value from.
     * @param mixed $default An optional default value to return if the key is not found (default: null).
     * 
     * @return mixed Return the value from HTTP request method body based on key.
     */
    public function getPost(string $key, mixed $default = null): mixed;

    /**
     * Get a field value from HTTP request body as an array.
     *
     * @param string $method The HTTP request method (e.g, `GET`, `POST`, etc..).
     * @param string $key The request body name to return.
     * @param array $default Optional default value to return.
     * 
     * @return array Return array of HTTP request method key values.
     * @throws InvalidArgumentException Throws if unsupported HTTP method was passed.
     */
    public function getArray(string $method, string $key, array $default = []): array;

    /**
     * Get the entire request body as an array or JSON object.
     * 
     * @param bool $object Whether to return an array or a JSON object (default: false).
     * 
     * @return array|object Return the request body as an array or JSON object.
     */
    public function getBody(bool $object = false): array|object;

    /**
     * Get an uploaded file object or any array of file object for multiple files.
     * 
     * @param string $name The file input field name.
     * @param int|null $index Optional file index for multiple files (default: null).
     * 
     * @return array<int,File>|File|null Return uploaded file instance or null if file input name not found.
     * @see https://luminova.ng/docs/3.0.2/http/file-object
     */
    public function getFile(string $name, ?int $index = null): File|array|null;

    /**
     * Get raw array of all uploaded files.
     *
     * @return array<string,array> Return an array containing uploaded files information.
     */
    public function getFiles(): array;

    /**
     * Get the current request method.
     *
     * @return string Return the request method in lowercased.
     */
    public function getMethod(): string;

    /**
     * Extract the boundary from the Content-Type header.
     * 
     * @return string|null Returns the boundary string or null if not found.
     */
    public function getBoundary(): ?string;

    /**
     * Check if the request method is GET.
     *
     * @return bool Returns true if the request method is GET, false otherwise.
     */
    public function isGet(): bool;

    /**
     * Check if the request method is POST.
     *
     * @return bool Returns true if the request method is POST, false otherwise.
     */
    public function isPost(): bool;

    /**
     * Check if the request method is the provided method.
     *
     * @param string $method The method to check against (e.g, `POST`, `GET`).
     * 
     * @return bool Returns true if the request method matches the provided method, false otherwise.
     */
    public function isMethod(string $method): bool;

    /**
     * Get the request content type.
     *
     * @return string Return the request content type or blank string if not available.
     */
    public function getContentType(): string;

    /**
     * Get request header authorization header from (e.g, `HTTP_AUTHORIZATION`, `Authorization` or `REDIRECT_HTTP_AUTHORIZATION`).
     * 
     * @return string|null Return the authorization header value or null if no authorization header was sent.
     */
    public function getAuth(): ?string;

    /**
     * Check if the current connection is secure
     * 
     * @return bool Return true if the connection is secure false otherwise.
     */
    public function isSecure(): bool;

    /**
     * Check if request is ajax request, see if a request contains the HTTP_X_REQUESTED_WITH header.
     * 
     * @return bool Return true if request is ajax request, false otherwise.
     */
    public function isAJAX(): bool;

    /**
     * Check if the request URL indicates an API endpoint.
     *
     * This method checks if the URL path starts with '/api' or 'public/api'.
     * 
     * @return bool Returns true if the URL indicates an API endpoint, false otherwise.
     */
    public function isApi(): bool;
    
    /**
     * Get the request URL query string.
     *
     * @return string Return the request URL query parameters as string.
     */
    public function getQuery(): string;

    /**
     * Get current URL query parameters as an associative array using the parameter name as key.
     * 
     * @return array<string,mixed> Return the request URL query parameters as an array.
     */
    public function getQueries(): ?array;

    /**
     * Get current request URL including the scheme, host and query parameters.
     * 
     * @return string Return the request full URL.
     */
    public function getUri(): string;

    /**
     * Get current request URL path information.
     * 
     * @return string Return the request URL paths.
     */
    public function getPaths(): string;

    /**
     * Returns un-decoded request URI, path and query string.
     *
     * @return string Return the raw request URI (i.e. URI not decoded).
     */
    public function getRequestUri(): string;

    /**
     * Get current hostname without port, if allowed host is set it will check if host is in allowed list or patterns.
     * 
     * @param bool $exception Weather to throw an exception if invalid host or not allowed host (default: false).
     * 
     * @return string Return the request hostname.
     * @throws SecurityException Throw if host is invalid or not allowed.
     */
    public function getHost(bool $exception = false): ?string;

    /**
     * Get current hostname with port if port is available. 
     * If allowed host is set it will check if host is in allowed list or patterns.
     * 
     * @param bool $exception Weather to throw an exception if invalid host or not allowed host (default: false).
     * @param bool $port Weather to return hostname with port (default: true).
     * 
     * @return string Return request hostname and port.
     * @throws SecurityException If host is invalid or not allowed.
     */
    public function getHostname(bool $exception = false, bool $port = true): ?string;

    /**
     * Get the request origin domain, if the list of trusted origin domains are specified, 
     * it will check if the origin is a trusted origin domain.
     * 
     * @return string|null Return the request origin domain if found and trusted, otherwise null.
     */
    public function getOrigin(): ?string;

    /**
     * Get the request origin port from `X_FORWARDED_PORT` or `SERVER_PORT` if available.
     *
     * @return int|string|null Return either a string if fetched from the server available, or integer, otherwise null.
     * 
     * > Check if X-Forwarded-Port header exists and use it, if available.
     * > If not available check for server-port header if also not available return NULL as default.
     */
    public function getPort(): int|string|null;

    /**
     * Gets the request scheme name.
     * 
     * @return string Return request scheme, if secured return `https` otherwise `http`.
     */
    public function getScheme(): string;

    /**
     * Gets the request server protocol (e.g: `HTTP/1.1`).
     * 
     * @param string $default The default server protocol to return if no available (default: `HTTP/1.1`)
     * 
     * @return string Return Request protocol name and version, if available, otherwise default is return `HTTP/1.1`.
    */
    public function getProtocol(string $default = 'HTTP/1.1'): string;
 
    /**
     * Get the request browser name and platform from user-agent information.
     * 
     * @return string Return browser name and platform.
     */
    public function getBrowser(): string;

    /**
     * Get request browser user-agent information.
     * 
     * @param string|null $useragent The User Agent string, if not provided, it defaults to (`HTTP_USER_AGENT`).
     * 
     * @return UserAgent Return instance user-agent class containing browser information.
     */
    public function getUserAgent(?string $useragent = null): UserAgent;

    /**
     * Check if the request origin matches the current application host.
     *
     * @param bool $subdomains Whether to consider subdomains or not (default: false).
     * 
     * @return bool Returns true if the request origin matches the current host, false otherwise.
     */
    public function isSameOrigin(bool $subdomains = false): bool;

    /**
     * Validates if the given (hostname's, origins, proxy ip or subnet) matches any of the trusted patterns.
     * This will consider the defined configuration in `App\Config\Security` during validation.
     * 
     * @param string $input The domain, origin or ip address to check.
     * @param string $context The context to check input for (e.g, `hostname`).
     * 
     * @return bool Return true if the input is trusted, false otherwise.
     * @throws InvalidArgumentException If invalid context is provided.
     * 
     * Supported Context:
     * - hostname - Validates a host name.
     * - origin - Validates an origin hostname.
     * - proxy Validates an IP address or proxy.
     */
    public static function isTrusted(string $input, string $context = 'hostname'): bool;

    /**
     * Check whether this request origin ip address is from a trusted proxy.
     * 
     * @return bool Return true if the request origin ip address is trusted false otherwise.
     */
    public function isTrustedProxy(): bool;

    /**
     * Check whether this request origin is from a trusted origins.
     * 
     * @return bool Return true if the request origin is trusted false otherwise.
     */
    public function isTrustedOrigin(): bool;
}