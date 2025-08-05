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
namespace Luminova\Template\Engines;

use \Throwable;
use \Luminova\Template\View;
use \Luminova\Foundation\Core\Application;
use \Luminova\Exceptions\BadMethodCallException;

/**
 * Simulates a language keyword for isolated template rendering.
 *
 * Grants scoped access to the application object, exported services, and view options,
 * while preventing direct access to `$this` inside templates.
 * 
 * @property \App\Application<\Luminova\Foundation\Core\Application>|null $app
 * 
 * @since 3.6.8
 * @example Usage in templates:
 * ```php
 * // Application object
 * $self->app;
 * 
 * // Exported services
 * $self->session->get();
 * 
 * // Options (prefixed with "_")
 * $self->_someOption;
 * $self->_title;
 * $self->_active;
 * 
 * // View methods
 * $self->link('about'); 
 * // OR $self->view->link('about');
 * ```
 */
final class Scope
{
    /**
     * Template view instance (for isolated templates to access via `$self->view`).
     * 
     * @var View|null $view
     */
    public static ?View $view = null;

    /**
     * Application instance (for isolated templates to access via `$self->app`).
     * 
     * @var \App\Application<Application>|null $app
     */
    public static ?Application $app = null;

    /**
     * The signed object ID.
     * 
     * @var int @id
     */
    private int $id = 0;

    /**
     * Creates a new Scope wrapper for template isolation.
     *
     * @param View   $view The current view instance.
     * @param string $keyword The alias name to conceptually replace `$this` in templates.
     */
    public function __construct(View $view, private string $keyword = '$self')
    {
        self::$app = $view->app;

        // Detach app from view for isolation mode
        self::$view = clone $view;
        self::$view->isIsolationObject = true;
        $this->id = spl_object_id($this);
    }

    /**
     * Check if self id has changed since initialized.
     * 
     * @param int $id The object id.
     * 
     * @return bool Return true if not changed, otherwise false.
     * @internal
     */
    public function __is(int $id): bool 
    { 
        return $this->id === $id; 
    }

    /**
     * Get the object id.
     * 
     * @return int Return the object id. 
     * @internal
     */
    public function __id():int 
    { 
        return $this->id; 
    }

    /**
     * Handles dynamic method calls on the scope object.
     *
     * Attempts to invoke an exported method from the view's registry.
     * If not found, and the method exists on the View object, it is called there.
     *
     * @param string $method    The method name.
     * @param array  $arguments The arguments to pass.
     *
     * @return mixed The method return value.
     *
     * @throws BadMethodCallException|Throwable If the method cannot be resolved.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return self::resolve($method, $arguments);
    }

    /**
     * Handles static calls on the scope object.
     *
     * Works the same as __call(), but forces the call to be treated as static.
     *
     * @param string $method    The method name.
     * @param array  $arguments The arguments to pass.
     *
     * @return mixed The method return value.
     *
     * @throws BadMethodCallException|Throwable If the method cannot be resolved.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::resolve($method, $arguments, true);
    }

     /**
     * Handle dynamic setting of template options within or outside the view.
     *
     * @param string $name The option name.
     * @param array  $value The value to be assigned name.
     *
     * @return void
     * @ignore
     */
    public function __set(string $name, mixed $value): void 
    {
        self::$view->__set($name, $value);
    }

    /**
     * Retrieves a property from the view or an exported option.
     *
     * First checks the view's internal property registry.
     * If not found, logs a critical error and returns null.
     *
     * @param string $property The property name.
     *
     * @return mixed|null The property value, or null if undefined.
     */
    public function __get(string $property): mixed
    {
        if($property === 'view'){
            return self::$view;
        }

        if($property === 'app'){
            return self::$app;
        }

        $result = self::$view->getProperty($property, true, false);

        if($result === View::KEY_NOT_FOUND){
            return self::$view->__log($property);
        }

        return $result;
    }

     /**
     * When checking if property isset.
     * 
     * @param string $property The property as option name or export alias.
     * 
     * @return bool Return true if property isset.
     */
    public function __isset(string $property): bool
    {
        return self::$view->__isset($property);
    }

    /**
     * When unsetting a property.
     * 
     * @param string $property The property as option name or export alias.
     * 
     * @return void
     */
    public function __unset(string $property): void 
    {
        self::$view->__unset($property);
    }

    /**
     * Resolves and calls a method from either the exports registry or the View.
     *
     * @param string $method    The method name to call.
     * @param array  $arguments The arguments to pass.
     * @param bool   $isStatic  Whether the call should be treated as static.
     *
     * @return mixed The method result.
     *
     * @throws Throwable If the call fails and no fallback is available.
     */
    private static function resolve(string $method, array $arguments, bool $isStatic = false): mixed
    {
        try{
            return self::$view::__fromExport($method, $arguments, $isStatic);
        }catch(Throwable $e){
            if($e instanceof BadMethodCallException && method_exists(self::$view, $method)){
                return $isStatic 
                    ? self::$view::{$method}(...$arguments)
                    : self::$view->{$method}(...$arguments);
            }

            throw $e;
        }
    }
}