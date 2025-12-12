<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use Luminova\Routing\Segments;

/**
 * Defines a controller that handles routing errors.
 *
 * Controllers implementing this interface provide the default `onTrigger()`
 * handler for manually triggered HTTP errors. Custom error handlers may also
 * be registered for specific URI patterns and can receive dependency-injected
 * services and matched URI segments.
 */
interface ErrorControllerInterface
{
    /**
     * Handle a manually triggered routing error.
     *
     * Called when the router encounters a routing error or when an HTTP error
     * status is explicitly triggered through the router.
     *
     * @param int $status HTTP status code associated with the error.
     * @param Segments $segments Request URI segments associated with the error.
     *
     * @return int Response status code, either `STATUS_SUCCESS` or
     *         `STATUS_SILENCE`.
     *
     * @example - Examples:
     * ```php
     * // Register the error controller globally.
     * Router::onError(
     *     [AppError::class, 'onTrigger']
     * );
     *
     * // Register a custom handler for a URI pattern.
     * Router::onError(
     *     [AppError::class, 'onError'],
     *     '/users'
     * );
     * 
     * #[Prefix(pattern: '/users/(:base)', onError: [AppError::class, 'onTrigger'])]
     * class MyController extends Controller {}
     *
     * // Trigger an HTTP error manually.
     * $this->app->router->trigger(500);
     * ```
     */
    public static function onTrigger(
        int $status,
        Segments $segments
    ): int;
}