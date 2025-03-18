<?php
/**
 * Luminova Framework Method Level Route Attribute
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes;
use \Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Route
{
    /** 
     * Represents middleware executed before handling the main controller logic. 
     * Typically used in HTTP authentication.
     * 
     * @var string BEFORE_MIDDLEWARE 
     */
    public const BEFORE_MIDDLEWARE = 'before'; 
  
    /** 
     * Represents middleware executed after the main controller logic has been handled. 
     * Typically used in HTTP tasks like cleanup, logging, or additional processing.
     * 
     * @var string AFTER_MIDDLEWARE 
     */
    public const AFTER_MIDDLEWARE = 'after';  

    /** 
     * Represents middleware applied globally to all commands, regardless of specific group.
     * Typically used in CLI tasks for universal security checks.
     * 
     * @var string GLOBAL_MIDDLEWARE 
     */
    public const GLOBAL_MIDDLEWARE = 'global'; 

    /** 
     * Represents middleware executed before executing commands in the same group. 
     * Typically used in CLI tasks for group security checks.
     * 
     * @var string GUARD_MIDDLEWARE 
     */
    public const GUARD_MIDDLEWARE = 'guard'; 

    /**
     * HTTP and CLI Route annotation constructor.
     *
     * @param string $pattern The route pattern for HTTP (e.g. `/`, `/blog/([0-9-.]+)`) 
     * or CLI command pattern (e.g. `blogs`, `blogs/limit/(:int)`).
     * @param array $methods The HTTP methods this route should responds to. (default: ['GET']).
     *                       Optionally use `[ANY]` for any HTTP methods.
     * @param bool $error Indicates if this is an error handler route for HTTP methods.
     * @param string|null $group The command group name for CLI route (default: NULL).
     * @param string|null $middleware Middleware type (default: NULL).
     *          -   HTTP middleware route - `Route::BEFORE_MIDDLEWARE` or `Route::AFTER_MIDDLEWARE`.
     *          -   CLI middleware route `Route::GLOBAL_MIDDLEWARE` for global authentication or `Route::GUARD_MIDDLEWARE` for command group authentication.
     * 
     * @example - For HTTP Route.
     * 
     * ```php
     * #[Route('/', methods: ['GET'])]
     * public function index():int {}
     * ```
     * ```php
     * #[Route('/', methods: ['GET'], middleware: Route::BEFORE_MIDDLEWARE)]
     * public function index():int {}
     * ```
     * 
     * @example - For CLI Route.
     * 
     * ```php
     * #[Route('argument', group: 'command')]
     * public function myCommand():int {}
     * ```
     * 
     * ```php
     * #[Route(group: 'command', middleware: Route::GLOBAL_MIDDLEWARE)]
     * public function middleware():int {}
     * ```
     */
    public function __construct(
        public string $pattern = '/',
        public array $methods = ['GET'],
        public bool $error = false,
        public ?string $group = null,
        public ?string $middleware = null
    ) {}
}