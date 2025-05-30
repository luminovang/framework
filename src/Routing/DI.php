<?php
/**
 * Luminova Framework Dependency Injection System.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Routing;

use \Closure;
use \Luminova\Seo\Schema;
use \Luminova\Email\Mailer;
use \Luminova\Http\Network;
use \Luminova\Http\Request;
use \Luminova\Cookies\Cookie;
use \Luminova\Cache\FileCache;
use \Luminova\Arrays\ArrayUtil;
use \Luminova\Cache\RedisCache;
use \Luminova\Http\Client\Curl;
use \Luminova\Security\JWTAuth;
use \Luminova\Sessions\Session;
use \Luminova\Cache\MemoryCache;
use \Luminova\Template\Response;
use \Luminova\Application\Factory;
use \Luminova\Languages\Translator;
use \Luminova\Cookies\CookieFileJar;
use \Luminova\Interface\ClientInterface;
use \Luminova\Interface\CookieInterface;
use \Luminova\Interface\NetworkInterface;
use \Luminova\Security\Encryption\Sodium;
use \Luminova\Security\Encryption\OpenSSL;
use \Luminova\Interface\CookieJarInterface;
use \Luminova\Interface\HttpRequestInterface;
use \Luminova\Interface\ViewResponseInterface;
use \Luminova\Notifications\Firebase\Notification;

class DI
{
    /**
     * User-defined bindings.
     * 
     * @var array<class-string,Closure|string> $bindings
     */
    private static array $bindings = [];

    /**
     * Bind a class or interface for dependency injection (DI) in controller methods.
     *
     * @param class-string $abstract Class or interface to bind.
     * @param Closure|class-string $resolver A class name or a closure that returns the instance.
     * 
     * @return void
     * 
     * @example - Defining dependencies:
     * 
     * ```php
     * namespace App;
     * 
     * use \Luminova\Core\CoreApplication;
     * use \Luminova\Routing\DI;
     * 
     * class Application extends CoreApplication
     * {
     *      protected function onPreCreate(): void 
     *      {
     *          // Simple binding using class name
     *          DI::bind(MyInterface::class, MyConcreteClass::class);
     * 
     *          // Custom instance with dependencies
     *          DI::bind('custom_service', function () {
     *              return new MyService(dependency: new SomeDependency());
     *          });
     * 
     *          // Logger example
     *          DI::bind(\Psr\Log\LoggerInterface::class, function () {
     *              return new \MyApp\Log\FileLogger('/writeable/logs/app.log');
     *          });
     *      }
     * }
     * ```
     * @note Use class names for simple objects. Only use closures when constructor logic is needed.
     */
    public static function bind(string $abstract, Closure|string $resolver): void 
    {
        self::$bindings[$abstract] = $resolver;
    }

    /**
     * Check if a class or interface is bound or has a default mapping.
     *
     * @param class-string $class The class or interface name.
     * 
     * @return bool Return true if resolvable, false otherwise.
     */
    public static function has(string $class): bool 
    {
        return isset(self::$bindings[$class]) || self::getDefault($class) !== null;
    }    

    /**
     * Resolve a new instance of a class or its interface mapping.
     *
     * @param class-string $class The class or interface name.
     * 
     * @return object<\T>|null Returns the resolved object or null if not mapped.
     */
    public static final function newInstance(string $class): ?object
    {
        $resolver = self::$bindings[$class] ?? self::getDefault($class);

        if ($resolver === null) {
            return null;
        }

        if($resolver instanceof Closure){
            return $resolver();
        }

        if (!class_exists($resolver)) {
            return null;
        }

        return new $resolver();
    }

    /**
     * Default resolver mappings for core classes/interfaces.
     *
     * @param class-string $class The class or interface name.
     * 
     * @return Closure|class-string|null Return class name or closure that resolves to class object.
     */
    private static function getDefault(string $class): Closure|string|null
    {
        return match ($class) {
            Request::class, HttpRequestInterface::class      => Request::class,
            Network::class, NetworkInterface::class          => Network::class,
            Curl::class, ClientInterface::class              => Curl::class,
            Response::class, ViewResponseInterface::class    => Response::class,
            Session::class       => Session::class,
            Factory::class       => Factory::class,
            Schema::class        => Schema::class,
            Mailer::class        => Mailer::class,
            FileCache::class     => FileCache::class,
            MemoryCache::class   => MemoryCache::class,
            RedisCache::class    => RedisCache::class,
            ArrayUtil::class     => ArrayUtil::class,
            OpenSSL::class       => OpenSSL::class,
            Sodium::class        => Sodium::class,
            JWTAuth::class       => JWTAuth::class,
            Translator::class    => Translator::class,
            Notification::class  => Notification::class,
            Cookie::class, CookieInterface::class            => fn(): CookieInterface => new Cookie('_default'),
            CookieFileJar::class, CookieJarInterface::class  => fn(): CookieJarInterface => new CookieFileJar(
                root('/writeable/temp/cookies.txt')
            ),
            default => null,
        };
    }
}