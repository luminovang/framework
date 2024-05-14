<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Application;

use \Luminova\Time\Task;
use \Luminova\Application\Functions;
use \Luminova\Application\Caller;
use \Luminova\Sessions\Session;
use \Luminova\Cookies\Cookie;
use \Luminova\Library\Modules;
use \Luminova\Languages\Translator;
use \Luminova\Storages\FileManager;
use \Luminova\Http\Request;
use \Luminova\Http\Network;
use \Luminova\Application\Services;
use \App\Controllers\Config\Services as BootServices;
use \Luminova\Template\ViewResponse;
use \Luminova\Logger\Logger;
use \Luminova\Security\InputValidator;
use \Luminova\Exceptions\RuntimeException;
use \Exception;
use \Throwable;

/**
 * Factory methods classes.
 *
 * @method static Functions           functions(bool $shared = true)                             Utility function helper class.
 * @method static Session             session(?SessionManagerInterface $manager = null, bool $shared = true)                   Server-side user session class.
 * @method static Cookie              cookie(string $name, mixed $value = '', array $options = [], bool $shared = true)                    Client-side cookie class
 * @method static Task                task(bool $shared = true)                      Time task utility class.
 * @method static Modules             modules(bool $shared = true)                               PSR-4 Modluel autloader and file importer class.
 * @method static Translator          language(?string $locale = null, bool $shared = true)      Application translation class.
 * @method static Logger              logger(bool $shared = true)                                PSR logger class.
 * @method static FileManager         fileManager(bool $shared = true)                           File manager class.
 * @method static InputValidator      validate(bool $shared = true)                              Input valdidation class.
 * @method static ViewResponse        response(int $status = 200, bool $shared = true)           Render response class.
 * @method static Request             request(bool $shared = true)                               HTTP Request class.
 * @method static Network             network(?HttpClientInterface $client = null, bool $shared = true)                               HTTP Network request class.
 * @method static Caller              caller(bool $shared = true)                                Class caller class.
*/

//@method static someClass get_by_user_id(int $id) Bla-bla
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
     * @var array<string,class-string> $classes
     */
    private static array $classes = [
        'task'          => Task::class,
        'session'       => Session::class,
        'cookie'        => Cookie::class,
        'functions'     => Functions::class,
        'modules'       => Modules::class,
        'language'      => Translator::class,
        'logger'        => Logger::class,
        'fileManager'   => FileManager::class,
        'validate'      => InputValidator::class,
        'response'      => ViewResponse::class,
        'request'       => Request::class,
        'network'       => Network::class,
        'caller'        => Caller::class
    ];

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $factory The factory class name.
     * @param array $arguments Arguments to pass to the factory constructor.
     * @param bool $shared The last parameter to pass to the factory constructor indicate if it should return a shared instance.
     * 
     * @example Factory::method('foo', 'bar', false)
     * @example Factory::method(false)
     * 
     * @return class-object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     * 
     * @ignore 
     */
    public static function __callStatic(string $factory, array $arguments): ?object
    {
        return static::call($factory, $arguments);
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $factory The factory class name.
     * @param array $arguments Arguments to pass to the factory constructor.
     * @param bool $shared The last parameter to pass to the factory constructor indicate if it should return a shared instance.
     * 
     * @example Factory::method('foo', 'bar', false)
     * @example Factory::method(false)
     * 
     * @return class-object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     * 
     * @ignore 
     */
    public function __call(string $factory, array $arguments): ?object
    {
        return static::call($factory, $arguments);
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
        return static::locator($factory) !== null;
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
        if(isset(static::$instances[$factory])){
            unset(static::$instances[$factory]);

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
        static::$instances = [];
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
        if(isset(static::$instances['service'])){
            return static::$instances['service'];
        }
   
        static::$instances['service'] = new Services();
        return static::$instances['service'];
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

            if($boot === null){
                $boot = new BootServices();
            }
            
            $boot->bootstrap();
            $instance = static::service();
            $instance->queuService($boot->getServices());
            static::$instances['service'] = $instance;
        }catch(RuntimeException $e){
            logger('critical', 'Error occurred while registering service, Exception: ' . $e->getMessage());
        }
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param string $factory The factory class name.
     * @param array $arguments Argument to pass to the factory constructor.
     * 
     * @return class-object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function call(string $factory, array $arguments): ?object
    {
        $shared = true; 
        
        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        if ($shared && isset(static::$instances[$factory])) {
            return static::$instances[$factory];
        }

        $class = static::locator($factory);

        if ($class === null) {
            throw new RuntimeException("Factory with method name '$factory' does not exist.");
        }

        return static::create($class, $factory, $shared, ...$arguments);
    }

    /**
     * Create an instance of the specified factory class.
     *
     * @param class-string $class The class name.
     * @param string|null $alias The alias of the factory class.
     * @param bool $shared Whether the instance should be shared or not.
     * @param mixed ...$arguments Parameters to pass to the factory constructor.
     * 
     * @return class-object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function create(string $class, ?string $alias = null, bool $shared = true, ...$arguments): ?object
    {
        $instance = null;
        try {
            $instance = new $class(...$arguments);

            if ($shared && $alias) {
                static::$instances[$alias] = $instance;
            }
        } catch (Throwable|Exception $e) {
            throw new RuntimeException("Failed to instantiate factory method '$alias', " . $e->getMessage(), $e->getCode(), $e);
        }

        return $instance;
    }

    /**
     * Get the fully qualified class name of the factory based on the provided context.
     *
     * @param string $factory The factory class name.
     * 
     * @return class=string Return the fully qualified class name.
    */
    private static function locator(string $factory): string
    {
        return static::$classes[$factory] ?? null;
    }
}