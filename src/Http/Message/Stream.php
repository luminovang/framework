<?php 
/**
 * Luminova Framework stream class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Message;

use \Throwable;
use \Stringable;
use \Psr\Http\Message\StreamInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;

class Stream implements StreamInterface, Stringable
{
    /**
     * Patterns for readable stream modes.
     * 
     * @var string IS_READABLE_MODES
    */
    private const IS_READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';

    /**
     * Patterns for writable stream modes.
     * 
     * @var string IS_WRITABLE_MODES
    */
    private const IS_WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    /** 
     * Holds the size of the stream in bytes.
     * 
     * @var int|null $size
     */
    private ?int $size = null;

    /**
     * Create a new stream instance using an underlying PHP resource.
     *
     * @param resource $resource A valid PHP stream resource (e.g., from fopen, php://temp).
     *
     * @throws InvalidArgumentException If the provided value is not a valid stream resource.
     */
    public function __construct(private mixed $resource)
    {
        if (!is_resource($this->resource)) {
            throw new InvalidArgumentException('Invalid resource provided; expected a valid stream resource.');
        }
    }

    /**
     * Writes data to the stream.
     *
     * @param string $string The data to write.
     * 
     * @return int Return the number of bytes written to the stream.
     * @throws RuntimeException if the stream is not writable or if the write operation fails.
     */
    public function write(string $string): int
    {
        $this->assertResource();

        if (!$this->isWritable()) {
            throw new RuntimeException('Error, stream is not writable.');
        }

        $bytesWritten = fwrite($this->resource, $string);

        if ($bytesWritten === false) {
            throw new RuntimeException('Unable to write data to the stream.');
        }

        $this->size = null;
        return $bytesWritten;
    }

    /**
     * Reads data from the stream.
     *
     * @param int $length The number of bytes to read.
     * 
     * @return string Return the data read from the stream.
     * @throws RuntimeException if the stream is not readable or if the read operation fails.
     * @throws InvalidArgumentException if the length parameter is negative.
     */
    public function read(int $length): string
    {
        $this->assertResource();

        if (!$this->isReadable()) {
            throw new RuntimeException('Error, stream is not readable.');
        }

        if ($length < 0) {
            throw new InvalidArgumentException('Read length parameter cannot be negative integer.');
        }

        if ($length === 0) {
            return '';
        }

        $data = fread($this->resource, $length);

        if ($data === false) {
            throw new RuntimeException('Unable to read data from the stream.');
        }

        return $data;
    }

    /**
     * Closes the stream and releases any resources associated with it.
     */
    public function close(): void
    {
        if (isset($this->resource) && is_resource($this->resource)) {
            fclose($this->resource);
            $this->detach();
        }
    }

    /**
     * Detaches the underlying resource from the stream.
     *
     * @return resource|null Return the detached resource, or null if none is present.
     */
    public function detach(): mixed
    {
        if (!isset($this->resource)) {
            return null;
        }

        $res = $this->resource;
        $this->resource = null;
        $this->size = null;
        return $res;
    }

    /**
     * Read the full stream content from the beginning.
     *
     * This method rewinds the stream pointer (if seekable) and then reads
     * the entire body in one operation. It ignores the current pointer
     * position and always returns the complete data stored in the stream.
     *
     * Useful when you want the whole response body regardless of whether
     * it has been partially read earlier.
     *
     * @return string Returns the full content of the stream.
     *
     * @throws RuntimeException If the stream cannot be read.
     */
    public function buffer(): string
    {
        if ($this->isSeekable()) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    /**
     * Retrieves the current position of the stream pointer.
     *
     * @return int Return the position of the stream pointer.
     * @throws RuntimeException if the position cannot be determined.
     */
    public function tell(): int
    {
        $this->assertResource();
        $position = ftell($this->resource);

        if ($position === false) {
            throw new RuntimeException('Unable to determine the current position of the stream pointer.');
        }

        return $position;
    }

    /**
     * Determines if the stream has reached the end of file.
     *
     * @return bool Return true if the stream is at EOF, false otherwise.
     */
    public function eof(): bool
    {
        $this->assertResource();
        return feof($this->resource);
    }

    /**
     * Seeks to a position within the stream.
     *
     * @param int $offset The position to seek to.
     * @param int $whence The seek mode (default: `SEEK_SET`).
     * 
     * @throws RuntimeException if the stream is not seekable or if the seek operation fails.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->assertResource();

        if (!$this->isSeekable()) {
            throw new RuntimeException('Error, stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Failed to seek to the specified position in the stream.');
        }
    }

    /**
     * Rewinds the stream to the beginning.
     *
     * @throws RuntimeException if the rewind operation fails.
     */
    public function rewind(): void
    {
        if (!rewind($this->resource)) {
            throw new RuntimeException('Failed to rewind the stream pointer to the beginning.');
        }
    }

    /**
     * Checks if the stream is writable.
     *
     * @return bool Return true if the stream is writable, false otherwise.
     */
    public function isWritable(): bool
    {
        return (bool) preg_match(self::IS_WRITABLE_MODES, $this->getMetadata('mode'));
    }

    /**
     * Checks if the stream is readable.
     *
     * @return bool Return true if the stream is readable, false otherwise.
     */
    public function isReadable(): bool
    {
        return (bool) preg_match(self::IS_READABLE_MODES, $this->getMetadata('mode'));
    }

    /**
     * Checks if the stream is seekable.
     *
     * @return bool Return true if the stream is seekable, false otherwise.
     */
    public function isSeekable(): bool
    {
        return $this->getMetadata('seekable') ?? false;
    }

    /**
     * Get the total size of the stream in bytes.
     *
     * Returns the number of bytes in the stream if known, or null if unknown.
     *
     * @return int|null Returns the size of the stream in bytes, or null if unknown.
     */
    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!isset($this->resource)) {
            return null;
        }

        $uri = $this->getMetadata('uri');
        if ($uri) {
            clearstatcache(true, $uri);
        }

        $stats = fstat($this->resource);

        if ($stats !== false && isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    /**
     * Retrieves the remaining contents of the stream as a string.
     *
     * @return string Return the remaining contents of the stream.
     * @throws RuntimeException if the stream is not readable or if the content retrieval fails.
     */
    public function getContents(): string
    {
        $this->assertResource();

        if (!$this->isReadable()) {
            throw new RuntimeException('Error, stream is not readable.');
        }

        $content = stream_get_contents($this->resource);

        if ($content === false) {
            throw new RuntimeException('Unable to retrieve the contents of the stream.');
        }

        return $content;
    }

    /**
     * Retrieves metadata about the stream or a specific key from the metadata array.
     *
     * @param string|null $key The specific metadata key to retrieve, or null to retrieve all metadata.
     * 
     * @return mixed Return the metadata value for the specified key, an array of all metadata if key is NULL, otherwise null or empty array.
     */
    public function getMetadata(?string $key = null): mixed
    {
        if (!isset($this->resource)) {
            return $key ? null : [];
        }
        
        $meta = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    /**
     * Reads the entire stream and returns it as a string.
     * 
     * @return string Return the contents of the stream.
     */
    public function toString(): string
    {
        try{
            return $this->buffer();
        }catch(Throwable){
            return '';
        }
    }

    /**
     * Converts the stream to a string by returning its contents.
     *
     * @return string Return the contents of the stream.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Ensures that the stream resource is available and valid.
     *
     * @throws RuntimeException if the stream resource is not available or has been detached.
     */
    private function assertResource(): void 
    {
        if (!isset($this->resource)) {
            throw new RuntimeException('Error, trying to access a detached stream.');
        }
    }
}