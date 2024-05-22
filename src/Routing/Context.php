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

final class Context 
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
     * @var array<string,string> $contexts
    */
    private static array $contexts = [];

    /**
     * Initialize Constructor
     * 
     * @param string $name Route content name
     * @param Closure|array<int,string>|null $onError Context error handling method.
     *      - array - Method name in [ViewErrors::class, 'methodname']; to handle error.
     *      - Closure - Closure(class-typehint ...arguments): int.
     * 
     * @throws RuntimeException If invalid error callback was provided.
     */
    public function __construct(string $name, Closure|array|null $onError = null) 
    {
        $this->name = $name;

        if($onError !== null && !($onError instanceof Closure) && !(is_array($onError) && count($onError) === 2)){
            throw new RuntimeException('Invalid error handler method. Expected either a Closure or a list array with two elements, where the first element is the class name and the second element is the method to handle the error', E_USER_WARNING);
        }

        $this->onError = $onError;

        if( $name !== self::WEB){
            self::$contexts[$name] = $name;
        }
    }

    /**
     * Get context route name
     * 
     * @return string $this->name route instance type.
     * @internal
    */
    public function getName(): string 
    {
        return $this->name;
    }

    /**
     * Get context controller error callback handler
     * 
     * @return null|callable|array<int,string> Return error handlers.
     * @internal
    */
    public function getErrorHandler(): Closure|array|null
    {
        return $this->onError;
    }

    /**
     * Get context registered custom instance
     * 
     * @return array<string,string> Return registered context
     * @internal
    */
    public static function getInstances(): array 
    {
        return self::$contexts;
    }
}