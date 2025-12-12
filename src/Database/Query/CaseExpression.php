<?php 
declare(strict_types=1);
/**
 * Luminova Framework's case expression.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Query;

use Luminova\Database\Query\Expression;
use Luminova\Exceptions\LogicException;
use Luminova\Database\Query\Helpers\RawExpression;

/**
 * SQL CASE expression builder.
 */
final class CaseExpression extends RawExpression
{
    private array $when = [];
    private mixed $else = null;
    private ?string $sql = null;
    private string $alias = '';

    /**
     * Create a CASE expression.
     *
     * @param Expression|string|null $expression Optional expression for simple CASE.
     */
    public function __construct(private readonly Expression|string|null $expression = null) {}

    /**
     * Add a WHEN condition.
     *
     * @param string $column Column name.
     * @param string $operator Comparison operator.
     * @param string|float|int|null $value Comparison value.
     * @param string|float|int|null $then Result returned when condition matches.
     */
    public function when(
        string $column,
        string $operator,
        array|string|float|int|null $value,
        string|float|int|null $then = null
    ): self 
    {
        $operator = Builder::toWhereOperator($operator, $value);
        $value = $this->parse($value);
    
        $this->when[] = [
            'condition' => "{$column} {$operator} {$value}",
            'then'      => $this->parse($then),
        ];

        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string|float|integer $value
     * @param string|float|integer|null $then
     * @return self
     */
    public function match(string|float|int $value, string|float|int|null $then): self 
    {
        $this->when[] = [
            'condition' => $this->parse($value),
            'then'      => $this->parse($then),
        ];

        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string|float|integer|null $then
     * @return self
     */
    public function then(string|float|int|null $then): self
    {
        if ($this->when === []) {
            throw new LogicException(
                'Cannot call then() without a preceding when() clause.'
            );
        }

        $key = array_key_last($this->when);

        if (($this->when[$key]['then'] ?? 'NULL') !== 'NULL') {
            throw new LogicException(
                'The current when() clause already has a THEN value.'
            );
        }

        $this->when[$key]['then'] = $this->parse($then);

        return $this;
    }

    /**
     * Add ELSE result.
     * 
     * 
     */
    public function else(mixed $value): self
    {
        $this->else = $this->parse($value);

        return $this;
    }

    /**
     * Finalize CASE expression.
     */
    public function end(): self
    {
        $this->sql = $this->build();

        return $this;
    }

    /**
     * Add SQL alias.
     */
    public function as(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Convert the raw expression to a string.
     *
     * This allows seamless usage in string contexts.
     *
     * @return string Return the raw SQL expression.
     */
    public function toString(): string
    {
        return ($this->sql ??= $this->build())
            . (($this->alias !== '') ? " AS {$this->alias}" : '');
    }

    /**
     * Undocumented function
     *
     * @return Expression
     */
    public function toExpression(): Expression
    {
        return new Expression($this->toString());
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    private function build(): string
    {
        $condition = 'CASE';

        if ($this->expression !== null) {
            $condition .= ' ' . $this->expression;
        }

        foreach ($this->when as $when) {
            $condition .= " WHEN {$when['condition']} THEN {$when['then']}";
        }

        if ($this->else !== null) {
            $condition .= " ELSE {$this->else}";
        }

        return $condition . ' END';
    }

    /**
     * Undocumented function
     *
     * @param mixed $value
     * @return string
     */
    private function parse(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->toString();
        }

        return match (true) {
            $value === null   => 'NULL',
            is_string($value) => "'{$value}'",
            is_array($value)  => '(' . Builder::escapeValueList(array_values($value)) . ')',
            default => (string) $value,
        };
    }
}