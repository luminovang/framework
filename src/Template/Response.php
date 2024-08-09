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
     * @var bool $encode
    */
    private bool $encode = true;

    /**
     * @var bool $minify
    */
    private bool $minify = false;

    /**
     * @var bool $minifyCodeblocks
    */
    private bool $minifyCodeblocks = false;

    /**
     * @var bool $codeblockButton
    */
    private bool $codeblockButton = false;

    /**
     * @var Minification|null $min
    */
    private static ?Minification $min = null;

    /**
     * Response constructor.
     *
     * @param int $status HTTP status code (default: 200 OK).
     * @param array<string,mixed> $headers The header key-pair.
     */
    public function __construct(
        private int $status = 200, 
        private array $headers = []
    )
    {
        $this->encode = (bool) env('enable.encoding', false);
        $this->minify = (bool) env('page.minification', false);
    }

    /**
     * Set status code.
     *
     * @param int $status HTTP status code.
     * 
     * @return self Return response class instance.
     */
    public function setStatus(int $status): self 
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Set enable content encoding.
     *
     * @param bool $encode Enable content encoding like gzip.
     * 
     * @return self Return response class instance.
     */
    public function encode(bool $encode): self 
    {
        $this->encode = $encode;

        return $this;
    }

    /**
     * Set enable content minification.
     *
     * @param bool $minify Enable content minification.
     * 
     * @return self Return response class instance.
     */
    public function minify(bool $minify): self 
    {
        $this->minify = $minify;

        return $this;
    }

    /** 
     * Set if HTML codeblock tags should be ignore during content minification.
     *
     * @param bool $minify Indicate if codeblocks should be minified (default: false).
     * @param bool $button Indicate if codeblock tags should include a copy button (default: false).
     *
     * @return self Return response class instance.
    */
    public function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }

    /**
     * Set response header.
     *
     * @param string $key The header key.
     * @param mixed $value The header value for key.
     * 
     * @return self Return response class instance.
     */
    public function header(string $key, mixed $value): self 
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Set response header.
     *
     * @param array<string,mixed> $headers The headers key-pair.
     * 
     * @return self Return response class instance.
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Get the HTTP status code.
     * 
     * @return int Return the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Get a single HTTP header.
     * 
     * @param string $name Header name.
     * 
     * @return string|null Return HTTP header.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Get all HTTP headers.
     * 
     * @return array Return HTTP headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Render any content format anywhere.
     *
     * @param mixed $content Response content.
     * @param int $status Content type of the response.
     * @param array $header Additional headers.
     * @param bool $encode Enable content encoding like gzip.
     * @param bool $minify Enable content minification and compress.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
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
     * Send a JSON response.
     *
     * @param array|object $content Data to be encoded as JSON
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
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
     * @param string $content The text content.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
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
     * @param string $content HTML content.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
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
     * @param string $content XML content.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
     */
    public function xml(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'application/xml'
        ], $this->encode, $this->minify);
    }

    /**
     * Download a file
     *
     * @param string $fileOrContent Path to the file or content to be downloaded.
     * @param string|null $name Optional Name to be used for the downloaded file (default: null).
     * @param array $headers Optional download headers.
     * 
     * @return bool Return true if the download was successful, false otherwise.
     */
    public function download(string $fileOrContent, ?string $name = null, array $headers = []): bool 
    {
        return FileManager::download($fileOrContent, $name, $headers);
    }

    /**
     * Streaming large files.
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
    * Redirect to another url.
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