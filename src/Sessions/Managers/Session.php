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

use Luminova\Logger\Logger;
use Luminova\Exceptions\RuntimeException;

final class Session extends AbstractSessionManager
{
    /**
     * Session ID bits per character.
     * 
     * @var array BITS_CHAR
     */
    private const BITS_CHAR = [
        4 => '[0-9a-f]',
        0 => '[0-9a-f]',
        5 => '[0-9a-v]',
        6 => '[0-9a-zA-Z,-]'
    ];

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
        return !isset($_SESSION[$this->getKey()]);
    }

    /** 
     * {@inheritdoc}
     */
    public function isClosed(): bool 
    {
        return session_status() !== PHP_SESSION_ACTIVE 
            || self::$writeClose;
    }

    /** 
     * {@inheritdoc}
     */
    public static function isValidId(string $sessionId): bool
    {
        $bitsPerCharacter = (int) ini_get('session.sid_bits_per_character');

        if(!isset(self::BITS_CHAR[$bitsPerCharacter])){
            return false;
        }

        $sidLength = (int) ini_get('session.sid_length');
    
        if ($sidLength <= 0) {
            return false;
        }

        $pattern = self::BITS_CHAR[$bitsPerCharacter];

        return (bool) preg_match("/^{$pattern}{{$sidLength}}$/", $sessionId);
    }

    /** 
     * {@inheritdoc}
     */
    public function setItems(array $items): self
    {
        $key = $this->getKey();

        $_SESSION[$key] = (array) ($_SESSION[$key] ?? []);
        $_SESSION[$key] = array_merge(
            $_SESSION[$key],
            $items
        );

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function deleteItem(string $name): self
    {
        $key = $this->getKey();
        $items = (array) ($_SESSION[$key] ?? []);

        if(array_key_exists($name, $items)){
            unset($_SESSION[$key][$name]);
            return $this;
        }

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function close(): bool 
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return self::$writeClose = session_write_close();
    }

    /** 
     * {@inheritdoc}
     */
    public function start(?string $sessionId = null): bool
    {
        $error = null;

        try{
            if (session_status() === PHP_SESSION_ACTIVE) {
                if ($sessionId === null || $sessionId === session_id()) {
                    self::$sessionId = session_id();
                    return true;
                }

                $error = 'A different session ID cannot be started 
                while a session is already active.';

                return false;
            }

            if ($sessionId !== null && !self::isValidId($sessionId)) {
                $bitsChar = (int) ini_get('session.sid_bits_per_character');

                $error = isset(self::BITS_CHAR[$bitsChar]) 
                    ? "The provided session ID '{$sessionId}' is invalid."
                    : "Unsupported session.sid_bits_per_character value: '{$bitsChar}'.";

                if (!PRODUCTION) {
                    return false;
                }

                $error .= " A new session ID will be generated.";
                $sessionId = null;
            }

            if($sessionId !== null){
                session_id($sessionId);
            }

            if (!session_start()) {
                return false;
            }

            self::$sessionId = session_id();

            return true;
        } finally {
            if($error !== null){
                $error = "Session Error: {$error}";

                if (PRODUCTION) {
                    Logger::error($error);
                }

                throw new RuntimeException($error);
            }
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function status():int
    {
        return session_status();
    }

    /** 
     * {@inheritdoc}
     */
    public function regenerateId(bool $clearSessionData = true): string|bool
    {
        return session_regenerate_id($clearSessionData) 
            ? session_id() 
            : false;
    }

    /** 
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        return (session_id() ?: self::$sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $delete = 0;

        foreach ($_SESSION as $name => $_) {
            if(!str_starts_with($name, self::KEY_PREFIX . "{$this->storage}")){
                continue;
            }

            unset($_SESSION[$name]);
            $delete++;
        }

        return $delete > 0;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasStorage(string $storage): bool
    {
        foreach(array_keys($_SESSION) as $key){
            if(str_starts_with($key, self::KEY_PREFIX . "{$storage}")){
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
        return (array) ($_SESSION[$this->getKey($storage)] ?? []);
    }

    /** 
     * {@inheritdoc}
     */
    public function getItems(): array
    {
       return (array) ($_SESSION[$this->getKey()] ?? []);
    }

    /**
     * Generate storage key.
     * 
     * @param string $storage Optional storage name.
     * 
     * @return string Storage name.
     */
    private function getKey(?string $storage = null): string 
    {
        $storage ??= $this->storage;

        return self::KEY_PREFIX . "{$storage}_{$this->namespace}";
    }
}