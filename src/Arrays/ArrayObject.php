<?php 
class ArrayObject {

    public static function getColumns(string $property, array|object $values): array 
    {
        if (is_array($values)) {
            return array_column($values, $property);
        }

        return array_map(function($object) use ($property) {
            return $object->$property;
        }, (array) $values);
    }

}