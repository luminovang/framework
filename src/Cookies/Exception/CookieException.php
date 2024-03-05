<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cookies\Exception;

use Luminova\Exceptions\AppException;

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
        'invalid_value' => 'Invalid cookie value: "%s".',
        'invalid_time' => 'Invalid time format: %s',
        'invalid_time_value' => 'Invalid time value',
    ];

    /**
     * Thrown when a cookie-related error occurs.
     *
     * @param string $type The type of error.
     * @param mixed|null $name The cookie name associated with the error (if applicable).
     * @return static
     */
    public static function throwWith(string $type, mixed $name = null): self
    {
        $message = self::$types[$type] ?? 'Unknown error occurred while creating cookie';
        if ($name === null) {
            $finalMessage = $message;
        }else{
            $finalMessage = sprintf($message, $name);
        }

        return new static($finalMessage);
    }
}