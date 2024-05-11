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
use \Luminova\Sessions\Session;
use \Luminova\Cookies\Cookie;
use \Luminova\Library\Modules;
use \Luminova\Languages\Translator;
use \Luminova\Storages\FileManager;
use \Luminova\Http\Request;
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
 * @method static Functions           functions(bool $shared = true)                        @return Functions
 * @method static Session             session(...$params, bool $shared = true)              @return Session
 * @method static Cookie              cookie(...$params, bool $shared = true)               @return Cookie
 * @method static Task                task(...$params, bool $shared = true)                 @return Task
 * @method static Modules             import(...$params, bool $shared = true)               @return Modules
 * @method static Translator          language($locale, bool $shared = true)                @return Translator
 * @method static Logger              logger(string $extension = '.log', $shared = true)    @return Logger
 * @method static FileManager          files($shared = true)                                @return FileManager
 * @method static InputValidator      validate($shared = true)                              @return InputValidator
 * @method static ViewResponse        response(int $status, $shared = true)                 @return ViewResponse
 * @method static Request             request($shared = true)                               @return Request
*/
class Factory 
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
        'task'       => Task::class,
        'session'    => Session::class,
        'cookie'     => Cookie::class,
        'functions'  => Functions::class,
        'modules'    => Modules::class,
        'language'   => Translator::class,
        'logger'     => Logger::class,
        'files'      => FileManager::class,
        'validate'   => InputValidator::class,
        'response'   => ViewResponse::class,
        'request'    => Request::class
    ];

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param class-string|string $factory The class name or class name alias.
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
     * @param class-string|string $factory The class name or class name alias.
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
     * @param class-string|string $alias The class name or alias.
     * 
     * @return bool Return true if class is available in factories, false otherwise.
     */
    public static function has(string $alias): bool
    {
        return static::locator($alias)[0] !== null;
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
        if(isset(static::$instances['services'])){
            return static::$instances['services'];
        }
   
        static::$instances['services'] = new Services();
        return static::$instances['services'];
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
            static::$instances['services'] = $instance;
        }catch(RuntimeException $e){
            logger('critical', 'Error occurred while registering service, Exception: ' . $e->getMessage());
        }
    }

    /**
     * Dynamically create an instance of the specified factory method class.
     *
     * @param class-string|string $factory The class name or class name alias.
     * @param array $arguments Argument to pass to the factory constructor.
     * 
     * @return class-object|null An instance of the factory class, or null if not found.
     * @throws RuntimeException If failed to instantiate the factory.
     */
    private static function call(string $factory, array $arguments): ?object
    {
        $shared = true; 
        [$class, $alias] = static::locator($factory);

        if ($class === null) {
            throw new RuntimeException("Factory with method name '$factory' does not exist.");
        }

        if (isset($arguments[count($arguments) - 1]) && is_bool($arguments[count($arguments) - 1])) {
            $shared = array_pop($arguments);
        }

        if ($shared && isset(static::$instances[$alias])) {
            return static::$instances[$alias];
        }

        return static::create($class, $alias, $shared, ...$arguments);
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
            throw new RuntimeException("Failed to instantiate factory method '$class', " . $e->getMessage(), $e->getCode(), $e);
        }

        return $instance;
    }

    /**
     * Get the fully qualified class name of the factory based on the provided context.
     *
     * @param class-string|string $class The class name or alias.
     * 
     * @return array<int,mixed> Return the fully qualified class name and alias.
    */
    private static function locator(string $class): array
    {
        $class = strtolower(get_class_name($class) ?? '');
        return [static::$classes[$class] ?? null, $class];
    }
}