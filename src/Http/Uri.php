<?php
/**
 * Luminova Framework HTTP network request class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\ErrorException;
use \Psr\Http\Message\UriInterface;
use \Stringable;

class Uri implements UriInterface, LazyInterface, Stringable
{
    /**
     * Constructs a new Uri instance with optional components.
     * 
     * @param string $scheme    Optional URI scheme (e.g., "http", "https").
     * @param string $userInfo  Optional user information (e.g., "username:password").
     * @param string $host      Optional host (e.g., "example.com").
     * @param int|null $port    Optional port (e.g., 80, 443, or null for default ports).
     * @param string $path      Optional path (e.g., "/api/resource").
     * @param string $query     Optional query string (e.g., "key=value").
     * @param string $fragment  Optional fragment (e.g., "section1").
     */
    public function __construct(
        private string $scheme = '',
        private string $userInfo = '',
        private string $host = '',
        private ?int $port = null,
        private string $path = '',
        private string $query = '',
        private string $fragment = ''
    ) {}

    /**
     * Creates a new Uri instance from an array of URI components.
     * 
     * @param array<string,mixed> $parts An associative array of URI components.
     * 
     * @return self Return a new Uri instance populated with the provided array values.
     * 
     * Supported keys in the array include:
     *   - 'scheme'   (string)   Optional. The scheme of the URI.
     *   - 'user'     (string)   Optional. The username for the URI.
     *   - 'pass'     (string)   Optional. The password for the URI.
     *   - 'host'     (string)   Optional. The host of the URI.
     *   - 'port'     (int|null) Optional. The port number.
     *   - 'path'     (string)   Optional. The path segment of the URI.
     *   - 'query'    (string)   Optional. The query string.
     *   - 'fragment' (string)   Optional. The fragment identifier.
     *
     * @example
     * $uri = Uri::fromArray([
     *     'scheme' => 'https',
     *     'user'   => 'user123',
     *     'pass'   => 'pass456',
     *     'host'   => 'example.com',
     *     'path'   => '/index.php',
     *     'query'  => 'id=42',
     *     'fragment' => 'top'
     * ]);
     *
     * echo $uri; // Outputs: https://user123:pass456@example.com/index.php?id=42#top
     */
    public static function fromArray(array $parts): self
    {
        return new self(
            scheme: $parts['scheme'] ?? '',
            userInfo: ($parts['user'] ?? '') . (isset($parts['pass']) ? ':' . $parts['pass'] : ''),
            host: $parts['host'] ?? '',
            port: $parts['port'] ?? null,
            path: $parts['path'] ?? '',
            query: ltrim($parts['query'] ?? '', '?'),
            fragment: ltrim($parts['fragment'] ?? '', '#')
        );
    }

    /**
     * Retrieves the URI scheme component.
     * 
     * @return string Return the URI scheme (e.g., "http", "https").
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * Retrieves the URI authority component.
     * 
     * @return string Return the URI authority, including user info, host, and port.
     */
    public function getAuthority(): string
    {
        $authority = (($this->userInfo !== '') 
            ? $this->userInfo . '@' . $this->host
            : $this->host
        );

        $authority .= ($this->port !== null) 
            ? ':' . $this->port
            : '';
        
        return $authority;
    }

    /**
     * Retrieves the user information component.
     * 
     * @return string Return the user information (e.g., "username:password").
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * Retrieves the host component.
     * 
     * @return string Return the host (e.g., "example.com").
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Retrieves the port component.
     * 
     * @return int|null Return the port number or null if no port is specified.
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Retrieves the path component.
     * 
     * @return string Return the path (e.g., "/api/resource").
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Retrieves the query string component.
     * 
     * @return string Return the query string (e.g., "key=value").
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Retrieves the fragment component.
     * 
     * @return string Return the fragment (e.g., "section1").
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * Converts the URI object to a string representation.
     * 
     * @return string Return the full URI as a string.
     */
    public function toString(): string
    {
        return $this->getString();
    }

    /**
     * Converts the URI's host to its ASCII representation using IDN (Internationalized Domain Names) conversion.
     *
     * This method converts the host component of the URI to its ASCII representation,
     * which is necessary for proper handling of internationalized domain names.
     * It uses the `idn_to_ascii` function with the specified options for the conversion.
     *
     * @param int $option The option flag for IDN conversion. Defaults to IDNA_DEFAULT.
     *                    See the PHP manual for possible values (e.g., IDNA_NONTRANSITIONAL_TO_ASCII).
     *
     * @return string Return the full URI string with the host converted to its ASCII representation.
     * @throws ErrorException If IDN support is unavailable or if the conversion fails.
     */
    public function toAsciiIdn(int $option = IDNA_DEFAULT): string
    {
        if (!function_exists('idn_to_ascii') || !defined('INTL_IDNA_VARIANT_UTS46')) {
            throw new ErrorException(
                'IDN support is unavailable: Install the `ext-intl` extension or the `polyfill-intl-idn` library.',
                ErrorException::NOT_SUPPORTED
            );
        }

        $info = [];
        $host = idn_to_ascii(
            $this->host,
            $option,
            INTL_IDNA_VARIANT_UTS46,
            $info
        );

        if ($host === false) {
            throw new ErrorException(self::getIdnError($info['errors'] ?? 0));
        }
     
        $authority = ($this->userInfo !== '') ? $this->userInfo . '@' . $host : $host;
        $authority .= ($this->port !== null) ? ':' . $this->port : '';
        return $this->getString($authority);
    }

    /**
     * Returns a new instance with the specified scheme.
     * 
     * @param string $scheme The new scheme (e.g., "http", "https").
     * 
     * @return UriInterface Return a new instance with the updated scheme.
     */
    public function withScheme(string $scheme): UriInterface
    {
        $clone = clone $this;
        $clone->scheme = $scheme;
        return $clone;
    }

    /**
     * Returns a new instance with the specified user info.
     * 
     * @param string $user The username.
     * @param string|null $password The password (optional).
     * @return UriInterface Return a new instance with the updated user info.
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $clone = clone $this;
        $clone->userInfo = $password ? "$user:$password" : $user;
        return $clone;
    }

    /**
     * Returns a new instance with the specified host.
     * 
     * @param string $host The new host (e.g., "example.com").
     * @return UriInterface Return a new instance with the updated host.
     */
    public function withHost(string $host): UriInterface
    {
        $clone = clone $this;
        $clone->host = $host;
        return $clone;
    }

    /**
     * Returns a new instance with the specified port.
     * 
     * @param int|null $port The new port or null for no port.
     * @return UriInterface Return a new instance with the updated port.
     */
    public function withPort(?int $port): UriInterface
    {
        $clone = clone $this;
        $clone->port = $port;
        return $clone;
    }

    /**
     * Returns a new instance with the specified path.
     * 
     * @param string $path The new path (e.g., "/api/resource").
     * @return UriInterface Return a new instance with the updated path.
     */
    public function withPath(string $path): UriInterface
    {
        $clone = clone $this;
        $clone->path = $path;
        return $clone;
    }

    /**
     * Returns a new instance with the specified query string.
     * 
     * @param string $query The new query string (e.g., "key=value").
     * @return UriInterface Return a new instance with the updated query string.
     */
    public function withQuery(string $query): UriInterface
    {
        $clone = clone $this;
        $clone->query = $query;
        return $clone;
    }

     /**
     * Returns a new instance with the specified fragment.
     * 
     * @param string $fragment The new fragment (e.g., "section1").
     * @return UriInterface Return a new instance with the updated fragment.
     */
    public function withFragment(string $fragment): UriInterface
    {
        $clone = clone $this;
        $clone->fragment = $fragment;
        return $clone;
    }

    /**
     * {@inheritdoc}
     * 
     * @ignore 
     * @internal
     */
    public function __toString(): string
    {
        return $this->getString();
    }

    /**
     * Build URI's as string.
     * 
     * @param string $authority The authority.
     * 
     * @return string Return full URL.
     */
    private function getString(?string $authority = null): string 
    {
        $uri = $this->scheme ? $this->scheme . '://' : '';
        $uri .= $authority ?? $this->getAuthority();
        $uri .= $this->path;
        $uri .= $this->query ? '?' . $this->query : '';
        $uri .= $this->fragment ? '#' . $this->fragment : '';
        return $uri;
    }

    /**
     * Generates an error message for IDN (Internationalized Domain Names) conversion failures.
     *
     * @param int $error The error code returned from the IDN conversion process.
     *
     * @return string A formatted error message detailing the IDN conversion failure,
     *                including specific IDNA error constants if applicable.
     */
    private static function getIdnError($error): string
    {
        $errors = array_filter(
            array_keys(get_defined_constants()),
            fn (string $name): bool => str_starts_with($name, 'IDNA_ERROR_')
        );

        $messages = [];
        foreach ($errors as $errorConstant) {
            if ($error & constant($errorConstant)) {
                $messages[] = $errorConstant;
            }
        }

        $message = 'IDN conversion failed: Unable to convert the host to ASCII.';

        if ($messages) {
            $message .= ' Errors: ' . implode(', ', $messages);
        }

        return $message;
    }
}