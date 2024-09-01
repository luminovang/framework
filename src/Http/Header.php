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
use \Luminova\Functions\Func;
use \App\Config\Apis;
use \Countable;

class Header implements Countable
{
    /**
     * All allowed HTTP request methods, must be in upper case.
     * @usages Router
     * 
     * @var string[] $httpMethods
    */
    public static array $httpMethods = ['GET', 'POST', 'PATCH', 'DELETE', 'PUT', 'OPTIONS', 'HEAD'];

    /**
     * Content types.
     * 
     * @var array<int,array> $contentTypes
    */
    private static array $contentTypes = [
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
        'form'   => ['application/x-www-form-urlencoded', 'multipart/form-data']
    ];

    /**
     * Header variables.
     * 
     * @var array<string,mixed> $variables
    */
    protected array $variables = [];

    /**
     * API configuration.
     * 
     * @var Apis $config
    */
    private static ?Apis $config = null;

    /**
     * Initializes the header constructor.
     * 
     * @param array<string,mixed> $variables The header variables key-pair.
    */
    public function __construct(?array $variables = null)
    {
        $this->variables = $variables ?? static::getHeaders();
    }

    /**
     * Initializes API configuration.
    */
    private static function initConfig(): void
    {
        self::$config ??= new Apis();
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
     * @param mixed $value The server variable value.
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
     * 
     * @return void 
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
     * @return int Return total number of server variables.
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
     * @return mixed Return the value of the specified server variable, or all server variables if $name is null.
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
     * Extract all request headers from apache_request_headers or _SERVER variables.
     *
     * @return array<string,string> Return the request headers.
     */
    public static function getHeaders(): array
    {
        if (function_exists('apache_request_headers') && ($headers = apache_request_headers()) !== false) {
            return $headers;
        }

        $headers = [];
        // If PHP function apache_request_headers() is not available or went wrong: manually extract headers
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_') || $name == 'CONTENT_TYPE' || $name == 'CONTENT_LENGTH') {
                $header = str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Retrieves specific HTTP headers from the current request, 
     * filtering for content-related headers.
     * 
     * @return array Return n associative array containing 'Content-Type', 
     * 'Content-Length', and 'Content-Encoding' headers.
     */
    public static function requestHeaders(): array
    {
        $headers = headers_list();
        $info = [];

        foreach ($headers as $header) {
            $header = trim($header);

            if (!str_starts_with($header, 'Content-')) {
                continue;
            }

            [$name, $value] = explode(':', $header, 2);
            $value = trim($value);
            $key = trim($name);

            if ($key === 'Content-Type' || $key === 'Content-Encoding') {
                $info[$key] = $value;
            } elseif ($key === 'Content-Length') {
                $info[$key] = (int) $value;
            }
        }

        return $info;
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
            'Content-Type'  => 'text/html',
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
    public static function headerNoCache(
        int $status = 200, 
        string|bool|null $contentType = null, 
        string|int|null $retry = null
    ): void 
    {
        $headers = [
            'X-Powered-By' => Foundation::copyright(),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Expires' => '0',
            'Content-Type' => $contentType ?? 'text/html'
        ];

        if($retry !== null){
            $headers['Retry-After'] = $retry;
        }

        static::parseHeaders($headers, $status);
    }

   /**
     * Parses and modifies the HTTP headers, ensuring necessary headers are set.
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

        if ($statusCode !== null) {
            http_response_code($statusCode);
        }

        if (isset($headers['default_headers'])) {
            $headers = array_replace(static::getSystemHeaders(), $headers);
        }

        if (Foundation::isApiContext()) {
            self::initConfig();
            $origin = static::server('HTTP_ORIGIN');
            
            if($origin){
                if (!isset($headers['Access-Control-Allow-Origin']) && !empty(self::$config->allowOrigins)) {
                    $allowed = null;
                    if (self::$config->allowOrigins === '*' || self::$config->allowOrigins === 'null') {
                        $allowed = '*';
                    } else {
                        foreach ([$origin, Func::mainDomain($origin)] as $value) {
                            if (in_array($value, (array) self::$config->allowOrigins)) {
                                $allowed = $value;
                                break;
                            }
                        }
                    }

                    if ($allowed === null) {
                        http_response_code(403);
                        exit('Origin Forbidden');
                    }

                    header('Access-Control-Allow-Origin: ' . $allowed);
                }
            }elseif(!$origin && self::$config->forbidEmptyOrigin){
                http_response_code(400);
                exit('Bad Origin Request');
            }

            if (!isset($headers['Access-Control-Allow-Headers']) && self::$config->allowHeaders !== []) {
                header('Access-Control-Allow-Headers: ' . implode(', ', self::$config->allowHeaders));
            }

            if (!isset($headers['Access-Control-Allow-Credentials'])) {
                header('Access-Control-Allow-Credentials: ' . (self::$config->allowCredentials ? 'true' : 'false'));
            }
        }

        $removeHeaders = [];
        foreach ($headers as $header => $value) {
            if (!$header || ($header === 'default_headers' || ($header === 'Content-Encoding' && $value === false))) {
                continue;
            }

            if($value === ''){
                $removeHeaders[] = $header;
            }else{          
                $value = ($header === 'Content-Type' && !str_contains($value, 'charset')) ? 
                    "{$value}; charset=" . env('app.charset', 'utf-8') : $value;

                header("$header: $value");
            }
        }

        if (!env('x.powered', true)) {
            $removeHeaders[] = 'X-Powered-By';
        }

        if($removeHeaders !== []){
            array_map('header_remove', $removeHeaders);
        }
    }

    /**
     * Get the content type based on file extension and charset.
     *
     * @param string $extension The file extension.
     * @param string $charset The character set.
     *
     * @return string Return the content type and optional charset.
     */
    public static function getContentType(string $extension = 'html', ?string $charset = null): string
    {
        $charset ??= env('app.charset', 'utf-8');

        return static::getContentTypes($extension, 0) . ($charset === '' ?: '; charset=' . $charset);
    }

    /**
     * Get content types by name 
     * 
     * @param string $type Type of content types to retrieve.
     * @param int|null $index The index of content type to return.
     * 
     * @return array<int,array>|string|null Return array, string of content types or null if not found.
    */
    public static function getContentTypes(string $type, int|null $index = 0): array|string|null
    {
        $type = ($type === 'txt') ? 'text' : $type;

        if($index === null){
            return self::$contentTypes[$type] ?? null;
        }

        return self::$contentTypes[$type][$index] ?? 'text/html';
    }
}