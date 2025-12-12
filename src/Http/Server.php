<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Countable;
use Luminova\Luminova;
use function Luminova\Funcs\root;
use Luminova\Interface\LazyObjectInterface;

class Server implements LazyObjectInterface, Countable
{
    /**
     * Instance mode.
     *
     * @var bool $isGlobal
     */
    private bool $isGlobal = false;

    /**
     * Global server instance.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Create a new server instance.
     *
     * If no server variables are provided, the instance reads from the global
     * `$_SERVER` array when values are requested.
     *
     * @param array<string,mixed> $variables Server variables to initialize with.
     */
    public function __construct(protected array $variables = [])
    {
        $this->isGlobal = $this->variables === [];
    }

    /**
     * Get a shared server instance backed by the global `$_SERVER` array.
     *
     * @return static The shared server instance.
     */
    public static function fromGlobal(): static
    {
        if (!(static::$instance instanceof self)) {
            static::$instance = new self();
        }

        static::$instance->isGlobal = true;

        return static::$instance;
    }

    /**
     * Determine whether this instance uses the global `$_SERVER` array.
     *
     * @return bool Returns `true` if the instance reads from `$_SERVER`,
     *              or `false` if it uses a custom server variable array.
     */
    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    /**
     * Get server variables.
     *
     * @param string|null $name Optional name of the server variable.
     * @param mixed $default Default value for the server key.
     *
     * @return mixed|array|string|null Return the value of the specified server variable, 
     *          or all server variables if $name is null.
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->isGlobal 
                ? $_SERVER 
                : $this->variables;
        }

        if($name === '' || !$this->has($name)){
            return $default;
        }

        return $this->isGlobal 
            ? $_SERVER[$name]
            : $this->variables[$name];
    }

    /**
     * Set a server variable value.
     *
     * @param string $key Server name.
     * @param mixed $value Server value.
     *
     * @return self Return instance of server class.
     */
    public function set(string $key, mixed $value): self
    {
        if($this->isGlobal){
            $_SERVER[$key] = $value;
            return $this;
        }

        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Removes a server variable by key
     * 
     * @param string $key Return the key to remove.
    */
    public function remove(string $key): void
    {
        if($this->isGlobal){
            unset($_SERVER[$key]);
            return;
        }

        unset($this->variables[$key]);
    }

    /**
     * Attempt to find a key in HTTP server headers.
     *
     * This method searches for a key in the request server headers, including normalized and stripped versions.
     *
     * @param string $key The key to search for.
     * 
     * @return mixed Return the value of the found header or false if not found.
     */
    public function search(string $key, mixed $default = false): mixed
    {
        $keys = [
            $key,
            strtoupper($key),

            // Replace underscores with hyphens
            str_replace('_', '-', $key),

            // Remove "HTTP_" prefix and replace underscores with hyphens
            str_replace('_', '-', substr($key, 5))
        ];
        
        foreach($keys as $name){
            if ($this->has($name)) {
                return $this->get($name);
            }
        }

        return $default; 
    }

    /**
     * Check if request header key exist.
     * 
     * @param string $key Header key to check.
     * 
     * @return bool Return true if key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return $this->isGlobal 
            ? array_key_exists($key, $_SERVER) 
            : array_key_exists($key, $this->variables);
    }

    /**
     * Get the total number of server variables.
     * 
     * @return int Return the umber of server variables
     */
    public function count(): int
    {
        return $this->isGlobal 
            ? count($_SERVER) 
            : count($this->variables);
    }

    /**
     * Extract HTTP headers from server variables.
     *
     * Converts `$_SERVER` header entries (`HTTP_*`, `CONTENT_TYPE`, and
     * `CONTENT_LENGTH`) into standard HTTP header names similar to
     * `apache_request_headers()`.
     *
     * @return array<string,string> Parsed HTTP headers.
     */
    public function getHeaders(): array
    {
        return Header::extractHeaders($this->get());
    }

    /**
     * Get default server variables.
     *
     * This method returns an array of default server variables commonly used in HTTP requests.
     * These variables include server name, port, host, user agent, accepted content types,
     * languages, character sets, client IP address, script information, server protocol,
     * and request timestamps.
     *
     * @return array Return an associative array containing default server variables and their values.
     */
    public static function getDefault(): array
    {
        $host = PRODUCTION ? APP_HOSTNAME : 'localhost';

        return [
            'SERVER_NAME'       => $host,
            'SERVER_PORT'       => PRODUCTION ? 443 : 80,
            'HTTP_HOST'         => $host,
            'REQUEST_URI'       => '/',
            'QUERY_STRING'      => '',
            'SERVER_PROTOCOL'   => 'HTTP/1.1',
            'HTTPS'             => (URL_SCHEME === 'https') ? 'on' : 'off',
            'HTTP_USER_AGENT'   => Luminova::copyright(true),
            'HTTP_ACCEPT'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'REMOTE_ADDR'       => '127.0.0.1',
            'SCRIPT_NAME'       => APP_CONTROLLER_INDEX,
            'PHP_SELF'          => APP_CONTROLLER_INDEX,
            'SCRIPT_FILENAME'   => root('/public/', 'index.php'),
            'REQUEST_TIME'      => time(),
            'UNIQUE_ID'         => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
            'REQUEST_TIME_FLOAT'    => microtime(true),
            'HTTP_ACCEPT_LANGUAGE'  => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET'   => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
        ];
    }
}