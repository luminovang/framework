<?php
/**
 * Luminova Framework HTTP network request class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Luminova\Http\Client\Curl;
use \Psr\Http\Client\ClientInterface;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\BadMethodCallException;
use \Luminova\Utils\Promise\{Promise, RejectedPromise};
use \Psr\Http\Message\{ResponseInterface, RequestInterface, UriInterface};
use \Luminova\Interface\{NetworkInterface, LazyInterface, PromiseInterface};
use \Exception;
use \Throwable;

class Network implements NetworkInterface, LazyInterface
{
    /**
     * Custom flag to skip header in request.
     * 
     * @var int SKIP_HEADER
     */
    public const SKIP_HEADER = 5319;

    /**
     * The network client interface to use.
     * 
     * @var \Luminova\Interface\ClientInterface<\T>|ClientInterface<\T>|null $client
     */
    private ?ClientInterface $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Curl();
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $method, array $arguments): mixed
    {
        if (count($arguments) < 1) {
            $error = new BadMethodCallException(sprintf(
                'Bad method call: request method "->%s(...)" require a URI and optional options array',
                $method
            ));

            if(str_ends_with($method, 'Async')){
                return new RejectedPromise($error);
            }

            throw $error;
        }

        $url = $arguments[0] ?? '';
        $options = $arguments[1] ?? [];

        return str_ends_with($method, 'Async')
            ? $this->requestAsync(substr($method, 0, -5), $url, $options)
            : $this->client->request($method, $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ?ClientInterface
    {
        return ($this->client && method_exists($this->client, 'getClient'))
            ? $this->client->getClient() 
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function get(
        string $uri = '', 
        array $options = []
    ): ResponseInterface
    {
        return $this->client->request('GET', $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(
        UriInterface|string $uri = '', 
        string $method = 'GET', 
        array $options = []
    ): PromiseInterface
    {
        return $this->client->requestAsync(
            $method, 
            $uri, 
            $options
        );
    }

    /**
     * {@inheritdoc}
     */
    public function post(
        string $uri = '', 
        array $options = []
    ): ResponseInterface
    {
        return $this->client->request('POST', $uri, $options);
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
        return $this->client->request($method, $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function send(
        RequestInterface $request, 
        array $options = []
    ): ResponseInterface
    {
        return $this->doSend($request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(
        RequestInterface $request, 
        array $options = []
    ): PromiseInterface
    {
        return $this->doSend($request, $options, true, true);
    }

    /**
     * {@inheritdoc}
     */
    public function requestAsync(
        string $method, 
        UriInterface|string $uri = '', 
        array $options = []
    ): PromiseInterface
    {
        return $this->client->requestAsync(
            $method, 
            $uri, 
            $options
        );
    }

    /**
     * Send request from request object.
     * 
     * @param RequestInterface $request
     * @param array $options
     * @param bool $async
     * @param bool $promise
     * 
     * @return PromiseInterface<\T>|ResponseInterface<\T> Return guzzle promise or response object for cURL client.
    */
    private function doSend(
        RequestInterface $request, 
        array $options = [],
        bool $async = false,
        bool $promise = false
    ): PromiseInterface|ResponseInterface
    {
        try{
            return $async 
                ? $this->client->sendAsync($request, $options)
                : $this->client->send($request, $options);
        }catch (GuzzleRequestException $e) {
            if($promise){
                return new Promise(function($resolve, $reject) use($e){
                    ($e->getResponse() instanceof ResponseInterface)
                        ? $resolve($e->getResponse())
                        : $reject($e);
                });
            }

            return $this->handleGuzzleRequestException($e);
        } catch (GuzzleException|Exception $e) {
            if($promise){
                return new RejectedPromise(
                    ($e instanceof AppException) ? $e : new RequestException($e->getMessage(), $e->getCode(), $e)
                );
            }

            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Handles a GuzzleRequestException by returning the response if available.
     *
     * This method processes a Guzzle request exception, checking if a valid response is present.
     * If a response is available, it returns the ResponseInterface; otherwise, it returns null.
     *
     * @param Throwable $e The exception thrown during a Guzzle request.
     *
     * @return ResponseInterface|null Returns the response if present, or null if no response is available.
     * @throws RequestException $e Throw the exception encountered during a request.
     */
    private function handleGuzzleRequestException(Throwable $e): ?ResponseInterface
    {
        if (($e instanceof GuzzleRequestException) && $e->getResponse() instanceof ResponseInterface) {
            return $e->getResponse();
        }

        $this->handleAppException($e);
    }

    /**
     * Handles an AppException by throwing a RequestException or re-throwing the original exception.
     *
     * This method processes an AppException. If the exception is an instance of AppException,
     * it rethrows it; otherwise, it wraps the exception in a RequestException and throws it.
     *
     * @param Throwable $e The application-specific or guzzle exception.
     *
     * @return never This method does not return a value and will always throw an exception.
     */
    private function handleAppException(Throwable $e): never
    {
        if ($e instanceof AppException) {
            throw $e;
        }

        if (
            ($e->getPrevious() instanceof AppException) || 
            ($e->getPrevious() instanceof GuzzleException)
        ) {
            throw new RequestException(
                $e->getPrevious()->getMessage(), 
                $e->getPrevious()->getCode(), 
                $e->getPrevious()
            );
        }

        throw new RequestException($e->getMessage(), $e->getCode(), $e);
    }
}