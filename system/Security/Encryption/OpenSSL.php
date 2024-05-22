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

use \Luminova\Interface\EncryptionInterface;
use \Luminova\Exceptions\EncryptionException;
use \App\Controllers\Config\Encryption;

/**
 * Crypt OpenSSL encryption class.
 */
class OpenSSL implements EncryptionInterface
{
    /**
     * @var string $key
    */
    private string $key = '';

    /**
     * @var string $message
    */
    private string $message = '';

    /**
     * @var string $method
    */
    private string $method = 'AES-128-CBC';

    /**
     * @var string $nonce
    */
    private string $nonce = '';

    /**
     * @var int $size
    */
    private int $size = 16;

    /**
     * HMAC digest to use
     *
     * @var string $digest
     */
    private string $digest = 'SHA512';

    /**
     * {@inheritdoc}
    */
    public function __construct(?string $key = null, ?string $method = null, int $size = 16)
    {
        $this->digest = Encryption::$digest;
        
        if($method !== null){
            $this->setMethod($method, $size);
        }

        if ($key !== null) {
            $this->setKey($key);
        }
    }

    /**
     * {@inheritdoc}
    */
    public function setData(string $data): void
    {
        $this->message = $data;
    }

    /**
     * {@inheritdoc}
    */
    public function setKey(string $key): void
    {
        $this->key = hash_hkdf($this->digest, $key, 0, Encryption::$keyInfo);
    }

    /**
     * {@inheritdoc}
     */
    public function setNonce(?string $nonce = null): void
    {
        $this->nonce = $nonce ?? $this->nonce();
    }

    /**
     * {@inheritdoc}
    */
    public function nonce(?string $string = null): string
    {
        if($string === null){
            return openssl_random_pseudo_bytes($this->size);
        }

        return mb_substr($string, 0, $this->size, '8bit');
    }

    /**
     * {@inheritdoc}
    */
    public function setMethod(string $method, int $size = 16): void
    {
        $this->method = $method;
        $this->size = openssl_cipher_iv_length($method);

        if($this->size === false){
            $this->size = $size;
        }
    }

    /**
     * {@inheritdoc}
    */
    public function encrypt(): string|bool
    {
        if (!$this->valid()) {
            throw new EncryptionException('Invalid contraption params!');
        }

        $crypt = openssl_encrypt($this->message, $this->method, $this->key, OPENSSL_RAW_DATA, $this->nonce);
        

        if ($crypt === false) {
            return false;
        }

        $message = trim($crypt);
        $hash = hash_hmac($this->digest, $message . $this->nonce, $this->key, true);

        $cypher = base64_encode(json_encode([
            'nonce' => base64_encode($this->nonce),
            'hash' => base64_encode($hash),
            'encrypted' => base64_encode($message),
        ]));


        $this->free();

        return $cypher;
    }

    /**
     * {@inheritdoc}
    */
    public function decrypt(): string|bool
    {
        if (!$this->valid()) {
            throw new EncryptionException('Invalid decryption parameters!');
        }

        $data = json_decode(base64_decode($this->message), true);

        if (!is_array($data) || !isset($data['nonce'], $data['hash'], $data['encrypted'])) {
            return false;
        }

        $nonce = base64_decode($data['nonce']);
        $hash = base64_decode($data['hash']);
        $encrypted = base64_decode($data['encrypted']);

        if (mb_strlen($encrypted, '8bit') < $this->size) {
            throw new EncryptionException('Decription error, message was truncated or tampered with.');
        }

        $expected = hash_hmac($this->digest, $encrypted . $nonce, $this->key, true);

        if (!hash_equals($hash, $expected)) {
            return false;
        }

        $decrypted = openssl_decrypt($encrypted, $this->method, $this->key, OPENSSL_RAW_DATA, $nonce);

        if ($decrypted === false) {
            return false;
        }

        $this->free();

        return trim($decrypted);
    }

     /**
     * {@inheritdoc}
    */
    public function free(): void 
    {
        $this->key = '';
        $this->nonce = '';
    }

    /**
     * Validate encryption parameters.
     *
     * @return bool True if parameters are valid, false otherwise.
    */
    private function valid(): bool
    {
        return $this->message !== '' && $this->method !== '' && $this->key !== '';
    }
}