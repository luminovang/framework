<?php
/**
 * Luminova Framework HTTP network request class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Http;

use \Luminova\Interface\NetworkClientInterface;
use \Luminova\Interface\NetworkInterface;
use \Luminova\Http\Message\Response;
use \Luminova\Http\Client\Curl;
use \Psr\Http\Message\RequestInterface;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Client\ClientInterface;
use \GuzzleHttp\Promise\PromiseInterface;
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\BadMethodCallException;
use \Exception;

class Network implements NetworkInterface
{
    /**
     * The network client interface to use.
     * This must be luminova NetworkClientInterface only.
     * 
     * @var NetworkClientInterface|null $client
     */
    private ?NetworkClientInterface $client = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(?NetworkClientInterface $client = null)
    {
        $this->client = $client ?? new Curl();
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $method, $arguments): mixed
    {
        $client = $this->getClient();

        if (!method_exists($client, $method)) {
            $message = $client instanceof ClientInterface
                ? 'Bad method call: method "%s(...)" is not supported in Curl client, use Guzzle client instead.'
                : 'Bad method call: method "%s(...)" does not exist.';
            throw new BadMethodCallException(sprintf($message, $method));
        }
    
        try {
            return $client->{$method}(...$arguments);
        } catch (GuzzleRequestException $e) {
            return $this->handleGuzzleRequestException($e);
        } catch (AppException $e) {
            return $this->handleAppException($e);
        } catch (GuzzleException|Exception $e) {
            throw new ConnectException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): ClientInterface|Curl|null
    {
        return $this->client->getClient();
    }

    /**
     * {@inheritdoc}
     */
    public function get(
        string $url, 
        array $options = []
    ): ResponseInterface|Response
    {
        return $this->client->request('GET', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function post(
        string $url, 
        array $options = []
    ): ResponseInterface|Response
    {
        return $this->client->request('POST', $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function request(
        string $method, 
        string $url, 
        array $options = []
    ): ResponseInterface|Response
    {
        return $this->client->request($method, $url, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(
        RequestInterface $request, 
        array $options = []
    ): PromiseInterface
    {
        if(!$this->getClient() instanceof ClientInterface){
            throw new RequestException('Request sendAsync is not supported in Curl client, use Guzzle client instead.');
        }

        try{
            return $this->getClient()->sendAsync($request, $options);
        }catch (GuzzleRequestException $e) {
            return $this->handleGuzzleRequestException($e);
        } catch (GuzzleException|Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }
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
        if(!$this->getClient() instanceof ClientInterface){
            throw new RequestException('Request requestAsync is not supported in Curl client, use Guzzle client instead.');
        }

        try{
            return $this->getClient()->requestAsync($method, $uri, $options);
        }catch (GuzzleRequestException $e) {
            return $this->handleGuzzleRequestException($e);
        } catch (GuzzleException|Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Handles a GuzzleRequestException by returning the response if available.
     *
     * This method processes a Guzzle request exception, checking if a valid response is present.
     * If a response is available, it returns the ResponseInterface; otherwise, it returns null.
     *
     * @param GuzzleRequestException $e The exception thrown during a Guzzle request.
     *
     * @return ResponseInterface|null Returns the response if present, or null if no response is available.
     * @throws RequestException $e Throw the exception encountered during a request.
     */
    private function handleGuzzleRequestException(GuzzleRequestException $e): ?ResponseInterface
    {
        $response = $e->getResponse();

        if ($response instanceof ResponseInterface) {
            return $response;
        }

        $previous = $e->getPrevious();
        if ($previous instanceof GuzzleException) {
            throw new RequestException($previous->getMessage(), $previous->getCode(), $previous);
        }

        throw new RequestException($e->getMessage(), $e->getCode(), $e);
    }

    /**
     * Handles an AppException by throwing a RequestException or re-throwing the original exception.
     *
     * This method processes an AppException. If the exception is an instance of AppException,
     * it rethrows it; otherwise, it wraps the exception in a RequestException and throws it.
     *
     * @param AppException $e The application-specific exception.
     *
     * @return never This method does not return a value and will always throw an exception.
     */
    private function handleAppException(AppException $e): never
    {
        if ($e instanceof AppException) {
            throw $e;
        }

        $previous = $e->getPrevious();
        if ($previous instanceof AppException) {
            throw new RequestException($previous->getMessage(), $previous->getCode(), $previous);
        }

        throw new RequestException($e->getMessage(), $e->getCode(), $e);
    }
}