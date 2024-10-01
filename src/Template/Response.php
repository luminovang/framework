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

use \Luminova\Interface\ViewResponseInterface;
use \Luminova\Storages\FileManager;
use \Luminova\Storages\FileDelivery;
use \Luminova\Optimization\Minification;
use \Luminova\Http\Header;
use \Luminova\Http\Encoder;
use \Luminova\Exceptions\JsonException;
use \Exception;

class Response implements ViewResponseInterface
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
     * {@inheritdoc}
     */
    public function setStatus(int $status): self 
    {
        $this->status = $status;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function encode(bool $encode): self 
    {
        $this->encode = $encode;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function minify(bool $minify): self 
    {
        $this->minify = $minify;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function codeblock(bool $minify, bool $button = false): self 
    {
        $this->minifyCodeblocks = $minify;
        $this->codeblockButton = $button;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function header(string $key, mixed $value): self 
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function headers(array $headers): self 
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function send(): void 
    {
        Header::sendStatus($this->status);
        Header::send($this->headers);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $name): mixed
    {
        return $this->headers[$name] ?? null;
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
    public function getProtocolVersion(): float
    {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? null;

        if ($protocol && preg_match('/^HTTP\/(\d+\.\d+)$/', $protocol, $matches)) {
            return (float) $matches[1];
        }

        return 1.0;
    }

    /**
     * {@inheritdoc}
     */
    public function clearHeaders(): void
    {
        $this->headers = [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function clearRedirects(): bool
    {
        if(isset($this->headers['Location'])) {
            unset($this->headers['Location']);
            return true;
        }
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function hasRedirects(): bool
    {
        return isset($this->headers['Location']);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function sendHeaders(): void 
    {
        Header::parseHeaders($this->headers, $this->status);
    }

    /**
     * {@inheritdoc}
     */
    public function sendStatus(): bool
    {
        return http_status_header($this->status);
    }

    /**
     * {@inheritdoc}
     */
    public function json(array|object $content): int 
    {
        try {
            $content = json_encode(
                is_object($content) ? (array) $content : $content, 
                JSON_THROW_ON_ERROR
            );

            return $this->render($content, $this->status, [
                'Content-Type' => 'application/json'
            ], $this->encode, $this->minify);
        }catch(Exception|\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }

        return STATUS_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function text(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'text/plain'
        ], $this->encode, $this->minify);
    }

    /**
     * {@inheritdoc}
     */
    public function html(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'text/html'
        ], $this->encode, $this->minify);
    }

    /**
     * {@inheritdoc}
     */
    public function xml(string $content): int 
    {
        return $this->render($content, $this->status, [
            'Content-Type' => 'application/xml'
        ], $this->encode, $this->minify);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function redirect(string $uri, ?string $method = null, ?int $code = null): void
    {
        if ($method === null && str_contains($_SERVER['SERVER_SOFTWARE'] ?? '', 'Microsoft-IIS')) {
            $method = 'refresh';
        } elseif ($method !== 'refresh' && $code === null) {
            if (
                isset($_SERVER['SERVER_PROTOCOL'], $_SERVER['REQUEST_METHOD'])
                && $this->getProtocolVersion() >= 1.1
            ) {
                $code = match ($_SERVER['REQUEST_METHOD']) {
                    'GET' => 302,
                    'POST', 'PUT', 'DELETE' => 303,
                    default => 307
                };
            }
        }

        $this->status = $code ?? 302;

        if ($method === 'refresh') {
            $this->header('Refresh', "0;url={$uri}")->send();
            exit(0);
        } 

        $this->header('Location', $uri)->send();
        exit(0);
    }
}