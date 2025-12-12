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

use \Psr\Http\Message\StreamInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Storage\Stream as FileStream;
use \Luminova\Exceptions\InvalidArgumentException;

class Stream extends FileStream implements StreamInterface
{
    /** 
     * Cached size of the stream in bytes, or null when unknown / invalidated.
     * 
     * @var int|null $size
     */
    private ?int $size = null;

    /**
     * Create a new HTTP response message stream from PHP resource.
     *
     * @param resource|null $resource A valid, open PHP stream resource (e.g. the return
     *                           value of `fopen()`, `tmpfile()`, or `php://temp`).
     *
     * @throws InvalidArgumentException If the provided value is not a valid stream resource.
     * 
     * @see self::from() - Create from string, resource, or file.
     * @see self::fromString() - Create from a string.
     * @see self::fromResource() - Create from a stream resource.
     */
    public function __construct(mixed $resource)
    {
        if ($resource !== null) {
            self::assertNewResource($resource);
        }

        $this->resource = $resource;
    }

    /**
     * Return the URI or filename associated with the stream, if available.
     *
     * For `php://temp` and file-based streams this is the underlying path or wrapper URI. 
     * For `php://memory` and `data://` streams it is the scheme URI. 
     * 
     * Returns `null` when the stream has been detached or no URI exists.
     *
     * @return string|null The stream URI, or `null` if unavailable.
     * 
     * @see self::getMetadata() To retrieve the full metadata array.
     */
    public function getUri(): ?string
    {
        return $this->metadata('uri') ?: null;
    }

    /**
     * Return the total size of the stream content in bytes.
     *
     * The result is cached after the first successful call and
     * invalidated whenever `write()` modifies the stream. 
     * 
     * Returns `null` for streams whose size cannot be determined (e.g. non-seekable network streams).
     *
     * @return int|null The stream size in bytes, or `null` if unknown.
     */
    public function getSize(): ?int
    {
        if (!isset($this->resource)) {
            return null;
        }

        $this->size ??= $this->size();
        return $this->size;
    }

    /**
     * Return all bytes from the current stream position to EOF.
     *
     * Unlike `buffer()`, this method does **not** rewind first; it reads only
     * the bytes that remain after the current pointer position.
     *
     * @return string The remaining stream content.
     * @throws RuntimeException If the stream has been detached, is not readable, or fails.
     * 
     * @see FileStream
     */
    public function getContents(): string
    {
        return $this->read(-1);
    }

    /**
     * Retrieve stream metadata or a single metadata value by key.
     *
     * Wraps `stream_get_meta_data()`. When the stream has been detached,
     * returns `null` for a keyed look-up or an empty array for a full look-up.
     *
     * @param string|null $key A specific metadata key (e.g. `'mode'`, `'seekable'`,
     *                         `'uri'`), or `null` to retrieve the full associative array.
     *
     * @return mixed The value for the requested key, the full metadata array when
     *               `$key` is `null`, or `null` / `[]` if the stream is detached.
     */
    public function getMetadata(?string $key = null): mixed
    {
        return $this->metadata($key);
    }

    /**
     * {@inheritDoc}
     * 
     * > After a successful write the cached size is invalidated so that the
     * next call to `getSize()` reflects the updated content length.
     */
    public function write(string $string): int
    {
        $this->size = null;
        return parent::write($string);
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->size = null;
        parent::close();
    }

    /**
     * {@inheritDoc}
     */
    public function detach(): mixed
    {
        $this->size = null;
        return parent::detach();
    }

    /**
     * {@inheritDoc}
     */
    protected static function newStatic(
        string $filename,
        string $mode = 'c+b',
        bool $autoOpen = true
    ): static 
    {
        $instance = new self(null);

        $instance->mode = $mode;
        $instance->filename = $filename;
        $instance->autoOpen = $autoOpen;

        if($autoOpen){
            $instance->open();
        }

        return $instance;
    }
}