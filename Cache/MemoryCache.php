<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cache;
use \Memcached;

class MemoryCache 
{
    /**
     * @var int Default cache time duration in seconds.
     */
    private int $cacheTime = 60;

    /**
     * @var array Memcached server configuration.
     */
    private array $config = [];

    /**
     * @var Memcached Memcached instance
    */
    private ?Memcached $memcache = null;

    /**
     * MemoryCache constructor.
     */
    public function __construct() {
        $this->memcache = new Memcached();
    }

    /**
     * Set Memcached server configuration.
     *
     * @param string $host Memcached server host.
     * @param int $port Memcached server port.
     * 
     * @return self
     */
    public function setConfig(string $host = "localhost", int $port = 11211): self 
    {
        $this->config[] = [
            "host" => $host,
            "port" => $port,
        ];
        return $this;
    }

    /**
     * Set Memcached server configuration from an array.
     *
     * @param array $config Memcached server configuration array.
     * @return self
     */
    public function addConfig(array $config): self 
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Initialize the Memcached engine with the configured servers.
     *
     * @return self
     */
    public function connect(): self 
    {
        if (empty($this->config) || !is_array($this->config)) {
            return $this;
        }
        
        foreach ($this->config as $config) {
            $this->memcache->addServer($config["host"], $config["port"]);
        }
        
        return $this;
    }

    /**
     * Set the cache expiration time duration in seconds.
     *
     * @param int $time Cache expiration time in seconds.
     * 
     * @return self
     */
    public function setExpire(int $time = 60): self 
    {
        $this->cacheTime = $time;
        return $this;
    }

    /**
     * Retrieve cached data or generate it using a callback if not found.
     *
     * @param string $key Cache key.
     * @param callable $cacheCallback Callback function to generate the data.
     * 
     * @return mixed Cached or generated data.
     */
    public function onExpired(string $key, callable $cacheCallback): mixed 
    {
        return $this->withExpired($key, $cacheCallback, $this->cacheTime);
    }

    /**
     * Retrieve cached data or generate it using a callback if not found with a custom expiration time.
     *
     * @param string $key Cache key.
     * @param callable $cacheCallback Callback function to generate the data.
     * @param int $expiration Custom cache expiration time in seconds.
     * 
     * @return mixed Cached or generated data.
     */
    public function withExpired(string $key, callable $cacheCallback, int $expiration): mixed 
    {
        $cachedResponse = $this->memcache->get($key);

        if ($cachedResponse !== false) {
            return $cachedResponse;
        } else {
            $funcResponse = $cacheCallback();

            $this->writeCache($key, $funcResponse, $expiration);

            return $funcResponse;
        }
    }

    /**
     * Write data to the cache with a custom expiration time.
     *
     * @param string $key Cache key.
     * @param mixed $value Data to be cached.
     * @param int $expiration Cache expiration time in seconds.
     * @return bool True on success, false on failure.
     */
    public function writeCache(string $key, mixed $value, int $expiration): bool 
    {
        return $this->memcache->set($key, $value, $expiration);
    }

    /**
     * Remove data associated with a cache key.
     *
     * @param string $key Cache key to remove.
     * @return bool True on success, false on failure.
     */
    public function remove(string $key): bool 
    {
        $this->memcache->delete($key);
        return true;
    }

    /**
     * Remove data associated with an array of cache keys.
     *
     * @param array $array Array of cache keys to remove.
     * @return void
     */
    public function removeList(array $array): void 
    {
        $this->memcache->deleteMulti($array);
    }

    /**
     * Clear the entire cache.
     */
    public function clearCache(): void 
    {
        $this->memcache->flush();
    }

    /**
     * Close the Memcached connection.
     */
    public function close(): void 
    {
        $this->memcache->quit();
    }
}