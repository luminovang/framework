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
use Luminova\Exceptions\ErrorCode;
use Luminova\Exceptions\LuminovaException;

class MailerException extends LuminovaException
{
    /**
     * Constructor for MailerException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: `ErrorCode::MAILER_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::MAILER_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Throws a mailer-related exception using a type-specific message template.
     *
     * The method resolves an error message based on the given `$type`, optionally
     * formats it using `$name` when a placeholder is present, and then throws
     * a new instance of the current exception class.
     *
     * Supported error types:
     * - `invalid_client`   - Unsupported or invalid mail client.
     * - `file_access`      - File access is denied for the given path.
     * - `class_not_exist`  - Required mail client class is missing.
     * - `no_client`        - No mail client was specified.
     *
     * @param string $type The error type used to resolve the message template.
     * @param mixed|null $name Optional value used to fill message placeholders.
     * @param string|int $code The exception code (default: ErrorCode::MAILER_ERROR).
     *
     * @return never This method always throws an exception.
     * @throws MailerException Always throws an instance of the current exception class.
     */
    public static function rethrow(
        string $type, 
        mixed $name = null, 
        string|int $code = ErrorCode::MAILER_ERROR
    ): void
    {
        throw new self(($name === null) 
            ? self::getDefaultMessage($type) 
            : sprintf(self::getDefaultMessage($type), $name), 
            $code
        );
    }

    /**
     * Returns the default exception message for a mailer validation error.
     *
     * @param string $type The validation error type.
     *
     * @return string The corresponding exception message template.
     */
    private static function getDefaultMessage(string $type): string
    {
        return match ($type) {
            'invalid_client'  => 'Invalid mail client "%s", available clients: [PHPMailer, NovaMailer, SwiftMailer].',
            'file_access'     => 'File access denied for "%s"',
            'class_not_exist' => 'Class "%s" does not exist, install package first before using.',
            'no_client'       => 'No mail client was specified.',
            default           => 'Unknown error occurred while creating email.',
        };
    }
}