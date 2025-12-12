<?php
/**
 * Luminova Framework Interface for encryption and decryption operations.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Luminova\Exceptions\EncryptionException;

interface EncryptionInterface
{
    /**
     * Set the data to encrypt/decrypt.
     * 
     * This method allows you to specify encoded message hash to decrypt 
     * or a plain text to encrypt.
     *
     * @param string $data The cipher message to encrypt or decrypt.
     * 
     * @return static Return instance of encryption driver class.
     */
    public function setData(string $data): self;

    /**
     * Sets Additional Authenticated Data (AEAD).
     * 
     * @param array|string $aad Data to authenticate (not encrypted).
     * 
     * @return static Return instance of encryption driver class.
     * @throws EncryptionException If array and failed to encode.
     * > **Note:**
     * > Must be identical for encryption and decryption or authentication fails.
     */
    public function setAssociatedData(array|string $aad): self;

    /**
     * Set the encryption key.
     *
     * @param string $key The encryption key.
     * @param int $length Optional key length (default: 0).
     * @param ?string $nonce Optional random salt to use during HKDF key derivation (default: null).
     * 
     * @return static Return instance of encryption driver class.
     * @throws EncryptionException If error while generating key.
     */
    public function setKey(string $key, int $length = 0, ?string $salt = null): self;

    /**
     * Set nonce for encryption, if null random nonce will be generated.
     *
     * @param string|null $nonce The nonce for encryption (default: null).
     * 
     * @return static Return instance of encryption driver class.
     */
    public function setNonce(?string $nonce = null): self;

    /**
     * Set the encryption method and block size for openssl.
     *
     * @param string $method The encryption cipher method.
     * @param int $size Optional encryption key size to use 
     *          if failed to determine size from method. (default: 16).
     *
     * @return static Return instance of encryption driver class.
     * @throws EncryptionException If the method or block size is invalid.
     */
    public function setMethod(string $method, int $size = 16): self;

    /**
     * Generate a random nonce, or return from a string.
     *
     * @param int The nonce length to generate.
     * @param string|null $string The string to drive nonce from.
     * 
     * @return string Return the generated encryption nonce string.
     * @throws EncryptionException If error while generating nonce.
     */
    public static function nonce(int $length, ?string $string = null): string;

    /**
     * Encrypt data into an encoded message.
     * 
     * This method performs cryptography to generate an encryption hash from a plan text.
     *
     * @return string Return the encrypted cipher message data if encryption succeed.
     * @throws EncryptionException If encryption fails due to invalid parameters.
     * 
     * > **Note:**
     * > Payload is encoded in base64 string.
     */
    public function encrypt(): string;

    /**
     * Decrypt an encoded message.
     * 
     * This method performs cryptography to decipher an encrypted hash into a readable plan text.
     *
     * @return string Return the decrypted plain-text content if decryption succeed.
     * @throws EncryptionException If decryption fails or invalid parameters.
     */
    public function decrypt(): string;

    /**
     * Free up cryptography resources.
     * 
     * This will clear:
     * - key
     * - nonce
     * - message
     * - addition authentication data
     * 
     * @return bool Return true if freed, otherwise false. 
     */
    public function free(): bool;
}