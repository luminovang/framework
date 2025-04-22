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

use \Luminova\Exceptions\AppException;
use \Throwable;

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
     * Constructor for MailerException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: 4499).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = self::MAILER_ERROR, 
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
     * @param string|int $code The exception code (default: 4499).
     * 
     * @return static Return new static exception class.
     */
    public static function throwWith(
        string $type, 
        mixed $name = null, 
        string|int $code = self::MAILER_ERROR
    ): static
    {
        $message = self::$types[$type] ?? 'Unknown error occurred while creating email';
        return new self($name === null ? $message : sprintf($message, $name), $code);
    }
}