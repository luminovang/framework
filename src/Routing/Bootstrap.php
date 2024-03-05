<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Routing;

final class Bootstrap 
{
    /** 
     * Default WEB controller type
     * 
     * @var string WEB
    */
    public const WEB = 'web';

    /** 
     * Default API controller type
     * 
     * @var string API
    */
    public const API = 'api';

    /** 
     * Default CLI controller type
     * 
     * @var string CLI
    */
    public const CLI = 'cli';

     /** 
     * Default CONSOLE controller type
     * 
     * @var string CONSOLE
    */
    public const CONSOLE = 'console';

     /** 
     * Default WEBHOOK controller type
     * 
     * @var string WEBHOOK
    */
    public const WEBHOOK = 'webhook';

    /**
     * @var string $name
    */
    private string $name = '';

    /**
     * @var callable|null $onError
    */
    private $onError = null;

    /**
     * @var array $instances
    */
    private static array $instances = [];

    /**
     * Initialize Constructor
     * 
     * @param string  $name  Bootstrap route name
     * @param ?callable $onError Bootstrap Callback function to execute
     */
    public function __construct(string $name, ?callable $onError = null) {
        $this->name = $name;
        $this->onError = $onError;

        if( $name !== self::WEB){
            static::$instances[] = $name;
        }
    }

    /**
     * Get bootstrap route name
     * 
     * @return string $this->name route instance type
    */
    public function getName(): string 
    {
        return $this->name;
    }

    /**
     * Get bootstrap controller error callback handler
     * 
     * @return ?callable $this->onError 
    */
    public function getErrorHandler(): ?callable 
    {
        return $this->onError;
    }

    /**
     * Get bootstrap registered custom instance
     * 
     * @return array static::$instances 
    */
    public static function getInstances(): array 
    {
        return static::$instances;
    }
}