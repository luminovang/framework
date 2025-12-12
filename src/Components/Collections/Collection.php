<?php
/**
 * Luminova Framework abstract model.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Collections;

use \Countable;
use \Traversable;
use \IteratorAggregate;

/**
 * Represents a collection of model instances or generic objects.
 *
 * Provides common collection operations such as filtering, mapping,
 * searching, extracting values, and iteration.
 *
 * @template T of mixed
 *
 * @implements IteratorAggregate<int, T>
 */
class Collection implements Countable, IteratorAggregate
{
    /**
     * Creates a new model collection instance.
     *
     * @param array<int, T> $items The collection items.
     *
     * @example
     * new ModelCollection([$user, $profile]);
     */
    public function __construct(protected array $items = []) {}

    /**
     * Retrieves all collection keys.
     *
     * @return array<int|string>
     */
    public function keys(): array
    {
        return array_keys($this->items);
    }

    /**
     * Finds the first item matching a callback condition.
     *
     * @param callable(T): bool $callback
     *
     * @return T|null
     */
    public function scan(callable $callback): mixed
    {
        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Executes a callback for every item in the collection.
     *
     * @param callable(T, int|string): mixed $callback
     *
     * @return static
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * Determines whether the model contains the specified attribute.
     *
     * Checks if an attribute value exists in the model's internal attribute
     * collection.
     *
     * @param string $name Attribute name to check.
     *
     * @return bool Returns `true` if the attribute exists, otherwise `false`.
     */
    public function has(int|string $name): bool
    {
        return array_key_exists($name, $this->items);
    }

    /**
     * Retrieves an item by its numeric position.
     *
     * Supports negative positions to retrieve items from the end
     * of the collection.
     *
     * @param int $position The zero-based item position.
     *
     * @return T|null The item at the given position or null if unavailable.
     *
     * @example
     * $users->at(0);  // First user
     * $users->at(-1); // Last user
     */
    public function at(int $position): mixed
    {
        $count = count($this->items);

        if ($position < 0) {
            $position += $count;
        }

        return $this->items[$position] ?? null;
    }

    /**
     * Retrieves an item by its numeric position.
     *
     * This method is an alias of {@see at()}.
     *
     * @param int $position The zero-based item position.
     *
     * @return T|null
     */
    public function nth(int $position = 0): mixed
    {
        return $this->at($position);
    }

    /**
     * Retrieves the first item in the collection.
     *
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->at(0);
    }

    /**
     * Retrieves a random item from the collection.
     *
     * @return T|null A random item or null when the collection is empty.
     */
    public function random(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

        return $this->items[array_rand($this->items)];
    }

    /**
     * Applies a callback to each collection item and returns a new collection.
     *
     * @param callable(T): mixed $callback
     *
     * @return static
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Filters collection items using a callback.
     *
     * When no callback is provided, items evaluated as false are removed.
     *
     * @param callable(T): bool|null $callback
     *
     * @return static
     *
     * @example
     * $active = $users->filter(
     *     fn(User $user) => $user->active
     * );
     */
    public function filter(?callable $callback = null): static
    {
        return new static(array_values(array_filter(
            $this->items,
            $callback
        )));
    }

    /**
     * Extracts a property value from each item.
     *
     * Supports both arrays and objects.
     *
     * @param string $key The property or array key name.
     *
     * @return array<int, mixed>
     *
     * @example
     * $emails = $users->pluck('email');
     */
    public function pluck(string $key): array
    {
        return array_map(
            static fn($item) => is_array($item)
                ? ($item[$key] ?? null)
                : ($item->{$key} ?? null),
            $this->items
        );
    }

    /**
     * Determines whether the collection contains a value or matching item.
     *
     * A callable can be provided to perform custom matching logic.
     *
     * @param mixed|callable(T): bool $value
     *
     * @return bool
     *
     * @example
     * $users->contains(fn(User $user) => $user->id === 10);
     */
    public function contains(mixed $value): bool
    {
        if ($this->items === []) {
            return false;
        }

        if (is_callable($value)) {
            foreach ($this->items as $item) {
                if ($value($item)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($value, $this->items, true);
    }

    /**
     * Retrieves the last item in the collection.
     *
     * @return T|null
     */
    public function last(): mixed
    {
        return $this->at(-1);
    }

    /**
     * Returns a new collection with reset numeric keys.
     *
     * @return static
     */
    public function values(): static
    {
        return new static(array_values($this->items));
    }

    /**
     * Returns an iterator for collection items.
     *
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    /**
     * Returns the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Converts the collection into an array.
     *
     * @return array<int, T|mixed>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Converts the collection into an object.
     *
     * @return object
     */
    public function toObject(): object
    {
        return (object) $this->items;
    }

    /**
     * Determines whether the collection contains no items.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}