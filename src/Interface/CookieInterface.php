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
namespace Luminova\Interface;

use \App\Config\Cookie as CookieConfig;

interface CookieInterface
{
    /**
     * Get cookie protected properties.
     * 
     * @param string $property property to retrieve.
     * @throws CookieException Throws if property does not exist.
     * @internal
     */
    public function __get(string $property): mixed;

    /**
     * Create a new Cookie instance from a `Set-Cookie` header.
     * 
     * @param string $cookie The cookie header string.
     * @param bool $raw Indicates if the cookie is raw.
     * @param CookieConfig|array<string,mixed> $options An array of default cookie options or cookie config class object.
     * 
     * @return CookieInterface A new Cookie instance.
     */
    public static function newFromString(
        string $cookie, 
        bool $raw = false, 
        CookieConfig|array $options = []
    ): CookieInterface;

    /**
     * Create a new Cookie instance from an array or json object.
     * 
     * @param array<string,mixed>|object $cookie An associative array or json object of cookies.
     * @param CookieConfig|array<string,mixed> $options An array of default cookie options or cookie config class object.
     * 
     * @return CookieInterface Return a new Cookie instance.
     */
    public static function newFromArray(
        array|object $cookies, 
        CookieConfig|array $options = []
    ): CookieInterface;

    /**
     * Set a cookie.
     * 
     * @param mixed $name The name of the cookie.
     * @param mixed $value The value of the cookie.
     * @param array<string,mixed> $options An array of cookie options.
     * 
     * @return CookieInterface A new Cookie instance.
     */
    public function set(mixed $name, mixed $value, array $options = []): CookieInterface;

    /**
     * Set the value of a cookie.
     * 
     * @param mixed $value The value to set.
     * 
     * @return CookieInterface This Cookie instance.
     */
    public function setValue(mixed $value): self;

    /**
     * Set cookie options.
     * 
     * @param CookieConfig|array<string,mixed> $options An array of cookie options or cookie config class object.
     * 
     * @return CookieInterface This Cookie instance.
     */
    public function setOptions(CookieConfig|array $options): self;

    /**
     * Retrieve the value from cookie, if value is an array, 
     * pass they key to retrieve specific key-value or null to return the array value.
     * 
     * @param string|null $key Optional key of the cookie to retrieve for array value.
     * 
     * @return mixed Return the value of the cookie.
     */
    public function get(?string $key = null): mixed;

    /**
     * Get the name of the cookie.
     *
     * @return string Return the current cookie name.
     */
    public function getName(): string;

    /**
     * Get the options associated with the cookie.
     *
     * @return array<string,mixed> Return the current options associated with the cookie.
     */
    public function getOptions(): array;

    /**
     * Get the value of the cookie.
     *
     * @return mixed Return the current value of the cookie.
     */
    public function getValue(): mixed;

    /**
     * Get the domain associated with the cookie.
     *
     * @return string Return the current domain associated with the cookie.
     */
    public function getDomain(): string;

    /**
     * Get the prefix associated with the cookie.
     *
     * @return string Return the current prefix associated with the cookie.
     */
    public function getPrefix(): string;

    /**
     * Get the maximum age of the cookie.
     *
     * @return int Return the current maximum age of the cookie.
     */
    public function getMaxAge(): int;

    /**
     * Get the path associated with the cookie.
     *
     * @return string Return the current path associated with the cookie.
     */
    public function getPath(): string;

    /**
     * Get the SameSite attribute of the cookie.
     *
     * @return string Return the current SameSite attribute of the cookie.
     */
    public function getSameSite(): string;

    /** 
     * Check if the cookie has expired.
     * 
     * @return bool Return true if the cookie has expired otherwise false.
     */
    public function isExpired(): bool;

    /**
     * Get the ID of the cookie.
     *
     * @return string Return the current ID of the cookie.
     */
    public function getId(): string;

    /**
     * Get the prefixed name of the cookie.
     *
     * @return string Return prepended prefix name of the cookie.
     */
    public function getPrefixedName(): string;

    /**
     * Get the cookie expiration time.
     * 
     * @param bool $return_string Return cookie expiration timestamp or string presentation.
     * 
     * @return int|string Return the current expiration time/unix-timestamp of the cookie.
     */
    public function getExpiry(bool $return_string = false): int|string;

    /**
     * Get the cookie as a header value.
     * 
     * @return string Return the header string representation of the Cookie object.
     */
    public function getString(): string;

    /**
     * Check if the cookie is secure.
     *
     * @return bool Return true if the cookie is secure, false otherwise.
     */
    public function isSecure(): bool;

    /**
     * Check if the cookie is HTTP-only.
     *
     * @return bool Return true if the cookie is HTTP-only, false otherwise.
     */
    public function isHttpOnly(): bool;

    /**
     * Check if the cookie value is raw.
     *
     * @return bool Return true if the cookie value is raw, false otherwise.
     */
    public function isRaw(): bool;

    /**
     * Check if a cookie exists.
     * 
     * @param string|null $key The key of the cookie to check.
     * 
     * @return bool Return true if the cookie exists, false otherwise.
     */
    public function has(?string $key = null): bool;

    /**
     * Check if a cookie name has a prefix.
     * 
     * @param string|null $name The name of the cookie.
     * 
     * @return bool Return true if the cookie name has a prefix, false otherwise.
     */
    public function hasPrefix(?string $name = null): bool;

    /**
     * Remove a cookie by name or clear all cookies.
     * 
     * @param string|null $key An optional key of the cookie to remove.
     * 
     * @return bool Return true if the cookie was removed, false otherwise.
     */
    public function delete(?string $key = null): bool;

    /**
     * Get the cookie as a string.
     * 
     * @return string Return the header string representation of the Cookie object.
     */
    public function toString(): string;

    /**
     * Convert the cookie to a string.
     * 
     * @return string Return the header string representation of the Cookie object.
     */
    public function __toString(): string;

    /**
     * Convert the cookie to an array.
     * 
     * @return array<string,mixed> Return an array representation of the Cookie object.
     */
    public function toArray(): array;
}