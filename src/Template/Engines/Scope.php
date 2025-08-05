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
use \Luminova\Exceptions\BadMethodCallException;

/**
 * Simulates a language keyword for isolated template rendering.
 *
 * Grants scoped access to the application object, exported services, and view options,
 * while preventing direct access to `$this` inside templates.
 * 
 * @mixin Luminova\Template\View
 * 
 * @category View
 * @property \Luminova\Foundation\Core\Application|null $app
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
     * The signed object ID.
     * 
     * @var int @id
     */
    private int $id = 0;

    /**
     * Creates a new Scope wrapper for template isolation.
     *
     * @param View $view The current view instance.
     * @param string $keyword The alias name to conceptually replace `$this` in templates.
     */
    public function __construct(private View $view, private string $keyword = '$self')
    {
        // Detach app from view for isolation mode
        $this->view->isIsolationObject = true;
        $this->id = spl_object_id($this);
        $view = null;
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
     * @param string $method The method name.
     * @param array $arguments The arguments to pass.
     *
     * @return mixed Return the method return value.
     *
     * @throws BadMethodCallException|Throwable If the method cannot be resolved.
     */
    public function __call(string $method, array $arguments): mixed
    {
        try{
            return $this->view->__fromExport($method, $arguments, true);
        }catch(Throwable $e){
            if(($e instanceof BadMethodCallException) && method_exists($this->view, $method)){
                return $this->view->{$method}(...$arguments);
            }

            throw $e;
        }
    }

    /**
     * Handle dynamic setting of template options within or outside the view.
     *
     * @param string $name The option name.
     * @param array $value The value to be assigned name.
     *
     * @return void
     * @ignore
     */
    public function __set(string $name, mixed $value): void 
    {
        $this->view->__set($name, $value);
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
            return $this->view;
        }

        if($property === 'layout'){
            return $this->view->layout;
        }

        if($property === 'app'){
            return $this->view->app;
        }

        $result = $this->view->getProperty($property, true, false);

        if($result === View::KEY_NOT_FOUND){
            return $this->view->__log($property);
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
        return $this->view->__isset($property);
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
        $this->view->__unset($property);
    }
}