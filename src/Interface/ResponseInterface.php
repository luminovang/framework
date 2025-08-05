<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Psr\Http\Message\{StreamInterface, MessageInterface};
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

interface ResponseInterface extends \Psr\Http\Message\ResponseInterface
{
    /**
     * Convert the HTTP response to a formatted string.
     *
     * This method generates the complete HTTP response string, including
     * the status line, headers, and body content. It checks for the
     * 'Content-Length' header and adds it if not present.
     *
     * @return string Returns the complete HTTP response as string suitable for sending over the network.
     */
    public function toString(): string;

    /**
     * Convert the HTTP response to a formatted string.
     *
     * @return string Returns the complete HTTP response as string suitable for sending over the network.
     */
    public function __toString(): string;

    /**
     * Retrieves the HTTP protocol version (e.g, `1.0`, `1.1`).
     *
     * Returns the version number (e.g., "1.1", "1.0"). 
     * If not set, it returns an empty string.
     *
     * @return string Return the HTTP protocol version.
     */
    public function getProtocolVersion(): string;

    /**
     * Retrieves the reason phrase associated with the HTTP status code (e.g, `OK`, `Internal Server Error`).
     *
     * @return string Return the reason phrase.
     */
    public function getReasonPhrase(): string;

    /**
     * Retrieves response HTTP status code.
     *
     * @return int Return the HTTP status code.
     */
    public function getStatusCode(): int;

    /**
     * Retrieves request file time modified.
     *
     * @return int Return the file modified if available, otherwise return `-1`.
     */
    public function getFileTime(): int;

    /**
     * Retrieves all request response headers.
     *
     * @return array<string,array> Return an associative nested arrays of response headers.
     */
    public function getHeaders(): array;

    /**
     * Convert an associative array of headers into a formatted string.
     *
     * This method converts the response headers to a string representation suitable for HTTP responses, where each
     * header is formatted as 'Key: Value' and separated by CRLF.
     * 
     * @return string Return a formatted string containing all headers, 
     *                followed by an additional CRLF to signal the end 
     *                of the headers section.
     */
    public function getHeadersString(): string;

    /**
     * Retrieves a specific header value by its key.
     *
     * @param string $name The header key to retrieve.
     * 
     * @return array<int,mixed> Return an array of header values.
     */
    public function getHeader(string $name): array;

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * @param string $name The header field name to retrieve the values.
     * 
     * @return string Return a string of values as provided for the given header concatenated together using a comma.
     */
    public function getHeaderLine(string $header): string;

    /**
     * Determine if a header exists in the response headers.
     *
     * @param string $name The header name to check.
     * 
     * @return bool Return true if the header exists, false otherwise.
     */
    public function hasHeader(string $name): bool;

    /**
     * Retrieves the response contents as a stream.
     *
     * This method opens a temporary stream, writes the response contents if available, 
     * and rewinds the stream before returning it.
     *
     * @return \T<StreamInterface> Return the response body as a stream object.
     * @throws RuntimeException Throws if the temporary stream cannot be opened.
     * 
     * @link https://luminova.ng/docs/0.0.0/http/stream
     */
    public function getBody(): StreamInterface;

    /**
     * Retrieves the extracted response contents as a string.
     *
     * @return string Return the processed response contents.
     * 
     * > **Note:** This method may not supported while using `Guzzle` client.
     * > To ensure compatibility use `$response->getBody()->getContents()` instead.
     */
    public function getContents(): string;

    /**
     * Retrieves the request response contents length.
     * 
     * @param string|int $encoding Optional string encoding (default: `null` or `env(app.mb.encoding)`).
     * 
     * @return int Returns the content length.
     */
    public function getLength(?string $encoding = null): int;

    /**
     * Retrieves additional information about the response.
     *
     * @return array<string,mixed> Return an associative array of response metadata.
     */
    public function getInfo(): array;
    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * 
     * @return \T<MessageInterface> Return new instance of response message object.
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withHeader(string $name, $value): MessageInterface;

     /**
     * Return an instance without the specified header.
     * 
     * @param string $name Case-insensitive header field name to remove.
     * 
     * @return \T<MessageInterface> Return new instance of response message object.
     */
    public function withoutHeader(string $name): MessageInterface;

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * @param int $code The 3-digit integer result code to set (e.g, `1xx` to `5xx`).
     * @param string $reasonPhrase The reason phrase to use.
     * 
     * @return \T<ResponseInterface> Return new instance of response object.
     * @throws InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * @param string $version HTTP protocol version (e.g.,`1.1`, `1.0`).
     * 
     * @return \T<MessageInterface> Return new instance of response message object.
     */
    public function withProtocolVersion(string $version): MessageInterface;

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * @param string $name Case-insensitive header field name to add.
     * @param string|string[] $value The header value(s).
     * 
     * @return \T<MessageInterface> Return new instance of response message object.
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader(string $name, $value): MessageInterface;

    /**
     * Return an instance with the specified message body.
     *
     * @param StreamInterface $body The instance of stream object with response body.
     * 
     * @return \T<MessageInterface> Return new instance of response message object.
     * @throws InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body): MessageInterface;
}