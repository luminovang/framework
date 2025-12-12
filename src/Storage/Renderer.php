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

use \Imagick;
use \Throwable;
use \DateTimeZone;
use Luminova\Runtime;
use Luminova\Luminova;
use Luminova\Time\Time;
use Luminova\Http\Header;
use Luminova\Utility\Mime;
use \Peterujah\Media\NanoImage;
use Luminova\Storage\Filesystem;
use Luminova\Security\Encryption\Crypter;
use Luminova\Exceptions\{FileException, RuntimeException, EncryptionException, InvalidArgumentException};

final class Renderer
{
    /**
     * Full storage path.
     * 
     * @var string $path
     */
    private string $path = '';

    /**
     * Image resize options.
     *
     * @var array $imageOptions
     */
    private array $imageOptions = [];

    /**
     * Provided eTag.
     *
     * @var bool $hasEtag
     */
    private bool $hasEtag = false;

    /**
     * If file is an image.
     *
     * @var bool
     */
    private bool $isImage = false;

    /**
     * File extension type.
     *
     * @var string|null
     */
    private ?string $extension = null;

    /**
     * Image resize handler.
     *
     * @var callable|null $onResizeImageHandler
     */
    private mixed $onResizeImageHandler = null;

    /**
     * Create a file renderer for a storage directory.
     *
     * @param string $dirpath Relative path to the storage directory.
     * @param bool $eTag Whether to generate and validate ETag headers (default: true).
     * @param bool $weakEtag Whether to generate weak ETag headers (default: false).
     *
     * @throws FileException If directory does not exist.
     * 
     * @see self::storage() To create instance from application storage directory.
     */
    public function __construct(
        string $dirpath,
        private bool $eTag = true,
        private bool $weakEtag = false
    ) 
    {
        if ($dirpath === DIRECTORY_SEPARATOR) {
            throw new FileException(
                "Invalid storage directory: {$dirpath}"
            );
        }

        $dirpath = trim($dirpath, TRIM_DS);

        if ($dirpath === '') {
            throw new FileException('Storage directory path cannot be empty.');
        }

        $basepath = Luminova::root($dirpath);

        if (!is_dir($basepath)) {
            throw new FileException(sprintf(
                'Storage directory "%s" does not exist: "%s".',
                $dirpath,
                $basepath
            ));
        }

        if (!is_readable($basepath)) {
            throw new FileException(sprintf(
                'Storage directory "%s" is not readable.',
                $dirpath
            ));
        }

        $this->path = $basepath;
    }

    /**
     * Create a file renderer for a directory within `/writeable/storages/`.
     *
     * The storage directory prefix is added automatically. Only a relative
     * path within the storage directory should be provided.
     *
     * @param string $basepath Relative path within `writeable/storages/`,
     *                         for example `images/photos`.
     * @param bool $eTag Whether to generate and validate ETag headers (default: true).
     * @param bool $weakEtag Whether to generate weak ETag headers (default: false).
     *
     * @return self A configured file renderer instance.
     * @throws FileException If the path is empty, absolute, contains traversal
     *                       segments, or the directory does not exist.
     * 
     * @example - Example:
     * ```php
     * $response = Renderer::storage('images/photos');
     *
     * $response->send(
     *     'photo.jpg',
     *     3600,
     *     ['X-Custom-Header' => 'Value']
     * );
     * ```
     */
    public static function storage(
        string $basepath,
        bool $eTag = true,
        bool $weakEtag = false
    ): self 
    {
        $basepath = trim($basepath, TRIM_DS);
        $normalized = str_replace('\\', '/', $basepath);

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('~(?:^|/)\.\.?(/|$)~', $normalized) === 1
        ) {
            throw new FileException(
                "Path: {$basepath} must be relative to storage directory: writeable/storages/"
            );
        }

        return new self(
            "writeable/storages/{$normalized}",
            $eTag,
            $weakEtag
        );
    }

    /**
     * Send a file to the client with cache validation and streaming support.
     *
     * Accepts a file basename or a signed file token. Resolves the source,
     * applies response headers and conditional cache validation, and streams
     * the file in chunks without loading its entire contents into memory.
     *
     * If image resizing is configured, the file is processed before output.
     *
     * @param string $source File basename relative to the configured base path, or a signed file token.
     * @param int|null $expiry Cache lifetime in seconds, or null to use the default or signed expiry.
     * @param array<string,mixed> $headers Additional response headers.
     * @param int $length Maximum chunk size in bytes (default: 2 MB).
     * @param int $delay Delay between chunks, as accepted by `uwait()`.
     *
     * @return bool Returns true if the file is streamed or a cache response is handled successfully, otherwise false.
     *
     * @throws RuntimeException If file processing fails.
     *
     * @see self::sign() To generate a signed file token.
     * @see self::resizeImage() To configure image resizing.
     * @see self::onResizeImage() To register custom image resize handler.
     *
     * @example Sending a file:
     * ```php
     * $renderer->send('photo.jpg', 3600, [
     *     'X-Custom-Header' => 'Value',
     * ]);
     * ```
     *
     * @example Sending a signed file:
     * ```php
     * $fileHash = $renderer->sign('photo.jpg', 3600);
     *
     * $renderer->send($fileHash, headers: [
     *     'X-Custom-Header' => 'Value',
     * ]);
     * ```
     * 
     * @example - Send Resized Image Examples:
     * 
     * ```php
     * $renderer->resizeImage(150, 150, true, 90);
     * $renderer->send('photo.jpg', headers: [
     *      'X-Custom-Header' => 'Value'
     * ]);
     * ```
     */
    public function send(
        string $source,
        ?int $expiry = null,
        array $headers = [],
        int $length = (1 << 21),
        int $delay = 0
    ): bool 
    {
        if ($length <= 0 || $delay < 0) {
            return false;
        }

        $head = $this->parseFileHeaders($source, $expiry, $headers);

        if ($head === true) {
            return true;
        }

        if ($head === false) {
            return false;
        }

        try {
            return $this->output(
                ($this->isImage && $this->imageOptions !== []) 
                    ? $this->modifyImage($head[0])
                    : Filesystem::contents($head[0], $length, delay: $delay),
                $head,
                $headers
            );
        } catch (Throwable $e) {
            RuntimeException::handleException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );

            return self::sendErrorHeaders(500);
        }
    }

    /**
     * Configures image resizing options.
     *
     * Sets the target dimensions, aspect ratio behavior, and output quality for
     * subsequent image rendering. If either dimension is omitted, the provided
     * dimension is used for both. If both are omitted, dimensions default to
     * 50 × 50 pixels.
     *
     * The resize handler is selected automatically based on available image
     * processing support, unless a custom handler has been registered using
     * {@see self::onResizeImage()}.
     *
     * @param int|null $width Target width in pixels, or null to use the height.
     * @param int|null $height Target height in pixels, or null to use the width.
     * @param bool $ratio Whether to preserve the source aspect ratio.
     * @param int $quality Output quality from 0 to 100, depending on the image format.
     *
     * @return self Returns the renderer instance for method chaining.
     *
     * @throws InvalidArgumentException If a provided dimension is not positive
     *     or the quality is outside the range of 0 to 100.
     *
     * @see self::onResizeImage() To register a custom image resize handler.
     * @see https://github.com/peterujah/nano-image
     */
    public function resizeImage(
        ?int $width = null,
        ?int $height = null,
        bool $ratio = true,
        int $quality = 100
    ): self 
    {
        if (
            ($width !== null && $width <= 0)
            || ($height !== null && $height <= 0)
        ) {
            throw new InvalidArgumentException(
                'Image dimensions must be positive integers.'
            );
        }

        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException(
                'Image quality must be between 0 and 100.'
            );
        }

        $handler = 'callable';

        if($this->onResizeImageHandler === null){
            $handler = match (true) {
                extension_loaded('gd')
                    && class_exists(NanoImage::class) => 'nano',

                extension_loaded('imagick')
                    && class_exists(Imagick::class) => 'imagick',

                default => 'callable',
            };
        }

        $width ??= $height ?? 50;
        $height ??= $width;

        $this->imageOptions = [
            'width'   => max($width, 1),
            'height'  => max($height, 1),
            'ratio'   => $ratio,
            'quality' => $quality,
            'handler' => $handler,
        ];

        return $this;
    }

    /**
     * Sets a custom image resizing handler.
     *
     * Registers a callback to handle image resizing with the requested dimensions,
     * aspect ratio behavior, and output quality. The callback should return the
     * resized image content as a string, or null if resizing fails.
     *
     * @param callable(string $filename,int $width,int $height,bool $ratio,int $quality,string $extension): ?string $handler The callback responsible for resizing the image and returning its content.
     *
     * @return self Returns the current instance for method chaining.
     *
     * @see self::resizeImage() - To set image resize options.
     * 
     * @example - Example:
     * ```php
     * $renderer->onResizeImage(
     *     static function (string $filename, int $width, int $height, bool $ratio, int $quality): ?string {
     *         $image = new Imagick($filename);
     *         $image->setImageCompressionQuality($quality);
     *
     *         $ratio
     *             ? $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true)
     *             : $image->thumbnailImage($width, $height, true, false);
     *
     *         $content = $image->getImageBlob();
     *         $image->clear();
     *         $image->destroy();
     *
     *         return $content !== '' ? $content : null;
     *     }
     * );
     * $renderer->resizeImage(150, 150);
     * ```
     * 
     * > **Note:**
     * > This handle is called only if file is an image and `resizeImage()` options was provided.
     */
    public function onResizeImage(callable $handler): self
    {
        $this->onResizeImageHandler = $handler;

        return $this;
    }

    /**
     * Decode and validate a signed file token.
     *
     * Decrypts the token, validates its payload and issue timestamp, and checks
     * whether it has expired. Returns the file basename and remaining validity
     * duration, or null if the token is invalid or expired.
     *
     * @param string $hash The URL-safe signed file token.
     *
     * @return array{basename:string,expires:int,info:array}|null Returns the decoded file basename 
     *      and remaining validity in seconds, or null on failure.
     */
    public static function decode(string $hash): ?array
    {
        if ($hash === '') {
            return null;
        }

        $hash = strtr($hash, '._-', '+/=');
        $data = Crypter::decrypt($hash);

        if (!is_string($data) || $data === '') {
            return null;
        }

        $payload = self::unpack($data);

        if ($payload === null) {
            return null;
        }

        if ($payload['exp'] === 0) {
            return [
                'basename' => $payload['file'],
                'expires'  => 0,
                'info'     => [
                    'created'    => $payload['iat'],
                    'expiration' => 0,
                    'timezone'   => $payload['tz']
                ]
            ];
        }

        try {
            $timezone = $payload['tz'] ?? null;
            $now = ($timezone === null)
                ? time()
                : Time::now($timezone)->getTimestamp();
        } catch (Throwable) {
            return null;
        }

        $expiresAt = $payload['iat'] + $payload['exp'];

        if ($now >= $expiresAt) {
            return null;
        }

        return [
            'basename'  => $payload['file'],
            'expires'   => $expiresAt - $now,
            'info'      => [
                'created'    => $payload['iat'],
                'expiration' => $payload['exp'],
                'timezone'   => $payload['tz']
            ]
        ];
    }

    /**
     * Generate a signed token for a file.
     *
     * Encodes the file basename, issue timestamp, expiration duration, and
     * optional timezone, then encrypts the payload to create a URL-safe token.
     *
     * An expiry of null or 0 creates a token without time-based expiration.
     *
     * @param string $basename File name relative to the configured base path.
     * @param int|null $expiry Token validity duration in seconds (default: 3600).
     * @param DateTimeZone|string|null $timezone Timezone used for timestamp generation.
     *
     * @return string|false Returns the URL-safe encrypted token, or false if the file does not exist.
     *
     * @throws EncryptionException If encryption fails.
     *
     * @see self::decode()
     * @see Luminova\Security\Encryption\Crypter
     */
    public function sign(
        string $basename,
        ?int $expiry = null,
        DateTimeZone|string|null $timezone = null
    ): string|false 
    {
        $filename = $this->parseBasename($basename);

        if (!is_file($filename) || ($expiry !== null && $expiry < 0)) {
            return false;
        }

        $timezone = ($timezone instanceof DateTimeZone)
            ? $timezone->getName()
            : $timezone;

        try {
            $iat = ($timezone === null) 
                ? time() 
                : Time::now($timezone)->getTimestamp();
        } catch (Throwable) {
            return false;
        }

        $payload = [
            'file' => $basename,
            'iat'  => $iat,
            'exp'  => $expiry ?? 0,
            'tz'   => (string) $timezone,
        ];

        $json = json_encode($payload);

        if ($json === false) {
            throw new EncryptionException(
                'Failed to encode file signature payload.'
            );
        }

        $data = Crypter::encrypt($json);

        if ($data === false) {
            throw new EncryptionException('Failed to encrypt file signature.');
        }

        return strtr($data, '+/=', '._-');
    }

    /**
     * Resizes an image and return image blob.
     *
     * Processes the source image using the configured handler or a custom resize
     * callback, then sends the response headers and image content.
     *
     * @param string $filename The file path.
     *
     * @return string|null Returns processing image blob or null if failed.
     *
     * @throws RuntimeException If image processing fails and the exception handler
     *     rethrows the error.
     */
    private function modifyImage(string $filename): ?string 
    {
        $content = null;

        $options = $this->imageOptions;
        $handler = $options['handler'] ?? 'nano';
        $width = $options['width'];
        $height = $options['height'];
        $ratio = (bool) $options['ratio'];
        $quality = $options['quality'];

        try {
            $this->extension ??= self::getExtension($filename);

            if ($this->onResizeImageHandler !== null) {
                return ($this->onResizeImageHandler)(
                    $filename,
                    $width,
                    $height,
                    $ratio,
                    $quality,
                    $this->extension
                );
            } 

            $content = $this->scaleImage(
                $handler,
                $filename,
                $width,
                $height,
                $ratio,
                $quality
            );

            if ($content === null) {
                throw new RuntimeException(sprintf(
                    'Unsupported image handler "%s", or no supported image '
                    . 'handler is available. Install Imagick or GD with NanoImage '
                    . '(composer require peterujah/nano-image), or register a '
                    . 'custom resize handler using %s::onResizeImage().',
                    $handler,
                    self::class
                ));
            }
        } finally {
            $this->imageOptions = [];
        }

        return $content;
    }

    /**
     * Sends content to the client.
     * 
     * The response includes the content length and an ETag when enabled.
     *
     * @param mixed $content
     * @param array{
     *     0:string,
     *     1:string,
     *     2:int,
     *     3:mixed,
     *     4:int|null
     * } $head Parsed file information containing the absolute filename,
     *     basename, expiry duration, an unused value, and the modification time.
     * @param array<string,mixed> $headers Additional response headers.
     * 
     * @return bool
     */
    private function output(
        mixed $content,
        array $head,
        array $headers
    ): bool
    {
        if (!is_string($content) || $content === '') {
            return self::sendErrorHeaders(500);
        }

        [$filename, $basename, $expireAt,, $filemtime] = $head;

        $filesize = strlen($content);
        $headers['Content-Length'] = $filesize;

        if ($this->eTag && !$this->hasEtag) {
            $headers['ETag'] = $this->getEtag(
                $filesize,
                $filemtime,
                $filename
            );
        }

        self::sendFileHeaders($headers, $basename, $expireAt);

        echo $content;

        return true;
    }

    /**
     * Resizes an image using the specified image handler.
     *
     * Supports native Imagick and NanoImage. Returns null when the specified
     * handler is not supported.
     *
     * @param string $handler Image processing handler, either "imagick" or "nano".
     * @param string $filename Absolute path to the source image.
     * @param int $width Target image width.
     * @param int $height Target image height.
     * @param bool $ratio Whether to use the configured proportional resize behavior.
     * @param int $quality Output image quality, from 0 to 100.
     *
     * @return string|null Returns the processed image content, or null if the
     *     handler is unsupported.
     *
     * @throws Throwable If the selected image handler fails during processing.
     */
    private function scaleImage(
        string $handler,
        string $filename,
        int $width,
        int $height,
        bool $ratio,
        int $quality
    ): ?string 
    {
        $image = null;

        try {
            if ($handler === 'imagick') {
                $image = new Imagick($filename);
                $image->setImageFormat($this->extension);
                $image->setImageCompressionQuality($quality);

                if ($ratio) {
                    $image->resizeImage(
                        $width,
                        $height,
                        Imagick::FILTER_LANCZOS,
                        1,
                        true
                    );
                } else {
                    $image->thumbnailImage(
                        $width,
                        $height,
                        true,
                        false
                    );
                }

                return $image->getImageBlob();
            }

            if ($handler === 'nano') {
                $image = new NanoImage();

                return $image
                    ->open($filename)
                    ->resize($width, $height, $ratio)
                    ->toString($quality);
            }

            return null;
        } finally {
            if ($image instanceof Imagick) {
                $image->clear();
                $image->destroy();
            } elseif ($image instanceof NanoImage) {
                $image->free();
            }
        }
    }

    /**
     * Resolves the full file path based on the base path and provided basename.
     *
     * @param string $basename The file name relative to the base path.
     *
     * @return string Returns the resolved full file path.
     */
    private function parseBasename(string $basename): string
    {
        $basename = ltrim($basename, TRIM_DS);

        if (
            $basename === $this->path 
            || str_starts_with($basename, ltrim($this->path, TRIM_DS))
        ) {
            return $basename;
        }

        return $this->path . $basename;
    }

    /**
     * Extract file data from a signed token payload.
     *
     * Supports JSON payloads and the legacy pipe-delimited format.
     *
     * @param string $data Decrypted payload containing file information.
     *
     * @return array{file:string,iat:int,exp:int,tz:string|null}|null
     *         Returns the unpacked file data, or null if the payload is invalid.
     */
    private static function unpack(string $data): ?array
    {
        try {
            if (json_validate($data)) {
                $parts = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

                if (
                    !is_array($parts)
                    || !isset($parts['file'], $parts['iat'], $parts['exp'])
                    || !is_string($parts['file'])
                    || $parts['file'] === ''
                    || !is_int($parts['iat'])
                    || !is_int($parts['exp'])
                    || $parts['iat'] <= 0
                    || $parts['exp'] < 0
                ) {
                    return null;
                }

                $timezone = $parts['tz'] ?? null;

                if ($timezone !== null && !is_string($timezone)) {
                    return null;
                }

                return [
                    'file' => $parts['file'],
                    'iat'  => $parts['iat'],
                    'exp'  => $parts['exp'],
                    'tz'   => ($timezone !== '') ? $timezone : null,
                ];
            }

            if (!str_contains($data, '|')) {
                return null;
            }

            $parts = explode('|', $data);

            if (count($parts) !== 4) {
                return null;
            }

            [$basename, $expiry, $issuedAt, $timezone] = $parts;

            if (
                $basename === '' ||
                !ctype_digit($expiry) ||
                !ctype_digit($issuedAt) ||
                (int) $issuedAt <= 0
            ) {
                return null;
            }

            return [
                'file' => $basename,
                'iat'  => (int) $issuedAt,
                'exp'  => (int) $expiry,
                'tz'   => ($timezone !== '') ? $timezone : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Set Output header.
     *
     * @param string $basename The file name (e.g: image.png).
     * @param int|null $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * @param array<string,mixed> $headers An associative array for additional headers passed by reference.
     * 
     * @return array|bool Return the filename or false if failed.
     */
    private function parseFileHeaders(string $basename, ?int $expiry, array &$headers): array|bool
    {
        $expiry ??= 0;
        $filename = $this->parseBasename($basename);

        if (!is_file($filename)) {
            $filename = null;
            $data = self::decode($basename);

            if (
                $data === null
                || !isset($data['basename'])
                || !is_string($data['basename'])
            ) {
                return self::sendErrorHeaders(404);
            }

            $basename = $data['basename'];
            $filename = $this->parseBasename($basename);
            $expiry = $data['expires'];

            if (!is_file($filename)) {
                return self::sendErrorHeaders(404);
            }
        }
  
        clearstatcache(true, $filename);

        $filesize = (int) ($headers['Content-Length'] ?? @filesize($filename));

        if ($filesize <= 0) {
            return self::sendErrorHeaders(500);
        }

        $filemtime = null;

        if ($this->eTag || $expiry > 0) {
            $filemtime = (int) @filemtime($filename);
        }

        $this->hasEtag = isset($headers['ETag']) && $headers['ETag'] !== '';
        
        if ($this->eTag && !$this->hasEtag) {
            $headers['ETag'] = $this->getEtag(
                $filesize, 
                $filemtime, 
                $filename
            );
        }

        $headers['Content-Length'] = $filesize;
       
        // If 0 then no caching load fresh
        if ($expiry > 0) {
            if ($this->eTag) {
                $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');

                if ($ifNoneMatch === $headers['ETag']) {
                    return self::sendNotModifiedHeaders($expiry, $filemtime);
                }
            }

            if($filemtime !== false){
                if (
                    isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
                    strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $filemtime
                ) {
                    return self::sendNotModifiedHeaders($expiry, $filemtime);
                }

                $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $filemtime);
            }
        }

        $isImage = null;
        $type = $headers['Content-Type'] 
            ?? null;
        $this->extension = self::getExtension($filename);

        if($type === '' || $type === null || $type === false){
            $mime = new Mime($filename);

            $type = $mime->getType();
            $isImage = ($this->extension !== 'svg')
                && $mime->isImage();
        }

        if ($type === '' || $type === null) {
            return self::sendErrorHeaders(500);
        }

        $isImage ??= self::isImage($this->extension, $type);

        $this->isImage = $isImage;
        $headers['Content-Type'] = $type;

        return [$filename, $basename, $expiry, $filesize, $filemtime];
    }

    /**
     * Get the file extension type.
     *
     * @param string $filename The filename.
     * 
     * @return string Return file extension
     */
    private static function getExtension(string $filename): string 
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    /**
     * Check if file extension is an image.
     *
     * @param string $extension The file extension type.
     * @param string $type The mime type for fallback.
     * 
     * @return bool Return true if an image, otherwise false.
     */
    private static function isImage(string $extension, string $type): bool
    {
        if($extension === 'svg'){
            return false;
        }

        static $images = [
            'jpg'   => true,
            'jpeg'  => true,
            'png'   => true,
            'gif'   => true,
            'webp'  => true,
            'bmp'   => true,
            'avif'  => true,
            'tif'   => true,
            'tiff'  => true,
            'ico'   => true,
            'pjp'   => true,
            'jfif'  => true,
            'apng'  => true,
            'pjpeg' => true,
        ];

        return isset($images[$extension])
            || str_starts_with($type, 'image/');
    }

    /**
     * Generate eTag.
     *
     * @param int $filesize
     * @param int|null $filemtime
     * @param string|null $filename
     * 
     * @return string Return file eTag string.
     */
    private function getEtag(
        int $filesize,
        ?int $filemtime = null,
        ?string $filename = null
    ): string 
    {
        $identifier = ($filemtime !== null)
            ? base_convert((string) $filemtime, 10, 36)
            : base_convert((string) crc32(($filename ?? '') . ':' . $filesize), 10, 36);

        return ($this->weakEtag ? 'W/' : '')
            . '"' . $identifier . '-' . $filesize . '"';
    }

    /**
     * Send a no-cache error response.
     *
     * Removes existing response headers, applies no-cache directives, and sends
     * the specified HTTP status code. If headers have already been sent, clears
     * removable output buffers and returns false without changing the response.
     *
     * @param int $statusCode HTTP status code to send.
     * @param array<string,string|int|float|bool> $headers Additional response headers.
     *
     * @return false Always returns false.
     */
    private static function sendErrorHeaders(
        int $statusCode,
        array $headers = []
    ): bool 
    {
        if (headers_sent()) {
            Runtime::cleanOutputBuffers('all');
            return false;
        }

        header_remove();

        $headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $headers['Expires'] = '0';
        $headers['Pragma'] = 'no-cache';

        self::sendHeaders($headers, $statusCode);

        return false;
    }

    /**
     * Send headers for a not-modified response.
     *
     * Adds expiration and last-modified headers when caching is enabled,
     * then sends an HTTP 304 response.
     *
     * @param int $expiry Cache lifetime in seconds.
     * @param int $filemtime File modification timestamp.
     * @param array<string,mixed> $headers Additional response headers.
     *
     * @return true Always returns true.
     */
    private static function sendNotModifiedHeaders(
        int $expiry,
        int $filemtime,
        array $headers = []
    ): bool 
    {
        if ($expiry > 0) {
            $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', time() + $expiry);
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', $filemtime);
        }

        self::sendHeaders($headers, 304, $expiry > 0);

        return true;
    }

    /**
     * Send file response headers.
     *
     * Configures cache directives and inline content disposition before
     * sending an HTTP 200 response.
     *
     * @param array<string,mixed> $headers Additional response headers.
     * @param string $basename The file name presented to the client.
     * @param int $expiry Cache lifetime in seconds. Values greater than zero
     *                    enable public caching; zero disables caching.
     *
     * @return void
     */
    private static function sendFileHeaders(
        array $headers,
        string $basename,
        int $expiry
    ): void 
    {
        if (!isset($headers['Expires'])) {
            $headers['Expires'] = ($expiry > 0)
                ? gmdate('D, d M Y H:i:s \G\M\T', time() + $expiry)
                : '0';

            if ($expiry <= 0) {
                $headers['Pragma'] = 'no-cache';
            }
        }

        if (!isset($headers['Cache-Control'])) {
            $headers['Cache-Control'] = ($expiry > 0)
                ? "public, max-age={$expiry}, immutable"
                : 'no-cache, no-store, must-revalidate';
        }

        $headers['Content-Disposition'] = 'inline; filename="' . $basename . '"';

        self::sendHeaders($headers, 200, $expiry > 0);
    }

    /**
     * Send HTTP response headers and clear buffered output.
     *
     * Removes the Pragma header when caching is enabled, sends the headers
     * with the specified status code, and clears removable output buffers.
     *
     * @param array<string,mixed> $headers Headers to send.
     * @param int $status HTTP response status code.
     * @param bool $cache Whether caching is enabled.
     *
     * @return void
     */
    private static function sendHeaders(
        array $headers,
        int $status = 200,
        bool $cache = false
    ): void 
    {
        if ($cache) {
            unset($headers['Pragma']);
        }

        Header::send($headers, true, false, $status, false);

        if ($cache) {
            header_remove('Pragma');
        }

        Runtime::cleanOutputBuffers('all');
    }

    /**
     * @deprecated 3.8.8 Use resizeImage()->send() instead.
     *
     * Configure image resizing and send the image to the client.
     *
     * @param string $basename File name relative to the renderer's base path.
     * @param int $expiry Cache lifetime in seconds. Use 0 to disable caching.
     * @param array{width?:int,height?:int,ratio?:bool,quality?:int} $options Image resizing options.
     * @param array<string,mixed> $headers Additional response headers.
     *
     * @return bool Returns true if the image is sent successfully, otherwise false.
     *
     * @throws RuntimeException If NanoImage is unavailable or image processing fails.
     *
     * @see self::resizeImage()
     * @see self::send()
     */
    public function sendImage(
        string $basename,
        int $expiry = 0,
        array $options = [],
        array $headers = []
    ): bool 
    {
        return $this->resizeImage(
            $options['width'] ?? null,
            $options['height'] ?? null,
            $options['ratio'] ?? true,
            $options['quality'] ?? 100
        )->send($basename, $expiry, $headers);
    }

    /**
     * Send a file using a signed file token.
     *
     * @deprecated 3.8.8 Use send() with the signed token as the source.
     *
     * @param string $fileHash Encrypted temporal file token.
     * @param array<string,mixed> $headers Additional response headers.
     * @param int $length Maximum chunk size in bytes (default: 2 MB).
     * @param int $delay Delay between chunks, as accepted by `uwait()`.
     *
     * @return bool Returns true if the file is streamed or a cache response is handled successfully, otherwise false.
     *
     * @throws EncryptionException If token decryption fails.
     * @throws RuntimeException If file processing fails.
     *
     * @see self::sign()
     * @see self::send()
     */
    public function sendSigned(
        string $fileHash,
        array $headers = [],
        int $length = (1 << 21),
        int $delay = 0
    ): bool 
    {
        return $this->send(
            $fileHash,
            headers: $headers,
            length: $length,
            delay: $delay
        );
    }
}