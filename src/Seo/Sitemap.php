<?php
/**
 * Luminova Framework sitemap generator.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Seo;  

use \DOMDocument;
use \App\Application;
use \App\Config\Sitemap as SitemapConfig;
use \Luminova\Utils\Async;
use \Luminova\Functions\Maths;
use \Luminova\Utils\LazyObject;
use \Luminova\Http\Client\Curl;
use \Luminova\Command\Terminal;
use \Luminova\Core\CoreApplication;
use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Command\Utils\{Text, Color};
use function \Luminova\Funcs\{root, write_content, is_command};

final class Sitemap
{
    /**
     * Visited links.
     * 
     * @var array $visited  
     */
    private static array $visited = [];

    /**
     * Failed connections.
     * 
     * @var array $failed  
     */
    private static array $failed = [];

    /**
     * Extracted urls.
     * 
     * @var array $urls  
     */
    private static array $urls = [];

    /**
     * Extracted urls count .
     * 
     * @var int $counts   
     */
    private static int $counts = 0;

    /**
     * Extracted urls count.
     * 
     * @var int $skipped   
     */
    private static int $skipped = 0;

    /**
     * Command instance.
     * 
     * @var Terminal<LazyInterface>|null $term  
     */
    private static ?Terminal $term = null;

    /**
     * Sitemap configuration.
     * 
     * @var SitemapConfig $config  
     */
    private static ?SitemapConfig $config = null;

    /**
     * Set maximum memory usage threshold (in bytes).
     * 
     * @var int $memoryThreshold  
     */
    private static int $memoryThreshold = 0;

    /**
     * Application HTTPS url.
     * 
     * @var string $https  
     */
    private static string $https = '';

    /**
     * Application HTTP url.
     * 
     * @var string $http  
     */
    private static string $http = '';

    /**
     * Connection url label.
     * 
     * @var string $label  
     */
    private static string $label = '';

    /**
     * Generate a sitemap and store in in `public` directory.
     * 
     * @param string|null $url The start URL to generate sitemap of (default: null)
     * @param Terminal<LazyInterface>|null $term The terminal instance, to use when generating site map in cli (default: null).
     * @param string $basename The base name to save generated sitemap as (e.g, `sitemap.xml`).
     * 
     * @return bool Return true if successful, false otherwise.
     * @throws RuntimeException If tries to call in none cli environment.
     */
    public static function generate(
        ?string $url = null, 
        ?LazyInterface $term = null, 
        string $basename = 'sitemap.xml'
    ): bool  
    {
        $totalMemory = ini_get('memory_limit');

        if (!is_command()) {
            throw new RuntimeException('Sitemap generator should be run in cli mode only.');
        }

        if ($term instanceof LazyObject) {
            if (!$term->isInstanceof(Terminal::class)) {
                throw new RuntimeException(
                    sprintf('Expected an instance of Terminal, but got %s.', get_class($term->getLazyInstance()))
                );
            }

            self::$term = $term->getLazyInstance();
            $term = null;
        }elseif($term === null){
            self::$term = new Terminal();
        }

        if ($totalMemory === '-1') {
            self::_print('No memory limit is enforced', 'error');
            return false;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 
        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

        self::$config ??= new SitemapConfig();
        set_max_execution_time(self::$config->maxExecutionTime);

        $url = ($url === null) ? self::startUrl() : self::toUrl($url);
       
        if($url === '' || $url === '/'){
            throw new RuntimeException(sprintf('Invalid start url: "%s", set start url in .env file "dev.app.start.url".', $url));
        }
   
        self::$visited = [];
        self::$failed = [];
        self::$urls = [];
        self::$counts = 0;
        self::$skipped = 0;
        self::$https = 'https://' . APP_HOSTNAME;
        self::$http = 'http://' . APP_HOSTNAME;

        // Start memory usage tracking
        self::$memoryThreshold = round(Maths::toBytes($totalMemory) * 0.7);

        $urls = self::getUrls($url);
        $app = Application::getInstance();
        
        if($urls === false || $urls === []){
            return false;
        }

        $url = rtrim($url, '/');
        foreach ($urls as $page) {
            $xml .= self::addXml($page, $url, $app);
        }

        $xml .= '</urlset>';

        if(write_content(root('public', $basename), $xml)){
            self::_print('');
            self::_print('header');
            self::_print(Text::block('Your sitemap was completed successfully', Text::CENTER, 1, 'white', 'green'));
            self::_print(Text::padding('Extracted:', 20, Text::LEFT) . Color::style('[' .self::$counts  . ']', 'green'));
            self::_print(Text::padding('Skipped:', 20, Text::LEFT) . Color::style('[' .self::$skipped  . ']', 'yellow'));
            self::_print(Text::padding('Failed:', 20, Text::LEFT) . Color::style('[' . count(self::$failed) . ']', 'red'));
    
            gc_mem_caches();
            return true;
        }
        gc_mem_caches();
        return false;
    }

    /**
     * Generates XML sitemap entries for a given page.
     *
     * If the `includeStaticHtml` config option is enabled, it will append a `.html` extension 
     * to the URL if necessary.
     *
     * @param array $page Page details, including 'link' and optional 'lastmod'.
     * @param string $url Base URL used to compare the priority.
     * @param CoreApplication|null $app Optional application instance for fetching last modification date.
     * 
     * @return string Return the generated XML string for the sitemap.
     */
    private static function addXml(array $page, string $url, ?CoreApplication $app = null): string 
    {
        $link = self::toHttps($page['link'], $url);
        $lastMod = $page['lastmod'] ?? self::getLastModified($link, $app);
        $priority = ($url === $page['link'] || $link === $url) ? '1.00' : '0.80';
        $changeFreq = (self::$config->changeFrequently !== null) 
            ? '       <changefreq>' . self::$config->changeFrequently . '</changefreq>' . PHP_EOL 
            : '';
        
        $xml = '   <url>' . PHP_EOL;
        $xml .= '       <loc>' . htmlspecialchars($link, ENT_QUOTES | ENT_XML1) . '</loc>' . PHP_EOL;
        $xml .= '       <lastmod>'. $lastMod .'</lastmod>' . PHP_EOL;
        $xml .= $changeFreq;
        $xml .= '       <priority>' . $priority . '</priority>' . PHP_EOL;
        $xml .= '   </url>' . PHP_EOL;

        // Include static HTML link if configured, and append '.html' where appropriate.
        if($link !== self::$http && $link !== self::$https && self::$config->includeStaticHtml){
            if(!self::matchesIgnore($link, self::$config->skipStaticHtml)){
                $htmlLink = str_contains($link, '.html') 
                    ? $link
                    : (str_contains($link, '/#') 
                        ? str_replace('/#', '.html#', $link) 
                        : (str_contains($link, '#') 
                            ? str_replace('#', '.html#', $link) 
                            : rtrim($link, '/') . '.html'));

                $xml .= '   <url>' . PHP_EOL;
                $xml .= '       <loc>' . htmlspecialchars($htmlLink, ENT_QUOTES | ENT_XML1) . '</loc>' . PHP_EOL;
                $xml .= '       <lastmod>'. $lastMod .'</lastmod>' . PHP_EOL;
                $xml .= $changeFreq;
                $xml .= '       <priority>' . $priority . '</priority>' . PHP_EOL;
                $xml .= '   </url>' . PHP_EOL;
            }
        }

        return $xml;
    }

    /**
     * Get the last modified timestamp for a given URL based on view patterns.
     *
     * @param string $url The URL to check for last modified timestamp.
     * @param CoreApplication $app The application instance or relevant context.
     * 
     * @return string Return the last modified timestamp in ISO 8601 format, or current timestamp if not found.
     */
    private static function getLastModified(string $url, CoreApplication $app): string
    {
        $url = str_replace(self::$https, '', $url);
        $modified = null;
        
        foreach (self::$config->viewUrlPatterns as $view => $pattern) {
            $regex = '#^' . preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern) . '$#';

            if (preg_match($regex, rtrim($url)) === 1) {
                $viewInfo = $app->view($view)->viewInfo();
                $modified = $viewInfo['modified'] ?? null;
                break; 
            }
        }

        $timestamp = $modified ? strtotime($modified) : false;

        if ($timestamp === false) {
            $timestamp = time();
        }
        
        return date('Y-m-d\TH:i:sP', $timestamp);
    }
    
    /**
     * Print a message to the CLI with optional color formatting.
     *
     * Action types:
     * - If the message is an empty string, it outputs a new line.
     * - If the message is 'header', it calls the CLI's header method.
     * - If the message is 'flush', it triggers the CLI's flush method.
     * - If the color is 'error', it prints the message as an error.
     * - Otherwise, it writes the message to the CLI with the specified color.
     *
     * @param string|null $message The message to print.
     * @param string|null $color The color to apply to the message (optional).
     * @param string $method The method to call for writing (default: `writeln`).
     * @param string|null $flush The last printed line to flush.
     * 
     * @return void
     */
    private static function _print(
        string|null $message, 
        ?string $color = null, 
        string $method = 'writeln',
        ?string $flush = null
    ): void 
    {
        if ($message === '') {
            self::$term->newLine();
            return;
        }
    
        match ($message) {
            'header' => self::$term->header(),
            'flush' => self::$term->flush($flush),
            default => ($color === 'error')
                ? self::$term->error($message)
                : self::$term->{$method}($message, $color)
        };
    }

    /**
     * Replace url HTTP to HTTPS
     * 
     * @param string $url The url to replace.
     * 
     * @return string Return https url.
     */
    private static function toHttps(string $url, string $search): string 
    {
        $url = str_starts_with($url, self::$https) ? $url : str_replace($search, self::$https, $url);
 
        if($url === self::$https . '/public' || $url === self::$http . '/public'){
            return self::$https;
        }

        return $url;
    }

    /**
     * Trim url and add slash.
     * 
     * @param string $url The url to trim.
     * 
     * @return string Return trimmed url.
     */
    private static function toUrl(string $url): string 
    {
        return rtrim($url, '/') . '/';
    }

    /**
     * Check if url is acceptable and not a hash nor in ignore list.
     * 
     * @param string $href The URL to check.
     * @param string $startUrl The start url.
     * 
     * @return bool Return true if url is acceptable and not in ignore list, otherwise false.
     */
    private static function isAcceptable(string $href): bool
    {
        if($href === '' || str_starts_with($href, '#')){
            return false;
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z\d+\-.]*:\/\//', $href) && !self::isPrefix($href)) {
            return false;
        }

        return !self::matchesIgnore($href, self::$config->ignoreUrls, true);
    }

    /**
     * Check if url is matched the start url prefix.
     * 
     * @param string $href The URL to check.
     * 
     * @return bool Return true if url is matched, false otherwise.
     */
    private static function isPrefix(string $href): bool 
    {
        return str_starts_with($href, self::startUrl() . trim(self::$config->scanUrlPrefix, '/'));
    }

    /**
     * Check if URL ignore pattern matches URL.
     * 
     * If line starts with '@', treat as raw regex otherwise convert glob pattern to regex.
     * 
     * @param string $url The URL to check.
     * @param array $patterns The URL patterns to check.
     * 
     * @return bool Return true if URL is in ignore pattern, false otherwise.
     */
    private static function matchesIgnore(string $url, array $patterns, bool $output = false): bool 
    {
        if ($patterns === []) {
            return false;
        }

        foreach ($patterns as $line) {
            if ($line === '') {
                continue;
            }

            $pattern = str_starts_with($line, '@') 
                ? '#' . substr($line, 1) . '#i'
                : '/^' . str_replace(['\*'], '.*', preg_quote($line, '/')) . '$/i';
          
            if ($url === $line || preg_match($pattern, $url)) {
                if($output){
                    self::_print('[Ignore] ' . self::replaceUrls($url), 'yellow');
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Get the default host and base application.
     * 
     * @return string Return the start URL ensuring it's a valid url.
     */
    private static function startUrl(): string 
    {
        return self::toUrl(env('dev.app.start.url', ''));
    }

    /**
     * Replace a relative url to absolute url.
     * 
     * @param string $url The URL to replace.
     * 
     * @return string Return an absolute url.
     */
    private static function replaceUrls(string $url): string 
    {
        if (str_starts_with($url, 'http')) {
           return rtrim($url, '/');
        }

        $url = trim(str_replace(['../', './'], '', $url), '/');
        $root = trim(basename(root()), '/') . '/public';

        if (str_starts_with($url, $root)) {
            return rtrim(self::startUrl(), '/') . substr($url, strlen($root));
        }

        if (str_starts_with($url, 'public/')) {
            return  self::startUrl() . substr($url, strlen('public/'));
        }
        
        return self::startUrl() . $url;
    }

    /**
     * Open connection and process URLs.
     * 
     * @param string $url The to browse.
     * @param bool $deep is connection a deep scan.
     * 
     * @return bool|array<string,mixed> Return the extracted URLs.
     */
    private static function getUrls(string $url, bool $deep = false): array|bool
    {
        if (self::$config->maxScan !== 0 && self::$counts >= self::$config->maxScan) {
            return self::$urls;
        }

        if (memory_get_usage() >= self::$memoryThreshold) {
            self::_print('Memory usage exceeded limit. Stopping extraction.', 'error');
            self::_print('');

            return self::$urls;
        }
        
        self::$label = '';
        $url = self::replaceUrls($url);
        $found = 0;
        $deepScans = [];
        $html = self::connection($url);
    
        if ($html === false) {
            self::$failed[] = $url;
            if(self::$label){
                self::_print('flush', null, 'writeln', self::$label);
            }
            
            self::_print('[Failed] ' . $url, 'red');

            if($deep){
                return false;
            }

            return self::$urls;
        }
    
        $dom = new DOMDocument();
        @$dom->loadHTML($html['document']);

        /**
         * @var DOMNodeList
         */
        $links = $dom->getElementsByTagName('a');
        $length = $links->count(); 
    
        $subUrls = [];
        foreach ($links as $link) {
            if (memory_get_usage() >= self::$memoryThreshold) {
                self::$skipped += $length;
                self::_print('Memory usage exceeded limit. Stopping extraction.', 'error');
                return self::$urls;
            }
    
            if (self::$config->maxScan !== 0 && self::$counts >= self::$config->maxScan) {
                self::$skipped += $length;
                return self::$urls;
            }
    
            $href = $link->getAttribute('href');

            if (!self::isAcceptable($href)) {
                continue;
            }

            $href = rtrim(self::replaceUrls($href), '/');

            if (self::isPrefix($href) && filter_var($href, FILTER_VALIDATE_URL) && !isset(self::$urls[$href])) {
                self::$counts++;
                $found++;
                $deepScans[$href] = $href;
                self::$urls[$href] = [
                    'link' => $href,
                    'lastmod' => $html['lastmod'],
                ];
            }
        }
    
        self::$skipped += ($length - $found);

        foreach ($deepScans as $scan) {
            if (self::$config->maxScan !== 0 && self::$counts >= self::$config->maxScan) {
                return self::$urls;
            }
            
            $link = self::toUrl($scan);

            if(!in_array($link, self::$visited) && self::isPrefix($link)){
                $subUrls = self::getUrls($link, true);

                if($subUrls !== false){
                    self::$urls = array_merge(self::$urls, $subUrls);
                }
            }
        }
    
        return self::$urls;
    }

    /**
     * cURL browser to open a connection to url and extract document body and file-time if header is set.
     * 
     * @param string $url The url to load it contents.
     * 
     * @return array<string,string>|false Return array containing the page content and file-time.
     */
    private static function connection(string $url): array|bool
    {
        $url = self::toUrl($url);
        self::$label = '[Scanning] ' . $url;
        
        self::_print(self::$label, 'cyan');

        try{
            /**
             * @var \Luminova\Interface\ResponseInterface $response
             */
            $response = Async::await(fn() => (new Curl([
                'file_time' => true,
                'onBeforeRequest' => fn(string $url, array $headers) => self::$term->watcher(
                    max(1, self::$config->scanSpeed), 
                    fn() => self::_print('flush'),
                    null,
                    false
                )
            ]))->request('GET', $url));
            
            self::_print('flush', null, 'writeln', self::$label);

            if ($response->getStatusCode() !== 200) {
                self::$label = "[{$response->getStatusCode()}] {$url}";
                self::_print(self::$label, 'red');
                return false;
            }

            if (!$response->getContents()) {
                self::$label = "[Error] Empty response from {$url}";
                self::_print(self::$label, 'red');
                return false;
            }

            self::$label = "[Done] {$url}";
            self::_print(self::$label);
            self::$visited[] = $url;
            $modified = $response->getFileTime();

            return [
                'document' => $response->getContents(),
                'lastmod'  => ($modified != -1) ? date("Y-m-d\TH:i:sP", $modified) : null,
            ];
        }catch(AppException $e){
            self::_print('flush');
            self::_print('flush', null, 'writeln', self::$label);

            self::$label = '[Error] ' . $e->getMessage();
            self::_print(self::$label, 'red');
        }

        return false;
    }
}