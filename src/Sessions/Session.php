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
use \Luminova\Interface\LazyInterface;
use \App\Config\Session as SessionConfig;
use \Luminova\Sessions\Manager\Session as SessionManager;
use \Luminova\Base\BaseSessionHandler;
use \Luminova\Functions\IP;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\LogicException;

class Session implements LazyInterface
{
    /**
     * Session manager interface
     * 
     * @var SessionManagerInterface $manager
     */
    private ?SessionManagerInterface $manager = null;

    /**
     * static class instance
     * 
     * @var self $instance 
     */
    private static ?self $instance = null;

    /**
     * Session configuration.
     * 
     * @var SessionConfig $config 
     */
    private static ?SessionConfig $config = null;

    /**
     * Session handler.
     * 
     * @var BaseSessionHandler $handler 
     */
    private ?BaseSessionHandler $handler = null;

    /**
     * Callback handler for ip change.
     * 
     * @var callable|null $onIpChange 
     */
    private mixed $onIpChange = null;

    /**
     * Initializes the backend session handler class.
     *
     * This constructor sets up the session manager to handle user login and backend session management. 
     * It allows for an optional custom session manager and session handler to be provided or defaults to the standard manager.
     *
     * @param SessionManagerInterface<\T>|null $manager Optional. A custom session manager instance.
     *                                                  If not provided, the default `\Luminova\Sessions\Manager\Session` will be used.
     *
     * > **Note:** When no custom manager is provided, the default session manager is automatically 
     * > initialized and configured using the session configuration settings.
     */
    public function __construct(?SessionManagerInterface $manager = null)
    {
        self::$config ??= new SessionConfig();
        $this->manager = $manager ?? new SessionManager();
        $this->manager->setTable(self::$config->tableIndex);
        $this->manager->setConfig(self::$config);
        $manager = null;
    } 

    /**
     * Singleton method to return an instance of the Session class.
     *
     * @param SessionManagerInterface<\T>|null $manager The session manager.
     * 
     * @return static Return static Session class instance.
     */
    public static function getInstance(?SessionManagerInterface $manager = null): static
    {
        if (self::$instance === null) {
            self::$instance = new static($manager);
        }

        return self::$instance;
    }

    /**
     * Sets the session save handler.
     *
     * @param BaseSessionHandler $handler The custom save handler instance.
     *
     * @return self Return the Session class instance.
     * @see https://luminova.ng/docs/edit/0.0.0/sessions/database-handler
     * @see https://luminova.ng/docs/edit/0.0.0/sessions/filesystem-handler
     * @see https://luminova.ng/docs/edit/0.0.0/base/session-handler
     */
    public function setHandler(BaseSessionHandler $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /** 
     * Retrieve data as an array from the current session storage.
     * 
     * @param string $index Optional key to retrieve.
     * 
     * @return array Return the retrieved data.
     */
    public function toArray(?string $index = null): array
    {
        return $this->manager->toAs('array', $index);
    }

    /** 
     * Retrieves data as an object from the current session storage.
     * 
     * @param string|null $index Optional key to retrieve.
     * 
     * @return object Return the retrieved data.
     */
    public function toObject(?string $index = null): object
    {
        return $this->manager->toAs('object', $index);
    }

    /**
     * Retrieves all stored session data as an array or object.
     * 
     * @param string $type The return session data type, it can be either `object` or `array` (default is 'array').
     * 
     * @return array|object Return all the stored session data as either an array or object.
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
     * @return SessionManagerInterface|null Return the storage manager instance.
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
     * @return self Return the Session class instance.
     */
    public function setStorage(string $storage): self
    {
        $this->manager->setStorage($storage);
        return $this;
    }

    /**
     * Retrieves the current session storage name.
     * 
     * @return string Return the current storage name.
     */
    public function getStorage(): string 
    {
        return $this->manager->getStorage();
    }

    /**
     * Retrieves a value from the session storage.
     *
     * @param string $key The key to identify the session data.
     * @param mixed $default Default value if the key is not found.
     * 
     * @return mixed Return retrieved data from session storage.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->getItem($key, $default);
    }

    /** 
     * Retrieves an item from a specified session storage instance.
     * 
     * @param string $index The key to identify the session data.
     * @param string $storage The storage key name.
     * 
     * @return mixed Return the retrieved data from session storage or null.
     */
    public function getFrom(string $storage, string $index): mixed
    {
        $result = $this->manager->getItems($storage);
        return ($result === []) ? null : ($result[$index] ?? null);
    }

    /** 
     * Sets an item to a specified session storage instance.
     * 
     * @param string $key The key to identify the session data.
     * @param mixed $value The value to associate with the specified key.
     * @param string $storage The storage key name.
     * 
     * @return self Return the Session class instance.
     */
    public function setTo(string $index, mixed $value, string $storage): self
    {
        $this->manager->setItem($index, $value, $storage);
        return $this;
    }

    /** 
     * Checks if the session user has successfully logged in online.
     * Optionally, specify a storage name to check; otherwise, it checks the current storage.
     * 
     * @param string|null $storage optional storage instance key.
     * 
     * @return bool Returns true if the session user is online, false otherwise.
     */
    public function online(?string $storage = null): bool
    {
        $data = $this->manager->getItems($storage ?? '');
        return isset($data['_session_online']) && $data['_session_online'] === 'on';
    }

    /** 
     * Retrieves the user's login session ID.
     *  A unique session ID is automatically generated once synchronize() is called.
     * 
     * @return string|null Returns the session ID or null if not logged in.
     * > **Note**
     * > The session ID returned from this method is not same as PHP `session_id`.
     */
    public function ssid(): ?string
    {
        return $this->manager->getItem('_session_online_id', null);
    }

    /** 
     * Retrieves the user's login session datetime in ISO 8601 format.
     * The session datetime is automatically generated once synchronize() is called.
     * 
     * @return string|null Returns the session login datetime or null if not logged in.
     */
    public function ssDate(): ?string
    {
        return $this->manager->getItem('_session_online_datetime', null);
    }

    /**
     * Sets a value in the session storage by key.
     *
     * This method saves or updates a value in the session using the specified key. 
     * If the key already exists, its value will be overwritten with the new value.
     *
     * @param string $key The key to identify the session data.
     * @param mixed $value The value to associate with the specified key.
     * 
     * @return self Returns the current Session instance.
     */
    public function set(string $key, mixed $value): self
    {
        $this->manager->setItem($key, $value);

        return $this;
    }

    /**
     * Adds a value to the session storage without overwriting existing keys.
     *
     * This method attempts to add a new key-value pair to the session. 
     * If the specified key already exists in the session storage, the method does not modify the value and sets the status to `false`. 
     * Otherwise, it adds the new key-value pair and sets the status to `true`.
     *
     * @param string $key The key to identify the session data.
     * @param mixed $value The value to associate with the specified key.
     * @param bool $status A reference variable to indicate whether the operation succeeded (`true`) or failed (`false`).
     * 
     * @return self Returns the current Session instance.
     */
    public function add(string $key, mixed $value, bool &$status = false): self
    {
        if($this->has($key)){
            $status = false;
            return $this;
        }

        $this->manager->setItem($key, $value);
        $status = true;
        return $this;
    }

    /** 
     * Remove a key from the session storage by passing the key.
     * 
     * @param string $key  The session data key to remove.
     * 
     * @return self Return the Session class instance.
     */
    public function remove(string $key): self
    {
        $this->manager->deleteItem($key);
        return $this;
    }

    /** 
     * Clear all data from session storage by passing the storage key.
     * 
     * @param string|null $storage Optionally session storage name to clear.
     * 
     * @return self Return the Session class instance.
     */
    public function clear(?string $storage = null): self
    {
        $this->manager->deleteItem(null, $storage ?? '');
        return $this;
    }

    /** 
     * Check if item key exists in session storage.
     * 
     * @param string $key The session data key to check.
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
     * @param string|null $ssid Optional specify session identifier from PHP `session_id`.
     *
     * @return void 
     * @throws RuntimeException If an invalid session ID is provided.
     * 
     * @example Starting a session with a specified session ID:
     * 
     * ```php
     * namespace App;
     * use Luminova\Core\CoreApplication;
     * use Luminova\Sessions\Session;
     * class Application extends CoreApplication
     * {
     *      protected ?Session $session = null;
     *      protected function onCreate(): void 
     *      {
     *          $this->session = new Session();
     *          $this->session->start('optional_session_id');
     *      }
     * }
     * ```
     */
    public function start(?string $ssid = null): void
    {
        if ($this->manager instanceof SessionManager) {
            if ((bool) ini_get('session.auto_start')) {
                $this->log('error', 'Session Error: The "session.auto_start" directive is enabled in php.ini. Disable to allow luminova manage sessions internally.');
                return;
            }

            $status = session_status();

            if ($status === PHP_SESSION_ACTIVE) {
                $this->useHandler();
                $this->ipChangeEventListener();
                $this->log('warning', 'Session Warning: A session is already active. Avoid calling $session->start() again.');
                return;
            }
            
            if ($status === PHP_SESSION_DISABLED) {
                throw new RuntimeException(
                    'Session Error: Sessions are disabled in the current environment. Please enable the "session" extension in php.ini to use session functionality.'
                );
            }            

            if ($status === PHP_SESSION_NONE) {
                $this->sessionConfigure();
                $this->useHandler();
                if($ssid !== null){
                    if(self::isValidSessionId($ssid)){
                        session_id($ssid);
                    }elseif(PRODUCTION){
                        $this->log('error', "Session Error: The provided session ID '{$ssid}' is invalid. A new session ID will be generated.");
                    }else{
                        throw new RuntimeException("Session Error: The provided session ID '{$ssid}' is invalid.");
                    }
                }

                session_start();
                $this->ipChangeEventListener();
            }
            return;
        }
        
        $this->sessionConfigure();
        $this->useHandler();
        $this->ipChangeEventListener();
    }

    /**
     * Clears all data stored in the session storage table `$tableIndex` based on your session configuration class.
     * This method differs from PHP's `session_destroy()` as it only affects the session table data, not the entire session.
     * 
     * @return bool Returns true if session data was successfully destroyed; false otherwise.
     */
    public function destroy(): bool 
    {
        return $this->manager->destroyItem();
    }

    /**
     * Clears all data stored in the session for the entire application.
     * This method uses PHP's `session_destroy()` and `setcookie` to affects the entire session.
     * 
     * @return bool Returns true if session data was successfully destroyed; false otherwise.
     * > **Note:** This will clear all session and cookie data for the entire application.
     */
    public function destroyAll(): bool
    {
        $params = self::$config ? [
            'cookieName' => self::$config->cookieName,
            'path' => self::$config->sessionPath,
            'domain' => self::$config->sessionDomain,
        ] : session_get_cookie_params();

        if(!$params){
            return false;
        }

        $_SESSION = [];
        $_COOKIE = [];

        setcookie(
            $params['cookieName'] ?? session_name(),
            '',
            [
                'expires' => time() - 42000, 
                'path' => $params['path'], 
                'domain' => $params['domain'], 
                'secure' => true, 
                'httponly' => true
            ]
        );
        session_destroy();
        return true;
    }

    /**
     * Synchronizes the user's online session, optionally associating it with an IP address.
     *
     * This method is called once after a successful login to initialize and maintain session data,
     * indicating that the user is online. It can also synchronize the session with a specific IP address.
     *
     * @param string|null $ip Optional. The IP address to associate with the session. If not provided,
     *                        the client's current IP address will be used if strict IP validation is enabled.
     *
     * @return self Returns the current Session instance.
     * @throws LogicException If strict IP validation is disabled and IP address is provided.
     * 
     * @example Synchronizing user login session:
     * ```php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\BaseController;
     * class AdminController extends BaseController
     * {
     *      public function loginAction(): int 
     *      {
     *          $username = $this->request->getPost('username');
     *          $password = $this->request->getPost('password');
     *          if($username === 'admin' && $password === 'password'){
     *              $this->app->session->set('username', $username);
     *              $this->app->session->set('email', 'admin@example.com');
     *              $this->app->session->synchronize();
     *              return response()->json(['success' => true]);
     *          }
     * 
     *          return response()->json(['success' => false, 'error' => 'Invalid credentials']);
     *      }
     * }
     * ```
     *
     * > **Note:** If the `$strictSessionIp` configuration option is enabled, the session will
     * > automatically associate with an IP address. When no IP address is provided, the client's
     * > current IP will be detected and used.
     */
    public function synchronize(?string $ip = null): self
    {
        $this->set('_session_online', 'on');
        $this->set('_session_online_id', uniqid('ssid'));
        $this->set('_session_online_datetime', date('c'));

        if(self::$config->strictSessionIp){
            $this->set('_session_online_ip', $ip ?? IP::get());
        }elseif($ip){
            throw new LogicException(sprintf(
                'Invalid Logic: %s %s',
                'The strictSessionIp configuration option is disabled, but an IP address was provided.',
                'To fix the problem, you must set the "App\Config\Session->strictSessionIp" configuration option to true.'
            ));
        }
 
        return $this;
    }

    /**
     * Checks if the user's IP address has changed since the last login session.
     *
     * @param string|null $storage Optional session storage name to perform the check.
     * 
     * @return bool Returns false if the user's IP address matches the session login IP, otherwise returns true.
     */
    public function ipChanged(?string $storage = null): bool
    {
        $default = $this->getStorage();
        $changed = false;

        if($storage && $storage !== $default){
            $this->setStorage($storage);
        }

        if($this->online()){
            $last = $this->get("_online_session_id", '');
            $changed = (!empty($last) && !IP::equals($last));
        }

        if($storage && $storage !== $default){
            $this->setStorage($default);
        }
        
        return $changed;
    }

   /**
     * IP Address Change Listener to detect and respond to user IP changes.
     *
     * This method monitors the user's IP address during a session and `$strictSessionIp` is enabled. If the IP address changes, 
     * the specified callback is executed. Based on the callback's return value:
     * - `true`: The session is destroyed.
     * - `false`: The session remains active, allowing manual handling of the IP change event.
     *
     * @param callable $onChange A callback function to handle the IP change event. 
     *                           The function receives the `Session` instance, the previous IP, 
     *                           and the current IP as arguments.
     * 
     * @return self Returns the current `Session` instance.
     *
     * @example Session IP address change event:
     * 
     * ```php
     * namespace App;
     * use Luminova\Core\CoreApplication;
     * use Luminova\Sessions\Session;
     * 
     * class Application extends CoreApplication
     * {
     *     protected ?Session $session = null;
     * 
     *     protected function onCreate(): void 
     *     {
     *         $this->session = new Session();
     *         $this->session->start();
     * 
     *         $this->session->onIpChanged(function (Session $instance, string $lastIp, string $currentIp): bool {
     *             // Handle the IP address change event manually
     *             return true; // Destroy the session, or return false to keep it or indication that it been handled
     *         });
     *     }
     * }
     * ```
     */
    public function onIpChanged(callable $onChange): self
    {
        $this->onIpChange = $onChange;
        return $this;
    }


    /**
     * Validates a session ID based on PHP's session configuration.
     *
     * This function checks if a given session ID is valid according to the current
     * PHP session configuration, specifically the 'session.sid_bits_per_character'
     * and 'session.sid_length' settings.
     *
     * @param string $id The session ID to validate.
     *
     * @return bool Returns true if the session ID is valid, false otherwise.
     *
     * @throws RuntimeException If the 'session.sid_bits_per_character' setting has an unsupported value.
     */
    public static function isValidSessionId(string $id): bool
    {
        $bitsPerCharacter = (int) ini_get('session.sid_bits_per_character');
        $sidLength = (int) ini_get('session.sid_length');

        $pattern = match ($bitsPerCharacter) {
            4 => '[0-9a-f]',
            5 => '[0-9a-v]',
            6 => '[0-9a-zA-Z,-]',
            default => throw new RuntimeException("Unsupported session.sid_bits_per_character value: '{$bitsPerCharacter}'.")
        };

        return preg_match('/^' . $pattern . '{' . $sidLength . '}$/', $id) === 1;
    }

    /**
     * Handles IP address change events during a user session.
     *
     * This method checks if the user's IP address has changed since the last login 
     * and takes appropriate actions based on the configuration and callback provided:
     * - If `strictSessionIp` is enabled and the IP address has changed:
     *   - Executes the `onIpChange` callback if defined.
     *   - If the callback returns `true` or no callback is defined, the session is cleared.
     * 
     * @return void
     */
    private function ipChangeEventListener(): void
    {
        if(self::$config->strictSessionIp && $this->ipChanged()){
            if( 
                $this->onIpChange !== null && 
                ($this->onIpChange)($this, $this->get("_online_session_id", ''), IP::get())
            ){
                $this->clear();
            }

            if($this->onIpChange === null){
                $this->clear();
            }
        }
    }

    /**
     * Configure session settings.
     *
     * @return void
     */
    private function sessionConfigure(): void
    {
        $sameSite = in_array(self::$config->sameSite, ['Lax', 'Strict', 'None'], true) 
            ? self::$config->sameSite 
            : 'Lax';

        session_set_cookie_params([
            'lifetime' => self::$config->expiration,
            'path'     => self::$config->sessionPath,
            'domain'   => self::$config->sessionDomain,
            'secure'   => true, 
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
        ini_set('session.name', self::$config->cookieName);
        ini_set('session.cookie_samesite', $sameSite);

        if (self::$config->expiration > 0) {
            ini_set('session.gc_maxlifetime', (string) self::$config->expiration);
        }

        if (self::$config->savePath && is_writable(self::$config->savePath)) {
            ini_set('session.save_path', self::$config->savePath);
        }

        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
    }

    /**
     * Enable session storage handler.
     */
    private function useHandler(): void 
    {
        if ($this->handler instanceof BaseSessionHandler) {
            if(self::$config instanceof SessionConfig){
                $this->handler->setConfig(self::$config);
            }
            
            session_set_save_handler($this->handler, true);
        }
    }

    /**
     * Log error messages for debugging purposes.
     *
     * @param string $level The log level.
     * @param string $message The log message.
     *
     * @return void
     */
    private function log(string $level, string $message): void
    {
        Logger::dispatch($level, $message);
    }
}