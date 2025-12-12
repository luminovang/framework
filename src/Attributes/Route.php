<?php
/**
 * Luminova Framework Method-Scope HTTP Route Attribute.
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

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final class Route
{
    /**
     * Middleware executed before the route controller.
     *
     * Commonly used for authentication, authorization, validation,
     * or other request preprocessing.
     */
    public const BEFORE_MIDDLEWARE = 'before';

    /**
     * Middleware executed after the route controller.
     *
     * Commonly used for cleanup, logging, response processing,
     * or other post-processing tasks.
     */
    public const AFTER_MIDDLEWARE = 'after';

    /**
     * Defines a repeatable HTTP route attribute.
     *
     * Route patterns support the following predefined placeholders:
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
     * The `ANY` method cannot be combined with other HTTP methods.
     *
     * @param string $pattern Route URI pattern, for example `/blog/(:int)`.
     * @param string[] $methods HTTP methods supported by the route. Defaults to `GET`.
     *     Use `ANY` to match all supported HTTP methods.
     * @param bool $error Whether this route handles an HTTP error.
     * @param string|null $middleware Middleware execution point. Must be either
     *     {@see self::BEFORE_MIDDLEWARE} or {@see self::AFTER_MIDDLEWARE}.
     * @param string[]|null $aliases Alternative URI patterns that resolve to this route.
     *
     * @throws RouterException If `ANY` is combined with another HTTP method or the
     *     middleware value is invalid.
     *
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-pattern
     * @see https://luminova.ng/docs/0.0.0/attributes/route
     *
     * @example - Example:
     * ```php
     * #[Route('/(:root)', methods: ['ANY'], middleware: Route::BEFORE_MIDDLEWARE)]
     * public function middleware(): int
     * {
     *     // Middleware implementation.
     * }
     *
     * #[Route('/', methods: ['GET'])]
     * public function index(): int
     * {
     *     // Method implementation.
     * }
     *
     * #[Route('/user/(:username)', methods: ['GET'])]
     * public function user(string $username): int
     * {
     *     // Method implementation.
     * }
     * ```
     */
    public function __construct(
        public string $pattern = '/',
        public array $methods = ['GET'],
        public bool $error = false,
        public ?string $middleware = null,
        public ?array $aliases = null,
    ) {
        if (in_array('ANY', $this->methods, true) && count($this->methods) > 1) {
            throw new RouterException(
                'The HTTP method "ANY" cannot be combined with other HTTP methods.'
            );
        }

        if (
            $this->middleware !== null &&
            $this->middleware !== self::BEFORE_MIDDLEWARE &&
            $this->middleware !== self::AFTER_MIDDLEWARE
        ) {
            throw new RouterException(sprintf(
                'Invalid HTTP middleware "%s". Expected "%s" or "%s".',
                $this->middleware,
                self::BEFORE_MIDDLEWARE,
                self::AFTER_MIDDLEWARE
            ));
        }
    }
}