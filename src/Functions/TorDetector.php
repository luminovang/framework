<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

use \Luminova\Application\Paths;

class TorDetector 
{
    /**
     * @var string $torExitNodeListUrl
    */
    private static $torExitNodeListUrl = 'https://check.torproject.org/torbulkexitlist';

    /**
     * @var int $cacheExpiry
    */
    private static $cacheExpiry = 86400; 

    /**
     * Function to fetch and cache the Tor exit node list
     * 
     * @return string|false 
    */
    private static function fetchTorExitNodeList(): string|bool
    {
        $currentTime = time();
        if (file_exists(static::getPth()) && ($currentTime - filemtime(static::getPth()) < static::$cacheExpiry)) {
            return file_get_contents(static::getPth());
        }

        $result = file_get_contents(static::$torExitNodeListUrl);

        if($result !== false){
            write_content(static::getPth(), $result);
        }

        return $result;
    }

    /**
     * Checks if the given IP address is a Tor exit node
     * 
     * @param string $ip
     * 
     * @return bool 
    */
    public static function isTorExitNode(string $ip): bool 
    {
        $result = static::fetchTorExitNodeList();
        
        if( $result === false){
            return false;
        }

        return strpos($result, $ip) !== false;
    }

    /**
     * Get storage file path
     * 
     * @return string 
    */
    private static function getPth(): string 
    {
        $path = path('caches');
        $path .= 'tor' . DIRECTORY_SEPARATOR;

        Paths::createDirectory($path);
        
        $file = $path . 'torbulkexitlist.txt';

        return $file;
        
    }
}