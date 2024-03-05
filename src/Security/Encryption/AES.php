<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Security\Encryption;
use Luminova\Security\Encryption\EncryptionInterface;
use Luminova\Exceptions\ErrorException;
use Luminova\Exceptions\InvalidException;

/**
 * AES encryption class.
 */
class AES implements EncryptionInterface
{
    protected string $key;
    protected string $data;
    protected string $method;
    protected ?string $iv = null;
    protected int $ivLength = 16;
    protected int $options = 0;

    /**
     * Constructor.
     *
     * @param string|null $data
     * @param string|null $key
     * @param int|null $blockSize
     * @param string $mode
     */
    public function __construct(?string $key = '', ?int $blockSize = null, string $mode = 'CBC')
    {
        $this->setKey($key);
        $this->setMethod($blockSize, $mode);
    }

    /**
     * Set data to encrypt/decrypt.
     *
     * @param string|null $data
     */
    public function setData(?string $data = null): void
    {
        $this->data = $data;
    }

    /**
     * Set encryption key.
     *
     * @param string|null $key
     */
    public function setKey(?string $key = null): void
    {
        $this->key = hash('sha256', $key, true);
    }

    /**
     * Set initialization vector (IV).
     *
     * @param string|null $iv
     */
    public function setInitializationVector(?string $iv = null): void
    {
        $this->iv = $iv;
    }

    /**
     * Set IV length based on the encryption method.
     */
    public function setIvLength(): void
    {
        $this->ivLength = openssl_cipher_iv_length($this->method);
    }

    /**
     * @param int|null $blockSize
     * @param string $mode
     * @throws ErrorException
     */
    public function setMethod(?int $blockSize = null, string $mode = 'CBC'): void
    {
		//if ($blockSize==192 && in_array('', array('CBC-HMAC-SHA1','CBC-HMAC-SHA256','XTS'))){
        if ($blockSize == 192 && in_array($mode, ['', 'CBC-HMAC-SHA1', 'CBC-HMAC-SHA256', 'XTS'])) {
            throw new ErrorException('Invalid block size and mode combination!');
        }

        $this->method = 'AES-' . $blockSize . '-' . $mode;
        $this->setIvLength();
    }

    /**
     * Validate encryption parameters.
     *
     * @return bool
     */
    public function validateParams(): bool
    {
        return $this->data !== '' && $this->method !== null;
    }

    /**
     * Generate a random initialization vector (IV).
     *
     * @return string
     */
    public function randomInitializationVector(): string
    {
        return openssl_random_pseudo_bytes($this->ivLength);
    }

    /**
     * Get IV from a string.
     *
     * @param string $string
     * @return string
     */
    public function getInitializationVectorFrom(string $string): string
    {
        return mb_substr($string, 0, $this->ivLength, '8bit');
    }

    /**
     * Encrypt data.
     *
     * @return string
     * @throws InvalidException
     */
    public function encrypt(): string
    {
        if ($this->validateParams()) {
            $cipherText = trim(openssl_encrypt($this->data, $this->method, $this->key, $this->options, $this->iv));
            $hash = hash_hmac('sha256', $cipherText . $this->iv, $this->key, true);
            return $this->iv . $hash . $cipherText;
        } else {
            throw new InvalidException('Invalid contraption params!');
        }
    }

    /**
     * Decrypt data.
     *
     * @return string|null
     * @throws InvalidException
     */
    public function decrypt(): ?string
    {
        if ($this->validateParams()) {
            $iv = substr($this->data, 0, $this->ivLength);
            $hash = substr($this->data, $this->ivLength, 32);
            $cipherText = substr($this->data, 48);

            if (!hash_equals(hash_hmac('sha256', $cipherText . $iv, $this->key, true), $hash)) {
                return null;
            }

            $ret = openssl_decrypt($cipherText, $this->method, $this->key, $this->options, $iv);
            return trim($ret);
        } else {
            throw new InvalidException('Invalid decryption params!');
        }
    }
}