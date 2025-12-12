<?php
/**
 * Luminova Framework Crypter class provides methods for encrypting 
 * and decrypting data using encryption algorithms in Openssl or Sodium.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security\Encryption;

use \Throwable;
use \Luminova\Security\Encryption\Key;
use \Luminova\Interface\EncryptionInterface;
use \Luminova\Exceptions\EncryptionException;
use \Luminova\Security\Encryption\Driver\{Openssl, Sodium};

final class Crypter
{
    /**
     * @var string|null $handler
     */
    private static ?string $handler = null;

    /**
     * @var string|null $method
     */
    private static ?string $method = null;

    /**
     * Returns a configured application encryption handler instance.
     *
     * Resolves the active encryption driver (OpenSSL or Sodium),
     * applies the encryption key, cipher method, and key size,
     * and returns a ready-to-use handler.
     *
     * If no key is provided, the application key (`env('app.key')`)
     * is used.
     *
     * @param string|null $key Encryption key. Defaults to `env('app.key')`.
     *
     * @return EncryptionInterface Active encryption handler instance.
     * @throws EncryptionException If the encryption key is missing.
     * @throws EncryptionException If the handler is invalid or its extension is not available.
     * @throws EncryptionException If the installed Sodium version is unsupported (< 1.0.14).
     * 
     * @see \App\Config\Encryption for application encryption configuration.
     */
    public static function getInstance(?string $key = null): EncryptionInterface
    {
        $key ??= env('app.key');

        if (empty($key)) {
            throw new EncryptionException(
                'Encryption key required. Pass a key as Crypter::getInstance($key) or set the application key (env "app.key"). To generate one: "php novakit generate:key".'
            );
        }

        self::$handler ??= Key::handler(true);

        if(self::$handler === Key::SODIUM){
            return new Sodium($key);
        }

        self::$method  ??= Key::method();

        return new Openssl(
            $key,
            self::$method,
            Key::size(self::$method)
        );
    }

    /**
     * Generate a random nonce, or return from a string.
     * 
     * This method generates drivers specific nonce.
     *
     * @param int The nonce length to generate.
     * @param string|null $string The string to drive nonce from.
     * 
     * @return string|null Return the generated encryption nonce string or null if failed.
     */
    public static function nonce(int $length, ?string $string = null): ?string
    {
        self::$handler ??= Key::handler(true);

        try{
            if(self::$handler === Key::SODIUM){
                return Sodium::nonce($length, $string);
            }

            return Openssl::nonce($length, $string);
        }catch(Throwable){
            return null;
        }
    }

    /**
     * Encrypts plaintext using the default encryption configuration.
     *
     * Automatically selects the configured handler, generates a nonce
     * when required, and returns the encrypted payload.
     *
     * @param string $data Plaintext to encrypt.
     * @param string|null $key Encryption key. Defaults to `env('app.key')`.
     * @param string|null $nonce Optional nonce. Generated automatically if omitted.
     * @param string|null $aad Additional data to authenticate (default: `null`).
     *
     * @return string|false Returns encrypted data or false on failure in production.
     *
     * @throws EncryptionException If encryption fails or input is invalid.
     * @see \App\Config\Encryption for application encryption configuration.
     */
    public static function encrypt(
        string $data, 
        ?string $key = null, 
        ?string $nonce = null,
        ?string $aad = null
    ): string|bool
    {
        $crypt = self::getInstance($key);

        try {
            if($aad){
                $crypt->setAssociatedData($aad);
            }

            return $crypt->setNonce($nonce)
                ->setData($data)
                ->encrypt();
        } catch (Throwable $e) {
            if ($e instanceof EncryptionException) {
                $e->handle();
                return false;
            }

            EncryptionException::throwException(
                sprintf('Encryption error: %s', $e->getMessage()),
                $e->getCode(),
                $e->getPrevious()
            );
        } finally{
            $crypt->free();
        }

        return false;
    }

    /**
     * Decrypts encrypted data using the default encryption configuration.
     *
     * Resolves the active handler, validates the input, and restores
     * the original plaintext.
     *
     * @param string $data Encrypted payload.
     * @param string|null $key Encryption key. Defaults to `env('app.key')`.
     * @param string|null $aad Additional data to authenticate (default: `null`).
     *
     * @return string|false Returns decrypted plaintext or false on failure in production.
     *
     * @throws EncryptionException If decryption fails or input is invalid.
     * @see \App\Config\Encryption for application encryption configuration.
     */
    public static function decrypt(
        string $data, 
        ?string $key = null,
        ?string $aad = null
    ): string|bool
    {
        $crypt = self::getInstance($key);

        try {
            if($aad){
                $crypt->setAssociatedData($aad);
            }

            return $crypt->setData($data)->decrypt();
        } catch (Throwable $e) {
            if ($e instanceof EncryptionException) {
                $e->handle();
                return false;
            }

            EncryptionException::throwException(
                sprintf('Decryption error: %s', $e->getMessage()),
                $e->getCode(),
                $e->getPrevious()
            );
        } finally{
            $crypt->free();
        }

        return false;
    }
}