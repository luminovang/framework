<?php 
/**
 * Luminova Framework http status codes.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
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
    public function __construct() {}

    /**
     * Determine if a HTTP status code is valid.
     * 
     * @param int $code The HTTP status code to check.
     * 
     * @return bool Return true if valid, otherwise false.
     */
    public static function isValid(int $code): bool 
    {
        return $code >= 100 && $code <= 599;
    }

    /**
     * Check if status code is in the error range.
     *
     * @param int $code HTTP status code.
     * 
     * @return bool Return true if client (4xx) or server (5xx) error.
     */
    public static function isError(int $code): bool
    {
        return $code >= 400 && $code < 600;
    }

    /**
     * Return all http status codes and it message phrase.
     * 
     * @return array<int,string> Return the status codes and message phrase.
     */
    public static function get(): array
    {
        return self::$codes;
    }

    /**
     * Get HTTP status code message phrase.
     * 
     * If fallback is null an empty string will be return if code is not found.
     * 
     * @param int $code The HTTP status code (e.g., 200, 404, etc.).
     * @param string|null $fallback Optional fallback string if code not found (default: 'Invalid').
     * 
     * @return string Return the status code message phrase or fallback if code not found.
     */
    public static function phrase(int $code, ?string $fallback = 'Invalid'): string
    {
        return self::$codes[$code] ?? $fallback ?? '';
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
     * @example - Returning status code message.
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