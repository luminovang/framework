<?php
/**
 * Luminova Framework Method Class Route Error Attribute
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

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS)]
final class Error
{
    /**
     * Defines a repeatable attribute for handling global HTTP route errors.
     *
     * This attribute assigns an error handler to a specific URI pattern within a given context,
     * allowing fine-grained control over how routing errors are managed. Multiple error handlers 
     * can be defined for different URI prefix-contexts and patterns within the same controller.
     *
     * @param string $context The routing context used to categorize the URI (default: `web`). 
     *                        Typically, this is the first segment of the URI (e.g., `api`, `blog`).
     * @param string $pattern The route pattern to match for error handling (e.g., `/`, `/.*`, `/blog/([0-9-.]+)`, `/blog/(:placeholder)`).
     *                        Can be a specific path, a regex-style pattern, or a placeholder.
     * @param string|array|null $onError A callable error handler, either as a string or a [class, method] array. 
     *                                   This handler will be invoked when the specified pattern matches.
     * 
     * @throws RouterException If the provided error handler is not callable.
     * 
     * @example Example usage:
     * 
     * ```php
     * // /app/Controllers/Http/MyController.php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\BaseController;
     * use Luminova\Attributes\Error;
     * use App\Errors\Controllers\ErrorController;
     * 
     * #[Error('web', pattern: '/', onError: [ErrorController::class, 'onWebError'])]
     * #[Error('foo', pattern: '/foo/', onError: [ErrorController::class, 'onWebFooError'])]
     * class MyController extends BaseController {
     *      // Class implementation
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