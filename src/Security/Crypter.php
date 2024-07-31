<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Security;

use \App\Config\Encryption;
use \Luminova\Interface\EncryptionInterface;
use \Luminova\Security\Encryption\OpenSSL;
use \Luminova\Security\Encryption\Sodium;
use \Luminova\Exceptions\EncryptionException;

/**
 * The Crypter class provides methods for encrypting and decrypting data using encryption algorithms in OpenSSL or Sodium.
*/
final class Crypter 
{
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
     * @var Encryption $config
    */
    private static ?Encryption $config = null;

    /**
     * Get an instance of OpenSSL or Sodium encryption.
     * 
     * @return class-object<EncryptionInterface> An instance of the encryption class.
     * 
     * @throws EncryptionException Throws when an empty encryption key is passed.
     * @throws EncryptionException Throws when invalid handler is specified or handler extension not loaded.
     */
    public static function getInstance(): EncryptionInterface
    {
        self::initConfig();
        $key = env('app.key', '');

        if ($key === '') {
            throw new EncryptionException('Encryption key is required. Run the command "php novakit generate:key" to generate a new application key.');
        }

        $handler = static::handler();
        
        if ($handler === false) {
            throw new EncryptionException('Invalid encryption handler or OpenSSL or Sodium extension is not loaded in your envirnment.');
        }

        if ($handler === 'sodium' && version_compare(SODIUM_LIBRARY_VERSION, '1.0.14', '<')) {
            throw new EncryptionException('The Sodium extension is not loaded or you are using a version earlier than 1.0.14.');
        }

        $handlers = [
            'openssl' => '\\' . OpenSSL::class,
            'sodium' => '\\' . Sodium::class
        ];

        $method = strtoupper(self::$config->method);
        $size = static::$ciphers[$method]['size'] ?? 16;
        $encryption = $handlers[$handler];

        return new $encryption($key, $method, $size);
    }

     /**
     * Determine if the given key and cipher method are valid.
     *
     * @param string $key The encryption key.
     * @param string $method The encryption cipher method.
     * 
     * @return bool Return true if encryption method and key are valid false otherwise.
     */
    public static function supported(string $key, string $method): bool
    {
        $cipher = strtoupper($method);

        if (!isset(static::$ciphers[$cipher])) {
            return false;
        }

        return mb_strlen($key, '8bit') === static::$ciphers[$cipher]['size'];
    }

    /**
     * Encrypt the given data using OpenSSL or Sodium encryption.
     *
     * @param string $data The data to encrypt.
     * 
     * @return string|bool The encrypted data, or false if encryption fails.
     * 
     * @throws EncryptionException Throws when invalid encryption data is passed.
    */
    public static function encrypt(string $data): string|bool 
    {
        $crypt = static::getInstance();

        try {
            $crypt->setNonce();
            $crypt->setData($data);

            return $crypt->encrypt();
        } catch (EncryptionException $e) {
            EncryptionException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return false;
    }

    /**
     * Decrypt the given data using OpenSSL or Sodium encryption.
     *
     * @param string $data The encrypted data to decrypt.
     * 
     * @return string|false|null The decrypted data, or null if decryption fails.
     * @throws EncryptionException Throws when invalid encryption data is passed.
     */
    public static function decrypt(string $data): string|bool|null
    {
        $crypt = static::getInstance();

        try {
            $crypt->setData($data);

            return $crypt->decrypt();
        } catch (EncryptionException $e) {
            EncryptionException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return false;
    }

    /** 
    * Generate a hash representation of user password string.
    *
    * @param string $password The actual password to hash.
    * @param array|null $options Optional password hash options.
    *
    * @return string|false Return hashed password otherwise false on empty password. 
    *
    * Default Options:
    *  ```
    * [
	*		'cost' => 12,
	*		'algorithm' => PASSWORD_BCRYPT,
    *       //'salt' => 'my_custom_salt', // You can optionally specify password salt
	* ];
    * ```
    */
	public static function password(string $password, ?array $options = null): string|bool
	{
		if($password === ''){
			return false;
		}

		$options ??= [
			'cost' => 12,
			'algorithm' => PASSWORD_BCRYPT
		];

		return password_hash($password, PASSWORD_BCRYPT, $options);
	}
	
	/** 
	* Verify a password against it stored hash value to determine if if they match.
	*
	* @param string $password The password string to verify.
	* @param string $hash The password stored hash value.
	*
	* @return bool Return true if password matches with the hash, otherwise false.
	*/
	public static function verify(string $password, string $hash): bool 
	{
		if($password === '' || $hash === ''){
			return false;
		}

		return password_verify($password, $hash);
	}

    /**
     * Generate a random key string using your default encryption handler.
     * For private key, or public key generation it uses openssl rsa.
     *
     * @param string $type The type of key to generate: (e.g, 'random', 'private', or 'public').
     * @param array $options Additional options for key generation.
     * 
     * Options Keys: 
     *      - For 'random' type, use key 'length' to specifies the length of the random string.
     *      - For 'private' type, use key 'private_key_bits default(2048)' to specifies the number of bits in the private key,
     *        and 'private_key_type (default: OPENSSL_KEYTYPE_RSA)' specifies the type of the private key (e.g., OPENSSL_KEYTYPE_RSA).
     *      - For 'public' type, use key 'private_key' to specify the private key string from which to derive the public key
     *        if the key private_key is not specified, it generate a new private key to use.
     * 
     * @return string|array<string,string>|false Return the generated key(s), an array of private and public key, or false on failure. 
    */
    public static function generate_key(string $type = 'random', array $options = []): array|string|bool
    {
        self::initConfig();
        $handler = static::handler();

        if ($type === 'random') {
            $length = ($options['length'] ?? static::$ciphers[strtoupper(self::$config->method)]['size'] ?? 16);

            if($handler === 'openssl') {
                $random = openssl_random_pseudo_bytes($length / 2);
            }elseif($handler === 'sodium') {
                $random = sodium_crypto_secretbox_keygen();
            }else{
                $random = random_bytes($length / 2);
            }

            return bin2hex($random);
        }

        if ($type === 'private') {
            $config = [
                'private_key_bits' => $options['private_key_bits'] ?? 2048,
                'private_key_type' => $options['private_key_type'] ?? OPENSSL_KEYTYPE_RSA,
            ];
            
            $private = openssl_pkey_new($config);

            if($private === false){
                return false;
            }

            openssl_pkey_export($private, $key);

            return $key;
        }

        if ($type === 'public') {
            $privateKey = $options['private_key'] ?? static::generate_key('private', $options);
            
            $private = openssl_pkey_get_private($privateKey);

            if ($private === false) {
                return false; 
            }

            $public = openssl_pkey_get_details($private)['key'];

            if ($public === false) {
                return false; 
            }

            return [
                'private_key' => $privateKey,
                'public_key' => $public
            ];
        }

        return false;
    }

    /**
     * Get the encryption extension handler.
     * 
     * @return string|false Return handler name or false if not found.
    */
    private static function handler(): string|bool
    {
        $handler = strtolower(self::$config->handler);

        if($handler === 'openssl' && extension_loaded('openssl')) {
            return $handler;
        }

        if($handler === 'sodium' && extension_loaded('sodium')) {
            return $handler;
        }
        
        return false;
    }

    /**
     * Initialize the configuration
    */
    private static function initConfig(): void 
    {
        self::$config ??= new Encryption();
    }
}