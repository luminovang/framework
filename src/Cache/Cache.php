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

use \Luminova\Cache\FileCache;
use \Luminova\Cache\MemoryCache;
use \Luminova\Exceptions\ClassException;
use \Memcached;

final class Cache
{
    /**
    * Engin type for file cache
    *
    * @var int FILE
    */
    public const FILE = 1;

    /**
    * Engin type for Memcached
    *
    * @var int MEM
    */
    public const MEM = 2;

    /**
    * Engin instance
    *
    * @var object $engine
    */
    public $engine;

    /**
    * Engin static instance
    *
    * @var self|null $instance
    */
    private static ?self $instance = null;

    /**
    * Cache constructor.
    *
    * @param int $engine The cache engine to use (e.g., self::FILE or self::MEM).
    */
    public function __construct(int $engine = self::FILE)
    {
        $this->engine = self::newInstance($engine);
    }

    /**
     * Get an instance of the cache engine.
     * 
     * @param int $engine The cache engine to use (e.g., self::FILE or self::MEM).
     * 
     * @return static The cache engine instance.
     */
    public static function getInstance(int $engine = self::FILE): static
    {
        if (self::$instance === null) {
            self::$instance = new static($engine);
        }

        return self::$instance;
    }

    /**
     * Create an instance of the cache engine based on the provided engine type.
     *
     * @param int $engine The cache engine to create (e.g., self::FILE or self::MEM).
     *
     * @return FileCache|MemoryCache Return the cache engine instance.
     * @throws ClassException When the Memcached class is not available for the MemoryCache.
     */
    private static function newInstance(int $engine): FileCache|MemoryCache
    {
        switch ($engine) {
            case static::MEM:
                if (class_exists(Memcached::class)) {
                    return new MemoryCache();
                }
                throw new ClassException('Memcached does not exist');
            case static::FILE:
            default:
                return new FileCache();
        }
    }
}