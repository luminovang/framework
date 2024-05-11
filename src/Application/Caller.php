<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Application;

use \Luminova\Exceptions\RuntimeException;
use \ReflectionClass;
use \ReflectionException;
use \ReflectionMethod;

class Caller 
{
      /**
     * Get all classes that extends a base class 
     * 
     * @param class-string $baseClass The base class to check 
     * 
     * @return array Return array of classes that extend the base class.
    */
    public static function extenders(string $baseClass): array 
    {
        $subClasses = [];
        $allClasses = get_declared_classes();

        foreach ($allClasses as $name) {
            if (is_subclass_of($name, $baseClass)) {
                $subClasses[] = $name;
            }
        }

        return $subClasses;
    }
    
    /**
     * Call all public methods within a given class.
     * 
     * @param class-string|class-object $class class name or instance of a class.
     * @param bool $return return type.
     * 
     * @return int|array<string,string>  Return all called methods in the given and their response.
     * @throws RuntimeException If failed to instantiate class.
    */
    public static function call(string|object $class, bool $return = false): int|array
    {
        try{
            $methods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);
            $calls = [];
            $count = 0;

            foreach ($methods as $method) {
                if ($method->class === get_class($class)) {
                    $name = $method->name;
                    if($return){
                        $calls[$name] = $class->$name();
                    }else{
                        $class->$name();
                        $count++;
                    }
                }
            }

            if($return){
                return $calls;
            }

            return $count;
        }catch(RuntimeException | ReflectionException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }
}