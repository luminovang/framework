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
namespace Luminova\Sessions\Managers;

use \Closure;
use \Throwable;
use Luminova\Logger\Logger;
use Luminova\Utility\Encoder;
use Luminova\Security\Encryption\Crypter;
use Luminova\Exceptions\{JsonException, RuntimeException};

final class Cookie extends AbstractSessionManager
{
    /**
     * The cookie ID name.
     * 
     * @var string COOKIE_ID
     */
    private const COOKIE_ID = 'PHPCKSESSID';

    /**
     * Sessions are enabled, but no session exists.
     * 
     * @var int NONE 
     */
    private const NONE = 1;

    /**
     * A session is currently active.
     * 
     * @var int ACTIVE 
     */
    private const ACTIVE = 2;

    /**
     * Cookie write close.
     * 
     * @var bool $writeClose
     */
    private static bool $writeClose = false;

    /**
     * The session id
     * 
     * @var string|null $sessionId
     */
    private static ?string $sessionId = null;

    /** 
     * {@inheritdoc}
     */
    public function isEmpty(): bool 
    {
        return !isset($_COOKIE[$this->getKey()]);
    }

    /** 
     * {@inheritdoc}
     */
    public function isClosed(): bool 
    {
        return self::$writeClose === true;
    }

    /** 
     * {@inheritdoc}
     */
    public static function isValidId(string $sessionId): bool
    {
        return (
            strlen($sessionId) === 16 ||
            strlen($sessionId) === 32
        ) && ctype_xdigit($sessionId);
    }

    /** 
     * {@inheritdoc}
     */
    public function setItems(array $items): self
    {
        $this->write(array_merge(
            (array) ($this->getItems()['__data'] ?? []),
            $items
        ));

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function getItems(): array
    {
        $key = $this->getKey();

        return $_COOKIE[$key] = $this->open();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $name): self
    {
        if(self::$writeClose){
            return $this;
        }

        $data = $this->toArray();
        $key = $this->getKey();

        if(!isset($_COOKIE[$key])) {
            return $this;
        }

        unset($data[$name]);

        $this->write($data);
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function clear(): bool 
    {
        if(self::$writeClose){
            return false;
        }

        $cleared = 0;
        $expire = time() - $this->config->expiration;

        $this->onEach(function(string $name, mixed $_) use ($expire, &$cleared): void {
            if($this->store(
                $name, 
                '', 
                $expire,
                encode: false
            )){
                unset($_COOKIE[$name]);
                $cleared++;
            }
        }, true);

        return $cleared > 0;
    }

    /** 
     * {@inheritdoc}
     */
    public function close(): bool 
    {
        if($this->status() !== self::ACTIVE){
            return false;
        }

        if(!$this->setCookieId()){
            return false;
        }

        self::$writeClose = true;
        $_COOKIE[self::COOKIE_ID] = null;

        return true;
    }

    /** 
     * {@inheritdoc}
     */
    public function status():int
    {
        $id = $this->getId();

        return ($id !== null && self::isValidId($id))
            ? self::ACTIVE 
            : self::NONE;
    }

    /** 
     * {@inheritdoc}
     */
    public function start(?string $sessionId = null): bool
    {
        self::$writeClose = false;
        $error = null;

        try{
            if ($this->status() === self::ACTIVE) {
                if ($sessionId === null || $sessionId === $this->getId()) {
                    return true;
                }

                $error = 'A different session ID cannot be started while a session is already active.';

                return false;
            }

            if ($sessionId !== null && !self::isValidId($sessionId)) {
                $error = "The provided session cookie value '{$sessionId}' is invalid.";

                if (!PRODUCTION) {
                    return false;
                }

                $error .= " A new session cookie value will be generated.";
                $sessionId = null;
            }

            return $this->sessionRegenerateId($sessionId);
        } finally {
            if($error !== null){
                $error = "Session Cookie Error: {$error}";

                if (!PRODUCTION) {
                    throw new RuntimeException($error);
                }

                Logger::error($error);
            }
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function regenerateId(bool $clearSessionData = true): string|bool
    {
        return $this->sessionRegenerateId(
            isRegenerate: true, 
            clearSessionData: $clearSessionData
        ) 
            ? ($_COOKIE[self::COOKIE_ID] ?? false) 
            : false;
    }

    /** 
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        return self::$sessionId ??= (
            ($_COOKIE[self::COOKIE_ID] ?? null) ?: null
        );
    }

    /** 
     * {@inheritdoc}
     */
    public function hasStorage(string $storage): bool
    {
        $id = $this->getId();
        $session = self::KEY_PREFIX . "{$id}_{$storage}";

        foreach (array_keys($_COOKIE) as $name) {
            if(str_starts_with($name, $session)){
                return true;
            }
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function toArray(?string $storage = null): array
    {
        $key = $this->getKey($storage);
        $_COOKIE[$key] = $this->open($storage);

        return (array) $_COOKIE[$key];
    }

    /**
     * Generate storage key.
     * 
     * @param string $storage Optional storage name.
     * 
     * @return string Storage name.
     */
    private function getKey(?string $storage = null, ?string $id = null): string 
    {
        $id ??= $this->getId();
        $storage ??= $this->storage;

        return self::KEY_PREFIX . "{$id}_{$storage}_{$this->namespace}";
    }

    /**
     * Write cookie data to cookie application storage table.
     *
     * @param array $data Array cookie contents.
     * @param string|null $storage Optional storage name.
     * 
     * @return void 
     */
    private function write(array $data, ?string $storage = null): void
    {
        if(self::$writeClose){
            return;
        }

        $key = $this->getKey($storage);

        $_COOKIE[$key] = $this->open();
        $_COOKIE[$key]['__data'] = $data;
        $_COOKIE[$key]['__secure'] = '0';

        if($this->config->encryptCookieData){
            $encrypted = Crypter::encrypt(json_encode($data));

            if($encrypted !== false){
                $_COOKIE[$key]['__secure'] = '1';
                $_COOKIE[$key]['__data'] = $encrypted;
            }
        }

        $this->store(
            $key, 
            serialize($_COOKIE[$key])
        );
    }

    /**
     * Check if the cookie data for a specific storage is encrypted.
     *
     * This method determines whether the cookie data for a given storage
     * is encrypted based on the configuration and stored cookie information.
     *
     * @param array $data The data to check.
     *
     * @return bool Returns true if the cookie data is encrypted, false otherwise.
     */
    private function isEncrypted(array $data): bool 
    {
        return (
            ($data['__secure'] ?? '0') === '1'
            && ($data['__data'] ?? '') !== ''
            && is_string($data['__data'] ?? [])
        );
    }

    /**
     * Refresh the cookie expiration time.
     * 
     * @param string $name The cookie name.
     * @param mixed $value The cookie value.
     * 
     * @return void
     */
    private function refresh(string $name, mixed $value): void
    {
        $this->store(
            $name, 
            is_array($value) ? serialize($value) : $value
        );
    }

    /**
     * Save cookie data.
     *
     * Cookie values are optionally compressed, then Base64 encoded before being
     * sent to the browser. Encrypted cookie data is not compressed.
     *
     * @param string $name Cookie name.
     * @param string $value Cookie contents.
     * @param int|null $expiry  Cookie expiration timestamp.
     * @param string|null $samesite SameSite cookie attribute.
     * @param bool $encode Whether to encode the cookie value.
     *
     * @return bool True if the cookie was successfully set, otherwise false.
     */
    private function store(
        string $name,
        string $value,
        ?int $expiry = null,
        ?string $samesite = null,
        bool $encode = true
    ): bool 
    {
        if (
            $value !== ''
            && !str_starts_with($value, 'be:')
        ) {
            if (
                $encode
                && !$this->config->encryptCookieData
                && !str_contains($value, ':cp:')
            ) {
                [$encoding, $compressed,] = Encoder::compress(
                    $value,
                    minLength: 0
                );

                if ($encoding !== null) {
                    $value = "{$encoding}:cp:{$compressed}";
                }
            }

            $value = 'be:' . base64_encode($value);

            if (strlen($name) + strlen($value) > 3800) {
                return false;
            }
        }

        return setcookie($name, $value, [
            'expires'  => $expiry ?? (time() + $this->config->expiration),
            'path'     => $this->config->sessionPath,
            'domain'   => $this->config->sessionDomain,
            'secure'   => PRODUCTION,
            'httponly' => true,
            'samesite' => $samesite ?? $this->config->sameSite,
        ]);
    }

    /**
     * Update the session ID cookie.
     *
     * @param string $id The session ID to store.
     * @param int|null $expiry The Unix expiration timestamp, or null to use the
     *                         configured session expiration.
     *
     * @return bool Return true if the cookie was successfully set, false otherwise.
     */
    private function setCookieId(
        string $id = '',
        ?int $expiry = null
    ): bool 
    {
        return setcookie(self::COOKIE_ID, $id, [
            'expires'  => $expiry ?? (time() + $this->config->expiration),
            'path'     => '/',
            'domain'   => $this->config->sessionDomain ?: null,
            'secure'   => PRODUCTION,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Open and decode cookie data.
     *
     * The cookie value is Base64 decoded, decompressed when necessary, and then
     * unserialized. The encrypted `__data` value is decrypted separately.
     *
     * @param string|null $storage Storage name.
     *
     * @return array Decoded cookie data, or an empty array on failure.
     */
    private function open(?string $storage = null): array
    {
        $key = $this->getKey($storage);

        return $this->parse(
            $_COOKIE[$key] ?? null,
            $key
        );

    }

    /**
     * Undocumented function
     *
     * @param mixed $data
     * @param string|null $key
     * @return array
     */
    private function parse(mixed $data, ?string $key = null): array
    {
        if ($data === null || $data === '') {
            return [];
        }

        if(isset($data['__decoded'])){
            return $data;
        }

        if (!is_string($data)) {
            $data = (array) $data;

            $data['__decoded'] = true;
            return $data;
        }

        $error = null;

        try {
            if (!str_starts_with($data, 'be:')) {
                $error = "invalid cookie encoding for key: '%s'";
                return [];
            }

            $items = base64_decode(substr($data, 3), true);

            if ($items === false || $items === '') {
                $error = "failed to decode cookie key: '%s'";
                return [];
            }

            if (str_contains($items, ':cp:')) {
                [$encoding, $compressed] = explode(':cp:', $items, 2);

                [, $items] = Encoder::decompress(
                    $compressed,
                    encoding: $encoding
                );

                if (!is_string($items)) {
                    $error = "failed to decompress cookie key: '%s'";
                    return [];
                }
            }

            try {
                $items = unserialize($items, [
                    'allowed_classes' => false,
                ]);
            } catch (Throwable $e) {
                $error = "failed to unserialize cookie key: '%s': {$e->getMessage()}";
                return [];
            }

            if (!is_array($items)) {
                $error = "invalid cookie data key: '%s'";
                return [];
            }

            if ($this->isEncrypted($items)) {
                $items['__data'] = $this->decode(
                    $items['__data'] ?? ''
                );
            }

            $items['__decoded'] = true;

            return $items;
        } finally {
            if($key !== null){
                $this->refresh($key, $data);

                if($error !== null){
                    Logger::error(sprintf(
                        "Session Cookie Error: {$error}", 
                        $key
                    ));
                }
            }
        }
    }

    /**
     * Decode encrypted cookie data.
     *
     * @param mixed $data Cookie data.
     *
     * @return mixed Decoded data, or false when decryption fails.
     */
    private function decode(mixed $data): mixed
    {
        if ($data === '' || !is_string($data)) {
            return $data;
        }

        $data = Crypter::decrypt($data);

        if ($data === false) {
            Logger::error(
                'Session Cookie Error: failed to decrypt cookie data'
            );

            return false;
        }

        if (!is_string($data)) {
            return $data;
        }

        try {
            return json_decode(
                $data,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $data;
        }
    }

    /**
     * Undocumented function
     *
     * @param \Closure $callback
     * @return void
     */
    private function onEach(Closure $callback, bool $thisStorage = false): void 
    {
        $session = self::KEY_PREFIX . $this->getId();

        if($thisStorage){
            $session .= "_{$this->storage}";
        }

        foreach ($_COOKIE as $name => $value) {
            if(!str_starts_with($name, $session)){
                continue;
            }

            $callback($name, $value);
        }
    }

    /**
     * Generate or refresh the cookie session ID.
     *
     * @param string|null $sessionId Optional session ID to use.
     * @param bool $isRegenerate Whether to generate a new session ID.
     * @param bool $clearSessionData Whether to clear existing session data.
     *
     * @return bool True if successful, otherwise false.
     */
    private function sessionRegenerateId(
        ?string $sessionId = null,
        bool $isRegenerate = false,
        bool $clearSessionData = false
    ): bool 
    {
        if (!$isRegenerate && $sessionId === null) {
            $sessionId = $_COOKIE[self::COOKIE_ID] ?? null;
        }

        $sessionId ??= bin2hex(random_bytes(8));

        if (!$this->setCookieId($sessionId)) {
            return false;
        }

        if (!$isRegenerate && !$clearSessionData) {
            self::$sessionId = $sessionId;
            $_COOKIE[self::COOKIE_ID] = $sessionId;

            return true;
        }

        if($clearSessionData){
            $this->clear();
            
            self::$sessionId = $sessionId;
            $_COOKIE[self::COOKIE_ID] = $sessionId;

            return true;
        }

        $old = $this->getId();

        // Copy old session data to new
        $this->onEach(function(string $name, mixed $value) use ($old, $sessionId): void {
            $new = str_replace($old, $sessionId, $name);

            $this->refresh($new, $value);
            $_COOKIE[$new] = is_string($value) ? $this->parse($value) : $value;

            unset($_COOKIE[$name]);
        });

        self::$sessionId = $sessionId;
        $_COOKIE[self::COOKIE_ID] = $sessionId;

        return true;
    }
}