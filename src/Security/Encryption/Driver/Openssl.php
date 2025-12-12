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
namespace Luminova\Security\Encryption\Driver;

use \Throwable;
use \JsonException;
use \App\Config\Encryption;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Interface\EncryptionInterface;
use \Luminova\Exceptions\EncryptionException;

/**
 * Crypt Openssl encryption class.
 */
class Openssl implements EncryptionInterface
{
    /**
     * Cryptography driver version.
     * 
     * @var string VERSION
     */ 
    private const VERSION = 'v1';

    /**
     * @var string $key
     */
    private string $key = '';

    /**
     * @var string $message
     */
    private string $message = '';

    /**
     * @var string $method
     */
    private string $method = 'AES-128-CBC';

    /**
     * @var string $nonce
     */
    private string $nonce = '';

    /**
     * Additional authentication data.
     * 
     * @var string $aad
     */
    private string $aad = '';

    /**
     * @var int $size
     */
    private int $size = 16;

    /**
     * HMAC digest to use
     *
     * @var string $digest
     */
    private string $digest = 'SHA512';

    /**
     * Configuration.
     *
     * @var Encryption $config
     */
    private static ?Encryption $config = null;

    /**
     * Initializes a new OpenSSL encryption driver.
     *
     * A key and cipher method may be provided at construction time,
     * or configured later using `setKey()` and `setMethod()` before
     * performing encryption or decryption.
     *
     * @param string|null $key Encryption key. If omitted, it must be set later.
     * @param string|null $method OpenSSL cipher method. If omitted, it must be set later.
     * @param int $size Fallback key size used when the cipher method
     *        does not expose a block size (default: 16).
     *
     * @throws EncryptionException When an invalid cipher method or block size is used.
     * @see  Luminova\Security\Encryption\Crypter
     *       Helper class that applies application-level encryption configuration.
     * @link https://luminova.ng/docs/0.0.0/encryption/driver Documentation.
     */
    public function __construct(?string $key = null, ?string $method = null, int $size = 16)
    {
        if ($method) {
            $this->setMethod($method, $size);
        }

        if ($key) {
            $this->setKey($key);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setData(string $data): self
    {
        $this->message = $data;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAssociatedData(array|string $aad): self
    {
        if (is_array($aad)) {
            try {
                $aad = json_encode($aad, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new EncryptionException(
                    'Failed to encode associated data.',
                    ErrorCode::JSON_ERROR,
                    $e
                );
            }
        }

        $this->aad = $aad;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setKey(string $key, int $length = 0, ?string $salt = null): self
    {
        self::$config ??= new Encryption();
        $this->digest = self::$config->digest;
        
        $key = hash_hkdf(
            $this->digest, 
            $key, 
            $length, 
            self::$config->keyInfo,
            $salt ?? ''
        );

        if($key === false){
            throw new EncryptionException('Failed to generate openssl hkdf key.');
        }

        $this->key = $key;
        return $this;
    }

    /**
     * {@inheritdoc}
     * 
     * > **Recommendation:**
     * > Set method first before nonce, if you are passing null as nonce for auto generate.
     * > This allows the generate nonce length to be droved from method IV size.
     */
    public function setNonce(?string $nonce = null): self
    {
        $this->nonce = $nonce ?? self::nonce($this->size);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function nonce(int $length, ?string $string = null): string
    {
        try{
            return ($string === null) 
                ? openssl_random_pseudo_bytes($length) 
                : mb_substr($string, 0, $length, '8bit');
        }catch(Throwable $e){
            throw new EncryptionException(
                $e->getMessage(), 
                $e->getCode(),
                $e->getPrevious()
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod(string $method, int $size = 16): self
    {
        $this->method = strtoupper($method);
        $ivSize = openssl_cipher_iv_length($method);

        if($ivSize === false){
            $this->size = $size;
            return $this;
        }

        $this->size = $ivSize;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(): string
    {
        if (!$this->valid()) {
            throw new EncryptionException('Invalid contraption params!');
        }

        try{
            $tag = '';
            $crypt = openssl_encrypt(
                $this->message, 
                $this->method, 
                $this->key, 
                OPENSSL_RAW_DATA, 
                $this->nonce,
                $tag,
                $this->aad
            );

            if ($crypt === false) {
                throw new EncryptionException('Encryption failed.');
            }

            $payload = [
                'v' => self::VERSION,
                'd' => 'openssl',
                'm' => $this->method,
                'n' => base64_encode($this->nonce),
                'c' => base64_encode($crypt)
            ];

            if ($this->isAead()) {
                $payload['t'] = base64_encode($tag);
            }

            if ($this->isMac()) {
                $digest = $this->macAlgo();

                $hash = hash_hmac($digest, $crypt . $this->nonce, $this->key, true);
                $payload['h'] = base64_encode($hash);
            }

            return base64_encode(
                json_encode($payload, JSON_THROW_ON_ERROR)
            );
        } catch (JsonException $e) {
            throw new EncryptionException(
                'Failed to encode encryption payload.',
                ErrorCode::JSON_ERROR,
                $e
            );
        } catch (Throwable $e) {
            throw new EncryptionException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decrypt(): string
    {
        if (!$this->valid()) {
            throw new EncryptionException('Invalid decryption parameters!');
        }

        $data = $this->decode();

        $isLegacy = $data['isLegacy'] ?? false;

        if ($isLegacy && mb_strlen($data['cipher'], '8bit') < $this->size) {
            throw new EncryptionException(
                'Decryption error, message was truncated or tampered with.',
                ErrorCode::SECURITY_ISSUE
            );
        }

        if ($this->isMac($data['method'])) {
            $digest = $this->macAlgo($data['method']);

            $expected = hash_hmac(
                $digest,
                $data['cipher'] . $data['nonce'], 
                $this->key, 
                true
            );

            if (!hash_equals($data['mac'], $expected)) {
                throw new EncryptionException(
                    'Data integrity check failed',
                    ErrorCode::SECURITY_ISSUE
                );
            }
        }

        $tag = null;

        if (!$isLegacy && $this->isAead($data['method'])) {
            $tag = $data['tag'];
        }

        $decrypted = false;

        try{
            $decrypted = openssl_decrypt(
                $data['cipher'], 
                $data['method'], 
                $this->key, 
                OPENSSL_RAW_DATA, 
                $data['nonce'],
                $tag,
                $this->aad
            );
        }catch(Throwable $e){
            throw new EncryptionException(
                $e->getMessage(), 
                $e->getCode(), 
                $e->getPrevious()
            );
        }

        if ($decrypted === false) {
           throw new EncryptionException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * {@inheritdoc}
     */
    public function free(): bool 
    {
        $this->aad = '';
        $this->key = '';
        $this->nonce = '';
        $this->message = '';
        self::$config = null;

        return true;
    }

    /**
     * Determine whether the cipher method is an AEAD mode.
     *
     * @param string|null $method Optional cipher method to check.
     *
     * @return bool True if the cipher is AEAD-based, false otherwise.
     */
    private function isAead(?string $method = null): bool
    {
        return str_contains($method ?? $this->method, 'GCM');
    }

    /**
     * Check whether the cipher method requires an explicit MAC.
     *
     * @param string|null $method Optional cipher method to check.
     *
     * @return bool True if a MAC is required, false otherwise.
     */
    private function isMac(?string $method = null): bool
    {
        return str_contains($method ?? $this->method, 'HMAC');
    }

    /**
     * Resolve the hash algorithm used for MAC generation.
     *
     * @param string|null $method Optional cipher method to inspect.
     *
     * @return string|null The hash algorithm name (e.g., "sha256"),
     *                     or null if no suitable algorithm is found.
     */
    private function macAlgo(?string $method = null): ?string
    {
        $method ??= $this->method;

        $algo = match (true) {
            str_contains($method, 'SHA256') => 'sha256',
            str_contains($method, 'SHA1')   => 'sha1',
            default => null,
        };

        if($algo === null){
            self::$config ??= new Encryption();
            $this->digest = self::$config->digest;

            return $this->digest;
        }

        return $algo;
    }

    /**
     * Decode cypher message.
     * 
     * @return array|null Return cypher payload.
     * @throws EncryptionException
     */
    private function decode(): array
    {
        $json = base64_decode($this->message, true);
        
        if ($json === false) {
           throw new EncryptionException('Invalid base64 encoded cipher message.');
        }

        try{
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = false;
        }

        if (!$data || !is_array($data)) {
            throw new EncryptionException(
                'Invalid cipher payload.',
                ErrorCode::JSON_ERROR
            );
        }

        $isLegacy = isset($data['encrypted']);

        $nonce  = $data['n'] ?? $data['nonce'] ?? null;
        $cipher = $data['c'] ?? $data['encrypted'] ?? null;
        $method = $data['m'] ?? null;
        $mac    = $data['h'] ?? $data['hash'] ?? null;

        if ((!$nonce || !$cipher) || ($isLegacy && !$mac) || (!$isLegacy && !$method)) {
            throw new EncryptionException('Cipher payload is incomplete.');
        }

        if (!$isLegacy) {
            $driver = $data['d'] ?? null;

            if ($driver !== 'openssl') {
                throw new EncryptionException(
                    'Unsupported encryption driver: ' . ($driver ?? 'missing'),
                    ErrorCode::NOT_SUPPORTED
                );
            }
        }

        $nonce  = base64_decode($nonce, true);
        $cipher = base64_decode($cipher, true);
        $tag    = isset($data['t']) ? base64_decode($data['t'], true) : null;
        $mac    = $mac ? base64_decode($data['h'], true) : null;

        if ($nonce === false || $cipher === false) {
            throw new EncryptionException('Invalid cipher encoding.');
        }

        return [
            'isLegacy'   => $isLegacy,
            'method'     => $method ?? $this->method,
            'nonce'      => $nonce,
            'cipher'     => $cipher,
            'tag'        => $tag,
            'mac'        => $mac
        ];
    }

    /**
     * Validate encryption parameters.
     *
     * @return bool True if parameters are valid, false otherwise.
     */
    private function valid(?string $method = null): bool
    {
        $method ??= $this->method;

        return $this->message
            && $method
            && $this->key;
    }
}