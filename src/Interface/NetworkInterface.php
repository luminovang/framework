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

use \Luminova\Http\Message\Response;
use \Luminova\Interface\NetworkClientInterface;
use \Luminova\Http\Client\Curl;
use \GuzzleHttp\Promise\PromiseInterface;
use \Psr\Http\Client\ClientInterface;
use \Psr\Http\Message\RequestInterface;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;

interface NetworkInterface
{
    /**
     * Initializes the NetworkInterface with an optional HTTP client instance.
     *
     * @param NetworkClientInterface<\T>|null $client The HTTP client instance to use for making requests.
     *                                         If null, a `Curl` client will be used by default.
     *
     * This constructor accepts an implementation of the `NetworkClientInterface`. If no client is provided,
     * the `Curl` client will be instantiated and used by default. The `NetworkClientInterface` must be compatible
     * with the Luminova framework.
     * 
     * Available Clients:
     * 
     * - `\Luminova\Http\Client\Curl` - Luminova HTTP CURL client.
     * - `\Luminova\Http\Client\Guzzle` - Guzzle HTTP client.
     */
    public function __construct(?NetworkClientInterface $client = null);

    /**
     * Dynamically calls any method from the client object.
     * This enables direct invocation of methods from either the `CURL` or `Guzzle` client.
     *
     * @param string $method The name of the method to call.
     * @param array $arguments Optional arguments to pass to the method.
     *
     * @return mixed|Response|ResponseInterface<\T> Returns the result of the method call.
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
     * @return ClientInterface<\T>|Curl|null Returns the instance of the HTTP client used for requests,
     * such as `GuzzleHttpClient`, `LuminovaCurlClient`, or any other client that implements the `PSR` ClientInterface.
     */
    public function getClient(): ClientInterface|Curl|null;

    /**
     * Executes a GET request to the specified URL with optional data and headers.
     *
     * @param string $url The URL to send the GET request to.
     * @param array $data The data to be sent in the query string (default: empty array).
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return Response|ResponseInterface<\T> Return the server's response to the GET request.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function get(string $url, array $options = []): ResponseInterface|Response;

    /**
     * Executes a POST request to the specified URL with the provided data and headers.
     *
     * @param string $url The URL to send the POST request to.
     * @param array $data The data to be sent in the request body.
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return Response|ResponseInterface<\T> Return the server's response to the POST request.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function post(string $url, array $options = []): ResponseInterface|Response;

    /**
     * Performs an HTTP request with the specified method, URL, data, and headers.
     *
     * @param string $method The HTTP method to use (e.g., `GET`, `POST`, `PUT`, `DELETE`).
     * @param string $url The target URL for the request.
     * @param array $data The data to be sent in the request body (default: empty array).
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return Response|ResponseInterface<\T> Return the response returned by the server.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface|Response;

    /**
     * Sends an HTTP request asynchronously using a request object.
     *
     * @param RequestInterface<\T> $request The request object that contains all necessary details for the request.
     * @param array $options Request options to apply to the given
     *                       request and to the transfer. See \GuzzleHttp\RequestOptions.
     *
     * @return PromiseInterface<\T> Return a promise that resolves to the server's response.
     *
     * @throws RequestException Throws if an error occurs or when called with a method that is incompatible with the underlying HTTP client (e.g., Curl).
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface;

    /**
     * Sends an HTTP request asynchronously using a UriInterface object or string.
     *
     * Use an absolute path to override the base path of the client, or a
     * relative path to append to the base path of the client. The URL can
     * contain the query string as well. Use an array to provide a URL
     * template and additional variables to use in the URL template expansion.
     *
     * @param string $method The HTTP method to use.
     * @param UriInterface|string $uri The request URI object or string (default: '').
     * @param array $options Additional request options to apply (default: []). 
     * 
     * @see \GuzzleHttp\RequestOptions.
     * 
     * @return PromiseInterface<\T> Return a promise that resolves to the server's response.
     * @throws RequestException Throws if an error occurs or when called with a method that is incompatible with the underlying HTTP client (e.g., Curl).
     */
    public function requestAsync(string $method, UriInterface|string $uri = '', array $options = []): PromiseInterface;
}