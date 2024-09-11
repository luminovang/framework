<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Core;

use \Luminova\Functions\IP;
use \Luminova\Storages\FileManager;
use \Luminova\Functions\Tor;
use \Luminova\Functions\Maths;
use \Luminova\Functions\Func;

abstract class CoreFunction extends Func
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
     * Initialize or return a shared an instance of the tor detector class.
     *
     * @return Tor Returns a tor detector class instance.
     */
    public static final function tor(): Tor
    {
        return self::$instances['tor'] ??= new Tor();
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