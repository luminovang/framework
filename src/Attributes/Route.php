<?php
/**
 * Luminova Framework Method Level Route Attribute
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Attributes;
use \Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Route
{
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
     *          -   HTTP middleware route - `before` or `after`.
     *          -   CLI middleware route `global` or `before` for global middleware. Using `after` for command group middleware.
     * 
     * @example For HTTP Route.
     *  ```php
     * #[Route('/', methods: ['GET'])]
     * public function index():int {}
     * ```
     *  ```php
     * #[Route('/', methods: ['GET'], middleware: 'before')]
     * public function index():int {}
     * ```
     * 
     * @example For CLI Route.
     * ```php
     * #[Route('foo', group: 'bar')]
     * public function foo():int {}
     * ```
     * 
     * ```php
     * #[Route(group: 'bar', middleware: 'global')]
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