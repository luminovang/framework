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

interface NetworkInterface 
{
    /**
     * Network constructor with http client instance 
     *
     * @param HttpClientInterface $client 
     */
    public function __construct(?HttpClientInterface $client = null);

    /**
     * Get network client instance 
     * 
     * @return HttpClientInterface
    */
    public function getClient(): HttpClientInterface;

    /**
     * Send a request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return Response
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function send(string $method, string $url, array $data = [], array $headers = []): Response;

    /**
     * Perform a GET request.
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return Response
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function get(string $url, array $data = [], array $headers = []): Response;

    /**
     * Fetch data using a GET request.
     *
     * @param string $url
     * @param array $headers
     *
     * @return Response
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function fetch(string $url, array $headers = []): Response;

    /**
     * Perform a POST request.
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return Response
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function post(string $url, array $data = [], array $headers = []): Response;

    /**
     * Perform a request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return Response
     * @throws RequestException
     * @throws ConnectException
     * @throws ClientException
     * @throws ServerException
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response;
} 