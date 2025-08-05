<?php
/**
 * Luminova Framework string helper class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility\String;

use \Normalizer;
use \Stringable;
use \ArrayAccess;
use \Luminova\Common\Helpers;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Utility\Object\Prototypeable;
use \Luminova\Interface\PrototypeableInterface;
use \Luminova\Exceptions\InvalidArgumentException;
use function \Luminova\Funcs\string_length;

/**
 * The `Str` class provides an object-oriented way to work with strings.
 * 
 * It supports chainable operations, text transformation, masking, slug creation,
 * hashing, and more — all while keeping a consistent interface.
 * 
 * It also supports the Luminova `PrototypeableInterface`, allowing you to 
 * dynamically add methods at runtime.
 * 
 * @mixin Luminova\Utility\Object\Prototypeable
 * 
 * @example - Usage:
 * ```php
 * $str = new Str('Hello World');
 * echo $str->toUpperCase();          // "HELLO WORLD"
 * echo $str->slugify();              // "hello-world"
 * echo $str->between('He', 'ld');    // "llo Wor"
 * echo $str->repeat(2);              // "Hello WorldHello World"
 * 
 * // Adding a dynamic prototype method
 * 
 * $str->prototype('toArray', fn($name) => explode(' ', $this->valueOf));
 * $str->toArray();
 * ```
 */
class Str implements Stringable, ArrayAccess, PrototypeableInterface
{
    /**
     * Encoding detection order.
     * 
     * @var array<int,string> $encodings
     */
    protected static array $encodings = [
        'UTF-8',
        'UTF-16',
        'UTF-32',
        'ISO-8859-1',
        'ISO-8859-15',
        'WINDOWS-1252',
        'LATIN1',
        'ASCII',
        'SJIS',
        'EUC-JP',
        'CP850',
        'BIG5'
    ];

    use Prototypeable;

    /**
     * Create a new string object.
     *
     * @param string $valueOf The initial string value.
     * @param string|null $encoding Optional string encoding (default: auto detect or fallback to `UTF-8`). 
     */
    public function __construct(
        protected string $valueOf = '', 
        protected ?string $encoding = null
    ) {}

    /**
     * Get the character at the specified position.
     *
     * Supports multibyte characters and negative indexes, where `-1` represents the last character.
     * Returns an empty string if the index is out of range.
     *
     * @param int $index The zero-based character position (can be negative).
     * 
     * @return static|null Returns a new `Str` instance containing the character, or an empty instance if not found.
     * @see charAt()
     *
     * @example - Example:
     * ```php
     * echo Str::of('Hello')->at(1);   // "e"
     * echo Str::of('Hello')->at(-1);  // "o"
     * echo Str::of('你好')->at(1);    // "好"
     * echo Str::of('Hello')->at(99);  // ""
     * ```
     */
    public function at(int $index): self
    {
        if($this->isEmpty()){
            return new static('');
        }

        $length = $this->length();

        if ($index < 0) {
            $index += $length;
        }

        $char = ($index < 0 || $index >= $length) 
            ? '' 
            : mb_substr($this->valueOf, $index, 1, $this->encoding());

        return new static($char, $this->encoding);
    }

    /**
     * Create a new `Str` instance from a string or any `Stringable` value.
     *
     * This static factory method makes it easy to initialize the string object
     * without using the `new` keyword directly.
     *
     * @param Stringable|string $valueOf The string or `Stringable` object to wrap.
     * @param string|null $encoding Optional string encoding (default: auto detect or fallback to `UTF-8`). 
     * 
     * @return static Returns a new `Str` instance containing the given value.
     *
     * @example - Example:
     * ```php
     * $str = Str::of('Hello World');
     * ```
     */
    public static function of(Stringable|string $valueOf, ?string $encoding = null): self
    {
        return new static((string) $valueOf, $encoding);
    }

    /**
     * Create a string from one or more Unicode values.
     *
     * @param int ...$codes Arguments of unicode code points.
     * 
     * @return static Returns a new `Str` instance representing the characters.
     *
     * @example - Example:
     * 
     * ```php
     * echo Str::fromCharCode(72, 101, 108, 108, 111); // "Hello"
     * ```
     */
    public static function fromCharCode(int ...$codes): self
    {
        return new static(implode('', array_map('chr', $codes)), 'UTF-8');
    }

    /**
     * Create a string from Unicode code points.
     *
     * @param int ...$points Arguments of unicode code points
     * 
     * @return static Returns a new `Str` instance representing the characters.
     *
     * @example - Example:
     * ```php
     * echo Str::fromCodePoint(9731, 9733, 9842); // "☃★♲"
     * ```
     */
    public static function fromCodePoint(int ...$points): self
    {
        $str = '';
        foreach ($points as $point) {
            $str .= mb_chr($point, 'UTF-8');
        }

        return new static($str, 'UTF-8');
    }

    /**
     * Return the internal string when cast to string.
     *
     * @return string Return the final value of string object.
     * @internal
     */
    public function __toString(): string 
    {
        return $this->valueOf;
    }

    /**
     * Set or update the current string value.
     *
     * @param Stringable|string $value The new string value.
     * @param string|null $encoding Optional string encoding (default: auto detect or fallback to `UTF-8`). 
     * 
     * @return self Returns the same instance for chaining.
     *
     * @example - Example:
     * ```php
     * $str = (new Str())->value('New value');
     * ```
     */
    public function value(Stringable|string $value, ?string $encoding = null): self 
    {
        $this->valueOf = (string) $value;
        $this->encoding = $encoding;
        $this->encoding();

        return $this;
    }

    /**
     * Hash the string using the given algorithm.
     *
     * Note: This modifies the current instance.
     *
     * @param string $algo The hash algorithm (e.g., "md5", "sha256").
     * @param bool $binary If true, outputs raw binary data instead of hex.
     * @param array $options Optional hashing options.
     * 
     * @return self Returns the same instance with the hashed value.
     * @throws InvalidArgumentException If the algorithm is unsupported.
     *
     * @example - Example:
     * ```php
     * $str = new Str('password');
     * echo $str->hash('sha256')->toString();
     * ```
     */
    public function hash(string $algo = 'md5', bool $binary = false, array $options = []): self
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported hash algorithm: %s', $algo));
        }

        $this->valueOf = hash($algo, $this->valueOf, $binary, $options);
        return $this;
    }

    /**
     * Get the string length.
     *
     * @param string|null $encoding Character encoding for multibyte support.
     * 
     * @return int The number of characters in the string.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hello'))->length(); // 5
     * ```
     */
    public function length(?string $encoding = null): int
    {
        if ($this->isEmpty()) {
            return 0;
        }

        return string_length(
            $this->valueOf, 
            $encoding ?? $this->encoding()
        );
    }

    /**
     * Detect the character encoding of the current string.
     * 
     * This method uses `mb_detect_encoding()` to guess the encoding from a list of common encodings.
     * If detection fails, it returns the `$default` value.
     * 
     * @param bool $strict Whether to use strict mode for detection (default: true).  
     *                     When true, `mb_detect_encoding` will only return a valid encoding 
     *                     if it can verify that the string is valid in that encoding.
     * @param string $default The fallback encoding to return if detection fails (default: 'UTF-8').
     * 
     * @return string Return the detected or default encoding name.
     * 
     * @example - Example:
     * ```php
     * Str::of("Hello")->encoding(); 
     * // 'UTF-8'
     * 
     * Str::of(iconv('UTF-8', 'ISO-8859-1', "Olá"))->encoding();
     * // 'ISO-8859-1'
     * ```
     */
    public function encoding(bool $strict = true, string $default = 'UTF-8'): string
    {
        if($this->isEmpty()){
            return 'UTF-8';
        }

        return $this->encoding ??= mb_detect_encoding(
            $this->valueOf, 
            self::$encodings, 
            $strict
        ) ?: $default;
    }

    /**
     * Split the string into chunks of the specified length.
     * 
     * This converts the string value into an array of `N` characters.
     *
     * @param int $length The length of each chunk.
     * 
     * @return array<int,string> An array of string segments.
     *
     * @example - Example:
     * ```php
     * print_r((new Str('abcde'))->split(2));
     * // ["ab", "cd", "e"]
     * ```
     */
    public function split(int $length = 1): array 
    {
        return str_split($this->valueOf, $length);
    }

    /**
     * Replace occurrences of a string or array of strings.
     * 
     * Uses PHP `str_replace` to replace one or more occurrence.
     *
     * @param string|array $search The string or array to search for.
     * @param string|array $replacement The replacement string or array.
     * 
     * @return static Returns a new `Str` instance with replacements applied.
     *
     * @example - Example:
     * ```php
     * echo Str::of('Hello World')->replace('World', 'PHP'); 
     * // "Hello PHP"
     * 
     * echo Str::of('I like cats and cats')->replace('cats', 'dogs');
     * // "I like dogs and dogs"
     * ```
     */
    public function replace(string|array $search, string|array $replacement): self
    {
        return $this->transform('str_replace', $search, $replacement, $this->valueOf);
    }

    /**
     * Replace occurrences using a regular expression.
     * 
     * Uses regex to find and replace all occurrence.
     *
     * @param string|array $pattern A regular expression pattern or array of patterns.
     * @param string|array $replacement Replacement string or array.
     * 
     * @return static Returns a new `Str` instance with replacements applied.
     *
     * @example - Example:
     * ```php
     * echo Str::of('Hello World')->replaceAll('/World/i', 'PHP');
     * // "Hello PHP"
     * 
     * echo Str::of('I like cats and Cats')->replaceAll('/cats/i', 'dogs');
     * // "I like dogs and dogs"
     * ```
     */
    public function replaceAll(string|array $pattern, string|array $replacement): self
    {
        return $this->transform('preg_replace', $pattern, $replacement, $this->valueOf);
    }

    /**
     * Repeat the string a given number of times.
     * 
     * It also support an optional suffix separator for the repeat process.
     *
     * @param int $times The number of repetitions.
     * @param string $separator An optional repeat suffix separator.
     * 
     * @return static Returns a new instance with the repeated string.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hi'))->repeat(3);
     * // "HiHiHi"
     * ```
     */
    public function repeat(int $times, string $separator = ''): self
    {
        return new static(rtrim(str_repeat(
            $this->valueOf . $separator, 
            $times
        ), $separator) ?: '', $this->encoding);
    }

    /**
     * Pad the string to a new length using a given character.
     *
     * @param int $length The target length.
     * @param string $char The padding character.
     * @param string $type The padding type (`left`, `right`, `both`).
     * 
     * @return static Returns a new instance with padding applied.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hi'))->pad(5, '_');
     * // "Hi___"
     * ```
     */
    public function pad(int $length, string $char = " ", string $type = 'right'): self
    {
        return $this->transform(
            'str_pad', 
            $this->valueOf, 
            $length, 
            $char, 
            match($type){
                'left'  => STR_PAD_LEFT,
                'both'  => STR_PAD_BOTH,
                default => STR_PAD_RIGHT
            }
        );
    }

    /**
     * Escape special characters in the string for HTML or attribute context.
     * 
     * @param string $context The value escaper content (Either `html` or `attr`).
     * @param string $encoding The character encoding (default: `UTF-8`).
     * 
     * @return static Returns a new instance with escaped content.
     *
     * @example - Example:
     * ```php
     * echo (new Str('<b>Unsafe</b>'))->escape();
     * // "&lt;b&gt;Unsafe&lt;/b&gt;"
     * ```
     */
    public function escape(string $context = 'html', string $encoding = 'UTF-8'): self
    {
        $fn = '\Luminova\Funcs\escape';

        if ($context === 'html' || $context === 'attr') {
            $fn = 'htmlspecialchars';
            $context = ENT_QUOTES | ENT_SUBSTITUTE;
        }

        $str = $this->transform($fn, $this->valueOf, $context, $encoding);
        $str->encoding = $encoding;

        return $str;
    }

    /**
     * Reverse the string.
     *
     * @return static Returns a new instance with reversed string.
     *
     * @example - Example:
     * ```php
     * echo (new Str('abc'))->reverse(); // "cba"
     * ```
     */
    public function reverse(): self
    {
        $str = $this->transform('strrev', $this->valueOf);
        $str->encoding = $this->encoding;

        return $str;
    }

    /**
     * Extracts a substring from the string using fixed start and optional length.
     *
     * Unlike `slice()`, this method does **not** support negative indexes.
     * It behaves like JavaScript's `substring()` method — counting only from
     * the beginning of the string.
     *
     * @param int $start The starting index (0-based).
     * @param int|null $length The number of characters to extract (null for the rest of the string).
     * 
     * @return static Returns a new instance containing the substring.
     * @see slice()
     *
     * @example - Example:
     * ```php
     * echo Str::of('abcdef')->substring(1, 3); // "bcd"
     * echo Str::of('abcdef')->substring(2);    // "cdef"
     * ```
     */
    public function substring(int $start, ?int $length = null): self 
    {
        $str = $this->transform('substr', $this->valueOf, $start, $length);
        $str->encoding = $this->encoding;

        return $str;
    }

    /**
     * Truncate the string to a specified length.
     *
     * If the string is longer than the limit, it is shortened gracefully.
     *
     * @param int $limit The maximum string length.
     * 
     * @return static Returns a new instance with the truncated string.
     *
     * @example - Example:
     * ```php
     * echo (new Str('This is too long'))->truncate(7);
     * // "This is"
     * ```
     */
    public function truncate(int $limit): self
    {
        if($this->isEmpty()){
			return new static('');
		}

        $encoding = $this->encoding();

		if ($this->length() > $limit) {
			return new static(mb_substr($this->valueOf, 0, $limit, $encoding), $encoding);
		}

        return new static($this->valueOf, $encoding);
    }

    /**
     * Extracts a section of the string by start and end indexes.
     *
     * Supports **negative indexes**, which count from the end of the string.
     * Works like JavaScript's `String.prototype.slice()`, more flexible than `slice()`,
     * since you can use both positive and negative positions.
     *
     * @param int $start The starting index (can be negative).
     * @param int|null $end The ending index (exclusive). Can be negative or null for full length.
     * 
     * @return static Returns a new `Str` instance containing the extracted substring.
     * @see substring()
     *
     * @example - Example:
     * ```php
     * echo Str::of('The quick brown fox')->slice(4, 9);  // "quick"
     * echo Str::of('你好世界')->slice(1, 3);               // "好世"
     * echo Str::of('The quick brown fox')->slice(-3);    // "fox"
     * echo Str::of('abcdef')->slice(-4, -1);             // "cde"
     * ```
     */
    public function slice(int $start, ?int $end = null): self
    {
        $length = $this->length();

        if ($start < 0) {
            $start = max(0, $length + $start);
        }

        if ($end !== null && $end < 0) {
            $end = $length + $end;
        }

        $end ??= $length;
        $end = max($start, $end);

        return $this->substring($start, $end - $start);
    }

    /**
     * Mask part of the string using a specified character.
     *
     * @param string $character The character to use for masking.
     * @param string $position The mask position ('left', 'right', 'center').
     * @return static Returns a new instance with the masked string.
     *
     * @example - Example:
     * ```php
     * echo (new Str('SensitiveData'))->mask('*', 'center');
     * // "S******ata"
     * ```
     */
    public function mask(string $character = '*', string $position = 'center'): self 
    {
        $str = $this->transform([Helpers::class, 'mask'], $this->valueOf, $character, $position);
        $str->encoding = $this->encoding;

        return $str;
    }

    /**
     * Split the string into individual words.
     *
     * Non-letter or non-number characters are treated as delimiters.
     *
     * @return array<int,string> Returns an array of string into words.
     *
     * @example - Example:
     * ```php
     * print_r((new Str('Hello, world! PHP rocks.'))->words());
     * // ["Hello", "world", "PHP", "rocks"]
     * ```
     */
    public function words(): array
    {
        if($this->isEmpty()){
            return [];
        }

        return preg_split(
            '/[^\p{L}\p{N}\']+/u', 
            trim($this->valueOf), 
            -1, 
            PREG_SPLIT_NO_EMPTY
        ) ?: [];
    }

    /**
     * Convert the string into a URL-friendly slug.
     *
     * @param string $delimiter The character used to separate words.
     * @return static Returns a new instance containing the slug.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hello World!'))->slugify();
     * // "hello-world"
     * ```
     */
    public function slugify(string $delimiter = '-'): self
    {
        $slug = strtolower(trim($this->valueOf));
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', $delimiter, $slug);
        $slug = preg_replace('/(' . preg_quote($delimiter, '/') . '){2,}/', $delimiter, $slug);

        return new static(trim($slug, $delimiter), $this->encoding);
    }

    /**
     * Wrap the string with another string before and/or after.
     *
     * @param string $before The value to prepend before string value
     * @param string $after The value to append after string value.
     * 
     * @return static Returns a new instance with wrapped content.
     *
     * @example - Example:
     * ```php
     * echo (new Str('text'))->wrap('<b>', '</b>');
     * // "<b>text</b>"
     * ```
     */
    public function wrap(string $before, string $after): self
    {
        return new static($before . $this->valueOf . $after, $this->encoding);
    }

    /**
     * Extract the text between two substrings.
     *
     * @param string $start The starting delimiter.
     * @param string $end The ending delimiter.
     * @return static Returns a new instance containing the extracted text or empty string if not found.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hello [John]'))->between('[', ']');
     * // "John"
     * ```
     */
    public function between(string $start, string $end): self
    {
        $sPos = strpos($this->valueOf, $start);
        if ($sPos === false) {
            return new static('');
        }

        $sPos += strlen($start);
        $ePos = strpos($this->valueOf, $end, $sPos);

        if ($ePos === false) {
            return new static('');
        }

        return new static(substr($this->valueOf, $sPos, $ePos - $sPos), $this->encoding);
    }

    /**
     * Randomly shuffle or select characters from the string.
     *
     * This method shuffles the string's characters or returns a random subset.
     * If `$unique` is true, duplicate characters are removed before shuffling.
     *
     * @param int|null $length Optional number of random characters to return. 
     *                         If null, the entire string is randomized.
     * @param bool $unique If true, ensures characters do not repeat in the result.
     * 
     * @return static Returns a new instance containing the randomized string.
     * 
     * @example - Examples:
     * ```php
     * $str = new Str('abcdefa');
     * echo $str->shuffle();         // "dfabcea" (varies)
     * echo $str->shuffle(3);        // "bfa" (varies)
     * echo $str->shuffle(3, true);  // "cde" (no repeats)
     * echo $str->shuffle(unique: true); // "abcdef" (duplicates removed, then shuffled)
     * ```
     */
    public function shuffle(?int $length = null, bool $unique = false): self
    {
        $chars = '';
        if(!$this->isEmpty()){
            $chars = preg_split('//u', $this->valueOf, -1, PREG_SPLIT_NO_EMPTY);

            if (empty($chars)) {
                return new static('');
            }

            if ($unique) {
                $chars = array_values(array_unique($chars));
            }
            
            shuffle($chars);

            if ($length !== null) {
                $chars = array_slice($chars, 0, min($length, count($chars)));
            }

            $chars = implode('', $chars);
        }

        return new static($chars, $this->encoding);
    }

    /**
     * Concatenate one or more strings to the current string.
     *
     * @param Stringable|string ...$strings Arguments of strings or Stringable objects to append.
     * 
     * @return static Returns a new `Str` instance containing the concatenated result.
     *
     * @example - Example:
     * ```php
     * $a = Str::of('Hello');
     * $b = Str::of('World');
     * echo $a->concat(' ', $b); // "Hello World"
     * ```
     */
    public function concat(Stringable|string ...$strings): self
    {
        $concat = $this->valueOf;

        foreach ($strings as $str) {
            $concat .= (string) $str;
        }

        return new static($concat, $this->encoding);
    }

    /**
     * Check if the string equals another string.
     *
     * @param Stringable|string $value The string to compare with.
     * @param bool $caseSensitive Compare case-sensitively or not (default: `true`).
     * 
     * @return bool Returns true if equal, false otherwise.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hello'))->equals('hello', false);
     * // true
     * ```
     */
    public function equals(Stringable|string $value, bool $caseSensitive = true): bool
    {
        if($this->isEmpty() && $value !== ''){
            return false;
        }

        return $caseSensitive
            ? strcmp($this->valueOf, (string) $value) === 0
            : strcasecmp($this->valueOf, (string) $value) === 0;
    }

    /**
     * Trim whitespace or custom characters from both ends.
     *
     * @param string $characters Optional characters to trim.
     * 
     * @return static Returns a new instance with trimmed string.
     *
     * @example - Example:
     * ```php
     * echo (new Str('  hello  '))->trim();
     * // "hello"
     * ```
     */
    public function trim(string $characters = " \t\n\r\0\x0B"): self 
    {
        return $this->transform('trim', $this->valueOf, $characters);
    }

    /**
     * Trim from the beginning only.
     *
     * @param string $characters Optional characters to trim.
     * 
     * @return static Returns a new instance with trimmed start.
     */
    public function trimStart(string $characters = " \t\n\r\0\x0B"): self 
    {
        return $this->transform('ltrim', $this->valueOf, $characters);
    }

    /**
     * Trim from the end only.
     *
     * @param string $characters Optional characters to trim.
     * 
     * @return static Returns a new instance with trimmed end.
     */
    public function trimEnd(string $characters = " \t\n\r\0\x0B"): self 
    {
        return $this->transform('rtrim', $this->valueOf, $characters);
    }

    /**
     * Check if the string contains another string.
     *
     * @param Stringable|string $needle The substring to find.
     * @param bool $caseSensitive Whether to match case-sensitively (default: `true`).
     * 
     * @return bool Returns true if found, false otherwise.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hello'))->contains('ell');
     * // true
     * ```
     */
    public function contains(Stringable|string $needle, bool $caseSensitive = true): bool 
    {
        return $this->filter('str_contains', (string) $needle, $caseSensitive);
    }

    /**
     * Alias for {@see contains()}.
     *
     * @param Stringable|string $needle The substring to find.
     * @param bool $caseSensitive Whether to match case-sensitively (default: `true`).
     * 
     * @return bool Return true if found, false otherwise.
     */
    public function includes(Stringable|string $needle, bool $caseSensitive = true): bool
    {
        return $this->filter('str_contains', (string) $needle, $caseSensitive);
    }

    /**
     * Test if the string matches a regex pattern.
     *
     * @param string $pattern The regex pattern.
     * 
     * @return bool Returns true if it matches, false otherwise.
     *
     * @example - Example:
     * ```php
     * echo (new Str('abc123'))->matches('/\d+/');
     * // true
     * ```
     */
    public function matches(string $pattern): bool 
    {
        return $this->filter('preg_match', $pattern);
    }

    /**
     * Find the position of the first match for a regex pattern.
     *
     * @param string $pattern Regular expression to search for.
     * 
     * @return int Returns the index of the first match, or -1 if not found.
     *
     * @example - Example:
     * ```php
     * $str = Str::of("I think Ruth's dog is cuter than your dog!");
     * $pos = $str->search('/[^\w\s\']/'); // 41
     * echo $str[$pos]; // "!"
     * ```
     */
    public function search(string $pattern): int
    {
        if (preg_match($pattern, $this->valueOf, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[0][1];
        }

        return -1;
    }

    /**
     * Get the position of the first occurrence of a substring within the string.
     *
     * This method works like PHP's native `strpos()`, but allows optional
     * case-insensitive search when `$caseSensitive` is set to `false`.
     *
     * @param Stringable|string $needle The substring to find within the string.
     * @param int $offset Optional. The position to start searching from (Default: `0`).
     * @param bool $caseSensitive Optional. Whether the search should be case-sensitive (Default: `true`).
     * 
     * @return int|false Returns the position of the first match, or `false` if not found.
     * 
     * @example - Example:
     * ```php
     * $str = Str::of('Hello World');
     * 
     * echo $str->position('World');           // 6
     * echo $str->position('world', 0, false); // 6 (case-insensitive)
     * echo $str->position('PHP');             // false
     * ```
     */
    public function position(Stringable|string $needle, int $offset = 0, bool $caseSensitive = true): int|bool
    {
        if ($needle === '') {
            return false;
        }

        return $caseSensitive
            ? strpos($this->valueOf, (string) $needle, $offset)
            : strpos(strtolower($this->valueOf), strtolower((string) $needle), $offset);
    }

    /**
     * Normalize the string to a specified Unicode form.
     *
     * This method ensures that visually identical Unicode strings are represented 
     * consistently in memory (useful for comparisons, hashing, or storage).
     *
     * @param string $form The normalization form (Default: `NFC`).
     *        Accepted values: `NFC`, `NFD`, `NFKC`, `NFKD`.
     * 
     * @return static Returns a new normalized string instance.
     * @throws RuntimeException If intl extension is not available.
     *
     * @example - Example:
     * ```php
     * // Characters with combining accents may look identical but differ in code units
     * $str = new Str("e\u{0301}");   // "e" + "́"
     * 
     * echo $str->normalize('NFC')
     *      ->toString();  // "é"
     * ```
     * > **Note:**
     * > Requires the PHP `intl` extension.
     */
    public function normalize(string $form = 'NFC'): self
    {
        if (!class_exists('Normalizer')) {
            throw new RuntimeException('The "intl" extension is required for normalize().');
        }

        $formConst = match (strtoupper($form)) {
            'NFD'  => Normalizer::FORM_D,
            'NFKC' => Normalizer::FORM_KC,
            'NFKD' => Normalizer::FORM_KD,
            default => Normalizer::FORM_C,
        };

        $normalized = Normalizer::normalize($this->valueOf, $formConst);
        return new static(($normalized !== false) ? $normalized : $this->valueOf);
    }

    /**
     * Convert the string to lowercase.
     *
     * @return static Returns a new instance with lowercase text.
     */
    public function toLowerCase(): self 
    {
        return $this->transform('strtolower', $this->valueOf);
    }

    /**
     * Convert the string to uppercase.
     *
     * @return static Returns a new instance with uppercase text.
     */
    public function toUpperCase(): self 
    {
        return $this->transform('strtoupper', $this->valueOf);
    }

    /**
     * Convert to camelCase.
     *
     * @return static Returns a new instance in camelCase format.
     */
    public function toCamelCase(): self
    {
        return $this->transform('\Luminova\Funcs\camel_case', $this->valueOf);
    }

    /**
     * Convert to PascalCase.
     *
     * @return static Returns a new instance in PascalCase format.
     */
    public function toPascalCase(): self
    {
        return $this->transform('\Luminova\Funcs\pascal_case', $this->valueOf);
    }

    /**
     * Convert to snake_case.
     *
     * @return static Returns a new instance in snake_case format.
     */
    public function toSnakeCase(): self
    {
        return $this->transform('\Luminova\Funcs\snake_case', $this->valueOf);
    }

    /**
     * Convert to kebab-case.
     *
     * @param bool $toLowerCase Whether to force lowercase.
     * 
     * @return static Returns a new instance in kebab-case format.
     */
    public function toKebabCase(bool $toLowerCase = false): self
    {
        return $this->transform('\Luminova\Funcs\kebab_case', $this->valueOf, $toLowerCase);
    }

    /**
     * Ensure the string is well-formed UTF-8.
     * 
     * Invalid byte sequences will be replaced with the Unicode replacement
     * character (U+FFFD).
     *
     * @return static A new well-formed string instance.
     *
     * @example - Example:
     * ```php
     * echo Str::of("Hello\x80World")->toWellFormed(); // "Hello�World"
     * ```
     */
    public function toWellFormed(): self
    {
        if ($this->isWellFormed()) {
            return new static($this->valueOf, 'UTF-8');
        }

        $str = $this->transform('mb_convert_encoding', $this->valueOf, 'UTF-8', 'UTF-8');
        $str->encoding = 'UTF-8';

        return $str;
    }

    /**
     * Get the transformed string value.
     *
     * @return string Return the final value of string object.
     *
     * @example - Example:
     * ```php
     * echo (new Str('Hello'))->toString(); // "Hello"
     * ```
     */
    public function toString(): string 
    {
        return $this->valueOf;
    }

    /**
     * Convert the string to a specific encoding.
     *
     * @param string $toEncoding The target encoding (e.g., 'UTF-8', 'ISO-8859-1').
     * @param string|null $fromEncoding Optional source encoding. If null, detects automatically.
     *
     * @return static Returns a new `Str` instance in the target encoding.
     *
     * @example - Example:
     * ```php
     * echo Str::of('Héllo 🌍')->toEncoding('ISO-8859-1'); // "H?llo ?"
     * echo Str::of('Héllo 🌍')->toEncoding('UTF-8');      // "Héllo 🌍"
     * ```
     */
    public function toEncoding(string $toEncoding, ?string $fromEncoding = null): static
    {
        $converted = null;

        if(!$this->isEmpty()){
            $fromEncoding ??= $this->encoding();
            $converted = @mb_convert_encoding($this->valueOf, $toEncoding, $fromEncoding);

            if(
                !$converted && 
                ($safe = @mb_convert_encoding($this->valueOf, 'UTF-8', 'UTF-8'))
            ){
                $converted = @mb_convert_encoding($safe, $toEncoding, 'UTF-8');
            }
        }

        $str = new static($converted ?: '');
        $str->encoding = $converted ? $toEncoding : 'UTF-8';

        return $str;
    }

    /**
     * Check if the string ends with a specific substring.
     * 
     * If `$position` is provided, the string is treated as if it were only that long.
     *
     * @param Stringable|string $needle The substring to check.
     * @param int|null $position The end position at which is expected to be found (default: `null` string length).
     * @param bool $caseSensitive Whether to match case-sensitively (default: `true`).
     * 
     * @return bool Returns true if the string ends with the given substring.
     * 
     * @example - Example:
     * ```php
     * Str::of("Hello world")->endsWith("world"); // true
     * Str::of("Hello world")->endsWith("World", caseSensitive: false); // true (case-insensitive)
     * Str::of("Hello world")->endsWith("Hello", 5); // true (checks "Hello")
     * ```
     */
    public function endsWith(
        Stringable|string $needle, 
        ?int $position = null,
        bool $caseSensitive = true
    ): bool 
    {
        return $this->filter(
            'str_ends_with', 
            (string) $needle, 
            $caseSensitive,
            ($position === null) 
                ? null
                : substr($this->valueOf, 0, $position)
        );
    }

    /**
     * Check if the string starts with a specific substring.
     * 
     * If `$position` is provided, the check starts from that character index.
     *
     * @param Stringable|string $needle The substring to check.
     * @param int|null $position The start position at which is expected to be found (default: `null` 0).
     * @param bool $caseSensitive Whether to match case-sensitively (default: `true`).
     * 
     * @return bool Returns true if the string starts with the given substring.
     * 
     * @example - Example:
     * ```php
     * Str::of("Hello world")->startsWith("Hello"); // true
     * Str::of("Hello world")->startsWith("hello", caseSensitive: false); // true (case-insensitive)
     * Str::of("Hello world")->startsWith("world", 6); // true (starts at index 6)
     * ```
     */
    public function startsWith(
        Stringable|string $needle, 
        ?int $position = null,
        bool $caseSensitive = true
    ): bool 
    {
        return $this->filter(
            'str_starts_with', 
            (string) $needle, 
            $caseSensitive,
            ($position === null) 
                ? null
                : substr($this->valueOf, $position)
        );
    }

    /**
     * Get the character at a specific index.
     *
     * Supports multibyte characters and returns a `Str` instance.
     * Negative or out-of-range indexes return an empty string.
     *
     * @param int $index 0-based position of the character.
     *
     * @return static Return a new `Str` instance containing the character, or empty if out of range.
     * @see at()
     *
     * @example - Example:
     * ```php
     * echo Str::of('Hello')->charAt(1);   // "e"
     * echo Str::of('你好')->charAt(1);      // "好"
     * echo Str::of('Hello')->charAt(10);  // ""
     * ```
     */
    public function charAt(int $index): string
    {
        if ($index < 0 || $index >= $this->length()) {
            return new static('');
        }

        return new static(mb_substr($this->valueOf, $index, 1, 'UTF-8'));
    }

    /**
     * Get the UTF-16 code unit at a specific index.
     *
     * @param int $index The character position (0-based).
     * 
     * @return int|null Returns the 16-bit code unit or null if out of range.
     *
     * @example - Example:
     * ```php
     * echo Str::of("☃★♲")->charCodeAt(1);   // 9733 (★)
     * echo Str::of("😀")->charCodeAt(0);     // 55357 (high surrogate)
     * echo Str::of("😀")->charCodeAt(1);     // 56832 (low surrogate)
     * ```
     */
    public function charCodeAt(int $index): ?int
    {
        $char = $this->charAt($index);
        if ($char === '') {
            return null;
        }

        $utf16 = mb_convert_encoding($char, 'UTF-16BE', 'UTF-8');

        if(!$utf16){
            return mb_ord($char, 'UTF-8') ?: null;
        }

        return unpack('n', $utf16)[1] ?? null;
    }

    /**
     * Get the Unicode code point at the given position.
     * 
     * This differs from `charCodeAt()` in that it returns the full Unicode
     * code point, even for surrogate pairs (e.g., emojis or rare symbols).
     *
     * @param int $index The position of the character.
     * 
     * @return int|null Returns the Unicode code point, or null if out of range.
     *
     * @example - Example:
     * ```php
     * echo Str::of('A')->codePointAt(0);     // 65
     * echo Str::of('你')->codePointAt(0);     // 20320
     * echo Str::of('😀')->codePointAt(0);     // 128512
     * ```
     */
    public function codePointAt(int $index): ?int
    {
        $char = $this->charAt($index);
        if ($char === '') {
            return null;
        }

        $utf16 = mb_convert_encoding($char, 'UTF-16BE', 'UTF-8');

        if(!$utf16){
            return mb_ord($char, 'UTF-8') ?: null;
        }

        $bytes = unpack('n*', $utf16);

        if (count($bytes) === 1) {
            return $bytes[1];
        }

        $high = $bytes[1];
        $low  = $bytes[2];

        return (($high - 0xD800) << 10) + ($low - 0xDC00) + 0x10000;
    }

    /**
     * Check whether the string is a well-formed UTF-8 sequence.
     *
     * This method verifies that all bytes in the string are valid UTF-8 characters.
     * It does not modify the string — it only checks for encoding validity.
     *
     * @return bool Returns `true` if the string is a valid UTF-8 sequence,
     *              or `false` if it contains invalid or malformed bytes.
     *
     * @example - Example:
     * ```php
     * echo Str::of('Hello')->isWellFormed();          // true
     * echo Str::of("Hello\xC0World")->isWellFormed(); // false
     * ```
     *
     * @see toWellFormed()
     */
    public function isWellFormed(): bool
    {
        return $this->isEmpty() || mb_check_encoding($this->valueOf, 'UTF-8');
    }

    /**
     * Check if the string is empty.
     *
     * @return bool Returns true if empty, false otherwise.
     */
    public function isEmpty(): bool 
    {
        return trim($this->valueOf) === '';
    }

    /**
     * Check if the string is numeric.
     *
     * @return bool Returns true if numeric, false otherwise.
     */
    public function isNumeric(): bool 
    {
        return is_numeric($this->valueOf);
    }

    /**
     * Check if value is a hexadecimal representation.
     * 
     * @return bool Return true if value is a hexadecimal representation.
     */
    public function isHex(): bool 
    {
        if ($this->isEmpty()) {
            return false;
        }

        return (bool) preg_match('/^(0x)?[0-9a-fA-F]+$/', (string) $this->valueOf);
    }

    /**
     * Check if a character exists at a given index.
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && $offset >= 0 && $offset < $this->length();
    }

    /**
     * Get the character at a specific position (UTF-8 safe).
     */
    public function offsetGet(mixed $offset): ?string
    {
        $this->assert($offset);

        return $this->offsetExists($offset)
            ? mb_substr($this->valueOf, $offset, 1, $this->encoding())
            : null;
    }

    /**
     * Set a character at a specific position.
     *
     * @note Replaces a single character (UTF-8 safe).
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->assert($offset);
        $encoding = $this->encoding();

        $before = mb_substr($this->valueOf, 0, $offset, $encoding);
        $after  = mb_substr($this->valueOf, $offset + 1, null, $encoding);
        $this->valueOf = $before . (string) $value . $after;
    }

    /**
     * Unset a character at a specific position.
     *
     * @note Removes the character entirely.
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->assert($offset);
        $encoding = $this->encoding();

        $before = mb_substr($this->valueOf, 0, $offset, $encoding);
        $after  = mb_substr($this->valueOf, $offset + 1, null, $encoding);
        $this->valueOf = $before . $after;
    }

    /**
     * Assert offset is int.
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    public function assert(mixed $offset): void
    {
        if (!is_int($offset)) {
            throw new InvalidArgumentException('String offset must be an integer.');
        }
    }

    /**
     * Internal utility for transforming string values.
     *
     * @param callable $function The function to apply.
     * @param mixed ...$arguments Arguments to pass to the function.
     * 
     * @return static Returns a new instance with the transformed string.
     */
    private function transform(callable $function, mixed ...$arguments): self 
    {
        return new static($function(...$arguments) ?: '');
    }

    /**
     * Internal helper for boolean-type string filters.
     *
     * @param callable $function The filter function name.
     * @param string $needle The substring or pattern.
     * @param bool $caseSensitive Case sensitivity flag.
     * 
     * @return bool True or false based on the function result.
     */
    private function filter(
        callable $function, 
        string $needle, 
        bool $caseSensitive = true,
        ?string $value = null
    ): bool 
    {
        $value ??= $this->valueOf;

        if ($value === '' && $needle === '') {
            return false;
        }

        if ($function === 'preg_match') {
            return (bool) preg_match($needle, $value);
        }

        return $caseSensitive 
            ? (bool) $function($value, $needle)
            : (bool) $function(strtolower($value), strtolower($needle));
    }
}