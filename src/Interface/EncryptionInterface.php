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
     * Creates a new encryption driver instance.
     * 
     * You can optionally pass a key during initialization 
     * or call `setKey()` later before performing encryption or decryption.
     * 
     * @param string $key The encryption key (default: null).
     * @param string $method The encryption cipher method (default: null).
     * @param int $size Optional encryption key size to use 
     *                  if failed to determine size from method. (default: 16).
     * 
     * @throws EncryptionException If the method or block size is invalid while using openssl.
     * @see Luminova\Security\Encryption\Crypter - Easy to use encryption help class that uses application encryption configuration.
     * @link https://luminova.ng/docs/0.0.0/encryption/driver - See Documentation.
     */
    public function __construct(?string $key = null, ?string $method = null, int $size = 16);

    /**
     * Set the data to encrypt/decrypt.
     * 
     * This method allows you to specify encoded message hash to decrypt 
     * or a plain text to encrypt.
     *
     * @param string $data The data to encrypt/decrypt.
     * 
     * @return static Return instance of encryption driver class.
     */
    public function setData(string $data): self;

    /**
     * Set the encryption key.
     *
     * @param string $key The encryption key.
     * 
     * @return static Return instance of encryption driver class.
     */
    public function setKey(string $key): self;

    /**
     * Set nonce for encryption, if null random nonce will be generated.
     *
     * @param string|null $nonce The nonce for encryption (default: null).
     * 
     * @return static Return instance of encryption driver class.
     * 
     * > **Note:**
     * > This method is only useful with OpenSSl driver, 
     * > if set in Sodium, the value will be ignored.
     */
    public function setNonce(?string $nonce = null): self;

    /**
     * Set the encryption method and block size for openssl.
     *
     * @param string $method The encryption cipher method.
     * @param int $size Optional encryption key size to use if failed to determine size from method. (default: 16).
     *
     * @return static Return instance of encryption driver class.
     * @throws EncryptionException If the method or block size is invalid.
     */
    public function setMethod(string $method, int $size = 16): self;

    /**
     * Generate a random nonce, or return from a string.
     *
     * @param string|null $string The string to extract the nonce from.
     * 
     * @return string Return the generated encryption nonce string.
     */
    public function nonce(?string $string = null): string;

    /**
     * Encrypt data into an encoded message.
     * 
     * This method performs cryptography to generate an encryption hash from a plan text.
     *
     * @return string|false Return the encrypted data, or false if encryption fails.
     * @throws EncryptionException If encryption fails due to invalid parameters.
     */
    public function encrypt(): string|bool;

    /**
     * Decrypt an encoded message.
     * 
     * This method performs cryptography to decipher an encrypted hash into a readable plan text.
     *
     * @return string|false Return the decrypted data, or false if decryption fails.
     * @throws EncryptionException If decryption fails due to invalid parameters.
     */
    public function decrypt(): string|bool;

    /**
     * Free up cryptography resources.
     * 
     * @return void 
     */
    public function free(): void;
}