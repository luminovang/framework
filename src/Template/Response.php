<?php 
/**
 * Class for handling HTTP responses.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Template;

use \WeakMap;
use \Exception;
use \Luminova\Utils\WeakReference;
use \Luminova\Http\{Header, Encoder};
use \Luminova\Exceptions\JsonException;
use \Luminova\Optimization\Minification;
use \Luminova\Interface\ViewResponseInterface;
use \Luminova\Storages\{FileManager, FileDelivery};
use function \Luminova\Funcs\{string_length, http_status_header};

class Response implements ViewResponseInterface
{
    /**
     * Indicates if the response content should be minified.
     * 
     * @var bool $minify
     */
    private bool $minify = false;

    /**
     * Weak reference map.
     * 
     * @var WeakMap|null $weak
     */
    private static ?WeakMap $weak = null;

    /**
     * Weak reference object key.
     * 
     * @var WeakReference|null $reference
     */
    private static ?WeakReference $reference = null;

    /**
     * Initializes the template content output response.
     *
     * @param int $status HTTP status code (default: 200 OK).
     * @param array<string,mixed> $headers Optional HTTP headers as key-value pairs.
     * @param bool $compress Whether to apply content compression (e.g., `gzip`, `deflate`), default is false.
     * @param bool $minifyCodeblocks Whether to exclude HTML code blocks from minification (default: false).
     * @param bool $codeblockButton Whether to automatically add a copy button to code blocks after minification (default: false).
     * 
     * > Note: If the `minify` method is not explicitly invoked, the environment variable `page.minification` will determine whether HTML content should be minified.
     */
    public function __construct(
        private int $status = 200, 
        private array $headers = [],
        private bool $compress = false,
        private bool $minifyCodeblocks = false,
        private bool $codeblockButton = false
    )
    {
        $this->minify = (bool) env('page.minification', false);
        self::$reference = new WeakReference();
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
    public function compress(bool $compress = true): self 
    {
        $this->compress = $compress;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function minify(bool $minify = true): self 
    {
        $this->minify = $minify;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function codeblock(bool $minify = true, bool $button = false): self 
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
    public function send(bool $validate = false): void 
    {
        if($validate){
            Header::validate($this->headers, $this->status);
        }else{
            Header::sendStatus($this->status);
            Header::send($this->headers);
        }

        Header::clearOutputBuffers();
    }

    /**
     * {@inheritdoc}
     */
    public function sendStatus(): bool
    {
        return $this->status >= 100 
            && $this->status < 600 
            && http_status_header($this->status);
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
    public function render(string $content, array $headers = []): int
    {
        $length = 0;
        $headers = ($headers === []) 
            ? $this->headers : 
            array_merge($this->headers, $headers);

        if(!isset($headers['Content-Type'])){
            $headers['Content-Type'] = 'application/json';
        }
       
        if($content !== '' && $this->minify && str_contains($headers['Content-Type'], 'text/html')){
            self::$weak[self::$reference] ??= new Minification();
            $content = self::$weak[self::$reference]->codeblocks($this->minifyCodeblocks)
                ->copyable($this->codeblockButton)
                ->compress($content, $headers['Content-Type']);

            $content = self::$weak[self::$reference]->getContent();
            $length = self::$weak[self::$reference]->getLength();
        }

        if($content !== '' && $this->compress){
            [$encoding, $content, $length] = Encoder::encode($content);

            if($encoding !== false){
                $headers['Content-Encoding'] = $encoding;
            }
        }

        if($length === 0){
            if($content === ''){
                $this->status = 204;
            }else{
                $length = $this->calculateContentLength($content, $headers['Content-Type']);
            }
        }

        $headers['default_headers'] = true;
        $headers['Content-Length'] = $length;

        Header::validate($headers, $this->status);
        Header::clearOutputBuffers();

        if($content === '' || $this->status === 204){
            return STATUS_SILENCE;
        }

        echo $content;

        return STATUS_SUCCESS;
    }

    /**
     * {@inheritdoc}
     */
    public function json(array|object $content): int 
    {
        try {
            return $this->render(
                json_encode(is_object($content) ? (array) $content : $content, JSON_THROW_ON_ERROR), 
                ['Content-Type' => 'application/json']
            );
        }catch(Exception|\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }

        return STATUS_SILENCE;
    }

    /**
     * {@inheritdoc}
     */
    public function text(string $content): int 
    {
        return $this->render($content, [
            'Content-Type' => 'text/plain'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function html(string $content): int 
    {
        return $this->render($content, [
            'Content-Type' => 'text/html'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function xml(string $content): int 
    {
        return $this->render($content, [
            'Content-Type' => 'application/xml'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function download(
        string $fileOrContent, 
        ?string $name = null, 
        array $headers = [],
        int $chunkSize = 8192,
        int $delay = 0
    ): bool 
    {
        return FileManager::download(
            $fileOrContent, 
            $name, 
            $headers,
            $chunkSize, 
            $delay
        );
    }

    /**
     * {@inheritdoc}
     */
    public function stream(
        string $path, 
        string $basename, 
        array $headers = [],
        bool $eTag = true,
        bool $weakEtag = false,
        int $expiry = 0,
        int $length = (1 << 21),
        int $delay = 0
        ): bool 
    {
        return (new FileDelivery($path, $eTag, $weakEtag))
            ->output($basename, $expiry, $headers, $length, $delay);
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

    /**
     * Get content length based on header charset or application charset.
     * 
     * @param string $content The content to calculate.
     * @param string $contentType Header content type.
     * 
     * @return int Return calculated content length.
     */
    protected function calculateContentLength(string $content, string $contentType): int 
    {
        $charset = null;
        if (
            str_contains($contentType, 'charset=') &&
            preg_match('/charset\s*=\s*["\']?([\w\-]+)["\']?/i', $contentType, $matches)
        ) {
            $charset = $matches[1];
        }

        return string_length($content, $charset);
    }
}