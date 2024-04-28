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

class Foundation 
{
    /**
    * @var string VERSION version name
    */
    public const VERSION = '2.9.8';

    /**
     * Minimum required php version.
     * 
    * @var string MIN_PHP_VERSION 
    */
    public const MIN_PHP_VERSION = '8.0';

    /**
     * @var string $version command line tool version
    */
    public const NOVAKIT_VERSION = '2.9.7';

    /**
     * Get the framework copyright information
     *
     * @return string Return framework copyright message.
     * @internal
    */
    public static function copyright(): string
    {
        return 'PHP Luminova (' . self::VERSION . ')';
    }

    /**
     * Get the framework version name or code.
     * 
     * @param bool $integer Return version code or version name (default: name).
     * 
     * @return string|int Return version name or code.
    */
    public static function version(bool $integer = false): string|int
    {
        return $integer ? (int) strict(self::VERSION, 'int') : self::VERSION;
    }
}