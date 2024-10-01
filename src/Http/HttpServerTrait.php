<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Http;

use \Luminova\Http\Message\Response;
use \Luminova\Http\Request;
use \Luminova\Http\Header;
use \Closure;

trait HttpServerTrait 
{
     /**
     * The server socket resource.
     * 
     * @var resource|false|null $socket
    */
    protected mixed $socket = null;

    /**
     * Server headers.
     * 
     * @var array<string,mixed> $headers
     */
    protected array $headers = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type',
    ];

    /**
     * Array to store defined routes.
     * 
     * @var array<string,array> $routes
     */
    protected array $routes = [];

    /**
     * Enable debug message.
     * 
     * @var bool $debug
     */
    protected bool $debug = false;

    /**
     * The connected clients information.
     * 
     * @var array<string,mixed> $clients
     */
    protected static array $clients = [];

     /**
     * The connected clients information.
     * 
     * @var array<string,mixed> $connections
     */
    protected static array $connections = [];

    /**
     * The connected client sockets.
     * 
     * @var resource[] $sockets
     */
    protected static array $sockets = [];

    /**
     * Maximum number of requests allowed.
     * 
     * @var int $maxRequests
     */
    protected int $maxRequests = 100;

    /**
     * Time window in seconds.
     * 
     * @var int $timeWindow
     */
    protected int $timeWindow = 60;

    /**
     * Limit the number of concurrent forks.
     * 
     * @var int $maxProcesses
    */
    protected int $maxProcesses = 10; 

    /**
     * The connection TCP string.
     * 
     * @var string $endpoint
     */
    protected string $endpoint = '';

    /**
     * The clas client ip address.
     * 
     * @var string $ip
     */
    protected string $ip = '';

    /**
     * Wather clients must connect first before accessing server.
     * 
     * @var bool $requireConnection
     */
    protected static bool $requireConnection = false;

     /**
     * Wather clients must connect first before accessing server.
     * 
     * @var bool $acceptWebSocket
     */
    protected bool $acceptWebSocket = false;

    /**
     * Callback function to intercept response.
     * 
     * @var Closure|null $onInterceptResponse
     */
    protected ?Closure $onInterceptResponse = null; 

    /**
     * Default response messages.
     * 
     * @var array<string,string> $responses
     */
    protected static array $responses = [
        'DEFAULT' => "HTTP/1.1 200 OK\r\n" .
                "Content-Type: text/plain\r\n" .
                "Content-Length: 18\r\n" .
                "X-Server-Response-Type: DEFAULT\r\n" .
                "\r\n" .
                "Hello world!",
        'PING' => "HTTP/1.1 200 OK\r\n" .
                "Content-Type: text/plain\r\n" .
                "Content-Length: 4\r\n" .
                "X-Server-Response-Type: PING\r\n" .
                "\r\n" .
                "PONG",
        'REQUEST_LIMIT' => "HTTP/1.1 429 Too Many Requests\r\n" .
                "Content-Type: text/plain\r\n" .
                "Content-Length: 38\r\n" .
                "X-Server-Response-Type: REQUEST_LIMIT\r\n" .
                "\r\n" .
                "Rate limit exceeded. Try again later.",
        'NOT_FOUND' => "HTTP/1.1 404 Not Found\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-Server-Response-Type: NOT_FOUND\r\n" .
                "\r\n" .
                "404 Not Found",
        'CONNECTED' => "HTTP/1.1 200 OK\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-Server-Response-Type: CONNECTED\r\n" .
                "Content-Length: 30\r\n" .
                "\r\n" .
                "Client successfully connected.",
        'DISCONNECTED' => "HTTP/1.1 200 OK\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-Server-Response-Type: DISCONNECTED\r\n" .
                "Content-Length: 32\r\n" .
                "\r\n" .
                "Client successfully disconnected.",
        'SERVER_ERROR' => "HTTP/1.1 500 Internal Server Error\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-Server-Response-Type: SERVER_ERROR\r\n" .
                "Content-Length: 30\r\n" .
                "\r\n" .
                "An error occurred on the server.",
        'NOT_CONNECTED' => "HTTP/1.1 400 Bad Request\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-Server-Response-Type: NOT_CONNECTED\r\n" .
                "Content-Length: 30\r\n" .
                "\r\n" .
                "Client is not connected.",
        'INVALID_WS_KEY' => "HTTP/1.1 400 Bad Request\r\n" .
                "Content-Type: text/plain\r\n" .
                "X-Server-Response-Type: INVALID_WS_KEY\r\n" .
                "Content-Length: 35\r\n" .
                "\r\n" .
                "WebSocket key is missing or invalid.",
        'WS_UPGRADE' => "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n"
    ]; 

    /**
     * Find the response for the given method and URI.
     *
     * @param string $method The HTTP method.
     * @param string $uri The requested URI.
     * 
     * @return array|null The response string, or null if not found.
     */
    protected function routing(string $method, string $uri): ?array
    {
        $routes = array_merge(
            $this->routes[$method] ?? [], 
            $this->routes['ALL'] ?? []
        );

        foreach ($routes as $route) {
            if ($this->uriCapture($route, $uri, $method)) {
                $this->_echo("Routing request to: {$uri} on route method: " . ($route['method'] ?? 'GET') . ", from accept: {$method}");
                return $route;
            }
        }

        return null;
    }

     /**
     * Handle error responses.
     *
     * @param Request $request The raw request data from the client.
     * @param string $type The server error response type.
     * 
     * @return string The constructed error response.
     */
    protected function onError(Request|null $request, string $type): string
    {
        $callback = $this->routes['ERROR']['callback'] ?? null;
        $error = self::$responses[$type] ?? self::$responses['NOT_FOUND'];

        if($callback instanceof Closure){
            $response = $callback($request, $type);

            return $response instanceof Response 
                ? $response->toString() 
                : ($response ?? $error);
        }

        return $error;
    }

    /**
     * Handle successful responses.
     *
     * @param array $route The matched route details.
     * @param Request $request The raw request data from the client.
     * 
     * @return string The constructed successful response.
     */
    protected function onSuccess(array $route, Request|null $request): string
    {
        $callback = $route['callback'] ?? null;

        if($callback instanceof Closure){
            $response = $callback($request);

            return $response instanceof Response 
                ? $response->toString() 
                : $response;
        }

        return self::$responses['DEFAULT'];
    }

    /**
     * Handles WebSocket errors by returning an appropriate error response.
     *
     * If a callback is defined for the error route, it will be executed with the
     * provided error type. If no specific error is defined, a default 'NOT_FOUND'
     * response will be returned.
     *
     * @param string $type The type of error that occurred.
     * 
     * @return string The corresponding error response.
     */
    protected function onWebSocketError(string $type): string
    {
        $callback = $this->routes['ERROR']['callback'] ?? null;

        if ($callback instanceof Closure) {
            $callback($type);
        }

        return self::$responses[$type] ?? self::$responses['NOT_FOUND'];
    }

    /**
     * Processes WebSocket messages and performs the handshake if valid.
     *
     * Checks if a valid WebSocket key is provided. If so, it executes the route's
     * callback with the incoming message. It then constructs and returns the HTTP
     * response necessary to upgrade the connection to a WebSocket.
     *
     * @param array $route The route information, including any associated callback.
     * @param string $message The message received from the WebSocket client.
     * @param string $key The WebSocket key sent by the client for the handshake.
     * 
     * @return string The HTTP response for upgrading to a WebSocket connection
     *                or an error message if the key is invalid.
     */
    protected function onWebSocket(array $payload, string $message): string
    {
        $key = $payload['headers']['Sec-WebSocket-Key'] ?? '';

        if ($key === '') {
            $this->_echo("No WebSocket key provided.");
            return $this->onWebSocketError('INVALID_WS_KEY');
        }

        $this->_echo("WebSocket key: [{$key}]");

        $callback = $route['callback'] ?? null;
        if ($callback instanceof Closure) {
            $callback($message, $payload);
        }

        $key = base64_encode(sha1($key . $this->guid, true));
        $this->_echo("WebSocket response key: [{$key}]");

        return self::$responses['WS_UPGRADE'] . 
            "Sec-WebSocket-Accept: {$key}\r\n\r\n";
    }

    /**
     * Retrieves the client details (ID and IP/Name) and stores them for reuse.
     *
     * @param resource $client The client socket resource.
     * 
     * @return array An array containing the client ID and IP/name.
     */
    protected function setAndGetClient($client, int $idx): array 
    {
        [$address, $port] = $this->getIpInfo($client);

        if ($address === null) {
            $this->_echo("Unable to retrieve IP address for the client.");
            return [-1, 'unknown'];
        }

        $info = self::$clients[$address] ?? null;
        $id = $info['id'] ?? -1;

        if(self::$clients === [] || $info === null){
            $id = uniqid();
            self::$clients[$address]['id'] = $id;
            self::$clients[$address]['idx'] = $idx;
            self::$clients[$address]['address'] = $address;
            self::$clients[$address]['port'] = $port ?? null;
            self::$clients[$address]['requests'] = 1;
            self::$clients[$address]['connection_time'] = time();

            $this->_echo("Client {$address} connected with id: {$id}.");
            return [$id, $address];
        }

        self::$clients[$address]['requests']++;
        if (
            $this->timeWindow > 0 && 
            time() - self::$clients[$address]['connection_time'] > $this->timeWindow
        ) {
            self::$clients[$address]['requests'] = 1;
            self::$clients[$address]['connection_time'] = time();
        }

        return [$id, $address];
    }


     /**
     * Accepts a client connection and returns the client's IP address.
     *
     * @param resource $client The client socket resource.
     * 
     * @return array|null The client's IP address and port, or null if not available.
     */
    protected function getIpInfo($client): ?array
    {
        $address = @stream_socket_get_name($client, true);
        if ($address !== false) {
            return explode(':', $address);
        }

        return [null, null];
    }

    /**
     * Retrieves the details of an HTTP request from a string or an array format.
     *
     * @param string $request The raw HTTP request as a string.
     * @param bool $headers Weather to retrieve the headers (default: false).
     * 
     * @return array An array containing the HTTP method, request URI, and HTTP version.
     *               Defaults to ['GET', '/', 'HTTP/1.1', [headers]] if values are not found.
     */
    protected function getRequestDetails(string $request, bool $headers = false): array 
    {
        $lines = explode("\r\n", $request);
        $line = array_shift($lines);
        $entry = explode(' ', $line, 3);

        return [
            $entry[0] ?? 'GET',
            $entry[1] ?? '/',
            $entry[2] ?? 'HTTP/1.1',
            $headers ? $this->parseHeaders($lines) : []
        ];
    }


    /**
     * Extract the boundary from the Content-Type header.
     *
     * @param string $contentType The Content-Type header value.
     * 
     * @return string|null Returns the boundary string or null if not found.
     */
    protected function getBoundary(string $contentType): ?string
    {
        preg_match('/boundary=([^;]+)/', $contentType, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Extract the body content based on the boundary.
     *
     * @param string $request The raw request data.
     * 
     * @return string The body content after the headers.
     */
    protected function getBodyFromRequest(string $request): string
    {
        $pos = strpos($request, "\r\n\r\n");
        return substr($request, $pos + 4); 
    }

     /**
     * Get the file name from Content-Disposition header.
     *
     * @param string $disposition The headers array from the part.
     * 
     * @return string|null The file name or null if not found.
     */
    protected function getFileName(string $disposition): ?string
    {
        preg_match('/filename="([^"]+)"/', $disposition, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Get the file name from Content-Disposition header.
     *
     * @param string $disposition The headers array from the part.
     * 
     * @return string|null The file name or null if not found.
     */
    protected function getFieldName(string $disposition): ?string
    {
        preg_match('/name="([^"]+)"/', $disposition, $matches);
        
        return $matches[1] ?? null;
    }

    /**
     * Reads the incoming data from the client in chunks.
     *
     * @param resource $client The client socket resource.
     * @param int $length The size of each chunk to read (default: 1024 bytes).
     * @return string The complete request data read from the client.
     */
    protected function readRequest($client, int $length = 1024): string 
    {
        if (!is_resource($client)) {
            return '';
        }

        $request = '';
        $this->_echo("Starting to read request from client...");

        while ($line = fread($client, $length)) {
            if ($line === false) {
                $this->_echo("Error reading from client.");
                break;
            }

            $request .= $line;

            if (strlen($line) < $length) {
                $this->_echo("End of request detected.");
                break;
            }
        }

        $this->_echo("Finished reading request.");
        return $request;
    }

    /**
     * Closes the client connection and cleans up associated resources.
     *
     * @param int $idx The index of the client socket in the sockets array.
     * @param string $address The client's address.
     *
     * @return void
     */
    protected function removeClient(int $idx, string $address): void
    {
        if(is_resource(self::$sockets[$idx])){
            fclose(self::$sockets[$idx]);
        }

        unset(self::$sockets[$idx], self::$clients[$address], self::$connections[$idx]);
    }

    /**
     * Parse the multipart body into an associative array of files and fields.
     *
     * @param array $fields The request fields for none file.
     * @param string $body The raw body content from the request.
     * @param string $boundary The boundary string.
     * 
     * @return array The parsed files and fields.
     */
    protected function parseMultipartBody(array &$fields, string $body, string $boundary): array
    {
        $files = [];
        $parts = explode("--{$boundary}", $body);
        
        foreach ($parts as $part) {
            if (trim($part) === '' || trim($part) === '--') {
                continue; 
            }

            $content = explode("\r\n\r\n", $part, 2);

            // Malformed part, skip
            if (count($content) < 2) {
                continue;
            }

            $headers = $this->parseHeaders($content[0]);
            $data = trim($content[1]);
            $disposition = $headers['Content-Disposition'] ?? '';

            if ($disposition !== '') {
                $fieldName = $this->getFieldName($disposition);

                if (isset($headers['Content-Type'])) {
                    $fileName = $this->getFileName($disposition);
                   
                    $files[$fieldName] = [
                        'name' => $fileName,
                        'size' => strlen($data),
                        'type' => $headers['Content-Type'],
                        'error' => UPLOAD_ERR_OK,
                    ];

                    $tmpName = tempnam(sys_get_temp_dir(), 'upload_');
                    if($tmpName && file_put_contents($tmpName, $data)){
                        $files[$fieldName]['tmp_name'] = $tmpName;
                    }else{
                        $files[$fieldName]['content'] = $data;
                    }
                } else {
                    $fields[$fieldName] = $data;
                }
            }
        }

        return $files;
    }

    /**
     * Parse headers from a string.
     *
     * @param string|array $lines The raw headers string or array of header strings.
     * 
     * @return array An associative array of headers.
     */
    protected function parseHeaders(string|array $lines): array
    {
        $headers = [];
        $lines = is_array($lines) ? $lines : explode("\r\n", $lines);

        foreach ($lines as $line) {
            if (trim($line) === ''){
                continue;
            } 

            $header = explode(': ', $line, 2);
            if(isset($header[1])){
                $headers[$header[0]] = trim($header[1]);
            }
        }

        return $headers;
    }

    /**
     * Find the response for the given method and URI.
     *
     * @param string $method The HTTP method.
     * @param string $uri The requested URI.
     * 
     * @return array|null The response array, or null if not found.
     */
    private function uriCapture(array $route, string $uri, string $method): bool
    {
        $pattern =  '/' . trim($route['pattern'] ?? '', '/');
        $accept = $route['method'] ?? 'GET';

        if($accept === 'ERROR' || $accept === 'WS_MESSAGE'){
            return false;
        }

        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');
        $isMethod = ($accept === $method || $accept === 'ALL');

        $pattern = '#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#';
        return $isMethod && preg_match($pattern, $uri) === 1;
    }

    /**
     * Handle an incoming request and send the appropriate response.
     *
     * This method processes the client's request, determines the appropriate 
     * response based on the defined routes, and sends the response back to the client.
     *
     * @param string $request The raw request data/message from the client.
     * @param resource $client The client socket resource.
     * 
     * @return void
     */
    protected function handleRequest(string $request, mixed $client): void
    {
        Header::send($this->headers);
        [$method, $uri, $protocol, $headers] = $this->getRequestDetails($request, true);

        $this->_echo("Request URI:{$uri}");
        $this->_echo("Request method:{$method}");
        $this->_echo("Request protocol: {$protocol}");

        $responseType = $headers['X-Server-Response-Type'] ?? 'NOT_FOUND';
        $websocket = $headers['Upgrade'] ?? null;
        $route = $this->routing($method, $uri);
        
        // Handle WebSocket Upgrade
        if ($this->acceptWebSocket && $websocket && strtolower($websocket) === 'websocket') {
            $response = ($route === null)
                ? $this->onWebSocketError($responseType)
                : $this->onWebSocket([
                    'method' => $method,
                    'uri' => $uri,
                    'headers' => $headers
                ], $request);
        }else{
            $body = [];
            $files = [];
            $type = $headers['Content-Type'] ?? '';
            $params = parse_url($uri);

            if (isset($params['query'])) {
                parse_str($params['query'], $body);
            }

            if ($type && str_contains($type, 'multipart/form-data')) {
                $files = $this->parseMultipartBody(
                    $body,
                    $this->getBodyFromRequest($request), 
                    $this->getBoundary($type)
                );
            }

            $instance = new Request($method, $uri, $body, $files, $request, null, $headers);
            $response = ($route === null)
                ? $this->onError($instance, $responseType)
                : $this->onSuccess($route, $instance, $client);
            $response = ($this->onInterceptResponse instanceof Closure) 
                ? ($this->onInterceptResponse)($response, $headers) 
                : $response;
        }

        $this->_echo("Response body:\n{$response}");

        fwrite($client, $response);
        fclose($client);
    }

    /**
     * Outputs a debug message to the standard output if debug mode is enabled.
     *
     * @param string $message The message to be outputted.
     * 
     * @return void
     */
    protected function _echo(string $message, ?string $func = null): void
    {
        if ($this->debug) {
            if($func === null){
                $backtrace = debug_backtrace()[1] ?? [];
                $func = $backtrace['function'] ?? null;
            }

            echo "[{$func}] {$message}\n"; 
        }
    }    
}