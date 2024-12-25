<?php
/**
 * Luminova Framework cURL network client class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Http\Client;

use \Luminova\Application\Foundation;
use \Luminova\Http\Message\Response;
use \Luminova\Http\Uri;
use \Luminova\Cookies\CookieFileJar;
use \Psr\Http\Message\ResponseInterface;
use \Luminova\Interface\CookieJarInterface;
use \Psr\Http\Message\UriInterface;
use \Psr\Http\Message\RequestInterface;
use \Luminova\Utils\Async;
use \Luminova\Storages\Stream;
use \Luminova\Http\Network;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Interface\ClientInterface;
use \Luminova\Functions\Normalizer;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;
use \Luminova\Exceptions\AppException;
use \CURLFile;
use \CurlHandle;
use \Exception;
use \JsonException;
use \Throwable;

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
     * {@inheritdoc}
     * 
     * @example - Example Request Client With base URL:
     * 
     * ```php
     * <?php
     * use Luminova\Http\Client\Curl;
     * $client = new Curl([
     *      'base_uri' => 'https://example.com/'
     * ]);
     * ```
     */
    public function __construct(private array $config = [])
    {
        if(($this->config['headers']['X-Powered-By'] ?? true) !== false){
            $this->config['headers']['X-Powered-By'] = $this->config['headers']['X-Powered-By'] ?? Foundation::copyright();
        }
        
        if(($this->config['headers']['User-Agent'] ?? true) !== false){
            $this->config['headers']['User-Agent'] = $this->config['headers']['User-Agent'] ?? Foundation::copyright(true);
        }
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
    public function setOption(int $option, mixed $value): self
    {
        $this->options[$option] = $value;
        return $this;
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
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'HEAD', 'OPTIONS', 'DELETE'], true)) {
            throw new ClientException('Invalid request method. Supported methods: GET, POST, HEAD, PATCH, PUT, OPTIONS, DELETE.');
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
        $onBeforeRequest = $this->mutable['onBeforeRequest'] ?? null;
   
        $this->options = array_replace(
            [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_MAXREDIRS => (int) ($this->mutable['max'] ?? 5),
                CURLOPT_TIMEOUT_MS => (int) ($this->mutable['timeout'] ?? 0),
                CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->mutable['connect_timeout'] ?? 0),
                CURLOPT_FOLLOWLOCATION => (bool) ($this->mutable['allow_redirects'] ?? true),
                CURLOPT_FILETIME => (bool) ($this->mutable['file_time'] ?? false),
                CURLOPT_USERAGENT => $headers['User-Agent'] ?? Foundation::copyright(true),
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

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
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

        if (isset($this->mutable['query'])) {
            $url = $this->options[CURLOPT_URL];
            $url .= (str_contains($url, '?') ? '&' : '?');
            $url .= http_build_query($this->mutable['query'], '', '&', PHP_QUERY_RFC3986);
        
            $this->options[CURLOPT_URL] = $url;
            $this->options[CURLOPT_HTTPGET] = true;
        }        

        if($cookies !== null && is_string($cookies)){
            $cookies = new CookieFileJar($cookies);
        }

        if ($cookies instanceof CookieJarInterface) {
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

        if (!curl_setopt_array($curl, $this->options)) {
            $failedOptions = [];
        
            foreach ($this->options as $opt => $value) {
                if (!curl_setopt($curl, $opt, $value)) {
                    $failedOptions[] = self::toOptionName($opt);
                }
            }
        
            $errorDetails = ($failedOptions !== [])
                ? " Failed options: " . implode(', ', $failedOptions) 
                : '';
            curl_close($curl);
            throw new RequestException("Failed to set cURL request options." . $errorDetails);
        }

        if($onBeforeRequest && is_callable($onBeforeRequest)){
            $onBeforeRequest($this->options[CURLOPT_URL], $this->options[CURLOPT_HTTPHEADER] ?? []);
        }

        $response = curl_exec($curl);
        $contents = '';

        if ($response === false) {
            $errorCode = curl_errno($curl);
            $error = curl_error($curl);
            curl_close($curl);
            self::handleException($error, $errorCode);
        }

        $info = (array) curl_getinfo($curl);
        curl_close($curl);
        
        if(($this->headers['Content-Length'][0] ?? 0) === 0){
            $this->headers['Content-Length'] = [$this->contentLength ?: strlen($response)];
        }

        if($isStream && $stream instanceof Stream){
            $stream->rewind();
        }else{
            $contents = $printHeaders 
                ? self::toHeaderString($this->headers) . "\r\n" . $response 
                : $response;
        }

        if ($cookies instanceof CookieJarInterface && isset($this->headers['Set-Cookie'])) {
            $headerCookies = $cookies->getFromHeader($this->headers['Set-Cookie']);
            $cookies->setCookies($headerCookies);
        
            $cookies = CookieFileJar::newCookie(
                $headerCookies,
                [
                    ...$cookies->getConfig(),
                    'readOnly' => true
                ]
            );
        }

        $response = null;
        return new Response(
            statusCode: (int) ($info['http_code'] ?? 0),
            headers: $this->headers,
            contents: $contents,
            info: $info,
            stream: $stream,
            cookie: $cookies
        );
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
            $this->isHeaderDone = true;
            return ($printHeaders && $stream instanceof Stream) ? $stream->write($header) : $length;
        }

        if (($head = self::normalizeHeader($header)) !== null) {
            $this->headers[$head[0]] = $head[1];

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
     * Handles writing data to the stream during a cURL request.
     *
     * @param string $data The incoming data chunk to be written.
     * @param Stream $stream The stream object to write the data to.
     * @param bool $printHeaders Whether headers should be included in the output.
     * @param array &$headers Reference to the headers array, which may be updated.
     *
     * @return int The number of bytes written to the stream, or the length of the data if not written.
     */
    private function onWriteFunction(
        string $data,
        Stream $stream, 
        bool $printHeaders
    ): int
    {
        if($this->isHeaderDone || $printHeaders){
            $bytes = $stream->write($data); 

            if($this->isHeaderDone){
                $this->contentLength += $bytes;
                $this->contentLength['Content-Length'] = [$this->contentLength];
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
     *
     * @throws ConnectException
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
     * @param array &$this->options The cURL options array, passed by reference
     *
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

        throw new RequestException("Invalid proxy format. Expected string or array.");
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
     * 
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
     * Handle different cURL error codes and throw appropriate exceptions.
     *
     * @param string $error The error message from the cURL request.
     * @param int $code The cURL error code.
     *
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
}