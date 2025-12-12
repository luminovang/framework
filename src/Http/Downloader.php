<?php 
/**
 * Luminova Framework File Downloader.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Luminova\Http\Header;
use \Luminova\Utility\MIME;
use \Luminova\Storage\Filesystem;
use \Luminova\Http\Message\Stream;
use \Luminova\Http\Message\Response;
use \Psr\Http\Message\StreamInterface;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Exceptions\RuntimeException;

final class Downloader
{
    /**
     * Create a downloadable response.
     *
     * Streams a file, resource, or raw string as an HTTP attachment.
     * Supports large-file streaming, HTTP range requests.
     *
     * Behavior:
     * - Detects source type (file, resource, string).
     * - Sends download headers and handles partial content (206).
     * - Streams content efficiently to the client.
     *
     * @param string|resource $source File path, open resource, or raw string.
     * @param string|null $filename Download filename shown to the client.
     * @param array<string,mixed> $headers Additional HTTP headers.
     * @param string|null $etag Optional ETag for caching.
     *
     * @return Response<ResponseInterface> Returns PSR-7 response object.
     * @throws RuntimeException When file source is missing or unreadable.
     * @see self::download() for direct streaming to client.
     * 
     * @example - Example:
     * ```php
     * use \Luminova\Http\Downloader;
     * 
     * // Download a file from path
     * $response = Downloader::response('/path/to/file.zip', 'download.zip');
     * 
     * // Send headers to browser
     * Header::send($response->getHeaders(), status $response->getStatusCode());
     * 
     * // Clear output buffers
     * Header::clearOutputBuffers();
     * 
     * // Use info for partial content download
     * $info = $response->getInfo(); // ['is_partial', 'offset', 'length', 'limit']
     * 
     * // Stream the body
     * echo $response->getBody();
     * ```
     */
    public static function response(
        mixed $source,
        ?string $filename = null,
        array $headers = [],
        ?string $etag = null
    ): ResponseInterface 
    {
        if ($source === null) {
            throw new RuntimeException("No source provided");
        }

        $etag ??= $headers['ETag'] ?? null;
        $mime = 'application/octet-stream';
        $size = 0;

        if ($source instanceof StreamInterface) {
            $stream = $source; 
            $size = $stream->getSize() ?? 0; 
        } elseif (is_resource($source)) {
            $stream = new Stream($source);
            $size = Filesystem::size($source);
        } elseif (is_string($source)) {
             if (is_file($source)) {
                if (!is_readable($source)) {
                    throw new RuntimeException("Download file not readable: $source");
                }

                $stream = new Stream(fopen($source, 'rb'));
                $filename ??= basename($source);
                $size = filesize($source);
                $mime = MIME::guess($source);
                $etag ??= self::generateETag($source, $filename);
            } elseif (Filesystem::isLikelyFile($source, true)) {
                throw new RuntimeException("Download file does not exist: {$source}");
            }else{
                $stream = Stream::fromString($source, 'rb');
                $size = strlen($source);
            }
        } else {
            throw new RuntimeException("Invalid download source type");
        }

        $filename ??= 'file_download';

        if ($etag !== null) {
            $headers['ETag'] = $etag;
        }

        [$isPartial, $offset, $length, $limit, $status, $newHeaders] = self::parseHeaders(
            $filename, 
            $mime, 
            $size, 
            $headers,
            $etag
        );

        return new Response($stream, $status, $newHeaders, info: [
            'is_partial' => $isPartial,
            'offset' => $offset,
            'length' => $length,
            'limit' => $limit
        ]);
    }

    /**
     * Send a downloadable response to the browser.
     *
     * Streams a file, resource, or raw string as an HTTP attachment.
     * Supports large-file streaming, HTTP range requests, and optional
     * chunked output with delay control.
     *
     * Behavior:
     * - Detects source type (file, resource, string).
     * - Sends download headers and handles partial content (206).
     * - Streams content efficiently to the client.
     * - Optionally deletes files after successful transfer.
     *
     * @param StreamInterface|string|resource $source File path, open resource, stream object, or raw string.
     * @param string|null $filename Download filename shown to the client.
     * @param array<string,mixed> $headers Additional HTTP headers.
     * @param bool $delete Delete file after download (files only).
     * @param int $chunkSize Bytes per chunk when streaming.
     * @param int $delay Microseconds delay between chunks.
     * @param string|null $etag Optional ETag for caching.
     *
     * @return bool Returns true on success, false on failure.
     * @throws RuntimeException When file source is missing or unreadable.
     * @see self::response() for creating a PSR-7 response without streaming.
     * 
     * @example - Example:
     * ```php
     * use \Luminova\Http\Downloader;
     * 
     * // Download a file from path
     * $status = Downloader::download('/path/to/file.zip', 'download.zip');
     * ```
     */
    public static function download(
        mixed $source,
        ?string $filename = null,
        array $headers = [],
        bool $delete = false,
        int $chunkSize = 8192,
        int $delay = 0,
        ?string $etag = null
    ): bool 
    {
        if ($source === null) {
            return false;
        }

        $handler = null;
        $length = null;
        $isFile = false;
        $length = 0;
        $mime = 'application/octet-stream';
        $etag ??= $headers['ETag'] ?? null;

        if ($source instanceof StreamInterface) {
            $handler = $source->detach();
            $length = $source->getSize() ?? 0;

            if ($handler === null && $length === 0) {
                $content = (string) $source;
                $length = strlen($content);
                $source = $content;
                $handler = null;
            }
        }elseif (is_resource($source)) {
            $handler = $source;
            $length = Filesystem::size($source);
        } elseif (is_string($source)) {
            if (is_file($source)) {
                if (!is_readable($source)) {
                    throw new RuntimeException("Download file is not readable: {$source}");
                }

                $handler = fopen($source, 'rb');
                if (!$handler) {
                    return false;
                }

                $isFile = true;
                $etag ??= self::generateETag($source, $filename);
                $filename ??= basename($source);
                $length = filesize($source);
                $mime = MIME::guess($source);
            }elseif (Filesystem::isLikelyFile($source, true)) {
                throw new RuntimeException("Download file does not exist: {$source}");
            }else{
                $length = strlen($source);
            }
        } else {
           throw new RuntimeException("Invalid download source type");
        }

        $filename ??= 'file_download';

        if ($length <= 0) {
            return false;
        }

        if ($etag !== null) {
            $headers['ETag'] = $etag;
        }

        [$isPartial, $offset, $length,, $status, $newHeaders] = self::parseHeaders(
            $filename, 
            $mime, 
            $length, 
            $headers,
            $etag
        );

        Header::send($newHeaders, false, status: $status);
        Header::clearOutputBuffers();

        if ($isPartial === false && $offset === 0 && $length === 0) {
            return false;
        }

        if ($handler !== null) {
            self::fromStream($handler, $length, $chunkSize, $delay, $offset, $isPartial);

            if ($isFile) {
                fclose($handler);
                if ($delete) {
                    @unlink($source);
                }
            } elseif (is_resource($source)) {
                fclose($handler);
            }

            return true;
        }

        self::fromString($source, $offset, $length, $chunkSize, $delay);
        return true;
    }

    /**
     * Stream data from a resource handler.
     *
     * @param resource $handler The open resource to read from.
     * @param int $length The total length of data to read.
     * @param int $chunkSize The size of each read chunk.
     * @param int $delay Microseconds delay between chunks.
     * @param int $offset The starting offset in the resource.
     * @param bool $isPartial Whether this is a partial content request.
     *
     * @return void
     */
    private static function fromStream(
        $handler,
        int $length,
        int $chunkSize,
        int $delay,
        int $offset,
        bool $isPartial = false
    ): void 
    {
        if ($offset > 0) {
            fseek($handler, $offset);
        }
            
        if(!$isPartial){
            fpassthru($handler);
            return;
        }

        while (!feof($handler) && $length > 0) {
            $read = min($chunkSize, $length);
            $chunk = fread($handler, $read);

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $length -= strlen($chunk);

            self::flushOutput($delay);
        }
    }

    /**
     * Stream data from a string.
     *
     * @param string $data The raw string data to stream.
     * @param int $offset The starting offset in the string.
     * @param int $length The total length of data to read.
     * @param int $chunkSize The size of each read chunk.
     * @param int $delay Microseconds delay between chunks.
     *
     * @return void
     */
    private static function fromString(
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

            self::flushOutput($delay);
        }
    }

    /**
     * Flush output buffers and apply delay.
     *
     * @param int $delay Microseconds delay to apply.
     *
     * @return void
     */
    private static function flushOutput(int $delay): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
            flush();
        }

        if ($delay > 0) {
            usleep($delay);
        }
    }

    /**
     * Generate an ETag for a file.
     *
     * @param string $file The file path.
     * @param string $filename The filename.
     *
     * @return string Returns the generated ETag.
     */
    private static function generateETag(string $file, string $filename): string 
    {
        return '"' . sha1(
            filesize($file) . ':' . filemtime($file) . ':' . $filename
        ) . '"';
    }

    /**
     * Get default download headers.
     *
     * @param string $filename The download filename.
     * @param string $mime The MIME type.
     * @param int $size The content size in bytes.
     *
     * @return array<string,mixed> The default headers.
     */
    private static function getDefaultHeaders(string $filename, string $mime, int $size): array 
    {
        return [
            'Connection' => 'close',
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Accept-Ranges' => 'bytes',
            'Expires' => 0,
            'Cache-Control' => 'must-revalidate',
            'Content-Length' => $size,
            'Pragma' => 'public',
            //'Content-Range' => "bytes 0-" . ($length - 1) . "/$length"
        ];
    }

    /**
     * Parse and prepare download headers.
     * 
     * @param string $filename The download filename.
     * @param string $mime The MIME type.
     * @param int $size The content size in bytes.
     * @param array<string,mixed> $headers Additional headers to send.
     * @param string|null $etag Optional ETag for caching.
     * 
     * @return array<int,mixed> Returns [isPartial, offset, length, $limit, status, headers].
     */
    private static function parseHeaders(
        string $filename,
        string $mime,
        int $size,
        array $headers = [],
        ?string $etag = null
    ): array 
    {
        $offset = 0;
        $length = $size;
        $isPartial = false;
        $etag = null;
        $headers = array_merge(self::getDefaultHeaders($filename, $mime, $size), $headers);

        $status = 200;
        $limit = 0;
        $isRangeRequest = isset($_SERVER['HTTP_RANGE']);

        if ($isRangeRequest && $etag !== null && isset($_SERVER['HTTP_IF_RANGE'])) {
            if (trim($_SERVER['HTTP_IF_RANGE']) !== $etag) {
                $isRangeRequest = false;
                unset($_SERVER['HTTP_RANGE']);
            }
        }

        if ($isRangeRequest) {
            [$length, $offset, $limit, $rangeHeader] = self::range($size);

            if ($rangeHeader === "bytes */$size") {
                return [false, 0, 0, 0, 416, [
                    'Content-Range' => $rangeHeader
                ]];
            }

            if ($rangeHeader !== null) {
                $isPartial = true;
                $status = 206;
                $headers['Content-Range']  = $rangeHeader;
                $headers['Content-Length'] = $length;
            }
        }

        return [$isPartial, $offset, $length, $limit, $status, $headers];
    }

    /**
     * Parses the HTTP Range header and returns the content range details.
     * 
     * @param int $size The total size of the content in bytes.
     * 
     * @return array<int,mixed> Return content length and range.
     *      - [length, offset, limit, rangeHeader].
     */
    private static function range(int $size): array
    {
        $range = $_SERVER['HTTP_RANGE'] ?? '';

        if ($range && str_contains($range, ',')) {
            // Multiple ranges not supported
            return [0, 0, 0, "bytes */$size"];
        }

        $offset = 0;
        $limit  = $size - 1;
        $length = $size;
        $header = null;

        if (!$range) {
            return [$length, $offset, $limit, null];
        }

        if (!preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            return [$length, $offset, $limit, null];
        }

        [$full, $start, $end] = $matches;

        // Suffix range: bytes=-500
        if ($start === '' && $end !== '') {
            $length = min((int) $end, $size);
            $offset = $size - $length;
            $limit  = $size - 1;
        }
        // Normal range: bytes=500-999
        elseif ($start !== '') {
            $offset = (int) $start;
            $limit  = ($end !== '') ? (int) $end : $limit;
        }

        // Invalid range → 416
        if ($offset >= $size || $offset > $limit) {
            return [0, 0, 0, "bytes */$size"];
        }

        $limit  = min($limit, $size - 1);
        $length = $limit - $offset + 1;
        $header = "bytes $offset-$limit/$size";

        return [$length, $offset, $limit, $header];
    }
}