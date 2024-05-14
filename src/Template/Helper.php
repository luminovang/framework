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

    /** 
     * Fixes the broken css,image & links when added additional slash(/) at the router link
     * The function will add the appropriate relative base based on how many invalid link detected.
     *
     * @return string relative path 
    */
    public static function relativeLevel(): string 
    {
        /*$uri = static::getViewUri();
        if (($pos = strpos($uri, '/public')) !== false) {
            $uri = substr($uri, $pos + 7);
        }*/

        $level = substr_count(static::getViewUri(), '/');
        
        if($level === 0){
            return './';
        }

        return str_repeat('../', $level);
    }

    /** 
     * Get template base view segments
     *
     * @return string template view segments
    */
    private static function getViewUri(): string
    {
        if(isset($_SERVER['REQUEST_URI'])){
            $length = isset($_SERVER['SCRIPT_NAME']) ? strlen(dirname($_SERVER['SCRIPT_NAME'])) : 0;
            $url = substr(rawurldecode($_SERVER['REQUEST_URI']), $length);

            if (($pos = strpos($url, '?')) !== false) {
                $url = substr($url, 0, $pos);
            }

            return '/' . trim($url, '/');
        }

        return '/';
    }

     /**
     * Convert view name to title and add suffix if specified
     *
     * @param string $view    View name
     * @param bool   $suffix  Whether to add suffix
     *
     * @return string View title
    */
    public static function toTitle(string $view, bool $suffix = false): string 
    {
        $view = str_replace(['_', '-', ','], [' ', ' ', ''], $view);
        $view = ucwords($view);

        if ($suffix) {
            if (!str_contains($view, '- ' . APP_NAME)) {
                $view .= ' - ' . APP_NAME;
            }
        }

        return trim($view);
    }
}