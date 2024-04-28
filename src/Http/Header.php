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

class Header
{
    /**
     * All allowed HTTP request methods, must be in upper case.
     * @usages Router
     * 
     * @var array<int,string> $httpMethods
    */
    public static array $httpMethods = ['GET', 'POST', 'PATCH', 'DELETE', 'PUT', 'OPTIONS', 'HEAD'];

    /**
     * Header variables.
     * 
     * @var array $variables
    */
    protected array $variables = [];

    /**
     * Initializes the header constructor.
     * 
     * @param array<string, mixed> $variables.
    */
    public function __construct(?array $variables = null)
    {
        $variables ??= static::getHeaders();

        $this->variables = $variables;
    }

   /**
     * Get header variables.
     *
     * @param string|null $name Optional name of the server variable.
     * @param mixed $default Default value for the server key.
     *
     * @return mixed|array|string|null The value of the specified server variable, or all server variables if $name is null.
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null || $name === '') {
            return $this->variables;
        }

        if($this->has($name)){
            return $this->variables[$name];
        }

        return $default;
    }

    /**
     * Set server variable.
     * 
     * @param string $key The server variable key to set.
     * @param string $value The server variable value.
     * 
     * @return void
    */
    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    /**
     * Removes a server variable by key
     * 
     * @param string $key The key to remove.
    */
    public function remove(string $key): void
    {
        unset($this->variables[$key]);
    }

    /**
     * Check if request header key exist.
     * 
     * @param string $key Header key to check.
     * 
     * @return bool Return true if key exists, false otherwise.
    */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->variables);
    }

     /**
     * Get the total number of server variables.
     * 
     * @return int Number of server variables
     */
    public function count(): int
    {
        return count($this->variables);
    }

     /**
     * Get server variables.
     *
     * @param string $key Key name of the server variable.
     *
     * @return mixed The value of the specified server variable, or all server variables if $name is null.
     * @internal
     */
    public static function server(string $key): mixed
    {
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        return null;
    }

   /**
     * Get all request headers.
     *
     * @return array<string,string> The request headers.
     */
    public static function getHeaders(): array
    {
        $headers = [];

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();

            if ($headers !== false) {
                return $headers;
            }
        }

        // If PHP function apache_request_headers() is not available or went wrong: manually extract headers
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_' || $name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH') {
                $header = str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get the request method.
     *
     * @return string The request method.
     * @internal
     */
    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD']??'';
    }

    /**
     * Get system headers.
     *
     * @return array<string,string> The system headers.
     * @ignore
     */
    public static function getSystemHeaders(): array
    {
        return [
            'Content-Type' => 'text/html',
            'Cache-Control' => env('default.cache.control', 'no-store, max-age=0, no-cache'),
            'Content-Language' => env('app.locale', 'en'), 
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN', //'deny',
            'X-XSS-Protection' => '1; mode=block',
            'X-Firefox-Spdy' => 'h2',
            'Vary' => 'Accept-Encoding',
            'Connection' => 'keep-alive', //'close',
            'X-Powered-By' => Foundation::copyright()
        ];
    }

    /**
     * Set no caching headers.
     * 
     * @param int $status HTTP status code.
     * 
     * @return void 
     * @internal Used in router and template.
     */
    public static function headerNoCache(int $status = 200): void 
    {
        static::parseHeaders([
            'X-Powered-By' => Foundation::copyright(),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Expires' => '0',
            'Content-Type' => 'text/html'
        ], $status);
    }

    /**
     * Parse and modify the "Content-Type" header to ensure it contains "charset".
     *
     * @param array $headers An array of HTTP headers.
     * @param int|null $statusCode HTTP response code (default: NULL)
     * 
     * @return void
     * @internal
     */
    public static function parseHeaders(array $headers, ?int $statusCode = null): void
    {
        if (headers_sent()) {
            return; 
        }
        
        if($statusCode !== null){
            http_response_code($statusCode);
        }
        foreach ($headers as $header => $value) {
            if ($header === 'Content-Type' && strpos($value, 'charset') === false) {
                header("$header: {$value}; charset=" . env('app.charset', 'utf-8'));
            }else{
                header("$header: $value");
            }
        }
    }

    /**
     * Get the content type based on file extension and charset.
     *
     * @param string $extension The file extension.
     * @param string $charset The character set.
     *
     * @return string The content type.
     */
    public static function getContentType(string $extension = 'html', ?string $charset = null): string
    {
        $charset ??= env('app.charset', 'utf-8');
        $types = static::contentTypes($extension);

        if($types === null){
            $types = ['text/html'];
        }

        return $types[0] . ($charset !== '' ? '; charset=' . $charset : '');
    }

    /**
     * Get content types by name 
     * 
     * @param string $type Type of content types to retrieve.
     * 
     * @return array<int,array> Array of content types or null if not found.
    */
    public static function getContentTypes(string $type): ?array 
    {
        $types = [
            'html'   => ['text/html', 'application/xhtml+xml'],
            'text'   => ['text/plain'],
            'js'     => ['application/javascript', 'application/x-javascript', 'text/javascript'],
            'css'    => ['text/css'],
            'json'   => ['application/json', 'application/x-json'],
            'jsonld' => ['application/ld+json'],
            'xml'    => ['application/xml', 'text/xml', 'application/x-xml'],
            'rdf'    => ['application/rdf+xml'],
            'atom'   => ['application/atom+xml'],
            'rss'    => ['application/rss+xml'],
            'form'   => ['application/x-www-form-urlencoded', 'multipart/form-data'],
        ];

        return $types[$type] ?? null;
    }

   /**
     * Get the request method for routing, considering overrides.
     *
     * @return string The request method for routing.
     * @internal
     */
    public static function getRoutingMethod(): string
    {
        $method = static::getMethod();

        if($method === '' && php_sapi_name() === 'cli'){
            return 'CLI';
        }
  
        if($method === 'HEAD'){
            ob_start();
            return 'GET';
        }

        if($method === 'POST'){
            $headers = static::getHeaders();

            if (isset($headers['X-HTTP-Method-Override']) && in_array($headers['X-HTTP-Method-Override'], ['PUT', 'DELETE', 'PATCH'])) {
                $method = $headers['X-HTTP-Method-Override'];
            }
        }
        
        return $method;
    }

    /**
     * Get request header authorization header.
     *
     * @return string|null The authorization header, or null if not present.
     */
    public static function getAuthorization(): string|null
    {
		$auth = null;

        if(!$auth = static::server('Authorization')){
            if(!$auth = static::server('HTTP_AUTHORIZATION')){ //Nginx or fast CGI
                if(function_exists('apache_request_headers')){ 
                    $headers = apache_request_headers();
                    $headers = array_combine(
                        array_map('ucwords', array_keys($headers)),
                        array_values($headers)
                    );
        
                    if (isset($headers['Authorization'])) {
                        $auth = $headers['Authorization'];
                    }
                }
            }
        }

        if($auth === null){
            return null;
        }

        return trim($auth);
	}
}