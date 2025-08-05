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

use \App\Application;
use \Luminova\Foundation\Core\Application as CoreApplication;

/**
 * Marker interface for controllers that handle routable errors.
 */
interface ErrorHandlerInterface 
{
    /**
     * Handle a manually triggered routing error.
     * 
     * This method is invoked when the routing system encounter `404` error while handling request 
     * or when explicitly triggers an error status (e.g., via `$router->trigger(404)`).
     * 
     * @param Application<CoreApplication> $app Application instance providing access to core services.
     * @param int $status HTTP status code associated with the error (default: 404).
     * @param array $arguments Optional request data such as URI segments or parameters.
     * 
     * @return int Return response status code either (`STATUS_SUCCESS` or `STATUS_SILENCE`).
     * @example - Usage:
     * 
     * Trigger Error Manually.
     * 
     * ```php
     * // App\Controllers\Http\*
     * // App\Modules\Controllers\Http\*
     * // App\Modules\<Module>\Controllers\Http\*
     * 
     * // Trigger with HTTP code
     * $this->app->router->trigger(500);
     * ```
     */
    public static function onTrigger(Application $app, int $status = 404, array $arguments = []): int;
}