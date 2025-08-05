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

use \Throwable;
use \Luminova\Logger\Logger;
use \Luminova\Sessions\Session;
use \Luminova\Base\Configuration;
use \Luminova\Exceptions\JsonException;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Security\Encryption\Crypter;
use \Luminova\Interface\SessionManagerInterface;

final class Cookie implements SessionManagerInterface 
{
    /**
     * Cookie config. 
     * 
     * @var Configuration $config
     */
    private ?Configuration $config = null;

    /**
     * The session storage index name.
     * 
     * @var string $table
     */
    private static string $table = 'default';

    /**
     * The session IS index name.
     * 
     * @var string $secureTable
     */
    private static string $secureTable = '__session_cookie_id';

    /**
     * Cookie write close.
     * 
     * @var bool $writeClose
     */
    private static bool $writeClose = false;

    /**
     * The session id
     * 
     * @var string|null $sid
     */
    private static ?string $sid = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(private string $storage = 'global') {}

    /**
     * {@inheritdoc}
     */
    public function setConfig(Configuration $config): void
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
    public function setItem(string $index, mixed $value, ?string $storage = null): self
    {
        return $this->setItems([$index => $value], $storage);
    }

    /** 
     * {@inheritdoc}
     */
    public function setItems(array $data, ?string $storage = null): self
    {
        $storage = $this->getKey($storage);
        $this->write(array_merge(
            $this->getItems($storage),
            $data
        ), $storage);

        return $this;
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
    public function deleteItem(?string $index = null, ?string $storage = null): self
    {
        $storage = $this->getKey();
    
        if(!self::$writeClose && isset($_COOKIE[self::$table][$storage])) {
            if($index){
                $data = $this->getItems($storage);

                if (isset($data[$index])) {
                    $data[$index] = null;
                    unset($data[$index]);
                }

                $this->write($data, $storage);
                return $this;
            }

            $this->write([], $storage);
        }

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function destroy(bool $allData = false): bool
    {
        if(self::$writeClose){
            return false;
        }

        $expire = time() - $this->config->expiration;

        if($allData){
            foreach ($_COOKIE as $name => $value) {
                if($this->store(
                    $name, 
                    '', 
                    $expire, 
                    str_ends_with($name, self::$secureTable) ? 'Strict' : $this->config->sameSite 
                )){
                    $_COOKIE[$name] = null;
                    unset($_COOKIE[$name]);
                }
            }

            return true;
        }

        if(isset($_COOKIE[self::$table]) || isset($_COOKIE[self::$secureTable])) {
            if(
                $this->store(self::$table, '', $expire) || 
                $this->store(self::$secureTable, '', $expire)
            ){
                $_COOKIE[self::$table] = [];
                $_COOKIE[self::$secureTable] = null;

                unset($_COOKIE[self::$table], $_COOKIE[self::$secureTable]);
                return true;
            }
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function commit(): self 
    {
        self::$writeClose = true;
        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function status():int
    {
        $id = $_COOKIE[self::$secureTable] ?? null;
        return ($id && self::isValidId($id))
            ? Session::ACTIVE 
            : Session::NONE;
    }

    /** 
     * {@inheritdoc}
     */
    public function start(?string $sessionId = null): bool
    {
        if(!$sessionId){
            return $this->create();
        }

        if(self::isValidId($sessionId)){
            if($this->create($sessionId)){
                self::$sid = $sessionId;
                return true;
            }
            
            return false;
        }

        $error = "Session Cookie Error: The provided session cookie ID '{$sessionId}' is invalid.";

        if(PRODUCTION){
            Logger::error("{$error} A new session cookie ID will be generated.");
            return $this->create();
        }

        throw new RuntimeException($error);
    }

    /** 
     * {@inheritdoc}
     */
    public function regenerateId(bool $clearData = true): string|bool
    {
        return $this->create(regenerate: true, clearData: $clearData) 
            ? ($_COOKIE[self::$secureTable] ?? false) 
            : false;
    }

    /** 
     * {@inheritdoc}
     */
    public function getId(): ?string
    {
        return $_COOKIE[self::$secureTable] ?? self::$sid;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        return isset($this->getItems()[$key]);
    }

    /** 
     * {@inheritdoc}
     */
    public function hasStorage(string $storage): bool
    {
        return isset(self::open()[$storage]['__data']);
    }

    /** 
     * {@inheritdoc}
     */
    public static function isValidId(string $sessionId): bool
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $sessionId);
    }

    /** 
     * {@inheritdoc}
     */
    public function getResult(string $type = 'array'): array|object
    {
        $_COOKIE[self::$table] = self::open();

        if($type === 'array'){
            return (array) $_COOKIE[self::$table];
        }

        if($_COOKIE[self::$table] === []){
            return (object) $_COOKIE[self::$table];
        }

        try {
            return (object) json_decode(
                json_encode($_COOKIE[self::$table], JSON_THROW_ON_ERROR),
                null,
                512,
                JSON_THROW_ON_ERROR
            );
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
        $result = $index ? ($result[$index]??null) : $result;
        $isArray = ($type === 'array');

        if($result === null){
            return null;
        }
    
        if($isArray && is_array($result)){
            return $result;
        }

        if(!$isArray && is_object($result)){
            return $result;
        }

        try {
            $data = json_decode(
                json_encode($result, JSON_THROW_ON_ERROR),
                $isArray ? true : null,
                512,
                JSON_THROW_ON_ERROR
            );

            return $isArray ? (array) $data : (object) $data;
        }catch(Throwable $e){
            if(is_scalar($result)){
                return $isArray ? [$result] : (object)[$result];
            }
            
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function getItems(?string $storage = null): array
    {
        $contents = [];
        $storage = $this->getKey($storage);
        $_COOKIE[self::$table] = self::open();

        if(isset($_COOKIE[self::$table][$storage])) {
            $contents = $this->isEncrypted($storage) 
                ?  Crypter::decrypt($_COOKIE[self::$table][$storage]['__data'])
                : $_COOKIE[self::$table][$storage]['__data'];
        }

        if(!$contents){
            return [];
        }
            
        if(json_validate($contents)){
            try {
                return $_COOKIE[self::$table][$storage]['__data'] = (array) json_decode(
                    $contents, true, 512, JSON_THROW_ON_ERROR
                );
            }catch(Throwable $e){
                Logger::error('Session Cookie Error: failed to read cookie data' . $e->getMessage());
                return [];
            }
        }

        return (array) $contents;
    }

    /**
     * Get storage name.
     * 
     * @param string $storage Optional storage name.
     * 
     * @return string Storage name.
     */
    private function getKey(?string $storage = null): string 
    {
        return $storage ?? $this->storage;
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

        $storage = $this->getKey($storage);
        if(!$storage){
            return;
        }

        $_COOKIE[self::$table] = self::open();
        $_COOKIE[self::$table][$storage]['__data'] = $data;
        $_COOKIE[self::$table][$storage]['__secure'] = 'off';
        $items = $_COOKIE[self::$table];

        if($this->config->encryptCookieData){
            $encrypted = Crypter::encrypt(json_encode($data));
            if($encrypted){
                $_COOKIE[self::$table][$storage]['__secure'] = 'on';
                $items[$storage]['__secure'] = 'on';
                $items[$storage]['__data'] = $encrypted;
            }
        }

        $this->store(
            self::$table, 
            json_encode($items), 
            time() + $this->config->expiration
        );
        $items = null;
    }

    /**
     * Opens and decodes the cookie data from the session table.
     *
     * This method attempts to retrieve and decode the cookie data stored in the session table.
     * If the data is a JSON string, it decodes it into an array. If it's already an array,
     * it returns it as is. In case of any errors during decoding, it logs the error and
     * returns an empty array.
     *
     * @return array The decoded cookie data as an array. Returns an empty array if
     *               the data couldn't be retrieved or decoded.
     */
    private static function open(): array 
    {
        try {
            return is_string($_COOKIE[self::$table] ?? []) 
                ? (array) json_decode($_COOKIE[self::$table], true, 512, JSON_THROW_ON_ERROR) 
                : (array) ($_COOKIE[self::$table] ?? []);
        }catch(Throwable $e){
            Logger::error('Session Cookie Error: failed to decode cookie data' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if the cookie data for a specific storage is encrypted.
     *
     * This method determines whether the cookie data for a given storage
     * is encrypted based on the configuration and stored cookie information.
     *
     * @param string|null $storage The storage name to check. If null, the default storage will be used.
     *
     * @return bool Returns true if the cookie data is encrypted, false otherwise.
     */
    private function isEncrypted(?string $storage = null): bool 
    {
        $storage = $this->getKey($storage);
        return (
            $this->config->encryptCookieData && 
            ($_COOKIE[self::$table][$storage]['__secure'] ?? 'off') === 'on' &&
            is_string($_COOKIE[self::$table][$storage]['__data'] ?? [])
        );
    }

    /**
     * Save cookie data.
     *
     * @param string $name Cookie name.
     * @param string $value cookie contents.
     * @param int $expiry cookie expiration time.
     * @param ?string $samesite cookie samesite attribute.
     * 
     * @return bool Return true if successful, otherwise false.
     */
    private function store(
        string $name, 
        string $value, 
        int $expiry, 
        ?string $samesite = null
    ): bool
    {
        return setcookie($name, $value, [
            'expires' => $expiry,
            'path' => $this->config->sessionPath,
            'domain' => $this->config->sessionDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => $samesite ?? $this->config->sameSite 
        ]);
    }

    /**
     * Generate cookie session id.
     *
     * @param string|null $sessionId Optional cookie session id.
     * 
     * @return bool Return true if successful, otherwise false.
     */
    private function create(
        ?string $sessionId = null, 
        bool $regenerate = false,
        bool $clearData = false
    ): bool
    {
        if(!$regenerate && isset($_COOKIE[self::$secureTable])){
            return true;
        }

        if($this->store(
            self::$secureTable, 
            $sessionId ?? bin2hex(random_bytes(16)), 
            time() + $this->config->expiration, 
            'Strict'
        )){
            $_COOKIE[self::$secureTable] = $sessionId;

            if(
                $clearData && 
                isset($_COOKIE[self::$table]) && 
                $this->store(self::$table, '', time() - $this->config->expiration)
            ) {
                $_COOKIE[self::$table] = [];
                unset($_COOKIE[self::$table]);
            }

            return true;
        }

        return false;
    }
}