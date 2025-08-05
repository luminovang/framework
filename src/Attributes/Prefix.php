<?php
/**
 * Luminova Framework Class-Scope Route Prefix Attribute.
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

#[Attribute(Attribute::TARGET_CLASS)]
final class Prefix
{
    /**
     * Defines a non-repeatable routing prefix for HTTP controller classes.
     *
     * This attribute assigns a URI prefix to a controller and optionally sets an error handler. 
     * It helps centralize error management and organize controllers when compiling attributes to routes for performance. 
     * 
     * **Predefined Route Placeholders:**
     *
     * - (:root)         → matches everything (catch-all)
     * - (:base)         → matches everything with or without `/` (catch-all)
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
     * @param string $pattern The base prefix or patterns this controller class should handle
     *                   (e.g., `/user/(:root)`, `/user` or `/user/?.*`).
     * @param string|array|null $onError Optional error handler for routing errors. 
     *                                   Can be a callable or a (e.g, `[class, method]`) array.
     * @param array<int,string> $exclude An optional list of URI prefixes to exclude from class matching.
     *                          This is used internally when parsing attributes routing performance.
     * @param bool $mergeExcluders Wether to merge the exclude list with based prefix or pattern (default: false).
     *          If true `pattern+exclude` are combined as (e.g, `/(?!api(?:/|$)|blog(?:/|$)|admin(?:/|$)).*'`).
     *
     * @throws RouterException If the provided error handler is not a valid callable.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-pattern
     * @see https://luminova.ng/docs/0.0.0/attributes/uri-prefix
     *
     * @example Usage:
     * ```php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Prefix;
     * use App\Errors\Controllers\ErrorController;
     *
     * #[Prefix(pattern: '/api/(:base)', onError: [ErrorController::class, 'onWebError'])]
     * class RestController extends BaseController {
     *      // Controller implementation
     * }
     * ```
     * 
     * @example Excluding Prefixes:
     * ```php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Prefix;
     *
     * #[Prefix('/', exclude: ['api', 'blog', 'admin'])]
     * class HomeController extends BaseController {
     *      // Controller implementation
     * }
     * ```
     * > Each controller can have **only one prefix**.
     * > And can optionally define error handler without needing `Error` attribute class.
     */
    public function __construct(
        public string $pattern, 
        public string|array|null $onError = null,
        public array $exclude = [],
        public bool $mergeExcluders = false
    ) 
    {
        if (!$this->onError) {
            return;
        }

        if(is_callable($this->onError) || (is_array($this->onError) && count($this->onError) === 2)){
            return;
        }
        
        throw new RouterException(
            'The provided error handler must be a valid callable, a [class, method] array, or null.'
        );
    }
}