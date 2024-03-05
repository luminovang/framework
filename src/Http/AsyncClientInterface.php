<?php 
namespace Luminova\Http;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
interface AsyncClientInterface
{
    public function sendAsync(GuzzleRequest $request): PromiseInterface;
}