<?php
declare(strict_types=1);
/**
 * Luminova Framework Incoming HTTP Request Handler Class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Generator;
use \Stringable;
use \JsonException;
use Luminova\Luminova;
use Luminova\Config\Env;
use \App\Config\Security;
use Luminova\Security\CSRF;
use Luminova\Http\Network\IP;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\StreamInterface;
use Luminova\Http\Helper\RequestTrait;
use \Psr\Http\Message\UploadedFileInterface;
use Luminova\Http\{Header, Server, UserAgent};
use Luminova\Http\Attribution\{UTM, UTMClient};
use Luminova\Exceptions\SecurityException;
use Luminova\Interface\{
    Arrayable, 
    RequestInterface, 
    CookieJarInterface,
    LazyObjectInterface
};

/**
 * Dynamic properties for accessing HTTP request data.
 * 
 * @property-read Server $server Server object representing HTTP server parameters.
 * @property-read Header $header Header object providing HTTP request headers information.
 * @property-read UserAgent $userAgent UserAgent object containing client browser details.
 *
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
 */
final class Request implements RequestInterface, LazyObjectInterface, Stringable, Arrayable
{
    /**
     * URL query parameters.
     * 
     * @var array<string,mixed>|null $queryParams
     */
    private ?array $queryParams = null;

    /**
     * Parsed request body.
     * 
     * @var array<string,array> $parsedBody
     */
    private array $parsedBody = [];
    
    /**
     * Request cookie jar object.
     * 
     * @var CookieJarInterface|null $cookie
     */
    private ?CookieJarInterface $cookie = null;

    /**
     * Shared object.
     * 
     * @var RequestInterface|null $instance
     */
    private static ?RequestInterface $instance = null;

    /**
     * Request port.
     *
     * @var int|null
     */
    private ?int $port = null;

    /**
     * Request helpers.
     */
    use RequestTrait;

    /**
     * Create a new HTTP request representing an incoming client request.
     *
     * @param string|null $method The HTTP method (e.g., `GET`, `Luminova\Http\Method::POST`).
     * @param UriInterface|string|null $uri The request URI. Can be a `UriInterface` 
     *          instance or a string containing path and query.
     * @param StreamInterface|array $body Request body. Can be a PSR-7 stream 
     *          or an associative array (form data, JSON, etc.).
     * @param UploadedFileInterface[]|array<string,array> $files Uploaded files object or an associative array (e.g., `['image' => [...]]`).
     * @param array<string,mixed>|null $cookies Optional cookies (`$_COOKIE`).
     * @param array<string,mixed>|null $serverParams Optional server parameters (`$_SERVER`).
     * @param array<string,mixed>|null $headers Optional HTTP headers. 
     *                  If not provided, they will be extracted from `apache_request_headers()` or `$_SERVER`.
     * @param string|null $protocolVersion HTTP protocol version (default: `null`).
     *
     * @link https://luminova.ng/docs/0.0.0/http/request
     * 
     * > Files are normalized to PSR-7 `UploadedFileInterface` instances.
     */
    public function __construct(
        private ?string $method = null,
        private UriInterface|string|null $uri = null,
        private StreamInterface|array $body = [],
        private array $files = [],
        private ?array $cookies = null,
        private ?array $serverParams = null,
        private ?array $headers = null,
        private ?string $protocolVersion = null
    ) 
    {
        $this->parseRequestUrl();
        $this->parseRequestBody();
        $this->uriQueryString = $this->buildRequestTarget();
    }

    /**
     * {@inheritdoc}
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
    ): self 
    {
        static $singleton;

        if(!$singleton instanceof RequestInterface){
            $singleton = new self(
                $method,
                $uri,
                $body,
                $files,
                $cookies,
                $serverParams,
                $headers,
                $protocolVersion
            );
        }

        return $singleton;
    }

    /**
     * {@inheritdoc}
     */
    public static function capture(bool $shared = true): self 
    {
        if(!$shared){
            return new self();
        }

        if(!self::$instance instanceof RequestInterface){
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Returns the data to be serialized.
     *
     * @return array{
     *      method:string,
     *      uri:string,
     *      body:array,
     *      files:array,
     *      cookies:?array,
     *      serverParams:?array,
     *      headers:?array,
     *      protocolVersion:?string
     * } Returns the data to be serialized.
     */
    public function __serialize(): array
    {
        return [
            'method' => $this->method,
            'uri' => (string) $this->uri,
            'body' => $this->body,
            'files' => $this->files,
            'cookies' => $this->cookies,
            'serverParams' => $this->serverParams,
            'headers' => $this->headers,
            'protocolVersion' => $this->protocolVersion
        ];
    }

    /**
     * Re-parse the request URL and body after unserialization
     *
     * @param array $data The data to unserialize.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->method = $data['method'] ?? null;
        $this->uri = $data['uri'] ?? null;
        $this->body = $data['body'] ?? [];
        $this->files = $data['files'] ?? [];
        $this->cookies = $data['cookies'] ?? null;
        $this->serverParams = $data['serverParams'] ?? null;
        $this->headers = $data['headers'] ?? null;
        $this->protocolVersion = $data['protocolVersion'] ?? null;

        $this->parseRequestUrl();
        $this->parseRequestBody();
        $this->uriQueryString = $this->buildRequestTarget();
    }

    /**
     * {@inheritdoc}
     */
    public function setField(string $field, mixed $value, ?string $method = null): self
    {
        if($field){
            $method = ($method === null) 
                ? $this->getAnyMethod() 
                : strtoupper($method);

            $this->parsedBody[$method][$field] = $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeField(string $field, ?string $method = null): self
    {
        if($field){
            $method = ($method === null) 
                ? $this->getAnyMethod() 
                : strtoupper($method);
                
            unset($this->parsedBody[$method][$field]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getGet(string $field, mixed $default = null): mixed
    {
        return $this->input($field, $default, 'GET');
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery(string $field, mixed $default = null): mixed
    {
        return $this->getQueryParams()[$field] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryString(): string
    {
        return http_build_query(
            $this->getQueryParams(), 
            '', 
            '&', 
            PHP_QUERY_RFC3986
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPost(string $field, mixed $default = null): mixed
    {
        return $this->input($field, $default, 'POST');
    }

    /**
     * Retrieve a value from any HTTP request method.
     *
     * @param string $field   Field name to retrieve.
     * @param mixed  $default Default value if field is missing.
     * 
     * @return mixed Returns the value of the field or default.
     */
    public function getAny(string $field, mixed $default = null): mixed
    {
        return $this->input($field, $default);
    }

    /**
     * {@inheritdoc}
     * 
     * Alias {@see self::getAny()}
     */
    public function input(string $field, mixed $default = null, ?string $method = null): mixed 
    {
        if(!$field){
            return null;
        }
        
       $method = ($method === null) 
            ? $this->getAnyMethod() 
            : strtoupper($method);

        if(!isset($this->parsedBody[$method])){
            return $default;
        }

        return $this->parsedBody[$method][$field] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getUtmParam(?string $param = null, bool $persist = false): ?UTMClient
    {
        if(!$this->isGet()){
            return null;
        }

        $client = null;
        $before = UTM::isPersistent();

        UTM::persistence($persist);
        $id = UTM::track($param);

        if($id !== null){
            $client = UTM::getClient($id);
        }

        UTM::persistence($before);

        return $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getCsrfToken(): ?string
    {
        $body = $this->parsedBody[$this->getAnyMethod()] ?? [];

        return $body[CSRF::INPUT_NAME] 
            ?? $body['csrf-token'] 
            ?? $this->header->get('X-Csrf-Token');
    }

    /**
     * {@inheritdoc}
     * 
     * @see self::getParsedBody()
     */
    public function getParsedBody(bool $asObject = false): array|object
    {
        if($this->parsedBody === []){
            $this->parseRequestBody();
        }

        $result = array_merge(
            (array) ($this->parsedBody[$this->getAnyMethod()] ?? $this->parsedBody),
            $this->files ?: $_FILES
        );

        if(!$asObject){
            return $result;
        }
        
        return (object) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getJsonrpc(?string $version = '2.0'): ?object
    {
        if (!$this->isPost()) {
            return null;
        }

        if ($this->parsedBody === []) {
            $this->parseRequestBody();
        }

        $json = $this->parsedBody['POST'] 
            ?? $this->parsedBody;

        if (
            !is_array($json)
            || !isset($json['jsonrpc'])
            || !is_string($json['jsonrpc'])
            || ($version !== null && $json['jsonrpc'] !== $version)
        ) {
            return null;
        }

        return (object) $json;
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): ?string
    {
        return file_get_contents('php://input') ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFields(): array
    {
        return array_keys($this->getParsedBody());
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->getParsedBody();
    }

    /**
     * {@inheritdoc}
     */
    public function getArray(string $field, array $default = [], ?string $method = null): array
    {
        $body = $this->getParsedBody();

        if(!$field || $body === []){
            return $default;
        }

        $result = $body[$field] ?? null;

        if ($result === null || $result === '' || $result === []) {
            return $default;
        }

        if(is_array($result)){
            return $result;
        }

        if (!is_string($result) || !json_validate($result)) {
            return [$result];
        }

        try{
            $decoded = json_decode(
                $result, 
                true, 
                512, 
                JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING
            );
            
            return is_array($decoded) ? $decoded : $default;
        }catch(JsonException){
            return [$result];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClientIp(): string 
    {
        return IP::get();
    }

    /**
     * {@inheritdoc}
     */
    public function getClientIps(): array 
    {
        return IP::all();
    }

    /**
     * {@inheritdoc}
     * 
     * @since 3.4.0
     */
    public function getCookie(): CookieJarInterface
    {
        if($this->cookie instanceof CookieJarInterface){
            return $this->cookie;
        }

        return $this->cookie = $this->parseRequestCookies();
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie(string $name): self
    {
        $this->cookie = $this->getCookie()
            ->setCookie($name);

        return $this;
    }

    /**
     * {@inheritdoc}
     * 
     * @see self::getUploadedFiles()
     */
    public function getFile(
        string $name, 
        ?int $index = null
    ): Generator|UploadedFileInterface|null
    {
        return $name ? $this->parseRequestFile(
            $this->files[$name] ?? $_FILES[$name] ?? null, 
            $index
        ) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->method ??= strtoupper($this->server->get('REQUEST_METHOD', ''));
    }

    /**
     * {@inheritdoc}
     */
    public function getMethodOverride(): ?string
    {
        $override = $this->header->get('X-Http-Method-Override') 
            ?? $this->server->get('HTTP_X_HTTP_METHOD_OVERRIDE');

        return $override ? strtoupper(trim($override)) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAnyMethod(): string
    {
        $method = $this->getMethod();

        if($method === 'POST'){
            return $this->getMethodOverride() ?? $method;
        }

        return $method;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType(): string
    {
        $type = $this->header->get('Content-Type') 
            ?? $this->server->get('CONTENT_TYPE');

        if($type){
            return $type;
        }

        if (!$this->isFormEncoded()) {
            return '';
        }

        $type = 'application/x-www-form-urlencoded';
        $this->header->set('Content-Type', $type);
        $this->server->set('CONTENT_TYPE', $type);

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuth(): ?string
    {
        $auth = $this->getAuthorization();

        if($auth === null){
            return null;
        }

        return trim(preg_replace('/^[^\s]+\s+/', '', $auth)) ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorization(): ?string
    {
        $auth = $this->header->get('Authorization')
            ?? $this->header->get('Redirect-Http-Authorization')
            ?? $this->server->get('REDIRECT_HTTP_AUTHORIZATION');

        if($auth === null){
            return $auth;
        }

        return trim($auth) ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getBoundary(): ?string
    {
        preg_match('/boundary=([^;]+)/', $this->getContentType(), $matches);
        return $matches[1] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams(): array
    {
        if ($this->queryParams !== null) {
            return $this->queryParams;
        }

        $params = (array) ($this->parsedBody['QUERY'] ?? []);
        $query = $_SERVER['QUERY_STRING'] ?? '';

        if ($query !== '') {
            $queryParams = [];

            parse_str($query, $queryParams);
            $params = [...$queryParams, ...$params];
        }

        ksort($params);

        return $this->queryParams = $params;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): UriInterface
    {
        if($this->uri instanceof UriInterface){
            return $this->uri;
        }

        return $this->uri = Uri::fromString($this->getUrl(true));
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(bool $withPort = false): string
    {
        $host = $this->getHostname(withPort: $withPort);
        $uri = $this->detectRequestUri(true);
        $uri = $host ? $uri : ltrim($uri, '/');

        return $this->getScheme() . '://' . $host . $uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaths(): string
    {
        return $this->getUri()->getPath() ?: '/';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestUri(): string
    {
        return $this->server->get('REQUEST_URI', '');
    }

    /**
     * {@inheritdoc}
     * 
     * @see self::getHostname() - To get host with port.
     */
    public function getHost(bool $exception = false): ?string
    {
        return $this->getHostname($exception, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getHostname(
        bool $exception = false,
        bool $withPort = true,
        bool $validateHost = true
    ): ?string 
    {
        $info = $this->parseHostname();

        $hostname = $info['host'] ?? '';
        $mode = $info['mode'] ?? 0;

        if (
            $mode === 1
            && (!$validateHost || self::isTrusted($hostname, 'hostname'))
        ) {
            $result = $hostname;
            $port = $info['port'];

            if ($withPort && $port !== null) {
                $result .= ":{$port}";
            }

            return $result;
        }

        if (!$exception) {
            return null;
        }

        throw new SecurityException(match($mode) {
            0 => 'Empty request hostname.',
            2 => "Invalid request hostname: '{$hostname}'.",
            default => "Untrusted request hostname: '{$hostname}'."
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getOrigin(bool $validate = false): ?string
    {
        $origin = $this->header->get('Origin') 
            ?? $this->server->get('HTTP_ORIGIN');

        if (!$origin || !$validate) {
            return $origin;
        }

        if(Security::$trustedOrigins === []){
            return $origin;
        }

        $domain = parse_url($origin, PHP_URL_HOST);

        if(!$domain){
            return null;
        }

        return self::isTrusted($domain, 'origin') ? $domain : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): int
    {
        if($this->port !== null){
            return $this->port;
        }

        $port = null;

        if($this->isTrustedProxy()){
            $port = $this->header->get('X-Forwarded-Port')
                ?? $this->server->get('HTTP_X_FORWARDED_PORT')
                ?? null;
        }

        $port ??= $this->parseHostname()['port'] 
              ?? $this->server->get('SERVER_PORT');

        if($port !== null && $port !== ''){
            $port = trim((string) $port);

            if (
                filter_var(
                    $port,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 65535]]
                ) !== false
            ) {
                return $this->port = (int) $port;
            }
        }

        return $this->port = $this->isSecure() ? 443 : 80;
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocol(): string
    {
        $protocol = $this->protocolVersion 
            ?? $this->server->get('SERVER_PROTOCOL', 'HTTP/1.1');

        if (str_starts_with($protocol, 'HTTP/')) {
            return $protocol;
        }

        return 'HTTP/' . $protocol;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string
    {
        $protocol = $this->protocolVersion ?? $this->getProtocol();

        if (str_starts_with($protocol, 'HTTP/')) {
            return substr($protocol, 5);
        }

        return $protocol;
    }
 
    /**
     * {@inheritdoc}
     */
    public function getBrowser(): string
    {
       $agent = $this->getUserAgent();
       return $agent->getBrowser() . ' on ' . $agent->getPlatform();
    }

    /**
     * {@inheritdoc}
     */
    public function getUserAgent(): UserAgent
    {
        return $this->fromReadonlyLazyProperty('userAgent');
    }

    /**
     * {@inheritdoc}
     */
    public function getReferer(bool $sameOrigin = true): ?string
    {
        $parts = null;

        if(!$this->isSameOriginFrom('referer', $sameOrigin, !$sameOrigin, $parts)){
            return null;
        }

        if (!$parts) {
            return null;
        }

        return ($parts['scheme'] ?? 'http') . '://' .
            $parts['host'] .
            ($parts['path'] ?? '/') .
            (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * {@inheritdoc}
     */
    public function hasField(string $field, ?string $method = null): bool
    {
        if(!$field || $this->parsedBody === []){
            return false;
        }
        
        $method = $method 
            ? strtoupper($method) 
            : $this->getAnyMethod();

        return isset($this->parsedBody[$method]) && 
            array_key_exists($field, $this->parsedBody[$method] ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function hasQuery(string $name): bool
    {
        return array_key_exists(
            $name, 
            $this->getQueryParams()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function hasFile(string $name): bool
    {
        return array_key_exists($name, $_FILES)
            || array_key_exists($name, $this->files);
    }

    /**
     * {@inheritdoc}
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * {@inheritdoc}
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * {@inheritdoc}
     */
    public function isQuery(): bool
    {
        return $this->getMethod() === 'QUERY';
    }

    /**
     * {@inheritdoc}
     */
    public function isMethod(string|array $method = 'GET'): bool
    {
        $requestMethod = $this->getMethod();

        if (!is_array($method)) {
            return $requestMethod === strtoupper($method);
        }

        foreach ($method as $name) {
            if ($requestMethod === strtoupper($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isAuth(string $scheme = 'Bearer'): bool 
    {
        $scheme = trim($scheme);

        if($scheme === ''){
            return false;
        }

        $auth = $this->getAuthorization();

        if ($auth === null || $auth === '') {
            return false;
        }

        $parts = preg_split('/\s+/', trim($auth), 2);

        if (count($parts) !== 2) {
            return false;
        }

        $type = trim($parts[0]);

        return $type !== ''
            && strcasecmp($type, $scheme) === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure(): bool
    {
        return $this->server->get('HTTPS') !== 'off' 
            || $this->server->get('SERVER_PORT') === 443;
    }

    /**
     * {@inheritdoc}
     */
    public function isAjax(): bool
    {
        return $this->isXmlHttpRequest();
    }

    /**
     * {@inheritdoc}
     */
    public function isPjax(): bool
    {
        $pJax = $this->header->get('X-PJAX')
            ?? $this->header->get('X-Pjax');

        return $pJax === true 
            || $pJax === 'true' 
            || $pJax === '1';
    }

    /**
     * {@inheritdoc}
     */
    public function isPrefetch(): bool
    {
        $prefetch = $this->header->get('Purpose')
            ?? $this->header->get('X-Moz')
            ?? $this->server->get('HTTP_X_REQUESTED_WITH');

        return $prefetch !== '' 
            && $prefetch !== null 
            && strcasecmp($prefetch, 'prefetch') === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isXmlHttpRequest(): bool
    {
        $ajax = $this->header->get('X-Requested-With')
            ?? $this->server->get('HTTP_X_REQUESTED_WITH');

        return $ajax !== '' 
            && $ajax !== null
            && strcasecmp($ajax, 'XMLHttpRequest') === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isApi(?bool $validateAjaxAsApi = null): bool
    {
        $segments = $this->getUri()->getPaths();

        if (
            isset($segments[0]) 
            && trim($segments[0], '/') === trim(Luminova::apiPrefix(), '/')
        ) {
            return true;
        }

        $validateAjaxAsApi ??= Env::get('app.validate.ajax.asapi', false);

        return $validateAjaxAsApi
            && $this->isAjax();
    }

    /**
     * {@inheritdoc}
     */
    public function isSameOrigin(bool $subdomains = false, bool $strict = false): bool
    {
        return $this->isSameOriginFrom('origin', $strict, $subdomains);
    }

    /**
     * {@inheritdoc}
     */
    public static function isTrusted(string $input, string $context = 'hostname'): bool
    {
        if(!in_array($context, ['hostname', 'origin', 'proxy'], true)){
            return false;
        }

        if($context === 'proxy'){
            return IP::isTrustedProxy($input);
        }

        $trusted = ($context === 'hostname') 
            ? Security::$trustedHostnames 
            : Security::$trustedOrigins;

        if($trusted === []){
            return true;
        }

        foreach ($trusted as $pattern) {
            $pattern = str_replace('\*', '.*', preg_quote($pattern, '/'));
            
            if (preg_match('/^' . $pattern . '$/', $input)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isTrustedProxy(): bool
    {
        if(Security::$trustedProxies === []){
            return true;
        }

        return IP::isTrustedProxy();
    }

    /**
     * {@inheritdoc}
     */
    public function isTrustedHost(): bool
    {
        return self::isTrusted($this->getHost(false), 'hostname');
    }

    /**
     * {@inheritdoc}
     */
    public function isTrustedOrigin(): bool
    {
        $origin = $this->header->get('Origin') 
            ?? $this->server->get('HTTP_ORIGIN');

        if (!$origin) {
            return false;
        }

        if(Security::$trustedOrigins === []){
            return true;
        }

        $domain = parse_url($origin, PHP_URL_HOST);

        if(!$domain) {
            return false;
        }

        return self::isTrusted($domain, 'origin');
    }
}