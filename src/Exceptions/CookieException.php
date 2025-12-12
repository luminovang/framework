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
use Luminova\Debugger\Tracer;
use Luminova\Exceptions\ErrorCode;
use Luminova\Exceptions\LuminovaException;

class CookieException extends LuminovaException
{
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
     * Throws a cookie-related exception using a type-specific message template
     * 
     * The method resolves an error message based on the given `$type`, optionally
     * formats it using `$name` when a placeholder is present, and then throws
     * a new instance of the current exception class.
     *
     * Supported error types:
     * - `invalid_name` - The cookie name contains reserved or invalid characters.
     * - `empty_name` - The cookie name is empty.
     * - `invalid_secure_prefix` - The `__Secure-` prefix is used without the `Secure` attribute.
     * - `invalid_host_prefix` - The `__Host-` prefix is used without the required attributes.
     * - `invalid_same_site` - The `SameSite` attribute has an invalid value.
     * - `invalid_same_site_none` - The `SameSite=None` attribute is used without the `Secure` attribute.
     * - `invalid_value` - The cookie value is invalid.
     *
     * @param string $type The type of error.
     * @param mixed|null $name The cookie name associated with the error (if applicable).
     * @param string|int $code The exception code (default: `ErrorCode::COOKIE_ERROR`).
     *
     * @return never This method always throws an exception.
     * @throws CookieException Always throws an instance of the current exception class.
     */
    public static function rethrow(
        string $type, 
        mixed $name = null, 
        string|int $code = ErrorCode::COOKIE_ERROR
    ): static
    {
        [$file, $line] = Tracer::trace(2);

        $e = new self(($name === null) 
            ? self::getDefaultMessage($type)
            : sprintf(self::getDefaultMessage($type), $name), 
            $code
        );

        if($file){
            $e->setLine($line)->setFile($file);
        }

        throw $e;
    }

    /**
     * Returns the default exception message for a cookie validation error.
     *
     * @param string $type The validation error type.
     *
     * @return string The corresponding exception message template.
     */
    private static function getDefaultMessage(string $type): string
    {
        return match ($type) {
            'invalid_name'
                => 'Invalid cookie name: "%s". Cookie names cannot contain reserved characters.',
            'empty_name'
                => 'Cookie name cannot be empty.',
            'invalid_secure_prefix'
                => 'Invalid cookie name: "%s". The "__Secure-" prefix requires the "Secure" attribute.',
            'invalid_host_prefix'
                => 'Invalid cookie name: "%s". The "__Host-" prefix requires the "Secure" attribute, an empty domain, and the path "/".',
            'invalid_same_site'
                => 'Invalid SameSite attribute: "%s". Allowed values are "None", "Lax", or "Strict".',
            'invalid_same_site_none'
                => 'Invalid SameSite attribute: "%s". "None" requires the "Secure" attribute.',
            'invalid_value'
                => 'Invalid cookie value: "%s".',
            default
                => 'An unknown error occurred while creating the cookie.',
        };
    }
}