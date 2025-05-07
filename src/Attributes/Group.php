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

#[Attribute(Attribute::TARGET_CLASS)]
final class Group
{
    /**
     * Defines a command group with a unique routing prefix for CLI controllers.
     * This attribute assigns a group name to a controller, allowing CLI commands 
     * within that group to share a common prefix for routing.
     *
     * @param string $name The command group name (e.g., 'foo').
     * @since 3.5.6
     * 
     * @example - Usage example:
     * 
     * ```php
     * // /app/Controllers/Cli/CommandController.php
     * namespace App\Controllers\Cli;
     * 
     * use Luminova\Base\BaseCommand;
     * use Luminova\Attributes\Group;
     * 
     * #[Group(name: 'foo')]
     * class CommandController extends BaseCommand {
     *      // Same name as group attribute name
     *      protected string $group = 'foo';
     * 
     *      // Class implementation
     * }
     * ```
     * > Only one group can be assigned to a controller class.
     */
    public function __construct(public string $name) 
    {}
}
