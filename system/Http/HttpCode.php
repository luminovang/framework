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

final class HttpCode
{
    /**
     * Http status code.
     * 
     * @var array<int, string> $codes
    */
    public static array $codes = [
        0 => 'Invalid',
        
        // 1xx Informational Responses
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing', // WebDAV; RFC 2518
    
        // 2xx Success
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        206 => 'Partial Content',
    
        // 3xx Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
    
        // 4xx Client Errors
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        429 => 'Too Many Requests',
    
        // 5xx Server Errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        507 => 'Insufficient Storage' // WebDAV; RFC 2518
    ];

    /**
     * Prevent instantiation 
    */
    private function __construct() {}

    /**
     * Return a status code title.
     * 
     * @param int|null $code Status code.
     * 
     * @return string|null|array Status code title or array of status codes.
    */
    public static function get(int|null $code = null): string|null|array
    {
        if($code === null){
            return static::$codes;
        }

        return static::$codes[$code] ?? null;
    }

    /**
     * Return a status code title using a fancy method call.
     * 
     * @param string $name Status method name.
     * @param array $arguments
     * 
     * @example HttpCode::status200;
     * 
     * @return string|null Status code title.
    */
    public static function __callStatic(string $name, array $arguments): string|null
    {
        if (preg_match('/^status(\d+)$/', $name, $matches)) {
            $statusCode = (int) $matches[1];

            if (isset(static::$codes[$statusCode])) {
                return static::$codes[$statusCode];
            }
        }

        return null;
    }
}