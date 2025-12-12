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
namespace Luminova\Http\Helper;

use \Generator;
use \JsonException;
use \Luminova\Http\Uri;
use \Luminova\Utility\MIME;
use \Luminova\Http\HttpStatus;
use \Luminova\Cookies\FileJar;
use \Luminova\Utility\Normalizer;
use \Luminova\Http\Message\Stream;
use \Psr\Http\Message\UriInterface;
use \App\Config\{Security, Session};
use \Psr\Http\Message\StreamInterface;
use \Luminova\Http\{Header, Server, File};
use \Luminova\Components\Object\LazyObject;
use \Luminova\Interface\CookieJarInterface;
use \Psr\Http\Message\UploadedFileInterface;
use \Luminova\Exceptions\InvalidArgumentException;

/**
 * @property Security|null $security Request security config
 */
trait RequestTrait
{
    /**
     * Lazy object. 
     * 
     * @var array{server:?Header<LazyObjectInterface>,header:?Server<LazyObjectInterface>} $lazy
     */
    private array $lazy = ['server' => null, 'header' => null];

    /**
     * Proxy headers.
     * 
     * @var array $proxyHeaders
     */
    private static array $proxyHeaders = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FORWARDED_FOR_IP',
        'HTTP_X_REAL_IP',
        'HTTP_VIA',
        'HTTP_FORWARDED',
        'HTTP_PROXY_CONNECTION',
    ];

    /**
     * {@inheritDoc}
     */
    public function isProxy(): bool
    {
        foreach (self::$proxyHeaders as $header) {
            if (isset($_SERVER[$header])){
                return true;
            }
        }
        
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isGraphQL(): bool
    {
        $body = $this->parsedBody[$this->getAnyMethod()] ?? [];

        if ($body === [] || !isset($body['query'])) {
            return false;
        }

        $query = $body['query'] ?? '';

        if (empty($query) || !is_string($query) || json_validate($query)) {
            return false;
        }

        return str_contains($query, '(') 
            && str_contains($query, '{')
            && str_ends_with($query, '}');
    }

    /**
     * {@inheritdoc}
     */
    public function getFiles(): array
    {
        return $this->parseRequestFile(
            $this->files ?: $_FILES, 
            isGenerator: false
        ) ?: [];
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget(): string
    {
        return $this->uriQueryString ?: '/';
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams(): array
    {
        return $this->serverParams ?? $_SERVER;
    }

    /**
     * {@inheritdoc}
     */
    public function __toArray(): array
    {
        return $this->getParsedBody();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): mixed
    {
        return $this->getParsedBody();
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams(): array
    {
        $cookies = $this->cookies 
            ?? $this->header->get('Cookie') 
            ?? $this->server->get('HTTP_COOKIE');

        if(!$cookies){
            return [];
        }

        $options = array_merge(
            FileJar::DEFAULT_OPTIONS,
            ['domain' => '.' . $this->getHost()]
        );

        $cookie = new FileJar([], ['readOnly' => true]);
        return is_string($cookies) 
            ? $cookie->getFromHeader(explode('; ', $cookies), default: $options)
            : $cookie->getFromGlobal($cookies, default: $options);
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
    public function toMultipart(): string
    {
        $boundary = '------LuminovaFormBoundary' . md5(time());
        $body = 'boundary=';

        foreach ($this->getParsedBody() as $key => $value) {
            $isArray = is_array($value);
            $body .= "{$boundary}\r\n";

            if ($isArray && (!empty($value['tmp_name']) || !empty($value['content']))) {
                $filePath = $value['tmp_name'] ?? null;
                $fileSize = $value['size'] ?? self::fnBox('filesize', $filePath);
                $fileType = $value['type'] ?? MIME::guess($filePath) ?? 'application/octet-stream';
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
    public function toString(): string
    {
        if($this->raw !== null){
            return $this->raw;
        }

        $type = $this->getContentType();

        if (str_contains($type, 'application/json')) {
            try{
                return $this->raw = (json_encode($this->getParsedBody(), JSON_THROW_ON_ERROR) ?: '');
            }catch(JsonException){
                return '';
            }
        }
        
        if (str_contains($type, 'multipart/form-data')) {
            return $this->raw = 'Content-Type: multipart/form-data; ' . $this->toMultipart();
        }
    
        return $this->raw = http_build_query($this->getParsedBody());
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): StreamInterface
    {
        if($this->body instanceof StreamInterface){
            return $this->body;
        }

        $body = '';

        if ($this->body !== []) {
            $body = http_build_query($this->body, '', '&', PHP_QUERY_RFC3986);
            $this->body = [];
        }

        return $this->body = Stream::fromStringReadOnly($body);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        return $this->header->get(default: []);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $name): array
    {
        return (array) $this->header->get($name, []);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine(string $header): string
    {
        return implode(', ', $this->getHeader($header));
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader(string $name): bool 
    {
        return $this->header->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        if ($code < 100 || $code >= 600) {
            throw new InvalidArgumentException(sprintf(
                'Invalid HTTP status code: %d was provided, status code must be between 1xx and 5xx.',
                $code
            ));
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?: HttpStatus::phrase($code);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader(string $name): static
    {
        $new = clone $this;
        $new->header->remove($name);
        unset($new->headers[$name]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader(string $name, $value): static
    {
        Header::assert($name, isValue: false);
        $value = Header::normalize($value);

        $new = clone $this;
        $new->headers[$name] = (array) $value;


        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion(string $version): static
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader(string $name, $value): static
    {
        Header::assert($name, isValue: false);
        $value = Header::normalize($value);
        $normalized = strtolower($name);

        $new = clone $this;
        $new->headers[$normalized] = array_merge(
            $new->headers[$normalized] ?? [], 
            (array) $value
        );

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod(string $method): static
    {
        if ($method === '') {
            throw new InvalidArgumentException('HTTP method cannot be empty');
        }

        if ($this->method === $method) {
            return $this;
        }

        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->uriQueryString = ($requestTarget !== '') ? $requestTarget : '/';
        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        $clone->uriQueryString = $clone->buildRequestTarget();

        if (!$preserveHost && $uri->getHost() !== '') {
            $clone->headers['host'] = [$uri->getHost()];
        }

        return $clone;
    }

    /**
     * Check if header IIS with UrlRewriteModule? was set indicating that url was rewritten.
     *
     * @return bool Return true if header IIS with UrlRewriteModule? was set and its 1.
     */
    private function wasUrlRewrite(bool $fromGlobal = false): bool
    {
        if($fromGlobal){
            if(isset($_SERVER['IIS_WasUrlRewritten']) 
                && (int) $_SERVER['IIS_WasUrlRewritten'] === 1){
                    unset($_SERVER['IIS_WasUrlRewritten']);
                return true;
            }

            return false;
        }

        if ((int) $this->server->get('IIS_WasUrlRewritten', 0) === 1) {
            $this->server->remove('IIS_WasUrlRewritten');
            $this->header->remove('IIS_WasUrlRewritten');
            return true;
        }

        return false;
    }

    /**
     * Parses and sets up request cookies.
     * 
     * @return CookieJarInterface
     */
    private function parseRequestCookies(): CookieJarInterface
    {
        $cookie = new FileJar([], ['readOnly' => true]);
        $cookies = $this->cookies 
            ?? $this->header->get('Cookie') 
            ?? $this->server->get('HTTP_COOKIE');

        if(!$cookies){
            return $cookie;
        }

        $config = new Session();
        $options = array_merge(
            FileJar::DEFAULT_OPTIONS,
            ['domain' => '.' . $this->getHost()]
        );

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

    private function buildRequestTarget(): string
    {
        if (!$this->uri || $this->uri === '/') {
            return '/';
        }

        if($this->uri instanceof UriInterface){
            $path = $this->uri->getPath() ?: '/';
            $query = $this->uri->getQuery();
        }else{
            $parts = parse_url((string) $this->uri);
            $query = $parts['query'] ?? '';
            $path = $parts['path'] ?? '/';
        }

        return ($query !== '') ? $path . '?' . $query : $path;
    }

    /**
     * Extract the request URI from server parameters.
     *
     * Derived from Zend Framework 1.x (2010).
     * @param bool $asString
     *
     * @internal
     */
    private function detectRequestUri(bool $asString = false): UriInterface|string
    {
        $uri = '/';

        if($asString){
            if ($this->wasUrlRewrite(true) && isset($_SERVER['UNENCODED_URL'])) {
                $uri = $_SERVER['UNENCODED_URL'];
                unset($_SERVER['UNENCODED_URL']);

            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $uri = strtok($_SERVER['REQUEST_URI'], '#');
            } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
                $uri = $_SERVER['ORIG_PATH_INFO'];

                if (!empty($_SERVER['QUERY_STRING'])) {
                    $uri .= '?' . $_SERVER['QUERY_STRING'];
                }

                unset($_SERVER['ORIG_PATH_INFO']);
            }

            $_SERVER['REQUEST_URI'] = $uri;

            return $uri;
        }

        if ($this->wasUrlRewrite() && $this->server->has('UNENCODED_URL')) {
            // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
            $uri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
            $this->header->remove('UNENCODED_URL');
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
            $this->header->remove('ORIG_PATH_INFO');
        }

        // Set the request URI
        $this->server->set('REQUEST_URI', $uri);
        return $this->uri = Uri::fromString($uri);
    }

    /**
     * Parse and initialize request URL, server params, and headers.
     * 
     * @return void
     * @throws InvalidArgumentException If the provided URI is malformed.
     */
    private function parseRequestUrl(): void
    {
        $isAuto = true;

        if ($this->uri !== null) {
            $isAuto = false;
            $parts = $this->normalizeUriParts($this->uri);
            $method = strtoupper($this->method ?? 'GET');
            $path = $parts['path'] ?? '/';

            $this->serverParams = array_replace(
                Server::getDefault(), 
                $this->serverParams ?? []
            );

            $this->serverParams['REQUEST_METHOD'] = $method;
            $this->serverParams['PATH_INFO'] = $path;

            if (!empty($parts['host'])) {
                $this->serverParams['SERVER_NAME'] = $parts['host'];
                $this->serverParams['HTTP_HOST'] = $parts['host'];
            }

            if (!empty($parts['scheme'])) {
                $https = ($parts['scheme'] === 'https');
                $this->serverParams['HTTPS'] = $https ? 'on' : null;
                $this->serverParams['SERVER_PORT'] = $https ? 443 : 80;
            }

            if (!empty($parts['port'])) {
                $this->serverParams['SERVER_PORT'] = $parts['port'];
                $this->serverParams['HTTP_HOST'] .= ':' . $parts['port'];
            }

            if (!empty($parts['user'])) {
                $this->serverParams['PHP_AUTH_USER'] = $parts['user'];
            }

            if (!empty($parts['pass'])) {
                $this->serverParams['PHP_AUTH_PW'] = $parts['pass'];
            }

            if ($this->body && empty($this->serverParams['CONTENT_TYPE']) && $this->isFormEncoded($method)) {
                $this->serverParams['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
            }

            if ($this->protocolVersion !== null) {
                $protocol = $this->protocolVersion;

                if (!str_starts_with($protocol, 'HTTP/')) {
                    $protocol = 'HTTP/' . $protocol;
                }

                $this->serverParams['SERVER_PROTOCOL'] = $protocol;
            }
            
            $query = $parts['query'] ?? '';

            $this->uriQueryString = $path;
            $this->serverParams['REQUEST_URI'] = ($query === '') ? $path : "{$path}?{$query}";
            $this->serverParams['QUERY_STRING'] = $query;

            $this->serverParams = array_merge(
                $this->headers ?? [], 
                $this->serverParams
            );
        }else{ 
            $this->uri = $this->detectRequestUri(true);

            $this->uriQueryString = strtok($this->uri, '?');
            $_SERVER['PATH_INFO'] = $this->uriQueryString;
        }


        $this->server = LazyObject::newObject(
            fn(): Server => new Server($isAuto ? $_SERVER : $this->serverParams)
        );

        $this->header = LazyObject::newObject(
            fn(): Header => new Header($isAuto ? null : $this->headers, self::$security)
        );
    }

    private function normalizeUriParts(UriInterface|string $uri): array
    {
        if ($uri instanceof Uri) {
            return $uri->toArray();
        }

        if ($uri instanceof UriInterface) {
            [$user, $pass] = array_pad(explode(':', $uri->getUserInfo(), 2), 2, null);

            return [
                'scheme' => $uri->getScheme() ?: null,
                'host'   => $uri->getHost() ?: null,
                'port'   => $uri->getPort(),
                'path'   => $uri->getPath() ?: '/',
                'query'  => $uri->getQuery() ?: null,
                'user'   => $user,
                'pass'   => $pass,
            ];
        }

        $parts = parse_url((string) $uri);
        if ($parts === false) {
            throw new InvalidArgumentException(sprintf('Malformed URI "%s".', $uri));
        }

        return $parts;
    }

    /**
     * Check of referer and origin.
     * 
     * @param string $context Context (e.g, `referer` or `origin`).
     * @param bool $strict Strict: $sameOrigin For `referer` and allow referer for `origin`.
     * @param bool $subdomains Allow subdomains If not `$sameOrigin` or strictly allow in `origin`. 
     * 
     * @return bool
     */
    private function isSameOriginFrom(
        string $context, 
        bool $strict = false, 
        bool $subdomains = false,
        ?array &$parts = null
    ): bool
    {
        $origin = '';
        $isReferer = $context === 'referer';

        if(!$isReferer){
            $origin = $this->header->get('Origin') ?? $this->server->get('HTTP_ORIGIN');
        }

        if($isReferer || (!$origin && $strict)){
            $origin = $this->header->get('Referer') 
                ?? $this->server->get('HTTP_REFERER');
        }

        if(!$origin){
            return false;
        }

        $parts = parse_url($origin) ?: null;

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            $parts = null;
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $parts = null;
            return false;
        }

        if ($isReferer && $strict) {
            $host = strtolower($this->getHostname(false));

            if (!$host || strtolower($parts['host']) !== $host) {
                $parts = null;
                return false;
            }

            return true;
        }

        $host = strtolower($parts['host']);
        $appHost = strtolower(APP_HOSTNAME);
        $isSame = $host === $appHost;

        if (!$subdomains) {
            if($isSame){
                return true;
            }
        }elseif($isSame || str_ends_with($host, '.' . $appHost)){
            return true;
        }

        $parts = null;
        return false;
    }

    /**
     * Parse the request body based on the request method.
     * 
     * @return void
     */
    private function parseRequestBody(): void
    {
        if ($this->parsedBody !== []) {
            return;
        }

        $method = $this->getMethod();
        $isStream = ($this->body instanceof StreamInterface);
    
        if($isStream || $this->body){
            if($isStream){
                $this->parsedBody = [$method => $this->readStreamBody($this->body)];
                return;
            }

            $this->parsedBody = [$method => $this->body[$method] ?? $this->body];
        }else{
            $this->parsedBody = [];
            $this->parsedBody[$method] = ($method === 'POST') ? $_POST : $_GET;
            $input = $this->getRaw();

            if(!empty($input)){
                $this->parsedBody[$method] = array_merge(
                    $this->parsedBody[$method],
                    $this->readStreamBody($input)
                );
                $input = null;
            }
        }

        if(!$this->isGet()){
            $this->parsedBody[$method] = $this->parsedBody[$method] + $this->getQueryParams();
        }

        if ($this->files === []) {
            $this->files = $_FILES ?? [];
        }
    }

    /**
     * Read request body from stream or raw input.
     * 
     * @param StreamInterface|array|string $input The request input.
     * 
     * @return array<string,mixed> Return request body.
     */
    private function readStreamBody(StreamInterface|array|string $input): array 
    {
        $body = [];
        $type = $this->getContentType();

        if(is_array($input)){
            $body = $input;
        }else{
            $input = (string) $input;

            if(str_contains($type, 'multipart/form-data')){
                $boundary = $this->getBoundary();

                if($boundary){
                    $result = self::getFromMultipart($input, '--' . $boundary);

                    $body = $result['params'] ?? [];
                    $this->files = $result['files'] ?? [];

                    $result = null;
                }
            } elseif(str_contains($type, 'application/json') && json_validate($input)) {
                try{
                    $body = json_decode($input, true, 512, JSON_THROW_ON_ERROR) ?: [];
                }catch(JsonException){}
            }

            if($body === [] && preg_match('/^[\w%]+=[^&]*(&[\w%]+=[^&]*)*$/', $input)){
                parse_str($input, $body);
            }

            if($body === [] && $input){
                $body = [$input];
            }
        }

        if ($this->files === []) {
            $this->files = $_FILES ?? [];
        }

        if($this->isGet()){
            return $body;
        }

        return $body + $this->getQueryParams();
    }

    /**
     * Parse the uploaded file(s) information and return file instances.
     *
     * @param array|null $file File information array.
     * @param int|null $index Optional file index for multiple files.
     * 
     * @return Generator<int,UploadedFileInterface,void,void>|UploadedFileInterface|null Return the parsed file information or null if the file array is empty.
     */
    private function parseRequestFile(
        ?array $file, 
        ?int $index = null,
        bool $isGenerator = true
    ): Generator|UploadedFileInterface|null
    {
        if($file === [] || $file === null){
            return null;
        }

        if (is_array($file['name'] ?? null)) {
            return ($index === null)
                ? $this->createFileGenerator($file, asGenerator: $isGenerator)
                : (isset($file['name'][$index]) ? $this->createFileInstance($file, $index) : null);
        }

        if (isset($file[0]['name'])) {
            return ($index === null) 
                ? $this->createFileGenerator($file, true, $isGenerator) 
                : $this->createFileInstance($file[$index] ?? [], $index, true);
        }

        return $this->createFileInstance($file, isFlat: true);
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
     * Create `UploadedFileInterface` instances from uploaded file data.
     *
     * Can return either a generator (lazy) or an array (eager), depending on `$asGenerator`.
     *
     * @param UploadedFileInterface[]array $files File data from the request (like $_FILES or normalized array)
     * @param bool $flat Flatten developer-friendly flat structure
     * @param bool $asGenerator Return a Generator if true; otherwise return an array
     *
     * @return Generator<int,UploadedFileInterface>|array<int,UploadedFileInterface>
     */
    private function createFileGenerator(array $files, bool $isFlat = false, bool $asGenerator = true): mixed
    {
        $generator = function (array $files, bool $isFlat): Generator {
            $entries = $isFlat ? $files : array_keys($files['name']);

            foreach ($entries as $index => $file) {
                yield $index => $this->createFileInstance($isFlat ? $file : $files, $index, $isFlat);
            }
        };

        if ($asGenerator) {
            return $generator($files, $isFlat);
        }

        return iterator_to_array($generator($files, $isFlat));
    }

    /**
     * Extract file array entry.
     * 
     * @param array $file The file array.
     * @param string $key The file index key.
     * @param int $index The un-flatten array index.
     * 
     * @return mixed Return entry value.
     */
    private static function fEntry(array $file, string $key, ?int $index): mixed 
    {
        $entry = ($index === null) 
            ? ($file[$key] ?? null)
            : ($file[$key][$index] ?? null);

        if($entry === null){
            return ($key === 'size') ? 0 : null;
        }

        return $entry;
    }

    /**
     * Create a File instance from the provided file data.
     *
     * @param UploadedFileInterface|array $file File data from the request.
     * @param int $index Index for handling multiple file uploads (default: 0).
     * @param bool $isFlat Wether index for handling multiple file uploads for flat structure (default: false).
     * 
     * @return UploadedFileInterface Return the created File instance.
     */
    private function createFileInstance(
        UploadedFileInterface|array $file, 
        int $index = 0, 
        bool $isFlat = false
    ): ?UploadedFileInterface
    {
        if ($file === []) {
            return null;
        }

        if ($file instanceof UploadedFileInterface) {
            return $file;
        }

        $eIndex = $isFlat ? null : $index;
        $name = self::fEntry($file, 'name', $eIndex);
        $temp = self::fEntry($file, 'tmp_name', $eIndex);
        $size = (int) self::fEntry($file, 'size', $eIndex);
        $type = self::fEntry($file,'type', $eIndex);
        $error = self::fEntry($file, 'error', $eIndex) ?? UPLOAD_ERR_OK;
        $content = self::fEntry($file, 'content', $eIndex);
        $path = self::fEntry($file, 'full_path', $eIndex); // For JS Upload Libraries

        $isBlob = ($content !== null || $this->isBlobUpload($temp, $type, $name, $path));
        $size = ($content && $size === 0) ? strlen($content) : $size;

        // Sanitize and derive name
        $path = ($path && $path !== 'blob') ? basename($path) : $path;
        $name ??= ($path && $path !== 'blob') ? $path : uniqid('file_', true);

        // Derive extension
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if(!$type && $extension){
           $type = MIME::guess($extension);
        }
        
        $type ??= MIME::guess($temp) ?: 'application/octet-stream';
        $extension = $extension ?: (MIME::findExtension($type) ?: '');

        if ($extension && !str_ends_with($name, ".$extension")) {
            $name .= ".$extension";
        }

        // Error handling for missing content or temp
        $error = ($error === UPLOAD_ERR_OK && ($size === 0 || ($content === null && $temp === null)) )
            ? UPLOAD_ERR_NO_FILE
            : $error;

        return new File(
            $index,
            $name,
            $type,
            $size,
            $extension,
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
     * Initializes request security configuration.
     * 
     * @return void
     */
    private static function initRequestSecurityConfig(): void
    {
        if(!self::$security instanceof Security){
            self::$security = new Security();
        }
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