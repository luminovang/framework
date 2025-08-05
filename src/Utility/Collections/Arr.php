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
namespace Luminova\Utility\Collections;

use \Throwable;
use \Countable;
use \PhpToken;
use \Stringable;
use \ArrayAccess;
use \ArrayObject;
use \Traversable;
use \SplFixedArray;
use \ArrayIterator;
use \JsonSerializable;
use \IteratorAggregate;
use \Luminova\Utility\String\{Str, Listifier};
use \Luminova\Exceptions\{JsonException, RuntimeException, InvalidArgumentException};
use function \Luminova\Funcs\{
    is_nested,
    is_associative,
    array_merge_recursive_distinct
};

class Arr implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable, Stringable 
{
    /**
     * Create a new array instance.
     * 
     * If no array is provided, an empty array is initialized.
     * The `$immutable` flag controls whether the instance should modify itself
     * or return a new instance when performing operations.
     *
     * @param array $array The initial array data.
     * @param bool $immutable Whether this instance should behave immutably (default: true).
     */
    public function __construct(
        private array $array = [],
        protected bool $immutable = true
    ) {}

    /**
     * Create a new array instance.
     *
     * Initializes an array object with the given array.
     * The `$immutable` flag determines whether operations modify this instance or return a new one.
     *
     * @param array $array The initial array data.
     * @param bool $immutable Whether the instance should be immutable (default: true).
     *
     * @return static Returns a new instance of array object.
     */
    public static function of(array $array, bool $immutable = true): self
    {
       return new static($array, $immutable);
    }

    /**
     * Enable or disable immutable mode for all Arr instances.
     *
     * When immutable mode is enabled (`true`), all array-transforming methods
     * such as `map()`, `filter()`, `reverse()`, etc., will return **new instances**
     * instead of modifying the current one.
     *
     * When disabled (`false`), methods will update the existing instance directly.
     *
     * @param bool $state Whether to enable (`true`) or disable (`false`) immutable mode. Default: true.
     *
     * @return self Return instance of array class.
     *
     * @example - Example:
     * ```php
     * $arr = (new Arr([1, 2, 3]))->immutable(true);
     * $new = $arr->reverse(); // returns a new Arr instance
     * 
     * $arr->reverse(); // modifies $arr directly
     * ```
     */
    public function immutable(bool $state = true): self
    {
        $this->immutable = $state;
        return $this;
    }

    /**
     * Apply a static or mutable array update depending on immutable mode.
     *
     * If immutable mode is enabled, this method returns a **new instance**
     * containing the provided array data. Otherwise, it updates the
     * current instance and returns `$this`.
     *
     * @param array $data The array data to apply or assign.
     *
     * @return static Returns a new instance or the current instance depending on immutable mode.
     *
     * @internal This helper is used internally by most modifying methods.
     */
    protected function __static(array $data): self
    {
        if ($this->immutable) {
            return new static($data);
        }

        $this->array = $data;
        return $this;
    }

    /**
     * Create or Update the current array from a json string.
     * 
     * @param string $json The json string to update from.
     * @param int $depth Set the maximum recursion depth, must be greater than zero (default: 512).
     * @param int $flags The json decoding flags using bitwise OR (|) operators to combine multiple flags.
     * 
     * @return self Return the updated instance with the new array from json string.
     * @throws JsonException Throws if unable to convert json string to array.
     */
    public static function fromJson(
        string $json, 
        int $depth = 512, 
        int $flags = 0
    ): self
    {
        $flags |= JSON_THROW_ON_ERROR;

        try{
            return new static(json_decode($json, true, $depth, $flags));
        }catch(\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
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
     * $arr = Arr::fromList('a,b,c'); // ['a', 'b', 'c'].
     * $arr = Arr::fromList('"a","b","c"'); // ['a', 'b', 'c'].
     * ```
     */
    public static function fromList(string $list): self
    {
        return new static(Listifier::toArray($list));
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
     * Get a value from the array by its key.
     *
     * If no key is provided, the entire array will be returned.
     *
     * @param string|int|null $key The key of the item to get. Set to null to return the full array. (default: null)
     * @param mixed $default The value to return if the key does not exist. (default: null)
     *
     * @return mixed Returns the value for the given key, the default value if not found, 
     *               or the full array when no key is provided.
     */
    public function get(
        string|int|null $key = null, 
        mixed $default = null
    ): mixed
    {
        return ($key === null) 
            ? $this->array 
            : ($this->array[$key] ?? $default);
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
    public function getAccessor(string $notations, mixed $default = null): mixed
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
     * Adds a value to a nested array using dot notation.
     *
     * @param string $notations The dot notation path to the nested key.
     * @param mixed $value The value to set at the nested key.
     * 
     * @return self Return the updated instance with the new element.
     */
    public function accessor(string $notations, mixed $value): self
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
     * Minify an array into a compact PHP code string.
     *
     * This method converts the array into a minified string representation using
     * PHP short array syntax (`[]`). It preserves string values exactly as they
     * are (including regex patterns or text containing the word "array") and 
     * removes all unnecessary whitespace, newlines, and trailing commas. Nested
     * arrays are fully handled.
     *
     * @param bool $object If true, returns a Stringable instance, otherwise returns a plain string (default: false).
     *
     * @return Str<Stringable>|string|null Returns a minified PHP array string.
     *         - Plain string value.
     *         - Stringable object wrapping the minified string if `$object` is true, or
     *         - null if the array cannot be exported.
     * 
     * @example - Examples:
     * ```php
     * $array = [
     *     'foo' => 'bar',
     *     'nested' => ['a', 'b', 'c'],
     *     'pattern' => '/\d+/',
     * ];
     *
     * // Minify to a string
     * $minified = Arr::of($array)->minify();
     * // Result: "['foo'=>'bar','nested'=>['a','b','c'],'pattern'=>'/\d+/']"
     *
     * // Get result as Stringable object
     * $str = Arr::of($array)->minify(true);
     * echo $str->toUpperCase(); // Can use Stringable methods
     * 
     * // Do not execute untrusted array using 'eval'
     * $minified = Arr::of($array)->minify();
     * $array = eval('return ' . $minified . ';');
     * // Result Array(...)
     * ```
     */
    public function minify(bool $object = false): Stringable|string|null
    {
        if ($this->array === []) {
            return $object ? Str::of('[]') : '[]';
        }

        $string = var_export($this->array, true);
        if ($string === null) {
            return null;
        }

        $tokens = PhpToken::tokenize('<?php ' . $string);
        $string = '';

        $parent = [];
        $isFromParentArray = false;

        foreach ($tokens as $token) {
            $text = $token->text;
            $id = $token->id;

            if ($id === T_OPEN_TAG) {
                continue;
            }

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $string .= $text;
                continue;
            }

            if ($id === T_WHITESPACE) {
                continue;
            }

            if ($id === T_ARRAY) {
                $isFromParentArray = true;
                continue;
            }

            if ($text === '(') {
                if ($isFromParentArray) {
                    $parent[] = true;
                    $string .= '[';
                    $isFromParentArray = false;
                } else {
                    $parent[] = false;
                    $string .= '(';
                }
                continue;
            }

            if ($text === ')') {
                $isParent = array_pop($parent) ?? false;
                $string .= $isParent ? ']' : ')';
                continue;
            }

            $string .= $text;
        }

        $string = preg_replace('/,(\s*])/m', '$1', $string);
        $string = trim($string);

        return $object ? Str::of($string) : $string;
    }

    /**
     * Compares the current array with one or more arrays and returns the difference.
     * The values from the current array that are not present in any of the passed arrays.
     * 
     * @param Traversable|ArrayAccess|Stringable|Arr|array ...$arrays One or more arrays or Arr instances to compare.
     * 
     * @return static Returns an array containing all the entries from the current array that are not present in any of the other arrays.
     */
    public function diff(Traversable|ArrayAccess|Stringable|Arr|array ...$arrays): self
    {
        $compares = [];

        foreach ($arrays as $array) {
            $compares[] = self::from($array);
        }

        return new static(array_diff($this->array, ...$compares));
    }

    /**
     * Sort the array and return a new sorted instance.
     *
     * Supports sorting by values or keys, with optional callbacks, flags,
     * and options to control order and key preservation.
     *
     * Behavior:
     * - If `$callback` is provided, it’s used for comparison (flags ignored).
     * - If `$byKey` is true, the sort is performed on keys instead of values.
     * - When no callback is given:
     *   - `sort()` / `rsort()` for simple value sort.
     *   - `asort()` / `arsort()` for value sort with preserved keys.
     *   - `ksort()` / `krsort()` for key sort.
     *
     * @param (callable(mixed $a, mixed $b): int)|null $callback Optional custom sort comparison function.
     * @param int $flags PHP sorting flags e.g., `SORT_NATURAL`, `SORT_FLAG_CASE`, (default: `SORT_REGULAR`).
     * @param string $order Sorting order: 'ASC' for ascending (default), 'DESC' for descending.
     * @param bool $preserveKeys Preserve original keys when sorting values (default: false).
     * @param bool $byKey Sort by array keys instead of values (default: false).
     *
     * @return static Return a new instance containing the sorted array.
     * @see immutable()
     *
     * @example - Examples:
     * ```php
     * $arr = new Arr(['b' => 2, 'a' => 1, 'c' => 3]);
     * 
     * $arr->sort(); // ASC: [1, 2, 3]
     * $arr->sort(order: 'DESC'); // DESC: [3, 2, 1]
     * $arr->sort(byKey: true); // Sort by key: ['a' => 1, 'b' => 2, 'c' => 3]
     * $arr->sort(fn($a, $b) => strlen($a) <=> strlen($b)); // Custom comparison
     * $arr->sort(flags: SORT_NATURAL | SORT_FLAG_CASE, 'DESC');
     * ```
     */
    public function sort(
        ?callable $callback = null,
        int $flags = SORT_REGULAR,
        string $order = 'ASC',
        bool $preserveKeys = false,
        bool $byKey = false
    ): self 
    {
        $array = $this->array;

        if ($callback) {
            if ($byKey) {
                uksort($array, $callback);
            } else {
                $preserveKeys ? uasort($array, $callback) : usort($array, $callback);
            }
            return $this->__static($array);
        }

        $isDescending = strtoupper($order) === 'DESC';

        if ($byKey) {
            $isDescending ? krsort($array, $flags) : ksort($array, $flags);
        } elseif ($preserveKeys) {
            $isDescending ? arsort($array, $flags) : asort($array, $flags);
        } else {
            $isDescending ? rsort($array, $flags) : sort($array, $flags);
        }

        return $this->__static($array);
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
     * Remove and return the last element of the current array.
     * 
     * This method updates the array in place, removing the final element.
     *
     * @return mixed Returns the removed element, or null if the array is empty.
     */
    public function pop(): mixed
    {
        return array_pop($this->array);
    }

    /**
     * Remove and return the first element of the current array.
     * 
     * This method updates the array in place, removing the first element.
     *
     * @return mixed Returns the removed element, or null if the array is empty.
     */
    public function shift(): mixed
    {
        return array_shift($this->array);
    }

    /**
     * Add one or more elements to the beginning of the current array.
     * 
     * This method updates the array in place.
     *
     * @param int $elements The number of elements in the array.
     * @param array ...$values One or more values to add to the start of the array.
     * 
     * @return self Returns the current instance after adding the elements.
     */
    public function unshift(int &$elements = 0, array ...$values): self
    {
        $elements = array_unshift($this->array, ...$values);
        return $this;
    }

    /**
     * Extract all the keys or a subset of the keys in the current array.
     * 
     * @param mixed $filter If filter value is specified, then only keys containing these values are returned.
     * @param bool $strict Determines if strict comparison (===) should be used during the search (default: false).
     * 
     * @return static Returns a new instance containing array keys from the current array.
     */
    public function keys(mixed $filter, bool $strict = false): self
    {
        return new static(array_keys(
            $this->array, 
            $filter, 
            $strict
        ));
    }

    /**
     * Extract all the values in the current array.
     * 
     * @return static Returns a new instance containing array values from the current array.
     */
    public function values(): self
    {
        return new static(array_values($this->array));
    }

    /**
     * Return a new array instance containing only unique values.
     *
     * Removes duplicate values from the current array.
     * Optionally, comparison behavior can be modified using sort flags.
     *
     * @param int $flags Sorting type flags to control comparison behavior.
     *                   - SORT_REGULAR (default): Normal comparison (no type change)
     *                   - SORT_NUMERIC: Compare items numerically
     *                   - SORT_STRING: Compare items as strings
     *                   - SORT_LOCALE_STRING: Compare as strings, based on the current locale
     *                   - SORT_NATURAL: Compare items using “natural order” like `natsort()`
     *
     * @return static Returns a new instance containing only unique values.
     *
     * @example - Example:
     * ```php
     * $arr = new Arr([1, 2, 2, '2', 3]);
     * $unique = $arr->unique(SORT_REGULAR);
     * // Result: [1, 2, '2', 3]
     * ```
     */
    public function unique(int $flags = SORT_REGULAR): self
    {
        return new static(array_unique($this->array, $flags));
    }

    /**
     * Return array iterator object for the current array.
     * 
     * @param int $flags Optional flags to control the behavior of the ArrayObject object (default: 0).
     * 
     * @return Traversable<TKey,TValue>|TValue[] Return an instance of an object implementing `Iterator` or `Traversable`.
     */
    public function iterator(int $flags = 0): Traversable
    {
        return new ArrayIterator($this->array, $flags);
    }

    /**
     * Extract a column from a multidimensional array.
     * 
     * @param string|int|null $property The column to extract.
     * @param string|int|null $index The index to use for extraction.
     * 
     * @return static Returns a new instance containing the extracted column.
     */
    public function column(string|int|null $property, string|int|null $index = null): self
    {
        return new static(array_column(
            $this->array, 
            $property, 
            $index
        ));
    }

    /**
     * Extracts values from an array of associative arrays or objects based on a specified key or property.
     *
     * @param string $property The key or property name to pluck values from.
     * 
     * @return static Returns a new instance containing the values corresponding to the specified property name.
     */
    public function pluck(string $property): self
    {
        $result = [];

        foreach ($this->array as $item) {
            if (is_array($item) && isset($item[$property])) {
                $result[] = $item[$property];
            } elseif (is_object($item) && isset($item->$property)) {
                $result[] = $item->$property;
            }
        }

        return new static($result);
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

        return array_search($search, $this->array, $strict);
    }

    /**
     * Add a value to the end of the array.
     *
     * Works like PHP’s `array_push()`, but modifies the current array instance.
     *
     * @param mixed $value The value to append to the end of the array.
     * 
     * @return self Returns the updated instance with the new value appended.
     */
    public function push(mixed $value): self
    {
        $this->array[] = $value;
        return $this;
    }

    /**
     * Replace the current array entirely with a new one.
     *
     * Converts any array-like input into a standard PHP array before replacing.
     *
     * @param IteratorAggregate|ArrayAccess|Stringable|Arr|array $array The new array or array-like object to set.
     * 
     * @return self Returns the updated instance containing the new array.
     */
    public function set(IteratorAggregate|ArrayAccess|Stringable|Arr|array $array): self
    {
        $this->array = self::from($array);
        return $this;
    }

    /**
     * Add or update a single element in the array.
     *
     * If the key already exists, its value will be replaced.
     * If not, the key-value pair will be added.
     *
     * @param string|int $key The key of the element to add or update.
     * @param mixed $value The value to assign to the key.
     * 
     * @return self Returns the updated instance with the modified element.
     */
    public function add(string|int $key, mixed $value): self
    {
        $this->array[$key] = $value;
        return $this;
    }

    /**
     * Replace elements in the current array with values from one or more arrays.
     *
     * This behaves replaces the existing array and keys are updated,
     * and new keys are added. Accepts multiple arrays or array-like objects.
     *
     * @param IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$replacements 
     *        One or more arrays or array-like objects to merge into the current array.
     * 
     * @return self Returns the updated instance with replaced values.
     */
    public function replace(IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$replacements): self
    {
        $this->array = array_replace(
            $this->array, 
            ...self::pack(...$replacements)
        );

        return $this;
    }

    /**
     * Merge another array with the current array.
     * 
     * @param IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$arrays The array to merge with the current array.
     * 
     * @return self Return the updated instance with the merged array.
     */
    public function merge(IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$arrays): self
    {
        $this->array = array_merge(
            $this->array, 
            ...self::pack(...$arrays)
        );
        
        return $this;
    }

    /**
     * Recursively merges multiple arrays. 
     * 
     * Values from later arrays will overwrite values from earlier arrays, including merging nested arrays.
     * 
     * @param IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$arrays The array to merge with the current array.
     * @param bool $distinct Whether to ensure unique values in nested arrays (default: false).
     * 
     * @return self Return the updated instance with the merged array.
     */
    public function mergeRecursive(
        bool $distinct = false,
        IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$arrays
    ): self
    {
        if($distinct){
            $this->array = array_merge_recursive_distinct(
                $this->array, 
                ...self::pack(...$arrays)
            );

            return $this;
        }

        $this->array = array_merge_recursive($this->array, ...self::pack(...$arrays));

        return $this;
    }

    /**
     * Merges the given array into the current array at specified intervals.
     *
     * This method inserts elements from the provided array (`$array`) into the
     * existing array (`$this->array`) at every `$position` interval. If there are 
     * remaining elements in `$array` after merging, they will be inserted randomly.
     *
     * @param int $intervals The interval at which elements from `$array` should be inserted.
     * @param IteratorAggregate|ArrayAccess|Stringable|Arr|array ..$arrays The array to merge into the current array.
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
     * $array = (new Arr($posts))->mergeIntervals(2, $ads);
     * print_r($array->getArray());
     * ```
     */
    public function mergeIntervals( 
        int $intervals = 4,
        IteratorAggregate|ArrayAccess|Stringable|Arr|array ...$arrays
    ): self 
    {
        if(($this->array === [] && $arrays === []) || ($this->array && $arrays === [])){
            return $this;
        }

        $arrays = self::pack(...$arrays);

        if($this->array === [] && $arrays){
            $this->array = array_merge(...$arrays);
            return $this;
        }

        $new = [];
        $addIndex = 0;
        $count = count($arrays);
    
        foreach ($this->array as $index => $value) {
            $new[] = $value;
            
            if (($index + 1) % $intervals === 0 && $addIndex < $count) {
                $new[] = $arrays[$addIndex];
                $addIndex++;
            }
        }
    
        while ($addIndex < $count) {
            $randomIndex = array_rand($new);
            array_splice($new, $randomIndex, 0, [$arrays[$addIndex]]);
            $addIndex++;
        }
    
        $this->array = $new;
        return $this;
    }

    /**
     * Iterates over each value in the array passing them to the callback function to filter.
     * 
     * @param callable|null $callback The filter callback function (default: null).
     * @param int $mode The array filter mode to use (default: 0).
     * 
     * @return static Returns a new instance containing the filters.
     */
    public function filter(?callable $callback = null, int $mode = 0): self
    {
        return $this->__static(array_filter(
            $this->array, 
            $callback, 
            $mode
        ));
    }

    /**
     * Applies the callback to the elements of the given arrays.
     * 
     * @param callable $callback The filter callback function (default: null).
     * @param mixed ...$arrays The array arguments to map.
     * 
     * @return static Returns a new instance containing the elements after applying callback.
     */
    public function map(callable $callback, mixed ...$arrays): self
    {
        return $this->__static(array_map(
            $callback, 
            $this->array, 
            ...self::pack(...$arrays)
        ));
    }

    /**
     * Iteratively reduce the array to a single value using a callback function.
     * 
     * @param (callable(TCarry, TItem): TCarry) $callback The reduce callback function
     * @param mixed $initial If the optional initial is available, (default: null).
     *                  it will be used at the beginning of the process, 
     *                  or as a final result in case the array is empty.
     * 
     * @return mixed Return the result of array reduce.
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce(
            $this->array, 
            $callback, 
            $initial
        );
    }

    /**
     * Update the current array with elements in reverse order.
     * 
     * @param bool $preserveKeys Whether to preserve the original keys of the current array 
     *              or reset resulting to sequential numeric keys. (default: false).
     * 
     * @return staticReturns a new instance containing the reversed array.
     */
    public function reverse(bool $preserveKeys = false): self
    {
        return $this->__static(array_reverse(
            $this->array, 
            $preserveKeys
        ));
    }

    /**
     * Return new array instance by splitting the array into chunks.
     * 
     * @param int $size The split chunk size.
     * @param bool $preserveKeys Whether to preserve the array keys or reindex the chunk numerically (default: false).
     * 
     * @return static Returns a new instance containing the chunked array.
     */
    public function chunk(int $size, bool $preserveKeys = false): self
    {
        return $this->__static(array_chunk(
            $this->array, 
            $size,
            $preserveKeys
        ));
    }

    /**
     * Create a new array instance containing a portion (slice) of the current array.
     *
     * @param int $offset The starting position of the slice.
     * @param int|null $length The number of elements to include (default: null).
     *        - If positive, includes up to that many elements from the offset.  
     *        - If negative, excludes that many elements from the end.  
     *        - If null, includes all elements from the offset to the end.
     * @param bool $preserveKeys Whether to keep the original array keys or reindex them (default: false).
     *
     * @return static Returns a new instance containing the sliced portion of the array.
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = false): self
    {
        return $this->__static(array_slice(
            $this->array, 
            $offset, 
            $length, 
            $preserveKeys
        ));
    }

    /**
     * Convert the current array to to json string representation.
     * 
     * @return string Return json string of the current array or blank string if error occurred.
     */
    public function __toString(): string 
    {
        return (string) $this->toJson(0);
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
       return (string) $this->toJson($throw ? JSON_THROW_ON_ERROR : 0);
    }

    /**
     * Convert the internal array to a native PHP array.
     *
     * @return array Returns the internal array representation.
     */
    public function toArray(): array
    {
        return $this->array;
    }

    /**
     * Serialize the object into an array representation for PHP's serialization system.
     *
     * @return array Returns the array representation of the object for serialization.
     */
    public function __serialize(): array
    {
        return $this->array;
    }

    /**
     * Restore the object state from a serialized array representation.
     *
     * @param array $data The array data to restore from.
     * 
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->array = $data;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * > This method is automatically called by `json_encode()`.
     *
     * @return mixed Returns the data to be serialized into JSON.
     */
    public function jsonSerialize(): mixed
    {
        return $this->array;
    }

    /**
     * Convert the current array to to comma string list representation.
     * 
     * @return string Return listify string representation of the current array.
     */
    public function toList(): string 
    {
        if($this->array === []){
            return '';
        }

        return Listifier::toList($this->array);
    }

    /**
     * Convert the current array to a JSON string.
     *
     * Automatically includes `JSON_UNESCAPED_UNICODE` flags
     * for readability, and ensures safe handling of encoding errors.
     *
     * @param int $flags JSON encoding flags combined with bitwise OR (`|`), (default: `JSON_THROW_ON_ERROR`).
     * @param int $depth Maximum encoding depth, must be greater than zero (default: `512`).
     *
     * @return string|null Returns the JSON string representation, or null if encoding fails.
     * @throws JsonException If encoding fails and `JSON_THROW_ON_ERROR` is enabled.
     */
    public function toJson(
        int $flags = JSON_THROW_ON_ERROR, 
        int $depth = 512
    ): ?string
    {
        $throwable = (bool) ($flags & JSON_THROW_ON_ERROR);
        $flags |= JSON_UNESCAPED_UNICODE;

        try{
            return json_encode($this->array, $flags, $depth) ?: null;
        }catch(\JsonException $e){
            if($throwable){
                throw new JsonException($e->getMessage(), $e->getCode(), $e);
            }

            return null;
        }
    }

    /**
     * Convert the array to a JSON string.
     *
     * This method returns a JSON representation of the array. By default, the output
     * is pretty-printed for readability. You can also return a Stringable object
     * for chainable string operations.
     *
     * @param bool $object Return as a Stringable object if true (default: false.)
     * @param int $flags JSON encoding flags combined with bitwise OR (`|`), (default: `JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT`).
     * @param int $depth Maximum encoding depth, must be greater than zero (default: `512`).
     *
     * @return string|Stringable|null The JSON string, Stringable object, or null if encoding fails.
     * @throws JsonException If encoding fails and `JSON_THROW_ON_ERROR` is enabled.
     */
    public function json(
        bool $object = false,
        int $flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT, 
        int $depth = 512
    ): ?string
    {
        $json = $this->toJson($flags, $depth);

        if($json  === null){
            return null;
        }

        return $object ? Str::of($json) : $json;
    }

    /**
     * Convert the current array to a JSON-decoded object.
     *
     * This method first converts the array to JSON (via `toJson()`),
     * then decodes it into a PHP object.
     *
     * @param int $flags JSON decoding flags combined with bitwise OR (`|`), (default: `JSON_THROW_ON_ERROR`).
     * @param int $depth Maximum decoding depth (default: `512`).
     *
     * @return object|null Returns an object representation of the array, or null on failure.
     * @throws JsonException If decoding fails and `JSON_THROW_ON_ERROR` is enabled.
     */
    public function toObject(
        int $flags = JSON_THROW_ON_ERROR, 
        int $depth = 512
    ): ?object
    {
        $throwable = (bool) ($flags & JSON_THROW_ON_ERROR);
        $json = $this->toJson($throwable ? JSON_THROW_ON_ERROR : 0);
        
        if($json === null){
            return null;
        }

        try{
            return json_decode($json, false, $depth, $flags) ?: null;
        }catch(\JsonException $e){
            if($throwable){
                throw new JsonException($e->getMessage(), $e->getCode(), $e);
            }

            return null;
        }
    }

    /**
     * Check if an offset exists in the array.
     * 
     * @param mixed $offset The array key to check.
     * 
     * @return bool Returns true if the offset exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->array[$offset]);
    }

    /**
     * Retrieve the value at a given offset.
     * 
     * @param mixed $offset The array key to retrieve.
     * 
     * @return mixed Returns the value at the specified offset, or null if it does not exist.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->array[$offset] ?? null;
    }

    /**
     * Assign a value to a given offset.
     * 
     * @param mixed $offset The array key to assign the value to. If null, the value will be appended.
     * @param mixed $value  The value to set.
     * 
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->array[] = $value;
            return;
        }

        $this->array[$offset] = $value;
    }

    /**
     * Unset the value at a given offset.
     * 
     * @param mixed $offset The array key to unset.
     * 
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->array[$offset]);
    }

    /**
     * Retrieve an external iterator for the array.
     * 
     * This method is required by the IteratorAggregate interface.
     * 
     * @return Traversable<TKey,TValue> Returns an iterator for iterating over the array.
     */
    public function getIterator(): Traversable
    {
        return $this->iterator();
    }

    /**
     * Pack one or more array or array-like arguments into an array of arrays.
     *
     * Useful for safely passing multiple replacements or data sets using
     * the spread operator (`...`), ensuring consistent unpacking behavior.
     *
     * @param mixed ...$arrays One or more arrays or array-like objects.
     * @return array<int,array> Returns an array of normalized arrays, always safe for unpacking.
     * 
     * @example - Example:
     * ```php
     *  $packed = Arr::pack(['a' => 1], new ArrayObject(['b' => 2]));
     *  array_replace(...$packed);
     * ```
     */
    public static function pack(mixed ...$arrays): array
    {
        if($arrays === []){
            return [[]];
        }

        if(func_num_args() > 1){
            $values = [];
            foreach ($arrays as $array) {
                $values[] = self::from($array);
            }

            return $values;
        }

        return [self::from($arrays[0] ?? [])];
    }

    /**
     * Prioritizes array items based on keyword matching.
     * 
     * Sorts an array (flat or associative) by prioritizing one or more keywords
     * found in a target field. Supports nested fields and partial keyword matching.
     *
     * Earlier keywords in `$sort` have higher priority. Matching is case-insensitive.
     * Optionally, you can limit the number of comparisons or slice the array for performance.
     *
     * @param array &$items The array to apply prioritize sorting (passed by reference).
     * @param array<int,string>|string $keywords A keyword or list of keywords to prioritize.
     * @param string|int $index The key name or index in each item to check for matches (default: `0`).
     * @param string|int|null $nested Optional nested key or index within the target field (default: `null`).
     * @param int|null $limit Optional limit on how many comparison checks to perform (default: `all`).
     * @param int|null $slice Optional number of items to slice and sort (default: `all`).
     *
     * @return void
     * 
     * @example - Sort a flat array:
     * ```php
     * $leagues = [
     *     'Ligue 1', 
     *     'Premier League', 
     *     'La Liga', 
     *     'Bundesliga'
     * ];
     * 
     * Arr::prioritize($leagues, ['Premier', 'La Liga']);
     * // Result: ['Premier League', 'La Liga', 'Bundesliga', 'Ligue 1']
     * ```
     *
     * @example - Sort associative array with nested keys:
     * ```php
     * $data = [
     *     ['category' => ['title' => 'Business News']],
     *     ['category' => ['title' => 'Technology']],
     *     ['category' => ['title' => 'Sports']],
     * ];
     *
     * Arr::prioritize($data, ['Technology', 'Business'], 'category', 'title');
     * // Result order: Technology → Business → Sports
     * ```
     *
     * @example - Sort associative array by a single key:
     * ```php
     * $items = [
     *     ['type' => 'PDF File'],
     *     ['type' => 'Text Document'],
     *     ['type' => 'Image File'],
     * ];
     *
     * Arr::prioritize($items, ['Text', 'Image'], 'type');
     * // Result order: Text Document → Image File → PDF File
     * ```
     *
     * @example - Limit sorting to a subset of items:
     * ```php
     * $records = [
     *     ['status' => 'low'],
     *     ['status' => 'urgent'],
     *     ['status' => 'important'],
     *     ['status' => 'unknown'],
     *     ['status' => 'important'],
     *     // ...
     * ];
     * // Only process the first 5 items for performance
     * Arr::prioritize($records, ['urgent', 'important'], 'status', limit: 5);
     * ```
     */
    public static function prioritize(
        array &$items,
        string|array $keywords,
        string|int $index = 0,
        string|int|null $nested = null,
        ?int $limit = null,
        ?int $slice = null
    ): void 
    {
        if (empty($items) || empty($keywords)) {
            return;
        }

        $keywords = (array) $keywords;
        $sortCount = count($keywords);
        $compCount = 0;
        $array = ($slice === null) 
            ? $items 
            : array_slice($items, 0, $slice);

        usort($array, static function ($a, $b) use ($keywords, $sortCount, $index, $nested, $limit, &$compCount) {

            if ($limit !== null && $compCount++ >= $limit) {
                return 0;
            }

            $aScore = $bScore = 0;

            foreach ($keywords as $i => $keyword) {
                $weight = $sortCount - $i; 
                $keyword = strtolower(trim($keyword));

                $x = strtolower((string) (($nested === null)
                    ? ($a[$index] ?? $a ?? '')
                    : ($a[$index][$nested] ?? $a ?? '')
                ));

                $y = strtolower((string) (($nested === null)
                    ? ($b[$index] ?? $b ?? '')
                    : ($b[$index][$nested] ?? $b ?? '')
                ));

                if (str_contains($x, $keyword)) {
                    $aScore += $weight;
                }
                
                if (str_contains($y, $keyword)) {
                    $bScore += $weight;
                }
            }

            return $bScore <=> $aScore;
        });

        if($slice === null){
            $items = $array;
            return;
        }

        array_splice($items, 0, $slice, $array);
    }

    /**
     * Convert various supported data types into a plain array.
     *
     * This method attempts to normalize different input types that represent
     * or can be converted into arrays, such as Traversable objects, ArrayObject,
     * SplFixedArray, Stringable (JSON or serialized), and instances of Arr.
     *
     * @param Traversable|ArrayAccess|Stringable|Arr|array $source The source data to convert into an array. 
     *  Supports:
     *     - ArrayObject, IteratorAggregate, Traversable
     *     - Arr or any object implementing `toArray()`
     *     - JSON or serialized strings
     *     - Scalar
     *
     * @return array Returns an array representation of the input source.
     * @see pack()
     * 
     * @throws JsonException If unable to decode a JSON string.
     * @throws InvalidArgumentException If the input cannot be converted to an array.
     * @throws RuntimeException For unexpected runtime conversion errors.
     * 
     * @example - Examples: 
     * 
     * ```php
     * // From a simple array
     * $arr = Arr::from(['name' => 'John']);
     * // ['name' => 'John']
     *
     * // From an ArrayObject
     * $obj = new ArrayObject(['a' => 1, 'b' => 2]);
     * $arr = Arr::from($obj);
     * // ['a' => 1, 'b' => 2]
     *
     * // From JSON string
     * $json = '{"city": "London", "country": "UK"}';
     * $arr = Arr::from($json);
     * // ['city' => 'London', 'country' => 'UK']
     *
     * // From serialized array string
     * $serialized = serialize(['foo' => 'bar']);
     * $arr = Arr::from($serialized);
     * // ['foo' => 'bar']
     *
     * // From Traversable (Generator)
     * $gen = (function() { yield 'x' => 10; yield 'y' => 20; })();
     * $arr = Arr::from($gen);
     * // ['x' => 10, 'y' => 20]
     *
     * // From custom object implementing toArray()
     * class Example {
     *     public function toArray(): array {
     *         return ['key' => 'value'];
     *     }
     * }
     * $arr = Arr::from(new Example());
     * // ['key' => 'value']
     * ```
     */
    public static function from(mixed $source): array
    {
        if ($source === [] || is_array($source)) {
            return $source;
        }

        try{
            if ($source instanceof ArrayObject) {
                return $source->getArrayCopy();
            }

            if ($source instanceof IteratorAggregate) {
                return iterator_to_array($source->getIterator(), true);
            }

            if ($source instanceof Traversable) {
                return iterator_to_array($source, true);
            }
            
            if (
                ($source instanceof self) ||
                ($source instanceof SplFixedArray) || 
                method_exists($source, 'toArray')
            ) {
                return $source->toArray();
            }

            if(is_string($source) || $source instanceof Stringable){
                $str = (string) $source;

                if(json_validate($str)){
                    return (array) json_decode($str, true, 512, JSON_THROW_ON_ERROR);
                }

                $arr = @unserialize($str);

                if ($arr === [] || is_array($arr)) {
                    return $arr;
                }
            }

            if(is_scalar($source)){
                return (array) $source;
            }
        }catch(Throwable $e){
            if($e instanceof \JsonException){
                throw new JsonException($e->getMessage(), $e->getCode(), $e);
            }

            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        throw new InvalidArgumentException(sprintf(
            'Expected array-like or JSON/serialized string convertible to array, got %s.',
            get_debug_type($source)
        ));
    }
}