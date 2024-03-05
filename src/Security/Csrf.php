<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Security;

use \BadMethodCallException;
use \App\Controllers\Config\Session as CookieConfig;

class Csrf 
{
    /**
     * Token session input name
     *
     * @var string $tokenName
    */
    private static $tokenName = "csrf_token";

    /**
     * Token session key name
     *
     * @var string $token
    */
    private static $token = "csrf_token_token";

    /**
     * Cookie config
     *
     * @var string $config CookieConfig::class name
    */
    private static string $config = CookieConfig::class;

    /**
     * Generates a CSRF token.
     *
     * @return string The generated CSRF token.
    */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Detain which storage engin to use
     * If session is not started but dev fallback to using cookie storage
     * 
     * 
     * @return string cookie or session
    */
    private static function tokenStorage(): string 
    {
        if (self::$config::$csrfStorage === 'cookie' || session_status() === PHP_SESSION_NONE) {
            return 'cookie';
        }

        return 'session';
    }

    /**
     * Save token depending on storage
     * 
     * @param string $token
     * @param ?int $expiry
     * 
     * @return void 
    */
    private static function saveToken(string $token, ?int $expiry = null): void 
    {
        $storage = self::tokenStorage();

        if($storage === 'cookie'){
            $expiration = $expiry === null ? time() + self::$config::$expiration : $expiry;
            setcookie(self::$token, $token, [
                'expires' => $expiration,
                'path' => self::$config::$sessionPath,
                'domain' => self::$config::$sessionDomain,
                'secure' => true,
                'httponly' => true,
                'samesite' => self::$config::$sameSite 
            ]);
            $_COOKIE[self::$token] = $token;
        }else{
            $_SESSION[self::$token] = $token;
        }
    }

    /**
     * Check if taken was already created
     * 
     * @return bool 
    */
    private static function hasToken(): bool 
    {
        $storage = self::tokenStorage();

        if($storage === 'cookie' && self::hasCookie()){
            return true;
        }

        return isset($_SESSION[self::$token]);
    }

     /**
     * Check if cookie taken was already created
     * 
     * @return bool 
    */
    private static function hasCookie(): bool 
    {
        return isset($_COOKIE[self::$token]) && !empty($_COOKIE[self::$token]);
    }

    /**
     * Generate and Stores the CSRF token in the session.
     * After it has been validated 
     * 
     * @return string $token 
     */
    public static function refreshToken(): string 
    {
        $token = self::generateToken();

        self::saveToken($token);

        return $token;
    }

    /**
     * Retrieves the CSRF token from the session or generates a new one if not available.
     *
     * @return string The CSRF token.
     */
    public static function getToken(): string 
    {
        if (self::hasToken()) {
            $storage = self::tokenStorage();

            if($storage === 'cookie'){
                return $_COOKIE[self::$token];
            }

            return $_SESSION[self::$token];
        }

        $token = self::generateToken();
        self::saveToken($token);

        return $token;
    }

    /**
     * Generates an HTML input field for the CSRF token.
     * 
     * @return void echo input field with generated CSRF token
     */
    public static function inputToken(): void 
    {
        echo '<input type="hidden" name="' . self::$tokenName . '" value="' . self::getToken() . '">';
    }

    /**
     * Generates an HTML meta tag for the CSRF token.
     * 
     * @return void echo input meta tag with generated CSRF token
     */
    public static function metaToken(): void 
    {
        echo '<meta name="' . self::$tokenName . '" content="' . self::getToken() . '">';
    }

    /**
     * Validates a submitted CSRF token.
     *
     * @param string $token The token submitted by the user.
     *
     * @return bool True if the submitted token is valid, false otherwise.
     */
    public static function validateToken(string $token): bool 
    {
        $storage = self::tokenStorage();
        $tokenHash = '';

        if($storage === 'cookie'){
            if(self::hasCookie()){
                $tokenHash = $_COOKIE[self::$token];
            }
        }elseif(isset($_SESSION[self::$token])){
            $tokenHash = $_SESSION[self::$token];
        }

        if($tokenHash === '' || $tokenHash === null){
            return false;
        }

        if (hash_equals($tokenHash, $token)) {
            self::clearToken($storage);

            return true;
        }

        return false; 
    }

    /**
     * Clear stored token
     *
     * @param string $storage storage engin type
     *
     * @return void 
     */
    private static function clearToken(string $storage): void 
    {
        if($storage === 'cookie'){
            self::saveToken('', time() - self::$config::$expiration);
            return;
        }

        unset($_SESSION[self::$token]);
    }

    /**
     * Call static method as none static 
     * 
     * @param string $name method name 
     * @param array $arguments method arguments
     * 
     * @return mixed 
     * @throws BadMethodCallException
    */
    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists(static::class, $name)) {
            return call_user_func_array([static::class, $name], $arguments);
        }
        throw new BadMethodCallException("Call to undefined method " . static::class . "::" . $name . "()");
    }
}
