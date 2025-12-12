<?php
declare(strict_types=1);
/**
 * Luminova Framework Routing URI prefix mapping.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Routing;

use \Closure;
use Luminova\Runtime;
use Luminova\Luminova;
use Luminova\Exceptions\RuntimeException;
use Luminova\Interface\ErrorControllerInterface;

/**
 * Defines a routing context and its URI prefix.
 *
 * A routing prefix identifies a group of routes that share a common URI
 * prefix and optional error handler. Prefixes can also be marked as CLI
 * command contexts.
 *
 * @example - HTTP context:
 * ```php
 * new Prefix(Prefix::WEB, [AppError::class, 'onTrigger']);
 * ```
 *
 * @example - API context:
 * ```php
 * new Prefix(Prefix::API, [AppError::class, 'onTrigger']);
 * ```
 *
 * @example - CLI context:
 * ```php
 * new Prefix(Prefix::CLI);
 * ```
 */
final class Prefix 
{
    /** 
     * Default prefix for standard HTTP request URIs.
     * 
     * @var string WEB
     */
    public const WEB = 'web';

    /** 
     * Default prefix for all CLI commands.
     * 
     * @var string CLI
     */
    public const CLI = 'cli';

    /** 
     * Application API based on env(api.prefix).
     * 
     * @var string APP_API
     */
    public const APP_API = '__app+api__';

    /** 
     * Suggested custom prefix for API routes (/api).
     * 
     * @var string API
     * @see slf::APP_API
     */
    public const API = 'api';

    /** 
     * Suggested custom prefix for control panel routes.
     * 
     * @var string PANEL
     */
    public const PANEL = 'panel';

    /** 
     * Suggested custom prefix for admin routes (/admin).
     * 
     * @var string ADMIN
     */
    public const ADMIN = 'admin';

    /** 
     * Suggested custom prefix for console routes.
     * 
     * @var string CONSOLE
     */
    public const CONSOLE = 'console';

    /** 
     * Suggested custom prefix for webhook endpoints.
     * 
     * @var string WEBHOOK
     */
    public const WEBHOOK = 'webhook';

    /**
     * @var array CUSTOM
     */
    private const CUSTOM = [
        self::WEB     => true,
        self::CLI     => true,
        self::API     => true,
        self::ADMIN   => true,
        self::PANEL   => true,
        self::CONSOLE => true,
        self::WEBHOOK => true,
    ];

    /**
     * URI prefix name.
     *
     * @var string $prefix
     */
    private string $prefix = self::WEB;

    /**
     * Prefix context error handler.
     *
     * @var Closure|array|null $onError
     */
    private Closure|array|null $onError = null;

    /**
     * Prefix for command.
     *
     * @var bool $isCommand
     */
    private bool $isCommand = false;

    /**
     * Create a routing context.
     *
     * The prefix identifies the URI context used by the router. An optional
     * error handler can be assigned to handle errors raised within that context.
     * A context can also be marked as a CLI command context.
     *
     * When the prefix is {@see self::CLI} and `$isCommand` is not explicitly
     * enabled, its command status is determined from the current runtime.
     * 
     * @template T of \Luminova\Foundation\Core\Application
     *
     * @param string $prefix The request URI prefix, such as `web`, `api`, `admin` etc...
     * @param (Closure(int $status, Segments $segments, T $app):int)|array{0:class-string<ErrorControllerInterface>,1:string}|null $onError
     *        Optional error handler for the routing context. The handler may be
     *        a closure or callable array in `[class, method]` format.
     * @param bool $isCommand Whether the context handles CLI commands.
     *
     * @throws RuntimeException If the prefix or error handler is invalid.
     *
     * @see self::with() For array definition.
     * @see ../../routes/ For the corresponding route files.
     */
    public function __construct(
        string $prefix = self::WEB,
        Closure|array|null $onError = null,
        bool $isCommand = false
    ) 
    {
        [$name, $error, $command] = self::normalize(
            $prefix,
            $onError,
            $isCommand
        );

        $this->prefix = $name;
        $this->onError = $error;
        $this->isCommand = $command;
    }

    /**
     * Create a routing context definition as an associative array.
     *
     * This is a convenience method for defining routing contexts without
     * instantiating a {@see Prefix} object. The returned array can be passed
     * directly to {@see Router::context()}.
     *
     * When the prefix is {@see self::CLI} and `$isCommand` is not explicitly
     * enabled, its command status is determined from the current runtime.
     * 
     * @template T of \Luminova\Foundation\Core\Application
     *
     * @param string $prefix The request URI prefix, such as `web`, `api`, `admin` etc...
     * @param (Closure(int $status, Segments $segments, T $app):int)|array{0:class-string<ErrorControllerInterface>,1:string}|null $onError
     *        Optional error handler for the routing context. The handler may be
     *        a closure or callable array in `[class, method]` format.
     * @param bool $isCommand Whether the context handles CLI commands.
     *
     * @return array{
     *     prefix: string,
     *     onError: Closure|array{0:class-string<ErrorControllerInterface>,1:string}|null,
     *     isCommand: bool
     * } The routing context definition.
     *
     * @throws RuntimeException If the prefix or error handler is invalid.
     *
     * @example - Create a web context:
     * ```php
     * Prefix::with(
     *     Prefix::WEB,
     *     [AppError::class, 'onTrigger']
     * );
     * ```
     *
     * @example - Create a CLI context:
     * ```php
     * Prefix::with(Prefix::CLI, isCommand: true);
     * ```
     *
     * @example - Register multiple contexts:
     * ```php
     * Boot::http()->router->context([
     *     Prefix::with(Prefix::WEB, [AppError::class, 'onTrigger']),
     *     Prefix::with(Prefix::API, [AppError::class, 'onTrigger']),
     *     Prefix::with(Prefix::CLI, isCommand: true),
     * ])->run();
     * ```
     */
    public static function with(
        string $prefix = self::WEB,
        Closure|array|null $onError = null,
        bool $isCommand = false
    ): array 
    {
        [$prefix, $onError, $isCommand] = self::normalize(
            $prefix,
            $onError,
            $isCommand
        );

        return [
            'prefix'    => $prefix,
            'onError'   => $onError,
            'isCommand' => $isCommand,
        ];
    }

    /**
     * Get the routing context prefix.
     *
     * @return string The routing context URI prefix.
     *
     * @internal
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Determine whether the routing context handles CLI commands.
     *
     * @return bool `true` if the context is configured for CLI commands,
     *              otherwise `false`.
     */
    public function isCommand(): bool
    {
        return $this->isCommand;
    }

    /**
     * Get the routing context error handler.
     *
     * @return Closure|array|null The configured error handler, or `null`
     *                            when no handler is assigned.
     */
    public function getErrorHandler(): Closure|array|null
    {
        return $this->onError;
    }

    /**
     * Validate and normalize a routing context definition.
     *
     * Validates the optional error handler and automatically determines whether
     * the CLI prefix represents a command context when `$isCommand` is not
     * explicitly enabled.
     *
     * @param string $prefix The routing context URI prefix.
     * @param Closure|array{0:class-string<ErrorControllerInterface>,1:string}|null $onError
     *        Optional routing context error handler.
     * @param bool $isCommand Whether the context handles CLI commands.
     *
     * @return array{string, Closure|array|null, bool} Normalized prefix,
     *         error handler, and command context values.
     *
     * @throws RuntimeException If the prefix or error handler is invalid.
     */
    private static function normalize(
        string $prefix,
        Closure|array|null $onError,
        bool $isCommand
    ): array 
    {
        $prefix = trim($prefix);

        if($prefix === self::APP_API){
           $prefix = Luminova::apiPrefix();
        }

        if (
            $prefix === '' 
            || (!isset(self::CUSTOM[$prefix]) && !preg_match('/^[\p{L}\p{N}._-]+$/u', $prefix))
        ) {
            throw new RuntimeException(sprintf(
                'Invalid routing prefix "%s". 
                Prefixes may contain only letters, numbers, dots, hyphens, and underscores.',
                $prefix
            ));
        }

        if ($onError !== null && !Runtime::isCallable($onError, true)) {
            throw new RuntimeException(
                'Invalid error handler: expected a Closure 
                or a callable array in [class, method] format.'
            );
        }

        if (!$isCommand && $prefix === self::CLI) {
            $isCommand = Runtime::isCommand();
        }

        return [$prefix, $onError, $isCommand];
    }
}