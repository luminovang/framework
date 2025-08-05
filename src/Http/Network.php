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

use \Throwable;
use \Luminova\Http\Client\Novio;
use \Psr\Http\Client\ClientInterface;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Utility\Promise\{Promise, Rejected};
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \Psr\Http\Message\{ResponseInterface, RequestInterface, UriInterface};
use \Luminova\Interface\{NetworkInterface, LazyObjectInterface, PromiseInterface};
use \Luminova\Exceptions\{AppException, Http\RequestException, BadMethodCallException};

/**
 * Network client for making HTTP requests synchronously or asynchronously.
 * 
 * Supports dynamic method resolution for HTTP verbs such as GET, POST, PUT, etc.
 * Use `config()` to set default configuration (e.g., base_uri).
 *
 * @method static ResponseInterface<\T> get(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> post(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> put(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> patch(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> delete(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> head(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> options(UriInterface<\T>|string $url, array $options = [])
 *
 * @method static PromiseInterface<\T> getAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> postAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> putAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> patchAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> deleteAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> headAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> optionsAsync(UriInterface<\T>|string $url, array $options = [])
 */
class Network implements NetworkInterface, LazyObjectInterface
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
     * Static method instance.
     * 
     * @var NetworkInterface|null $instance
     */
    private static ?NetworkInterface $instance = null;

    /**
     * The network client interface to use.
     * 
     * @var array<string,mixed> $config
     */
    private static array $config = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Novio(self::$config);
    }

    /**
     * {@inheritdoc}
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->resolve($method, $arguments);
    }

    /**
     * {@inheritdoc}
     * @since 3.6.8
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        if(!self::$instance instanceof self){
            self::$instance = new self();
        }

        return self::$instance->resolve($method, $arguments, true);
    }

    /**
     * {@inheritdoc}
     * @since 3.6.8
     */
    public static function config(array $config): void
    {
        self::$config = $config;
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
    public function get(UriInterface|string $uri = '', array $options = []): ResponseInterface
    {
        try{
            return $this->client->request('GET', $uri, $options);
        } catch (Throwable $e) {
            return $this->handleRequestException($e, false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(UriInterface|string $uri = '', string $method = 'GET', array $options = []): PromiseInterface
    {
        return $this->requestAsync($method, $uri, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function post(UriInterface|string $uri = '', array $options = []): ResponseInterface
    {
        try{
            return $this->client->request('POST', $uri, $options);
        } catch (Throwable $e) {
            return $this->handleRequestException($e, false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, UriInterface|string $uri = '', array $options = []): ResponseInterface
    {
        try{
            return $this->client->request($method, $uri, $options);
        } catch (Throwable $e) {
            return $this->handleRequestException($e, false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->doSend($request, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->doSend($request, $options, true, true);
    }

    /**
     * {@inheritdoc}
     */
    public function requestAsync(string $method, UriInterface|string $uri = '', array $options = []): PromiseInterface
    {
        try{
            return $this->client->requestAsync($method, $uri, $options);
        } catch (Throwable $e) {
            return $this->handleRequestException($e, true);
        }
    }

    /**
     * Send request from request object.
     * 
     * @param RequestInterface<\T> $request
     * @param array $options
     * @param bool $async
     * @param bool $promise
     * 
     * @return PromiseInterface<\T>|ResponseInterface<\T> Return guzzle promise or response object for Novio client.
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
        } catch (Throwable $e) {
            return $this->handleRequestException($e, $promise);
        }
    }

    /**
     * Handles a GuzzleRequestException by returning the response if available.
     *
     * This method processes a Guzzle request exception, checking if a valid response is present.
     * If a response is available, it returns the ResponseInterface; otherwise, it returns null.
     *
     * @param Throwable $e The exception thrown during request.
     * @param bool $promise
     *
     * @return PromiseInterface<\T>|ResponseInterface<\T>|never Returns the response if present, or null if no response is available.
     * @throws RequestException $e Throw the exception encountered during a request.
     */
    private function handleRequestException(Throwable $e, bool $promise = false): PromiseInterface|ResponseInterface
    {
        if($e instanceof GuzzleRequestException) {
            if($promise){
                return new Promise(function($resolve, $reject) use($e){
                    ($e->getResponse() instanceof ResponseInterface)
                        ? $resolve($e->getResponse())
                        : $reject($e);
                });
            }

            if ($e->getResponse() instanceof ResponseInterface) {
                return $e->getResponse();
            }
        }
        
        if($promise){
            return new Rejected(
                ($e instanceof AppException) ? $e : new RequestException($e->getMessage(), $e->getCode(), $e)
            );
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

    /**
     * Resolves the dynamic method name and performs the corresponding HTTP request.
     *
     * @param string  $method The HTTP method (e.g., 'get', 'post', 'put', etc.)
     * @param array   $arguments Contains URL as first element and optional options array as second.
     * @param bool    $isStatic  Flag to indicate static method context.
     *
     * @return mixed Response object or a Promise.
     *
     * @throws BadMethodCallException for invalid static calls with no arguments.
     */
    private function resolve(string $method, array $arguments, bool $isStatic = false): mixed
    {
        $options = $arguments[1] ?? [];
        $url = $arguments[0] ?? '';
        $isAsync = str_ends_with($method, 'Async');

        if ($isStatic) {
            $base = $options['base_uri'] 
                ?? self::$config['base_uri'] 
                ?? null;

            if ($url === '' && empty($base)) {
                $error = new BadMethodCallException(sprintf(
                    'Bad static method call: Network::%s(...) requires at least a URL, or set base_uri via Network::config().',
                    $method
                ));

                if($isAsync){
                    return new Rejected($error);
                }

                throw $error;
            }
        }

        try{
            if ($isAsync) {
                return $this->client->requestAsync(substr($method, 0, -5), $url, $options);
            }

            return $this->client->request($method, $url, $options);
        } catch (Throwable $e) {
            return $this->handleRequestException($e, $isAsync);
        }
    }
}