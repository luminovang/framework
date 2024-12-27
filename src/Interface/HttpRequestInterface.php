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
use \Luminova\Http\Server;
use \Luminova\Http\Header;
use \Luminova\Http\UserAgent;
use \Luminova\Interface\CookieJarInterface;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\SecurityException;
use \Generator;

/**
 * Anonymous methods to retrieve values from HTTP request fields. 
 * 
 * @method mixed getPut(string|null $field, mixed $default = null)       Get a field value from HTTP PUT request or entire fields if `$field` param is null.
 * @method mixed getOptions(string|null $field, mixed $default = null)   Get a field value from HTTP OPTIONS request  or entire fields if `$field` param is null.
 * @method mixed getPatch(string|null $field, mixed $default = null)     Get a field value from HTTP PATCH request or entire fields if `$field` param is null.
 * @method mixed getHead(string|null $field, mixed $default = null)      Get a field value from HTTP HEAD request or entire fields if `$field` param is null.
 * @method mixed getConnect(string|null $field, mixed $default = null)   Get a field value from HTTP CONNECT request or entire fields if `$field` param is null.
 * @method mixed getTrace(string|null $field, mixed $default = null)     Get a field value from HTTP TRACE request or entire fields if `$field` param is null.
 * @method mixed getPropfind(string|null $field, mixed $default = null)  Get a field value from HTTP PROPFIND request or entire fields if `$field` param is null.
 * @method mixed getMkcol(string|null $field, mixed $default = null)     Get a field value from HTTP MKCOL request or entire fields if `$field` param is null.
 * @method mixed getCopy(string|null $field, mixed $default = null)      Get a field value from HTTP COPY request or entire fields if `$field` param is null.
 * @method mixed getMove(string|null $field, mixed $default = null)      Get a field value from HTTP MOVE request or entire fields if `$field` param is null.
 * @method mixed getLock(string|null $field, mixed $default = null)      Get a field value from HTTP LOCK request or entire fields if `$field` param is null.
 * @method mixed getUnlock(string|null $field, mixed $default = null)    Get a field value from HTTP UNLOCK request or entire fields if `$field` param is null.
 * 
 * @property Server|null $server The server instance representing HTTP server parameters and configurations.
 * @property Header|null $header The header instance providing HTTP request headers information.
 * @property UserAgent|null $agent The user-agent instance containing client browser details.
 */

interface HttpRequestInterface
{
    /**
     * Get a value from any HTTP request method.
     *
     * @param string $method HTTP request body key (e.g, `$request->getPut('field', 'default value')`).
     * @param array $arguments Arguments as the default value (default: blank string).
     * 
     * @return mixed Return value from the HTTP request if set; otherwise, return the default value.
     * @internal
     */
    public function __call(string $method, array $arguments): mixed;

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
     * Set a specific field in the request body for the given HTTP method.
     * 
     * @param string $field The name of the field to set.
     * @param mixed $value The value to assign to the field.
     * @param string|null $method Optional HTTP method, if null the current request method will be used (e.g, `GET`, `POST`).
     * 
     * @return self Returns the instance request class.
     */
    public function setField(string $field, mixed $value, ?string $method = null): self;

    /**
     * Get a field value from HTTP GET request or entire fields if `$field` param is null.
     *
     * @param string|null $field The field key to retrieve the value value from.
     * @param mixed $default An optional default value to return if the key is not found (default: null).
     * 
     * @return mixed Return the value from HTTP request method body based on key.
     */
    public function getGet(string|null $field, mixed $default = null): mixed;

    /**
     * Get a field value from HTTP POST request or entire fields if `$field` param is null.
     *
     * @param string|null $field The field key to retrieve the value value from.
     * @param mixed $default An optional default value to return if the key is not found (default: null).
     * 
     * @return mixed Return the value from HTTP request method body based on key.
     */
    public function getPost(string|null $field, mixed $default = null): mixed;

    /**
     * Get a field value from HTTP request body as an array.
     *
     * @param string $field The request body field name to return.
     * @param array $default Optional default value to return.
     * @param string|null $method Optional HTTP request method, if null current request method will be used (e.g, `GET`, `POST`, etc..).
     * 
     * @return array Return array of HTTP request method key values.
     */
    public function getArray(string $field, array $default = [], ?string $method = null): array;

    /**
     * Get the entire request body as an array or JSON object.
     * 
     * @param bool $object Whether to return an array or a JSON object (default: false).
     * 
     * @return array<string,mixed>|object Return the request body as an array or JSON object.
     */
    public function getBody(bool $object = false): array|object;

    /**
     * Get an uploaded file instance or a generator yielding file instances for multiple files.
     * 
     * @param string $name The file input field name.
     * @param int|null $index Optional file index for multiple files. If null, all files will be returned (default: null).
     * 
     * @return Generator<int,\Luminova\Http\File,void,void>|\Luminova\Http\File|null Returns an uploaded `File` instance, 
     *         a generator yielding `File` instances for multiple files, or `null` if the input name was not found.
     * 
     * @link https://luminova.ng/docs/0.0.0/http/file-object
     * @see https://luminova.ng/docs/0.0.0/files/uploader
     */
    public function getFile(string $name, ?int $index = null): Generator|File|null;

    /**
     * Get raw array of original uploaded file information without any modification.
     *
     * @return array<string,array> Return an array containing uploaded files information.
     * @see https://luminova.ng/docs/0.0.0/files/uploader
     */
    public function getFiles(): array;

    /**
     * Retrieves the instance of cookie jar containing the cookies from the request headers.
     * 
     * @param string|null $name An optional cookie name to pre-initialize.
     *
     * @return CookieJarInterface Return the cookie jar instance populated with parsed cookies.
     * @link https://luminova.ng/docs/0.0.0/cookies/cookie-file-jar
     */
    public function getCookie(?string $name = null): CookieJarInterface;

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
     * Parses a multipart/form-data string into an associative array with form fields and file data.
     *
     * @param string $data The raw multipart form data content.
     * @param string $boundary The boundary string used to separate form data parts.
     * 
     * @return array Return an array containing {param:array,files:array}:
     *               - 'params' => Associative array of form field names and values
     *               - 'files'  => Associative array of files with metadata and binary content
     */
    public static function getFromMultipart(string $data, string $boundary): array;

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
     * @see https://luminova.ng/docs/0.0.0/security/jwt
     */
    public function getAuth(): ?string;
    
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
     * Get the full URL of the current request.
     *
     * This method returns the complete URL, including the protocol (e.g., http or https),
     * the domain name, the path, and any query string parameters.
     *
     * @return string Return the full URL of the request.
     */
    public function getUrl(): string;

    /**
     * Get the URI (path and query string) of the current request (e.g, `/foo/bar?query=123`).
     *
     * This method returns only the URI, which includes the path and query string, 
     * but excludes the protocol and domain name. 
     *
     * @return string Return the URI of the request (path and query string).
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
     * @return int Return the port number, otherwise default to `443` secured or `80` for insecure.
     * 
     * > Check if X-Forwarded-Port header exists and use it, if available.
     * > If not available check for server-port header if also not available return default port.
     */
    public function getPort(): int;

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
     * @return \Luminova\Http\UserAgent Return instance user-agent class containing browser information.
     * @link https://luminova.ng/docs/0.0.0/http/user-agent
     */
    public function getUserAgent(?string $useragent = null): UserAgent;

    /**
     * Check if a specific field exists in the request body for the given HTTP method.
     * 
     * @param string $field The name of the field to check.
     * @param string|null $method Optional HTTP method, if null the current request method will be used (e.g, `GET`, `POST`).
     * 
     * @return bool Returns true if the field exists for the given method; otherwise, false.
     */
    public function hasField(string $field, ?string $method = null): bool;

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
    public function isMethod(string $method = 'GET'): bool;

    /**
     * Check if the Authorization header matches the specified type.
     *
     * @param string $type The expected type of authorization (e.g., 'Bearer', 'Basic').
     *
     * @return bool Returns true if the Authorization header matches the specified type, otherwise false.
     */
    public function isAuth(string $type = 'Bearer'): bool;

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
     * Checks if the request is routed through a proxy server by inspecting various proxy-related headers.
     * 
     * @return bool Returns true if the request is likely from proxy, false otherwise.
     */
    public function isProxy(): bool;

    /**
     * Check if the request origin matches the current application host.
     *
     * @param bool $subdomains Whether to consider subdomains or not (default: false).
     * @param bool $strict When set to true, if request origin is empty it checks for referer if also empty return false (default: false).
     *              If set to false, it checks for only the request origin is empty it returns true without further validation.
     * 
     * @return bool Returns true if the request origin matches the current host, false otherwise.
     */
    public function isSameOrigin(bool $subdomains = false, bool $strict = false): bool;

    /**
     * Validates if the given (hostname's, origins, proxy ip or subnet) matches any of the trusted patterns.
     * This will consider the defined configuration in `App\Config\Security` during validation.
     * 
     * @param string $input The domain, origin or ip address to check (e.g, `example.com`, `192.168.0.1`).
     * @param string $context The context to check input for (e.g, `hostname`).
     * 
     * @return bool Return true if the input is trusted, false otherwise.
     * @throws InvalidArgumentException If invalid context is provided.
     * 
     * Supported Context:
     * - hostname - Validates a host name.
     * - origin - Validates an origin hostname.
     * - proxy Validates an IP address or proxy.
     * 
     * @see https://luminova.ng/docs/0.0.0/functions/ip
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