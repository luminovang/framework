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

class ArrayObject 
{
    /**
     * @param string $property
     * @param array|object $values
     * 
     * @return array
    */
    public function getColumns(string $property, array|object $values): array 
    {
        if (is_array($values)) {
            return array_column($values, $property);
        }

        return array_map(function($object) use ($property) {
            return $object->$property;
        }, (array) $values);
    }
}