<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Database\Results;

use \stdClass;
use \Luminova\Database\Drivers\DriversInterface;
use \ReflectionClass;
use \ReflectionException;

class Statements
{
    /**
     * @var DriversInterface|null $statement
    */
    private ?DriversInterface $statement = null;

    /**
     * Initialize with executed statement
     * 
     * @param DriversInterface $statement
    */
    public function __construct(DriversInterface $statement)
    {
        $this->statement = $statement;
    }

    /**
     * Fetches all rows as an array of objects.
     *
     * @return mixed The array of result objects.
     */
    public function getAll(): mixed 
    {
        return  $this->statement->getAll();
    }

     /**
     * Fetches a single row as an object.
     *
     * @return mixed The result object or false if no row is found.
     */
    public function getOne(): mixed 
    {
        return  $this->statement->getOne();
    }

    /**
     * Fetches all rows as a 2D array of integers.
     *
     * @return int integers.
    */
    public function getInt(): int 
    {
        return  $this->statement->getInt();
    }

    /**
     * Fetches all rows as a stdClass object.
     *
     * @return stdClass The stdClass object containing the result rows.
     */
    public function getObject(): stdClass 
    {
        return  $this->statement->getObject();
    }

    /**
     * Fetches all rows as a array.
     *
     * @return array The array containing the result rows.
     */
    public function getArray(): array 
    {
        return  $this->statement->getArray();
    }


    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @return string The last insert ID.
     */
    public function getLastId(): string 
    {
        return  $this->statement->getLastInsertId();
    }

    /**
     * Returns the number of rows affected by the last statement execution.
     *
     * @return int The number of rows.
    */
    public function getCount(): int 
    {
        return  $this->statement->rowCount();
    }

    /**
     * Get result 
     * 
     * @param string $type [object, array]
     * 
     * @return stdClass|array The result
    */
    public function getResult(string $type = 'object'): stdClass|array 
    {
        return  $this->statement->getResult($type);

    }

    /**
     * Get result 
     * 
     * @param string $mapping [all, one, total, object, array, lastId, count or className]
     * 
     * @return mixed
    */
    public function get(string $mapping = 'all'): mixed 
    {
        return match ($mapping) {
            'all' => $this->statement->getAll(),
            'one' => $this->statement->getOne(),
            'total' => $this->statement->getInt(),
            'object' => $this->statement->getObject(),
            'array' => $this->statement->getArray(),
            'lastId' => $this->statement->getLastInsertId(),
            'count' => $this->statement->rowCount(),
            default => $this->getClass($this->statement->getObject(), $mapping)
        };
    }

    /**
     * Get result mapped to class
     * 
     * @example class MyClass {
     *  public $property1;
     *  public $property2;
     *  public function __construct(object $data) {
     *      $this->property1 = $data->property1 ?? null;
     *      $this->property2 = $data->property2 ?? null;
     *  }
     * }
     * @param string $class class with namespace 
     * 
     * @return object|null $object class instance or null 
    */
    private function getClass(stdClass $object, string $className = ''): ?object
    {
        $object = null;
        if ($className !== '' && class_exists($className) && $object !== null) {
            try {
                $class = new ReflectionClass($className);
                if ($class->isInstantiable()){
                    $object = $class->newInstanceArgs([$object]);
                }
            } catch (ReflectionException $e) {
                logger('debug', $e->getMessage(), [
                    'content' => self::class,
                    'className' => $className
                ]);
            }
        }
        
        return $object;
    }
}