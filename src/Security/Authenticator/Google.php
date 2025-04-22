<?php
/**
 * Luminova Framework google authentication class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security\Authenticator;

use \Luminova\Interface\AuthenticatorInterface;
use \Luminova\Time\Time;
use \Luminova\Base\BaseCache;
use \Psr\Cache\CacheItemPoolInterface;
use \Psr\SimpleCache\CacheInterface;
use \Luminova\Exceptions\EncryptionException;
use \Luminova\Exceptions\InvalidArgumentException;
use \DateTimeZone;

class Google implements AuthenticatorInterface
{
    /**
     * The shared secret key.
     * 
     * @var string|null $secret
     */
    private ?string $secret = null;

    /**
     * Initialize a Google Authenticator instance with the necessary parameters.
     *
     * @param string $accountName The account name (e.g., user email or username).
     * @param string $issuer The issuer's name (e.g., your app or website name).
     * @param DateTimeZone|string|null $timezone The timezone for time-based calculations (optional).
     * @param CacheItemPoolInterface|CacheInterface|BaseCache|null $cache The instance of PSR cache or Luminova base-cache for preventing code reuse (optional).
     * 
     * @throws EncryptionException If the issuer or account name contains invalid characters (colon `:`).
     */
    public function __construct(
        private string $accountName,
        private string $issuer,
        private DateTimeZone|string|null $timezone = null,
        private CacheItemPoolInterface|CacheInterface|BaseCache|null $cache = null
    ) 
    {
        if (str_contains($this->issuer . $this->accountName, ':')) {
            throw new EncryptionException(
                "Initialization error: 'Issuer' and 'AccountName' must not contain a colon (':')."
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(bool $encode = false): string
    {
        return $encode
            ? rawurlencode("{$this->issuer}:{$this->accountName}")
            : "{$this->issuer}:{$this->accountName}";
    }

    /**
     * {@inheritdoc}
     */
    public function getAccount(): string
    {
        return $this->accountName;
    }

    /**
     * {@inheritdoc}
     */
    public function getSecret(): ?string
    {
        return $this->secret;
    }

    /**
     * {@inheritdoc}
     */
    public function getQRCodeUrl(): string
    {
        $this->assertSecret(__METHOD__);
        return sprintf(
            "otpauth://totp/%s?secret=%s&issuer=%s",
            $this->getLabel(true),
            $this->secret,
            rawurlencode($this->issuer)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setSecret(string $secret): self
    {
        $this->secret = $secret;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTimezone(DateTimeZone|string|null $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setCache(CacheItemPoolInterface|CacheInterface|BaseCache $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function generateSecret(int $length = 16): string
    {
        if ($length <= 0 || $length % 8 !== 0) {
            throw new InvalidArgumentException(
                "Secret generation error: Length ({$length}) must be a positive number divisible by 8."
            );
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $secret;
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $code, int $discrepancy = 1, int $timeStep = 30): bool 
    {
        $this->assertSecret(__METHOD__);
        $key = ($this->cache === null) ? '' : md5("google_totp_{$this->accountName}_{$code}");

        if ($this->has($key)) {
            return false;
        }

        $currentTime = Time::now($this->timezone)->getTimestamp();

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->generateCode($this->secret, floor($currentTime / $timeStep) + $i);

            if ($this->isValid($calculatedCode, $code)) {
                $this->set($key, true, $timeStep);
                return true;
            }
        }

        return false;
    }

    /**
     * Validates if the calculated TOTP code matches the provided code.
     *
     * This function compares the calculated TOTP code with the provided code. It ensures that
     * the codes match by using the `hash_equals` function, which provides a timing-attack-safe
     * comparison. The function also pads the codes with leading zeros to ensure they have the
     * same length.
     *
     * @param string $calculated The calculated TOTP code.
     * @param string $code The provided TOTP code.
     *
     * @return bool True if the calculated code matches the provided code, false otherwise.
     */
    protected function isValid(string $calculated, string $code): bool 
    {
        return hash_equals(str_pad($calculated, 6, '0', STR_PAD_LEFT), str_pad($code, 6, '0', STR_PAD_LEFT));
    }

    /**
     * Checks if a specific key exists in the cache.
     *
     * This method checks whether the given key is present in the cache.
     * It supports both `PSR-16` (Simple Cache) and `PSR-6` (Cache Pool) interfaces.
     *
     * @param string $key The cache key to check for existence.
     *
     * @return bool `true` if the key exists in the cache, otherwise `false`.
     */
    protected function has(string $key): bool 
    {
        if(!$this->cache || !$key){
            return false;
        }

        return ($this->cache instanceof CacheInterface) 
            ? $this->cache->has($key) 
            : $this->cache->hasItem($key);
    }

    /**
     * Stores a key-value pair in the cache with an expiration time.
     *
     * This method saves a cache item with the specified key and value. The item will be stored
     * in the cache for the specified `timeStep` duration. It supports both `PSR-16` (Simple Cache)
     * and `PSR-6` (Cache Pool) interfaces for compatibility with various caching mechanisms.
     *
     * @param string $key The cache key to store the value under.
     * @param bool $value The value to store (typically `true` for flags).
     * @param int $timeStep The duration in seconds for which the cache item is valid.
     *
     * @return bool `true` if the cache item was successfully stored, otherwise `false`.
     */
    protected function set(string $key, bool $value, int $timeStep): bool 
    {
        if(!$this->cache || !$key){
            return false;
        }

        if ($this->cache instanceof CacheInterface) {
            return $this->cache->set($key, $value, $timeStep);
        }

        if ($this->cache instanceof CacheItemPoolInterface) {
            return $this->cache->save(
                $this->cache->getItem($key)->expiresAfter($timeStep)->set($value)
            );
        }

        return $this->cache->setItem($key, $value, $timeStep);
    }

    /**
     * Generate a TOTP code for a given secret and time step to be used for validation.
     *
     * @param string $secret The shared secret key.
     * @param int $timeWindow The time step for which to generate the code.
     * 
     * @return string The generated TOTP code.
     * @throws EncryptionException If invalid base32 character is found in secret.
     */
    protected function generateCode(string $secret, int $timeWindow): string
    {
        $timeWindowBytes = pack('N*', 0) . pack('N*', $timeWindow);
        $hash = hash_hmac('SHA1', $timeWindowBytes, $this->base32Decode($secret), true);

        $offset = ord(substr($hash, -1)) & 0x0F;
        $binaryCode = (ord($hash[$offset]) & 0x7F) << 24
            | (ord($hash[$offset + 1]) & 0xFF) << 16
            | (ord($hash[$offset + 2]) & 0xFF) << 8
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binaryCode % 10 ** 6), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a Base32-encoded string.
     *
     * @param string $data The Base32-encoded string.
     * 
     * @return string The decoded binary string.
     * @throws EncryptionException If invalid base32 character is found in secret.
     */
    protected function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binaryString = '';

        foreach (str_split($data) as $char) {
            $charIndex = strpos($alphabet, $char);
            if ($charIndex === false) {
                throw new EncryptionException('Invalid Base32 character in secret.');
            }
            $binaryString .= str_pad(decbin($charIndex), 5, '0', STR_PAD_LEFT);
        }

        $binaryData = '';
        foreach (str_split($binaryString, 8) as $byte) {
            $binaryData .= chr(bindec($byte));
        }

        return $binaryData;
    }

    /**
     * Asserts that a secret is set. Throws an exception if the secret is not set.
     *
     * @param string $fn The name of the calling function, used for error context.
     *
     * @throws EncryptionException If the secret is not set.
     */
    private function assertSecret(string $fn): void
    {
        if (!$this->secret) {
            throw new EncryptionException(
                "Secret is required in method '{$fn}'. Call \$auth->setSecret(...) to set authentication secret."
            );
        }
    }
}