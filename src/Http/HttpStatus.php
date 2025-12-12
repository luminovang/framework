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

final class HttpStatus
{
    // Generic
    public const INVALID = 0;

    // 1xx Informational
    public const CONTINUE = 100;
    public const SWITCHING_PROTOCOLS = 101;
    public const PROCESSING = 102;
    public const EARLY_HINTS = 103;

    // 2xx Success
    public const OK = 200;
    public const CREATED = 201;
    public const ACCEPTED = 202;
    public const NON_AUTHORITATIVE_INFORMATION = 203;
    public const NO_CONTENT = 204;
    public const RESET_CONTENT = 205;
    public const PARTIAL_CONTENT = 206;
    public const MULTI_STATUS = 207;
    public const ALREADY_REPORTED = 208; 
    public const IM_USED = 226;

    // 3xx Redirection
    public const MULTIPLE_CHOICES = 300;
    public const MOVED_PERMANENTLY = 301;
    public const MOVED_TEMPORARILY = 302;
    public const FOUND = 302;
    public const SEE_OTHER = 303;
    public const NOT_MODIFIED = 304;
    public const USE_PROXY = 305;
    public const SWITCH_PROXY = 306;
    public const TEMPORARY_REDIRECT = 307;
    public const PERMANENTLY_REDIRECT = 308;

    // 4xx Client Errors
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const PAYMENT_REQUIRED = 402;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const NOT_ACCEPTABLE = 406;
    public const PROXY_AUTHENTICATION_REQUIRED = 407;
    public const REQUEST_TIMEOUT = 408;
    public const CONFLICT = 409;
    public const GONE = 410;
    public const LENGTH_REQUIRED = 411;
    public const PRECONDITION_FAILED = 412;
    public const REQUEST_ENTITY_TOO_LARGE = 413;
    public const REQUEST_URI_TOO_LONG = 414;
    public const UNSUPPORTED_MEDIA_TYPE = 415;
    public const REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    public const EXPECTATION_FAILED = 417;
    public const I_AM_A_TEAPOT = 418;
    public const AUTHENTICATION_TIMEOUT = 419;
    public const ENHANCE_YOUR_CALM = 420;
    public const MISDIRECTED_REQUEST = 421; 
    public const UNPROCESSABLE_ENTITY = 422;
    public const LOCKED = 423;
    public const FAILED_DEPENDENCY = 424;
    public const METHOD_FAILURE = 424; // legacy duplicate
    public const TOO_EARLY = 425;
    public const UPGRADE_REQUIRED = 426;
    public const PRECONDITION_REQUIRED = 428;
    public const TOO_MANY_REQUESTS = 429;
    public const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
    public const NO_RESPONSE = 444;
    public const RETRY_WITH = 449;
    public const BLOCKED_BY_WINDOWS_PARENTAL_CONTROLS = 450;
    public const UNAVAILABLE_FOR_LEGAL_REASONS = 451;
    public const REQUEST_HEADER_TOO_LARGE = 494;
    public const CERT_ERROR = 495;
    public const NO_CERT = 496;
    public const HTTP_TO_HTTPS = 497;
    public const CLIENT_CLOSED_REQUEST = 499;

    // 5xx Server Errors
    public const INTERNAL_SERVER_ERROR = 500;
    public const NOT_IMPLEMENTED = 501;
    public const BAD_GATEWAY = 502;
    public const SERVICE_UNAVAILABLE = 503;
    public const GATEWAY_TIMEOUT = 504;
    public const HTTP_VERSION_NOT_SUPPORTED = 505;
    public const VARIANT_ALSO_NEGOTIATES = 506;
    public const INSUFFICIENT_STORAGE = 507;
    public const LOOP_DETECTED = 508;
    public const BANDWIDTH_LIMIT_EXCEEDED = 509;
    public const NOT_EXTENDED = 510;
    public const NETWORK_AUTHENTICATION_REQUIRED = 511;
    public const NETWORK_READ_TIMEOUT_ERROR = 598;
    public const NETWORK_CONNECT_TIMEOUT_ERROR = 599;
    public const VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL = 506;

    /**
     * Http status codes and messages.
     * 
     * @var array<int,string> LABELS
     */
    public const LABELS = [
        self::INVALID => 'Invalid',

        // 1xx Informational
        self::CONTINUE => 'Continue',
        self::SWITCHING_PROTOCOLS => 'Switching Protocols',
        self::PROCESSING => 'Processing',
        self::EARLY_HINTS  => 'Early Hints',

        // 2xx Success
        self::OK => 'OK',
        self::CREATED => 'Created',
        self::ACCEPTED => 'Accepted',
        self::NON_AUTHORITATIVE_INFORMATION => 'Non-Authoritative Information',
        self::NO_CONTENT => 'No Content',
        self::RESET_CONTENT => 'Reset Content',
        self::PARTIAL_CONTENT => 'Partial Content',
        self::MULTI_STATUS => 'Multi-Status',
        self::ALREADY_REPORTED => 'Already Reported',
        self::IM_USED => 'IM Used',

        // 3xx Redirection
        self::MULTIPLE_CHOICES => 'Multiple Choices',
        self::MOVED_PERMANENTLY => 'Moved Permanently',
        self::MOVED_TEMPORARILY => 'Moved Temporarily',
        self::FOUND => 'Found',
        self::SEE_OTHER => 'See Other',
        self::NOT_MODIFIED => 'Not Modified',
        self::USE_PROXY => 'Use Proxy',
        self::SWITCH_PROXY => 'Switch Proxy',
        self::TEMPORARY_REDIRECT => 'Temporary Redirect',
        self::PERMANENTLY_REDIRECT => 'Permanent Redirect',

        // 4xx Client Errors
        self::BAD_REQUEST => 'Bad Request',
        self::UNAUTHORIZED => 'Unauthorized',
        self::PAYMENT_REQUIRED => 'Payment Required',
        self::FORBIDDEN => 'Forbidden',
        self::NOT_FOUND => 'Not Found',
        self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
        self::NOT_ACCEPTABLE => 'Not Acceptable',
        self::PROXY_AUTHENTICATION_REQUIRED => 'Proxy Authentication Required',
        self::REQUEST_TIMEOUT => 'Request Timeout',
        self::CONFLICT => 'Conflict',
        self::GONE => 'Gone',
        self::LENGTH_REQUIRED => 'Length Required',
        self::PRECONDITION_FAILED => 'Precondition Failed',
        self::REQUEST_ENTITY_TOO_LARGE => 'Request Entity Too Large',
        self::REQUEST_URI_TOO_LONG => 'Request-URI Too Long',
        self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
        self::REQUESTED_RANGE_NOT_SATISFIABLE => 'Requested Range Not Satisfiable',
        self::EXPECTATION_FAILED => 'Expectation Failed',
        self::I_AM_A_TEAPOT => "I'm a Teapot",
        self::AUTHENTICATION_TIMEOUT => 'Authentication Timeout',
        self::ENHANCE_YOUR_CALM => 'Enhance Your Calm',
        self::UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        self::MISDIRECTED_REQUEST => 'Misdirected Request',
        self::LOCKED => 'Locked',
        self::FAILED_DEPENDENCY => 'Failed Dependency',
        self::METHOD_FAILURE => 'Method Failure',
        self::TOO_EARLY => 'Too Early',
        self::UPGRADE_REQUIRED => 'Upgrade Required',
        self::PRECONDITION_REQUIRED => 'Precondition Required',
        self::TOO_MANY_REQUESTS => 'Too Many Requests',
        self::REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
        self::NO_RESPONSE => 'No Response',
        self::RETRY_WITH => 'Retry With',
        self::BLOCKED_BY_WINDOWS_PARENTAL_CONTROLS => 'Blocked by Windows Parental Controls',
        self::UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
        self::REQUEST_HEADER_TOO_LARGE => 'Request Header Too Large',
        self::CERT_ERROR => 'Cert Error',
        self::NO_CERT => 'No Cert',
        self::HTTP_TO_HTTPS => 'HTTP to HTTPS',
        self::CLIENT_CLOSED_REQUEST => 'Client Closed Request',

        // 5xx Server Errors
        self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
        self::NOT_IMPLEMENTED => 'Not Implemented',
        self::BAD_GATEWAY => 'Bad Gateway',
        self::SERVICE_UNAVAILABLE => 'Service Unavailable',
        self::GATEWAY_TIMEOUT => 'Gateway Timeout',
        self::HTTP_VERSION_NOT_SUPPORTED => 'HTTP Version Not Supported',
        self::VARIANT_ALSO_NEGOTIATES => 'Variant Also Negotiates',
        self::INSUFFICIENT_STORAGE => 'Insufficient Storage',
        self::LOOP_DETECTED => 'Loop Detected',
        self::BANDWIDTH_LIMIT_EXCEEDED => 'Bandwidth Limit Exceeded',
        self::NOT_EXTENDED => 'Not Extended',
        self::NETWORK_AUTHENTICATION_REQUIRED => 'Network Authentication Required',
        self::NETWORK_READ_TIMEOUT_ERROR => 'Network Read Timeout Error',
        self::NETWORK_CONNECT_TIMEOUT_ERROR => 'Network Connect Timeout Error',
        self::VARIANT_ALSO_NEGOTIATES_EXPERIMENTAL => 'Variant Also Negotiates (Experimental)'
    ];

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

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
        return self::LABELS;
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
        return self::LABELS[$code] ?? $fallback ?? '';
    }

    /**
     * Return a status code message using a fancy method call.
     * Your method call must follow this pattern: `status followed by the http status code`.
     * 
     * @param string $name The status code method name (e.g, HttpStatus::status404()).
     * @param array $arguments Unused array of arguments.
     * 
     * @return string|null Return the http status code message, otherwise null.
     * 
     * @example - Returning status code message.
     * 
     * ```php
     * echo HttpStatus::status200();
     * ```
     */
    public static function __callStatic(string $name, array $arguments): ?string
    {
        if (preg_match('/^status(\d+)$/', $name, $matches)) {
            $code = (int) $matches[1];

            return self::LABELS[$code] ?? null;
        }

        return null;
    }
}