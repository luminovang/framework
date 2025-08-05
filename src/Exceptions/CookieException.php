<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Exceptions;

use \Throwable;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Exceptions\AppException;

class CookieException extends AppException
{
    /**
     * @var array $types
    */
    private static array $types = [
        'invalid_name' => 'Invalid cookie name: "%s". The name contains reserved characters which are not allowed.',
        'empty_name' => 'Cookie name cannot be empty.',
        'invalid_secure_prefix' => 'Invalid secure prefix: "%s". The "__Secure-" prefix can only be used when the cookie is set with the "Secure" attribute.',
        'invalid_host_prefix' => 'Invalid host prefix: "%s". The "__Host-" prefix can only be used when the cookie is set with the "Secure" attribute, and only when the domain is empty and the path is set to "/".',
        'invalid_same_site' => 'Invalid SameSite attribute: "%s". The SameSite attribute must be one of "None", "Lax", or "Strict".',
        'invalid_same_site_none' => 'Invalid SameSite attribute: "%s". The SameSite attribute cannot be set to "None" unless the cookie is also set with the "Secure" attribute.',
        'invalid_value' => 'Invalid cookie value: "%s".'
    ];

    /**
     * Constructor for CacheException.
     *
     * @param string  $message The exception message.
     * @param string|int $code The exception code (default: `ErrorCode::COOKIE_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::COOKIE_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Thrown when a cookie-related error occurs.
     *
     * @param string $type The type of error.
     * @param mixed|null $name The cookie name associated with the error (if applicable).
     * @param string|int $code The exception code (default: `ErrorCode::COOKIE_ERROR`).
     * 
     * @return static
     */
    public static function rethrow(
        string $type, 
        mixed $name = null, 
        string|int $code = ErrorCode::COOKIE_ERROR
    ): static
    {
        $message = self::$types[$type] ?? 'Unknown error occurred while creating cookie';

        [$file, $line] = parent::trace(2);

        $e = new self(($name === null) 
            ? $message 
            : sprintf($message, $name), 
            $code
        );

        if($file){
            $e->setLine($line)->setFile($file);
        }

        throw $e;
        
    }
}