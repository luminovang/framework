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

use \Luminova\Application\Foundation;
use \GuzzleHttp\Client as GuzzleClient;
use \Luminova\Interface\NetworkClientInterface;
use \Psr\Http\Client\ClientInterface;
use \Psr\Http\Message\ResponseInterface;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Exception;

class Guzzle implements NetworkClientInterface
{
    /**
     * HTTP guzzle client.
     * 
     * @var GuzzleClient $client
     */
    private ?GuzzleClient $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(private array $config = [])
    {
        if(($this->config['headers']['X-Powered-By'] ?? true) !== false){
            $this->config['headers']['X-Powered-By'] = $this->config['headers']['X-Powered-By'] ?? Foundation::copyright();
        }
        
        if(($this->config['headers']['User-Agent'] ?? true) !== false){
            $this->config['headers']['User-Agent'] = $this->config['headers']['User-Agent'] ?? Foundation::copyright(true);
        }

        $this->client = new GuzzleClient($this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ?ClientInterface
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(?string $option = null): mixed
    {
        return method_exists($this->client, 'getConfig') 
            ? $this->client->getConfig($option)
            : $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $method, 
        string $url, 
        array $options = []
    ): ResponseInterface
    {
        if ($method === 'POST') {
            $options['form_params'] = $options['form_params'] ?? [];
        }

        try {
            return $this->client->request($method, $url, $options);
        } catch (GuzzleRequestException $e) {
            $response = $e->getResponse();
            if ($response instanceof ResponseInterface) {
                return $response;
            }

            $previous = $e->getPrevious();
            if ($previous instanceof GuzzleException) {
                throw new RequestException($previous->getMessage(), $previous->getCode(), $previous);
            }
            
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException|Exception $e) {
            throw new ConnectException($e->getMessage(), $e->getCode(), $e);
        }
    }
}