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

class PageViewCache
{
    /**
     * The directory where cached files will be stored.
     * 
     * @var string $directory 
     */
    private string $directory;

    /**
     * The expiration time for cached 
     * 
     * @var int $expiration 
     */
    private int $expiration;

    /**
     * @var string $key Cache key
     */
    private string $key;

    /**
     * @var string $type Cache type
     */
    private string $type = 'html';

    /**
     * Class constructor.
     *
     * @param int $expiration The expiration time for cached files in seconds (default: 24 hours).
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     */
    public function __construct(int $expiration = 24 * 60 * 60, string $directory = 'cache')
    {
        $this->directory = $directory;
        $this->expiration = $expiration;
    }

    /**
     * Set cache expiration in seconds.
     *  
     * @param int $seconds Expiry (default: 24 hours).
     * 
     * @return self 
    */
    public function setExpiry(int $seconds): self
    {
        $this->expiration = $seconds;

        return $this;
    }

    /**
     * Set cache directory
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * 
     * @return self 
    */
    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Set cache directory
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * 
     * @return self 
    */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
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
        $this->key = md5($key);
    }

    /**
     * Get the cache key.
     *
     * @return string The cache key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the file path for the cache based on the current request URI.
     * 
     * @return string The file path for the cache.
    */
    public function getFilename(): string
    {
        return $this->getLocation() . $this->key . '.' . $this->type;
    }

    /**
     * Get the cache directory path.
     *
     * @return string The cache directory path.
     */
    public function getLocation(): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if the cached file is still valid based on its expiration time.
     *
     * @return bool True if the cache is still valid; false otherwise.
     */
    public function hasCache(): bool
    {
        $location = $this->getFilename();

        return file_exists($location) && !$this->expired($this->key, $this->directory);
    }

    /**
     * Check if the cached has expired.
     * 
     * @param string $key Cache key
     * 
     * @return bool True if the cache is still valid; false otherwise.
    */
    public static function expired(string $key, string $directory): bool
    {
        $metaLocation = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pagecache.lock';

        if(file_exists($metaLocation)){
            $info = json_decode(file_get_contents($metaLocation), true);
            if(isset($info[$key])){
                return time() >= (int) ($info[$key]['Expiry'] ?? 0);
            }
        }

        return true;
    }

    public function delete(){
        
    }

    public function clear(){
        
    }

    /**
     * Load the content from the cache file and exit the script.
     * 
     * @return bool True if loading was successful; false otherwise.
    */
    public function readContent(): bool
    {
        $headers = [];
        $metadta = $this->getLocation() . 'pagecache.lock';

        if (file_exists($metadta)) {
            $items = json_decode(file_get_contents($metadta), true);

            if(isset($items[$this->key])){
                $headers = Header::getSystemHeaders();
                $item = $items[$this->key];
                $headers['Content-Type'] = $item['Content-Type'];
                $headers['Content-Encoding'] = $item['Content-Encoding'];
                $headers['Expires'] = gmdate("D, d M Y H:i:s",  $item['Expiry']) . ' GMT';
                $headers['Cache-Control'] = 'max-age=' . $item['MaxAge'] . ', public';
                $headers['ETag'] =  '"' . $item['ETag'] . '"';
            }else{
                return false;
            }
        }else{
            return false;
        }

        $location = $this->getFilename();
        
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
     * @param array|null $metadata Cache information
     *
     * @return bool True if saving was successful; false otherwise.
     */
    public function saveCache(string $content, ?string $info = null, ?array $metadata = null): bool
    {
        $location = $this->getLocation();
        $filename = $this->getFilename();

        make_dir($location);     

        if($info !== null){
            $content .= '<!--[File was cached on - '. date('D jS M Y H:i:s') . ', Using: ' . $info . ']-->';
        }
  
        if(write_content($filename, $content)){
            $metadata = ($metadata === null) ? [] : $metadata;

            $metaLocation = $location . 'pagecache.lock';
            $jsonData = file_get_contents($metaLocation);
    
            $madatadaInfo = ($jsonData === false) ? [] : json_decode($jsonData, true);
    
            $metadata['MaxAge'] = $this->expiration;
            $metadata['Expiry'] = time() + $this->expiration;
            $metadata['Date'] = date("D, d M Y H:i:s");
            $metadata['ETag'] = md5_file($filename);

            $madatadaInfo[$this->key] = $metadata;
    
            $updateInfo = json_encode($madatadaInfo, JSON_PRETTY_PRINT);
    
            if(!write_content($metaLocation, $updateInfo)){
                unlink($filename);

                return false;
            }

            return true;
        }

        return false;
    }
}
