<?php 
/**
 * Luminova Framework Http Server
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 * @category \Luminova\Http\
 */
namespace Luminova\Http;

use \Luminova\Http\HttpServerTrait;
use \Luminova\Http\Request;
use \Luminova\Exceptions\RuntimeException;
use \Closure;

class HttpServer 
{
    /**
     * List of PIDs of forked processes.
     * 
     * @var array $pids
     */
    private array $pids = []; 

    /**
     * Track active child processes
     * 
     * @var int $activeProcesses
     */
    private int $activeProcesses = 0; 

    /**
     * Weather the server is running.
     * 
     * @var bool $running
     */
    private bool $running = false;

    /**
     * Weather the server is booting.
     * 
     * @var bool $booting
     */
    private bool $booting = false;

    /**
     * The seconds part of the timeout to be set.
     * 
     * @var int|null $timeout
     */
    private ?int $timeout = null;

    /**
     * The microseconds part of the timeout to be set.
     * 
     * @var int $microseconds
     */
    private int $microseconds = 0;

    /**
     * Is connected.
     * 
     * @var bool $isConnected
     */
    private static bool $isConnected = false;

    /**
     * A unique identifier for the WebSocket communication handshake.
     * 
     * @var string $guid
     */
    public string $guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    
    /**
     * Include HTTP Server helper class Trait.
     */
    use HttpServerTrait;   
 
    /**
     * Initializes the HTTP server by binding to the specified address and port.
     *
     * @param string $address The connection IP address, hostname or a valid TCP connection string to bind to (e.g., `127.0.0.1`, `example.com`, or `tcp://127.0.0.1:8080`).
     * @param int|null $port Optional port number between 1024 and 49151 to bind to (default: null).
     * @param bool $autostart Whether to start the server automatically upon initialization (default: true).
     * @param resource|array|null $options Optional resource for stream context or an array of options to create `stream_context_create` resource (default: `null`).
     *
     * @throws RuntimeException Throws if the server fails to create a valid socket.
     */
    public function __construct(
        private string $address, 
        private ?int $port = null,
        private bool $autostart = true,
        private mixed $options = null
    )
    {
        self::$clients = [];
        self::$sockets = [];
        $this->running = false;
        $this->booting = false;
        self::$isConnected = false;
        self::$requireConnection = false;

        if($this->autostart){
            $this->start();
        }
    }

    /**
     * Magic method to route calls to undefined methods.
     *
     * @param string $method HTTP method (e.g., 'GET', 'POST').
     * @param array $arguments Arguments to pass to the route.
     * 
     * @return void
     */
    public function __call(string $method, array $arguments)
    {
        $this->route($method, ...$arguments);
    }

    /**
     * Destructor to ensure the server socket is properly closed.
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->shutdown();
    }

    /**
     * Enables or disables debug mode for the application.
     *
     * @param bool $debug Indicates whether to enable (true) or disable (false) debug mode.
     * 
     * @return self Returns instance of HttpServer.
     */
    public function debug(bool $debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Specifies whether the client must connect to the server before sending and receiving HTTP requests.
     * 
     * For example:
     * - Clients should send a GET request to connect: `curl -X GET http://127.0.0.1:8080/connect`.
     * - Similarly, to disconnect: `curl -X GET http://127.0.0.1:8080/disconnect`.
     * 
     * If set to false, you can handle the connection and disconnection logic manually without the need
     * for these specific routes.
     * 
     * @param bool $required Whether a connection is required before handling requests (default: true).
     * 
     * @return self Returns instance of HttpServer.
     */
    public function connectionRequired(bool $required = true): self
    {
        self::$requireConnection = $required;
        return $this;
    }

    /**
     * Configures whether the server should accept WebSocket connections.
     *
     * @param bool $accept Set to true to enable WebSocket connections; false to disable.
     * 
     * @return self Returns instance of HttpServer.
     */
    public function websocket(bool $accept = true): self
    {
        $this->acceptWebSocket = $accept;
        return $this;
    }

    /**
     * Checks if the configured port is currently in use.
     * 
     * @return bool Return true if the port is in use, false otherwise.
     */
    public function isPortFree(): bool
    {
        $addr = $this->getAddress();
        $connection = fsockopen($addr['address'], $addr['port']);
        
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }

        return true; 
    }

    /**
     * Check if socket server is successfully connected.
     * 
     * @return bool Return true if is connected or otherwise false.
     */
    private function isConnected(): bool 
    {
        return $this->socket !== null && is_resource($this->socket);
    }

    /**
     * Check if the port is in use and free it if necessary
     * 
     * @param int $port The port number to check
     * 
     * @return bool Returns true if the port was freed, false otherwise
     */
    public function freePortIfStuck(?int $port = null): bool
    {
        $port ??= $this->getAddress()['port'];

        $command = shell_exec("lsof -t -i :{$port}");
        $pid = trim($command ?? '');

        if ($pid) {
            $kill = "kill -9 {$pid}";
            shell_exec($kill);

            $stillAlive = shell_exec("lsof -t -i :{$port}");
            if (!trim($stillAlive ?? '')) {
                $this->_echo("Port {$port} was freed successfully.");
                return true;
            }

            $this->_echo("Failed to free port {$port}. Process still running.");
            return false;
        }

        $this->_echo("Port {$port} is not in use.");
        return false;
    }

    /**
     * Automatically close connections that are idle for too long.
     * 
     * @param int $timeout The server timeout in seconds wait for idle connections.
     * @param int $microseconds The microseconds part of the timeout to be set (default: 0).
     * 
     * @return self Returns instance of HttpServer.
     */
    public function setTimeout(int $timeout, int $microseconds = 0): self
    {
        if($this->isConnected()){
            stream_set_timeout($this->socket, $timeout, $microseconds);
        }

        $this->timeout = $timeout;
        $this->microseconds = $microseconds;
        return $this;
    }

    /**
     * Sets the rate limit for incoming client requests.
     * 
     * @param int $maxRequests The maximum number of requests allowed in the time window.
     * @param int $timeWindow The time window in seconds for the request limit.
     * 
     * @return self Returns instance of HttpServer.
     */
    public function setRateLimit(int $maxRequests, int $timeWindow): self
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;

        return $this;
    }

    /**
     * Set the maximum number of client processes to create when the third argument of `start()` is true.
     * 
     * @param int $maxProcesses Maximum number of client processes allowed.
     * 
     * @return self Returns instance of HttpServer.
     * @throws RuntimeException Throw if `$max` is not a positive integer.
     */
    public function setMaxProcesses(int $max): self
    {
        if($max <= 0){
            throw new RuntimeException('The maximum number of client processes must be a positive integer.');
        }

        $this->maxProcesses = $max;
        return $this;
    }

    /**
     * Set headers from an associative array of key-value pairs.
     *
     * @param array $headers Associative array of headers, where the key is the header name 
     *              and the value is the header value.
     *
     * @return self Returns instance of HttpServer.
     */
    public function setHeader(string $key, mixed $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Set headers from an associative array of key-value pairs.
     *
     * @param array<string,mixed> $headers Associative array of headers, where the key is the header name 
     *          and the value is the header value.
     *
     * @return self Returns instance of HttpServer.
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * Get the server socket resource for registration in the event loop.
     *
     * @return resource|null The server socket resource or null.
     */
    public function getSocket(): mixed
    {
        return $this->socket;
    }

    /**
     * Get the ID of the server socket.
     *
     * @return int The ID of the server socket.
     */
    public function getId(): int
    {
        return (int) $this->socket ?? -1;
    }

    /**
     * Extracts the address and port from the DNS connection string.
     * 
     * @return array Return an associative array containing 'address' and 'port'.
     */
    public function getAddress(): array
    {
        if($this->port === null){
            $endpoint = parse_url($this->endpoint);
            $address = $endpoint['host'] ?? '127.0.0.1';
            $port = $endpoint['port'] ?? 8080;

            return ['address' => $address, 'port' => $port];
        }

        return ['address' => $this->address, 'port' => $this->port];
    }

    /**
     * Starts the server by creating a socket and binding to the specified address and port.
     *
     * @return self Returns the Http Server instance after the socket is successfully created.
     *
     * @throws RuntimeException If the server cannot bind to the address or port, or if the port is already in use.
     */
    public function start(): self 
    {
        $errno = 0;
        $errstr = '';

        $this->endpoint = str_contains($this->address, '://') 
            ? $this->address 
            : "tcp://{$this->address}" . ($this->port !== null ? ":{$this->port}" : '');

        $this->socket = stream_socket_server(
            $this->endpoint, 
            $errno, 
            $errstr, 
            STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,
            is_array($this->options) ? stream_context_create($this->options) : $this->options
        );
        
        if (!$this->socket) {
            if (!$this->isPortFree()) {
                throw new RuntimeException("Port {$this->port} is already in use.");
            }

            $error = str_contains($this->endpoint, 'ssl://') 
                ? "Failed to create SSL socket server on %s: %s"
                : "Failed to create socket server on %s: %s";
            throw new RuntimeException(sprintf($error, $this->endpoint, $errstr), $errno);
        }

        stream_set_blocking($this->socket, false);
        if($this->timeout !== null){
            stream_set_timeout($this->socket, $this->timeout, $this->microseconds);
        }

        $this->_echo("Server created on address: {$this->address}" . ($this->port !== null ? " listening on port: {$this->port}" : "") . ".");

        return $this;
    }

    /**
     * Runs the server and begins listening for incoming connections.
     *
     * @param int|null $timeout Optional timeout for client connections, in microseconds (default: 100000 µs).
     * @param int $sleep Time in microseconds to wait between connection attempts (default: 10000 µs).
     * @param bool $simultaneous Whether to allow multiple simultaneous connections (default: false).
     *
     * @return void
     */
    public function run(
        int|null $timeout = 100000, 
        int $sleep = 10000, 
        bool $simultaneous = false
    ): void
    {
        if (!is_resource($this->socket)) {
            $this->_echo("No valid socket connection to start.");
            return;
        }

        $this->_echo("Server running at: {$this->endpoint}.");
        
        $this->running = true;
        $this->booting = true;
        self::$sockets = [$this->socket];
        $timeout ??=0;

        if($simultaneous){
            $this->listeners($timeout, $sleep);
        }else{
            $this->listen($timeout, $sleep);
            $this->waitForConnection();
        }

        if (!$this->running) {
            $this->_echo("Server with ID: {$this->getId()} at {$this->endpoint} has been successfully stopped.");
            exit(0);
        }
    }

    /**
     * Gracefully shuts down the server and closes the socket connection.
     *
     * @param bool $kill Whether to attempt freeing the port if it's stuck (default: false).
     * 
     * @return void
     */
    public function shutdown(bool $kill = false): void
    {
        if ($this->isConnected()) {
            if(!$this->booting){
                $this->_echo("Preparing to shut down server at {$this->address}" . ($this->port ? " on port {$this->port}" : "") . ".");
            }
            
            $id = $this->getId();
            fclose($this->socket);
            $this->socket = null;

            if($this->running){
                foreach (self::$sockets as $idx => $socket) {
                    $this->removeClient($idx, self::$connections[$idx][1] ?? '');
                }
            }
            
            $this->running = false;
            self::$isConnected = false;
            self::$sockets = [];
            self::$connections = [];
            self::$clients = [];
            $this->routes = [];
            $this->pids = [];

            if($this->booting){
                $this->booting = false;
                return;
            }

            $callback = $this->routes['DISCONNECT']['callback'] ?? null;
            if($callback instanceof Closure){
                $response = $callback([
                    'id' => $id, 'address' => $this->address, 
                    'port' => $this->port,'endpoint' => $this->endpoint
                ]);

                echo ($response instanceof Request) ? $response->toString() : $response;
            }

            if($kill){
                $this->freePortIfStuck();
            }

            $this->booting = false;
            $this->_echo("Server with ID: {$id} has been successfully shutdown.");
            return;
        }

        if($kill){
            $this->freePortIfStuck();
            return;
        }

        $this->_echo("No active server connection to shutdown.");
    }

    /**
     * Marks a client as connected to the server and sets its connection status.
     *
     * @param string|null $address The client's IP address or identifier. 
     *                  If not provided, defaults to the server's own IP address.
     *
     * @return void
     */
    public function on(?string $address = null): void
    {
        $address ??= end(self::$connections)[1] ?? '';
        $this->_echo("Connecting client: {$address}.");
        
        self::$clients[$address]['connected'] = true;
        self::$isConnected = true;
    }

    /**
     * Marks a client as disconnected and removes it from the active clients list.
     *
     * @param string|null $address The client's IP address or identifier. 
     *                      If not provided, defaults to the server's own IP address.
     *
     * @return void
     */
    public function off(?string $address = null): void
    {
        $address ??= end(self::$connections)[1] ?? '';
        $this->_echo("Disconnecting client: {$address}.");
        
        self::$isConnected = false;
        self::$clients[$address]['connected'] = false;
        unset(self::$clients[$address]);
    }

    /**
     * Accept an incoming client connection and route the request.
     *
     * This method handles an incoming connection, reads the client's request,
     * and sends an HTTP response based on the defined routes. If a specific
     * response is provided, it will be used; otherwise, a default response 
     * will be sent.
     *
     * @param string $pattern URI pattern or null for all (e.g, `/`, `/foo/{segment}`).
     * @param Closure $response Optional response object or string.
     * 
     * @return void
     */
    public function accept(string $pattern, Closure $response): void
    {
        $this->route('ALL', $pattern, $response);
    }

    /**
     * Define a route for GET requests.
     * 
     * @param string $pattern URI pattern (e.g, '/') to match.
     * @param Closure $response The response callback.
     * 
     * @return void
     */
    public function get(string $pattern, Closure $response): void
    {
        $this->route('GET', $pattern, $response);
    }

    /**
     * Define a route for POST requests.
     *
     * @param string $pattern URI pattern (e.g, '/') to match.
     * @param Closure $response The response callback.
     * 
     * @return void
     */
    public function post(string $pattern, Closure $response): void
    {
        $this->route('POST', $pattern, $response);
    }

    /**
     * Register a simple "ping" route for health checks.
     *
     * @return void
     */
    public function ping(): void
    {
        $this->route('GET', '/ping(?:/.*)?', function() {
            return self::$responses['PING'];
        });
    }

    /**
     * Define a route for error responses.
     *
     * @param Closure $response The response callback.
     * 
     * @return void
     */
    public function error(Closure $response): void
    {
        $this->route('ERROR', '/', $response);
    }

    /**
     * Define a route for connection responses.
     *
     * @param Closure|null $response The response callback.
     *                       Callback signature: function(array $clients): void
     *                       where $clients is an array of connected client information.
     * 
     * @return void
     */
    public function connect(?Closure $response = null): void
    {
        $this->route('GET', '/connect(?:/.*)?', function() use ($response) {
            $address = end(self::$connections)[1] ?? '';
            $client = self::$clients[$address] ?? [];

            if($response instanceof Closure){
                $response($client, self::$clients);
            }

            return self::$responses['CONNECTED'];
        });
    }

    /**
     * Define a route for disconnection responses.
     *
     * @param Closure|null $response The response callback (optional).
     *                              Callback signature: function(array $clients): void
     *                              where $clients is an array of connected client information.
     * 
     * @return void
     */
    public function disconnect(?Closure $response = null): void
    {
        $this->route('GET', '/disconnect(?:/.*)?', function() use ($response) {
            $address = end(self::$connections)[1] ?? '';
            $client = self::$clients[$address] ?? [];
            $client['connected'] = false;

            if($response instanceof Closure){
                $response($client, self::$clients);
            }

            $this->off($address);
            return self::$responses['DISCONNECTED'];
        });
    }

    /**
     * Define a route for specific HTTP methods.
     *
     * @param string $methods HTTP methods to accept (e.g, `GET|POST|PUT`).
     * @param string $pattern URI pattern or null for all (e.g, `/`).
     * @param Closure $response The response callback.
     * 
     * @return void
     */
    public function route(
        string $methods, 
        string $pattern,
        Closure $response,
    ): void
    {
        $pipes = (str_contains($methods, '|'))
            ?  explode('|', $methods)
            : [$methods];

        foreach ($pipes as $method) {
            $method = strtoupper($method);
            $this->routes[$method][] = [
                'method' => $method,
                'pattern' => $pattern,
                'callback' => $response
            ];
        }
    }

    /**
     * Sets a callback to intercept and modify the server response before sending it.
     * 
     * Example usage:
     * `function(string $response, array $headers): string`
     *
     * @param Closure $intercept Callback to modify the response content.
     * 
     * @return void
     */
    public function onResponse(Closure $intercept): void
    {
        $this->onInterceptResponse = $intercept;
    }

    /**
     * Monitors incoming client connections and handles requests concurrently.
     *
     * @param int $timeout Timeout for the stream select operation (default: null).
     * @param int $sleep Duration to pause between operations in microseconds (default: 0).
     *
     * @return void
     */
    private function listeners(int $timeout, int $sleep): void
    {
        $this->_echo("Server is now running with ID: {$this->getId()}.");
        while (($this->running && $this->socket !== null && self::$sockets !== [])) {
            $code = $this->getRemainingSockets($timeout);

            if ($code === 1) {
                break;
            }

            if ($code === -1) {
                continue;
            }

            if ($code === 0 && ($client = @stream_socket_accept($this->socket))) {
                self::$sockets[] = $client;
                $sid = $this->getSid();
                self::$connections[$sid] = $this->setAndGetClient($client, $sid);

                $this->waitForFreeProcess();
                $this->listening($sid);
                $this->waitForConnections();
            }

            if ($sleep > 0) {
                usleep($sleep);
            }
        }
    }

    /**
     * Fork a new process to manage a non-simultaneous client request.
     *
     * @param int $timeout Timeout for the stream select operation.
     * @param int $sleep Duration to pause between operations in microseconds (default: 0).
     *
     * @return void
     */
    private function listen(int $timeout, int $sleep): void
    {
        $pid = pcntl_fork();

        if ($pid == -1) {
            $this->_echo("Failed to fork process for handling request PID {$pid}.");
            $this->shutdown();
            return;
        } 
        
        if ($pid !== 0) {
            $this->pids[0] = [
                'pid' => $pid,
                'timeout' => $timeout,
                'sleep' => $sleep
            ];
            
            if($this->booting){
                $this->_echo("Server is now running with ID: {$this->getId()}.");
            }
            return;
        }

        exit(0);
    }

    /**
     * Fork a new process to manage simultaneous client request listening.
     * 
     * @param int $idx The index of the client connection.
     *
     * @return void
     */
    private function listening(int $idx): void
    {
        $pid = pcntl_fork();
        
        if ($pid == -1) {
            $this->_echo("Failed to fork process for handling request PID {$pid}.");
            $this->removeClient($idx, self::$connections[$idx][1]);
            return;
        } 
        
        if ($pid !== 0) {
            $this->activeProcesses++;
            $this->_echo("Forked child process with PID {$pid}. Active processes: {$this->activeProcesses}.");
            $this->pids[$idx] = [
                'pid' => $pid
            ];
            return;
        }

        exit(0);
    }

    /**
     * Checks if there are any active socket connections ready for reading.
     *
     * @param int $timeout The maximum time to wait for a socket to become readable (in seconds). 
     *
     * @return bool Returns true if sockets are ready for reading; otherwise, false.
     */
    private function isReady(int $timeout): bool 
    {
        $write = $except = null;

        if (stream_select(self::$sockets, $write, $except, $timeout) === false) {
            $this->_echo("Error while reading socket.");
            return false;
        }

        return true;
    }

    /**
     * Cleans up any remaining child processes after handling the request.
     * 
     * @param bool $simultaneous Weather it's for simultaneous connection.
     *
     * @return void
     */
    private function cleanupProcesses(bool $simultaneous = true): void
    {
        $status = 0;
        if(!$simultaneous){
            pcntl_wait($status);
            return;
        }

        while ($this->activeProcesses > 0) {
            pcntl_wait($status);
            $this->activeProcesses--;
        }
    }

    /**
     * Waits for a free process slot if the maximum concurrent processes is reached.
     *
     * @return void
     */
    private function waitForFreeProcess(): void
    {
        $status = 0;
        while ($this->activeProcesses >= $this->maxProcesses) {
            $this->_echo("Max processes reached. Waiting for a child process to exit...");
            pcntl_wait($status, WNOHANG);
            $this->activeProcesses--;
        }
    }

    /**
     * Waits for forked process id to finish before handling connection request.
     * 
     * @param int $pid The process id to wait for.
     * 
     * @return int Return the exit status code of the process.
    */
    private function waitAsyncProcess(int $pid): int
    {
        $status = 0;
        while (pcntl_waitpid($pid, $status, WNOHANG) == 0) {
            usleep(10000); 
        }

        return $status;
    }

    /**
     * Waits for all forked child processes to complete and handle connections simultaneously.
     *
     * @return void
     */
    private function waitForConnections(): void
    {
        foreach ($this->pids as $idx => $fork) {
            $status = $this->waitAsyncProcess($fork['pid']);
            self::$clients[self::$connections[$idx][1]]['pid'] = $fork['pid'];

            $this->handler();
            $this->exited($status, $fork['pid']);
        }

        $this->cleanupProcesses();
    }

    /**
     * Waits for forked child process to complete and handle connection.
     *
     * @return void
     */
    private function waitForConnection(): void 
    {
        if($this->pids === []){
            return;
        }

        $status = $this->waitAsyncProcess($this->pids[0]['pid']);

        while (($this->pids !== [] && $this->running && $this->socket !== null && self::$sockets !== [])) {
            $code = $this->getRemainingSockets($this->pids[0]['timeout']);

            if ($code === 1) {
                break;
            }

            if ($code === -1) {
                continue;
            }

            if ($code === 0 && ($client = @stream_socket_accept($this->socket))) {
                self::$sockets[] = $client;
                $sid = $this->getSid();

                self::$connections[$sid] = $this->setAndGetClient($client, $sid);
                self::$clients[self::$connections[$sid][1]]['pid'] = $this->pids[0]['pid'];
                $this->handler();
                $this->exited($status, $this->pids[0]['pid']);
                $this->cleanupProcesses(false);
            }

            if ($this->pids[0]['sleep'] > 0) {
                usleep($this->pids[0]['sleep']);
            }
        }
    }

    /**
     * Handler client incoming requests and processes response accordingly.
     *
     * @return void
     */
    private function handler(): void 
    {
        foreach (self::$sockets as $idx => $socket) {
            if ($socket === $this->socket) {
                continue;
            }

            [$id, $address] = self::$connections[$idx];

            if ($this->maxRequests > 0 && self::$clients[$address]['requests'] > $this->maxRequests) {
                $this->_echo("Rate limit exceeded for {$address}");
                $request = self::$responses['REQUEST_LIMIT'];

                if (self::$requireConnection && !self::$isConnected) {
                    $this->_echo("Client {$address} is not connected.");
                    $request = self::$responses['NOT_CONNECTED'];
                    $this->removeClient($idx, $address);
                } else {
                    $this->handleRequest($request, $socket);
                }
                continue;
            }

            $this->_echo("Client {$address} received request from id: {$id}.");
            while ($request = $this->readRequest($socket)) {
                $this->_echo("Handling HTTP request from client {$idx}.");

                 if (self::$requireConnection && !self::$isConnected && !$this->isConnecting($request, $address)) {
                    $this->_echo("Client {$address} is not connected.");
                    $this->handleRequest(self::$responses['NOT_CONNECTED'], $socket);
                    $this->removeClient($idx, $address);
                    break;
                }
                
                if ($this->maxRequests > 0 && self::$clients[$address]['requests'] > $this->maxRequests) {
                    $this->_echo("Rate limit exceeded for {$address}");
                    $this->handleRequest(self::$responses['REQUEST_LIMIT'], $socket);
                    break;
                }

                $this->handleRequest($request, $socket);

                if (str_contains($request, "Connection: close")) {
                    $this->_echo("Client requested connection close. Closing client {$idx}.");
                    $this->removeClient($idx, $address);
                    break;
                }
            }
        }
    }

    /**
     * Check if client is connected or attempting to connect.
     *
     * @param string $request The HTTP request string.
     * 
     * @return bool Return true if client is connected or is attempting to connect.
     */
    private function isConnecting(string $request, string $address): bool 
    {
        [$method, $uri] = $this->getRequestDetails($request);
        $isConnecting = ($method === 'GET' && str_starts_with($uri, '/connect'));

        if($isConnecting){
            $this->on($address);
        }elseif($method === 'GET' && str_starts_with($uri, '/disconnect')){
            $this->off($address);
        }

        return (self::$isConnected || $isConnecting);
    }

    /**
     * Filter the remaining sockets and retrieves status based on available sockets and a timeout.
     *
     * - 0: The current socket is valid and ready.
     * - 1: No valid sockets to monitor.
     * - 3: The current socket is not found in the available sockets.
     * - -1: An error occurred while attempting to read the socket.
     *
     * @param int $timeout The timeout in seconds to wait for the socket to be ready.
     * 
     * @return int Return the status code representing the result.
     */
    private function getRemainingSockets(int $timeout): int 
    {
        self::$sockets = array_filter(self::$sockets, fn($socket) => is_resource($socket));

        if (self::$sockets === []) {
            $this->running = false;
            $this->_echo("No valid sockets to monitor.");
            return 1;
        }

        if (!$this->isReady($timeout)) {
            $this->_echo("Error while reading socket.");
            return -1;
        }

        return in_array($this->socket, self::$sockets, true) ? 0 : 3;
    }
}