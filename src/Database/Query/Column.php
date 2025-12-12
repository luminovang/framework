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
namespace Luminova\Database\Query;

use \Closure;
use \Throwable;
use Luminova\Database\Query\Builder;
use Luminova\Database\Query\Expression;
use Luminova\Exceptions\InvalidArgumentException;

/**
 * @see Builder::whereGroup()
 * @see Builder::whereNested()
 * @see Builder::onCompound()
 * @see Builder::column()
 */
final class Column
{
    /**
     * Create a query column condition.
     *
     * Represents a column, comparison operator, and value used by the query
     * builder when constructing grouped or nested conditions.
     *
     * @param string $name The column name.
     * @param string $operator The comparison operator (e.g., `=`, `!=`, `<`, `>`, `LIKE`).
     * @param mixed $value The value to compare against.
     *
     * @throws InvalidArgumentException If the column name or operator is invalid.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $operator,
        public readonly mixed $value,
    ) 
    {
        if(trim($this->name) === ''){
            throw new InvalidArgumentException(
                "Column name cannot be empty."
            );
        }

        Builder::parseOperator($this->operator);
    }

    /**
     * Check whether the condition value is a closure.
     *
     * @return bool True if the value is a closure, otherwise false.
     */
    public function isClosureValue(): bool
    {
        return $this->value instanceof Closure;
    }

    /**
     * Convert the column condition to the builder's internal array format.
     *
     * @return array{string:array{operator:string,value:mixed}} Return array column structure.
     */
    public function toArray(): array
    {
        return [
            $this->name => [
                'operator' => Builder::toWhereOperator($this->operator, $this->value),
                'value'    => $this->value ?? new Expression('NULL'),
            ],
        ];
    }

    /**
     * Extract the column condition components.
     *
     * Returns the column name, comparison operator, and resolved value in order.
     * Closure values are evaluated before returning the result.
     *
     * @param bool $extractRaw Whether to extract the raw SQL string from an expression value.
     * @param Builder|null $newBuilder Optional builder instance used when resolving closure values.
     *
     * @return array{0:string,1:string,2:mixed} The column name, operator, and comparison value.
     * @throws Throwable If resolving a closure value fails or an invalid operation occurs.
     *
     * @example - Example:
     * ```php
     * [$name, $operator, $value] = $column->getColumn();
     *
     * // $name     = 'foo'
     * // $operator = '='
     * // $value    = 'bar'
     * ```
     */
    public function getColumn(bool $extractRaw = false, ?Builder $newBuilder = null): array
    {
        $value = $this->value;
        
        if ($value instanceof Closure) {
            $value = Builder::resolveClosureValue($value, $newBuilder);
        }

        if ($extractRaw && $value instanceof Expression) {
            $value = $value->toString();
        }

        return [
            $this->name,
            Builder::toWhereOperator($this->operator, $value),
            $value ?? new Expression('NULL'),
        ];
    }
}