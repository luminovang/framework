<?php
/**
 * Luminova Framework Method-Scope Route Attribute.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes;

use \Attribute;
use \Luminova\Exceptions\RouterException;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Route
{
    /** 
     * Middleware executed **before** the main controller logic.
     * 
     * Commonly used for HTTP authentication or pre-processing tasks.
     * 
     * @var string BEFORE_MIDDLEWARE
     */
    public const HTTP_BEFORE_MIDDLEWARE = 'before'; 
  
    /** 
     * Middleware executed **after** the main controller logic.
     * 
     * Useful for HTTP tasks like cleanup, logging, or post-processing.
     * 
     * @var string AFTER_MIDDLEWARE
     */
    public const HTTP_AFTER_MIDDLEWARE = 'after'; 

    /** 
     * Middleware applied **globally** to all CLI commands, regardless of group.
     * 
     * Typically used for universal CLI tasks like security checks or logging.
     * 
     * @var string CLI_GLOBAL_MIDDLEWARE
     */
    public const CLI_GLOBAL_MIDDLEWARE = 'global'; 

    /** 
     * Middleware executed **before commands** in the same CLI group.
     * 
     * Typically used for group-specific CLI security checks or setup tasks.
     * 
     * @var string CLI_GROUP_MIDDLEWARE
     */
    public const CLI_GROUP_MIDDLEWARE = 'guard';

    /** 
     * Middleware executed **before** the main controller logic.
     * 
     * @var string BEFORE_MIDDLEWARE
     * @deprecated Since 3.6.8 Use HTTP_BEFORE_MIDDLEWARE instead
     */
    public const BEFORE_MIDDLEWARE = self::HTTP_BEFORE_MIDDLEWARE; 
    
    /** 
     * Middleware executed **after** the main controller logic.
     * 
     * @var string AFTER_MIDDLEWARE
     * @deprecated Since 3.6.8 Use HTTP_AFTER_MIDDLEWARE instead
     */
    public const AFTER_MIDDLEWARE = self::HTTP_AFTER_MIDDLEWARE; 
    
    /** 
     * Middleware applied **globally** to all CLI commands, regardless of group.
     * 
     * @var string GLOBAL_MIDDLEWARE
     * @deprecated Since 3.6.8 Use CLI_GLOBAL_MIDDLEWARE instead
     */
    public const GLOBAL_MIDDLEWARE = self::CLI_GLOBAL_MIDDLEWARE; 

    /** 
     * Middleware executed **before commands** in the same CLI group.
     * 
     * @var string GUARD_MIDDLEWARE
     * @deprecated Since 3.6.8 Use CLI_GROUP_MIDDLEWARE instead
     */
    public const GUARD_MIDDLEWARE = self::CLI_GROUP_MIDDLEWARE;

    /**
     * Defines a repeatable attribute for registering HTTP or CLI routes.
     *
     * This attribute links controller methods to specific URI patterns (HTTP) 
     * or command patterns (CLI). You can also attach middleware and define 
     * error handlers for HTTP routes.
     *
     * **Predefined Route Placeholders:**
     *
     * - (:root)         → matches everything (catch-all)
     * - (:any)          → matches any characters, including slashes
     * - (:int)          → matches integers (digits only)
     * - (:integer)      → alias for :int
     * - (:mixed)        → matches any characters except slash (lazy)
     * - (:string)       → matches a non-empty segment without slashes
     * - (:optional)     → optional segment (may be empty)
     * - (:alphabet)     → letters only (A-Z, a-z)
     * - (:alphanumeric) → letters and digits only
     * - (:username)     → letters, digits, dots, underscores, hyphens
     * - (:version)      → version numbers like: 1.0, 2.3.4, 10.0.1.2, etc.
     * - (:number)       → integer or decimal with optional sign
     * - (:double)       → floating-point number with optional sign
     * - (:float)        → decimal numbers only
     * - (:path)         → multiple segments separated by slashes
     * - (:uuid)         → standard UUID (8-4-4-4-12 hex digits)
     *
     * @param string $pattern The route pattern (e.g., `/blog/(:int)`, `/blogs/{$id}` or `/blog/(\d+)`).
     * @param array $methods HTTP methods this route responds to, Use ['ANY'] to match all methods (default: ['GET']).
     * @param bool $error Whether this route is an HTTP error handler (for: `HTTP` only).
     * @param string|null $group CLI command group this route belongs to (for: `CLI` only).
     * @param string|null $middleware Optional middleware assignment:
     *        - For `HTTP`: `Route::HTTP_BEFORE_MIDDLEWARE` or `Route::HTTP_AFTER_MIDDLEWARE`
     *        - For `CLI`: `Route::CLI_GLOBAL_MIDDLEWARE` (global) or `Route::CLI_GROUP_MIDDLEWARE` (group-specific)
     * @param array<int,string>|null $aliases Optional list of alternative URI patterns or CLI commands that map to the same route (e.g. ['/blog/(:int)', '/blog/id/(:int)']).
     *
     * @throws RouterException If the middleware is invalid or unsupported.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-pattern
     * @see https://luminova.ng/docs/0.0.0/attributes/route
     *
     * @example HTTP Controller Routing:
     * ```php
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
     * 
     *     #[Route('/user/(:username)', methods: ['GET'])]
     *     public function user(string $username): int {
     *         // Method implementation
     *     }
     * }
     * ```
     *
     * @example CLI Controller Routing:
     * ```php
     * namespace App\Controllers\Cli;
     * 
     * use Luminova\Base\BaseCommand;
     * use Luminova\Attributes\Route;
     * use Luminova\Attributes\Group;
     *
     * #[Group('foo')]
     * class FooCommand extends BaseCommand
     * {
     *     #[Route(group: 'foo', middleware: Route::CLI_GLOBAL_MIDDLEWARE)]
     *     public function middleware(): int {
     *         // CLI middleware implementation
     *     }
     *
     *     #[Route('argument', group: 'foo')]
     *     public function doFoo(): int {
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
        public ?string $middleware = null,
        public ?array $aliases = null
    ) 
    {
        if($this->middleware !== null){
            if(
                $this->group !== null && 
                $this->middleware !== self::CLI_GLOBAL_MIDDLEWARE && 
                $this->middleware !== self::CLI_GROUP_MIDDLEWARE
            ){
                throw new RouterException(sprintf(
                    'Invalid CLI middleware "%s". Expected "%s" or "%s" when a group is defined.',
                    $this->middleware,
                    self::CLI_GLOBAL_MIDDLEWARE,
                    self::CLI_GROUP_MIDDLEWARE
                ));
            }

            if(
                $this->group === null && 
                $this->middleware !== self::HTTP_BEFORE_MIDDLEWARE && 
                $this->middleware !== self::HTTP_AFTER_MIDDLEWARE
            ){
                throw new RouterException(sprintf(
                    'Invalid HTTP middleware "%s". Expected "%s" or "%s" when no group is defined.',
                    $this->middleware,
                    self::HTTP_BEFORE_MIDDLEWARE,
                    self::HTTP_AFTER_MIDDLEWARE
                ));
            }
        }
    }
}