<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

interface ViewResponseInterface
{
    /**
     * Set the HTTP status code.
     *
     * @param int $status HTTP status code.
     * 
     * @return self Return instance of the Response class.
     */
    public function setStatus(int $status): self;

    /**
     * Enable or disable content encoding.
     *
     * @param bool $encode Whether to enable content encoding like gzip.
     * 
     * @return self Instance of the Response class.
     */
    public function encode(bool $encode): self;

    /**
     * Enable or disable content minification.
     *
     * @param bool $minify Whether to minify the content.
     * 
     * @return self Instance of the Response class.
     */
    public function minify(bool $minify): self;

    /** 
     * Configure HTML code block minification and copy button.
     *
     * @param bool $minify Whether to minify code blocks.
     * @param bool $button Whether to add a copy button to code blocks (default: false).
     *
     * @return self Instance of the Response class.
     */
    public function codeblock(bool $minify, bool $button = false): self;

    /**
     * Set an HTTP header.
     *
     * @param string $key The header name.
     * @param mixed $value The header value.
     * 
     * @return self Instance of the Response class.
     */
    public function header(string $key, mixed $value): self;

    /**
     * Set multiple HTTP headers.
     *
     * @param array<string,mixed> $headers Associative array of headers.
     * 
     * @return self Instance of the Response class.
     */
    public function headers(array $headers): self;

    /**
     * Send HTTP response headers to the client.
     * 
     * This method sends the HTTP status code (if set) and all accumulated 
     * headers to the client.
     *
     * @return void
     */
    public function send(): void;

    /**
     * Get the current HTTP status code.
     * 
     * @return int Return the current HTTP status code.
     */
    public function getStatusCode(): int;

    /**
     * Retrieve a specific HTTP header.
     * 
     * @param string $name The name of the header.
     * 
     * @return mixed Return the header value, or null if it doesn't exist.
     */
    public function getHeader(string $name): mixed;

    /**
     * Retrieve all HTTP headers.
     * 
     * @return array<string,mixed> Return list of all HTTP headers.
     */
    public function getHeaders(): array;

    /**
     * Retrieves the HTTP protocol version (e.g, `1.0`, `1.1`).
     *
     * @return float Return the HTTP protocol version.
     */
    public function getProtocolVersion(): float;

    /**
     * Clear all previous HTTP headers.
     * 
     * @return void
     */
    public function clearHeaders(): void;
    
    /**
     * Clear previous set HTTP header redirects.
     * 
     *  @return bool Return true if any redirect was cleared, false otherwise.
     */
    public function clearRedirects(): bool;
    
    /**
     * Determine if the response headers has any redirects.
     * 
     * @return bool Return true if headers contain any redirect, otherwise false.
     */
    public function hasRedirects(): bool;

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
    ): int;

    /** 
     * Send the defined HTTP headers without any body.
     *
     * @return void
     */
    public function sendHeaders(): void;

    /**
     * Send the HTTP status header.
     * 
     * @return bool True if the status header was sent, false otherwise.
     */
    public function sendStatus(): bool;

    /**
     * Send a JSON response.
     *
     * @param array|object $content The data to encode as JSON.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     * @throws JsonException Throws if json error occurs.
     */
    public function json(array|object $content): int;

    /**
     * Send a plain text response.
     *
     * @param string $content The text content to send.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function text(string $content): int;

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content to send.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function html(string $content): int;

    /**
     * Send an XML response.
     *
     * @param string $content XML content to send.
     * 
     * @return int Response status code `STATUS_SUCCESS` if content was rendered, otherwise `STATUS_ERROR`.
     */
    public function xml(string $content): int;

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
    ): bool;

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
    ): bool;

    /** 
     * Redirect the client to a different URL location.
     *
     * @param string $uri  The target URI for the redirection.
     * @param string|null $method Optional. The redirection method (`refresh` or `null` for standard).
     * @param int|null $code Optional HTTP status code (e.g., `302`, `303`, `307`).
     *
     * @return void
     */
    public function redirect(string $uri, ?string $method = null, ?int $code = null): void;
}