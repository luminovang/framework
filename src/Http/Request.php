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
use \Luminova\Functions\IPAddress;
use \Luminova\Functions\Normalizer;
use \App\Controllers\Config\Security;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\SecurityException;

/**
 * Requet HTTP getter method 
 * 
 * @method mixed getGet(string $key, mixed $default = null)       Get a value from the GET request.
 * @method mixed getPost(string $key, mixed $default = null)      Get a value from the POST request.
 * @method mixed getPut(string $key, mixed $default = null)       Get a value from the PUT request.
 * @method mixed getOptions(string $key, mixed $default = null)   Get a value from the OPTIONS request.
 * @method mixed getPatch(string $key, mixed $default = null)     Get a value from the PATCH request.
 * @method mixed getHead(string $key, mixed $default = null)      Get a value from the HEAD request.
 * @method mixed getConnect(string $key, mixed $default = null)   Get a value from the CONNECT request.
 * @method mixed getTrace(string $key, mixed $default = null)     Get a value from the TRACE request.
 * @method mixed getPropfind(string $key, mixed $default = null)  Get a value from the PROPFIND request.
 * @method mixed getMkcol(string $key, mixed $default = null)     Get a value from the MKCOL request.
 * @method mixed getCopy(string $key, mixed $default = null)      Get a value from the COPY request.
 * @method mixed getMove(string $key, mixed $default = null)      Get a value from the MOVE request.
 * @method mixed getLock(string $key, mixed $default = null)      Get a value from the LOCK request.
 * @method mixed getUnlock(string $key, mixed $default = null)    Get a value from the UNLOCK request.
 * 
 * @param string $key     The key of the value to retrieve.
 * @param mixed $default  (optional) The default value to return if the key is not found.
 * @return mixed Response from HTTP request method body key value.
 */
final class Request
{
    /**
     * Http request method and body.
     *
     * @var array<string,mixed> $httpBody
     */
    private array $httpBody = [];

    /**
     * Http request body.
     *
     * @var array<string,mixed> $body
     */
    private array $body = [];

    /**
     * Http request methods.
     *
     * @var array<int, string> $methods
     */
    private array $methods = [
        'get', 'post', 'put','delete', 'options', 
        'patch', 'head', 'connect', 'trace', 'propfind', 
        'mkcol', 'copy', 'move', 'lock', 'unlock'
    ];    

    /**
     * Http server instance.
     *
     * @var null|Server $server
     */
    public ?Server $server = null;

    /**
     * Http request header instance.
     *
     * @var null|Header $header
    */
    public ?Header $header = null;

     /**
     * Browser request user-agent information.
     *
     * @var null|UserAgent $agent
     */
    public ?UserAgent $agent = null;

    /**
     * Request uri 
     * 
     * @var null|string $uri
    */
    private ?string $uri = null;

    /**
     * Initializes the Request object.
     */
    public function __construct()
    {
        $this->server = new Server((array) $_SERVER);
        $this->header = new Header();
        $this->httpBody['get'] = $_GET;
        $this->httpBody['post'] = $_POST;
        $this->uri = null;

        foreach ($this->methods as $method) {
            if( $method !== 'post' && $method !== 'get'){
                $this->httpBody[$method] = $this->parseRequestBody($method);
            }
        }
        
        $this->body = $this->parseRequestBody();
    }

    /**
     * Get a value from the HTTP request.
     *
     * @param string $key HTTP request body key.
     * @param array $arguments Arguments as the default value (default: blank string).
     * 
     * @return mixed Return value from the HTTP request if is set otherwise return null.
     * @internal
    */
    public function __call(string $key, array $arguments): mixed 
    {
        $method = strtolower(substr($key, 3));
    
        if(in_array($method, $this->methods, true)){
            $default = ($arguments[1] ?? '');

            if(isset($this->httpBody[$method])){
                return $this->httpBody[$method][$arguments[0]] ??  $default;
            }

            return $default;
        }
       
        return null;
    }

    /**
     * Get a value from the request method context array.
     *
     * @param string $method HTTP request method context.
     * @param string $key    Request body key.
     * @param array $default Default value.
     * 
     * @return array Return array of HTTP request method key values.
     * @throws InvalidArgumentException Throws if unsupported HTTP method was passed.
     */
    public function getArray(string $method, string $key, array $default = []): array
    {
        $method = strtolower($method);
        if(in_array($method, $this->methods, true)){
            if(isset($this->httpBody[$method][$key])) {
                $result = $this->httpBody[$method][$key];
                
                if(is_string($result)) {
                    $decode = json_decode($result, true);

                    if ($decode !== null || $decode !== false) {
                        return (array) $decode ?? $default;
                    }
                }
                
                return (array) $result ?? $default;
            }
        }
        
        throw new InvalidArgumentException("Request method '$method' is not supported, supported methods are [" . implode(', ', $this->methods) . "]");
    }

    /**
     * Get the request body as an array or json object.
     * 
     * @param bool $object Whether to return an array or a json object (default: false).
     * 
     * @return array|object Return the request body as an array or json object.
     */
    public function getBody(bool $object = false): array|object
    {
        if($object){
            return (object) $this->body;
        }

        return $this->body;
    }

   /**
     * Get the uploaded file information.
     * 
     * @param string $name File name.
     * 
     * @return File|false Uploaded file information or false if file not found.
     */
    public function getFile(string $name): File|false
    {
        if (isset($_FILES[$name])) {
            return $this->parseFile($_FILES[$name]);
        }

        return false;
    }

    /**
     * Get the uploaded files information.
     *
     * @return false|array<int,File> Uploaded files information or false if no files found.
    */
    public function getFiles(): array|false
    {
        $files = [];
        foreach ($_FILES as $index => $fileInfo) {
            $files[] = $this->parseFile($fileInfo, $index);
        }

        if($files === []){
            return false;
        }

        return $files;
    }

    /**
     * Get the request method.
     *
     * @return string Return the request method in lowercased.
    */
    public function getMethod(): string
    {
        return strtolower($this->server->get('REQUEST_METHOD', ''));
    }

    /**
     * Check if the request method is GET.
     *
     * @return bool Returns true if the request method is GET, false otherwise.
    */
    public function isGet(): bool
    {
        return $this->getMethod() === 'get';
    }

   /**
     * Check if the request method is POST.
     *
     * @return bool Returns true if the request method is POST, false otherwise.
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'post';
    }

    /**
     * Check if the request method is the provided method.
     *
     * @param string $method The method to check against.
     * 
     * @return bool Returns true if the request method matches the provided method, false otherwise.
    */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtolower($method);
    }

    /**
     * Get the request content type.
     *
     * @return string The request content type.
     */
    public function getContentType(): string
    {
        return $this->server->get('CONTENT_TYPE', '');
    }

    /**
     * Get request header authorization header [HTTP_AUTHORIZATION, Authorization].
     * 
     * @return string|null Return the authorization header value or null if no authorization header was sent.
     */
    public function getAuth(): string|null
    {
        if(!$auth = $this->header->get('Authorization')){
            if(!$auth = $this->server->get('HTTP_AUTHORIZATION')){
                $auth = $this->server->get('REDIRECT_HTTP_AUTHORIZATION');
            }
        }

        if($auth === null){
            return null;
        }

        return trim($auth ?? '');
    }

    /**
     * Check to see if a request was made from the command line.
     *
     * @return bool Return true if the request was made from the command line.
     */
    public function isCommand(): bool
    {
        return is_command();
    }

    /**
     * Check if the current connection is secure
     * 
     * @return bool Return true if the connection is secure false otherwise.
     */
    public function isSecure(): bool
    {
        return ($this->server->get('HTTPS') !== 'off' || $this->server->get('SERVER_PORT') === 443);
    }

    /**
     * Check if request is ajax request, see if a request contains the HTTP_X_REQUESTED_WITH header.
     * 
     * @return bool Return true if request is ajax request, false otherwise
     */
    public function isAJAX(): bool
    {
        return strtolower($this->server->get('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Check if the request URL indicates an API endpoint.
     *
     * This method checks if the URL path starts with '/api' or 'public/api'.
     * 
     * @return bool Returns true if the URL indicates an API endpoint, false otherwise.
     */
    public function isApi(): bool
    {
        return Foundation::isApiContext();
    }
    
    /**
     * Get the request url query string.
     *
     * @return string Return url query string.
    */
    public function getQuery(): string
    {
        $queries = $this->getQueries();

        if($queries === null){
            return '';
        }

        return http_build_query($queries, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Get current url query parameters as an array.
     * 
     * @return array<string, mixed> Url query parameters.
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
     * Get current request url
     * 
     * @return string Return request url or null if not set.
    */
    public function getUri(): string
    {
        return $this->getScheme() . '://' . $this->getHost() . $this->server->get('REQUEST_URI', '');

        /* 
        if (($params = $this->getQuery()) !== '') {
            $params = '?' . $params;
        }

        return $this->getScheme().'://'.$this->getHost() . $this->getPaths() . $params;*/
    }

    /**
     * Get current request url path information
     * 
     * @return string Return request url paths.
    */
    public function getPaths(): string
    {
        $url = $this->server->get('REQUEST_URI', '');

        if($url === ''){
            return '';
        }

        return parse_url($url, PHP_URL_PATH);
    }

    /**
     * Returns the requested URI (path and query string).
     *
     * @return string The raw URI (i.e. not URI decoded)
    */
    public function getRequestUri(): string
    {
        if($this->uri === null){
            $this->uri = $this->extractRequestUri();
        }
        
        return $this->uri;
    }

    /**
     * Get current hostname without port, if allowed host is set it will check if host is in allowed list or patterns.
     * 
     * @param bool $extension Should throw an exception if invalid host or not allowed host (default: false).
     * 
     * @return string Return hostname.
     * @throws SecurityException If host is invalid or not allowed.
    */
    public function getHost(bool $extension = false): string|null
    {
        return $this->getHostname($extension, false);
    }

    /**
     * Get current hostname with port if port is available. 
     * If allowed host is set it will check if host is in allowed list or patterns.
     * 
     * @param bool $extension Should throw an exception if invalid host or not allowed host (default: false).
     * @param bool $port Should return hostname with port (default: true).
     * 
     * @return string Return hostname.
     * @throws SecurityException If host is invalid or not allowed.
    */
    public function getHostname(bool $extension = false, bool $port = true): string|null
    {
        if (!$hostname = $this->server->get('HTTP_HOST')) {
            if (!$hostname = $this->header->get('HOST')) {
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
    
        if($extension){
            throw new SecurityException(sprintf($error, $hostname));
        }

        return '';
    }

    /**
     * Get the origin domain if list of trusted origin domains are specified.
     * It will check if the origin is a trusted origin domain.
     * 
     * @return string|null Origin domain if found and trusted, otherwise null.
    */
    public function getOrigin(): ?string
    {
        $origin = $this->server->get('HTTP_ORIGIN');

        if (!$origin) {
            return null;
        }

        if(Security::$trustedOrigins === []){
            return $origin;
        }

        $domain = parse_url($origin, PHP_URL_HOST);

        if ($domain === '') {
            return null;
        }

        if(static::isTrusted($domain, 'origin')){
            return $domain;
        }

        return null;
    }

    /**
     * Get the request origin port.
     *
     * @return int|string|null Can be a string if fetched from the server bag
     * 
     * > Check if X-Forwarded-Port header exists and use it, if available.
     * > If not available check for server-port header if also not available return NULL as default.
    */
    public function getPort(): int|string|null
    {
        if (!$port = $this->server->get('HTTP_X_FORWARDED_PORT')) {
            if (!$port = $this->server->get('X_FORWARDED_PORT')) {
                return $this->server->get('SERVER_PORT');
            }
        }
        
        $pos = ('[' === $port[0]) ? strpos($port, ':', strrpos($port, ']')) : strrpos($port, ':');

        if ($pos !== false && $port = substr($port, $pos + 1)) {
            return (int) $port;
        }

        return $this->isSecure() ? 443 : 80;
    }

    /**
     * Gets the request's scheme.
    */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Gets the request server protocol (e.g: HTTP/1.1).
    */
    public function getProtocol(): string
    {
        return $this->server->get('SERVER_PROTOCOL', 'HTTP/1.1');
    }
 
    /**
     * Get user browser information.
     * 
     * @return string Return browser name and platform.
     */
    public function getBrowser(): string
    {
       $agent = $this->getUserAgent();

       return $agent->getBrowser() . ' on ' . $agent->getPlatform();
    }

    /**
     * Get browser user-agent information.
     * 
     * @param string|null $useragent The User Agent string. If not provided, it defaults to $_SERVER['HTTP_USER_AGENT'].
     * 
     * @return UserAgent Return user agent instance.
     */
    public function getUserAgent(?string $useragent = null): UserAgent
    {
        if($this->agent === null){
            $this->agent = new UserAgent($useragent);
        }

        return $this->agent;
    }

    /**
     * Check if the request's origin matches the current host.
     *
     * @param bool $subdomains Whether to consider subdomains or not. Default is true.
     * 
     * @return bool Returns true if the request's origin matches the current host, false otherwise.
     */
    public function isSameOrigin(bool $subdomains = true): bool
    {
        $origin = $this->server->get('HTTP_ORIGIN');

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

        if ($subdomains) {
            return Normalizer::mainDomain($origin) === APP_HOSTNAME;
        }

        return false;
    }

    /**
     * Check if the given (hostnames, origins, proxy ip or subnet) matches any of the trusted patterns.
     * 
     * @param string $input The domain, origin or ip address to check.
     * @param string $context The context to check (hostname, origin or proxy).
     * 
     * @return bool Return true if the input is trusted, false otherwise.
     * @throws InvalidArgumentException If invalid context is provided.
     */
    public static function isTrusted(string $input, string $context = 'hostname'): bool
    {
        if($context !== 'hostname' && $context !== 'origin' && $context !== 'proxy'){
            throw new InvalidArgumentException(sprintf('Invalid Context "%s".', $context));
        }

        if($context === 'proxy'){
            return IPAddress::isTrustedProxy($input);
        }

        $trusted = ($context === 'hostname') ? Security::$trustedHostname : Security::$trustedOrigins;

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
     * Check whether this request origin ip address is from a trusted proxy.
     * 
     * @return bool Return true if the request origin ip address is trusted false otherwise.
    */
    public function isTrustedProxy(): bool
    {
        return static::isTrusted($this->server->get('REMOTE_ADDR', ''), 'proxy');
    }

    /**
     * Check whether this request origin is from a trusted origins.
     * 
     * @return bool Return true if the request origin is trusted false otherwise.
    */
    public function isTrustedOrigin(): bool
    {
        $origin = $this->server->get('HTTP_ORIGIN');

        if (!$origin) {
            return false;
        }

        if(Security::$trustedOrigins === []){
            return true;
        }

        $domain = parse_url($origin, PHP_URL_HOST);

        if ($domain === '') {
            return false;
        }

        if(static::isTrusted($domain, 'origin')){
            return true;
        }

        return false;
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
        } elseif ($this->server->has('REQUEST_URI')) {
            $uri = $this->server->get('REQUEST_URI');

            // To only use path and query remove the fragment.
            $uri = strtok($uri, '#');

            if (strpos($uri, '?') !== false) {
                // Remove the fragment if exists
                $uri = strtok($uri, '#');
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $uri = $this->server->get('ORIG_PATH_INFO');
            if ($this->server->has('QUERY_STRING') && $query = $this->server->get('QUERY_STRING')) {
                $uri .= '?' . $query;
            }
            $this->server->remove('ORIG_PATH_INFO');
        }

        // Set the request URI
        $this->server->set('REQUEST_URI', $uri);

        return $uri;
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
     * @param string|null $method HTTP request method.
     * 
     * @return array Return request body as an array.
     */
    private function parseRequestBody(?string $method = null): array
    {
        $body = [];

        if ($method === null || $this->getMethod() === $method) {
            $input = file_get_contents('php://input');
            $type = $this->getContentType();
            if ($type !== '' && strpos($type, 'multipart/form-data') !== false) {

                $body = array_merge($_FILES, $_POST);
               
                if ($input !== false) {
                    parse_str($input, $fields);
                    $body = array_merge($body, $fields);
                }
            } else {
                if ($input !== false) {
                    parse_str($input, $body);
                }
            }
        }

        return $body;
    }

    /**
     * Parse the uploaded files information and return file instance.
     *
     * @param array $file File information array.
     * @param int $index File index.
     * 
     * @return File|false Return parsed file information or false if no file found.
     */
    protected function parseFile(array $file, int $index = 0): File|false
    {
        if(empty($file)){
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $mime = get_mime($file['tmp_name']);

        if($extension === '' && $mime !== false){
            [, $extension] = explode('/', $mime);
            $file['name'] = uniqid('file_') . '.' . $extension;
        }

        return new File(
            $index,
            $file['name'] ?? null,
            $file['type'] ?? null,
            (int) ($file['size'] ?? 0),
            ($mime === false ? null : $mime),
            (empty($extension) ? '' : strtolower($extension)),
            $file['tmp_name'] ?? null,
            $file['error'] ?? 0
        );
    }
}
