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
use \SodiumException;
use \App\Config\Encryption;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Interface\EncryptionInterface;
use \Luminova\Exceptions\EncryptionException;

/**
 * Crypt Sodium encryption class.
 */
class Sodium implements EncryptionInterface
{
    /**
     * Cypher mode supports associated data.
     * 
     * @var string AEAD
     */
    public const AEAD = 'aead';

    /**
     * Cypher mode use simple symmetric encryption.
     * 
     * @var string SECRETBOX
     */
    public const SECRETBOX = 'secretbox';

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
     * Configuration.
     *
     * @var Encryption $config
     */
    private static ?Encryption $config = null;

    /**
     * Initializes a new Sodium encryption driver.
     *
     * A key may be provided at construction time, or set later
     * using `setKey()` before performing encryption or decryption.
     *
     * @param string|null $key Encryption key. If omitted, it must be set before use.
     *
     * @throws EncryptionException When the provided key is invalid for Sodium.
     *
     * @see  Luminova\Security\Encryption\Crypter
     *       Helper class that applies application-level encryption configuration.
     * @link https://luminova.ng/docs/0.0.0/encryption/driver Documentation.
     */
    public function __construct(?string $key = null)
    {
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
    public function setKey(string $key, int $length = 32, ?string $salt = null): self
    {
        try{
            $this->key = sodium_crypto_generichash(
                $key, 
                $salt, 
                $length
            );
        }catch(Throwable $e){
            throw new EncryptionException(
                $e->getMessage(), 
                $e->getCode(),
                $e->getPrevious()
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setNonce(?string $nonce = null): self
    {
        if($nonce !== null){
            $this->nonce = $nonce;
            return $this;
        }

        $length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if ($this->getCipherMode() === self::SECRETBOX) {
            $length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        }

        $this->nonce = self::nonce($length);
        return $this;
    }

    /**
     * {@inheritdoc}
     * 
     * > **Examples:**
     * > Use `SODIUM_CRYPTO_SECRETBOX_NONCEBYTES` for SECRETBOX.
     * > Use `SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES` for AEAD
     */
    public static function nonce(int $length, ?string $string = null): string
    {
        try{
            return ($string === null) 
                ? random_bytes($length)
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
     * 
     * > **Note:**
     * > Calling this method has no effect.
     * > Method is only supported in openssl driver.
     */
    public function setMethod(string $method, int $size = 24): self 
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function encrypt(): string
    {
        if (!$this->valid()) {
            throw new EncryptionException('Invalid encryption parameters.');
        }

        $cipherMode = $this->getCipherMode();

        if ($cipherMode === self::SECRETBOX && $this->aad !== '') {
            throw new EncryptionException(
                'AAD is not supported with sodium secretbox.',
                ErrorCode::NOT_SUPPORTED
            );
        }

        try {
            $crypt = match ($cipherMode) {
                self::SECRETBOX => sodium_crypto_secretbox(
                    $this->message,
                    $this->nonce,
                    $this->key
                ),

                default => sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    $this->message,
                    $this->aad,
                    $this->nonce,
                    $this->key
                ),
            };

            if ($crypt === false) {
                throw new EncryptionException('Encryption failed.');
            }

            $payload = [
                'v' => self::VERSION,
                'm' => $cipherMode,
                'd' => 'sodium',
                'n' => base64_encode($this->nonce),
                'c' => base64_encode($crypt),
            ];

            sodium_memzero($crypt);

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
        $size = $isLegacy 
            ? SODIUM_CRYPTO_SECRETBOX_MACBYTES
            : SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if (mb_strlen($data['cipher'], '8bit') < $size) {
            throw new EncryptionException(
                'Decryption error, message was truncated or tampered with.',
                ErrorCode::SECURITY_ISSUE
            );
        }

        $decrypted = false;

        try{
            if($isLegacy){
                $decrypted = sodium_crypto_secretbox_open(
                    $data['cipher'], 
                    $data['nonce'], 
                    $this->key
                );
            }else{
                $decrypted = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                    $data['cipher'],
                    $this->aad,
                    $data['nonce'],
                    $this->key
                );
            }
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
        self::$config = null;

        try{
            if(isset($this->key)){
                sodium_memzero($this->key);
            }

            if(isset($this->nonce)){
                sodium_memzero($this->nonce);
            }

            if(isset($this->message)){
                sodium_memzero($this->message);
            }

            return true;
        }catch(SodiumException){
            return false;
        }
    }

    /**
     * Validate encryption parameters.
     *
     * @return bool True if parameters are valid, false otherwise.
     */
    private function valid(): bool
    {
        return $this->message && $this->key;
    }

    /**
     * Get sodium cipher mode.
     * 
     * @return string Return mode.
     */
    private function getCipherMode(): string
    {
        self::$config ??= new Encryption();

        if(isset(self::$config->sodiumCipher)){
            return self::$config->sodiumCipher;
        }

        return self::SECRETBOX;
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

        if ($data === false || !is_array($data)) {
            throw new EncryptionException(
                'Invalid cipher payload.',
                ErrorCode::JSON_ERROR
            );
        }

        $nonce  = $data['n'] ?? $data['nonce'] ?? null;
        $cipher = $data['c'] ?? $data['encrypted'] ?? null;

        if (!$nonce || !$cipher) {
           throw new EncryptionException('Cipher payload is incomplete.');
        }

        $isLegacy = isset($data['encrypted']);

        if (!$isLegacy) {
            $driver = $data['d'] ?? null;

            if ($driver !== 'sodium') {
                throw new EncryptionException(
                    'Unsupported encryption driver: ' . ($driver ?? 'missing'),
                    ErrorCode::NOT_SUPPORTED
                );
            }
        }

        $nonce  = base64_decode($nonce, true);
        $cipher = base64_decode($cipher, true);

        if ($nonce === false || $cipher === false) {
           throw new EncryptionException('Invalid cipher encoding.');
        }

        $isSecretBox = ($data['m'] ?? null) === self::SECRETBOX;

        return [ 
            'isLegacy'   => $isLegacy || $isSecretBox,
            'nonce'      => $nonce,
            'cipher'     => $cipher
        ];
    }
}