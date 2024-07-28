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

use \Luminova\Optimization\Minification;
use \Luminova\Cache\ViewCache;
use \DateTimeInterface;

class Helper 
{
    /**
     * Page minification instance.
     * 
     * @var ?Minification $min
    */
    private static ?Minification $min = null;

    /**
     * View cache instance.
     * 
     * @var ViewCache $viewCache 
    */
    private static ?ViewCache $viewCache  = null;

    /** 
     * Initialize minification instance.
     *
     * @param mixed $contents view contents output buffer.
     * @param string $type The content type.
     * @param bool $ignore Weather to ignore code blocks minification.
     * @param bool $copy Weather to include code block copy button.
     *
     * @return Minification Return minified instance.
    */
    public static function getMinification(
        mixed $contents, 
        string $type = 'html', 
        bool $ignore = true, 
        bool $copy = false,
    ): Minification
    {
        if(self::$min === null){
            self::$min = new Minification();
        }

        self::$min->codeblocks($ignore);
        self::$min->copyable($copy);

        return self::$min->compress($contents, $type);
    }

    /** 
     * Get page view cache instance
     *
     * @param string $directory Cache directory path. 
     * @param string $key The view cache key.
     * @param DateTimeInterface|int|null $expiry  Cache expiration ttl (default: 0).
     *
     * @return ViewCache Return page view cache instance.
    */
    public static function getCache(
        string $directory, 
        string $key,
        DateTimeInterface|int|null $expiry = 0, 
    ): ViewCache
    {
        if(self::$viewCache === null){
            self::$viewCache = new ViewCache();
        }

        self::$viewCache->setExpiry($expiry);
        self::$viewCache->setDirectory($directory);
        self::$viewCache->setKey($key);

        return self::$viewCache;
    }

    /** 
     * Get base view file directory and trim.
     *
     * @param string $path The path to trim.
     *
     * @return string Return trimmed path.
    */
    public static function bothTrim(string $path): string 
    {
        return  trim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /** 
     * Fixes the broken css,image & links when added additional slash(/) at the router link
     * The function will add the appropriate relative base based on how many invalid link detected.
     *
     * @return string Return relative path.
    */
    public static function relativeLevel(): string 
    {
        $level = 0;
        if(isset($_SERVER['REQUEST_URI'])){
            $length = isset($_SERVER['SCRIPT_NAME']) ? strlen(dirname($_SERVER['SCRIPT_NAME'])) : 0;
            $url = substr(rawurldecode($_SERVER['REQUEST_URI']), $length);

            if (($pos = strpos($url, '?')) !== false) {
                $url = substr($url, 0, $pos);
            }

            $level = substr_count('/' . trim($url, '/'), '/');
        }

        if($level === 0){
            return './';
        }

        return str_repeat('../', $level);
    }

    /**
     * Convert view name to title and add suffix if specified.
     *
     * @param string $view  The view name.
     * @param bool   $suffix Whether to add suffix.
     *
     * @return string Return view page title.
     */
    public static function toTitle(string $view, bool $suffix = false): string 
    {
        $view = strtr($view, ['_' => ' ', '-' => ' ', ',' => '']);
        $view = ucwords($view);

        if ($suffix && !str_contains($view, ' - ' . APP_NAME)) {
            $view .= ' - ' . APP_NAME;
        }

        return $view;
    }
}