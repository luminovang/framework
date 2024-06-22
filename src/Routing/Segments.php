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

class Segments 
{
    /**
     * @var array $segments
    */
    private array $segments = [];

    /**
     * Initializes class.
     * 
     * @param array<int,string> Array list of url segments.
    */
    public function __construct(array $segments)
    {
        $this->segments = $segments;
    }

    /**
     * Get the current view uri segment by index position.
     * 
     * @param int $index Position index to return segment
     * 
     * @return string view segment
    */
    public function index(int $index = 0): string
    {
        return $this->segments[$index] ?? '';
    }

    /**
     * Get first segment of current view uri.
     * 
     * @return string First url segment
    */
    public function first(): string
    {
        $segments = $this->segments;

        return reset($segments);
    }
    
    /**
     * Get the last segment of current view uri.
     * 
     * @return string Current uri segment 
    */
    public function current(): string 
    {
        $segments = $this->segments;

        return end($segments);
    }

    /**
     * Get the current view segment before last segment.
     * 
     * @return string
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
     * @return array<int,string> Array list of url segments
    */
    public function segments(): array 
    {
        return $this->segments;
    }
}