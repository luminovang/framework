<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Http;

use \Luminova\Application\Foundation;
use \Luminova\Http\Header;
use \Luminova\Http\Server;
use \Luminova\Http\File;
use \Luminova\Http\UserAgent;
use \Luminova\Functions\IP;
use \Luminova\Functions\Func;
use \App\Config\Security;
use \Luminova\Interface\HttpRequestInterface;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\SecurityException;
use \Stringable;
use \JsonException;

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
final class Request implements HttpRequestInterface, Stringable
{
    /**
     * Http request methods.
     *
     * @var array<int,string> $methods
     */ 
    public static array $methods = [
        'GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 
        'PATCH', 'HEAD', 'CONNECT', 'TRACE', 'PROPFIND', 
        'MKCOL', 'COPY', 'MOVE', 'LOCK', 'UNLOCK'
    ]; 

    /**
     * Http server instance.
     *
     * @var Server|null $server
     */
    public ?Server $server = null;

    /**
     * Http request header instance.
     *
     * @var Header|null $header
    */
    public ?Header $header = null;

    /**
     * Browser request user-agent information.
     *
     * @var UserAgent|null $agent
     */
    public ?UserAgent $agent = null;

    /**
     * Request security configuration.
     *
     * @var Security|null $config
     */
    private static ?Security $config = null;

    /**
     * Initializes a new Request object, representing an HTTP request.
     * 
     * @param string|null $method The HTTP method used for the request (e.g., 'GET', 'POST').
     * @param string|null $uri The request URI, typically the path and query string.
     * @param array<string,mixed> $body The request body, provided as an associative array 
     *                                  (e.g., `['field' => 'value']`). This may include form data, JSON, etc.
     * @param array<string,mixed> $files The request files, provided as an associative array (e.g., `['field' => array|string]`). 
     * @param string|null $raw Optional request raw-body. 
     * @param array<string,mixed>|null $server Optional. Server variables (e.g., `$_SERVER`). 
     *                                         If not provided, defaults to global `$_SERVER`.
     * @param array<string,mixed>|null $headers Optional. An associative array of HTTP headers. 
     *                                          If not provided, headers request headers will be extracted from `apache_request_headers` or `$_SERVER`.
     */
    public function __construct(
        private ?string $method = null,
        private ?string $uri = null,
        private array $body = [],
        private array $files = [],
        private ?string $raw = null,
        ?array $server = null,
        ?array $headers = null
    ) {
        $this->server = new Server($server ?? $_SERVER);
        $this->header = new Header($headers);
        $this->parseBody();
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $key, array $arguments): mixed 
    {
        $method = strtoupper(substr($key, 3));
        $body = $this->body[$method] ?? [];

        return $body[$arguments[0]] ?? $arguments[1] ?? null;
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

        $contentType = $this->getContentType();

        if (str_contains($contentType, 'application/json')) {
            return $this->raw = json_encode($this->getBody());
        }
        
        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->raw = $this->toMultipart();
        }
    
        return $this->raw = http_build_query($this->getBody());
    }

    /**
     * {@inheritdoc}
     */
    public function toMultipart(): string
    {
        $boundary = '----WebKitFormBoundary' . md5(time());
        $body = '';

        foreach ($this->getBody() as $key => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        return $body;
    }

    /**
     * {@inheritdoc}
     */
    public function getGet(string $key, mixed $default = null): mixed
    {
        return $this->body['GET'][$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->body['POST'][$key] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(bool $object = false): array|object
    {
        $body = ($this->body === [])
            ? $this->parseBody()
            : ($this->body[$this->getMethod()] ?? $this->body);
        
        $body = array_merge($body, $this->files ?: $_FILES);
        return $object ? (object) $body : $body;
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(string $name, ?int $index = null): File|array|null
    {
        $files = $this->files[$name] ?? $_FILES[$name] ?? null;

        if ($files !== null && ($parsed = $this->parseFile($files, $index)) !== false) {
            return $parsed;
        }

        return null;
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
        return strtoupper($this->method ?? $this->server->get('REQUEST_METHOD', ''));
    }

    /**
     * {@inheritdoc}
     */
    public function getArray(string $method, string $key, array $default = []): array
    {
        $method = strtoupper($method);
        if (isset($this->body[$method][$key])) {
            $result = $this->getBody()[$key];
    
            if(is_string($result) && json_validate($result)) {
                try{
                    $decode = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

                    if ($decode !== null || $decode !== false) {
                        return (array) $decode ?? $default;
                    }
                }catch(JsonException){
                    return $default;
                }
            }
            
            return (array) $result ?? $default;
        }
        
        throw new InvalidArgumentException(sprintf(
            'Request method" "%s" is not supported, supported methods are: [%s]',
            $method,
            implode(', ', self::$methods))
        );
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
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType(): string
    {
        return $this->header->get('Content-Type', 
            $this->server->get('CONTENT_TYPE', '')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAuth(): ?string
    {
        if(!$auth = $this->header->get('Authorization')){
            if(!$auth = $this->server->get('HTTP_AUTHORIZATION')){
                $auth = $this->server->get('REDIRECT_HTTP_AUTHORIZATION');
            }
        }

        return ($auth === null) ? null : trim($auth ?? '');
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
    public function isAJAX(): bool
    {
        $ajax = $this->header->get('X-Requested-With', $this->server->get('HTTP_X_REQUESTED_WITH', ''));

        return strtolower($ajax) === 'xmlhttprequest';
    }

    /**
     * {@inheritdoc}
     */
    public function isApi(): bool
    {
        return Foundation::isApiContext();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        $queries = $this->getQueries();
        return ($queries === null) ? '' : http_build_query($queries, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueries(): ?array
    {
        $queries = $this->server->get('QUERY_STRING');
        if(null === $queries || $queries === ''){
            return null;
        }

        $queries = explode('&', $queries);
        $values = [];

        foreach ($queries as $value) {
            [$key, $value] = explode('=', $value);
            $values[$key] = urldecode($value);
        }

        ksort($values);
        return  $values;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): string
    {
        return $this->getScheme() . '://' . $this->getHost() . $this->server->get('REQUEST_URI', '');
    }

    /**
     * {@inheritdoc}
     */
    public function getPaths(): string
    {
        $url = $this->server->get('REQUEST_URI', '');
        return ($url === '') ? '' : parse_url($url, PHP_URL_PATH);
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
        if (!$hostname = $this->header->get('Host', $this->server->get('HTTP_HOST'))) {
           if(!$hostname = $this->server->get('HOST')){
                if (!$hostname = $this->server->get('SERVER_NAME')) {
                    $hostname = $this->server->get('SERVER_ADDR', '');
                }
            }
        }

        if($hostname === null){
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
            if(static::isTrusted($hostname, 'hostname')){
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
        $origin = $this->header->get('Origin', $this->server->get('HTTP_ORIGIN'));
        self::initConfig();

        if (!$origin) {
            return null;
        }

        if(self::$config->trustedOrigins === []){
            return $origin;
        }

        $domain = parse_url($origin, PHP_URL_HOST);
        return ($domain === '')
            ? null 
            : (static::isTrusted($domain, 'origin') ? $domain : null);
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): string|int|null
    {
        if (!($port = $this->header->get('X-Forwarded-Port', $this->server->get('HTTP_X_FORWARDED_PORT')))) {
            if(!($port = $this->server->get('X_FORWARDED_PORT'))){
                return $this->server->get('SERVER_PORT');
            }
        }
        
        $pos = ('[' === $port[0]) ? strpos($port, ':', strrpos($port, ']')) : strrpos($port, ':');

        if ($pos !== false && ($port = substr($port, $pos + 1))) {
            return (int) $port;
        }

        return $this->isSecure() ? 443 : 80;
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
        if(!$this->agent instanceof UserAgent){
            $this->agent = new UserAgent($useragent);
        }

        return $this->agent;
    }

    /**
     * {@inheritdoc}
     */
    public function isSameOrigin(bool $subdomains = false): bool
    {
        $origin = $this->header->get('Origin', $this->server->get('HTTP_ORIGIN'));

        if (!$origin) {
            return true;
        }

        $origin = parse_url($origin, PHP_URL_HOST);

        if (empty($origin)) {
            return false;
        }

        if($origin === APP_HOSTNAME){
            return true;
        }

        return ($subdomains) 
            ? (Func::mainDomain($origin) === APP_HOSTNAME)
            : false;
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

        self::initConfig();
        $trusted = ($context === 'hostname') ? self::$config->trustedHostname : self::$config->trustedOrigins;

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
        return static::isTrusted($this->server->get('REMOTE_ADDR', ''), 'proxy');
    }

    /**
     * {@inheritdoc}
     */
    public function isTrustedOrigin(): bool
    {
        $origin = $this->header->get('Origin', $this->server->get('HTTP_ORIGIN'));
        self::initConfig();

        if (!$origin) {
            return false;
        }

        if(self::$config->trustedOrigins === []){
            return true;
        }

        $domain = parse_url($origin, PHP_URL_HOST);
        return ($domain === '') 
            ? false 
            : static::isTrusted($domain, 'origin');
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
     * Parse the request body based on the request method.
     * 
     * @return array<string,array> Return request body as an array.
     */
    private function parseBody(): array
    {
        $body = $this->body;
        $method = $this->getMethod();
        $this->body = [];
        $this->body[$method] = [];

        if ($method === 'GET' || $method === 'POST') {
            $this->body[$method] = ($body === []) 
                ? ($method === 'GET' ? $_GET : $_POST) 
                : $body;

            if ($method === 'GET') {
                return $this->body[$method];
            }
        }

        if(($this->body[$method] ?? []) === []){
            $input = file_get_contents('php://input');
  
            if ($input !== false) {
                $params = [];
                parse_str($input, $params);
                $this->body[$method] = $params;

                if($this->raw === null){
                    $this->raw = $input;
                }
            }
        }

        if ($this->files === []) {
            $this->files = $_FILES ?? [];
        }

        return $this->body[$method];
    }

    /**
     * Parse the uploaded file(s) information and return file instances.
     *
     * @param array $file File information array.
     * @param int|null $index Optional file index for multiple files.
     * 
     * @return File|array<int,File>|false Return the parsed file information or false if the file array is empty.
     */
    private function parseFile(array $file, ?int $index = null): File|array|bool
    {
        if($file === []){
            return false;
        }

        if (is_array($file['name'] ?? null)) {
            return ($index === null)
                ? array_map(fn($idx) => $this->createFileInstance($file, $idx), array_keys($file['name']))
                : $this->createFileInstance($file, $index);
        }

        return $this->createFileInstance($file, null);
    }

    /**
     * Create a File instance based on the provided file data.
     *
     * @param array $file File information array.
     * @param int|null $index File index (default: null).
     * 
     * @return File Return the created File instance.
     */
    private function createFileInstance(array $file, ?int $index = null): File
    {
        if ($index === null) {
            $name = $file['name'] ?? null;
            $temp = $file['tmp_name'] ?? null;
            $size = $file['size'] ?? 0;
            $type = $file['type'] ?? null;
            $error = $file['error'] ?? UPLOAD_ERR_OK;
            $content = $file['content'] ?? null;
        } else {
            $name = $file['name'][$index] ?? null;
            $temp = $file['tmp_name'][$index] ?? null;
            $size = $file['size'][$index] ?? 0;
            $type = $file['type'][$index] ?? null;
            $error = $file['error'][$index] ?? UPLOAD_ERR_OK;
            $content = $file['content'][$index] ?? null;
        }

        $error = ($error === UPLOAD_ERR_OK && $content === null && $temp === null) 
            ? UPLOAD_ERR_NO_FILE
            : $error;

        $mime = get_mime($temp);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === '' && $mime) {
            [, $extension] = explode('/', $mime);
            $name = uniqid('file_') . '.' . strtolower($extension);
        }

        return new File(
            $index ?? 0,
            $name,
            $type,
            (int) $size,
            $mime ?: null,
            $extension ?: '',
            $temp,
            $error,
            $content
        );
    }

    /**
     * Initializes API configuration.
     * 
     * @return void
     */
    private static function initConfig(): void
    {
        self::$config ??= new Security();
    }
}