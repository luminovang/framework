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

use \Luminova\Exceptions\RuntimeException;

interface ServicesInterface
{
    /**
     * Register bootstrap autoload all your application services.
     * Add each service in a new line within the bootstrap method.
     * 
     * Usage:
     *     - self::newService(Configuration::class) as $config = service('Configuration')
     *     - self::newService('\Luminova\Config\Configuration') as $config = service('Configuration')
     *     - self::newService(Configuration:class, 'config') as $config = service('config')
     *     - Services::Configuration()
     *     - Services::config()
     * 
     * @return void
     * @throws RuntimeException Throws If the service already exists causing duplicate service or invalid class arguments.
     */
    public function bootstrap(): void;
}