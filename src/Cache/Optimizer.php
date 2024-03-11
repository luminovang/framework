<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cache;

use \Luminova\Http\Header;
use \Luminova\Application\Paths;

class Optimizer
{
    /**
     * @var string $cacheDir The directory where cached files will be stored.
     */
    private string $cacheDir;

    /**
     * @var int $cacheExpiration The expiration time for cached 
     */
    private int $cacheExpiration;

    /**
     * @var string $optimizerKey Cache key
     */
    private string $optimizerKey;

    /**
     * Class constructor.
     *
     * @param int $cacheExpiration The expiration time for cached files in seconds (default: 24 hours).
     * @param string $cacheDir The directory where cached files will be stored (default: 'cache').
     */
    public function __construct(int $cacheExpiration = 24 * 60 * 60, string $cacheDir = 'cache')
    {
        $this->cacheDir = $cacheDir;
        $this->cacheExpiration = $cacheExpiration;
    }

    /**
     * Get the file path for the cache based on the current request URI.
     *
     * @param string $extension file extension
     * 
     * @return string The file path for the cache.
    */
    public function getCacheLocation(string $extension = 'html'): string
    {
        return $this->getCacheFilepath() . $this->getKey() . '.' . $extension;
    }

    /**
     * Get the cache directory path.
     *
     * @return string The cache directory path.
     */
    public function getCacheFilepath(): string
    {
        return rtrim($this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if the cached file is still valid based on its expiration time.
     *
     * @return bool True if the cache is still valid; false otherwise.
     */
    public function hasCache(): bool
    {
        $location = $this->getCacheLocation();
        return file_exists($location) && time() - filectime($location) < $this->cacheExpiration;
    }

    /**
     * Get the formatted file modification time.
     *
     * @return string Formatted file modification time.
     */
    public function getFileTime(): string
    {
        $timestamp = filectime($this->getCacheLocation());
        return date('D jS M Y H:i:s', $timestamp);
    }

    /**
     * Load the content from the cache file and exit the script.
     * 
     * @return bool True if loading was successful; false otherwise.
     */
    public function getCache(string $info = null): bool
    {
        $headers = Header::getSystemHeaders();
        $location = $this->getCacheLocation();
        $infoLocation = $this->getCacheLocation('json');
        
        // Calculate the cache expiration time based on file creation time
        $fileCreationTime = filectime($location);
        if($fileCreationTime !== false){
            $cacheExpirationTime = $fileCreationTime + $this->cacheExpiration;
        }else{
            $cacheExpirationTime = $this->cacheExpiration;
        }

        // Set the "Expires" header based on the calculated expiration time
        $headers['Expires'] = gmdate("D, d M Y H:i:s", $cacheExpirationTime) . ' GMT';

        if (file_exists($infoLocation)) {
            $info = json_decode(file_get_contents($infoLocation), true);
            $headers['Content-Type'] = $info['Content-Type'];
            //$headers['Content-Length'] = $info['Content-Length'];
            $headers['Content-Encoding'] = $info['Content-Encoding'];
        }
        
        foreach ($headers as $header => $value) {
            header("$header: $value");
        }
        
        $bytesRead = readfile($location);
        
        if (ob_get_length() > 0) {
            ob_end_flush();
        }
        
        return $bytesRead !== false;
    }


    /**
     * Save the content to the cache file.
     *
     * @param string $content The content to be saved to the cache file.
     * @param string $info Framework copyright information
     *
     * @return bool True if saving was successful; false otherwise.
     */
    public function saveCache(string $content, ?string $info = null, array $cacheData = []): bool
    {
        $location = $this->getCacheFilepath();
        Paths::createDirectory($location);     
        if($info !== null){
            $now = date('D jS M Y H:i:s', time());
            $content .= '<!--[File was cached on - '. $now . ', Using: ' . $info . ']-->';
        }
    
        if($cacheData !== []){
            write_content($this->getCacheLocation('json'), json_encode($cacheData));
        }
        $bytesWritten = write_content($this->getCacheLocation(), $content);
        return $bytesWritten !== false;
    }

    /**
     * Get the cache key.
     *
     * @return string The cache key.
     */
    public function getKey(): string
    {
        return $this->optimizerKey;
    }

    /**
     * Set the cache key.
     *
     * @param string $key The key to set.
     *
     * @return void
     */
    public function setKey(string $key): void
    {
        $this->optimizerKey = md5($key);
    }
}
