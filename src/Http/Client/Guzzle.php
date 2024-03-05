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

use \GuzzleHttp\Client as GuzzleHttpClient;
use \Luminova\Http\NetworkClientInterface;
use \Luminova\Http\NetworkResponse;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Http\Exceptions\RequestException;
use \Luminova\Http\Exceptions\ConnectException;
use \Luminova\Http\Exceptions\ClientException;
use \Luminova\Http\Exceptions\ServerException;

class Guzzle implements NetworkClientInterface
{
    /**
     * @var GuzzleHttpClient
    */
    private $client;

    /**
     * Guzzle client constructor.
     * @param array $config client configuration
     * 
    */
    public function __construct(array $config = [])
    {
        $this->client = new GuzzleHttpClient($config);
    }

    /**
     * Perform an HTTP request using Guzzle.
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
        $options = ['headers' => $headers];

        if ($method === 'POST') {
            $options['form_params'] = $data;
        }

        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody();

            return new NetworkResponse(
                $response->getStatusCode(),
                $response->getHeaders(),
                $body,
                $body->getContents()
            );
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            if ($response !== null) {
                $body = $response->getBody();
                return new NetworkResponse(
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