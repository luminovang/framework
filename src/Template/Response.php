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

use \Throwable;
use \Luminova\Component\Seo\Minifier;
use \Luminova\Exceptions\JsonException;
use \Luminova\Http\{Header, Helper\Encoder};
use \Luminova\Interface\ViewResponseInterface;
use \Luminova\Exceptions\Http\ResponseException;
use \Luminova\Utility\Storage\{Filesystem, FileDelivery};
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
     * Response result.
     * 
     * @var array<string,mixed> $result
     */
    private ?array $result = null;

    /**
     * Mark response as failed.
     * 
     * @var bool $failed
     */
    private bool $failed = false;

    /**
     * Shared object.
     * 
     * @var ViewResponseInterface|null $instance
     */
    private static ?ViewResponseInterface $instance = null;

    /**
     * Initialize a response object with optional content, headers, and processing settings.
     *
     * - Sets the HTTP status code.
     * - Optionally applies content compression and HTML minification.
     * - Can automatically add copy buttons to code blocks after minification.
     * - If `$content` is provided, it will be processed immediately via `content()`.
     *
     * @param int $status HTTP status code (default: 200 OK).
     * @param array<string,mixed> $headers Optional headers as key-value pairs.
     * @param bool $compress Whether to apply content compression (`gzip`, `deflate`), default false.
     * @param bool $minifyCodeblocks Exclude HTML code blocks from minification (default false).
     * @param bool $codeblockButton Add a copy button to minified code blocks (default false).
     * @param string|array|object|null $content Optional content to process immediately.
     *
     * @throws ResponseException If content is not `null` and error was encountered while processing.
     * 
     * > **Note:** 
     * > If the `minify` method is not explicitly invoked, 
     * > the environment variable `page.minification` determines HTML minification.
     */
    public function __construct(
        private int $status = 200, 
        private array $headers = [],
        private bool $compress = false,
        private bool $minifyCodeblocks = false,
        private bool $codeblockButton = false,
        string|array|object|null $content = null
    )
    {
        $this->minify = (bool) env('page.minification', false);

        if($content !== null){
            $this->content($content);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getInstance(
        int $status = 200,
        array $headers = [],
        bool $compress = false,
        bool $minifyCodeblocks = false,
        bool $codeblockButton = false,
        string|array|object|null $content = null
    ): self 
    {
        if (!self::$instance instanceof ViewResponseInterface) {
            self::$instance = new self(
                $status,
                $headers,
                $compress,
                $minifyCodeblocks,
                $codeblockButton,
                $content
            );
        }

        return self::$instance;
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
        Header::clearOutputBuffers('all');
        Header::setOutputHandler(withHandler: false);

        if($validate){
            Header::validate($this->headers, $this->status);
        }else{
            Header::sendStatus($this->status);
            Header::send(array_replace(Header::getDefault(), $this->headers));
        }
        Header::clearOutputBuffers('all');
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
    public function getResult(): ?array
    {
        return $this->result;
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
    public function failed(bool $failed = true): self 
    {
        $this->failed = $failed;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function content(string|array|object $content, array $headers = []): self
    {
        if(!is_string($content)){
            $content = $this->toJson($content);

            if(empty($headers['Content-Type'])){
                $headers['Content-Type'] = 'application/json';
            }
        }

        [$headers, $contents] = $this->process($content, $headers);
        $isNoContent = ($contents === '' || $this->status === 204 || $this->status === 304);

        $this->result = [
            'exit' => $this->failed 
                ? STATUS_ERROR 
                : ($isNoContent ? STATUS_SILENCE : STATUS_SUCCESS),
            'status' => $this->status,
            'headers' => Header::response($headers),
            'contents' => $isNoContent ? '' : $contents
        ];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function output(bool $ifHeaderNotSent = false): int 
    {
        if(!$this->result){
            return STATUS_ERROR;
        }

        $status = $this->status;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $isNoContent = $method === 'HEAD';

        if (
            !$isNoContent && 
            $this->result['contents'] === '' && 
            $this->status !== 204 && 
            $this->status !== 304
        ) {
            $isNoContent = true;
            $status = 204;
        }

        if (!$ifHeaderNotSent || ($ifHeaderNotSent && headers_sent())) {
            Header::sendStatus($status);

            if($isNoContent){
                unset(
                    $this->result['headers']['Content-Type'], 
                    $this->result['headers']['Content-Length']
                );
            }

            foreach($this->result['headers'] as $header => $value){
                header("{$header}: {$value}");
            }
        }
       
        Header::clearOutputBuffers('all');

        if($isNoContent){
            return $this->result['exit'];
        }
         
        Header::setOutputHandler(true);
        echo $this->result['contents'];

        return $this->result['exit'];
    }

    /**
     * {@inheritdoc}
     */
    public function render(string $content, array $headers = []): int
    {
        [$headers, $contents] = $this->process($content, $headers);

        Header::validate($headers, $this->status);
        Header::clearOutputBuffers('all');

        if($contents === '' || $this->status === 204 || $this->status === 304){
            return $this->failed ? STATUS_ERROR : STATUS_SILENCE;
        }

        Header::setOutputHandler(true);
        echo $contents;
        return $this->failed ? STATUS_ERROR : STATUS_SILENCE;
    }

    /**
     * {@inheritdoc}
     */
    public function json(array|object $content): int 
    {
        return $this->render($this->toJson($content), [
            'Content-Type' => 'application/json'
        ]);
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
        return Filesystem::download(
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
    public function redirect(string $uri, ?string $method = null): void
    {
        if ($this->status < 300 || $this->status > 308) {
            $this->status = 0;
        }

        if ($method === null && str_contains($_SERVER['SERVER_SOFTWARE'] ?? '', 'Microsoft-IIS')) {
            $method = 'refresh';
        } elseif ($method !== 'refresh' && $this->status === 0) {
            $httpMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $protocol = $_SERVER['SERVER_PROTOCOL'] ?? null;

            if ($protocol && $httpMethod && $this->getProtocolVersion() >= 1.1) {
                $this->status = match ($httpMethod) {
                    'GET' => 302,
                    'POST', 'PUT', 'DELETE' => 303,
                    default => 307,
                };
            }
        }

        $this->status = $this->status ?: 302;

        if ($method === 'refresh') {
            $this->header('Refresh', "0;url={$uri}")->send();
        } else {
            $this->header('Location', $uri)->send();
        }

        exit(0);
    }

    /**
     * Detect the appropriate `Content-Type` header based on the provided content body.
     *
     * This method attempts to infer the MIME type of a response body using basic heuristics.
     * It's especially useful when no explicit content type is provided and you want a best-guess detection.
     *
     * @param mixed $body The content to inspect and classify.
     *
     * @return string Return the detected MIME type (e.g. `application/json`, `text/html`, `text/css`, etc).
     * @internal
     */
    public static function detectContentType(mixed $body): string
    {
        if (is_array($body) || is_object($body)) {
            return 'application/json';
        }

        if (!is_string($body)) {
            return 'application/octet-stream';
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            return 'text/plain';
        }

        if (
            (($trimmed[0] === '{' && str_ends_with($trimmed, '}')) ||
            ($trimmed[0] === '[' && str_ends_with($trimmed, ']')))
            && json_validate($trimmed)
        ) {
            return 'application/json';
        }

        if (
            str_starts_with($trimmed, '<?xml') ||
            preg_match('/^<(\w+:)?[\w-]+(\s+[^>]*)?>/', $trimmed)
        ) {
            return 'application/xml';
        }

        if (
            str_contains($trimmed, '<html') ||
            str_contains($trimmed, '<head>') ||
            str_contains($trimmed, '<body>') ||
            str_contains($trimmed, '<!DOCTYPE html') ||
            preg_match('/<!DOCTYPE\s+html\b/i', $trimmed)
        ) {
            return 'text/html';
        }

        if (
            preg_match('/\b(?:@import|@media|@keyframes|#[a-f\d]+\s*\{)/i', $trimmed) ||
            (
                str_contains($trimmed, ':') &&
                str_contains($trimmed, '{') &&
                str_contains($trimmed, '}')
            ) ||
            preg_match('/^[\s\S]*{[\s\S]*}$/', $trimmed)
        ) {
            return 'text/css';
        }

        if (
            preg_match('/\b(?:function\s*\w+|const\s+\w+\s*=|let\s+\w+\s*=|var\s+\w+\s*=|=>|import\s+|export\s+)/', $trimmed)
        ) {
            return 'application/javascript';
        }

        if (
            str_contains($trimmed, '<svg') &&
            str_contains($trimmed, 'xmlns="http://www.w3.org/2000/svg"')
        ) {
            return 'image/svg+xml';
        }

        $magic = substr($trimmed, 0, 2);

        if ($magic === "\xFF\xD8") {
            return 'image/jpeg';
        }

        if ($magic === "\x89\x50") {
            return 'image/png';
        }

        if ($magic === "BM") {
            return 'image/bmp';
        }

        if ($magic === "GI") {
            return 'image/gif';
        }

        if (
            preg_match('/^([^\n]+,)+[^\n]+$/m', $trimmed) ||
            preg_match('/^([^\n]+\t)+[^\n]+$/m', $trimmed)
        ) {
            return 'text/csv';
        }

        return 'text/plain';
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

    /**
     * Converts an array or object to JSON with the appropriate content type header.
     *
     * @param array|object $content Data to encode as JSON.
     * 
     * @return string Return JSON string
     * @throws JsonException If encoding fails.
     */
    private function toJson(array|object $content): string 
    {
        try {
            return json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }catch(Throwable $e){
            if($e instanceof \JsonException){
                throw new JsonException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }
    }

    /**
     * Process contents.
     * 
     * It processes response content and headers, applying minification, compression,
     * and setting required defaults like Content-Type and Content-Length.
     *
     * @param string $content Response body content.
     * @param array<string,mixed> $headers Additional headers to merge with defaults.
     * 
     * @return array{0:array<string,mixed>,1:string} [processed headers, processed content]
     * @throws ResponseException If processing fails.
     */
    private function process(string $content, array $headers = []): array
    {
        $length = 0;
        $headers = ($headers === []) 
            ? $this->headers : 
            array_merge($this->headers, $headers);

        if(empty($headers['Content-Type'])){
            $headers['Content-Type'] = self::detectContentType($content);
        }
       
        try{
            if($content !== '' && $this->minify && str_contains($headers['Content-Type'], 'text/html')){
                $minifier = (new Minifier())->codeblocks($this->minifyCodeblocks)
                    ->copyable($this->codeblockButton)
                    ->compress($content, $headers['Content-Type']);

                $content = $minifier->getContent();
                $length = $minifier->getLength();
            }

            if($content !== '' && $this->compress){
                [$encoding, $content, $length] = Encoder::encode($content);

                if($encoding !== false){
                    $headers['Content-Encoding'] = $encoding;
                }
            }

            if($length === 0 && $content !== ''){
                $length = $this->calculateContentLength($content, $headers['Content-Type']);
            }

            if($content === ''){
                $this->status = 204;
            }

            $headers['X-System-Default-Headers'] = true;
            $headers['Content-Length'] = $length;

            if($content === '' || $this->status === 204 || $this->status === 304){
                unset($headers['Content-Type'], $headers['Content-Length']);

                return [$headers, ''];
            }

            return [$headers, $content];
        }catch(Throwable $e){
            throw new ResponseException($e->getMessage(), $e->getCode(), $e);
        }
    }
}