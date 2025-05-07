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

use \Luminova\Exceptions\AppException;
use \Throwable;

class RouterException extends AppException
{
    /**
     * Routing system errors.
     * 
     * @var array<string, string> $types 
     */
    private static array $types = [
        'invalid_argument' => 'Invalid argument "%s", expected "%s", "%s" is given.',
        'empty_argument' => 'Invalid argument "%s", cannot be empty.',
        'invalid_namespace' => 'Invalid MVC namespace: "%s". Must start with "\App\" and end with "Controllers\" (e.g., "\App\Controllers\", "\App\<Folder>\Controllers\").',
        'invalid_module_namespace' => 'Invalid HMVC module namespace: "%s". Must start with "\App\Modules\" and end with "Controllers\" (e.g., "\App\Modules\Controllers\", "\App\Modules\<Module>\Controllers\").',
        'invalid_context' => 'The application environment is not configured correctly. The route context "%s" may be missing or incorrect.',
        'invalid_context_log' => 'The view context "%s" is missing create view context to register your application routes /routes/%s.php',
        'invalid_controller' => 'Invalid class "%s". Only subclasses of BaseCommand, BaseController, or RoutableInterface are allowed.',
        'invalid_class' => 'Class "%s" does not exist in any of application registered namespaces: (%s).',
        'invalid_method' => 'Invalid method "%s" in controller. Only public non-static methods are allowed.',
        'invalid_cli_middleware' => 'The before middleware is not used in cli context, use middleware() instead',
        'invalid_middleware' => 'The middleware method is not used in web context, use before() for cli instead',
        'bad_method' => 'Method "%s()" does not accept any arguments, but %d were provided in router patterns called in %s, line: %d',
        'no_method' => 'Call to undefined or inaccessible method %s::%s',
        'no_context' => 'No router context was provided. Refer to the documentation for context setup.',
        'no_route' => 'No matching route nor method was found to handle request or your routing configuration is incorrect.'
    ];

    /**
     * Constructor for RouterException.
     *
     * @param string  $message The exception message.
     * @param string|int $code  The exception code (default: 4161).
     * @param Throwable|null $previous The previous exception if applicable (default: null).
     */
    public function __construct(
        string $message, 
        string|int $code = self::ROUTING_ERROR, 
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Thrown router exception.
     *
     * @param string $type The type of error.
     * @param string|int $code Exception code (default: 4161).
     * @param array $values Message placeholders.
     * 
     * @return void
     * @throws static Throw the exception message.
    */
    public static function throwWith(
        string $type, 
        string|int $code = self::ROUTING_ERROR, 
        array $values = []
    ): void
    {
        throw new self(self::withMessage($type, ...$values), $code);
    }

    /**
     * Get formatted message.
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