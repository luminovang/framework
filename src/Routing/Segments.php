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
use function \Luminova\Funcs\{array_last, array_first};

class Segments implements Countable
{
    /**
     * Initializes URI segments class.
     * 
     * @param array<int,string> A list array of URI segments.
     * @see Router::getSegment()
     */
    public function __construct(private array $segments = []) {}

    /**
     * Retrieve the number of segments.
     * 
     * @return int Return the number of URI segments
     */
    public function count(): int 
    {
        return count($this->segments);
    }

    /**
     * Get the current view URI segment by index position.
     * 
     * @param int $index Position index to return segment
     * 
     * @return string Return view URL segment.
     */
    public function index(int $index = 0): string
    {
        return $this->segments[$index] ?? '';
    }

    /**
     * Get request URI prefix from current view URIs.
     * 
     * @return string Return the URI prefix.
     */
    public function prefix(): string
    {
        return array_first($this->segments);
    }

    /**
     * @deprecated Since 3.6.6, Use prefix() instead.
     */
    public function first(): string
    {
        return $this->prefix();
    }
    
    /**
     * Get the last segment as the current view URL segment.
     * 
     * @return string Return the current URL segment.
     */
    public function current(): string 
    {
        return array_last($this->segments);
    }

    /**
     * Get the current view segment before last segment.
     * 
     * @return string Return the the URL segment before the last.
     */
    public function previous(): string 
    {
        if (count($this->segments) > 1) {
            return $this->segments[count($this->segments) - 2];
        }

        return '';
    }

    /**
     * Get the current view segments as array.
     * 
     * @return array<int,string> Return an array list of URL segments.
     */
    public function segments(): array 
    {
        return $this->segments;
    }
}