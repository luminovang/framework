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

use \Luminova\Exceptions\RuntimeException;
use \Closure;

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
     * @var null|Closure|array<int,string>$onError
    */
    private Closure|array|null $onError = null;

    /**
     * @var array $instances
    */
    private static array $instances = [];

    /**
     * Initialize Constructor
     * 
     * @param string  $name  Bootstrap route name
     * @param Closure|array<int,string>|null $onError Bootstrap Callback function to execute.
     *      - string - Method name in [ViewErrors::class, 'methodname']; to handle error.
     * 
     * @throws RuntimeException If invalid error callback was provided.
     */
    public function __construct(string $name, Closure|array|null $onError = null) 
    {
        $this->name = $name;

        if($onError !== null && !($onError instanceof Closure) && is_array($onError) && count($onError) !== 2){
            throw new RuntimeException('Invalid error callback method.');
        }

        $this->onError = $onError;

        if( $name !== self::WEB){
            static::$instances[] = $name;
        }
    }

    /**
     * Get bootstrap route name
     * 
     * @return string $this->name route instance type.
     * @internal
    */
    public function getName(): string 
    {
        return $this->name;
    }

    /**
     * Get bootstrap controller error callback handler
     * 
     * @return null|callable|array<int,string> $this->onError 
     * @internal
    */
    public function getErrorHandler(): Closure|array|null
    {
        return $this->onError;
    }

    /**
     * Get bootstrap registered custom instance
     * 
     * @return array<int, string> static::$instances 
     * @internal
    */
    public static function getInstances(): array 
    {
        return static::$instances;
    }
}