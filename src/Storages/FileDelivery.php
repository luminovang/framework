<?php
/**
 * Luminova Framework Private File Delivery Class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Storages;

use \Luminova\Luminova;
use \Luminova\Security\Crypter;
use \Luminova\Http\Header;
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
     * @param bool $eTag Whether to generate ETag header and apply validation as needed (default: true).
     * @param bool $weakEtag Whether to use a weak ETag header or string (default: false).
     * 
     * > **Note:** Set `$eTag` to true even if you are passing custom etag header.
     */
    public function __construct(
        private string $path, 
        private bool $eTag = true,
        private bool $weakEtag = false
    ){}

    /**
     * Static method to initialize the FileDelivery with a base path and ETag option.
     * 
     * @param string $path The base path for file storage, (e.g: /images/).
     * @param bool $eTag Whether to generate ETag header and apply validation as needed (default: true).
     * @param bool $weakEtag Whether to use a weak ETag header or string (default: false).
     * 
     * @return static Instance of the FileDelivery class.
     * 
     * @example Using storage method:
     * 
     * ```php
     * FileDelivery::storage('images/photos')
     * ```
     * 
     * > **Note:**
     * > Your files must be stored in the storage directory located in `/writeable/storages/`.
     * > Additionally you don't need to specify the `/writeable/storages/` in your `$path` parameter.
     * > Set `$eTag` to true even if you are passing custom etag header.
     */
    public static function storage(string $path, bool $eTag = true, bool $weakEtag = false): static
    {
        return new self(root('writeable/storages/' . trim($path, TRIM_DS)), $eTag, $weakEtag);
    }

    /**
     * Read and outputs any file content with appropriate headers.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $headers An associative array for additional headers to set.
     * @param int $length Optional size of each chunk to be read (default: 2MB).
     * @param int $delay Optional delay in microseconds between chunk length (default: 0).
     * 
     * @return bool Returns true if file output is successfully, false otherwise.
     * 
     * > By default `304`, `404` and `500` headers will be set based file status and cache control.
     */
    public function output(
        string $basename, 
        int $expiry = 0, 
        array $headers = [],
        int $length = (1 << 21),
        int $delay = 0
    ): bool
    {
        $filename = $this->assertOutputHead($basename, $expiry, $headers);

        if($filename === true){
            return true;
        }

        if($filename === false){
            return false;
        }

        $handler = fopen($filename, 'rb');

        if ($handler === false) {
            return false;
        }

        $filesize = self::cacheHeaders($headers, $basename, $filename, $expiry);
        $read = FileManager::read(
            $handler, 
            $filesize, 
            $headers['Content-Type'],
            $length,
            $delay
        );

        if(is_resource($handler)){
            fclose($handler);
        }
        
        return $read ? true : self::expiredHeader(500);
    }

    /**
     * Modify image height, width and quality before outputs the image content with appropriate headers.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $options Image filter options.
     *  -    width (int)  -   New output width (default: 200).
     *  -    height (int) -   New output height (default: 200).
     *  -    ratio (bool) -  Use aspect ratio while resizing image (default: true).
     *  -    quality (int) - Image quality (default: 100, 9 PNG).
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
            throw new RuntimeException(
                'To use this method you need to install "NanoImage" by running command "composer require peterujah/nano-image"' 
            );
        }

        $filename = $this->assertOutputHead($basename, $expiry, $headers);

        if($filename === true){
            return true;
        }

        if($filename === false){
            return false;
        }

        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;

        try{
            $img = (new NanoImage())->open($filename);
            if($width || $height){
                $img->resize(
                    max($width ?? $height, 1),
                    max($height ?? $width, 1),
                    (bool) ($options['ratio'] ?? true)
                );
            }

            self::cacheHeaders($headers, $basename, null, $expiry);

            $result = $img->get($options['quality'] ?? 100);
            $img->free();

            return (bool) $result;
        }catch(Exception $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Temporally output file based on the URL hash key if valid and not expired.
     *
     * @param string $urlHash The encrypted URL hash.
     * @param array $headers Additional headers to set.
     * 
     * @return bool Return true if file output is successful, false otherwise.
     * @throws EncryptionException Throws if decryption failed or an error is encountered.
     */
    public function temporal(string $urlHash, array $headers = []): bool
    {
        $data = Crypter::decrypt($urlHash);

        if ($data) {
            [$basename, $expiry, $then] = explode('|', $data);
            $expiration = (int) $then + (int) $expiry;

            if (time() > $expiration) {
                return self::expiredHeader(404);
            }

            return $this->output($basename, ($expiration - time()), $headers);
        }

        return self::expiredHeader(404);
    }

    /**
     * Generates temporal URL with an expiration time for given filename.
     *
     * @param string $basename The name of the file.
     * @param int $expiry The expiration time in seconds (default: 1hour).
     * 
     * @return string|false Return based64 encrypted url, otherwise false.
     * @throws EncryptionException Throws if encryption failed or an error occurred.
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
     * Set Output header.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $headers An associative array for additional headers passed by reference.
     * 
     * @return string|bool Return the filename or false if failed.
     */
    private function assertOutputHead(string $basename, int $expiry, array &$headers): string|bool
    {
        $filename = $this->path . DIRECTORY_SEPARATOR . ltrim($basename, TRIM_DS);
  
        clearstatcache(true, $filename);

        if (!file_exists($filename)) {
            return self::expiredHeader(404);
        }

        if ($this->eTag || $expiry > 0) {
            $filemtime = filemtime($filename);
            $filesize = (int) filesize($filename);
        }
        
        if ($this->eTag) {
            // $headers['ETag'] = '"' . md5_file($filename) . '"';
            $headers['ETag'] ??= ($this->weakEtag ? 'W/' : '') . '"' . base_convert($filemtime ?: '0', 10, 36) . '-' . $filesize . '"';
        }

        $headers['Content-Length'] ??= $filesize;
       
        // If 0 then no caching load fresh
        if ($expiry > 0) {

            if ($this->eTag) {
                $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');

                if ($ifNoneMatch === $headers['ETag']) {
                    return self::notModifiedHeader($expiry, $filemtime);
                }
            }

            if($filemtime !== false){
                if (
                    isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
                    strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $filemtime
                ) {
                    return self::notModifiedHeader($expiry, $filemtime);
                }

                $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $filemtime);
            }
        }

        $headers['Content-Type'] ??= get_mime($filename) ?: null;

        if (!$headers['Content-Type']) {
            return self::expiredHeader(500);
        }

        return $filename;
    }

    /**
     * Sets headers for an expired or not found response.
     *
     * @param int $statusCode The HTTP status code to set.
     * @param array $headers Headers to set.
     * 
     * @return false Always return false.
     */
    private static function expiredHeader(int $statusCode, array $headers = []): bool
    {
        $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $headers['Expires'] = '0';
        $headers['Pragma'] = 'no-cache';
        self::headers($headers, $statusCode);

        return false;
    }

    /**
     * Sets headers for an not modified response.
     *
     * @param int $expiry Cache expiry.
     * @param array $headers Headers to set.
     * 
     * @return true Always return true.
     */
    private static function notModifiedHeader(int $expiry, int $filemtime, array $headers = []): bool
    {
        if($expiry > 0){
            $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + $expiry);
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $filemtime);
        }

        self::headers($headers, 304, ($expiry > 0));

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
     * @return int Return the filesize.
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
            $headers['Cache-Control'] = ($expiry > 0) 
                ? "public, max-age={$expiry}, immutable" 
                : 'no-cache, no-store, must-revalidate';
        }

        $headers['Content-Disposition'] = 'inline; filename="' . $basename . '"';

        if($filename !== null){
            $headers['Content-Length'] ??= (int) filesize($filename);
        }

        self::headers($headers, 200, ($expiry > 0));
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
        
        $headers['X-Powered-By'] = Luminova::copyright();

        Header::sendStatus($status);
        Header::send($headers, false, false);

        if($cache){
            header_remove('Pragma');
        }
    }
}