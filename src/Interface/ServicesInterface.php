<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;


interface ServicesInterface
{
    /**
     * Bootstrap all your application services 
     * Add each service in a new line within the bootstrap method 
     * 
     * @example static::addService(ServiceTest::class, "Test Argument");
     * @example static::addService(ServiceTest::class, "Test Argument", true, false);
     * 
     * @return void 
    */
    public static function bootstrap(): void;
}