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

use \Luminova\Sessions\SessionInterface;
use \App\Controllers\Config\Session as SessionConfig;
use \Psr\Log\LoggerInterface;
use \Luminova\Logger\NovaLogger;
use \Luminova\Sessions\SessionManager;

class Session 
{
    /**
     * session interface
     * 
     * @var SessionInterface $manager
    */
    protected ?SessionInterface $manager = null;

    /**
     * logger interface
     * 
     * @var LoggerInterface $logger
    */
    protected ?LoggerInterface $logger = null;

    /**
     * static class instance
     * 
     * @var Session $instance 
    */
    protected static ?Session $instance = null;

    /**
     * session config instance
     * 
     * @var null|string $config 
    */
    protected static ?string $config = null;

    /**
     * Initializes session constructor
     *
     * @param SessionInterface $manager The session manager.
    */
    public function __construct(SessionInterface $manager = null)
    {
        static::$config = SessionConfig::class;
        $this->manager = $manager ?? new SessionManager();
        $this->ipAuthSession();
    } 

    /**
     * Get an instance of the Session class.
     *
     * @param SessionInterface $manager The session manager.
     * 
     * @return static self instance
    */
    public static function getInstance(SessionInterface $manager): static
    {
        if (static::$instance === null) {
            static::$instance = new static($manager);
        }

        return static::$instance;
    }

    /**
     * Set the logger for this session.
     *
     * @param LoggerInterface $logger The logger to set.
    */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /** 
     * Get data as array from current session storage 
     * 
     * @param string $index optional key to get
     * 
     * @return array
    */
    public function toArray(string $index = ''): array
    {
        return $this->manager->toArray($index);
    }

    /** 
     * Get data as object from current session storage
     * 
     * @param string $index optional key to get
     * 
     * @return object
    */
    public function toObject(string $index = ''): object
    {
        return $this->manager->toObject($index);
    }

    /** 
     * Get all storage data as array 
     * 
     * @return array
    */
    public function toExport(): array
    {
        return $this->manager->getResult();
    }

    /**
     * Set the session manager.
     *
     * @param SessionInterface $manager The session manager to set.
    */
    public function setManager(SessionInterface $manager): void
    {
        $this->manager = $manager;
    }

    /**
     * Get the session manager.
     *
     * @return SessionInterface $this->manager 
    */
    public function getManager(): ?SessionInterface
    {
        return $this->manager;
    }

    /**
     * Set the storage key for the session.
     *
     * @param string $storage The storage key to set.
    */
    public function setStorage(string $storage): self
    {
        $this->manager->setStorage($storage);

        return $this;
    }

    /**
     * Get storage name
     * 
     * @return string
    */
    public function getStorage(): string 
    {
        return $this->manager->getStorage();
    }

    /**
     * Get the value from the session by key.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default default value 
     * 
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->get($key, $default);
    }

    /** 
     * Get data from specified storage instance
     * 
     * @param string $index value key to get
     * @param string $storage Storage key name
     * 
     * @return mixed
    */
    public function getFrom(string $index, string $storage): mixed
    {
        return $this->manager->getFrom($index, $storage);
    }

    /** 
     * Get data from specified storage instance
     * 
     * @param string $index value key to get
     * @param mixed $data data to set
     * @param string $storage Storage key name
     * 
     * @return self
    */
    public function setTo(string $index, mixed $data, string $storage): self
    {
        $this->manager->setTo($index, $data, $storage);

        return $this;
    }

    /** 
     * Check if session user is online from any storage instance
     * 
     * @param string $storage optional storage instance key
     * 
     * @return bool
    */
    public function online(string $storage = ''): bool
    {
        return $this->manager->online($storage);
    }

    /**
     * Set the value in the session by key.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     * 
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        $this->manager->set($key, $value);

        return $this;
    }

    /**
     * Add a value to the session by key.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     * 
     * @return self
     */
    public function add(string $key, mixed $value): self
    {
        $this->manager->add($key, $value);

        return $this;
    }

   /**
     * Remove a value from the session by key.
     *
     * @param string $key The key to remove.
     * 
     * @return self
     */
    public function remove(string $key): self
    {
        $this->manager->remove($key);

        return $this;
    }

    /**
     * Clear the session storage.
     *
     * @param string $storage The storage key to clear.
     * 
     * @return self
     */
    public function clear(string $storage = ''): self
    {
        $this->manager->clear($storage);
        
        return $this;
    }

    /** 
     * Check if key exists in session
     * @param string $key
     * 
     * @return bool
    */
    public function has(string $key): bool
    {
        return $this->manager->has($key);
    }

    /**
    * Initialize and start session manager.
    *
    * @return void
    */
    public function start(): void
    {
        if ($this->manager instanceof SessionManager) {
            $this->logger ??= new NovaLogger();
            if ((bool) ini_get('session.auto_start')) {
                $this->logger->error('Session: session.auto_start is enabled in php.ini. Aborting.');
                return;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                //$this->logger->warning('Session: Sessions is enabled, and one exists. Please don\'t $session->start();');
                return;
            }

            if (session_status() === PHP_SESSION_NONE) {
                $this->sessionConfigure();
                session_start();
            }
            return;
        }
        
        $this->sessionConfigure();
    }

    /**
     * Start an online session with an optional IP address.
     *
     * @param string $ip The IP address.
     * 
     * @return self
    */
    public function synchronize(string $ip = ''): self
    {
        $this->manager->set('_online', 'YES');

        if(static::$config::$strictSessionIp){
            $ip = func()->ip()->get();
            if($ip){
                $this->manager->set('_online_session_id', $ip);
            }
        }
 
        return $this;
    }

    /**
     * Check if user ip address match with session login ip
     * If not logout
     *
     * @param string $storage Optional storage location
     * 
     * @return void
     */
    public function ipAuthSession(string $storage = ''): void
    {
        if(static::$config::$strictSessionIp){
            $default = $this->getStorage();

            if($storage !== '' && $storage !== $default){
                $this->setStorage($storage);
            }

            if($this->manager->online()){
                $last = $this->manager->get("_online_session_id", '');
                $current = func('ip')->get();
                if($last !== null && $last !== '' && $last !== $current){
                    $this->manager->set('_online', '');
                    $this->manager->set('_online_session_id', '');
                }
            }

            if($storage !== '' && $storage !== $default){
                $this->setStorage($default);
            }

        }
    }

    /**
     * Check if user ip address match with session login ip
     *
     * @param string $storage Optional storage location
     * 
     * @return bool
     */
    public function ipChanged(string $storage = ''): bool
    {
        $default = $this->getStorage();
        if($storage !== '' && $storage !== $default){
            $this->setStorage($storage);
        }

        if($this->manager->online()){
            $last = $this->manager->get("_online_session_id", '');
            $current = func('ip')->get();
            if($last !== null && $last !== '' & $last !== $current){
                return false;
            }
        }

        if($storage !== '' && $storage !== $default){
            $this->setStorage($default);
        }
        
        return true;
    }

    /**
    * Configure session settings.
    *
    * @return void
    */
    private function sessionConfigure(): void
    {
        $cookieParams = [
            'lifetime' => time() + static::$config::$expiration,
            'path'     => static::$config::$sessionPath,
            'domain'   => static::$config::$sessionDomain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => static::$config::$sameSite,
        ];
        ini_set('session.name', static::$config::$cookieName);
        ini_set('session.cookie_samesite', static::$config::$sameSite);
        session_set_cookie_params($cookieParams);

        if (static::$config::$expiration > 0) {
            ini_set('session.gc_maxlifetime', (string) $cookieParams['lifetime']);
        }

        if (static::$config::$savePath !== '') {
            ini_set('session.save_path', static::$config::$savePath);
        }

        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        $this->manager->setConfig(static::$config);
    }
}