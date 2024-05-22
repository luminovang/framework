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

class Arrays
{
    private array $array = [];

    public function __construct(array $array = [])
    {
        $this->array = $array;
    }

    public function get(): array 
    {
        return $this->array;
    }
   
    /**
     * Check if array is a nested array
     * 
     * @return bool 
    */
    public function isNested(): bool 
    {
        return is_nested($this->array);
    }
}
