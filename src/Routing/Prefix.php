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
namespace Luminova\Routing;

use \Luminova\Interface\ErrorHandlerInterface;
use \Luminova\Exceptions\RuntimeException;
use \Closure;

final class Prefix 
{
    /** 
     * Default WEB controller type.
     * 
     * @var string WEB
     */
    public const WEB = 'web';

    /** 
     * Default API controller type.
     * 
     * @var string API
     */
    public const API = 'api';

    /** 
     * Default CLI controller type.
     * 
     * @var string CLI
     */
    public const CLI = 'cli';

    /** 
     * Default CONSOLE controller type.
     * 
     * @var string CONSOLE
     */
    public const CONSOLE = 'console';

    /** 
     * Default WEBHOOK controller type.
     * 
     * @var string WEBHOOK
     */
    public const WEBHOOK = 'webhook';

    /**
     * @var string $name
     */
    private string $name = '';

    /**
     * Error handler.
     * 
     * @var Closure|string[]|null $onError
     */
    private Closure|array|null $onError = null;

    /**
     * Array of prefixes.
     * 
     * @var array<string,string> $prefixes
     */
    private static array $prefixes = [];

    /**
     * Initialize constructor to register a router prefix.
     * This constructor serves as a url prefix locator for your application routing.
     * 
     * @param string $name The route url prefix name (e.g, `blog`).
     * @param Closure|array{0:class-string<ErrorHandlerInterface>,1:string}|null $onError Optional prefix context error handler.
     *      - Callable Array - Method name in [ViewErrors::class, 'methodname']; to handle error.
     *      - Closure - Closure(class-string<\T> $arguments [, mixed $... ]): int.
     * 
     * @throws RuntimeException Throws if invalid error handler was provided.
     */
    public function __construct(string $name, Closure|array|null $onError = null) 
    {
        $this->name = $name;

        if($onError !== null && !($onError instanceof Closure) && !(is_array($onError) && count($onError) === 2)){
            throw new RuntimeException('Invalid error handler: expected a Closure or a callable array in [class, method] format.');
        }

        $this->onError = $onError;

        if( $name !== self::WEB){
            self::$prefixes[$name] = $name;
        }
    }

    /**
     * Get route prefix name.
     * 
     * @return string Return route prefix name.
     * @internal
     */
    public function getName(): string 
    {
        return $this->name;
    }

    /**
     * Get route prefix error handler.
     * 
     * @return Closure|string[]|null Return router error handlers.
     * @internal
     */
    public function getErrorHandler(): Closure|array|null
    {
        return $this->onError;
    }

    /**
     * Get route registered prefixes.
     * 
     * @return array<string,string> Return registered route prefixes.
     * @internal
     */
    public static function getPrefixes(): array 
    {
        return self::$prefixes;
    }
}