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

use \App\Controllers\Config\Cookie as CookieConfig;

interface CookieInterface
{
     /**
     * Cookies will be sent in all contexts, i.e in responses to both
     * third-party and cross-origin requests. If `SameSite=None` is set,
     * the cookie `Secure` attribute must also be set (or the cookie will be blocked).
     */
    public const NONE = 'none';

    /**
     * Cookies are not sent on normal cross-site sub requests (for example to
     * load images or frames into a third party site), but are sent when a
     * user is navigating to the origin site (i.e. when following a link).
     */
    public const LAX = 'lax';

    /**
     * Cookies will only be sent in a third-party context and not be sent
     * along with requests initiated by third party websites.
     */
    public const STRICT = 'strict';

    /**
     * Expires date string format.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Date
     * @see https://tools.ietf.org/html/rfc7231#section-7.1.1.2
     */
    public const EXPIRES_FORMAT = 'D, d-M-Y H:i:s T';

    /** 
     * Set key and value to session
     * 
     * @param string $key key to set
     * @param mixed $value value to set
     * 
     * @return Cookie new Cookie instance
    */
    public function set(mixed $name, mixed $value, array $options = []): Cookie;

    /** 
     * Set key and value to session
     * 
     * @param string $key key to set
     * @param mixed $value value to set
     * 
     * @return self
    */
    public function setValue(mixed $value): self;

    /** 
     * get data from session
     * 
     * @param string $index key to get
     * 
     * @return mixed
    */
    public function get(?string $key = null): mixed;

    /** 
     * Check if key exists in session
     * 
     * @param string $key
     * 
     * @return bool
    */
    public function has(?string $key = null): bool;

    /**
     * Remove key from the current session storage by passing the key.
     *
     * @param string $index Key index to unset.
     * 
     * @return self
    */
    public function delete(?string $key = null): self;

    /**
     * Set cookie options 
     * 
     * @param string|array $options Options array or CookieConfig class name
     * 
     * @return self $this
    */
    public function setOptions(string|array $options): self;

     /**
     * Create a new Cookie instance from a `Set-Cookie` header.
     *
     * @param string $cookie Cookie header string 
     * @param bool $raw Is raw cookie
     * 
     * @return Cookie New Cookie instance
     */
    public function setFromString(string $cookie, bool $raw = false): Cookie;

     /** 
     * Get cookie id
     * 
     * @return string
    */
    public function getId(): string;

     /** 
     * Get cookie prefix
     * 
     * @return string
    */
    public function getPrefix(): string;

     /**
     * Gets the cookie name prepended with the prefix
     * 
     * @return string
     */
    public function getPrefixedName(): string;

    /** 
     * Get cookie name
     * 
     * @return string
    */
    public function getName(): string;

    /** 
     * Get cookie value
     * 
     * @return mixed
    */
    public function getValue(): mixed;

   /** 
     * Get cookie expiry
     * 
     * @return int
    */
    public function getExpiry(): int;

    /** 
     * Get cookie expiry time as string
     * 
     * @return string
    */
    public function getExpiryString(): string;

    /**
     * Checks if the cookie is expired.
     */
    public function hasExpired(): bool;

    /**
     * Gets the "Max-Age" cookie attribute.
     * 
     * @return int
     */
    public function getMaxAge(): int;

     /** 
     * Get cookie path
     * 
     * @return string
    */
    public function getPath(): string;

    /** 
     * Get cookie domain
     * 
     * @return string
    */
    public function getDomain(): string;

     /** 
     * Get cookie security attribute
     * 
     * @return bool
    */
    public function isSecure(): bool;

    /** 
     * Get cookie httpOnly attribute
     * 
     * @return bool
    */
    public function isHttpOnly(): bool;

   /** 
     * Get cookie sameSite attribute
     * 
     * @return string
    */
    public function getSameSite(): string;

     /** 
     * Get cookie raw attribute
     * 
     * @return bool
    */
    public function isRaw(): bool;

     /** 
     * Get cookie options
     * 
     * @return array<string, mixed> 
    */
    public function getOptions(): array;

     /**
     * Returns the Cookie as a header value.
     * 
     * @return string
    */
    public function getString(): string;

     /**
     * Returns the Cookie as a header value.
     * 
     * @return string
    */
    public function toString(): string;

     /** 
     * Check if cookie name has prefix
     * 
     * @param ?string $name 
     * 
     * @return bool
    */
    public function hasPrefix(?string $name = null): bool;

    /**
     * Returns the string representation of the Cookie object.
     *
     * @return string
     */
    public function __toString();

    /**
     * Returns the array representation of the Cookie object.
     *
     * @return array<string, bool|int|string>
     */
    public function toArray(): array;
}