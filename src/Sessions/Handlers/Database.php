<?php 
/**
 * Luminova Framework.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Sessions\Handlers;

use \Luminova\Base\BaseSessionHandler;
use \Luminova\Security\Crypter;
use \Luminova\Database\Builder;
use \Luminova\Functions\Ip;
use \Luminova\Logger\Logger;
use \Luminova\Time\Time;
use \ReturnTypeWillChange;
use \Throwable;

/**
 * Custom Database for session management with optional encryption support.
 */
class Database extends BaseSessionHandler
{
    /**
     * Client session ip address.
     * 
     * @var string|false $ipAddress
     */
    protected static string|bool $ipAddress = false;

    /**
     * Session database lock id.
     * 
     * @var string|null $lockId
     */
    private ?string $lockId = null;

    /**
     * Constructor to initialize the session database handler.
     *
     * @param string $table The name of the database table for session storage.
     * @param array<string,mixed> $options Configuration options for session handling.
     * 
     * @throws RuntimeException if an error occurred.
     * @see https://luminova.ng/docs/0.0.0/sessions/database-handler
     */
    public function __construct(private string $table, array $options = []) 
    {
        parent::__construct($options);
    }

    /**
     * Opens the session storage mechanism.
     *
     * @param string $path The save path for session files (unused in this implementation).
     * @param string $name The session name.
     * 
     * @return bool Return bool value from callback `onCreate`, otherwise always returns true for successful initialization.
     * 
     * @example Example usage of `onCreate` callback:
     * ```php
     * $handler = new Database('sessions', [
     *    'onCreate' => function (string $path, string $name): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function open(string $path, string $name): bool
    {
        return $this->options['onCreate'] ? ($this->options['onCreate'])($path, $name) : true;
    }

    /**
     * Closes the session storage mechanism.
     *
     * @return bool Return bool value from callback `onClose`, otherwise always returns true for successful cleanup.
     * 
     * @example Example usage of `onClose` callback:
     * ```php
     * $handler = new Database('sessions', [
     *    'onClose' => function (bool $status): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function close(): bool
    {
        $closed = $this->isLocked() ? $this->unlock() : true;
        return $this->options['onClose'] 
            ? ($this->options['onClose'])($closed) 
            : $closed;
    }

    /**
     * Validates a session ID.
     *
     * @param string $id The session ID to validate.
     * 
     * @return bool Return bool value from `onValidate` callback, otherwise returns true if id is valid and exists else false.
     * 
     * @example Example usage of `onValidate` callback:
     * ```php
     * $handler = new Database('sessions', [
     *    'onValidate' => function (string $id, bool $exists): bool {
     *          return $exists && doExtraCheck($id);
     *     }
     * ]);
     * ```
     */
    public function validate_sid(string $id): bool
    {
        $exists = preg_match('/^' . $this->pattern . '$/', $id) === 1;

        if ($this->table && $exists) {
            $exists = $this->table("exists_{$id}")
                ->where($this->prefixed('id'), '=', $id)
                ->has();
        }

        return $this->options['onValidate'] 
            ? ($this->options['onValidate'])($id, $exists) 
            : $exists;
    }

    /**
     * Deletes a session by ID.
     *
     * @param string $id The session ID.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function destroy(string $id): bool
    {
        if ($this->isLocked() && $this->table()->where($this->prefixed('id'), '=', $id)->delete() < 1) {
            return false;
        }

        $this->clearCache([$id, "exists_{$id}"]);
        return $this->close() 
            ? $this->destroySessionCookie() 
            : false;
    }

    /**
     * Performs garbage collection for expired sessions.
     *
     * @param int $maxLifetime The maximum session lifetime in seconds.
     * 
     * @return int|false Return the number of deleted sessions, or false on failure.
     */
    #[ReturnTypeWillChange]
    public function gc(int $maxLifetime): int|false
    {
        $expiration = Time::now()->getTimestamp() - $maxLifetime;

        if ($this->options['cacheable']) {
            $records = $this->table()
                ->where($this->prefixed('timestamp'), '<', $expiration)
                ->returns('array')
                ->select([$this->prefixed('id')]);
                
            if (!$records) {
                return false;
            }
           
            $id = $this->prefixed('id');
            $ids = array_map(fn($record) => ['exists_' . $record[$id], $record[$id]], $records);
        
            if ($ids !== []) {
                $this->clearCache(array_merge(...$ids));
            }
        }

        return $this->table()
            ->where($this->prefixed('timestamp'), '<', $expiration)
            ->delete();
    }

    /**
     * Reads session data by ID.
     *
     * @param string $id The session ID.
     * 
     * @return string Return the session data or an empty string if not found or invalid.
     */
    public function read(string $id): string
    {
        if ($this->lock($id) === false) {
            $this->fileHash = md5('');
            return '';
        }

        $data = $this->table($id)->where($this->prefixed('id'), '=', $id)->find([
            $this->prefixed('data'), 
            $this->prefixed('ip')
        ]);

        if (!$data) {
            $this->fileHash = md5('');
            return '';
        }
 
        $prefixed = $this->prefixed('data');
        $data = $data->{$prefixed} ?? '';
        $data = ($data && $this->options['encryption']) 
            ? Crypter::decrypt($data) 
            : $data;
        
        $this->fileHash = md5($data);

        return $data;
    }

    /**
     * Writes session data.
     *
     * @param string $id The session ID.
     * @param string $data The session data.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function write(string $id, string $data): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        if ($this->fileHash === md5($data)) {
            return $this->table()->where($this->prefixed('id'), '=', $id)->update([
                $this->prefixed('timestamp') => Time::now()->getTimestamp()
            ]) > 0;
        }

        $encrypted = ($data && $this->options['encryption']) ? Crypter::encrypt($data) : $data;

        if ($encrypted === false) {
            return false;
        }
        
        $body = [
            $this->prefixed('data')      => $encrypted,
            $this->prefixed('timestamp') => Time::now()->getTimestamp(),
            $this->prefixed('lifetime')  => (int) ini_get('session.gc_maxlifetime')
        ];

        if ($this->options['session_ip']) {
            $body[$this->prefixed('ip')] = $this->getIp();
        }

        $existing = $this->table("exists_{$id}")->where($this->prefixed('id'), '=', $id)->has();

        if ($existing) {
            $updated = $this->table()->where($this->prefixed('id'), '=', $id)->update($body);

            if ($updated > 0) {
                $this->fileHash = md5($data);
                $this->clearCache([$id]);
                return true;
            }

            return false;
        }

        $body[$this->prefixed('id')] = $id;
        if($this->table()->insert($body) > 0){
            $this->fileHash = md5($data);
            return true;
        }

        return false;
    }

    /**
     * Returns a Builder instance for the session table.
     *
     * @param string|null $key Optional cache key.
     * 
     * @return Builder Return the Builder instance.
     */
    private function table(?string $key = null): Builder
    {
        $builder = Builder::table($this->table)->returns('object');

        if ($key && $this->options['cacheable']) {
            $builder->cache($key);
        }

        return $builder;
    }

    /**
     * Prefix column name with the custom column prefix.
     * 
     * @param string $column The column name to prefix.
     * 
     * @return string Return the prefixed column name.
     */
    private function prefixed(string $column): string 
    {
        return ($this->options['columnPrefix']??'') . $column;
    }

    /**
     * Clears cache for the given keys.
     *
     * @param array $keys The keys to clear from cache.
     * @return void
     */
    private function clearCache(array $keys): void
    {
        if (!$this->options['cacheable']) {
            return;
        }

        $cache = Builder::table($this->table)->cache('none')->getCache();

        foreach ($keys as $key) {
            $cacheKey = md5($key);
            try{
                if ($cache->hasItem($cacheKey)) {
                    $cache->deleteItem($cacheKey);
                }
            }catch(Throwable){}
        }
    }

    /**
     * Check if database session is locked.
     * 
     * @return bool Return true if session is locked, false otherwise.
     */
    protected function isLocked(): bool
    {
        if (!$this->options['autoLockDatabase']) {
            return false;
        }

        return $this->lockId !== null;
    }

    /**
     * Lock database session.
     * 
     * @return bool Return true if successful, otherwise false.
     */
    protected function lock(string $id): bool
    {
        if (!$this->options['autoLockDatabase']) {
            return true;
        }

        $lockId = md5($id . $this->getIp());
        try{
            if(Builder::lock($lockId)){
                $this->lockId = $lockId;
                return true;
            }
        }catch(Throwable $e){
            Logger::dispatch(
                'critical',
                'Session Database Handler Error: Failed to lock database: '.$e->getMessage(),
                [
                    'session_id' => $id,
                    'session_client_ip' => $this->getIp()
                ]
            );
        }

        return false;
    }

    /**
     * Releases database session lock.
     * 
     * @return bool Return true if successful, otherwise false.
     */
    protected function unlock(): bool
    {
        if (!$this->lockId || !$this->options['autoLockDatabase']) {
            return true;
        }

        try{
            if (Builder::unlock($this->lockId)) {
                $this->lockId = null;
                return true;
            }
        }catch(Throwable $e){
            Logger::dispatch(
                'critical', 
                'Session Database Handler Error: Failed to unlock database: '.$e->getMessage(),
                [
                    'session_lock_id' => $this->lockId,
                    'session_client_ip' => $this->getIp()
                ]
            );
        }

        return false;
    }

    /**
     * Get client IP address.
     * 
     * @return string Return numeric IP address.
     */
    private function getIp(): string 
    {
        if (!$this->options['session_ip']) {
            return '';
        }
        
        return self::$ipAddress ??= Ip::toNumeric() ?: '';
    }
}