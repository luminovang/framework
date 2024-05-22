<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Sessions;

use \Luminova\Interface\SessionManagerInterface;
use \App\Controllers\Config\Session as SessionConfig;
use \Luminova\Sessions\SessionManager;

class Session 
{
    /**
     * session interface
     * 
     * @var SessionManagerInterface $manager
    */
    private ?SessionManagerInterface $manager = null;

    /**
     * static class instance
     * 
     * @var Session $instance 
    */
    private static ?Session $instance = null;

    /**
     * Initializes session constructor
     *
     * @param SessionManagerInterface|null $manager The session manager.
    */
    public function __construct(?SessionManagerInterface $manager = null)
    {
        $this->manager = $manager ?? new SessionManager();
    } 

    /**
     * Get an instance of the Session class.
     *
     * @param SessionManagerInterface|null $manager The session manager.
     * 
     * @return static self instance
    */
    public static function getInstance(?SessionManagerInterface $manager = null): static
    {
        if (self::$instance === null) {
            self::$instance = new static($manager);
        }

        return self::$instance;
    }

    /** 
     * Retrieve data as an array from the current session storage.
     * 
     * @param string $index Optional key to retrieve.
     * 
     * @return array The retrieved data.
     */
    public function toArray(string $index = ''): array
    {
        return $this->manager->toAs('array', $index);
    }

    /** 
     * Retrieves data as an object from the current session storage.
     * 
     * @param string $index Optional key to retrieve.
     * 
     * @return object The retrieved data.
     */
    public function toObject(string $index = ''): object
    {
        return $this->manager->toAs('object', $index);
    }

    /**
     * Retrieves all stored session data as an array or object.
     * 
     * @param string $type Return type of object or array (default is 'array').
     * 
     * @return array|object All stored session data.
    */
    public function toExport(string $type = 'array'): array|object
    {
        return $this->manager->getResult($type);
    }

    /**
     * Sets the session manager.
     *
     * @param SessionManagerInterface $manager The session manager to set.
     * 
     * @return void
    */
    public function setManager(SessionManagerInterface $manager): void
    {
        $this->manager = $manager;
    }

    /**
     * Retrieves the session storage manager instance (`CookieStorage` or `SessionManager`).
     *
     * @return SessionManagerInterface|null The storage manager instance.
    */
    public function getManager(): ?SessionManagerInterface
    {
        return $this->manager;
    }

    /**
     * Sets the storage name to store and retrieve items from.
     *
     * @param string $storage The storage key to set.
     * 
     * @return self The Session class instance.
    */
    public function setStorage(string $storage): self
    {
        $this->manager->setStorage($storage);

        return $this;
    }

    /**
     * Retrieves the current session storage name.
     * 
     * @return string The current storage name.
    */
    public function getStorage(): string 
    {
        return $this->manager->getStorage();
    }

    /**
     * Retrieves a value from the session storage.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default Default value if the key is not found.
     * 
     * @return mixed The retrieved data.
    */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->getItem($key, $default);
    }

    /** 
     * Retrieves an item from a specified session storage instance.
     * 
     * @param string $index The key to retrieve.
     * @param string $storage The storage key name.
     * 
     * @return mixed The retrieved data.
     */
    public function getFrom(string $index, string $storage): mixed
    {
        $result = $this->manager->getItems($storage);

        if ($result === []) {
            return null;
        }

        return $result[$index] ?? null;
    }

    /** 
     * Sets an item to a specified session storage instance.
     * 
     * @param string $index The key to set.
     * @param mixed $data The data to set.
     * @param string $storage The storage key name.
     * 
     * @return self
     */
    public function setTo(string $index, mixed $data, string $storage): self
    {
        $this->manager->setItem($index, $data, $storage);

        return $this;
    }

    /** 
     * Checks if the session user has successfully logged in online.
     * Optionally, specify a storage name to check; otherwise, it checks the current storage.
     * 
     * @param string $storage optional storage instance key
     * 
     * @return bool Returns true if the session user is online, false otherwise.
    */
    public function online(string $storage = ''): bool
    {
        $data = $this->manager->getItems($storage);
        return isset($data['_session_online']) && $data['_session_online'] === 'on';
    }

    /** 
     * Retrieves the user's login session ID.
     *  A unique session ID is automatically generated once synchronize() is called.
     * 
     * @return string|null Returns the session ID or null if not logged in.
    */
    public function ssid(): string|null
    {
        return $this->manager->getItem('_session_online_id', null);
    }

    /** 
     * Retrieves the user's login session datetime in ISO 8601 format.
     * The session datetime is automatically generated once synchronize() is called.
     * 
     * @return string|null Returns the session login datetime or null if not logged in.
    */
    public function ssdate(): string|null
    {
        return $this->manager->getItem('_session_online_datetime', null);
    }

    /**
     * Set the value in the session by key.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     * 
     * @return self The Session class instance.
    */
    public function set(string $key, mixed $value): self
    {
        $this->manager->setItem($key, $value);

        return $this;
    }

    /**
     * Adds an item to the session storage without overwriting existing values.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     * 
     * @return bool True if item was added else false.
    */
    public function add(string $key, mixed $value): bool
    {
        if($this->has($key)){
            return false;
        }

        $this->manager->setItem($key, $value);

        return true;
    }

   /** 
     * Remove a key from the session storage by passing the key.
     * 
     * @param string $index The key to remove.
     * 
     * @return self The Session class instance.
     */
    public function remove(string $key): self
    {
        $this->manager->deleteItem($key);

        return $this;
    }

    /** 
     * Clear all data from session storage by passing the storage key.
     * 
     * @param string $storage Optionally pass storage name to clear.
     * 
     * @return self The Session class instance.
     */
    public function clear(string $storage = ''): self
    {
        $this->manager->deleteItem(null, $storage);
        
        return $this;
    }

    /** 
     * Check if item key exists in session storage.
     * 
     * @param string $key Key to check
     * 
     * @return bool Return true if key exists in session storage else false.
    */
    public function has(string $key): bool
    {
        return $this->manager->hasItem($key);
    }

    /**
     * Initializes session data and starts the session if it isn't already started.
     * This method replaces the default PHP session_start(), but with additional configuration.
     *
     * @return void
    */
    public function start(): void
    {
        if ($this->manager instanceof SessionManager) {
            if ((bool) ini_get('session.auto_start')) {
                logger('error', 'Session: session.auto_start is enabled in php.ini. Aborting.');
                return;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->ipListener();
                logger('warning', 'Session: Sessions is enabled, and one exists. don\'t $session->start() again.');
                return;
            }

            if (session_status() === PHP_SESSION_NONE) {
                $this->sessionConfigure();
                session_start();
                $this->ipListener();
            }
            return;
        }
        
        $this->sessionConfigure();
        $this->ipListener();
    }

    /**
     * Starts a user's online login session, optionally specifying an IP address.
     * This method should be called to indicate that the user has successfully logged in.
     *
     * @param string $ip The IP address.
     * 
     * @return self The Session class instance.
    */
    public function synchronize(string $ip = ''): self
    {
        $this->set('_session_online', 'on');
        $this->set('_session_online_id', uniqid('ssid'));
        $this->set('_session_online_datetime', date('c'));

        if(SessionConfig::$strictSessionIp && $ip = ip_address()){
            $this->set('_session_online_ip', $ip);
        }
 
        return $this;
    }

    /**
     * Listens for changes in the user's IP address to detect if it has changed since the last login.
     * If the new IP address does not match the previous login IP address, it logs out the user and clears the session.
     *
     * @param string $storage Optional storage location.
     * 
     * @return void
    */
    private function ipListener(string $storage = ''): void
    {
        $default = $this->getStorage();

        if(SessionConfig::$strictSessionIp && $this->ipChanged($storage)){
            
            if($storage !== '' && $storage !== $default){
                $this->setStorage($storage);
            }

            $this->clear();

            if($storage !== '' && $storage !== $default){
                $this->setStorage($default);
            }
        }
    }

    /**
     * Checks if the user's IP address has changed since the last login session.
     *
     * @param string $storage Optional storage location
     * 
     * @return bool Returns false if the user's IP address matches the session login IP, otherwise returns true.
    */
    public function ipChanged(string $storage = ''): bool
    {
        $default = $this->getStorage();

        if($storage !== '' && $storage !== $default){
            $this->setStorage($storage);
        }

        if($this->online()){
            $last = $this->get("_online_session_id", '');

            if(!empty($last) && $last != ip_address()){
                return true;
            }
        }

        if($storage !== '' && $storage !== $default){
            $this->setStorage($default);
        }
        
        return false;
    }

    /**
     * Configure session settings.
     *
     * @return void
    */
    private function sessionConfigure(): void
    {
        $cookieParams = [
            'lifetime' => time() + SessionConfig::$expiration,
            'path'     => SessionConfig::$sessionPath,
            'domain'   => SessionConfig::$sessionDomain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => SessionConfig::$sameSite,
        ];
        ini_set('session.name', SessionConfig::$cookieName);
        ini_set('session.cookie_samesite', SessionConfig::$sameSite);
        session_set_cookie_params($cookieParams);

        if (SessionConfig::$expiration > 0) {
            ini_set('session.gc_maxlifetime', (string) $cookieParams['lifetime']);
        }

        if (SessionConfig::$savePath !== '') {
            ini_set('session.save_path', SessionConfig::$savePath);
        }

        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
    }
}