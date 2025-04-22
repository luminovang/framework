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

use \Luminova\Interface\CookieJarInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Cookies\CookieTrait;
use \App\Config\Cookie as CookieConfig;
use \Luminova\Time\Time;
use \Luminova\Exceptions\CookieException;
use \Luminova\Exceptions\FileException;
use \JsonException;
use \Stringable;
use \Countable;

class CookieFileJar implements CookieJarInterface, LazyInterface, Stringable, Countable
{
    /**
     * Cookies. 
     * 
     * @var array $cookies
     */
    private array $cookies = [];

    /**
     * Configuration for cookie behavior and storage.
     * 
     * @var array<string,mixed> $config
     */
    private array $config = [
        'name'            => null,
        'domain'          => null,
        'path'            => null,
        'newSession'      => false,
        'netscapeFormat'  => false,
        'readOnly'        => false,
        'emulateBrowser'  => false
    ];

    /**
     * Cookie value. 
     * 
     * @var mixed $value
     */
    private mixed $value = null;

    /**
     * Cookie size. 
     * 
     * @var mixed $value
     */
    private ?int $size = null;

    /**
     * Cookie storage path.
     * 
     * @var string|null $filePath
     */
    private ?string $filePath = null;

    /**
     * Cookie class instance.
     * 
     * @var self $instance
     */
    private static ?self $instance = null;

    /**
     * Cookie helper trait class.
     */
    use CookieTrait;

    /**
     * Constructor to initialize cookies from a file path or an array.
     * 
     * If a string is provided, it is treated as a file path, and cookies are loaded from the file.
     * If an array is provided, it is directly used as the cookies array.
     *
     * @param string|array<string,array<string,mixed>> $from The source of cookies, either a file path (string) or an array of cookies.
     * @param array<string,mixed> $config Optional settings or configurations for cookies.
     * 
     * @throws CookieException If invalid source file location is provided.
     * @throws FileException If the `$from` is provided as an array, the cookie jar is not in read-only mode, and writing the cookies to the file fails.
     * @example - Array Structure:
     *  ```php
     * $from = [
     *     "CookieName" => [
     *         "value" => "64ke1i31a93f", // The cookie value.
     *         "options" => [
     *             "prefix"   => "optional-prefix",
     *             "raw"      => false,                   // Whether the value is raw or URL-encoded.
     *             "expires"  => "Thu, 31 Oct 2080 16:12:50 GMT", // Expiry date in GMT format.
     *             "path"     => "/",                    // Valid URL path for the cookie.
     *             "domain"   => ".example.com",         // Domain where the cookie is valid.
     *             "secure"   => true,                   // True if the cookie is HTTPS only.
     *             "httponly" => true,                   // True if the cookie is HTTP-only (inaccessible via JavaScript).
     *             "samesite" => "Lax"                   // SameSite policy for cross-site request behavior.
     *         ]
     *     ]
     * ];
     * ```
     */
    public function __construct(string|array $from, array $config = [])
    {
        $this->config = [...$this->config, ...$config];
        $this->initCookies($from);
    }

    /** 
     * {@inheritdoc}
     */
    public static function newCookie(
        array $cookies, 
        array $config = []
    ): CookieJarInterface
    {
        return new self($cookies, $config);
    }

    /**
     * {@inheritdoc}
     */
    public function __get(string $property): mixed 
    {
        $options = $this->toArray();
        if(array_key_exists($property, $options)){
            return $options[$property];
        }

        throw new CookieException(sprintf('Invalid cookie property "%s", does not exist.', $property));
    }

    /** 
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * {@inheritdoc}
     */
    public function toString(bool $metadata = false): string
    {
        $options = $this->toArray();
        return $metadata 
            ? self::parseToString(
                $options['value'] ?? '', 
                $this->getPrefixedName(),
                $options
            )
            : sprintf('%s=%s', 
                $this->getPrefixedName(), 
                $this->toValue($cookie['value'] ?? 'deleted')
            );
    }

    /** 
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            ...$this->getOptions(),
            'value'  => $this->getValue(),
            'name' => $this->config['name']
        ];
    }

    /** 
     * {@inheritdoc}
     */
    public function toNetscapeCookies(?array $cookies = null): string
    {
        $cookies ??= $this->cookies;
        if(!$cookies){
            return '';
        }

        $output = "# Netscape HTTP Cookie File\n";
        $output .= "# https://curl.haxx.se/docs/http-cookies.html\n";
        $output .= "# This file was generated programmatically! Edit at your own risk.\n\n";

        foreach ($cookies as $name => $cookie) {
            $options = $cookie['options'] ?? [];
            $domain = $options['domain'] ?? 'localhost';

            $output .= sprintf(
                "%s%s\t%s\t%s\t%s\t%d\t%s\t%s\n",
                ($options['httponly'] ?? $options['httpOnly'] ?? false) ? "#HttpOnly_" : "",
                $domain,
                (($options['flag'] ?? false) || $domain[0] === '.') ? 'TRUE' : 'FALSE',
                $options['path'] ?? '/',
                ($options['secure'] ?? false) ? 'TRUE' : 'FALSE',
                strtotime($options['expires'] ?? '0'),
                $name,
                $this->toValue($cookie['value'] ?? '')
            );
        }

        return $output;
    }

    /** 
     * {@inheritdoc}
     */
    public function set(mixed $name, mixed $value, array $options = []): self
    {
        $finalValue = $this->toValue($value);
        if($finalValue === false){
            throw CookieException::throwWith('invalid_value', __FUNCTION__ . '->(..., $value)" ');
        }

        $this->cookies[$name] = [
            'value' => $finalValue,
            'options' => array_change_key_case($options, CASE_LOWER),
        ];
        $this->cookies[$name]['options']['flag'] = ($this->cookies[$name]['options']['domain'][0] === '.');

        $this->save();
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function setValue(mixed $value): self
    {
        $this->assertName(__FUNCTION__);
        $finalValue = $this->toValue($value);

        if($finalValue === false){
            throw CookieException::throwWith('invalid_value', __FUNCTION__ . '->($value)" ');
        }

        $this->cookies[$this->config['name']]['value'] = $finalValue;
        $this->save();

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function setCookies(array $cookies): self
    {
        $this->cookies = [
            ...$this->cookies,
            ...self::toLowercase($cookies)
        ];

        $this->save();
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function setOptions(CookieConfig|array $options): self
    {
        $this->assertName(__FUNCTION__);
        $this->cookies[$this->config['name']]['options'] = self::parseOptions(
            $options, 
            $this->getOptions()
        );

        $this->save();
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function setCookiePath(string $path): self
    {
        $this->config['path'] = $path;
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function setCookieDomain(string $domain): self
    {
        $this->config['domain'] = $domain;
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function get(string $name): array
    {
        return $this->cookies[$name] ?? [];
    }

    /** 
     * {@inheritdoc}
     */
    public function getCookie(string $name): self 
    {
        $this->config['name'] = $name;
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->config['name'] ?? null;
    }

    /** 
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        $this->assertName(__FUNCTION__);
        return $this->cookies[$this->config['name']]['options'] ?? [];
    }

    /** 
     * {@inheritdoc}
     */
    public function getOption(string $key): mixed
    {
        return $this->getOptions()[strtolower($key)] ?? null;
    }

    /** 
     * {@inheritdoc}
     */
    public function getValue(bool $as_array = false): mixed
    {
        $this->assertName(__FUNCTION__);
        $value = $this->cookies[$this->config['name']]['value'] ?? null;

        if(!$value || !$as_array){
            return $value;
        }

        try{
            return json_validate($value) 
                ? json_decode($value, true) 
                : $value;
        }catch(JsonException){
            return $value;
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function getDomain(): string
    {
        return $this->getOption('domain') ?? '';
    }

    /** 
     * {@inheritdoc}
     */
    public function getPrefix(): string
    {
        return $this->getOption('prefix') ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxAge(): int
    {
        return Time::now()->getMaxAge($this->getExpiry());
    }

    /** 
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return $this->getOption('path') ?? '';
    }

    /** 
     * {@inheritdoc}
     */
    public function getSameSite(): string
    {
        return $this->getOption('samesite') ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getPrefixedName(): string
    {
        $name = $this->getPrefix();

        if ($this->isRaw()) {
            $name .= ($this->getName() ?? '');
            return $name;
        } 

        $search = str_split(self::RESERVED_CHAR_LIST);
        $name .= str_replace(
            $search, 
            array_map('rawurlencode', $search),
            $this->getName() ?? ''
        );

        return $name;
    }

    /** 
     * {@inheritdoc}
     */
    public function getExpiry(bool $return_string = false): int|string
    {
        $expiry = strtotime($this->getOption('expires') ?? '0');
        return $return_string
            ? gmdate(self::EXPIRES_FORMAT, $expiry) 
            : $expiry;
    }

    /** 
     * {@inheritdoc}
     */
    public function getCookies(): array
    {
        if($this->cookies !== []){
            return $this->cookies;
        }

        if ($this->filePath && file_exists($this->filePath)) {
            try{
                return $this->isNetscapeCookie() 
                    ? $this->fromNetscapeCookies()
                    : json_decode(
                        get_content($this->filePath)?:'',
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    ) ?? [];
            }catch(JsonException){
                return [];
            }
        }

        return [];
    }

    /** 
     * {@inheritdoc}
     */
    public function getCookieNames(): array
    {
        if($this->cookies === []){
            return [];
        }

        return array_keys($this->cookies);
    }

    /** 
     * {@inheritdoc}
     */
    public static function getFromHeader(
        array $headers, 
        bool $raw = false, 
        array $default = []
    ): array
    {
        $cookies = [];
        
        foreach($headers as $cookie){
            [$name, $value, $options] = self::parseFromString(trim($cookie), $raw, $default);

            $options = array_change_key_case($options ?: [], CASE_LOWER);
            $options['flag'] = ($options['domain'][0] === '.');

            $cookies[$name] = [
                'value' => $value,
                'options' => $options,
            ];
        }

        return $cookies;
    }

    /** 
     * {@inheritdoc}
     */
    public static function getFromGlobal(
        array $cookies, 
        bool $raw = false, 
        array $default = []
    ): array
    {
        $entry = [];

        if($default !== []){
            $default = array_change_key_case($default, CASE_LOWER);
            $default['flag'] = ($default['domain'][0] === '.');
        }

        $default['raw'] = $raw;

        foreach($cookies as $name => $value){
            $entry[$name] = [
                'value' => $value,
                'options' => $default,
            ];
        }

        return $entry;
    }

    /** 
     * {@inheritdoc}
     */
    public function getCookieFile(): ?string 
    {
        return $this->filePath;
    }

    /** 
     * {@inheritdoc}
     */
    public function getConfig(): array 
    {
        return $this->config;
    }

    /** 
     * {@inheritdoc}
     */
    public function getCookieByDomain(string $domain, string $path = '/'): array
    {
        if(!$domain){
            throw new CookieException('Domain cannot be empty. Provide a valid domain.');
        }

        $cookies = [];
    
        foreach ($this->cookies as $name => $cookie) {
            if (
                ($cookie['options']['domain'] ?? '') === $domain &&
                ($cookie['options']['path'] ?? '/') === $path &&
                (($cookie['options']['expires'] ?? PHP_INT_MAX) >= Time::now()->getTimestamp())
            ) {
                $cookies[$name] = $cookie;
            }
        }
    
        return $cookies;
    }

    /** 
     * {@inheritdoc}
     */
    public function getCookieStringByDomain(?string $url = null, bool $metadata = false): ?string
    {
        if($url === null && (!$this->config['domain'] || !$this->config['path'])) {
            throw new CookieException(
                'A valid domain and path is required. Provide a URL or configure the domain using "setCookieDomain" and "setCookiePath".'
            );
        }

        [$domain, $path] = ($this->config['path'] && $this->config['domain']) 
            ? [$this->config['domain'], $this->config['path']] 
            : $this->getCookieInfoFromRequest($url);

        $cookies = $this->getCookieByDomain($domain, $path);
    
        if ($cookies === []) {
            return null;
        }

        $cookieString = '';
        foreach ($cookies as $name => $cookie) {
            $cookieString .= $metadata 
                ? self::parseToString(
                    $cookie['value'], 
                    self::parsePrefixName(
                        $name, 
                        $cookie['options']['prefix'] ?? '', 
                        $cookie['options']['raw'] ?? false
                    ), 
                    $cookie['options']
                ) 
                : sprintf('%s=%s; ', 
                    self::parsePrefixName(
                        $name, 
                        $cookie['options']['prefix'] ?? '', 
                        $cookie['options']['raw'] ?? false
                    ), 
                    $this->toValue($cookie['value'] ?? 'deleted')
                );
        }

        return rtrim($cookieString, '; ');
    }

    /** 
     * {@inheritdoc}
     */
    public function isSecure(): bool
    {
        return (bool)($this->getOption('secure') ?? false);
    }

    /** 
     * {@inheritdoc}
     */
    public function isHttpOnly(): bool
    {
        return (bool)($this->getOption('httponly') ?? false);
    }

    /** 
     * {@inheritdoc}
     */
    public function isRaw(): bool
    {
        return (bool)($this->getOption('raw') ?? false);
    }

    /** 
     * {@inheritdoc}
     */
    public function isSubdomains(): bool
    {
        return (bool)($this->getOption('flag') ?? str_starts_with($this->getDomain(), '.'));
    }

    /** 
     * {@inheritdoc}
     */
    public function isEmulateBrowser(): bool 
    {
        return $this->config['emulateBrowser'] ?? false;
    }

    /** 
     * {@inheritdoc}
     */
    public function isNewSession(): bool 
    {
        return $this->config['newSession'] ?? false;
    }

    /** 
     * {@inheritdoc}
     */
    public function isNetscapeCookie(): bool 
    {
        return $this->config['netscapeFormat'] ?? false;
    }

    /** 
     * {@inheritdoc}
     */
    public function isExpired(): bool
    {
        $expiry = $this->getOption('expires') ?? 0;
        return ($expiry > 0 && $expiry < Time::now()->getTimestamp());
    }

    /** 
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return $this->cookies === [];
    }

    /** 
     * {@inheritdoc}
     */
    public function isReadOnly(): bool
    {
        return $this->config['readOnly'] === true;
    }

    /** 
     * {@inheritdoc}
     */
    public function has(string $name): bool 
    {
        return $this->cookies !== [] && isset($this->cookies[$name]);
    }

    /** 
     * {@inheritdoc}
     */
    public function count(): int 
    {
        return count($this->cookies);
    }

    /** 
     * {@inheritdoc}
     */
    public function size(): int
    {
        if($this->cookies === []){
            return $this->size = 0;
        }

        return $this->size ??= $this->calculateSize();
    }

    /** 
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $copy = $this->cookies;
        $this->cookies = [];

        if($this->save()){
            $copy = null;
            return true;
        }

        $this->cookies = $copy;
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(?string $name = null): bool
    {
        $name ??= $this->config['name'] ?? null;

        if (!$name || !isset($this->cookies[$name])) {
            return false; 
        }

        unset($this->cookies[$name]);
        return $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function forceExpire(?string $name = null): bool
    {
        $name ??= $this->config['name'];

        if (!$name || !isset($this->cookies[$name])) {
            return false; 
        }

        $options = $this->cookies[$name]['options'] ?? [];
    
        if ($options !== []) {
            $this->cookies[$name]['options']['expires'] = time() - ($options['expires'] ?? PHP_INT_MAX);
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Initializes cookies from either a file path or an array.
     * 
     * @param string|array $from Either a file path to load cookies from, or an array of cookie data.
     *
     * @throws CookieException If an invalid file path is provided.
     *
     * @return void
     */
    private function initCookies(string|array $from): void 
    {
        if (is_string($from)) {
            if(!is_file($from)){
                throw new CookieException(sprintf(
                    'Invalid cookie source file: "%s", an array or a valid file path is required.', 
                    $from
                ));
            }

            $this->filePath = $from;
            $this->cookies = $this->getCookies();
        }else{
            $this->cookies = $from;
            $this->filePath ??= root('/writeable/temp/') . 'cookies.txt';

            if($this->cookies !== []){
                $this->save();
            }
        }

        if(
            $this->cookies !== [] && 
            empty($this->config['name']) && 
            $this->count() < 2 && 
            ($name = array_key_first($this->cookies)) !== null
        ){
            $this->config['name'] = $name;
        }
    }

    /**
     * Retrieve cookie path from URL based RFC 6265.
     *
     * @link https://datatracker.ietf.org/doc/html/rfc6265#section-5.1.4
     * 
     * @return array Return an array of request host and path. 
     */
    protected function getCookieInfoFromRequest(string $url): array
    {
        $info = parse_url($url);
        $path = $info['path'] ?? '';
        
        if ($path === '' || $path === '/' || !str_starts_with($path, '/')) {
            return [$info['host'] ?? '', '/'];
        }
        
        return [$info['host'] ?? '', substr($path, 0, strrpos($path, '/') ?: 1)];
    }

    /**
     * Parses Netscape-formatted cookies from a file and converts them into an array.
     *
     * This method reads a file containing Netscape-formatted cookies, processes each line,
     * and converts the cookie information into a structured array. Each cookie is represented
     * as an associative array with 'value' and 'options' keys.
     *
     * The method handles the following cookie attributes:
     * - domain
     * - flag (whether the cookie is valid for subdomains)
     * - path
     * - secure
     * - expires
     * - name
     * - value
     * - httpOnly
     *
     * It also calculates additional attributes like 'max-age' and sets default values for 'raw' and 'samesite'.
     *
     * @return array Return an associative array where keys are cookie names and values are arrays containing
     *               'value' (the cookie value) and 'options' (an array of cookie attributes).
     */
    protected function fromNetscapeCookies(): array
    {
        if(!$this->filePath){
            return [];
        }
        
        $cookies = [];
        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $parts = explode("\t", $line);

            if (count($parts) !== 7) {
                continue;
            }

            [$domain, $flag, $path, $secure, $expires, $name, $value] = $parts;
            $httpOnly = false;
            $prefix = '';

            if (str_starts_with($domain, '#')) {
                $segments = explode('_', $domain, 2);
                if (count($segments) === 2) {
                    [$prefix, $domain] = $segments;
                    $httpOnly = ($prefix === '#HttpOnly');
                }
            }

            $cookies[$name] = [
                'value' => $value,
                'options' => [
                    'prefix'   => ($prefix && !$httpOnly) ? ltrim($prefix, '#') : '',
                    'raw'      => false,
                    'expires'  => gmdate(self::EXPIRES_FORMAT, $expires),
                    'path'     => $path,
                    'domain'   => $domain,
                    'flag'     => ($flag === 'TRUE'),
                    'secure'   => ($secure === 'TRUE'),
                    'httponly' => $httpOnly,
                    'samesite' => self::LAX,
                ],
            ];
        }

        return $cookies;
    }

    /**
     * Saves the current cookie data to a file.
     * 
     * @return bool Returns true if the save operation was successful, false otherwise.
     * @throws FileException If writing the cookies to the file fails.
     */
    protected function save(): bool
    {
        if(!$this->filePath){
            return false;
        }

        $this->size = null;
        if($this->isReadOnly()){
            return true;
        }

        if(!make_dir(dirname($this->filePath))){
            return false;
        }

        return write_content(
            $this->filePath, 
            $this->isNetscapeCookie() 
                ? $this->toNetscapeCookies() 
                : json_encode($this->cookies, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Calculates the size of a single cookie's name-value pair in bytes.
     * 
     * @return int The size of the cookie name-value pair in bytes.
     */
    protected function calculateSize(): int 
    {
        return array_reduce(
            array_keys($this->cookies),
            fn (int $index, string $name) => $index + mb_strlen("{$name}=" . $this->cookies[$name]['value'], '8bit'),
            0
        );
    }

    /**
     * Converts all option keys in the cookies array to lowercase.
     *
     * This function iterates through the provided cookies array and converts
     * all keys in the 'options' sub-array to lowercase for each cookie.
     *
     * @param array $cookies An associative array of cookies, where each cookie
     *                       is represented as an array with potential 'options' key.
     *
     * @return array The modified cookies array with lowercase option keys.
     */
    protected static function toLowercase(array $cookies): array
    {
        foreach ($cookies as $name => $cookie) {
            if (isset($cookie['options'])) {
                $cookies[$name]['options'] = array_change_key_case($cookie['options'], CASE_LOWER);
            }
        }

        return $cookies;
    }

    /**
     * Asserts that a cookie name has been set.
     * 
     * @param string $fn The name of the calling function, used in the exception message.
     *
     * @return void
     * @throws CookieException If the cookie name is not set.
     */
    private function assertName(string $fn): void
    {
        if ($this->config['name']) {
            return;
        }

        throw new CookieException(sprintf(
            'Cookie name is not set. To set the name, call "$cookieJar->getCookie(\'cookie-name\')->%s(...)" or set the name in cookie config "new CookieFileJar(\'cookie-array-or-file\', [\'name\' => \'Name\'])->%s(...)".',
            $fn, $fn
        ));
    }
}