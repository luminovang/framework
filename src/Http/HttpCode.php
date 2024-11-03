<?php 
/**
 * Luminova Framework http status codes.
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
     * Http status codes and messages.
     * 
     * @var array<int,string> $codes
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
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status', 
    
        // 3xx Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)', 
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
    
        // 4xx Client Errors
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Typ',
        416 => 'Requested Range Not Satisfiable', 
        417 => 'Expectation Failed', 
        418 => 'I\'m a Teapot', // RFC 2324 April Fools' joke
        419 => 'Authentication Timeout', 
        420 => 'Enhance Your Calm', 
        422 => 'Unprocessable Entity', 
        423 => 'Locked', 
        424 => 'Failed Dependency', 
        424 => 'Method Failure', 
        425 => 'Unordered Collection', 
        426 => 'Upgrade Required', 
        428 => 'Precondition Required', 
        429 => 'Too Many Requests', 
        431 => 'Request Header Fields Too Large', 
        444 => 'No Response', 
        449 => 'Retry With', 
        450 => 'Blocked by Windows Parental Controls', 
        451 => 'Unavailable For Legal Reasons', 
        494 => 'Request Header Too Large', 
        495 => 'Cert Error', 
        496 => 'No Cert', 
        497 => 'HTTP to HTTPS', 
        499 => 'Client Closed Request',
    
        // 5xx Server Errors
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates', 
        507 => 'Insufficient Storage', // WebDAV; RFC 2518
        508 => 'Loop Detected', 
        509 => 'Bandwidth Limit Exceeded', 
        510 => 'Not Extended', 
        511 => 'Network Authentication Required', 
        598 => 'Network Read Timeout Error', 
        599 => 'Network Connect Timeout Error'
    ];

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Return a status code message using a fancy method call.
     * 
     * @param string $name The status code method name (e.g, $status->status404).
     * 
     * @return string|null Return the http status code message, otherwise null.
     * 
     * @example Returning status code message.
     * 
     * ```php
     * echo $status->status200;
     * ```
     */
    public function __get(string $name): ?string
    {
        return self::{$name}();
    }

    /**
     * Return an http status code message or the entire status codes.
     * 
     * @param int|null $code The http status code (e.g, 200, 404 etc) (default: null).
     * 
     * @return array<int,string>string|null Return the status code message, null if code not found or array of status codes if null is passed.
     */
    public static function get(?int $code = null): array|string|null
    {
        return ($code === null) 
            ? self::$codes
            : (self::$codes[$code] ?? null);
    }

    /**
     * Return a status code message using a fancy method call.
     * Your method call must follow this pattern: `status followed by the http status code`.
     * 
     * @param string $name The status code method name (e.g, HttpCode::status404()).
     * @param array $arguments Unused array of arguments.
     * 
     * @return string|null Return the http status code message, otherwise null.
     * 
     * @example Returning status code message.
     * 
     * ```php
     * echo HttpCode::status200();
     * ```
     */
    public static function __callStatic(string $name, array $arguments): ?string
    {
        if (preg_match('/^status(\d+)$/', $name, $matches)) {
            $code = (int) $matches[1];

            return self::$codes[$code] ?? null;
        }

        return null;
    }
}