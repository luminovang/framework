<?php
/**
 * Luminova Framework Encryption key-tool.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security\Encryption;

use \OpenSSLCertificate;
use \OpenSSLAsymmetricKey;
use \App\Config\Encryption;
use Luminova\Common\Helpers;
use Luminova\Exceptions\ErrorCode;
use \Luminova\Exceptions\EncryptionException;

final class Key 
{
    /**
     * Flag to generate a public key.
     * 
     * @var string TYPE_PUBLIC
     */
    public const TYPE_PUBLIC  = 'public';

    /**
     * Flag to generate a private key.
     * 
     * @var string TYPE_PRIVATE
     */
    public const TYPE_PRIVATE = 'private';

    /**
     * Flag to generate a public and private key-pair.
     * 
     * @var string TYPE_PAIR
     */
    public const TYPE_PAIR  = 'pair';

    /**
     * Flag to generate a random key.
     * 
     * @var string TYPE_RANDOM
     */
    public const TYPE_RANDOM  = 'random';

    /**
     *  Flag for openssl key handler.
     * 
     * @var string OPENSSL
     */
    public const OPENSSL  = 'openssl';

    /**
     * Flag for sodium key handler.
     * 
     * @var string SODIUM
     */
    public const SODIUM  = 'sodium';

    /**
     * The supported cipher algorithms and their properties.
     *
     * @var array<string,array> $ciphers
     */
    public static array $ciphers = [
        'AES-128-CBC' => ['size' => 16],
        'AES-192-CBC' => ['size' => 24],
        'AES-256-CBC' => ['size' => 32],
        'AES-128-CBC-HMAC-SHA1'   => ['size' => 16],
        'AES-256-CBC-HMAC-SHA1'   => ['size' => 32],
        'AES-128-CBC-HMAC-SHA256' => ['size' => 16],
        'AES-256-CBC-HMAC-SHA256' => ['size' => 32],
        'AES-128-CFB'  => ['size' => 16],
        'AES-192-CFB'  => ['size' => 24],
        'AES-256-CFB'  => ['size' => 32],
        'AES-128-CFB1' => ['size' => 16],
        'AES-192-CFB1' => ['size' => 24],
        'AES-256-CFB1' => ['size' => 32],
        'AES-128-CFB8' => ['size' => 16],
        'AES-192-CFB8' => ['size' => 24],
        'AES-256-CFB8' => ['size' => 32],
        'AES-128-CTR'  => ['size' => 16],
        'AES-192-CTR'  => ['size' => 24],
        'AES-256-CTR'  => ['size' => 32],
        'AES-128-ECB'  => ['size' => 16],
        'AES-192-ECB'  => ['size' => 24],
        'AES-256-ECB'  => ['size' => 32],
        'AES-128-OFB'  => ['size' => 16],
        'AES-192-OFB'  => ['size' => 24],
        'AES-256-OFB'  => ['size' => 32],
        'AES-128-XTS'  => ['size' => 16],
        'AES-256-XTS'  => ['size' => 32],
    ];

    /**
     * Encryption configuration instance.
     *
     * @var Encryption|null
     */
    private static ?Encryption $config = null;

    /**
     * Generated key information.
     * 
     * @var array $key
     */
    private array $key = [];

    /**
     * Key generator for public, private, or random keys.
     *
     * The constructor creates a key based on the given type:
     * - `public`: Generates a public key from an existing private key, or creates a new key pair.
     * - `private`: Generates a new private key.
     * - `random`: Generates a random key string.
     *
     * @param string $type Type of key to generate (`public`, `private`, or `random`).
     * @param string|null $handler Crypto handler (`Key::OPENSSL` or `Key::SODIUM`). 
     *                             If null, defaults to the configured handler.
     * @param string|null $passphrase Optional passphrase for OpenSSL private keys, 
     *                                or for generating new ones.
     * @param array<string,mixed> $options Options for deriving or generating keys:
     *        - `private_key` string: existing private/secret key.
     *        - Other key options.
     *
     * @link https://php.net/manual/en/function.openssl-pkey-new.php
     *
     * @see getResult()
     * @see getPrivate()
     * @see getPublic()
     * @see getRandom()
     * 
     * @example - Example usage:
     *
     * Generate a private key:
     * ```php
     * $key = new Key(Key::TYPE_PRIVATE, [
     *     'private_key_bits' => 4096,
     *     'private_key_type' => OPENSSL_KEYTYPE_RSA
     * ]);
     * echo $key->getPrivate();
     * ```
     *
     * Generate a public key from an existing private key:
     * ```php
     * $private = \Luminova\Funcs\get_content('/path/to/private.pem');
     * $key = new Key(Key::TYPE_PUBLIC, ['private_key' => $private]);
     * echo $key->getPublic();
     * ```
     *
     * Generate a random key string:
     * ```php
     * $key = new Key(Key::TYPE_RANDOM);
     * echo $key->getRandom();
     * ``` 
     */
    public function __construct(
        private string $type = self::TYPE_PUBLIC, 
        private ?string $handler = null,
        private ?string $passphrase = null,
        array $options = []
    ) 
    {
        $key = match($this->type) {
            self::TYPE_PUBLIC  => self::newPublic($handler, $passphrase, $options),
            self::TYPE_PRIVATE => self::newPrivate($handler, $passphrase, $options),
            self::TYPE_PAIR    => self::newKeyPair($handler, $passphrase, $options),
            self::TYPE_RANDOM  => self::newRandom(
                $handler,
                $options['length'] ?? null, 
                toHex: false
            ),
            default   => null
        };

        $this->key = [$this->type => $key ?: null];
        $key = null;
    }

    /**
     * Get the generated key value.
     *
     * @return string|array|null Return key detail, or null if not available.
     */
    public function getResult(): string|array|null
    {
        return $this->key[$this->type] ?? null; 
    }

    /**
     * Get the generated public key.
     *
     * @return string|null Public key string, or null if not available.
     */
    public function getPublic(): ?string
    {
        return $this->key[self::TYPE_PAIR]['public'] 
            ?? $this->key[self::TYPE_PUBLIC]
            ?? null; 
    }

    /**
     * Get the generated private key.
     *
     * @return string|null Private key string (PEM format), or null if not available.
     */
    public function getPrivate(): ?string
    {
        return $this->key[self::TYPE_PAIR]['private'] 
            ?? $this->key[self::TYPE_PRIVATE] 
            ?? null; 
    }

    /**
     * Get the generated random key.
     *
     * @param bool $toHex Whether to return the key in hex format (default: true).
     *
     * @return string|null Random key string in hex or raw bytes, or null if not available.
     */
    public function getRandom(bool $toHex = true): ?string
    {
        $bytes = $this->key[self::TYPE_RANDOM] ?? null;

        if (!$bytes) {
            return null;
        }

        return $toHex ? bin2hex($bytes) : $bytes;
    }

    /**
     * Retrieve the key size based on cipher method.
     *
     * @param string|null $method Cipher method name (default: `App\Config\Encryption->method`).
     * 
     * @return int Return the key size in bytes.
     */
    public static function size(?string $method = null): int 
    {
        $cipher = self::cipher($method) ?? ['size' => 16];

        return $cipher['size'];
    }

    /**
     * Retrieve cipher information by method.
     *
     * @param string|null $method Cipher method name (default: `App\Config\Encryption->method`).
     * 
     * @return array{size:int}|null Cipher properties or null if unsupported.
     */
    public static function cipher(?string $method = null): ?array 
    {
        $method ??= self::method();

        return self::$ciphers[strtoupper($method)] ?? null;
    }

    /**
     * Retrieve encryption method from default configuration.
     *
     * @param string|null $default The default cipher method if not found.
     * 
     * @return string Return the cipher method or default.
     */
    public static function method(string $default = 'AES-128-CBC'): string 
    {
        self::config();
        return strtoupper(self::$config->method ?: $default);
    }

    /**
     * Verify that the given key length matches the expected size for the cipher method.
     *
     * If no cipher method is provided, the default from `App\Config\Encryption->method`
     * will be used.
     *
     * @param string $key The encryption key to validate.
     * @param string|null $method Optional cipher method (default: `App\Config\Encryption->method`).
     *
     * @return bool Return true if the key length is valid for the cipher, false otherwise.
     */
    public static function isSupported(string $key, ?string $method = null): bool
    {
        $cipher = self::cipher($method);
        return $cipher && mb_strlen($key, '8bit') === $cipher['size'];
    }

    /**
     * Validate if a given key is private.
     *
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string $key Key in PEM format (OpenSSL) or Base64-encoded string (Sodium).
     * @param string|null $passphrase Passphrase for OpenSSL private keys.
     * @param string|null $handler Encryption handler (e.g, `Key::OPENSSL` or `Key::SODIUM`) (default: auto-detects).
     * 
     * @return bool Return true if valid private key, false otherwise.
     */
    public static function isPrivate(mixed $key, ?string $passphrase = null, ?string $handler = null): bool
    {
        $handler = self::getResolvedHandler($handler, true);
        self::assertKeyType($key, handler: $handler);

        if ($handler === self::SODIUM) {

            if (!is_string($key)) {
                return false;
            }
            
            $raw = Helpers::isBase64Encoded($key) ? base64_decode($key, true) : $key;

            if ($raw === false) {
                return false;
            }

            return strlen($raw) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES;
        }

        return $handler === self::OPENSSL && openssl_pkey_get_private($key, $passphrase) !== false;
    }

    /**
     * Validate if a given key is public.
     *
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string $key Key in PEM format (OpenSSL) or Base64-encoded string (Sodium).
     * @param string|null $handler Encryption handler (e.g, `Key::OPENSSL` or `Key::SODIUM`) (default: auto-detects).
     * 
     * @return bool Return true if valid public key, false otherwise.
     */
    public static function isPublic(mixed $key, ?string $handler = null): bool
    {
        $handler = self::getResolvedHandler($handler, true);
        self::assertKeyType($key, handler: $handler);

        if ($handler === self::SODIUM) {
            if (!is_string($key)) {
                return false;
            }

            $raw = Helpers::isBase64Encoded($key) ? base64_decode($key, true) : $key;

            if ($raw === false) {
                return false;
            }

            return strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES;
        }

        return $handler === self::OPENSSL && openssl_pkey_get_public($key) !== false;
    }

    /**
     * Validate whether a private and public key pair match.
     *
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string $privateKey Private key (PEM for OpenSSL or Base64 for Sodium).
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string $publicKey Public key (PEM for OpenSSL or Base64 for Sodium).
     * @param int $algo OpenSSL algorithm (default: OPENSSL_ALGO_SHA256).
     * @param string|null $data Optional data to sign (default: 'test_message').
     * @param string|null $passphrase Passphrase for OpenSSL private keys.
     * @param string|null $handler Encryption handler (e.g., `Key::OPENSSL` or `Key::SODIUM`) (default: auto-detects).
     * 
     * @return bool Return true if keys match, false otherwise.
     */
    public static function isMatch(
        mixed $privateKey,
        mixed $publicKey,
        int $algo = OPENSSL_ALGO_SHA256,
        ?string $data = null,
        ?string $passphrase = null,
        ?string $handler = null
    ): bool 
    {
        $handler = self::getResolvedHandler($handler, true);
        self::assertKeyType($privateKey, handler: $handler);
        self::assertKeyType($publicKey, handler: $handler);


        $data ??= 'test_message';

        if ($handler === self::SODIUM) {
            if (!is_string($privateKey) || !is_string($publicKey)) {
                return false;
            }

            $privRaw = Helpers::isBase64Encoded($privateKey) ? base64_decode($privateKey, true) : $privateKey;
            $pubRaw  = Helpers::isBase64Encoded($publicKey) ? base64_decode($publicKey, true) : $publicKey;

            if ($privRaw === false || $pubRaw === false) {
                return false;
            }

            if (strlen($privRaw) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ||
                strlen($pubRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                return false;
            }

            $signature = sodium_crypto_sign_detached($data, $privRaw);
            return sodium_crypto_sign_verify_detached($signature, $data, $pubRaw);
        }

        if ($handler !== self::OPENSSL) {
            return false;
        }

        $privateRes = openssl_pkey_get_private($privateKey, $passphrase);
        $publicRes = openssl_pkey_get_public($publicKey);

        if (!$privateRes || !$publicRes) {
            return false;
        }

        return openssl_sign($data, $signature, $privateRes, $algo) &&
            openssl_verify($data, $signature, $publicRes, $algo) === 1;
    }

    /**
     * Derive or generate a public key using the chosen crypto handler.
     *
     * - For OpenSSL:
     *   If a private key is provided, the public key is derived from it.
     *   If not, a new private key is created and its public key is extracted.
     *
     * - For Sodium:
     *   If a secret (private) key is provided, the corresponding public key is derived.
     *   If not, a new key-pair is generated and the public key is returned.
     *
     * @param string|null $handler Crypto handler (`Key::OPENSSL` or `Key::SODIUM`). 
     *                             If null, defaults to the configured handler.
     * @param string|null $passphrase Optional passphrase for OpenSSL private keys, 
     *                                or for generating new ones.
     * @param array<string,mixed> $options Options for deriving or generating keys:
     *        - `private_key` string: existing private/secret key.
     *
     * @return string|false Return the generated/derived public key (PEM for OpenSSL, base64 for Sodium),
     *                      or false on failure.
     * @see newKeyPair()
     */
    public static function newPublic(
        ?string $handler = null, 
        ?string $passphrase = null, 
        array $options = []
    ): string|bool
    {
        $handler = self::getResolvedHandler($handler, true);

        if ($handler === self::SODIUM) {
            return base64_encode(sodium_crypto_sign_publickey(
                $options['private_key'] ?? sodium_crypto_sign_keypair()
            ));
        }

        if ($handler !== self::OPENSSL) {
            return false;
        }

        $privateKey = $options['private_key'] 
            ?? self::newPrivate($handler, $passphrase, $options);

        if($privateKey === false){
            return false;
        }

        self::assertKeyType($privateKey, handler: $handler);
        $private = openssl_pkey_get_private($privateKey, $passphrase);

        if ($private === false) {
            return false; 
        }

        $public = openssl_pkey_get_details($private)['key'] ?? false;

        if ($public === false) {
            return false; 
        }

        return $public;
    }

    /**
     * Generate a new private/public key pair using the chosen crypto handler.
     *
     * - For OpenSSL:
     *   A PEM-encoded private key is generated (optionally protected with a passphrase),
     *   and the corresponding public key is extracted.
     *
     * - For Sodium:
     *   A new key-pair is created, and both keys are returned as base64 strings.
     *
     * @param string|null $handler Crypto handler (`Key::OPENSSL` or `Key::SODIUM`).
     *                             If null, defaults to the configured handler.
     * @param string|null $passphrase Optional passphrase for OpenSSL private key generation.
     * @param array<string,mixed> $options Options for generating the key pair:
     *        - For OpenSSL: key size, type, etc.
     *
     * @return array{private:string,public:string}|false 
     *         Return an array with both private and public keys (PEM for OpenSSL, base64 for Sodium),
     *         or false on failure.
     */
    public static function newKeyPair(
        ?string $handler = null, 
        ?string $passphrase = null, 
        array $options = []
    ): array|bool
    {
        $privateKey = self::newPrivate($handler, $passphrase, $options);

        if($privateKey === false){
            return false;
        }

        $public = self::newPublic($handler, $passphrase, [
            'private_key' => $privateKey
        ]);

        return ['private' => $privateKey, 'public' => $public];
    }

    /**
     * Generate a new private key.
     *
     * Creates a private key using the given handler.
     * - OpenSSL: generates a PEM-encoded RSA key (default 2048-bit).
     * - Sodium: generates an Ed25519 private key (Base64-encoded).
     *
     * @param string|null $handler Encryption handler (`Key::OPENSSL` or `Key::SODIUM`).
     * @param string|null $passphrase Optional key passphrase (OpenSSL only).
     * @param array<string,mixed>|null $options Options for key generation (OpenSSL only).
     *
     * @return string|false Return a private key string (PEM or Base64) or false on failure.
     *
     * @link https://php.net/manual/en/function.openssl-pkey-new.php
     * 
     * > **Note:**
     * > If an empty array is passed options will default to:
     * >     - 'private_key_bits': Number of bits (default: 2048).
     * >     - 'private_key_type': Key type (default: OPENSSL_KEYTYPE_RSA).
     * > Pass null to use default Openssl configuration 
     */
    public static function newPrivate(
        ?string $handler = null,
        ?string $passphrase = null, 
        ?array $options = []
    ): string|bool
    {
        $handler = self::getResolvedHandler($handler, true);

        if ($handler === self::SODIUM) {
            return base64_encode(sodium_crypto_sign_secretkey(
                sodium_crypto_sign_keypair()
            ));
        }

        if($handler !== self::OPENSSL){
            return false;
        }

        $options = ($options === []) ? [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ] : $options;

        $private = openssl_pkey_new($options);

        if($private === false){
            return false;
        }

        return openssl_pkey_export($private, $key, $passphrase) ? $key : false;
    }

    /**
     * Generate a random encryption key.
     *
     * Uses the specified handler:
     * - Sodium: generates a secretbox key (32 bytes).
     * - OpenSSL: generates pseudo-random bytes (default: cipher size).
     *
     * @param string|null $handler Encryption handler (`Key::OPENSSL` or `Key::SODIUM`).
     * @param int|null $length Key length in bytes (OpenSSL only; defaults to cipher size).
     * @param bool $toHex If true, return the key as hex; if false, return raw bytes.
     *
     * @return string Return random key string (hex or raw).
     */
    public static function newRandom(
        ?string $handler = null, 
        ?int $length = null, 
        bool $toHex = true
    ): string
    {
        $handler = self::getResolvedHandler($handler, true);

        if($handler === self::SODIUM) {
            $bytes = sodium_crypto_secretbox_keygen();
        }elseif($handler === self::OPENSSL){
            $length ??= self::size();
            $size = (int) ceil($length / 2);

            $bytes = ($handler === self::OPENSSL) 
                ? openssl_random_pseudo_bytes($size)
                : random_bytes($size);
        } else {
            return '';
        }

        return $toHex ? bin2hex($bytes) : $bytes;
    }

    /**
     * Determine the application encryption handler from configuration.
     *
     * Checks if the required PHP extension is loaded and optionally asserts
     * that the handler is valid, throwing exceptions if not.
     *
     * @param bool $assert If true, throws an exception on invalid handler.
     * 
     * @return string|false Return handler name (self::OPENSSL|self::SODIUM) or false if unavailable.
     * @throws EncryptionException If $assert is true and the handler is invalid or unsupported.
     */
    public static function handler(bool $assert = false): string|bool
    {
        return self::getResolvedHandler(null, $assert);
    }

    /**
     * Resolve and validate the encryption handler.
     *
     * This method determines which crypto handler to use (`Key::OPENSSL` or `Key::SODIUM`), 
     * either from the provided argument or from the application’s configuration.
     *
     * Sodium requires version **1.0.14 or higher**.
     *
     * @param string|null $extension Optional handler extension name (`Key::OPENSSL` or `Key::SODIUM`).
     * @param bool $assert If true, throw exception on failure. If false, return `false`.
     *
     * @return string|false Return the resolved handler name, or `false` if not found and `$assert` is false.
     * @throws EncryptionException If `$assert` is true and the handler is missing/unsupported.
     */
    private static function getResolvedHandler(?string $extension, bool $assert = false): string|false
    {
        if ($extension === null) {
            self::config();
            $extension = (string) self::$config->handler;
        }

        $handler = match (strtolower($extension)) {
            self::OPENSSL => extension_loaded('openssl') ? 'openssl' : false,
            self::SODIUM  => extension_loaded('sodium') ? 'sodium' : false,
            default   => false,
        };

        if (!$assert) {
            return $handler;
        }

        if ($handler === false) {
            throw new EncryptionException(sprintf(
                'No encryption handler found for "%s". Ensure the extension is installed and enabled.',
                $extension
            ));
        }

        if ($handler === self::SODIUM && version_compare(SODIUM_LIBRARY_VERSION, '1.0.14', '<')) {
            throw new EncryptionException(sprintf(
                'Sodium extension version %s is unsupported. Minimum required version is 1.0.14.',
                SODIUM_LIBRARY_VERSION
            ));
        }

        return $handler;
    }

    /**
     * Initialize the configuration instance.
     */
    private static function config(): void 
    {
        self::$config ??= new Encryption();
    }

    /**
     * Assert that the given value is a valid key or certificate type.
     *
     * Accepted types:
     * - OpenSSLAsymmetricKey
     * - OpenSSLCertificate
     * - array
     * - string
     *
     * @param mixed $value The value to validate.
     *
     * @throws EncryptionException If the value is not one of the accepted types.
     */
    private static function assertKeyType(
        mixed $value, 
        bool $nullable = false, 
        string|bool $handler = self::OPENSSL
    ): void
    {
        if(!$handler || !in_array($handler, [self::SODIUM, self::OPENSSL], true)){
             throw new EncryptionException(
                sprintf("Unsupported handler '%s'. Use (Key::OPENSSL or Key::SODIUM).", $handler),
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        if($handler === self::SODIUM && is_string($value)){
            return;
        }

        if (
            $handler === self::OPENSSL && (
                ($value instanceof OpenSSLAsymmetricKey) ||
                ($value instanceof OpenSSLCertificate) ||
                is_array($value) ||
                is_string($value)
            )
        ) {
            return;
        }

        if($nullable && ($value === null || $value === false)){
            return;
        }

        $expected = ($handler === self::OPENSSL) 
            ? 'OpenSSLAsymmetricKey, OpenSSLCertificate, array, or string'
            : 'string';

        throw new EncryptionException(sprintf(
            'Invalid key type: %s. Expected: %s.',
            get_debug_type($value),
            $expected
        ), ErrorCode::INVALID_ARGUMENTS);
    }
}