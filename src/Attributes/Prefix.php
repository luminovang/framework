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
use Luminova\Exceptions\RouterException;

#[Attribute(Attribute::TARGET_CLASS)]
final class Prefix
{
    /**
     * Defines a non-repeatable routing prefix for HTTP controller classes.
     *
     * This attribute assigns a URI prefix to a controller and optionally sets an error handler. 
     * It helps centralize error management and organize controllers when compiling attributes 
     * to routes for performance. 
     * 
     * **Predefined Route Placeholders:**
     *
     * - `(:base)`         Matches an optional root path and any following content.
     * - `(:root)`         Matches an optional root path followed by any content.
     * - `(:group)`        Matches any content, including `/`, and may be empty.
     * - `(:int)`          Matches one or more digits. Alias of `(:integer)`.
     * - `(:integer)`      Matches one or more digits.
     * - `(:mixed)`        Matches a single URI segment, including an empty segment.
     * - `(:string)`       Matches a non-empty URI segment without `/`.
     * - `(:optional)`     Matches an optional URI segment.
     * - `(:alphabet)`     Matches letters only (`A-Z` and `a-z`).
     * - `(:alphanumeric)` Matches letters and digits only.
     * - `(:username)`     Matches letters, digits, `.`, `_`, and `-`, with an optional `@` prefix.
     * - `(:number)`       Matches integers or decimal numbers with an optional `+` or `-` sign.
     * - `(:numeric)`      Matches integers or decimal numbers with an optional `-` sign.
     * - `(:version)`      Matches dot-separated numeric versions such as `1.0`, `2.3.4`, or `10.0.1.2`.
     * - `(:double)`       Matches integers or decimal numbers with an optional `+` or `-` sign.
     * - `(:float)`        Matches decimal numbers with an optional `+` or `-` sign.
     * - `(:file)`         Matches a filename containing an extension.
     * - `(:filepath)`     Matches a file path containing zero or more directories and a filename with an extension.
     * - `(:path)`         Matches two or more non-empty URI segments separated by `/`.
     * - `(:uuid)`         Matches a UUID-shaped value in `8-4-4-4-12` hexadecimal format.
     * - `(:ulid)`         Matches a 26-character ULID using Crockford Base32.
     *
     * Typed placeholders can be made optional by prefixing their type with `?`,
     * for example `(:?int)`, `(:?string)`, or `(:?numeric)`.
     *
     * @param string $pattern The base prefix or patterns this controller class should handle
     *                   (e.g., `/user/(:root)`, `/user` or `/user/?.*`).
     * @param string|array|null $onError Optional error handler for routing errors. 
     *                                   Can be a callable or a (e.g, `[class, method]`) array.
     * @param string[] $exclude An optional list of URI prefixes to exclude from class matching.
     *                          This is used internally when parsing attributes routing performance.
     * @param bool $mergeExcluders Wether to merge the exclude list with based prefix or pattern (default: false).
     *          If true `pattern+exclude` are combined as (e.g, `/(?!api(?:/|$)|blog(?:/|$)|admin(?:/|$)).*'`).
     *
     * @throws RouterException If the provided error handler is not a valid callable.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-pattern
     * @see https://luminova.ng/docs/0.0.0/attributes/uri-prefix
     * 
     * @signature - $onError Signature:
     * 
     * Invoked when specified URI pattern matches.
     * 
     * ```php
     * fn(
     *      int $status, 
     *      Luminova\Routing\Segments $segments, 
     *      Luminova\Foundation\Core\Application $app
     * ):int
     * ```
     *
     * @example - Usage:
     * ```php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\Controller;
     * use Luminova\Attributes\Prefix;
     * use App\Errors\Controllers\AppError;
     *
     * #[Prefix(pattern: '/api/(:base)', onError: [AppError::class, 'onTrigger'])]
     * class RestController extends Controller {
     *      // Controller implementation
     * }
     * ```
     * 
     * @example Excluding Prefixes:
     * ```php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\Controller;
     * use Luminova\Attributes\Prefix;
     *
     * #[Prefix('/', exclude: ['api', 'blog', 'admin'])]
     * class MainController extends Controller {
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