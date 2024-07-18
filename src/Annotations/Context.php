<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Annotations;
use \Attribute;
use \Closure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
final class Context
{
    /**
     * Route annotation constructor.
     *
     * @param string $pattern The URI route pattern for error handling.
     * @param string|null $name The HTTP route context name for prefixing.
     * @param Closure|array|null $onError The error handler for route context.
     * 
     * @example For HTTP Route.
     *  ```
     * #[Context('web', pattern: '/', onError: [ViewErrors::class, 'onWebError'])]
     *  class MyController extends BaseController{}
     * ```
     */
    public function __construct(
        public string $name = 'web',
        public string $pattern = '/',
        public Closure|array|null $onError = null,
    ) {}
}
