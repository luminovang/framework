<?php
/**
 * Luminova Framework Method Class Route Prefix Attribute
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Attributes;
use \Attribute;
use \Closure;

#[Attribute(Attribute::TARGET_CLASS)]
final class Prefix
{
    /**
     * Defines a routing prefix for controller classes, specifying URI handling and error management.
     *
     * This attribute allows the assignment of a URI prefix, context, and error handling mechanism 
     * to a controller class. Only one prefix can be applied to a single class.
     *
     * @param string $pattern The URI prefix or patterns that the controller should handle (e.g, `/user/account`, `/user/?.*`, `/user/(:root)`).
     * @param Closure|array|null $onError An error handler, which can be a Closure or an array specifying the class and method for handling errors.
     *
     * @example Usage example:
     * ```php
     * #[Prefix(pattern: '/api/(:root)', onError: [ViewErrors::class, 'onWebError'])]
     * class RestController extends BaseController {}
     * ```
     */
    public function __construct(
        public string $pattern,
        public Closure|array|null $onError = null,
    ) {}
}
