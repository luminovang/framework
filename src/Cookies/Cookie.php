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

use \Stringable;
use \Luminova\Time\Time;
use \App\Config\Cookie as CookieConfig;
use \Luminova\Base\Cookie as BaseCookie;
use \Luminova\Interface\{Arrayable, CookieInterface};
use \Luminova\Exceptions\CookieException;

class Cookie extends BaseCookie implements CookieInterface, Stringable, Arrayable
{
    /**
     * Cookie prefix.
     * 
     * @var string $prefix
     */
    protected string $prefix = '';

    /**
     * Cookie name.
     * 
     * @var string $name 
     */
    protected string $name = '';

    /**
     * Cookie value.
     * 
     * @var mixed $value 
     */
    protected mixed $value = '';

    /**
     * Cookie expiration time.
     * 
     * @var int $expires
     */
    protected int $expires = 0;

    /**
     * Cookie path.
     * 
     * @var string $path
     */
    protected string $path = '/';

    /**
     * Cookie domain.
     * 
     * @var string $domain
     */
    protected string $domain = '';

    /**
     * Cookie is secure.
     * 
     * @var bool $secure
     */
    protected bool $secure = false;

    /**
     * Cookie http only.
     * 
     * @var bool $httpOnly
     */
    protected bool $httpOnly = true;

    /**
     * Cookie same site attribute.
     * 
     * @var string $sameSite
     */
    protected string $sameSite = 'Lax';

    /**
     * Is cookie raw enabled.
     * 
     * @var bool $raw
     */
    protected bool $raw = false;

    /**
     * Cookie options.
     * 
     * @var array<string,mixed> $options
     */
    protected array $options = [];

    /**
     * Cookie configuration.
     * 
     * @var ?CookieConfig $config
     */
    private static ?CookieConfig $config = null;

    /**
     * Initialize and create new cookie object.
     * 
     * @param string $name The cookie name to initialize with.
     * @param mixed $value Optional cookie value.
     * @param CookieConfig|array $options An optional array of cookie options or instance of cookie config class.
     * 
     * @throws CookieException Throws if error occurs or invalid cookie attributes.
     */
    public final function __construct(string $name, mixed $value = '', CookieConfig|array $options = []) 
    {
        $this->setOptions(($options === []) 
            ? self::$config ??= new CookieConfig() 
            : $options
        );

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
    public function __get(string $property): mixed 
    {
        if(property_exists($this, $property)){
            return $this->{$property};
        }

        throw new CookieException(sprintf('Invalid property "%s", does not exist.', $property));
    }

    /** 
     * {@inheritdoc}
     */
    public function setOptions(CookieConfig|array $options): self
    {
        $this->options = self::parseOptions($options);
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
        $finalValue = $this->toValue($value);

        if($finalValue === false){
            throw CookieException::rethrow('invalid_value', __FUNCTION__ . '->($value)" ');
        }
        
        $this->saveGlobal(null, $value);
        $this->saveContent($finalValue);

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function get(?string $key = null): mixed
    {
        $value = $this->getContents();

        if(!$key || !is_array($value)){
            return $value;
        }

        return $value[$key] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(?string $key = null): bool
    {
        $name = $this->getName();

        if (!isset($_COOKIE[$name])) {
            return false; 
        }

        $expired = time() - ($this->options['expires'] ?? PHP_INT_MAX);
    
        if ($key === null || $key === '') {
            $this->saveGlobal();
            return $this->saveContent('', $expired);
        }
    
        $value = $this->getContents();

        if (!is_array($value) || !isset($value[$key])) {
            return false;
        }
    
        unset($value[$key]);

        $finalValue = $this->toValue($value);
        $expiry = null;

        if($finalValue === false){
            $finalValue = '';
            $expiry = $expired;
        }
    
        $this->saveGlobal(null, $value);
        return $this->saveContent($finalValue, $expiry);
    }
    
    /** 
     * {@inheritdoc}
     */
    public function has(?string $key = null): bool
    {
        $name = $this->getName();

        if (!$key && isset($_COOKIE[$name])) {
            return true;
        }

        $value = $this->getContents();

        if(is_array($value) && isset($value[$key])){
            return true;
        }

        return isset($_COOKIE[$key]);
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
    public function getExpiry(bool $returnString = false): int|string
    {
        return $returnString 
            ? gmdate(self::EXPIRES_FORMAT, $this->expires) 
            : $this->expires;
    }

    /** 
     * {@inheritdoc}
     */
    public function isExpired(): bool
    {
        return ($this->expires > 0 && $this->expires < Time::now()->getTimestamp());
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxAge(): int
    {
        return Time::now()->getMaxAge($this->expires);
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
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return self::parseToString(
            $this->getValue(), 
            $this->getPrefixedName(),
            [
                'expires' => $this->getExpiry(),
                'path' => $this->getPath(),
                'domain' => $this->getDomain(),
                'secure' => $this->isSecure(),
                'httponly' => $this->isHttpOnly(),
                'samesite' => $this->getSameSite()
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getString(): string
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
            return $name;
        } 

        $search = str_split(self::RESERVED_CHAR_LIST);
        $name .= str_replace($search, array_map('rawurlencode', $search), $this->getName());

        return $name;
    }

    /**
     * {@inheritdoc}
     */
    public static function newFromString(
        string $cookie, 
        bool $raw = false, 
        CookieConfig|array $options = []
    ): CookieInterface
    {
        return new self(...self::parseFromString(
            $cookie, 
            $raw, 
            self::parseOptions($options)
        ));
    }

    /**
     * {@inheritdoc}
     */
    public static function newFromArray(
        array|object $cookies, 
        CookieConfig|array $options = []
    ): CookieInterface
    {
        $options = self::parseOptions($options);
        $name  = null;
        $value = null;
        $raw = false;

        foreach ($cookies as $key => $val) {
            $line = strtolower($key);
            $raw = ($line === 'raw' && $val);

            if($line === 'name'){
                $name = $val;
                continue;
            }

            if($line === 'value'){
                $value = $val;
                continue;
            }

            $options[$line] = $val;
        }
        
        return new self(
            $raw ? urldecode($name) : $name, 
            $value, 
            $options
        );
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->__toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): mixed
    {
        return $this->__toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function __toArray(): array
    {
        return [
            ...$this->options,
            'name'   => $this->name,
            'value'  => $this->value,
            'prefix' => $this->prefix,
            'raw'    => $this->raw,
        ];
    }

    /**
     * Validates the cookie name per RFC 2616.
     *
     * If `$raw` is true, names should not contain invalid characters as `setrawcookie()` will reject it.
     *
     * @return void
     * @throws CookieException If Invalid Cookie Name or empty string is passed.
     */
    private function validateName(): void
    {
        if ($this->name === '') {
            throw CookieException::rethrow('empty_name');
        }

        if ($this->raw && strpbrk($this->name, self::RESERVED_CHAR_LIST) !== false) {
            throw CookieException::rethrow('invalid_name', $this->name);
        }
    }

    /**
     * Validates the special prefixes if some attribute requirements are met.
     *
     * @return void
     * @throws CookieException If invalid attribute prefix are passed.
     */
    private function validatePrefix(): void
    {
        if (str_starts_with($this->prefix, '__Secure-') && !$this->secure) {
            throw CookieException::rethrow('invalid_secure_prefix');
        }

        if (str_starts_with($this->prefix, '__Host-') && (!$this->secure || $this->domain !== '' || $this->path !== '/')) {
            throw CookieException::rethrow('invalid_host_prefix');
        }
    }

    /**
     * Validates the `SameSite` to be within the allowed types.
     *
     * @return void
     * @throws CookieException If invalid same-site was passed.
     * 
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite
     */
    private function validateSameSite(): void
    {
        $sameSite = $this->sameSite;

        if ($sameSite === '') {
            $sameSite = self::DEFAULT_OPTIONS['samesite'];
        }

        $sameSite = strtolower($sameSite);

        if (!in_array($sameSite, ['none', 'lax', 'strict'], true)) {
            throw CookieException::rethrow('invalid_same_site', $sameSite);
        }

        if ($sameSite === 'none' && !$this->secure) {
            throw CookieException::rethrow('invalid_same_site_none');
        }
    }

    /**
     * Pass options to variable.
     * 
     * @param string $key option key.
     * 
     * @return mixed Return the option value after passing.
     */
    private function passOption(string $key): mixed 
    {
        if(isset($this->options[$key]) && $this->options[$key] !== ''){
            return $this->options[$key];
        }

        return $this->options[$key] = self::DEFAULT_OPTIONS[$key];
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

            if(is_array($_COOKIE[$name])){
                return $_COOKIE[$name];
            }

            if (is_string($_COOKIE[$name]) && json_validate($_COOKIE[$name])) {
               return json_decode($_COOKIE[$name], true) ?? [];
            }

            return $_COOKIE[$name];
        }

        return null;
    }

    /**
     * Save delete data from cookie storage.
     *
     * @param string $value contents
     * @param ?int $expiry cookie expiration
     * @param array $options cookie options
     * 
     * @return bool Return true if cookie was saved.
     */
    private function saveContent(string $value = '', ?int $expiry = null, array $options = []): bool
    {
        $name = $this->getName();

        if($options === []){
            $options = $this->options;
            $options['expires'] = ($expiry ?? $this->options['expires']);
        }

        $isRaw = $options['raw'];
        unset($options['raw'], $options['prefix']);

        return $isRaw 
            ? setrawcookie($name, $value, $options) 
            : setcookie($name, $value, $options);
    }

    /**
     * Save cookie to global variables
     *
     * @param string $name The cookie name.
     * @param string $value The contents.
     * 
     * @return void
     */
    private function saveGlobal(?string $name = null, mixed $value = ''): void 
    {
        $name ??= $this->name;
        $_COOKIE[$name] = $value;
        $this->value = $value;
    }
}