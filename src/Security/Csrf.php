<?php
/**
 * Luminova Framework Cross-Site Request Forgery Protection.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Security;

use \Luminova\Sessions\Session;
use \App\Config\Session as CookieConfig;

final class Csrf 
{
    /**
     * Token session input name.
     *
     * @var string $tokenName
     */
    private static $tokenName = "csrf_token";

    /**
     * Token session key name.
     *
     * @var string $token
     */
    private static $token = "csrf_token_token";

    /**
     * Cookie config.
     *
     * @var CookieConfig $config
     */
    private static ?CookieConfig $config = null;

    /**
     * Initialize the session configuration.
     */
    private static function intConfig(): void 
    {
        self::$config ??= new CookieConfig();
    }

    /**
     * Retrieves a previously generated CSRF token or generates a new token 
     * if none was found, then stores it.
     *
     * @return string Return the CSRF token.
     */
    public static function getToken(): string 
    {
        if (self::hasToken()) {
            return (self::tokenStorage() === 'cookie') 
                ? $_COOKIE[self::$token] 
                : $_SESSION[self::$token];
        }

        $token = self::generateToken();
        self::saveToken($token);
        return $token;
    }

    /**
     * Generates a new CSRF token and stores it. 
     * Use this method when you need to regenerate a token after validation.
     * 
     * @return string Return the generated CSRF token.
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
     * @param bool $reuse Weather to retain or delete the token after successful verification (default: true).
     * 
     * @return bool Return true if the submitted token is valid, false otherwise.
     */
    public static function validate(string $token, bool $reuse = false): bool 
    {
        self::intConfig();
        $storage = self::tokenStorage();
        $tokenHash = '';

        if ($storage === 'cookie') {
            $tokenHash = self::hasCookie() ? $_COOKIE[self::$token] : '';
        } elseif(isset($_SESSION[self::$token])) {
            $tokenHash = $_SESSION[self::$token];
        }

        if ($tokenHash && hash_equals($tokenHash, $token)) {
            if(!$reuse) {
                self::delete();
            }

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
        return (self::tokenStorage() === 'cookie') 
            ? self::hasCookie() 
            : isset($_SESSION[self::$token]);
    }


    /**
     * Generates a new CSRF token.
     *
     * @return string Return a new generated token.
     */
    private static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Determine which storage location to use.
     * 
     * If session is not enabled fallback to using cookie storage.
     * 
     * @return string Return cookie or session based on csrf storage configuration.
     */
    private static function tokenStorage(): string 
    {
        self::intConfig();
        $status = session_status();

        if (self::$config->csrfStorage === 'cookie' || $status === PHP_SESSION_DISABLED) {
            return 'cookie';
        }

        if($status === PHP_SESSION_NONE){
            (new Session())->start();
        }

        return 'session';
    }

    /**
     * Save token depending on storage.
     * 
     * @param string $token The generated csrf token to save.
     * @param ?int $expiry The expiration time of the token in seconds.
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
     * @return bool Return true if cookie toke exists, false otherwise.
     */
    private static function hasCookie(): bool 
    {
        return isset($_COOKIE[self::$token]) && $_COOKIE[self::$token] !== '';
    }
}