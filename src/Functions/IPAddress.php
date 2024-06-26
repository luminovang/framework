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

use \Luminova\Functions\TorDetector;
use \App\Controllers\Config\IPConfig;
use \Luminova\Time\Time;
use \Luminova\Http\Network;
use \Luminova\Exceptions\AppException;
use \Exception;

class IPAddress
{
   /**
    * CloudFlare heder.
    *
    * @var string $cloudFlare
   */
   private static string $cloudFlare = 'HTTP_CF_CONNECTING_IP';

   /**
    * Ip configuration.
    *
    * @var IPConfig $config
   */
   private static ?IPConfig $config = null;

   /**
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
    * Initializes IPAddress
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
	 * @return string The client's IP address or '0.0.0.0' if not found.
	*/
   public static function get(): string 
   {
      if (isset($_SERVER[self::$cloudFlare])) {
         $_SERVER['REMOTE_ADDR'] = $_SERVER[self::$cloudFlare];
         $_SERVER['HTTP_CLIENT_IP'] = $_SERVER[self::$cloudFlare];
         
         return $_SERVER[self::$cloudFlare];
      }

      foreach (self::$ipHeaders as $header) {
         $ips = isset($_SERVER[$header]) ? $_SERVER[$header] : getenv($header);
         
         if ($ips !== false) {
            $list = array_map('trim', explode(',', $ips));
            foreach ($list as $ip) {
               if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                  return $ip;
               }
            }
         }
      }
      
      return '0.0.0.0'; 
   }

   /**
    * Get IP address information from third party API 
    * 
    * @param string|null $ip Ip address to lookup if null it will use current ip address
    * @param array $options Optional information to store user ip address with.
    *
    * @return null|object $ipInfo
   */
   public static function info(?string $ip = null, array $options = []): ?object
   {
      $ip ??= static::get();
      $path = path('caches') . 'ip' . DIRECTORY_SEPARATOR;

      make_dir($path);

      $cacheFile = $path . "ip_info_$ip.json";

      if (file_exists($cacheFile)) {
         $response = file_get_contents($cacheFile);
         $result = json_decode($response);

         return $result;
      }

      $headers = [];
      self::initConfig();

      if (self::$config->apiProvider === 'ipapi') {
         $url = "https://ipapi.co/$ip/json/" . (self::$config->apiKey === '' ?: '?key=' . self::$config->apiKey);
      } elseif (self::$config->apiProvider === 'iphub') {
         $url = 'http://' . self::$config->ipHubVersion . '.api.iphub.info/ip/' . $ip;
         $headers = (self::$config->apiKey === [] ? [] : ['X-Key' => self::$config->apiKey]);
      }else{
         return self::ipInfoError('Invalid ip address info api provider ' . self::$config->apiProvider , 700);
      }

      try {
         $response = (new Network())->get($url, [], $headers);
         $statusCode = $response->getStatusCode();
         $content = $response->getContents();

         if($content === null){
            return self::ipInfoError('No ip info available', $statusCode);
         }

         $result = json_decode($content, true);

         if (isset($result['error'])) {
            return self::ipInfoError($result['info'] ?? $result['reason'], $result['code'] ?? $statusCode);
         }

         $ipInfo = [
            'success' => true,
            'provider' => self::$config->apiProvider,
            'datetime' => Time::now()->format('Y-m-d H:i:s'),
            'ipInfo' => $result,
            'options' => $options,
         ];

         write_content($cacheFile, json_encode($ipInfo));

         return (object) $ipInfo;
      } catch (AppException | Exception $e) {
         return self::ipInfoError($e->getMessage(), $e->getCode());
      }

      return null;
   }

   /**
     * Check if the request origin IP matches any of the trusted proxy IP addresses or subnets.
     * 
     * @param string $ip The origin IP address
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
         if (strpos($proxy, '/')) {
               [$subnet, $mask] = explode('/', $proxy);
               $subnet = ip2long($subnet);
               $mask = ~((1 << (32 - $mask)) - 1);

               if ((ip2long($ip) & $mask) === ($subnet & $mask)) {
                  return true;
               }
         } elseif ($ip === $proxy) {
            return true;
         }
      }

      return false;
   }

   /**
    * Return error information
    * @param string $message error message 
    * @param int $status Error status code 
    *
    * @return object $error
   */
   private static function ipInfoError(string $message, int $status = 404): object
   {
      $types = [
         'RateLimited' => 'You have reached your subscription request rate limit allowance.'
      ];

      $error = [
         'success' => false,
         'error' => [
            'status' => $status,
            'message' => $types[$message] ?? $message
         ],
      ];

      return (object) $error;
   }

  /**
   * Check if an IP address is valid.
   *
   * @param string|null $address The IP address to validate.
   * @param int $version The IP version to validate (4 for IPv4, 6 for IPv6).
   *
   * @return bool True if the IP address is valid, false otherwise.
   */
  public static function isValid(?string $address = null, int $version = 0): bool 
  {
      $address ??= static::get();

      return match ($version) {
         4 => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
         6 => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
         default => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false
      };
  }

  /**
   * Convert an IP address to its numeric representation (IPv4 or IPv6).
   *
   * @param string|null $address The IP address to convert.
   *
   * @return int|string Numeric IP address or empty string on error.
   */
  public static function toNumeric(?string $address = null): int|string
  {
      $address ??= static::get();
      $ip = false;

      if (static::isValid($address, 4)) {
         $ip = ip2long($address);
      }elseif (static::isValid($address, 6)) {
         $ip = inet_pton($address);
      }

      if($ip === false){
         return '';
      }

      return $ip;
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

         return $ip !== false ? $ip : '';
   }

   /**
     * Checks if the given IP address is a Tor exit node
     * 
     * @param string|null $ip Ip address if null it will use current user's IP.
     * 
     * @return bool Return true if the IP address is a Tor exit node.
   */
   public static function isTor(string|null $ip = null): bool 
   {
      return TorDetector::isTor($ip ?? static::get());
   }
}
