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

use \Closure;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\ErrorHandlerInterface;

final class Prefix 
{
    /** 
     * Default prefix for standard HTTP request URIs.
     * 
     * @var string WEB
     */
    public const WEB = 'web';

    /** 
     * Suggested custom prefix for API routes (/api).
     * 
     * @var string API
     */
    public const API = 'api';

    /** 
     * Default prefix for all CLI commands.
     * 
     * @var string CLI
     */
    public const CLI = 'cli';

    /** 
     * Suggested custom prefix for control panel routes.
     * 
     * @var string PANEL
     */
    public const PANEL = 'panel';

    /** 
     * Suggested custom prefix for admin routes (/admin).
     * 
     * @var string ADMIN
     */
    public const ADMIN = 'admin';

    /** 
     * Suggested custom prefix for console routes.
     * 
     * @var string CONSOLE
     */
    public const CONSOLE = 'console';

    /** 
     * Suggested custom prefix for webhook endpoints.
     * 
     * @var string WEBHOOK
     */
    public const WEBHOOK = 'webhook';

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
     * 
     * This constructor serves as a url prefix locator for your application routing.
     * 
     * @param string $prefix The route URI prefix name (e.g, `blog`).
     * @param Closure|array{0:class-string<ErrorHandlerInterface>,1:string}|null $onError Optional prefix context error handler.
     *      - Callable Array - Method name in [App\Errors\Controllers\ErrorController::class, 'methodname']; to handle error.
     *      - Closure - Closure(class-string<\T> $arguments [, mixed $... ]): int.
     * 
     * @throws RuntimeException Throws if invalid error handler was provided.
     */
    public function __construct(private string $prefix, Closure|array|null $onError = null) 
    {
        if(
            $onError !== null && 
            !($onError instanceof Closure) && 
            !(is_array($onError) && count($onError) === 2)
        ){
            throw new RuntimeException(
                'Invalid error handler: expected a Closure or a callable array in [class, method] format.'
            );
        }

        $this->onError = $onError;

        if($this->prefix !== self::WEB){
            self::$prefixes[$this->prefix] = $this->prefix;
        }
    }

    /**
     * Get route prefix name.
     * 
     * @return string Return route prefix name.
     * @internal
     */
    public function getPrefix(): string 
    {
        return $this->prefix;
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