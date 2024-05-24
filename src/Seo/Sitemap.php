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
use \Luminova\Base\BaseApplication;
use \Luminova\Base\BaseConsole;
use \Luminova\Command\TextUtils;
use \Luminova\Functions\Maths;
use \Luminova\Exceptions\RuntimeException;
use \DOMDocument;

final class Sitemap
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
     * Sitemap configuration.
     * 
     * @var SitemapConfig $config  
    */
    private static ?SitemapConfig $config = null;

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
        }

        if ($totalMemory === '-1') {
            self::$cli?->error('No memory limit is enforced');
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

        self::$config ??= new SitemapConfig();
        $url = ($url === null) ? self::startUrl() : self::toUrl($url);
       
        if($url === '' || $url === '/'){
            throw new RuntimeException(sprintf('Invalid start url: "%s", set start url in .env file "dev.app.start.url".', $url));
        }

        self::$cli = $cli;
        self::$visited = [];
        self::$faied = [];
        self::$urls = [];
        self::$counts = 0;
        self::$skipped = 0;

        // Start memory usage tracking
        self::$memoryThreshold = round(Maths::toBytes($totalMemory) * 0.7);

        $urls = self::getUrls($url);
        $app = app();
        if($urls === false || $urls === []){
            return false;
        }

        foreach ($urls as $page) {
            $link = str_replace(rtrim($url, '/') . '/', APP_URL . '/', $page['link']);
            if($link === APP_URL . '/public'){
                $link = APP_URL;
            }

            $xml .= '   <url>' . PHP_EOL;
            $xml .= '       <loc>' . htmlspecialchars($link) . '</loc>' . PHP_EOL;
            $xml .= '       <lastmod>'. ($page['lastmod'] ?? self::getLastmodified($link, $app)) .'</lastmod>' . PHP_EOL;
            $xml .= '       <priority>' . ($url === $page['link'] ? '1.00' : '0.8' ) . '</priority>' . PHP_EOL;
            $xml .= '   </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        $index = root('public');

        if(write_content($index . 'sitemap.xml', $xml)){
            self::$cli?->writeln();
            self::$cli?->header();
            self::$cli?->writeln(TextUtils::border('Your sitemap was completed succefully'), 'green');
            self::$cli?->writeln(TextUtils::padEnd('Extracted:', 20) . self::$cli?->color('[' .self::$counts  . ']', 'green'));
            self::$cli?->writeln(TextUtils::padEnd('Skipped:', 20) . self::$cli?->color('[' .self::$skipped  . ']', 'yellow'));
            self::$cli?->writeln(TextUtils::padEnd('Failed:', 20) . self::$cli?->color('[' . count(self::$faied) . ']', 'red'));

            return true;
        }

        return false;
    }

    /**
     * Get the last modified timestamp for a given URL based on view patterns.
     *
     * @param string $url The URL to check for last modified timestamp.
     * @param BaseApplication $app The application instance or relevant context.
     * 
     * @return string The last modified timestamp in ISO 8601 format, or current timestamp if not found.
     */
    private static function getLastModified(string $url, ?BaseApplication $app = null): string
    {
        $url = str_replace(APP_URL, '', $url);
        $lastmod = null;
        
        foreach (self::$config->viewUrlPatterns as $view => $pattern) {
            $regex = '#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#';

            if (preg_match($regex, rtrim($url)) === 1) {
                $viewInfo = $app->view($view)->viewInfo();
                $lastmod = $viewInfo['modified'] ?? null;
                break; 
            }
        }
        
        return date('Y-m-d\TH:i:sP', strtotime($lastmod ?? date('Y-m-d H:i:s')));
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
        if($href === '' || str_starts_with($href, '#')){
            return false;
        }

        if(self::matchesIgnore($href)){
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
        foreach (self::$config->ignoreUrls as $line) {
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
        return self::toUrl(env('dev.app.start.url', ''));
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
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $url = str_replace(['../', './'], '', $url);
        $url = trim($url, '/');
        $root = trim(basename(root()), '/') . '/public';

        if (str_starts_with($url, $root)) {
            return str_replace($root, rtrim(self::startUrl(), '/'), $url);
        }

        if (str_starts_with($url, 'public/')) {
            return str_replace('public/', self::startUrl(), $url);
        }

        return self::startUrl() . $url;
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
        if (self::$config->maxScan !== 0 && self::$counts >= self::$config->maxScan) {
            return self::$urls;
        }

        if (memory_get_usage() >= self::$memoryThreshold) {
            self::$cli?->error('Memory usage exceeded limit. Stopping extraction.');
            self::$cli?->newLine();

            return self::$urls;
        }

        $url = self::replaceUrls($url);
        $found = 0;
        $deepscans = [];
    
        self::$cli?->writeln('[Progress] ' . $url);
        $html = self::connection($url);
        self::$cli?->flush();
    
        if ($html === false) {
            self::$faied[] = $url;
            self::$cli?->writeln('[Failed] ' . $url);

            if($deep){
                return false;
            }

            return self::$urls;
        }
    
        self::$cli?->writeln('[Done] ' . $url);
    
        $dom = new DOMDocument();
        @$dom->loadHTML($html['document']);
        $links = $dom->getElementsByTagName('a');
        $length = $links->count(); 
    
        $subUrls = [];
        foreach ($links as $link) {
            if (memory_get_usage() >= self::$memoryThreshold) {
                self::$skipped += $length;
                self::$cli?->error('Memory usage exceeded limit. Stopping extraction.');
                return self::$urls;
            }
    
            if (self::$config->maxScan !== 0 && self::$counts >= self::$config->maxScan) {
                self::$skipped += $length;
                return self::$urls;
            }
    
            $href = $link->getAttribute('href');

            if (self::isAcceptable($href)) {
                $href = rtrim(self::replaceUrls($href), '/');

                if (str_starts_with($href, self::startUrl()) && filter_var($href, FILTER_VALIDATE_URL) && !isset(self::$urls[$href])) {
                    self::$counts++;
                    $found++;
                    $deepscans[$href] = $href;
                    self::$urls[$href] = [
                        'link' => $href,
                        'lastmod' => $html['lastmod'],
                    ];
                }
            }
        }
    
        self::$skipped += ($length - $found);

        foreach ($deepscans as $scan) {
            if (self::$config->maxScan !== 0 && self::$counts >= self::$config->maxScan) {
                return self::$urls;
            }
            
            $link = self::toUrl($scan);

            if(!in_array($link, self::$visited) && str_starts_with($link, self::startUrl())){
                $subUrls = self::getUrls($link, true);

                if($subUrls !== false){
                    self::$urls = array_merge(self::$urls, $subUrls);
                }
            }
        }
    
        return self::$urls;
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
        $url = self::toUrl($url);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $document = curl_exec($ch);
        $info = curl_getinfo($ch);

        if (curl_errno($ch) !== 0) {
            self::$cli?->writeln('[Error] ' . curl_error($ch), 'red');
            return false;
        }

        curl_close($ch);

        if ($document === false || $document === '') {
            self::$cli?->writeln('[Empty] ' . $url, 'red');
            return false;
        }

        self::$visited[] = $url;
        $lastmod = $info['filetime'] ?? -1;

        return [
            'document' => $document, 
            'lastmod' => ($lastmod != -1 ? date("Y-m-d\TH:i:sP", $lastmod) : null)
        ];
    }
}