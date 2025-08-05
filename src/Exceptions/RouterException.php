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
namespace Luminova\Exceptions;

use \Throwable;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Exceptions\AppException;

class RouterException extends AppException
{
    /**
     * Constructor for RouterException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: `ErrorCode::ROUTING_ERROR`).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(string $message, string|int $code = ErrorCode::ROUTING_ERROR, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Throw a routing system exception.
     * 
     * Throw an exception from description key type.
     *
     * @param string $type The error description property key.
     * @param string|int $code Exception code (default: `ErrorCode::ROUTING_ERROR`).
     * @param array $values Array of placeholders to format message if key support formatting.
     * 
     * @return void
     * @throws RouterException Throw the exception message.
    */
    public static function rethrow(string $type, string|int $code = ErrorCode::ROUTING_ERROR, array $values = []): void
    {
        [$file, $line] = parent::trace(2);

        $e = new self(self::getInformation($type, ...$values), $code);

        if($file){
            $e->setLine($line)->setFile($file);
        }

        throw $e;
    }

    /**
     * Get formatted error message.
     *
     * @param string $type The error description property key.
     * @param mixed ...$values Arguments of placeholders to format message if key support formatting.
     * 
     * @return string Return a formatted error message.
    */
    public static function getInformation(string $type, mixed ...$values): string
    {
        $message = self::getErrorInformation($type);

        return empty($values) ? $message : sprintf($message, ...$values);
    }

     /**
     * Routing system error description.
     * 
     * @param string $property The error key name.
     * 
     * @return string Return error message. 
     */
    private static function getErrorInformation(string $property): string 
    {
        $url = 'https://luminova.ng/docs/0.0.0/';

        return match($property){
            'argument.empty' => 'Invalid argument "%s": value cannot be empty.',
            'invalid.namespace' => 'Invalid namespace format "%s". Use only letters, numbers, and single or double backslashes between segments.',
            'invalid.namespace.root' => 'Invalid "%s" namespace "%s". Namespace must start with "%s". Other roots are not allowed%s. See docs: <link>' . $url . 'routing/system#lmv-docs-addnamespace</link>',
            'invalid.namespace.end' => 'Invalid "%s" namespace "%s". Namespace must end with "\Controllers\". Other endings are not allowed. See docs: <link>' . $url . 'routing/system#lmv-docs-addnamespace</link>',
            'invalid.context' => 'Application misconfigured: route context "%s" is missing or incorrect. See docs <link>' . $url . 'routing/url-prefix</link>, <link>' . $url . 'boot/public</link>',
            'invalid.controller' => 'Invalid class "%s". Must extend "Luminova\Base\Command", "Luminova\Base\Controller", or implement "Luminova\Interface\RoutableInterface".',
            'invalid.request.method' => 'Invalid request method "%s" in %s content.',
            'invalid.class' => 'Class "%s" not found in any registered namespace: (%s).',
            'invalid.method' => 'Invalid method "%s" in controller. Only public non-static methods are allowed.',
            'invalid.middleware.cli' => 'The "guard()" method is not allowed non-CLI context. Use "middleware()" instead.',
            'invalid.middleware.http' => 'The "middleware()" method is not allowed in CLI context. Use "guard()" instead.',
            'bad.method' => 'Method "%s()" accepts no arguments, but %d were provided (route URI pattern in %s, line %d).',
            'no.context' => 'No route context defined to handle this request. See docs: <link>' . $url . 'introduction/features<link>, <link>' . $url . 'boot/public<link>',
            'no.route.handler' => 'No matching route or method found to handle the request, or routing configuration is incorrect.',
            'invalid.cli.group' => 'Invalid CLI group name "%s". A valid command group must start with a letter and can contain only lowercase letters, numbers, hyphens (-), underscores (_), or colons (:).',
            default => 'RoutingSystemError: Unknown error occurred while routing request.'
        };
    }
}