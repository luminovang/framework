<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security\Encryption;

use \Luminova\Interface\EncryptionInterface;
use \Luminova\Exceptions\EncryptionException;
use \SodiumException;

/**
 * Crypt Sodium encryption class.
 */
class Sodium implements EncryptionInterface
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
     * @var string $nonce
     */
    private string $nonce = '';

    /**
     * {@inheritdoc}
     */
    public function __construct(?string $key = null, ?string $method = null, int $size = 16)
    {
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
        $this->key = sodium_crypto_generichash($key, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
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
        if ($string === null) {
            return random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        }

        return mb_substr($string, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    }

    /**
     * {@inheritdoc}
     */
    public function setMethod(string $method, int $size = 16): void {}

    /**
     * {@inheritdoc}
     */
    public function encrypt(): string|bool
    {
        if (!$this->valid()) {
            throw new EncryptionException('Invalid contraption params!');
        }

        try{
            $encrypted = sodium_crypto_secretbox($this->message, $this->nonce, $this->key);

            if ($encrypted === false) {
                return false;
            }

            $cipher = base64_encode(json_encode([
                'nonce' => base64_encode($this->nonce),
                'hash' => null,
                'encrypted' => base64_encode($encrypted),
            ]));

            sodium_memzero($encrypted);
            $this->free();

            return $cipher;
        }catch(SodiumException $e){
            EncryptionException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return false;
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

        if (!is_array($data) || !isset($data['nonce'], $data['encrypted'])) {
            return false;
        }

        $nonce = base64_decode($data['nonce']);
        $encrypted = base64_decode($data['encrypted']);

        if (mb_strlen($encrypted, '8bit') < SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new EncryptionException('Decryption error, message was truncated or tampered with.');
        }

        try{
            $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key);

            if ($decrypted === false) {
                return false;
            }

            $this->free();

            return trim($decrypted);
        }catch(SodiumException $e){
            EncryptionException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return false;
    }

    /**
     * Validate encryption parameters.
     *
     * @return bool True if parameters are valid, false otherwise.
     */
    private function valid(): bool
    {
        return $this->message !== '' && $this->key !== '';
    }

    /**
     * {@inheritdoc}
     */
    public function free(): void 
    {
        try{
            if(isset($this->key)){
                sodium_memzero($this->key);
            }

            if(isset($this->nonce)){
                sodium_memzero($this->nonce);
            }
        }catch(SodiumException){}
    }
}
