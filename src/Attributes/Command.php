<?php
/**
 * Luminova Framework Method-Scope CLI Route Attribute.
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
final class Command
{
    /**
     * Middleware applied globally to all CLI commands.
     *
     * This middleware runs independently of the command group and is
     * typically used for common tasks such as initialization, logging,
     * or security checks.
     */
    public const GLOBAL_MIDDLEWARE = 'global';

    /**
     * Middleware applied to commands within the same CLI group.
     *
     * This middleware is typically used for group-specific initialization,
     * validation, authorization, or other shared command logic.
     */
    public const GROUP_MIDDLEWARE = 'guard';

    /**
     * Defines a repeatable attribute for registering CLI commands.
     *
     * This attribute maps controller methods to command patterns and
     * optionally assigns them to a command group, middleware, aliases,
     * or an error handler.
     *
     * Predefined command placeholders:
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
     * @param string $pattern CLI command pattern, for example `list` or `user/(:int)`.
     * @param string|null $group Command group this route belongs to.
     * @param bool $error  Whether this command handles a CLI error.
     * @param string|null $middleware  Middleware scope. Must be either
     *      {@see self::GLOBAL_MIDDLEWARE} or {@see self::GROUP_MIDDLEWARE}.
     * @param string[]|null $aliases Alternative command patterns that resolve to the same command.
     *
     * @throws RouterException
     *      If the middleware value is invalid.
     *
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-pattern
     * @see https://luminova.ng/docs/0.0.0/attributes/route
     *
     * @example - Example:
     * ```php
     * namespace App\Controllers\Cli;
     *
     * use Luminova\Attributes\Command;
     *
     * #[Group('foo')]
     * class FooCommand extends \Luminova\Base\Command
     * {
     *     #[Command(middleware: Command::GLOBAL_MIDDLEWARE)]
     *     public function middleware(): int
     *     {
     *         // CLI middleware implementation.
     *     }
     *
     *     #[Command('ping')]
     *     public function pingFoo(): int
     *     {
     *         // CLI command implementation.
     *     }
     * }
     * ```
     * @example - Command: 
     * ```bash
     * cd project/public/
     * index.php foo ping
     * ```
     */
    public function __construct(
        public string $pattern = '/',
        public ?string $group = null,
        public bool $error = false,
        public ?string $middleware = null,
        public ?array $aliases = null,
    ) {
        if (
            $this->middleware !== null &&
            $this->middleware !== self::GLOBAL_MIDDLEWARE &&
            $this->middleware !== self::GROUP_MIDDLEWARE
        ) {
            throw new RouterException(sprintf(
                'Invalid CLI middleware "%s". Expected "%s" or "%s".',
                $this->middleware,
                self::GLOBAL_MIDDLEWARE,
                self::GROUP_MIDDLEWARE
            ));
        }
    }
}