<?php
/**
 * Luminova Framework HTTP File Downloader.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Throwable;
use \SplFileObject;
use \Luminova\Http\Header;
use \Luminova\Utility\MIME;
use \Luminova\Storage\Filesystem;
use \Luminova\Http\Message\Stream;
use \Luminova\Http\Message\Response;
use \Psr\Http\Message\StreamInterface;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Exceptions\RuntimeException;

/**
 * Downloader provides a unified interface for streaming, offloading, or
 * generating file download responses.
 *
 * Supported static proxy methods:
 *
 * @method static bool send(mixed $source, ?string $filename = null, array $headers = [], ?string $etag = null)
 *         Stream the resource directly to the client using PHP.
 *
 * @method static bool trySend(mixed $source, ?string $filename = null, array $headers = [], ?string $etag = null)
 *         Attempt to stream the resource, suppressing errors if the operation fails.
 *
 * @method static bool sendFile(mixed $source, ?string $filename = null, array $headers = [], ?string $etag = null)
 *         Send the file using X-Sendfile (Apache) if available, falling back to PHP streaming.
 *
 * @method static bool sendAccel(mixed $source, ?string $filename = null, array $headers = [], ?string $etag = null)
 *         Send the file using X-Accel-Redirect (Nginx) if available, falling back to PHP streaming.
 *
 * @method static ResponseInterface response(mixed $source, ?string $filename = null, array $headers = [], ?string $etag = null)
 *         Build and return a PSR-7 compatible response without sending output.
 *
 * @see self::__callStatic() Handles static proxy dispatch.
 */
class Downloader
{
    /**
     * Use native PHP streaming to deliver the download.
     */
    public final const X_DOWNLOAD = 1;

    /**
     * Use Apache X-Sendfile to offload file delivery.
     */
    public final const X_SENDFILE = 2;

    /**
     * Use Nginx X-Accel-Redirect to offload file delivery.
     */
    public final const X_ACCEL = 3;

    /**
     * Cached PSR-7 response instance.
     *
     * @var Response|null $response
     */
    private ?Response $response = null;

    /**
     * Active stream handler or resource used for reading content.
     *
     * @var resource|mixed|null $handler
     */
    private mixed $handler = null;

    /**
     * Resolved MIME type of the download.
     *
     * @var string $mime
     */
    private string $mime = 'application/octet-stream';

    /**
     * Download source type flag (file, stream, resource, contents).
     *
     * @var string $type
     */
    private string $type = '';

    /**
     * File or stream metadata (status, length offset, etc).
     *
     * @var array<string,mixed> $info
     */
    private array $info = [];

    /**
     * Prepared response headers for the download.
     *
     * @var array<string,string> $dHeaders
     */
    private array $dHeaders = [];

    /**
     * Total file size in bytes.
     *
     * @var int $size
     */
    private int $size = 0;

    /**
     * Last modified timestamp (Unix time).
     *
     * @var int $fileTime
     */
    private int $fileTime = 0;

    /**
     * Indicates response-only mode (no direct output).
     *
     * @var bool $isResponse
     */
    private bool $isResponse = false;

    /**
     * Whether the source is a valid resource/stream.
     *
     * @var bool $isResource
     */
    private bool $isResource = false;

    /**
     * Whether the current response is partial (HTTP 206).
     *
     * @var bool $isPartial
     */
    private bool $isPartial = false;

    /**
     * Real filesystem base path stripped when building X-Accel-Redirect URIs.
     * Set via {@see self::setAccelPaths()}.
     * 
     * @var string $accelRealBase
     */
    protected string $accelRealBase = '';

    /**
     * Nginx internal location prefix used for X-Accel-Redirect URIs.
     * Set via {@see self::setAccelPaths()}.
     * 
     * @var string $accelInternalPrefix
     */
    protected string $accelInternalPrefix = '/protected/';

    /**
     * Initialize a new Downloader instance.
     *
     * Accepts a file path, an open PHP resource, a PSR-7 StreamInterface, or
     * a raw string of file contents as the download source.
     *
     * @param StreamInterface|SplFileObject|resource|string $source  File path, open resource,
     *                                                  PSR-7 stream, or raw content.
     * @param string|null $filename Filename shown in the browser download dialog. 
     *                      Falls back to the basename of the file path, or `'file_download'`.
     * @param array<string,mixed> $headers  Additional HTTP headers merged into the response.
     * @param string|null $etag ETag string for cache validation.
     *                       Falls back to `$headers['ETag']` when omitted.
     *
     * @throws RuntimeException If the source is `null`, the file path does not
     *                          exist, or the path is not readable.
     *
     * @example - Downloading a file from a path:
     * ```php
     * use Luminova\Http\Downloader;
     *
     * $dl = new Downloader('/var/storage/report.pdf', 'Q3-Report.pdf');
     * $dl->download();
     * $dl->close();
     * ```
     *
     * @example - Downloading from a PSR-7 stream:
     * ```php
     * use Luminova\Http\Downloader;
     * use Luminova\Http\Message\Stream;
     *
     * $stream = Stream::from('/tmp/export.csv', 'rb');
     * $dl = new Downloader($stream, 'export.csv', ['Cache-Control' => 'no-store']);
     * $dl->download();
     * $dl->close();
     * ```
     *
     * @example - Serving raw string content as a download:
     * ```php
     * use Luminova\Http\Downloader;
     *
     * $csv = "id,name\n1,Alice\n2,Bob\n";
     * $dl  = new Downloader($csv, 'users.csv', ['Content-Type' => 'text/csv']);
     * $dl->download();
     * $dl->close();
     * ```
     */
    public function __construct(
        private mixed $source,
        private ?string $filename = null,
        private array $headers  = [],
        private ?string $etag = null
    ) {
        if ($this->source === null) {
            throw new RuntimeException('No download source provided');
        }

        $this->type = $this->getSourceType();
        $this->etag ??= ($this->headers['ETag'] ?? null);

        if($this->etag){
            $this->dHeaders['ETag'] = $this->etag;
        }
    }

    /**
     * Static proxy that creates a Downloader instance and immediately dispatches
     * the requested action.
     * 
     * @param string $method Proxy method name.
     * @param mixed[] $arguments Constructor arguments forwarded to `__construct`.
     *
     * @return mixed Return result of called method.
     * @throws RuntimeException For unrecognized method names.
     *
     * @example - Example:
     * ```php
     * use Luminova\Http\Downloader;
     *
     * // Stream file directly to the browser in one call
     * Downloader::send('/var/storage/archive.zip', 'archive.zip');
     *
     * // Obtain a PSR-7 Response without streaming
     * $response = Downloader::response('/var/storage/archive.zip', 'archive.zip');
     * ```
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $instance = new static(...$arguments);

        try{
            return match ($method) {
                'send'      => $instance->download(),
                'trySend'   => $instance->tryDownload(),
                'sendFile'  => $instance->download(self::X_SENDFILE),
                'sendAccel' => $instance->download(self::X_ACCEL),
                'response'  => $instance->getResponse(),
                default => throw new RuntimeException(
                    sprintf("Method '%s' does not exist on %s", $method, static::class)
                ),
            };
        } finally {
            $instance->close();
        }
    }

    /**
     * Configure the real-to-internal path mapping used when serving files via
     * Nginx's `X-Accel-Redirect`.
     *
     * Nginx requires the response header to carry an **internal** URI that
     * matches a location block marked with `internal;`, not the real filesystem
     * path. This method tells the Downloader how to translate one to the other.
     *
     * ```nginx
     * # Example nginx.conf snippet
     * location /protected/ {
     *     internal;
     *     alias /var/www/example.com/writeable/storages/;
     * }
     * ```
     *
     * @param string $realBasePath Absolute filesystem path that should be stripped from the source path.
     *                                 (e.g. `'/var/www/example.com/writeable/storages'`)
     * @param string $internalPrefix  Nginx internal location prefix that replaces the stripped portion.
     *                                 (e.g. `'/protected/'`)
     *
     * @return static Returns downloader instance.
     * @see static::getAccelInternalPath()
     *
     * @example - Example:
     * ```php
     * use Luminova\Http\Downloader;
     *
     * // Real path:     /var/www/example.com/writeable/storages/videos/tour.mp4
     * // Nginx alias:   location /protected/ { internal; alias /var/www/example.com/writeable/storages/; }
     * // Resulting header:  X-Accel-Redirect: /protected/videos/tour.mp4
     *
     * $dl = new Downloader('/var/www/example.com/writeable/storages/videos/tour.mp4', 'tour.mp4');
     * $dl->setAccelPaths('/var/www/example.com/writeable/storages', '/protected/');
     * $dl->download(Downloader::X_ACCEL);
     * $dl->close();
     * ```
     */
    public function setAccelPaths(string $realBasePath, string $internalPrefix = '/protected/'): self
    {
        $this->accelRealBase = rtrim(realpath($realBasePath) ?: $realBasePath, DIRECTORY_SEPARATOR);
        $this->accelInternalPrefix = '/' . trim($internalPrefix, '/') . '/';

        return $this;
    }

    /**
     * Check whether the resolved source type matches the given type string.
     *
     * Possible type values: `'file'`, `'resource'`, `'stream'`, `'contents'`.
     *
     * @param string $type Type string to test against.
     * 
     * @return bool Return true if type matches `$source`, otherwise false.
     *
     * @example - Example:
     * ```php
     * $dl = new Downloader('/path/to/file.zip');
     *
     * if ($dl->is('file')) {
     *     echo 'Source is a filesystem file.';
     * }
     * ```
     */
    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Returns `true` when the last download served a partial (HTTP 206) response.
     *
     * @return bool Return true if download is partial, otherwise false.
     */
    public function isPartial(): bool
    {
        return $this->isPartial;
    }

    /**
     * Resolve the source type, optionally re-detecting it when not yet set.
     *
     * @return string|false The type string, or `false` when detection fails.
     */
    public function getType(): string|bool
    {
        if ($this->type) {
            return $this->type;
        }

        $type = $this->getSourceType(false);

        return $type ? ($this->type = $type) : false;
    }

    /**
     * Return metadata set during the last `download()` or `getResponse()` call.
     *
     * Keys: `offset` (int), `length` (int), `status` (int HTTP status code).
     *
     * @return array<string,int> Return associative array of download information.
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Return the filename that will be (or was) sent to the client.
     *
     * @return string|null Return download file name or null if not available.
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * Return the resolved MIME type of the download source.
     *
     * @return string|null Return download MIME type.
     */
    public function getMime(): ?string
    {
        return $this->mime;
    }

    /**
     * Return the ETag string used for cache validation, or `null` if none.
     *
     * @return string|null Return download ETag value or null if not available.
     */
    public function getETag(): ?string
    {
        return $this->etag;
    }

    /**
     * Return the resolved content length in bytes.
     *
     * @return int Return download content size or file size in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Return the last-modified Unix timestamp of the source, or `0` when
     * unavailable.
     *
     * @return int Return download file ast-modified Unix timestamp.
     */
    public function getFileTime(): int
    {
        return $this->fileTime;
    }

    /**
     * Return the original download source as supplied to the constructor.
     *
     * @return mixed Return the download source.
     */
    public function getSource(): mixed
    {
        return $this->source;
    }

    /**
     * Return the current download response headers.
     *
     * @return array<string,mixed> Return an associative array of download prepared/sent headers.
     */
    public function getHeaders(): array
    {
        return $this->dHeaders;
    }

    /**
     * Close all open handles and release internal state.
     *
     * Call this after streaming is complete, or after obtaining and sending a
     * PSR-7 response. The instance should not be reused after `close()`.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * $dl = new Downloader('/var/storage/export.zip', 'export.zip');
     * $dl->download();
     * $dl->close(); // Always close to release file handles
     * ```
     */
    public function close(): void
    {
        if (is_resource($this->source)) {
            fclose($this->source);
        } elseif ($this->source instanceof StreamInterface) {
            $this->source->close();
        }

        if ($this->handler !== null && $this->handler !== $this->source) {
            if (is_resource($this->handler)) {
                fclose($this->handler);
            } elseif ($this->handler instanceof StreamInterface) {
                $this->handler->close();
            }
        }

        $this->source    = null;
        $this->handler   = null;
        $this->response  = null;
        $this->isResponse = false;
    }

    /**
     * Delete the source file from disk.
     *
     * Only applicable when the source type is `'file'`. Silently returns
     * `false` for other source types.
     *
     * @return bool `true` on success, `false` when the source is not a file or
     *              the unlink fails.
     *
     * @example - Example:
     * ```php
     * $dl = new Downloader('/tmp/generated-report.pdf', 'report.pdf');
     * $dl->download();
     *
     * // Remove the temp file once streaming is complete
     * $dl->deleteSourceFile();
     * $dl->close();
     * ```
     */
    public function deleteSourceFile(): bool
    {
        if ($this->is('file') && is_string($this->source)) {
            return @unlink($this->source);
        }

        return false;
    }

    /**
     * Build and return a PSR-7 Response without streaming to the client.
     *
     * Use this when your framework's dispatch layer is responsible for sending
     * the response (e.g. middleware pipelines, etc.).
     *
     * The returned `Response` wraps the source in a `StreamInterface` body.
     * Call `$downloader->close()` after the body has been sent.
     *
     * @return ResponseInterface PSR-7 response ready to be dispatched.
     * @throws RuntimeException  If no valid source is set.
     *
     * @see self::download() For direct streaming to the client.
     *
     * @example - PSR-7 middleware response:
     * ```php
     * use Luminova\Http\Downloader;
     * use Luminova\Http\Header;
     *
     * $dl = new Downloader('/var/storage/archive.zip', 'archive.zip');
     * $response = $dl->getResponse();
     *
     * // 1. Send HTTP status + headers
     * Header::send($response->getHeaders(), status: $response->getStatusCode());
     *
     * // 2. Flush any output buffers before streaming
     * Header::clearOutputBuffers();
     *
     * // 3. Stream the body
     * $response->getBody()->send();
     *
     * // 4. Inspect range info if needed: ['offset', 'length', 'status']
     * $info = $dl->getInfo();
     *
     * $dl->close();
     * ```
     *
     * @example - One-liner via static proxy:
     * ```php
     * $response = Downloader::response('/var/storage/archive.zip', 'archive.zip');
     * ```
     */
    public function getResponse(): ResponseInterface
    {
        if (!$this->source) {
            throw new RuntimeException('No valid download source found.');
        }

        if ($this->response instanceof Response) {
            return $this->response;
        }

        $this->createResponse();

        $this->isResponse = true;
        $options = $this->getOptions($this->handler);

        unset($this->dHeaders['X-Accel-Redirect'], $this->dHeaders['X-Sendfile']);

        return $this->response = new Response(
            $this->handler,
            $options[3] ?? 200,
            $this->dHeaders,
            info: $this->info
        );
    }

    /**
     * Stream the download directly to the client.
     *
     * Sends all necessary HTTP headers (including `Content-Disposition`,
     * `Content-Length`, range headers for partial content, and caching
     * headers), then streams the source content in chunks.
     *
     * When `$mode` is `X_SENDFILE` or `X_ACCEL`, the method emits only the
     * appropriate offload header and lets the web server handle the transfer;
     * this is only effective when the source is a `'file'`.
     *
     * @param int $mode Transfer strategy. One of:
     *                       - `Downloader::X_DOWNLOAD` – PHP streams the content (default).
     *                       - `Downloader::X_SENDFILE` – Apache/Lighttpd `X-Sendfile`.
     *                       - `Downloader::X_ACCEL`    – Nginx `X-Accel-Redirect`.
     * @param int $chunkSize Bytes read per iteration when streaming (default `8192`).
     * @param int $delay Microseconds to sleep between chunks, `0` disables throttling (default `0`).
     *
     * @return bool Returns `true` on success, `false` if the source yielded zero bytes
     *              or an offload header could not be set.
     * @throws RuntimeException When the source is missing or unreadable.
     *
     * @see self::getResponse() For PSR-7 response without direct streaming.
     * @see self::tryDownload() For an exception-safe variant.
     *
     * @example - Basic file download:
     * ```php
     * use Luminova\Http\Downloader;
     *
     * $dl = new Downloader('/var/storage/report.pdf', 'Q3-Report.pdf');
     * $dl->download();
     * $dl->deleteSourceFile(); // optionally remove temp file
     * $dl->close();
     * ```
     *
     * @example - Throttled streaming (e.g. rate-limiting large files):
     * ```php
     * // Stream in 16 KB chunks with a 5 ms pause between each
     * $dl = new Downloader('/var/storage/video.mp4', 'video.mp4');
     * $dl->download(Downloader::X_DOWNLOAD, 16384, 5000);
     * $dl->close();
     * ```
     *
     * @example - Nginx X-Accel-Redirect offload:
     * ```php
     * $dl = new Downloader('/var/www/storage/videos/tour.mp4', 'tour.mp4');
     * $dl->setAccelPaths('/var/www/storage', '/protected/');
     * $dl->download(Downloader::X_ACCEL);
     * $dl->close();
     * ```
     *
     * @example - Apache X-Sendfile offload:
     * ```php
     * $dl = new Downloader('/var/storage/large-file.iso', 'large-file.iso');
     * $dl->download(Downloader::X_SENDFILE);
     * $dl->close();
     * ```
     */
    public function download(
        int $mode = self::X_DOWNLOAD,
        int $chunkSize = 8192,
        int $delay = 0,
    ): bool 
    {
        if (!$this->source) {
            throw new RuntimeException('No valid download source found.');
        }

        $isSendFile = ($mode === self::X_SENDFILE || $mode === self::X_ACCEL);

        if (!$this->createDownloadable($isSendFile)) {
            return false;
        }

        $this->isResponse = false;
        $options = $this->getOptions($this->source);

        if ($isSendFile && $this->is('file')) {
            return $this->sendFile($mode, $options[3] ?? 200);
        }

        return $this->sendContents($chunkSize, $delay, $options);
    }

    /**
     * Exception-safe wrapper around {@see self::download()}.
     *
     * Catches any `Throwable` thrown during streaming and returns `false`
     * instead of propagating the exception. Useful in fire-and-forget contexts
     * where you prefer a boolean result over exception handling.
     *
     * Accepts the same parameters as {@see self::download()}.
     *
     * @param int $mode Transfer strategy (`X_DOWNLOAD`, `X_SENDFILE`, `X_ACCEL`).
     * @param int $chunkSize Bytes per streaming chunk.
     * @param int $delay Microseconds between chunks.
     *
     * @return bool Returns `true` on success, `false` on failure or exception.
     *
     * @example - Example:
     * ```php
     * use \Luminova\Http\Downloader;
     *
     * $dl = new Downloader('/var/storage/export.csv', 'export.csv');
     *
     * if (!$dl->tryDownload()) {
     *     // Log or show an error — no exception to catch
     *     error_log('Download failed silently');
     * }
     *
     * $dl->close();
     * ```
     */
    public function tryDownload(
        int $mode = self::X_DOWNLOAD,
        int $chunkSize = 8192,
        int $delay = 0,
    ): bool 
    {
        try {
            return $this->download($mode, $chunkSize, $delay);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Prepare the internal handler and content-length for a direct-stream
     * download.
     *
     * @param bool $isSendFile `true` when an offload header (X-Sendfile /
     *                         X-Accel-Redirect) will be used instead of PHP
     *                         streaming; skips opening a file resource.
     *
     * @return bool Return `false` when the resolved content length is zero.
     */
    private function createDownloadable(bool $isSendFile): bool
    {
        if (!$this->handler || $this->isResponse) {
            $this->isResource = false;
            $this->handler    = null;
            $this->size       = 0;

            if ($this->is('stream')) {
                $this->isResource = true;
                $this->size = (int) $this->source->getSize();
                $this->handler  = $this->source->detach();

                if ($this->size === 0 && $this->handler) {
                    $this->size = Filesystem::size($this->handler);
                }
            } elseif ($this->is('resource')) {
                $this->isResource = true;
                $this->size = Filesystem::size($this->source);
            } elseif ($this->is('contents')) {
                $this->size = strlen($this->source);
            } elseif ($this->is('fileObject')) {
                $this->isResource = true;
                try{
                    $this->size = (int) $this->source->getSize();
                } catch(Throwable){
                    $this->size = 0;
                }
            } elseif ($this->is('file')) {
                if (!$isSendFile) {
                    $this->isResource = true;
                    $this->handler = fopen($this->source, 'rb');

                    if (!$this->handler) {
                        $this->handler = null;
                        return false;
                    }

                    $this->size = Filesystem::size($this->handler);
                }

                if ($this->size === 0) {
                    $this->size = (int) @filesize($this->source);
                }

                $this->filename ??= (basename($this->source) ?: null);
                $this->mime ??= MIME::guess($this->source);
            }
        }

        $this->filename ??= 'file_download';

        return $this->size > 0;
    }

    /**
     * Prepare the internal `StreamInterface` handler for a PSR-7 response.
     * 
     * @return void
     */
    private function createResponse(): void
    {
        if (!$this->handler || !$this->isResponse) {
            $this->size = 0;

            if ($this->is('stream')) {
                $this->handler = $this->source;
                $this->size  = (int) $this->source->getSize();
            } elseif ($this->is('resource')) {
                $this->handler = new Stream($this->source);
                $this->size    = (int) $this->handler->getSize();
            } elseif ($this->is('contents')) {
                $this->handler = Stream::fromString($this->source, 'rb+');
                $this->size  = strlen($this->source);
            } elseif ($this->is('file')) {
                $this->handler  = Stream::from($this->source, 'rb+');
                $this->filename ??= (basename($this->source) ?: null);
                $this->size = (int) $this->handler->getSize();
                $this->mime ??= MIME::guess($this->source);
            } elseif ($this->is('fileObject')) {
                $this->handler = Stream::fromString((string) $this->source, 'rb+');

                try{
                    $this->size  = (int) $this->source->getSize();
                }catch(Throwable){
                    $this->size  = 0;
                }
            }

            if ($this->handler instanceof Stream) {
                $this->handler->setReadOnly(true, true);
            }
        }

        $this->filename ??= 'file_download';
    }

    /**
     * Detect the type of the download source.
     *
     * @param bool $assert When `true`, throws `RuntimeException` on unresolvable
     *                     sources. When `false`, returns `false` instead.
     *
     * @return string|false One of `'stream'`, `'resource'`, `'file'`,
     *                      `'contents'`, or `false`.
     *
     * @throws RuntimeException On invalid type (when `$assert` is `true`).
     */
    private function getSourceType(bool $assert = true): string|bool
    {
        if ($this->source instanceof StreamInterface) {
            return 'stream';
        }

        if ($this->source instanceof SplFileObject) {
            return 'fileObject';
        }

        if (is_resource($this->source)) {
            return 'resource';
        }

        if (!is_string($this->source)) {
            if (!$assert) {
                return false;
            }

            throw new RuntimeException(sprintf(
                'Invalid download source. Expected file path, resource, stream, or string; got %s.',
                is_object($this->source) ? get_class($this->source) : gettype($this->source)
            ));
        }

        if (is_file($this->source)) {
            if (!is_readable($this->source)) {
                if (!$assert) {
                    return false;
                }

                throw new RuntimeException("Download file: '{$this->source}' is not readable.");
            }

            return 'file';
        }

        if (Filesystem::isLikelyFile($this->source, forFile: true)) {
            if (!$assert) {
                return false;
            }

            throw new RuntimeException("Download file: '{$this->source}' does not exist.");
        }

        return 'contents';
    }

    /**
     * Compute the last-modified timestamp and ETag, then delegate to
     * `parseOptions()` to build the full set of response headers.
     *
     * Populates `$this->info` and `$this->isPartial`.
     *
     * @param mixed $source The active source handle (resource, Stream, or path).
     * @return array<int,mixed> Return array list of download options: `[isPartial, offset, length, status]`.
     */
    protected function getOptions(mixed $source): array
    {
        $this->fileTime = Filesystem::getLastModified($source);
        $this->etag ??= $this->generateETag($this->fileTime);

        $options = $this->parseOptions();
        [$isPartial, $offset, $length, $status] = $options;

        $this->isPartial = $isPartial;
        $this->info      = [
            'offset' => $offset,
            'length' => $length,
            'status' => $status,
        ];

        return $options;
    }

    /**
     * Emit an `X-Sendfile` or `X-Accel-Redirect` offload header and send
     * the response status + remaining headers via `Header::send()`.
     *
     * The `Content-Length` and `Content-Range` headers are intentionally
     * omitted because the web server computes them internally.
     *
     * @param int $mode `X_SENDFILE` or `X_ACCEL`.
     * @param int $status HTTP status code.
     *
     * @return bool Return `true` on success, `false` for an unrecognized mode.
     */
    protected function sendFile(int $mode, int $status): bool
    {
        if ($status === 304 || ($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD') {
            return true;
        }

        if ($mode === self::X_ACCEL) {
            $this->dHeaders['X-Accel-Redirect'] = $this->getAccelInternalPath($this->source);
        } elseif ($mode === self::X_SENDFILE) {
            $this->dHeaders['X-Sendfile'] = $this->source;
        } else {
            return false;
        }

        $headers = $this->dHeaders;
        unset($headers['Content-Length'], $headers['Content-Range']);

        Header::send($headers, false, status: $status);

        return true;
    }

    /**
     * Flush headers to the client and stream content in chunks.
     *
     * Clears all output buffers before writing so that buffered content from
     * earlier in the request does not corrupt the download.
     *
     * @param int $chunkSize Bytes per read iteration.
     * @param int $delay Microseconds between chunks (0 = no throttle).
     * @param array<int,mixed> $options   Result of `parseOptions()`.
     *
     * @return bool Return `true` after streaming, `false` when there is nothing to send.
     */
    protected function sendContents(int $chunkSize, int $delay, array $options): bool
    {
        [$isPartial, $offset, $length, $status] = $options;

        unset($this->dHeaders['X-Accel-Redirect'], $this->dHeaders['X-Sendfile']);

        Header::send($this->dHeaders, false, status: $status);

        if ($status === 304) {
            return true;
        }

        if ($length === 0) {
            return false;
        }

        $delay = max(0, $delay);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!$this->isResource) {
            $this->readContents($this->source, $offset, $length, $chunkSize, $delay);
            return true;
        }

        if($this->is('fileObject')){
            $this->readFileObject(
                $this->source,
                $length,
                $chunkSize,
                $delay,
                $offset,
                $isPartial
            );
            return true;
        }

        $this->readStream(
            $this->handler ?? $this->source,
            $length,
            $chunkSize,
            $delay,
            $offset,
            $isPartial
        );

        return true;
    }

    /**
     * Translate a real filesystem path to an Nginx internal URI for use with
     * `X-Accel-Redirect`.
     *
     * The translation works as follows:
     *
     * 1. Resolve the real absolute path via `realpath()`.
     * 2. If `$accelRealBase` is configured and the real path begins with it,
     *    strip that prefix and prepend `$accelInternalPrefix`.
     * 3. Otherwise fall back to `$accelInternalPrefix . basename($path)` so
     *    that a minimal working URI is always returned.
     *
     * Configure the base paths with {@see self::setAccelPaths()} before calling
     * `download(Downloader::X_ACCEL)`.
     *
     * ```
     * accelRealBase     = '/var/www/storage'
     * accelInternalPrefix = '/protected/'
     *
     * /var/www/storage/videos/tour.mp4  →  /protected/videos/tour.mp4
     * /var/www/storage/docs/report.pdf  →  /protected/docs/report.pdf
     * /tmp/outside.zip                  →  /protected/outside.zip  (fallback)
     * ```
     *
     * @param string $path Absolute filesystem path to the source file.
     * 
     * @return string Nginx-internal URI to emit as `X-Accel-Redirect`.
     */
    protected function getAccelInternalPath(string $path): string
    {
        $real   = realpath($path) ?: $path;
        $prefix = rtrim($this->accelInternalPrefix, '/');

        if (
            $this->accelRealBase !== ''
            && str_starts_with($real, $this->accelRealBase)
        ) {
            $relative = ltrim(
                substr($real, strlen($this->accelRealBase)),
                DIRECTORY_SEPARATOR
            );

            return $prefix . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        return $prefix . '/' . basename($real);
    }

    /**
     * Stream data from a PHP resource handle.
     *
     * For full-file (non-partial) requests with no offset, `fpassthru()` is
     * used for maximum throughput. Partial or offset-based requests use a
     * chunked read loop that respects `$chunkSize`, `$delay`, and
     * connection-abort detection.
     *
     * @param SplFileObject|resource $handler   Open, readable PHP stream resource.
     * @param int $length Total bytes to send.
     * @param int $chunkSize Bytes per `fread()` call.
     * @param int $delay Microseconds to sleep between chunks.
     * @param int $offset Byte position to seek to before reading.
     * @param bool $isPartial `true` when serving a `206 Partial Content`.
     *
     * @return void
     */
    protected function readStream(
        mixed $handler,
        int $length,
        int $chunkSize,
        int $delay,
        int $offset,
        bool $isPartial = false
    ): void 
    {
        if ($offset > 0) {
            $meta = stream_get_meta_data($handler);
            if ($meta['seekable'] ?? false) {
                fseek($handler, $offset);
            }
        }else{
            rewind($handler);
        }

        if (!$isPartial && $offset === 0) {
            fpassthru($handler);
            return;
        }

        while ($length > 0 && !feof($handler)) {
            $chunk = fread($handler, min($chunkSize, $length));

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $length -= strlen($chunk);

            flush();

            if (connection_aborted()) {
                break;
            }

            if ($delay > 0) {
                usleep($delay);
            }
        }
    }

    /**
     * Stream data from a resource or SplFileObject.
     *
     * @param SplFileObject $handler Open resource or file object.
     * @param int $length Total bytes to read.
     * @param int $chunkSize Size of each read chunk.
     * @param int $delay Microseconds delay between chunks.
     * @param int $offset Starting offset.
     * @param bool $isPartial Whether this is a partial request.
     * 
     * @return void
     */
    protected function readFileObject(
        SplFileObject $file,
        int $length,
        int $chunkSize,
        int $delay,
        int $offset = 0,
        bool $isPartial = false
    ): void 
    {
        try{
            if ($offset > 0) {
                $file->seek($offset);
            } else {
                $file->rewind();
            }
        }catch(Throwable){}

        if (!$isPartial && $offset === 0) {
            $file->fpassthru();
            return;
        }

        while ($length > 0 && !$file->eof()) {
            $chunk = $file->fread(min($chunkSize, $length));

            if ($chunk === '') {
                break;
            }

            echo $chunk;
            $length -= strlen($chunk);

            flush();

            if (connection_aborted()) {
                break;
            }

            if ($delay > 0) {
                usleep($delay);
            }
        }
    }

    /**
     * Stream a slice of a raw string to the client in chunks.
     *
     * @param string $data The full raw string content.
     * @param int $offset Start position within `$data`.
     * @param int $length  Number of bytes to send from `$offset`.
     * @param int $chunkSize Maximum bytes per `echo` call.
     * @param int $delay Microseconds to sleep between chunks.
     *
     * @return void
     */
    protected function readContents(
        string $data,
        int $offset,
        int $length,
        int $chunkSize,
        int $delay
    ): void 
    {
        $end = $offset + $length;

        while ($offset < $end) {
            $read = min($chunkSize, $end - $offset);
            echo substr($data, $offset, $read);
            $offset += $read;

            flush();

            if (connection_aborted()) {
                break;
            }

            if ($delay > 0) {
                usleep($delay);
            }
        }
    }

    /**
     * Generate a quoted ETag string derived from file size, last-modified time,
     * and filename.
     *
     * Format: `"<hex size>-<hex mtime>-<8-char sha1 of filename>"`
     *
     * @param int|mixed $source A Unix timestamp (int) or a source handle whose
     *                          last-modified time will be looked up.
     *
     * @return string Quoted ETag, e.g. `'"1a3f-5e0d4c2b-3f8a1c9e"'`.
     */
    protected function generateETag(mixed $source): string
    {
        $mtime = is_int($source) ? $source : Filesystem::getLastModified($source);

        return '"'
            . dechex($this->size)
            . '-' . dechex($mtime)
            . '-' . substr(sha1((string) $this->filename), 0, 8)
            . '"';
    }

    /**
     * Merge the standard download headers into `$this->dHeaders`.
     *
     * User-supplied headers passed to the constructor take **priority** over
     * the defaults computed here. Headers are merged in "defaults first, user
     * second" order so that any key already present in `$this->headers` wins.
     *
     * Generated defaults:
     * - `Content-Type`        – resolved MIME type (fallback: `application/octet-stream`).
     * - `Accept-Ranges`       – `bytes`.
     * - `Content-Length`      – resolved file size in bytes.
     * - `Cache-Control`       – `public, max-age=0, must-revalidate`.
     * - `Content-Disposition` – `attachment` with RFC 5987 UTF-8 filename encoding.
     *
     * @return void
     */
    protected function setDownloadHeaders(): void
    {
        $defaults = [
            'Content-Type'        => $this->mime ?: 'application/octet-stream',
            'Accept-Ranges'       => 'bytes',
            'Content-Length'      => $this->size,
            'Cache-Control'       => 'public, max-age=0, must-revalidate',
            'Content-Disposition' => sprintf(
                "attachment; filename=\"%s\"; filename*=UTF-8''%s",
                addslashes($this->filename),
                rawurlencode($this->filename)
            ),
        ];

        $this->dHeaders = array_merge($defaults, $this->headers);
    }

    /**
     * Merge `Content-Range` and `Accept-Ranges` headers into `$this->dHeaders`
     * for partial content (206) or unsatisfiable range (416) responses.
     *
     * Unlike a full replacement, this preserves any user-supplied headers that
     * are already present.
     *
     * @param string $range Formatted `Content-Range` value,
     *                      e.g. `'bytes 0-499/1234'` or `'bytes x/1234'`.
     *
     * @return void
     */
    protected function setRangeHeaders(string $range): void
    {
        $this->dHeaders = array_merge($this->headers, [
            'Content-Range' => $range,
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Evaluate conditional request headers, determine the HTTP range (if any),
     * and assemble the full set of response headers.
     *
     * Processing order:
     * 1. Attach `ETag` and `Last-Modified` to the response headers.
     * 2. Honour `If-None-Match` → 304 Not Modified.
     * 3. Honour `If-Modified-Since` → 304 Not Modified.
     * 4. Validate `If-Range` (ETag or date) before honouring `Range`.
     * 5. Parse the `Range` header and return partial-content metadata.
     *
     * @return array<int,mixed> Four-element tuple `[isPartial, offset, length, status]`:
     *   - `isPartial` (`bool`)  – `true` for HTTP 206, `false` otherwise.
     *   - `offset`    (`int`)   – Byte offset from which to start reading.
     *   - `length`    (`int`)   – Number of bytes to send (0 = nothing / error).
     *   - `status`    (`int`)   – HTTP status code (200, 206, 304, or 416).
     */
    protected function parseOptions(): array
    {
        if ($this->etag) {
            $this->dHeaders['ETag'] = $this->etag;
        }

        if ($this->fileTime > 0) {
            $this->dHeaders['Last-Modified'] = gmdate('D, d M Y H:i:s', $this->fileTime) . ' GMT';
        }

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $ifModified  = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

        // ETag cache hit → 304 Not Modified
        if ($this->etag && $ifNoneMatch) {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (trim($candidate) === $this->etag) {
                    $this->setDownloadHeaders();
                    return [false, 0, 0, 304];
                }
            }
        }

        // Date-based cache hit → 304 Not Modified
        if ($ifModified && $this->fileTime > 0 && strtotime($ifModified) >= $this->fileTime) {
            $this->setDownloadHeaders();
            return [false, 0, 0, 304];
        }

        $range    = $_SERVER['HTTP_RANGE']    ?? null;
        $ifRange  = $_SERVER['HTTP_IF_RANGE'] ?? null;
        $useRange = ($range !== null);

        // Validate If-Range: only honour Range if the resource hasn't changed
        if ($useRange && $ifRange) {
            $valid = false;

            if ($this->etag && trim($ifRange) === $this->etag) {
                $valid = true;
            } else {
                $time = strtotime($ifRange);
                if ($time !== false && $this->fileTime > 0 && $time >= $this->fileTime) {
                    $valid = true;
                }
            }

            if (!$valid) {
                $useRange = false;
            }
        }

        // Full-file download
        if (!$useRange) {
            $this->setDownloadHeaders();
            return [false, 0, $this->size, 200];
        }

        // Partial-content download
        [$ok, $offset, $length, $header] = $this->range($range);

        $this->setRangeHeaders($ok ? $header : "bytes */{$this->size}");

        if (!$ok) {
            return [false, 0, 0, 416];
        }

        $this->headers['Content-Length'] = $length;

        return [true, $offset, $length, 206];
    }

    /**
     * Parse the `Range` request header and return byte range details.
     *
     * Supports the three RFC 7233 single-range formats:
     *
     * | Format       | Meaning                                 |
     * |--------------|-----------------------------------------|
     * | `bytes=N-M`  | Bytes from N to M (inclusive).          |
     * | `bytes=N-`   | Bytes from N to the end of the file.    |
     * | `bytes=-N`   | The last N bytes of the file.           |
     *
     * Multi-range requests (`bytes=0-499,600-999`) are explicitly rejected;
     * the caller should respond with a 416 in that case.
     *
     * @param string|null $range Raw `Range` header value.
     *
     * @return array<int,mixed> Four-element tuple `[ok, offset, length, header]`:
     *   - `ok`      (`bool`)        – `false` when the range is invalid or out of bounds.
     *   - `offset`  (`int`)         – Byte offset from which to start reading.
     *   - `length`  (`int`)         – Number of bytes in the range.
     *   - `header`  (`string|null`) – Formatted `Content-Range` value, e.g.
     *                                 `'bytes 0-499/1234'`, or `null` on failure.
     */
    protected function range(?string $range): array
    {
        // Reject missing or multi-range values
        if (!$range || str_contains($range, ',')) {
            return [false, 0, 0, null];
        }

        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
            return [false, 0, 0, null];
        }

        $size = $this->size;
        [, $start, $end] = $m;

        if ($start === '' && $end !== '') {
            // bytes=-500  →  last 500 bytes
            $length = min((int) $end, $size);
            $offset = $size - $length;
            $limit  = $size - 1;
        } elseif ($start !== '' && $end === '') {
            // bytes=500-  →  from byte 500 to EOF
            $offset = (int) $start;
            $limit  = $size - 1;
            $length = $limit - $offset + 1;
        } else {
            // bytes=500-999  →  explicit range
            $offset = (int) $start;
            $limit  = (int) $end;
            $length = $limit - $offset + 1;
        }

        if ($offset >= $size || $offset > $limit) {
            return [false, 0, 0, null];
        }

        $limit  = min($limit, $size - 1);
        $length = $limit - $offset + 1;

        return [
            true,
            $offset,
            $length,
            "bytes {$offset}-{$limit}/{$size}",
        ];
    }
}