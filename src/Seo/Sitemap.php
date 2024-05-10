<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Seo;  

use \App\Controllers\Config\Sitemap as SitemapConfig;
use \Luminova\Base\BaseConsole;
use \Luminova\Command\TextUtils;
use \Luminova\Application\Functions;
use \Luminova\Exceptions\RuntimeException;
use \DOMDocument;

class Sitemap
{
    /**
     * Visited links 
     * @var array $visited  
    */
    private static array $visited = [];

    /**
     * Failed connections 
     * @var array $failed  
    */
    private static array $faied = [];

    /**
     * Extracted urls  
     * @var array $urls  
    */
    private static array $urls = [];

    /**
     * Extracted urls count  
     * @var int $counts   
    */
    private static int $counts = 0;

    /**
     * Extracted urls count  
     * @var int $skipped   
    */
    private static int $skipped = 0;

    /**
     * Command instance 
     * @var BaseConsole $cli  
    */
    private static ?BaseConsole $cli = null;

    /**
     * Set maximum memory usage threshold (in bytes)
     * @var int $memoryThreshold  
    */
    private static int $memoryThreshold = 0;

    /**
     * Generate site map 
     * 
     * @param null|string $url 
     * @param null|BaseConsole $cli
     * 
     * @return bool 
     * @throws RuntimeException If tries to call in none cli environment.
    */
    public static function generate(?string $url = null, ?BaseConsole $cli = null): bool 
    {
        set_time_limit(300);
        $totalMemory = ini_get('memory_limit');

        if (!is_command()) {
            throw new RuntimeException('Sitemap generator should be run in cli mode.');
            return false;
        }

        if ($totalMemory === '-1') {
            static::$cli?->error('No memory limit is enforced');
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

        $url = ($url === null) ? static::startUrl() : static::toUrl($url);
       
        if($url === '' || $url === '/'){
            throw new RuntimeException(sprintf('Invalid start url: "%s", set start url in .env file "dev.app.start.url".', $url));
            return false;
        }
        static::$cli = $cli;
        static::$visited = [];
        static::$faied = [];
        static::$urls = [];
        static::$counts = 0;
        static::$skipped = 0;

        // Start memory usage tracking
        static::$memoryThreshold = round(Functions::math()->toBytes($totalMemory) * 0.7);
        //static::$memoryThreshold = (int) memory_get_usage(true) * 0.7;

        $urls = self::getUrls($url);
        $app = app();
        if($urls === false || $urls === []){
            return false;
        }

        foreach ($urls as $page) {
            $link = str_replace(rtrim($url, '/') . '/', APP_URL . '/', $page['link']);
            $lastmod = ($page['lastmod'] === null) ? static::getLastmodified($link, $app) : $page['lastmod'];

            $xml .= '   <url>' . PHP_EOL;
            $xml .= '       <loc>' . htmlspecialchars($link) . '</loc>' . PHP_EOL;
            $xml .= '       <lastmod>'. $lastmod .'</lastmod>' . PHP_EOL;
            $xml .= '       <priority>' . ($url === $page['link'] ? '1.00' : '0.8' ) . '</priority>' . PHP_EOL;
            $xml .= '   </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        $index = root('public');

        if(write_content($index . 'sitemap.xml', $xml)){
            static::$cli?->writeln();
            static::$cli?->header();
            static::$cli?->writeln(TextUtils::border('Your sitemap was completed succefully'), 'green');
            static::$cli?->writeln(TextUtils::padEnd('Extracted:', 20) . static::$cli?->color('[' .static::$counts  . ']', 'green'));
            static::$cli?->writeln(TextUtils::padEnd('Skipped:', 20) . static::$cli?->color('[' .static::$skipped  . ']', 'yellow'));
            static::$cli?->writeln(TextUtils::padEnd('Failed:', 20) . static::$cli?->color('[' . count(static::$faied) . ']', 'red'));

            return true;
        }

        return false;
    }

    /**
     * Get the last modified timestamp for a given URL based on view patterns.
     *
     * @param string $url The URL to check for last modified timestamp.
     * @param Application $app The application instance or relevant context.
     * @return string The last modified timestamp in ISO 8601 format, or current timestamp if not found.
     */
    private static function getLastModified(string $url, ?object $app = null): string
    {
        $url = str_replace(APP_URL, '', $url);
        $lastmod = null;
        
        foreach (SitemapConfig::$viewUrlPatterns as $view => $pattern) {
            $regex = '#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#';

            if (preg_match($regex, rtrim($url)) === 1) {
                $viewInfo = $app->view($view)->viewInfo();
                $lastmod = $viewInfo['modified'] ?? null;
                break; 
            }
        }
        
        return $lastmod ?? date('Y-m-d\TH:i:sP');
    }

    /**
     * Trim url and add slash 
     * 
     * @param string $url url to trim.
     * 
     * @return string URL
    */
    private static function toUrl(string $url): string 
    {
        return rtrim($url, '/') . '/';
    }

    /**
     * Check if url is acceptable and not a hash nor in ignore list.
     * 
     * @param string $href to check.
     * 
     * @return bool
    */
    private static function isAcceptable(string $href): bool
    {
        if(empty($href) || str_starts_with($href, '#')){
            return false;
        }

        if(static::matchesIgnore($href)){
            return false;
        }

        return true;
    }

    /**
     * Check if URL ignore pattern matches URL.
     * 
     * @param string $url
     * 
     * @return bool
    */
    private static function matchesIgnore(string $url): bool 
    {
        foreach (SitemapConfig::$ignoreUrls as $line) {
            $pattern = str_replace('/', '\/', $line);
            $pattern = str_replace('*', '.+?', $pattern);

            if (preg_match('/^' . $pattern . '$/', $url) || $url === $line) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the default host and base application 
     * 
     * @return string
    */
    private static function startUrl(): string 
    {
        return static::toUrl(env('dev.app.start.url', ''));
    }

    /**
     * Replace url and remove exessive dots.
     * 
     * @param string $url URL to replace.
     * 
     * @return string
    */
    private static function replaceUrls(string $url): string 
    {
        if (strpos($url, 'http') === 0) {
            return $url;
        }

        $url = str_replace(['../', './'], '', $url);
        $url = trim($url, '/');
        //$root = trim(SitemapConfig::$projectDirName, '/') . '/public';
        $root = trim(basename(root()), '/') . '/public';

        if (str_starts_with($url, $root)) {
            return str_replace($root, rtrim(static::startUrl(), '/'), $url);
        }

        if (strpos($url, 'public/') === 0) {
            return str_replace('public/', static::startUrl(), $url);
        }

        return static::startUrl() . $url;
    }

    /**
     * Open connection and process urls 
     * 
     * @param string $url to browse.
     * @param bool $deep is connection a deep scan.
     * 
     * @return bool|array<string, mixed> extracted urls
    */
    private static function getUrls(string $url, bool $deep = false): array|bool
    {
        if (SitemapConfig::$maxScan !== 0 && static::$counts >= SitemapConfig::$maxScan) {
            return static::$urls;
        }

        if (memory_get_usage() >= static::$memoryThreshold) {
            static::$cli?->error('Memory usage exceeded limit. Stopping extraction.');
            static::$cli?->newLine();

            return static::$urls;
        }

        $url = static::replaceUrls($url);
        $found = 0;
        $deepscans = [];
    
        static::$cli?->writeln('[Progress] ' . $url);
        $html = static::connection($url);
        static::$cli?->flush();
    
        if ($html === false) {
            static::$faied[] = $url;
            static::$cli?->writeln('[Failed] ' . $url);

            if($deep){
                return false;
            }

            return static::$urls;
        }
    
        static::$cli?->writeln('[Done] ' . $url);
    
        $dom = new DOMDocument();
        @$dom->loadHTML($html['document']);
        $links = $dom->getElementsByTagName('a');
        $length = $links->count(); 
    
        $subUrls = [];
        foreach ($links as $link) {
            if (memory_get_usage() >= static::$memoryThreshold) {
                static::$skipped += $length;
                static::$cli?->error('Memory usage exceeded limit. Stopping extraction.');
                return static::$urls;
            }
    
            if (SitemapConfig::$maxScan !== 0 && static::$counts >= SitemapConfig::$maxScan) {
                static::$skipped += $length;
                return static::$urls;
            }
    
            $href = $link->getAttribute('href');

            if (static::isAcceptable($href)) {
                $href = rtrim(static::replaceUrls($href), '/');

                if (str_starts_with($href, static::startUrl()) && filter_var($href, FILTER_VALIDATE_URL)) {
                    if (!isset(static::$urls[$href])) {
                        static::$counts++;
                        $found++;
                        $deepscans[$href] = $href;
                        static::$urls[$href] = [
                            'link' => $href,
                            'lastmod' => $html['lastmod'],
                        ];
                    }
                }
            }
        }
    
        static::$skipped += ($length - $found);

        foreach ($deepscans as $scan) {
            if (SitemapConfig::$maxScan !== 0 && static::$counts >= SitemapConfig::$maxScan) {
                return static::$urls;
            }
            
            $link = static::toUrl($scan);

            if(!in_array($link, static::$visited) && str_starts_with($link, static::startUrl())){
                $subUrls = self::getUrls($link, true);

                if($subUrls !== false){
                    static::$urls = array_merge(static::$urls, $subUrls);
                }
            }
        }
    
        return static::$urls;
    }

    /**
     * Open a connection to url and extract document body and filetime if header is set
     * 
     * @param string $url url to load
     * 
     * @return bool|array<string, string>
    */
    private static function connection(string $url): array|bool 
    {
        $url = static::toUrl($url);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $document = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (curl_errno($ch)) {
            static::$cli?->writeln('[Error] ' . curl_error($ch), 'red');
            return false;
        }

        curl_close($ch);

        if (empty($document)) {
            static::$cli?->writeln('[Empty] ' . $url, 'red');
            return false;
        }

        static::$visited[] = $url;
        $timestamp = $info['filetime'] ?? -1;

        if ($timestamp != -1) {
            $lastmod = date("Y-m-d\TH:i:sP", $timestamp);
        }else{
            $lastmod = null;
        }

        return [
            'document' => $document, 
            'lastmod' => $lastmod
        ];
    }
}