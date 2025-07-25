<?php
/**
 * Luminova Framework Array implementation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Arrays;

use \Luminova\Arrays\Listify;
use \Luminova\interface\LazyInterface;
use \Luminova\Exceptions\JsonException;
use \Luminova\Exceptions\RuntimeException;
use \Countable;
use \Stringable;
use \ArrayIterator;
use function \Luminova\Funcs\{
    is_nested,
    is_associative,
    array_merge_recursive_distinct
};

class ArrayUtil implements LazyInterface, Countable, Stringable
{
    /**
     * Constructor to initialize array instance.
     * If no array is provided, an empty array will be initialized.
     * 
     * @param array<string|int,mixed> $array The array to initialize.
     */
    public function __construct(private array $array = []){}

    /**
     * Create or Update the current array from a json string.
     * 
     * @param string $json The json string to update from.
     * @param bool|null $assoc Whether to convert to an associative array (default: true).
     * @param int $depth Set the maximum recursion depth, must be greater than zero (default: 512).
     * @param int $flags The json decoding flags using bitwise OR (|) operators to combine multiple flags.
     * 
     * @return self Return the updated instance with the new array from json string.
     * @throws JsonException Throws if unable to convert json string to array.
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
    
     /**
     * Create or Update the current array from a string list.
     * 
     * @param string $list The string list to update or create from.
     * 
     * @return self Return the updated instance with the new array from string list.
     * @throws RuntimeException Throws if unable to convert string list to array.
     * 
     * @example - Examples of a string list:
     * 
     * ```php
     * $arr->fromList('a,b,c'); // ['a', 'b', 'c'].
     * $arr->fromList('"a","b","c"'); // ['a', 'b', 'c'].
     * ```
     */
    public function fromStringList(string $list): self
    {
        $this->array = Listify::toArray($list);

        return $this;
    } 

    /**
     * Check if the current array is a nested array by containing array as value.
     * 
     * @param bool $recursive Whether to check deeply nested array values (default: false).
     * @param bool $strict Whether to require all values to be arrays (default: false).
     * 
     * @return bool Return true if the array is nested, false otherwise.
     */
    public function isNested(bool $recursive = false, bool $strict = false): bool
    {
        return is_nested($this->array, $recursive, $strict);
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
     * Get an element from the array based on the provided key.
     * 
     * @param string|int|null $key The key of the element to retrieve, or null to get the entire array (default: null).
     * @param mixed $default The default value if the array key was not found (default: null).
     * 
     * @return mixed Return the value for the specified key or default value, If key is null return the entire array.
     */
    public function get(
        string|int|null $key = null, 
        mixed $default = null
    ): mixed
    {
        return ($key === null) ? $this->array : ($this->array[$key] ?? $default);
    }

    /**
     * Retrieve the modified array after processing.
     *
     * This method returns the internally stored array, which may have been modified
     * by other operations such as merging, filtering, or reordering.
     *
     * @return array Return the processed array.
     */
    public function getArray(): array
    {
        return $this->array;
    }

    /**
     * Retrieves a nested value from the current array using a dot notation for nested keys.
     *
     * @param string $notations The dot notation path to the value.
     * @param mixed $default The default value to return if the path is not found. Defaults to null.
     * 
     * @return mixed Return the value for the specified notations, or the default value if not found.
     */
    public function getNested(string $notations, mixed $default = null): mixed
    {
        $keys = explode('.', $notations);
        $current = $this->array;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return $default;
            }
        }

        return $current;
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
     * Adds a value to a nested array using dot notation.
     *
     * @param string $notations The dot notation path to the nested key.
     * @param mixed $value The value to set at the nested key.
     * 
     * @return self Return the updated instance with the new element.
     */
    public function addNested(string $notations, mixed $value): self
    {
        $keys = explode('.', $notations);
        $current = &$this->array; 

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }

            $current = &$current[$key];
        }

        $current = $value;

        return $this;
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
     * Compares the current array with one or more arrays and returns the difference.
     * The values from the current array that are not present in any of the passed arrays.
     * 
     * @param ArrayUtil|array ...$arrays One or more arrays or ArrayUtil instances to compare.
     * 
     * @return array Returns an array containing all the entries from the current array that are not present in any of the other arrays.
     */
    public function diff(ArrayUtil|array ...$arrays): array
    {
        $compares = [];

        foreach ($arrays as $array) {
            $compares[] = ($array instanceof ArrayUtil) ? $array->get() : $array;
        }

        return array_diff($this->array, ...$compares);
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
     * Extract all the keys or a subset of the keys in the current array.
     * 
     * @param mixed $filter If filter value is specified, then only keys containing these values are returned.
     * @param bool $strict Determines if strict comparison (===) should be used during the search (default: false).
     * 
     * @return array Return list of array keys in the current array.
     */
    public function keys(mixed $filter, bool $strict = false): array
    {
        return array_keys($this->array, $filter, $strict);
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
     * Return array iterator object for the current array.
     * 
     * @param int $flags Optional flags to control the behavior of the ArrayObject object (default: 0).
     * 
     * @return ArrayIterator Return a new instance of array iterator containing the current array elements.
     */
    public function iterator(int $flags = 0): ArrayIterator
    {
        return new ArrayIterator($this->array, $flags);
    }

    /**
     * Extract a column from a multidimensional array.
     * 
     * @param string|int|null $property The column to extract.
     * @param string|int|null $index The index to use for extraction.
     * 
     * @return ArrayUtil Return a new instance of ArrayUtil containing the extracted column.
     */
    public function column(string|int|null $property, string|int|null $index = null): ArrayUtil
    {
        return new ArrayUtil(array_column($this->array, $property, $index));
    }

    /**
     * Extracts values from an array of associative arrays or objects based on a specified key or property.
     *
     * @param string $property The key or property name to pluck values from.
     * 
     * @return ArrayUtil Return a new instance of ArrayUtil containing the values corresponding to the specified property name.
     */
    public function pluck(string $property): ArrayUtil
    {
        $result = [];

        foreach ($this->array as $item) {
            if (is_array($item) && isset($item[$property])) {
                $result[] = $item[$property];
            } elseif (is_object($item) && isset($item->$property)) {
                $result[] = $item->$property;
            }
        }

        return new ArrayUtil($result);
    }

    /**
     * Searches for a value or key in the current array and returns the corresponding key or index.
     *
     * @param mixed $search The value or key to search for.
     * @param bool $strict Whether to also check the types of the search in the searching array haystack (default: true).
     * @param bool $forKey Whether to search for a key (defaults: false).
     * 
     * @return string|int|false Returns the key or index if found, otherwise returns false.
     */
    public function search(mixed $search, bool $strict = true, bool $forKey = false): string|int|bool
    {
        if ($forKey) {
            return $this->has($search) ? $search : false;
        } 

        $key = array_search($search, $this->array, $strict);
        return $key !== false ? $key : false;
    }

    /**
     * Replace the current array with another array.
     * 
     * @param ArrayUtil|array<string|int,mixed> $replacements The array to replace with the current array.
     * 
     * @return self Return the updated instance with the merged array.
     */
    public function replace(ArrayUtil|array $replacements): self
    {
        $replacements = ($replacements instanceof ArrayUtil) 
            ? $replacements->get() 
            : $replacements;
        $this->array = array_replace($this->array, $replacements);
        return $this;
    }

    /**
     * Merge another array with the current array.
     * 
     * @param ArrayUtil|array<string|int,mixed> $array The array to merge with the current array.
     * 
     * @return self Return the updated instance with the merged array.
     */
    public function merge(ArrayUtil|array $array): self
    {
        $array = ($array instanceof ArrayUtil) ? $array->get() : $array;
        $this->array = array_merge($this->array, $array);
        return $this;
    }

    /**
     * Recursively merges multiple arrays. Values from later arrays will overwrite values from earlier arrays, including merging nested arrays.
     * 
     * @param ArrayUtil|array<string|int,mixed> $array The array to merge with the current array.
     * @param bool $distinct Whether to ensure unique values in nested arrays (default: false).
     * 
     * @return self Return the updated instance with the merged array.
     */
    public function mergeRecursive(ArrayUtil|array $array, bool $distinct = false): self
    {
        $array = ($array instanceof ArrayUtil) ? $array->get() : $array;
        $this->array = ($distinct) 
            ? array_merge_recursive_distinct($this->array, $array)
            : array_merge_recursive($this->array, $array);

        return $this;
    }

    /**
     * Merges the given array into the current array at specified intervals.
     *
     * This method inserts elements from the provided array (`$array`) into the
     * existing array (`$this->array`) at every `$position` interval. If there are 
     * remaining elements in `$array` after merging, they will be inserted randomly.
     *
     * @param ArrayUtil|array $array The array to merge into the current array.
     * @param int $intervals The interval at which elements from `$array` should be inserted.
     * 
     * @return self Returns the instance with the modified array.
     * 
     * @example - Merging Posts with Advertisements:
     * 
     * ```php
     * $posts = [
     *    ["id" => 1, "title" => "Post 1"],
     *    ["id" => 2, "title" => "Post 2"],
     *    ["id" => 3, "title" => "Post 3"],
     *    ["id" => 4, "title" => "Post 4"]
     * ];
     *
     * $ads = [
     *    ["id" => 101, "title" => "Ad 1"],
     *    ["id" => 102, "title" => "Ad 2"],
     *    ["id" => 103, "title" => "Ad 3"],
     * ];
     *
     * $array = (new ArrayUtil($posts))->mergeIntervals($ads, 2);
     * print_r($array->getArray());
     * ```
     */
    public function mergeIntervals(ArrayUtil|array $array, int $intervals = 4): self 
    {
        $array = ($array instanceof ArrayUtil) ? $array->getArray() : $array;

        if(!$this->array && !$array || $this->array && !$array){
            return $this;
        }

        if(!$this->array && $array){
            $this->array = $array;
            return $this;
        }

        $mergedToArray = [];
        $addIndex = 0;
        $count = count($array);
    
        foreach ($this->array as $index => $value) {
            $mergedToArray[] = $value;
            
            if (($index + 1) % $intervals === 0 && $addIndex < $count) {
                $mergedToArray[] = $array[$addIndex];
                $addIndex++;
            }
        }
    
        while ($addIndex < $count) {
            $randomIndex = array_rand($mergedToArray);
            array_splice($mergedToArray, $randomIndex, 0, [$array[$addIndex]]);
            $addIndex++;
        }
    
        $this->array = $mergedToArray;
        return $this;
    }

    /**
     * Iterates over each value in the array passing them to the callback function to filter.
     * 
     * @param callable|null $callback The filter callback function (default: null).
     * @param int $mode The array filter mode to use (default: 0).
     * 
     * @return ArrayUtil Return a new instance of ArrayUtil containing the filters.
     */
    public function filter(?callable $callback = null, int $mode = 0): ArrayUtil
    {
        return new ArrayUtil(array_filter($this->array, $callback, $mode));
    }

    /**
     * Applies the callback to the elements of the given arrays.
     * 
     * @param callable $callback The filter callback function (default: null).
     * @param array ...$arguments The array arguments to map.
     * 
     * @return ArrayUtil Return a new instance of ArrayUtil containing the elements after applying callback.
     */
    public function map(callable $callback, array ...$arguments): ArrayUtil
    {
        return new ArrayUtil(array_map($callback, $this->array, ...$arguments));
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
     * @param bool $preserveKeys Whether to preserve the original keys of the current array or reset resulting to sequential numeric keys. (default: false).
     * 
     * @return self Return the updated instance with the reversed array.
     */
    public function reverse(bool $preserveKeys = false): self
    {
        $this->array = array_reverse($this->array, $preserveKeys);
        return $this;
    }

    /**
     * Return new array instance by splitting the array into chunks.
     * 
     * @param int $size The split chunk size.
     * @param bool $preserveKeys Whether to preserve the array keys or reindex the chunk numerically (default: false).
     * 
     * @return ArrayUtil Return a new instance of ArrayUtil containing the chunked array.
     */
    public function chunk(int $size, bool $preserveKeys = false): ArrayUtil
    {
        return new ArrayUtil(array_chunk($this->array, $size, $preserveKeys));
    }

    /**
     * Return new array instance containing a slice of current array.
     * 
     * @param int $offset The array slice offset.
     * @param int|null $length The array slice length (default: null).
     *                  - If length is given and is positive, then the sequence will have that many elements in it. 
     *                  - If length is given and is negative then the sequence will stop that many elements from the end of the array. 
     *                  - If it is omitted, then the sequence will have everything from offset up until the end of the array.
     * @param bool $preserveKeys Whether to preserve the array keys or to reorder and reset the array numeric keys (default: false).
     * 
     * @return ArrayUtil Return a new instance of ArrayUtil containing the slice of array.
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = false): ArrayUtil
    {
        return new ArrayUtil(array_slice($this->array, $offset, $length, $preserveKeys));
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
     * @param bool $throw Whether throw json exception if error occurs (default: true).
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
     * Convert the current array to to comma string list representation.
     * 
     * @return string Return string list representation of the current array.
    */
    public function toStringList(): string 
    {
        if($this->array === []){
            return '';
        }

        return Listify::toList($this->array);
    }

    /**
     * Convert the current array to json string representation.
     * 
     * @param int $flags The json encoding flags using bitwise OR (|) operators to combine multiple flags.
     * @param int $depth Set the maximum depth, must be greater than zero (default: 512).
     * @param bool $throw Whether throw json exception if error occurs (default: true).
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
     * @param bool|null $assoc Whether to convert to an associative array (default: true).
     * @param int $flags The json encoding flags using bitwise OR (|) operators to combine multiple flags.
     * @param int $depth Set the maximum depth, must be greater than zero (default: 512).
     * @param bool $throw Whether throw json exception if error occurs (default: true).
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
}