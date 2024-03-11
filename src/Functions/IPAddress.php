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
use \Luminova\Http\Client\Curl;
use \Luminova\Http\Exceptions\RequestException;
use \Luminova\Http\Exceptions\ConnectException;
use \Luminova\Http\Exceptions\ClientException;
use \Luminova\Http\Exceptions\ServerException;
use \Luminova\Application\Paths;
use \Exception;

class IPAddress
{
   /**
    * @var array $cf
   */
   private static string $cf = 'HTTP_CF_CONNECTING_IP';

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
	 * Get the client's IP address.
	 *
	 * @return string The client's IP address or '0.0.0.0' if not found.
	*/
  public static function get(): string 
   {

      if (isset($_SERVER[static::$cf])) {
         $_SERVER['REMOTE_ADDR'] = $_SERVER[static::$cf];
         $_SERVER['HTTP_CLIENT_IP'] = $_SERVER[static::$cf];
         return $_SERVER[static::$cf];
      }

      foreach (static::$ipHeaders as $header) {
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
    * @param array $option additional option to store / return
    *
    * @return null|object $ipInfo
   */
   public static function getInfo(?string $ip = null, array $options = []): ?object
   {
      if ($ip === null) {
         $ip = static::get();
      }

      $path = path('caches') . "ip" . DIRECTORY_SEPARATOR;

      Paths::createDirectory($path);

      $cacheFile = $path . "ip_info_$ip.json";

      if (file_exists($cacheFile)) {
         $response = file_get_contents($cacheFile);
         $result = json_decode($response);

         return $result;
      }

      $network = new Network(new Curl());
      $headers = [];
      if (IPConfig::$apiProvider === 'ipapi') {
         $url = IPConfig::$apiKey === '' ? "https://ipapi.co/$ip/json/" : "https://ipapi.co/$ip/json/?key=" . IPConfig::$apiKey;
      } elseif (IPConfig::$apiProvider === 'iphub') {
         $url = "http://" . IPConfig::$ipHubVersion . ".api.iphub.info/ip/$ip";
         $headers = IPConfig::$apiKey === [] ? [] : ['X-Key' => IPConfig::$apiKey];
      }else{
         return static::ipInfoError('Invalid ip address info api provider ' . IPConfig::$apiProvider , 700);
      }

      try {
         $response = $network->request('GET', $url, [], $headers);
         $statusCode = $response->getStatusCode();
         $content = $response->getContents();

         if($content === null){
            return static::ipInfoError('No ip info available', $statusCode);
         }

         $result = json_decode($content, true);

         if (isset($result['error'])) {
            return static::ipInfoError($result['info'] ?? $result['reason'], $result['code'] ?? $statusCode);
         }

         $ipInfo = [
            'success' => true,
            'provider' => IPConfig::$apiProvider,
            'datetime' => Time::now()->format('Y-m-d H:i:s'),
            'ipInfo' => $result,
            'options' => $options,
         ];

         write_content($cacheFile, json_encode($ipInfo));

         return (object) $ipInfo;
      } catch (RequestException | ConnectException | ClientException | ServerException | Exception $e) {
         return static::ipInfoError($e->getMessage(), $e->getCode());
      }

      return null;
   }

   /**
    * Return error information
    * @param string $message error message 
    * @param string $status Error status code 
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
   * @param string $address The IP address to validate.
   * @param int    $version   The IP version to validate (4 for IPv4, 6 for IPv6).
   *
   * @return bool True if the IP address is valid, false otherwise.
   */
  public static function isValid(?string $address = null, int $version = 0): bool 
  {
      if($address === null){
         $address = self::get();
      }

      return match ($version) {
         4 => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
         6 => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
         default => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false
      };
  }

  /**
   * Convert an IP address to its numeric representation (IPv4 or IPv6).
   *
   * @param string $address The IP address to convert.
   *
   * @return int|string Numeric IP address or empty string on error.
   */
  public static function toNumeric(?string $address = null): int|string
  {
      if($address === null){
         $address = self::get();
      }

      $ip = false;

      if (self::isValid($address, 4)) {
         $ip = ip2long($address);
      }elseif (self::isValid($address, 6)) {
         $ip = inet_pton($address);
      }

      if( $ip === false){
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
         if($numeric === null){
            $numeric = self::toNumeric();
         }

         // Check if it's binary (IPv6) or numeric (IPv4).
         if (is_numeric($numeric)) {
            // Convert numeric (IPv4) to human-readable IPv4 address.
            $ip = long2ip($numeric);
         }elseif (is_string($numeric)) {
            // Convert binary (IPv6) to human-readable IPv6 address.
            $ip = inet_ntop($numeric);
         }

         return $ip !== false ? $ip : '';
   }

   /**
     * Checks if the given IP address is a Tor exit node
     * 
     * @param string|null $ip
     * 
     * @return bool 
    */
   public static function isTor(string|null $ip = null): bool 
   {
      if($ip === null){
         $ip = self::get();
      }

      return TorDetector::isTorExitNode($ip);
   }
}
