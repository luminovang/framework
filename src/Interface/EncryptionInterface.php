<?php
/**
 * Luminova Framework Interface for encryption and decryption operations.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \Luminova\Exceptions\EncryptionException;

interface EncryptionInterface
{
    /**
     * Constructor, optional pass a blank key string and `setKey()` later before encrypt/decrypt.
     *
     * @param string $key The encryption key (default: null).
     * @param string $method The encryption cipher method (default: null).
     * @param int $size Key size for encryption (default: 16).
     * 
     * @throws EncryptionException If the method or block size is invalid while using openssl.
    */
    public function __construct(?string $key = null, ?string $method = null, int $size = 16);

    /**
     * Set the data to encrypt/decrypt.
     *
     * @param string $data The data to encrypt/decrypt.
     */
    public function setData(string $data): void;

    /**
     * Set the encryption key.
     *
     * @param string $key The encryption key.
     * 
     * @return void 
     */
    public function setKey(string $key): void;

    /**
     * Set nonce for encryption and decryption, if null random nonce will be generated.
     *
     * @param string|null $nonce The nonce for encryption (default: null).
     * 
     * @return void 
     */
    public function setNonce(?string $nonce = null): void;

    /**
     * Set the encryption method and block size for openssl.
     *
     * @param string $method The encryption cipher method.
     * @param int $size Key size for encryption (default: 16).
     *
     * @return void
     * @throws EncryptionException If the method or block size is invalid.
     */
    public function setMethod(string $method, int $size = 16): void;

    /**
     * Generate a random nonce, or return from a string.
     *
     * @param string|null $string The string to extract the nonce from.
     * 
     * @return string Return the nonce string.
     */
    public function nonce(?string $string = null): string;

    /**
     * Encrypt data.
     *
     * @return string|bool Return the encrypted data, or false if encryption fails.
     * @throws EncryptionException If encryption fails due to invalid parameters.
     */
    public function encrypt(): string|bool;

    /**
     * Decrypt data.
     *
     * @return string|bool Return the decrypted data, or false if decryption fails.
     * @throws EncryptionException If decryption fails due to invalid parameters.
     */
    public function decrypt(): string|bool;

    /**
     * Free up resources.
     * 
     * @return void 
    */
    public function free(): void;
}