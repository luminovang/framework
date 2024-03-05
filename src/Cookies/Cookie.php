<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cookies;

use \Luminova\Cookies\CookieInterface;
use \Luminova\Cookies\Exception\CookieException;
use \App\Controllers\Config\Cookie as CookieConfig;
use \Luminova\Time\Time;
use \DateTimeInterface;

class Cookie implements CookieInterface
{
    /**
     * @var string $prefix
    */
    protected string $prefix = '';

    /**
     * @var string $name
    */
    protected string $name = '';

     /**
     * @var mixed $value
    */
    protected mixed $value = '';

     /**
     * @var int $expires
    */
    protected int $expires;

     /**
     * @var string $path
    */
    protected string $path;

     /**
     * @var string $domain
    */
    protected string $domain;

     /**
     * @var bool $secure
    */
    protected bool $secure;

     /**
     * @var bool $httpOnly
    */
    protected bool $httpOnly;

     /**
     * @var string $sameSite
    */
    protected string $sameSite;

     /**
     * @var bool $raw
    */
    protected bool $raw;

     /**
     * @var array $options
    */
    protected array $options = [];

     /**
     * @var array $default
    */
    protected array $default = [
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
     * A cookie name can be any US-ASCII characters, except control characters,
     * spaces, tabs, or separator characters.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#attributes
     * @see https://tools.ietf.org/html/rfc2616#section-2.2
     */
    private string $reservedCharsList = "=,; \t\r\n\v\f()<>@:\\\"/[]?{}";


    /**
     * Cookie constructor.
     * 
     * @param string $name Cookie name 
     * @param mixed $value cookie value
     * @param array $options Cookie options
     * 
     * @throws CookieException
    */
    final public function __construct(string $name, mixed $value = '', array $options = []) 
    {
        if( $options === []){
            $options = CookieConfig::class;
        }

        $this->setOptions($options);

        $this->name = $name;
        $this->value = $value;
        $this->prefix = $this->passOption('prefix');
        $this->domain = $this->passOption('domain');
        $this->path = $this->passOption('path');
        $this->expires = $this->passOption('expires');
        $this->secure = $this->passOption('secure');
        $this->httpOnly = $this->passOption('httponly');
        $this->sameSite = $this->passOption('samesite');
        $this->raw = $this->passOption('raw');

        $this->validateName();
        $this->validatePrefix();
        $this->validateSameSite();

        if($value !== ''){
            $this->setValue($value);
        }
    }

    /**
     * Set cookie options 
     * 
     * @param string|array $options Options array or CookieConfig class name
     * 
     * @return self $this
    */
    public function setOptions(string|array $options): self
    {
        // Convert $options to an array if it's an instance of CookieConfig
        if ($options === CookieConfig::class) {
            $options = [
                'expires'  => $options::$expiration,
                'path'     => $options::$cookiePath,
                'domain'   => $options::$cookieDomain,
                'secure'   => $options::$secure,
                'httponly' => $options::$httpOnly,
                'samesite' => $options::$sameSite,
                'raw'      => $options::$cookieRaw,
            ];
        }

        $options['expires'] = $this->toTimestamp($options['expires']);

        
        // Merge the new options with the old options
        $this->options = array_merge($this->default, $options);

        return $this;
    }

     /** 
     * Set key and value to session
     * 
     * @param string $key key to set
     * @param mixed $value value to set
     * 
     * @return Cookie new Cookie instance
    */
    public function set(mixed $name, mixed $value, array $options = []): Cookie
    {
        return new self($name, $value, $options);
    }

    /** 
     * Set key and value to session
     * 
     * @param string $key key to set
     * @param mixed $value value to set
     * 
     * @return self
    */
    public function setValue(mixed $value): self
    {
        $finalValue = $this->parseString($value);

        if($finalValue === false){
            throw CookieException::throwWith('invalid_value', $value);
        }
        $this->saveGlobal($value);
        $this->saveContent($finalValue);

        return $this;
    }

    /** 
     * get data from session
     * 
     * @param string $index key to get
     * 
     * @return mixed
    */
    public function get(?string $key = null): mixed
    {
        $value = $this->getContents();

        if($key === null || $key === ''){
            return $value ?? null;
        }

        if($key !== null && $key !== '' && is_array($value)){
            return $value[$key] ?? null;
        }

        return $value ?? null;
    }

   /**
     * Remove key from the current session storage by passing the key.
     *
     * @param string $index Key index to unset.
     * 
     * @return self
    */
    public function delete(?string $key = null): self
    {
        $name = $this->getName();

        if (!isset($_COOKIE[$name])) {
            return $this; 
        }

        $expired = time() - $this->options['expires'];
    
        // If $key is null or empty, delete the entire cookie
        if ($key === null || $key === '') {
            $this->saveGlobal();
            $this->saveContent('', $expired);

            return $this;
        }
    
        $value = $this->getContents();
    
        // If the value is not an array or the key doesn't exist, nothing to delete
        if (!is_array($value) || !isset($value[$key])) {
            return $this;
        }
    
        unset($value[$key]);

        $finalValue = $this->parseString($value);
        $expiry = null;

        if($finalValue === false){
            $finalValue = '';
            $expiry = $expired;
        }
    
        $this->saveGlobal($value);
        $this->saveContent($finalValue, $expiry);

        return $this;
    }
    
    /** 
     * Check if key exists in session
     * 
     * @param string $key
     * 
     * @return bool
    */
    public function has(?string $key = null): bool
    {
        $name = $this->getName();

        if (($key === null || $key === '') && isset($_COOKIE[$name])) {
            return true;
        }

        $value = $this->getContents();

        if(is_array($value) && isset($value[$key])){
            return true;
        }

        if (isset($_COOKIE[$key])) {
            return true;
        }

        return false;
    }

    /** 
     * Get cookie name
     * 
     * @return string
    */
    public function getName(): string
    {
        return $this->name;
    }

    /** 
     * Get cookie options
     * 
     * @return array
    */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** 
     * Get cookie value
     * 
     * @return mixed
    */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /** 
     * Get cookie domain
     * 
     * @return string
    */
    public function getDomain(): string
    {
        return $this->domain;
    }

     /** 
     * Get cookie prefix
     * 
     * @return string
    */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** 
     * Get cookie expiry
     * 
     * @return int
    */
    public function getExpiry(): int
    {
        return $this->expires;
    }

    /** 
     * Get cookie expiry time as string
     * 
     * @return string
    */
    public function getExpiryString(): string
    {
        return gmdate(self::EXPIRES_FORMAT, $this->expires);
    }

    /** 
     * Checks if the cookie has expired.
     * 
     * @return bool
    */
    public function hasExpired(): bool
    {
        return $this->expires === 0 || $this->expires < Time::now()->getTimestamp();
    }

    /**
     * Gets the "Max-Age" cookie attribute.
     * 
     * @return int
     */
    public function getMaxAge(): int
    {
        $maxAge = $this->expires - Time::now()->getTimestamp();

        return $maxAge >= 0 ? $maxAge : 0;
    }

    /** 
     * Get cookie path
     * 
     * @return string
    */
    public function getPath(): string
    {
        return $this->path;
    }

    /** 
     * Get cookie samesite attribute
     * 
     * @return string
    */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

     /** 
     * Get cookie security attribute
     * 
     * @return bool
    */
    public function isSecure(): bool
    {
        return $this->secure;
    }

     /** 
     * Get cookie httponly attribute
     * 
     * @return bool
    */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /** 
     * Get cookie raw attribute
     * 
     * @return bool
    */
    public function isRaw(): bool
    {
        return $this->raw;
    }

     /**
     * Returns the Cookie as a header value.
     * 
     * @return string
    */
    public function getString(): string
    {
        return $this->__toString();
    }

     /**
     * Returns the Cookie as a header value.
     * 
     * @return string
    */
    public function toString(): string
    {
        return $this->__toString();
    }

    /** 
     * Get cookie id
     * 
     * @return string
    */
    public function getId(): string
    {
        return implode(';', [$this->getPrefixedName(), $this->getPath(), $this->getDomain()]);
    }

    /**
     * Gets the cookie name prepended with the prefix
     * 
     * @return string
     */
    public function getPrefixedName(): string
    {
        $name = $this->getPrefix();

        if ($this->isRaw()) {
            $name .= $this->getName();
        } else {
            $search  = str_split($this->reservedCharsList);
            $replace = array_map('rawurlencode', $search);

            $name .= str_replace($search, $replace, $this->getName());
        }

        return $name;
    }

     /**
     * Create a new Cookie instance from a `Set-Cookie` header.
     *
     * @param string $cookie Cookie header string 
     * @param bool $raw Is raw cookie
     * 
     * @return Cookie New Cookie instance
     */
    public function setFromString(string $cookie, bool $raw = false): Cookie
    {
        $options = ($this->options === []) ? $this->default : $this->options;
        $options['raw'] = $raw;

        $parts = preg_split('/\;[\s]*/', $cookie);
        $part  = explode('=', array_shift($parts), 2);

        $name  = $raw ? $part[0] : urldecode($part[0]);
        $value = isset($part[1]) ? ($raw ? $part[1] : urldecode($part[1])) : '';
        unset($part);

        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                [$attr, $val] = explode('=', $part);
            } else {
                $attr = $part;
                $val  = true;
            }

            $options[strtolower($attr)] = $val;
        }

        return new self($name, $value, $options);
    }

    /** 
     * Convert value to string
     * 
     * @param array|object|int $value 
     * 
     * @return string|bool
    */
    private function parseString(mixed $value): string|bool
    {
        if (!is_string($value) && !is_int($value)) {
            $value = json_encode($value);
            if ($value === false) {
                return false;
            }
        }

        $finalValue = (string) $value;

        return $finalValue;
    }

    /**
     * Pass options to variable 
     * 
     * @param string $key option key
     * 
     * @return mixed $option
    */
    private function passOption(string $key): mixed 
    {
        if(isset($this->options[$key]) && $this->options[$key] !== ''){
            return $this->options[$key];
        }

        $option = $this->default[$key];

        $this->options[$key] = $option;

        return $option;
    }


    /** 
     * Check if cookie name has prefix
     * 
     * @param ?string $name 
     * 
     * @return bool
    */
    public function hasPrefix(?string $name = null): bool
    {
        $name ??= $this->name;

        if (strpos($name, '__Secure-') === 0) {
            return true;
        }

        if (strpos($name, '__Host-') === 0) {
            return true;
        }

        return false;
    }

    /** 
     * Get data as array from storage 
     * 
     * @return mixed
    */
    private function getContents(): mixed
    {
        $name = $this->getName();

        if (isset($_COOKIE[$name])) {
            $content = [];

            if(is_array($_COOKIE[$name])){
                return $_COOKIE[$name];
            }

            if ($this->isJson($_COOKIE[$name])) {
                $content = json_decode($_COOKIE[$name], true) ?? [];
            } else {
                $content = $_COOKIE[$name];
            }

            return $content;
        }

        return null;
    }

    /** 
     * Is value a valid JSON string
     * 
     * @param string $value
     * 
     * @return bool
    */
    private function isJson(string $value): bool
    {
        json_decode($value);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Save delete data from cookie storage.
     *
     * @param string $value contents
     * @param ?int $expiry cookie expiration
     * @param array $options cookie options
     * 
     * @return void
    */
    private function saveContent(string $value = '', ?int $expiry = null, array $options = []): void
    {
        
        $name = $this->getName();

        if($options === []){
            //$expiration = $expiry === null ? time() + $this->options['expires'] : $expiry;
            $expiration = $expiry === null ? $this->options['expires'] : $expiry;
            $options = $this->options;
            $options['expires'] = $expiration;
        }else{
            $options = $this->options;
        }

        $isRaw = $options['raw'];
        unset($options['raw'], $options['prefix']);

        if($isRaw){
            setrawcookie($name, $value, $options);
            return;
        }

        setcookie($name, $value, $options);
    }

     /**
     * Save cookie to global variables
     *
     * @param string $name cookie name
     * @param string $value contents
     * 
     * @return void
    */
    private function saveGlobal(mixed $value = '', ?string $name = null): void 
    {
        $name ??= $this->name;
        $_COOKIE[$name] =  $value;
    }

    /**
     * Converts expires time to Unix timestamp format.
     *
     * @param DateTimeInterface|int|string $expires
     * 
     * @return int $timestamp
     */
    protected function toTimestamp($expires = 0): int
    {
        if ($expires instanceof DateTimeInterface) {
            $expires = $expires->format('U');
        }

        if (!is_string($expires) && !is_int($expires)) {
            throw CookieException::throwWith('invalid_time', gettype($expires));
        }

        if (!is_numeric($expires)) {
            $expires = strtotime($expires);

            if ($expires === false) {
                throw CookieException::throwWith('invalid_time_value');
            }
        }

        $timestamp = $expires > 0 ? (int) $expires : 0;

        return $timestamp;
    }
  
    /**
     * Returns the string representation of the Cookie object.
     *
     * @return string
     */
    public function __toString(): string
    {
        $headers = [];
        $value = $this->getValue();


        if ($value === '') {
            $headers[] = $this->getPrefixedName() . '=deleted';
            $headers[] = 'Expires=' . gmdate(self::EXPIRES_FORMAT, 0);
            $headers[] = 'Max-Age=0';
        } else {
            if(is_array($value)){
                $value = json_encode($value);
            }

            $value = $this->isRaw() ? $value : rawurlencode($value);

            $headers[] = sprintf('%s=%s', $this->getPrefixedName(), $value);

            if ($this->getExpiry() !== 0) {
                $headers[] = 'Expires=' . $this->getExpiryString();
                $headers[] = 'Max-Age=' . $this->getMaxAge();
            }
        }

        if ($this->getPath() !== '') {
            $headers[] = 'Path=' . $this->getPath();
        }

        if ($this->getDomain() !== '') {
            $headers[] = 'Domain=' . $this->getDomain();
        }

        if ($this->isSecure()) {
            $headers[] = 'Secure';
        }

        if ($this->isHttpOnly()) {
            $headers[] = 'HttpOnly';
        }

        $samesite = $this->getSameSite();

        if ($samesite === '') {
            // modern browsers warn in console logs that an empty SameSite attribute
            // will be given the `Lax` value
            $samesite = self::LAX;
        }

        $headers[] = 'SameSite=' . ucfirst(strtolower($samesite));

        return implode('; ', $headers);
    }

    /**
     * Returns the array representation of the Cookie object.
     *
     * @return array<string, bool|int|string>
     */
    public function toArray(): array
    {
        return array_merge($this->options, [
            'name'   => $this->name,
            'value'  => $this->value,
            'prefix' => $this->prefix,
            'raw'    => $this->raw,
        ]);
    }

    /**
     * Validates the cookie name per RFC 2616.
     *
     * If `$raw` is true, names should not contain invalid characters
     * as `setrawcookie()` will reject this.
     *
     * @throws CookieException
     */
    protected function validateName(): void
    {
        if ($this->raw && strpbrk($this->name, $this->reservedCharsList) !== false) {
            throw CookieException::throwWith('invalid_name', $this->name);
        }

        if ($this->name === '') {
            throw CookieException::throwWith('empty_name');
        }
    }

    /**
     * Validates the special prefixes if some attribute requirements are met.
     *
     * @throws CookieException
     */
    protected function validatePrefix(): void
    {
        if (strpos($this->prefix, '__Secure-') === 0 && !$this->secure) {
            throw CookieException::throwWith('invalid_secure_prefix');
        }

        if (strpos($this->prefix, '__Host-') === 0 && (!$this->secure || $this->domain !== '' || $this->path !== '/')) {
            throw CookieException::throwWith('invalid_host_prefix');
        }
    }

     /**
     * Validates the `SameSite` to be within the allowed types.
     *
     * @throws CookieException
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite
     */
    protected function validateSameSite(): void
    {
        $sameSite = $this->sameSite;

        if ($sameSite === '') {
            $sameSite = $this->default['samesite'];
        }

        $sameSite = strtolower($sameSite);

        if (!in_array(strtolower($sameSite), ['none', 'lax', 'strict'], true)) {
            throw CookieException::throwWith('invalid_same_site', $sameSite);
        }

        if (strtolower($sameSite) === 'none' && !$this->secure) {
            throw CookieException::throwWith('invalid_same_site_none');
        }
    }
}