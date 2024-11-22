<?php
/**
 * Luminova Framework event trigger.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Luminova\Exceptions\InvalidArgumentException;

final class Event
{
    /**
     * Static container for event callbacks.
     * 
     * @var array $events
     */
    protected static array $events = [];

    /**
     * Static event object.
     * 
     * @var self $instance
     */
    private static ?self $instance = null;

    /**
     * Initialize a new event class.
     */
    public function __construct(){}

    /**
     * Retrieve event singleton instance.
     * 
     * @return self Return a new static event object.
     */
    public static function getInstance(): self 
    {
        return self::$instance ??= new self();
    }

    /**
     * Trigger a named event and execute callbacks that have been hooked onto it.
     * If hooks are defined, only the hooks will be triggered.
     * 
     * @param string $name The event name to trigger.
     * @param array $arguments Arguments to pass to each callback.
     * @param string|null $hook Optional hook name to trigger specific hooks.
     * 
     * @return int Return the number of callbacks triggered.
     * @throws InvalidArgumentException Throws if an invalid event name was passed.
     */
    public static function emit(string $name, array $arguments = [], ?string $hook = null): int
    {
        self::assertValidity($name, false, false);
        $emitted = 0;
        $event = self::$events[$name] ?? [];

        if ($event === []) {
            return 0;
        }

        if ($hook !== null) {
            $eHook = $event[$hook] ?? null;
            if($eHook === null){
                return 0;
            }

            $eHook(...$arguments);
            return 1;
        }

        foreach ($event as $callback) {
            $callback(...$arguments);
            $emitted++;
        }

        return $emitted;
    }

    /**
     * Add a callback to be triggered on a specific event.
     * 
     * @param string $name The event name to bind.
     * @param string $hook A unique identifier for the callback.
     * @param callable $callback The function to invoke when the event is triggered.
     * 
     * @return void
     * @throws InvalidArgumentException Throws if an invalid event name, hoot or callback was passed.
     */
    public static function on(string $name, string $hook, callable $callback): void
    {
        self::assertValidity($name, $hook, $callback);
        self::$events[$name][$hook] = $callback;
    }

    /**
     * Remove a callback from an event, or all callbacks if no hook is specified.
     * 
     * @param string $name The event name to unbind.
     * @param string|null $hook Optional. The unique identifier of the callback to remove.
     * 
     * @return bool Return true if successful, false if the event or callback does not exist.
     * @throws InvalidArgumentException Throws if an invalid event name was passed.
     */
    public static function off(string $name, ?string $hook = null): bool
    {
        self::assertValidity($name, false, false);
        if ($hook === null) {
            if (isset(self::$events[$name])) {
                unset(self::$events[$name]);
                return true;
            }
            return false;
        }

        if (isset(self::$events[$name][$hook])) {
            unset(self::$events[$name][$hook]);
            return true;
        }

        return false;
    }

    /**
     * Retrieve all event callbacks, or callbacks for a specific event.
     * 
     * @param string|null $name Optional. The event name to filter by.
     * 
     * @return array Return the list of event callbacks.
     */
    public static function events(?string $name = null): array
    {
        return $name ? (self::$events[$name] ?? []) : self::$events;
    }

    /**
     * Clear all events and associated callbacks.
     * 
     * @return void
     */
    public static function detach(): void
    {
        self::$events = [];
    }

   /**
     * Asserts the validity of event name, hook, and callback.
     * 
     * @param string|false $name The event name to validate. Must be a non-empty string if provided.
     * @param string|false $hook The hook identifier to validate. Must be a non-empty string if provided.
     * @param callable|false $callback The callback function to validate. Must be callable if provided.
     * 
     * @return void
     * @throws InvalidArgumentException Throws if any parameter is invalid.
     */
    private static function assertValidity(
        string|bool $name, 
        string|bool $hook, 
        callable|bool $callback
    ): void 
    {
        if ($name !== false && $name === '') {
            throw new InvalidArgumentException('The event name cannot be an empty string. Please provide a valid event name.');
        }

        if ($hook !== false && $hook === '') {
            throw new InvalidArgumentException('The hook identifier cannot be an empty string. Please provide a valid hook.');
        }

        if ($callback !== false && !is_callable($callback)) {
            throw new InvalidArgumentException('The callback provided is not callable. Please ensure the callback is a valid function or method.');
        }
    }
}