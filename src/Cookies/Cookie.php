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

use \Luminova\Interface\CookieInterface;
use \Luminova\Exceptions\CookieException;
use \Luminova\Exceptions\JsonException;
use \App\Controllers\Config\Cookie as CookieConfig;
use \Luminova\Time\Time;
use \Luminova\Time\Timestamp;
use \Throwable;

class Cookie implements CookieInterface
{
    /**
     * @var string $prefix Cookie prefix
    */
    protected string $prefix = '';

    /**
     * @var string $name Cookie name.
    */
    protected string $name = '';

     /**
     * @var mixed $value Cookie value.
    */
    protected mixed $value = '';

     /**
     * @var int $expires Cookie expiration time.
    */
    protected int $expires = 0;

     /**
     * @var string $path Cookie path.
    */
    protected string $path = '/';

     /**
     * @var string $domain Cookie domain
    */
    protected string $domain = '';

     /**
     * @var bool $secure Cookie is secure.
    */
    protected bool $secure = false;

     /**
     * @var bool $httpOnly Cookie http only.
    */
    protected bool $httpOnly = true;

     /**
     * @var string $sameSite Cookie same site attribute.
    */
    protected string $sameSite = 'Lax';

     /**
     * @var bool $raw Is cookie raw enabled
    */
    protected bool $raw = false;

     /**
     * @var array<string, mixed> $options Cookie options.
    */
    protected array $options = [];

    /**
     * @var array $default Cookie default options.
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
     * {@inheritdoc}
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

        $options['expires'] = Timestamp::ttlTimestamp($options['expires']);

        
        // Merge the new options with the old options
        $this->options = array_merge($this->default, $options);

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function set(mixed $name, mixed $value, array $options = []): CookieInterface
    {
        return new self($name, $value, $options);
    }

    /** 
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function getName(): string
    {
        return $this->name;
    }

    /** 
     *{@inheritdoc}
    */
    public function getOptions(): array
    {
        return $this->options;
    }

    /** 
     * {@inheritdoc}
    */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /** 
     * {@inheritdoc}
    */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /** 
     * {@inheritdoc}
    */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** 
     * {@inheritdoc}
    */
    public function getExpiry(bool $return_string = false): int|string
    {
        if($return_string){
            return gmdate(self::EXPIRES_FORMAT, $this->expires);
        }

        return $this->expires;
    }

    /** 
     * {@inheritdoc}
    */
    public function hasExpired(): bool
    {
        return $this->expires === 0 || $this->expires < Time::now()->getTimestamp();
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxAge(): int
    {
        $maxAge = $this->expires - Time::now()->getTimestamp();

        return $maxAge >= 0 ? $maxAge : 0;
    }

    /** 
     * {@inheritdoc}
    */
    public function getPath(): string
    {
        return $this->path;
    }

    /** 
     * {@inheritdoc}
    */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    /** 
     * {@inheritdoc}
    */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /** 
     * {@inheritdoc}
    */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /** 
     * {@inheritdoc}
    */
    public function isRaw(): bool
    {
        return $this->raw;
    }

     /**
     * {@inheritdoc}
    */
    public function getString(): string
    {
        return $this->__toString();
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
    public function getId(): string
    {
        return implode(';', [$this->getPrefixedName(), $this->getPath(), $this->getDomain()]);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function newFromString(string $cookie, bool $raw = false): CookieInterface
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
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function toString(): string
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
                $headers[] = 'Expires=' . $this->getExpiry(true);
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
            $samesite = self::LAX;
        }

        $headers[] = 'SameSite=' . ucfirst(strtolower($samesite));

        return implode('; ', $headers);
    }

    /**
     * {@inheritdoc}
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
     * If `$raw` is true, names should not contain invalid characters as `setrawcookie()` will reject it.
     *
     * @throws CookieException If Invalid Cookie Name or empty string is passed.
     */
    private function validateName(): void
    {
        if ($this->name === '') {
            throw CookieException::throwWith('empty_name');
        }

        if ($this->raw && strpbrk($this->name, $this->reservedCharsList) !== false) {
            throw CookieException::throwWith('invalid_name', $this->name);
        }
    }

    /**
     * Validates the special prefixes if some attribute requirements are met.
     *
     * @throws CookieException If invalid attribute prefix are passed.
     */
    private function validatePrefix(): void
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
     * @throws CookieException If invalid same-site was passed.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite
    */
    private function validateSameSite(): void
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
        try {
            json_decode($value, null, 512, JSON_THROW_ON_ERROR);

            return true;
        } catch (Throwable|JsonException $e) {
            return false;
        }
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
     * Get cookie protected properties.
     * 
     * @param string $property property to retrieve.
     * @throws CookieException Throws if property does not exist.
     * @internal
    */
    public function __get(string $property): mixed 
    {
        if(property_exists($this, $property)){
            return $this->{$property};
        }

        throw new CookieException(sprintf('Invalid property "%s", does not exist.', $property));
    }
}