<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Library;

use \RuntimeException;

class Importer
{
    /**
     * Import a custom library into your project 
     * You must place your external libraries in libraries/libs/ directory
     * 
     * @param string $library the name of the library
     * @example Foo/Bar/Baz
     * @example Foo/Bar/Baz.php
     * @example Foo.php
     * @example Foo
     * 
     * @return bool true if the library was successfully imported
     * @throws RuntimeException if library could not be found
    */
    public static function import(string $library): bool
    {
        $file = rtrim($library, '/');

        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }

        $filePath = path('library') . $file;

        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }

        throw new RuntimeException("Library '$library' does not exist.");
    }
}