<?php 
/**
 * Class for handling HTTP responses.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Template;

use \Luminova\Storages\FileManager;
use \Luminova\Storages\FileDelivery;
use \Luminova\Optimization\Minification;
use \Luminova\Http\Header;
use \Luminova\Http\Encoder;
use \Luminova\Exceptions\JsonException;
use \Exception;

class Response 
{
    /**
     * Indicates if the response content should be minified.
     * 
     * @var bool $minify
     */
    private bool $minify = false;

    /**
     * Handles content minification object.
     * 
     * @var Minification|null $min
     */
    private static ?Minification $min = null;

    /**
     * Response constructor.
     *
     * @param int $status HTTP status code (default: 200 OK).
     * @param array<string,mixed> $headers HTTP headers as key-value pairs.
     * @param bool $encode Whether to enable content encoding like gzip.
     * @param bool $minifyCodeblocks Indicates if code blocks should be minified.
     * @param bool $codeblockButton Indicates if code blocks should include a copy button.
     */
    public function __construct(
        private int $status = 200, 
        private array $headers = [],
        private bool $encode = false,
        private bool $minifyCodeblocks = false,
        private bool $codeblockButton = false
    )
    {
        $this->minify = (bool) env('page.minification', false);
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $status HTTP status code.
     * 
     * @return self Return instance of the Response class.
     */
    public function setStatus(int $status): self 
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Enable or disable content encoding.
     *
     * @param bool $encode Whether to enable content encoding like gzip.
     * 
     * @return self Instance of the Response class.
     */
    public function encode(bool $encode): self 
    {
        $this->encode = $encode;

        return $this;
    }

    /**
     * Enable or disable content minification.
     *
     * @param bool $minify Whether to minify the content.
     * 
     * @return self Instance of the Response class.
     */
    public function minify(bool $minify): self 
    {
        $this->minify = $minify;

        return $this;
    }

    /** 
     * Configure HTML code block minification and copy button.
     *
     * @param bool $minify Whether to minify code blocks.
     * @param bool $button Whether to add a copy button to code blocks (default: false).
     *
     * @return self Instance of the Response class.
     */
    public function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }

    /**
     * Set an HTTP header.
     *
     * @param string $key The header name.
     * @param mixed $value The header value.
     * 
     * @return self Instance of the Response class.
     */
    public function header(string $key, mixed $value): self 
    {
        $this->headers[$key] = $value;

        return $this;
    }

     /**
     * Set multiple HTTP headers.
     *
     * @param array<string,mixed> $headers Associative array of headers.
     * 
     * @return self Instance of the Response class.
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Get the current HTTP status code.
     * 
     * @return int Return the current HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Retrieve a specific HTTP header.
     * 
     * @param string $name The name of the header.
     * 
     * @return string|null Return the header value, or null if it doesn't exist.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Retrieve all HTTP headers.
     * 
     * @return array<string,mixed> Return list of all HTTP headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Clear all previous HTTP headers.
     * 
     * @return void
     */
    public function clearHeaders(): void
    {
        $this->headers = [];
    }
    
    
    /**
     * Clear previous set HTTP header redirects.
     * 
     *  @return void
     */
    public function clearRedirects(): void
    {
        if(isset($this->headers['Location'])) {
            unset($this->headers['Location']);
        }
    }
    
    /**
     * Determine if the response headers has any redirects.
     * 
     * @return bool Return true if headers contain any redirect, otherwise false.
     */
    public function hasRedirects(): bool
    {
        return isset($this->headers['Location']);
    }

    /**
     * Send the response content with headers.
     *
     * @param string $content The content to send.
     * @param int $status HTTP status code (default: 200).
     * @param array<string,mixed> $headers Additional headers (default: []).
     * @param bool $encode Whether to enable content encoding (default: false).
     * @param bool $minify Whether to minify content (default: false).
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function render(
        string $content, 
        int $status = 200, 
        array $headers = [],
        bool $encode = false, 
        bool $minify = false
    ): int
    {
        if($content === ''){
            return STATUS_ERROR;
        }

        $headers = array_merge($this->headers, $headers);

        if(!isset($headers['Content-Type'])){
            $headers['Content-Type'] = 'application/json';
        }

        $length = false;
       
        if($minify && str_contains($headers['Content-Type'], 'text/html')){
            self::$min ??= new Minification();
            self::$min->codeblocks($this->minifyCodeblocks);
            self::$min->copyable($this->codeblockButton);
            
            $instance = self::$min->compress($content, $headers['Content-Type']);
            $content = $instance->getContent();
            $length = $instance->getLength();
        }

        if($encode){
            [$encoding, $content] = (new Encoder())->encode($content);
            if($encoding !== false){
                $headers['Content-Encoding'] = $encoding;
            }
        }

        if($content === ''){
            return STATUS_ERROR;
        }

        $headers['default_headers'] = true;
        $headers['Content-Length'] = ($length === false ? string_length($content) : $length);

        Header::parseHeaders($headers, $status);
        echo $content;

        return STATUS_SUCCESS;
    }

    /** 
     * Send the defined HTTP headers without any body.
     *
     * @return void
     */
    public function sendHeaders(): void 
    {
        Header::parseHeaders($this->headers, $this->status);
    }

    /**
     * Send the HTTP status header.
     * 
     * @return bool True if the status header was sent, false otherwise.
     */
    public function sendStatus(): bool
    {
        return http_status_header($this->status);
    }

    /**
     * Send a JSON response.
     *
     * @param array|object $content The data to encode as JSON.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     * @throws JsonException Throws if json error occurs.
     */
    public function json(array|object $content): int 
    {
        if (is_object($content)) {
            $content = (array) $content;
        }
        try {
            $content = json_encode($content, JSON_THROW_ON_ERROR);

            return $this->render($content, $this->status, [
                'Content-Type' => 'application/json'
            ], $this->encode, $this->minify);
        }catch(Exception|\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }

        return STATUS_ERROR;
    }

    /**
     * Send a plain text response.
     *
     * @param string $content The text content to send.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function text(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'text/plain'
        ], $this->encode, $this->minify);
    }

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content to send.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function html(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'text/html'
        ], $this->encode, $this->minify);
    }

    /**
     * Send an XML response.
     *
     * @param string $content XML content to send.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function xml(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'application/xml'
        ], $this->encode, $this->minify);
    }

    /**
     * Send a file or content to download on browser.
     *
     * @param string $fileOrContent Path to the file or content for download.
     * @param string|null $name Optional name for the downloaded file.
     * @param array $headers Optional download headers.
     * 
     * @return bool Return true if the download was successful, false otherwise.
     */
    public function download(
        string $fileOrContent, 
        ?string $name = null, 
        array $headers = []
    ): bool 
    {
        return FileManager::download($fileOrContent, $name, $headers);
    }

    /**
     * Send large files using stream to read file content.
     *
     * @param string $path The path to file storage (e.g: /writeable/storages/images/).
     * @param string $basename The file name (e.g: image.png).
     * @param array $headers Optional stream headers.
     * @param bool $eTag Whether to generate ETag headers (default: true).
     * @param int $expiry Expiry time in seconds for cache control (default: 0), indicating no cache.
     * 
     * @return bool Return true if file streaming was successful, false otherwise.
     */
    public function stream(
        string $path, 
        string $basename, 
        array $headers = [],
        bool $eTag = true,
        int $expiry = 0
        ): bool 
    {
        return (new FileDelivery($path, $eTag))->output($basename, $expiry, $headers);
    }

    /** 
     * Send to another url location.
     *
     * @param string $url The new url location.
     * @param int $response_code The response status code.
     *
     * @return void
     */
    public function redirect(string $url = '/', int $response_code = 0): void 
    {
        header("Location: $url", true, $response_code);

        exit(0);
    }
}