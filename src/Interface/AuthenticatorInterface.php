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

use \Luminova\Base\BaseCache;
use \Psr\Cache\CacheItemPoolInterface;
use \Psr\SimpleCache\CacheInterface;
use \Luminova\Exceptions\EncryptionException;
use \Luminova\Exceptions\InvalidArgumentException;
use \DateTimeZone;

interface AuthenticatorInterface
{
    /**
     * Get the label used in the TOTP configuration, optionally URL-encoded.
     *
     * @param bool $encode Whether to URL-encode the label.
     * 
     * @return string Return the label in the format 'Issuer:AccountName'.
     */
    public function getLabel(bool $encode = false): string;

    /**
     * Get the account name associated with this instance.
     *
     * @return string Return the account name.
     */
    public function getAccount(): string;

    /**
     * Get the shared secret key.
     *
     * @return string|null Return the shared secret key.
     */
    public function getSecret(): ?string;

    /**
     * Generate a QR code URL for configuring Google Authenticator.
     *
     * @return string Return the URL for the QR code.
     * @throws EncryptionException If called without a valid authentication secret.
     */
    public function getQRCodeUrl(): string;

    /**
     * Initialize a Google Authenticator instance with the necessary parameters.
     *
     * @param string $secret The shared secret key for TOTP generation.
     * 
     * @return AuthenticatorInterface Return the authenticator client instance.
     */
    public function setSecret(string $secret): self;

    /**
     * Set the timezone for time-based calculations.
     *
     * @param DateTimeZone|string|null $timezone The new timezone.
     * 
     * @return AuthenticatorInterface Return the authenticator client instance.
     */
    public function setTimezone(DateTimeZone|string|null $timezone): self;

    /**
     * Set the cache instance for code reuse prevention.
     *
     * @param CacheItemPoolInterface|CacheInterface|BaseCache $cache The instance of PSR cache or Luminova base-cache.
     * 
     * @return AuthenticatorInterface Return the authenticator client instance.
     */
    public function setCache(CacheItemPoolInterface|CacheInterface|BaseCache $cache): self;

    /**
     * Generate a new Base32-encoded secret key.
     *
     * @param int $length The desired length of the secret (default: 16).
     * 
     * @return string Return the generated Base32 secret key.
     * @throws InvalidArgumentException If the length is not positive or not divisible by 8.
     */
    public static function generateSecret(int $length = 16): string;

    /**
     * Verify a given TOTP code against the shared secret.
     *
     * @param string $code The user-provided TOTP code.
     * @param int $discrepancy The allowed time-step discrepancy (default: 1).
     * @param int $timeStep The time step duration in seconds (default: 30).
     *              The time Step will also be used for caching if cache is enabled.
     * 
     * @return bool Return true if the code is valid, false otherwise.
     * @throws EncryptionException If called without a valid authentication secret or an invalid base32 character is found in secret.
     */
    public function verify(string $code, int $discrepancy = 1, int $timeStep = 30): bool;
}