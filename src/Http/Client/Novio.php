<?php
/**
 * Luminova Framework network client class.
 * 
 * Network Optimized Versatile I/O Operations (Novio).
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Client;

use \CURLFile;
use \Exception;
use \Generator;
use \Throwable;
use \CurlHandle;
use \CurlMultiHandle;
use \Luminova\Http\Uri;
use \Luminova\Luminova;
use \Luminova\Http\Network;
use Luminova\Http\HttpCode;
use \Luminova\Utility\Async;
use \Luminova\Cookies\FileJar;
use \Luminova\Http\Message\Stream;
use \Luminova\Http\Message\Response;
use \Luminova\Http\Helper\Normalizer;
use function \Luminova\Funcs\array_extend_default;
use \Luminova\Exceptions\{ErrorCode, AppException};
use \Luminova\Interface\ResponseInterface as MsgResponseInterface;
use \Psr\Http\Message\{UriInterface, RequestInterface, ResponseInterface};
use \Luminova\Interface\{PromiseInterface, ClientInterface, CookieJarInterface};
use \Luminova\Exceptions\Http\{RequestException, ConnectException, ClientException, ServerException};

class Novio implements ClientInterface
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
     * Request response Headers.
     * 
     * @var array<string,array> $headers
     */
    private array $headers = [];

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
     * Request metadata
     * 
     * @var array<string,mixed> $metadata
     */
    private array $metadata = [
        'length'        => 0,
        'isHeaderDone'  => false
    ];

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
     * use Luminova\Http\Client\Novio;
     * 
     * $client = new Novio([
     *      'base_uri' => 'https://example.com/'
     * ]);
     * 
     * $response = $client->request('GET', 'about');
     * ```
     * 
     * @example - Example Using Novio multi request:
     * 
     * ```php
     * use Luminova\Http\Client\Novio;
     * 
     * $multi = Novio::multi([
     *      'base_uri' => 'https://example.com/'
     * ]);
     * $multi->run();
     * ```
     */
    public function __construct(private array $config = []) {}

    /**
     * Create a new multi Novio instance for parallel request execution.
     *
     * Call `add()` to queue requests, then `run()` or `iterator()` to execute.
     *
     * @param array<string, mixed> $config Optional configuration settings.
     * 
     * @return self Return a new instance of Novio class that resolves to Novio multi handler.
     *
     * @example - Example:
     * 
     * ```php
     * use Luminova\Http\Client\Novio;
     * $multi = Novio::multi();
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
     * This must be used after calling Novio::multi().
     *
     * @param string $method  HTTP method (e.g. 'GET', 'POST').
     * @param UriInterface|string $uri Optional target URI.
     * @param array<string,mixed> $options Optional request options (headers, body, etc).
     * 
     * @return self Return instance of Novio class.
     * @throws RequestException Throws if called without initializing Novio multi request.
     *
     * @example - Example:
     * ```php
     * use Luminova\Http\Client\Novio;
     * 
     * $multi = Novio::multi();
     * 
     * $multi->add('GET', 'https://example.com', ['headers' => ['X-Test' => 'yes']]);
     * $multi->add('POST', 'https://api.example.org', ['body' => 'data']);
     * ```
     */
    public function add(string $method, UriInterface|string $uri = '', array $options = []): self 
    {
        if(!$this->isMulti){
            self::handleException(sprintf(
                '%s can only be used in multi mode. Call Novio::multi() before adding requests.',
                __METHOD__
            ), ErrorCode::LOGIC_ERROR);
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
     * @throws RequestException Throws if called without initializing Novio multi request.
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
                ErrorCode::LOGIC_ERROR
            );
        }        

        $method = strtoupper(trim($method));
        [$curl, $stream, $cookies, $isStream, $printHeaders] = $this->buildRequestOptions($method, $uri, $options);

        $onBeforeRequest = $this->mutable['onBeforeRequest'] ?? null;
       
        if($onBeforeRequest && is_callable($onBeforeRequest)){
            $onBeforeRequest($this->options[CURLOPT_URL], $this->options[CURLOPT_HTTPHEADER] ?? [], null);
        }

        [$response, $info] = $this->doRequest($curl);
        
        if(($this->headers['Content-Length'][0] ?? 0) === 0){
            $length = $this->metadata['length'] ?? 0;
            $this->headers['Content-Length'] = [$length ?: strlen($response)];
        }

        if($isStream && ($stream instanceof Stream)){
            $isStream = true;
            $stream->rewind();
        }elseif($printHeaders){
            $response = self::toHeaderString($this->headers) . "\r\n" . $response;
        }

        [$httpVersion, $statusCode, $reasonPhrase] = $this->getResponseInfo(
            $this->headers, 
            $info
        );

        return new Response(
            body: $isStream ? $stream : $response,
            statusCode: (int) $statusCode,
            headers: $this->headers,
            info: $info,
            reasonPhrase: $reasonPhrase,
            protocolVersion: $httpVersion,
            cookie: $this->parseResponseCookies(
                $cookies, 
                $this->headers['Set-Cookie'] ?? null
            )
        );
    }

    /**
     * Extract response information.
     * 
     * @param array $headers The response headers.
     * @param array $info Response info.
     * 
     * @return array
     */
    private function getResponseInfo(array $headers, array $info): array 
    {
        $httpVersion = match($info['http_version'] ?? 0) {
            CURL_HTTP_VERSION_1_0 => '1.0',
            CURL_HTTP_VERSION_1_1 => '1.1',
            CURL_HTTP_VERSION_2_0,
            CURL_HTTP_VERSION_2TLS => '2.0',
            CURL_HTTP_VERSION_3 => '3.0',
            default => ''
        };

        $statusCode = (int) ($info['http_code'] ?? 0);
        $reasonPhrase = '';

        $phrase = $headers['X-Response-Protocol-Status-Phrase'][0] ?? null;
        if ($phrase && preg_match('#HTTP/(\d\.\d)\s+(\d+)\s+(.+)#', $phrase, $matches)) {
            $httpVersion = $httpVersion ?: $matches[1];
            $statusCode = $statusCode ?: (int) $matches[2];
            $reasonPhrase = trim($matches[3]);
        }

        $httpVersion = $httpVersion ?: '1.1';
        $reasonPhrase = $reasonPhrase ?: HttpCode::phrase($statusCode);

        return [$httpVersion, $statusCode, $reasonPhrase];
    }

    /**
     * Build CURL request options.
     * 
     * @param string $method The HTTP request method.
     * @param UriInterface|string Optional URL to use.
     * @param array<string,mixed> Optional request options.
     * 
     * @return array Return an array of request information and Novio object. 
     * @throws ClientException Throw of error occurs.
    */
    private function buildRequestOptions(string $method, UriInterface|string $uri, array $options): array
    {
        if ($method === '' || !in_array($method, self::METHODS, true)) {
            throw new ClientException(sprintf(
                'Unsupported HTTP method "%s". Allowed methods are: [%s].',
                $method ?: '(empty)',
                implode(', ', self::METHODS)
            ));
        }

        $curl = curl_init();

        if ($curl === false) {
            throw new ClientException('Unable to initialize cURL. Novio client could not start a connection.');
        }

        $this->extendOptions($options);

        $url = $this->toFullUrl($uri);

        if(!$url || $url === '/'){
            curl_close($curl);
            throw new ConnectException(sprintf(
                'Invalid URL resolved: "%s". Provide a valid absolute URL or a relative path to the base URI.',
                $url
            ));
        }

        $headers = $this->mutable['headers'] ?? [];
        $decoding = $this->mutable['decode_content'] ?? true;
        $verify = $this->mutable['verify'] ?? null;
        $ssl = $this->mutable['ssl'] ?? null;
        $proxy = $this->mutable['proxy'] ?? null;
        $cookies = $this->mutable['cookies'] ?? null;
        $onProgress = $this->mutable['onProgress'] ?? null;

        $this->options[CURLOPT_HEADER] = false;
        $this->options[CURLOPT_RETURNTRANSFER] = true;
        $this->options[CURLOPT_URL] = $url;

 
        if (isset($headers['User-Agent'])) {
            $headers['User-Agent'] = $headers['User-Agent'];
            $this->options[CURLOPT_USERAGENT] = $headers['User-Agent'];
        }

        if (isset($headers['Referer'])) {
            $this->options[CURLOPT_REFERER] = $headers['Referer'];
        }else{
            $this->options[CURLOPT_AUTOREFERER] = ($this->mutable['referer'] ?? false) === true;
        }

        if ($decoding !== true) {
            $this->options[CURLOPT_ENCODING] = ($decoding === false) ? null : $decoding;
        }

        if($ssl || $verify){
            $this->setSslOptions($ssl, $verify);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $isBody = !empty($this->mutable['body']);
            $isParam = !empty($this->mutable['form_params']);
            $isMultipart = !empty($this->mutable['multipart']);

            $this->options[CURLOPT_POST] = ($method === 'POST');
            $this->options[CURLOPT_POSTFIELDS] = match(true){
                $isParam        => http_build_query($this->mutable['form_params'], '', '&'),
                $isMultipart    => self::getMultiPart($this->mutable['multipart']),
                $isBody         => self::getPostFields($this->mutable['body']),
                default         => []
            };

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = (!$isBody && $isParam) 
                    ? 'application/x-www-form-urlencoded' 
                    : ($isMultipart ? 'multipart/form-data' : 'application/json');

            }
        } elseif($method === 'GET') {
            $this->options[CURLOPT_HTTPGET] = true;
        } elseif($method === 'HEAD') {
            $this->options[CURLOPT_NOBODY] = true;
        }

        if (!empty($this->mutable['query'])) {
            $url .= (str_contains($url, '?') ? '&' : '?');
            $url .= http_build_query($this->mutable['query'], '', '&', PHP_QUERY_RFC3986);

            $this->options[CURLOPT_URL] = $url;
        }
        
        if ($method !== 'GET' && $method !== 'HEAD') {
            $this->options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if($cookies !== null){
            $cookies = is_string($cookies) ? new FileJar($cookies) : $cookies;

            if (!$cookies instanceof CookieJarInterface) {
                self::handleException(sprintf(
                    'Cookie class does not implement %s interface', 
                    CookieJarInterface::class
                ), ErrorCode::NOT_SUPPORTED);
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
            $this->setProxyOptions($proxy);
        }

        $res = null;
        $stream = null;
        $onHeader = $this->mutable['on_headers'] ?? null;
        $isStream = (bool) ($this->mutable['stream'] ?? false);
        $printHeaders = (bool) ($this->mutable['output_headers'] ?? false);

        if($onHeader && is_callable($onHeader)){
            $res = new Response(statusCode: 204, headers: ['Content-Length' => [0]]);
        }else{
            $onHeader = null;
        }

        if($isStream){
            $stream = self::createStreamResponse($curl, $this->mutable['sink'] ?? 'php://temp');
        }
       
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
            ) use ($onProgress): void 
            {
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
     * Extend request options.
     * 
     * @param array<string,mixed> $options.
     */
    private function extendOptions(array $options): void 
    {
        $this->mutable = array_extend_default($this->config, $options);
        $this->headers = [];
        $this->metadata['length'] = 0;
        $this->metadata['isHeaderDone'] = false;

        $xPowered = $this->mutable['headers']['X-Powered-By'] ?? null;
        $userAgent = $this->mutable['headers']['User-Agent'] ?? null;
        $referer = $this->mutable['referer'] ?? null;

        if($xPowered === null || $xPowered === true){
            $this->mutable['headers']['X-Powered-By'] = Luminova::copyright();
        }
        
        if($userAgent === null || $userAgent === true){
            $this->mutable['headers']['User-Agent'] = Luminova::copyright(true);
        }

        if($referer === null){
            $this->mutable['headers']['Referer'] = APP_URL;
        }elseif ($referer && is_string($referer)) {
            $this->mutable['headers']['Referer'] ??= $referer;
        }

        $this->options = array_replace(
            [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_MAXREDIRS => (int) ($this->mutable['max'] ?? 5),
                CURLOPT_TIMEOUT_MS => (int) ($this->mutable['timeout'] ?? 0),
                CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->mutable['connect_timeout'] ?? 0),
                CURLOPT_FOLLOWLOCATION => (bool) ($this->mutable['allow_redirects'] ?? true),
                CURLOPT_FILETIME => (bool) ($this->mutable['file_time'] ?? false),
                CURLOPT_HTTP_VERSION => $this->mutable['version'] ?? CURL_HTTP_VERSION_NONE,
            ],
            $this->options
        );
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
            'length' => 0
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
                '%s can only be used in multi mode. Call Novio::multi() before running requests.',
                $fn
            ), ErrorCode::LOGIC_ERROR);
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
                $request['length'] ?: strlen($body)
            ];
        }

        $isStream = false;
        if ($request['isStream'] && $request['stream'] instanceof Stream) {
            $isStream = true;
            $request['stream']->rewind();
        } elseif ($request['printHeaders']) {
            $body = self::toHeaderString($headers) . "\r\n" . $body;
        }

        unset($this->parallels[$this->jobId]);

        [$httpVersion, $statusCode, $reasonPhrase] = $this->getResponseInfo(
            $headers, 
            $info
        );

        return new Response(
            body: $isStream ? $request['stream'] : $body,
            statusCode: (int) $statusCode,
            headers: $headers,
            info: $info,
            reasonPhrase: $reasonPhrase,
            protocolVersion: $httpVersion,
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

        $this->metadata[$key] = $value;
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

        return $this->metadata[$key] ?? $default;
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
                $length = $this->getMetadata('length', 0) + $bytes;

                $this->setMetadata('length', $length);
                $this->setHeader('Content-Length', [$length]);
            }

            return $bytes;
        }

        return strlen($data);
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

        if (trim($header) !== '' && ($head = self::normalizeHeader($header)) !== null) {
            $this->setHeader($head[0], $head[1]);

            if ($onHeader && ($response instanceof ResponseInterface)) {
                try{
                    $onHeader(
                        $response->withHeader(
                            'Content-Length', 
                            $this->headers['Content-Length'] ?? [0]
                        ), 
                        $header
                    );
                }catch(Exception $e){
                    throw new ClientException(
                        sprintf('Response rejected: %s (code: %d).', $e->getMessage(), $e->getCode()),
                        ErrorCode::HTTP_CLIENT_ERROR,
                        $e
                    );
                }
            }
        }

        return ($printHeaders && $stream instanceof Stream) 
            ? $stream->write($header) 
            : $length;
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
    private function setSslOptions(array|null $ssl, string|bool|null $verify): void
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
    private function setProxyOptions(array|string $proxy): void
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
     * Build a fully qualified URL from a base URI and a given path or URI.
     *
     * Rules:
     * - Empty `$uri`: return base URI if defined, otherwise empty.
     * - `Uri` instance: apply base host (if present) and convert to ASCII IDN.
     * - `UriInterface`: apply base host (if present).
     * - String: append to base URI with normalized slashes.
     * - If IDN conversion is disabled: validate and return raw URL.
     *
     * @param UriInterface|Uri|string $uri URI or path to resolve. Defaults to an empty string.
     *
     * @return string Return fully resolved request URL.
     *
     * @throws RequestException If the resulting URL is invalid.
     * @throws Throwable If IDN conversion fails for other reasons.
     */
    private function toFullUrl(UriInterface|string $uri = ''): string
    {
        $base = $this->mutable['base_uri'] ?? '';
        $hasBase = !empty($base);

        if($uri === '' || $uri === null){
            $uri = $hasBase ? $base : '';
        }elseif ($hasBase) {
            $uri = ($uri instanceof UriInterface) 
                ? $uri->withHost($base) 
                : rtrim($base, '/') . '/' . ltrim($uri, '/');
        }

        $parts = $this->perseUrl((string) $uri);
        $idnEnabled = $this->mutable['idn_conversion'] ?? true;

        if($idnEnabled && defined('IDNA_DEFAULT')){
            return $this->toIdnUrl($uri, $parts);
        }

        $uri = (string) $uri;
        
        if ($this->isUrl($uri)) {
            return $uri;
        }

        throw new RequestException(sprintf(
            'Invalid URL: "%s". Provide a valid absolute URL or relative path.',
            $uri
        ));
    }

    /**
     * Convert a URI to its ASCII representation using IDN (Internationalized Domain Names).
     *
     * - If the input is not a `Uri` instance, it is reconstructed from parsed parts.
     * - Applies the configured IDN conversion option (`idn_conversion`) and variant (`idn_variant`).
     * - Falls back to returning the raw URI string if IDN support is unavailable.
     *
     * @param UriInterface|string $uri  The URI to convert.
     * @param array $parts              Parsed components of the URI (from `parse_url` or equivalent).
     *
     * @return string URI with the host converted to its ASCII IDN form, or raw URI if unsupported.
     * @throws Throwable If conversion fails for reasons other than unsupported IDN.
     */
    private function toIdnUrl(UriInterface|string $uri, array $parts): string
    {
        if (!$uri instanceof Uri) {
            $uri = Uri::fromArray($parts);
        }

        $idn = $this->mutable['idn_conversion'] ?? true;

        try {
            return $uri->toAsciiIdn(
                ($idn === true) ? \IDNA_DEFAULT : $idn,
                $this->mutable['idn_variant'] ?? \INTL_IDNA_VARIANT_UTS46
            );
        } catch (Throwable $e) {
            if ($e->getCode() === ErrorCode::NOT_SUPPORTED) {
                return (string) $uri;
            }
            throw $e;
        }
    }

    /**
     * Check if the given value is a valid URL.
     *
     * @param mixed $url Value to test.
     *
     * @return bool Return true if the value is a valid URL, false otherwise.
     */
    private function isUrl(mixed $url): bool 
    {
        return $url && filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Parse a URL string into its component parts.
     *
     * Ensures that the parsed URL contains a valid `host` component.
     *
     * @param string $url The URL string to parse.
     *
     * @return array An associative array of URL components, as returned by `parse_url()`.
     * @throws RequestException If the URL is missing the host component or is invalid.
     */
    private function perseUrl(string $url): array 
    {
        $parts = $url ? parse_url($url) : [];

        if (!isset($parts['host'])) {
            throw new RequestException(
                sprintf('Invalid URL structure: (%s) Missing host component. Ensure the URL is correctly formatted.', $url)
            );
        }

        return $parts;
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
        }catch(Throwable $e){
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
            $head = strtolower($key);
            
            if(
                (($head === 'x-powered-by' || $head === 'user-agent') && $value === false) || 
                Network::SKIP_HEADER === $value
            ){
                continue;
            }

            Normalizer::assertHeader($key);
            $value = Normalizer::normalizeHeaderValue($value);

            $line[] = "{$key}: " . implode(', ', $value);
        }

        return $line;
    }

    /**
     * Parses 'Set-Cookie' headers from the response and updates the cookie jar.
     *
     * If cookies are enabled and Set-Cookie headers exist in the response:
     * 1. Extracts cookies from the headers using the cookie jar's parser
     * 2. Updates the jar with the new cookies
     * 3. Returns a new read-only Cookie FileJar containing the parsed cookies
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

        return FileJar::newCookie(
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