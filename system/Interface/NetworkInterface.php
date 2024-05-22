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
use \Luminova\Interface\HttpClientInterface;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Psr7\Request;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;

interface NetworkInterface 
{
    /**
     * Constructor for NetworkInterface.
     *
     * @param HttpClientInterface|null $client The HTTP client instance.
     */
    public function __construct(?HttpClientInterface $client = null);

    /**
     * Get the HTTP client instance.
     * 
     * @return HttpClientInterface The HTTP client instance.
    */
    public function getClient(): HttpClientInterface;

    /**
     * Send a request.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $url The URL to send the request to.
     * @param array $data The request body data.
     * @param array $headers The request headers.
     *
     * @return Response The response from the request.
     *
     * @throws RequestException If there is an error making the request.
     * @throws ConnectException If there is an error connecting to the server.
     * @throws ClientException If there is an error on the client side.
     * @throws ServerException If there is an error on the server side.
     */
    public function send(string $method, string $url, array $data = [], array $headers = []): Response;

    /**
     * Perform a GET request.
     *
     * @param string $url The URL to send the request to.
     * @param array $data The request body data.
     * @param array $headers The request headers.
     *
     * @return Response The response from the request.
     *
     * @throws RequestException If there is an error making the request.
     * @throws ConnectException If there is an error connecting to the server.
     * @throws ClientException If there is an error on the client side.
     * @throws ServerException If there is an error on the server side.
     */
    public function get(string $url, array $data = [], array $headers = []): Response;

    /**
     * Fetch data using a GET request.
     *
     * @param string $url The URL to send the request to.
     * @param array $headers The request headers.
     *
     * @return Response The response from the request.
     *
     * @throws RequestException If there is an error making the request.
     * @throws ConnectException If there is an error connecting to the server.
     * @throws ClientException If there is an error on the client side.
     * @throws ServerException If there is an error on the server side.
     */
    public function fetch(string $url, array $headers = []): Response;

    /**
     * Perform a POST request.
     *
     * @param string $url The URL to send the request to.
     * @param array $data The request body data.
     * @param array $headers The request headers.
     *
     * @return Response The response from the request.
     *
     * @throws RequestException If there is an error making the request.
     * @throws ConnectException If there is an error connecting to the server.
     * @throws ClientException If there is an error on the client side.
     * @throws ServerException If there is an error on the server side.
     */
    public function post(string $url, array $data = [], array $headers = []): Response;

    /**
     * Perform a request.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $url The URL to send the request to.
     * @param array $data The request body data.
     * @param array $headers The request headers.
     *
     * @return Response The response from the request.
     *
     * @throws RequestException If there is an error making the request.
     * @throws ConnectException If there is an error connecting to the server.
     * @throws ClientException If there is an error on the client side.
     * @throws ServerException If there is an error on the server side.
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response;

    /**
     * Send a request asynchronously.
     *
     * @param Request $request The request object to send.
     *
     * @return PromiseInterface A promise that resolves with the response.
     * @throws RequestException Throws if called method while using Curl client.
     */
    public function sendAsync(Request $request): PromiseInterface;
}