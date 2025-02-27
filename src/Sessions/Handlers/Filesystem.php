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
use \Luminova\Functions\Ip;
use \Luminova\Time\Time;
use \Luminova\Exceptions\RuntimeException;
use \ReturnTypeWillChange;

/**
 * Custom File Handler for session management with optional encryption support.
 */
class Filesystem extends BaseSessionHandler
{
    /**
     * The directory where session files will be stored.
     * 
     * @var string $filePath
     */
    private string $filePath;

    /**
     * The directory where session files will be stored.
     * 
     * @var string $fileName
     */
    private string $fileName;

    /**
     * The file handle.
     *
     * @var resource|null $fileHandle
     */
    private mixed $fileHandle = null;

    /**
     * Whether this is a session file.
     *
     * @var bool $isNewSession
     */
    private bool $isNewSession = false;

    /**
     * Constructor to initialize the session file handler.
     *
     * @param string $filePath The directory for session files (e.g, `/writeable/session/`).
     * @param array<string,mixed> $options Configuration options for session handling.
     * 
     * @throws RuntimeException if failed to create session save path, path is not writable or an error occurred.
     * @see https://luminova.ng/docs/0.0.0/sessions/filesystem-handler
     */
    public function __construct(?string $filePath = null, array $options = [])
    {
        parent::__construct($options);
        
        if ($filePath) {
            $this->filePath = rtrim($filePath, TRIM_DS);
            ini_set('session.save_path', $this->filePath);
            return;
        }

        $filePath = rtrim(ini_get('session.save_path'), TRIM_DS);

        if (!$filePath) {
            $filePath = rtrim(root('/writeable/session/'), TRIM_DS);
            ini_set('session.save_path', $filePath);
        }

        $this->filePath = $filePath;
    }

    /**
     * Opens the session storage mechanism.
     *
     * @param string $path The save path for session files (unused in this implementation).
     * @param string $name The session name.
     * 
     * @return bool Return bool value from callback `onCreate`, otherwise always returns true for successful initialization.
     * @throws RuntimeException if failed to create session save path or path is not writable.
     * 
     * @example Example usage of `onCreate` callback:
     * ```php
     * $handler = new Filesystem('path/to/sessions', [
     *    'onCreate' => function (string $path, string $name, string $filename): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function open(string $path, string $name): bool
    {
        $path = rtrim($path, TRIM_DS) . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($path) && !mkdir($path, $this->options['dir_permission'], true)) {
            throw new RuntimeException(sprintf('Failed to create session save path: "%s".', $this->filePath));
        }

        if (!is_writable($path)) {
            throw new RuntimeException(sprintf('Session save path: "%s" is not writable.', $this->filePath));
        }

        $this->filePath = rtrim($path, TRIM_DS);
        $this->fileName = ($this->options['session_ip'] ? md5(Ip::get()) . '_': '');

        return $this->options['onCreate'] ? ($this->options['onCreate'])($path, $name, $this->fileName) : true;
    }

    /**
     * Closes the session storage mechanism.
     *
     * @return bool Return bool value from callback `onClose`, otherwise always returns true for successful cleanup.
     * 
     * @example Example usage of `onClose` callback:
     * ```php
     * $handler = new Filesystem('path/to/sessions', [
     *    'onClose' => function (bool $status): bool {
     *          return true; // Your logic here...
     *     }
     * ]);
     * ```
     */
    public function close(): bool
    {
        if (is_resource($this->fileHandle)) {
            flock($this->fileHandle, LOCK_UN);
            fclose($this->fileHandle);

            $this->fileHandle = null;
            $this->isNewSession = false;
        }

        return $this->options['onClose'] ? ($this->options['onClose'])(true) : true;
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
     * $handler = new Filesystem('sessions', [
     *    'onValidate' => function (string $id, bool $exists): bool {
     *          return $exists && doExtraCheck($id);
     *     }
     * ]);
     * ```
     */
    public function validate_sid(string $id): bool
    {
        $exists = (
            preg_match('/^' . $this->pattern . '$/', $id) === 1 && 
            file_exists($this->getFile($id))
        );

        return $this->options['onValidate'] 
            ? ($this->options['onValidate'])($id, $exists) 
            : $exists;
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
        $file = $this->getFile($id);
        $continue = $this->createReadHandler($file);

        if (!$continue) {
            return $continue;
        }

        $data = '';
        clearstatcache();

        if (is_readable($file) && ($length = filesize($file)) > 0) {
            while (($buffer = fread($this->fileHandle, $length - strlen($data))) !== false) {
                $data .= $buffer;
                if (strlen($data) >= $length) {
                    break;
                }
            }
        }

        $data = ($data && $this->options['encryption']) ? Crypter::decrypt($data) : $data;
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
        if (!is_resource($this->fileHandle)) {
            return false;
        }

        // Skip writing if data hasn't changed
        if ($this->fileHash === md5($data)) {
            return $this->isNewSession || touch($this->getFile($id));
        }

        // Truncate and rewind only for existing sessions
        if (!$this->isNewSession) {
            ftruncate($this->fileHandle, 0);
            rewind($this->fileHandle);
        }

        $encrypted = ($data && $this->options['encryption']) ? Crypter::encrypt($data) : $data;
        if ($encrypted === false) {
            return false;
        }

        $length = strlen($encrypted);
        $written = 0;

        while ($written < $length) {
            $result = fwrite($this->fileHandle, substr($encrypted, $written));

            if ($result === false) {
                $this->log('error', 'Session: Unable to write data.');
                return false;
            }

            $written += $result;
        }

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
        $file = $this->getFile($id);

        if (file_exists($file)) {
            clearstatcache();
            return unlink($file) && $this->destroySessionCookie();
        }

        return false;
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
        if (!is_dir($this->filePath) || ($directory = opendir($this->filePath)) === false) {
            $this->log('debug', "Session: Garbage collector couldn't list files in directory: '{$this->filePath}'.");
            return false;
        }

        $expiration = Time::now()->getTimestamp() - $maxLifetime;
        $basePath = rtrim($this->filePath, TRIM_DS) . DIRECTORY_SEPARATOR;
        $deleted = 0;

        while (($file = readdir($directory)) !== false) {
            $path = $basePath . $file;

            if (!str_starts_with($file, 'sess_') || !is_file($path)) {
                continue;
            }

            if (($filemtime = filemtime($path)) !== false && $filemtime < $expiration && unlink($path)) {
                $deleted++;
            }
        }

        closedir($directory);
        return $deleted > 0 ? $deleted : false;
    }

    /**
     * Generates the full file path for a session file based on the session ID.
     *
     * @param string $id The session ID used to generate the file name.
     *
     * @return string Return the complete file path for the session file.
     */
    private function getFile(string $id): string
    {
        return "{$this->filePath}/sess_{$this->fileName}{$id}";
    }

    /**
     * Creates a file handler for reading session data.
     *
     * @param string $file The file path.
     * 
     * @return string|bool Returns true if the handler is successfully created, an empty string for a new session, or false on failure.
     */
    private function createReadHandler(string $file): string|bool
    {
        if ($this->fileHandle !== null) {
            rewind($this->fileHandle);
            return true;
        }

        $this->isNewSession = !file_exists($file);
        $this->fileHandle = fopen($file, 'c+b');
        
        if ($this->fileHandle === false) {
            $this->log('error', "Session: Unable to open file: '{$file}'.");
            return false;
        }

        if (!flock($this->fileHandle, LOCK_EX)) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
            $this->log('error', "Session: Unable to obtain lock for file: '{$file}'.");
            return false;
        }

        if ($this->isNewSession) {
            chmod($file, 0600);
            $this->fileHash = md5('');
            return '';
        }

        return true;
    }
}