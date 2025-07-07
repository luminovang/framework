<?php
/**
 * Luminova Framework cURL network client class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Client;

use \Luminova\Luminova;
use \Luminova\Http\Message\Response;
use \Luminova\Http\Uri;
use \Luminova\Cookies\CookieFileJar;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Interface\ResponseInterface as MsgResponseInterface;
use \Luminova\Interface\CookieJarInterface;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\RequestInterface;
use \Luminova\Utils\Async;
use \Luminova\Http\Network;
use \Luminova\Storages\Stream;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Interface\ClientInterface;
use \Luminova\Functions\Normalizer;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;
use \CurlHandle;
use \CurlMultiHandle;
use \Generator;
use \CURLFile;
use \Exception;
use \JsonException;
use \Throwable;
use function \Luminova\Funcs\array_extend_default;

class Curl implements ClientInterface
{
    /**
     * The extended options.
     * 
     * @var array<string,mixed> $mutable
     */
    private ?array $mutable = null;

    /**
     * CURL Options.
     * 
     * @var array $constants
     */
    private static array $constants = [];

    /**
     * cURL Request Options.
     * 
     * @var array<int,mixed> $options
     */
    private array $options = [];

    /**
     * Indicate if reading headers is completed.
     * 
     * @var bool $isHeaderDone
     * @required
     */
    private bool $isHeaderDone = false;

    /**
     * Request response Headers.
     * 
     * @var array<string,array> $headers
     */
    private array $headers = [];

    /**
     * Request responser content length.
     * 
     * @var int $contentLength
     */
    private int $contentLength = 0;

    /**
     * The curl multi handle.
     *
     * @var CurlMultiHandle|null
     */
    private ?CurlMultiHandle $mh = null;

    /**
     * Responses collected after execution.
     *
     * @var ResponseInterface[]|MsgResponseInterface[] $response
     */
    private array $response = [];

    /**
     * Mode indicating CURL object is for parallels request.
     * 
     * @var bool $isMulti
     */
    private bool $isMulti = false;

    /**
     * Current job ID.
     * 
     * @var array<int,mixed> $parallels
     */
    private array $parallels = [];

    /**
     * Queued parallels requests.
     * 
     * @var int|bool $jobId
     */
    private int|bool $jobId = false;

    /**
     * Supported HTTP request methods.
     * 
     * @var array METHODS
     */
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'HEAD', 'OPTIONS', 'DELETE'];

    /**
     * {@inheritdoc}
     * 
     * @example - Example Request Client With base URL:
     * 
     * ```php
     * use Luminova\Http\Client\Curl;
     * 
     * $client = new Curl([
     *      'base_uri' => 'https://example.com/'
     * ]);
     * ```
     * 
     * @example - Example Using CURL multi request:
     * 
     * ```php
     * use Luminova\Http\Client\Curl;
     * 
     * $multi = Curl::multi([
     *      'base_uri' => 'https://example.com/'
     * ]);
     * $multi->run();
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
    }

    /**
     * Create a new multi Curl instance for parallel request execution.
     *
     * Call `add()` to queue requests, then `run()` or `iterator()` to execute.
     *
     * @param array<string, mixed> $config Optional configuration settings.
     * 
     * @return self Return a new instance of CURL class that resolves to CURL multi handler.
     *
     * @example - Example:
     * 
     * ```php
     * use Luminova\Http\Client\Curl;
     * $multi = Curl::multi();
     * 
     * $multi->add('GET', 'https://example.com');
     * $multi->add('GET', 'https://example.org');
     * $multi->add('GET', 'https://example.net');
     * 
     * $multi->run();
     * $results = $multi->getResponses();
     *
     * // OR stream responses as they complete:
     * foreach ($multi->iterator() as $response) {
     *     // handle $response
     * }
     * ```
     */
    public static function multi(array $config = []): self
    {
        $instance = new self($config);
        $instance->isMulti = true;
        $instance->mh = curl_multi_init();

        return $instance;
    }

    /**
     * Queue a new request for multi executions.
     *
     * This must be used after calling Curl::multi().
     *
     * @param string $method  HTTP method (e.g. 'GET', 'POST').
     * @param UriInterface|string $uri Optional target URI.
     * @param array<string,mixed> $options Optional request options (headers, body, etc).
     * 
     * @return self Return instance of CURL class.
     * @throws RequestException Throws if called without initializing multi CURL.
     *
     * @example - Example:
     * ```php
     * use Luminova\Http\Client\Curl;
     * 
     * $multi = Curl::multi();
     * 
     * $multi->add('GET', 'https://example.com', ['headers' => ['X-Test' => 'yes']]);
     * $multi->add('POST', 'https://api.example.org', ['body' => 'data']);
     * ```
     */
    public function add(string $method, UriInterface|string $uri = '', array $options = []): self 
    {
        if(!$this->isMulti){
            self::handleException(sprintf(
                '%s can only be used in multi mode. Call Curl::multi() before adding requests.',
                __METHOD__
            ), AppException::LOGIC_ERROR);
        }

        $this->parallels[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'options' => $options,
            'metadata' => []
        ];

        return $this;
    }

    /**
     * Returns all responses collected from completed multi requests.
     *
     * @return ResponseInterface[]|MsgResponseInterface[] Return an array of response objects.
     */
    public function getResponses(): array
    {
        return $this->response;
    }

    /**
     * {@inheritdoc}
     */
    public function getClient(): self
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(?string $option = null): mixed
    {
        $config = $this->mutable ?? $this->config;

        if($option === null){
            return $config;
        }

        return $config[$option] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigs(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function setOption(int $option, mixed $value): self
    {
        $this->options[$option] = $value;
        return $this;
    }

    /**
     * Run all queued multi requests concurrently and collect responses.
     *
     * This method will execute the requests, and responses can be retrieved using `getResponses`.
     *
     * @return void
     * @throws RequestException Throws if called without initializing multi CURL.
     */
    public function run(): void
    {
        $this->setMultiHandlers(__METHOD__);
        $running = null;

        do {
            $status = curl_multi_exec($this->mh, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($running && $status === CURLM_OK && $this->parallels !== []) {
            if (curl_multi_select($this->mh) === -1) {
                usleep(100); 
            }

            do {
                $status = curl_multi_exec($this->mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($current = curl_multi_info_read($this->mh)) {

                $result = $this->getMultiResponse($current['handle']);

                if ($result instanceof ResponseInterface) {
                    $this->response[$this->jobId] = $result;
                }
            }
        }

        curl_multi_close($this->mh);
        $this->parallels = [];
    }

    /**
     * Iterate over each response as it arrives.
     *
     * This method yields each response as soon as it's ready.
     *
     * @return Generator<int,ResponseInterface|MsgResponseInterface,void,void> Yields a Response object for each completed request.
     * @throws RequestException Throws if called without initializing parallel CURL.
     */
    public function iterator(): Generator
    {
        $this->setMultiHandlers(__METHOD__);
        $running = null;

        do {
            $status = curl_multi_exec($this->mh, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($running && $status === CURLM_OK && $this->parallels !== []) {
            if (curl_multi_select($this->mh) === -1) {
                usleep(100); 
            }

            do {
                $status = curl_multi_exec($this->mh, $running);
            } while ($status === CURLM_CALL_MULTI_PERFORM);

            while ($current = curl_multi_info_read($this->mh)) {

                $result = $this->getMultiResponse($current['handle']);

                if ($result instanceof ResponseInterface) {
                    yield $this->jobId => $result;
                }
            }
        }

        curl_multi_close($this->mh);
        $this->parallels = [];
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->request($request->getMethod(), $request->getUri(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface 
    {
        return $this->request($request->getMethod(), $request->getUri());
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsync(RequestInterface $request, array $options = []): PromiseInterface
    {
        return $this->requestAsync($request->getMethod(), $request->getUri(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function requestAsync(string $method, UriInterface|string $uri = '', array $options = []): PromiseInterface
    {
        return Async::awaitPromise(fn() => $this->request(
            $method, 
            $uri, 
            $options
        ));
    }
    
    /**
     * {@inheritdoc}
     */
    public function request(string $method, UriInterface|string $uri = '', array $options = []): ResponseInterface
    {
        if ($this->isMulti) {
            throw new ClientException(
                'Multi request requires using add() to queue and run() or iterator() to execute.',
                ClientException::LOGIC_ERROR
            );
        }        

        $method = strtoupper($method);
        [$curl, $stream, $cookies, $isStream, $printHeaders] = $this->buildRequestOptions($method, $uri, $options);

        $onBeforeRequest = $this->mutable['onBeforeRequest'] ?? null;
       
        if($onBeforeRequest && is_callable($onBeforeRequest)){
            $onBeforeRequest($this->options[CURLOPT_URL], $this->options[CURLOPT_HTTPHEADER] ?? [], null);
        }

        [$response, $info] = $this->doRequest($curl);
        
        if(($this->headers['Content-Length'][0] ?? 0) === 0){
            $this->headers['Content-Length'] = [$this->contentLength ?: strlen($response)];
        }

        if($isStream && $stream instanceof Stream){
            $stream->rewind();
        }elseif($printHeaders){
            $response = self::toHeaderString($this->headers) . "\r\n" . $response;
        }

        return new Response(
            statusCode: (int) ($info['http_code'] ?? 0),
            headers: $this->headers,
            contents: $response,
            info: $info,
            stream: $stream,
            cookie: $this->parseResponseCookies($cookies, $this->headers['Set-Cookie'] ?? null)
        );
    }

    /**
     * Build CURL request options.
     * 
     * @param string $method The HTTP request method.
     * @param UriInterface|string Optional URL to use.
     * @param array<string,mixed> Optional request options.
     * 
     * @return array Return an array of request information and CURL object. 
     * @throws ClientException Throw of error occurs.
    */
    private function buildRequestOptions(string $method, UriInterface|string $uri, array $options): array
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new ClientException(sprintf(
                'Invalid request method. Supported methods: [%s]', 
                implode(', ', self::METHODS)
            ));
        }

        $curl = curl_init();

        if ($curl === false) {
            throw new ClientException('Failed to initialize cURL client connection.');
        }

        $this->mutable = array_extend_default($this->config, $options);
        $this->headers = [];
        $this->contentLength = 0;
        $this->isHeaderDone = false;

        $headers = $this->mutable['headers'] ?? [];
        $decoding = $this->mutable['decode_content'] ?? false;
        $verify = $this->mutable['verify'] ?? null;
        $ssl = $this->mutable['ssl'] ?? null;
        $proxy = $this->mutable['proxy'] ?? null;
        $cookies = $this->mutable['cookies'] ?? null;
        $onProgress = $this->mutable['onProgress'] ?? null;
   
        $this->options = array_replace(
            [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_MAXREDIRS => (int) ($this->mutable['max'] ?? 5),
                CURLOPT_TIMEOUT_MS => (int) ($this->mutable['timeout'] ?? 0),
                CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->mutable['connect_timeout'] ?? 0),
                CURLOPT_FOLLOWLOCATION => (bool) ($this->mutable['allow_redirects'] ?? true),
                CURLOPT_FILETIME => (bool) ($this->mutable['file_time'] ?? false),
                CURLOPT_USERAGENT => $headers['User-Agent'] ?? Luminova::copyright(true),
                CURLOPT_HTTP_VERSION => $this->mutable['version'] ?? CURL_HTTP_VERSION_NONE,
            ],
            $this->options
        );
    
        $this->options[CURLOPT_HEADER] = false;
        $this->options[CURLOPT_RETURNTRANSFER] = true;
        $this->options[CURLOPT_URL] = $this->parseUrl(
            $this->mutable['base_uri'] ?? null, 
            $uri,
            $this->mutable['idn_conversion'] ?? true
        );

        if(!$this->options[CURLOPT_URL] || $this->options[CURLOPT_URL] === '/'){
            curl_close($curl);
            throw new ConnectException(sprintf(
                'Invalid or missing URL: "%s" must be a valid absolute URL or a relative path.',
                $this->options[CURLOPT_URL]
            ));
        }

        if ($this->mutable['referer'] ?? false) {
            $headers['Referer'] = $headers['Referer'] ?? APP_URL;
            $this->options[CURLOPT_AUTOREFERER] = true;
        }

        if ($decoding) {
            $this->options[CURLOPT_ENCODING] = $decoding;
        }

        if($ssl || $verify){
            $this->setSsl($ssl, $verify);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $isBody = isset($this->mutable['body']);
            $isParam = isset($this->mutable['form_params']);
            $isMultipart = isset($this->mutable['multipart']);
            $data = [];

            if ($isBody) {
                $data = self::getPostFields($this->mutable['body']);
                $this->options[CURLOPT_POST] = ($method === 'POST');
            } elseif ($isParam) {
                $data = http_build_query($this->mutable['form_params'], '', '&');
            } elseif ($isMultipart) {
                $data = self::getMultiPart($this->mutable['multipart']);
            }

            $this->options[CURLOPT_POSTFIELDS] = $data;

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = (!$isBody && $isParam) 
                    ? 'application/x-www-form-urlencoded' 
                    : ($isMultipart ? 'multipart/form-data' : 'application/json');

            }
        } elseif($method === 'HEAD') {
            $this->options[CURLOPT_NOBODY] = true;
        }

        if ($method !== 'GET' && $method !== 'HEAD') {
            $this->options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (!empty($this->mutable['query'])) {
            $url = $this->options[CURLOPT_URL];
            $url .= (str_contains($url, '?') ? '&' : '?');
            $url .= http_build_query($this->mutable['query'], '', '&', PHP_QUERY_RFC3986);
        
            $this->options[CURLOPT_URL] = $url;
            $this->options[CURLOPT_HTTPGET] = ($method === 'GET');
        }        

        if($cookies !== null){
            $cookies = is_string($cookies) ? new CookieFileJar($cookies) : $cookies;

            if (!$cookies instanceof CookieJarInterface) {
                self::handleException(sprintf(
                    'Cookie class does not implement %s interface', 
                    CookieJarInterface::class
                ), AppException::NOT_SUPPORTED);
            }

            $cookieString = $cookies->getCookieStringByDomain($this->options[CURLOPT_URL] ?? '');
        
            if ($cookieString) {
                if ($cookies->isEmulateBrowser()) {
                    $headers['Cookie'] = $cookieString;
                } else {
                    $this->options[CURLOPT_COOKIE] = $cookieString;
                }
            }
        
            $this->options[CURLOPT_COOKIESESSION] = $cookies->isNewSession();
        }

        if ($headers) {
            $this->options[CURLOPT_HTTPHEADER] = self::toRequestHeaders($headers);
        }

        if ($proxy) {
            $this->setProxy($proxy);
        }

        $onHeader = ($this->mutable['on_headers'] ?? null);
        $isStream = (bool) ($this->mutable['stream'] ?? false);
        $res = ($onHeader && is_callable($onHeader)) 
            ? new Response(204, ['Content-Length' => [0]]) 
            : null;
        $stream = $isStream 
            ? self::createStreamResponse($curl, $this->mutable['sink'] ?? 'php://temp')
            : null;
        $printHeaders = (bool) ($this->mutable['output_headers'] ?? false);
       
        $this->options[CURLOPT_HEADERFUNCTION] = fn($curl, $header) => $this->onHeaderFunction(
            $header,
            $printHeaders,
            $stream, 
            $res,
            $onHeader
        );

        if ($isStream) {
            $this->options[CURLOPT_WRITEFUNCTION] = fn($curl, $data) => $this->onWriteFunction(
                $data,
                $stream, 
                $printHeaders
            );
        }

        if ($onProgress) {
            $this->options[CURLOPT_NOPROGRESS] = false;
            $this->options[CURLOPT_PROGRESSFUNCTION] = static function (
                mixed $resource,
                float $downloadSize,
                float $downloaded,
                float $uploadSize,
                float $uploaded
            ) use ($onProgress) {
                $onProgress($resource, $downloadSize, $downloaded, $uploadSize, $uploaded);
            };
        }

        if (!curl_setopt_array($curl, $this->options)) {
            $failed = $this->getFailedOptions($curl);
            curl_close($curl);

            throw new RequestException("Failed to set cURL request options.{$failed}");
        }

        return [
            $curl,
            $stream,
            $cookies,
            $isStream,
            $printHeaders
        ];
    }

    /**
     * Attach per-request metadata needed during response collection.
     *
     * @param int $idx The request index.
     * @param array $values The processed request properties.
     * 
     * @return void
     */
    private function setMultiMetadata(int $idx, array $values): void
    {
        $this->parallels[$idx]['metadata'] = [
            'curl'         => $values[0],
            'stream'       => $values[1],
            'cookies'      => $values[2],
            'isStream'     => $values[3],
            'printHeaders' => $values[4],
            'headers'      => [],
            'onBeforeRequest' => $this->mutable['onBeforeRequest'] ?? null,
            'url' => $this->options[CURLOPT_URL],
            'httpHeaders' => $this->options[CURLOPT_HTTPHEADER] ?? [],
            'isHeaderDone' => false,
            'contentLength' => 0
        ];
    }

    /**
     * Prepare and bind all handlers to the curl multi handle.
     * 
     * @param string $fn The calling class method.
     * 
     * @return void
     */
    private function setMultiHandlers(string $fn): void
    {
        if (!$this->isMulti) {
            self::handleException(sprintf(
                '%s can only be used in multi mode. Call Curl::multi() before running requests.',
                $fn
            ), AppException::LOGIC_ERROR);
        }        

        $this->response = [];

        foreach ($this->parallels as $idx => $request) {
            [$ch, $stream, $cookies, $isStream, $printHeaders] = 
                $this->buildRequestOptions(
                    $request['method'],
                    $request['uri'],
                    $request['options']
                );

            if (curl_multi_add_handle($this->mh, $ch) !== CURLM_OK) {
                throw new ClientException("Unable to add cURL handle for request at index {$idx}.");
            }

            $this->setMultiMetadata($idx, [
                $ch, $stream, $cookies, $isStream, $printHeaders,
            ]);
        }
    }

    /**
     * Collect completed requests and either yield or store them.
     *
     * @param CurlHandle $ch Current CURL request to process.
     * 
     * @return Luminova\Interface\ResponseInterface|ResponseInterface|null Return response object.
     */
    private function getMultiResponse(CurlHandle $ch): ?ResponseInterface
    {
        $jobId = $this->findParallelIndex($ch);
        $request = $this->parallels[$jobId]['metadata'] ?? null;

        if ($jobId === null || $request === null) {
            curl_multi_remove_handle($this->mh, $ch);
            curl_close($ch);
            return null;
        }

        $this->jobId = $jobId;
        $headers = $request['headers'];
        $onBeforeRequest = $request['onBeforeRequest'];

        if ($onBeforeRequest && is_callable($onBeforeRequest)) {
            $onBeforeRequest($request['url'], $request['httpHeaders'] ?? [], $this->jobId);
        }
        
        [$body, $info] = $this->doMultiRequest($ch, $this->parallels[$this->jobId]['method']);
      
        if (($headers['Content-Length'][0] ?? 0) === 0) {
            $headers['Content-Length'] = [
                $request['contentLength'] ?: strlen($body)
            ];
        }

        if ($request['isStream'] && $request['stream'] instanceof Stream) {
            $request['stream']->rewind();
        } elseif ($request['printHeaders']) {
            $body = self::toHeaderString($headers) . "\r\n" . $body;
        }

        unset($this->parallels[$this->jobId]);

        return new Response(
            statusCode: (int) ($info['http_code'] ?? 0),
            headers: $headers,
            contents: $body,
            info: $info,
            stream: $request['stream'],
            cookie: $this->parseResponseCookies(
                $request['cookies'],
                $headers['Set-Cookie'] ?? null
            )
        );
    }

    /**
     * Execute CURL request and return response and info.
     * 
     * @param CurlHandle $curl The CURL object that resolved to options.
     * 
     * @return array{0: string|null, 1: array} Return response and info.
     * @throws AppException Throw app exception.
     */
    private function doRequest(CurlHandle $curl): array
    {
        $response = curl_exec($curl);
        $errorCode = curl_errno($curl);
    
        if ($response === false || $errorCode) {
            $error = curl_error($curl);

            curl_close($curl);
            self::handleException($error, $errorCode);
        }

        $info = (array) curl_getinfo($curl);
        curl_close($curl);
        
        return [$response, $info];
    }

    /**
     * Read content and info from a completed curl handle.
     *
     * @param CurlHandle $curl
     * 
     * @return array{0: string|null, 1: array} Return response and info.
     */
    private function doMultiRequest(CurlHandle $curl, string $method): array
    {
        $response = curl_multi_getcontent($curl);
        $errorCode = curl_errno($curl);

        if (($method !== 'HEAD' && !$response) || $errorCode) {
            $error = curl_error($curl);
            curl_multi_remove_handle($this->mh, $curl);
            curl_close($curl);
            self::handleException($error, $errorCode);
        }

        $info = (array) curl_getinfo($curl);
        curl_multi_remove_handle($this->mh, $curl);
        curl_close($curl);

        return [$response, $info];
    }

    /**
     * Identifies which cURL options failed to be set and returns them as a formatted string.
     *
     * @param CurlHandle $curl The cURL handle to test option setting against.
     * 
     * @return string Return formatted error message listing failed options, or empty string if none failed.
     */
    private function getFailedOptions(CurlHandle $curl): string
    {
        $failed = [];
        
        foreach ($this->options as $opt => $value) {
            if (!curl_setopt($curl, $opt, $value)) {
                $failed[] = self::toOptionName($opt);
            }
        }
        
        return ($failed !== [])
            ? ' Failed options: [' . implode(', ', $failed) . ']'
            : '';
    }

    /**
     * Process and handle incoming HTTP headers during a cURL request.
     *
     * @param string $header The raw header string to process.
     * @param Stream|null $stream The stream object to write headers to if outputting headers.
     * @param ResponseInterface|null $response The response object to update with new headers.
     * @param bool $printHeaders Whether to output headers to the stream.
     * @param callable|null $onHeader Optional callback function to execute for each header.
     *
     * @return int The length of the processed header.
     *
     * @throws ClientException If the header callback throws an exception.
     */
    private function onHeaderFunction(
        string $header,
        bool $printHeaders,
        ?Stream $stream = null, 
        ?ResponseInterface $response = null,
        ?callable $onHeader = null
    ): int
    {
        $length = strlen($header);

        if (trim($header) === '') {
            $this->setMetadata('isHeaderDone', true);

            return ($printHeaders && $stream instanceof Stream) ? $stream->write($header) : $length;
        }

        if (($head = self::normalizeHeader($header)) !== null) {

            $this->setHeader($head[0], $head[1]);

            if ($response instanceof ResponseInterface) {
                try{
                    $onHeader(
                        $response->withHeader('Content-Length', $this->headers['Content-Length'] ?? [0]), 
                        $header
                    );
                }catch(Exception $e){
                    throw new ClientException(
                        sprintf('Response rejected: %s (code: %d).', $e->getMessage(), $e->getCode()),
                        ClientException::HTTP_CLIENT_ERROR,
                        $e
                    );
                }
            }
        }

        return ($printHeaders && $stream instanceof Stream) ? $stream->write($header) : $length;
    }

    /**
     * Find the index of a parallel request by its associated cURL handle.
     *
     * @param CurlHandle $ch The cURL handle to search for.
     * 
     * @return int|null Return the index of the multi request if found, otherwise null.
     */
    private function findParallelIndex(CurlHandle $ch): ?int
    {
        foreach ($this->parallels as $idx => $meta) {
            if ($meta['metadata']['curl'] === $ch) {
                return $idx;
            }
        }

        return null;
    }

    /**
     * Set a request header for the current job.
     *
     * If running in multi mode, the header is set in the metadata for the current job.
     * Otherwise, it's added to the standard headers array.
     *
     * @param string $key Header name.
     * @param mixed $value Header value.
     * 
     * @return void
     */
    private function setHeader(string $key, mixed $value): void 
    {
        if ($this->isMulti) {
            $job = $this->parallels[$this->jobId] ?? [];
            $headers = $job['metadata']['headers'] ?? [];
            $headers[$key] = $value;

            $this->parallels[$this->jobId]['metadata']['headers'] = $headers;
            return;
        }

        $this->headers[$key] = $value;
    }

    /**
     * Store a metadata value for the current job or instance.
     *
     * If running in parallel mode, the value is set in the job's metadata.
     * Otherwise, it's stored directly in the instance property.
     *
     * @param string $key Metadata key.
     * @param mixed $value Metadata value.
     * 
     * @return void
     */
    private function setMetadata(string $key, mixed $value): void 
    {
        if ($this->isMulti) {
            $this->parallels[$this->jobId]['metadata'][$key] = $value;
            return;
        }

        $this->{$key} = $value;
    }

    /**
     * Retrieve a metadata value for the current job or instance.
     *
     * If running in parallel mode, the value is fetched from the job's metadata.
     * Otherwise, it's retrieved from the instance property.
     *
     * @param string $key Metadata key to retrieve.
     * @param mixed $default Default value to return if the key does not exist.
     * 
     * @return mixed The metadata value or default if not found.
     */
    private function getMetadata(string $key, mixed $default = null): mixed 
    {
        if ($this->isMulti) {
            return $this->parallels[$this->jobId]['metadata'][$key] ?? $default;
        }

        return $this->{$key} ?? $default;
    }

    /**
     * Handles writing data to the stream during a cURL request.
     *
     * @param string $data The incoming data chunk to be written.
     * @param Stream $stream The stream object to write the data to.
     * @param bool $printHeaders Whether headers should be included in the output.
     *
     * @return int Return the number of bytes written to the stream, or the length of the data if not written.
     */
    private function onWriteFunction(
        string $data,
        Stream $stream, 
        bool $printHeaders
    ): int
    {
        $isDone = $this->getMetadata('isHeaderDone', false);

        if($isDone || $printHeaders){
            $bytes = $stream->write($data); 

            if($isDone){
                $length = $this->getMetadata('contentLength', 0) + $bytes;

                $this->setMetadata('contentLength', $length);
                $this->setHeader('Content-Length', [$length]);
            }

            return $bytes;
        }

        return strlen($data);
    }

    /**
     * Sets SSL options for the cURL request.
     *
     * This function configures SSL/TLS settings for the cURL request, including
     * certificate verification and custom certificate files.
     *
     * @param array|string|null $ssl SSL configuration. Can be:
     *                               - A string representing the path to the certificate info file
     *                               - An array with keys:
     *                                 'verify_host' (int): SSL verification level (0-2)
     *                                 'cert_info' (string): Path to the certificate info file
     *                                 'ssl_cert_file' (string): Path to the SSL certificate file
     *                                 'ssl_cert_password' (string): Password for the SSL certificate
     *                                 'cert_timeout' (int): Certificate cache timeout
     * @param string|bool|null $verify SSL verification or cert path.
     *
     * @return void
     * @throws ConnectException Throw if path is not valid.
     */
    private function setSsl(array|null $ssl, string|bool|null $verify): void
    {
        if ($verify !== null) {
            if ($verify === true) {
                $this->options[CURLOPT_SSL_VERIFYPEER] = true;
                $this->options[CURLOPT_SSL_VERIFYHOST] = 2;
                return;
            }

            if (is_string($verify)) {
                if (!is_readable($verify)) {
                    throw new ConnectException("The provided CA certificate file '{$verify}' is not readable.");
                }

                $this->options[CURLOPT_CAINFO] = $verify;
                return;
            }
        }

        if (!$ssl || $ssl === []) {
            return;
        }

        $certInfo = $ssl['cert_info'] ?? null;
        $sslCertFile = $ssl['ssl_cert_file'] ?? null;
        $certPath = $ssl['cert_path'] ?? null;
        $sslCertPassword = $ssl['ssl_cert_password'] ?? null;

        if (isset($ssl['cert_timeout'])) {
            $this->options[CURLOPT_CA_CACHE_TIMEOUT] = (int) $ssl['cert_timeout'];
        }

        if (isset($ssl['verify_host'])) {
            $this->options[CURLOPT_SSL_VERIFYHOST] = (int) $ssl['verify_host'];
        }

        if ($certInfo) {
            if (!is_readable($certInfo)) {
                throw new ConnectException("The CA certificate info file '{$certInfo}' is not readable.");
            }
            $this->options[CURLOPT_CAINFO] = $certInfo;
        }

        if ($certPath) {
            if (!is_readable($certPath)) {
                throw new ConnectException("The certificate path '{$certPath}' is not readable.");
            }
            $this->options[CURLOPT_CAPATH] = $certPath;
        }

        if ($sslCertFile) {
            if (!is_readable($sslCertFile)) {
                throw new ConnectException("The SSL certificate file '{$sslCertFile}' is not readable.");
            }
            $this->options[CURLOPT_SSLCERT] = $sslCertFile;
        }

        if ($sslCertPassword) {
            $this->options[CURLOPT_SSLCERTPASSWD] = $sslCertPassword;
        }
    }

    /**
     * Sets the proxy for the cURL request based on the provided proxy configuration.
     *
     * @param array|string $proxy The proxy configuration, which can be a string or an associative array.
     *
     * @return void
     * @throws RequestException If the provided proxy format is invalid.
     */
    private function setProxy(array|string $proxy): void
    {
        if (is_string($proxy)) {
            $this->options[CURLOPT_PROXY] = $proxy;
            return;
        }

        if (is_array($proxy)) {
            $url = $this->options[CURLOPT_URL] ?? '';
            $parsedUrl = parse_url($url);
            $scheme = $parsedUrl['scheme'] ?? 'http';
            $host = $parsedUrl['host'] ?? '';

            if (!empty($proxy['no'])) {
                foreach ($proxy['no'] as $noProxy) {
                    if (str_ends_with($host, $noProxy)) {
                        return;
                    }
                }
            }

            if (isset($proxy[$scheme])) {
                $this->options[CURLOPT_PROXY] = $proxy[$scheme];
            }

            if (!empty($this->options[CURLOPT_PROXY])) {
                if (!empty($proxy['username']) && !empty($proxy['password'])) {
                    $this->options[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
                }
            }

            return;
        }

        throw new RequestException('Invalid proxy format. Expected string or array.');
    }

    /**
     * Constructs a complete URL by combining a base URL and a URI.
     *
     * This method handles various scenarios:
     * - If the base URL is null or the URI is a valid URL, it returns the URI as-is.
     * - Otherwise, it combines the base URL and URI, ensuring proper formatting.
     *
     * @param string|null $base The base URL. Can be null if not needed.
     * @param UriInterface|string $uri The URI to append to the base URL. Default is an empty string.
     * @param int|bool $idn If IDN support.
     *
     * @return string Return the complete URL formed by combining the base URL and URI.
     */
    private function parseUrl(?string $base, UriInterface|string $uri = '', int|bool $idn = true): string
    {
        if($uri instanceof Uri){
            return ($base === null) 
                ? $uri->toAsciiIdn()
                : $uri->withHost($base)->toAsciiIdn();
        }

        $uri = ($uri instanceof UriInterface) 
            ? (string) (!$base ? $uri : $uri->withHost($base))
            : (!$base ? $uri : rtrim($base, '/') . '/' . ltrim($uri, '/'));

        if ($uri === '/' || $idn === false || filter_var($uri, FILTER_VALIDATE_URL)) {
            return $uri;
        }

        $parts = parse_url($uri);

        if (!isset($parts['host'])) {
            throw new RequestException(
                sprintf('Invalid URL structure: (%s) Missing host component. Ensure the URL is correctly formatted.', $uri)
            );
        }

        try{
            return Uri::fromArray($parts)->toAsciiIdn($idn === true ? IDNA_DEFAULT : $idn);
        }catch(Throwable $e){
            if($e->getCode() === AppException::NOT_SUPPORTED){
                return $uri;
            }

            throw $e;
        }
    }

    /**
     * Creates and returns a Stream object from a provided resource or file path.
     * If a file path is provided, it opens a stream to that file. The method ensures
     * the stream is both readable and writable, throwing an exception if these conditions
     * are not met or if the stream cannot be opened.
     *
     * @param CurlHandle $curl The cURL handle, passed by reference, used to close the connection in case of an error.
     * @param mixed $sink A resource or a string representing the file path to be used for the stream.
     * 
     * @return Stream Return the created Stream object.
     * @throws RequestException If the stream cannot be opened, or if it is not both readable and writable.
     */
    private static function createStreamResponse(CurlHandle &$curl, mixed $sink): Stream
    {
        if (!is_resource($sink)) {
            $handler = @fopen($sink, 'r+');
            
            if ($handler === false) {
                curl_close($curl);
                throw new RequestException(sprintf('Failed to open temporary stream for "%s".', $sink));
            }
            
            $sink = $handler;
        }

        $stream = new Stream($sink);

        if (!$stream->isReadable() || !$stream->isWritable()) {
            curl_close($curl);
            throw new RequestException('Stream must be both readable and writable.');
        }

        return $stream;
    }

    /**
     * Get the human-readable name of a cURL option (for better debugging).
     * 
     * @param int $option The option identifier.
     * 
     * @return string|int Return option name.
     */
    private static function toOptionName(int $option): string|int
    {
        if (self::$constants === []) {
            self::$constants = array_flip(get_defined_constants(true)['curl']);
        }

        return self::$constants[$option] ?? $option;
    }

    /**
     * Normalize an HTTP header string into a key-value pair array.
     *
     * @param string $header The raw HTTP header string.
     *
     * @return array|null An array with the header key and value(s) if valid, null otherwise.
     */
    private static function normalizeHeader($header): ?array
    {
        if (str_contains($header, ': ')) {
            [$key, $value] = explode(': ', $header, 2);

            $key = trim($key);
            Normalizer::assertHeader($key);

            $value = trim($value);
            Normalizer::assertValue($value);

            return [$key, [$value]];
        }
        
        if(stripos($header, 'HTTP/') === 0){
            return ['X-Response-Protocol-Status-Phrase', [trim($header)]];
        }

        return null;
    }

    /**
     * Convert an associative array of POST data into a JSON string.
     *
     * @param array $data The POST data array.
     *
     * @return string|null Return JSON-encoded string if data is not empty, null otherwise.
     * @throws ClientException If JSON encoding fails.
     */
    private static function getPostFields(array $data): ?string
    {
        if ($data === []) {
            return null;
        }

        try{
            return json_encode($data, JSON_THROW_ON_ERROR);
        }catch(JsonException|Exception $e){
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create a multipart form data array from given items.
     *
     * @param array $items The array of multipart items, each containing 'name' and 'contents'.
     *
     * @return array The formatted multipart data array.
     * @throws RequestException If an item is missing the required 'name' key or has invalid contents.
     */
    public static function getMultiPart(array $items): array
    {
        $multipart = [];
    
        foreach ($items as $item) {
            if (!isset($item['name'])) {
                throw new RequestException("The 'name' key is required for each multipart item.");
            }
    
            $line = ['name' => $item['name']];
    
            if (is_string($item['contents'])) {
                // If contents is a string, it's either a plain data or a file path
                // Otherwise, treat it as plain data
                if (is_file($item['contents']) && is_readable($item['contents'])) {
                    $line['contents'] = new CurlFile($item['contents']);
                } else {
                    $line['contents'] = $item['contents'];
                }
            } elseif ($item['contents'] instanceof CurlFile) {
                $line['contents'] = $item['contents'];
            } else {
                throw new RequestException("Invalid contents for multipart item: " . print_r($item, true));
            }
    
            if (isset($item['filename'])) {
                $line['filename'] = $item['filename'];
            }
    
            $multipart[] = $line;
        }
    
        return $multipart;
    }

    /**
     * Convert response headers array into string representation.
     * 
     * @param array $headers The headers to convert to string.
     * 
     * @return string Return the string representation.
     */
    private static function toHeaderString(array $headers): string
    {
        $line = '';

        foreach ($headers as $name => $values) {
            if($name === 'X-Response-Protocol-Status-Phrase'){
                $line .= $values[0] . "\r\n";
                continue;
            }

            if($name === 'Content-Length' && $values[0] === 0){
                continue;
            }

            foreach ($values as $part) {
                $line .= $name . ': ' . $part . "\r\n";
            }
        }

        return $line;
    }

    /**
     * Convert an array of headers to cURL format.
     *
     * @param array $headers The request headers.
     *
     * @return array<int,string> Return request headers as array.
     */
    private static function toRequestHeaders(array $headers): array
    {
        $line = [];
        foreach ($headers as $key => $value) {
            $key = (string) $key;
            if(($key === 'X-Powered-By' && $value === false) || Network::SKIP_HEADER === $value){
                continue;
            }

            Normalizer::assertHeader($key);
            $value = Normalizer::normalizeHeaderValue($value);

            $line[] = "{$key}: {$value[0]}";
        }

        return $line;
    }

    /**
     * Parses 'Set-Cookie' headers from the response and updates the cookie jar.
     *
     * If cookies are enabled and Set-Cookie headers exist in the response:
     * 1. Extracts cookies from the headers using the cookie jar's parser
     * 2. Updates the jar with the new cookies
     * 3. Returns a new read-only CookieFileJar containing the parsed cookies
     *
     * Returns null if:
     * - No cookie jar was provided
     * - No Set-Cookie headers were present in the response
     *
     * @param CookieJarInterface|null $cookies The cookie jar to update (or null if cookies disabled).
     * 
     * @return CookieJarInterface|null New read-only cookie jar with parsed cookies, or null if not applicable.
     */
    private function parseResponseCookies(?CookieJarInterface $cookies, ?array $setCookie = null): ?CookieJarInterface
    {
        if (!($cookies instanceof CookieJarInterface) || !$setCookie) {
            return null;
        }
        
        $headerCookies = $cookies->getFromHeader($setCookie);
        $cookies->setCookies($headerCookies);

        return CookieFileJar::newCookie(
            $headerCookies,
            [
                ...$cookies->getConfig(),
                'readOnly' => true
            ]
        );
    }

    /**
     * Handle different cURL error codes and throw appropriate exceptions.
     *
     * @param string $error The error message from the cURL request.
     * @param int $code The cURL error code.
     *
     * @return void
     * @throws ConnectException If there's a connection-related error.
     * @throws RequestException If there's an issue with the request format, timeout, or protocol.
     * @throws ClientException If the client received an unexpected response.
     * @throws ServerException If the server responds with unexpected behavior.
     */
    private static function handleException(string $error, int $code): void
    {
        $exception = match ($code) {
            CURLE_COULDNT_CONNECT => ['Connection failed', ConnectException::class],
            CURLE_URL_MALFORMAT => ['Invalid URL format', ConnectException::class],
            CURLE_OPERATION_TIMEOUTED => ['Connection timed out', ConnectException::class],
            CURLE_SSL_CONNECT_ERROR => ['SSL connection issue', ConnectException::class],
            CURLE_GOT_NOTHING => ['No response received', ClientException::class],
            CURLE_WEIRD_SERVER_REPLY => ['Unexpected server response', ServerException::class],
            CURLE_TOO_MANY_REDIRECTS => ['Too many redirects', ServerException::class],
            CURLE_UNSUPPORTED_PROTOCOL => ['Unsupported protocol', ServerException::class],
            CURLE_PARTIAL_FILE => ['Partial file received', ClientException::class],
            CURLE_ABORTED_BY_CALLBACK => ['Operation aborted by callback', ClientException::class],
            CURLE_SEND_ERROR => ['Failed to send data', ConnectException::class],
            CURLE_RECV_ERROR => ['Failed to receive data', ClientException::class],
            CURLE_HTTP_NOT_FOUND => ['Resource not found', RequestException::class],
            default => ['Request error', RequestException::class],
        };

        throw new $exception[1](sprintf('%s: %s (code: %d)', $exception[0], $error, $code));
    }
}