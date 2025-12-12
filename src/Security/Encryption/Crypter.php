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
use App\Kernel;
use Luminova\Logger\Entry;
use Luminova\Config\Env;
use Luminova\Security\Encryption\Key;
use Luminova\Interface\EncryptionInterface;
use Luminova\Exceptions\EncryptionException;
use Luminova\Security\Encryption\Driver\{Openssl, Sodium};

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
     * Creates a configured application encryption handler.
     *
     * Resolves the configured encryption driver, applies the encryption key,
     * and returns a ready-to-use encryption handler.
     *
     * If no key is provided, the configured application key is used.
     *
     * @param string|null $key Encryption key. Defaults to the configured application key.
     *
     * @return EncryptionInterface Configured encryption handler instance.
     *
     * @throws EncryptionException If the encryption key is missing, 
     *      the configured handler is invalid, a required encryption extension is unavailable, 
     *      or the installed Sodium version is unsupported.
     *
     * @see \App\Config\Encryption Application encryption configuration.
     */
    public static function getInstance(?string $key = null): EncryptionInterface
    {
        $key ??= Env::get('app.key');
        
        self::assertKey($key);

        return self::newInstance($key);
    }

    /**
     * Generate a random nonce, or return from a string.
     * 
     * This method generates drivers specific nonce.
     *
     * @param int $length The nonce length to generate.
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
     * Encrypt plaintext using the configured encryption handler.
     *
     * Creates an encryption handler using the provided or configured encryption
     * key, optionally authenticates additional data, and returns the encrypted
     * payload.
     *
     * A nonce may be provided explicitly or generated automatically when required
     * by the configured encryption algorithm.
     *
     * @param string $data Plaintext data to encrypt.
     * @param string|null $key   Encryption key to use. Defaults to the configured
     *                           application key.
     * @param string|null $nonce Optional nonce. Generated automatically when required.
     * @param string|null $aad Additional authenticated data, or null when not used.
     *
     * @return string|false The encrypted payload, or false if encryption fails in
     *                      production.
     *
     * @throws EncryptionException If no encryption key is available or encryption
     *                             fails in a non-production environment.
     *
     * @see \App\Config\Encryption Application encryption configuration.
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

            EncryptionException::handleException(
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
     * Decrypt encrypted data using the configured encryption handler.
     *
     * Attempts decryption with the provided key or each configured application
     * key until the encrypted payload is successfully decrypted.
     *
     * @param string $data Encrypted payload to decrypt.
     * @param array|string|null $key Encryption key or keys to use. Defaults to
     *                                the configured application keys.
     * @param string|null $aad  Additional authenticated data used during
     *                                encryption, or null when not required.
     *
     * @return string|false The decrypted plaintext, or false when decryption fails
     *                      in production.
     *
     * @throws EncryptionException If no encryption key is available or decryption
     *                             fails in a non-production environment.
     *
     * @see \App\Config\Encryption Application encryption configuration.
     * @see \App\Kernel::getApplicationKeys() Set multiple application keys
     */
    public static function decrypt(
        string $data,
        array|string|null $key = null,
        ?string $aad = null
    ): string|bool
    {
        static $logs;
        $keys = ($key !== null && $key !== [])
            ? (array) $key
            : Kernel::getApplicationKeys();

        self::assertKey($keys);

        $crypt = self::newInstance();
        $logs ??= new Entry('critical');

        try {
            if ($aad !== null) {
                $crypt->setAssociatedData($aad);
            }

            foreach ($keys as $index => $secret) {
                try {
                    return $crypt
                        ->setKey($secret)
                        ->setData($data)
                        ->decrypt();
                } catch (Throwable $e) {
                    $logs->add(
                        sprintf(
                            'Decryption failed with application key at index %d: %s',
                            $index,
                            $e->getMessage()
                        ),
                        [
                            'code'  => $e->getCode(),
                            'index' => $index,
                        ]
                    );
                }
            }

            if (PRODUCTION) {
                return false;
            }

            throw new EncryptionException(sprintf(
                'Decryption failed for all (%d) available application keys.',
                count($keys)
            ));
        } finally {
            $crypt->free();
            $logs->log();
        }

        return false;
    }

    /**
     * Create a new instance of the configured encryption handler.
     *
     * Resolves the configured encryption handler and creates an instance using
     * the specified key and encryption method settings.
     *
     * @param string|null $key Optional encryption key.
     *
     * @return EncryptionInterface A configured encryption handler instance.
     */
    private static function newInstance(?string $key = null): EncryptionInterface
    {
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
     * Validate that at least one encryption key is available.
     *
     * @param string|array|null $key Encryption key or collection of keys to validate.
     *
     * @return void
     *
     * @throws EncryptionException If no valid encryption key is provided.
     */
    private static function assertKey(string|array|null $key): void
    {
        if ($key === null || $key === '' || $key === []) {
            throw new EncryptionException(sprintf(
                'No encryption key found. Provide one, generate and set env(app.key), env(app.keys), 
                or override %s::getApplicationKeys(). To generate via cli: "php novakit generate:key"',
                Kernel::class
            ));
        }
    }
}