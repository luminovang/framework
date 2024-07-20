<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Attributes;
use \Attribute;
use \Closure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
final class Error
{
    /**
     * Route error global handling attribute constructor.
     *
     * @param string $context The route context name for URI prefixing.
     * @param string $pattern The URI route pattern to match for current error handling (e.g. `/`,`/.*`,  `/blog/([0-9-.]+)`).
     * @param Closure|array|null $onError The error handler, which can be a Closure or an array specifying a class and method.
     * 
     * @example For HTTP Route.
     *  ```
     * #[Error('web', pattern: '/', onError: [ViewErrors::class, 'onWebError'])]
     *  class MyController extends BaseController{}
     * ```
     */
    public function __construct(
        public string $context = 'web',
        public string $pattern = '/',
        public Closure|array|null $onError = null,
    ) {}
}