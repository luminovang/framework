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
use \Luminova\Security\Encryption\Driver\{Openssl, Sodium};
use \Luminova\Exceptions\{AppException, EncryptionException};

final class Crypter 
{
    /**
     * Supported encryption handlers.
     *
     * @var array<string,class-string<EncryptionInterface>>
     */
    private static array $handlers = [
        'openssl' => Openssl::class,
        'sodium'  => Sodium::class,
    ];

    /**
     * Create and return the configured encryption handler instance.
     *
     * Initializes the selected handler with the encryption key, cipher method, and key size.
     * 
     * > You may pass an explicit key via the $key argument, fall back to the application key (env 'app.key').
     *
     * @param string|null $key Optional encryption key (default: `env('app.key')`).
     *
     * @return EncryptionInterface Return instance of application encryption handler (e.g, `Openssl` or `Sodium`).
     *
     * @throws EncryptionException If the application key is missing.
     * @throws EncryptionException If the handler is invalid or the required extension is not loaded.
     * @throws EncryptionException If Sodium < 1.0.14 is installed.
     */
    public static function getInstance(?string $key = null): EncryptionInterface
    {
        $key ??= env('app.key');

        if (empty($key)) {
            throw new EncryptionException(
                'Encryption key required. Pass a key as Crypter::getInstance($key) or set the application key (env "app.key"). To generate one: "php novakit generate:key".'
            );
        }

        $handler = Key::handler(true);
        $method  = Key::method();

        return new (self::$handlers[$handler])(
            $key,
            $method,
            Key::size($method)
        );
    }

    /**
     * Encrypt plaintext with the default encryption configuration.
     *
     * Generates a nonce if required and uses the configured handler
     * (OpenSSL or Sodium) to perform encryption.
     *
     * @param string  $data  Plaintext to encrypt.
     * @param string|null $key Optional encryption key (default: `env('app.key')`).
     * @param string|null $nonce Optional nonce (default: `auto`).
     *
     * @return string|false Return encrypted data in binary or base64, or false on failure.
     *
     * @throws EncryptionException If the input is invalid or encryption fails.
     *
     * @see \App\Config\Encryption
     */
    public static function encrypt(string $data, ?string $key = null, ?string $nonce = null): string|bool
    {
        $crypt = self::getInstance($key);

        try {
            return $crypt->setNonce($nonce)
                ->setData($data)
                ->encrypt();
        } catch (Throwable $e) {
            if ($e instanceof AppException) {
                $e->handle();
                return false;
            }

            EncryptionException::throwException(
                sprintf('Encryption failed: %s', $e->getMessage()),
                $e->getCode(),
                $e->getPrevious()
            );
        }

        return false;
    }

    /**
     * Decrypt ciphertext with the default encryption configuration.
     *
     * Restores plaintext from the encrypted input. Validates data
     * and propagates errors from the underlying handler.
     *
     * @param string $data Encrypted data to decrypt.
     * @param string|null $key Optional encryption key (default: `env('app.key')`).
     *
     * @return string|false Return decrypted plaintext or false on failure.
     *
     * @throws EncryptionException If the input is invalid or decryption fails.
     *
     * @see \App\Config\Encryption
     */
    public static function decrypt(string $data, ?string $key = null): string|bool
    {
        $crypt = self::getInstance($key);

        try {
            return $crypt->setData($data)->decrypt();
        } catch (Throwable $e) {
            if ($e instanceof AppException) {
                $e->handle();
                return false;
            }

            EncryptionException::throwException(
                sprintf('Decryption failed: %s', $e->getMessage()),
                $e->getCode(),
                $e->getPrevious()
            );
        }

        return false;
    }
}