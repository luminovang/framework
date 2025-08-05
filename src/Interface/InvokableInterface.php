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
namespace Luminova\Interface;

/**
 * Interface InvokableInterface
 *
 * Defines a contract for classes that can be invoked as functions.
 * Any class implementing this interface must implement the magic 
 * method __invoke(), allowing instances to be called like functions.
 */
interface InvokableInterface
{
    /**
     * Invokes the class instance as a callable.
     *
     * @param mixed ...$arguments One or more arguments passed dynamically to the invokable instance.
     * 
     * @return mixed Return the result returned by the class when invoked.
     */
    public function __invoke(mixed ...$arguments): mixed;
}