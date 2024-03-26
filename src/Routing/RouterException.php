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
        'invalid_controller' => 'Invalid class "%s". Only subclasses of BaseCommand, BaseController, BaseViewController, or BaseApplication are allowed.',
        'invalid_class' => 'Class "%s" does not exist in the App\Controllers namespace.',
        'invalid_method' => 'Invalid method "%s" in controller. Only public non-static methods are allowed.'
    ];

    /**
     * Thrown when a cookie-related error occurs.
     *
     * @param string $type The type of error.
     * @param int $code Exception code 
     * @param array ...$values Message placeholders
     * 
     * @return void
     * @throws self
    */
    public static function throwWith(string $type, int $code = 0, array ...$values): void
    {
        $message = static::withMessage($type, $values);

        self::throwException($message, $code);

        if($type === 'invalid_context'){
            logger('critical', static::withMessage('invalid_context_log', $values));
        }
    }

    /**
     * Get formatted message 
     *
     * @param string $type The type of error.
     * @param array ...$values Message placeholders
     * 
     * @return string $finalMessage
    */
    public static function withMessage(string $type, array ...$values): string
    {
        $message = static::$types[$type] ?? 'Unknown error occurred while creating route';
        $finalMessage = empty($values) ? $message : sprintf($message, $values);

        return $finalMessage;
    }
}