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
namespace Luminova\Interface;

use \Generator;
use Luminova\Http\Uri;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\StreamInterface;
use Luminova\Http\Attribution\UTMClient;
use Luminova\Exceptions\RuntimeException;
use Luminova\Interface\CookieJarInterface;
use \Psr\Http\Message\UploadedFileInterface;
use Luminova\Http\{Server, Header, UserAgent};
use Luminova\Exceptions\{SecurityException, InvalidArgumentException};

/**
 * Dynamic methods for accessing HTTP request fields by method.
 *
 * Each method returns the value of a specific field for the HTTP request method,
 * or all fields if `$field` is `null`.
 *
 * @method array|object all(bool $object = false)                   Return all request fields as array or object.
 * @method mixed getPut(string $field, mixed $default = null)       Get value from PUT request.
 * @method mixed getOptions(string $field, mixed $default = null)   Get value from OPTIONS request.
 * @method mixed getPatch(string $field, mixed $default = null)     Get value from PATCH request.
 * @method mixed getHead(string $field, mixed $default = null)      Get value from HEAD request.
 * @method mixed getConnect(string $field, mixed $default = null)   Get value from CONNECT request.
 * @method mixed getTrace(string $field, mixed $default = null)     Get value from TRACE request.
 * @method mixed getPropfind(string $field, mixed $default = null)  Get value from PROPFIND request.
 * @method mixed getMkcol(string $field, mixed $default = null)     Get value from MKCOL request.
 * @method mixed getCopy(string $field, mixed $default = null)      Get value from COPY request.
 * @method mixed getMove(string $field, mixed $default = null)      Get value from MOVE request.
 * @method mixed getLock(string $field, mixed $default = null)      Get value from LOCK request.
 * @method mixed getUnlock(string $field, mixed $default = null)    Get value from UNLOCK request.
 * @method mixed getAny(string $field, mixed $default = null)       Get value from any request method.
 *
 * @property-read Server $server HTTP server properties.
 * @property-read Header $header HTTP request headers.
 * @property-read UserAgent $userAgent Client user-agent and browser info.
 */
interface RequestInterface extends \Psr\Http\Message\RequestInterface
{
    /**
     * Get a shared HTTP request instance representing the incoming client request.
     *
     * @param string|null $method The HTTP request method, such as `GET` or `POST`.
     * @param UriInterface|string|null $uri The request URI as a PSR-7 URI instance or string.
     * @param StreamInterface|array $body The request body as a PSR-7 stream or associative array.
     * @param array $files Uploaded files as PSR-7 `UploadedFileInterface` instances or an array.
     * @param array<string,mixed>|null $cookies Request cookies. Defaults to `$_COOKIE`.
     * @param array<string,mixed>|null $serverParams Server parameters. Defaults to `$_SERVER`.
     * @param array<string,mixed>|null $headers HTTP headers. If omitted, headers are extracted
     *        from `apache_request_headers()` or `$_SERVER`.
     * @param string|null $protocolVersion The HTTP protocol version.
     *
     * @return RequestInterface Returns a new or shared request instance.
     *
     * @link https://luminova.ng/docs/0.0.0/http/request
     *
     * > Uploaded files are normalized to PSR-7 `UploadedFileInterface` instances.
     */
    public static function getInstance(
        ?string $method = null,
        UriInterface|string|null $uri = null,
        StreamInterface|array $body = [],
        array $files = [],
        ?array $cookies = null,
        ?array $serverParams = null,
        ?array $headers = null,
        ?string $protocolVersion = null
    ): self;

    /**
     * Capture the incoming HTTP request.
     *
     * @param bool $shared Whether to return the shared request instance.
     *
     * @return RequestInterface Returns a new or shared request instance.
     *
     * @link https://luminova.ng/docs/0.0.0/http/request
     */
    public static function capture(bool $shared = true): self;

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
     * Remove a specific field from the request body for the given HTTP method.
     * 
     * @param string $field The name of the field to remove.
     * @param string|null $method Optional HTTP method, if null the current request method will be used (e.g, `GET`, `POST`).
     * 
     * @return self Returns the instance request class.
     */
    public function removeField(string $field, ?string $method = null): self;

    /**
     * Get a field value from HTTP GET request or entire fields if `$field` param is null.
     *
     * @param string $field The input field name to retrieve the value value from.
     * @param mixed $default An optional default value to return if the key is not found (default: null).
     * 
     * @return mixed Return the value from HTTP request method body based on key.
     */
    public function getGet(string $field, mixed $default = null): mixed;

    /**
     * Get a field value from HTTP POST request or entire fields if `$field` param is null.
     *
     * @param string $field The input field name to retrieve the value value from.
     * @param mixed $default An optional default value to return if the key is not found (default: null).
     * 
     * @return mixed Return the value from HTTP request method body based on key.
     */
    public function getPost(string $field, mixed $default = null): mixed;

    /**
     * Get a field value from any valid HTTP request method.
     * 
     * If `$method` is provided, it checks field in the specified HTTP method, 
     * otherwise it use the current request method.
     * 
     * Alias {@see getAny()}
     *
     * @param string $field The input field name to retrieve the value value from.
     * @param mixed $default An optional default value to return if the field is not found (default: null).
     * @param string|null $method Optional HTTP method to retrieve valid from (default: `null` as `ANY`).
     * 
     * @return mixed Return the value from HTTP request method body based on field name or default value.
     */
    public function input(string $field, mixed $default = null, ?string $method = null): mixed;

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
    public function getParsedBody(bool $object = false): array|object;

    /**
     * Get the request body as a PSR-7 stream.
     *
     * Converts the request body into a StreamInterface. If the body is already
     * a StreamInterface, it is returned as-is. Otherwise, the array body is
     * encoded as an RFC3986-compliant query string.
     *
     * @return StreamInterface Returns request body as stream.
     * @throws RuntimeException If failed to open temporary stream.
     */
    public function getBody(): StreamInterface;

    /**
     * Retrieve the raw HTTP request body.
     *
     * This method returns the unprocessed request body as a string.
     * It is useful for reading JSON payloads, XML, or other raw input
     * directly from the client. Returns null if the body is empty.
     *
     * @return string|null Returns the raw request content or null if empty.
     */
    public function getRaw(): ?string;

     /**
     * Retrieve the CSRF token from the current request.
    *
    * Checks the parsed request body for the configured CSRF input name
    * and `csrf-token`, then falls back to the `X-CSRF-Token` request header.
    *
    * @return string|null Return the CSRF token if found, otherwise null.
    *
    * @see \Luminova\Security\CSRF::validate() To validate the CSRF token.
    */
    public function getCsrfToken(): ?string;

    /**
     * Get UTM parameter data from the request.
     * 
     * @param string|null $param The specific UTM parameter to retrieve (e.g., 'utm_source', 'utm_medium'). 
     *                           If null, returns all standard UTM param.
     * @param bool $persist Whether to persist UTM data in configured storage (default: false).
     * 
     * @return UTMClient|null Returns an instance of UTMClient containing the UTM data, or null if not available.
     * 
     * @see \Luminova\Components\Campaign\UTM - UTM handler class.
     * @see UTMClient - UTM data client class.
     */
    public function getUtmParam(?string $param = null, bool $persist = false): ?UTMClient;

    /**
     * Retrieve an array of request body fields.
     * 
     * This method extract all keys from request body.
     * 
     * @return array<int,string> Return an array list request fields.
     */
    public function getFields(): array;

    /**
     * Get an uploaded file or a generator for multiple uploaded files.
     *
     * @param string $name The file input field name.
     * @param int|null $index The optional file index for multiple files. If null,
     *                        a generator yielding all matching files is returned.
     *
     * @return Generator<int,UploadedFileInterface,void,void>|UploadedFileInterface|null
     *         An uploaded file, a generator yielding uploaded files, or null if
     *         the input field was not found.
     *
     * @link https://luminova.ng/docs/0.0.0/http/file-object
     * @see https://luminova.ng/docs/0.0.0/http/file-uploader
     */
    public function getFile(
        string $name,
        ?int $index = null
    ): Generator|UploadedFileInterface|null;

    /**
     * Get the raw uploaded file information.
     *
     * Returns the uploaded file data without modification.
     *
     * @return array<int,UploadedFileInterface> The uploaded file information.
     *
     * @see https://luminova.ng/docs/0.0.0/http/file-uploader
     */
    public function getFiles(): array;

    /**
     * Get the request cookie jar.
     *
     * The cookie jar is populated with cookies parsed from the request headers.
     *
     * @param string|null $name Optional cookie name to pre-initialize.
     *
     * @return CookieJarInterface The request cookie jar.
     *
     * @link https://luminova.ng/docs/0.0.0/cookies/cookie-file-jar
     */
    public function getCookie(?string $name = null): CookieJarInterface;

    /**
     * Retrieves actual HTTP method if provided by the client.
     *
     * @return string Return the HTTP method in uppercase.
     */
    public function getMethod(): string;

    /**
     * Returns the HTTP method of the request or method overrides.
     *
     * If the original request method is POST and a method override is present
     * (e.g., via `_method` field or `X-HTTP-Method-Override` header), the overridden
     * method is returned instead. This is useful for supporting RESTful methods
     * when method is spoofed from POST requests.
     *
     * @return string Return the HTTP method (e.g., GET, POST, PUT, PATCH, DELETE).
     */
    public function getAnyMethod(): string;

    /**
     * Retrieves the HTTP method override if provided by the client.
     *
     * This method checks for the "X-HTTP-Method-Override" value first in the request headers. 
     * If found, the override method is returned in uppercase.
     *
     * @return string|null Return the overridden HTTP method in uppercase, or null if not set.
     */
    public function getMethodOverride(): ?string;

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
     * @return array{params:array,files:array} Return an array containing {param:array,files:array}:
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
     * Get the authorization credentials without the authentication scheme prefix.
     *
     * Removes the authentication scheme (e.g., `Bearer`, `Basic`, or custom schemes)
     * from the Authorization header and returns only the credential value.
     *
     * @return string|null The authorization credential or null if unavailable.
     * 
     * @see self::isAuth()
     * @see self::getAuthorization()
     *
     * @example - Example:
     * ```php
     * Authorization: 'Bearer token123'
     * echo $request->getAuth(); // token123
     * ```
     */
    public function getAuth(): ?string;

    /**
     * Get the raw Authorization header value.
     *
     * Retrieves the Authorization header from the request headers, including
     * fallback sources used by some web servers and proxy configurations.
     *
     * The returned value may include the authentication scheme prefix.
     *
     * @return string|null The raw authorization header value or null if unavailable.
     * 
     * @see self::isAuth()
     * @see self::getAuth()
     * 
     * @example - Example:
     * ```php
     * Authorization: 'Bearer token123'
     * echo $request->getAuthorization(); // Bearer token123
     * ```
     */
    public function getAuthorization(): ?string;
    
    /**
     * Retrieves a query parameter from the request.
     *
     * The parameter is resolved from the parsed query parameters, including
     * parameters supplied through the HTTP `QUERY` method when supported.
     *
     * @param string $field The query parameter name to retrieve.
     * @param mixed $default The value to return when the parameter does not exist.
     *
     * @return mixed The query parameter value, or `$default` when not found.
     *
     * @see self::getQueryParams()
     */
    public function getQuery(string $field, mixed $default = null): mixed;

    /**
     * Retrieves all query parameters from the request.
     *
     * Query parameters may be provided through the URL query string or the
     * HTTP `QUERY` method. When the same parameter exists in both sources,
     * the `QUERY` method value takes precedence.
     *
     * @return array<string,mixed> The parsed query parameters.
     *
     * @see self::getQuery()
     * @see self::getQueryString()
     */
    public function getQueryParams(): array;

    /**
     * Retrieves the request query parameters as an encoded query string.
     *
     * The returned string is encoded using RFC 3986 and can be used as a
     * URL query string.
     *
     * @return string The RFC 3986 encoded query string.
     *
     * @see self::getQueryParams()
     */
    public function getQueryString(): string;

    /**
     * Get the full URL of the current request.
     *
     * This method returns the complete URL, including the protocol (e.g., http or https),
     * the domain name, the path, and any query string parameters.
     * 
     * @param bool $withPort Whether to return hostname with port (default: false).
     *
     * @return string Return the full URL of the request.
     */
    public function getUrl(bool $withPort = false): string;

    /**
     * Get the URI (path and query string) of the current request (e.g, `/foo/bar?query=123`).
     *
     * This method returns only the URI, which includes the path and query string, 
     * but excludes the protocol and domain name. 
     *
     * @return Uri<UriInterface> Return the URI of the request (path and query string).
     */
    public function getUri(): UriInterface;

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
     * Get the current request hostname without the port.
     *
     * If allowed hosts are configured, the hostname is validated against
     * the allowed hosts or patterns.
     *
     * @param bool $exception Whether to throw an exception when the hostname
     *                        is invalid or not allowed.
     *
     * @return string|null The request hostname, or null if it cannot be determined.
     *
     * @throws SecurityException If the hostname is invalid or not allowed and
     *                           exception throwing is enabled.
     */
    public function getHost(bool $exception = false): ?string;

    /**
     * Get the current request hostname, optionally including the port.
     *
     * If allowed hosts are configured, the hostname is validated against
     * the allowed hosts or patterns.
     *
     * @param bool $exception Whether to throw an exception when the hostname
     *                        is invalid or not allowed.
     * @param bool $port Whether to include the port when available.
     *
     * @return string|null The request hostname, optionally with its port.
     *
     * @throws SecurityException If the hostname is invalid or not allowed and
     *                           exception throwing is enabled.
     */
    public function getHostname(bool $exception = false, bool $port = true): ?string;

    /**
     * Get the request origin domain.
     * 
     * It validates origin, if `$validate` is `true` and list of trusted origin domains are defined 
     * in application security configuration class {@see App\Config\Security}, 
     * it will check if the origin is a trusted origin domain.
     * 
     * @param bool $validate Wether to validate request origin (default: false).
     * 
     * @return string|null Return the request origin domain if found and trusted, otherwise null.
     */
    public function getOrigin(bool $validate = false): ?string;

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
     * @return string Return Request protocol name and version, if available, otherwise default is return `HTTP/1.1`.
    */
    public function getProtocol(): string;

    /**
     * Get the HTTP protocol version used in the request.
     *
     * @return string Returns the HTTP protocol version (e.g., "1.1", "2.0").
     */
    public function getProtocolVersion(): string;
 
    /**
     * Get the request browser name and platform from user-agent information.
     * 
     * @return string Return browser name and platform.
     */
    public function getBrowser(): string;

    /**
     * Get request browser user-agent information.
     * 
     * The User Agent string is driven from request header (`HTTP_USER_AGENT`).
     * 
     * @return UserAgent Return instance user-agent class containing browser information.
     * @link https://luminova.ng/docs/0.0.0/http/user-agent
     */
    public function getUserAgent(): UserAgent;

    /**
     * Retrieve the HTTP referer in a safe and controlled way.
     *
     * This method reads the `Referer` header and validates it before use.
     * It protects against malformed URLs, non-HTTP schemes, and open
     * redirect vulnerabilities.
     *
     * By default, only same-origin referers are allowed. This prevents
     * redirecting users to external or untrusted domains.
     *
     *
     * @param bool $sameOrigin When true (default), only allow referers that
     *                         match the current host.
     *
     * @return string|null Returns a sanitized referer URL, or null if the
     *                     referer is missing, invalid, or not allowed.
     * 
     * > **Note:**
     * > The HTTP Referer header is optional and can be spoofed. Never treat
     * > it as a security guarantee. Always provide a fallback when it is missing.
     */
    public function getReferer(bool $sameOrigin = true): ?string;

    /**
     * Check if the request method is GET.
     *
     * @return bool Returns true if the request method is GET, false otherwise.
     */
    public function isGet(): bool;

    /**
     * Check if the request method is QUERY.
     *
     * @return bool Returns true if the request method is QUERY, false otherwise.
     */
    public function isQuery(): bool;

    /**
     * Check if the request method is POST.
     *
     * @return bool Returns true if the request method is POST, false otherwise.
     */
    public function isPost(): bool;

    /**
     * Checks whether the request method matches one of the provided methods.
     *
     * Supports checking against a single HTTP method or multiple methods.
     * Method names are compared case-insensitively after normalization.
     *
     * @param array<string>|string $method The method or list of methods to check against
     * (e.g., `POST`, `Luminova\Http\Method::GET`, or `[POST, PUT]`).
     *
     * @return bool Returns true if the request method matches any provided method,
     * false otherwise.
     */
    public function isMethod(array|string $method = 'GET'): bool;

    /**
     * Check if the Authorization header matches the specified type.
     *
     * @param string $scheme The expected scheme type of authorization (e.g., 'Bearer', 'Basic').
     *
     * @return bool Returns true if the Authorization header matches the specified type, otherwise false.
     * 
     * @see self::getAuth()
     * @see self::getAuthorization()
     */
    public function isAuth(string $scheme = 'Bearer'): bool;

    /**
     * Check if the current connection is secure
     * 
     * @return bool Return true if the connection is secure false otherwise.
     */
    public function isSecure(): bool;

    /**
     * Determine if the current request is a GraphQL request.
     *
     * This method checks the parsed request body for the presence of a GraphQL query.
     * It validates that:
     * - The `query` key exists and is a non-empty string.
     * - The `query` is not a pure JSON string.
     * - The query contains `(` and `{` and ends with `}` to roughly match GraphQL syntax.
     *
     * @return bool Returns true if the request appears to be a GraphQL query, false otherwise.
     */
    public function isGraphQL(): bool;

    /**
     * Determine whether the current request targets an API endpoint.
     *
     * The request is considered an API request when its URI path uses the
     * configured API prefix, such as `/api` or `/public/api`.
     *
     * @param bool|null $ajaxAsApi Whether to treat XMLHttpRequest (AJAX)
     *                             requests as API requests. If null, the
     *                             default value from `env(app.validate.ajax.asapi)`
     *                             is used.
     *
     * @return bool True if the request targets an API endpoint, otherwise false.
     * 
     * @see self::isAjax()
     */
    public function isApi(?bool $ajaxAsApi = null): bool;

    /**
     * Determine whether the current request is an AJAX request.
     *
     * Checks whether the request contains the `X-Requested-With` HTTP header
     * with the value `XMLHttpRequest`.
     *
     * @return bool True if the request is an AJAX request, otherwise false.
     * 
     * @see self::isApi()
     */
    public function isAjax(): bool;

    /**
     * Determine whether the request was made through PJAX.
     *
     * A PJAX request includes the `X-PJAX` request header.
     *
     * @return bool Returns true if the request is a PJAX request, otherwise false.
     */
    public function isPjax(): bool;

    /**
     * Determine whether the request was made for prefetching.
     *
     * Checks the `X-Moz` and `Purpose` request headers for the `prefetch` value.
     *
     * @return bool Returns true if the request is a prefetch request, otherwise false.
     */
    public function isPrefetch(): bool;

    /**
     * Determine whether the request was made through XMLHttpRequest.
     *
     * Checks the `X-Requested-With` request header for the `XMLHttpRequest` value.
     *
     * @return bool Returns true if the request is an XMLHttpRequest, otherwise false.
     */
    public function isXmlHttpRequest(): bool;

    /**
     * Check whether the request likely passed through a proxy.
     *
     * A request is considered proxied if any known proxy header
     * is present in the server environment.
     * 
     * @return bool Returns true if the request is likely from proxy, false otherwise.
     */
    public function isProxy(): bool;

    /**
     * Determine whether the request origin matches the current application host.
     *
     * When subdomains are enabled, origins using a subdomain of the current
     * application host are also considered valid.
     *
     * In strict mode, an empty `Origin` header falls back to the `Referer`
     * header. If both are unavailable, the request is considered invalid.
     * When strict mode is disabled, an empty `Origin` header is accepted.
     *
     * @param bool $subdomains Whether to allow matching subdomains.
     * @param bool $strict Whether to require origin validation when the
     *                     `Origin` header is unavailable.
     *
     * @return bool True if the request origin matches the current application
     *              host, otherwise false.
     */
    public function isSameOrigin(
        bool $subdomains = false,
        bool $strict = false
    ): bool;

    /**
     * Checks whether the current request method supports form-encoded body data.
     *
     * Common methods that support form-encoded request data include `POST`, `PUT`,
     * `PATCH`, `DELETE`, and `QUERY`.
     * 
     * @param string|null $method Optional HTTP method to check. If null, the current request
     * method is used.
     *
     * @return bool Returns true if the current request method supports form-encoded
     * body data; otherwise, false.
     */
    public function isBodySupported(?string $method = null): bool;

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

    /**
     * Retrieve the request target (e.g., path, query string).
     *
     * @return string Returns the request-target as it will appear in the HTTP request line.
     */
    public function getRequestTarget(): string;

    /**
     * Retrieve server parameters (e.g., $_SERVER values).
     *
     * @return array Returns an array of server parameters.
     */
    public function getServerParams(): array;

    /**
     * Retrieve cookies sent with the request.
     *
     * @return array Returns an array of cookies (name => value).
     */
    public function getCookieParams(): array;

    /**
     * Retrieve all HTTP headers.
     *
     * @return array Returns an array of headers (name => array of values).
     */
    public function getHeaders(): array;

    /**
     * Get the client's IP address from the request.
     *
     * @return string Returns the client's IP address.
     */
    public function getClientIp(): string;

    /**
     * Get all detected client and proxy IP addresses from the request.
     *
     * @return array<int, string> Returns all detected client and proxy IP addresses.
     */
    public function getClientIps(): array;

    /**
     * Checks whether a field exists in the request body.
     *
     * The field is checked against the parsed body of the specified HTTP method.
     * If no method is provided, the current request method is used.
     *
     * @param string $field The name of the field to check.
     * @param string|null $method The HTTP method to check against (e.g., `POST`, `PUT`).
     * If null, the current request method is used.
     *
     * @return bool Returns true if the field exists; otherwise, false.
     */
    public function hasField(string $field, ?string $method = null): bool;

    /**
     * Check if a specific HTTP header is present.
     *
     * @param string $name Header name (case-insensitive).
     * 
     * @return bool Returns true if header exists, false otherwise.
     */
    public function hasHeader(string $name): bool;

    /**
     * Checks whether a query parameter exists in the request.
     *
     * Query parameters are checked from the URL query string and HTTP `QUERY`
     * method payload when available.
     *
     * @param string $name The query parameter name to check.
     *
     * @return bool Returns true if the query parameter exists; otherwise, false.
     */
    public function hasQuery(string $name): bool;
    
    /**
     * Checks whether an uploaded file exists in the request.
     *
     * The file is checked against the uploaded files collection using the provided
     * field name.
     *
     * @param string $name The uploaded file field name to check.
     *
     * @return bool Returns true if the file exists; otherwise, false.
     */
    public function hasFile(string $name): bool;

    /**
     * Return a new instance with the specified HTTP status code and reason phrase.
     *
     * @param int $code HTTP status code.
     * @param string $reasonPhrase Optional reason phrase; defaults to standard if empty.
     * 
     * @return self Returns a new request instance with updated status.
     * @throws InvalidArgumentException If the HTTP status code is invalid.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): self;

    /**
     * Return a new instance without a specific HTTP header.
     *
     * @param string $name Header name to remove.
     * 
     * @return self Returns a new request instance with the header removed.
     */
    public function withoutHeader(string $name): self;

    /**
     * Return a new instance with a specific HTTP header.
     *
     * @param string $name Header name.
     * @param string|array $value Header value(s).
     * 
     * @return self Returns a new request instance with updated header.
     */
    public function withHeader(string $name, $value): self;

    /**
     * Return a new instance with a specific HTTP protocol version.
     *
     * @param string $version Protocol version (e.g., "1.1", "2.0").
     * 
     * @return self Returns a new request instance with updated protocol version.
     */
    public function withProtocolVersion(string $version): self;

    /**
     * Return a new instance with an additional HTTP header value.
     *
     * @param string $name Header name.
     * @param string|array $value Header value(s) to append.
     * 
     * @return self Returns a new request instance with added header value.
     */
    public function withAddedHeader(string $name, $value): self;

    /**
     * Return a new instance with the provided message body.
     *
     * @param StreamInterface $body Stream representing the request body.
     * 
     * @return self Returns a new request instance with updated body.
     */
    public function withBody(StreamInterface $body): self;

    /**
     * Return a new instance with a specific HTTP method.
     *
     * @param string $method HTTP method (e.g., GET, POST).
     * 
     * @return self Returns a new request instance with updated method.
     * @throws InvalidArgumentException If the method is empty.
     */
    public function withMethod(string $method): self;

    /**
     * Return a new instance with a specific request target.
     *
     * @param string $requestTarget The request-target (path and query).
     * 
     * @return self Returns a new request instance with updated request target.
     */
    public function withRequestTarget(string $requestTarget): self;

    /**
     * Return a new instance with a specific URI.
     *
     * @param UriInterface $uri The URI to use.
     * @param bool $preserveHost Whether to preserve the original Host header.
     * 
     * @return self Returns a new request instance with updated URI.
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): self;
}