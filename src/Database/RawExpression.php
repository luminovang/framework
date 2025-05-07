<?php 
/**
 * Luminova Framework's raw SQL expression.
 * 
 * This bypasses query binding, allows embedding raw SQL expressions into queries 
 * while preventing automatic escaping by the database builder.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database;

use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\BadMethodCallException;
use \Stringable;
use \JsonSerializable;

/**
 * @method static RawExpression currentDate()   Create a raw SQL expression for current date, using `CURDATE()` SQL function.
 * @method static RawExpression currentTime()   Create a raw SQL expression for current time, using `CURTIME()` SQL function.
 * @method static RawExpression currentYear()   Create a raw SQL expression for current year, using `YEAR(NOW())` SQL function.
 * @method static RawExpression currentMonth()  Create a raw SQL expression for current month, using `MONTH(NOW())` SQL function.
 * @method static RawExpression currentDay()    Create a raw SQL expression for current day, using `DAY(NOW())` SQL function.
 * @method static RawExpression currentHour()   Create a raw SQL expression for current hour, using `HOUR(NOW())` SQL function.
 * @method static RawExpression currentMinute() Create a raw SQL expression for current minute, using `MINUTE(NOW())` SQL function.
 * @method static RawExpression currentSecond() Create a raw SQL expression for current seconds, using `SECOND(NOW())` SQL function.
 */
final class RawExpression implements Stringable, JsonSerializable
{
    /**
     * Create a new RawExpression instance.
     *
     * @param string $expression The raw SQL expression.
     * 
     * @throws InvalidArgumentException Throw if empty expression is given.
     */
    public function __construct(private string $expression)
    {
        if (trim($this->expression) === '') {
            throw new InvalidArgumentException('The expression must be a string.');
        }
    }
    
    /**
     * Dynamically handle static method calls for SQL date and time functions.
     *
     * This method allows calling SQL date and time functions dynamically
     * using uppercase method names prefixed with "CURRENT". For example:
     * 
     * - `RawExpression::currentDate()` -> `CURDATE()`
     * - `RawExpression::currentTime()` -> `CURTIME()`
     * - `RawExpression::currentYear()` -> `YEAR(NOW())`
     * - `RawExpression::currentMonth()` -> `MONTH(NOW())`
     *
     * @param string $method The called static method name.
     * @param array<int,mixed> $arguments The arguments passed to the method (unused).
     * 
     * @return self Returns a new RawExpression instance with the generated SQL function.
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        $expression = strtoupper($method);

        if (!str_starts_with($expression, 'CURRENT')) {
            throw new BadMethodCallException("Method '$method' does not exist.");
        }

        $method = substr($expression, 7);

        return new self(in_array($method, ['TIME', 'DATE'], true) ? "CUR$method()" : "$method(NOW())");
    }

    /**
     * Create a raw SQL expression for the current timestamp.
     *
     * @return self Returns an instance with the `NOW()` SQL function.
     */
    public static function now(): self
    {
        return new self('NOW()');
    }

   /**
     * Create a raw SQL expression to extract the year from a date or timestamp.
     *
     * @param string $expression The column name or SQL expression containing the date.
     * @return self Returns an instance representing `YEAR(expression)`.
     */
    public static function year(string $expression): self
    {
        return new self("YEAR($expression)");
    }

    /**
     * Create a raw SQL expression to extract the month from a date or timestamp.
     *
     * @param string $expression The column name or SQL expression containing the date.
     * @return self Returns an instance representing `MONTH(expression)`.
     */
    public static function month(string $expression): self
    {
        return new self("MONTH($expression)");
    }

    /**
     * Create a raw SQL expression to extract the day from a date or timestamp.
     *
     * @param string $expression The column name or SQL expression containing the date.
     * @return self Returns an instance representing `DAY(expression)`.
     */
    public static function day(string $expression): self
    {
        return new self("DAY($expression)");
    }

    /**
     * Create a raw SQL expression to extract the time from a datetime or timestamp.
     *
     * @param string $expression The column name or SQL expression containing the datetime.
     * 
     * @return self Returns an instance representing `TIME(expression)`.
     */
    public static function time(string $expression): self
    {
        return new self("TIME($expression)");
    }

    /**
     * Create a raw SQL expression for the COUNT(*) function.
     *
     * @param string $column The column name to index counting (default: `*`).
     * 
     * @return self Returns an instance with the `COUNT(column)` SQL function.
     */
    public static function count(string $column = '*'): self
    {
        return new self("COUNT($column)");
    }

    /**
     * Create a SUM aggregate function expression.
     *
     * This method generates a SQL `SUM()` expression, which calculates the 
     * total sum of a specified column in a query.
     *
     * @param string $column The column to calculate sum.
     * 
     * @return self Returns an instance with the `SUM(column)` SQL function.
     * 
     * > Using `*` as the column name is not recommended, specify a column instead.
     */
    public static function sum(string $column): self
    {
        return new self("SUM($column)");
    }

    /**
     * Create an AVG aggregate function expression.
     *
     * This method generates a SQL `AVG()` expression, which calculates the 
     * average value of a specified column in a query.
     *
     * @param string $column The column to calculate average.
     * 
     * @return self Returns a new instance containing the `AVG(column)` expression.
     * 
     * > Using `*` as the column name is not recommended, specify a column instead.
     */
    public static function average(string $column): self
    {
        return new self("AVG($column)");
    }

    /**
     * Create a raw SQL expression for incrementing a column.
     *
     * @param string $column The column name to increment.
     * @param int $amount The increment amount (default: 1).
     * 
     * @return self Returns an instance with the increment SQL expression.
     */
    public static function increment(string $column, int $amount = 1): self
    {
        return new self("$column + $amount");
    }

    /**
     * Create a raw SQL expression for decrementing a column.
     *
     * @param string $column The column name to decrement.
     * @param int $amount The decrement amount (default: 1).
     * 
     * @return self Returns an instance with the decrement SQL expression.
     */
    public static function decrement(string $column, int $amount = 1): self
    {
        return new self("$column - $amount");
    }

    /**
     * Get the raw SQL expression.
     *
     * @return string Return the raw SQL expression.
     */
    public function getExpression(): string
    {
        return $this->expression;
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
        return $this->expression;
    }

    /**
     * Serialize the expression for JSON output.
     *
     * @return string Return the serialized string.
     */
    public function jsonSerialize(): string
    {
        return $this->expression;
    }
}