<?php
/**
 * Luminova Framework Private File Response (FDS).
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Storage;

use \Throwable;
use \DateTimeZone;
use \Luminova\Luminova;
use \Luminova\Time\Time;
use \Luminova\Http\Header;
use \Luminova\Utility\MIME;
use \Peterujah\NanoBlock\NanoImage;
use \Luminova\Storage\Filesystem;
use \Luminova\Security\Encryption\Crypter;
use function \Luminova\Funcs\root;
use \Luminova\Exceptions\{FileException, RuntimeException};

final class FileResponse
{
    /**
     * Full storage path.
     * 
     * @var string $path
     */
    private string $path = '';

    /**
     * Initialize a FileResponse instance with a base path and ETag configuration.
     *
     * @param string $basepath Base path to file storage within `/writeable/` (e.g., `storages/images/foo/`).
     * @param bool $eTag Whether to generate ETag headers and perform validation (default: true).
     * @param bool $weakEtag Whether to use weak ETag headers (default: false).
     *
     * @throws FileException If the path is invalid or does not exist.
     * > **Note:**
     * > - Files must reside in the `/writeable/` directory.
     * > Set `$eTag` to true even if you provide a custom ETag header, 
     *       otherwise caching and validation may not behave as expected.
     */
    public function __construct(
        string $basepath, 
        private bool $eTag = true,
        private bool $weakEtag = false
    ) 
    {
        $basepath = trim($basepath, TRIM_DS);

        if (str_starts_with($basepath, '/') || str_starts_with($basepath, '\\')) {
            throw new FileException("Path must be relative to storage root, not absolute: {$basepath}");
        }

        $filepath = root('writeable/' . $basepath);

        if (!is_dir($filepath)) {
            throw new FileException(sprintf('Storage path: %s, does not exist: %s', $basepath, $filepath));
        }

        $this->path = $filepath;
    }

    /**
     * Create a FileResponse instance using the storage directory.
     *
     * @param string $basepath Relative storage path (e.g., 'images/photos').
     * @param bool $eTag Whether to generate ETag headers and perform validation (default: true).
     * @param bool $weakEtag Whether to use weak ETag headers (default: false).
     *
     * @return self Returns a new configured instance of FileResponse.
     * @throws FileException If the path is invalid or does not exist.

     * @example - Example:
     * ```php
     * $response = FileResponse::storage('images/photos');
     * ```
     *
     * > **Note:**
     * > - Files must reside in the `/writeable/storages/` directory.
     * > - Do not include `/writeable/storages/` in the `$path` parameter; it is prepended automatically.
     * > - Set `$eTag` to true even if passing a custom ETag header.
     */
    public static function storage(string $basepath, bool $eTag = true, bool $weakEtag = false): self
    {
        $basepath = trim($basepath, TRIM_DS);
        return new self(
            "storages/{$basepath}", 
            $eTag, 
            $weakEtag
        );
    }

    /**
     * Sends a file to the client with proper cache and response headers.
     *
     * The method resolves the file path, applies cache validation
     * (ETag / Last-Modified), sets headers, and streams the file
     * in chunks to the client.
     *
     * @param string $basename File name relative to the configured base path.
     * @param int $expiry Cache lifetime in seconds (0 disables caching).
     * @param array<string,mixed> $headers Additional response headers.
     * @param int $length Chunk size for streaming (default: 2MB).
     * @param int $delay Optional delay between chunks in microseconds.
     *
     * @return bool Returns true on successful output or cache hit, false on failure.
     * @throws RuntimeException Throws if an error occurred during file processing.
     *
     * > **Note:**
     * > It automatically sends 304, 404, or 500 responses when applicable.
     */
    public function send(
        string $basename, 
        int $expiry = 0, 
        array $headers = [],
        int $length = (1 << 21),
        int $delay = 0
    ): bool
    {
        $filename = $this->perseHeaders($basename, $expiry, $headers);

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

        try {
            $filesize = self::cacheHeaders($headers, $basename, $filename, $expiry);

            $read = Filesystem::read(
                $handler,
                $filesize,
                $headers['Content-Type'] ?? null,
                $length,
                $delay
            );

            if($read){
                return true;
            }
        }catch(Throwable $e){
            RuntimeException::throwException($e->getMessage(), $e->getCode(), $e);
        } finally {
            if(is_resource($handler)){
                fclose($handler);
            }
        }

        return self::expiredHeader(500);
    }

    /**
     * Resizes an image and outputs it to the client with proper headers.
     *
     * This method opens the given image file, optionally resizes it using
     * width, height, and aspect ratio options, sets cache and response headers,
     * and outputs the image content. Returns true on success, false otherwise.
     *
     * @param string $basename File name relative to base path (e.g., image.png).
     * @param int    $expiry   Cache lifetime in seconds (0 disables caching).
     * @param array<string,mixed> $options Image processing options:
     *   - 'width'   (int)  Output width (default: 200 if resizing).
     *   - 'height'  (int)  Output height (default: 200 if resizing).
     *   - 'ratio'   (bool) Preserve aspect ratio when resizing (default: true).
     *   - 'quality' (int)  Output quality (JPEG 0–100, PNG 0–9; default: 100).
     * @param array<string,mixed> $headers Additional response headers.
     *
     * @return bool Returns true if image is output successfully, false otherwise.
     * @throws RuntimeException If NanoImage is not installed or an error occurs during processing.
     * @see https://gitgub.com/peterujah/nano-image
     *
     * > **Note:**
     * > Automatically sets 304, 404, or 500 headers based on file status and cache control.
     */
    public function sendImage(
        string $basename, 
        int $expiry = 0, 
        array $options = [], 
        array $headers = []
    ): bool
    {
        if(!class_exists(NanoImage::class)){
            throw new RuntimeException(
                'NanoImage is required. Install via "composer require peterujah/nano-image".'
            );
        }

        $filename = $this->perseHeaders($basename, $expiry, $headers);

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
                    max($width ?? $height ?? 1, 1),
                    max($height ?? $width ?? 1, 1),
                    (bool) ($options['ratio'] ?? true)
                );
            }

            self::cacheHeaders($headers, $basename, null, $expiry);

            $result = $img->get($options['quality'] ?? 100);
            $img->free();

            return (bool) $result;
        }catch(Throwable $e){
            RuntimeException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Validates a temporal signed file token and streams the file if valid.
     *
     * The token must contain the filename, issue timestamp, expiry duration,
     * and timezone, encrypted using Crypter. If the token is invalid,
     * malformed, or expired, a 404 response is returned.
     *
     * @param string $fileHash Encrypted temporal file token.
     * @param array<string,mixed> $headers Additional response headers.
     * @param int $length Chunk size for streaming (default: 2MB).
     * @param int $delay Optional delay between chunks in microseconds.
     *
     * @return bool Returns true if the file is successfully streamed, false otherwise.
     *
     * @throws EncryptionException If token decryption fails.
     * @throws RuntimeException Throws if an error occurred during file processing.
     */
    public function sendSigned(
        string $fileHash, 
        array $headers = [], 
        int $length = (1 << 21), 
        int $delay = 0
    ): bool
    {
        $data = Crypter::decrypt(strtr($fileHash, '._-', '+/='));

        if ($data === false) {
           return self::expiredHeader(404);
        }

        $payload = $this->unpack($data);

        if ($payload === null || $payload['exp'] <= 0 || $payload['iat'] <= 0) {
            return self::expiredHeader(404);
        }

        $now = Time::now($payload['tz']);
        $expiresAt = $payload['iat'] + $payload['exp'];

        if ($now > $expiresAt) {
            return self::expiredHeader(404);
        }

        return $this->send(
            $payload['file'], 
            ($expiresAt - $now), 
            $headers, 
            $length, 
            $delay
        );
    }

    /**
     * Generates a temporal signed token for a file with an expiration time.
     *
     * The token encodes the filename, expiry duration, timestamp,
     * and timezone, then encrypts the payload using Crypter.
     *
     * @param string $basename The file name to sign.
     * @param int $expiry Expiration time in seconds (default: 3600).
     * @param DateTimeZone|string|null $timezone Optional timezone for timestamp generation.
     *
     * @return string|false Returns a base64-encoded encrypted token, or false if the file does not exist.
     *
     * @throws EncryptionException If encryption fails.
     * @see Luminova\Security\Encryption\Crypter Used for generating signed file hash.
     */
    public function sign(
        string $basename, 
        int $expiry = 3600, 
        DateTimeZone|string|null $timezone = null
    ): string|bool
    {
        $filename = $this->path . DIRECTORY_SEPARATOR . ltrim($basename, TRIM_DS);
  
        if (!is_file($filename) || $expiry <= 0) {
            return false;
        }

        $timezone = ($timezone instanceof DateTimeZone) 
            ? $timezone->getName() 
            : $timezone;

        $payload = [
            'file' => $basename,
            'iat'  => Time::now($timezone),
            'exp'  => $expiry,
            'tz'   => $timezone,
        ];

        return strtr(
			Crypter::encrypt(json_encode($payload)),
			'+/=', '._-'
		);
    }

    /**
     * Extracts file data from a signed token.
     *
     * @param string $data Encrypted or encoded token containing file information.
     * 
     * @return array<string,int|string|null>|null {
     *     @type string      'file' File name
     *     @type int         'iat'  Issue timestamp (UNIX time)
     *     @type int         'exp'  Expiry duration in seconds
     *     @type string|null 'tz'   Timezone name or null
     * }
     */
    private function unpack(string $data): ?array 
    {
        $basename = null;

        if (json_validate($data)) {
            $parts = json_decode($data, true);

            if (isset($parts['file'], $parts['iat'])) {
                $basename = $parts['file'];
                $expiry = $parts['exp'];
                $issuedAt = $parts['iat'];
                $timezone = $parts['tz'] ?? null;
            }
        }elseif (str_contains($data, '|')) {
           $parts = explode('|', $data, 4);

            if (count($parts) === 4) {
                [$basename, $expiry, $issuedAt, $timezone] = $parts;
            }
        }

        if($basename === null){
            return null;
        }

        return [
            'file' => (string) $basename,
            'iat'  => (int) $issuedAt,
            'exp'  => (int) $expiry,
            'tz'   => $timezone ?: null,
        ];
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
    private function perseHeaders(string $basename, int $expiry, array &$headers): string|bool
    {
        $filename = $this->path . DIRECTORY_SEPARATOR . ltrim($basename, TRIM_DS);
  
        clearstatcache(true, $filename);

        if (!is_file($filename)) {
            return self::expiredHeader(404);
        }

        if ($this->eTag || $expiry > 0) {
            $filemtime = filemtime($filename);
            $filesize = (int) filesize($filename);
        }
        
        if ($this->eTag) {
            // $headers['ETag'] = '"' . md5_file($filename) . '"'
            $headers['ETag'] ??= ($this->weakEtag ? 'W/' : '') . 
                '"' . base_convert($filemtime ?: '0', 10, 36) . 
                '-' . $filesize . '"';
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

        $headers['Content-Type'] ??= MIME::guess($filename);

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
    private static function cacheHeaders(
        array $headers, 
        string $basename, 
        ?string $filename, 
        int $expiry
    ): int
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
        $headers['X-Powered-By'] = Luminova::copyright();

        if($cache){
            unset($headers['Pragma']);
        }

        Header::send($headers, true, false, $status, false);

        if($cache){
            header_remove('Pragma');
        }

        Header::clearOutputBuffers('all');
    }
}