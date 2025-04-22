<?php 
/**
 * Luminova Framework.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Sessions\Handlers;

use \Luminova\Base\BaseSessionHandler;
use \Luminova\Security\Crypter;
use \Luminova\Exceptions\RuntimeException;
use \ReturnTypeWillChange;

/**
 * Custom Array Handler for session management with optional encryption support.
 */
class ArrayHandler extends BaseSessionHandler
{
    /**
     * Array storage. 
     *  
     * @var array $storage
     */
    private static array $storage = [];

    /**
     * Constructor to initialize the session array handler.
     * 
     * @param array<string,mixed> $options Configuration options for session handling.
     * 
     * @throws RuntimeException if an error occurred.
     */
    public function __construct(array $options = []) 
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
     * @example - Example usage of `onCreate` callback:
     * ```php
     * $handler = new ArrayHandler([
     *    'onCreate' => function (string $path, string $name, string $filename): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function open(string $path, string $name): bool
    {
        return $this->options['onCreate'] ? ($this->options['onCreate'])($path, $name, 'array') : true;
    }

    /**
     * Closes the session storage mechanism.
     *
     * @return bool Return bool value from callback `onClose`, otherwise always returns true for successful cleanup.
     * 
     * @example - Example usage of `onClose` callback:
     * ```php
     * $handler = new ArrayHandler([
     *    'onClose' => function (bool $status): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function close(): bool
    {
        return $this->options['onClose'] ? ($this->options['onClose'])(true) : true;
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
        $data = self::$storage[$id] ?? '';

        if (!$data) {
            $this->fileHash = md5('');
            return '';
        }

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
        if ($this->fileHash === md5($data)) {
            return true;
        }

        $encrypted = ($data && $this->options['encryption']) 
            ? Crypter::encrypt($data) 
            : $data;

        if ($encrypted === false) {
            return false;
        }

        self::$storage[$id] = $encrypted;
        $this->fileHash = md5($data);
        $encrypted = null;
        return true;
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
        unset(self::$storage[$id]);
        return true;
    }

    /**
     * Validates a session ID.
     *
     * @param string $id The session ID to validate.
     * 
     * @return bool Return bool value from `onValidate` callback, otherwise returns true if id is valid and exists else false.
     * 
     * @example - Example usage of `onValidate` callback:
     * ```php
     * $handler = new ArrayHandler([
     *    'onValidate' => function (string $id, bool $exists): bool {
     *          return $exists && doExtraCheck($id);
     *     }
     * ]);
     * ```
     */
    public function validate_sid(string $id): bool
    {
        $exists = isset(self::$storage[$id]) && preg_match('/^' . $this->pattern . '$/', $id) === 1;

        return $this->options['onValidate'] 
            ? ($this->options['onValidate'])($id, $exists) 
            : $exists;
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
        return 0;
    }
}