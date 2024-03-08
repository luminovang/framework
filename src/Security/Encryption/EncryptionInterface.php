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

interface EncryptionInterface
{
    /**
     * Set data to encrypt/decrypt.
     *
     * @param string|null $data
     */
    public function setData(?string $data = null): void;

    /**
     * Set encryption key.
     *
     * @param string|null $key
     */
    public function setKey(?string $key = null): void;

    /**
     * Set initialization vector (IV).
     *
     * @param string|null $iv
     */
    public function setInitializationVector(?string $iv = null): void;

    /**
     * Set IV length based on the encryption method.
     */
    public function setIvLength(): void;

    /**
     * @param int|null $blockSize
     * @param string $mode
     * @throws ErrorException
     */
    public function setMethod(?int $blockSize = null, string $mode = 'CBC'): void;

    /**
     * Validate encryption parameters.
     *
     * @return bool
     */
    public function validateParams(): bool;

    /**
     * Generate a random initialization vector (IV).
     *
     * @return string
     */
    public function randomInitializationVector(): string;

    /**
     * Get IV from a string.
     *
     * @param string $string
     * @return string
     */
    public function getInitializationVectorFrom(string $string): string;
    /**
     * Encrypt data.
     *
     * @return string
     * @throws InvalidException
     */
    public function encrypt(): string;

    /**
     * Decrypt data.
     *
     * @return string|null
     * @throws InvalidException
     */
    public function decrypt(): ?string;
}