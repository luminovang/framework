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
use \Luminova\Exceptions\RouterException;

#[Attribute(Attribute::TARGET_CLASS)]
final class Prefix
{
    /**
     * Defines a non-repeatable routing prefix for HTTP controller classes, 
     * specifying URI prefix pattern and HTTP error handling.
     *
     * This attribute assigns a URI prefix and an optional error handler to a controller class, enabling centralized 
     * routing and error management. Only one prefix can be assigned to a controller.
     *
     * @param string $pattern The URI prefix or pattern that the controller should handle (e.g., `/user/account`, `/user/?.*`, `/user/(:root)`).
     * @param string|array|null $onError An optional error handler, either as a callable or a [class, method] array, for handling routing errors.
     * @throws RouterException If the provided error handler is not a valid callable.
     * 
     * @example Usage:
     * ```php
     * // /app/Controllers/Http/RestController.php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Prefix;
     * use App\Errors\Controllers\ErrorController;
     * 
     * #[Prefix(pattern: '/api/(:root)', onError: [Views::class, 'onWebError'])]
     * class RestController extends BaseController {
     *      // Class implementation
     * }
     * ```
     */
    public function __construct(
        public string $pattern,
        public string|array|null $onError = null
    ) 
    {
        if ($this->onError === null) {
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
