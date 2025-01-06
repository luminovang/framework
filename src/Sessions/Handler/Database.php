<?php 
/**
 * Luminova Framework.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Sessions\Handler;

use \Luminova\Base\BaseSessionHandler;
use \Luminova\Security\Crypter;
use \Luminova\Database\Builder;
use \Luminova\Functions\Ip;
use \Luminova\Time\Time;
use \ReturnTypeWillChange;
use \Throwable;

/**
 * Custom Database for session management with optional encryption support.
 */
class Database extends BaseSessionHandler
{
    /**
     * Constructor to initialize the session database handler.
     *
     * @param string $table The name of the database table for session storage.
     * @param array<string,mixed> $options Configuration options for session handling.
     * 
     * @throws RuntimeException if an error occurred.
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
     *    'onClose' => function (): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function close(): bool
    {
        return $this->options['onClose'] ? ($this->options['onClose'])() : true;
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
                ->exists();
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
        $deleted = $this->table()->where($this->prefixed('id'), '=', $id)->delete();

        if ($deleted) {
            $this->clearCache([$id, "exists_{$id}"]);
            return $this->close() ? $this->destroySessionCookie() : true;
        }

        return false;
    }

    /**
     * Performs garbage collection for expired sessions.
     *
     * @param int $max_lifetime The maximum session lifetime in seconds.
     * 
     * @return int|false Return the number of deleted sessions, or false on failure.
     */
    #[ReturnTypeWillChange]
    public function gc(int $max_lifetime): int|false
    {
        $expiration = Time::now()->getTimestamp() - $max_lifetime;

        if ($this->options['cacheable']) {
            $records = $this->table()
                ->where($this->prefixed('timestamp'), '<', $expiration)
                ->select([$this->prefixed('id')]);

            if (!$records) {
                return false;
            }

            $id = $this->prefixed('id');
            $ids = array_map(fn($record) => ['exists_' . $record->{$id}, $record->{$id}], $records);

            if ($ids !== []) {
                $this->clearCache(array_merge(...$ids));
            }
        }

        return $this->table()->where($this->prefixed('timestamp'), '<', $expiration)->delete();
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
        $data = $this->table($id)->where($this->prefixed('id'), '=', $id)->find([
            $this->prefixed('data'), 
            $this->prefixed('ip')
        ]);

        if (!$data) {
            $this->fileHash = md5('');
            return '';
        }

        $prefixed = $this->prefixed('data');
        $data = ($data->{$prefixed} && $this->options['encryption']) 
            ? Crypter::decrypt($data->{$prefixed}) 
            : $data->{$prefixed};
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
            $body[$this->prefixed('ip')] = Ip::toNumeric();
        }

        $existing = $this->table("exists_{$id}")->where($this->prefixed('id'), '=', $id)->exists();

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
        $builder = Builder::table($this->table);

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
}