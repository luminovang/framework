<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Http;

use \Luminova\Http\NetworkClientInterface;
use \Luminova\Http\NetworkResponse;

class Network
{
    /**
     * @var NetworkClientInterface
     */
    private NetworkClientInterface $client;

    /**
     * Network constructor with http client instance 
     *
     * @param NetworkClientInterface $client 
     */
    public function __construct(NetworkClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Send a request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return NetworkResponse
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function send(string $method, string $url, array $data = [], array $headers = []): NetworkResponse
    {
        return $this->client->request($method, $url, $data, $headers);
    }

    /**
     * Perform a GET request.
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return NetworkResponse
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function get(string $url, array $data = [], array $headers = []): NetworkResponse
    {
        return $this->client->request("GET", $url, $data, $headers);
    }

    /**
     * Fetch data using a GET request.
     *
     * @param string $url
     * @param array $headers
     *
     * @return NetworkResponse
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function fetch(string $url, array $headers = []): NetworkResponse
    {
        return $this->client->request("GET", $url, [], $headers);
    }

    /**
     * Perform a POST request.
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return NetworkResponse
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function post(string $url, array $data = [], array $headers = []): NetworkResponse
    {
        return $this->client->request("POST", $url, $data, $headers);
    }

     /**
     * Perform a request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return NetworkResponse
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): NetworkResponse
    {
        return $this->client->request($method, $url, $data, $headers);
    }
}