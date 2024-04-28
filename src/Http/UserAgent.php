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
     * Constructor
     *
     * Sets the User Agent and runs the compilation routine.
     *
     * @param string|null $useragent The User Agent string. If not provided, it defaults to $_SERVER['HTTP_USER_AGENT'].
     */
    public function __construct(?string $useragent = null)
    {
        if ($useragent === null && isset($_SERVER['HTTP_USER_AGENT'])) {
            $useragent = trim($_SERVER['HTTP_USER_AGENT']);
        }

        $this->expose($useragent ?? '');
        $this->isReferral();
    }

    /**
     * Magic method to dynamically access properties.
     *
     * @param string $name The name of the property.
     * 
     * @return mixed The value of the property if exists, otherwise null.
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
    public static function parse(?string $userAgent = null, bool $return_array = false): array|object|false
    {
        $userAgent ??= (trim($_SERVER['HTTP_USER_AGENT']??''));
       
        if (!empty($userAgent)) {
            $pattern = '/^(.*?)\/([\d.]+) \(([^;]+); ([^;]+); ([^)]+)\) (.+)$/';

            if (preg_match($pattern, $userAgent, $matches)) {
                $browser = [
                    'userAgent'        => $matches[0], // Full User Agent String
                    'browser'          => $matches[1], // Browser Name
                    'version'          => $matches[2], // Browser Version
                    'platform'         => $matches[3], // Operating System Name
                    'platform_version' => $matches[4], // Operating System Version
                ];

                return $return_array ? $browser : (object) $browser;
            }
        }

        return false;
    }

    /**
     * Parse and expose user agent information.
     * 
     * @param string $userAgent The user agent string to parse and expose.
     * 
     * @return void
     */
    public function expose(string $userAgent): void
    {
        $agent = static::parse($userAgent, false);
        $this->useragent = $userAgent;

        if($agent !== false){
            $this->isBrowser = true;
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
     * @return bool True if the user agent is from known robot, false otherwise.
    */
    public function isRobot(): bool 
    {
        foreach (Browser::$robotPatterns as $name => $pattern) {
            if (preg_match('/' . preg_quote($pattern, '/') . '/i', $this->useragent)) {
                $this->isRobot = true;
                $this->robot = $name;
                return true;
            }
            
        }

        $this->isRobot = false;
        $this->robot = '';
        return false;
    }

    /**
     * Check if the user agent string represents a mobile device.
     * 
     * @return bool True if the user agent represents a mobile device, false otherwise.
    */
    public function isMobile(): bool 
    {
        foreach (Browser::$mobileKeywords as $name) {
            if (stripos($this->useragent, $name) !== false) {
                $this->isMobile = true;
                $this->mobile = $name;
                return true;
            }
        }

        $this->isMobile = false;
        $this->mobile = '';
        return false;
    }

    /**
     * Check if the user agent string belongs to a browser.
     *
     * @param string|null $key Optional. If provided, checks if the browser name matches the given key.
     * 
     * @return bool True if the user agent belongs to a browser, or if the given key matches the browser name, false otherwise.
     */
    public function isBrowser(?string $key = null): bool
    {
        if (!$this->isBrowser) {
            return false;
        }

        if ($key === null) {
            if ($this->browser === '') {
                return false;
            }

            foreach (Browser::$browsers as $keyword) {
                if (stripos($this->browser, $keyword) !== false) {
                    return true;
                }
            }
            
            return true;
        }

        return isset(Browser::$browsers[$key]) && strtolower($this->browser) === strtolower(Browser::$browsers[$key]);
    }

    /**
     * Reset user agent information.
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