<?php
/**
 * Luminova Framework Array implementation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Arrays;

use \Countable;
use \Stringable;
use \Luminova\Exceptions\JsonException;

class Arrays implements Countable, Stringable
{
    /**
     * Constructor to initialize array instance.
     * 
     * @param array<string|int,mixed> $array The array to initialize.
     */
    public function __construct(private array $array = [])
    {
    }

    /**
     * Add or update an element in the array.
     * 
     * @param string|int $key The key for the element.
     * @param mixed $value The value to be added.
     * 
     * @return self Return the updated instance with the new element.
     */
    public function add(string|int $key, mixed $value): self
    {
        $this->array[$key] = $value;
        return $this;
    }

    /**
     * Remove an array key if it exist.
     * 
     * @param string|int $key The array key to remove.
     * 
     * @return bool Return true if array key was removed, false otherwise.
    */
    public function remove(string|int $key): bool
    {
        unset($this->array[$key]);
        return !$this->has($key);
    }

    /**
     * Clear all elements from the array.
     * 
     * @return bool Return true if the entire array was cleared, false otherwise.
    */
    public function clear(): bool
    {
        $this->array = [];
        return $this->isEmpty();
    }

    /**
     * Get an element from the array based on the provided key.
     * 
     * @param string|int|null $key The key of the element to retrieve, or null to get the entire array (default: null).
     * @param mixed $default The default value if the array key was not found (default: null).
     * 
     * @return array|null Return the value for the specified key or default value, If key is null return the entire array.
     */
    public function get(string|int|null $key = null, mixed $default = null): ?array
    {
        return ($key === null) ? $this->array : ($this->array[$key] ?? $default);
    }

    /**
     * Extract a column from a multidimensional array.
     * 
     * @param string|int|null $property The column to extract.
     * @param string|int|null $index The index to use for extraction.
     * 
     * @return Arrays Return a new instance of Arrays containing the extracted column.
     */
    public function getColumn(string|int|null $property, string|int|null $index = null): Arrays
    {
        return new Arrays(array_column($this->array, $property, $index));
    }

    /**
     * Check if the current array is a nested array by containing array as value.
     * 
     * @param bool $recursive Whether to check deeply nested array values (default: false).
     * 
     * @return bool Return true if the array is nested, false otherwise.
     */
    public function isNested(bool $recursive = false): bool
    {
        return is_nested($this->array, $recursive);
    }

    /**
     * Check if the current array is associative, which mean it must use string a named keys.
     * 
     * @return bool Return true if the array is associative, false otherwise.
     */
    public function isAssoc(): bool
    {
        return is_associative($this->array);
    }
    
    /**
     * Check if the current array is a list, which means it must be indexed by consecutive integers keys.
     * 
     * @return bool Return true if the array is a list, false otherwise.
     */
    public function isList(): bool
    {
        return array_is_list($this->array);
    }

    /**
     * Check if array is empty.
     * 
     * @return bool Return true if the array empty, false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->array === [];
    }

    /**
     * Count the number of elements in the current array.
     * 
     * @return int Return the number of elements in the array.
     */
    public function count(): int
    {
        return count($this->array);
    }

    /**
     * Check if the array contains an element with the given key.
     * 
     * @param string|int $key The array key to check.
     * 
     * @return bool Return true if array key exists, false otherwise.
    */
    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->array);
    }

    /**
     * Sort the current array elements.
     * 
     * @param int $flags The array sort flags (default: SORT_REGULAR).
     * 
     * @return self Return the updated instance with the sorted element.
     */
    public function sort(int $flags = SORT_REGULAR): self
    {
        sort($this->array, $flags);
        return $this;
    }

    /**
     * Merge another array with the current array.
     * 
     * @param array<string|int,mixed> $array The array to merge with the current array.
     * 
     * @return self Return the updated instance with the merged array.
     */
    public function merge(array $array): self
    {
        $this->array = array_merge($this->array, $array);
        return $this;
    }

    /**
     * Iterates over each value in the array passing them to the callback function to filter.
     * 
     * @param callable|null $callback The filter callback function (default: null).
     * @param int $mode The array filter mode to use (default: 0).
     * 
     * @return Arrays Return a new instance of Arrays containing the filters.
     */
    public function filter(?callable $callback = null, int $mode = 0): Arrays
    {
        return new Arrays(array_filter($this->array, $callback, $mode));
    }

    /**
     * Applies the callback to the elements of the given arrays.
     * 
     * @param callable $callback The filter callback function (default: null).
     * @param array ...$arrays The array filter mode to use (default: 0).
     * 
     * @return Arrays Return a new instance of Arrays containing the elements after applying callback.
     */
    public function map(callable $callback, array ...$arrays): Arrays
    {
        return new Arrays(array_map($callback, $this->array, ...$arrays));
    }

    /**
     * Iteratively reduce the array to a single value using a callback function.
     * 
     * @param callable $callback The reduce callback function (default: null).
     * @param mixed $initial If the optional initial is available, 
     *                  it will be used at the beginning of the process, 
     *                  or as a final result in case the array is empty.
     * 
     * @return mixed Return the result of array reduce.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->array, $callback, $initial);
    }

    /**
     * Update the current array with elements in reverse order.
     * 
     * @param bool $preserve_keys Weather to preserve the array key (default: false).
     * 
     * @return self Return the updated instance with the reversed array.
     */
    public function reverse(bool $preserve_keys = false): self
    {
        $this->array = array_reverse($this->array, $preserve_keys);
        return $this;
    }

    /**
     * Return new array instance by splitting the array into chunks.
     * 
     * @param int $size The split chunk size.
     * @param bool $preserve_keys Weather to the keys should be preserved or reindex the chunk numerically (default: false).
     * 
     * @return Arrays Return a new instance of Arrays containing the chunked array.
     */
    public function chunk(int $size, bool $preserve_keys = false): Arrays
    {
        return new Arrays(array_chunk($this->array, $size, $preserve_keys));
    }

    /**
     * Return new array instance containing a slice of current array.
     * 
     * @param int $offset The array slice offset.
     * @param int|null $length The array slice length (default: null).
     *                  - If length is given and is positive, then the sequence will have that many elements in it. 
     *                  - If length is given and is negative then the sequence will stop that many elements from the end of the array. 
     *                  - If it is omitted, then the sequence will have everything from offset up until the end of the array.
     * @param bool $preserve_keys Weather not to reorder and reset the array indices (default: false).
     * 
     * @return Arrays Return a new instance of Arrays containing the slice of array.
     */
    public function slice(int $offset, ?int $length = null, bool $preserve_keys = false): Arrays
    {
        return new Arrays(array_slice($this->array, $offset, $length, $preserve_keys));
    }

    /**
     * Extract all the keys or a subset of the keys in the current array.
     * 
     * @param mixed $filter_value If specified, then only keys containing these values are returned.
     * @param bool $strict Determines if strict comparison (===) should be used during the search (default: false).
     * 
     * @return array Return list of array keys in the current array.
     */
    public function keys(mixed $filter_value, bool $strict = false): array
    {
        return array_keys($this->array, $filter_value, $strict);
    }

    /**
     * Extract all the values in the current array.
     * 
     * @return array Return list of array values in the current array.
     */
    public function values(): array
    {
        return array_values($this->array);
    }

    /**
     * Convert the current array to to json string representation.
     * 
     * @return string Return json string of the current array or blank string if error occurred.
    */
    public function __toString(): string 
    {
        return $this->toString(false);
    }

    /**
     * Convert the current array to to json string representation.
     * 
     * @param bool $throw Weather throw json exception if error occurs (default: true).
     * 
     * @return string Return json string of the current array, if throw is false return null on error.
     * @throws JsonException Throws if unable to convert to json string.
    */
    public function toString(bool $throw = true): string 
    {
        $json = $this->toJson(0, 512, $throw);
        return ($json === null) ? '' : $json;
    }

    /**
     * Convert the current array to json string representation.
     * 
     * @param int $flags The json encoding flags using bitwise OR (|) operators to combine multiple flags.
     * @param int $depth Set the maximum depth, must be greater than zero (default: 512).
     * @param bool $throw Weather throw json exception if error occurs (default: true).
     * 
     * @return string|null Return json string of the current array, if throw is false return null on error.
     * @throws JsonException Throws if unable to convert to json string.
     */
    public function toJson(int $flags = 0, int $depth = 512, bool $throw = true): ?string
    {
        try{
            return json_encode($this->array, $flags | JSON_THROW_ON_ERROR, $depth);
        }catch(\JsonException $e){
            if(!$throw){
                return null;
            }

            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Convert the current array to json object representation.
     * 
     * @param bool|null $assoc Weather to convert to an associative array (default: true).
     * @param int $flags The json encoding flags using bitwise OR (|) operators to combine multiple flags.
     * @param int $depth Set the maximum depth, must be greater than zero (default: 512).
     * @param bool $throw Weather throw json exception if error occurs (default: true).
     * 
     * @return object|null Return json object of the current array, if throw is false return null on error.
     * @throws JsonException Throws if unable to convert to json string or convert to object.
     */
    public function toObject(
        bool|null $assoc = true, 
        int $flags = 0, 
        int $depth = 512,
        bool $throw = true
    ): ?object
    {
        $json = $this->toJson(0, 512, $throw);
        if($json === null){
            return null;
        }

        try{
            return json_decode($json, $assoc, $flags | JSON_THROW_ON_ERROR, $depth);
        }catch(\JsonException $e){
            if(!$throw){
                return null;
            }

            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Update the current array from a json string.
     * 
     * @param string $json The json string to update from.
     * @param bool|null $assoc Weather to convert to an associative array (default: true).
     * @param int $depth Set the maximum recursion depth, must be greater than zero (default: 512).
     * @param int $flags The json decoding flags using bitwise OR (|) operators to combine multiple flags.
     * 
     * @return self Return the updated instance with the new array from json string.
     * @throws JsonException Throws if unable to convert to json string.
     */
    public function fromJson(
        string $json, 
        bool|null $assoc = true, 
        int $depth = 512, 
        int $flags = 0
    ): self
    {
        try{
            $this->array = json_decode($json, $assoc, $flags | JSON_THROW_ON_ERROR, $depth);
        }catch(\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }   
}
