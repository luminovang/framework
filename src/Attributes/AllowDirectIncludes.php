<?php
/**
 * Luminova Framework Class-Scope Route Error Attribute.
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
final class AllowDirectIncludes
{
    /**
     * Allows direct PHP include/require statements in this class.
     *
     * When enabled, the debugger will skip include/require enforcement.
     */
    public function __construct() {}
}