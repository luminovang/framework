<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

use \Luminova\Exceptions\AppException;

class MailerException extends AppException
{
    /**
     * @var array $types
    */
    private static array $types = [
        'invalid_client' => 'Invalid mail client "%s", available clients: [PHPMailer, NovaMailer, SwiftMailer].',
        'file_access' => 'File access denied for "%s"',
        'class_not_exist' => 'Class "%s" does not exist, install package first before using.',
        'no_client' => 'No mail client was specified.'
    ];

    /**
     * Thrown when a cookie-related error occurs.
     *
     * @param string $type The type of error.
     * @param mixed|null $name The cookie name associated with the error (if applicable).
     * @param string|int $code Exception code.
     * 
     * @return static Return new static exception class.
     */
    public static function throwWith(string $type, mixed $name = null, string|int $code = 0): static
    {
        $message = self::$types[$type] ?? 'Unknown error occurred while creating email';
        return new static($name === null ? $message : sprintf($message, $name), $code);
    }
}