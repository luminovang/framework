<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Config;

use Luminova\Config\Configuration;
use Luminova\Exceptions\FileException;
use SplFileObject;

class DotEnv
{
    /**
     * Register environment variables from a .env file.
     *
     * @param string $path The path to the .env file.
     * 
     * @return void 
     * @throws FileException If the .env file is not found.
     */
    public static function register(string $path): void
    {
        if (!file_exists($path)) {
            throw new FileException("DotEnv file not found: $path");
        }

        $file = new SplFileObject($path, 'r');
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) >= 2) {
                [$name, $value] = $parts;
                $name = trim($name);
                $value = trim($value);
                Configuration::set($name, $value);
            }
        }
    }
}