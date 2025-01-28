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

use \Luminova\Exceptions\FileException;

final class Tor 
{
    /**
     * Tor exit node list. 
     * 
     * @var string $torExitNodeListUrl
     */
    private static string $torExitNodeListUrl = 'https://check.torproject.org/torbulkexitlist';

    /**
     * Save path. 
     * 
     * @var ?string $path
     */
    private static ?string $path = null;

    /**
     * Function to fetch and cache the Tor exit node list.
     * 
     * @param int $expiration Cache expiration time in seconds.
     * 
     * @return string|bool Return fetched exit node list, otherwise false.
     * @throws FileException Throws if error occurs or unable to read or write to directory.
     */
    private static function fetchTorExitNodeList(int $expiration): string|bool
    {
        self::$path ??= self::getPth();

        if(self::$path){
            throw new FileException(sprintf('Unable to read or write to tor exit directory: %s.', self::$path));
        }

        if (file_exists(self::$path) && (time() - filemtime(self::$path) < $expiration)) {
            return get_content(self::$path);
        }

        $result = file_get_contents(self::$torExitNodeListUrl);

        if($result === false){
           return false;
        }

        return write_content(self::$path, $result);
    }

    /**
     * Checks if the given IP address is a Tor exit node.
     * 
     * @param string $ip The Ip address to check.
     * @param int $expiration The expiration time to request for new exit nodes from tor api (default: 2_592_000).
     * 
     * @return bool Return true if the IP address is a Tor exit node otherwise false.
     */
    public static function isTor(string $ip, int $expiration = 2_592_000): bool 
    {
        $result = self::fetchTorExitNodeList($expiration);
        
        if($result === false){
            return false;
        }

        return str_contains($result, $ip);
    }

    /**
     * Get storage file path.
     * 
     * @return string Return storage path.
     */
    private static function getPth(): ?string 
    {
        static $path = null;
        $path ??= root('/writeable/caches/tor/');
        
        if(!make_dir($path)){
            return null;
        }

        return "{$path}torbulkexitlist.txt";
    }
}