<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

use \App\Config\IPConfig;
use \Luminova\Time\Time;
use \Luminova\Http\Network;
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
      if (isset($_SERVER[self::$cloudFlare])) {
         $_SERVER['REMOTE_ADDR'] = $_SERVER[self::$cloudFlare];
         $_SERVER['HTTP_CLIENT_IP'] = $_SERVER[self::$cloudFlare];
         
         return $_SERVER[self::$cloudFlare];
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
               return $ip;
            }
         }
      }
      
      return PRODUCTION ? '0.0.0.0' : $_SERVER['REMOTE_ADDR']; 
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
      $ip ??= static::get();
      $path ??= root('/writeable/caches/ip/');
      $filename =  "{$path}ip_info_{$ip}.json";
      $headers = [];
      $url = 'http://';

      if (file_exists($filename) && ($response = get_content($filename)) !== false) {
         return json_decode($response);
      }

      self::initConfig();

      if (self::$config->apiProvider === 'ipapi') {
         $url .= "ipapi.co/{$ip}/json/";
         $url .= (self::$config->apiKey === '' ? '' : '?key=' . self::$config->apiKey);
      } elseif (self::$config->apiProvider === 'iphub') {
         $url .= self::$config->ipHubVersion . ".api.iphub.info/ip/{$ip}";

         if(self::$config->apiKey !== ''){
            $headers = ['X-Key' => self::$config->apiKey];
         }
      }else{
         return self::error(sprintf('Invalid ip address ip api provider: "%s".', self::$config->apiProvider), 700);
      }

      try {
         $response = (new Network())->get($url, [], $headers);
         $statusCode = $response->getStatusCode();
         $content = $response->getContents();

         if($content === null){
            return self::error('No ip info available', $statusCode);
         }

         $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

         if (isset($result['error'])) {
            return self::error($result['info'] ?? $result['reason'], $result['code'] ?? $statusCode);
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

      $ip ??= static::get();

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
      $ip ??= static::get();

      return match ($version) {
         4 => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
         6 => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
         default => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false
      };
   }

  /**
   * Convert an IP address to its numeric representation (IPv4 or IPv6).
   *
   * @param string|null $ip The IP address to convert.
   *
   * @return int|string Return numeric representation of the IP address, or an empty string on error.
   */
   public static function toNumeric(?string $ip = null): int|string
   {
      $ip ??= static::get();
      $ip = false;

      if (static::isValid($ip, 4)) {
         $ip = ip2long($ip);
      }elseif (static::isValid($ip, 6)) {
         $ip = inet_pton($ip);
      }

      return ($ip === false) ? '' : $ip;
   }

  /**
   * Convert a numeric IP address to its string representation (IPv4 or IPv6).
   *
   * @param int|string $numeric The numeric IP address to convert.
   *
   * @return string IP address in string format or empty string on error.
   */
   public static function toAddress(int|string $numeric = null): string
   {
      $ip = ''; 
      $numeric ??= static::toNumeric();

      if (is_numeric($numeric)) {
         $ip = long2ip($numeric);
      }elseif (is_string($numeric)) {
         $ip = inet_ntop($numeric);
      }

      return ($ip === false) ? '' : $ip;
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
      return Tor::isTor($ip ?? static::get(), $expiration);
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
}
