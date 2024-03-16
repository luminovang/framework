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
use \GuzzleHttp\Client as GuzzleClient;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Psr7\Request as GuzzleRequest;

class GuzzleAsyncClient implements AsyncClientInterface
{
    private $client;

    public function __construct()
    {
        $this->client = new GuzzleClient();
    }

    public function sendAsync(GuzzleRequest $request): PromiseInterface
    {
        return $this->client->sendAsync($request);
    }
}