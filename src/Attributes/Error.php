<?php
/**
 * Luminova Framework Method Class Route Error Attribute
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes;
use \Attribute;
use \Closure;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
final class Error
{
    /**
     * Defines an attribute for handling global route errors.
     *
     * @param string $context The route context used to categorize the URI,  (defaults: `web`) for generic prefix handling.
     *                  The context is typically the first segment of the URI (e.g., `api`, `blog`).
     * @param string $pattern The route pattern to match for current error handling (e.g. `/`, `/.*`, `/blog/([0-9-.]+)` or `/blog/(:placeholder)`).
     *                  This can be a specific path, a regex-style pattern or placeholder.
     * @param Closure|array|null $onError The error handler callback, which can either be a Closure or an array containing the class and method responsible for handling the error.
     * 
     * @example - Example usage for defining an error handler on a route:
     * 
     * ```php
     * // /app/Controllers/Http/MyController.php
     * namespace \App\Controller\Http;
     * 
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Error;
     * 
     * #[Error('web', pattern: '/', onError: [ViewErrors::class, 'onWebError'])]
     * #[Error('foo', pattern: '/foo/', onError: [ViewErrors::class, 'onWebFooError'])]
     * class MyController extends BaseController {
     *      // Class implemenations
     * }
     * ```
     */
    public function __construct(
        public string $context = 'web',
        public string $pattern = '/',
        public Closure|array|null $onError = null,
    ) {}
}