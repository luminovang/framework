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
use \Luminova\Functions\Normalizer;
use \App\Controllers\Config\Apis;

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
     * Get output headers
     * 
     * @return array<string, mixed> $info
    */
    public static function requestHeaders(): array
    {
        $headers = headers_list();
        $info = [];

        foreach ($headers as $header) {
            [$name, $value] = explode(':', $header, 2);

            $name = trim($name);
            $value = trim($value);

            switch ($name) {
                case 'Content-Type':
                    $info['Content-Type'] = $value;
                    break;
                case 'Content-Length':
                    $info['Content-Length'] = (int) $value;
                    break;
                case 'Content-Encoding':
                    $info['Content-Encoding'] = $value;
                    break;
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
    public static function headerNoCache(int $status = 200, string|false|null $contentType = null): void 
    {
        static::parseHeaders([
            'X-Powered-By' => Foundation::copyright(),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Expires' => '0',
            'Content-Type' => $contentType ?? 'text/html'
        ], $status);
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
            $origin = static::server('HTTP_ORIGIN');
            if($origin){
                if (!isset($headers['Access-Control-Allow-Origin']) && !empty(Apis::$allowOrigins)) {
                    $allowed = null;
                    if (Apis::$allowOrigins === '*' || Apis::$allowOrigins === 'null') {
                        $allowed = '*';
                    } else {
                        foreach ([$origin, Normalizer::mainDomain($origin)] as $value) {
                            if (in_array($value, (array) Apis::$allowOrigins)) {
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
            }elseif(!$origin && Apis::$forbidEmptyOrigin){
                http_response_code(400);
                exit('Bad Origin Request');
            }

            if (!isset($headers['Access-Control-Allow-Headers']) && Apis::$allowHeaders !== []) {
                header('Access-Control-Allow-Headers: ' . implode(', ', Apis::$allowHeaders));
            }

            if (!isset($headers['Access-Control-Allow-Credentials'])) {
                header('Access-Control-Allow-Credentials: ' . (Apis::$allowCredentials ? 'true' : 'false'));
            }
        }

        $charset = env('app.charset', 'utf-8');
        
        foreach ($headers as $header => $value) {
            if ($header === 'default_headers' || ($header === 'Content-Encoding' && $value === false)) {
                continue;
            }

            if ($header === 'Content-Type' && strpos($value, 'charset') === false) {
                header("$header: {$value}; charset=$charset");
            } else {
                header("$header: $value");
            }
        }

        if (!env('x.powered', true)) {
            header_remove('X-Powered-By');
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
            'form'   => ['application/x-www-form-urlencoded', 'multipart/form-data']
        ];

        if($index === null){
            return $types[$type] ?? null;
        }

        return $types[$type][$index] ?? 'text/html';
    }
}