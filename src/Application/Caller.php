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
namespace Luminova\Application;

use \Luminova\Exceptions\RuntimeException;
use \RecursiveCallbackFilterIterator;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \FilesystemIterator;
use \SplFileInfo;
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
     * @param string|null $namespace Optional provide target class namespace (default: null).
     * 
     * @return array<int,class-string> Return an array of fully qualified class names that extend the base class.
     */
    public static function extenders(string $baseClass, string $directory, ?string $namespace = null): array 
    {
        $classes = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
                fn (SplFileInfo $entry) => $entry->isFile() && $entry->getExtension() === 'php'
            )
        );

        foreach ($files as $file) {
            if($namespace === null){
                require_once $file->getRealPath();
            }else{
                try{
                    $fileName = pathinfo($file->getBasename(), PATHINFO_FILENAME);
                    $class = new ReflectionClass("{$namespace}\\{$fileName}");
                }catch(ReflectionException){
                    continue;
                }

                if($class->isSubclassOf($baseClass)){
                    $classes[] = $class->getName();
                }
            }
        }

        if($namespace === null){
            foreach (get_declared_classes() as $class) {
                if (is_subclass_of($class, $baseClass)) {
                    $classes[] = $class;
                }
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
     * @return int|array<int,string> Return all called methods in the given and their response.
     * @throws RuntimeException If failed to instantiate class.
    */
    public static function call(string|object $class, bool $return = false): int|array
    {
        try{
            $instance = new ReflectionClass($class);
            $methods = $instance->getMethods(ReflectionMethod::IS_PUBLIC);
            $calls = [];
            $count = 0;

            foreach ($methods as $method) {
                if ($method->class !== $instance->getName()) {
                    continue;
                }

                $name = $method->name;
                if($return){
                    $calls[] = $name;
                }else{
                    $class->$name();
                    $count++;
                }
            }

            return $return ? $calls : $count;
        }catch(RuntimeException|ReflectionException $e){
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }
}