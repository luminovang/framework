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
namespace Luminova\Foundation\Core;

use \Luminova\Utility\IP;
use \Luminova\Common\{Helpers, Maths};
use \Luminova\Utility\Storage\FileManager;

/**
 * @deprecated 3.6.8 This class has been deprecated will be removed in future
 */
abstract class Functions extends Helpers
{
    /**
     * Method instances.
     * 
     * @var array $instance
     */
    private static array $instances = [];

    /**
     * Initialize or return a shared an instance of the IP address class.
     *
     * @return IP Returns a ip address class instance.
     */
    public static final function ip(): IP
    {
        return self::$instances['ip'] ??= new IP();
    }

    /**
     * Initialize or return a shared an instance of the Files class.
     *
     * @return FileManager Returns a file class instance.
     */
    public static final function files(): FileManager
    {
        return self::$instances['files'] ??= new FileManager();
    }

    /**
     * Initialize or return a shared an instance of the math class.
     *
     * @return Maths Returns a math class instance.
     */
    public static final function math(): Maths
    {
        return self::$instances['math'] ??= new Maths();
    }
}