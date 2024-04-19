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
     * All allowed HTTP request methods.
     * Must leave in upper case.
     * 
     * @var array<int,string> $httpMethods
    */
    public static array $httpMethods = ['GET', 'POST', 'PATCH', 'DELETE', 'PUT', 'OPTIONS', 'HEAD'];


    /**
     * Get all request headers.
     *
     * @return array The request headers
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

        // Method getallheaders() not available or went wrong: manually extract headers
        foreach ($_SERVER as $name => $value) {
            if ((substr($name, 0, 5) == 'HTTP_') || ($name == 'CONTENT_TYPE') || ($name == 'CONTENT_LENGTH')) {
                $headers[str_replace([' ', 'Http'], ['-', 'HTTP'], ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get server variables.
     *
     * @param string|null $name Optional name of the server variable
     *
     * @return mixed|array|string|null $_SERVER
    */
    public function getServer(?string $name = null): mixed
    {
        if ($name === null || $name === '') {
            return $_SERVER;
        }

        return $_SERVER[$name] ?? null;
    }

    /**
     * Get the request method.
     *
     * @return string The request method
    */
    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD']??'';
    }

    /**
     * Get system headers.
     *
     * @return array The system headers
     * @ignore
     */
    public static function getSystemHeaders(): array
    {
        return [
            'Content-Type' => 'text/html; charset=UTF-8',
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
     * Set no caching headers
     * 
     * @param int $status HTTP status code.
     * 
     * @return void 
     * @internal Used in router and template
    */
    public static function headerNoCache(int $status = 200): void 
    {
        http_response_code($status);
        header('X-Powered-By: ' . Foundation::copyright());
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Expires: 0");
    }

    /**
     * Get the content type based on file extension and charset.
     *
     * @param string $extension The file extension
     * @param string $charset The character set
     *
     * @return string The content type
    */
    public static function getContentType(string $extension = 'html', string $charset = ''): string
    {
        $types = [
            'json' => 'application/json',
            'text' => 'text/plain',
            'xml' => 'application/xml',
            'html' => 'text/html',
        ];

        $contentType = $types[$extension] ?? 'text/html';

        return $contentType . ($charset !== '' ? '; charset=' . $charset : '');
    }

    /**
     * Get the request method for routing, considering overrides.
     *
     * @return string The request method for routing
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
     * Get request header authorization header
     *
     * @return string 
    */
    public static function getAuthorization(): string
    {
		$headers = '';
		if(isset($_SERVER['Authorization'])) {
			$headers = trim($_SERVER["Authorization"]);
		}elseif(isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
			$headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
		}elseif(function_exists('apache_request_headers')) {
			$rHeaders = apache_request_headers();
			// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
			$rHeaders = array_combine(
                array_map('ucwords', array_keys($rHeaders)),
                array_values($rHeaders)
			);

			if (isset($rHeaders['Authorization'])) {
				$headers = trim($rHeaders['Authorization']);
			}
		}
        
		return $headers;
	}
}