<?php 
/**
 * Luminova Framework HTTP request response object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng/docs/0.0.0/http/response
*/
namespace Luminova\Http\Message;

use \Luminova\Storages\Stream;
use \Luminova\Http\HttpCode;
use \Luminova\Interface\CookieJarInterface;
use \Luminova\Functions\Normalizer;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\RuntimeException;
use \Stringable;

class Response implements Stringable
{
    /**
     * Initialize a new network request response object.
     *
     * @param int $statusCode The HTTP status code of the response (default: 200).
     * @param array $headers An array of response headers.
     * @param string $contents The extracted content from the response.
     * @param array $info Additional response information from cURL (optional).
     * @param string $reasonPhrase The reason phrase associated with the status code (default: 'OK').
     * @param string $protocolVersion The HTTP protocol version (default: '1.1').
     * @param Stream|null $stream Optionally passed s stream object.
     * @param CookieJarInterface|null $cookie Optionally HTTP cookie jar object.
     */
    public function __construct(
        private int $statusCode = 200, 
        private array $headers = [], 
        private string $contents = '', 
        private array $info = [],
        private string $reasonPhrase = 'OK',
        private string $protocolVersion = '1.1',
        private ?Stream $stream = null,
        public ?CookieJarInterface $cookie = null
    ) {}

    /**
     * Convert the HTTP response to a formatted string.
     *
     * This method generates the complete HTTP response string, including
     * the status line, headers, and body content. It checks for the
     * 'Content-Length' header and adds it if not present.
     *
     * @return string Returns the complete HTTP response as string suitable for sending over the network.
     */
    public function toString(): string 
    {
        $version = $this->getProtocolVersion();
        $phrase = $this->getReasonPhrase();
        $headers = $this->getHeadersString();

        $response = "HTTP/{$version} {$this->statusCode} {$phrase}\r\n";
        $response .= $headers;

        if (!str_contains($headers, 'Content-Length')) {
            $response .= "Content-Length: " . strlen($this->contents) . "\r\n";
        }

        $response .= "\r\n";
        $response .= $this->contents;

        return $response; 
    }

    /**
     * Convert the HTTP response to a formatted string.
     *
     * @return string Returns the complete HTTP response as string suitable for sending over the network.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Retrieves the HTTP protocol version (e.g, `1.0`, `1.1`).
     *
     * Returns the version number (e.g., "1.1", "1.0"). 
     * If not set, it returns an empty string.
     *
     * @return string Return the HTTP protocol version.
     */
    public function getProtocolVersion(): string 
    {
        if($this->protocolVersion === ''){
            $status = $this->getHeader('X-Response-Protocol-Status-Phrase')[0] ?? null;

            if ($status && preg_match('/^HTTP\/(\d+\.\d+)/', $status, $matches)) {
                $this->protocolVersion = $matches[1] ?? '';
            }
        }

        return $this->protocolVersion; 
    }

    /**
     * Retrieves the reason phrase associated with the HTTP status code (e.g, `OK`, `Internal Server Error`).
     *
     * @return string Return the reason phrase.
     */
    public function getReasonPhrase(): string
    {
        if($this->reasonPhrase === ''){
            $this->reasonPhrase = HttpCode::get($this->statusCode);
        }

        return $this->reasonPhrase;
    }

    /**
     * Retrieves response HTTP status code.
     *
     * @return int Return the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Retrieves request file time modified.
     *
     * @return int Return the file modified if available, otherwise return `-1`.
     */
    public function getFileTime(): int
    {
        return $this->info['filetime'] ?? -1;
    }

    /**
     * Retrieves all request response headers.
     *
     * @return array<string,array> Return an associative nested arrays of response headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

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
    public function getHeadersString(): string 
    {
        $headers = '';
        
        foreach ($this->headers as $key => $value) {
            
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $headers .= "$key: $value\r\n";
        }
        
        return $headers;
    }

    /**
     * Retrieves a specific header value by its key.
     *
     * @param string $name The header key to retrieve.
     * 
     * @return array<int,mixed> Return an array of header values.
     */
    public function getHeader(string $name): array
    {
        return (array) $this->headers[strtolower($name)] ?? [];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * @param string $name The header field name to retrieve the values.
     * 
     * @return string Return a string of values as provided for the given header concatenated together using a comma.
     */
    public function getHeaderLine(string $header): string
    {
        return implode(', ', $this->getHeader($header));
    }

    /**
     * Determine if a header exists in the response headers.
     *
     * @param string $name The header name to check.
     * 
     * @return bool Return true if the header exists, false otherwise.
     */
    public function hasHeader(string $name): bool 
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * Retrieves the response contents as a stream.
     *
     * This method opens a temporary stream, writes the response contents if available, 
     * and rewinds the stream before returning it.
     *
     * @return Stream Return the response body as a stream object.
     * @throws RuntimeException Throws if the temporary stream cannot be opened.
     * 
     * @see https://luminova.ng/docs/0.0.0/http/stream
     */
    public function getBody(): Stream
    {
        if($this->stream instanceof Stream){
            return $this->stream;
        }

        $resource = fopen('php://temp', 'r+');
        
        if ($resource === false) {
            throw new RuntimeException('Failed to open temporary stream ("php://temp").');
        }

        if ($this->contents !== '') {
            fwrite($resource, $this->contents);
            fseek($resource, 0);
        }

        return new Stream($resource);
    }

    /**
     * Retrieves the extracted response contents as a string.
     *
     * @return string Return the processed response contents.
     */
    public function getContents(): string
    {
        return $this->contents;
    }

    /**
     * Retrieves additional information about the response.
     *
     * @return array<string,mixed> Return an associative array of response metadata.
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string $name Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * 
     * @return Response Return new static response object.
     * @throws InvalidArgumentException for invalid header names or values.
     */
    public function withHeader(string $name, array|string $value): Response
    {
        Normalizer::assertHeader($name);
        $value = Normalizer::normalizeHeaderValue($value);
        $normalized = strtolower($name);

        $new = clone $this;
        if (isset($new->headers[$normalized])) {
            unset($new->headers[$normalized]);
        }

        $new->headers[$normalized] = $value;


        return $new;
    }

     /**
     * Return an instance without the specified header.
     * 
     * @param string $name Case-insensitive header field name to remove.
     * 
     * @return Response Return new static response object.
     */
    public function withoutHeader(string $name): Response
    {
        $name = strtolower($name);

        if (!isset($this->headers[$name])) {
            return $this;
        }

        $new = clone $this;
        unset($new->headers[$name]);

        return $new;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * @param int $code The 3-digit integer result code to set (e.g, `1xx` to `5xx`).
     * @param string $reasonPhrase The reason phrase to use.
     * 
     * @return Response Return new static response object.
     * @throws InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): Response
    {
        if ($code < 100 || $code >= 600) {
            throw new InvalidArgumentException(sprintf(
                'Invalid HTTP status code: %d was provided, status code must be between 1xx and 5xx.',
                $code
            ));
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?: HttpCode::get($code);
        return $new;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * @param string $version HTTP protocol version (e.g.,`1.1`, `1.0`).
     * 
     * @return Response Return new static response object.
     */
    public function withProtocolVersion(string $version): Response
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }
}