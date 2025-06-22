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
use \Luminova\Exceptions\CookieException;
use \Luminova\Exceptions\FileException;

interface CookieJarInterface
{
    /**
     * Create a new cookie instance.
     *
     * @param array<string,array<string,mixed>> $cookies An associative array of cookies where
     *        the key is the cookie name and the value contains the cookie data and options.
     * @param array<string,mixed> $config Optional configurations or settings to apply to the 
     *        new or updated cookie.
     * 
     * @return static<CookieJarInterface> Returns the created or updated cookie jar instance.
     * @throws CookieException If invalid source file location is provided.
     * @throws FileException If the `$from` is provided as an array, the cookie jar is not in read-only mode, and writing the cookies to the file fails.
     */
    public static function newCookie(array $cookies, array $config = []): CookieJarInterface;

    /**
     * Set a cookie value by name with additional options.
     * 
     * @param mixed $name The name of the cookie.
     * @param mixed $value The value of the cookie.
     * @param array $options An array of cookie options.
     * 
     * @return static<CookieJarInterface> Return instance of Cookie Jar.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function set(mixed $name, mixed $value, array $options = []): CookieJarInterface;

    /**
     * Set the value of a cookie.
     * 
     * @param mixed $value The value to set.
     * 
     * @return static<CookieJarInterface> Return instance of Cookie Jar.
     * @throws CookieException Throws if called without cookie name specified.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function setValue(mixed $value): self;

    /**
     * Set cookie options.
     * 
     * @param CookieConfig|array $options An array of cookie options or cookie config class object.
     * 
     * @return static<CookieJarInterface> Return instance of Cookie Jar.
     * @throws CookieException Throws if called without cookie name specified.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function setOptions(CookieConfig|array $options): self;

    /**
     * Set a custom path for retrieving request cookies.
     * This path will be used when filtering cookies instead of extracting the path from a request URL.
     *
     * @param string $path The custom path to set.
     * 
     * @return static<CookieJarInterface> Returns the current instance for method chaining.
     * > **Note:** If set a custom cookie request path, you must also set domain `setCookieDomain`.
     */
    public function setCookiePath(string $path): self;

    /**
     * Set a custom domain for retrieving request cookies.
     * This domain will be used when filtering cookies instead of extracting the domain from a request URL.
     *
     * @param string $domain The custom domain to set.
     * 
     * @return static<CookieJarInterface> Returns the current instance for method chaining.
     * > **Note:** If set a custom cookie request domain, you must also set path `setCookiePath`.
     */
    public function setCookieDomain(string $domain): self;

    /**
     * Set cookie from an array.
     * 
     * @param array<string,array<string,mixed>> $cookies The array of cookies to set.
     * 
     * @return static<CookieJarInterface> Return instance of Cookie Jar.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function setCookies(array $cookies): self;

    /**
     * Get cookie protected properties.
     * 
     * @param string $property property to retrieve.
     * 
     * @return mixed Return property value.
     * @throws CookieException Throws if property does not exist or if called without cookie name specified.
     * @internal
     */
    public function __get(string $property): mixed;

    /**
     * Retrieve cookie information by it's name.
     * 
     * @param string|null $name The cookie name to retrieve.
     * 
     * @return array<string,mixed> Return the value of the cookie name or entire cookies if null is passed.
     */
    public function get(string $name): array;

    /** 
     * Set a specify cookie name to make ready for access or manage.
     * 
     * @param string $name The name of cookie to access.
     * 
     * @return static<CookieJarInterface> Return instance of cookie class.
     * 
     * @example - Manage Cookie By Name:
     * 
     * ```php
     * $cookie = $cookieJar->getCookie('my-cookie');
     * echo $cookie->getPrefix();
     * echo $cookie->getDomain();
     * echo $cookie->toString();
     * ```
     */
    public function getCookie(string $name): self;

    /**
     * Retrieves the entire cookies from the source.
     *
     * If cookies have already been loaded, they are returned directly.
     * If a file path is provided, the cookies are loaded from the file.
     * If no file path is provided, an empty array is returned.
     *
     * @return array<string,array<string,mixed>> Return an associative array of cookies where keys are the cookie names.
     */
    public function getCookies(): array;

    /** 
     * Retrieves an array of all cookie names in cookie jar.
     * 
     * @return array<int,string> Return all loaded cookie names.
     */
    public function getCookieNames(): array;

    /** 
     * Retrieves the cookie storage jar filename.
     * 
     * @return string|null Return the cookie filepath and filename or null.
     */
    public function getCookieFile(): ?string;

    /** 
     * Retrieves the cookie initialization configuration options.
     * 
     * @return array<string,mixed> Return an array of cookie global configuration.
     */
    public function getConfig(): array;

    /**
     * Retrieve all cookies for a specific domain and path.
     * Only unexpired cookies are returned.
     *
     * @param string $domain The domain to filter cookies.
     * @param string $path   The path to filter cookies. Defaults to '/'.
     * 
     * @return array<string,array> Return an array of cookies for the specified domain and path.
     * @throws CookieException If the domain is empty.
     */
    public function getCookieByDomain(string $domain, string $path = '/'): array;

    /**
     * Retrieve cookies for a specific domain and path as a formatted string.
     * Only unexpired cookies are included.
     *
     * @param string|null $url The request URL to extract domain and path.
     *                         If null, defaults to set domain (`setCookieDomain`) and path (`setCookiePath`).
     * @param bool $metadata Whether to include metadata (e.g., `Expires`, `Max-Age` etc...).
     *              This can be used for testing purposes or to emulate a browser's behavior.
     * 
     * @return string|null Return a semicolon-separated cookie string, or null if no cookies match.
     * @throws CookieException If no valid domain is found or set.
     */
    public function getCookieStringByDomain(?string $url = null, bool $metadata = false): ?string;

    /**
     * Get the cookie name if specified.
     *
     * @return string|null Return the name of the cookie or null string if not specified.
     */
    public function getName(): ?string;

    /**
     * Get the options associated with the cookie name if name is specified.
     *
     * @return array<string,mixed> Return the options associated with the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     * 
     * @example - Specify Name:
     * 
     * ```php
     * $cookieJar->getCookie('cookie-name')->getOptions();
     * ```
     */
    public function getOptions(): array;

    /**
     * Get an option value associated with the cookie name
     *
     * @param string $key The option key name to retrieve.
     * 
     * @return mixed Return the option value associated with the cookie-option key.
     * @throws CookieException Throws if called without cookie name specified.
     * 
     * @example - Specify Name:
     * 
     * ```php
     * $cookieJar->getCookie('cookie-name')->getOption('expires');
     * ```
     */
    public function getOption(string $key): mixed;

    /**
     * Get the cookie value if name is specified.
     *
     * @param bool $asArray Whether to return value as an array if its a valid json string (default: false).
     * 
     * @return mixed Return the value of the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getValue(bool $asArray = false): mixed;

    /**
     * Get the domain associated with the cookie, if name is specified.
     *
     * @return string Return the domain associated with the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getDomain(): string;

    /**
     * Get the prefix associated with the cookie.
     *
     * @return string The prefix associated with the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getPrefix(): string;

    /**
     * Get the maximum age of the cookie.
     *
     * @return int The maximum age of the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getMaxAge(): int;

    /**
     * Get the path associated with the cookie.
     *
     * @return string The path associated with the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getPath(): string;

    /**
     * Get the SameSite attribute of the cookie.
     *
     * @return string The SameSite attribute of the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getSameSite(): string;

    /**
     * Get the prefixed name of the cookie.
     *
     * @return string The prepended prefix name of the cookie.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getPrefixedName(): string;

    /**
     * Get the cookie expiration time.
     * 
     * @param bool $returnString Whether to retrieve unix-timestamp or formate cookie datetime string
     * 
     * @return string|int Return cookie expiration timestamp or formatted string presentation.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function getExpiry(bool $returnString = false): string|int;

    /**
    * Parse and retrieve cookies from `Set-Cookie` header values.
    *
    * This method processes an array of `Set-Cookie` header strings to extract cookie names, values, 
    * and options. And returns an array containing the extracted cookies.
    *
    * @param array<int,string> $headers An array of `Set-Cookie` header strings to parse.
    * @param bool $raw Whether to handle cookies as raw values (default: false).
    * @param array<string,mixed> $default Optional default options to apply to cookies.
    * 
    * @return array<string,array<string,mixed>> An associative array of extracted cookies, where 
    *         the key is the cookie name and the value contains the cookie data and options.
    * 
    * @example - Example:
    * ```php
    * $headers = [
    *     "SessionID=abc123; Path=/; HttpOnly",
    *     "UserID=42; Secure; Max-Age=3600",
    * ];
    * 
    * $defaultOptions = [
    *     'domain' => 'example.com',
    * ];
    * 
    * // Parse cookies from headers
    * $cookies = $cookieHandler->getFromHeader($headers, false, $defaultOptions);
    * 
    * foreach ($cookies as $name => $cookie) {
    *     echo "Cookie: $name, Value: {$cookie['value']}" . PHP_EOL;
    * }
    * ```
    */
    public static function getFromHeader(
        array $headers, 
        bool $raw = false, 
        array $default = []
    ): array;

    /**
    * Parse and retrieve cookies from global variable `$_COOKIE` values.
    *
    * This method processes an array of `Cookies` key-pir value to extract cookie names, values, 
    * And returns an array containing the extracted cookies.
    *
    * @param array<string,mixed> $cookies An array of `$_COOKIE` to parse.
    * @param bool $raw Whether to handle cookies as raw values (default: false).
    * @param array<string,mixed> $default Optional default options to apply to cookies.
    * 
    * @return array<string,array<string,mixed>> An associative array of extracted cookies, where 
    *         the key is the cookie name and the value contains the cookie data and options.
    * 
    * @example - Parse cookies from global:
    
    * ```php
    * $cookies = $cookieHandler->getFromGlobal($_COOKIE, false, $defaultOptions);
    * 
    * foreach ($cookies as $name => $cookie) {
    *     echo "Cookie: $name, Value: {$cookie['value']}" . PHP_EOL;
    * }
    * ```
    */
    public static function getFromGlobal(
        array $cookies, 
        bool $raw = false, 
        array $default = []
    ): array;

    /** 
     * Count the number of cookies in storage.
     * 
     * @return int Return the number of cookies in storage.
     */
    public function count(): int;

    /**
     * Calculates the total size of all cookies in bytes.
     *
     * @return int Return total size of the cookies in bytes.
     */
    public function size(): int;

    /**
     * Check if a cookie with name exists.
     * 
     * @param string $name The key of the cookie to check.
     * 
     * @return bool Return true if the cookie name exists, false otherwise.
     */
    public function has(string $name): bool;

    /**
     * Check if a cookie name has a prefix.
     * 
     * @param string|null $name An optional cookie name to check for prefix, 
     *              if null will use name from `getCookie()` method or from config name key.
     * 
     * @return bool Return true if the cookie name has a prefix, false otherwise.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function hasPrefix(?string $name = null): bool;

    /**
     * Clear the entire cookie stored in file jar.
     * 
     * @return bool Return true if the cookie was cleared, false otherwise.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function clear(): bool;

    /**
     * Remove a cookie by name.
     * 
     * @param string|null $name An optional cookie name to remove, 
     *              if null will use name from `getCookie()` method or from config name key.
     * 
     * @return bool Return true if the cookie was removed, false otherwise.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function delete(?string $name = null): bool;

    /** 
     * Force mark a cookie as expired.
     * 
     * @param string|null $name An optional cookie name to expire, 
     *              if null will use name from `getCookie()` method or from config name key.
     * 
     * @return bool Return true if cookie has expired, otherwise false.
     * @throws FileException If writing the cookies to the file fails.
     */
    public function forceExpire(?string $name = null): bool;

    /**
     * Check if the cookie is secure.
     *
     * @return bool Return true if the cookie is secure, false otherwise.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function isSecure(): bool;

    /**
     * Check if the cookie is HTTP-only.
     *
     * @return bool Return true if the cookie is HTTP-only, false otherwise.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function isHttpOnly(): bool;

    /**
     * Check if the cookie value is raw.
     *
     * @return bool return true if the cookie value is raw, false otherwise.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function isRaw(): bool;

    /** 
     * Check Whether the cookie include subdomains support.
     * 
     * @return bool Return true if subdomain is included, false otherwise.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function isSubdomains(): bool;

    /** 
     * Check if the cookie has expired.
     * 
     * @return bool Return true if the cookie has expired otherwise false.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function isExpired(): bool;

    /**
     * Checks if the cookie object is empty.
     *
     * This method determines whether the cookie jar contains any cookies.
     * It returns true if there are no cookies stored, and false otherwise.
     *
     * @return bool Returns true if the cookie jar is empty, false otherwise.
     */
    public function isEmpty(): bool;

    /**
     * Checks if the cookie configuration is set to read-only mode.
     *
     * @return bool Returns `true` if the cookie jar is read-only, otherwise `false`.
     */
    public function isReadOnly(): bool;

    /** 
     * Check Whether cookie configuration is set to emulate browser behavior during requests, 
     * by setting cookies in the request header object `Cookie` instead of `CURLOPT_COOKIE`.
     * 
     * @return bool Return true if should emulate browser, false otherwise.
     */
    public function isEmulateBrowser(): bool;

    /** 
     * Check Whether cookie configuration is marked as a new session.
     * 
     * @return bool Return true if marked as a new session, false otherwise.
     */
    public function isNewSession(): bool;

    /** 
     * Check Whether cookie configuration is set to be read and write as netscape cookie format.
     * 
     * @return bool Return true if the cookie should be in netscape cookie format, false otherwise.
     */
    public function isNetscapeCookie(): bool;

    /**
     * Get the cookie as a string header value.
     * 
     * @return string Return the header string representation of the Cookie object.
     * @param bool $metadata Whether to include metadata (e.g., `Expires`, `Max-Age` etc...).
     *              This can be used for testing purposes or to emulate a browser's behavior.
     * 
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function toString(bool $metadata = false): string;

    /**
     * Convert the cookie to a string header value.
     * 
     * @return string Return the header string representation of the Cookie object.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function __toString(): string;

    /**
     * Convert the cookie to an array.
     * 
     * @return array<string,mixed> Return an array representation of the Cookie object.
     * @throws CookieException Throws if called without cookie name specified.
     */
    public function toArray(): array;

    /**
     * Converts an array of cookies to a Netscape-formatted cookie file string.
     *
     * This method generates a string representation of cookies in the Netscape cookie file format.
     * If no cookies are provided or available, it returns null.
     *
     * @param array|null $cookies An optional array of cookies to convert. If null, uses the instance's cookies.
     *                            Each cookie should be an associative array with 'value' and 'options' keys.
     *
     * @return string Return a string containing the Netscape-formatted cookies, or an empty string if no cookies are available.
     */
    public function toNetscapeCookies(?array $cookies = null): string;
}