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

class FileException extends AppException
{
    /**
     * Constructor for FileException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: 6204).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = self::FILESYSTEM_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Handle file exception.
     * 
     * @param string $file Filename. 
     * @param string $message Exception message.
     * @param Throwable|null $previous Exception thrown.
     * 
     * @throws static Throws exception.
    */
    public static function handleFile(
        string $file, 
        string $message = '', 
        ?Throwable $previous = null
    ): void 
    {
        static::throwException(
            'Unable to write file: "' . $file . '", ' . $message, 
            self::WRITE_PERMISSION_DENIED, 
            $previous
        );
    }

    /**
     * Handle file exception.
     * 
     * @param string $file Filename. 
     * @param string $message Exception message.
     * @param Throwable|null $previous Exception thrown.
     * 
     * @throws static Throws exception.
    */
    public static function handleReadFile(
        string $file, 
        string $message = '', 
        ?Throwable $previous = null
    ): void 
    {
        static::throwException(
            'Unable to open file: "' . $file . '", ' . $message, 
            self::READ_PERMISSION_DENIED, 
            $previous
        );
    }

    /**
     * Handle directory exception.
     * 
     * @param string $path File path.
     * @param string $message Exception message.
     * @param Throwable|null $previous Exception thrown.
     * 
     * @throws static Throws exception.
    */
    public static function handleDirectory(
        string $path, 
        string $message = '', 
        ?Throwable $previous = null
    ): void 
    {
        static::throwException(
            'Unable to create a directory: "' . $path . '", ' . $message, 
            self::CREATE_DIR_FAILED, 
            $previous
        );
    }

    /**
     * Handle file permission exception.
     * 
     * @param string $path File path.
     * @param string $message Exception message.
     * @param Throwable|null $previous Exception thrown.
     * 
     * @throws static Throws exception
    */
    public static function handlePermission(
        string $path, 
        string $message = '', 
        ?Throwable $previous = null
    ): void 
    {
        static::throwException(
            'Unable to set permission for file: "' . $path . '", ' . $message, 
            self::SET_PERMISSION_FAILED, 
            $previous
        );
    }
}