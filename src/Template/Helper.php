<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Template;


use \Luminova\Cache\PageMinifier;
use \Luminova\Cache\PageViewCache;
use \DateTimeInterface;

class Helper 
{
    private static ?PageMinifier $minifier = null;

    /**
     * View cache instance 
     * 
     * @var PageViewCache $viewCache 
    */
    private static ?PageViewCache $viewCache  = null;

    /** 
     * Render minification
     *
     * @param mixed $contents view contents output buffer
     * @param string $type content type
     * @param bool $ignore
     * @param bool $copy 
     *
     * @return PageMinifier Return minifier instance.
    */
    public static function getMinifier(
        mixed $contents, 
        string $type = 'html', 
        bool $ignore = true, 
        bool $copy = false,
        bool $minify = true,
        bool $encode = true
    ): PageMinifier
    {
        if(static::$minifier === null){
            static::$minifier = new PageMinifier();
        }

        static::$minifier->codeblocks($ignore);
        static::$minifier->copiable($copy);

        return static::$minifier->compress($contents, $type, $minify, $encode);
    }

    /** 
     * Get page view cache instance
     *
     * @param string $direcory Cache directory path. 
     * @param DateTimeInterface|int|null $expiry  Cache expiration ttl (default: 0).
     * @param string|null $key Optional cache key.
     *
     * @return PageViewCache Return page view cache instance.
    */
    public static function getCache(
        string $direcory, 
        DateTimeInterface|int|null $expiry = 0, 
        string|null $key = null
    ): PageViewCache
    {
        $key ??= static::cachekey();

        if(static::$viewCache === null){
            static::$viewCache = new PageViewCache();
        }

        static::$viewCache->setExpiry($expiry);
        static::$viewCache->setDirectory($direcory);
        static::$viewCache->setKey($key);

        return static::$viewCache;
    }

    /**
     * Determine if the cache has expired or not.
     * 
     * @param string $direcory The cache directory.
     * @param string|null $key Optional cache key.
     * 
     * @return bool true if the cache has expired otherwise false.
    */
    public static function expired(string $direcory, ?string $key = null): bool
    {
        $key ??= static::cachekey();

        if($key === ''){
            return false;
        }

        return PageViewCache::expired($key, $direcory);
    }

    /**
     * Generate cache file key
     * 
     * @param string|null $url Optional request url to drive cache key from.
     * 
     * @return string Return MD5 hashed cache key.
    */
    public static function cachekey(?string $url = null): string 
    {
        $url ??= ($_SERVER['REQUEST_URI'] ?? 'index');
        $key = preg_replace(['/[\/?&=#]/', '/-+/'], ['-', '-'], $url);

        return md5(trim($key, '-'));
    }

     /** 
     * Get base view file directory
     *
     * @param string path
     *
     * @return string path
    */
    public static function bothtrim(string $path): string 
    {
        return  trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}