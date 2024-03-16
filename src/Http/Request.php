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

use \Luminova\Http\Header;
use \Luminova\Exceptions\InvalidArgumentException;

class Request
{
    /**
     * @var array $get Http GET request method
     */
    private array $get;
    
    /**
     * @var array $post Http POST request method
     */
    private array $post;
    
    /**
     * @var array $put Http PUT request method
     */
    private array $put;
    
    /**
     * @var array $delete Http DELETE request method
     */
    private array $delete;
    
    /**
     * @var array $options Http OPTIONS request method
     */
    private array $options;
    
    /**
     * @var array $patch Http PATCH request method
     */
    private array $patch;
    
    /**
     * @var array $head Http HEAD request method
     */
    private array $head;
    
    /**
     * @var array $connect Http CONNECT request method
     */
    private array $connect;
    
    /**
     * @var array $trace Http TRACE request method
     */
    private array $trace;
    
    /**
     * @var array $propfind Http PROPFIND request method
     */
    private array $propfind;
    
    /**
     * @var array $mkcol Http MKCOL request method
     */
    private array $mkcol;
    
    /**
     * @var array $copy Http COPY request method
     */
    private array $copy;
    
    /**
     * @var array $move Http MOVE request method
     */
    private array $move;
    
    /**
     * @var array $lock Http LOCK request method
     */
    private array $lock;
    
    /**
     * @var array $unlock Http UNLOCK request method
     */
    private array $unlock;
    
    /**
     * @var array $body Http request body
    */
    private array $body;

    /**
     * @var array $methods Http request methods
    */
    private array $methods = [
        'get', 'post', 'put',
        'delete', 'options', 'patch',
        'head', 'connect', 'trace',
        'propfind', 'mkcol', 'copy', 'move', 'lock', 'unlock'
    ];    

    /**
     * Initializes
    */
    public function __construct()
    {

        $this->get = $_GET;
        $this->post = $_POST;
        
        foreach ($this->methods as $method) {
            if( $method !== 'post' && $method !== 'get'){
                $this->{$method} = $this->parseRequestBody($method);
            }
        }
        
        $this->body = $this->parseRequestBody();
    }

    /**
     * Get a value from the Specified request method.
     *
     * @param string $method
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public function find(string $method, string $key, mixed $default = null): mixed
    {
        $property = strtolower($method);
        $value = isset($this->methods[$property]) ? $this->{$property}[$key] : $default;

        return $value ?? $default;
    }

    /**
     * Get a value from the GET request.
     *
     * @param string $key
     * @param mixed $default
     * 
     * @deprecated This method will be changed later to replace find(), use getGet() instead 
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Get a value from the GET request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getGet(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Get a value from the POST request.
     *
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public function getPost(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get a value from the request context array.
     *
     * @param string $method request method context
     * @param string $key
     * @param string $index array index
     * @param mixed $default
     * 
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function getArray(string $method, string $key, string $index, mixed $default = null): mixed
    {
        $context = strtolower($method);
        if(in_array($context, $this->methods, true)){
            $contents = $this->{$context};
            
            if(isset($contents[$key])) {
                $content = $contents[$key];
                
                if(is_string($content)) {
                    $decodedArray = json_decode($content, true);
                    if ($decodedArray !== null) {
                        return $decodedArray[$index] ?? $default;
                    }
                }
                
                return $content[$index] ?? $default;
            }
        }
        
        throw new InvalidArgumentException("Method '$method' is not allowed. Use any of [" . implode(', ', $this->methods) . "]");
    }



    /**
     * Get a value from the PUT request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPut(string $key, mixed $default = null): mixed
    {
        return $this->put[$key] ?? $default;
    }

    /**
     * Get a value from the DELETE request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getDelete(string $key, mixed $default = null): mixed
    {
        return $this->delete[$key] ?? $default;
    }

    /**
     * Get a value from the OPTIONS request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Get a value from the PATCH request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPatch(string $key, mixed $default = null): mixed
    {
        return $this->patch[$key] ?? $default;
    }

    /**
     * Get a value from the HEAD request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getHead(string $key, mixed $default = null): mixed
    {
        return $this->head[$key] ?? $default;
    }

    /**
     * Get a value from the CONNECT request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConnect(string $key, mixed $default = null): mixed
    {
        return $this->connect[$key] ?? $default;
    }

    /**
     * Get a value from the TRACE request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getTrace(string $key, mixed $default = null): mixed
    {
        return $this->trace[$key] ?? $default;
    }

    /**
     * Get a value from the PROPFIND request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPropfind(string $key, mixed $default = null): mixed
    {
        return $this->propfind[$key] ?? $default;
    }

    /**
     * Get a value from the MKCOL request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMkcol(string $key, mixed $default = null): mixed
    {
        return $this->mkcol[$key] ?? $default;
    }

    /**
     * Get a value from the COPY request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getCopy(string $key, mixed $default = null): mixed
    {
        return $this->copy[$key] ?? $default;
    }

    /**
     * Get a value from the MOVE request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMove(string $key, mixed $default = null): mixed
    {
        return $this->move[$key] ?? $default;
    }

    /**
     * Get a value from the LOCK request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getLock(string $key,mixed  $default = null): mixed
    {
        return $this->lock[$key] ?? $default;
    }

    /**
     * Get a value from the UNLOCK request.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getUnlock(string $key, mixed $default = null): mixed
    {
        return $this->unlock[$key] ?? $default;
    }

    /**
     * Get the request body as an array.
     *
     * @return array
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Get the request body as an object.
     *
     * @return object
     */
    public function getBodyAsObject(): object
    {
        return (object) $this->body;
    }

    /**
     * Get the uploaded file information.
     * @param string $name file name
     * @return object|null
    */
    public function getFile(string $name): ?object
    {
        if (isset($_FILES[$name])) {
            return $this->parseFiles($_FILES[$name]);
        }
        return null;
    }

    /**
     * Get the uploaded files information.
     *
     * @return object|null
    */
    public function getFiles(): ?object
    {
        $files = [];
        foreach ($_FILES as $index => $fileInfo) {
            $files[] = $this->parseFiles($fileInfo, $index);
        }
        if( $files  == []){
            return null;
        }
        return (object) $files;
    }

    /**
     * Get the uploaded files information.
     * @param array $fileInfo file array information
     * @param int $index file index
     * @return object
    */
    private function parseFiles(array $fileInfo, int $index = 0): object{
        if(empty($fileInfo)){
            return (object)[];
        }

        $extension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
        $mime = mime_content_type($fileInfo['tmp_name']);
        if($extension === ''){
            [$format, $extension] = explode('/', $mime);
            $fileInfo['name'] = uniqid('file_') . '.' . $extension;
        }
        
        return (object)[
            'index' => $index,
            'name' => $fileInfo['name'] ?? null,
            'type' => $fileInfo['type'] ?? null,
            'format' => $format ?? null,
            'size' => $fileInfo['size']??0,
            'mime' => $mime ?? null,
            'extension' => strtolower( $extension ?? '' ),
            'temp' => $fileInfo['tmp_name'] ?? null,
            'error' => $fileInfo['error'] ?? null,
        ];
    }

     /**
     * Get the request method 
     *
     * @return string The Request method
    */
    public function getMethod(): string
    {
        return strtolower($_SERVER['REQUEST_METHOD']??'');
    }

    /**
     * Check if the request method is get
     *
     * @return bool
    */
    public function isGet(): bool
    {
        return $this->getMethod() === 'get';
    }

    /**
     * Check if the request method is post
     *
     * @return bool
    */
    public function isPost(): bool
    {
        return $this->getMethod() === 'post';
    }

    /**
     * Check if the request method is
     * @param string $method 
     * 
     * @return bool
    */
    public function isMethod(string $method): bool
    {
        $method = strtolower($method);

        return $this->getMethod() === $method;
    }

     /**
     * Get the request content type
     *
     * @return string The Request content type
     */
    public function getContentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    /**
     * Parse the request body based on the request method.
     *
     * @param string|null $method
     * @return array
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
	 * Get authorization header
     * 
     * @return string
	*/
    public function getAuthorization(): string
    {
		return Header::getAuthorization();
	}
	
	/**
	 * get access token from header
     * 
     * @return string|null
	*/
	public function getAuthBearer(): ?string 
    {
		$auth = Header::getAuthorization();
		if (!empty($auth)) {
			if (preg_match('/Bearer\s(\S+)/', $auth, $matches)) {
				return $matches[1];
			}
		}
		return null;
	}

    /**
     * Is CLI?
     *
     * Test to see if a request was made from the command line.
     *
     * @return bool
    */
    public function isCommandLine(): bool
    {
        return defined('STDIN') ||
            (empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0) ||
            php_sapi_name() === 'cli' ||
            array_key_exists('SHELL', $_ENV) ||
            !array_key_exists('REQUEST_METHOD', $_SERVER);
    }

    /**
     * Check if the current connection is secure
     * 
     * @return bool 
    */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    }

    /**
     * Check if request is ajax request
     * Test to see if a request contains the HTTP_X_REQUESTED_WITH header.
     * 
     * @return bool 
    */
    public function isAJAX(): bool
    {
        $with = $_SERVER['HTTP_X_REQUESTED_WITH']??'';
        return $with !== '' && strtolower($with) === 'xmlhttprequest';
    }

    /**
     * Get request url
     * 
     * @return string 
    */
    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * Get user browser info
     * 
     * @return array 
    */
    public function getBrowser(): array
    {
        if (ini_get('browscap')) {
            $browser = get_browser(null, true);
            
            if ($browser !== false) {
                return $browser;
            }
        }

        // If get_browser() fails, fallback to parsing the user agent string
        return self::parseUserAgent();
    }

    /**
     * Pass user agent string browser info
     * 
     * @param ?string $userAgent
     * @param bool $returnObject If set to true, this function will return an array instead of an object.
     * 
     * @return array|object 
    */
    public static function parseUserAgent(?string $userAgent = null, bool $returnObject = false): array|object
    {
        if($userAgent === null){
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        $browserInfo = [];

        if($userAgent !== ''){
            $pattern = '/^(.*?)\/([\d.]+) \(([^;]+); ([^;]+); ([^)]+)\) (.+)$/';
            if (preg_match($pattern, $userAgent, $matches)) {
                $browserInfo['userAgent'] = $matches[0]; // Full User Agent String
                $browserInfo['parent'] = $matches[1] . ' ' . $matches[2]; // Browser Name & Version
                $browserInfo['browser'] = $matches[1];
                $browserInfo['version'] = $matches[2]; // Browser Version
                $browserInfo['platform'] = $matches[3]; // Operating System Name
                $browserInfo['platform_version'] = $matches[4]; // Operating System Version
                //$browserInfo['additional_info'] = $matches[5]; // Additional Information
                //$browserInfo['gecko_info'] = $matches[6]; // Gecko Information
            }
        }

        if ($returnObject) {
            return (object) $browserInfo;
        }
        
        return $browserInfo;
    }

    /**
     * Get user agent string
     * 
     * @return string 
    */
    public function getUserAgent(): string
    {
        $browser = $this->getBrowser();

        return $browser['browser'] . ' on ' . $browser['platform'];
    }

    /**
     * Check if request header exist
     * 
     * @param string $headerName
     * 
     * @return bool 
    */
    public function hasHeader(string $key): bool
    {
        return array_key_exists($key, $_SERVER);
    }

    /**
     * Get request header by key name.
     * 
     * @param string $key
     * 
     * @return Header|null header instance
    */
    public function header(string $key): ?Header
    {
        if ($this->hasHeader($key)) {
            return new Header($_SERVER[$key]);
        }
        return null;
    }

    /**
     * Get request headers.
     *
     * @return array The request headers
    */
    public function getHeaders(): array 
    {
        return Header::getHeaders();
    }

    /**
     * Get request header.
     *
     * @return string The request headers
    */
    public function getHeader(string $key): string 
    {
        $headers = Header::getHeaders();
        $key = strtoupper($key);
        
        if (isset($headers[$key])) {
            return $headers[$key];
        }
        
        // Replace underscores with hyphens
        $normalized = str_replace('_', '-', $key);

        if (isset($headers[$normalized])) {
            return $headers[$normalized];
        }
        
        // Remove "HTTP_" prefix and replace underscores with hyphens
        $stripped = str_replace('_', '-', substr($key, 5));

        if (isset($headers[$stripped])) {
            return $headers[$stripped];
        }

        return ''; 
    }

}
