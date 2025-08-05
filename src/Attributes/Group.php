<?php
/**
 * Luminova Framework Class-Scope CLI Route Group Attribute
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
final class Group
{
    /**
     * Defines a unique command group to CLI controllers.
     *
     * This attribute assigns a group name to a routable CLI controller, so all commands
     * within that group share a common name during command routing execution. Each controller can
     * only have **one group**.
     *
     * @param string $name The command group name (e.g., 'foo').
     * 
     * @throws RouterException If an invalid command group name is provided.
     * @since 3.5.6
     * @see https://luminova.ng/docs/0.0.0/routing/dynamic-uri-pattern
     * @see https://luminova.ng/docs/0.0.0/attributes/cli-group
     *
     * @example Usage:
     * Command: (`php index.php foo <arguments> <options>`)
     * ```php
     * namespace App\Controllers\Cli;
     * 
     * use Luminova\Base\BaseCommand;
     * use Luminova\Attributes\Group;
     *
     * #[Group(name: 'foo')]
     * class CommandController extends BaseCommand {
     *      // Must match the group name
     *      protected string $group = 'foo';
     *
     *      // Command implementations here
     * }
     * ```
     * > Note: Only one group can be assigned per controller class.
     */
    public function __construct(public string $name) {}
}