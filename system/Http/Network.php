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

use \Luminova\Interface\HttpClientInterface;
use \Luminova\Interface\NetworkInterface;
use \Luminova\Http\Message\Response;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Client;
use \Luminova\Http\Client\Curl;
use \GuzzleHttp\Psr7\Request;
use \Luminova\Exceptions\Http\RequestException;

class Network implements NetworkInterface
{
    /**
     * @var HttpClientInterface|null $client
     */
    private ?HttpClientInterface $client = null;

    /**
     * {@inheritdoc}
    */
    public function __construct(?HttpClientInterface $client = null)
    {
        $this->client = $client ?? new Curl();
    }

    /**
     * {@inheritdoc}
    */
    public function getClient(): HttpClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
    */
    public function send(string $method, string $url, array $data = [], array $headers = []): Response
    {
        return $this->client->request($method, $url, $data, $headers);
    }

    /**
     * {@inheritdoc}
    */
    public function get(string $url, array $data = [], array $headers = []): Response
    {
        return $this->client->request('GET', $url, $data, $headers);
    }
    
    /**
     * {@inheritdoc}
    */
    public function fetch(string $url, array $headers = []): Response
    {
        return $this->client->request('GET', $url, [], $headers);
    }

    /**
     * {@inheritdoc}
    */
    public function post(string $url, array $data = [], array $headers = []): Response
    {
        return $this->client->request('POST', $url, $data, $headers);
    }

    /**
     * {@inheritdoc}
    */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response
    {
        return $this->client->request($method, $url, $data, $headers);
    }

    /**
     * {@inheritdoc}
    */
    public function sendAsync(Request $request): PromiseInterface
    {
        if($this->client instanceof Client){
            return $this->client->sendAsync($request);
        }

        throw new RequestException('Request sendAsync is not supported in Curl client, use Guzzle client instead.');
    }
}