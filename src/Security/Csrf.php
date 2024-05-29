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

use \Luminova\Exceptions\BadMethodCallException;
use \App\Controllers\Config\Session as CookieConfig;

final class Csrf 
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
     * @var CookieConfig $config
    */
    private static ?CookieConfig $config = null;

    /**
     * Call static method as none static 
     * 
     * @param string $name method name 
     * @param array $arguments method arguments
     * 
     * @return mixed 
     * @throws BadMethodCallException
     * @internal
    */
    public function __call(string $name, array $arguments): mixed
    {
        if (method_exists(self::class, $name)) {
            return self::{$name}(...$arguments);
        }
        
        throw new BadMethodCallException("Call to undefined or inaccessible method " . self::class . "::" . $name);
    }

    /**
     * Initalize the session configuration.
    */
    private static function intConfig(): void 
    {
        self::$config ??= new CookieConfig();
    }

    /**
     * Retrieves a previously generated CSRF token or generates a new token if none was found, then stores it.
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
     * Generates a new CSRF token and stores it. 
     * Use this method when you need to regenerate a token after validation.
     * 
     * @return string The generated CSRF token.
    */
    public static function refresh(): string 
    {
        $token = self::generateToken();
        self::saveToken($token);
        return $token;
    }

    /**
     * Delete stored CSRF token.
     *
     * @return void
     */
    public static function delete(): void 
    {
        self::intConfig();
        $storage = self::tokenStorage();

        if($storage === 'cookie'){
            self::saveToken('', time() - self::$config->expiration);
            return;
        }

        unset($_SESSION[self::$token]);
    }

    /**
     * Generates and display an HTML hidden input field for the CSRF token.
     * 
     * @return void 
    */
    public static function inputToken(): void 
    {
        echo '<input type="hidden" name="' . self::$tokenName . '" value="' . self::getToken() . '">';
    }

    /**
     * Generates and display an HTML meta tag for the CSRF token.
     * 
     * @return void
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
    public static function validate(string $token): bool 
    {
        self::intConfig();
        $storage = self::tokenStorage();
        $tokenHash = '';

        if ($storage === 'cookie' && self::hasCookie()) {
            $tokenHash = $_COOKIE[self::$token];
        } elseif(isset($_SESSION[self::$token])) {
            $tokenHash = $_SESSION[self::$token];
        }

        if (empty($tokenHash)) {
            return false;
        }

        if (hash_equals($tokenHash, $token)) {
            self::delete();
            return true;
        }

        return false; 
    }

    /**
     * Checks if a token has already been generated.
     * 
     * @return bool Returns true if a token has already been created, otherwise false.
    */
    public static function hasToken(): bool 
    {
        $storage = self::tokenStorage();

        if ($storage === 'cookie' && self::hasCookie()) {
            return true;
        }

        return isset($_SESSION[self::$token]);
    }


    /**
     * Generates a CSRF token.
     *
     * @return string The generated CSRF token.
    */
    private static function generateToken(): string
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
        self::intConfig();
        if (self::$config->csrfStorage === 'cookie' || session_status() === PHP_SESSION_NONE) {
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
        self::intConfig();
        $storage = self::tokenStorage();

        if($storage === 'cookie'){
            setcookie(self::$token, $token, [
                'expires' => ($expiry ?? time() + self::$config->expiration),
                'path' => self::$config->sessionPath,
                'domain' => self::$config->sessionDomain,
                'secure' => true,
                'httponly' => true,
                'samesite' => self::$config->sameSite 
            ]);
            $_COOKIE[self::$token] = $token;
            return;
        }

        $_SESSION[self::$token] = $token;
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
}
