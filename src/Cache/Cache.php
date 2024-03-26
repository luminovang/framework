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

class Cache
{
    /**
    * Engin type for file cache
    *
    * @var string FILE
    */
    public const FILE = 'FileCache';

    /**
    * Engin type for Memcached
    *
    * @var string MEM
    */
    public const MEM = 'MemoryCache';

    /**
    * Engin instance
    *
    * @var object $engine
    */
    public $engine;

    /**
    * Engin static instance
    *
    * @var Cache $instance
    */
    private static ?Cache $instance = null;

    /**
    * Cache constructor.
    *
    * @param string $engine The cache engine to use (e.g., self::FILE or self::MEM).
    */
    public function __construct(string $engine = self::FILE)
    {
        $this->engine = static::newInstance($engine);
    }

    /**
     * Get an instance of the cache engine.
     * @param string $engine The cache engine to use (e.g., self::FILE or self::MEM).
     * 
     * @return self self::$instance The cache engine instance.
     */
    public static function getInstance(string $engine = self::FILE): self
    {
        if (static::$instance === null) {
            static::$instance = new static($engine);
        }

        return static::$instance;
    }

    /**
     * Create an instance of the cache engine based on the provided engine type.
     *
     * @param string $engine The cache engine to create (e.g., self::FILE or self::MEM).
     *
     * @return FileCache|MemoryCache|object The cache engine instance.
     * @throws ClassException When the Memcached class is not available for the MemoryCache.
     */
    private static function newInstance(string $engine): object
    {
        switch ($engine) {
            case self::MEM:
                if (class_exists('\Memcached')) {
                    return new MemoryCache();
                } else {
                    throw new ClassException('Memcached does not exist');
                }
            case self::FILE:
            default:
                return new FileCache();
        }
    }
}