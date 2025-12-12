<?php 
declare(strict_types=1);
/**
 * Luminova Framework's column condition.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Query\Helpers;

use \Stringable;

/**
 * Raw SQL expression base class.
 */
abstract class RawExpression implements Stringable
{
    public function __construct() {}

    /**
     * Convert expression to SQL.
     */
    public function toString(): string
    {
        return '';
    }

    /**
     * Convert the raw expression to a string.
     *
     * This allows seamless usage in string contexts.
     *
     * @return string Return the raw SQL expression.
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}