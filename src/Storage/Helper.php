<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storage;

class Helper
{

    private static array $unites = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    public static function byteToUnit(int $bytes, bool $add_name = false): string
    {
        $index = 0;
    
        while ($bytes >= 1024 && $index < count(static::$unites) - 1) {
            $bytes /= 1024;
            $index++;
        }
    
        $formatted = number_format($bytes, 2);
        $result = $add_name ? $formatted . ' ' . static::$unites[$index] : $formatted;
    
        return $result;
    }

    public static function toBytes($from) {
		$number = substr($from, 0, -2);
		$suffix = strtoupper(substr($from,-2));
	
		//B or no suffix
		if(is_numeric(substr($suffix, 0, 1))) {
			return preg_replace('/[^\d]/', '', $from);
		}
	
		$exponent = array_flip(static::$unites)[$suffix] ?? null;
		if($exponent === null) {
			return 0;
		}
	
		return $number * (1024 ** $exponent);
	}
}