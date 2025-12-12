<?php
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
use \Luminova\Luminova;
use \App\Config\Security;
use \Luminova\Http\Network\IP;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\StreamInterface;
use \Luminova\Http\Helper\RequestTrait;
use \Psr\Http\Message\UploadedFileInterface;
use \Luminova\Http\{Header, Server, UserAgent};
use \Luminova\Http\Attribution\{UTM, UTMClient};
use \Luminova\Exceptions\{InvalidArgumentException, SecurityException};
use \Luminova\Interface\{Arrayable, RequestInterface, LazyObjectInterface, CookieJarInterface};

/**
 * Dynamic methods for accessing HTTP request fields by method.
 *
 * Each method returns the value of a specific field for the HTTP request method,
 * or all fields if `$field` is `null`.
 *
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
     * Target request URI+Query string.
     * 
     * @var string $uriQueryString
     */
    private string $uriQueryString = '/';

    /**
     * Parsed request body.
     * 
     * @var array<string,array> $parsedBody
     */
    private array $parsedBody = [];

    /**
     * Optional request attributes.
     * 
     * @var array<string,mixed> $attributes
     */
    private array $attributes = [];

    /**
     * Raw request contents.
     * 
     * @var string|null $row
     */
    private ?string $raw = null;

    /**
     * Server object representing HTTP server parameters.
     * 
     * @var Server<LazyObjectInterface>|null $server
     */
    public ?LazyObjectInterface $server = null;

    /**
     * Header object providing HTTP request headers information.
     * 
     * @var Header<LazyObjectInterface>|null $header
     */
    public ?LazyObjectInterface $header = null;

    /**
     * UserAgent object containing client browser details.
     * 
     * @var UserAgent<LazyObjectInterface>|null $agent
     */
    public ?LazyObjectInterface $agent = null;

    /**
     * Request security configuration.
     *
     * @var Security|null $security
     */
    private static ?Security $security = null;

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
     * Request helpers.
     */
    use RequestTrait;

    /**
     * Create a new HTTP request representing an incoming client request.
     *
     * @param string|null $method The HTTP method (e.g., `GET`, `Luminova\Http\Method::POST`).
     * @param UriInterface|string|null $uri The request URI. Can be a `UriInterface` instance or a string containing path and query.
     * @param StreamInterface|array $body Request body. Can be a PSR-7 stream or an associative array (form data, JSON, etc.).
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
        if(!self::$instance instanceof RequestInterface){
            self::$instance = new self(
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

        return self::$instance;
    }

    /**
     * Get a value from any HTTP request method.
     *
     * @param string $method HTTP request body key (e.g, `$request->getPut('field', 'default value')`).
     * @param array $arguments Arguments as the default value (default: blank string).
     * 
     * @return mixed Return value from the HTTP request if set; otherwise, return the default value.
     * @internal
     */
    public function __call(string $method, array $arguments): mixed 
    {
        $field = $arguments[0] ?? null;

        if(!$field){
            throw new InvalidArgumentException(sprintf(
                'Method "%s()" requires a non-empty field name. Use getParsedBody() to retrieve the full request body.',
                $method
            ));
        }

        $httpMethod = strtoupper(substr($method, 3));

        if(in_array($httpMethod, Method::METHODS, true)) {
            return $this->parsedBody[$httpMethod][$field] ?? $arguments[1] ?? null;
        }

        throw new InvalidArgumentException(
            sprintf('Method "%s()" is not a supported HTTP request accessor.', $method)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * {@inheritdoc}
     */
    public function setField(string $field, mixed $value, ?string $method = null): self
    {
        if($field){
            $method = ($method === null) ? $this->getAnyMethod() : strtoupper($method);
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
            $method = ($method === null) ? $this->getAnyMethod() : strtoupper($method);
            unset($this->parsedBody[$method][$field]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getGet(string $field, mixed $default = null): mixed
    {
        return ($field === null)
            ? ($this->parsedBody['GET'] ?? []) 
            : ($this->parsedBody['GET'][$field] ?? $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getPost(string $field, mixed $default = null): mixed
    {
        return ($field === null)
            ? ($this->parsedBody['POST'] ?? []) 
            : ($this->parsedBody['POST'][$field] ?? $default);
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
        
       $method = ($method === null) ? $this->getAnyMethod() : strtoupper($method);

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

        return $body['csrf_token'] ?? $body['csrf-token'] ?? null;
    }

    /**
     * {@inheritdoc}
     * 
     * @see self::getParsedBody()
     */
    public function getParsedBody(bool $object = false): array|object
    {
        if($this->parsedBody === []){
            $this->parseRequestBody();
        }

        $method =  $this->getAnyMethod();
        $result = array_merge(
            $this->parsedBody[$method] ?? $this->parsedBody,
            $this->files ?: $_FILES
        );

        if(!$object){
            return $result;
        }
        
        return (object) $result;
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
            $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : $default;
        }catch(JsonException){
            return [$result];
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     * 
     * @since 3.4.0
     */
    public function getCookie(?string $name = null): CookieJarInterface
    {
        if($this->cookie instanceof CookieJarInterface){
            return $this->cookie;
        }

        $this->cookie = $this->parseRequestCookies();
        return $name 
            ? $this->cookie->getCookie($name) 
            : $this->cookie;
    }

    /**
     * {@inheritdoc}
     * 
     * @see self::getUploadedFiles()
     */
    public function getFile(string $name, ?int $index = null): Generator|UploadedFileInterface|null
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
        $override = $this->header->get('X-HTTP-Method-Override') 
            ?? $this->server->get('X-HTTP-Method-Override') ;

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
        $type = $this->header->get('Content-Type') ?? $this->server->get('CONTENT_TYPE');

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
        $auth = $this->header->get('Authorization')
            ?? $this->server->get('HTTP_AUTHORIZATION')
            ?? $this->server->get('REDIRECT_HTTP_AUTHORIZATION');

        return $auth ? trim($auth ?? '') : null;
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
    public function getQuery(?string $name = null, mixed $default = null): mixed
    {
        $params = $this->getQueryParams();

        if(!$params){
            return $default;
        }

        return ($name === null) 
            ? http_build_query($params, '', '&', PHP_QUERY_RFC3986)
            : $params[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams(): array
    {
        if ($this->queryParams !== null) {
            return $this->queryParams;
        }

        $query = $_SERVER['QUERY_STRING'] ?? '';

        if ($query === '') {
            return $this->queryParams = [];
        }

        $params = [];

        parse_str($query, $params);
        ksort($params);

        return $this->queryParams = (array) $params;
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
        $host = $this->getHostname(port: $withPort);
        $uri = (string) $this->detectRequestUri(true);
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
     */
    public function getHost(bool $exception = false): ?string
    {
        return $this->getHostname($exception, false);
    }

    /**
     * {@inheritdoc}
     */
    public function getHostname(bool $exception = false, bool $port = true): ?string
    {
        $hostname = $this->header->get('Host')
            ?? $this->server->get('HTTP_HOST')
            ?? $this->server->get('HOST')
            ?? $this->server->get('SERVER_NAME')
            ?? $this->server->get('SERVER_ADDR');

        if(!$hostname){
            return '';
        }
    
        $hostname = trim($hostname);

        // Remove any port number from the hostname
        if(!$port){
            $hostname = strtolower(preg_replace('/:\d+$/', '', $hostname));
        }

        $error = 'Invalid Hostname "%s".';
        // Remove any unwanted characters from the hostname
        if($hostname && preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $hostname) === ''){
            if(self::isTrusted($hostname, 'hostname')){
                return $hostname;
            }

            $error = 'Untrusted Hostname "%s".';
        }
    
        if($exception){
            throw new SecurityException(sprintf($error, $hostname));
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getOrigin(bool $validate = false): ?string
    {
        $origin = $this->header->get('Origin') ?? $this->server->get('HTTP_ORIGIN');

        if (!$origin || !$validate) {
            return $origin;
        }

        self::initRequestSecurityConfig();
        if(self::$security->trustedOrigins === []){
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
        $port = $this->header->get('X-Forwarded-Port') 
            ?? $this->server->get('HTTP_X_FORWARDED_PORT') 
            ?? $this->server->get('SERVER_PORT');

        if (!$port) {
            return $this->isSecure() ? 443 : 80;
        }

        $pos = str_starts_with($port, '[') 
            ? strpos($port, ':', strrpos($port, ']')) 
            : strrpos($port, ':');

        return ($pos !== false) 
            ? (int) substr($port, $pos + 1) 
            : ($this->isSecure() ? 443 : 80);
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
        if(!($this->agent instanceof UserAgent) && !($this->agent instanceof LazyObjectInterface)){
            $this->agent = new UserAgent();
        }

        return $this->agent;
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
        
        $method = $method ? strtoupper($method) : $this->getAnyMethod();

        return isset($this->parsedBody[$method]) && 
            array_key_exists($field, $this->parsedBody[$method] ?? []);
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
    public function isMethod(string $method = 'GET'): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * {@inheritdoc}
     */
    public function isAuth(string $type = 'Bearer'): bool 
    {
        if(!$type){
            return false;
        }

        $auth = $this->getAuth();

        if (!$auth) {
            return false;
        }

        $name = explode(' ', $auth, 2)[0] ?? '';

        return ($name === '') ? false : strcasecmp($name, $type) === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isSecure(): bool
    {
        return ($this->server->get('HTTPS') !== 'off' || $this->server->get('SERVER_PORT') === 443);
    }

    /**
     * {@inheritdoc}
     */
    public function isAjax(): bool
    {
        $ajax = $this->header->get('X-Requested-With') 
            ?? $this->server->get('HTTP_X_REQUESTED_WITH', '');

        return $ajax && strcasecmp($ajax, 'XMLHttpRequest') === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function isApi(?bool $ajaxAsApi = null): bool
    {
        return Luminova::isApiPrefix($ajaxAsApi);
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
            throw new InvalidArgumentException(sprintf('Invalid Context name: "%s".', $context));
        }

        if($context === 'proxy'){
            return IP::isTrustedProxy($input);
        }

        self::initRequestSecurityConfig();
        $trusted = ($context === 'hostname') 
            ? self::$security->trustedHostnames 
            : self::$security->trustedOrigins;

        if(!$trusted){
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
        return self::isTrusted($this->server->get('REMOTE_ADDR', ''), 'proxy');
    }

    /**
     * {@inheritdoc}
     */
    public function isTrustedOrigin(): bool
    {
        $origin = $this->header->get('Origin') ?? $this->server->get('HTTP_ORIGIN');

        if (!$origin) {
            return false;
        }

        self::initRequestSecurityConfig();
        if(self::$security->trustedOrigins === []){
            return true;
        }

        $domain = parse_url($origin, PHP_URL_HOST);

        if(!$domain) {
            return false;
        }
        
        return self::isTrusted($domain, 'origin');
    }
}