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

use \Luminova\Exceptions\JsonException;
use \Luminova\Exceptions\Http\ResponseException;

interface ViewResponseInterface
{
    /**
     * Set the HTTP status code for the response.
     *
     * @param int $status The HTTP status code to be set.
     * 
     * @return static Return instance of the Response class.
     */
    public function setStatus(int $status): self;

    /**
     * Enable or disable content compression using encoding (e.g., `gzip`, `deflate`).
     *
     * @param bool $compress Whether to compress content (default: true).
     * 
     * @return static Return instance of the Response class.
     */
    public function compress(bool $compress = true): self;

    /**
     * Enable or disable HTML content minification.
     * This overrides the environment variable `page.minification`.
     *
     * @param bool $minify Whether to minify the HTML content (default: true).
     * 
     * @return static Return instance of the Response class.
     */
    public function minify(bool $minify = true): self;

    /** 
     * Configure minification behavior for HTML code blocks and optional copy button.
     *
     * @param bool $minify Whether to exclude HTML code blocks from minification (default: false).
     * @param bool $button Whether to include a copy button in code blocks (default: false).
     * 
     * @return static Return instance of the Response class.
     */
    public function codeblock(bool $minify = true, bool $button = false): self;

    /**
     * Set an individual HTTP header.
     *
     * @param string $key The header name.
     * @param mixed $value The header value for name.
     * 
     * @return static Return instance of the Response class.
     */
    public function header(string $key, mixed $value): self;

    /**
     * Set multiple HTTP headers at once.
     *
     * @param array<string,mixed> $headers An associative array of headers.
     * 
     * @return static Return instance of the Response class.
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
     * Retrieve response metadata built by `content()`.
     * 
     * **Returned Array Keys:**
     * - `exit` (int): Response exit code (e.g., `STATUS_*`).
     * - `status` (int): HTTP status code to send.
     * - `headers` (array<string,mixed>): Validated response headers.
     * - `contents` (string): Processed response body.
     *
     * @return array<string,mixed>|null Response data previously built using `content()` or null.
     * @see content()
     * @see output()
     * 
     * @example - Example:
     * ```php
     * $response = new Response();
     * $response->content('<p>Hello world!</p>');
     * 
     * $result = $response->getResult();
     * echo $result['contents'];
     * ```
     */
    public function getResult(): ?array;

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
     * Mark the response as failed.
     * 
     * When a response is marked failed:
     * - Methods like `output()`, `render()`, `json()`, `html()`, etc., will return `STATUS_ERROR`
     *   instead of `STATUS_SUCCESS` or `STATUS_SILENCE`, even if content is empty or HTTP status is `204`.
     * - Essential for marking a failed middleware authentication, so routing system can terminate execution.
     *
     * @param bool $failed Whether to mark the response as failed (default: `true`).
     * 
     * @return static Return instance of the Response class.
     *
     * @example
     * ```php
     * $response = (new Response())
     *     ->failed()
     *     ->content(['message']);
     * 
     * $response->output(); // Returns STATUS_ERROR
     * ```
     * > **Note:** 
     * > Only use this method when handling middleware responses or when you want to explicitly return STATUS_ERROR.
     */
    public function failed(bool $failed = true): self;

    /**
     * Render and send response content along with optional headers.
     *
     * - Processes headers and content (minification, compression).
     * - Sends headers and outputs the response body.
     *
     * @param string $content Response content to render.
     * @param array<string,mixed> $headers Optional headers to send with the content.
     * 
     * @return int Returns `STATUS_SUCCESS` if content is sent, or `STATUS_SILENCE` for empty responses.
     * 
     * @throws ResponseException If any error occur while rendering response.
     */
    public function render(string $content, array $headers = []): int;

    /**
     * Build and process response content without sending it.
     *
     * - Converts non-string content to JSON automatically.
     * - Processes headers, minification, compression, and content length.
     * - Stores the result internally for later retrieval via `getResult()`.
     *
     * @param string|array|object $content Response body or data to convert to JSON.
     * @param array<string,mixed> $headers Optional headers to merge with defaults.
     * 
     * @return static Return instance of the Response class.
     * @throws ResponseException If processing fails.
     * @see getResult()
     * @see output()
     * 
     * @example - Example:
     * ```php
     * $response = new Response();
     * $response->content('<p>Hello world!</p>');
     * 
     * $result = $response->getResult();
     * echo $result['contents'];
     * ```
     */
    public function content(string|array|object $content, array $headers = []): self;

    /**
     * Send the processed response to the client.
     *
     * - Outputs HTTP status, headers, and body generated by `content()`.
     * - Clears output buffers before sending content.
     * - Optionally skips sending headers if they have already been sent.
     *
     * @param bool $ifHeaderNotSent If `true`, headers are only sent 
     *              if they **have not** already been sent (default: false).
     * 
     * @return int Response exit code: `STATUS_SUCCESS`, `STATUS_SILENCE`, or `STATUS_ERROR`.
     *
     * @see content()
     * @see failed()
     */
    public function output(bool $ifHeaderNotSent = false): int;

    /**
     * Send a JSON response.
     *
     * @param array|object $content An array or JSON object data to be encoded as JSON string.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     * 
     * @throws ResponseException If any error occur while rendering response.
     * @throws JsonException Throws if a JSON encoding error occurs.
     */
    public function json(array|object $content): int;

    /**
     * Send a plain text response.
     *
     * @param string $content Text content to send.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     * 
     * @throws ResponseException If any error occur while rendering response.
     */
    public function text(string $content): int;

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content to send.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     * 
     * @throws ResponseException If any error occur while rendering response.
     */
    public function html(string $content): int;

    /**
     * Send an XML response.
     *
     * @param string $content XML content to send.
     * 
     * @return int Return status code: `STATUS_SUCCESS` if successful, otherwise `STATUS_ERROR`.
     * 
     * @throws ResponseException If any error occur while rendering response.
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
     * @see Luminova\Utility\Storage\FileDelivery For more  advanced usage.
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
     * Supports standard HTTP redirects or a `refresh` header.
     * Make sure to use a valid status code between 300–308.
     *
     * @param string $uri The target URL to redirect to.
     * @param string|null $method Redirection method: (`refresh`) or `null` for standard redirect.
     *
     * @return void
     * @see Luminova\Funcs\redirect() Global helper function.
     * 
     * @example - Usage:
     * 
     * ```php
     * new Response(302)->redirect('users/1000')
     * ```
     * > When redirecting ensure you use HTTP status code between (`300` to  `308`).
     */
    public function redirect(string $uri, ?string $method = null): void;
}