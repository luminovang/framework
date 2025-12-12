<?php
/**
 * Luminova Framework Cross-Site Request Forgery Protection.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \App\Config\Session as Config;
use Luminova\Interface\SessionManagerInterface;
use Luminova\Sessions\Managers\{Session, Cookie};

/**
 * Cross-Site Request Forgery (CSRF) token manager.
 *
 * Generates, stores, retrieves, validates, and deletes CSRF tokens.
 * Uses session storage by default and falls back to cookie storage when
 * cookie storage is configured or PHP sessions are unavailable.
 *
 * @see self::csrf() To determine the token storage manager.
 * @see \Luminova\Http\Request::getCsrfToken() To retrieve the token from a request.
 */
final class CSRF
{
    /**
     * Token input name.
     *
     * @var string INPUT_NAME
     */
    public const INPUT_NAME = 'csrf_token';

    /**
     * Token session storage key.
     *
     * @var string
     */
    private const TOKEN_NAME = 'csrf_token_token';

    /**
     * Session storage manager.
     *
     * @var SessionManagerInterface|null $manager
     */
    private static ?SessionManagerInterface $manager = null;

    /**
     * Private constructor.
     */
    private function __construct(){}

    /**
     * Retrieve the current CSRF token or generate one if none exists.
     *
     * @return string Return the current CSRF token.
     */
    public static function getToken(): string
    {
        $token = self::csrf()->getItem(self::TOKEN_NAME, '');

        return ($token !== '') 
            ? $token 
            : self::refresh();
    }

    /**
     * Return the CSRF token as an HTTP response header.
     *
     * @return array{X-CSRF-Token:string} Return the CSRF response header.
     */
    public static function getHeader(): array
    {
        return [
            'X-CSRF-Token' => self::getToken(),
        ];
    }

    /**
     * Generate and store a new CSRF token.
     *
     * Use this method to replace the current token, such as after
     * successful validation when a new token is required.
     *
     * @return string Return the generated CSRF token.
     */
    public static function refresh(): string
    {
        $token = self::newToken();

        self::csrf()->setItem(self::TOKEN_NAME, $token);

        return $token;
    }

    /**
     * Delete the stored CSRF token.
     *
     * @return void
     */
    public static function delete(): void
    {
        self::csrf()->deleteItem(self::TOKEN_NAME);
    }

    /**
     * Output an HTML hidden input containing the CSRF token.
     *
     * @return void
     */
    public static function inputToken(): void
    {
        printf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars(self::INPUT_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Output an HTML meta tag containing the CSRF token.
     *
     * @return void
     */
    public static function metaToken(): void
    {
        printf(
            '<meta name="%s" content="%s">',
            htmlspecialchars(self::INPUT_NAME, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Validate a submitted CSRF token.
     *
     * @param string $token The token submitted by the user.
     * @param bool $reusable Whether to retain the token after successful verification.
     *
     * @return bool Return true if the token is valid, false otherwise.
     */
    public static function validate(string $token, bool $reusable = false): bool
    {
        if ($token === '') {
            return false;
        }

        $stored = self::csrf()->getItem(self::TOKEN_NAME, '');

        if ($stored === '' || !hash_equals($stored, $token)) {
            return false;
        }

        if (!$reusable) {
            self::delete();
        }

        return true;
    }

    /**
     * Checks if a token has already been generated.
     * 
     * @return bool Returns true if a token has already been created, otherwise false.
     */
    public static function hasToken(): bool 
    {
        return self::csrf()->hasItem(self::TOKEN_NAME);
    }

    /**
     * Generate a cryptographically secure CSRF token.
     *
     * @return string Return a 64-character hexadecimal token.
     */
    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Determine the storage location for CSRF tokens.
     *
     * Uses session storage by default and falls back to cookie storage when
     * cookie storage is configured or PHP sessions are unavailable.
     *
     * @return SessionManagerInterface Return the configured CSRF storage manager.
     */
    private static function csrf(): SessionManagerInterface
    {
        if (self::$manager instanceof SessionManagerInterface) {
            return self::$manager;
        }

        $config = new Config();
        $config->sameSite = 'Strict';
        $config->useStrictMode = true;

        $useCookie = $config->csrfStorage === 'cookie'
            || session_status() === PHP_SESSION_DISABLED;

        self::$manager = $useCookie
            ? new Cookie('csrf')
            : new Session('csrf');

        $useCookie 
            ? self::$manager->setConfig($config)
            : \Luminova\Sessions\Session::configure($config);

        self::$manager->setNamespace('csrf_auth');
        self::$manager->start();

        return self::$manager;
    }
}