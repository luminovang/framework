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
namespace Luminova\Routing;

use \Countable;
use \Stringable;

class Segments implements Countable, Stringable
{
    /**
     * Initialize the URI segments collection.
     *
     * @param array<int,string> $segments List of URI segments.
     *
     * @see Router::getSegment()
     */
    public function __construct(protected array $segments = []) {}

    /**
     * Determine whether a URI segment at the given position matches a value.
     *
     * Leading and trailing slashes are ignored when comparing the values.
     *
     * @param int $position The zero-based segment position.
     * @param string $segment The segment value to compare.
     *
     * @return bool True if the segment exists and matches the given value.
     */
    public function is(int $position, string $segment): bool
    {
        $path = $this->position($position);

        if ($path === null) {
            return false;
        }

        return trim($path, '/') === trim($segment, '/');
    }

    /**
     * Determine whether a URI segment exists at the given position.
     *
     * @param int $position The zero-based segment position.
     *
     * @return bool True if a segment exists at the given position.
     */
    public function has(int $position): bool
    {
        return isset($this->segments[$position])
         || ($this->segments[$position] ?? null) === '';
    }

    /**
     * Determine whether the URI contains the given segment.
     *
     * @param string $segment The segment value to search for.
     *
     * @return bool True if the segment exists in the URI.
     */
    public function contains(string $segment): bool
    {
        return in_array(trim($segment, '/'), $this->segments, true);
    }

    /**
     * Get the number of URI segments.
     *
     * @return int The number of URI segments.
     */
    public function count(): int
    {
        return count($this->segments);
    }

    /**
     * Get a URI segment by its zero-based position.
     *
     * @param int $index The segment position.
     *
     * @return string|null The URI segment, or null if the position does not exist.
     */
    public function position(int $index = 0): ?string
    {
        return $this->segments[$index] ?? null;
    }

    /**
     * Get the first URI segment as the request URI prefix.
     *
     * @return string|null The first URI segment, or null if no segments exist.
     */
    public function first(): ?string
    {
        return array_first($this->segments);
    }

    /**
     * Get the URI segment prefix.
     * 
     * Alias: {@see self::first()}
     *
     * @return string|null The first URI segment, or null if no segments exist.
     */
    public function prefix(): ?string
    {
        return $this->first();
    }

    /**
     * Get the last URI segment.
     *
     * @return string|null The last URI segment, or null if no segments exist.
     */
    public function last(): ?string
    {
        return array_last($this->segments);
    }

    /**
     * Get the URI segment immediately before the last segment.
     *
     * @return string|null The previous URI segment, or null if none exists.
     */
    public function previous(): ?string
    {
        if (count($this->segments) > 1) {
            return $this->segments[count($this->segments) - 2];
        }

        return null;
    }

    /**
     * Get all URI segments.
     *
     * @return array<int,string> The list of URI segments.
     */
    public function all(): array
    {
        return $this->segments;
    }

    /**
     * Convert the segments to URI path.
     *
     * @return string The URI path represented by the segments.
     */
    public function toString(): string
    {
        return '/' . implode('/', $this->segments);
    }

    /**
     * Convert the URI segments into a string representation.
     *
     * @return string The URI path represented by the segments.
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}