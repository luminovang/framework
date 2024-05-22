<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Exceptions;

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
        'invalid_middleware' => 'The middleware method is not used in web context, use before() for cli instead',
        'bad_method' => 'Method "%s()" does not accept any arguments, but %d were provided in router patterns called in %s, line: %d',
        'no_method' => 'Call to undefined or inaccessible method %s::%s'
    ];

    /**
     * Thrown router exception.
     *
     * @param string $type The type of error.
     * @param int $code Exception code.
     * @param array $values Message placeholders.
     * 
     * @return void
     * @throws static Exception message.
    */
    public static function throwWith(string $type, int $code = 0, array $values = []): void
    {
        throw new static(static::withMessage($type, ...$values), $code);
    }

    /**
     * Get formatted message 
     *
     * @param string $type The type of error.
     * @param mixed ...$values Message placeholders.
     * 
     * @return string Return formatted message.
    */
    public static function withMessage(string $type, mixed ...$values): string
    {
        $message = self::$types[$type] ?? 'Unknown error occurred while creating route';
        return empty($values) ? $message : sprintf($message, ...$values);
    }
}