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

use \Psr\Http\Client\ClientInterface;
use \Psr\Http\Message\RequestInterface;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;

interface NetworkInterface
{
    /**
     * Initializes the Network class with an optional HTTP client instance.
     *
     * @param ClientInterface<\T>|null $client The HTTP client to use for making requests.
     *                                         If null, a `Curl` client will be used by default.
     *
     * This constructor accepts request clients that implements PSR `ClientInterface`.
     * 
     * **Available Clients:**
     * 
     * - `\Luminova\Http\Client\Curl` - Luminova HTTP CURL client.
     * - `\Luminova\Http\Client\Guzzle` - Guzzle HTTP client.
     */
    public function __construct(?ClientInterface $client = null);

    /**
     * Dynamically calls any method from the client object.
     * This enables direct invocation of methods from either the `CURL` or `Guzzle` client.
     *
     * @param string $method The name of the method to call.
     * @param array $arguments Optional arguments to pass to the method.
     *
     * @return ResponseInterface<\T>|PromiseInterface|mixed Returns the result of the method call.
     * 
     * @throws BadMethodCallException Throws if invalid parameters are passed.
     * @throws RequestException If an error occurs during the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters a 4xx HTTP error.
     * @throws ServerException If the server encounters a 5xx HTTP error.
     */
    public function __call(string $method, array $arguments): mixed;

    /**
     * Retrieves the current HTTP client instance.
     * 
     * This method returns the Luminova `CURL` class object if it is being used; otherwise, it returns the client interface object.
     *
     * @return ClientInterface<\T>|null Returns the instance of the HTTP client used for requests,
     * such as `GuzzleHttpClient`, `LuminovaCurlClient`, or any other client that implements the `PSR` ClientInterface, otherwise null.
     */
    public function getClient(): ?ClientInterface;

    /**
     * Executes a GET request to the specified URL with optional data and headers.
     *
     * @param string $uri The target URL or URI for the request.
     * @param array $data The data to be sent in the query string (default: empty array).
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return ResponseInterface<\T> Return the server's response to the GET request.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function get(string $uri = '', array $options = []): ResponseInterface;

    /**
     * Executes a POST request to the specified URL with the provided data and headers.
     *
     * @param string $uri The target URL or URI for the request.
     * @param array $data The data to be sent in the request body.
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return ResponseInterface<\T> Return the server's response to the POST request.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function post(string $uri = '', array $options = []): ResponseInterface;

   /**
     * Sends an HTTP request and returns a promise that resolves with the response.
     *
     * This method supports both asynchronous and synchronous requests. To disable 
     * asynchronous behavior, include the option `['async' => false]` in the `$options` array.
     *
     * @param UriInterface|string $uri The request URI object or string (default: '').
     * @param string $method The HTTP method to use (e.g., `GET`, `POST`, `PUT`, `DELETE`).
     * @param array $options Optional request options, such as headers or query parameters (e.g., `['headers' => [...]]`).
     *
     * @return PromiseInterface<\T> A promise that resolves with the HTTP response.
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
    public function fetch(
        UriInterface|string $uri = '', 
        string $method = 'GET', 
        array $options = []
    ): PromiseInterface;

    /**
     * Performs an HTTP request with the specified method, URL, data, and headers.
     *
     * @param string $method The HTTP method to use (e.g., `GET`, `POST`, `PUT`, `DELETE`).
     * @param UriInterface|string $uri The request URI object or string (default: '').
     * @param array $data The data to be sent in the request body (default: empty array).
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return ResponseInterface<\T> Return the response returned by the server.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function request(
        string $method, 
        UriInterface|string $uri = '', 
        array $options = []
    ): ResponseInterface;

    /**
     * Sends an HTTP request using a request object.
     *
     * @param RequestInterface<\T> $request The request object that contains all necessary details for the request.
     * @param array<string,mixed> $options Request options to apply to the given request and to the transfer.
     *
     *  @return ResponseInterface<\T> Return the response returned by the server.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function send(
        RequestInterface $request, 
        array $options = []
    ): ResponseInterface;

    /**
     * Sends an HTTP request asynchronously using a request object.
     *
     * @param RequestInterface<\T> $request The request object that contains all necessary details for the request.
     * @param array<string,mixed> $options Request options to apply to the given request and to the transfer.
     *
     * @return PromiseInterface<\T> Return guzzle promise that resolves to the request response or cURL request response.
     */
    public function sendAsync(
        RequestInterface $request, 
        array $options = []
    ): PromiseInterface;

    /**
     * Sends an HTTP request asynchronously using a UriInterface object or string.
     *
     * @param string $method The HTTP method to use.
     * @param UriInterface|string $uri The request URI object or string (default: '').
     * @param array $options Additional request options to apply (default: []). 
     * 
     * @return PromiseInterface<\T> Return guzzle promise that resolves to the request response or cURL request response.
     */
    public function requestAsync(
        string $method, 
        UriInterface|string $uri = '', 
        array $options = []
    ): PromiseInterface;
}