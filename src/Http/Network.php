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

use \Luminova\Http\Client\ClientInterface;
use \Luminova\Http\Message\Response;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Client;
use \Luminova\Http\Client\Curl;
use \GuzzleHttp\Psr7\Request;

class Network implements NetworkInterface
{
    /**
     * @var ClientInterface $client
     */
    private ClientInterface $client;

    /**
     * {@inheritdoc}
    */
    public function __construct(?ClientInterface $client = null)
    {
        if($client === null){
            $this->client = new Curl();
        }else{
            $this->client = $client;
        }
    }

    /**
     * {@inheritdoc}
    */
    public function getClient(): ClientInterface
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
        return $this->client->request("GET", $url, $data, $headers);
    }
    
    /**
     * {@inheritdoc}
    */
    public function fetch(string $url, array $headers = []): Response
    {
        return $this->client->request("GET", $url, [], $headers);
    }

    /**
     * {@inheritdoc}
    */
    public function post(string $url, array $data = [], array $headers = []): Response
    {
        return $this->client->request("POST", $url, $data, $headers);
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
    }
}