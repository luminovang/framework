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
use Luminova\Exceptions\RouterException;

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
     * @param string $context The URI prefix context name used to categorize URIs (default: 'web'). 
     *              Typically the first segment of the URI (e.g., `api`, `blog`).
     * @param string $pattern The route URI pattern to allow this  error handler (default: `/` root).
     *                         (e.g., `/blog/(:int)`, `/blogs/{$id}`, `/blog/(\d+)`, `/` or `/.*`).
     * @param string|array{0:class-string,1:string} $onError A callable error handler, provided as a string or `[class, method]` array.
     *
     * @throws RouterException If the provided error handler is not callable or `null` was provided.
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-placeholder
     * @see https://luminova.ng/docs/0.0.0/attributes/error
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
     * @example Usage:
     * 
     * ```php
     * namespace App\Controllers\Http;
     * use Luminova\Base\Controller;
     * use Luminova\Attributes\Error;
     * use App\Errors\Controllers\AppError;
     *
     * #[Error(pattern: '/', onError: [AppError::class, 'onTrigger'])] // Global websites error handler
     * #[Error('foo', pattern: '/foo/', onError: [AppError::class, 'handler'])] // Custom for URI prefix
     * class MyController extends Controller {
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