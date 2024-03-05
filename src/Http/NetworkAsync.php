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
 
 use \Luminova\Http\AsyncClientInterface;
 use \Luminova\Http\NetworkRequest;
 use \GuzzleHttp\Promise\PromiseInterface;
 use \GuzzleHttp\Psr7\Request as GuzzleRequest;

class NetworkAsync
{
    private $client;

    public function __construct(AsyncClientInterface $client)
    {
        $this->client = $client;
    }

    public function sendAsync(NetworkRequest $request): PromiseInterface
    {
        $guzzleRequest = new GuzzleRequest($request->getMethod(), $request->getUrl());
        return $this->client->sendAsync($guzzleRequest);
    }
}