<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Routing;

use \Luminova\Exceptions\AppException;
use \Throwable;

class RouterException extends AppException
{
    /**
     * @var array<string, string> $types
    */
    private static array $types = [
        'invalid_argument' => 'Invalid argument "%s", expected "%s", "%s" is given.',
        'empty_argument' => 'Invalid argument "%s", cannot be empty.',
        'invalid_namespace' => 'Invalid namespace. Only namespaces starting with "\App\Controllers\" are allowed.',
        'invalid_context' => 'The application environment is not configured correctly. The route context "%s" may be missing or incorrect.',
        'invalid_context_log' => 'The view context "%s" is missing create view context to register your application routes /routes/%s.php',
        'invalid_controller' => 'Invalid class "%s". Only subclasses of BaseCommand, BaseController, BaseViewController, ViewErrors, or BaseApplication are allowed.',
        'invalid_class' => 'Class "%s" does not exist in the App\Controllers namespace.',
        'invalid_method' => 'Invalid method "%s" in controller. Only public non-static methods are allowed.',
        'invalid_cli_middleware' => 'The before middleware is not used in cli context, use middleware() instead',
        'invalid_middleware' => 'The middleware method is not used in web context, use before() for cli instead'
    ];

    /**
     * Thrown when a cookie-related error occurs.
     *
     * @param string $type The type of error.
     * @param int $code Exception code 
     * @param mixed ...$values Message placeholders
     * 
     * @return void
     * @throws self
    */
    public static function throwWith(string $type, int $code = 0, ?Throwable $previous = null, mixed ...$values): void
    {
     
        $message = static::withMessage($type, ...$values);

        static::throwException($message, $code, $previous);

        if($type === 'invalid_context'){
            logger('critical', static::withMessage('invalid_context_log', $values));
        }
    }

    /**
     * Get formatted message 
     *
     * @param string $type The type of error.
     * @param mixed ...$values Message placeholders
     * 
     * @return string $finalMessage
    */
    public static function withMessage(string $type, mixed ...$values): string
    {
        $message = static::$types[$type] ?? 'Unknown error occurred while creating route';
        $finalMessage = empty($values) ? $message : sprintf($message, ...$values);

        return $finalMessage;
    }
}