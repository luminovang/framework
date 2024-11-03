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

use \Luminova\Exceptions\FileException;
use \SplFileObject;

final class Environment
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
            throw new FileException("Environment file not found on: $path, make sure you add .env file to your project root");
        }

        $file = new SplFileObject($path, 'r');
        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) >= 2) {
                [$name, $value] = $parts;
                $name = trim($name);
                $value = trim($value);
                setenv($name, $value);
            }
        }
    }

    /**
     * Magic method to retrieve session properties.
     *
     * @param string $key The name of the property to retrieve.
     * 
     * @return mixed
     */
    public static function get(string $key): mixed 
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            $value = self::getNotationConvention($key);
        }

        return $value;
    }

    /**
     * Convert variable to dot or underscore notation.
     *
     * @param string $input The input string .
     * @param string $notation The conversion notion
     * @return string
    */
    public static function variableToNotation(string $input, string $notation = "."): string 
    {
        if ($notation === ".") {
            $output = str_replace('_', '.', $input);
        } elseif ($notation === "_") {
            $output = str_replace('.', '_', $input);
        } else {
            return $input; 
        }

        $pattern = '/([a-z0-9])([A-Z])/';
    
        if ($notation === ".") {
            $output = preg_replace($pattern, '$1.$2', $output);
        } elseif ($notation === "_") {
            $output = preg_replace($pattern, '$1_$2', $output);
        }
    
        // Remove leading dot or underscore (if any)
        $output = ltrim($output, $notation);
    
        return $notation === "_" ? strtoupper($output) : strtolower($output);
    }

    /**
     * Search variable by dot or underscore notation.
     *
     * @param string $key The input string .
     * 
     * @return mixed
    */
    private static function getNotationConvention(string $key): mixed 
    {
        $keys = [str_replace('_', '.', $key), str_replace('.', '_', $key)];

        foreach ($keys as $convertedKey) {
            $value = $_ENV[$convertedKey] ?? $_SERVER[$convertedKey] ?? getenv($convertedKey);
            if ($value !== false) {
                return $value;
            }
        }

        return false;
    }
}