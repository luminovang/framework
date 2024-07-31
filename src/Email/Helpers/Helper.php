<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Email\Helpers;

class Helper
{
    /**
     * Check whether a file path is safe, accessible, and readable.
     *
     * @param string $path A relative or absolute path to a file
     *
     * @return bool
     */
    public static function fileIsAccessible($path): bool
    {
        if (!self::isPermittedPath($path)) {
            return false;
        }
        
        $readable = is_file($path);
        if (!str_starts_with($path, '\\\\')) {
            $readable = $readable && is_readable($path);
        }
        return  $readable;
    }

    /**
     * Check whether a file path is of a permitted type.
     * Used to reject URLs and phar files from functions that access local file paths,
     * such as addAttachment.
     *
     * @param string $path A relative or absolute path to a file
     *
     * @return bool
     */
    public static function isPermittedPath($path): bool
    {
        return !preg_match('#^[a-z][a-z\d+.-]*://#i', $path);
    }
}