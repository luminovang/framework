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
use \Luminova\Luminova;
use function \Luminova\Funcs\root;
use \Luminova\Interface\LazyObjectInterface;

class Server implements LazyObjectInterface, Countable
{
    /**
     * Initializes the server constructor.
     * 
     * @param array<string,mixed> $variables An Associative array of server variables to initialize with.
     */
    public function __construct(protected array $variables = []){}

    /**
     * Get server variables.
     *
     * @param string|null $name Optional name of the server variable.
     * @param mixed $default Default value for the server key.
     *
     * @return mixed|array|string|null Return the value of the specified server variable, or all server variables if $name is null.
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null || $name === '') {
            return $this->variables;
        }

        return $this->has($name) ? $this->variables[$name] :$default;
    }

    /**
     * Set server variable.
     * 
     * @param string $key The server variable key to set.
     * @param mixed $value The server variable value.
     * 
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->variables[$key] = $value;
    }

    /**
     * Removes a server variable by key
     * 
     * @param string $key Return the key to remove.
    */
    public function remove(string $key): void
    {
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
        $key = strtoupper($key);
        
        if (array_key_exists($key, $this->variables)) {
            return $this->variables[$key];
        }
        
        // Replace underscores with hyphens
        $normalized = str_replace('_', '-', $key);

        if (array_key_exists($normalized, $this->variables)) {
            return $this->variables[$normalized];
        }
        
        // Remove "HTTP_" prefix and replace underscores with hyphens
        $stripped = str_replace('_', '-', substr($key, 5));

        return array_key_exists($stripped, $this->variables) 
            ? $this->variables[$stripped] 
            : $default; 
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
        return array_key_exists($key, $this->variables);
    }

    /**
     * Get the total number of server variables.
     * 
     * @return int Return the umber of server variables
     */
    public function count(): int
    {
        return count($this->variables);
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
            'SERVER_NAME' => $host,
            'SERVER_PORT' => 80,
            'HTTP_HOST' => $host,
            'HTTP_USER_AGENT' => Luminova::copyright(true),
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-us,en;q=0.5',
            'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/' . CONTROLLER_SCRIPT_PATH . '/' . 'index.php',
            'PHP_SELF' => '/' . CONTROLLER_SCRIPT_PATH . '/' . 'index.php',
            'PATH_INFO' => CONTROLLER_SCRIPT_PATH,
            'SCRIPT_FILENAME' => root('/public/', 'index.php'),
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'UNIQUE_ID' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')
        ];
    }
}