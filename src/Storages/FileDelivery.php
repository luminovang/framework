<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storages;

use \Luminova\Application\Foundation;
use \Luminova\Security\Crypter;
use \Luminova\Storages\FileManager;
use \Peterujah\NanoBlock\NanoImage;
use \Luminova\Exceptions\RuntimeException;
use \Exception;

final class FileDelivery
{
    /**
     * Constructor to initialize the file path and ETag option.
     * 
     * @param string $path The base path to file storage (e.g: /writeable/storages/images/).
     * @param bool $eTag Whether to generate ETag headers (default: true).
    */
    public function __construct(
        private string $path, 
        private bool $eTag = true
    ){}

    /**
     * Static method to initialize the FileDelivery with a base path and ETag option.
     * 
     * @param string $path The base path for file storage, (e.g: /images/).
     * @param bool $eTag Whether to generate ETag headers.
     * 
     * @return static Instance of the FileDelivery class.
     *  > Note
     *  > Your files must be stored in the storage directory located in `/writeable/storages/`.
     *  > Additionally you don't need to specify the `/writeable/storages/` in your `$path` parameter.
    */
    public static function storage(string $path, bool $eTag = true): static
    {
        return new self(root('writeable/storages/' . trim($path, TRIM_DS)), $eTag);
    }

    /**
     * Outputs the file content with appropriate headers.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $headers An associative array for additional headers to set.
     * 
     * @return bool Returns true if file output is successfully, false otherwise.
     * 
     * > By default `304`, `404` and `500` headers will be set based file status and cache control.
     */
    public function output(string $basename, int $expiry = 0, array $headers = []): bool
    {
        $filename = $this->assertOutputHead($basename, $expiry, $headers);

        if($filename === true){
            return true;
        }

        if($filename === false){
            return false;
        }

        $read = false;

        if ($handler = fopen($filename, 'rb')) {
            $filesize = static::cacheHeaders($headers, $basename, $filename, $expiry);
            $read = FileManager::read($handler, $filesize, $headers['Content-Type']);
        }
 
        return $read ? true : static::expiredHeader(500);
    }

    /**
     * Outputs the image content with appropriate headers.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $options Image filter options.
     *  -    width (int)  -   New output width.
     *  -    height (int) -  New output height.
     *  -    ratio (bool) -  Use aspect ratio while resizing image.
     *  -    quality (int) - Image quality.
     * @param array<string,mixed> $headers An associative array for additional headers to set.
     * 
     * @return bool Returns true if file output is successfully, false otherwise.
     * @throws RuntimeException Throws if NanoImage image is not installed or if error occurred during image processing.
     * 
     * > By default `304`, `404` and `500` headers will be set based file status and cache control.
     */
    public function outputImage(string $basename, int $expiry = 0, array $options = [], array $headers = []): bool
    {
        if(!class_exists(NanoImage::class)){
            throw new RuntimeException('To use this method you need to install "NanoImage" by running command "composer require peterujah/nano-image"' );
        }

        $filename = $this->assertOutputHead($basename, $expiry, $headers);

        if($filename === true){
            return true;
        }

        if($filename === false){
            return false;
        }

        try{
            $img = new NanoImage();
         
            $img->open($filename);
            $img->resize(
                $options['width'] ?? 200, 
                $options['height'] ?? 200, 
                $options['ratio'] ?? true
            );

            static::cacheHeaders($headers, $basename, null, $expiry);
            $image = $img->get($options['quality']??100);
            if(is_string($image)){
                echo $image;
            }

            $img->free();
        }catch(Exception $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * Processes a temporal URL and outputs the file if valid and not expired.
     *
     * @param string $url_hash The encrypted URL hash.
     * @param array $headers Additional headers to set.
     * 
     * @return bool True if file output is successful, false otherwise.
     * @throws EncryptionException Throws if decryption failed.
    */
    public function temporal(string $url_hash, array $headers = []): bool
    {
        $data = Crypter::decrypt($url_hash);

        if ($data !== false && $data !== null) {
            [$basename, $expiry, $then] = explode('|', $data);
            $expiration = (int) $then + (int) $expiry;

            if (time() > $expiration) {
                return static::expiredHeader(404);
            }

            return $this->output($basename, ($expiration - time()), $headers);
        }

        return static::expiredHeader(404);
    }

    /**
     * Generates temporal URL with an expiration time for given filename.
     *
     * @param string $basename The name of the file.
     * @param int $expiry The expiration time in seconds (default: 1hour).
     * 
     * @return string|false Return based64 encrypted url, otherwise false.
     * @throws EncryptionException Throws if encryption failed.
    */
    public function url(string $basename, int $expiry = 3600): string|bool
    {
        $filename = $this->path . DIRECTORY_SEPARATOR . ltrim($basename, TRIM_DS);
  
        if (!file_exists($filename)) {
            return false;
        }

        if ($expiry > 0) {
            return Crypter::encrypt("{$basename}|{$expiry}|" . time());
        }

        return false;
    }

    /**
     * Set Output head.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $headers An associative array for additional headers passed by reference.
     * 
     * @return string|bool Filename or false.
     */
    private function assertOutputHead(string $basename, int $expiry, array &$headers): string|bool
    {
        $filename = $this->path . DIRECTORY_SEPARATOR . ltrim($basename, TRIM_DS);
  
        if (!file_exists($filename)) {
            return static::expiredHeader(404);
        }

        if ($this->eTag) {
            $eTag = '"' . md5_file($filename) . '"';
            $headers['ETag'] = $eTag;

            if ($expiry > 0) {
                $filemtime = filemtime($filename);
                $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
                if ($ifNoneMatch === $eTag) {
                    return static::notModifiedHeader($expiry, $filemtime);
                }

                if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($modify = $filemtime) !== false && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $modify) {
                   return static::notModifiedHeader($expiry, $filemtime);
                }

                $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $filemtime);
            }
        }

        $headers['Content-Type'] ??= get_mime($filename);
        if (!isset($headers['Content-Type'])) {
            return static::expiredHeader(500);
        }

        return $filename;
    }

    /**
     * Sets headers for an expired or not found response.
     *
     * @param int $statusCode The HTTP status code to set.
     * @param array $headers Headers to set.
     * 
     * @return false
     */
    private static function expiredHeader(int $statusCode, array $headers = []): bool
    {
        $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $headers['Expires'] = '0';
        $headers['Pragma'] = 'no-cache';
        static::headers($headers, $statusCode);

        return false;
    }

    /**
     * Sets headers for an not modified response.
     *
     * @param int $expiry Cache expiry.
     * @param array $headers Headers to set.
     * 
     * @return true
     */
    private static function notModifiedHeader(int $expiry, $filemtime, array $headers = []): bool
    {
        if($expiry > 0){
            $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + $expiry);
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $filemtime);
        }

        static::headers($headers, 304, ($expiry > 0));

        return true;
    }

    /**
     * Sets caching-related headers.
     *
     * @param array $headers The headers array.
     * @param string $basename The name of the file.
     * @param string|null $filename The full file path.
     * @param int $expiry The expiration time in seconds.
     * 
     * @return int Filesize.
    */
    private static function cacheHeaders(array $headers, string $basename, string|null $filename, int $expiry): int
    {
        if (!isset($headers['Expires'])) {
            if ($expiry > 0) {
                $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + $expiry);
            } else {
                $headers['Pragma'] = 'no-cache';
                $headers['Expires'] = '0';
            }
        }

        if (!isset($headers['Cache-Control'])) {
            $headers['Cache-Control'] = $expiry > 0 ? 'public, max-age=' . $expiry : 'no-cache, no-store, must-revalidate';
        }

        $headers['Content-Disposition'] = 'inline; filename="' . $basename . '"';
        if($filename !== null){
            $headers['Content-Length'] = filesize($filename);
        }

        static::headers($headers, 200, ($expiry > 0));

        return $headers['Content-Length'] ?? 0;
    }

    /**
     * Sets HTTP headers.
     *
     * @param array $headers The headers to set.
     * @param int $status HTTP status code.
     * @param bool $cache Allow caching if true remove pragma header.
    */
    private static function headers(array $headers, int $status = 200, bool $cache = false): void 
    {
        if (headers_sent()) {
            return;
        }
        
        http_response_code($status);
        $headers['X-Powered-By'] = Foundation::copyright();
        $removeHeaders = [];

        foreach ($headers as $header => $value) {
            if(!$header){
                continue;
            }

            if($value === ''){
                $removeHeaders[] = $header;
            }else{
                header("$header: $value");
            }
        }

        if($cache){
            $removeHeaders[] = 'Pragma';
        }

        if($removeHeaders !== []){
            array_map('header_remove', $removeHeaders);
        }
    }
}