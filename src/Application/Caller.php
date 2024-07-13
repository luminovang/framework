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
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \ReflectionClass;
use \ReflectionException;
use \ReflectionMethod;

final class Caller 
{
    /**
     * Retrieve all classes that extend a specified base class within a given directory.
     * 
     * @param class-string $baseClass The fully qualified name of the base class to check.
     * @param string $directory The directory to search for classes that extend the base class.
     * 
     * @return array<int,class-string> An array of fully qualified class names that extend the base class.
     */
    public static function extenders(string $baseClass, string $directory): array 
    {
        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getRealPath();
            }
        }

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, $baseClass)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
    
    /**
     * Call all public methods within a given class.
     * 
     * @param class-string<T>|class-object<T> $class class name or instance of a class.
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