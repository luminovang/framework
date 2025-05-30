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
namespace Luminova\Functions;

use \App\Config\IPConfig;
use \Luminova\Time\Time;
use \Luminova\Http\Client\Curl;
use \Luminova\Utils\Async;
use \Luminova\Functions\Tor;
use \Luminova\Exceptions\AppException;
use \Exception;
use \JsonException;

final class IP
{
    /**
     * Header used by CloudFlare to get the client's IP address.
     *
     * @var string $cloudFlare
     */
    private static string $cloudFlare = 'HTTP_CF_CONNECTING_IP';

    /**
     * List of possible headers to check for the client's IP address.
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
     * IP Error message.
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
        self::$config ??= new IPConfig();
    }

    /**
     * Get the client's IP address.
     *
     * @return string Return the client's IP address or '0.0.0.0' if not found.
     */
    public static function get(): string 
    {
        if(self::$cip !== null){
            return self::$cip;
        }

        if (isset($_SERVER[self::$cloudFlare])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER[self::$cloudFlare];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER[self::$cloudFlare];
            
            return self::$cip = $_SERVER[self::$cloudFlare];
        }

        foreach (self::$ipHeaders as $header) {
            $ips = $_SERVER[$header] ?? getenv($header);
            if ($ips === false) {
                continue;
            }

            $list = explode(',', $ips);
            if($list === []){
                continue;
            }

            foreach ($list as $ip) {
                $ip = trim($ip);
                if($ip === ''){
                    continue;
                }

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return self::$cip = $ip;
                }
            }
        }
        
        return self::$cip = (PRODUCTION ? '0.0.0.0' : $_SERVER['REMOTE_ADDR']); 
    }

    /**
     * Get the local IP address of the machine.
     * 
     * @return string|false Returns the local IP address as a string, or false if unable to retrieve it.
     */
    public static function getLocalAddress():string|bool
    {
        if (($hostName = getHostName()) !== false) {
            $ip = getHostByName($hostName);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        $output = null;
        $pattern = null;

        if(is_platform('windows')){
            $output = shell_exec('ipconfig');
            $pattern = '/IPv4 Address[.\s]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/';
        } else {
            $output = shell_exec('ifconfig');
            $pattern = '/inet\s([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/';
        }

        if ($output && preg_match($pattern, $output, $matches)) {
            return $matches[1]; 
        }

        return false;
    }

    /**
     * Get the local network IP address (not the loopback address).
     * 
     * @return string|false Returns the local network IP address as a string, or false if unable to retrieve it.
     */
    public static function getLocalNetworkAddress(): string|bool
    {
        $output = null;
        $pattern = null;

        if(is_platform('windows')){
            $output = shell_exec('ipconfig');
            $pattern = '/IPv4 Address[.\s]*:\s*(?!127\.0\.0\.1)([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/';
        } else {
            $output = shell_exec('ifconfig');
            $pattern = '/inet\s(?!127\.0\.0\.1)([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/';
        }

        if ($output && preg_match($pattern, $output, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Retrieves the MAC (Media Access Control) address of the current system's network interface.
     *
     * This method executes platform-specific commands to obtain the MAC address:
     * - On Windows: uses the `getmac` command.
     * - On Unix-like systems: uses `ifconfig -a` or `ip link`.
     *
     * @return string|bool Returns the first matched MAC address as a string, or false if not found.
     */
    public static function getMacAddress(): string|bool
    {
        $output = shell_exec(is_platform('windows') ? 'getmac' : 'ifconfig -a 2>/dev/null || ip link');
        preg_match('/([a-f0-9]{2}[:-]){5}[a-f0-9]{2}/i', $output, $matches);

        return $matches[0] ?? false;
    }

    /**
     * Get IP address information from third party API.
     * Uses the current IP if `$address` is `null`.
     * 
     * @param string|null $ip The ip address to lookup (default: null).
     * @param array $options Optional information to store user ip address with.
     *
     * @return null|object Return ip address information, otherwise null.
     */
    public static function info(?string $ip = null, array $options = []): ?object
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
            $response = Async::await(fn() => (new Curl())->request('GET', $url, $settings));

            if($response->getContents() === null){
                return self::error('No ip info available', $response->getStatusCode());
            }

            $result = json_decode($response->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if (isset($result['error'])) {
                return self::error($result['info'] ?? $result['reason'], $result['code'] ?? $response->getStatusCode());
            }

            $ipInfo = [
                'success' => true,
                'provider' => self::$config->apiProvider,
                'datetime' => Time::now()->format('Y-m-d H:i:s'),
                'ipInfo' => $result,
                'options' => $options,
            ];

            if(make_dir($path)){
                write_content($filename, json_encode($ipInfo, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }

            return (object) $ipInfo;
        } catch (AppException|Exception|JsonException $e) {
            return self::error($e->getMessage(), $e->getCode());
        }

        return null;
    }

    /**
     * Check if the request origin IP matches any of the trusted proxy IP addresses or subnets.
     * Uses the current IP if `$ip` is `null`.
     * 
     * @param string $ip The IP address to check (default: null).
     * 
     * @return bool Return true if the request origin IP matches the trusted proxy IPs, false otherwise.
     */
    public static function isTrustedProxy(?string $ip = null): bool
    {
        self::initConfig();

        if(self::$config->trustedProxies === []){
            return false;
        }

        $ip ??= self::get();

        if ($ip === '' || $ip === null) {
            return false;
        }

        foreach (self::$config->trustedProxies as $proxy) {
            if ($ip === $proxy) {
                return true;
            }

            if (str_contains($proxy, '/')) {
                [$subnet, $mask] = explode('/', $proxy);
                $subnet = ip2long($subnet);
                $mask = ~((1 << (32 - $mask)) - 1);

                if ((ip2long($ip) & $mask) === ($subnet & $mask)) {
                return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an IP address is valid.
     * Uses the current IP if `$address` is `null`.
     *
     * @param string|null $ip The IP address to validate (default: null).
     * @param int $version The IP version to validate (e.g., `4`, `6`, or `0` for any).
     *
     * @return bool Return true if the IP address is valid, false otherwise.
     */
    public static function isValid(?string $ip = null, int $version = 0): bool
    {
        $ip ??= self::get();

        return match ($version) {
            4 => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            6 => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
            default => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6) !== false
        };
    }

    /**
     * Compare two IP addresses to determine if they are equal.
     *
     * @param string $ip1 The first IP address to compare.
     * @param string|null $ip2 The second IP address to compare (default: null).
     *
     * @return bool Returns `true` if both IP addresses are valid and equal; `false` otherwise.
     */
    public static function equals(string $ip1, ?string $ip2 = null): bool 
    {
        $ip2 ??= self::get();

        if($ip1 === $ip2){
            return true;
        }

        $ip1 = self::toNumeric($ip1);
        $ip2 = self::toNumeric($ip2);

        return ($ip1 !== false && $ip2 !== false && $ip1 === $ip2);
    }

    /**
     * Convert an IPv6-mapped address to its IPv4 representation.
     *
     * @param string|null $ipv6 The IPv6 address to convert.
     *
     * @return string|false Return the IPv4 address, or false if the input is not a valid IPv6 address.
     */
    public static function toIpv4(?string $ipv6 = null): string|bool
    {
        $ipv6 ??= self::get();

        if (self::isValid($ipv6, 4)) {
            return $ipv6;
        }

        if (!self::isValid($ipv6, 6)) {
            return false;
        }

        if (str_starts_with($ipv6, '::ffff:')) {
            $ipv4Part = substr($ipv6, 7);
            if (self::isValid($ipv4Part, 4)) {
                return $ipv4Part;
            }
        }
        
        $binary = inet_pton($ipv6);

        if ($binary !== false && substr($binary, 0, 12) === str_repeat("\x00", 10) . "\xFF\xFF") {
            return inet_ntop(substr($binary, 12));
        }

        return false;
    }

    /**
     * Convert an IPv4 address to its IPv6-mapped representation.
     *
     * @param string|null $ipv4 The IPv4 address to convert.
     * @param bool $binary Weather to return the binary representation (default: false).
     *
     * @return string|false Return the IPv6-mapped address, or false if the input is not a valid IPv4 address.
     */
    public static function toIpv6(?string $ipv4 = null, bool $binary = false): string|bool
    {
        $ipv4 ??= self::get();

        if (self::isValid($ipv4, 6)) {
            return $binary ? inet_pton( $ipv4) : $ipv4;
        }

        if (!self::isValid($ipv4, 4)) {
            return false;
        }

        return $binary ? inet_pton('::ffff:' . $ipv4) : '::ffff:' . $ipv4;
    }

    /**
     * Convert an IP address to its numeric long integer representation (IPv4 or IPv6).
     *
     * @param string|null $ip The IP address to convert.
     *
     * @return string|false Return long integer representation of the IP address, otherwise false.
     */
    public static function toNumeric(?string $ip = null): string|bool
    {
        $ip ??= self::get();

        if (self::isValid($ip, 4)) {
            return (string) ip2long($ip);
        }

        if (self::isValid($ip, 6)) {
            if (str_starts_with($ip, '::ffff:')) {
                $binary = inet_pton($ip);
                if ($binary !== false) {
                    return '0x' . bin2hex($binary);
                }
            } else {
                return '0x' . preg_replace('/(^|:)(0+)/', '$1', str_replace(':', '', $ip));
            }
        }

        $ipv4 = self::toIpv4($ip);
        if ($ipv4 !== false) {
            return self::toNumeric($ipv4);
        }

        $binary = inet_pton($ip);
        if ($binary !== false) {
            return '0x' . bin2hex($binary);
        }

        return false; 
    }

    /**
     * Convert a numeric or hexadecimal IP address representation to its original (IPv4 or IPv6).
     *
     * @param string|int|null $numeric The ipv4 numeric or ipv6 hexadecimal representation to convert.
     *
     * @return string|false Return original IP address, otherwise false on error.
     */
    public static function toAddress(string|int|null $numeric = null): string|bool
    {
        $numeric ??= self::toNumeric(); 

        if (
            is_numeric($numeric) && 
            filter_var($numeric, FILTER_VALIDATE_INT) !== false && $numeric <= 0xFFFFFFFF
        ) {
            return long2ip((int) $numeric);
        }
        
        if(self::isHex($numeric)){
            $hex = str_pad(ltrim($numeric, '0x'), 32, '0', STR_PAD_LEFT);
            return implode(':', str_split($hex, 4));
        }
        
        if (is_string($numeric)) {
            return inet_ntop($numeric);
        }

        return false;
    }

    /**
     *  Convert an IP address to its binary representation (IPv4 or IPv6).
     *
     * @param string $ip The IP address to convert.
     *
     * @return string|false Return binary representation of an IP address, otherwise false on error.
     */
    public static function toBinary(?string $ip = null): string|bool
    {
        $ip ??= self::toNumeric(); 

        if (self::isValid($ip, 4)) {
            if(($ip = ip2long($ip)) !== false){
                return str_pad(pack('N', $ip), 16, "\0", STR_PAD_LEFT);
            }

            return false;
        } 
        
        if (self::isValid($ip, 6)) {
            return inet_pton($ip);
        }

        return false;
    }

    /**
     * Convert a binary representation of an IP address to its original IP address.
     *
     * @param string $binary The binary representation of the IP address.
     * @param bool $ipv6 Optional flag to specify if the IP address is IPv6 (default: false). 
     *                If true, assumes the address is IPv6.
     *
     * @return string|false Returns the original IP address as a string, or false if the conversion fails.
     */
    public static function fromBinary(string $binary, bool $ipv6 = false): string|bool
    {
        $length = strlen($binary);

        if (!$ipv6 && ($length === 4 || $length === 16)) {
            // Handle IPv4 case (when padding makes it longer)
            if ($length === 16) {
                // Extract last 4 bytes for IPv4
                $binary = substr($binary, -4); 
            }

            $binary = unpack('N', $binary);
            return ($binary !== false) ? long2ip((int) $binary[1]) : false;
        }

        if ($length === 16) {
            return inet_ntop($binary);
        }

        return false;
    }

    /**
     * Checks if the given IP address is a Tor exit node
     * 
     * @param string|null $ip Ip address if null it will use current user's IP.
     * @param int $expiration The expiration time to request for new exit nodes from tor api (default: 2592000 30 days).
     * 
     * @return bool Return true if the IP address is a Tor exit node.
     * @throws FileException Throws if error occurs or unable to read or write to directory.
     */
    public static function isTor(string|null $ip = null, int $expiration = 2592000): bool 
    {
        return Tor::isTor($ip ?? self::get(), $expiration);
    }

    /**
     * Check if value is a hexadecimal representation.
     * 
     * @param mixed $value 
     * 
     * @return bool Return true if value is a hexadecimal representation.
     */
    private static function isHex(mixed $value): bool 
    {
        $value = (string) $value;
        return preg_match('/^0x[0-9a-fA-F]+$/', $value) || preg_match('/^[0-9a-fA-F]+$/', $value);
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