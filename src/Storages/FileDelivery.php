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

final class FileDelivery
{
     /**
     * @var string $filepath
    */
    private static string $filepath;

    /**
     * @var bool $etag
    */
    private static bool $etag = true;

    /**
     * Constructor to initialize the file path and ETag option.
     * 
     * @param string $filepath Path to the private storage (e.g: /writeable/storages/images/).
     * @param bool $etag Whether to generate ETag headers.
    */
    public function __construct(string $filepath, bool $etag)
    {
        static::$filepath = $filepath;
        static::$etag = $etag;
    }

    /**
     * Static method to initialize the FileDelivery with a base path and ETag option.
     * 
     * @param string $basepath Base path for file storage, (e.g: /images/).
     * @param bool $etag Whether to generate ETag headers.
     * 
     * @return static Instance of the FileDelivery class.
     *  > Note
     *  > Your files must be stored in the storage directory located in `/writeable/storages/`.
     *  > Additionally you don't need to specify the `/writeable/storages/` in your `$basepath` parameter.
    */
    public static function storage(string $basepath, bool $etag = true): static
    {
        $path = root('writeable/storages/' . trim($basepath, DIRECTORY_SEPARATOR));
        return new self($path, $etag);
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
        $filename = static::$filepath . DIRECTORY_SEPARATOR . ltrim($basename, DIRECTORY_SEPARATOR);
  
        if (!file_exists($filename)) {
            return static::expiredHeader(404);
        }

        if (self::$etag) {
            $etag = '"' . md5_file($filename) . '"';
            $headers['ETag'] = $etag;

            if ($expiry > 0) {
                $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
                if ($ifNoneMatch === $etag) {
                    return static::notModifiedHeader($expiry);
                }

                if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($modify = filemtime($filename)) !== false && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $modify) {
                   return static::notModifiedHeader($expiry);
                }
            }
        }

        $headers['Content-Type'] ??= get_mime($filename);
        if (!$headers['Content-Type']) {
            return static::expiredHeader(500);
        }

        static::cacheHeaders($headers, $basename, $filename, $expiry);

        if ($fp = fopen($filename, 'rb')) {
            return fpassthru($fp) !== false;
        }
        return false;
    }

    /**
     * Processes a temporal URL and outputs the file if valid and not expired.
     *
     * @param string $url_hash The encrypted URL hash.
     * @param array $headers Additional headers to set.
     * 
     * @return bool True if file output is successful, false otherwise.
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
    */
    public function url(string $basename, int $expiry = 3600): string|bool
    {
        $filename = static::$filepath . DIRECTORY_SEPARATOR . ltrim($basename, DIRECTORY_SEPARATOR);
  
        if (!file_exists($filename)) {
            return false;
        }

        if ($expiry > 0) {
            return Crypter::encrypt("{$basename}|{$expiry}|" . time());
        }

        return false;
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
        http_response_code($statusCode);
        $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $headers['Expires'] = '0';
        static::headers($headers);

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
    private static function notModifiedHeader(int $expiry, array $headers = []): bool
    {
        http_response_code(304);
        if($expiry > 0){
            $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + $expiry);
        }
        static::headers($headers);

        return true;
    }

    /**
     * Sets caching-related headers.
     *
     * @param array $headers The headers array.
     * @param string $basename The name of the file.
     * @param string $filename The full file path.
     * @param int $expiry The expiration time in seconds.
    */
    private static function cacheHeaders(array $headers, string $basename, string $filename, int $expiry): void
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
        $headers['Content-Length'] = filesize($filename);

        static::headers($headers);
    }

    /**
     * Sets HTTP headers.
     *
     * @param array $headers The headers to set.
    */
    private static function headers(array $headers): void 
    {
        $headers['X-Powered-By'] = Foundation::copyright();
        foreach ($headers as $key => $value) {
            header("$key: $value");
        }
    }
}