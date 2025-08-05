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
namespace Luminova\Cookies;

use \Luminova\Interface\LazyObjectInterface;
use \App\Config\Cookie as CookieConfig;
use \Luminova\Time\Time;
use \Luminova\Time\Timestamp;

abstract class BaseCookie implements LazyObjectInterface
{
    /**
     * Cookies will be sent in all contexts, i.e., in responses to both
     * third-party and cross-origin requests. If `SameSite=None` is set,
     * the cookie `Secure` attribute must also be set (or the cookie will be blocked).
     * 
     * @var string NONE
     */
    public const NONE = 'none';

    /**
     * Cookies are not sent on normal cross-site sub-requests (for example to
     * load images or frames into a third-party site), but are sent when a
     * user is navigating to the origin site (i.e., when following a link).
     * 
     * @var string LAX
     */
    public const LAX = 'lax';

    /**
     * Cookies will only be sent in a third-party context and not be sent
     * along with requests initiated by third-party websites.
     * 
     * @var string STRICT
     */
    public const STRICT = 'strict';

    /**
     * Expires date string format.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Date
     * @see https://tools.ietf.org/html/rfc7231#section-7.1.1.2
     * 
     * @var string EXPIRES_FORMAT
     */
    public const EXPIRES_FORMAT = 'D, d-M-Y H:i:s T';

    /**
     * A cookie name can be any US-ASCII characters, except control characters,
     * spaces, tabs, or separator characters.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#attributes
     * @see https://tools.ietf.org/html/rfc2616#section-2.2
     * 
     * @var string RESERVED_CHAR_LIST
     */
    public const RESERVED_CHAR_LIST = "=,; \t\r\n\v\f()<>@:\\\"/[]?{}";

    /**
     * Cookie default options.
     * 
     * @var array<string,mixed> DEFAULT_OPTIONS
     */
    public const DEFAULT_OPTIONS = [
        'prefix' => '',
        'expires'  => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
        'raw'      => false,
    ];

    /** 
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return '';
    }

    /** 
     * {@inheritdoc}
     */
    public function hasPrefix(?string $name = null): bool
    {
        $name ??= ($this->getName() ?? '');

        if(!$name){
            return false;
        }

        if (str_starts_with($name, '__Secure-')) {
            return true;
        }

        return str_starts_with($name, '__Host-');
    }

    /**
     * Converts cookie data to a string suitable for use in HTTP headers.
     *
     * @param mixed $value The value of the cookie.
     * @param string $prefix The prefix to prepend to the cookie name.
     * @param array $option Additional options for the cookie (e.g., path, domain, expiration).
     * 
     * @return string Return the formatted cookie string.
     */
    protected function parseToString(mixed $value, string $prefix,  array $option): string
    {
        $expires = self::_getter('expires', $option);
        $path = self::_getter('path', $option);
        $domain = self::_getter('domain', $option);
        $secure = self::_getter('secure', $option);
        $httpOnly = self::_getter('httponly', $option);
        $samesite = self::_getter('samesite', $option) ?: self::LAX;

        $headers = [];

        if ($value === '') {
            $headers[] = "{$prefix}=deleted";
            $headers[] = 'Expires=' . gmdate(self::EXPIRES_FORMAT, 0);
            $headers[] = 'Max-Age=0';
        } else {
            $value = $this->toValue($value);
            $value = self::_getter('raw', $option) ? $value : rawurlencode($value);

            $headers[] = sprintf('%s=%s', $prefix, $value);

            if ($expires) {
                $expires = is_numeric($expires) ? (int) $expires : strtotime($expires);
                $headers[] = 'Expires=' . gmdate(self::EXPIRES_FORMAT, $expires);
                $headers[] = 'Max-Age=' . Time::now()->getMaxAge($expires);
            }
        }

        if ($path) {
            $headers[] = 'Path=' . $path;
        }

        if ($domain) {
            $headers[] = 'Domain=' . $domain;
        }

        if ($secure) {
            $headers[] = 'Secure';
        }

        if ($httpOnly) {
            $headers[] = 'HttpOnly';
        }

        if ($samesite === '') {
            $samesite = self::LAX;
        }

        $headers[] = 'SameSite=' . ucfirst(strtolower($samesite));

        return implode('; ', $headers);
    }

    /**
     * Parses options for cookies and normalizes them.
     *
     * @param CookieConfig|array $options Cookie configuration options.
     * @param array|null $default Default values for options if not provided.
     * 
     * @return array<string,mixed> Return the normalized options array.
     */
    protected static function parseOptions(CookieConfig|array $options, ?array $default = null): array
    {
        if ($options instanceof CookieConfig) {
            $options = [
                'prefix'   => isset($options->cookiePrefix) ? $options->cookiePrefix : '',
                'expires'  => $options->expiration,
                'path'     => $options->cookiePath,
                'domain'   => $options->cookieDomain,
                'secure'   => $options->secure,
                'httponly' => $options->httpOnly,
                'samesite' => $options->sameSite,
                'raw'      => $options->cookieRaw,
            ];
        }

        $options['expires'] = Timestamp::ttlTimestamp($options['expires']);
        
        return array_merge($default ?? self::DEFAULT_OPTIONS, $options);
    }

    /**
     * Parses a cookie string into its component parts.
     *
     * @param string $cookie The cookie string to parse.
     * @param bool $raw Whether to handle the cookie name and value as raw data.
     * @param array $options Additional options for the cookie.
     * 
     * @return array<int,mixed> Return an array containing the cookie name, value, and parsed options.
     */
    protected static function parseFromString(
        string $cookie, 
        bool $raw = false, 
        array $options = []
    ): array
    {
        $options['raw'] = $raw;
        $parts = preg_split('/\;[\s]*/', $cookie);
        $part = explode('=', array_shift($parts), 2);

        $name = $raw ? $part[0] : urldecode($part[0]);
        $value = isset($part[1]) ? ($raw ? $part[1] : urldecode($part[1])) : '';
        unset($part);

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$attr, $val] = explode('=', $part);
            } else {
                $attr = $part;
                $val  = true;
            }

            $options[strtolower($attr)] = $val;
        }
        
        return [$name, $value, $options];
    }

    /**
     * Prepares a cookie name with a given prefix, encoding reserved characters as necessary.
     *
     * @param string $name The cookie name.
     * @param string $prefix The prefix to prepend.
     * @param bool $raw Whether to treat the name as raw data (skip encoding).
     * 
     * @return string Return the prefixed and encoded cookie name.
     */
    protected static function parsePrefixName(string $name, string $prefix, bool $raw = false): string
    {
        $line = $prefix;

        if ($raw) {
            $line .= $name;
        } else {
            $search  = str_split(self::RESERVED_CHAR_LIST);
            $line .= str_replace($search, array_map('rawurlencode', $search), $name);
        }

        return $line;
    }

    /**
     * Retrieves a value from an options array by key.
     *
     * @param string $key The key to retrieve.
     * @param array $option The options array.
     * 
     * @return mixed Return the value of the key, or null if not set.
     */
    protected static function _getter(string $key, array $option): mixed
    {
        return $option[$key] ?? null;
    }

    /**
     * Converts a mixed value to its string representation.
     *
     * This method handles various types of values and converts them to appropriate string formats:
     * - Empty strings are returned as-is.
     * - Arrays and objects are JSON encoded.
     * - Boolean values are converted to '1' for true and '0' for false.
     * - All other types are cast to string.
     *
     * @param mixed $value The value to be converted to a string.
     *
     * @return string|false Return the string representation of the input value or false.
     */
    protected function toValue(mixed $value): string|bool
    {
        if($value === ''){
            return $value;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);

            if ($value === false) {
                return false;
            }

            return $value ;
        } 

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}