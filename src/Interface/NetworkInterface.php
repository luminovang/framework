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

use \Psr\Http\Client\ClientInterface;
use \Luminova\Interface\PromiseInterface;
use \Psr\Http\Message\{UriInterface, RequestInterface, ResponseInterface};
use \Luminova\Exceptions\Http\{ServerException, ClientException, ConnectException, RequestException};

interface NetworkInterface
{
    /**
     * Initializes the Network class with an optional HTTP client instance.
     *
     * @param ClientInterface<\T>|null $client The HTTP client to use for making requests.
     *                     If null, a `Novio` client will be used by default.
     *
     * > This constructor accepts request clients that implements PSR `ClientInterface`.
     * 
     * **Available Clients:**
     * 
     * - `\Luminova\Http\Client\Novio` - Luminova HTTP client.
     * - `\Luminova\Http\Client\Guzzle` - Guzzle HTTP client.
     */
    public function __construct(?ClientInterface $client = null);

    /**
     * Handle dynamic instance method calls for HTTP verbs.
     * 
     * Supports any valid HTTP method (e.g., `get`, `post`, `put`, `delete`, etc.)
     * and optionally async variants (e.g., `getAsync`, `postAsync`).
     * 
     * > Dynamically calls any method from the client object.
     * > This enables direct invocation of methods from either the `Novio` or `Guzzle` client.
     *
     * @param string $method The HTTP method name or async variant.
     * @param array $arguments First argument should be the URI or URL, second is optional options array.
     *
     * @return ResponseInterface<\T>|PromiseInterface<\T>|mixed Response object or a Promise for async requests.
     * 
     * @throws BadMethodCallException Throws if invalid parameters are passed.
     * @throws RequestException If an error occurs during the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters a 4xx HTTP error.
     * @throws ServerException If the server encounters a 5xx HTTP error.
     */
    public function __call(string $method, array $arguments): mixed;

    /**
     * Handle dynamic static method calls for HTTP verbs.
     *
     * @param string $method The HTTP method name or async variant.
     * @param array  $arguments First argument should be the URL or URI if base URL was set using `config`, 
     *                      second is optional options array.
     *
     * @return ResponseInterface<\T>|PromiseInterface<\T>|mixed Response object or a Promise for async requests.
     *
     * @throws BadMethodCallException Throws if invalid parameters are passed.
     * @throws RequestException If an error occurs during the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters a 4xx HTTP error.
     * @throws ServerException If the server encounters a 5xx HTTP error.
     * 
     * @example - Enables static calls like:
     * ```php
     * Network::get('https://api.example.com/user/100');
     * Network::postAsync('https://api.example.com', [...]);
     * ```
     * @example - Using base URL
     * 
     * ```php
     * Network::config(['base_uri' => 'https://example.com']);
     * 
     * Network::get('/user/100');
     * ```
     */
    public static function __callStatic(string $method, array $arguments): mixed;

    /**
     * Retrieves the current HTTP client instance.
     * 
     * This method returns the Luminova `Novio` class object if it is being used; otherwise, it returns the client interface object.
     *
     * @return ClientInterface<\T>|null Returns the instance of the HTTP client used for requests,
     * such as `Guzzle`, `Novio`, or any other HTTP client that implements the `PSR` ClientInterface, otherwise null.
     */
    public function getClient(): ?ClientInterface;

    /**
     * Define immutable global configurations for HTTP requests.
     *
     * This method sets global options such as `base_uri`, headers, or timeouts.
     * It must be called **before** making any network request (especially when using static methods),
     * as the configuration becomes locked after the first usage.
     *
     * @param array<string,mixed> $config Key-value pairs of request configuration options.
     * @return void
     *
     * @example - Setting a base URL for all static requests:
     *
     * ```php
     * Network::config([
     *     'base_uri' => 'https://example.com/'
     * ]);
     *
     * // Then, you can make relative requests:
     * Network::get('user/100/info');
     * ```
     *
     * > **Note:** This method is best suited for static usage, where a default base URL
     * or common settings (e.g., headers) are required across all requests.
     * Once the client is created, the config cannot be changed.
     */
    public static function config(array $config): void;

    /**
     * Executes a GET request to the specified URL with optional data and headers.
     *
     * @param string $uri The target URL or URI for the request.
     * @param array<string,mixed> $options Optional request options, such as headers or query parameters (e.g., `['headers' => [...]]`).
     *
     * @return ResponseInterface<\T> Return the server's response to the GET request.
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function get(UriInterface|string $uri = '', array $options = []): ResponseInterface;

    /**
     * Executes a POST request to the specified URL with the provided data and headers.
     *
     * @param UriInterface<\T>|string $uri The target URL or URI for the request.
     * @param array<string,mixed> $options Optional request options, such as headers or query parameters (e.g., `['headers' => [...]]`).
     *
     * @return ResponseInterface<\T> Return the server's response to the POST request.
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function post(UriInterface|string $uri = '', array $options = []): ResponseInterface;

    /**
     * Sends an HTTP request and returns a promise that resolves with the response.
     *
     * This method supports both asynchronous and synchronous requests. To disable 
     * asynchronous behavior, include the option `['async' => false]` in the `$options` array.
     *
     * @param UriInterface<\T>|string $uri The request URI object or string (default: '').
     * @param string $method The HTTP method to use (e.g., `GET`, `Luminova\Http\Method::POST`).
     * @param array<string,mixed> $options Optional request options, such as headers or query parameters (e.g., `['headers' => [...]]`).
     *
     * @return PromiseInterface<\T> Return a promise that resolves with the HTTP response.
     *
     * @example Example usage:
     * 
     * ```php
     * (new Network())
     *     ->fetch('https://example.com', 'GET', ['headers' => ['Accept' => 'application/json']])
     *     ->then(function (Psr\Http\Message\ResponseInterface $response) {
     *         echo $response->getBody()->getContents();
     *     })
     *     ->catch(function (Throwable $exception) {
     *         echo 'Error: ' . $exception->getMessage();
     *     });
     * ```
     */
    public function fetch(UriInterface|string $uri = '', string $method = 'GET', array $options = []): PromiseInterface;

    /**
     * Performs an HTTP request with the specified method, URL, data, and headers.
     *
     * @param string $method The HTTP method to use (e.g., `Luminova\Http\Method::GET`, `POST`).
     * @param UriInterface<\T>|string $uri The request URI object or string (default: '').
     * @param array<string,mixed> $options Optional request options, such as headers or query parameters (e.g., `['headers' => [...]]`).
     *
     * @return ResponseInterface<\T> Return the response returned by the server.
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function request(string $method, UriInterface|string $uri = '', array $options = []): ResponseInterface;

    /**
     * Sends an HTTP request using a request object.
     *
     * @param RequestInterface<\T> $request The request object that contains all necessary details for the request.
     * @param array<string,mixed> $options Request options to apply to the given request and to the transfer.
     *
     * @return ResponseInterface<\T> Return the response returned by the server.
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * Sends an HTTP request asynchronously using a request object.
     *
     * @param RequestInterface<\T> $request The request object that contains all necessary details for the request.
     * @param array<string,mixed> $options Request options to apply to the given request and to the transfer.
     *
     * @return PromiseInterface<\T> Return guzzle promise that resolves to the request response or Novio request response.
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface;

    /**
     * Sends an HTTP request asynchronously using a UriInterface object or string.
     *
     * @param string $method The HTTP method to use.
     * @param UriInterface<\T>|string $uri The request URI object or string (default: '').
     * @param array<string,mixed> $options Optional request options, such as headers or query parameters (e.g., `['headers' => [...]]`).
     * 
     * @return PromiseInterface<\T> Return guzzle promise that resolves to the request response or Novio request response.
     */
    public function requestAsync(string $method, UriInterface|string $uri = '', array $options = []): PromiseInterface;
}