<?php 
/**
 * Represents a cached item in the file cache.
 *
 * @package Luminova\Cache
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cache;

class FileCacheItem 
{
    /**
     * @var int $expiry
    */
    private int $expiry;

    /**
     * @var bool $lock
    */
    private bool $lock;

    /**
     * @var mixed $data
    */
    private mixed $data;

    /**
     * Set the expiration time for the cache item.
     *
     * @param int $expiry The expiration time in seconds.
     * @return self
    */
    public function setExpiry(int $expiry): self 
    {
        $this->expiry = $expiry;
        return $this;
    }

    /**
     * Set the lock status for the cache item.
     *
     * @param bool $lock The lock status (true for locked, false for unlocked).
     * @return self
    */
    public function setLock(bool $lock): self 
    {
        $this->lock = $lock;
        return $this;
    }

    /**
     * Set the data for the cache item.
     *
     * @param mixed $data The data to be cached.
     * @return self
    */
    public function setData(mixed $data): self 
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get the expiration time of the cache item.
     *
     * @return int The expiration time in seconds.
    */
    public function getExpiry(): int 
    {
        return $this->expiry;
    }

    /**
     * Check if the cache item is locked.
     *
     * @return bool The lock status (true if locked, false if unlocked).
    */
    public function getLock(): bool 
    {
        return $this->lock;
    }

    /**
     * Get the data stored in the cache item.
     *
     * @return mixed The cached data.
    */
    public function getData(): mixed 
    {
        return $this->data;
    }
}
