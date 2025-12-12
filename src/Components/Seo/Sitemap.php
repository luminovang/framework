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
namespace Luminova\Components\Seo;  

use \Throwable;
use \DOMDocument;
use \Luminova\Luminova;
use \Luminova\Utility\Maths;
use \Luminova\Routing\Router;
use \Luminova\Command\Terminal;
use \Luminova\Components\Async;
use \Luminova\Http\Client\Novio;
use \Luminova\Base\Configuration;
use \App\Config\Sitemap as SitemapConfig;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Command\Utils\{Text, Color};
use function \Luminova\Funcs\{root, view, write_content};

final class Sitemap
{
    /**
     * Visited links.
     * 
     * @var array $visited  
     */
    private static array $visited = [];

    /**
     * Failed connections or broken 404 urls.
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
     * Novakit CLI options.
     * 
     * @var array $options  
     */
    private static array $options = [];

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
     * Message verbosity level.
     * 
     * @var int $verbose  
     */
    private static int $verbose = 3;

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
     * Generate a sitemap or broken-link report in the `public` directory.
     *
     * This command crawls a starting URL (or the app’s default start URL) to collect all
     * reachable pages. It then generates either:
     * - an **XML sitemap** for search engines, or
     * - a **JSON report** of broken links (if scanning mode is enabled).
     *
     * @param string|null $url Starting URL to scan.
     *     If null, the value from `env(dev.app.start.url)` will be used.
     * @param string $basename Output filename (e.g. `sitemap.xml` or `broken.json`).
     * @param App\Config\Sitemap<Luminova\Base\Configuration>|null $config
     *     Optional configuration to control crawling behavior (speed, limit, etc.).
     * @param array<string,mixed> $options {
     *     Additional generation options:
     *     @var bool $isBroken     Generate broken-link report instead of sitemap. Default false.
     *     @var int  $verbose      Output verbosity level (0–3). Default 3.
     *     @var bool $isDryRun     Simulate run without writing output. Default false.
     *     @var bool $ignoreAssets Skip assets like images, CSS, and JS. Default true.
     * }
     *
     * @return bool True if generation succeeds, false on failure.
     *
     * @throws RuntimeException If called outside CLI mode or given an invalid Terminal instance.
     *
     * @example
     * ```php
     * // Generate a sitemap
     * Sitemap::generate(
     *     url: 'https://example.com',
     *     basename: 'sitemap.xml'
     * );
     *
     * // Generate a broken-link report
     * Sitemap::generate(
     *     url: 'https://example.com',
     *     basename: 'broken.json',
     *     options: ['isBroken' => true]
     * );
     * ```
     */
    public static function generate(
        ?string $url = null,
        string $basename = 'sitemap.xml',
        ?Configuration $config = null,
        array $options = []
    ): bool  
    {
        if (!Luminova::isCommand()) {
            throw new RuntimeException('Sitemap generator should be run in CLI mode only.');
        }

        if (str_contains($basename, '/') || str_contains($basename, '\\')) {
            throw new RuntimeException(sprintf(
                'Invalid basename "%s". It must not contain directory separators or paths — only a file name like "sitemap.xml".',
                $basename
            ));
        }

        self::$options = array_replace([
            'isBroken'      => false, 
            'isLinkTree'    => false,
            'verbose'       => 3,
            'isDryRun'      => false,
            'ignoreAssets'  => true,
            'treeFormat'    => '{url} {title} ({status})'
        ], $options);

        self::$config = $config ?? new SitemapConfig();
        set_max_execution_time(self::$config->maxExecutionTime);

        self::$memoryThreshold = round(Maths::toBytes(ini_get('memory_limit')) * 0.6);

        $url = ($url === null) 
            ? self::startUrl() 
            : self::toUrl($url);

        if ($url === '' || $url === '/') {
            throw new RuntimeException(
                sprintf('Invalid start URL "%s". Set a valid one in .env (dev.app.start.url).', $url)
            );
        }

        self::$visited = [];
        self::$failed = [];
        self::$urls = [];
        self::$counts = 0;
        self::$skipped = 0;
        self::$https = 'https://' . APP_HOSTNAME;
        self::$http = 'http://' . APP_HOSTNAME;

        $urls = self::crawl($url);
        
        if (empty($urls)) {
            return false;
        }

        if(self::$options['isLinkTree']){
            return self::report(
                self::generateLinkTree($urls, $basename), 
                $basename,
            );
        }

        return self::report(self::$options['isBroken']
            ? self::generateBrokenLinkReport($basename)
            : self::generateXmlSitemap($urls, $url), 
            $basename,
        );
    }

    /**
     * Generate a JSON report containing all detected broken links.
     *
     * This method converts the list of failed URLs collected during
     * crawling into a human-readable JSON string and updates the
     * output filename to use a `.json` extension.
     *
     * @param string $basename The base filename used for the output report (modified by reference).
     *
     * @return string Returns a JSON-encoded string of broken links.
     */
    private static function generateBrokenLinkReport(string &$basename): string
    {
        $basename = str_ends_with($basename, '.xml') 
            ? 'broken.sitemap.json' 
            : $basename;

        return json_encode(self::$failed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate a customizable link tree report.
     * 
     * This method creates a plain TXT report listing URLs and their metadata.
     * Developers can specify a format template to control which segments appear
     * and how they are displayed.
     * 
     * Default format: `{url} {title} {lastmod} {status}`
     * 
     * Supported placeholders:
     * - `{url}`: URL of the page
     * - `{title}`: Page title
     * - `{status}`: HTTP status code
     * - `{lastmod}`: Last modified date
     * 
     * @param array $urls Array of URLs with metadata.
     * @param string &$basename Output filename (will default to `.txt` if invalid)
     * 
     * @return string Generated report content.
     * 
     * @example - Example:
     * ```php
     * $format = '{url} | {title} | Status: {status} | Last Modified: {lastmod}';
     * $report = self::generateLinkTree($urls, $basename, $format);
     * ```
     */
    private static function generateLinkTree(
        array $urls, 
        string &$basename
    ): string
    {
        $basename = (str_ends_with($basename, '.xml') || str_ends_with($basename, '.json'))
            ? 'sitemap.link.tree.txt' 
            : $basename;

        $lines = [];
        $format = self::$options['treeFormat'];
        if(!$format || $format === true){
            $format = '{url} {title} ({status})';
        }

        foreach ($urls as $data) {
            $line = strtr($format, [
                '{url}' => $data['link'] ?? '',
                '{title}' => $data['title'] ?? '',
                '{status}' => $data['status'] ?? '',
                '{lastmod}' => $data['lastmod'] ?? '',
            ]);

            $lines[] = preg_replace('/\s+/', ' ', trim($line));
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Generate an XML sitemap from the collected URLs.
     *
     * Builds the XML structure according to the sitemap protocol.
     * Each valid page URL is added as a <url> entry in the sitemap.
     *
     * @param array $urls A list of URLs collected during the crawl process.
     * @param string $url The root or starting URL used for sitemap generation.
     *
     * @return string Returns the complete XML sitemap as a string.
     */
    private static function generateXmlSitemap(array $urls, string $url): string
    {
        $url = rtrim($url, '/');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . PHP_EOL;

        foreach ($urls as $page) {
            $xml .= self::addXml($page, $url);
        }

        return $xml . '</urlset>';
    }

    /**
     * Generates a CLI summary report after completing a sitemap or broken link scan.
     * 
     * This method writes the final output file, clears memory caches, 
     * and displays a formatted summary in the terminal showing:
     * - Total scanned URLs
     * - Skipped URLs
     * - Failed (broken) URLs
     * 
     * It also prints the location of the saved output file for easy access.
     *
     * @param string $contents The report contents to be written to file.
     * @param string $basename The filename to save in the public directory.
     *
     * @return bool Returns true if the report file was successfully written, false on failure.
     * @throws RuntimeException If writing the file fails.
     */
    private static function report(string $contents, string $basename): bool
    {
        $filepath = root('public', $basename);

        gc_collect_cycles(); 
        gc_mem_caches();

        // Save report to file if not dry run
        if (!self::$options['isDryRun'] && !write_content($filepath, $contents)) {
            
            return false;
        }

        [$report, $failLabel] = match (true) {
            self::$options['isBroken']   => ['Broken Link Scan Completed', 'Broken Links Found:'],
            self::$options['isLinkTree'] => ['Link Tree Generation Completed', 'Invalid Links Found:'],
            default  => ['Sitemap Generation Completed', 'Failures Detected:'],
        };

        self::_print('');
        self::_print('header');

        self::_print(
            Text::block($report, Text::CENTER, 1, 'white', 'green')
        );

        self::_print(
            Text::padding('Scanned:', 20, Text::LEFT) 
            . Color::style('[' . self::$counts . ']', 'green')
        );

        self::_print(
            Text::padding('Skipped:', 20, Text::LEFT) 
            . Color::style('[' . self::$skipped . ']', 'yellow')
        );

        self::_print(
            Text::padding($failLabel, 20, Text::LEFT) 
            . Color::style('[' . count(self::$failed) . ']', 'red')
        );

        if (!self::$options['isDryRun']) {
            self::_print(
                Text::padding('Output File:', 20, Text::LEFT) 
                . Color::style($filepath, 'cyan')
            );
        }

        return true;
    }

    /**
     * Generates XML sitemap entries for a given page.
     *
     * If the `includeStaticHtml` config option is enabled, it will append a `.html` extension 
     * to the URL if necessary.
     *
     * @param array $page Page details, including 'link' and optional 'lastmod'.
     * @param string $url Base URL used to compare the priority.
     * 
     * @return string Return the generated XML string for the sitemap.
     */
    private static function addXml(array $page, string $url): string 
    {
        $link = self::toHttps($page['link'], $url);
        $lastMod = $page['lastmod'] ?? self::getLastModified($link);
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
     * 
     * @return string Return the last modified timestamp in ISO 8601 format, or current timestamp if not found.
     */
    private static function getLastModified(string $url): string
    {
        $url = str_replace(self::$https, '', $url);

        foreach (self::$config->viewUrlPatterns as $template => $pattern) {
            if (preg_match('#^' . Router::toPatterns($pattern) . '$#', rtrim($url))) {
                $modified = view($template)->info('modified');
                return date('Y-m-d\TH:i:sP', strtotime($modified ?: 'now'));
            }
        }
        return date('Y-m-d\TH:i:sP');
    }
    
    /**
     * Print a message to the CLI with optional color formatting and verbosity control.
     *
     * Behavior:
     * - Empty string → prints a new line.
     * - 'header' → calls the CLI header method.
     * - 'flush' → triggers the CLI flush (optionally with the last label).
     * - 'error' color → prints as an error.
     * - Otherwise → writes using the given method and color.
     *
     * Verbosity levels:
     * - 0: silent.
     * - 1: show only SUCCESS messages.
     * - 2: show SUCCESS and ERROR messages.
     * - 3: show all messages.
     *
     * @param string|null $message Message to print or special action keyword.
     * @param string|null $color Optional color to apply (e.g., 'error', 'info').
     * @param string $method CLI output method to call (default: `writeln`).
     * @param bool $output Whether to flush with the last label.
     * @param string $verbose Verbosity tag (e.g., 'NORMAL', 'SUCCESS', 'ERROR', 'DEBUG').
     * @param bool $label Whether to save this message as the current label.
     * 
     * @return void
     */
    private static function _print(
        string|null $message, 
        ?string $color = null, 
        string $method = 'writeln',
        bool $output = false,
        string $verbose = 'NORMAL',
        bool $label = false
    ): void 
    {
        if (self::$verbose < 3 && $message !== 'flush' && $method !== 'flush' && $verbose !== 'NORMAL') {
            if (self::$verbose === 0) {
                return;
            }

            if (self::$verbose === 1 && $verbose !== 'SUCCESS') {
                return;
            }

            if (self::$verbose === 2 && !in_array($verbose, ['SUCCESS', 'ERROR'], true)) {
                return;
            }
        }

        if($label){
            self::$label = $message;
        }

        switch ($message) {
            case '':
                Terminal::newLine();
            break;
            case 'header':
                Terminal::header();
            break;
            case 'flush':
                Terminal::flush($output ? self::$label : null);
            break;
            default:
                if ($color === 'error') {
                    $method = 'error';
                    $color = 'white';
                }

                Terminal::{$method}($message, $color);
            break;
        }
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
        $url = str_starts_with($url, self::$https) 
            ? $url 
            : str_replace($search, self::$https, $url);

        return ($url === self::$https . '/public' || $url === self::$http . '/public') 
            ? self::$https 
            : $url;
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
        return rtrim(preg_replace('#(?<!:)//+#', '/', $url), '/') . '/';
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
     * Check if a URL points to a valid webpage (not a static asset or non-page resource).
     *
     * This method determines whether the given URL represents a valid webpage
     * by verifying that it does not belong to common asset directories (like `/assets`
     * or `/public/assets`) and that its file extension — if present — is one of the
     * recognized webpage types (`.html`, `.php`, `.asp`, etc.).
     *
     * This ensures the sitemap or broken link scanner only processes real pages,
     * not scripts, images, or other non-content files.
     *
     * @param string $url The URL to evaluate.
     *
     * @return bool Return true if the URL is a valid webpage, false otherwise.
     *
     * @example - Example:
     * ```php
     * self::isWebPageUrl('https://example.com/about')             // true
     * self::isWebPageUrl('https://example.com/about.html')        // true
     * self::isWebPageUrl('https://example.com/assets/logo.png')   // false
     * self::isWebPageUrl('https://example.com/script.js')         // false
     * ```
     */
    private static function isWebPageUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if ($path === '') {
            return false;
        }

        $path = ltrim(strtolower($path), '/');

        if (
            !self::$options['ignoreAssets'] && 
            (str_starts_with($path, 'public/assets/') || str_starts_with($path, 'assets/'))
        ) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === '') {
            return true;
        }

        static $allowed = ['html', 'htm', 'php', 'asp', 'aspx', 'jsp'];

        return in_array($ext, $allowed, true);
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
                    self::_print(
                        '[Ignored] ' . self::normalizeUrl($url), 
                        'yellow',
                        verbose: 'ERROR'
                    );
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
     * Normalize and sanitize a given URL to ensure consistent formatting.
     *
     * This method fixes malformed or redundant URL segments and ensures 
     * the returned URL is properly structured and absolute relative to 
     * the application's root. It handles both absolute (HTTP/HTTPS) 
     * and relative (local/public) URLs.
     *
     * Behavior:
     * - Removes duplicate slashes (`//`) except in protocol definitions.
     * - Restores missing protocol slashes if the regex collapses them.
     * - Trims trailing slashes for absolute URLs.
     * - Cleans relative paths such as `../` and `./`.
     * - Resolves paths under `/public` to the base `startUrl()`.
     * - Converts local paths to full URLs relative to the app root.
     *
     * @param string $url The URL or relative path to normalize.
     * 
     * @return string Returns the normalized and absolute URL.
     *
     * @example - Example:
     * ```php
     *  self::normalizeUrl('https:////example.com//path//'); 
     *  // https://example.com/path
     *
     *  self::normalizeUrl('/public/home');
     *  // http://localhost/example.com/public/home
     *
     *  self::normalizeUrl('../home/');
     *  // http://localhost/example.com/public/home
     * ```
     */
    private static function normalizeUrl(string $url): string 
    {
        // Clean duplicate slashes except protocol
        $url = preg_replace('#(?<!:)//+#', '/', $url);

        // Fix protocol if regex collapsed `//`
        if (preg_match('#^https?:/[^/]#i', $url)) {
            $url = preg_replace('#^((?:https?):)/#i', '$1//', $url);
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return rtrim($url, '/');
        }
  
        // Sanitize relative paths (localhost)
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
     * @return array<string,mixed>|bool Return the extracted URLs.
     */
    private static function crawl(string $url, bool $deep = false): array|bool
    {
        if (self::isLimitExceeded()) {
            return $deep ? false : self::$urls;
        }
        
        self::$label = '';
        $url = self::normalizeUrl($url);
        $contents = self::connection($url);
    
        if ($contents === false || $contents === -1) {
            self::$failed[] = $url;

            if ($contents === false) {
                if(self::$label){
                    self::_print('flush', output: true);
                }
                
                self::_print("[Failed] {$url}", 'red', verbose: 'ERROR');
            }
            return $deep ? false : self::$urls;
        }
    
        try{
            $dom = new DOMDocument();
            @$dom->loadHTML($contents['document']);
        }catch(Throwable $e){
            if(self::$label){
                self::_print('flush', output: true);
            }

            self::_print("[Error] {$e->getMessage()}", 'red', verbose: 'ERROR');
            return $deep ? false : self::$urls;
        }

        $links = $dom->getElementsByTagName('a');
        $length = $links->count();
        $found = 1;
        $title = null;

        if(self::$options['isLinkTree']){
            $titles = $dom->getElementsByTagName('title');
            $title = ($titles->length > 0) 
                ? $titles->item(0)->textContent 
                : "No title found.";
        }

        self::$urls[$url] = [
            'link' => $url, 
            'title' => $title, 
            'status' => $contents['status'],
            'lastmod' => $contents['lastmod']
        ];
        self::$counts += 1;
    
        foreach ($links as $link) {
            if (self::isLimitExceeded()) {
                self::$skipped += $length;
                // return self::$urls;
                return $deep ? false : self::$urls;
            }
    
            $href = $link->getAttribute('href');

            if (!self::isAcceptable($href)) {
                continue;
            }

            $href = rtrim(self::normalizeUrl($href), '/');

            if (
                self::isPrefix($href) && 
                filter_var($href, FILTER_VALIDATE_URL) && 
                !isset(self::$urls[$href]) &&
                self::crawl($href, true) !== false
            ) {
                // $children[$href] = $href;
                // self::$urls[$href] = ['link' => $href, 'lastmod' => $contents['lastmod']];
                // self::$counts++;
                $found++;
            }
        }

        self::$skipped += ($length - $found);
        return self::$urls;
    }

    /**
     * Check if the sitemap generation has exceeded its configured limits.
     *
     * This method verifies whether the current crawl has hit the maximum
     * scan count or exceeded the allocated memory threshold. When a limit
     * is reached, it logs the reason for stopping the crawl.
     *
     * @return bool Returns true if a limit was exceeded, otherwise false.
     */
    private static function isLimitExceeded(): bool
    {
        if (self::$config->maxScan > 0 && self::$counts >= self::$config->maxScan) {
            self::_print(
                sprintf('Max scan limit reached: %d', self::$config->maxScan),
                'error',
                verbose: 'DEBUG'
            );
            self::_print('', verbose: 'DEBUG');
            return true;
        }

        if (memory_get_usage(true) >= self::$memoryThreshold) {
            self::_print(
                sprintf('Memory usage exceeded limit: %s', Maths::toUnit(self::$memoryThreshold, withName: true)),
                'error',
                verbose: 'DEBUG'
            );
            self::_print('', verbose: 'DEBUG');
            return true;
        }

        return false;
    }


    /**
     * Novio browser to open a connection to url and extract document body and file-time if header is set.
     * 
     * @param string $url The url to load it contents.
     * 
     * @return array<string,string>|false Return array containing the page content and file-time.
     */
    private static function connection(string $url): array|bool|int
    {
        $url = self::toUrl($url);

        if(!self::isPrefix($url) || in_array($url, self::$visited)){
            return false;
        }

        self::$visited[] = $url;

        if (
            $url === '' ||
            str_starts_with($url, '#') ||
            str_starts_with($url, 'mailto:') ||
            str_starts_with($url, 'tel:') ||
            !self::isWebPageUrl($url)
        ) {
            self::_print("[Invalid] {$url}", 'brightRed', label: true);
            return false;
        }

        self::_print("[Scanning] {$url}", 'cyan', label: true);

        try{
            /**
             * @var \Luminova\Interface\ResponseInterface $response
             */
            $response = Async::await(fn() => (new Novio([
                'file_time' => true,
                'allow_redirects' => true,
                'onBeforeRequest' => fn(string $url, array $headers) => Terminal::watcher(
                    max(1, self::$config->scanSpeed), 
                    fn() => self::_print('flush'),
                    beep: false
                )
            ]))->request('GET', $url));
            
            self::_print('flush', output: true);
            $status = $response->getStatusCode();
            $isSuccessCode = ($status === 200 || ($status >= 300 && $status < 400));
            $isError = false;
            
            if (!$isSuccessCode) {
                $isError = true;
                $label = "[{$status}] Failed {$url}";

                if ($status >= 500) {
                    $label = "[Server Error] Internal sever error {$url}";
                }elseif (self::$options['isBroken'] && in_array($status, [0, 404, 410], true)) {
                    $label = "[{$status}] {$url}";
                }
            }

            if (empty($response->getContents())) {
                $isError = true;
                $label = "[Error] Empty response from {$url}";
            }

            if ($isError) {
                self::_print($label, 'red', verbose: 'ERROR', label: true);
                return false;
            }
            
            self::_print("[Completed] {$url}", verbose: 'SUCCESS', label: true);
            $modified = $response->getFileTime();

            return [
                'document' => $response->getContents(),
                'lastmod' => ($modified != -1) ? date("Y-m-d\TH:i:sP", $modified) : null,
                'status' => $status,
                'link' => $url
            ];
        }catch(Throwable $e){
            self::_print('flush');
            self::_print('flush', output: true);
            self::_print("[Error] {$e->getMessage()}", 'red', verbose: 'ERROR', label: true);
        }

        return -1;
    }
}