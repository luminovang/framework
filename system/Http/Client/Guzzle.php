<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Http\Client;

use \GuzzleHttp\Client;
use \Luminova\Interface\HttpClientInterface;
use \Luminova\Http\Message\Response;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;

class Guzzle implements HttpClientInterface
{
    /**
     * @var Client $client guzzle client
    */
    private Client $client;

    /**
     * {@inheritdoc}
     * 
    */
    public function __construct(array $config = [])
    {
        $this->client = new Client($config);
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response
    {
        $options = ($headers === [] ? [] : ['headers' => $headers]);

        if ($method === 'POST') {
            $options['form_params'] = $data;
        }

        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody();

            return new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                $body,
                $body->getContents()
            );
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                $body = $response->getBody();
                return new Response(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    $body,
                    $body->getContents()
                );
            } else {
                $previous = $e->getPrevious();
                if ($previous instanceof GuzzleException) {
                    throw new RequestException($previous->getMessage(), $previous->getCode(), $previous);
                }
                throw new RequestException($e->getMessage(), $e->getCode(), $e);
            }
        } catch (GuzzleException $e) {
            throw new ConnectException($e->getMessage(), $e->getCode(), $e);
        }
    }
}