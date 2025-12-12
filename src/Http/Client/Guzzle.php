<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Client;

use \Throwable;
use \Luminova\Luminova;
use \GuzzleHttp\Client;
use \Luminova\Promise\Promise;
use \Psr\Http\Client\ClientInterface;
use \Luminova\Interface\PromiseInterface;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Psr\Http\Message\{UriInterface, RequestInterface, ResponseInterface};

class Guzzle implements \Luminova\Interface\ClientInterface
{
    /**
     * HTTP guzzle client.
     * 
     * @var ClientInterface|Client $client
     */
    private ?ClientInterface $client = null;

    /**
     * {@inheritdoc}
     * 
     * @example - Example Request Client With base URL:
     * 
     * ```php
     * use Luminova\Http\Client\Guzzle;
     * $client = new Guzzle([
     *      'base_uri' => 'https://example.com/'
     * ]);
     * ```
     */
    public function __construct(private array $config = [])
    {
        if(($this->config['headers']['X-Powered-By'] ?? true) !== false){
            $this->config['headers']['X-Powered-By'] = $this->config['headers']['X-Powered-By'] ?? Luminova::copyright();
        }
        
        if(($this->config['headers']['User-Agent'] ?? true) !== false){
            $this->config['headers']['User-Agent'] = $this->config['headers']['User-Agent'] ?? Luminova::copyright(true);
        }

        $this->client = new Client($this->config);
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
    public function getConfig(?string $option = null): mixed
    {
        return method_exists($this->client, 'getConfig') 
            ? $this->client->getConfig($option)
            : (($option === null) 
                ? $this->config 
                : ($this->config[$option] ?? null)
            );
    }

    /**
     * {@inheritdoc}
     * 
     * Setting option while using Guzzle client will have no effect.
     */
    public function setOption(int $option, mixed $value): self
    {
       return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->call('send', $request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface 
    {
        return $this->call('sendRequest', $request);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->promise('sendAsync', $request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function requestAsync(string $method, UriInterface|string $uri = '', array $options = []): PromiseInterface
    {
        return $this->promise('requestAsync', $method, $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $method, 
        UriInterface|string $uri = '', 
        array $options = []
    ): ResponseInterface
    {
        if ($method === 'POST') {
            $options['form_params'] = $options['form_params'] ?? [];
        }

       return $this->call('request', $method, (string) $uri, $options);
    }

    /**
     * Executes a method on the Guzzle client and handles exceptions.
     *
     * @param string $method The name of the method to call on the Guzzle client.
     * @param mixed ...$arguments Variable number of arguments to pass to the method.
     *
     * @return mixed The result of the method call, or a ResponseInterface in case of certain exceptions.
     *
     * @throws RequestException If a GuzzleRequestException occurs without a valid response.
     * @throws ConnectException If a GuzzleException or general Exception occurs.
     */
    private function call(
        string $method, 
        mixed ...$arguments
    ): mixed
    {
        try {
            return $this->client->{$method}(...$arguments);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
            if ($response instanceof ResponseInterface) {
                return $response;
            }

            $previous = $e->getPrevious();
            if ($previous instanceof GuzzleException) {
                throw new RequestException($previous->getMessage(), $previous->getCode(), $previous);
            }

            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        } catch (Throwable $e) {
            throw new ConnectException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Creates a Promise object for asynchronous HTTP requests.
     *
     * This method wraps the Guzzle client's asynchronous methods in a Promise,
     * handling both successful responses and errors.
     *
     * @param string $method The name of the asynchronous method to call on the Guzzle client.
     * @param mixed ...$arguments Variable number of arguments to pass to the method.
     *
     * @return Promise A Promise object that resolves with the response or rejects with an error.
     */
    private function promise(
        string $method,
        mixed ...$arguments
    ): Promise 
    {
        return new Promise(function ($resolve, $reject) use ($method, $arguments) {
            try {
                $response = $this->client->{$method}(...$arguments);

                if ($response instanceof \GuzzleHttp\Promise\PromiseInterface) {
                    $response->then(
                        fn($result) => $resolve($result),
                        fn($error) => $reject($error instanceof Throwable ? $error : new RequestException($error))
                    );
                    $response->wait();
                    return;
                }

                $resolve($response);
            } catch (Throwable $e) {
                $this->handlePromiseException($e, $resolve, $reject);
            }
        });
    }
    
    /**
     * Handles exceptions thrown during asynchronous requests and resolves or rejects the promise accordingly.
     *
     * @param Throwable $e The exception that occurred during the request.
     * @param callable $resolve The callback function to be called when the promise is resolved.
     * @param callable $reject The callback function to be called when the promise is rejected.
     *
     * @return void
     */
    private function handlePromiseException(Throwable $e, callable $resolve, callable $reject): void 
    {

        if ($e instanceof \GuzzleHttp\Exception\RequestException) {
            $response = $e->getResponse();

            if ($response instanceof ResponseInterface) {
                $resolve($response);
                return;
            }
        }

        $previous = ($e->getPrevious() instanceof GuzzleException) 
            ? $e->getPrevious() 
            : $e;

        if ($previous instanceof GuzzleException) {
            $reject(new ConnectException($previous->getMessage(), $previous->getCode(), $previous));
            return;
        }

        $reject(new RequestException($e->getMessage(), $e->getCode(), $e));
    }
}