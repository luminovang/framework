<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Http;

use \App\Controllers\Config\Browser;

/**
 * Getter method for retrieving the browser information.
 * 
 * @method string getBrowser() Get the browser information.
 * @method string getUserAgent() Get the user agent string.
 * @method string getPlatform() Get the platform/operating system information.
 * @method string getVersion() Get the browser version.
 * @method string getRobot() Get the robot name, if the user agent is from a known robot.
 * @method string getMobile() Get the mobile device name, if the user agent represents a mobile device.
 * @method string getReferrer() Get the referrer hostname, if available.
 * @method string getPlatformVersion() Get the platform/operating system version.
 */
class UserAgent
{
    /**
     * The full User Agent string.
     *
     * @var string $useragent
     */
    protected string $useragent = '';

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
     * @var string 4mobile
     */
    protected string $mobile = '';

    /**
     * The platform/operating system version.
     *
     * @var string $platformversion
     */
    protected string $platformversion = '';

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
     * API configuration.
     * 
     * @var Browser $config
    */
    private static ?Browser $config = null;

    /**
     * Constructor
     *
     * Sets the User Agent and runs the compilation routine.
     *
     * @param string|null $useragent The User Agent string. If not provided, it defaults to $_SERVER['HTTP_USER_AGENT'].
     */
    public function __construct(?string $useragent = null)
    {
        $useragent ??= trim($_SERVER['HTTP_USER_AGENT']??'');
        self::$config ??= new Browser();
        $this->replace($useragent);
        $this->isReferral();
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
        return $this->{$property} ?? null;
    }

    /**
     * Magic method to dynamically call methods.
     *
     * @param string $name The name of the method.
     * @param array $arguments The arguments passed to the method.
     * 
     * @return mixed The result of the method call or null if method does not exist.
     * @ignore
     */
    public function __call(string $name, mixed $arguments): mixed
    {
        $method = strtolower(substr($name, 3));

        return $this->{$method} ?? null;
    }

    /**
     * Get the Agent String.
     * 
     * @return string The Agent String.
     */
    public function toString(): string
    {
        return $this->useragent ?? '';
    }

    /**
     * Get the Agent String.
     * 
     * @return string The Agent String.
     */
    public function __toString(): string
    {
        return $this->useragent ?? '';
    }

    /**
     * Parse the user agent string and extract browser information.
     *
     * This method parses the user agent string and extracts information such as browser name, version,
     * operating system, and platform.
     *
     * @param string|null $userAgent The user agent string to parse. If not provided, it defaults to the
     *                                user agent string from the HTTP headers.
     * @param bool $return_array      If set to true, this function will return an array instead of an object.
     * 
     * @return array|object|false Returns an array or object containing the parsed browser information.
     */
    public static function parse(?string $userAgent = null, bool $return_array = false): array|object|bool
    {
        $userAgent ??= trim($_SERVER['HTTP_USER_AGENT']??'');
       
        if (!empty($userAgent)) {
            if (preg_match('/^(.*?)\/([\d.]+) \(([^;]+); ([^;]+); ([^)]+)\) (.+)$/', $userAgent, $matches)) {
                return self::extract($matches, $return_array, true);
            }

            // Maybe PostMan or other API tools
            if (preg_match('/^([^\/]+)\/([\d.]+)$/i', $userAgent, $matches)) {
                return self::extract($matches, $return_array);
            }
        }

        return false;
    }

    /**
     * Parse and replace user agent class properties with new user agent information.
     * 
     * @param string $userAgent The user agent string to parse and expose.
     * 
     * @return void
     */
    public function replace(string $userAgent): void
    {
        $agent = static::parse($userAgent, false);
        $this->useragent = $userAgent;

        if($agent !== false){
            $this->isBrowser = $agent->isBrowser;
            $this->platform = $agent->platform;
            $this->version = $agent->version;
            $this->browser = $agent->browser;
            $this->platformversion = $agent->platform_version;
            $this->isRobot();
            $this->isMobile();
            return;
        }

        $this->reset();
    }

    /**
     * Check if the referral hostname is from another site.
     * 
     * @return bool Return true if the referral hostname is from another site.
     */
    public function isReferral(): bool
    {
        if($this->referrer === ''){
            if (isset($_SERVER['HTTP_REFERER'])) {
                $this->referrer = trim($_SERVER['HTTP_REFERER']);

                $hostname = @parse_url($this->referrer, PHP_URL_HOST);
                $this->isReferrer = ($hostname && $hostname !== APP_HOSTNAME);

                return $this->isReferrer;
            } 

            $this->isReferrer = false;
            $this->referrer = '';
            return false;
        }

        return $this->isReferrer;
    }

    /**
     * Check if the user agent string is from a known robot.
     * 
     * @param string|null $keyword Optional robot name, keyword or pattern.
     * - Pass `NULL` to check if robot is in array of robot keywards `self::$config->robotPatterns`.
     * 
     * @return bool True if the user agent is from known robot, false otherwise.
    */
    public function isRobot(?string $keyword = null): bool 
    {
        if($keyword === null){
            foreach (self::$config->robotPatterns as $pattern => $name) {
                if($this->is($pattern)){
                    $this->isRobot = true;
                    $this->robot = $name;

                    return true;
                }
            }

            $this->isRobot = false;
            $this->robot = '';

            return false;
        }

        if(stripos($this->robot, $keyword) !== false){
            return true;
        }

        return $this->is($keyword);
    }

    /**
     * Check if the user agent string represents a mobile device.
     * 
     * @param string|null $keyword Optional mobile device name, keyword or pattern.
     *  - Pass `NULL` to check if mobile is in array of mobile devices `self::$config->mobileKeywords`.
     * 
     * @return bool True if the user agent represents a mobile device, false otherwise.
    */
    public function isMobile(?string $keyword = null): bool 
    {
        if($keyword === null){
            foreach (self::$config->mobileKeywords as $pattern => $name) {
                if (stripos($this->useragent, $pattern) !== false) {
                    $this->isMobile = true;
                    $this->mobile = $name;
                    return true;
                }
            }

            $this->isMobile = false;
            $this->mobile = '';

            return false;
        }

        if(stripos($this->mobile, $keyword) !== false){
            return true;
        }

        return $this->is($keyword);
    }

    /**
     * Check if the user agent string belongs to a specific browser.
     *
     * @param string|null $name Optional browser name, keyword or pattern.
     *   If `NULL` is passed it will check if the user agent is any valid browser.
     * 
     * @return bool Return true if the user agent belongs to a specific browser, or if the given name matches the browser name or user-agent, false otherwise.
    */
    public function isBrowser(?string $name = null): bool
    {
        if (!$this->isBrowser || $this->browser === '') {
            return false;
        }

        if ($name === null) {
            return true;
        }

        if(stripos($this->browser, $name) !== false){
            return true;
        }

        return $this->is($name);
    }

    /**
     * Check if the user agent string is trusted based on allowed browsers.
     * 
     * @return bool Return true if the user agent matches any of the browser name / patterns in allowed browsers, false otherwise.
    */
    public function isTrusted(): bool
    {
        if (!$this->useragent) {
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
     * Check if keyword or patterns matched with the user agent string, browser, mobile or robot name.
     * 
     * @param string $name The keyward or pattern to check if matched on user-agent string.
     * @param string|null $lookup The context to lookup matches, if null it will search user-agent string.
     *  - `browser`, `mobile` or `robot`
     * 
     * @return bool Return true if matched otherwise false.
    */
    public function is(string $name, ?string $lookup = null): bool 
    {
        if($lookup === null && $pattern = preg_replace('/(^\/|\/$|\/[imsxADSUXJu]*)/', '', $name)){
            return preg_match('/' . $pattern . '/i', $this->useragent);
            //return preg_match('/' . preg_quote($pattern, '/') . '/i', $this->useragent);
        }

        $lookup = $this->{$lookup} ?? false;

        if($lookup && stripos($lookup, $name) !== false){
            return true;
        }

        return false;
    }

    /**
     * Extract User Agent Information
     * 
     * @param array $matches Matched user agent information
     * @param bool $return_array Return type of user agent.
     * 
     * @return array|object User agent information
    */
    private static function extract(array $matches, bool $return_array = false, bool $isBrowser = false): array|object
    {
        $browser = [
            'isBrowser'        => $isBrowser,
            'userAgent'        => $matches[0] ?? '',
            'browser'          => $matches[1] ?? '',
            'version'          => $matches[2] ?? '',
            'platform'         => $matches[3] ?? '',
            'platform_version' => $matches[4] ?? '',
        ];

        return $return_array ? $browser : (object) $browser;
    }

    /**
     * Reset user agent information.
     * @ignore
    */
    protected function reset(): void 
    {
        $this->isBrowser = false;
        $this->isRobot = false;
        $this->isMobile = false;
        $this->browser = '';
        $this->version = '';
        $this->mobile = '';
        $this->robot = '';
        $this->platform = '';
        $this->platformversion = '';
    }
}