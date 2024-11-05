<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Routing;

use \Countable;

class Segments implements Countable
{
    /**
     * Initializes URL segments class.
     * 
     * @param array<int,string> A list array of URL segments.
     */
    public function __construct(private array $segments = [])
    {
    }

    /**
     * Retrieve the number of segments.
     * 
     * @return int Return the number of URL segments
     */
    public function count(): int 
    {
        return count($this->segments);
    }

    /**
     * Get the current view uri segment by index position.
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
     * Get first segment of current view uri.
     * 
     * @return string Return the first URL segment.
     */
    public function first(): string
    {
        $segments = $this->segments;
        return reset($segments);
    }
    
    /**
     * Get the last segment as the current view URL segment.
     * 
     * @return string Return the current URL segment.
     */
    public function current(): string 
    {
        $segments = $this->segments;
        return end($segments);
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