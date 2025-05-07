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

use \Luminova\Exceptions\RouterException;
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
     * Defines a repeatable attribute for registering HTTP and CLI routes.
     *
     * This attribute maps controller methods to specific URI patterns and HTTP methods (for web) 
     * or command patterns (for CLI),
     * with optional middleware and error handling support.
     *
     * @param string $pattern The route pattern for HTTP (e.g., `/`, `/blog/([0-9-.]+)`) 
     *                        or CLI command pattern (e.g., `blogs`, `blogs/limit/(:int)`).
     * @param array $methods The HTTP methods this route responds to (default: `['GET']`). 
     *                        Use `['ANY']` to match all HTTP methods for enhanced performance.
     * @param bool $error Indicates if this route-method is an error handler for HTTP routes.
     * @param string|null $group The CLI command group this route belongs to (default: `null`).
     *                          Applicable only to CLI command controllers.
     * @param string|null $middleware Optional middleware authentication assignment:
     *              - HTTP: Use `Route::BEFORE_MIDDLEWARE` or `Route::AFTER_MIDDLEWARE`.
     *              - CLI:  Use `Route::GLOBAL_MIDDLEWARE` for global handling or `Route::GUARD_MIDDLEWARE` for command group handling.
     *
     * @throws RouterException If the provided middleware handler is not a valid supported in context.
     * 
     * @example HTTP Routing:
     * ```php
     * // /app/Controllers/Http/MyController.php
     * 
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Route;
     * 
     * class MyController extends BaseController
     * {
     *     #[Route('/(:root)', methods: ['ANY'], middleware: Route::BEFORE_MIDDLEWARE)]
     *     public function middleware(): int {
     *         // Middleware implementation
     *     }
     *
     *     #[Route('/', methods: ['GET'])]
     *     public function index(): int {
     *         // Method implementation
     *     }
     * }
     * ```
     *
     * @example CLI Routing:
     * ```php
     * // /app/Controllers/Cli/MyCommand.php
     * 
     * namespace App\Controllers\Cli;
     * 
     * use Luminova\Base\BaseCommand;
     * use Luminova\Attributes\Route;
     * 
     * class MyCommand extends BaseCommand
     * {
     *     #[Route(group: 'command', middleware: Route::GLOBAL_MIDDLEWARE)]
     *     public function middleware(): int 
     *     {
     *         // CLI middleware implementation
     *     }
     *
     *     #[Route('argument', group: 'command')]
     *     public function doFoo(): int 
     *     {
     *         // CLI method implementation
     *     }
     * }
     * ```
     */
    public function __construct(
        public string $pattern = '/',
        public array $methods = ['GET'],
        public bool $error = false,
        public ?string $group = null,
        public ?string $middleware = null
    ) 
    {
        if($this->middleware !== null){
            if(
                $this->group !== null && 
                $this->middleware !== self::GLOBAL_MIDDLEWARE && 
                $this->middleware !== self::GUARD_MIDDLEWARE
            ){
                throw new RouterException(sprintf(
                    'Invalid CLI middleware "%s". Expected "%s" or "%s" when a group is defined.',
                    $this->middleware,
                    self::GLOBAL_MIDDLEWARE,
                    self::GUARD_MIDDLEWARE
                ));
            }

            if(
                $this->group === null && 
                $this->middleware !== self::BEFORE_MIDDLEWARE && 
                $this->middleware !== self::AFTER_MIDDLEWARE
            ){
                throw new RouterException(sprintf(
                    'Invalid HTTP middleware "%s". Expected "%s" or "%s" when no group is defined.',
                    $this->middleware,
                    self::BEFORE_MIDDLEWARE,
                    self::AFTER_MIDDLEWARE
                ));
            }
        }
    }
}