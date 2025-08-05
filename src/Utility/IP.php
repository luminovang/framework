<?php 
/**
 * Luminova Framework IP Address helper.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

use \Throwable;
use \Luminova\Luminova;
use \Luminova\Time\Time;
use \App\Config\IPConfig;
use \Luminova\Utility\Async;
use \Luminova\Http\Client\Novio;
use \Luminova\Exceptions\FileException;
use \Luminova\Exceptions\RuntimeException;
use function \Luminova\Funcs\{
    root,
    is_platform,
    write_content,
    get_content,
    make_dir
};

/**
 * @example Usages:
 * 
 * ```php
 * IP::toBinary('127.0.0.1');      // binary packed IPv6
 * IP::toHex('127.0.0.1');         // "0x00000000000000000000ffff7f000001"
 * IP::toAddress('000000000000...'); // "0000:0000:0000:0000:0000:ffff:7f00:0001"
 * IP::expand('::1');    // "0000:0000:0000:0000:0000:0000:0000:0001"
 * ```
 */
final class IP
{
    /**
     * Cloudflare header key used to retrieve the real client IP.
     *
     * @var string $cloudFlare
     */
    private static string $cloudFlare = 'HTTP_CF_CONNECTING_IP';

    /**
     * Tor exit node list. 
     * 
     * @var string $torExitNodeListUrl
     */
    private static string $torExitNodeListUrl = 'https://check.torproject.org/torbulkexitlist';

    /**
     * Save path. 
     * 
     * @var ?string $path
     */
    private static ?string $torExitPath = null;

    /**
     * Ordered list of headers to inspect when resolving the client IP.
     *
     * These include common proxy, CDN, and forwarding headers.
     * The first valid public IP found will be used.
     *
     * @var array $ipHeaders
     */
    private static array $ipHeaders = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    /**
     * Ip configuration.
     *
     * @var IPConfig $config
     */
    private static ?IPConfig $config = null;

    /**
     * Last Client ip address.
     *
     * @var string $cip
     */
    private static ?string $cip = null;

    /**
     * IP api rete limit error message.
     * 
     * @var array $errors
     */
    private static array $errors = [
        'RateLimited' => 'You have reached your subscription request rate limit allowance.'
    ];

    /**
     * Initializes IP
     */
    public function __construct(){}

    /**
     * Initializes API configuration.
     */
    private static function initConfig(): void
    {
        if(!self::$config instanceof IPConfig){
            self::$config = new IPConfig();
        }
    }

    /**
     * Resolve the real client IP address.
     * 
     * This method checks common proxy and CDN headers (including Cloudflare)
     * to determine the real client IP. It validates that the IP is public 
     * (not private or reserved) and returns the first valid match.
     *
     * - Checks Cloudflare's header first, then other common proxy/CDN headers.
     * - Skips private and reserved IP ranges to ensure only public addresses are returned.
     * - Caches the result for subsequent calls during the same request.
     * - Defaults to '0.0.0.0' in production if no valid IP is found
     *   (or '127.0.0.1' in development for easier debugging).
     * - Returns '0.0.0.0' automatically for CLI environments, since there’s no client.
     *
     * @return string Return the detected client IP address, or a fallback if unavailable.
     */
    public static function get(): string 
    {
        if(Luminova::isCommand()){
            return '0.0.0.0';
        }

        if(self::$cip !== null){
            return self::$cip;
        }

        if (isset($_SERVER[self::$cloudFlare])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER[self::$cloudFlare];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER[self::$cloudFlare];
        }

        foreach (self::$ipHeaders as $header) {
            $ips = $_SERVER[$header] ?? getenv($header) ?? false;

            if (!$ips) {
                continue;
            }

            foreach (explode(',', $ips) as $ip) {
                $ip = trim($ip);

                if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return self::$cip = $ip;
                }
            }
        }

        return self::$cip = (PRODUCTION ? '0.0.0.0' : '127.0.0.1');
    }

    /**
     * Retrieve the machine’s primary local IP address (IPv4 or IPv6).
     *
     * This method first attempts to resolve the IP from the system hostname using {@see IP::getPrimaryAddress()}.
     * If that fails, it falls back to platform-specific commands:
     * - On Windows: executes `ipconfig`
     * - On Unix-like systems: executes `ifconfig`
     *
     * The first detected IP address (IPv4 or IPv6) is returned.
     *
     * @return string|false Returns the detected local IP address, or false if it cannot be determined.
     */
    public static function getLocalAddress(): string|bool
    {
        if (($ip = self::getPrimaryAddress()) !== false) {
            return $ip;
        }

        $isWindows = is_platform('windows');
        $cmd = $isWindows ? 'ipconfig' : 'ifconfig';

        $output = @shell_exec($cmd);

        if (!$output) {
            return false;
        }

        $ipv4Pattern = $isWindows
            ? '/IPv4 Address[.\s]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/'
            : '/inet\s([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/';

        $ipv6Pattern = $isWindows
            ? '/IPv6 Address[.\s]*:\s*([a-f0-9:]+)/i'
            : '/inet6\s([a-f0-9:]+)/i';

        if (preg_match($ipv4Pattern, $output, $matches)) {
            $ip = $matches[1];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }

        if (preg_match($ipv6Pattern, $output, $matches)) {
            $ip = $matches[1];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $ip;
            }
        }

        return false;
    }

    /**
     * Retrieve the machine’s active local network IP address (non-loopback).
     * 
     * This method detects and returns the first non-loopback IP address (IPv4 or IPv6)
     * from the system network interfaces using platform-specific commands:
     * - On Windows: uses `ipconfig`
     * - On Unix-like systems: uses `ifconfig`
     *
     * It first tries to detect an IPv4 address, then falls back to IPv6 if none is found.
     * If no valid address is detected, it falls back to {@see IP::getPrimaryAddress()}.
     *
     * @return string|false Returns the local network IP address, or false if none can be determined.
     */
    public static function getLocalNetworkAddress(): string|bool
    {
        $isWindows = is_platform('windows');
        $cmd = $isWindows ? 'ipconfig' : 'ifconfig';
        $output = @shell_exec($cmd);

        if ($output) {
            $ipv4Pattern = $isWindows
                ? '/IPv4 Address[.\s]*:\s*(?!127\.0\.0\.1)([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/'
                : '/inet\s(?!127\.0\.0\.1)([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/';

            if (preg_match($ipv4Pattern, $output, $matches)) {
                $ip = $matches[1];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $ip;
                }
            }

            $ipv6Pattern = $isWindows
                ? '/IPv6 Address[.\s]*:\s*(?!fe80)([a-f0-9:]+)/i'
                : '/inet6\s(?!fe80)([a-f0-9:]+)/i';

            if (preg_match($ipv6Pattern, $output, $matches)) {
                $ip = $matches[1];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    return $ip;
                }
            }
        }

        return self::getPrimaryAddress();
    }

    /**
     * Get the machine’s primary local hostname IP address (non-loopback).
     *
     * Attempts to resolve the system hostname to an IP address.
     * Returns false if it cannot determine a valid, non-loopback IP.
     *
     * @return string|false Returns the local IP address, or false if not resolvable.
     */
    public static function getPrimaryAddress(): string|bool
    {
        $host = @getHostName();
        if (!$host) {
            return false;
        }

        $ip = @getHostByName($host);
        if (!$ip || $ip === '127.0.0.1' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $ip;
    }

    /**
     * Get the MAC (Media Access Control) address of the system.
     * 
     * This method attempts to retrieves the MAC address of the current system's network interface, 
     * it matches the first non-virtual, non-loopback MAC.
     *
     * Uses system commands depending on the platform:
     * - Windows: `getmac`
     * - Unix/Linux/macOS: `ip link` or `ifconfig -a`
     *
     * @return string|false Returns the first detected MAC address, or false if not found.
     */
    public static function getMacAddress(): string|bool
    {
        $cmd = is_platform('windows') 
            ? 'getmac' 
            : 'ip link 2>/dev/null || ifconfig -a 2>/dev/null';
        
        $output = @shell_exec($cmd);

        if (!$output) {
            return false;
        }

        //'/([a-f0-9]{2}[:-]){5}[a-f0-9]{2}/i'
        if (preg_match('/(?:ether|HWaddr)\s+([a-f0-9]{2}(?:[:-][a-f0-9]{2}){5})/i', $output, $matches)) {
            $mac = strtolower($matches[1]);
            if ($mac !== '00:00:00:00:00:00') {
                return $mac;
            }
        }

        return false;
    }

    /**
     * Retrieve detailed IP address information from a third-party API.
     *
     * - Uses the current client IP if `$ip` is null.
     * - Caches responses locally to avoid repeated lookups.
     * - Merges any additional `$metadata` into the stored result.
     *
     * @param string|null $ip The IP address to query (default: detected client IP).
     * @param array $metadata Optional metadata to associate with the lookup.
     *
     * @return object|null Return the resolved IP information as an object on success, or null on failure.
     */
    public static function info(?string $ip = null, array $metadata = []): ?object
    {
        static $path = null;
        $ip ??= self::get();
        $path ??= root('/writeable/caches/ip/');
        $filename =  "{$path}ip_info_{$ip}.json";

        if (file_exists($filename) && ($response = get_content($filename)) !== false) {
            return json_decode($response);
        }

        self::initConfig();
        [$url, $settings] = self::getProvider($ip);

        if($url === null){
            return self::error(sprintf(
                'Invalid ip address ip api provider: "%s".', 
                self::$config->apiProvider
            ), 700);
        }

        try {
            $response = Async::await(fn() => (new Novio())->request('GET', $url, $settings));

            if($response->getContents() === null){
                return self::error('No ip info available', $response->getStatusCode());
            }

            $result = json_decode($response->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if (isset($result['error'])) {
                return self::error($result['info'] ?? $result['reason'], $result['code'] ?? $response->getStatusCode());
            }

            $options = [
                'success' => true,
                'provider' => self::$config->apiProvider,
                'datetime' => Time::now()->format('Y-m-d H:i:s'),
                'ipInfo' => $result,
                'metadata' => $metadata,
            ];

            if(make_dir($path)){
                write_content($filename, json_encode($options, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }

            return (object) $options;
        } catch (Throwable $e) {
            return self::error($e->getMessage(), $e->getCode());
        }

        return null;
    }
  
    /**
     * Check if an IP address (or the current client IP) is in the list of trusted proxy IPs or subnets.
     *
     * This checks against the application's trusted proxy configuration
     * (`App\Config\IPConfig->trustedProxies`). Supports both IPv4 and IPv6 addresses.
     *
     * @param string|null $ip The IP address to verify. If null, the current client IP is used.
     *
     * @return bool Returns true if the IP is a trusted proxy, false otherwise.
     */
    public static function isTrustedProxy(?string $ip = null): bool
    {
        $ip ??= self::get();

        if (!$ip || $ip === '0.0.0.0' || $ip === '::' || !self::isValid($ip)) {
            return false;
        }

        self::initConfig();

        if(self::$config->trustedProxies === []){
            return false;
        }

        foreach (self::$config->trustedProxies as $proxy) {
            if ($ip === $proxy) {
                return true;
            }

            if (str_contains($proxy, '/')) {
                [$subnet, $mask] = explode('/', $proxy, 2);
                $subnet = trim($subnet);
                $mask = (int) $mask;

                if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong = ip2long($ip);
                    $subnetLong = ip2long($subnet);
                    $maskBits = ~((1 << (32 - $mask)) - 1);

                    if (($ipLong & $maskBits) === ($subnetLong & $maskBits)) {
                        return true;
                    }
                }elseif (
                    filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
                    self::matchIpv6Subnet($ip, $subnet, $mask)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether an IP address is private, loopback, or reserved.
     *
     * Checks whether the given IP (or current client IP if null) falls within a
     * non-routable or special-use range:
     *   - Private networks: 10.0.0.0/8, 172.16.0.0–172.31.255.255, 192.168.0.0/16
     *   - Loopback: 127.0.0.0/8
     *   - Link-local: 169.254.0.0/16
     *   - Reserved/special IPv4 and IPv6 ranges
     *
     * @param string|null $ip Optional IP address. If null, uses `self::get()`.
     *
     * @return bool Returns true if the IP is private, reserved, loopback, or non-routable; false if it's public.
     *
     * @example - Examples:
     * ```php
     * IP::isPrivate('127.0.0.1');       // true
     * IP::isPrivate('192.168.1.100');   // true
     * IP::isPrivate('8.8.8.8');         // false
     * IP::isPrivate('::1');             // true
     * IP::isPrivate();                  // uses current client IP
     * ```
     */
    public static function isPrivate(?string $ip = null): bool
    {
        $ip ??= self::get();

        if (!$ip || $ip === '0.0.0.0' || $ip === '::') {
            return true;
        }

        if (!self::isValid($ip)) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    /**
     * Check if the given IP address is a known Tor exit node.
     *
     * The method checks the cached Tor exit node list (updated every `$expiration`
     * seconds). If the list is expired or missing, it fetches a new copy.
     *
     * @param string|null $ip Optional The IP address to check, if null default to client IP.
     * @param int $expiration Cache expiration time in seconds (default: 2,592,000 = 30 days).
     *
     * @return bool Returns true if the IP is a known Tor exit node, otherwise false.
     * @throws FileException If unable to read or write to the cache directory.
     *
     * @example - Examples:
     * ```php
     * IP::isTor('185.220.101.1'); // true or false
     * IP::isTor();                 // check current client IP
     * ```
     */
    public static function isTor(?string $ip = null, int $expiration = 2_592_000): bool 
    {
        $ip ??= self::get();

        if (!self::isValid($ip)) {
            return false;
        }

        $list = self::fetchTorNodeList($expiration);
        return $list !== false && str_contains($list, $ip);
    }

    /**
     * Validate whether an IP address is a valid IPv4.
     *
     * Automatically falls back to the current client IP if none is provided.
     *
     * @param string|null $ip Optional IP address to check.
     * 
     * @return bool Returns true if valid IPv4, false otherwise.
     *
     * @example - Examples:
     * ```php
     * IP::isIpv4('192.168.0.1'); // true
     * IP::isIpv4('::1');         // false
     * IP::isIpv4();              // check current client IP
     * ```
     */
    public static function isIpv4(?string $ip = null): bool 
    {
        return self::isValid($ip, 4);
    }

    /**
     * Validate whether an IP address is a valid IPv6.
     *
     * Automatically falls back to the current client IP if none is provided.
     *
     * @param string|null $ip Optional IP address to check.
     * 
     * @return bool Returns true if valid IPv6, false otherwise.
     *
     * @example - Examples:
     * ```php
     * IP::isIpv6('2001:db8::1');   // true
     * IP::isIpv6('192.168.1.1');   // false
     * IP::isIpv6();                // check current client IP
     * ```
     */
    public static function isIpv6(?string $ip = null): bool 
    {
        return self::isValid($ip, 6);
    }

    /**
     * Validate an IP address (IPv4 or IPv6).
     * 
     * This method ensures the IP is valid for the given version. It supports:
     * - IPv4 (e.g. `192.168.1.1`)
     * - IPv6 (e.g. `2001:db8::1`)
     * - IPv4-mapped IPv6 (e.g. `::ffff:192.168.1.1`)
     * 
     * If `$ip` is `null`, it automatically checks the current client IP (`self::get()`).
     *
     * @param string|null $ip The IP address to validate. If null, the current IP is used.
     * @param int $version The IP version: `4` (IPv4), `6` (IPv6), or `0` (any). Default is `0`.
     *
     * @return bool Returns `true` if the IP is valid for the given version, otherwise `false`.
     * 
     * @see isIpv6()
     * @see isIpv4()
     *
     * @example - Examples:
     * ```php
     * IP::isValid('192.168.1.1');            // true
     * IP::isValid('2001:db8::1', 6);         // true
     * IP::isValid('::ffff:192.168.0.1', 4);  // true
     * IP::isValid('::1');                    // true
     * IP::isValid('256.256.256.256');        // false
     * IP::isValid(null);                     // checks current client IP
     * ```
     */
    public static function isValid(?string $ip = null, int $version = 0): bool
    {
        $ip ??= self::get();

        if ($ip === '' || $ip === '0.0.0.0' || $ip === '::') {
            return false;
        }

        if (preg_match('/^::\d+\.\d+\.\d+\.\d+$/', $ip)) {
            $ip = '::ffff:' . substr($ip, 2);
        }

        $flags = match ($version) {
            4 => FILTER_FLAG_IPV4,
            6 => FILTER_FLAG_IPV6,
            default => 0
        };

        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) !== false) {
            return true;
        }

        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }

        return match ($version) {
            4 => strlen($bin) === 4,
            6 => strlen($bin) === 16,
            default => true,
        };
    }

    /**
     * Compare two IP addresses for equality.
     *
     * This method normalizes both IPv4 and IPv6 addresses to a consistent
     * binary representation before comparing. It ensures accurate results
     * even when the same address is written in different formats
     * (e.g. `::1` and `0:0:0:0:0:0:0:1` are equal).
     *
     * IPv4-mapped IPv6 addresses (e.g. `::ffff:192.168.0.1`) are automatically
     * normalized to their native IPv4 form before comparison.
     *
     * @param string $ip1 The first IP address.
     * @param string|null $ip2 The second IP address to compare.  
     *                         If null, the current client IP is used.
     *
     * @return bool Returns true if both IP addresses are equivalent, false otherwise.
     *
     * @example - Examples:
     * ```php
     * IP::equals('127.0.0.1', '127.0.0.1');         // true
     * IP::equals('::1', '0:0:0:0:0:0:0:1');         // true
     * IP::equals('::ffff:192.168.0.1', '192.168.0.1'); // true
     * IP::equals('192.168.1.1', '192.168.1.2');     // false
     * IP::equals('invalid', '127.0.0.1');           // false
     * ```
     */
    public static function equals(string $ip1, ?string $ip2 = null): bool 
    {
        if(!$ip1 || !self::isValid($ip1)){
            return false;
        }

        $ip2 ??= self::get();

        if ($ip1 === $ip2) {
            return true;
        }

        $bin1 = self::toBinary($ip1);
        $bin2 = self::toBinary($ip2);

        if ($bin1 === false || $bin2 === false) {
            return false;
        }

        return hash_equals($bin1, $bin2);
    }

    /**
     * Convert an IPv6 (or IPv4-mapped IPv6) to its IPv4 dotted form when possible.
     *
     * - If the input is already a valid IPv4 string, it is returned as-is.
     * - If the input is a valid IPv6 that embeds an IPv4 address (mapped or compatible),
     *   the embedded IPv4 is returned (e.g. "::ffff:127.0.0.1" => "127.0.0.1", "::127.0.0.1" => "127.0.0.1").
     * - Otherwise returns false.
     *
     * @param string|null $ip IPv4/IPv6 string. If null, uses self::get().
     * 
     * @return string|false Returns IPv4 dotted string or false when conversion not possible.
     *
     * @example - Examples:
     * ```php
     * IP::toIpv4('127.0.0.1')           // "127.0.0.1"
     * IP::toIpv4('::ffff:192.168.0.1') // "192.168.0.1"
     * IP::toIpv4('::127.0.0.1')        // "127.0.0.1"
     * IP::toIpv4('2001:db8::1')        // false
     * ```
     */
    public static function toIpv4(?string $ip = null): string|bool
    {
        $ip ??= self::get();

        if (self::isValid($ip, 4)) {
            return $ip;
        }

        if (str_starts_with($ip, '::ffff:')) {
            $ipv4 = substr($ip, 7);
            return self::isValid($ipv4, 4) ? $ipv4 : false;
        }

        $bin = @inet_pton($ip);

        if ($bin === false || strlen($bin) !== 16) {
            return false;
        }

        $zeros = str_repeat("\x00", 10);
        $isMapped = false;

        if (
            strncmp($bin, $zeros . "\xFF\xFF", 12) === 0 || 
            strncmp($bin, $zeros . "\x00\x00", 12) === 0
        ) {
            $isMapped = true;
            $bin = substr($bin, 12);
        }

        $ipv4 = @inet_ntop($bin);

        if ($ipv4 === false && ($data = unpack('N', $isMapped ? $bin : substr($bin, 12, 4))) !== false) {
            $ipv4 = long2ip($data[1]);
        }

        return ($ipv4 !== false && self::isValid($ipv4, 4)) ? $ipv4 : false;
    }

    /**
     * Convert an IPv4 address to its IPv6-mapped (or compatible) representation.
     *
     * - If the input is already a valid IPv6 address, it is returned as-is.
     * - If the input is a valid IPv4 address, it will be mapped to IPv6 as "::ffff:a.b.c.d".
     * - Returns false for invalid input.
     *
     * @param string|null $ip The IPv4 address to convert. If null, uses self::get().
     * @param bool $compatible Whether to return an IPv4-compatible (::a.b.c.d) instead of mapped (::ffff:a.b.c.d).
     *
     * @return string|false Returns the IPv6 string (or binary if $binary = true), or false if invalid.
     *
     * @example - Examples:
     * ```php
     * IP::toIpv6('127.0.0.1')          // "::ffff:127.0.0.1"
     * IP::toIpv6('192.168.0.1')        // "::ffff:192.168.0.1"
     * IP::toIpv6('::1')                // "::1"
     * IP::toIpv6('127.0.0.1', true) // "::127.0.0.1"
     * ```
     */
    public static function toIpv6(?string $ip = null, bool $compatible = false): string|bool
    {
        $ip ??= self::get();

        if (self::isValid($ip, 6)) {
            return $ip;
        }

        if (!self::isValid($ip, 4)) {
            return false;
        }

        return $compatible ? '::' . $ip : '::ffff:' . $ip;
    }

    /**
     * Convert an IP address to a specific IP version or automatically flip to the other version.
     * 
     * Converts the given IP address to either IPv4 or IPv6 format, 
     * depending on the specified version. If an IP is provided and no version was provided, 
     * it flips the original IP to other version.
     * 
     * @param string|null $ip The IP address to convert. If null, uses the server's IP.
     * @param int|null $version The target IP version: 4 for IPv4, 6 for IPv6. If `null`, flip IPv(6<>4).
     * 
     * @return string|bool Returns the converted IP address on success, or false if conversion fails.
     * 
     * @example - Examples:
     * ```php
     * IP::toVersion('2001:db8::1', 4); // Returns '::ffff:192.0.2.33' (if mappable)
     * IP::toVersion('192.0.2.33', 6);  // Returns '::ffff:192.0.2.33'
     * IP::toVersion('192.0.2.33');     // Returns '::ffff:192.0.2.33' (v6)
     * ```
     * > **Note:**
     * > This method will always return a mapped IPV4, when converting IPV4 to IPV6.
     * > To get IPv4-compatible use `IP::toIpv6()` instead.
     */
    public static function toVersion(?string $ip = null, ?int $version = null): string|bool
    {
        if ($ip !== null && $version === null) {
            if (self::isValid($ip, 4)) {
                $version = 6;
            }elseif (self::isValid($ip, 6)) {
                $version = 4;
            }else{
                return false;
            }
        }

        $ip ??= self::get();
        return match ($version) {
            4 => self::toIpv4($ip),
            6 => self::toIpv6($ip),
            default => $ip,
        };
    }

    /**
     * Expand an IP address (IPv4 or IPv6) into its full IPv6-style representation.
     * 
     * Converts:
     * - Compressed IPv6 (e.g. `2001:db8::1`) into full 8-block form  
     *   → `2001:0db8:0000:0000:0000:0000:0000:0001`
     * - IPv4 (e.g. `127.0.0.1`) into its IPv4-mapped IPv6 equivalent  
     *   → `0000:0000:0000:0000:0000:ffff:7f00:0001`
     * - IPv4-mapped IPv6 (e.g. `::ffff:127.0.0.1`) expands correctly as well.
     * 
     * @param string $ip The IPv4 or IPv6 address to expand.
     * 
     * @return string|false Return the fully expanded IPv6-style address, or false if invalid or malformed addresses.
     * 
     * @example - Examples:
     * ```php
     * IP::expand('2001:db8::ff00:42:8329');
     * // '2001:0db8:0000:0000:0000:ff00:0042:8329'
     * 
     * IP::expand('127.0.0.1');
     * // '0000:0000:0000:0000:0000:ffff:7f00:0001'
     * 
     * IP::expand('::1');
     * // '0000:0000:0000:0000:0000:0000:0000:0001'
     * ```
     */
    public static function expand(string $ip): string|false
    {
        if (!self::isValid($ip)) {
            return false;
        }

        if (str_starts_with($ip, '::ffff:')) {
            $ipv4 = substr($ip, 7);

            if (!self::isValid($ipv4, 4)) {
                return false;
            }

            $bin = str_repeat("\0", 10) . "\xff\xff" . pack('N', ip2long($ipv4));
        }elseif (self::isValid($ip, 4)) {
            $bin = str_repeat("\x00", 10) . "\xff\xff" . pack('N', ip2long($ip));
        } else {
            $bin = @inet_pton($ip);
            if ($bin === false) {
                return false;
            }

            if (strlen($bin) === 4) {
                $bin = str_repeat("\x00", 10) . "\xff\xff" . $bin;
            }elseif (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\x00", 12)) {
                $bin = str_repeat("\x00", 10) . "\xff\xff" . substr($bin, 12);
            }
        }

        $parts = unpack('n*', $bin);
        return implode(':', array_map(fn($p) => sprintf('%04x', $p), $parts));
    }

    /**
     * Convert an IP address (IPv4 or IPv6) to its numeric (decimal) representation.
     *
     * For IPv4, this returns a standard unsigned 32-bit decimal number.
     * For IPv6, this returns a large decimal number using BCMath precision.
     *
     * @param string|null $ip The IP address to convert. If null, the current IP is used.
     *
     * @return string|false Returns the numeric string representation, or false on invalid input or failure.
     * @throws RuntimeException If BCMath extension is not available.
     *
     * @example - Examples:
     * ```php
     * IP::toNumeric('127.0.0.1'); // "2130706433"
     * IP::toNumeric('::1'); // "1"
     * IP::toNumeric('2001:db8::1'); // "42540766411282592856903984951653826561"
     * IP::toNumeric('invalid-ip'); // false
     * ```
     * 
     * @see IP::toBinary() - For a binary string representation.
     * @see IP::toHex() - For a hexadecimal string representation.
     * @see IP::toAddress() - To convert back to a human-readable IP string.
     */
    public static function toNumeric(?string $ip = null): string|bool
    {
        $ip ??= self::get();

        if (!self::isValid($ip)) {
            return false;
        }

        if (str_starts_with($ip, '::ffff:')) {
            $ip = substr($ip, 7);
        }

        if (self::isValid($ip, 4)) {
            return sprintf('%u', ip2long($ip));
        }

        static $isBcMath = null;
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        $isBcMath ??= function_exists('bcadd');

        if (!$isBcMath) {
            throw new RuntimeException('BCMath extension is required for IPv6 numeric conversion');
        }

        $hex = bin2hex($packed);
        $decimal = '0';

        for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($hex[$i]));
        }

        return $decimal;
    }

    /**
     * Convert an IPv4 or IPv6 address to its hexadecimal representation.
     *
     * Produces a consistent, reversible hex format using `inet_pton()`.
     * IPv4 returns 8 hex chars, IPv6 returns 32 hex chars, both prefixed with `0x`.
     *
     * This method is intended for readable or storable representations of IPs
     * (e.g., in databases or configuration files).
     *
     * @param string|null $ip The IP address to convert. If null, the current client IP is used.
     * @param bool $prefix Whether to include prefix `0x`, (default: true).
     *
     * @return string|false Returns the IP address in hexadecimal format (prefixed with `0x`),
     *                      or false if the input is invalid.
     *
     * @example - Examples:
     * ```php
     * IP::toHex('192.168.1.1');        // "0xc0a80101"
     * IP::toHex('2001:db8::1');        // "0x20010db8000000000000000000000001"
     * IP::toHex('::ffff:192.168.1.1'); // "0xc0a80101"
     * IP::toHex('2001:db8::1', false); // "20010db8000000000000000000000001" (no-prefix)
     * ```
     * 
     * @see IP::toBinary() - For a binary string representation.
     * @see IP::toNumeric() - For a numeric string representation.
     * @see IP::toAddress() - To convert back to a human-readable IP string.
     */
    public static function toHex(?string $ip = null, bool $prefix = true): string|bool
    {
        return self::toBinOrHex($ip, $prefix, false);
    }

    /**
     * Convert an IP address to its binary representation.
     *
     * IPv4 addresses are converted to IPv4-mapped IPv6 (::ffff:IPv4) and padded
     * to 16 bytes to provide a consistent binary format for storage or comparison.
     * IPv6 addresses are returned in standard network order as produced by `inet_pton()`.
     *
     * This is useful for storing IPs in binary fields, hashing, or converting to
     * a hexadecimal string for human-readable or database-safe formats.
     *
     * @param string|null $ip The IP address to convert. If null, uses the current IP.
     *
     * @return string|false Returns the (4/16) byte binary string, or false if the IP is invalid.
     *
     * @example - Examples:
     * ```php
     * IP::toBinary('127.0.0.1');
     * // Returns: "\0\0\0\0\0\0\0\0\0\0\xff\xff\x7f\x00\x00\x01"
     *
     * IP::toBinary('::1');
     * // Returns: "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\x01"
     *
     * IP::toBinary('::ffff:192.168.1.1');
     * // Returns: "\0\0\0\0\0\0\0\0\0\0\xff\xff\xc0\xa8\x01\x01"
     * ```
     *
     * @see IP::toHex() - For a hexadecimal string representation.
     * @see IP::toNumeric() - For a numeric string representation.
     * @see IP::toAddress() - To convert back to a human-readable IP string.
     */
    public static function toBinary(?string $ip = null): string|false
    {
        return self::toBinOrHex($ip, false, true);
    }

    /**
     * Convert a numeric, hexadecimal, or binary IP back to a readable IPv4 or IPv6 address.
     *
     * Supports:
     *  - Decimal IPv4 (e.g. "2130706433")
     *  - Hexadecimal with optional "0x" prefix (e.g. "0x20010db8...")
     *  - Binary input (e.g. output from inet_pton())
     *
     * @param string|int|null $data The IPv4 numeric or IPv6 hex/binary representation to convert.
     * @param int|null $version The IP version to enforce: `4` for IPv4, `6` for IPv6, or null to auto-detect.
     *
     * @return string|false Returns the converted IP address, or false on invalid input.
     *
     * @example - Examples:
     * ```php
     * IP::toAddress(2130706433); // "127.0.0.1"
     * IP::toAddress('0x20010db8000000000000000000000001'); // "2001:db8::1"
     * IP::toAddress('42540766411282592856903984951653826561'); // "2001:db8::1"
     * IP::toAddress(inet_pton('2001:db8::ff00:42:8329')); // "2001:db8::ff00:42:8329"
     * ```
     */
    public static function toAddress(string|int|null $data = null, ?int $version = null): string|bool
    {
        if ($data === '') {
            return false;
        }

        if($data === null){
            return self::toVersion(version: $version);
        }

        if (is_numeric($data) && (int) $data >= 0 && (int) $data <= 0xFFFFFFFF) {
            return self::toVersion(long2ip((int) $data), $version);
        }

        $bin = false;

        if(self::isHex($data, 32)){
            $hex = preg_replace('/^0x/i', '', (string) $data);
            $bin = @hex2bin(str_pad($hex, 32, '0', STR_PAD_LEFT));
        }else{
            $data = (string) $data;
            $length = strlen($data);

            if (ctype_digit($data) && $length > 10) {
                $decimal = $data;
                $hex = '';
                while (bccomp($decimal, '0') > 0) {
                    $remainder = bcmod($decimal, '16');
                    $decimal = bcdiv($decimal, '16', 0);
                    $hex = dechex((int)$remainder) . $hex;
                }
                $hex = str_pad($hex, 32, '0', STR_PAD_LEFT);
                $bin = hex2bin($hex);
            }elseif ($length === 4 || $length === 16) {
                $bin = $data;
            }
        }

        if ($bin === false) {
            return false;
        }

        $ip = @inet_ntop($bin) ?: self::fromBinary($data);

        if ($ip === false || $version === null) {
            return $ip;
        }

        return self::toVersion($ip, $version);
    }

    /**
     * Check if a value is a valid hexadecimal representation.
     *
     * Supports optional "0x" prefix and an optional maximum length limit.
     *
     * @param mixed $value The value to check.
     * @param int|null $maxLength Optional maximum length (number of hex digits, excluding "0x").
     *
     * @return bool Returns true if the value is a valid hexadecimal string, false otherwise.
     * @internal
     * 
     * @example - Examples:
     * ```php
     * IP::isHex('0xFF');       // true
     * IP::isHex('1a2b');       // true
     * IP::isHex('1a2b', 2);    // false (too long)
     * IP::isHex('xyz');        // false
     * ```
     */
    public static function isHex(mixed $value, ?int $maxLength = null): bool
    {
        if ($value === '' || !is_scalar($value)) {
            return false;
        }

        $value = (string) $value;
        $pattern = ($maxLength !== null && $maxLength > 0)
            ? sprintf('/^(?:0x)?[0-9a-fA-F]{1,%d}$/', $maxLength)
            : '/^(?:0x)?[0-9a-fA-F]+$/';

        return (bool) preg_match($pattern, $value);
    }

    /**
     * Convert a binary representation of an IP address to its original IP address.
     *
     * @param string $binary The binary representation of the IP address.
     *
     * @return string|false Returns the original IP address as a string, or false if the conversion fails.
     */
    private static function fromBinary(string $data): string|bool
    {
        $length = strlen($data);
        $bin = false;

        if ($length === 4 || $length === 16) {
            // Handle IPv4 case (when padding makes it longer)
            // Extract last 4 bytes for IPv4
            $bin = unpack('N', ($length === 16) ? substr($data, -4) : $data);
        }

        return ($bin === false) 
            ? @inet_ntop($data)
            : (@long2ip((int) $bin[1]) ?: @inet_ntop($data));
    }

    /**
     * Return error information.
     *
     * @param string $message error message.
     * @param int $status Error status code.
     *
     * @return object Return error information.
     */
    private static function error(string $message, int $status = 404): object
    {
        return (object) [
            'success' => false,
            'error' => [
                'status' => $status,
                'message' => self::$errors[$message] ?? $message
            ],
        ];
    }

    /**
     * Convert an IP address to a 16-byte binary or hexadecimal representation.
     * 
     * IPv4 addresses are converted to IPv4-mapped IPv6 (::ffff:IPv4) to maintain a
     * consistent 16-byte representation.
     * 
     * @param string|null $ip The IP to convert. Uses current IP if null.
     * @param bool $prefix If false and returning hex, no "0x" prefix is added.
     * @param bool $binary If true, return raw 16-byte binary; otherwise return hex string.
     * 
     * @return string|false Binary or hex string, or false if invalid IP.
     */
    private static function toBinOrHex(
        ?string $ip = null, 
        bool $prefix = true,
        bool $binary = false
    ): string|bool
    {
        $ip ??= self::get();

        if (!self::isValid($ip)) {
            return false;
        }

        if (self::isValid($ip, 4)) {
            $long = @ip2long($ip);

            if ($long === false) {
                return false;
            }

            $bin = str_repeat("\x00", 10) . "\xff\xff" . pack('N', $long);
        }else{
            $bin = @inet_pton($ip);

            if ($bin === false) {
                return false;
            }

            if (strlen($bin) === 4) {
                $bin = str_repeat("\0", 10) . "\xff\xff" . $bin;
            }
        }

        if (strlen($bin) > 16) {
           return false;
        }

        return $binary ? $bin : ($prefix ? '0x' . bin2hex($bin) : bin2hex($bin));
    }

    /**
     * Compare two IPv6 addresses within a subnet.
     *
     * @param string $ip The IP address to check.
     * @param string $subnet The subnet base address.
     * @param int $mask The prefix length.
     *
     * @return bool True if $ip falls within $subnet/$mask, false otherwise.
     */
    private static function matchIpv6Subnet(string $ip, string $subnet, int $mask): bool
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $bytes = intdiv($mask, 8);
        $bits = $mask % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $maskByte = ~((1 << (8 - $bits)) - 1) & 0xFF;
        return (ord($ipBin[$bytes]) & $maskByte) === (ord($subnetBin[$bytes]) & $maskByte);
    }

    /**
     * Function to fetch and cache the Tor exit node list.
     * 
     * @param int $expiration Cache expiration time in seconds.
     * 
     * @return string|bool Return fetched exit node list, otherwise false.
     * @throws FileException Throws if error occurs or unable to read or write to directory.
     */
    private static function fetchTorNodeList(int $expiration): string|bool
    {
        self::$torExitPath ??= self::getTorNodesFileCache();

        if(self::$torExitPath){
            throw new FileException(sprintf('Unable to read or write to tor exit directory: %s.', self::$torExitPath));
        }

        if (file_exists(self::$torExitPath) && (time() - filemtime(self::$torExitPath) < $expiration)) {
            return get_content(self::$torExitPath);
        }

        $result = file_get_contents(self::$torExitNodeListUrl);

        if($result === false){
           return false;
        }

        return write_content(self::$torExitPath, $result);
    }

    /**
     * Get file cache.
     * 
     * @return string Return storage path.
     */
    private static function getTorNodesFileCache(): ?string 
    {
        $path = root('/writeable/caches/tor/');
        
        if(!make_dir($path)){
            return null;
        }

        return "{$path}torbulkexitlist.txt";
    }

    /**
     * Get the IP address api provider request endpoint and options.
     *
     * @param string $ip The client IP address making the request.
     *
     * @return array<int,mixed> Return request endpoint and options.
     */
    private static function getProvider(string $ip): array 
    {
        $url = 'https://';

        if (self::$config->apiProvider === 'ipapi') {
            $url .= "ipapi.co/{$ip}/json/";
            $url .= (self::$config->apiKey === '') ? '' : '?key=' . self::$config->apiKey;
            return [$url, []];
        }
    
        if (self::$config->apiProvider === 'iphub') {
            $url .= self::$config->ipHubVersion . ".api.iphub.info/ip/{$ip}";
            $options = (self::$config->apiKey === '')
                ? [] 
                : ['headers' => ['X-Key' => self::$config->apiKey]];
            return [$url, $options];
        }

        return [null, []];
    }
}