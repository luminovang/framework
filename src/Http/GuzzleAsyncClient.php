<?php 
namespace Luminova\Http;
use Luminova\Http\AsyncClientInterface;
use \GuzzleHttp\Client as GuzzleClient;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Psr7\Request as GuzzleRequest;
use \Psr\Http\Message\ResponseInterface;
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