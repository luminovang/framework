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

use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\RequestInterface;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Client\ClientInterface as PsrClientInterface;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;

interface ClientInterface extends PsrClientInterface
{
    /**
     * Initialize the HTTP request client constructor.
     * 
     * @param array<string,mixed> $config Option default client request configuration.
     * 
     * > When configuration is passed to the constructor, request methods cannot override it value.
     * 
     * @link https://luminova.ng/docs/3.4.0/http/network#lmv-docs-usage-examples
     */
    public function __construct(array $config = []);

    /**
     * Retrieve the original HTTP request client object.
     * 
     * @return \T<\Psr\Http\Client\ClientInterface> Return instance of request client object.
    */
    public function getClient(): PsrClientInterface;

    /**
     * Retrieve configuration option from client object.
     * 
     * @param string|null $option The option name to return (default: null).
     * 
     * @return mixed Return configuration option based on option name, or return all if option is null, otherwise null.
    */
    public function getConfig(?string $option = null): mixed;

    /**
     * Set a custom cURL option and it's value.
     * 
     * @param int $option The curl option identifier (e.g, `CURLOPT_USERAGENT`).
     * @param mixed $value The curl client option value.
     * 
     * @return self Return instance of cURL client class.
     * 
     * > The options will be ignored if used with `Guzzle` client.
     */
    public function setOption(int $option, mixed $value): self;

    /**
     * Sends a request and returns response.
     *
     * @param \T<RequestInterface> $request Instance of HTTP request object.
     *
     * @return ResponseInterface Return instance of HTTP response object.
     * @throws ClientException If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;

    /**
     * Sends an HTTP request asynchronously to the specified URI.
     *
     * Builds the request using the provided HTTP request object and optional configurations.
     *
     * @param \T<RequestInterface> $request The HTTP request object containing request details.
     * @param array<string,mixed> $options Optional parameters to customize request behavior (e.g., headers, timeout).
     *
     * @return PromiseInterface Return guzzle promise that resolves to the request response or cURL request response object.
     *
     * @throws RequestException If the request format is invalid or fails.
     * @throws ConnectException If the server cannot be reached.
     * @throws ClientException For 4xx client-side HTTP errors.
     * @throws ServerException For 5xx server-side HTTP errors.
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface;

    /**
     * Initiates an HTTP request asynchronously with a specified method and URI.
     *
     * @param string $method The HTTP method to use (e.g., `GET`, `POST`).
     * @param UriInterface|string $uri The target URI or URL for the request (absolute or relative).
     * @param array<string,mixed> $options Optional configurations to adjust the request behavior (e.g., headers, data, timeout).
     *
     * @return PromiseInterface Return guzzle promise that resolves to the request response or cURL request response object.
     *
     * @throws RequestException If the request is improperly formatted or fails.
     * @throws ConnectException If the server is unreachable.
     * @throws ClientException For client-side HTTP errors (4xx responses).
     * @throws ServerException For server-side HTTP errors (5xx responses).
     */
    public function requestAsync(string $method, UriInterface|string $uri = '', array $options = []): PromiseInterface;

    /**
     * Sends an HTTP request synchronously to the specified URI.
     *
     * Uses the provided HTTP request object and optional settings to perform the request.
     *
     * @param \T<RequestInterface> $request The HTTP request object containing request details.
     * @param array<string,mixed> $options Optional parameters to customize the request (e.g., headers, timeout).
     *
     * @return ResponseInterface Return guzzle promise that resolves to the request response or cURL request response object.
     *
     * @throws RequestException If the request format is invalid or fails.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException For 4xx client-side HTTP errors.
     * @throws ServerException For 5xx server-side HTTP errors.
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * Performs a synchronous HTTP request to a specified URI.
     *
     * Executes the request using the specified method and URI, with optional configurations to modify its behavior.
     *
     * @param string $method The HTTP method to use (e.g., `GET`, `POST`).
     * @param \T<UriInterface>|string $uri The target URI or URL for the request (absolute or relative).
     * @param array<string,mixed> $options Optional configurations to adjust the request behavior (e.g., headers, data, timeout).
     *
     * @return ResponseInterface Return the psr response interface object or cURL response object depending on client.
     *
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws RequestException For request formatting, timeout, or protocol issues.
     * @throws ClientException For client-side HTTP errors (4xx responses).
     * @throws ServerException For server-side HTTP errors (5xx responses).
     *
     * @link https://luminova.ng/docs/0.0.0/http/network Documentation for network operations.
     */
    public function request(string $method, UriInterface|string $uri = '', array $options = []): ResponseInterface;
} 