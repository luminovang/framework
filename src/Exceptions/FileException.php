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

use Luminova\Exceptions\AppException;
use \Throwable;

class FileException extends AppException
{
    /**
     * Handle file exception.
     * 
     * @param string $file Filename. 
     * @param string $message Exception message.
     * @param Throwable|null $previous Exception thrown.
     * 
     * @throws static Throws exception.
    */
    public static function handleFile(string $file, string $message = '', ?Throwable $previous = null): void 
    {
        static::throwException('Unable to write file: "' . $file . '", ' . $message, 0, $previous);
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
    public static function handleReadFile(string $file, string $message = '', ?Throwable $previous = null): void 
    {
        static::throwException('Unable to open file: "' . $file . '", ' . $message, 0, $previous);
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
    public static function handleDirectory(string $path, string $message = '', ?Throwable $previous = null): void 
    {
        static::throwException('Unable to create a directory: "' . $path . '", ' . $message, 0, $previous);
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
    public static function handlePermission(string $path, string $message = '', ?Throwable $previous = null): void 
    {
        static::throwException('Unable to set permission for file: "' . $path . '", ' . $message, 0, $previous);
    }
}