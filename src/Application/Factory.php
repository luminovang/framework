<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Application;

use \Luminova\Time\Task;
use \Luminova\Core\CoreFunction;
use \Luminova\Application\Caller;
use \Luminova\Sessions\Session;
use \Luminova\Cookies\Cookie;
use \Luminova\Functions\Escape;
use \Luminova\Library\Modules;
use \Luminova\Languages\Translator;
use \Luminova\Storages\FileManager;
use \Luminova\Http\Request;
use \Luminova\Http\Network;
use \Luminova\Application\Services;
use \App\Config\Services as BootServices;
use \Luminova\Template\Response;
use \Luminova\Logger\Logger;
use \Luminova\Security\Validation;
use \Luminova\Notifications\Firebase\Notification;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\AppException;
use \Exception;
use \Throwable;

/**
 * Factory methods classes.
 *
 * @method static CoreFunction        functions(bool $shared = true)                             Utility function helper class.
 * @method static Session             session(?\Luminova\Interface\SessionManagerInterface $manager = null, bool $shared = true)                   Server-side user session class, if manager is null `\Luminova\Sessions\SessionManager` will be used instead.
 * @method static Cookie              cookie(string $name, mixed $value = '', array $options = [], bool $shared = true)                    Client-side cookie class
 * @method static Task                task(bool $shared = true)                      Time task utility class.
 * @method static Modules             modules(bool $shared = true)                               PSR-4 Module autoloader and file importer class.
 * @method static Translator          language(?string $locale = null, bool $shared = true)      Application translation class.
 * @method static Logger              logger(bool $shared = true)                                PSR logger class.
 * @method static FileManager         fileManager(bool $shared = true)                           File manager class.
 * @method static Validation          validate(bool $shared = true)                              Input validation class.
 * @method static Response            response(int $status = 200, array $headers = [], bool $shared = true)           Render response class.
 * @method static Request             request(bool $shared = true)                               HTTP Request class.
 * @method static Network             network(?\Luminova\Interface\ClientInterface $client = null, bool $shared = true)                               HTTP Network request class.
 * @method static Caller              caller(bool $shared = true)                                Class caller class.
 * @method static Notification        notification(string $serviceAccount = 'serviceAccount.json', bool $shared = true)                              Firebase cloud message notification class.
 * @method static Escape              escaper(string|null $encoding = 'utf-8', bool $shared = true)                              Input escaper class instance.
*/
final class Factory 
{
    /**
     * Cached instances of factories classes.
     *
     * @var array<string,object> $instances
     */
    private static array $instances = [];

    /**
     * Available factory classes.
     *
     * @var array<string,class-string<\T>> $classes
     */
    private static array $classes = [
        'task'          => Task::class,
        'session'       => Session::class,
        'cookie'        => Cookie::class,
        'functions'     => 'CoreFunction',
        'escaper'       => Escape::class,
        'modules'       => Modules::class,
        'language'      => Translator::class,
        'logger'        => Logger::class,
        'fileManager'   => FileManager::class,
        'validate'      => Validation::class,
        'response'      => Response::class,
        'request'       => Request::class,
        'network'       => Network::class,
        'caller'        => Caller::class,
        'notification'        => Notification::class
    ];

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $factory The factory class name.
     * @param array $arguments Arguments to pass to the factory constructor.
     * @param bool $shared The last parameter to pass to the factory constructor indicate if it should return a shared instance.
     * 
     * @return object<\T> An instance of the factory class.
     * @throws RuntimeException If failed to instantiate the factory.
     * 
     * @example - Invoke method with param and no-hared instance:
     * 
     * ```php 
     * use \Luminova\Application\Factory; 
     * 
     * $result = Factory::method('foo', 'bar', false)
     * ```
     * 
     * @example - Invoke method without param and no-hared instance:
     * 
     * ```php 
     * use \Luminova\Application\Factory; 
     * $result = Factory::method(false)
     * ```
     * 
     * @ignore 
     */
    public static function __callStatic(string $factory, array $arguments): object
    {
        return self::call($factory, $arguments);
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $factory The factory class name.
     * @param array $arguments Arguments to pass to the factory constructor.
     * @param bool $shared The last parameter to pass to the factory constructor indicate if it should return a shared instance.
     * 
     * @return object<\T> An instance of the factory class.
     * @throws RuntimeException If failed to instantiate the factory.
     * 
     * @ignore 
     * @example - Invoke method with param and no-shared instance:
     * ```php 
     * $result = factory()->method('foo', 'bar', false)
     * ```
     * 
     * @example - Invoke method without param and no-shared instance:
     * 
     * ```php 
     * $result = factory()->method(false)
     * ```
     */
    public function __call(string $factory, array $arguments): object
    {
        return self::call($factory, $arguments);
    }

    /**
     * Check if class is available in factories
     *
     * @param string $factory The factory class name.
     * 
     * @return bool Return true if class is available in factories, false otherwise.
     */
    public static function has(string $factory): bool
    {
        return self::locator($factory) !== null;
    }

     /**
     * Delete a shared instance of factory class.
     *
     * @param string $factory The factory class name.
     * 
     * @return bool Return true if shared instance was deleted, false otherwise.
    */
    public static function delete(string $factory): bool
    {
        if(isset(self::$instances[$factory])){
            unset(self::$instances[$factory]);
            return true;
        }

        return false;
    }

    /**
     * Clear all shared instance of factory.
     * 
     * @return void
    */
    public static function clear(): void
    {
        self::$instances = [];
    }

    /**
     * Return shared service class instance.
     * 
     * @param bool $shared Shared instance or not (default: true).
     * 
     * @return Services Return instance of service class
    */
    public static function service(): Services
    {
        if(isset(self::$instances['service'])){
            return self::$instances['service'];
        }
   
        self::$instances['service'] = new Services();
        return self::$instances['service'];
    }

    /**
     * Initialize and Register queued services.
     * 
     * @return void 
     * @ignore
    */
    public static function register(): void 
    {
        try{
            static $boot = null;
            $boot ??= new BootServices();
     
            $boot->bootstrap();
            $instance = self::service();
            $instance->queService($boot->getServices());
            self::$instances['service'] = $instance;
        }catch(RuntimeException $e){
            Logger::dispatch('critical', 'Error occurred while registering service. ' . $e->getMessage());
        }
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $factory The factory class name.
     * @param array $arguments Argument to pass to the factory constructor.
     * 
     * @return object<\T> An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function call(string $factory, array $arguments): object
    {
        $shared = true; 
        
        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        if ($shared && isset(self::$instances[$factory])) {
            return self::$instances[$factory];
        }

        $class = self::locator($factory);

        if ($class === null) {
            throw new RuntimeException("Factory with method name '$factory' does not exist.");
        }

        return self::create($class, $factory, $shared, ...$arguments);
    }

    /**
     * Create an instance of the specified factory class.
     *
     * @param class-string<\T> $class The class name.
     * @param string|null $alias The alias of the factory class.
     * @param bool $shared Whether the instance should be shared or not.
     * @param mixed $arguments [, mixed $... ] Parameters to pass to the factory constructor.
     *  
     * @return object<\T> An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function create(string $class, ?string $alias = null, bool $shared = true, mixed ...$arguments): object
    {
        try {
            $instance = ($class === 'CoreFunction') 
                ? new class extends CoreFunction{} 
                : new $class(...$arguments);
            
            if ($shared && $alias) {
                self::$instances[$alias] = $instance;
            }

            return $instance;
        } catch (Throwable|Exception $e) {
            if($e instanceof AppException){
                throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            throw new RuntimeException("Failed to instantiate factory class: '{$alias}', " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get the fully qualified class name of the factory based on the provided context.
     *
     * @param string $factory The factory class name.
     * 
     * @return class-string<\T>|null Return the fully qualified class name.
    */
    private static function locator(string $factory): ?string
    {
        return self::$classes[$factory] ?? null;
    }
}