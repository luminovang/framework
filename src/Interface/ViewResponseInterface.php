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

interface ViewResponseInterface
{
    /**
     * Set the HTTP status code for the response.
     *
     * @param int $status The HTTP status code to be set.
     * 
     * @return self Return instance of the Response class.
     */
    public function setStatus(int $status): self;

    /**
     * Enable or disable content compression using encoding (e.g., `gzip`, `deflate`).
     *
     * @param bool $compress Whether to compress content (default: true).
     * 
     * @return self Return instance of the Response class.
     */
    public function compress(bool $compress = true): self;

    /**
     * Enable or disable HTML content minification.
     * This overrides the environment variable `page.minification`.
     *
     * @param bool $minify Whether to minify the HTML content (default: true).
     * 
     * @return self Return instance of the Response class.
     */
    public function minify(bool $minify = true): self;

    /** 
     * Configure minification behavior for HTML code blocks and optional copy button.
     *
     * @param bool $minify Whether to exclude HTML code blocks from minification (default: false).
     * @param bool $button Whether to include a copy button in code blocks (default: false).
     * 
     * @return self Return instance of the Response class.
     */
    public function codeblock(bool $minify = true, bool $button = false): self;

    /**
     * Set an individual HTTP header.
     *
     * @param string $key The header name.
     * @param mixed $value The header value for name.
     * 
     * @return self Return instance of the Response class.
     */
    public function header(string $key, mixed $value): self;

    /**
     * Set multiple HTTP headers at once.
     *
     * @param array<string,mixed> $headers An associative array of headers.
     * 
     * @return self Return instance of the Response class.
     */
    public function headers(array $headers): self;

    /**
     * Send all response headers and the status code to the client without content body.
     * Optionally validate the against REST Api headers based on `App\Config\Apis` if `$validate` is set to true.
     * 
     * @param bool $validate Whether to apply APIs headers validations (default: false).
     * 
     * @return void
     */
    public function send(bool $validate = false): void;

    /**
     * Send the HTTP status code header with the corresponding status message.
     * Additionally, it sends the 'Status' header for compatibility with older clients.
     * 
     * @return bool Returns true if the status header is valid and successfully sent, otherwise false.
     */
    public function sendStatus(): bool;

    /**
     * Get the current HTTP status code.
     * 
     * @return int Return the current HTTP status code.
     */
    public function getStatusCode(): int;

    /**
     * Retrieve the value of a specific HTTP header.
     * 
     * @param string $name The header name.
     * 
     * @return mixed Return the header value, or null if not found.
     */
    public function getHeader(string $name): mixed;

    /**
     * Retrieve all set HTTP headers.
     * 
     * @return array<string,mixed> Return an array of all headers.
     */
    public function getHeaders(): array;

    /**
     * Get the HTTP protocol version being used (e.g., `1.0`, `1.1`).
     *
     * @return float Return the HTTP protocol version.
     */
    public function getProtocolVersion(): float;

    /**
     * Clear all previously set HTTP headers.
     * 
     * @return void
     */
    public function clearHeaders(): void;

    /**
     * Clear any redirects set in the response headers.
     * 
     * @return bool Return true if any redirects were cleared, false otherwise.
     */
    public function clearRedirects(): bool;

    /**
     * Check if the response headers contain any redirects.
     * 
     * @return bool Return true if redirects are set, false otherwise.
     */
    public function hasRedirects(): bool;

    /**
     * Render and output any type response content along with additional optional headers.
     *
     * @param string $content The response content to render.
     * @param array<string,mixed> $headers Additional headers to send with the content.
     * 
     * @return int Return the status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     */
    public function render(string $content, array $headers = []): int;

    /**
     * Send a JSON response.
     *
     * @param array|object $content An array or JSON object data to be encoded as JSON string.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     * @throws \Luminova\Exceptions\JsonException Throws if a JSON encoding error occurs.
     */
    public function json(array|object $content): int;

    /**
     * Send a plain text response.
     *
     * @param string $content Text content to send.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     */
    public function text(string $content): int;

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content to send.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     */
    public function html(string $content): int;

    /**
     * Send an XML response.
     *
     * @param string $content XML content to send.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     */
    public function xml(string $content): int;

    /**
     * Send a file or content as a browser download.
     *
     * @param string $fileOrContent The file path or content for download.
     * @param string|null $name Optional name for the downloaded file.
     * @param array $headers Optional download headers.
     * @param int $chunk_size The size of each chunk in bytes for large content (default: 8192, 8KB).
     * @param int $delay The delay between each chunk in microseconds (default: 0).
     * 
     * @return bool Return true if the download was successful, false otherwise.
     */
    public function download(
        string $fileOrContent, 
        ?string $name = null, 
        array $headers = [],
        int $chunkSize = 8192,
        int $delay = 0
    ): bool;

    /**
     * Stream output any file or large files to the client.
     *
     * @param string $path File directory location (e.g., `/writeable/storage/images/`).
     * @param string $basename The file name (e.g., `image.png`).
     * @param array $headers Optional output headers.
     * @param bool $eTag Whether to generate ETag headers (default: true).
     * @param bool $weakEtag Whether to use a weak ETag header or string (default: false).
     * @param int $expiry Enable cache expiry time in seconds, 0 for no cache (default: 0).
     * @param int $length Optional size of each chunk to be read (default: 2MB).
     * @param int $delay Optional delay in microseconds between chunk length (default: 0).
     * 
     * @return bool Return true if file streaming was successful, false otherwise.
     * @see Luminova\Storages\FileDelivery For more  advanced usage.
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
    ): bool;

    /** 
     * Redirect the client to a new URL.
     *
     * @param string $uri The target URI.
     * @param string|null $method Optional redirection method (`refresh` or standard null).
     * @param int|null $code Optional HTTP status code (e.g., `302`, `303`, `307`).
     * 
     * @return void
     */
    public function redirect(string $uri, ?string $method = null, ?int $code = null): void;
}