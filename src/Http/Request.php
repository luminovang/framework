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
use \App\Config\Files;
use \Luminova\Interface\HttpRequestInterface;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\SecurityException;
use \Stringable;
use \JsonException;

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
     * {@inheritdoc}
     */
    public ?Server $server = null;

    /**
     * {@inheritdoc}
     */
    public ?Header $header = null;

    /**
     * {@inheritdoc}
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
        $this->parseRequestBody();
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $method, array $arguments): mixed 
    {
        $field = $arguments[0] ?? null;
        $httpMethod = strtoupper(substr($method, 3));
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
                $this->body[$this->getMethod()] ?? $this->body,
                $this->files ?: $_FILES
            );
        }
        
        return array_merge(
            $this->body[$this->getMethod()] ?? $this->body,
            $this->files ?: $_FILES
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFile(string $name, ?int $index = null): File|array|bool
    {
        return $this->parseRequestFile(
            $this->files[$name] ?? $_FILES[$name] ?? null, 
            $index
        );
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
    public function getArray(string $method, string $field, array $default = []): array
    {
        $method = strtoupper($method);
        if (isset($this->body[$method][$field])) {
            $result = $this->getBody()[$field];
    
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
            $headers = [];

            foreach (explode("\r\n", $rawHeaders) as $headerLine) {
                if (str_contains($headerLine, ': ')) {
                    [$key, $value] = explode(': ', $headerLine, 2);
                    $headers[strtolower($key)] = $value;
                }
            }

            if (
                isset($headers['content-disposition']) && 
                preg_match('/name="([^"]+)"/', $headers['content-disposition'], $matches)
            ) {
                if (preg_match('/filename="([^"]+)"/', $headers['content-disposition'], $fileMatches)) {
                    $files[$matches[1]] = [
                        'name' => $fileMatches[1],
                        'type' => $headers['content-type'] ?? 'application/octet-stream',
                        'size' => strlen($content),
                        'error' => UPLOAD_ERR_OK,
                        'content' => $content,
                        'tmp_name' => null
                    ];
                } else {
                    $params[$matches[1]] = json_validate($content) 
                        ? json_decode($content, true) 
                        : $content;
                }
            }
        }

        return ['params' => $params, 'files' => $files];
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
    public function isProxy(): bool 
    {
        $headers = [
            'X-Forwarded-For' => 'HTTP_X_FORWARDED_FOR',
            'X-Forwarded-For-Ip' => 'HTTP_FORWARDED_FOR_IP',
            'X-Real-Ip' => 'HTTP_X_REAL_IP',
            'Via' => 'HTTP_VIA',
            'Forwarded' => 'HTTP_FORWARDED',
            'Proxy-Connection' => 'HTTP_PROXY_CONNECTION'
        ];

        foreach ($headers as $head => $server) {
            if ($this->header->exist($head, $server)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isAJAX(): bool
    {
        $ajax = $this->header->get('X-Requested-With', $this->server->get('HTTP_X_REQUESTED_WITH', ''));
        return ($ajax === '')
            ? false 
            : strtolower($ajax) === 'xmlhttprequest';
    }

    /**
     * {@inheritdoc}
     */
    public function isApi(): bool
    {
        return Foundation::isApiPrefix();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        $queries = $this->getQueries();
        return ($queries === null) 
            ? '' 
            : http_build_query($queries, '', '&', PHP_QUERY_RFC3986);
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
        return $this->server->get('REQUEST_URI', '');
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(): string
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
        $origin = $this->header->get('Origin', $this->server->get('HTTP_ORIGIN'));
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
        if($useragent || !($this->agent instanceof UserAgent)){
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

        if (!$origin) {
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

        self::initRequestSecurityConfig();
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
        return self::isTrusted($this->server->get('REMOTE_ADDR', ''), 'proxy');
    }

    /**
     * {@inheritdoc}
     */
    public function isTrustedOrigin(): bool
    {
        $origin = $this->header->get('Origin', $this->server->get('HTTP_ORIGIN'));
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
     * Parse the request body based on the request method.
     * 
     * @return void
     */
    protected function parseRequestBody(): void
    {
        $method = $this->getMethod();
    
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
     * @param array $file File information array.
     * @param int|null $index Optional file index for multiple files.
     * 
     * @return File[]|File|false Return the parsed file information or false if the file array is empty.
     */
    protected function parseRequestFile(array $file, ?int $index = null): File|array|bool
    {
        if($file === [] || $file === null){
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
            $path = $file['full_path'] ?? null; // Js Chunk Upload library
        } else {
            $name = $file['name'][$index] ?? null;
            $temp = $file['tmp_name'][$index] ?? null;
            $size = $file['size'][$index] ?? 0;
            $type = $file['type'][$index] ?? null;
            $error = $file['error'][$index] ?? UPLOAD_ERR_OK;
            $content = $file['content'][$index] ?? null;
            $path = $file['full_path'][$index] ?? null;
        }

        $error = ($error === UPLOAD_ERR_OK && $content === null && $temp === null) 
            ? UPLOAD_ERR_NO_FILE
            : $error;

        $isBlob = ($content !== null || $this->isBlobUpload($temp, $type, $name, $path));
        $type ??= (get_mime($temp)?: 'application/octet-stream');
        $name ??= uniqid('file_');
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $extension = strtolower((!$extension && $type) ? Files::getExtension($type) : $extension);

        return new File(
            $index ?? 0,
            $name,
            $type,
            (int) $size,
            $extension ?: '',
            $temp,
            $error,
            $content,
            $isBlob
        );
    }

    /**
     * Determine if the file was uploaded in chunks or is considered a BLOB.
     *
     * Chunked uploads commonly have a file name like 'blob', a MIME type of 'application/octet-stream',
     * and still a temporary file path ('tmp_name'). If these conditions are met, the file may be part of
     * a chunked upload or treated as a BLOB.
     *
     * @param string|null $temp The temporary file path.
     * @param string|null $type The MIME type of the file.
     * @param string|null $name The original file name.
     * @param string|null $full_path The full path of the uploaded file, if provided.
     * 
     * @return bool Returns true if the file appears to be a chunked or BLOB upload, otherwise false.
     */
    private function isBlobUpload(?string $temp, ?string $type, ?string $name, ?string $full_path = null): bool
    {
        return (
            !$type ||
            (
                (!$name || $name === 'blob') && 
                (!$full_path || $full_path === 'blob') && 
                $type === 'application/octet-stream' && 
                $temp !== null
            )
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