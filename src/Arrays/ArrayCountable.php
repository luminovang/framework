<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Arrays;
use \Countable;

class ArrayCountable implements Countable 
{
    /**
     * @var array $array
    */
    private array $array = [];

    /**
    * @param array $array
    */
    public function __construct(array $array) 
    {
        $this->array = $array;
    }

    /**
    * @return int array count
    */
    public function count(): int 
    {
        return count($this->array);
    }

     /**
     * Check if array is a nested array
     * 
     * @return bool 
    */
    public function isNested(): bool 
    {
        foreach ($this->array as $value) {
            if (is_array($value)) return true;
        }

        return false;
    }
}
