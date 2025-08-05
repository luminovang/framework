<?php
/**
 * Luminova Framework String Listification class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility\String;

use \Luminova\Interface\LazyObjectInterface;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\RuntimeException;

class Listifier implements LazyObjectInterface
{
    /**
     * Determines if a string is a valid list based on the expected list format.
     *
     * @param string $list The string to check.
     * 
     * @return bool Return true if the string is a valid list; otherwise, false.
     */
    public static function isList(string $list): bool
    {
        if(
            $list === '' || 
            !str_contains($list, ',') || 
            !str_contains($list, ';')
        ){
            return false;
        }

        try{
            if (preg_match('/^[\w\s"\'=,;\[\]]+$/', $list)) {
                self::toArray($list);
                return true;
            }
        }catch(RuntimeException){
            return false;
        }

        return false;
    }

    /**
     * Converts a string list to its original array structure.
     *
     * @param string $list The string list to convert.
     * 
     * @return array Return the extracted array.
     * @throws RuntimeException Throws if invalid list format is detected.
     */
    public static function toArray(string $list): array
    {
        $array = [];

        foreach(self::split($list) as $value) {
            if($value !== '' && $value[0] !== '[' && str_contains($value, '=')){
                [$key, $val] = explode('=', $value, 2);
                $array[$key] = self::parseValue($val); 
            }else{
                $array[] = self::parseValue($value); 
            }
        }

        gc_mem_caches();
        return $array;
    }

    /**
     * Builds a string representation of an array.
     *
     * @param array $input The input array to convert.
     * @param string $delimiter The delimiter to use between (default: ',').
     *      - For none nested array use `,`.
     *      - For nested array use `;`.
     * 
     * @return string Return the resulting string representation of the array.
     * @throws InvalidArgumentException Throws if invalid delimiter was passed.
     * 
     * > Recommended to leave the default delimiter to automatically decide which one to use.
     */
    public static function toList(array $input, string $delimiter = ','): string
    {
        if($delimiter !== ',' && $delimiter !== ';'){
            throw new InvalidArgumentException('Invalid delimiter specified, supported delimiters are "," for none nested array, or ";" for nested arrays.');
        }

        $list = '';

        foreach($input as $key => $value){
            $line = match(true) {
                is_array($value) => '[' . self::toList($value, ';') . ']',
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => 'null',
                is_string($value) => addslashes($value),
                default => (string) $value
            };

            $list .= (is_string($key) ? "{$key}={$line}" : $line) . $delimiter;
        }
       
        return rtrim($list, $delimiter);
    }

    /**
     * Determines the type of value and returns the appropriate type.
     *
     * @param string $value The value to determine the type for.
     * @return mixed The value with the appropriate type.
     */
    private static function parseValue(string $value): mixed 
    {
        $value = trim($value);

        if($value === '[]'){
            return [];
        }
        
        if($value === ''){
            return '';
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return to_numeric($value, true);
        }
        
        if($value[0] === '['){
            if (
                $value[-1] !== ']' || 
                str_contains($value, ',') ||
                substr_count($value, '[') !== substr_count($value, ']')
            ) {
                throw new RuntimeException(sprintf('Invalid nested array definition for: "%s"', $value));
            }
           
            return self::toArray($value);
        }
        
        if (substr_count($value, '=') > 1) {
            throw new RuntimeException(sprintf(
                'Invalid list definition, cannot contain multiple equals for: "%s"', 
                $value
            ));
        }

        return trim($value, '"');
    }


    /**
     * Trims the outer brackets from a string if present.
     *
     * @param string $string The string to trim.
     * 
     * @return string Return the trimmed string.
     */
    private static function trimArray(string $string): string
    {
        $string = trim($string);
        $length = strlen($string);

        if ($length >= 2 && $string[0] === '[' && $string[$length - 1] === ']') {
            return substr($string, 1, -1);
        }

        return $string;
    }

    /**
     * Splits a string into an array, handling nested structures and delimiters.
     *
     * @param string $string The input string to split.
     * 
     * @return array Return splitted string as an array.
     */
    private static function split(string $string): array 
    {
        if($string === ''){
            return [];
        }

        $string = trim($string);
        $delimiter = ',';

        if($string[0] === '['){
            $delimiter = ';';
            $string = self::trimArray($string);
        }

        return explode($delimiter, $string);
    }
}