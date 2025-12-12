<?php
/**
 * Luminova Framework Urchin Tracking Module.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Attribution;

use \Throwable;
use \Luminova\Logger\Logger;
use \Luminova\Http\Network\IP;
use function \Luminova\Funcs\root;
use \Luminova\Exceptions\RuntimeException;
 
final class UTM
{
    /**
     * File storage mode.
     * 
     * @var string FILE_STORAGE
     */
    public const FILE_STORAGE = 'file';

    /**
     * Cookie storage mode.
     * 
     * @var string COOKIE_STORAGE
     */
    public const COOKIE_STORAGE = 'cookie';

    /**
     * Standard UTM parameter keys.
     * 
     * @var array $params
     */
    private static array $params = [
        'utm_campaign',
        'utm_medium',
        'utm_source',
        'utm_term',
        'utm_content'
    ];

    /**
     * Default expiration time in seconds (30 days).
     * 
     * @var int $expiration
     */
    private static int $expiration = 2592000;

    /**
     * Storage for UTM data.
     * 
     * @var array $storage
     */
    private static array $storage = [];

    /**
     * Storage type ('cookie' or 'file').
     * 
     * @var string $storageType
     */
    private static string $storageType = self::COOKIE_STORAGE;

    /**
     * File path for file storage (if applicable).
     * 
     * @var string $storageFile
     */
    private static string $storageFile = '/writeable/tracking/';

    /**
     * Cookie prefix for UTM data storage.
     * 
     * @var string $cookiePrefix
     */
    private static string $cookiePrefix = 'utm_';

    /**
     * Whether to enable lazy cleanup of expired data (only for cookie storage).
     * 
     * @var bool $lazyCleanup
     */
    private static bool $lazyCleanup = false;

    /**
     * Whether to persist UTM data.
     * 
     * @var bool $persist
     */
    private static bool $persist = true;

    /**
     * Current campaign identifier.
     * 
     * @var string $campaign
     */
    private static string $campaign = '';

    /**
     * Default cookie options.
     * 
     * @var array $cookieOptions
     */
    private static array $cookieOptions = [
        'expires' => 2592000,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    /**
     * Private constructor to prevent instantiation.
     */
    private function __construct(){}
    
    /**
     * Capture and persist UTM parameters from the current request.
     *
     * Reads UTM values from the URL query string (`$_GET`) and stores them
     * using the configured storage backend (cookie or file).
     *
     * If no UTM parameters are present in the request, nothing is stored
     * and `null` is returned.
     *
     * @param string[]|string|null $capture UTM parameter names to capture. When null, the default
     *        configured UTM parameters are used.
     * @param int|null $expiration Expiration time in seconds. Defaults to 30 days (2592000).
     *
     * @return string|null Return the generated or existing client ID, or null when no UTM parameters were captured.
     * @throws RuntimeException If storage is `file` and not writable.
     * 
     * @see self::setStorage() Configure the storage backend.
     * @see self::setExpiration() Configure the default expiration time.
     *
     * @example - Examples:
     * ```php
     * use Luminova\Components\Campaign\UTM;
     *
     * // Configure storage (file or cookie)
     * UTM::setStorage('file', '/path/to/utm_storage.json');
     *
     * // Capture UTM parameters from the current request
     * $clientId = UTM::track();
     *
     * if ($clientId !== null) {
     *     $client = UTM::getClient($clientId);
     *
     *     $ip        = $client->getIp();
     *     $userAgent = $client->getUserAgent();
     *     $campaign  = $client->getCampaign();
     *     $hits      = $client->getHits();
     * }
     * ```
     */
    public static function track(array|string|null $capture = null, ?int $expiration = null): ?string
    {
        $capture ??= self::$params;

        if (is_string($capture)) {
            $capture = [$capture];
        }

        $utmData = [];

        foreach ($capture as $name) {
            if (isset($_GET[$name])) {
                $utmData[$name] = (string) $_GET[$name];
            }
        }

        if ($utmData === []) {
            return null;
        }

        $expiration ??= self::$expiration;
        $ip = IP::get();
        $id = self::getOrGenerateClientId($ip);
        $utmData = array_merge(self::getClientData($id), $utmData);

        $meta = $utmData['_meta'] ?? [];

        $meta += [
            'client_id'   => $id,
            'ip'          => $ip,
            'campaign_id' => self::$campaign,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'referer'     => $_SERVER['HTTP_REFERER'] ?? null,
            'uri'         => isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])
                ? URL_SCHEME . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
                : null,
            'hits'        => 0,
            'created_at'  => time(),
        ];

        $meta['hits']++;
        $meta['expires_at'] = time() + $expiration;

        $utmData['_meta'] = $meta;

        self::save($id, $utmData, $expiration);

        return $id;
    }

    /**
     * Set the current campaign identifier.
     * 
     * @param string $campaign The campaign identifier.
     * 
     * @return void
     */
    public static function campaign(string $campaign): void
    {
        self::$campaign = $campaign;
    }

    /**
     * Set whether to persist UTM data.
     * 
     * If set to false, UTM data will not be stored in cookies or files.
     * 
     * @param bool $persist Whether to persist the UTM data.
     * 
     * @return void
     */
    public static function persistence(bool $persist): void
    {
        self::$persist = $persist;
    }

    /**
     * Set whether to enable lazy cleanup of expired data (only for cookie storage).
     * 
     * @param bool $enabled Whether to enable lazy cleanup.
     * 
     * @return void
     */
    public static function lazyCleanup(bool $enabled): void
    {
        self::$lazyCleanup = $enabled;
    }

    /**
     * Set the cookie prefix for UTM data storage.
     * 
     * @param string $prefix The cookie prefix.
     * 
     * @return void
     */
    public static function setCookiePrefix(string $prefix): void
    {
        self::$cookiePrefix = $prefix;
    }

    /**
     * Get the UTM parameter keys.
     * 
     * @return array The array of UTM parameter keys.
     */
    public static function getParams(): array
    {
        return self::$params;
    }

    /**
     * Get the default expiration time for UTM data.
     * 
     * @return int The expiration time in seconds.
     */
    public static function getExpiration(): int
    {
        return self::$expiration;
    }

    /**
     * Get the storage type for UTM data.
     * 
     * @return string The storage type ('cookie' or 'file').
     */
    public static function getStorageType(): string
    {
        return self::$storageType;
    }

    /**
     * Get the storage file path (if applicable).
     * 
     * @return string|null The storage file path or null if not set.
     */
    public static function getStorageFile(): ?string
    {
        if(self::$storageType !== 'file'){
            return null;
        }

        return self::getFile(false);
    }

    /**
     * Get the cookie prefix used for UTM data storage.
     * 
     * @return string The cookie prefix.
     */
    public static function getCookiePrefix(): string
    {
        return self::$cookiePrefix;
    }

    /**
     * Check if lazy cleanup is enabled.
     * 
     * @return bool Returns true if lazy cleanup is enabled, false otherwise.
     */
    public static function isLazyCleanup(): bool
    {
        return self::$lazyCleanup;
    }

    /**
     * Check if UTM data persistence is enabled.
     * 
     * @return bool Returns true if persistence is enabled, false otherwise.
     */
    public static function isPersistent(): bool
    {
        return self::$persist;
    }

    /**
     * Get the current campaign identifier.
     * 
     * @return string Returns the current campaign ID.
     */
    public static function getCurrentCampaign(): string
    {
        return self::$campaign;
    }

    /**
     * Get all stored UTM clients.
     * 
     * @return UTMClient[] Array of UTMClient instances.
     */
    public static function getClients(): array
    {
        self::load();
        self::cleanupExpired();

        if(self::$storage === []){
            return [];
        }

        return array_map(
            fn($data) => new UTMClient($data),
            array_values(self::$storage)
        );
    }

    /**
     * Get a specific UTM client by ID.
     * 
     * @param string $id The client ID.
     * 
     * @return UTMClient|null The UTMClient instance or null if not found.
     */
    public static function getClient(string $id): ?UTMClient
    {
        self::load($id);
        self::cleanupExpired();

        if (!isset(self::$storage[$id])) {
            return null;
        }

        return new UTMClient(self::$storage[$id]);
    }

    /**
     * Set the storage method for UTM data.
     * 
     * This method allows you to configure how UTM data is stored, either in cookies or in a file.
     * 
     * @param string $type The storage type ('cookie' or 'file').
     * @param array<string,mixed>|string|null $fileOrCookieOptions Optional file path for 'file' storage 
     *                      or cookie options array for 'cookie' storage.
     * 
     * @return void
     * @throws RuntimeException If the file is not writable or readable.
     * 
     * @example - Examples:
     * ```php
     * use Luminova\Components\Campaign\UTM;
     * 
     * // Set storage method to file
     * UTM::setStorage('file', '/writeable/to/utm_storage.json');
     * 
     * // Set storage method to cookie with custom options
     * UTM::setStorage('cookie', [
     *     'path' => '/',
     *     'secure' => true,
     *     'httponly' => true,
     *     'samesite' => 'Lax'
     * ]);
     * ```
     */
    public static function setStorage(string $type, array|string|null $fileOrCookieOptions = null): void
    {
        if (!in_array($type, [self::COOKIE_STORAGE, self::FILE_STORAGE], true)) {
            throw new RuntimeException(
                "Invalid UTM storage type: {$type}. Use 'cookie' or 'file'."
            );
        }

        self::$storageType = $type;

        if(!$fileOrCookieOptions){
            return;
        }

        $isArray = is_array($fileOrCookieOptions);

        if ($type === 'cookie' && $isArray) {
            self::$cookieOptions = array_merge(
                self::$cookieOptions, 
                $fileOrCookieOptions
            );
            return;
        }
    
        if ($type === 'file' && !$isArray) {
            if (!is_writable($fileOrCookieOptions)) {
                throw new RuntimeException("UTM storage file is not writable: $fileOrCookieOptions");
            }

            if(!is_readable($fileOrCookieOptions)){
               throw new RuntimeException("UTM storage file is not readable: $fileOrCookieOptions");
            }

            if (is_file($fileOrCookieOptions)) {
                self::$storageFile = $fileOrCookieOptions;
                self::$storage = json_decode(file_get_contents(self::$storageFile), true) ?: [];
                return;
            }

            self::$storageFile = root($fileOrCookieOptions, 'utm_storage.json');
        }
    }

    /**
     * Set the default expiration time for UTM data.
     * 
     * @param int $seconds Expiration time in seconds.
     * 
     * @return void
     */
    public static function setExpiration(int $seconds): void
    {
        self::$expiration = $seconds;
    }

    /**
     * Clear expired UTM data from file storage.
     * 
     * @return int Returns the number of cleared entries.
     * > Cron-friendly cleanup for file storage
     */
    public static function clearFileStorage(): int
    {
        if(!self::$persist){
            return 0;
        }

        if (self::$storageType !== 'file') {
            return 0;
        }

        $file = self::getFile();

        if (!$file) {
            return 0;
        }

        $data = json_decode(file_get_contents($file), true) ?: [];
        $now = time();
        $count = 0;
        foreach ($data as $id => $client) {
            if (isset($client['_meta']['expires_at']) && $client['_meta']['expires_at'] < $now) {
                unset($data[$id]);
                $count++;
            }
        }

        if (file_put_contents($file, json_encode($data)) !== false) {
            return $count;
        }

        return 0;
    }

    /**
     * Generate or retrieve the client ID.
     * 
     * @param string $ip The client's IP address.
     * 
     * @return string The client ID.
     */
    private static function getOrGenerateClientId(string $ip): string
    {
        $key = self::$cookiePrefix . 'client_id';

        if (!empty($_COOKIE[$key])) {
            self::refresh($key, $_COOKIE[$key]);
            return $_COOKIE[$key];
        }

        $id = md5(
            self::$campaign .
            $ip . 
            ($_SERVER['HTTP_USER_AGENT'] ?? '') . 
            ($_SERVER['HTTP_REFERER'] ?? '')
        );

        if(!self::$persist){
            return $id;
        }

        self::load($id);
        self::store($key, $id, time() + self::$expiration, false);
        $_COOKIE[$key] = $id;

        return $id;
    }

    /**
     * Retrieve stored UTM data for a specific client ID.
     * 
     * @param string $id The client ID.
     * 
     * @return array The stored UTM data.
     */
    private static function getClientData(string $id): array
    {
        self::load($id);
        return self::$storage[$id] ?? [];
    }

    /**
     * Save UTM data for a specific client ID.
     * 
     * @param string $id The client ID.
     * @param array $data The UTM data to store.
     * @param int|null $expiration Expiration time in seconds.
     * 
     * @return void
     */
    private static function save(string $id, array $data, ?int $expiration = null): void
    {
        $expiration ??= self::$expiration;
        self::$storage[$id] = $data;

        if(!self::$persist){
            return;
        }

        if (self::$storageType === 'cookie') {
            $name = self::$cookiePrefix . $id;
            self::store($name, json_encode($data), time() + $expiration);
            $_COOKIE[$name] = $data;
            return;
        }
        
        if (self::$storageType === 'file') {
            $file = self::getFile();

            if (!$file) {
                return;
            }

            file_put_contents($file, json_encode(self::$storage));
        }
    }

    /**
     * Resolve and return the UTM storage file path.
     *
     * If a directory is provided, the default storage file
     * "utm_storage.json" will be used inside that directory.
     *
     * @param bool $createIfNotExists Whether to create the file if it does not exist.
     *
     * @return string|null The resolved storage file path, or null if it does not exist.
     * @throws RuntimeException If the file cannot be created or is not writable.
     */
    private static function getFile(bool $createIfNotExists = true): ?string
    {
        if (!self::$storageFile) {
            return null;
        }

        $file = self::$storageFile;

        if (is_dir($file)) {
            $file = root($file, 'utm_storage.json');
        }

        if (!file_exists($file)) {
            if (!$createIfNotExists) {
                return null;
            }

            if (!@touch($file)) {
                throw new RuntimeException(
                    'Unable to create UTM storage file at: ' . $file
                );
            }
        }

        if ($createIfNotExists && !is_writable($file)) {
            throw new RuntimeException(
                'UTM storage file is not writable: ' . $file
            );
        }

        return self::$storageFile = $file;
    }

    /**
     * Load UTM data from storage.
     * 
     * @param string|null $id Optional client ID to load specific data.
     * 
     * @return void
     */
    private static function load(?string $id = null): void
    {
        if(!self::$persist || ($id !== null && isset(self::$storage[$id]))){
            return;
        }

        if (self::$storageType === 'cookie') {
            foreach ($_COOKIE as $key => $value) {

                if($key === self::$cookiePrefix . 'client_id'){
                    continue;
                }

                if (str_starts_with($key, self::$cookiePrefix)) {

                    $decoded = self::decode($value);

                    if(!$decoded){
                        continue;
                    }

                    $client = $decoded['_meta']['client_id'] ?? $key;

                    if ($client === null || ($id !== null && $client !== $id)) {
                        continue;
                    }

                    self::$storage[$client] = $decoded;
                    self::refresh($key, $value);
                }
            }

            // Lazy cleanup: remove expired cookies
            if (self::$lazyCleanup) {
                self::lazyCookieCleanup();
            }

            return;
        }
        
        if (self::$storageType === 'file') {
            $file = self::getFile();

            if (!$file) {
                return;
            }
            
            self::$storage = json_decode(file_get_contents($file), true) ?: [];
        }
    }

    /**
     * Cleanup expired UTM data from storage.
     * 
     * @return void
     */
    private static function cleanupExpired(): void
    {
        $now = time();
        foreach (self::$storage as $id => $data) {
            if (isset($data['_meta']['expires_at']) && $data['_meta']['expires_at'] < $now) {
                unset(self::$storage[$id]);

                if (self::$storageType === 'cookie') {
                    self::store($id, '', time() - 3600, false);
                    unset($_COOKIE[$id]);
                }
            }
        }
    }

    /**
     * Lazy cleanup of expired cookies.
     * 
     * Lazy cleanup: remove expired cookie data during page load
     * 
     * @return void
     */
    private static function lazyCookieCleanup(): void
    {
        if(!self::$persist){
            return;
        }

        $now = time();
        foreach ($_COOKIE as $key => $value) {
            if($key === self::$cookiePrefix . 'client_id'){
                continue;
            }

            if (str_starts_with($key, self::$cookiePrefix)) {
                $decoded = self::decode($value);

                if(!$decoded){
                    continue;
                }

                if ($decoded && isset($decoded['_meta']['expires_at']) && $decoded['_meta']['expires_at'] < $now) {
                    self::store($key, '', time() - 3600, false);
                    unset($_COOKIE[$key], self::$storage[$key]);
                    //unset(self::$storage[$decoded['_meta']['client_id']]);
                }
            }
        }
    }

    /**
     * Decode UTM cookie.
     *
     * @param mixed $data The raw data to decode.
     * 
     * @return mixed Return decoded UTM cookie data.
     */
    private static function decode(mixed $data): mixed 
    {
        if(!$data){
            return null;
        }

        if(is_string($data) && str_starts_with($data, 'gz:')){
            $data = gzinflate(base64_decode(substr($data, 3), true));

            if ($data === false) {
                return null;
            }
        }

        if(!json_validate($data)){
            return $data;
        }

        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        }catch(Throwable $e){
            Logger::error('UTM Cookie Error: failed to decode cookie data' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Refresh the cookie expiration time.
     * 
     * @param string $name The cookie name.
     * @param mixed $value The cookie value.
     * 
     * @return void
     */
    private static function refresh(string $name, mixed $value): void
    {
        if(!self::$persist){
            return;
        }

        if(is_array($value)){
            $value = json_encode($value);
        }

        self::store($name, $value, time() + self::$expiration);
    }

    /**
     * Store a cookie with specified parameters.
     * 
     * @param string $name The name of the cookie.
     * @param string $value The value of the cookie.
     * @param int $expiry The expiration timestamp of the cookie.
     * 
     * @return bool Returns true on success, false on failure.
     */
    private static function store(
        string $name, 
        string $value, 
        int $expiry,
        bool $encode = true
    ): bool
    {
        $options = self::$cookieOptions;
        $options['expires'] = $expiry;

        if($encode && $name !== self::$cookiePrefix . 'client_id' && !str_starts_with($value, 'gz:')){
            $value = 'gz:' . base64_encode(gzdeflate($value, 6));
        }

        if (strlen($name) + strlen($value) > 3800) {
            return false;
        }

        return setcookie($name, $value, $options);
    }
}