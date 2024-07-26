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

final class TorDetector 
{
    /**
     * @var string $torExitNodeListUrl
    */
    private static string $torExitNodeListUrl = 'https://check.torproject.org/torbulkexitlist';

    /**
     * @var int $cacheExpiry
    */
    private static int $cacheExpiry = 86400; 

    /**
     * Function to fetch and cache the Tor exit node list
     * 
     * @return string|bool 
    */
    private static function fetchTorExitNodeList(): string|bool
    {
        $currentTime = time();
        if (file_exists(self::getPth()) && ($currentTime - filemtime(self::getPth()) < self::$cacheExpiry)) {
            return get_content(self::getPth());
        }

        $result = file_get_contents(self::$torExitNodeListUrl);

        if($result !== false){
            write_content(self::getPth(), $result);
        }

        return $result;
    }

    /**
     * Checks if the given IP address is a Tor exit node
     * 
     * @param string $ip Ip address to check.
     * 
     * @return bool true if the IP address is a Tor exit node otherwise false.
    */
    public static function isTor(string $ip): bool 
    {
        $result = self::fetchTorExitNodeList();
        
        if( $result === false){
            return false;
        }

        return strpos($result, $ip) !== false;
    }

    /**
     * Get storage file path
     * 
     * @return string Return storage path.
    */
    private static function getPth(): string 
    {
        $path = path('caches') . 'tor' . DIRECTORY_SEPARATOR;

        make_dir($path);
        return $path . 'torbulkexitlist.txt';
    }
}