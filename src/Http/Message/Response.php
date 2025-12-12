<?php 
/**
 * Luminova Framework HTTP request response object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 * @link https://luminova.ng/docs/0.0.0/http/response
 */
namespace Luminova\Http\Message;

use \Throwable;
use \Stringable;
use \Luminova\Http\Header;
use \Luminova\Http\HttpStatus;
use \Luminova\Http\Message\Stream;
use \Psr\Http\Message\StreamInterface;
use \Luminova\Interface\ResponseInterface;
use \Luminova\Interface\CookieJarInterface;
use \Luminova\Exceptions\InvalidArgumentException;

class Response implements ResponseInterface, Stringable
{
    /**
     * Creates a new HTTP response object.
     *
     * @param StreamInterface|string $body Response body as a PSR-7 stream or string.
     * @param int $statusCode HTTP status code (default: 200).
     * @param array $headers Response headers.
     * @param array $info Optional response info (Novio).
     * @param string $reasonPhrase Reason phrase for the status code (default: 'OK').
     * @param string $protocolVersion HTTP protocol version (default: '1.1').
     * @param CookieJarInterface|null $cookie Optional cookie jar instance.
     */
    public function __construct(
        private StreamInterface|string $body = '',
        private int $statusCode = 200, 
        private array $headers = [], 
        private array $info = [],
        private string $reasonPhrase = 'OK',
        private string $protocolVersion = '1.1',
        public ?CookieJarInterface $cookie = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string 
    {
        $version = $this->getProtocolVersion();
        $phrase = $this->getReasonPhrase();
        $headers = $this->getHeadersString();

        $response = "HTTP/{$version} {$this->statusCode} {$phrase}\r\n";
        $response .= $headers;

        if (!str_contains($headers, 'Content-Length')) {
            $response .= "Content-Length: " . $this->getLength() . "\r\n";
        }

        $response .= "\r\n";
 
        return $response . $this->getContents();
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): StreamInterface
    {
        if($this->body instanceof StreamInterface){
            return $this->body;
        }

        return $this->body = Stream::fromString((string) $this->body);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $name): array
    {
        return (array) ($this->headers[strtolower($name)] ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine(string $header): string
    {
        return implode(', ', $this->getHeader($header));
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase(): string
    {
        if($this->reasonPhrase === ''){
            $this->reasonPhrase = HttpStatus::phrase($this->statusCode);
        }

        return $this->reasonPhrase;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileTime(): int
    {
        return $this->info['filetime'] ?? -1;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        if (is_string($this->body) || ($this->body instanceof Stringable)) {
            return (string) $this->body;
        }

        if (!$this->body instanceof StreamInterface) {
            return '';
        }

        if($this->body instanceof Stream){
            return $this->body->toString();
        }

        try {
            if ($this->body->isSeekable()) {
                $this->body->seek(0);
            }

            return $this->body->getContents();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLength(?string $encoding = null): int
    {
        if($this->body === ''){
            return 0;
        }

        return $this->getBody()->getSize() 
            ?? strlen($this->getContents(), $encoding);
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string
    {
        if ($this->protocolVersion !== '') {
            return $this->protocolVersion;
        }

        $phrase = $this->getHeader('X-Response-Protocol-Status-Phrase');

        if($phrase !== []){
            $status = $phrase[0] ?? null;

            if ($phrase && is_string($status) && preg_match('/^HTTP\/(\d+\.\d+)/', $status, $matches)) {
                return $this->protocolVersion = $matches[1];
            }
        }

        return $this->protocolVersion = '1.1';
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader(string $name): bool 
    {
        if($this->headers === []){
            return false;
        }

        $headers = array_change_key_case($this->headers, CASE_LOWER);

        return isset($headers[strtolower($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        if ($code < 100 || $code >= 600) {
            throw new InvalidArgumentException(sprintf(
                'Invalid HTTP status code: %d was provided, status code must be between 1xx and 5xx.',
                $code
            ));
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?: HttpStatus::phrase($code);
        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader(string $name): static
    {
        $normalize = strtolower($name);

        if (!isset($this->headers[$name]) && !isset($this->headers[$normalize])) {
            return $this;
        }

        $new = clone $this;
        unset($new->headers[$name], $new->headers[$normalize]);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader(string $name, $value): static
    {
        Header::assert($name, isValue: false);
        $value = Header::normalize($value);
        $normalized = strtolower($name);

        $new = clone $this;
        $new->headers[$normalized] = (array) $value;


        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion(string $version): static
    {
        if ($this->protocolVersion === $version) {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader(string $name, $value): static
    {
        Header::assert($name);
        $value = Header::normalize($value);
        $normalized = strtolower($name);

        $new = clone $this;
        $new->headers[$normalized] = array_merge(
            $new->headers[$normalized] ?? [], 
            (array) $value
        );

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }
}