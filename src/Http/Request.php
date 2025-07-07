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

use \Luminova\Luminova;
use \Luminova\Http\Header;
use \Luminova\Http\Server;
use \Luminova\Http\File;
use \Luminova\Http\UserAgent;
use \Luminova\Cookies\CookieFileJar;
use \Luminova\Functions\IP;
use \Luminova\Functions\Func;
use \Luminova\Utils\LazyObject;
use \App\Config\Security;
use \App\Config\Files;
use \App\Config\Session;
use \Luminova\Interface\CookieJarInterface;
use \Luminova\Interface\HttpRequestInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\SecurityException;
use \Generator;
use \Stringable;
use \JsonException;
use function \Luminova\Funcs\get_mime;

/**
 * @method mixed getPut(string|null $field, mixed $default = null)
 * @method mixed getOptions(string|null $field, mixed $default = null) 
 * @method mixed getPatch(string|null $field, mixed $default = null)
 * @method mixed getHead(string|null $field, mixed $default = null) 
 * @method mixed getConnect(string|null $field, mixed $default = null)
 * @method mixed getTrace(string|null $field, mixed $default = null)
 * @method mixed getPropfind(string|null $field, mixed $default = null)
 * @method mixed getMkcol(string|null $field, mixed $default = null)
 * @method mixed getCopy(string|null $field, mixed $default = null)
 * @method mixed getMove(string|null $field, mixed $default = null)
 * @method mixed getLock(string|null $field, mixed $default = null)
 * @method mixed getUnlock(string|null $field, mixed $default = null)
 * @method mixed getAny(string $field, mixed $default = null)
 */
final class Request implements HttpRequestInterface, LazyInterface, Stringable
{
    /**
     * URL query parameters.
     * 
     * @var array<string,mixed>|false $queries
     */
    private array|bool $queries = false;

    /**
     * Server object representing HTTP server parameters and configurations.
     * 
     * @var Server<LazyInterface|null $server
     */
    public ?LazyInterface $server = null;

    /**
     * Header object providing HTTP request headers information.
     * 
     * @var Header<LazyInterface|null $header
     */
    public ?LazyInterface $header = null;

    /**
     * UserAgent object containing client browser details.
     * 
     * @var UserAgent<LazyInterface>|null $agent
     */
    public ?LazyInterface $agent = null;

    /**
     * Request security configuration.
     *
     * @var Security|null $config
     */
    private static ?Security $config = null;

    /**
     * Request cookie jar object.
     * 
     * @var CookieJarInterface|null $cookie
     */
    private ?CookieJarInterface $cookie = null;

    /**
     * Initializes a new Request object, representing an HTTP request.
     * 
     * @param string|null $method The HTTP method used for the request (e.g., `Method::GET`, `Method::POST`).
     * @param string|null $uri The request URI, typically the path and query string.
     * @param array<string,mixed> $body The request body, provided as an associative array 
     *                                  (e.g., `['field' => 'value']`). This may include form data, JSON, etc.
     * @param array<string,mixed> $files The request files, provided as an associative array (e.g., `['field' => array|string]`). 
     * @param array<string,mixed>|null $cookies Optional. An associative array of Cookies (e.g, $_COOKIE).
     * @param string|null $raw Optional request raw-body. 
     * @param array<string,mixed>|null $server Optional. Server variables (e.g., `$_SERVER`). 
     *                                         If not provided, defaults to global `$_SERVER`.
     * @param array<string,mixed>|null $headers Optional. An associative array of HTTP headers. 
     *                                          If not provided, headers request headers will be extracted from `apache_request_headers` or `$_SERVER`.
     * 
     * @link https://luminova.ng/docs/0.0.0/http/request
     */
    public function __construct(
        private ?string $method = null,
        private ?string $uri = null,
        private array $body = [],
        private array $files = [],
        private ?array $cookies = null,
        private ?string $raw = null,
        ?array $server = null,
        ?array $headers = null,
    ) {
        $this->parseRequestUrl($server, $headers);
        $this->parseRequestBody();
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $method, array $arguments): mixed 
    {
        $field = $arguments[0] ?? null;
        $httpMethod = strtoupper(substr($method, 3));

        if($httpMethod === 'ANY' && !$field){
            throw new InvalidArgumentException('The method: "getAny()" requires a valid field name.');
        }
        
        $httpMethod = ($httpMethod === 'ANY') ? $this->getAnyMethod() : $httpMethod;
        $body = $this->body[$httpMethod] ?? [];

        return ($field === null) 
            ? $body 
            : ($body[$field] ?? $arguments[1] ?? null);
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
    public function toString(): string
    {
        if($this->raw !== null){
            return $this->raw;
        }

        $type = $this->getContentType();

        if (str_contains($type, 'application/json')) {
            try{
                return $this->raw = (json_encode($this->getBody(), JSON_THROW_ON_ERROR) ?: '');
            }catch(JsonException){
                return '';
            }
        }
        
        if (str_contains($type, 'multipart/form-data')) {
            return $this->raw = 'Content-Type: multipart/form-data; ' . $this->toMultipart();
        }
    
        return $this->raw = http_build_query($this->getBody());
    }

    /**
     * {@inheritdoc}
     */
    public function toMultipart(): string
    {
        $boundary = '------LuminovaFormBoundary' . md5(time());
        $body = 'boundary=';

        foreach ($this->getBody() as $key => $value) {
            $isArray = is_array($value);
            $body .= "{$boundary}\r\n";

            if ($isArray && (!empty($value['tmp_name']) || !empty($value['content']))) {
                $filePath = $value['tmp_name'] ?? null;
                $fileSize = $value['size'] ?? self::fnBox('filesize', $filePath);
                $fileType = $value['type'] ?? self::fnBox('get_mime', $filePath) ?? 'application/octet-stream';
                $fileName = $value['name'] ?? self::fnBox('basename', $filePath);
                
                $fileContent = ($filePath === null) 
                    ? $value['content'] 
                    : file_get_contents($filePath);

                $body .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"{$fileName}\"\r\n";
                if($fileSize){
                    $body .= "Content-Length: {$fileSize}\r\n";
                }
                $body .= "Content-Type: {$fileType}\r\n\r\n";
                $body .= "{$fileContent}\r\n";
            } else {
                $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
                $body .= $isArray ? json_encode($value) . "\r\n" : "{$value}\r\n";
            }
        }

        $body .= "{$boundary}--\r\n";

        return $body;
    }

    /**
     * {@inheritdoc}
     */
    public function setField(string $field, mixed $value, ?string $method = null): self
    {
        if($field){
            $method = ($method === null) ? $this->getAnyMethod() : strtoupper($method);
            $this->body[$method][$field] = $value;
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
            unset($this->body[$method][$field]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getGet(string|null $field, mixed $default = null): mixed
    {
        return ($field === null)
            ? ($this->body['GET'] ?? []) 
            : ($this->body['GET'][$field] ?? $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getPost(string|null $field, mixed $default = null): mixed
    {
        return ($field === null)
            ? ($this->body['POST'] ?? []) 
            : ($this->body['POST'][$field] ?? $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(bool $object = false): array|object
    {
        if($this->body === []){
            $this->parseRequestBody();
        }

        if($object){
            return (object) array_merge(
                $this->body[$this->getAnyMethod()] ?? $this->body,
                $this->files ?: $_FILES
            );
        }
        
        return array_merge(
            $this->body[$this->getAnyMethod()] ?? $this->body,
            $this->files ?: $_FILES
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getArray(string $field, array $default = [], ?string $method = null): array
    {
        $method = ($method === null) ? $this->getAnyMethod() : strtoupper($method);

        if ($field && isset($this->body[$method][$field])) {
            $result = $this->getBody()[$field];

            if(json_validate($result)) {
                try{
                    return (json_decode($result, true, 512, JSON_THROW_ON_ERROR) ?: $default);
                }catch(JsonException){
                    return $default;
                }
            }
            
            return (array) $result;
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
     */
    public function getFile(string $name, ?int $index = null): Generator|File|null
    {
        return $name ? $this->parseRequestFile(
            $this->files[$name] ?? $_FILES[$name] ?? null, 
            $index
        ) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles(): array
    {
        return $this->files ?: $_FILES;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        $this->method ??= strtoupper($this->server->get('REQUEST_METHOD', ''));

        return $this->method;
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
    public static function getFromMultipart(string $data, string $boundary): array
    {
        $params = [];
        $files = [];
        $parts = explode($boundary, $data);
        array_pop($parts);

        foreach ($parts as $part) {
            $tPart = trim($part);
            if (
                $tPart === '' || 
                $tPart == "--" || 
                $tPart == "--\r\n"
            ) {
                continue;
            }

            [$rawHeaders, $content] = explode("\r\n\r\n", $part, 2) + [null, null];
            $content = rtrim($content ?? '');
            $headers = self::getMultipartHeaders($rawHeaders);

            if (
                $headers !== [] &&
                isset($headers['content-disposition']) && 
                preg_match('/name="([^"]+)"/', $headers['content-disposition'], $matches)
            ) {
                $isFile = preg_match('/filename="([^"]+)"/', $headers['content-disposition'], $fileMatches);
                $value = $isFile ? [
                    'name' => $fileMatches[1],
                    'type' => $headers['content-type'] ?? 'application/octet-stream',
                    'size' => strlen($content),
                    'error' => UPLOAD_ERR_OK,
                    'content' => $content,
                    'tmp_name' => null
                ] : (json_validate($content) ? json_decode($content, true) : $content);

                if(str_contains($matches[1], '[') && str_ends_with($matches[1], ']')){
                    $info = self::getFieldInfo($matches[1]);

                    if($isFile){
                        $files[$info['field']] = self::getArrayField($files[$info['field']] ?? []);

                        if($info['key'] === null){
                            $files[$info['field']][] = $value;
                        }else{
                            $files[$info['field']][$info['key']] = $value;
                        }
                    }else{
                        $params[$info['field']] = self::getArrayField($params[$info['field']] ?? []);

                        if($info['key'] === null){
                            $params[$info['field']][] = $value;
                        }else{
                            $params[$info['field']][$info['key']] = $value;
                        }
                    }
                }elseif($isFile){
                    $files[$matches[1]] = $value;
                }else{
                    $params[$matches[1]] = $value;
                }
            }
        }

        return ['params' => $params, 'files' => $files];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getQuery(?string $name = null, mixed $default = null): mixed
    {
        $this->queries = $this->getQueries() ?? [];

        if(!$this->queries){
            return $default;
        }

        return ($name === null) 
            ? http_build_query($this->queries, '', '&', PHP_QUERY_RFC3986)
            : $this->queries[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueries(): ?array
    {
        if($this->queries !== false){
            return $this->queries;
        }

        $queries = $this->server->get('QUERY_STRING');

        if(null === $queries || $queries === ''){
            $this->queries = [];
            return null;
        }

        $queries = explode('&', html_entity_decode($queries));
        $values = [];

        foreach ($queries as $value) {
            [$key, $value] = explode('=', $value, 2);
            $values[$key] = urldecode($value);
        }

        ksort($values);
        return $this->queries = $values;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): string
    {
        return $this->server->get('REQUEST_URI', '');
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(bool $withPort = false): string
    {
        $host = $this->getHostname(false, $withPort);
        $uri = $this->getUri();
        $uri = $host ? $uri :  ltrim($uri, '/');

        return $this->getScheme() . '://' . $host . $uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaths(): string
    {
        $url = $this->getUri();

        if($url === ''){
            return '';
        }

        return parse_url($this->getUrl(), PHP_URL_PATH);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestUri(): string
    {
        return $this->uri ?? $this->extractRequestUri();
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
    public function getOrigin(): ?string
    {
        $origin = $this->header->get('Origin') ?? $this->server->get('HTTP_ORIGIN');
        self::initRequestSecurityConfig();

        if (!$origin) {
            return null;
        }

        if(self::$config->trustedOrigins === []){
            return $origin;
        }

        $domain = parse_url($origin, PHP_URL_HOST);
        return ($domain === '')
            ? null 
            : (self::isTrusted($domain, 'origin') ? $domain : null);
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
    public function getProtocol(string $default = 'HTTP/1.1'): string
    {
        return $this->server->get('SERVER_PROTOCOL', $default);
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
    public function getUserAgent(?string $useragent = null): UserAgent
    {
        if($useragent || !($this->agent instanceof UserAgent)){
            $this->agent = new UserAgent($useragent);
        }

        return $this->agent;
    }

    /**
     * {@inheritdoc}
     */
    public function hasField(string $field, ?string $method = null): bool
    {
        if(!$field){
            return false;
        }
        
        $method = $method ? strtoupper($method) : $this->getAnyMethod();

        return ($this->body === [] || ($this->body[$method]??null) === null) 
            ? false 
            : array_key_exists($field, $this->body[$method] ?? []);
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
        return $this->getAnyMethod() === 'POST';
    }

    /**
     * {@inheritdoc}
     */
    public function isMethod(string $method = 'GET'): bool
    {
        return $this->getAnyMethod() === strtoupper($method);
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
    public function isProxy(): bool 
    {
        return $this->header->isProxy();
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
    public function isApi(): bool
    {
        return Luminova::isApiPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function isSameOrigin(bool $subdomains = false, bool $strict = false): bool
    {
        $origin = $this->header->get('Origin') ?? $this->server->get('HTTP_ORIGIN');
        $origin = (!$origin && $strict) 
            ? ($this->header->get('Referer') ?? $this->server->get('HTTP_REFERER'))
            : $origin;

        if(!$origin){
            return !$strict;
        }

        $origin = parse_url($origin, PHP_URL_HOST);

        if (!$origin) {
            return false;
        }

        if($subdomains){
            return $origin === APP_HOSTNAME_ALIAS || Func::mainDomain($origin) === APP_HOSTNAME;
        }

        return $origin === APP_HOSTNAME;
    }

    /**
     * {@inheritdoc}
     */
    public static function isTrusted(string $input, string $context = 'hostname'): bool
    {
        if($context !== 'hostname' && $context !== 'origin' && $context !== 'proxy'){
            throw new InvalidArgumentException(sprintf('Invalid Context name: "%s".', $context));
        }

        if($context === 'proxy'){
            return IP::isTrustedProxy($input);
        }

        self::initRequestSecurityConfig();
        $trusted = ($context === 'hostname') ? self::$config->trustedHostnames : self::$config->trustedOrigins;

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
        self::initRequestSecurityConfig();

        if (!$origin) {
            return false;
        }

        if(self::$config->trustedOrigins === []){
            return true;
        }

        $domain = parse_url($origin, PHP_URL_HOST);
        return ($domain === '') 
            ? false 
            : self::isTrusted($domain, 'origin');
    }

    /**
     * The following methods are derived from code of the Zend Framework (1.10dev - 2010-01-24)
     *
     * Code subject to the new BSD license (https://framework.zend.com/license).
     *
     * Copyright (c) 2005-2010 Zend Technologies USA Inc. (https://www.zend.com/)
     * @internal
     */
    protected function extractRequestUri(): string
    {
        $uri = '';

        if ($this->wasUrlRewrite() && $this->server->has('UNENCODED_URL')) {
            // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
            $uri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
        } elseif (($query = $this->server->get('REQUEST_URI')) !== null) {
            // Regular REQUEST_URI, handle fragment removal and query string parsing
            $uri = strtok($query, '#');
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $uri = $this->server->get('ORIG_PATH_INFO');
            if (($query = $this->server->get('QUERY_STRING')) !== null) {
                $uri .= '?' . $query;
            }

            $this->server->remove('ORIG_PATH_INFO');
        }

        // Set the request URI
        $this->server->set('REQUEST_URI', $uri);
        return $this->uri = $uri;
    }

    /**
     * Check if header IIS with UrlRewriteModule? was set indicating that url was rewritten.
     *
     * @return bool Return true if header IIS with UrlRewriteModule? was set and its 1.
     */
    private function wasUrlRewrite(): bool
    {
        if ((int) $this->server->get('IIS_WasUrlRewritten', 0) === 1) {
            $this->server->remove('IIS_WasUrlRewritten');
            return true;
        }

        return false;
    }

    /**
     * Parses and sets up request cookies.
     * 
     * @return CookieJarInterface
     */
    protected function parseRequestCookies(): CookieJarInterface
    {
        $cookie = new CookieFileJar([], ['readOnly' => true]);
        $cookies = $this->cookies 
            ?? $this->header->get('Cookie') 
            ?? $this->server->get('HTTP_COOKIE');

        if(!$cookies){
            return $cookie;
        }

        $config = new Session();
        $options = [
            ...CookieFileJar::DEFAULT_OPTIONS,
            'domain' => '.' . $this->getHost()
        ];

        $cookie->setCookies(
            is_string($cookies) 
                ? $cookie->getFromHeader(explode('; ', $cookies), false, $options)
                : $cookie->getFromGlobal($cookies, false, $options)
        );

        if($cookie->has($config->cookieName)){
            $cookie->getCookie($config->cookieName)
                ->setOptions([
                    'domain'    =>  $config->sessionDomain,
                    'path'      =>  $config->sessionPath,
                    'secure'    =>  true,
                    'httponly'  =>  true,
                    'expires'   =>  $config->expiration,
                    'samesite'  =>  $config->sameSite,
                    'raw'       =>  false
                ]);
        }
        
        return $cookie;
    }

    /**
     * Parses and sets up the request URL based on the provided server and header arrays.
     *
     * @param array|null $server The server array containing request information.
     * @param array|null $headers The headers array containing request headers.
     *
     * @return void
     * @throws InvalidArgumentException If the provided URI is malformed.
     */
    protected function parseRequestUrl(?array $server = null, ?array $headers = null): void
    {
        if ($this->uri === null) {
            $this->server = LazyObject::newObject(Server::class, fn(): array => [$server ?? $_SERVER]);
            $this->header = LazyObject::newObject(Header::class, Fn(): array => [$headers]);

            return;
        }

        $parts = parse_url($this->uri);

        if ($parts === false) {
            throw new InvalidArgumentException(sprintf('Malformed URI "%s".', $this->uri));
        }

        $method = strtoupper($this->method ?? 'GET');
        $server = array_replace(Server::getDefault(), $server ?? []);

        $server['PATH_INFO'] = '';
        $server['REQUEST_METHOD'] = $method;

        if (isset($parts['host'])) {
            $server['SERVER_NAME'] = $parts['host'];
            $server['HTTP_HOST'] = $parts['host'];
        }

        if (isset($parts['scheme'])) {
            $server['SERVER_PORT'] = ($parts['scheme'] === 'https') ? 443 : 80;
            $server['HTTPS'] = ($parts['scheme'] === 'https') ? 'on' : null;
        }

        if (isset($parts['port'])) {
            $server['SERVER_PORT'] = $parts['port'];
            $server['HTTP_HOST'] .= ':' . $parts['port'];
        }

        if (isset($parts['user'])) {
            $server['PHP_AUTH_USER'] = $parts['user'];
        }

        if (isset($parts['pass'])) {
            $server['PHP_AUTH_PW'] = $parts['pass'];
        }

        $path = $parts['path'] ?? '/';
        $type = $server['CONTENT_TYPE'] ?? null;
        $body = match ($method) {
            'POST', 'PUT', 'DELETE', 'PATCH' => [],
            default => $this->body[$method] ?? [],
        };

        if ($body !== [] && !$type && $this->isFormEncoded($method)) {
            $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        }

        $query = '';
        if (isset($parts['query'])) {
            $params = [];
            parse_str(html_entity_decode($parts['query']), $params);

            $this->body[$method] = $body ? array_replace($params, $body) : $params;
            $query = http_build_query($this->body, '', '&');
        } elseif ($body) {
            $query = http_build_query($body, '', '&');
        }

        $server['REQUEST_URI'] = ($query === '') ? $path : "{$path}?{$query}";
        $server['QUERY_STRING'] = $query;

        $this->server = LazyObject::newObject(Server::class, fn():array => [$server]);
        $this->header = LazyObject::newObject(Header::class, fn():array => [[...($headers ?? []), ...$server]]);
    }

    /**
     * Parse the request body based on the request method.
     * 
     * @return void
     */
    protected function parseRequestBody(): void
    {
        $method = $this->getAnyMethod();
    
        if($this->body !== []){
            if(!isset($this->body[$method])){
                $this->body = [$method => $this->body];
            }
            
            return;
        }

        $this->body = [];
        $this->body[$method] = ($method === 'POST') ? $_POST : $_GET;

        if($this->body[$method] === []){
            $input = file_get_contents('php://input');

            if ($input !== false) {
                $type = $this->getContentType();

                if(str_contains($type, 'multipart/form-data')){
                    if(($boundary = $this->getBoundary())){
                        $result = self::getFromMultipart($input, '--' . $boundary);
                        $this->body[$method] = $result['params'];
                        $this->files = $result['files'];
                    }
                } elseif(str_contains($type, 'application/json')) {
                    try{
                        $this->body[$method] = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
                    }catch(JsonException){}
                }

                if($this->body[$method] === []){
                    parse_str($input, $this->body[$method]);
                }

                $input = $result = null;
            }
        }

        if ($this->files === []) {
            $this->files = $_FILES ?? [];
        }
    }

    /**
     * Parse the uploaded file(s) information and return file instances.
     *
     * @param array|null $file File information array.
     * @param int|null $index Optional file index for multiple files.
     * 
     * @return Generator<int,File,void,void>|File|null Return the parsed file information or null if the file array is empty.
     */
    protected function parseRequestFile(array|null $file, ?int $index = null): Generator|File|null
    {
        if($file === [] || $file === null){
            return null;
        }

        if (is_array($file['name'] ?? null)) {
            return ($index === null)
                ? $this->createFileGenerator($file)
                : (isset($file['name'][$index]) ? $this->createFileInstance($file, $index) : null);
        }

        if (isset($file[0]['name'])) {
            return ($index === null) 
                ? $this->createFileGenerator($file, true) 
                : $this->createFileInstance($file[$index] ?? [], null, $index);
        }

        return $this->createFileInstance($file, null);
    }

    /**
     * Parses raw multipart headers into an associative array.
     *
     * @param string $rawHeaders The raw header string from a multipart request.
     * 
     * @return array Return an associative array where keys are lowercase header names and values are their corresponding values.
     */
    private static function getMultipartHeaders(string $rawHeaders): array 
    {
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $headerLine) {
            if (str_contains($headerLine, ': ')) {
                [$key, $value] = explode(': ', $headerLine, 2);
                $headers[strtolower($key)] = $value;
            }
        }

        return $headers;
    }

    /**
     * Determines if the request method is typically associated with form-encoded data.
     *
     * This method checks if the given HTTP method (or the current request method if not specified)
     * is one that typically involves form-encoded data submission (POST, PUT, DELETE, or PATCH).
     *
     * @param string|null $method The HTTP method to check. If null, the current request method is used.
     *
     * @return bool Returns true if the method is associated with form-encoded data, false otherwise.
     */
    private function isFormEncoded(?string $method = null): bool 
    {
        return in_array(
            $method ?? $this->getMethod(), 
            ['POST', 'PUT', 'DELETE', 'PATCH'], 
            true
        );
    }

    /**
     * Extracts the base field name and key from a given field string.
     *
     * @param string $field The field name, which may include array-style brackets (e.g., "foo[bar]").
     * @return array Return an associative array with:
     *               - 'field' => The base field name.
     *               - 'key'   => The key inside brackets, or null if absent.
     */
    private static function getFieldInfo(string $field): array 
    {
        if (preg_match('/^([^\[]+)\[([^\]]*)\]$/', $field, $matches)) {
            return [
                'field' => $matches[1], 
                'key' => ($matches[2] === '') ? null : $matches[2]
            ];
        }

        return ['field' => $field, 'key' => null];
    }

    /**
     * Ensures field value is always returned as an array.
     *
     * @param mixed $field The input value, which may be a scalar, an array, or null.
     * 
     * @return array If the input is already an array, it is returned as-is. 
     *               If null or an empty array, returns an empty array.
     *               Otherwise, wraps the input in an array.
     */
    private static function getArrayField(mixed $field): array 
    {
        if (!$field || $field === []) {
            return [];
        }

        return is_array($field) ? $field : [$field];
    }

    /**
     * Generator function to yield `File` instances for multiple uploaded files.
     *
     * @param array $file File data from the request.
     * @param bool $flat Generate File instances from developer-friendly flat structure.
     * 
     * @return Generator<int,File,void,void> yield File A `File` instance for each file in the input array.
     */
    private function createFileGenerator(array $files, bool $flat = false): Generator
    {
        foreach ($flat ? $files : array_keys($files['name']) as $index => $file) {
            yield $index => $flat
                ? $this->createFileInstance($file, null, $index)
                : $this->createFileInstance($files, $index);
        }
    }

    /**
     * Create a File instance from the provided file data.
     *
     * @param array $file File data from the request.
     * @param int|null $index Optional index for handling multiple file uploads (default: null).
     * @param int|null $flatIndex Optional index for handling multiple file uploads for flat structure (default: null).
     * 
     * @return File Return the created File instance.
     */
    private function createFileInstance(array $file, ?int $index = null, ?int $flatIndex = null): ?File
    {
        if ($file === []) {
            return null;
        }

        $extract = fn(string $key) => ($index === null)
            ? ($file[$key] ?? ($key === 'size' ? 0 : null)) 
            : ($file[$key][$index] ?? ($key === 'size' ? 0 : null));

        $name = $extract('name');
        $temp = $extract('tmp_name');
        $size = (int) $extract('size');
        $type = $extract('type');
        $error = $extract('error') ?? UPLOAD_ERR_OK;
        $content = $extract('content');
        $path = $extract('full_path'); // For JS Upload Libraries

        $isBlob = ($content !== null || $this->isBlobUpload($temp, $type, $name, $path));
        $size = ($content && $size === 0) ? strlen($content) : $size;

        // Sanitize and derive name
        $path = ($path && $path !== 'blob') ? basename($path) : $path;
        $name ??= ($path && $path !== 'blob') ? $path : uniqid('file_', true);

        // Derive extension
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $type ??= get_mime($temp) ?: 'application/octet-stream';
        $extension = $extension ?: (Files::getExtension($type) ?: '');

        if ($extension && !str_ends_with($name, ".$extension")) {
            $name .= ".$extension";
        }

        // Error handling for missing content or temp
        $error = ($error === UPLOAD_ERR_OK && ($size === 0 || ($content === null && $temp === null)) )
            ? UPLOAD_ERR_NO_FILE
            : $error;

        return new File(
            $index ?? $flatIndex ?? 0,
            $name,
            $type,
            $size,
            strtolower($extension),
            $temp,
            $error,
            $content,
            $isBlob
        );
    }

    /**
     * Determine if the file is uploaded as a blob or part of a chunked upload.
     * 
     * Chunked uploads commonly have a file name like 'blob', a MIME type of 'application/octet-stream',
     * and still a temporary file path ('tmp_name'). If these conditions are met, the file may be part of
     * a chunked upload or treated as a BLOB.
     *
     * @param string|null $temp The temporary file path of the uploaded file.
     * @param string|null $type The MIME type of the uploaded file.
     * @param string|null $name The original file name.
     * @param string|null $path Optional full path provided by JS libraries.
     *
     * @return bool Returns true if the file is detected as a blob upload, false otherwise.
     */
    private function isBlobUpload(?string $temp, ?string $type, ?string $name, ?string $path): bool
    {
        if (
            $temp === null || 
            $name === null || 
            $name === 'blob' || 
            $path === 'blob' ||
            !$type || 
            $type === 'application/octet-stream' 
        ) {
            return true;
        }

        $pattern = '/chunk|part|blob/i';
        return (
            ($temp && preg_match($pattern, $temp)) || 
            ($name && preg_match($pattern, $name)) || 
            ($path && preg_match($pattern, $path))
        );
    }

    /**
     * Initializes API configuration.
     * 
     * @return void
     */
    private static function initRequestSecurityConfig(): void
    {
        self::$config ??= new Security();
    }

    /**
     * Call function PHP function if path is null.
     *
     * @param string $fn The name of the function to call.
     * @param string|null $path The path to apply the function to.
     *
     * @return mixed Return the result of called function to the path.
     */
    private static function fnBox(string $fn, ?string $path): mixed 
    {
        return ($path === null) ? null : $fn($path);
    }
}