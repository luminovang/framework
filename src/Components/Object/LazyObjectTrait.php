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
namespace Luminova\Components\Object;

use \Throwable;
use \Stringable;
use \ReflectionClass;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\LazyObjectInterface;
use \Luminova\Components\Object\Helpers\LazyDynamicTrait;

/**
 * @mixin LazyDynamicTrait
 */
trait LazyObjectTrait
{
    /** 
     * If lazy is active.
     * 
     * @var bool $isLazyGhostActive
     */
    private bool $isLazyGhostActive = false;

    /**
     * Lazy dynamic proxy helpers.
     */
    use LazyDynamicTrait;

    /**
     * Create a "lazy ghost" instance of this class.
     *
     * A ghost is an object created without running its constructor.  
     * This is useful if you need to fill private or protected properties manually
     * before the object is used.
     *
     * The initializer receives the raw ghost ($this) and optional arguments.  
     * It can:
     * - Mutate $this and return nothing, OR  
     * - Return a fully constructed instance.
     *
     * @param (callable(static $ghost, mixed ...$args): static|null|void) $initializer The class initializer.
     * @param (callable(): array)|null $arguments Optional callback providing init args.
     * 
     * @return static Return unconstructed ghost instance.
     * @throws RuntimeException if error while invoking class method.
     * @see hydrateLazyGhost() - To manually trigger hydration of a lazy ghost object.
     *
     * @example - Example:
     * ```php
     * $ghost = MyClass::newLazyGhost(function (MyClass $obj, string $name) {
     *     return new MyClass('Alice');
     * });
     *
     * echo $ghost->getName(); // Alice
     * ```
     * 
     * @example - Example:
     * ```php
     * $ghost = MyClass::newLazyGhost(function (MyClass $obj, string $name) {
     *     $obj->name = $name; // set private data
     * });
     *
     * $ghost->hydrateLazyGhost(['Alice']); // manually hydrate before use
     * echo $ghost->getName(); // Alice
     * ```
     */
    public static final function newLazyGhost(callable $initializer, ?callable $arguments = null): static
    {
        $ref = new ReflectionClass(static::class);

        /** @var static $obj */
        $obj = $ref->newInstanceWithoutConstructor();
        $obj->lazyInitializer = $initializer;
        $obj->lazyClassNamespace = null; //static::class;
        $obj->isLazyGhostActive = true;
        $obj->lazyArguments = $arguments;
        return $obj;
    }

    /**
     * Manually trigger hydration of a lazy ghost object.
     *
     * If you plan to call a public method immediately, call this first to ensure
     * the ghost has been initialized. If the initializer returns an object, it is stored;
     * otherwise we assume $this was mutated in place.
     *
     * @param array|null $arguments Optional arguments to override lazy initialization arguments.
     * 
     * @return void 
     * @throws RuntimeException if error while initializing class.
     * 
     * @see newLazyGhost() - To create lazy initializer.
     * @example - Example:
     * ```php
     * $ghost = MyClass::newLazyGhost(fn(MyClass $obj): void => $obj->age = 42);
     * $ghost->hydrateLazyGhost(); // runs initializer
     * echo $ghost->getAge(); // 42
     * ```
     */
    public final function hydrateLazyGhost(?array $arguments = null): void
    {
        $this->onHydrateLazyObject(__FUNCTION__, arguments: $arguments);
    }

    /**
     * Create a "lazy proxy" instance of this class.
     *
     * A proxy defers real object creation until the first time you access it.  
     * Unlike ghosts, proxies require the initializer to return a *fully constructed object*.  
     * This wrapper only exposes the public API of your class.
     *
     * @param (callable(static $proxy, mixed ...$args): static) $initializer Must return an instance
     * @param (callable(): array)|null $arguments Optional callback providing initialization arguments.
     * 
     * @return LazyObjectInterface<static> Proxy object that initializes on first use
     * @throws RuntimeException if error while invoking class method.
     *
     * @example - Example:
     * ```php
     * $proxy = MyClass::newLazyProxy(fn(): MyClass => new MyClass('data'));
     * echo $proxy->getData(); // object is created on first call
     * ```
     * 
     * > **Note:** initializer must return instance of class.
     */
    public static final function newLazyProxy(callable $initializer, ?callable $arguments = null): object
    {
        return new class(static::class, $initializer, $arguments) implements LazyObjectInterface, Stringable
        {
            /**
             * Lazy dynamic proxy helpers.
             */
            use LazyDynamicTrait;

            /** 
             * @param string $className
             * @param callable(static $proxy, mixed ...$args) $initializer
             * @param (callable(): array)|null $arguments
             */
            public function __construct(
                private string $className,
                private mixed $initializer, 
                private mixed $arguments = null
            ){
                $this->lazyClassNamespace = $className;
                $this->lazyInitializer = $initializer;
                $this->lazyArguments = $arguments;
            }

            /**
             * Hydrates (instantiates) the lazy-loaded object on demand.
             *
             * @param string|null $fn
             * @param string|null $assert
             * @param array|null $arguments
             *
             * @throws RuntimeException If instantiation fails or the initializer throws any non-AppException error.
             */
            private function onHydrateLazyObject(
                ?string $fn = null, 
                ?string $assert = null, 
                ?array $arguments = null
            ): void
            {
                if ($this->lazyObject instanceof $this->lazyClassNamespace) {
                    $this->assertLazyImplements($assert);
                    $this->lazyInitializer = null;
                    $this->lazyArguments   = null;
                    return;
                }

                if (!$this->lazyInitializer || !is_callable($this->lazyInitializer)) {
                    throw new RuntimeException('LazyProxy: invalid initializer');
                }

                try {
                    $this->lazyObject = ($this->lazyInitializer)(...$this->getLazyArguments(true));
                } catch (Throwable $e) {
                    if($e instanceof AppException){
                        throw $e;
                    }
                    
                    throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                }

                if (!($this->lazyObject instanceof $this->lazyClassNamespace)) {
                    throw new RuntimeException(
                        "Initializer for LazyProxy did not return an instance of {$this->lazyClassNamespace}"
                    );
                }

                $this->assertLazyImplements($assert);
                $this->lazyInitializer = null;
                $this->lazyArguments = null;
            }
        };
    }

    /**
     * Hydrates (instantiates) the lazy-loaded object on demand.
     *
     * This method forces creation of the underlying object if it hasn't been 
     * instantiated yet. It executes the stored initializer callback or, if a 
     * class namespace is set, constructs the object directly. After hydration, 
     * it optionally verifies that the object implements or extends a required type.
     *
     * @param string|null $fn Optional method name used for error reporting or context.
     * @param string|null $assert Optional class or interface name to validate against.
     * @param array|null $arguments Optional arguments to pass to the initializer (overrides defaults).
     *
     * @throws RuntimeException If instantiation fails or the initializer throws any non-AppException error.
     */
    private function onHydrateLazyObject(
        ?string $fn = null, 
        ?string $assert = null, 
        ?array $arguments = null
    ): void
    {
        if(!$this->isLazyGhostActive){
            return;

            // throw new LogicException(sprintf(
            //    'Cannot call %s on %s before creating it with %s::newLazyGhost(...).',
            //    $fn ?  static::class . "::{$fn}()" : __METHOD__,
            //    static::class,
            //    static::class
            // ));
        }

        if ($this->lazyObject instanceof static || $this->lazyInitializer === null) {
            $this->assertLazyImplements($assert);
            return;
        }

        try {
            $arguments ??= $this->getLazyArguments();

            $this->__construct(...$arguments);
            $object = ($this->lazyInitializer)($this);
            
        } catch (Throwable $e) {
            if($e instanceof AppException){
                throw $e;
            }
            
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $this->lazyObject = ($object instanceof static) ? $object : $this;
        
        $this->assertLazyImplements($assert);
        $this->lazyInitializer = null;
        $this->lazyArguments   = null;
    }
}