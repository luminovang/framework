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

use \Countable;

class Server implements Countable
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
     * @return mixed|array|string|null The value of the specified server variable, or all server variables if $name is null.
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null || $name === '') {
            return $this->variables;
        }

        if($this->has($name)){
            return $this->variables[$name];
        }

        return $default;
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
     * @param string $key The key to remove.
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
     * @return mixed The value of the found header or false if not found.
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

        if (array_key_exists($stripped, $this->variables)) {
            return $this->variables[$stripped];
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
}