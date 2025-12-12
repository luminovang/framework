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

use \Stringable;
use \JsonSerializable;
use Luminova\Database\Helpers\Util;
use Luminova\Exceptions\BadMethodCallException;
use Luminova\Exceptions\InvalidArgumentException;

/**
 * @method static Expression currentDate() Create an SQL expression for current date, using `CURDATE()`.
 * @method static Expression currentTime() Create an SQL expression for current time, using `CURTIME()`.
 * @method static Expression currentYear() Create an SQL expression for current year, using `YEAR(NOW())`.
 * @method static Expression currentMonth() Create an SQL expression for current month, using `MONTH(NOW())`.
 * @method static Expression currentDay() Create an SQL expression for current day, using `DAY(NOW())`.
 * @method static Expression currentHour() Create an SQL expression for current hour, using `HOUR(NOW())`.
 * @method static Expression currentMinute() Create an SQL expression for current minute, using `MINUTE(NOW())`.
 * @method static Expression currentSecond() Create an SQL expression for current seconds, using `SECOND(NOW())`.
 */
final class Expression implements Stringable, JsonSerializable
{
    /**
     * @var string DISTANCE_METHOD_SPHERE
     */
    public const DISTANCE_METHOD_SPHERE = 'sphere';

    /**
     * @var string DISTANCE_METHOD_SPHERE_WKT
     */
    public const DISTANCE_METHOD_SPHERE_WKT = 'sphere.wkt';

    /**
     * @var string DISTANCE_METHOD_HAVERSINE
     */
    public const DISTANCE_METHOD_HAVERSINE = 'haversine';
 
    /**
     * @var array DISTANCE_RADIUS
     */
    public const DISTANCE_RADIUS = [
        'km'  => [self::DISTANCE_METHOD_SPHERE => 1000.0,    self::DISTANCE_METHOD_HAVERSINE => 6371.0],
        'mi'  => [self::DISTANCE_METHOD_SPHERE => 1609.34,   self::DISTANCE_METHOD_HAVERSINE => 3959.0],
        'm'   => [self::DISTANCE_METHOD_SPHERE => 1.0,       self::DISTANCE_METHOD_HAVERSINE => 6371000.0],
        'ft'  => [self::DISTANCE_METHOD_SPHERE => 0.3048,    self::DISTANCE_METHOD_HAVERSINE => 20903520.0],
    ];

    /**
     * Create a new Expression instance.
     *
     * @param string $expression The raw SQL expression.
     * 
     * @throws InvalidArgumentException Throw if empty expression is given.
     */
    public function __construct(private string $expression)
    {
        if (trim($this->expression) === '') {
            throw new InvalidArgumentException(
                'The expression must be a string.'
            );
        }
    }

    /**
     * Dynamically static proxy method calls for SQL date and time functions.
     *
     * This method allows calling SQL date and time functions dynamically
     * using uppercase method names prefixed with "CURRENT". For example:
     * 
     * - `Expression::currentDate()` -> `CURDATE()`
     * - `Expression::currentTime()` -> `CURTIME()`
     * - `Expression::currentYear()` -> `YEAR(NOW())`
     * - `Expression::currentMonth()` -> `MONTH(NOW())`
     *
     * @param string $method The called static method name.
     * @param array<int,mixed> $arguments The arguments passed to the method (unused).
     * 
     * @return self Returns a new Expression instance with the generated SQL function.
     */
    public static function __callStatic(string $method, array $arguments): self
    {
        $expression = strtoupper($method);

        if (!str_starts_with($expression, 'CURRENT')) {
            throw new BadMethodCallException("Method '$method' does not exist.");
        }

        $method = substr($expression, 7);

        return new self(in_array($method, ['TIME', 'DATE'], true) 
            ? "CUR{$method}()" 
            : "{$method}(NOW())");
    }

    /**
     * Convert a PHP float array into a SQL-ready literal.
     *
     * For PostgreSQL: returns `[0.12,-0.34,...]`  
     * For other DBs: returns JSON string for storage in TEXT/JSON column.
     *
     * @param float[] $embedding Numeric array representing the embedding vector.
     * @param bool $pgFormat Set true for PostgreSQL pgvector syntax, false for JSON
     * 
     * @return self Returns an instance containing the SQL-ready literal.
     */
    public static function vector(array $embedding, bool $pgFormat = false): self
    {
        $literal = $pgFormat
            ? '[' . implode(',', $embedding) . ']'
            : self::escape($embedding);

        return new self($literal);
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
     * @param float|int $amount The increment amount (default: 1).
     * 
     * @return self Returns an instance with the increment SQL expression.
     */
    public static function increment(string $column, float|int $amount = 1): self
    {
        return new self("$column + $amount");
    }

    /**
     * Create a raw SQL expression for decrementing a column.
     *
     * @param string $column The column name to decrement.
     * @param float|int $amount The decrement amount (default: 1).
     * @param bool $allowNegative Whether to prevent the result from becoming negative.
     * 
     * @return self Returns an instance with the decrement SQL expression.
     */
    public static function decrement(
        string $column,
        float|int $amount = 1,
        bool $allowNegative = false
    ): self 
    {
        $amount = self::number($amount);

        return new self(
            $allowNegative
                ? "$column - $amount"
                : "IF($column >= $amount, $column - $amount, 0)"
        );
    }

    /**
     * Creates a raw SQL expression for the `VALUES()` function.
     *
     * This is typically used in `ON DUPLICATE KEY UPDATE` clauses to reference the value that
     * would have been inserted into a column.
     *
     * > **Note:** `VALUES()` is valid in MySQL versions below 8.0.20. In newer versions,
     * consider using `AS new ... UPDATE col = new.col` if you're targeting MySQL 8+ strictly.
     *
     * @param string $column The column name to reference from the attempted insert.
     *
     * @return self Returns a new Expression instance wrapping the `VALUES(column)` expression.
     *
     * @example - Example:
     * ```php
     * Builder::table('users')
     *     ->insert([...])
     *     ->onDuplicate(['email' => Expression::values('email')]);
     * ```
     */
    public static function values(string $column): self
    {
        return new self("VALUES($column)");
    }

    /**
     * Create a SQL expression that calculates the distance between two coordinates.
     *
     * Generates a database distance calculation expression using either the native
     * spatial `ST_Distance_Sphere()` function or the Haversine formula.
     *
     * The row latitude and longitude columns are compared against a fixed coordinate
     * or bound SQL placeholders. Rows with missing latitude or longitude values
     * return `NULL` instead of producing invalid geometry calculations.
     *
     * The returned expression can be used in SELECT clauses, ordering, or other
     * SQL expressions.
     *
     * @param string $lngColumn Longitude column name.
     * @param string $latColumn Latitude column name.
     * @param string|float|null $longitudeExpr Reference longitude value or SQL placeholder.
     *      Defaults to `:longitude`.
     * @param string|float|null $latitudeExpr Reference latitude value or SQL placeholder.
     *      Defaults to `:latitude`.
     * @param string $unit Distance unit: `km`, `mi`, `m`, or `ft`.
     * @param string $method Calculation method: `sphere`, sphere.wkt` or `haversine`.
     *
     * @return self Returns an SQL expression instance containing the distance calculation.
     *
     * @throws InvalidArgumentException If the distance unit or calculation method
     *      is not supported.
     *
     * @example Using placeholders
     * ```php
     * $expression = Expression::distance(
     *     'user_longitude',
     *     'user_latitude',
     *     ':longitude',
     *     ':latitude',
     * );
     *
     * Builder::table('users')
     *     ->select([
     *         'user_name',
     *         $expression->alias('distance')
     *     ])
     *     ->bind(':longitude', 3.3792)
     *     ->bind(':latitude', 6.5244)
     *     ->get();
     * ```
     *
     * @example Using fixed coordinates
     * ```php
     * Builder::table('users')
     *     ->select([
     *         Expression::distance(
     *             'user_longitude',
     *             'user_latitude',
     *             3.3792,
     *             6.5244,
     *         )
     *     ])
     *     ->get();
     * ```
     */
    public static function distance(
        string $lngColumn,
        string $latColumn,
        string|float|null $longitudeExpr = null,
        string|float|null $latitudeExpr = null,
        string $unit = 'km',
        string $method = 'sphere'
    ): self
    {
        $unit = strtolower($unit);
        $method = strtolower($method);
        $radius = self::distanceRadius($unit, $method);

        $longitudeExpr = ($longitudeExpr === null) 
            ? ':longitude' 
            : Util::normalizeCoordinate($longitudeExpr, 'longitudeExpr', -180, 180);

        $latitudeExpr = ($latitudeExpr === null) 
            ? ':latitude' 
            : Util::normalizeCoordinate($latitudeExpr, 'latitudeExpr', -90, 90);

        $expression = match ($method) {
            self::DISTANCE_METHOD_SPHERE => "
                ST_Distance_Sphere(
                    POINT({$lngColumn}, {$latColumn}),
                    POINT({$longitudeExpr}, {$latitudeExpr})
                ) / {$radius}
            ",

            self::DISTANCE_METHOD_SPHERE_WKT => "
                ST_Distance_Sphere(
                    ST_GeomFromText(
                        CONCAT('POINT(', {$lngColumn}, ' ', {$latColumn}, ')'),
                        4326
                    ),
                    ST_GeomFromText(
                        CONCAT('POINT(', {$longitudeExpr}, ' ', {$latitudeExpr}, ')'),
                        4326
                    )
                ) / {$radius}
            ",

            self::DISTANCE_METHOD_HAVERSINE => "
                ({$radius} * ACOS(
                    LEAST(1, GREATEST(-1,
                        COS(RADIANS({$latitudeExpr})) 
                        * COS(RADIANS({$latColumn}))
                        * COS(RADIANS({$lngColumn}) - RADIANS({$longitudeExpr}))
                        + SIN(RADIANS({$latitudeExpr}))
                        * SIN(RADIANS({$latColumn}))
                    ))
                ))
            ",
            default => throw new InvalidArgumentException(
                "Unsupported distance method [{$method}]."
            )
        };

        return new self("
            CASE
                WHEN {$latColumn} IS NULL OR {$lngColumn} IS NULL
                THEN NULL
                ELSE {$expression}
            END
        ");
    }

    /**
     * Create a SQL CONCAT expression.
     *
     * Combines multiple columns or expressions into a single string value.
     *
     * @param string[] $columns The columns or expressions to concatenate.
     * @param string|null $alias Optional alias for the generated expression.
     *
     * @return self Returns an expression instance.
     * see self::concatWith()
     * 
     * @example - Example:
     * ```php
     * Expression::concat(['first_name', 'last_name']);
     * // Generates: CONCAT(first_name, last_name)
     * ```
     * 
     * @example - With Alias:
     * ```php
     * Expression::concat(['first_name', 'last_name'], 'name');
     * // Generates: CONCAT(first_name, last_name) AS name
     * ```
     */
    public static function concat(
        array $columns,
        ?string $alias = null,
    ): self 
    {
        if ($columns === []) {
            return new self('');
        }
 
        $expression = 'CONCAT(' . implode(', ', $columns) . ')';

        if ($alias === null) {
            return new self($expression);
        }

        return self::withAlias($expression, $alias);
    }

    /**
     * Create a SQL concatenate with separator expression.
     *
     * Concatenates columns or expressions using the specified separator.
     * NULL values are ignored by the database CONCAT_WS function.
     *
     * @param string $separator The separator used between values.
     * @param string[] $columns The columns or expressions to concatenate.
     * @param string|null $alias Optional alias for the generated expression.
     *
     * @return self Returns an expression instance.
     *
     * @see self::concat()
     */
    public static function concatWith(
        string $separator,
        array $columns,
        ?string $alias = null
    ): self 
    {
        if ($columns === []) {
            return new self('');
        }

        $expression = sprintf(
            "CONCAT_WS('%s', %s)",
            str_replace("'", "''", $separator),
            implode(', ', $columns)
        );

        if ($alias === null) {
            return new self($expression);
        }

        return self::withAlias($expression, $alias);
    }

   /**
     * Create a SQL JSON_EXTRACT expression.
     *
     * Extracts a JSON value from a column using the specified JSON path.
     *
     * @param string $column The JSON column name.
     * @param string $path The JSON path expression.
     * @param string|null $alias Optional alias for SELECT expressions.
     *
     * @return self Returns an expression instance.
     */
    public static function jsonExtract(
        string $column,
        string $path,
        ?string $alias = null
    ): self 
    {
        return self::json('JSON_EXTRACT', [
            $column,
            self::escape(self::toPath($path))
        ], $alias);
    }

    /**
     * Create a JSON scalar extraction expression.
     *
     * Extracts a JSON value and converts JSON strings into SQL strings.
     *
     * @param string $column The JSON column name.
     * @param string $path The JSON path expression.
     * @param string|null $alias Optional alias for SELECT expressions.
     *
     * @return self Returns an expression instance.
     */
    public static function jsonValue(
        string $column,
        string $path,
        ?string $alias = null
    ): self 
    {
        return self::json('JSON_UNQUOTE', [
            self::jsonExtract($column, self::toPath($path))->toString()
        ], $alias);
    }

    /**
     * Create a JSON_SET expression.
     *
     * Inserts or updates a value inside a JSON document.
     *
     * @param string $column The JSON column name.
     * @param string $path The JSON path expression.
     * @param mixed $value The JSON value to store.
     *
     * @return self Returns an expression instance.
     */
    public static function jsonSet(
        string $column,
        string $path,
        mixed $value
    ): self 
    {
        return self::json('JSON_SET', [
            $column,
            self::escape(self::toPath($path)),
            self::escape($value)
        ]);
    }

    /**
     * Create a JSON_REMOVE expression.
     *
     * Removes one or more paths from a JSON document.
     *
     * @param string $column The JSON column name.
     * @param string ...$paths JSON paths to remove.
     *
     * @return self Returns an expression instance.
     */
    public static function jsonRemove(
        string $column,
        string ...$paths
    ): self 
    {
        return self::json('JSON_REMOVE', [
            $column,
            ...array_map(
                static fn(string $path) => self::escape(self::toPath($path)),
                $paths
            )
        ]);
    }

    /**
     * Create a JSON_CONTAINS expression.
     *
     * Checks whether a JSON document contains a value.
     *
     * @param string $column The JSON column name.
     * @param mixed $value The JSON value to search for.
     * @param string|null $path Optional JSON path.
     *
     * @return self Returns an expression instance.
     */
    public static function jsonContains(
        string $column,
        mixed $value,
        ?string $path = null
    ): self 
    {
        $arguments = [
            $column,
            self::escape($value)
        ];

        if ($path !== null) {
            $arguments[] = self::escape(self::toPath($path));
        }

        return self::json('JSON_CONTAINS', $arguments);
    }

    /**
     * Create a JSON_LENGTH expression.
     *
     * Returns the number of elements in a JSON document or path.
     *
     * @param string $column The JSON column name.
     * @param string|null $path Optional JSON path.
     *
     * @return self Returns an expression instance.
     */
    public static function jsonLength(
        string $column,
        ?string $path = null
    ): self 
    {
        $arguments = [$column];

        if ($path !== null) {
            $arguments[] = self::escape(self::toPath($path));
        }

        return self::json('JSON_LENGTH', $arguments);
    }

    /**
     * Append an SQL alias to this expression.
     *
     * Adds an `AS` clause to the current expression and returns the same
     * expression instance for method chaining.
     *
     * @param string $alias SQL alias name.
     *
     * @return self Returns the expression instance.
     * @throws InvalidArgumentException If the alias is empty or invalid.
     *
     * @example - Example:
     * ```php
     * Expression::distance(
     *     'user_lat',
     *     'user_lng'
     * )->alias('distance');
     *
     * // Generates:
     * // ... AS distance
     * ```
     */
    public function alias(string $alias): self
    {
        $alias = trim($alias);

        self::assertAlias($alias);

        $this->expression .= " AS {$alias}";

        return $this;
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
     * Get the raw SQL expression.
     *
     * @return string Return the raw SQL expression.
     */
    public function toString(): string
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

    /**
     * Earth distance conversion values.
     *
     * Sphere: divisor for ST_Distance_Sphere() output (meters).
     * Haversine: earth radius in selected unit
     * 
     * @param string $unit
     * @param string $method
     * 
     * @return float
     * @throws InvalidArgumentException If not supported
     */
    public static function distanceRadius(string $unit, string $method): float
    {
        $method = match ($method) {
            self::DISTANCE_METHOD_SPHERE,
            self::DISTANCE_METHOD_SPHERE_WKT => self::DISTANCE_METHOD_SPHERE,
            self::DISTANCE_METHOD_HAVERSINE  => self::DISTANCE_METHOD_HAVERSINE,
            default => throw new InvalidArgumentException(
                "Unsupported distance method [{$method}]."
            ),
        };

        $unit = match ($unit) {
            'miles'     => 'mi',
            'meters'    => 'm',
            'feet'      => 'ft',
            'kilometer' => 'km',
            default     => $unit
        };
        
        return self::DISTANCE_RADIUS[$unit][$method] ?? throw new InvalidArgumentException(
            "Unsupported distance unit [{$unit}]."
        );
    }

    /**
     * Convert decrement amount.
     *
     * @param float|int $value
     * 
     * @return string
     */
    private static function number(float|int $value): string
    {
        return is_int($value)
            ? (string) $value
            : rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }

    /**
     * Escape value and quote literal string or json string.
     *
     * @param mixed $value
     * 
     * @return string
     */
    private static function escape(mixed $value): string
    {
        return match (true) { 
            $value === null => 'NULL', 
            is_bool($value) => $value ? 'TRUE' : 'FALSE', 
            is_int($value), is_float($value) => (string) $value, 
            is_array($value) || is_object($value) => json_encode($value, JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
            ),
            default => Builder::quote((string) $value)
        };
    }

    /**
     * Normalize path.
     * 
     * | Input         | Output          |
     * | ------------- | --------------- |
     * | `name`        | `$.name`        |
     * | `user.name`   | `$.user.name`   |
     * | `items[0].id` | `$.items[0].id` |
     * | `$.name`      | `$.name`        |
     * | `[0]`         | `$[0]`          |
     * | `$[0]`        | `$[0]`          |
     * | `$`           | `$`             |
     * | `""`          | `$`             |
     *
     * @param string $path
     * @return string
     */
    private static function toPath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $path === '$') {
            return '$';
        }

        if (str_starts_with($path, '$')) {
            return $path;
        }

        return str_starts_with($path, '[')
            ? '$' . $path
            : '$.' . ltrim($path, '.');
    }

    /**
     * Create JSON function expression.
     *
     * @param string $function
     * @param array $arguments
     * @param string|null $alias
     * 
     * @return self
     */
    private static function json(
        string $function,
        array $arguments,
        ?string $alias = null
    ): self 
    {
        $expression = $function . '(' . implode(', ', $arguments) . ')';

        if ($alias === null) {
            return new self($expression);
        }

        return self::withAlias($expression, $alias);
    }

    /**
     * Retrn new expression with alias.
     *
     * @param string $expr
     * @param string|null $alias
     * 
     * @return self
     */
    private static function withAlias(string $expr, string $alias): self 
    {
        self::assertAlias($alias);
        return new self("{$expr} AS  {$alias}");
    }

    /**
     * Validate an SQL alias identifier.
     *
     * @param string $alias Alias name.
     *
     * @return void
     *
     * @throws InvalidArgumentException If the alias is invalid.
     */
    private static function assertAlias(string $alias): void
    {
        if ($alias === '') {
            throw new InvalidArgumentException(
                'SQL alias cannot be empty.'
            );
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid SQL alias [%s]. Alias must start with a letter or underscore and contain only letters, numbers, and underscores.',
                    $alias
                )
            );
        }
    }
}