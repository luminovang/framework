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
use \Luminova\Cookies\CookieFileJar;
use \Luminova\Interface\CookieJarInterface;
use \Luminova\Storages\Stream;
use \Luminova\Http\Network;
use \Luminova\Interface\NetworkClientInterface;
use \Luminova\Functions\Normalizer;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;
use \CURLFile;
use \CurlHandle;
use \Exception;
use \JsonException;

class Curl implements NetworkClientInterface
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
     * Sends an HTTP request with the specified method, URL, data, and headers.
     *
     * @param string $method The HTTP method to use (e.g., GET, POST, PUT, DELETE).
     * @param string $url The target URL for the request.
     * @param array $data The data to be sent in the request body (default: empty array).
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return Response Return the response returned by the server.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function send(
        string $method, 
        string $url, 
        array $options = []
    ): Response
    {
        return $this->request($method, $url, $options);
    }

    /**
     * Fetches data from the specified URL using a GET request.
     *
     * @param string $url The URL to fetch data from.
     * @param array $headers An array of headers to include in the request (default: empty array).
     *
     * @return Response Return the server's response containing the fetched data.
     *
     * @throws RequestException If an error occurs while making the request.
     * @throws ConnectException If a connection to the server cannot be established.
     * @throws ClientException If the client encounters an error (4xx HTTP status codes).
     * @throws ServerException If the server encounters an error (5xx HTTP status codes).
     */
    public function fetch(
        string $url, 
        array $options = []
    ): Response
    {
        return $this->request('GET', $url, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): Response
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
        $headers = $this->mutable['headers'];
        $base_uri = $this->mutable['base_uri'] ?? null;
        $decoding = $this->mutable['decode_content'] ?? false;
        $verify = $this->mutable['verify'] ?? false;
        $proxy = $this->mutable['proxy'] ?? null;
        $cookies = $this->mutable['cookies'] ?? null;

        $curlOptions = [
            CURLOPT_URL => ($base_uri ? rtrim($base_uri) . '/' . ltrim($url) : $url),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_MAXREDIRS => (int) ($this->mutable['max'] ?? 5),
            CURLOPT_TIMEOUT => (int) ($this->mutable['connect_timeout'] ?? 0),
            CURLOPT_FOLLOWLOCATION => (bool) ($this->mutable['allow_redirects'] ?? true),
            CURLOPT_FILETIME => (bool) ($this->mutable['file_time'] ?? false),
            CURLOPT_USERAGENT => $headers['User-Agent'] ?? Foundation::copyright(true),
            CURLOPT_HTTP_VERSION => $this->mutable['version'] ?? CURL_HTTP_VERSION_NONE
        ];

        if ($this->mutable['referer'] ?? false) {
            $headers['Referer'] = $headers['Referer'] ?? APP_URL;
            $curlOptions[CURLOPT_AUTOREFERER] = true;
        }

        if ($decoding) {
            $curlOptions[CURLOPT_ENCODING] = $decoding ?? '';
        }

        if ($verify) {
            $curlOptions[CURLOPT_SSL_VERIFYPEER] = true;
            $curlOptions[CURLOPT_SSL_VERIFYHOST] = 2;

            if ($verify !== true) {
                if (!is_readable($verify)) {
                    curl_close($curl);
                    throw new ConnectException('The peer certificates path is not readable');
                }
                $curlOptions[CURLOPT_CAINFO] = $verify;
            }
        }

        $isPostable = in_array($method, ['POST', 'PUT', 'PATCH'], true);
        $data = null;

        if ($isPostable) {
            $isBody = isset($this->mutable['body']);
            $isParam = isset($this->mutable['form_params']);
            $isMultipart = isset($this->mutable['multipart']);

            if ($isBody) {
                $data = self::getPostFields($this->mutable['body']);
                $curlOptions[CURLOPT_POST] = ($method === 'POST');
            } elseif ($isParam) {
                $data = http_build_query($this->mutable['form_params']);
            } elseif ($isMultipart) {
                $data = self::getMultiPart($this->mutable['multipart']);
            }

            $curlOptions[CURLOPT_POSTFIELDS] = $data;

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = (!$isBody && $isParam) 
                    ? 'application/x-www-form-urlencoded' 
                    : ($isMultipart ? 'multipart/form-data' : 'application/json');

            }
        } elseif($method === 'HEAD') {
            $curlOptions[CURLOPT_NOBODY] = true;
        }

        if ($method !== 'GET' && $method !== 'HEAD') {
            $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (isset($this->mutable['query'])) {
            $url = $curlOptions[CURLOPT_URL];
            $url .= (str_contains($url, '?') ? '&' : '?');
            $url .= http_build_query($this->mutable['query']);
        
            $curlOptions[CURLOPT_URL] = $url;
            $curlOptions[CURLOPT_HTTPGET] = true;
        }        

        if($cookies !== null && is_string($cookies)){
            $cookies = new CookieFileJar($cookies);
        }

        if ($cookies instanceof CookieJarInterface) {
            $cookieString = $cookies->getCookieStringByDomain($curlOptions[CURLOPT_URL] ?? '');
        
            if ($cookieString) {
                if ($cookies->isEmulateBrowser()) {
                    $headers['Cookie'] = $cookieString;
                } else {
                    $curlOptions[CURLOPT_COOKIE] = $cookieString;
                }
            }
        
            $curlOptions[CURLOPT_COOKIESESSION] = $cookies->isNewSession();
        }

        if ($headers) {
            $curlOptions[CURLOPT_HTTPHEADER] = self::toRequestHeaders($headers);
        }

        if ($proxy) {
            $this->setProxy($proxy, $curlOptions);
        }

        if (!curl_setopt_array($curl, $curlOptions)) {
            $failedOptions = [];
        
            foreach ($curlOptions as $key => $value) {
                if (!curl_setopt($curl, $key, $value)) {
                    $failedOptions[] = self::getOptionName($key);
                }
            }
        
            $errorDetails = !empty($failedOptions) 
                ? " Failed options: " . implode(', ', $failedOptions) 
                : '';
        
            throw new RequestException("Failed to set cURL request options." . $errorDetails);
        }

        $responseHeaders = [];
        $contentSize = 0;
        $onHeader = ($this->mutable['on_headers'] ?? null);
        $isStream = (bool) ($this->mutable['stream'] ?? false);
        $res = ($onHeader !== null && is_callable($onHeader)) 
            ? new Response(204, ['Content-Length' => [0]]) 
            : null;
        $stream = $isStream 
            ? self::createStreamResponse($curl, $this->mutable['sink'] ?? 'php://temp')
            : null;

        $outputHeaders = (bool) ($this->mutable['output_headers'] ?? false);
        $headersComplete = false;

        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, $header) 
        use ($stream, &$responseHeaders, &$headersComplete, $onHeader, $res, $outputHeaders) {
            $length = strlen($header);

            if (trim($header) === '') {
                $headersComplete = true;
                return ($outputHeaders && $stream instanceof Stream) ? $stream->write($header) : $length;
            }

            if (($head = self::normalizeHeader($header)) !== null) {
                $responseHeaders[$head[0]] = $head[1];

                if ($res instanceof Response) {
                    try{
                        $onHeader(
                            $res->withHeader('Content-Length', $responseHeaders['Content-Length'] ?? [0]), 
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

            return ($outputHeaders && $stream instanceof Stream) ? $stream->write($header) : $length;
        });
        

        if ($isStream) {
            curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($curl, $data) 
            use ($stream, &$responseHeaders, &$contentSize, &$headersComplete, $outputHeaders) 
            {
                if($headersComplete || $outputHeaders){
                    $bytes = $stream->write($data); 

                    if($headersComplete){
                        $contentSize += $bytes;
                        $responseHeaders['Content-Length'] = [$contentSize];
                    }

                    return $bytes;
                }

                return strlen($data);
            });
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
        
        if(($responseHeaders['Content-Length'][0] ?? 0) === 0){
            $responseHeaders['Content-Length'] = [$contentSize ?: strlen($response)];
        }

        if($isStream && $stream instanceof Stream){
            $stream->rewind();
        }else{
            $contents = $outputHeaders 
                ? self::toHeaderString($responseHeaders) . "\r\n" . $response 
                : $response;
        }

        if ($cookies instanceof CookieJarInterface && isset($responseHeaders['Set-Cookie'])) {
            $headerCookies = $cookies->getFromHeader($responseHeaders['Set-Cookie']);
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
            headers: $responseHeaders,
            contents: $contents,
            info: $info,
            stream: $stream,
            cookie: $cookies
        );
    }

    /**
     * Sets the proxy for the cURL request based on the provided proxy configuration.
     *
     * @param array|string $proxy The proxy configuration, which can be a string or an associative array.
     * @param array &$curlOptions The cURL options array, passed by reference
     *
     * @throws RequestException If the provided proxy format is invalid.
     */
    private function setProxy(array|string $proxy, array &$curlOptions): void
    {
        if (is_string($proxy)) {
            $curlOptions[CURLOPT_PROXY] = $proxy;
            return;
        }

        if (is_array($proxy)) {
            $url = $curlOptions[CURLOPT_URL] ?? '';
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
                $curlOptions[CURLOPT_PROXY] = $proxy[$scheme];
            }

            if (!empty($curlOptions[CURLOPT_PROXY])) {
                if (!empty($proxy['username']) && !empty($proxy['password'])) {
                    $curlOptions[CURLOPT_PROXYUSERPWD] = $proxy['username'] . ':' . $proxy['password'];
                }
            }

            return;
        }

        throw new RequestException("Invalid proxy format. Expected string or array.");
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
        $type = '';
        switch ($code) {
            case CURLE_COULDNT_CONNECT:
                throw new ConnectException(sprintf('Connection failed: %s (code: %d).', $error, $code));
            case CURLE_URL_MALFORMAT:
                throw new ConnectException(sprintf('Invalid URL format: %s (code: %d).', $error, $code));
            case CURLE_OPERATION_TIMEOUTED:
                throw new ConnectException(sprintf('Connection timed out: %s (code: %d).', $error, $code));
            case CURLE_SSL_CONNECT_ERROR:
                throw new ConnectException(sprintf('SSL connection issue: %s (code: %d).', $error, $code));
            case CURLE_GOT_NOTHING:
                throw new ClientException(sprintf('No response received: %s (code: %d).', $error, $code));
            case CURLE_WEIRD_SERVER_REPLY:
                throw new ServerException(sprintf('Unexpected server response: %s (code: %d).', $error, $code));
            case CURLE_TOO_MANY_REDIRECTS:
                throw new ServerException(sprintf('Too many redirects: %s (code: %d).', $error, $code));
            case CURLE_UNSUPPORTED_PROTOCOL:
                throw new ServerException(sprintf('Unsupported protocol: %s (code: %d).', $error, $code));
            case CURLE_PARTIAL_FILE:
                throw new ClientException(sprintf('Partial file received: %s (code: %d).', $error, $code));
            case CURLE_ABORTED_BY_CALLBACK:
                throw new ClientException(sprintf('Operation aborted by callback: %s (code: %d).', $error, $code));
            case CURLE_SEND_ERROR:
                throw new ConnectException(sprintf('Failed to send data: %s (code: %d).', $error, $code));
            case CURLE_RECV_ERROR:
                throw new ClientException(sprintf('Failed to receive data: %s (code: %d).', $error, $code));
            case CURLE_HTTP_NOT_FOUND:
                $type = 'Resource not found';
                break;
            default:
                $type = 'Request error';
        }

        throw new RequestException(sprintf('%s: %s (code: %d)', $type, $error, $code));
    }

    /**
     * Get the human-readable name of a cURL option (for better debugging).
     * 
     * @param int $option The option identifier.
     * 
     * @return string|int Return option name.
     */
    private static function getOptionName(int $option): string|int
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
     * @param array $multipartItems The array of multipart items, each containing 'name' and 'contents'.
     *
     * @return array The formatted multipart data array.
     * @throws RequestException If an item is missing the required 'name' key or has invalid contents.
     */
    public static function getMultiPart(array $multipartItems): array
    {
        $multipart = [];
    
        foreach ($multipartItems as $item) {
            if (!isset($item['name'])) {
                throw new RequestException("The 'name' key is required for each multipart item.");
            }
    
            $multipartItem = [
                'name' => $item['name']
            ];
    
            if (is_string($item['contents'])) {
                // If contents is a string, it's either a plain data or a file path
                // Otherwise, treat it as plain data
                if (is_file($item['contents']) && is_readable($item['contents'])) {
                    $multipartItem['contents'] = new CurlFile($item['contents']);
                } else {
                    $multipartItem['contents'] = $item['contents'];
                }
            } elseif ($item['contents'] instanceof CurlFile) {
                $multipartItem['contents'] = $item['contents'];
            } else {
                throw new RequestException("Invalid contents for multipart item: " . print_r($item, true));
            }
    
            if (isset($item['filename'])) {
                $multipartItem['filename'] = $item['filename'];
            }
    
            $multipart[] = $multipartItem;
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