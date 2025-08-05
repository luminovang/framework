<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Stringable;
use \App\Config\Browser;
use \Luminova\Interface\LazyObjectInterface;

/**
 * Accessors for parsed user-agent details.
 *
 * Available dynamic getter methods:
 *
 * @method string getBrowser()           Get the browser name (e.g. "Firefox").
 * @method string getVersion()           Get the browser version (e.g. "143.0").
 * @method string getUserAgent()         Get the full User-Agent string.
 * @method string getPlatform()             Get the platform/OS name (e.g. "Macintosh").
 * @method string getPlatformModel()       Get the platform/OS name (e.g. "Macintosh").
 * @method string getOs()                Get the device OS identifier (e.g. "Intel Mac OS X").
 * @method string getOsVersion()         Get the device / OS version (e.g. "10.15").
 * @method string getEngine()            Get the rendering engine (e.g. "Gecko", "Blink").
 * @method string getEngineVersion()     Get the engine version/build (e.g. "20100101").
 * @method array  getAttributes()        Get the matched agent attributes (e.g. ["Xbox", "Xbox Series X"]).
 * @method array  getLanguages()         Get the languages if available (e.g. ["en-US"]).
 * @method string getRobot()             Get the robot/crawler name if detected.
 * @method string getMobile()            Get the mobile device name if detected.
 * @method string getReferrer()          Get the referrer hostname if available.
 */
class UserAgent implements LazyObjectInterface, Stringable
{
    /**
     * Whether the user agent represents a browser.
     *
     * @var bool $isBrowser
     */
    protected bool $isBrowser = false;

    /**
     * Whether the user agent represents a known robot.
     *
     * @var bool $isRobot
     */
    protected bool $isRobot = false;

    /**
     * Whether the user agent represents a mobile device.
     *
     * @var bool $isMobile
     */
    protected bool $isMobile = false;

    /**
     * The platform/operating system name.
     *
     * @var string $platform
     */
    protected string $platform = '';

    /**
     * The platform/operating system name.
     *
     * @var string $platformModel
     */
    protected string $platformModel = '';

    /**
     * The browser name.
     *
     * @var string $browser
     */
    protected string $browser = '';

    /**
     * The browser version.
     *
     * @var string $version
     */
    protected string $version = '';

    /**
     * The mobile device name.
     *
     * @var string $mobile
     */
    protected string $mobile = '';

     /**
     * The device name.
     *
     * @var string $os
     */
    protected string $os = '';

    /**
     * The device languages.
     *
     * @var array<string> $languages
     */
    protected array $languages = [];

    /**
     * The user agent attributes.
     *
     * @var array<string> $attributes
     */
    protected array $attributes = [];

    /**
     * The device engine name.
     *
     * @var string $engine
     */
    protected string $engine = '';

     /**
     * The mobile device engine version.
     *
     * @var string $engineVersion
     */
    protected string $engineVersion = '';

    /**
     * The platform/operating system version.
     *
     * @var string $osVersion
     */
    protected string $osVersion = '';

    /**
     * The name of the robot if it's a known robot.
     *
     * @var string $robot
     */
    protected string $robot = '';

    /**
     * The referral hostname if available.
     *
     * @var string $referrer
     */
    protected string $referrer = '';

    /**
     * Whether the referral hostname is from another site.
     *
     * @var bool $isReferrer
     */
    protected bool $isReferrer = false;

    /**
     * Whether the browser is chromium based.
     *
     * @var bool $isChromium
     */
    protected bool $isChromium = false;

    /**
     * API configuration.
     * 
     * @var Browser $config
     */
    private static ?Browser $config = null;

    /**
     * Device detection patterns.
     * 
     * @var array $devicePatterns
     */
    private static array $devicePatterns = [
        'bot'     => '/compatible;[^)]*?([A-Za-z0-9\-._!]*?(?:bot|slurp|yahoos?lurp|yahoo!? ?slurp|searchbot|chatgpt|crawler|spider|bingbot|googlebot)[A-Za-z0-9\-._!]*)\/?([\d._+]+)?/i',
        'console' => '/\b(PlayStation|Nintendo|Xbox)(?:[\s_]+([A-Za-z0-9]+(?:[\s_][A-Za-z0-9]+)*))?(?:[\s\/_]*([\d._]+))?/i',
        'reader' => '/\b(?:(Kindle)\/([\d._+]+)|(Dalvik|NoteAir[0-9A-Za-z]+)\b(?:\/| Build\/)([\d._+]+))/i',
        'browser' => [
            'Chrome'   => '/Chrome\/([\d._+]+)/i',
            'Firefox'  => '/Firefox\/([\d._+]+)/i',
            'Edge'     => '/Edg\/([\d._*]+)/',
            'Opera'    => '/(?:OPR|Opera)\/([\d._+]+)/i',
            'Vivaldi'  => '/Vivaldi\/([\d._+]+)/i',
            'IE'       => '/MSIE\s([\d._+]+)/i',
            'Firebird' => '/Firebird\/([\d._+]+)/i',
            'Safari'   => '/Version\/([\d._+]+).*Safari\//i',
            'Others'   => '/\b(?!Mozilla)([A-Za-z-_]+)\/([\d._+]+)/i',
            'Mozilla'  => '/Mozilla\/([\d._+]+)/i'
        ],
    ];

    /**
     * Create a new UserAgent instance.
     *
     * Initializes the user agent parser by setting the user agent string and 
     * compiling its properties. If no user agent is provided, it falls back 
     * to `$_SERVER['HTTP_USER_AGENT']`.  
     * Also runs the referral check automatically.
     *
     * @param string|null $useragent Optional user agent string to parse. 
     *                               Defaults to the current HTTP request's user agent if not provided.
     *
     * @return void
     *
     * @example - Example:
     * 
     * ```php
     * // Use client browser from current request
     * $ua = new UserAgent();
     * echo $ua->browser; // e.g. "Chrome"
     *
     * // Use custom user agent string
     * $ua = new UserAgent("PostmanRuntime/7.35.0");
     * echo $ua->browser; // e.g. "PostmanRuntime"
     * ```
     */
    public function __construct(protected ?string $useragent = null)
    {
        $this->useragent ??= self::getDefaultAgent();
        self::$config ??= new Browser();

        $this->replace($this->useragent);
        //$this->isReferral();
    }

    /**
     * Magic method to dynamically access properties.
     *
     * @param string $property The name of the property.
     * 
     * @return mixed The value of the property if exists, otherwise null.
     * @ignore
     */
    public function __get(string $property): mixed
    {
        $property = $this->parsePropertyName($property);

        return $this->{$property} ?? null;
    }

    /**
     * Magic method to dynamically call methods.
     *
     * @param string $name The name of the method.
     * @param array $arguments The arguments passed to the method.
     * 
     * @return mixed Return the result of the method call or null if method does not exist.
     * @ignore
     */
    public function __call(string $name, mixed $arguments): mixed
    {
        $method = $this->parsePropertyName(strtolower(substr($name, 3)));

        return $this->{$method} ?? null;
    }

    /**
     * Magic method: return the full User Agent string when cast to string.
     *
     * This allows you to use the object directly in string context, e.g. `echo $ua;`.
     *
     * @return string Return the full User Agent string, or an empty string if not set.
     */
    public function __toString(): string
    {
        return $this->useragent ?? '';
    }

    /**
     * Get browser language if available.
     *
     * @param int $index Return only the preferred index in languages (default: 0).
     * 
     * @return string Return language (e.g. "en-US").
     */
    public function getLanguage(int $index = 0): ?string
    {
        return $this->languages[$index] ?? null;
    }

    /**
     * Get the full User Agent string explicitly.
     *
     * Use this method if you prefer a clear function call instead of relying on
     * PHP's magic `__toString()` behavior.
     *
     * @return string Return the full User Agent string, or an empty string if not set.
     *
     * @example - Example:
     * ```php
     * $ua = new UserAgent();
     * echo $ua->toString();   // Explicit
     * echo $ua;              // Implicit (calls __toString)
     * ```
     */
    public function toString(): string
    {
        return $this->useragent ?? '';
    }

    /**
     * Convert parsed user agent details into an array.
     *
     * @return array<string, mixed> Associative array of user agent details.
     */
    public function toArray(): array
    {
        return [
            'userAgent'      => $this->useragent,
            'browser'        => $this->browser,
            'version'        => $this->version,
            'os'             => $this->os,
            'osVersion'      => $this->osVersion,
            'platform'       => $this->platform,
            'platformModel'  => $this->platformModel,
            'engine'         => $this->engine,
            'engineVersion'  => $this->engineVersion,
            'attributes'     => $this->attributes,
            'languages'      => $this->languages,
            'isChromium'     => $this->isChromium,
            'isBrowser'      => $this->isBrowser(),
            'isRobot'        => $this->isRobot(),
            'isMobile'       => $this->isMobile(),
            'isReferral'     => $this->isReferral()
        ];
    }

    /**
     * Replace the current user agent properties with new values from a given string.
     *
     * Parses the provided user agent string and updates the class properties
     * (`browser`, `version`, `platform`, `osVersion`, etc.) with the new values.  
     * If parsing fails, all user agent properties are reset.
     *
     * @param string $userAgent The raw user agent string to parse and apply.
     *
     * @return self Return instance of user agent class.
     * @see UserAgent::parse() For details on parsing user agent string.
     * 
     * @example - Example:
     * 
     * ```php
     * $ua = new UserAgent();
     * $ua->replace("Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0 Safari/537.36");
     * echo $ua->browser; // "Chrome"
     * ```
     */
    public function replace(string $userAgent): self
    {
        $agent = self::parse($userAgent, false);
        $this->useragent = $userAgent;

        if($agent === false){
            $this->reset();
            return $this;
        }

        $this->isChromium = $agent->isChromium;
        $this->isBrowser = $agent->isBrowser;
        $this->platform = $agent->platform;
        $this->platformModel = $agent->platformModel;
        $this->browser = $agent->browser;
        $this->version = $agent->version;
        $this->os = $agent->os;
        $this->osVersion = $agent->osVersion;
        $this->engine = $agent->engine;
        $this->engineVersion = $agent->engineVersion;
        $this->attributes = $agent->attributes;
        $this->languages = $agent->languages;
        $this->isRobot();
        $this->isMobile();
        return $this;
    }

    /**
     * Check if the User Agent string matches a given keyword or regex pattern.
     *
     * Unlike {@see is()}, this method always checks the raw User Agent string, 
     * ignoring parsed properties such as `browser`, `mobile`, or `robot`.
     * 
     * Behavior:
     * - If `$pattern` looks like a regex (e.g, delimited with `/`), it is used directly.
     * - Otherwise, the method builds a case-insensitive regex:
     *   - If `$asGroup` is true (or the pattern contains `|`), the pattern is wrapped in a non-capturing group `(?:...)`.
     *   - If `$wordBoundary` is true, the pattern is bounded with `\b`.
     * - If regex evaluation fails (e.g., malformed pattern), it falls back to a simple
     *   case-insensitive substring check with `str_contains`.
     *
     * @param string $pattern The keyword or regex pattern to match.
     * @param bool $asGroup Whether to wrap the pattern in a non-capturing group `(?:...)`.
     * @param bool $wordBoundary Whether to enforce word boundaries when `$pattern` is not a regex.
     * 
     * @return bool Return true if the User Agent matches the given pattern, false otherwise.
     * 
     * @see is()
     * 
     * @example - Example:
     * ```php
     * $ua = new UserAgent("Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
     *
     * $ua->match("Windows");                       // true (substring)
     * $ua->match("mobile|android", asGroup: true); // true if any token matches
     * $ua->match("Windows", wordBoundary: true);   // true (word boundary match)
     * $ua->match("Win");                           // true (substring match)
     * $ua->match("Win", wordBoundary: true);       // false (no full word "Win")
     * $ua->match("/Win[dD]ows/");                  // true (regex match)
     * $ua->match("Linux");                         // false
     * ```
     */
    public function match(string $pattern, bool $asGroup = false, bool $wordBoundary = false): bool
    {
        if (!$pattern || !$this->useragent) {
            return false;
        }

        if (preg_match('/^\/.*\/[imsxADSUXJu]*$/', $pattern)) {
            $regex = $pattern;
        } else {
            $expr = ($asGroup || str_contains($pattern, '|')) ? "(?:{$pattern})" : $pattern;
            $expr = $wordBoundary ? "\\b{$expr}\\b" : $expr;
            $regex = "/{$expr}/i";
        }

        if ((bool) @preg_match($regex, $this->useragent)) {
            return true;
        }

        return str_contains(strtolower($this->useragent), strtolower($pattern));
    }

    /**
     * Check if a keyword or regex pattern matches either the full User Agent string 
     * or a specific parsed property (browser, mobile, or robot).
     *
     * Unlike {@see match()}, this method can scope the check to a parsed property 
     * rather than always checking the raw User Agent string.
     * 
     * Behavior:
     * - If `$context` is `null`, `$pattern` is tested against the full UA string.
     * - If `$context` is set (`browser`, `mobile`, or `robot`), a case-insensitive 
     *   substring match is performed against that property.
     * - `$pattern` may be a plain keyword or a regex pattern.
     * 
     * @param string $pattern The keyword or regex pattern to test.
     * @param string|null $property The property to check (`browser`, `platform`, or `os`).
     *                             If null, the raw User Agent string is checked.
     * 
     * @return bool Return true if a match is found, false otherwise.
     * 
     * @see match() For more advance matching.
     * 
     * @example - Example:
     * ```php
     * $ua = new UserAgent($_SERVER['HTTP_USER_AGENT']);
     * 
     * // Check UA string directly
     * $ua->is('Chrome');                  // true/false
     * $ua->is('Macintosh|Mac OS X');      // true/false (regex)
     * 
     * // Check specific properties
     * $ua->is('Firefox', 'browser');      // true/false
     * $ua->is('iPhone', 'mobile');        // true/false
     * $ua->is('Googlebot', 'robot');      // true/false
     * ```
     */
    public function is(string $pattern, ?string $property = null): bool 
    {
        $search = $this->useragent;
        $pattern = preg_replace('/(^\/|\/$|\/[imsxADSUXJu]*)/', '', $pattern);

        if($property){
            $property = $this->parsePropertyName($property);
            $search = $this->{$property} ?? null;

            if($search && is_array($search)){
                $search = implode('; ', $search);
            }
        }

        if(!$search || !$pattern){
            return false;
        }

        if((bool) @preg_match('/' . $pattern . '/i', $search)){
            return true;
        }

        return str_contains(strtolower($search), strtolower($pattern));
    }

    /**
     * Check if the current request was referred from another site.
     *
     * A request is considered a referral if `HTTP_REFERER` exists
     * and its hostname is different from the application hostname.
     *
     * @param string|null $hostname Optional hostname to check against instead of the app's hostname.
     * @param bool $includeSubdomain If true, subdomains are treated as different sites.
     * 
     * @return bool Return true if the referral is from an external site, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($ua->isReferral()) {
     *     echo "Visitor came from another website.";
     * }
     * 
     * if ($ua->isReferral('example.com')) {
     *     echo "Visitor came from example.com.";
     * }
     * ```
     */
    public function isReferral(?string $hostname = null, bool $includeSubdomain = false): bool
    {
        $this->referrer = trim($_SERVER['HTTP_REFERER'] ?? '');

        if ($this->referrer === '') {
            return $this->isReferrer = false;
        }

        $refHost = parse_url($this->referrer, PHP_URL_HOST);

        if (!$refHost) {
            return $this->isReferrer = false;
        }

        $targetHost = $hostname 
            ? (parse_url($hostname, PHP_URL_HOST) ?: $hostname) 
            : APP_HOSTNAME;

        if (!$includeSubdomain) {
            $refHost = $this->getBaseDomain($refHost);
            $targetHost = $this->getBaseDomain($targetHost);
        }

        $this->isReferrer = strcasecmp($refHost, $targetHost) !== 0;
        return $this->isReferrer;
    }

    /**
     * Check if the browser is Chromium-based.
     *
     * This flag is true for browsers built on the Chromium project 
     * (e.g. Chrome, Edge, Brave, Opera, Vivaldi), which all use the Blink 
     * rendering engine. 
     *
     * The value is determined during User Agent parsing, based on a 
     * combination of the engine and browser identifiers.
     *
     * @return bool Return true if the current browser is Chromium-based, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($ua->isChromium()) {
     *     echo "This browser is Chromium-based.";
     * }
     * ```
     */
    public function isChromium(): bool
    {
        return $this->isChromium;
    }

    /**
     * Check if the user agent string belongs to a known robot (crawler, bot, or spider).
     *
     * @param string|null $keyword Optional robot name, keyword, or regex pattern.
     *   - If `null`, it checks against the predefined list in `self::$config->robotPatterns`.
     *
     * @return bool Return true if the user agent is recognized as a robot, false otherwise.
     *
     * @example - Example:
     * 
     * ```php
     * if ($ua->isRobot()) {
     *     echo "Request is from a known bot.";
     * }
     *
     * if ($ua->isRobot('Googlebot')) {
     *     echo "Specifically from Googlebot.";
     * }
     * ```
     */
    public function isRobot(?string $keyword = null): bool 
    {
        if($keyword !== null){
            return $this->is($keyword, 'robot') 
                || $this->is($keyword);
        }

        if($this->isRobot){
            return true;
        }

        foreach (self::$config->robotPatterns as $pattern => $name) {
            if($this->match($pattern)){
                $this->isRobot = true;
                $this->robot = $name;

                return true;
            }
        }

        $this->isRobot = $this->platform === 'bot';
        $this->robot = 'unknown';

        return $this->isRobot;
    }

    /**
     * Check if the user agent string represents a mobile device.
     *
     * @param string|null $keyword Optional mobile device name, keyword, or regex pattern.
     *   - If `null`, it checks against the predefined list in `self::$config->mobileKeywords`.
     *
     * @return bool Return true if the user agent matches a mobile device, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($ua->isMobile()) {
     *     echo "User is browsing from a mobile device.";
     * }
     *
     * if ($ua->isMobile('iPhone')) {
     *     echo "User is on an iPhone.";
     * }
     * ```
     */
    public function isMobile(?string $keyword = null): bool 
    {
        if($keyword !== null){
            return $this->is($keyword, 'mobile') 
                || $this->is($keyword, 'platform')
                || $this->is($keyword);
        }

        if($this->isMobile){
            return true;
        }

        foreach (self::$config->mobileKeywords as $pattern => $name) {
            if (str_contains(strtolower($this->useragent), strtolower($pattern))) {
                $this->isMobile = true;
                $this->mobile = $name;
                return true;
            }
        }

        $this->isMobile = $this->match('mobile|android|tablet|ipad|ipod');
        $this->mobile = 'unknown';

        return $this->isMobile;
    }

    /**
     * Check if the user agent string belongs to a specific browser.
     *
     * @param string|null $name Optional browser name, keyword, or regex pattern.
     *   - If `null`, it checks if the user agent is any valid browser.
     *
     * @return bool Return true if the user agent matches a browser, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($ua->isBrowser()) {
     *     echo "User is on a browser.";
     * }
     *
     * if ($ua->isBrowser('Chrome')) {
     *     echo "User is specifically using Chrome.";
     * }
     * ```
     */
    public function isBrowser(?string $name = null): bool
    {
        if($name !== null){
            return $this->is($name, 'browser') || $this->is($name);
        }

        if (!$this->isBrowser) {
            return false;
        }

        return $this->isBrowser = $this->platform !== 'bot' && (
            ($this->browser && $this->browser !== 'unknown') || 
            ($this->engine && $this->engine !== 'unknown')
        );
    }

    /**
     * Check if the user agent string is trusted based on allowed browsers.
     *
     * Trusted browsers are defined in `self::$config->browsers`. If no browsers
     * are configured, all user agents are considered trusted.
     *
     * @return bool Return true if the user agent is trusted, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($ua->isTrusted()) {
     *     echo "This user agent is allowed.";
     * } else {
     *     echo "Blocked: untrusted browser.";
     * }
     * ```
     */
    public function isTrusted(): bool
    {
        if ($this->useragent === '' || $this->useragent === '0') {
            return false;
        }

        if (self::$config->browsers === []) {
            return true;
        }

        if(isset(self::$config->browsers[$this->browser])){
            return true;
        }

        foreach(self::$config->browsers as $agent){
            if($this->is($agent)){
                return true;
            }
        }

        return false;
    }

    /**
     * Parse a user agent string into structured client information.
     *
     * This method analyzes a user agent string and extracts detailed information, including:
     * - Browser name and version
     * - Rendering engine and engine version
     * - Operating system and version
     * - Platform type (desktop, mobile, tablet, tv, watch, bot)
     * - Device or platform model (e.g., iPad, Apple Watch, Chromecast, Smart TV)
     * - Preferred languages
     * - Whether the browser is Chromium-based
     *
     * If no string is provided, the method uses the current request's `HTTP_USER_AGENT` header.
     *
     * @param string|null $userAgent Optional user agent string (default: `$_SERVER['HTTP_USER_AGENT']`).
     * @param bool $returnArray When true, return the result as an associative array; otherwise return as an object.
     *
     * @return false|array<string,string|bool>|object{
     *     isBrowser: bool,
     *     isChromium: bool,
     *     userAgent: string,
     *     browser: string,
     *     version: string,
     *     engine: string,
     *     engineVersion: string,
     *     platform: string,
     *     platformModel: string,
     *     os: string,
     *     osVersion: string,
     *     languages: array
     * } Return parsed client information on success, or `false` if the string is empty or unrecognized.
     *
     * @see replace() For replacing class object with new agent information.
     *
     * @example - Example:
     * ```php
     * $info = UserAgent::parse();
     * // object with browser, version, engine, OS, platform, etc.
     *
     * $info = UserAgent::parse('Mozilla/5.0 (Linux; U; Android 4.0.3; en-in; SonyEricssonMT11i' .
     * ' Build/4.1.A.0.562) AppleWebKit/534.30 (KHTML, like Gecko)' .
     * ' Version/4.0 Mobile Safari/534.30'); 
     * ```
     */
    public static function parse(?string $userAgent = null, bool $returnArray = false): array|object|bool
    {
        $userAgent ??= self::getDefaultAgent();

        if ($userAgent === '') {
            return false;
        }

        $version = '';
        $platform = null;
        $browser = 'unknown';
        $engine = 'unknown';

        foreach (self::$devicePatterns as $context => $patterns) {
            if($platform){
                break;
            }

            $patterns = is_array($patterns) ? $patterns : [$context => $patterns];

            foreach ($patterns as $name => $pattern) {
                if(($m = self::matchBrowser($userAgent, $name, $pattern)) !== null){
                    $platform = $context;
                    [$version, $browser] = $m;
                    break;
                }
            }
        }

        [$engine, $engineVersion, $isChromium] = self::matchEngine($userAgent, $browser, $version);
        [$os, $osVersion, $platformModel, $languages, $attr] = self::matchAttributes($userAgent, $platform);

        return self::extract([
            $userAgent, 
            $browser, 
            $version,
            $platform ?: 'bot',
            $platformModel,
            $os,
            $osVersion,
            $engine,
            $engineVersion,
            $languages,
            $isChromium,
            $attr
        ], $returnArray);
    }

    /**
     * Build a structured user agent result from regex matches.
     *
     * Converts raw regex match data into a normalized user agent record,
     * including browser name, version, and platform details.
     *
     * @param array $matches Regex matches containing user agent components:
     *   - [0] full user agent string
     *   - [1] browser name
     *   - [2] browser version
     *   - [3] platform name
     *   - [4] platform model
     * @param bool $returnArray When true, return result as an array, otherwise return as an object.
     *
     * @return array<string,mixed>|object<string,mixed> Return a normalized userAgent information as array or object.
     */
    private static function extract(array $matches, bool $returnArray = false): array|object
    {
        $info = [
            'userAgent'      => $matches[0] ?? '',
            'browser'        => $matches[1] ?? 'unknown',
            'version'        => str_replace('_', '.', $matches[2] ?? ''),
            'platform'       => $matches[3],
            'platformModel'  => $matches[4] ?? '',
            'os'             => $matches[5] ?? '',
            'osVersion'      => str_replace('_', '.', $matches[6] ?? ''),
            'engine'         => $matches[7] ?? 'unknown',
            'engineVersion'  => str_replace('_', '.', $matches[8] ?? ''),
            'languages'      => [],
            'attributes'     => array_map(fn($item) => trim($item), $matches[11] ?? []),
            'isBrowser'      => false,
            'isChromium'     => $matches[10] ?? false
        ];

        $languages = $matches[9] ?? null;
        if($languages){
            $info['languages'] = array_map(
                fn($l) => preg_replace('/;q=[\d.]+/', '', trim($l)), 
                explode(',', $languages)
            );
        }

        $info['isBrowser'] = $matches[3] !== 'bot' && (
            ($info['browser'] && $info['browser'] !== 'unknown') || 
            ($info['engine'] && $info['engine'] !== 'unknown')
        );

        return $returnArray ? $info : (object) $info;
    }

    /**
     * Extracts the base domain (e.g. example.com from blog.example.com).
     * 
     * @param string $host The hostname to extract base domain.
     * 
     * @return string Return base domain.
     */
    private function getBaseDomain(string $host): string
    {
        $parts = explode('.', $host);
        $count = count($parts);

        if ($count >= 2) {
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }
        return $host; 
    }

    /**
     * Get default request user agent string.
     * 
     * @return string Return agent string.
     */
    private static function getDefaultAgent(): string
    {
        return trim($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    /**
     * Normalize a property name to a consistent format.
     *
     * This method converts known variations of property names into 
     * a standardized form for internal use:
     * 
     * - "os_version" or "osversion" → "osVersion"
     * - "userAgent" → "useragent"
     * - Any other input is returned unchanged.
     *
     * @param string $property The property name to normalize.
     * 
     * @return string Return the normalized property name.
     */
    private function parsePropertyName(string $property): string 
    {
        if ($property === 'userAgent' || $property === 'user_agent') {
            return 'useragent';
        }

        if (
            $property === 'browserversion' ||
            $property === 'browserVersion' ||
            $property === 'browser_version'
        ) {
            return 'version';
        }

        if($property === 'platformmodel' || $property === 'platform_model'){
           return 'platformModel';
        }

        if ($property === 'osversion' || $property === 'os_version') {
            return 'osVersion';
        }

        if ($property === 'engineversion' || $property === 'engine_version') {
            return 'engineVersion';
        }

        return $property;
    }

    /**
     * Match and extract operating system, version, platform type, model, and language
     * information from a User-Agent string.
     *
     * @param string $ua User-Agent string to analyze.
     * 
     * @return array Return array containing matched [$os, $osVersion, $platform, $platformModel, $languages]
     */
    private static function matchAttributes(string $ua, ?string &$platform) :array
    {
        $os = null;
        $osVersion = null;
        $platformModel = null;
        $languages = '';
        $parts = [];

        if (preg_match('/\((.*?)\)/', $ua, $m)) {
            $parts = explode(';', $m[1]);
            $model = trim($parts[0] ?? '');

            foreach ($parts as $p) {

                if ($os && $osVersion && $platformModel && $platform) {
                    break;
                }

                $p = trim($p);

                if (str_starts_with($p, 'rv:')) {
                    continue;
                }

                if (preg_match('/^[a-z]{2}(?:-[a-zA-Z]{2})?$/', $p)) {
                    $languages = ($languages === '') ? $p : $languages . ',' . $p;
                    continue;
                }

                if(self::isMacOs($p, $osm)){
                    $platformModel = $osm['model'];
                    $osVersion ??= $osm['version'];
                    $os ??= 'macOS';
                    $platform = 'desktop';
                    continue;
                }

                if (preg_match('/\b(Windows|Win)(?:\s+([A-Za-z]+))?(?:[\s_]*([\d._]+))?/i', $p, $osm)) {
                    $os ??= 'Windows';
                    $platform ??= (stripos($p, 'Phone') !== false) ? 'mobile' : 'desktop';
                    $osVersion ??= ($osm[3] ?? null) ?: ($osm[2] ?? null);

                    $platformModel ??= !empty($osm[2]) 
                        ? "Windows {$osm[2]}" 
                        : (!empty($osm[3]) ? "{$osm[1]}{$osm[3]}" : "Windows NT {$osVersion}");
                    continue;
                }
                
                if (preg_match('/Android[\s_]*([\d._]+)/i', $p, $osm)) {
                    $os ??= 'Android';
                    $osVersion ??= $osm[1] ?? null;
                    $platform ??= 'mobile';
                    continue;
                }

                if (preg_match('/(?:CPU )?(?:iPhone|iPad|iPod).*OS[\s_]*([\d._]+)/i', $p, $osm)) {
                    $os ??= 'iOS';
                    $osVersion ??= $osm[1] ?? null;
                    $platform ??= (stripos($p, 'iPad') !== false) ? 'tablet' : 'mobile';
                    continue;
                }

                if (preg_match('/(WatchOS|Apple Watch)/i', $p)) {
                    $os ??= 'watchOS';
                    $platform ??= 'watch';
                    $platformModel ??= 'Apple Watch';
                    continue;
                }

                if (preg_match('/Android Wear|Wear ?OS/i', $p)) {
                    $os ??= 'WearOS';
                    $platform ??= 'watch';
                    continue;
                }

                if (preg_match('/Tizen.*SM-R/i', $p)) {
                    $os ??= 'Tizen';
                    $platform ??= 'watch';
                    $platformModel ??= 'Samsung Galaxy Watch';
                    continue;
                }

                if (preg_match('/AppleTV/i', $p)) {
                    $os ??= 'tvOS';
                    $platform ??= 'tv';
                    $platformModel ??= 'Apple TV';
                    continue;
                }

                if (preg_match('/(SmartTV|HbbTV|NetCast|Tizen|Web0S|AndroidTV|CrKey)/i', $p)) {
                    $platform ??= 'tv';
                    $platformModel ??= (stripos($p, 'TV') !== false) 
                        ? 'Smart TV' 
                        : 'Chromecast';
                    continue;
                }

                if (preg_match('/\bLinux(?:\s+([a-z0-9._+-]+))?/i', $p, $l)) {
                    $os ??= 'Linux';
                    $platform ??= 'desktop';
                    $osVersion ??= $l[1] ?? null;

                    if (
                        !$platformModel && 
                        preg_match('/\b(Ubuntu|CentOs|Kali|Debian|Fedora|Red Hat|SUSE|Mint|Gecko)\b/i', $ua, $d)
                    ) {
                        $platformModel = $d[1];
                    }
                    continue;
                }
                
                if (!$platform && preg_match('/bot|slurp|searchbot|chatgpt|crawler|crawl|spider|bingbot/i', $p)) {
                    $platform ??= 'bot';
                }

                if (!$os && $p !== $model) {
                    $os = $p;
                }
            }

            $platformModel ??= $model;
        }

        if ($languages === '') {
            $languages = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

            if (preg_match('/;\s([a-z]{2}(?:-[a-zA-Z]{2})?)\)/', $ua, $m)) {
                $languages = $m[1];
            }
        }

        return [$os ?: 'unknown', (string) $osVersion, (string) $platformModel, $languages, $parts];
    }


    /**
     * Detect whether a given user agent string belongs to macOS and extract details.
     *
     * If a match is found, the $matches array will be populated with:
     *   - 'model'   => The hardware model (normalized to "Macintosh" if not specified).
     *   - 'version' => The extracted macOS version (e.g. "10.15.7"), or null if missing.
     *
     * @param string $p        The User-Agent string or substring to check.
     * @param array|null $matches Reference variable to receive extracted values 
     *                            when a match is found. Defaults to an empty array.
     *
     * @return bool True if the string matches a macOS User-Agent format, false otherwise.
     */
    private static function isMacOs(string $p, ?array &$matches = []): bool 
    {
        if (preg_match('/((?:(Intel)\s+)?(?:Mac OS X|OS X|macOS))[\s_]*([\d._]+)/i', $p, $m)) {
            $matches = [
                'model'   => ($m[1] ?? null) ?: 'Macintosh',
                'version' => ($m[2] ?? null)
            ];
            return true;
        }

        if (preg_match('/(Macintosh)?;?\s*(?:Intel\s+)?(Mac OS X|OS X|macOS)[\s_]*([\d._]+)/i', $p, $m)) {
            $matches = [
                'model'   => ($m[2] ?? $m[1]) ?: 'Macintosh',
                'version' => $m[3] ?? null,
            ];
            return true;
        }

        return false;
    }


    /**
     * Match and extract the rendering engine name and version from a User-Agent string.
     *
     * Detects Gecko, AppleWebKit, WebKit, Trident, Presto, etc.
     * If Chromium-based browsers (Chrome, Edge, Opera, Brave, Vivaldi) are found, 
     * detects Blink and flags Chromium engines unless overridden by known exceptions.
     *
     * @param string $ua User-Agent string to analyze.
     * @param string $browser The browser name matched earlier.
     * @param string $version The browser version matched earlier.
     * 
     * @return array Return array containing matched [$engine, $engineVersion, $isChromium]
     */
    private static function matchEngine(string $ua, string $browser, string $version): array
    {
        $engine = 'unknown';
        $engineVersion = '';
        $isChromium = false;
        $pattern = '/\b((NoteAir[0-9A-Za-z]+)(?:\s*Build)?|AppleWebKit|Netscape|WebKit|AndroidWebkit|Trident|Presto|Gecko)\/([\d._+\-]+)/i';

        if (preg_match($pattern, $ua, $m)) {
            $isReader = (stripos($m[0], 'NoteAir') !== false);
            $engine = $isReader ? $m[2] : $m[1];
            $engineVersion = $isReader ? ($m[3] ?: $m[4]) : ($m[2] ?: $m[3]);

            // Blink piggybacks Chrome/Edge
            if(in_array($browser, ['Chrome', 'Edge', 'Opera', 'Brave', 'Vivaldi'], true)){
                if ($engine === 'AppleWebKit') {
                    $isChromium = !preg_match('/(UCBrowser|SamsungBrowser|PhantomJS)/i', $ua);
                }elseif($engine === 'WebKit' && ($browser === 'Chrome' || $browser === 'Edge')){
                    $engine = 'Blink';
                    $engineVersion = $version ?: $engineVersion;
                }
            }
        }

       return [$engine, $engineVersion, $isChromium];
    }

    /**
     * Match a user agent string against a given browser/device pattern.
     *
     * This method applies a regex pattern to a user agent string, then extracts
     * the browser/device name and version based on the provided $name category.
     *
     * @param string $ua  The full user agent string.
     * @param string $name  A logical name for the pattern (e.g., "bot", "console", "reader").
     * @param string $pattern The regex pattern to use for matching.
     *
     * @return array|null Returns an array with [version, browser] if matched, or null if no match.
     */
    private static function matchBrowser(string $ua, string $name, string $pattern): ?array
    {
        if (!preg_match($pattern, $ua, $m)) {
            return null;
        }

        $browser = 'unknown';
        $version = '';

        switch (strtolower($name)) {
            case 'bot':
                $browser = trim($m[1] ?? '');
                $version = $m[2] ?? '';
                $token   = $version ? '/' . $version : '';

                if (preg_match('/^([A-Za-z0-9\-\._!]+)\/([\d._]+)$/i', $browser . $token, $bm)) {
                    $browser = $bm[1];
                    $version = $bm[2];
                }
                break;

            case 'console':
            case 'reader':
                $isExtended = !empty($m[4]);
                $browser    = trim($isExtended ? $m[3] : ($m[1] ?? ''));
                $version    = $isExtended ? ($m[4] ?? $m[5] ?? '') : ($m[2] ?? '');
                break;

            case 'others':
                $browser = trim($m[1] ?? '');
                $version = $m[2] ?? '';
                break;

            default:
                $browser = $name;
                $version = ($name === 'Opera')
                    ? ($m[2] ?? $m[1] ?? '')
                    : ($m[1] ?? '');
        }

        return [$version, $browser];
    }
    
    /**
     * Reset user agent information.
     * 
     * @return void
     * @ignore
     */
    protected function reset(): void 
    {
        $this->isBrowser = false;
        $this->isRobot = false;
        $this->isMobile = false;
        $this->isChromium = false;
        $this->browser = 'unknown';
        $this->version = '';
        $this->mobile = '';
        $this->robot = '';
        $this->platform = 'bot';
        $this->platformModel = '';
        $this->os = '';
        $this->engine = 'unknown';
        $this->languages = [];
        $this->attributes = [];
        $this->engineVersion ='';
        $this->osVersion = '';
    }
}