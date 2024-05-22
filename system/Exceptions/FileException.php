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

class FileException extends AppException
{
    /**
     * Handle file excption.
     * 
     * @param string $file Filename. 
     * @param string $message Exception message.
     * 
     * @throws static Throws exception.
    */
    public static function handleFile(string $file, string $message = ''): void 
    {
        static::throwException('Unable to write file: "' . $file . '", ' . $message);
    }

    /**
     * Handle directory excption.
     * 
     * @param string $path File path.
     * @param string $message Exception message.
     * 
     * @throws static Throws exception.
    */
    public static function handleDirectory(string $path, string $message = ''): void 
    {
        static::throwException('Unable to create a directory: "' . $path . '", ' . $message);
    }

    /**
     * Handle file permission excption.
     * 
     * @param string $path File path.
     * @param string $message Exception message.
     * 
     * @throws static Throws exception
    */
    public static function handlePermission(string $path, string $message = ''): void 
    {
        static::throwException('Unable to set permission for file: "' . $path . '", ' . $message);
    }
}