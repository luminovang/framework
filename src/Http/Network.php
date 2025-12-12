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
use \Luminova\Luminova;
use \Luminova\Http\Client\Novio;
use \Luminova\Http\Client\Guzzle;
use \Psr\Http\Client\ClientInterface;
use \GuzzleHttp\Exception\GuzzleException;
use \Luminova\Promise\{Promise, Rejected};
use \Luminova\Interface\{ExceptionInterface, PromiseInterface};
use \GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use \Psr\Http\Message\{ResponseInterface, RequestInterface, UriInterface};
use \Luminova\Exceptions\{RuntimeException, Http\RequestException, BadMethodCallException};

/**
 * Network client for making HTTP requests synchronously or asynchronously.
 * 
 * Supports dynamic method resolution for HTTP verbs such as GET, POST, PUT, etc.
 * Use `config()` to set default configuration (e.g., base_uri).
 *
 * ResponseInterface: 
 * 
 * @method static ResponseInterface<\T> get(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> put(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> patch(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> delete(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> head(UriInterface<\T>|string $url, array $options = [])
 * @method static ResponseInterface<\T> options(UriInterface<\T>|string $url, array $options = [])
 *
 * PromiseInterface:
 * 
 * @method static PromiseInterface<\T> getAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> postAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> putAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> patchAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> deleteAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> headAsync(UriInterface<\T>|string $url, array $options = [])
 * @method static PromiseInterface<\T> optionsAsync(UriInterface<\T>|string $url, array $options = [])
 */
final class Network
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
     * @var \Luminova\Interface\ClientInterface<ClientInterface>|ClientInterface|null $http
     */
    private static ?ClientInterface $http = null;

    /**
     * The network client interface to use.
     * 
     * @var array<string,mixed> $config
     */
    private static array $config = [];

    /**
     * Prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Handle dynamic static method calls for HTTP verbs.
     *
     * @param string $method The HTTP method name or async variant.
     * @param array  $arguments First argument should be the URL or URI if base URL was set using `config`, 
     *                      second is optional options array.
     *
     * @return ResponseInterface<\T>|PromiseInterface<\T>|mixed Response object or a Promise for async requests.
     *
     * @throws BadMethodCallException Throws if invalid parameters are passed.
     * @throws RequestException If an error occurs during the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters a 4xx HTTP error.
     * @throws ServerException If the server encounters a 5xx HTTP error.
     * 
     * @example - Enables static calls like:
     * ```php
     * Network::get('https://api.example.com/user/100');
     * Network::postAsync('https://api.example.com', [...]);
     * ```
     * @example - Using base URL
     * 
     * ```php
     * Network::config(['base_uri' => 'https://example.com']);
     * 
     * Network::get('/user/100');
     * ```
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::resolve($method, $arguments, true);
    }

    /**
     * Define immutable global configurations for HTTP requests.
     *
     * This method sets global options such as `base_uri`, headers, or timeouts.
     * It must be called **before** making any network request (especially when using static methods),
     * as the configuration becomes locked after the first usage.
     *
     * @param array<string,mixed> $config Key-value pairs of request configuration options.
     * @return void
     *
     * @example - Setting a base URL for all static requests:
     *
     * ```php
     * Network::config([
     *     'base_uri' => 'https://example.com/'
     * ]);
     *
     * // Then, you can make relative requests:
     * Network::get('user/100/info');
     * ```
     *
     * > **Note:** This method is best suited for static usage, where a default base URL
     * or common settings (e.g., headers) are required across all requests.
     * Once the client is created, the config cannot be changed.
     */
    public static function config(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Set the HTTP client used by the Network class.
     *
     * Overrides the default client for all outgoing requests.
     * If not set, the default Luminova `\Luminova\Http\Client\Novio` client is used.
     *
     * @param ClientInterface $client HTTP client implementation.
     * 
     * @return void
     */
    public static function client(ClientInterface $client): void
    {
        self::$http = $client;
    }

    /**
     * Retrieves the current HTTP client instance.
     * 
     * This method returns the Luminova `Novio` or `Guzzle` client object if it is being used; otherwise, 
     * it returns the client interface object.
     *
     * @return ClientInterface|null Returns the instance of the HTTP client used for requests.
     */
    public static function getClient(): ClientInterface
    {
        if(!self::$http instanceof ClientInterface){
            return self::create();
        }

        if(self::$http instanceof Guzzle){
            return self::$http->getClient();
        }

        return self::$http;
    }

    /**
     * Execute a synchronous GET request.
     *
     * Sends a GET request to the given URI and returns the server response.
     *
     * @param UriInterface<T>|string $uri The target URI.
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return ResponseInterface<T> Returns the request response object.
     *
     * @throws RequestException  On request errors.
     * @throws ConnectException  On connection failures.
     * @throws ClientException   On 4xx client errors.
     * @throws ServerException   On 5xx server errors.
     */
    public static function get(
        UriInterface|string $uri = '',
        array $options = []
    ): ResponseInterface {
        try {
            return self::create()->request('GET', $uri, $options);
        } catch (Throwable $e) {
            return self::handleRequestException($e, false);
        }
    }

    /**
     * Dispatch an HTTP request and return a promise.
     *
     * By default, the request is sent asynchronously. To force synchronous
     * execution, pass `['async' => false]` in the options array.
     *
     * @param UriInterface<T>|string $uri Request URI object or URI string.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return PromiseInterface<T> Promise resolving to the HTTP response.
     *
     * @example - Example:
     * ```php
     * Network::fetch(
     *     'https://example.com',
     *     'GET',
     *     ['headers' => ['Accept' => 'application/json']]
     * )->then(
     *     fn (ResponseInterface $response) => 
     *         echo $response->getBody()->getContents()
     * )->catch(
     *     fn (Throwable $e) => echo $e->getMessage()
     * );
     * ```
     */
    public static function fetch(
        UriInterface|string $uri = '',
        string $method = 'GET',
        array $options = []
    ): PromiseInterface 
    {
        return self::requestAsync($method, $uri, $options);
    }

    /**
     * Execute a synchronous POST request.
     *
     * Sends a POST request to the given URI and returns the server response.
     *
     * @param UriInterface<T>|string $uri Target URI.
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return ResponseInterface<T> Returns the request response object.
     *
     * @throws RequestException On request errors.
     * @throws ConnectException On connection failures.
     * @throws ClientException On 4xx client errors.
     * @throws ServerException On 5xx server errors.
     */
    public static function post(
        UriInterface|string $uri = '',
        array $options = []
    ): ResponseInterface 
    {
        try {
            return self::create()->request('POST', $uri, $options);
        } catch (Throwable $e) {
            return self::handleRequestException($e, false);
        }
    }

    /**
     * Send a synchronous HTTP request.
     *
     * Executes an HTTP request using the given method and URI and
     * returns the server response.
     *
     * @param string $method HTTP method (e.g., GET, POST).
     * @param UriInterface<T>|string $uri Request URI object or URI string.
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return ResponseInterface<T> Returns the request response object.
     *
     * @throws RequestException  On request errors.
     * @throws ConnectException  On connection failures.
     * @throws ClientException   On 4xx client errors.
     * @throws ServerException   On 5xx server errors.
     */
    public static function request(
        string $method,
        UriInterface|string $uri = '',
        array $options = []
    ): ResponseInterface 
    {
        try {
            return self::create()->request($method, $uri, $options);
        } catch (Throwable $e) {
            return self::handleRequestException($e, false);
        }
    }

    /**
     * Send a synchronous HTTP request using a request object.
     *
     * Dispatches a fully configured request instance and returns
     * the server response.
     *
     * @param RequestInterface<T> $request Prepared request instance.
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return ResponseInterface<T> Returns the request response object.
     *
     * @throws RequestException  On request errors.
     * @throws ConnectException  On connection failures.
     * @throws ClientException   On 4xx client errors.
     * @throws ServerException   On 5xx server errors.
     */
    public static function send(
        RequestInterface $request,
        array $options = []
    ): ResponseInterface 
    {
        return self::doSend($request, $options);
    }

    /**
     * Send an HTTP request asynchronously.
     *
     * Dispatches the given request using the configured HTTP client and
     * returns a promise that resolves to the response.
     *
     * @param RequestInterface<T> $request Fully configured request instance.
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return PromiseInterface<T> Promise resolving to the request response.
     */
    public static function sendAsync(
        RequestInterface $request,
        array $options = []
    ): PromiseInterface 
    {
        return self::doSend($request, $options, true, true);
    }

    /**
     * Send an HTTP request asynchronously using a URI.
     *
     * Creates and dispatches an asynchronous request for the given HTTP method
     * and URI, returning a promise that resolves to the response.
     *
     * @param string $method HTTP method (GET, POST, etc.).
     * @param UriInterface<T>|string $uri Request URI object or URI string.
     * @param array<string,mixed> $options Client-specific request options.
     *
     * @return PromiseInterface<T> Promise resolving to the request response.
     */
    public static function requestAsync(
        string $method,
        UriInterface|string $uri = '',
        array $options = []
    ): PromiseInterface 
    {
        try {
            return self::create()->requestAsync($method, $uri, $options);
        } catch (Throwable $e) {
            return self::handleRequestException($e, true);
        }
    }

    /**
     * Create or return the shared HTTP client instance.
     *
     * @return ClientInterface|\Luminova\Interface\ClientInterface<ClientInterface>
     */
    private static function create(): ClientInterface
    {
        if (self::$http instanceof ClientInterface) {
            return self::$http;
        }

        $client = Luminova::kernel()->getHttpClient(self::$config) 
            ?? new Novio(self::$config);

        if ($client instanceof ClientInterface) {
            return self::$http = $client;
        }

        if (is_string($client) && class_exists($client)) {
            $client = new $client(self::$config);

            if ($client instanceof ClientInterface) {
                return self::$http = $client;
            }
        }

        throw new RuntimeException(sprintf(
            'Invalid HTTP client: %s returned by %s; expected %s implementation.',
            get_class($client),
            'Kernel::getHttpClient()',
            ClientInterface::class
        ));
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
    private static function doSend(
        RequestInterface $request, 
        array $options = [],
        bool $async = false,
        bool $promise = false
    ): PromiseInterface|ResponseInterface
    {
        try{
            return $async 
                ? self::create()->sendAsync($request, $options)
                : self::create()->send($request, $options);
        } catch (Throwable $e) {
            return self::handleRequestException($e, $promise);
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
    private static function handleRequestException(
        Throwable $e, 
        bool $promise = false
    ): PromiseInterface|ResponseInterface
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
                ($e instanceof ExceptionInterface) 
                    ? $e : new RequestException($e->getMessage(), $e->getCode(), $e)
            );
        }

        self::handleAppException($e);
    }

    /**
     * Handles an App Exception by throwing a RequestException or re-throwing the original exception.
     *
     * This method processes an App Exception. If the exception is an instance of App Exception,
     * it rethrows it; otherwise, it wraps the exception in a RequestException and throws it.
     *
     * @param Throwable $e The application-specific or guzzle exception.
     *
     * @return void
     * @throws Exception
     */
    private static function handleAppException(Throwable $e): void
    {
        if ($e instanceof ExceptionInterface) {
            throw $e;
        }

        if (
            ($e->getPrevious() instanceof ExceptionInterface) || 
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
     * @param string $method The HTTP method (e.g., 'get', 'post', 'put', etc.)
     * @param array $arguments Contains URL as first element and optional options array as second.
     * @param bool $isStatic Flag to indicate static method context.
     *
     * @return mixed Response object or a Promise.
     *
     * @throws BadMethodCallException for invalid static calls with no arguments.
     */
    private static function resolve(string $method, array $arguments, bool $isStatic = false): mixed
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
                return self::create()->requestAsync(substr($method, 0, -5), $url, $options);
            }

            return self::create()->request($method, $url, $options);
        } catch (Throwable $e) {
            return self::handleRequestException($e, $isAsync);
        }
    }
}