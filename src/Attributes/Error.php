<?php
/**
 * Luminova Framework Class-Scope Route Error Attribute.
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

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
final class Error
{
    /**
     * Defines a repeatable attribute for handling global HTTP routing errors.
     *
     * This attribute lets you assign an error handler to a specific URI or URI pattern
     * within a given context. You can define multiple error handlers for different
     * URI prefixes or patterns in the same controller, giving fine-grained control 
     * over routing error management.
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
     * - (:number)       → integer or decimal with optional sign
     * - (:double)       → floating-point number with optional sign
     * - (:float)        → decimal numbers only
     * - (:path)         → multiple segments separated by slashes
     * - (:uuid)         → standard UUID (8-4-4-4-12 hex digits)
     *
     * @param string $context The URI prefix context name used to categorize URIs (default: 'web'). 
     *              Typically the first segment of the URI (e.g., `api`, `blog`).
     * @param string $pattern The route URI pattern to allow this  error handler (default: `/` root).
     *                         (e.g., `/blog/(:int)`, `/blogs/{$id}`, `/blog/(\d+)`, `/` or `/.*`).
     * @param string|array $onError A callable error handler, provided as a string or `[class, method]` array.
     *                                   Invoked when the specified pattern matches.
     *
     * @throws RouterException If the provided error handler is not callable or `null` was provided.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-placeholder
     * @see https://luminova.ng/docs/0.0.0/attributes/error
     *
     * @example Usage:
     * 
     * ```php
     * namespace App\Controllers\Http;
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Error;
     * use App\Errors\Controllers\ErrorController;
     *
     * #[Error(pattern: '/', onError: [ErrorController::class, 'onWebError'])] // Global websites error handler
     * #[Error('foo', pattern: '/foo/', onError: [ErrorController::class, 'onWebFooError'])] // Custom for (foo) URI prefix
     * class MyController extends BaseController {
     *      // Controller implementation
     * }
     * ```
     */
    public function __construct(
        public string $context = 'web',
        public string $pattern = '/',
        public string|array|null $onError = null,
    )
    {
        if ($this->onError === null) {
            throw new RouterException(
                'The Error attribute "$onError" requires a valid error handler; null is not allowed.'
            );
        }

        if(is_callable($this->onError) || (is_array($this->onError) && count($this->onError) === 2)){
            return;
        }
        
        throw new RouterException(
            'The provided error handler must be a valid callable or a [class, method] array.'
        );
    }
}