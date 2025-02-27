<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Sessions\Managers;

use \Luminova\Base\BaseConfig;
use \Luminova\Interface\SessionManagerInterface;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\JsonException;
use \Throwable;

final class Session implements SessionManagerInterface 
{
    /**
     * Session configuration. 
     * 
     * @var BaseConfig $config
     */
    private ?BaseConfig $config = null;

    /**
     * The session storage index name.
     * 
     * @var string $table
     */
    private static string $table = 'default';

    /**
     * {@inheritdoc}
     */
    public function __construct(private string $storage = 'global') {}

    /**
     * {@inheritdoc}
     */
    public function setConfig(BaseConfig $config): void
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorage(string $storage): self 
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTable(string $table): self 
    {
        self::$table = $table;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStorage(): string 
    {
        return $this->storage;
    }

    /** 
     * {@inheritdoc}
     */
    public function getItem(string $index, mixed $default = null): mixed
    {
        return $this->getItems()[$index] ?? $default;
    }

    /** 
     * {@inheritdoc}
     */
    public function setItem(string $index, mixed $data, ?string $storage = null): self
    {
        return $this->setItems([$index => $data], $storage);
    }

    /** 
     * {@inheritdoc}
     */
    public function setItems(array $data, ?string $storage = null): self
    {
        $storage ??= $this->storage;
        $_SESSION[self::$table][$storage] = $_SESSION[self::$table][$storage] ?? [];
        $_SESSION[self::$table][$storage] = array_merge(
            (array) $_SESSION[self::$table][$storage],
            $data
        );

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function deleteItem(?string $index = null, ?string $storage = null): self
    {
        $storage ??= $this->storage;

        if($storage && isset($_SESSION[self::$table][$storage])){
            if($index){
                $_SESSION[self::$table][$storage][$index] = [];

                unset($_SESSION[self::$table][$storage][$index]);
                return $this;
            }

            $_SESSION[self::$table][$storage] = [];
            unset($_SESSION[self::$table][$storage]);
        }

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function commit(): self 
    {
        session_write_close();
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function start(?string $sessionId = null): bool
    {
        if(!$sessionId){
            return session_start();
        }

        if(self::isValidId($sessionId)){
            return session_id($sessionId) 
                ? session_start() 
                : (PRODUCTION ? session_start() : false);
        }

        $error = "Session Error: The provided session ID '{$sessionId}' is invalid.";

        if(PRODUCTION){
            Logger::error("{$error} A new session ID will be generated.");
            return session_start();
        }

        throw new RuntimeException($error);
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
    public function regenerateId(): string|bool
    {
        return session_regenerate_id(true) ? session_id() : false;
    }

    /** 
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        return (session_id()?:null);
    }

    /** 
     * {@inheritdoc}
     */
    public static function isValidId(string $sessionId): bool
    {

        $bitsPerCharacter = filter_var(ini_get('session.sid_bits_per_character'), FILTER_VALIDATE_INT);
        $sidLength = filter_var(ini_get('session.sid_length'), FILTER_VALIDATE_INT);
    
        if ($bitsPerCharacter === false || $sidLength === false) {
            return false;
        }

        $pattern = match ($bitsPerCharacter) {
            4 => '[0-9a-f]',
            5 => '[0-9a-v]',
            6 => '[0-9a-zA-Z,-]',
            default => throw new RuntimeException(
                sprintf("Unsupported session.sid_bits_per_character value: '%d'.", $bitsPerCharacter),
                RuntimeException::NOT_SUPPORTED
            )
        };

        return (bool) preg_match("/^{$pattern}{{$sidLength}}$/", $sessionId);
    }

    /** 
     * {@inheritdoc}
     */
    public function destroy(bool $allData = false): bool
    {
        if ($allData) {
            $_SESSION = [];
            $cookieName = session_name() ?: $this->config?->cookieName;

            session_unset();
            session_destroy();
            session_regenerate_id(true);

            return $cookieName ? setcookie(
                $cookieName,
                '',
                [
                    'expires' => time() - $this->config->expiration, 
                    'path' => $this->config->sessionPath ?? '/', 
                    'domain' => $this->config->sessionDomain ?? '', 
                    'secure' => true, 
                    'httponly' => true,
                    'samesite' => $this->config->sameSite ?? 'Strict'
                ]
            ) : true;
        }

        if (isset($_SESSION[self::$table])) {
            $_SESSION[self::$table] = [];
            unset($_SESSION[self::$table]);
            return true;
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        if(isset($_SESSION[self::$table][$this->storage])){
            return isset($_SESSION[self::$table][$this->storage][$key]);
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasStorage(string $storage): bool
    {
        return isset($_SESSION[self::$table][$storage]);
    }

    /** 
     * {@inheritdoc}
     */
    public function getResult(string $type = 'array'): array|object
    {
        if($type === 'array'){
            return (array) $_SESSION[self::$table] ?? [];
        }

        $result = $_SESSION[self::$table] ?? [];

        if($result === []){
            return (object) $result;
        }

        try {
            return (object) json_decode(json_encode($result, JSON_THROW_ON_ERROR));
        }catch(Throwable $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function toAs(string $type = 'array', ?string $index = null): object|array|null
    {
        $result = $this->getItems();
        $result = ($index !== null) ? ($result[$index] ?? null) : $result;

        if($result === null){
            return null;
        }
    
        if($type === 'array'){
            return (array) $result;
        }

        if($result === []){
            return (object) $result;
        }

        try {
            return (object) json_decode(json_encode($result, JSON_THROW_ON_ERROR));
        }catch(Throwable $e){
            if(is_scalar($result)){
                return ($type === 'array') ? [$result] : (object)[$result];
            }

            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function getItems(?string $storage = null): array
    {
        $storage ??= $this->storage;

        if (isset($_SESSION[self::$table][$storage])) {
            return (array) $_SESSION[self::$table][$storage];
        }

        return [];
    }
}