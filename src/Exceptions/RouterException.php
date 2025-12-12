<?php 
/**
 * Luminova Framework Router system exception.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Exceptions;

use \Throwable;
use Luminova\Debugger\Tracer;
use Luminova\Exceptions\ErrorCode;
use Luminova\Exceptions\LuminovaException;

class RouterException extends LuminovaException
{
    /**
     * Constructor for RouterException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: `ErrorCode::ROUTING_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = ErrorCode::ROUTING_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Throws a routing system exception based on a predefined error type.
     *
     * The method resolves an error message using the provided `$type`, optionally
     * formats it using `$values`, attaches debugging trace information (file and line),
     * and throws a `RouterException`.
     *
     * Supported error types:
     * - `argument.empty`            - Required argument is empty.
     * - `invalid.namespace`         - Namespace contains invalid characters.
     * - `invalid.namespace.root`    - Namespace does not start with required root.
     * - `invalid.namespace.end`     - Namespace does not end with Controllers segment.
     * - `invalid.context`           - Route context is missing or invalid.
     * - `invalid.controller`        - Controller does not implement required base type.
     * - `invalid.request.method`    - Request method is invalid for the context.
     * - `invalid.class`             - Class not found in registered namespaces.
     * - `invalid.method`            - Invalid controller method definition.
     * - `invalid.middleware.cli`    - CLI middleware used in HTTP context.
     * - `invalid.middleware.http`   - HTTP middleware used in CLI context.
     * - `bad.method`                - Method argument mismatch in route definition.
     * - `no.context`                - No route context available for request.
     * - `no.route.handler`          - No matching route handler found.
     * - `invalid.cli.group`         - Invalid CLI command group name.
     *
     * @param string $type The error type key used to resolve the message template.
     * @param string|int $code Exception code (default: ErrorCode::ROUTING_ERROR).
     * @param array $values Values used to format placeholders in the message.
     *
     * @return never This method always throws an exception.
     * @throws RouterException Always throws a routing exception.
     */
    public static function rethrow(
        string $type, 
        string|int $code = ErrorCode::ROUTING_ERROR, 
        array $values = []
    ): void
    {
        [$file, $line] = Tracer::trace(2);

        $e = new self(self::getInformation($type, ...$values), $code);

        if($file){
            $e->setLine($line)->setFile($file);
        }

        throw $e;
    }

    /**
     * Get a routing system exception based on a predefined error type.
     *
     * The method resolves an error message using the provided `$type`, optionally
     * formats it using `$values`.
     *
     * Supported error types:
     * - `argument.empty`            - Required argument is empty.
     * - `invalid.namespace`         - Namespace contains invalid characters.
     * - `invalid.namespace.root`    - Namespace does not start with required root.
     * - `invalid.namespace.end`     - Namespace does not end with Controllers segment.
     * - `invalid.context`           - Route context is missing or invalid.
     * - `invalid.controller`        - Controller does not implement required base type.
     * - `invalid.request.method`    - Request method is invalid for the context.
     * - `invalid.class`             - Class not found in registered namespaces.
     * - `invalid.method`            - Invalid controller method definition.
     * - `invalid.middleware.cli`    - CLI middleware used in HTTP context.
     * - `invalid.middleware.http`   - HTTP middleware used in CLI context.
     * - `bad.method`                - Method argument mismatch in route definition.
     * - `no.context`                - No route context available for request.
     * - `no.route.handler`          - No matching route handler found.
     * - `invalid.cli.group`         - Invalid CLI command group name.
     *
     * @param string $type The error type key used to resolve the message template.
     * @param mixed ...$values Arguments of placeholders to format message if key support formatting.
     *
     * @return string Return a formatted error message.
     */
    public static function getInformation(string $type, mixed ...$values): string
    {
        $message = self::getDefaultMessage($type);

        return empty($values) ? $message : sprintf($message, ...$values);
    }

    /**
     * Returns the default exception message for a routing validation error.
     *
     * @param string $type The validation error type.
     *
     * @return string The corresponding exception message template.
     */
    private static function getDefaultMessage(string $type): string
    {
        $url = 'https://luminova.ng/docs/0.0.0/';

        return match ($type) {
            'argument.empty'
                => 'Invalid argument "%s": value cannot be empty.',

            'invalid.namespace'
                => 'Invalid namespace "%s". Only letters, numbers, and "\\" separators are allowed.',

            'invalid.namespace.root'
                => 'Invalid namespace "%s": must start with "%s"%s. See: <link>' . $url . 'routing/system#lmv-docs-addnamespace</link>',

            'invalid.namespace.end'
                => 'Invalid namespace "%s": must end with "\\Controllers\\". See: <link>' . $url . 'routing/system#lmv-docs-addnamespace</link>',

            'invalid.context'
                => 'Route context "%s" is missing or invalid. See: <link>' . $url . 'routing/url-prefix</link>, <link>' . $url . 'boot/public</link>',

            'invalid.controller'
                => 'Invalid controller "%s". Must extend Command/Controller or implement RoutableInterface.',

            'invalid.request.method'
                => 'Invalid request method "%s" in %s context.',

            'invalid.class'
                => 'Class "%s" not found in registered namespaces (%s).',

            'invalid.method'
                => 'Invalid method "%s". Only public non-static methods are allowed.',

            'invalid.middleware.cli'
                => '"guard()" is not allowed in HTTP context. Use "Router::middleware()" instead.',

            'invalid.middleware.http'
                => '"middleware()" is not allowed in CLI context. Use "Router::guard()" instead.',

            'bad.method'
                => 'Method "%s()" expects no arguments, but %d given (route: %s, line %d).',

            'no.context'
                => 'No route context available. See: <link>' . $url . 'introduction/features</link>, <link>' . $url . 'boot/public</link>',

            'no.route.handler'
                => 'No matching route handler found or routing configuration is invalid.',

            'invalid.cli.group'
                => 'Invalid CLI group "%s". Must start with a letter and contain only lowercase letters, numbers, "-", "_" or ":".',

            default
                => 'Routing error: unknown failure during request handling.',
        };
    }
}