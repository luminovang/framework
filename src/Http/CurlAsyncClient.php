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
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Promise\Utils;
use \GuzzleHttp\Psr7\Request as GuzzleRequest;
use \GuzzleHttp\Psr7\Response;

class CurlAsyncClient implements AsyncClientInterface
{
    public function sendAsync(GuzzleRequest $request): PromiseInterface
    {
        $ch = curl_init();
        $url = $request->getUri()->__toString();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        // You may need to set other options based on the request (e.g., headers, method)

        return Utils::promiseFor(curl_exec($ch))
            ->then(function ($response) {
                return new Response(200, [], $response);
            });
    }

   /* public function sendAsyncRequest($method, $url, $data = [], $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $ch;
    }*/
}