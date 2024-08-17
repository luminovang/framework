<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \App\Config\Cookie as CookieConfig;

interface CookieInterface
{
  /**
   * Set cookie options.
   * 
   * @param CookieConfig|array $options An array of cookie options or cookie config class object.
   * 
   * @return CookieInterface This Cookie instance.
   */
  public function setOptions(CookieConfig|array $options): self;

  /**
   * Create a new Cookie instance from a `Set-Cookie` header.
   * 
   * @param string $cookie The cookie header string.
   * @param bool $raw Indicates if the cookie is raw.
   * 
   * @return CookieInterface A new Cookie instance.
   */
  public function newFromString(string $cookie, bool $raw = false): CookieInterface;

  /**
   * Set a cookie.
   * 
   * @param mixed $name The name of the cookie.
   * @param mixed $value The value of the cookie.
   * @param array $options An array of cookie options.
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
   * Retrieve the value of a cookie.
   * 
   * @param string|null $key The key of the cookie to retrieve.
   * 
   * @return mixed The value of the cookie.
   */
  public function get(?string $key = null): mixed;

  /**
   * Check if a cookie exists.
   * 
   * @param string|null $key The key of the cookie to check.
   * 
   * @return bool True if the cookie exists, false otherwise.
   */
  public function has(?string $key = null): bool;

  /**
   * Remove a cookie.
   * 
   * @param string|null $key The key of the cookie to remove.
   * 
   * @return CookieInterface This Cookie instance.
   */
  public function delete(?string $key = null): self;

 /**
   * Get the name of the cookie.
   *
   * @return string The name of the cookie.
   */
  public function getName(): string;

  /**
   * Get the options associated with the cookie.
   *
   * @return array The options associated with the cookie.
   */
  public function getOptions(): array;

  /**
   * Get the value of the cookie.
   *
   * @return mixed The value of the cookie.
   */
  public function getValue(): mixed;

  /**
   * Get the domain associated with the cookie.
   *
   * @return string The domain associated with the cookie.
   */
  public function getDomain(): string;

  /**
   * Get the prefix associated with the cookie.
   *
   * @return string The prefix associated with the cookie.
   */
  public function getPrefix(): string;

  /**
   * Get the maximum age of the cookie.
   *
   * @return int The maximum age of the cookie.
   */
  public function getMaxAge(): int;

  /**
   * Get the path associated with the cookie.
   *
   * @return string The path associated with the cookie.
   */
  public function getPath(): string;

  /**
   * Get the SameSite attribute of the cookie.
   *
   * @return string The SameSite attribute of the cookie.
   */
  public function getSameSite(): string;

  /**
   * Check if the cookie is secure.
   *
   * @return bool True if the cookie is secure, false otherwise.
   */
  public function isSecure(): bool;

  /**
   * Check if the cookie is HTTP-only.
   *
   * @return bool True if the cookie is HTTP-only, false otherwise.
   */
  public function isHttpOnly(): bool;

  /**
   * Check if the cookie value is raw.
   *
   * @return bool True if the cookie value is raw, false otherwise.
   */
  public function isRaw(): bool;

  /**
   * Get the ID of the cookie.
   *
   * @return string The ID of the cookie.
   */
  public function getId(): string;

  /**
   * Get the prefixed name of the cookie.
   *
   * @return string The prepended prefix name of the cookie.
   */
  public function getPrefixedName(): string;

  /**
   * Get the cookie expiration time.
   * 
   * @param bool $return_string Return cookie expiration timestamp or string presentation.
   * 
   * @return int|string The expiration time.
  */
  public function getExpiry(bool $return_string = false): int|string;

  /**
   * Get the cookie as a header value.
   * 
   * @return string The cookie as a header value.
   */
  public function getString(): string;

  /** 
   * {@inheritdoc}
  */
  public function hasExpired(): bool;

  /**
   * Get the cookie as a string.
   * 
   * @return string The cookie as a string.
   */
  public function toString(): string;


  /**
   * Check if a cookie name has a prefix.
   * 
   * @param string|null $name The name of the cookie.
   * 
   * @return bool True if the cookie name has a prefix, false otherwise.
   */
  public function hasPrefix(?string $name = null): bool;

  /**
   * Convert the cookie to a string.
   * 
   * @return string The string representation of the Cookie object.
   */
  public function __toString(): string;

  /**
   * Convert the cookie to an array.
   * 
   * @return array<string, mixed> The array representation of the Cookie object.
   */
  public function toArray(): array;
}