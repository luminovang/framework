<?php 
/**
 * Luminova Framework database builder class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database;

use \Luminova\Time\Time;
use \Luminova\Logger\Logger;
use \Luminova\Base\BaseCache;
use \Luminova\Cache\FileCache;
use \Luminova\Database\Manager;
use \Luminova\Utils\LazyObject;
use \Luminova\Cache\MemoryCache;
use \Luminova\Database\Connection;
use \Luminova\Database\RawExpression;
use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\CacheException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Throwable;
use \DateTimeZone;
use \JsonException;
use \DateTimeInterface;

final class Builder implements LazyInterface
{  
    /**
     * Debug mode: Collects SQL queries and parameters using the builder’s internal logic.
     *
     * Useful for inspecting how the builder constructs queries before they reach the database driver.
     *
     * @var int DEBUG_BUILDER
     */
    public const DEBUG_BUILDER = 1;

    /**
     * Debug mode: Enables debugging at the driver level (e.g., PDO, MySQLi).
     *
     * Captures the actual SQL statement and bound values sent to the database engine.
     *
     * @var int DEBUG_DRIVER
     */
    public const DEBUG_DRIVER = 2;

    /**
     * Debug mode: Dumps SQL queries and parameters to output during execution.
     *
     * Uses the builder’s internal logic and immediately prints the query structure for inspection.
     *
     * @var int DEBUG_BUILDER_DUMP
     */
    public const DEBUG_BUILDER_DUMP = 3;

    /**
     * Return result as an array.
     * 
     * @var string RETURN_ARRAY
     */
    public const RETURN_ARRAY = 'array';

    /**
     * Return result as an object.
     * 
     * @var string RETURN_OBJECT
     */
    public const RETURN_OBJECT = 'object';

    /**
     * Return prepared statement.
     * 
     * @var string RETURN_STATEMENT
     */
    public const RETURN_STATEMENT = 'stmt';

    /**
     * Copy between tables.
     * 
     * @var int MODE_COPY 
     */
    private const MODE_COPY = 3881;

    /**
     * Database connection instance.
     *
     * @var Connection<LazyInterface>|null $conn
     */
    private static ?LazyInterface $conn = null;

    /**
     * Database driver instance.
     *
     * @var DatabaseInterface|null $db
     */
    private ?DatabaseInterface $db = null;

    /**
     * Cache class instance.
     * 
     * @var BaseCache|null $cache 
     */
    private static ?BaseCache $cache = null;

    /**
     * Class instance.
     * 
     * @var self|null $instance 
     */
    private static ?self $instance = null;

    /**
     * Database table name to query.
     * 
     * @var string $tableName 
     */
    private string $tableName = '';

    /**
     * Table copy selection query.
     * 
     * @var string $copySelections 
     */
    private string $copySelections = '';

    /**
     * Table join bind parameters.
     * 
     * @var array $joinConditions 
     */
    private array $joinConditions = [];

    /**
     * Table query limit and offset for select method. 
     * 
     * @var string $selectLimit 
     */
    private string $selectLimit = '';

    /**
     * Supports row-level locking.
     * 
     * @var string $lock
     */
    private string $lock = '';

    /**
     * Table query max limit for update and delete methods.
     * 
     * @var int $maxLimit 
     */
    private int $maxLimit = 0;

    /**
     * Table query group column by.
     * 
     * @var array<string,array<int,string>> $options 
     */
    private array $options = [
        'grouping'  => [],
        'ordering'  => [],
        'filters'   => [],
        'binds'     => [],
        'duplicate' => [], // Insert on duplicate
        'match'     => [] // Table query match against order rows.
    ];

    /**
     * Table query where column.
     * 
     * @var array<int,mixed> $whereCondition 
     */
    private array $whereCondition = [];

    /**
     * Table query and query column.
     * 
     * @var array<int,mixed> $andConditions 
     */
    private array $andConditions = [];

    /**
     * Table query update set values.
     * 
     * @var array<int,mixed> $querySetValues 
     */
    private array $querySetValues = [];

    /**
     * Printable debug title.
     * 
     * @var string[] $debugTitles 
     */
    private array $debugTitles = [];

    /**
     * Match against modes.
     * 
     * @var array<string,mixed> $matchModes
     */
    private static array $matchModes = [
        'NATURAL_LANGUAGE' => 'IN NATURAL LANGUAGE MODE',
        'BOOLEAN' => 'IN BOOLEAN MODE',
        'NATURAL_LANGUAGE_WITH_QUERY_EXPANSION' => 'IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION',
        'WITH_QUERY_EXPANSION' => 'WITH QUERY EXPANSION'
    ];

    /**
     * Has Cache flag.
     * 
     * @var bool $hasCache 
     */
    private bool $hasCache = false;

    /**
     * Transaction status flag.
     * 
     * @var bool $inTransaction 
     */
    private bool $inTransaction = false;

    /**
     * Ignore duplicates during insertion.
     * 
     * @var bool $insertIgnoreDuplicate 
     */
    private bool $insertIgnoreDuplicate = false;

    /**
     * Caching status flag.
     * 
     * @var bool $caching 
     */
    private bool $caching = true;

    /**
     * Close connection after execution.
     * 
     * @var bool $closeConnection 
     */
    private bool $closeConnection = false;

    /**
     * is cache method is called for current query.
     * 
     * @var bool $queryWithCache 
     */
    private bool $queryWithCache = false;

    /**
     * Enable query debugging.
     * 
     * @var int $debugMode 
     */
    private int $debugMode = 0;

    /**
     * Strict check.
     * 
     * @var bool $strictChecks
     */
    private bool $strictChecks = true;

    /**
     * The debug query information.
     * 
     * @var array<string,mixed> $debugInformation 
     */
    private array $debugInformation = [];

    /**
     * Result return type.
     * 
     * @var string $resultReturnType 
     */
    private string $resultReturnType = 'object';

    /**
     * Cache key.
     * 
     * @var string $cacheKey 
     */
    private string $cacheKey = 'default';

    /**
     * Table alias.
     * 
     * @var string $tableAlias 
     */
    private string $tableAlias = '';

    /**
     * Join table.
     * 
     * @var array $tableJoin 
     */
    private array $tableJoin = [];

    /**
     * Query builder.
     * 
     * @var string $buildQuery 
     */
    private string $buildQuery = '';

    /**
     * Query builder caching driver.
     * 
     * @var string $cacheDriver 
     */
    private static string $cacheDriver = '';

    /**
     * The last inserted Id.
     * 
     * @var mixed $lastInsertId
     */
    private static mixed $lastInsertId = null;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * Initializes the Builder with an optional table name and alias.
     *
     * @param string|null $table The database table name (must be a valid non-empty string).
     * @param string|null $alias Optional table alias (default: null).
     *
     * @throws InvalidArgumentException If the table name is empty or contains invalid characters.
     */
    private function __construct(?string $table = null, ?string $alias = null)
    {
        $table = ($table === null) ? null : trim($table);
        
        if($table !== null || $alias !== null){
            $this->assertTable($table, $alias);
        }

        self::$conn ??= LazyObject::newObject(fn() => Connection::getInstance());
        self::$cacheDriver = env('database.caching.driver', 'filesystem');
        $this->tableName = $table ?? '';
        $this->tableAlias = $alias ? "AS {$alias}" : '';
    }

    /**
     * Check if database connected.
     * 
     * @return bool Return true if database connected, false otherwise.
     */
    public static function isConnected(): bool 
    {
        return (self::$conn->database() instanceof DatabaseInterface) && self::$conn->database()->isConnected();
    }

    /**
     * Checks if the given lock is free.
     *
     * @param string|int $identifier Lock identifier (must be an integer for PostgreSQL).
     * 
     * @return bool Return true if the lock is free, false if it is currently held.
     * @throws DatabaseException If an invalid action is provided or an invalid PostgreSQL lock name is used.
     */
    public static function isLocked(string|int $identifier): bool 
    {
        return self::administration($identifier, 'isLocked');
    }

    /**
     * Prevent outside cloning and reset query properties before cloning.
     * 
     * @ignore
     */
    private function __clone() 
    {
        $this->reset();
    }

    /**
     * Prevent outside deserialization.
     * 
     * @ignore
     */
    public function __wakeup() {}

    /**
     * Get or initialize the shared singleton instance of the Builder class.
     *
     * This method also allows setting global configurations for query execution, 
     * ensuring consistency across multiple queries.
     *
     * @param string|null $table Optional table name (non-empty string).
     * @param string|null $alias Optional table alias (default: null).
     * 
     * @return Builder Returns the singleton instance of the Builder class.
     * @throws DatabaseException If the database connection fails.
     *
     * @example - Example: 
     * ```php
     * $instance = Builder::getInstance()
     *     ->caching(true)
     *     ->returns('array')
     *     ->strict(true);
     * ```
     * Now use the instance with inherited settings:
     * ```php
     * $result = $instance->table('users')
     *     ->where('id', '=', 100)
     *     ->select(['name']);
     * ```
     */
    public static function getInstance(?string $table = null, ?string $alias = null): static 
    {
        return self::$instance ??= new self($table, $alias);
    }

    /**
     * Retrieve last inserted id from database after insert method is called.
     * 
     * @return mixed Return last inserted id from database.
     */
    public function getLastInsertedId(): mixed 
    {
        return self::$lastInsertId;
    }

    /**
     * Get database connection driver instance.
     * 
     * @return DatabaseInterface Return database driver instance.
     * @throws DatabaseException Throws if database connection failed.
     */
    public static function database(): DatabaseInterface
    {
        if(self::isConnected()){
            return self::$conn->database();
        }

        throw new DatabaseException('Error: Database connection failed.');
    }

    /**
     * Creates an instance of the builder class and sets the target database table.
     *
     * @param string $table The name of the database table (must be a non-empty string).
     * @param string|null $alias Optional alias for the table (default: `null`).
     * 
     * @return Builder Returns an instance of the builder class.
     * @throws InvalidArgumentException If the provided table name is empty.
     * 
     * @example - Performing a table join and executing queries:
     * ```php
     * $tbl = Builder::table('users', 'u')
     *     ->innerJoin('roles', 'r')
     *      ->on('u.user_id', '=', 'r.role_user_id')
     *     ->where('u.user_id', '=', 1);
     *
     * // Updating records
     * $result = $tbl->update(['r.role_id' => 1]);
     * 
     * // Selecting records
     * $result = $tbl->select(['r.role_id', 'u.name']);
     * ```
     */
    public static function table(string $table, ?string $alias = null): Builder
    {
        if(!$table){
            throw new InvalidArgumentException(
                'Invalid table argument, $table argument expected non-empty string.'
            );
        }

        return self::initializer($table, $alias);
    }

    /**
     * Executes an SQL query with an optional placeholders.
     * 
     * This method allows direct execution of raw SQL queries. If an array of values 
     * is passed to the `execute` method, prepared statements are used for security.
     * Otherwise, ensure that manually embedded values in the query are properly escaped.
     * 
     * @param string $query The SQL query string (must be non-empty).
     * 
     * @return self Returns an instance of the builder class.
     * @throws InvalidArgumentException If the provided query string is empty.
     * 
     * @see execute()
     * 
     * @example - Executing a raw query:
     * ```php
     * $stmt = Builder::query("SELECT * FROM users WHERE id = :user_id");
     * $result = $stmt->execute(['user_id' => 1]);
     * ```
     * > **Note:** To cache result, you must call `cache()` before the `execute()` method.
     */
    public static function query(string $query): self 
    {
        if (!$query) {
            throw new InvalidArgumentException(
                'Invalid: The parameter $query requires a valid and non-empty SQL query string.'
            );
        }

        $extend = self::initializer();
        $extend->buildQuery = $query;

        return $extend;
    }

    /**
     * Execute an SQL query string and return the number of affected rows.
     * 
     * @param string $query Query string to execute.
     * 
     * @return int Return number affected rows or `0` if failed.
     * 
     * @throws InvalidArgumentException Thrown if query string is empty.
     * @throws DatabaseException Throws if error occurs.
     */
    public static function exec(string $query): int 
    {
        if ($query === '') {
            throw new InvalidArgumentException(
                'Invalid: The parameter $query requires a valid and non-empty SQL query string.'
            );
        }

        try {
            return self::getInstance()
                ->database()
                ->exec($query);
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Creates a new raw SQL expression.
     *
     * This method is used to pass raw SQL expressions that should not be escaped 
     * or quoted by the query builder. It is useful for performing operations 
     * like `COUNT(*)`, `NOW()`, or `scores + 1` directly in queries.
     *
     * @param string $expression The raw SQL expression.
     * 
     * @return RawExpression Return RawExpression instance representing the raw SQL string.
     * @throws InvalidArgumentException If an empty string is passed.
     * 
     * @example - Using RawExpression in an INSERT Query
     * ```php
     * Builder::table('logs')->insert([
     *      'message' => 'User login',
     *      'created_at' => Builder::raw('NOW()'),
     *      'updated_at' => Luminova\Database\RawExpression::now()
     * ]);
     * ```
     */
    public static function raw(string $expression): RawExpression 
    {
        return new RawExpression($expression);
    }

    /**
     * Specifies a table join operation in the query.
     *
     * This method defines how another table should be joined to the current query.
     *
     * @param string $table The name of the table to join.
     * @param string $type The type of join to perform (default: `"INNER"`).
     * @param string|null $alias Optional alias for the joined table (default: `null`).
     * 
     * @return self Returns the instance of builder class.
     * 
     * @throws InvalidArgumentException If either `$table` or `$type` is an empty string.
     *
     * **Join Types**
     *
     * - `INNER` - Returns only rows with matching values in both tables.
     * - `LEFT`  - Returns all rows from the left table and matching rows from the right table, filling in `NULL` for non-matching rows.
     * - `RIGHT` - Returns all rows from the right table and matching rows from the left table, filling in `NULL` for non-matching rows.
     * - `CROSS` - Returns the Cartesian product of the two tables.
     * - `FULL`  - Returns rows with matches in either table, filling in `NULL` for non-matching rows.
     * - `FULL OUTER` - Returns all rows from both tables, with `NULL` in places where there is no match.
     *
     * @example - Basic join usage:
     * 
     * ```php
     * Builder::table('product', 'p')->join('users', 'LEFT', 'u');
     * ```
     * @example - Joining without an alias:
     * 
     * ```php
     * Builder::table('users')->join('orders', 'INNER');
     * ```
     */
    public function join(string $table, string $type = 'INNER', ?string $alias = null): self
    {
        $table = trim($table);
        $this->assertTable($table, $alias);
        $this->tableJoin[$table . ($alias ?? '')] = [
            'type' => strtoupper($type),
            'table' => $table,
            'alias' => $alias ? "AS {$alias}" : ''
        ];
        
        return $this;
    }

    /**
     * Adds a join conditions to the table query.
     *
     * This method defines a condition for joining tables, allowing comparisons 
     * between columns or values.
     *
     * @param string $condition The column name or condition to join on.
     * @param string $comparison The comparison operator (e.g, `=`, `<>`, `>`, `<`).
     * @param mixed  $value The value to compare, placeholder or another table column.
     *                      - String literals must be wrapped in quotes.
     *                      - Unquoted values are treated as column names.
     *                      - Placeholder, a named colon prefixed placeholder string to be referenced to `bind(...)`.
     *
     * @return self Returns the instance of builder class.
     *
     * @example - Using `on()` for table joins:
     * 
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('roles', 'r')
     *          ->on('u.user_id', '=', 'r.role_user_id') // Column comparison
     *          ->on('u.user_group', '=', 1)             // Value comparison
     *          ->on('u.user_name', '=', '"peter"');     // String literal (quoted)
     *          ->on('r.role_name', '=', ':role_name')->bind(':role_name', 'foo');     // Colon prefixed placeholder
     *     ->where('u.user_id', '=', 1);
     * ```
     * 
     * Multiple table join:
     * 
     * ```php
     * Builder::table('users', 'u')
     *     ->innerJoin('roles', 'r')
     *          ->on('u.user_id', '=', 'r.role_user_id')
     *     ->leftJoin('orders', 'o')
     *          ->on('u.user_id', '=', 'o.order_user_id')
     *     ->where('u.user_id', '=', 1);
     * ```
     * 
     * > **Note:** When using multiple joins in one query, always call `on()` immediately after each `join()`.
     *
     * @see join()
     * @see leftJoin()
     * @see rightJoin()
     * @see innerJoin()
     * @see crossJoin()
     * @see fullJoin()
     * @see bind()
     */
    public function on(string $condition, string $comparison, mixed $value): self
    {
        $value = ($value instanceof RawExpression) ? $value->getExpression() : $value;
        $this->joinConditions[array_key_last($this->tableJoin)][] = "{$condition} {$comparison} {$value}";

        return $this;
    }

    /**
     * Sets table join condition as `INNER JOIN`.
     * 
     * @param string $table The table name.
     * @param string|null $alias Optional table join alias (default: NULL).
     * 
     * @return self Returns the instance of the class.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     */
    public function innerJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, 'INNER', $alias);
    }

    /**
     * Sets table join condition as `LEFT JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * 
     * @return self Returns the instance of the class.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     */
    public function leftJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, 'LEFT', $alias);
    }

    /**
     * Sets table join condition as `RIGHT JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * 
     * @return self Returns the instance of the class.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     */
    public function rightJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, 'RIGHT', $alias);
    }

    /**
     * Sets table join condition as `CROSS JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * 
     * @return self Returns the instance of the class.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     */
    public function crossJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, 'CROSS', $alias);
    }

    /**
     * Sets table join condition as `FULL JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * 
     * @return self Returns the instance of the class.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     */
    public function fullJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, 'FULL', $alias);
    }

    /**
     * Sets table join condition as `FULL OUTER JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * 
     * @return self Returns the instance of the class.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     */
    public function fullOuterJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, 'FULL OUTER', $alias);
    }

    /**
     * Sets the query limit for SELECT statements.
     *
     * This method adds a `LIMIT` clause to the query, restricting the number of 
     * rows returned and optionally specifying an offset.
     *
     * @param int $limit  The maximum number of results to return. Must be greater than 0.
     * @param int $offset The starting offset for the results (default: `0`).
     *
     * @return self Returns the instance of the builder class.
     *
     * @example - Limiting number of results:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->limit(10, 5)
     *      ->select();
     * ```
     * Generates: `LIMIT 5,10`
     */
    public function limit(int $limit, int $offset = 0): self
    {
        if($limit > 0){
            $offset = max(0, $offset);
            $this->selectLimit = " LIMIT {$offset},{$limit}";
        }

        return $this;
    }

    /**
     * Sets a maximum limit for `UPDATE` or `DELETE` operations.
     *
     * This method applies a `LIMIT` clause to restrict the number of 
     * rows affected by `UPDATE` or `DELETE` queries.
     *
     * @param int $limit The maximum number of rows to update or delete.
     *
     * @return self  Returns the instance of the builder class.
     *
     * @example - Limiting number of rows to affect:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('country', '=','NG')
     *      ->max(50)
     *      ->update(['is_local' => 1]);
     * ```
     * This ensures the query affects at most 50 rows.
     */
    public function max(int $limit): self
    {
        $this->maxLimit = max(0, $limit);

        return $this;
    }

    /**
     * Enable or disable strict conditions for query execution.
     *
     * When strict mode is enabled, certain operations (e.g., `delete`, `update`) may require a `WHERE` clause 
     * or logic operator to prevent accidental modifications of all records. This helps enforce safer query execution.
     *
     * @param bool $strict Whether to enable strict mode (default: `true`).
     *
     * @return self Returns the instance of the builder class.
     *
     * @example - Enabling strict mode:
     * 
     * ```php
     * Builder::table('users')->strict()->delete(); 
     * ```
     * If no `WHERE` condition is set, an exception will be thrown.
     *
     * @example - Disabling strict mode:
     * 
     * ```php
     * Builder::table('users')->strict(false)->delete();
     * ```
     * The query will execute even if no `WHERE` condition is present.
     */
    public function strict(bool $strict = true): self
    {
       $this->strictChecks = $strict;

       return $this;
    }

    /**
     * Applies ascending or descending sorting order specified column for query results.
     *
     * This method applies an `ORDER BY` clause to the query, allowing 
     * results to be sorted in ascending (`ASC`) or descending (`DESC`) order.
     *
     * @param string $column The name of the column to sort by.
     * @param string $order The sorting direction (default: `ASC`) 
     *              (e.g, `ASC` for ascending, `DESC` for descending).
     * 
     * 
     * @return self Returns the instance of the class.
     * @see ascending() - Newest items first.
     * @see descending() - Oldest items first.
     *
     * @example - Ordering results:
     * 
     * ```php
     * Builder::table('users')
     *      ->order('created_at', 'DESC');
     * ```
     * Generates: `ORDER BY created_at DESC`
     */
    public function order(string $column, string $order = 'ASC'): self 
    {
        $this->options['ordering'][] = "{$column} {$order}";

        return $this;
    }

    /**
     * Applies a descending order to the specified column in the result set.
     * 
     * When to use descending order (i.e., newest items first).
     * 
     * @param string $column The name of the column to sort by in descending order.
     * 
     * @return self Returns the instance of the class.
     * @see order()
     * @see ascending())
     */
    public function descending(string $column): self 
    {
        return $this->order($column, 'DESC');
    }

    /**
     * Applies an ascending order to the specified column in the result set.
     * 
     * When to use descending order (i.e., oldest items first).
     * 
     * @param string $column The name of the column to sort by in ascending order.
     * 
     * @return self Returns the instance of the class.
     * @see order()
     * @see descending()
     */
    public function ascending(string $column): self 
    {
        return $this->order($column, 'ASC');
    }

    /**
     * Set the result ordering for method match against.
     * 
     * @param array $columns The column names to index match order.
     * @param string|int|float $value The value to match against in order.
     * @param string $mode The comparison match mode operator.
     *      Optionally you can choose any of these modes or pass your own mode.
     *          - NATURAL_LANGUAGE
     *          - BOOLEAN
     *          - NATURAL_LANGUAGE_WITH_QUERY_EXPANSION
     *          - WITH_QUERY_EXPANSION
     * @param string $order The order algorithm to use (either "ASC" or "DESC").
     * 
     * @return self Returns the instance of the class.
     */
    public function orderAgainst(
        array $columns, 
        string|int|float $value, 
        string $mode = 'NATURAL_LANGUAGE', 
        string $order = 'ASC'
    ): self 
    {
        $this->options['match'][] = [
            'mode' => self::$matchModes[$mode] ?? $mode,
            'column' => implode(", ", $columns),
            'value' => $value,
            'order' => $order,
        ];
        return $this;
    }

    /**
     * Sets a `GROUP BY` clause for the query.
     *
     * This method adds a column to the `GROUP BY` clause, allowing 
     * aggregation of results based on the specified column.
     *
     * @param string $column The name of the column to group by.
     * 
     * @return self Returns the instance of the class.
     *
     * @example - Grouping results:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('status', '=', 'active')
     *      ->group('country')
     *      ->select(['name'])
     * ```
     * Generates: `GROUP BY country`
     */
    public function group(string $group): self 
    {
        $this->options['grouping'][] = $group;
        
        return $this;
    }

    /**
     * Add a filter `HAVING` expression to the query.
     *
     * This method allows filtering grouped results using aggregate functions.
     * It appends a HAVING condition to the query, enabling advanced filtering
     * after the `GROUP BY` clause.
     *
     * @param RawExpression|string $expression The column or expression to evaluate 
     *          (e.g, `Builder::raw('COUNT(columnName)'),`, `RawExpression::count('columnName')`).
     * @param string $comparison The comparison operator (e.g., '=', '>', '<=', etc.).
     * @param mixed $value The value to compare against.
     * @param string $operator Logical operator to combine with other HAVING clauses (default: 'AND').
     * 
     * @return self Returns the instance of the class.
     * 
     * @example - Filtering Using Having clause:
     * 
     * ```php
     * Builder::table('orders')
     *      ->group('category')
     *      ->having('totalSales', '>', 1000)
     *      ->select(['category', 'SUM(sales) as total_sales'])
     * ```
     * Generates: `HAVING totalSales > 1000`
     * 
     * Parsing Raw Expression:
     * 
     * ```php
     * Builder::table('orders')
     *      ->group('category')
     *      ->having(RawExpression::sum('amount'), '>=', 1000)
     *      ->having(RawExpression::count('order_id'), '>', 10, 'OR')
     *      ->select(['category']);
     * ```
     * Generates: `HAVING SUM(amount) >= 1000 OR COUNT(order_id) > 10`
     */
    public function having(
        RawExpression|string $expression, 
        string $comparison, 
        mixed $value,
        string $operator = 'AND'
    ): self
    {
        $this->options['filters'][] = [
            'expression' => $expression,
            'comparison' => $comparison,
            'operator' => $operator,
            'value' => $value,
        ];
        
        return $this;
    }

    /**
     * Adds a `WHERE` condition to the query.
     *
     * This method sets a conditional clause where the specified column 
     * must satisfy the given comparison operator and value.
     *
     * @param string $column The name of the column to filter by.
     * @param string $comparison The comparison operator (e.g., `=`, `>=`, `<>`, `LIKE`, `REGEXP`).
     * @param mixed $value The value to compare against.
     * 
     * @return self Return instance of builder class.
     * 
     * @see find()
     * @see select()
     * @see total()
     * @see sum(),
     * @see average()
     * @see delete()
     * @see update()
     * @see copy()
     * @see fetch()
     * @see stmt()
     *
     * @example - Using the `WHERE` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('status', '=', 'active');
     * ```
     * Generates: `WHERE status = 'active'`
     * 
     * ```php
     * Builder::table('users')
     *      ->where('status', '', ['active', 'disabled']);
     * ```
     * Generates: `WHERE status IN ('active', 'disabled')`
     * 
     * ```php
     * Builder::table('users')
     *      ->where('status', 'NOT', ['active', 'disabled']);
     * ```
     * Generates: `WHERE status NOT IN ('active', 'disabled')`
     * 
     *  ```php
     * Builder::table('users')
     *      ->where('status', 'NOT EXISTS', Builder::raw('(SELECT 1 FROM views WHERE id = 1)'));
     * ```
     * Generates: `WHERE status NOT EXISTS (SELECT 1 FROM views WHERE id = 1)`
     */
    public function where(string $column, string $comparison, mixed $value): self
    {
        $this->whereCondition = [
            'type' => 'WHERE', 
            'value' => null,
            'column' => $column,
            'placeholder' => null
        ];

        if($value instanceof RawExpression){
            $this->whereCondition['query'] = " WHERE {$column} {$comparison} {$value->getExpression()}";
            return $this;
        }

        if(is_array($value)){
            $this->whereCondition = [];
            return $this->inArray($column, $comparison, $value, 'AND');
        }
        
        $placeholder = self::trimPlaceholder($column);
 
        $this->whereCondition['query'] = " WHERE {$column} {$comparison} {$placeholder}";
        $this->whereCondition['value'] = $value;
        $this->whereCondition['placeholder'] = $placeholder;

        return $this;
    }

    /**
     * Add an advanced conditional clause to the query builder.
     * 
     * Supports a variety of query functions for building complex WHERE clauses.
     * 
     * Supported Functions:
     * - **REGULAR**: Default comparison as provided (e.g., `WHERE column = value`).
     * - **CONJOIN**: Combine multiple conditions (e.g., `WHERE (a = 1 OR b = 1)`).
     * - **NESTED**: Grouped conditions for deeply nested logic (e.g., `WHERE ((a = 1 OR b = 1) AND (c = 2))`).
     * - **AGAINST**: Full-text search using `MATCH ... AGAINST`.
     * - **INARRAY**: Use `IN (...)` for filtering against a list of values.
     * 
     * @param string $clause The logical connector (`AND`, `OR`, only).
     * @param string $column The column name to compare.
     * @param string $comparison The comparison operator (`=`, `<>`, `>=`, `LIKE`, etc.).
     * @param mixed $value The value to compare against.
     * @param string $function The clause function type (default: `REGULAR`).
     * 
     * @return self Returns instance for builder class.
     * @throws InvalidArgumentException If `INARRAY` is used with an empty array.
     * 
     * @internal Used internally by the builder to support complex WHERE logic.
     * 
     * @example Example usage:
     * 
     * ```php
     * Builder::table('users')
     *     ->where('id', '=', 100)
     *     ->clause('OR', 'id', '=', 101)
     *     ->clause('AND', 'name', '=', 'Peter', 'REGULAR')
     *     ->clause('AND', 'roles', '=', ['admin', 'editor'], 'INARRAY');
     * ```
     */
    public function clause(
        string $clause, 
        string $column, 
        string $comparison, 
        mixed $value, 
        string $function = 'REGULAR'
    ): self
    {
        if ($function === 'INARRAY' && $value === []) {
            throw new InvalidArgumentException('Invalid argument $value; expected a non-empty array when using INARRAY.');
        }

        $this->andConditions[] = [
            'type' => $clause,
            'function' => $function,
            'column' => $column,
            'value' => $value,
            'comparison' => $comparison
        ];

        return $this;
    }

    /**
     * Adds an `AND` condition to the query.
     *
     * This method appends an additional condition using the `AND` operator, 
     * requiring multiple conditions to be met.
     *
     * @param string $column The name of the column to filter by.
     * @param string $comparison The comparison operator (e.g., `=`, `>=`, `<>`, `LIKE`, `REGEXP`, `IN`, `NOT`).
     * @param mixed $value The value to compare against.
     * 
     * @return self Return instance of builder class.
     *
     * @example - Using the `AND` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('status', '=', 'active')
     *      ->and('role', '=', 'admin')
     *      ->select();
     * ```
     * Generates: `WHERE status = 'active' AND role = 'admin'`
     * 
     * Using REGEXP for partial match:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('status', '=', 'active')
     *      ->and('department', 'REGEXP', 'HR|Finance|Marketing')
     *      ->select();
     * ```
     * Generates: `WHERE status = 'active' AND department REGEXP 'HR|Finance|Marketing'`
     */
    public function and(string $column, string $comparison, mixed $value): self
    {
        if(is_array($value)){
            return $this->inArray($column, $comparison, $value, 'AND');
        }

        return $this->clause('AND', $column, $comparison, $value);
    }

    /**
     * Add a condition to the query using the `OR` operator.
     * 
     * This method appends a conditional clause where the specified column 
     * must satisfy the given comparison operator and value.
     * 
     * @param string $column The name of the column to apply the condition.
     * @param string $comparison The comparison operator to use (e.g., `=`, `>=`, `<>`, `LIKE`, `IN`, `NOT`).
     * @param mixed $value The value to compare the column against.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Using the `OR` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->or('status', '=', 'active')
     *      ->or('role', '!=', 'admin');
     * ```
     * Generates: `WHERE status = 'active' OR role != 'admin'`
     */
    public function or(string $column, string $comparison, mixed $value): self
    {
        if(is_array($value)){
            return $this->inArray($column, $comparison, $value, 'OR');
        }

        return $this->clause('OR', $column, $comparison, $value);
    }

    /**
     * Add a `LIKE` clause to the query for pattern matching.
     *
     * @param string $column The column name to compare.
     * @param string $expression The pattern to match using SQL `LIKE` (e.g. `%value%`).
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Using the `LIKE` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->like('name', '%pet%')
     *      ->like('username', '%pet%', 'OR');
     * ```
     * Generates: `WHERE name LIKE '%pet%' OR username LIKE '%pet%'`
     */
    public function like(string $column, string $expression, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, 'LIKE', $expression);
    }

    /**
     * Add a `NOT LIKE` clause to the query to exclude pattern matches.
     *
     * @param string $column The column name to compare.
     * @param string $expression The pattern to exclude using SQL `NOT LIKE` (e.g. `%value%`).
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Using the `NOT LIKE` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->notLike('name', '%pet%');
     * ```
     * Generates: `WHERE name NOT LIKE '%pet%'`
     */
    public function notLike(string $column, string $expression, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, 'NOT LIKE', $expression);
    }

    /**
     * Set query match columns and mode.
     * 
     * @param array $columns The column names to match against.
     * @param string $mode The comparison match mode operator.
     *      Optionally you can choose any of these modes or pass your own mode.
     *          - NATURAL_LANGUAGE
     *          - BOOLEAN
     *          - NATURAL_LANGUAGE_WITH_QUERY_EXPANSION
     *          - WITH_QUERY_EXPANSION
     * 
     * @param mixed $value The value to match against.
     * 
     * @return self Return instance of builder class.
     */
    public function against(array $columns, string $mode, mixed $value): self
    {
        return $this->clause(
            'AND', 
            implode(", ", $columns),
            self::$matchModes[$mode] ?? $mode, 
            $value,
            'AGAINST',
        );
    }

    /**
     * Adds a condition to filter results where the given column is NOT NULL.
     *
     * This method appends an "AND column IS NOT NULL" condition to the query.
     * It ensures that only records with a non-null value in the specified column are retrieved.
     *
     * @param string $column The column name to check for non-null values.
     * @param string $clause The column clause condition (e.g, `AND` or `OR`).
     * 
     * @return self Return instance of builder class.
     * 
     * @see nullable()
     * 
     * @example - Example usage:
     * ```php
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->isNotNull('address')
     *      ->select();
     * ```
     */
    public function isNotNull(string $column, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, '', self::raw('IS NOT NULL'));
    }

    /**
     * Adds a condition to filter results where the given column is NULL.
     *
     * This method appends an "AND column IS NULL" condition to the query.
     * It ensures that only records with a null value in the specified column are retrieved.
     * 
     * @param string $column The column name to check for null values.
     * @param string $clause The column clause condition (e.g, `AND` or `OR`).
     * 
     * @return self Return instance of builder class.
     * 
     * @see nullable()
     * 
     * @example - Example usage:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->isNull('address')
     *      ->select();
     * ```
     */
    public function isNull(string $column, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, '', self::raw('IS NULL'));
    }

    /**
     * Adds a condition to filter results where the given column is `NULL` or `NOT NULL`.
     *
     * This method appends a null match based on "$clause" condition to the query.
     * It ensures that only records with a null or non-null value in the specified column are retrieved.
     *
     * @param string $column The column name to check for non-null values.
     * @param bool $isNull Whether the the column should be null or not (default: true).
     * @param string $clause The column clause condition (e.g, `AND` or `OR`).
     * 
     * @return self Return instance of builder class.
     * 
     * @see isNull()
     * @see isNotNull()
     * 
     * @example - Example usage:
     * ```php
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->nullable('address')
     *      ->select();
     * ```
     */
    public function nullable(string $column, bool $isNull = true, string $clause = 'AND'): self
    {
        return $this->clause(
            $clause, 
            $column, 
            '', 
            self::raw($isNull ? 'IS NULL' : 'IS NOT NULL')
        );
    }

    /**
     * Set the columns and values to be updated in the query.
     * 
     * This method should be invoked before the `update()` method to specify which columns to update.
     *
     * @param string $column The name of the column to update.
     * @param mixed $value The value to set for the column.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Setting update columns and values:
     * 
     * ```php
     * Builder::table('users')
     *      ->set('status', 'active')
     *      ->set('updated_at', Builder::datetime())
     *      ->update();
     * ```
     * Generates: `UPDATE table SET status = 'active', updated_at = '2024-04-03 14:30:45'`
     */
    public function set(string $column, mixed $value): self
    {
        $this->querySetValues[$column] = $value;

        return $this;
    }

    /**
     * Conjoin multiple conditions using either `AND` or `OR`.
     *
     * This method creates a logical condition group where conditions are combined using the specified operator.
     *
     * @param array<int,column|array<string,array<string,mixed>>> $conditions The conditions to group.
     *                          Or `column` method for simplified builder.
     * @param string $operator The join logical operator (`AND` or `OR`) within each group (default: `AND`).
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException Throws if invalid group operator is specified.
     *
     * @example - Group conditions:
     * 
     * ```php
     * Builder::table('fooTable')->conjoin([
     *     ['column1' => ['comparison' => '=', 'value' => 1]],
     *     ['column2' => ['comparison' => '=', 'value' => 2]]
     * ], 'OR');
     * ```
     * Using Column: 
     * 
     * ```php
     * Builder::table('fooTable')->conjoin([
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ], 'OR');
     * ```
     * Generates: `WHERE (column1 = 1 OR column2 = 2)`
     */
    public function conjoin(array $conditions, string $operator = 'AND', string $clause = 'AND'): self
    {
        $operator = strtoupper($operator);
        if(!in_array($operator, ['AND', 'OR'])){
            throw new InvalidArgumentException(sprintf(
                "Invalid logical operator '%s'. Allowed values are 'AND' or 'OR'.", 
                $operator
            ));
        }

        $this->andConditions[] = [
            'type' => $clause,
            'function' => 'CONJOIN',
            'operator' => $operator,
            'conditions' => $conditions
        ];

        return $this;
    }

    /**
     * Creates a nested conjoin condition group by combining two condition sets.
     *
     * This method groups two sets of conditions and binds them with the specified logical operator.
     *
     * @param array<int,column|array<string,array<string,mixed>>> $conditions1 The first condition group.
     *                              Or `column` method for simplified builder.
     * @param array<int,array<string,array<string,mixed>>> $conditions2 The second condition group.
     * @param string $operator The join logical operator (`AND` or `OR`) within each group (default: `AND`).
     * @param string $nestedOperator The nested logical operator (`AND` or `OR`) to bind the groups (default: `AND`).
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException Throws if invalid group operator is specified.
     *
     * @example - Generating a nested  conditions:
     * 
     * ```php
     * Builder::table('fooTable')
     * ->where('fooUser', '=', 100)
     * ->nested([
     *      ['foo' => ['comparison' => '=', 'value' => 1]],
     *      ['bar' => ['comparison' => '=', 'value' => 2]]
     * ],
     * [
     *      ['baz' => ['comparison' => '=', 'value' => 3]],
     *      ['bra' => ['comparison' => '=', 'value' => 4]]
     * ], 
     * 'OR', 
     * 'AND');
     * ```
     * Generates: `WHERE fooUser = 100 AND ((foo = 1 OR bar = 2) AND (baz = 3 OR bra = 4))`
     * 
     * Using Column: 
     * 
     * ```php
     * $tbl = Builder::table('fooTable');
     * $tbl->nested([
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ],[
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ], 
     * 'OR', 
     * 'AND');
     * ```
     */
    public function nested(
        array $conditions1, 
        array $conditions2, 
        string $operator = 'AND', 
        string $nestedOperator = 'AND',
        string $clause = 'AND'
    ): self
    {
        $nestedOperator = strtoupper($nestedOperator);
        if(!in_array($nestedOperator, ['AND', 'OR'])){
            throw new InvalidArgumentException(sprintf(
                "Invalid nested logical operator '%s'. Allowed values are 'AND' or 'OR'.", 
                $nestedOperator
            ));
        }

        $this->andConditions[] = [
            'type' => $clause,
            'function' => 'NESTED',
            'bind' => $nestedOperator,
            'operator' => $operator,
            'X' => $conditions1,
            'Y' => $conditions2
        ];

        return $this;
    }

    /**
     * Define a column condition for use in nested and conjoin queries.
     *
     * This method simplifies the process of specifying a column condition with a comparison operator and a value.
     * It is particularly useful when used within methods like `conjoin()`, `nested()`, 'andConjoin()', `orConjoin()`
     * `orNested()` or `andNested()`.
     *
     * @param string $name The name of the column.
     * @param string $comparison The comparison operator (e.g., `=`, `!=`, `<`, `>`, `LIKE`).
     * @param mixed $value The value to compare against.
     *
     * @return array<string,array> Returns builder array column structure.
     *
     * @example - Using `column` with `conjoin()`:
     * 
     * ```php
     * $tbl = Builder::table('users');
     * $tbl->conjoin([
     *     Builder::column('age', '>=', 18),
     *     Builder::column('status', '=', 'active')
     * ], 'AND');
     * ```
     * Generates: `WHERE (age >= 18 AND status = 'active')`
     *
     * @example - Using `column` directly in a query:
     * 
     * ```php
     * $tbl = Builder::table('products');
     * $tbl->nested(
     *     [Builder::column('price', '>', 100), Builder::column('rate', '>=', 10)],  
     *     [Builder::column('price', '>', 100), Builder::column('price', '>', 100)] 
     * );
     * ```
     * Generates: `SELECT * FROM products WHERE price > 100`
     */
    public static function column(string $name, string $comparison, mixed $value): array
    {
       return [$name => ['comparison' => $comparison, 'value' => $value]];
    }

    /**
     * Binds a join named placeholder parameter to a value.
     *
     * Use this method to manually assign a value to a named placeholder—typically 
     * used within a join condition where dynamic values are required.
     *
     * @param string $placeholder The named placeholder. Must start with a colon `:` (e.g. `:id`).
     * @param mixed $value The value to bind to the placeholder. Arrays are JSON encoded.
     * 
     * @throws InvalidArgumentException If the placeholder does not start with a colon `:`.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Bind Placeholder Example:
     * 
     * ```php
     * $result = Builder::table('users', 'u')
     *     ->innerJoin('orders', 'o')
     *         ->on('o.order_user_id', '=', 'u.user_id')
     *         ->on('o.order_id', '=', ':order_number')
     *         ->bind(':order_number', 13445)
     *     ->where('u.user_id', '=', 100)
     *     ->select();
     * ```
     */
    public function bind(string $placeholder, mixed $value): self 
    {
        if (!str_starts_with($placeholder, ':')) {
            throw new InvalidArgumentException(sprintf(
                'Invalid param placeholder: %s. Placeholder must start with colon prefix ":" (e.g., "%s")',
                $placeholder,
                self::trimPlaceholder($placeholder)
            ));
        }

        $this->options['binds'][$placeholder] = self::escape($value, false, true);

        return $this;
    }

    /**
     * Groups multiple conditions using the `OR` operator.
     *
     * This method creates a logical condition group where at least one condition must be met.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The conditions to be grouped with `OR`.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @see conjoin()
     *
     * @example - Example: 
     * 
     * ```php
     * Builder::table('fooTable')->orConjoin([
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ]);
     * ```
     * Generates: `WHERE (column1 = 1 OR column2 = 2)`
     */
    public function orConjoin(array $conditions, string $clause = 'AND'): self
    {
        return $this->conjoin($conditions, 'OR', $clause);
    }

    /**
     * Groups multiple conditions using the `AND` operator.
     *
     * This method creates a logical condition group where all conditions must be met.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The conditions to be grouped with `AND`.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @see conjoin()
     *
     * @example - Example: 
     * 
     * ```php
     * Builder::table('fooTable')->andConjoin([
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ]);
     * ```
     * Generates: `WHERE (column1 = 1 AND column2 = 2)`
     */
    public function andConjoin(array $conditions, string $clause = 'AND'): self
    {
        return $this->conjoin($conditions, 'AND', $clause);
    }

    /**
     * Binds two condition groups using the `OR` operator.
     *
     * This method creates two logical condition groups and combines them using `OR`.
     *
     * @param array<int,array<string,array<string,mixed>>> $columns1 The first condition group.
     * @param string $joinOperator The logical operator to bind both group (e.g, `AND`, `OR`).
     *              - `AND` - Groups are combined with AND (e.g., `WHERE ((a OR b) AND (c OR d))`).
     *              - `OR`  - Groups are combined with OR (e.g., `WHERE ((a OR b) OR (c OR d))`).
     * @param array<int,array<string,array<string,mixed>>> $columns2 The second condition group.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @see nested()
     *
     * @example - Generating a query with nested `OR` conditions:
     * 
     * ```php
     * Builder::table('fooTable')
     * ->orNested([
     *      Builder::column('foo', '=', 1),
     *      Builder::column('bar', '=', 2)
     * ],
     * 'AND',
     * [
     *      Builder::column('baz', '=', 3),
     *      Builder::column('bra', '=', 4)
     * ]);
     * ```
     * Generates: `WHERE ((foo = 1 OR bar = 2) AND (baz = 3 OR bra = 4))`
     */
    public function orNested(array $columns1, string $joinOperator, array $columns2, string $clause = 'AND'): self
    {
        return $this->nested($columns1, $columns2, 'OR', $joinOperator, $clause);
    }

    /**
     * Binds two condition groups using the `AND` operator.
     *
     * This method creates two logical condition groups and combines them using `AND`.
     *
     * @param array<int,array<string,array<string,mixed>>> $columns1 The first condition group.
     * @param string $joinOperator The logical operator to bind both group (e.g, `AND`, `OR`).
     *                  - `AND` - Groups are combined with AND (e.g., `WHERE ((a AND b) AND (c AND d))`).
     *                  - `OR`  - Groups are combined with OR (e.g., `WHERE ((a AND b) OR (c AND d))`).
     * @param array<int,array<string,array<string,mixed>>> $columns2 The second condition group.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @see nested()
     *
     * @example - Generating a query with nested `AND` conditions:
     * ```php
     * Builder::table('fooTable')
     * ->andNested([
     *      Builder::column('foo', '=', 1),
     *      Builder::column('bar', '=', 2)
     * ],
     * 'OR',
     * [
     *      Builder::column('baz', '=', 3),
     *      Builder::column('bra', '=', 4)
     * ]);
     * ```
     * Generates: `WHERE ((foo = 1 AND bar = 2) OR (baz = 3 AND bra = 4))`
     */
    public function andNested(array $columns1, string $joinOperator, array $columns2, string $clause = 'AND'): self
    {
        return $this->nested($columns1, $columns2, 'AND', $joinOperator, $clause);
    }

    /**
     * Adds find `IN` condition to search using `IN ()` expression.
     * 
     * This method allows you to set an array-value expressions to search for a given column name.
     * 
     * @param string $column The column name.
     * @param array<int,string|int|float> $list The expression values.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If values is not provided.
     * @throws JsonException If an error occurs while encoding values.
     */
    public function in(string $column, array $list, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, 'IN', $list, 'INARRAY');
    }

    /**
     * Adds a IN condition to the query with support for `IN`, `NOT IN`, or other wrappers.
     *
     * This is used for matching one or more values against a comma-separated list column,
     * such as checking if a value exists within a set stored in a single field.
     *
     * @param string $column      The column name to search within.
     * @param string $expression  A SQL expression modifier (e.g., `IN` or `NOT`).
     * @param array<int,string|int|float>  $values      Values to check against.
     * @param string $clause      Logical clause to chain with (e.g., `AND`, `OR`).
     *
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If values is not provided.
     * @throws JsonException If an error occurs while encoding values.
     * 
     * @example - Example:
     * ```php
     * Builder::table('languages')
     *     ->inArray('post_tags', 'NOT', ['php', 'sql'])
     *      ->select();
     * ```
     * Will generate a clause like: `NOT IN(...)
     */
    public function inArray(string $column, string $expression, array $values, string $clause = 'AND'): self
    {
        $expression = preg_replace('/\bIN\b/i', '', $expression);
        return $this->clause(
            $clause, $column, 
            "{$expression} IN", $values, 'INARRAY'
        );
    }

    /**
     * Adds find `NOT IN` condition to search using `NOT IN ()` expression.
     *
     * This method creates a condition where the specified column's value is not in the provided list.
     *
     * @param string $column The name of the column to check against the list.
     * @param array<int,string|int|float> $list An array of values to compare against the column.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     *
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If the provided list is empty.
     * 
     * @example - Example
     * ```php
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->notIn('state', ['Enugu', 'Lagos', 'Abuja']);
     * ```
     */
    public function notIn(string $column, array $list, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, 'NOT IN', $list, 'INARRAY');
    }

    /**
     * Add a condition for `FIND_IN_SET` expression for the given column name.
     *
     * @param string $search The search value or column name depending on `$isSearchColumn`.
     * @param string $comparison The comparison operator for matching (e.g., `exists`, `first`, `>= foo`, `<= bar`).
     * @param array<int,mixed>|string $list The comma-separated values or a column name containing the list.
     * @param bool $isSearchColumn Whether the `$search` argument is a column name (default: false).
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Returns the instance of the builder class.
     * @throws InvalidArgumentException Throws if list value is not empty.
     * 
     * Default Operators:
     * 
     * - `exists`, `>` - Check if exists or match any in the list.
     * - `first`, `=` - Check if it's the first in the list.
     * - `last` - Check if it's the first in the list.
     * - `position` - Position in the list (as inset_position).
     * - `contains` - Check if it contains the search term (uses the `$search` as the search value).
     * - `none` - No match in the list.
     * 
     * @example - Usage Examples:
     * 
     * Using the `custom` Operator:
     * ```php
     * Builder::table('fruits')
     *      ->inset('banana', '= 2', ['apple','banana','orange']);
     * ```
     * Using the `exists` Operator with a column:
     * ```php
     * Builder::table('employees')
     *      ->inset('PHP', 'exists', 'column_language_skills');
     * ```
     * 
     * Using the `exists` Operator with a search column:
     * ```php
     * Builder::table('employees')
     *      ->inset('department', 'exists', 'HR,Finance,Marketing', true);
     * ```
     */
    public function inset(
        string $search, 
        string $comparison, 
        array|string $list,
        bool $isSearchColumn = false,
        string $clause = 'AND'
    ): self
    {
        if($list === [] || $list === ''){
            throw new InvalidArgumentException('Invalid argument $list, expected non-empty array or string.');
        }

        $isList = is_array($list);

        $this->andConditions[] = [
            'type' => $clause, 
            'function' => 'INSET',
            'list' => $isList ? implode(',', $list) : $list, 
            'isList' => $isList,
            'search' => $search, 
            'isSearchColumn' => $isSearchColumn,
            'comparison' => $comparison,
        ];

        return $this;
    }

    /**
     * Set result return type to an `object`, `array` or prepared `statement` object.
     * 
     * This method changes the default result return type from and `object` to either  `array` or `statement` object.
     * 
     * @param string $type Return type (e.g, `Builder::RETURN_OBJECT`, `Builder::RETURN_ARRAY` or `Builder::RETURN_STATEMENT`).
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException Throws if an invalid type is provided.
     * 
     * > **Note:** Call method before `fetch`, `find` `select` etc...
     */
    public function returns(string $type): self
    {
        $type = strtolower($type);

        if(
            $type === self::RETURN_OBJECT || 
            $type === self::RETURN_ARRAY || 
            $type === self::RETURN_STATEMENT
        ){
            $this->resultReturnType = $type;
            
            return $this;
        }

        throw new InvalidArgumentException(sprintf('Invalid return type: "%s", expected "array" or "object".', $type));
    }

    /**
     * Enables debugging for query execution.
     *
     * Supports multiple debug modes for tracking or dumping query strings and parameters.
     * In production, debug information is logged using the `debug` level when applicable.
     *
     * @param int $mode Debug mode to activate:
     *        - `Builder::DEBUG_BUILDER`: Collects query strings and parameters internally.
     *        - `Builder::DEBUG_BUILDER_DUMP`: Immediately outputs query strings and parameters.
     *        - `Builder::DEBUG_DRIVER`: Enables driver-level debugging (e.g., PDO, MySQLi).
     *
     * @return self Return instance of builder class.
     */
    public function debug(int $mode = self::DEBUG_BUILDER_DUMP): self
    {
        $this->debugMode = $mode;
        $this->debugTitles = [];

        if($mode === self::DEBUG_BUILDER || $mode === self::DEBUG_BUILDER_DUMP){
            $this->debugInformation = [];
            return $this;
        }

        $this->db?->setDebug(true);
        return $this;
    }

    /**
     * Helper method to get a formatted date/time string for SQL storage.
     *
     * @param string $format The format to return (default: `datetime`).
     *                            Available formats:
     *                              - `time`     → HH:MM:SS (e.g., `14:30:45`)
     *                              - `datetime` → YYYY-MM-DD HH:MM:SS (e.g., `2025-04-03 14:30:45`)
     *                              - `date`     → YYYY-MM-DD (e.g., `2025-04-03`)
     *                              - `unix`     → UNIX timestamp (e.g., `1712256645`)
     * @param DateTimeZone|string|null $timezone Optional timezone string or object (default: null).
     * @param int|null $timestamp Optional UNIX timestamp to format; uses current time if null.
     *
     * @return string Return formatted date/time or UNIX timestamp.
     */
    public static function datetime(
        string $format = 'datetime', 
        DateTimeZone|string|null $timezone = null, 
        ?int $timestamp = null
    ): string
    {
        if ($format === 'unix') {
            return (string) ($timestamp ?? Time::now($timezone)->getTimestamp());
        }

        $dateFormat = match ($format) {
            'time'     => 'H:i:s',
            'date'     => 'Y-m-d',
            default    => 'Y-m-d H:i:s'
        };

        return ($timestamp === null) 
            ? Time::now($timezone)->format($dateFormat)
            : Time::fromTimestamp($timestamp, $timezone)->format($dateFormat);
    }

    /**
     * Escapes a value for safe use in SQL queries.
     *
     * This method handles various types and formats them appropriately:
     * 
     * - `null`, `bool`, and numeric values are cast based on the `$strict` flag.
     * - Arrays and objects are encoded as JSON. Existing valid JSON strings are preserved.
     * - `RawExpression` instances are returned as-is, unescaped.
     * - Strings can optionally be escaped with `addslashes()` and/or wrapped in quotes.
     * 
     * @param mixed $value The value to escape.
     * @param bool $enQuote If true, wraps the value in single quotes unless it's JSON.
     * @param bool $strict Whether to use strict type casting (default: false).
     *       If true:
     *        - `null` returns `null` instead of `'NULL'`
     *        - `bool` returns `true|false` instead of `1|0`
     *        - Empty arrays return `[]` instead of `'[]'`
     *       If false:
     *        - `null` returns `'NULL'` (as string)
     *        - `bool` returns `1|0`
     *        - Empty arrays return `'[]'`
     * @param bool $numericCheck If true, numeric strings are cast to int/float:
     *        - Enables `+0` cast and `JSON_NUMERIC_CHECK` for JSON encoding.
     *        - If false, numeric strings are preserved as-is.
     * @param bool $addSlashes If true, string values are passed through `addslashes()`.
     * 
     * @return string|int|float|bool|null Returns a properly escaped and type-safe value.
     * @throws JsonException If JSON encoding fails for arrays or objects.
     */
    public static function escape(
        mixed $value, 
        bool $enQuote = false, 
        bool $strict = false,
        bool $numericCheck = false,
        bool $addSlashes = false
    ): mixed
    {
        if ($value === '') {
            return '';
        }

        if ($value === null) {
            return $strict ? null : 'NULL';
        }

        if ($value === []) {
            return $strict ? [] : '[]';
        }

        if ($value instanceof RawExpression) {
            return $value->getExpression();
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $strict ? $value : ($value ? 1 : 0);
        }

        if (is_numeric($value)) {
            return $numericCheck ? $value + 0 : (string) $value;
        }

        $isJson = is_array($value) || is_object($value);
        $flags = JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;
        if($numericCheck){
            $flags |= JSON_NUMERIC_CHECK;
        }

        $value = $isJson
            ? json_encode($value, $flags)
            : (($isJson = json_validate($value)) 
                ? $value : 
                ($addSlashes ? addslashes((string) $value) : (string) $value)
            );

        if(!$enQuote || $isJson){
            return $value;
        }

        return "'{$value}'";
    }

    /**
     * Globally enable or disabled all caching for subsequent select operations.
     *
     * @param bool $enable The caching status action.
     * 
     * @return self Return instance of builder class.
     */
    public function caching(bool $enable): self
    {
        $this->caching = $enable;
        return $this;
    }

    /**
     * Sets the auto-close connection status for the current query.
     *
     * This method allows you to control whether the database connection should be automatically closed after executing the query.
     * By default, the connection remains open after query execution.
     *
     * @param bool $close Whether to automatically close the connection after executing the query (default: true).
     *
     * @return self Return instance of builder class.
     */
    public function closeAfter(bool $close = true): self 
    {
        $this->closeConnection = $close;
        return $this;
    }

    /**
     * Retrieves the current cache instance.
     *
     * This method returns the static cache instance used by the class.
     * The cache instance is typically set up earlier in the class's lifecycle.
     *
     * @return BaseCache|null Returns the current cache instance if set, or null if no cache has been initialized.
     */
    public function getCache(): ?BaseCache
    {
        return self::$cache;
    }

    /**
     * Get an array of debug query information.
     * 
     * Returns detailed debug information about the query string, including formats for `MySQL` and `PDO` placeholders, as well as the exact binding mappings for each column.
     * 
     * @return array<string,mixed> Return array containing query information.
     * @see printDebug()
     */
    public function getDebug(): array 
    {
        return $this->debugInformation;
    }

    /**
     * Print the debug query information in the specified format.
     * 
     * @param string $format Optional output format (e.g, `html`, `json` or `NULL`).
     *              The format is only applied when debug mode is `Builder::DEBUG_BUILDER_DUMP`.
     *
     * @return void
     * @see getDebug()
     * @see dump()
     */
    public function printDebug(?string $format = null): void
    {
        $this->dump($format);
    }

    /**
     * Print the debug query information in the specified format.
     *
     * This method also print the debug statement information for the last statement execution.
     * If no format is provided or running is CLI/command mode, defaults to `print_r` without any formatting.
     * 
     * Supported formats: 
     * 
     * - `null` → Print a readable array (default), 
     * - `html` → Format output in html `pre`. 
     * - `json` →  Output as json-pretty print.
     *
     * @param string $format Optional output format (e.g, `html`, `json` or `NULL`).
     *              The format is only applied when debug mode is `Builder::DEBUG_BUILDER_DUMP`
     * 
     * @return void
     * @see getDebug()
     * @see printDebug()
     */
    public function dump(?string $format = null): void
    {
        if($this->debugMode === self::DEBUG_DRIVER || $this->debugMode === 0){
            $this->db->dumpDebug();
            return;
        }

        if($this->debugMode !== self::DEBUG_BUILDER_DUMP){
            return;
        }

        if($format === null || PHP_SAPI === 'cli' || is_command()){
            print_r($this->debugInformation);
            return;
        }

        if(strtolower($format) === 'json') {
            echo json_encode($this->debugInformation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        echo '<pre>';
        print_r($this->debugInformation);
        echo '</pre>';
    }

    /**
     * Deletes the cached data associated with current table or a specific database table.
     * 
     * @param string|null $storage Optional storage name for the cache. Defaults to the current table name or 'capture' if not specified.
     * @param string|null $subfolder Optional file-based caching feature, the subfolder name used while storing the cache if any (default: null).
     * @param string|null $persistentId Optional memory-based caching feature, to set a unique persistent connection ID (default: `__database_builder__`).
     * 
     * @return bool Returns true if the cache was successfully cleared; otherwise, false.
     */
    public function cacheDelete(
        ?string $storage = null, 
        ?string $subfolder = null,
        ?string $persistentId = null
    ): bool
    {
        return $this->newCache($storage, $subfolder, $persistentId)->clear();
    }

    /**
     * Deletes all cached items for the specified subfolder or the default database cache.
     *
     * @param string|null $subfolder Optional file-based caching feature, the subfolder name used while storing caches if any (default: null).
     * @param string|null $persistentId Optional memory-based caching feature, to set a unique persistent connection ID (default: `__database_builder__`).
     * 
     * @return bool Returns true if the cache was successfully flushed, false otherwise.
     */
    public function cacheDeleteAll(?string $subfolder = null, ?string $persistentId = null): bool
    {
        return $this->newCache(null, $subfolder, $persistentId)->flush();
    }

    /**
     * Configures and manages caching for database queries or operations.
     * 
     * @param string $key The unique key identifying the cache item.
     * @param string|null $storage Optional storage name for the cache. Defaults to the current table name or 'capture' if not specified.
     * @param DateTimeInterface|int $expiry The cache expiration time (default: to 7 days).
     * @param string|null $subfolder Optional file-based caching feature, to set subfolder within the cache root directory (default: `database`).
     * @param string|null $persistentId Optional memory-based caching feature, to set a unique persistent connection ID (default: `__database_builder__`).
     * 
     * @return self Return instance of builder class.
     * @throws CacheException If an error occurs while creating cache or reading expired cache.
     */
    public function cache(
        string $key, 
        ?string $storage = null, 
        DateTimeInterface|int $expiry = 7 * 24 * 60 * 60, 
        ?string $subfolder = null,
        ?string $persistentId = null
    ): self
    {
        if($this->caching){
            self::$cache ??= $this->newCache($storage, $subfolder, $persistentId);
            self::$cache->setExpire($expiry);
            $this->cacheKey = md5($key);
            $this->queryWithCache = true;
            // Check if the cache exists and not expired
            $this->hasCache = (self::$cache->hasItem($this->cacheKey) && !self::$cache->hasExpired($this->cacheKey));
        }

        return $this;
    }

    /**
     * Insert records into a specified database table.
     * 
     * This method allows for inserting multiple records at once by accepting an array of associative arrays.
     * Each associative array should contain the column names as keys and their corresponding values as values.
     * 
     * @param array<int,array<string,mixed>> $values An array of associative arrays,
     *      where each associative array represents a record to be inserted into the table.
     * @param bool $usePrepare If set to true, uses prepared statements with bound values for the insert operation.
     *      If false, executes a raw query instead (default: true).
     * 
     * @return int Returns the number of affected rows, 0 if no rows were inserted.
     * @throws DatabaseException If an error occurs during the insert operation or if the provided values are not associative arrays.
     * @throws JsonException If an error occurs while encoding array values to JSON format.
     */
    public function insert(array $values, bool $usePrepare = true): int
    {
        return $this->doInsertOrReplace($values, 'INSERT', $usePrepare);
    }

    /**
     * Specifies an action to take when a duplicate key conflict occurs during an `INSERT` operation.
     *
     * This method allows defining custom update operations when a record with a duplicate key is encountered.
     *
     * Supported operations:
     * 
     * - `=` → Replace the value (e.g., `email = 'new@example.com'`).
     * - `+=` → Increment the existing value (e.g., `points = points + 10`).
     * - `-=` → Decrement the existing value (e.g., `points = points - 5`).
     *
     * @param string $column The column to update on duplicate key.
     * @param string $operation The operation to apply (`"="`, `"+="`, `"-="`).
     * @param mixed $value The new value or increment amount.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Example usage:
     * 
     * ```php
     * Builder::table('users')
     *     ->onDuplicate('points', '=', 'VALUES(points)')
     *     ->onDuplicate('points', '+=', 10) // Increment points by 10 on duplicate key
     *     ->onDuplicate('email', '=', 'new@example.com') // Update email on duplicate key
     *     ->insert([
     *         [
     *          'id' => 1, 
     *          'name' => 'Alice', 
     *          'points' => 50, 
     *          'email' => 'alice@example.com'
     *         ]
     *     ]);
     * ```
     */
    public function onDuplicate(string $column, string $operation, mixed $value): self 
    {
        $this->options['duplicate'][$column] = [
            'value' => $value,
            'operation' => $operation
        ];

        return $this;
    }

    /**
     * Sets whether duplicates should be ignored during insertion.
     * 
     * This method allows you to control the behavior of handling duplicate keys.
     * When set to `true`, duplicate keys will be ignored. If set to `false`, the default 
     * behavior is to handle duplicates as normal (which may result in an error or update based on the context).
     * 
     * @param bool $ignore Whether to ignore duplicates (default: `true`).
     * 
     * @return self Return instance of builder class.
     * 
     * @example - To ignore duplicates during insertion:
     * ```php
     * Builder::table('users')
     *     ->ignoreDuplicate()
     *     ->insert([
     *         [
     *          'id' => 1, 
     *          'name' => 'Alice', 
     *          'points' => 50, 
     *          'email' => 'alice@example.com'
     *         ]
     *     ]);
     * ```
     */
    public function ignoreDuplicate(bool $ignore = true): self 
    {
        $this->insertIgnoreDuplicate = $ignore;
        return $this;
    }

    /**
     * Replaces records in the specified database table.
     * 
     * This method replaces existing records, it will first **deletes** existing rows with the same primary key 
     * or unique key before inserting new ones. 
     * 
     * This may lead to unintended data loss, especially if foreign key constraints exist.
     * 
     * @param array<int,array<string,mixed>> $values An array of associative arrays,
     *      where each associative array represents a record to be replaced in the table.
     * @param bool $prepare If `true`, executes the operation using prepared statements with bound values.
     *      If `false`, executes a raw SQL query instead (default: `true`).
     * 
     * @return int Returns the number of affected rows (each `REPLACE` may count as **two**: one delete + one insert).
     * @throws DatabaseException If an error occurs during the operation or if the provided values are invalid.
     * @throws JsonException If an error occurs while encoding array values to JSON format.
     * 
     * > **Warning:** Since `replace` removes and re-inserts data, it can reset auto-increment values 
     * and trigger delete/insert events instead of update events.
     */
    public function replace(array $values, bool $prepare = true): int
    {
        return $this->doInsertOrReplace($values, 'REPLACE', $prepare);
    }

    /**
     * Prepares a copy selection query for copying data from the current table.
     *
     * This method selects the specified columns from the current table context, allowing the data 
     * to be copied into another table using the `to()` method.
     *
     * @param array<int,string> $columns The list of column names to be selected for copying.
     *
     * @return self Return instance of builder class.
     * @throws DatabaseException Throws if an error occurs.
     * @see to()
     * 
     * @example - Prepare a copy of specific columns
     * 
     * ```php
     * Builder::table('users')
     *      ->where('id', '=', 1)
     *      ->copy(['id', 'email', 'created_at'])
     *      ->to('backup_users', ['id', 'email', 'created_at']);
     * ```
     */
    public function copy(array $columns): self
    {
        $this->copySelections = $this->buildExecutableStatement(
            '', 'select', $columns, 'all', self::MODE_COPY
        );
        return $this;
    }

    /**
     * Executes the selected columns from `copy` method and insert into a target table.
     *
     * This method must be used after calling `copy()`, as it executes the previously prepared 
     * selection query and inserts the results into the specified target table.
     *
     * @param string $table The name of the target table where the copied data will be inserted.
     * @param array<int,string> $columns The list of target table column names that should receive the copied values.
     *
     * @return int Return the number of affected rows or 0 if the operation fails.
     * @throws DatabaseException Throws if an error occurs.
     * @throws InvalidArgumentException Throws if empty table is specified.
     * @see copy()
     * 
     * > **Warning:** Ensure the column structure of both tables is compatible; otherwise, the query may fail.
     */
    public function to(string $targetTable, array $columns): int
    {
        if(!$targetTable){
            throw new InvalidArgumentException('Copy target table cannot be empty.');
        }

        if(!$this->copySelections){
            return 0;
        }

        $inserts = trim(implode(',', $columns), ',');
        $sql = "INSERT INTO {$targetTable} ({$inserts}) {$this->copySelections}";

        if($this->debugMode === self::DEBUG_BUILDER){
            return $this->setDebugInformation($sql, 'copy') ? 1 : 0;
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug($sql, 'SQL QUERY');
            return 0;
        }

        $result = $this->db->query($sql)->ok() 
            ? $this->db->rowCount() 
            : 0;

        $this->reset();
        return $result;
    }

    /**
     * Executes an SQL query that was previously set using the `query()` method.
     * 
     * @param array<string,mixed>|null $placeholder An associative array of placeholder values to bind to the query.
     * @param int $mode The result return mode (default: `RETURN_ALL`).
     * 
     * **Available Return Modes:**
     * - `RETURN_ALL` - Returns all rows (default).
     * - `RETURN_NEXT` - Returns a single row or the next row from the result set.
     * - `RETURN_2D_NUM` - Returns a 2D array with numerical indices.
     * - `RETURN_ID` - Returns the last inserted ID.
     * - `RETURN_COUNT` - Returns the number of affected rows.
     * - `RETURN_COLUMN` - Returns columns from the result.
     * - `RETURN_INT` - Returns an integer count of records.
     * - `RETURN_STMT` - Returns a PDO or MySQLi prepared statement object.
     * - `RETURN_RESULT` - Returns a MySQLi result object or a PDO statement object.
     * 
     * @return mixed|\PDOStatement|\mysqli_result|\mysqli_stmt Returns the query result, a prepared statement object, or `false` on failure.
     *      - `PDOStatement` - If `env(database.connection)` is `PDO` and return mode is set to `RETURN_STMT` or `RETURN_RESULT`.
     *      - `mysqli_stmt` - If `env(database.connection)` is `MYSQLI` and return mode is set to `RETURN_STMT`.
     *      - `mysqli_result` - If `env(database.connection)` is `MYSQLI` and return mode is set to `RETURN_RESULT`.
     * @throws DatabaseException If `execute()` is called without setting a query.
     * 
     * @see query()
     * 
     * @example - Executing a prepared query:
     * ```php
     * $stmt = Builder::query("SELECT * FROM users WHERE user_id = :id");
     * $result = $stmt->execute(['id' => 1]);
     * 
     * // Fetching a single row:
     * $user = $stmt->execute(['id' => 1], RETURN_NEXT);
     * 
     * // Fetching row with catch:
     * $user = $stmt->cache()
     *      ->execute(['id' => 1], RETURN_ALL);
     * ```
     */
    public function execute(?array $placeholder = null, int $mode = RETURN_ALL): mixed 
    {
        if($this->buildQuery === ''){
            throw new DatabaseException(
                sprintf(
                    'Cannot call "%s" without a prepared SQL query. Use "%s" first.',
                    '$stmt->execute(...)',
                    'Builder::query(...)'
                ),
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        if(
            $mode !== RETURN_STMT && 
            $this->queryWithCache && 
            $this->hasCache && 
            (self::$cache instanceof BaseCache)
        ){
            $response = self::$cache->getItem($this->cacheKey);

            if($response !== null){
                $this->cacheKey = '';
                $this->queryWithCache = false;
                $this->reset();

                return $response;
            }
        }

        try {
            $response = $this->executeRawSqlQuery($this->buildQuery, $placeholder ?? [], $mode);

            if($mode !== RETURN_STMT && $this->queryWithCache && (self::$cache instanceof BaseCache)){
                self::$cache->set($this->cacheKey, $response);
                self::$cache = null;
                $this->queryWithCache = false;
            }

            return $response;
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Calculate the total number of records in selected table.
     * 
     * @param string $column The column to index calculation (default: *).
     * 
     * @return int Return total number of records in table, otherwise `0` if no result.
     * @throws DatabaseException If an error occurs.
     * 
     * @example - Get the number of users in country `NG`:
     * 
     * ```php
     * $total = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->total();
     * ```
     */
    public function total(string $column = '*'): int 
    {
        return (int) $this->buildExecutableStatement("SELECT COUNT({$column})");
    }

    /**
     * Determine if a records exists in selected table.
     * 
     * @return bool Return true if records exists in table, otherwise false.
     * @throws DatabaseException If an error occurs.
     * 
     * @example - Check if users in country `NG` exists in table:
     * 
     * ```php
     * $has = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->has();
     * ```
     */
    public function has(): bool 
    {
        return $this->buildExecutableStatement("SELECT COUNT(*)") > 0;
    }

    /**
     * Check if a database table exists.
     *
     * This method determines whether the specified table exists in the database.
     *
     * @return bool Returns `true` if the table exists, otherwise `false`.
     * @throws DatabaseException If an error occurs or the database driver is unsupported.
     *
     * @example - Check if the `users` table exists:
     * 
     * ```php
     * $exists = Builder::table('users')->exists();
     * ```
     * > **Note:** This method does not require a `WHERE` clause or logical operators.
     */
    public function exists(): bool 
    {
        $driver = $this->db->getDriver();
        $query = match ($driver) {
            'mysql', 'mysqli' => 'information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName',
            'pgsql'     => "pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = :tableName",
            'sqlite'    => "sqlite_master WHERE type = 'table' AND name = :tableName",
            'sqlsrv', 'mssql' => 'sys.tables WHERE name = :tableName',
            'cubrid'          => 'db_class WHERE class_name = :tableName',
            'dblib'           => "sysobjects WHERE xtype = 'U' AND name = :tableName",
            'oci', 'oracle'   => 'user_tables WHERE table_name = UPPER(:tableName)',
            default => throw new DatabaseException(
                "Unsupported database driver: {$driver}", 
                DatabaseException::INVALID_ARGUMENTS
            ),
        };

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$query}")
            ->bind(':tableName', $this->tableName);
        
        return $stmt->execute() && $stmt->getCount() > 0;
    }

    /**
     * Calculate the total sum of a numeric column in the table.
     * 
     * @param string $column The column to calculate the sum.
     * 
     * @return int|float Return total sum columns, otherwise 0 if no result.
     * @throws DatabaseException If an error occurs.
     * @example - Get the total sum of users votes in country `NG`:
     * 
     * ```php
     * $votes = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->sum('votes');
     * ```
     */
    public function sum(string $column): int|float
    {
        return $this->buildExecutableStatement("SELECT SUM({$column}) AS totalCalc", 'sum') ?: 0;
    }

    /**
     * Calculate the average value of a numeric column in the table.
     * 
     * @param string $column The column to calculate the average.
     * 
     * @return int|float Return total average of columns, otherwise 0 if no result.
     * @throws DatabaseException If an error occurs.
     * @example - Get the total average of users votes in country `NG`:
     * 
     * ```php
     * $votes = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->average('votes');
     * ```
     */
    public function average(string $column): int|float
    {
        return $this->buildExecutableStatement("SELECT AVG({$column}) AS totalCalc", 'average') ?: 0;
    }

    /**
     * Select multiple records from table.
     * 
     * @param array<int,string> $columns select columns.
     * 
     * @return object|null|array|int|float|bool Return selected rows, otherwise false if execution failed.
     * @throws DatabaseException If an error occurs.
     * @example - Get the all users from country `NG`:
     * 
     * ```php
     * $users = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->select(['votes', 'name']);
     * ```
     */
    public function select(array $columns = ['*']): mixed 
    {
        return $this->buildExecutableStatement('', 'select', $columns);
    }

    /**
     * Select a single or next record from table,
     * 
     * @param array<int,string> $columns The table columns to return (default: *).
     * 
     * @return object|null|array|int|float|bool Return selected single row, otherwise false if execution failed.
     * @throws DatabaseException If where method was not called or an error occurs.
     * @example - Get a single user from country `NG`:
     * 
     * ```php
     * $user = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->find(['votes', 'name']);
     * ```
     */
    public function find(array $columns = ['*']): mixed 
    {
        $this->assertStrictConditions(__METHOD__, true);
        
        return $this->buildExecutableStatement('', 'find', $columns);
    }

    /**
     * Select records from table, by passing desired fetch mode and result type.
     * 
     * @param string $result The fetch result type (next or all).
     * @param int $mode The fetch result mode FETCH_* (default: FETCH_OBJ).
     * @param array<int,string> $columns The table columns to return (default: *).
     * 
     * @return object|null|array|int|float|bool Return selected records, otherwise false if execution failed.
     * @throws DatabaseException If an error occurs.
     */
    public function fetch(string $result = 'all', int $mode = FETCH_OBJ, array $columns = ['*']): mixed 
    {
        if ($result === 'all' || $result === 'next') {
            return $this->buildExecutableStatement('', 'fetch', $columns, $result, $mode);
        }

        throw new DatabaseException(
            'Invalid fetch result type, expected "all or next".', 
            DatabaseException::VALUE_FORBIDDEN
        );
    }

    /**
     * Returns query prepared statement based on build up method conditions.
     * 
     * @param array<int,string> $columns An optional array of columns to return (default: `[*]`).
     *                      Column maybe required when called `stmt` with `select`, `find` or `fetch`.
     * 
     * @return DatabaseInterface Return prepared statement if query is successful otherwise null.
     * @throws DatabaseException Throws if an error occurs.
     */
    public function stmt(array $columns = ['*']): ?DatabaseInterface
    {
        if($this->resultReturnType === self::RETURN_STATEMENT){
            return $this->db;
        }

        $this->resultReturnType = self::RETURN_STATEMENT;
        $stmt = $this->buildExecutableStatement('', self::RETURN_STATEMENT, $columns);

        if($stmt instanceof DatabaseInterface){
            return $stmt;
        }

        $this->reset();
        return null;
    }

    /**
     * Update table with columns and values.
     * 
     * This method constructs and executes an `UPDATE` statement based on the current query conditions. 
     * It ensures that strict mode prevents execution without a `WHERE` clause, reducing the risk of accidental override.
     * 
     * @param array<string,mixed>|null $setValues An optional associative array of columns and values to update if not already set defined using `set` method.
     * 
     * @return int Return number of affected rows.
     * @throws DatabaseException Throw if error occurred while updating or where method was never called.
     * @throws JsonException If an error occurs while encoding values.
     * 
     * @example - Update table with columns and values
     * ```php
     * Builder::table('users')
     *      ->where('id', '=', 1)
     *      ->strict(true) // Enable or disable strict where clause check
     *      ->update([
     *          'updated_at' => Builder::datetime(),
     *          'scores' => Builder::raw('scores + 1')
     *      ]);
     * ```
     */
    public function update(?array $setValues = null): int 
    {
        $this->assertStrictConditions(__METHOD__);

        $columns = (!$setValues || $setValues === []) ? $this->querySetValues : $setValues;

        if ($columns === []) {
            throw new DatabaseException(
                'Update operation without SET values is not allowed. Set update values directly with update method or use set method instead.', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        if(isset($columns[0])){
            throw new DatabaseException(
                'Invalid update values, values must be an associative array, key-value pairs, where the key is the column name and the value to update.', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        $sql = "UPDATE {$this->tableName} {$this->tableAlias}";
        $sql .= $this->getJoinConditions();
        $sql .= ' SET ' . self::buildPlaceholder($columns, true);
        $sql .= $this->whereCondition['query'] ?? '';
        $this->buildConditions($sql, $this->whereCondition === []);

        if($this->maxLimit > 0){
            $sql .= " LIMIT {$this->maxLimit}";
        }

        if($this->debugMode === self::DEBUG_BUILDER){
            $this->setDebugInformation($sql, 'update', $columns);
            return 0;
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug($sql, 'SQL QUERY');
        }

        try {
            $this->db->prepare($sql);
            $this->bindStrictColumns($columns);

            if(isset($this->whereCondition['placeholder'])){
                $this->setBindValue($this->whereCondition['placeholder'], $this->whereCondition['value']);
            }
            
            $this->bindConditions();
            $this->bindJoinPlaceholders();

            if($this->debugMode === self::DEBUG_BUILDER_DUMP){
                $this->reset();
                return 0;
            }

            $response = $this->db->execute() ? $this->db->rowCount() : 0;
            $this->reset();

            return $response;
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Delete records from the table.
     *
     * This method constructs and executes a `DELETE` statement based on the 
     * current query conditions. It ensures that strict mode prevents execution 
     * without a `WHERE` clause, reducing the risk of accidental deletions.
     *
     * @return int Return the number of affected rows.
     * @throws DatabaseException If an error occurs during execution.
     * 
     * @example - Delete table column:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('id', '=', 1)
     *      ->strict(true) // Enable or disable strict where clause check
     *      ->delete();
     * ```
     */
    public function delete(): int
    {
        $this->assertStrictConditions(__METHOD__);

        $sql = "DELETE FROM {$this->tableName} {$this->tableAlias}";
        $sql .= $this->getJoinConditions();

        try {
            return (int) $this->getStatementExecutionResult($sql, 'delete');
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Get error information.
     * 
     * @return array Return error information.
     */
    public function errors(): array 
    {
        return $this->db->errors();
    }

    /**
     * Begins a transaction with optional read-only isolation level and savepoint.
     *
     * @param int $flags Optional flags to set transaction properties.
     *                  For MySQLi:
     *                      - MYSQLI_TRANS_START_READ_ONLY: Set transaction as read-only.
     *                  For PDO:
     *                      - No predefined flags, specify `4` to create read-only isolation.
     * @param ?string $name Optional name for a savepoint.
     *                    If provided in PDO, savepoint will be created instead.
     * 
     * @return bool Returns true if the transaction and optional savepoint were successfully started.
     * @throws DatabaseException Throws exception on PDO if failure to set transaction isolation level or create savepoint.
     * 
     * @see commit()
     * @see rollback();
     * 
     * @example - Transaction:
     * 
     * ```php
     * $tbl = Builder::table('users')
     *  ->transaction()
     *  ->where('country', '=', 'NG');
     * 
     * if($tbl->update(['suburb', 'Enugu'])){
     *      $tbl->commit();
     * }else{
     *      $tbl->rollback();
     * }
     * 
     * $tbl->free();
     * ```
     */
    public function transaction(int $flags = 0, ?string $name = null): bool 
    {
        if($this->db->beginTransaction($flags, $name)){
            return $this->inTransaction = true;
        }

        DatabaseException::throwException(
            sprintf(
                'Transaction failed to start%s (flags: %d)', 
                $name ? " for \"$name\"" : '', 
                $flags
            ),
            DatabaseException::DATABASE_TRANSACTION_FAILED
        );

        return $this->inTransaction = false;
    }

    /**
     * Checks if a transaction is currently active.
     *
     * @return bool Returns true if a transaction is active, false otherwise.
     */
    public function inTransaction(): bool 
    {
        return $this->inTransaction;
    }

    /**
     * Commits a transaction.
     *
     * @param int $flags Optional flags for custom handling.
     *                 Only supported in MySQLi.
     * @param ?string $name Optional name for a savepoint.
     *                Only supported in MySQLi.
     * 
     * @return bool Returns true if the transaction was successfully committed.
     * 
     * @see transaction()
     */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        if($this->inTransaction && $this->db->commit($flags, $name)){
            $this->inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Rolls back the current transaction or to a specific savepoint.
     *
     * @param int $flags Optional flags for custom handling.
     *                   Only supported in MySQLi.
     * @param ?string $name Optional name of the savepoint to roll back to.
     *                    If provided in PDO, rolls back to the savepoint named.
     * 
     * @return bool Return true if rolled back was successful, otherwise false.
     * @throws DatabaseException Throws exception on PDO if failure to create savepoint.
     * 
     * @see transaction()
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        if($this->inTransaction && $this->db->rollback($flags, $name)){
            $this->inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * Apply a row-level lock to the query for concurrency control.
     *
     * Call this method before executing `find()` or similar fetch operations.
     * Must be used within a transaction.
     * 
     * **Lock Modes:**
     * 
     * - `'update'`: Exclusive lock. Allows reads, blocks writes by others.
     * - `'shared'`: Shared lock. Allows others to read, but not write.
     * 
     * 
     * @param string $mode The lock mode: 'update' or 'shared' (default: `update`).
     * 
     * @return self
     * @throws InvalidArgumentException If invalid lock type is given.
     * 
     * > **Note:** Must be used inside a transaction.
     *
     * @example Lock rows for update (exclusive lock):
     * ```php
     * $tbl = Builder::Table('users');
     * 
     * $tbl->transaction();
     * 
     * $rows = $tbl->where('user_id', '=', 123)
     *     ->lockFor('update') // Prevents others from reading or writing
     *     ->find();
     * 
     * $tbl->commit();
     * ```
     *
     * @example Lock rows for shared read (shared lock):
     * ```php
     * $tbl = Builder::Table('users');
     * 
     * $tbl->transaction();
     * 
     * $rows = $tbl->where('user_id', '=', 123)
     *     ->lockFor('shared') // Allows others to read, but not write
     *     ->find();
     * 
     * $tbl->commit();
     * ```
     */
    public function lockFor(string $mode = 'update'): self 
    {
        $mode = strtolower($mode);

        if(!in_array($mode, ['update', 'shared'], true)){
            throw new InvalidArgumentException("Invalid lock type: $mode");
        }
        
        $forUpdate = ($mode === 'update');

        $this->lock = match ($this->db->getDriver()) {
            'mysql', 'mysqli' => $forUpdate ? 'FOR UPDATE' : 'LOCK IN SHARE MODE',
            'pgsql'           => $forUpdate ? 'FOR UPDATE' : 'FOR SHARE',
            'sqlite'          => '', // SQLite locks the whole DB automatically
            'sqlsrv', 'mssql', 'dblib' => $forUpdate 
                ? 'WITH (UPDLOCK, ROWLOCK)' 
                : 'WITH (HOLDLOCK, ROWLOCK)',
            'cubrid'          => $forUpdate ? 'WITH LOCK' : '',
            'oci', 'oracle'   => $forUpdate ? 'FOR UPDATE' : '',
            default           => '',
        };

        return $this;
    }

    /**
     * Handles database-level locking using advisory locks for PostgreSQL and MySQL.
     * 
     * - **PostgreSQL**: Uses `pg_advisory_lock()` and `pg_advisory_unlock()`, requiring an **integer** lock name.
     * - **MySQL**: Uses `GET_LOCK()`, `RELEASE_LOCK()`, and `IS_FREE_LOCK()`, allowing **string** lock names.
     *
     * @param string|int $identifier Lock identifier (must be an integer for PostgreSQL).
     * @param int $timeout Lock timeout in seconds (only applicable for MySQL).
     * 
     * @return bool Return true if the operation was successful, false otherwise.
     * @throws DatabaseException If an invalid action is provided or an invalid PostgreSQL lock name is used.
     */
    public static function lock(string|int $identifier, int $timeout = 300): bool 
    {
        return self::administration($identifier, 'lock', $timeout);
    }

    /**
     * Releases the lock for the given name.
     *
     * @param string|int $identifier Lock identifier (must be an integer for PostgreSQL).
     * 
     * @return bool Return true if the lock was successfully released, false otherwise.
     * @throws DatabaseException If an invalid action is provided or an invalid PostgreSQL lock name is used.
     */
    public static function unlock(string|int $identifier): bool 
    {
        return self::administration($identifier, 'unlock');
    }

    /**
     * Truncate database table records.
     * 
     * This method will attempt to clear all table records. 
     * If transaction is enable and operation is failed it rolls back to default.
     * 
     * @param bool $transaction Whether to use transaction (default true).
     * @param int|null $restIncrement Whether to reset auto-increment if applicable (default `null`).
     * 
     * @return bool Return true truncation was completed, otherwise false.
     * @throws DatabaseException Throws if an error occurred during execution.
     * 
     * @example - Clear all records in table:
     * 
     * ```php
     * Builder::table('users')->truncate();
     * ```
     */
    public function truncate(bool $transaction = true, ?int $restIncrement = null): bool 
    {
        try {
            $driverName = $this->db->getDriver();
            $transaction = ($transaction && $driverName !== 'sqlite');

            if ($transaction && !$this->db->beginTransaction()) {
                DatabaseException::throwException(
                    'Failed: Unable to start transaction', 
                    DatabaseException::DATABASE_TRANSACTION_FAILED
                );
                return false;
            }

            if ($driverName === 'mysql' || $driverName === 'mysqli' || $driverName === 'pgsql') {
                $completed = $this->db->exec("TRUNCATE TABLE {$this->tableName}");
            } elseif ($driverName === 'sqlite') {
                $deleteSuccess = $this->db->exec("DELETE FROM {$this->tableName}");
                $resetSuccess = true;

                $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sqlite_sequence'")
                    ->fetchNext('array');

                if ($result) {
                    $resetSuccess = $this->db->exec("DELETE FROM sqlite_sequence WHERE name = '{$this->tableName}'");
                }

                $completed = $deleteSuccess && $resetSuccess;

                if ($completed && !$this->db->exec("VACUUM")) {
                    $completed = false;
                }
            } else {
                $deleteSuccess = $this->db->exec("DELETE FROM {$this->tableName}");
                $resetSuccess = ($restIncrement !== null)
                    ? $this->db->exec("ALTER TABLE {$this->tableName} AUTO_INCREMENT = {$restIncrement}")
                    : true;

                $completed = $deleteSuccess && $resetSuccess;
            }

            if ($transaction && $this->db->inTransaction()) {
                if ($completed && $this->db->commit()) {
                    $this->reset();
                    return true;
                }

                $this->db->rollback();
                $completed = false;
            }

            $this->reset();
            return (bool) $completed;

        } catch (Throwable $e) {
            if ($transaction && $this->db->inTransaction()) {
                $this->db->rollback();
            }

            $this->reset();
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }

        if ($transaction && $this->db->inTransaction()) {
            $this->db->rollback();
        }

        $this->reset();
        return false;
    }

    /**
     * Creates a temporary table and copies all records from the main table to the temporary table.
     *
     * @param bool $transaction Whether to use a transaction (default is true).
     *
     * @return bool Returns true if the operation was successful; false otherwise.
     * @throws DatabaseException Throws an exception if a database error occurs during the operation.
     *
     * @example - Example:
     * ```php
     * if (Builder::table('users')->temp()) {
     *     $data = Builder::table('temp_users')->select();
     * }
     * ```
     * 
     * **Note:**
     * - Temporary tables are automatically deleted when the current session ends.
     * - To query the temporary table, use the `temp_` prefix before the main table name.
     */
    public function temp(bool $transaction = true): bool 
    {
        if($this->tableName === ''){
            throw new DatabaseException(
                'You must specify a table name before creating temporal table.', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        try {
            if($transaction && !$this->db->beginTransaction()){
                DatabaseException::throwException(
                    'Failed: Unable to start transaction', 
                    DatabaseException::DATABASE_TRANSACTION_FAILED
                );
                return false;
            }

            $create = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_{$this->tableName} ";
            $create .= "AS (SELECT * FROM {$this->tableName} WHERE 1 = 0)";

            if (
                $this->db->exec($create) > 0 && 
                $this->db->exec("INSERT INTO temp_{$this->tableName} SELECT * FROM {$this->tableName}") > 0
            ) {
                $result = false;
                if($transaction && $this->db->inTransaction()){
                    if($this->db->commit()){
                        $result = true;
                    }else{
                        $this->db->rollBack();
                    }
                }

                $this->reset();
                return $result;
            }

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->reset();
            return false;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->reset();
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }

        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        $this->reset();
        return false;
    }

    /**
     * Drop database table table or temporal if it exists.
     * 
     * @param bool $transaction Whether to use a transaction (default: false).
     * @param bool $isTemporalTable Whether the table is a temporary table (default false).
     * 
     * @return bool Return true if table was successfully dropped, false otherwise.
     * @throws DatabaseException Throws if error occurs.
     * 
     * @example - Dripping a database table:
     * 
     * ```
     * Builder::table('users')->drop();
     * ```
     * Drop table using transaction: 
     * 
     * ```
     * Builder::table('users')->drop(true);
     * ```
     * 
     * Drop temporal table: 
     * 
     * ```
     * Builder::table('users')->drop(false, true);
     * ```
     */
    public function drop(bool $transaction = false, bool $isTemporalTable = false): bool 
    {
        if ($this->tableName === '') {
            throw new DatabaseException(
                'You must specify a table name before dropping a temporary table.', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        try {
            if ($transaction && !$this->db->beginTransaction()) {
                DatabaseException::throwException(
                    'Failed: Unable to start transaction for drop table.', 
                    DatabaseException::DATABASE_TRANSACTION_FAILED
                );
                return false;
            }

            $drop = $this->getDropTableSQL($isTemporalTable);

            if ($this->db->exec($drop) > 1) {
                $result = false;
                if ($transaction && $this->db->inTransaction()) {
                    if ($this->db->commit()) {
                        $result = true;
                    } else {
                        $this->db->rollBack();
                    }
                }

                $this->reset();
                return $result;
            }

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->reset();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->reset();
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }

        return false;
    }
    
    /**
     * Retrieves the database manager instance.
     * 
     * Returns a singleton instance of the Manager class initialized with the current database connection.
     * 
     * @return Manager Database manager class instance.
     * @throws DatabaseException Throws if database connection failed.
     * 
     * @see https://luminova.ng/docs/0.0.0/database/manager
     */
    public function manager(): Manager 
    {
        return new Manager($this->db, $this->tableName);
    }

    /**
     * Exports the database table and downloads it to the browser as JSON or CSV format.
     * 
     * @param string $as Export as csv or json format.
     * @param string|null $filename Filename to download.
     * @param array $columns Table columns to export (default: all).
     * 
     * @return bool Return true if export is successful, false otherwise.
     * 
     * @throws DatabaseException If an invalid format is provided or if unable to create the export.
     */
    public function export(string $as = 'csv', ?string $filename = null, array $columns = ['*']): bool 
    {
        return  $this->manager()->export($as, $filename, $columns);
    }

    /**
     * Creates a backup of the database table.
     * 
     * @param string|null $filename Optional name of the backup file (default: null). If not provided, table name and timestamp will be used.
     * 
     * @return bool Return true if backup is successful, false otherwise.
     * 
     * @throws DatabaseException If unable to create the backup directory or if failed to create the backup.
     */
    public function backup(?string $filename = null): bool 
    {
        return $this->manager()->backup($filename, true);
    }

    /**
     * Frees up the statement cursor and sets the statement object to null.
     * 
     * If in transaction, it will return false, you can free when transaction is done (e.g, `committed` or `rollback`).
     * Additionally it automatically closes database connection if `closeAfter` is enabled.
     * 
     * @return bool Return true if successful, otherwise false.
     */
    public function free(): bool 
    {
        if($this->inTransaction || !($this->db instanceof DatabaseInterface)){
            return !$this->inTransaction;
        }

        if($this->closeConnection){
            return $this->close();
        }

        $this->db->free();
        $this->db = null;

        return true;
    }

    /**
     * Close database connection.
     * 
     * This method closes the current connection attached to query instance and also all open connection in pool.
     * 
     * @return bool Return true if database connection is close, otherwise false. 
     */
    public function close(): bool 
    {
        if($this->db instanceof DatabaseInterface){
            $this->db->free();

            if($this->db->isConnected()){
                $this->db->close();
            }

            $this->db = null;
        }

        if(!(self::$conn instanceof Connection) || !self::isConnected()){
            return true;
        }

        if(self::$conn->purge(true)){
            self::$conn = null;
            return true;
        }

        return false;
    }

    /**
     * Reset query builder executions to default as needed.
     * 
     * This method frees the database statement cursor if not in transaction and `returns` is not a `statement` object. 
     * Also it automatically closes database connection if `closeAfter` is enabled.
     * 
     * @return void 
     */
    public function reset(): void 
    {
        $this->tableJoin = [];
        $this->joinConditions = [];
        $this->selectLimit = '';
        $this->maxLimit = 0;
        $this->options = [
            'grouping' => [],
            'binds'   => [],
            'ordering' => [],
            'filters'  => [],
            'match'    => []
        ];
        $this->whereCondition = [];
        $this->andConditions = [];
        $this->querySetValues = [];
        $this->hasCache = false;
        $this->debugMode = 0;
        $this->strictChecks = true;
        $this->insertIgnoreDuplicate = false;
        $this->copySelections = '';
        $this->buildQuery = '';

        if(!$this->inTransaction || $this->resultReturnType !== self::RETURN_STATEMENT){
            $this->free();
        }
        
        $this->resultReturnType = 'object';
    }

    /**
     * Initializes a new singleton instance and inherit parent the `getInstance` method setup.
     *
     * @param string|null $table The name of the table to initialize the builder with.
     * @param string|null $alias The alias for the table.
     *
     * @return self Return a new instance of the Builder class.
     *
     * @throws Exception If an error occurs during initialization.
     */
    private static function initializer(?string $table = null, ?string $alias = null): Builder
    {
        if(!self::$instance instanceof self){
            $instance = new self($table, $alias);
            $instance->db = self::database();

            return $instance;
        }

        $caching = self::$instance->caching;
        $returns = self::$instance->resultReturnType;
        $strict = self::$instance->strictChecks;
        $close = self::$instance->closeConnection;
        $clone = clone self::$instance;

        $clone->tableName = $table ?? '';
        $clone->tableAlias = $alias ?? '';
        $clone->caching = $caching;
        $clone->resultReturnType = $returns;
        $clone->strictChecks = $strict;
        $clone->closeConnection = $close;
        $clone->db = self::database();
        return $clone;
    }

    /**
     * Assert where clause while performing delete or update statement.
     * 
     * @param string $fn The method that is called.
     * @param bool $required Force strict check.
     * 
     * @return void
     */
    private function assertStrictConditions(string $fn, bool $required = false): void
    {
        if (
            ($required || $this->strictChecks) && 
            $this->whereCondition === [] && 
            $this->andConditions === []
        ) {
            throw new DatabaseException(
                sprintf('Execution of %s is not allowed in strict mode without a "WHERE" condition.', $fn), 
                DatabaseException::VALUE_FORBIDDEN
            );
        }
    }

    /**
     * Validates the provided table name and alias.
     * 
     * @param string|null $table The table name to validate (optional).
     * @param string|null $alias The alias name to validate (optional).
     * 
     * @throws InvalidArgumentException If the table name or alias contains invalid characters.
     */
    private function assertTable(string|null $table, string|null $alias): void 
    {
        if($table !== null){
            if($table === '' || $table === '``'){
                throw new InvalidArgumentException(
                    'Invalid table name. The ($table) argument must be a non-empty string.'
                );
            }

            if (!preg_match('/^`?[a-zA-Z0-9_.-]+`?$/', $table)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid table name "%s". Only letters, numbers, underscores, and dots are allowed.', $table
                ));
            }
        }

        if ($alias !== null && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid table alias "%s". Aliases must start with a letter or underscore and contain only letters, numbers, or underscores.', 
                $alias
            ));
        }
    }

    /**
     * Create query and execute it.
     * 
     * @param string $query The base SQL query string to execute.
     * @param string $method The execution method called (expected: `total`, `stmt`, `select`, `find`, `delete`, `fetch`).
     * @param array $columns For select and find methods, the column names to return.
     * @param string $result The fetch result type (next or all).
     * @param int $mode The database result mode for result retrieval (e.g., `FETCH_OBJ`, `FETCH_*`).
     * 
     * @return mixed Return the execution result, value varies based on the `$method` and `$mode` parameter.
     * @throws DatabaseException If an error occurs during query execution or result fetching.
     */
    private function buildExecutableStatement(
        string $query, 
        string $method = 'total', 
        array $columns = ['*'], 
        string $result = 'all', 
        int $mode = FETCH_OBJ
    ): mixed
    {
        if(
            $this->resultReturnType !== self::RETURN_STATEMENT &&
            $this->debugMode !== self::DEBUG_BUILDER && 
            $mode !== self::MODE_COPY &&
            $method !== self::RETURN_STATEMENT && 
            $this->queryWithCache && 
            $this->hasCache &&
            (self::$cache instanceof BaseCache)
        ){
            $response = self::$cache->getItem($this->cacheKey);

            if($response !== null){
                $this->cacheKey = '';
                $this->queryWithCache = false;
                $this->reset();
                return $response;
            }
        }

        if(in_array($method, ['select', 'find', 'stmt', 'fetch'], true)){
            $columns = ($columns === ['*']) ? '*' : implode(', ', $columns);
            $query = "SELECT {$columns}";
        }
        
        $query .= " FROM {$this->tableName} {$this->tableAlias}";

        if($this->lock && in_array($this->db->getDriver(), ['sqlsrv', 'mssql', 'dblib'])){
            $query .= ' ' . $this->lock;
        }
     
        $query .= $this->getJoinConditions();

        try {
            $response = $this->getStatementExecutionResult($query, $method, $result, $mode);

            if($mode === self::MODE_COPY || $this->resultReturnType === self::RETURN_STATEMENT){ 
                return $response;
            }

            if(
                $this->debugMode !== self::DEBUG_BUILDER &&
                $method !== self::RETURN_STATEMENT &&
                $this->queryWithCache && 
                (self::$cache instanceof BaseCache)
            ){
                self::$cache->set($this->cacheKey, $response);
                self::$cache = null;
                $this->queryWithCache = false;
            }

            return $response;
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }
        
        return false;
    }

    /**
     * Executes an SQL statement and returns the result based on specified parameters.
     *
     * This method builds and executes an SQL query, applying various conditions, ordering,
     * and limits as specified. It handles different types of queries including select,
     * delete, and custom operations.
     * 
     *  - 'stmt': boolean indicating if the statement was prepared successfully
     *  - 'select': array of fetched results.
     *  - 'find': single fetched result.
     *  - 'total': count query result.
     *  - 'delete': number of affected rows.
     *  - 'fetch': fetched results based on $result and $mode.
     *  - default: calculated total result.
     *
     * @param string $sql The base SQL query string to execute.
     * @param string $method The execution method called (expected: `total`, `stmt`, `select`, `find`, `delete`, `fetch`).
     * @param string $result The return result type for `$method` operations when `fetch` is used (expected: `next`, `all` or `stream`).
     * @param int $mode The database result mode for result retrieval (e.g., `FETCH_OBJ`).
     *
     * @return mixed Return the execution result, value varies based on the `$method` and `$mode` parameter.
     * @throws DatabaseException If an error occurs during query execution or result fetching.
     */
    private function getStatementExecutionResult(
        string $sql, 
        string $method = 'total', 
        string $result = 'all', 
        int $mode = FETCH_OBJ
    ): mixed
    {
        // When using IN as WHERE and it has other ANDs ORs as binding.
        $isOrdered = false;
        $response = false;
        $isEmptyWhere = $this->whereCondition === [];
        $isDelete = $method === 'delete';
        $isWhereParam = false;
        
        if (!$isEmptyWhere) {
            $isWhereParam = isset($this->whereCondition['placeholder']);
            $sql .= $this->whereCondition['query'];
        }

        if($this->andConditions !== []){
            $this->buildConditions($sql, $isEmptyWhere);
        }

        if(!$isDelete){
            if($this->getOptions('grouping') !== []){
                $sql .= ' GROUP BY ' . rtrim(implode(', ', $this->getOptions('grouping')), ', ');
            }

            if($this->getOptions('ordering') !== []){
                $isOrdered = true;
                $sql .= ' ORDER BY ' . rtrim(implode(', ', $this->getOptions('ordering')), ', ');
            }

            $this->setHavingConditions($sql);
            $this->setMatchAgainst($sql, $isOrdered);
        }

        if($isDelete || $method === 'find'){
            $limit = ($method === 'find') ? 1 : $this->maxLimit;
            $sql .= ($limit > 0) ? " LIMIT {$limit}" : '';
        }elseif($this->selectLimit !== ''){
            $sql .= $this->selectLimit;
        }

        if($this->lock && !in_array($this->db->getDriver(), ['sqlsrv', 'mssql', 'dblib'])){
            $sql .= ' ' . $this->lock;
        }

        if($mode === self::MODE_COPY){
            return $sql;
        }

        if($this->debugMode === self::DEBUG_BUILDER){
           return $this->setDebugInformation($sql, $method);
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug($sql, 'SQL QUERY');
        }

        $hasParams = ($isWhereParam 
            || $this->getOptions('match') !== [] 
            || $this->andConditions !== []);

        if($hasParams){
            $this->db->prepare($sql);

            if ($isWhereParam) {
                $this->setBindValue($this->whereCondition['placeholder'], $this->whereCondition['value']);
                $this->bindConditions();
            }elseif(!$this->bindConditions()) {
                $hasParams = $this->getOptions('binds') !== [];
            }
        }

        if($hasParams){
            $this->bindJoinPlaceholders();

            if($this->debugMode === self::DEBUG_BUILDER_DUMP){
                return false;
            }

            $this->db->execute();
        }else{
            if($this->debugMode === self::DEBUG_BUILDER_DUMP){
                return false;
            }

            $this->db->query($sql);
        }

        if($this->resultReturnType === self::RETURN_STATEMENT){
            return $this->db;
        }

        if ($this->db->ok()) {
            $response = match ($method) {
                'stmt' => true,
                'select' => $this->db->fetchAll($this->resultReturnType),
                'find' => $this->db->fetchNext($this->resultReturnType),
                'total' => $this->db->getCount(),
                'delete' => $this->db->rowCount(),
                'fetch' => $this->db->fetch($result, $mode),
                default => ($this->db->fetchNext() ?: (object) ['totalCalc' => 0])->totalCalc
            };
        }
        
        $this->reset();
        $sql = null;
        
        return $response;
    }

    /**
     * Handle insert or replace statement.
     * 
     * @param array<int,array<string,mixed>> $values The values to insert or replace.
     * @param string $type The insert type to execute (expected: `INSERT` or `REPLACE`).
     * @param bool $prepare Whether to use the prepared statement or query execution.
     * 
     * @return int Return the number of affected rows.
     * @throws DatabaseException If an error occurs during the operation or if the provided values are invalid.
     * @throws JsonException If an error occurs while encoding array values to JSON format.
     */
    private function doInsertOrReplace(array $values, string $type, bool $prepare = true): int
    {
        if ($values === []) {
            return 0;
        }

        if (!isset($values[0])) {
            $values = [$values];
        }
        
        if (!is_associative($values[0])) {
            DatabaseException::throwException(
                sprintf('Invalid %s values, values must be an associative array.', strtolower($type)), 
                DatabaseException::VALUE_FORBIDDEN
            );
            return 0;
        }

        if($this->insertIgnoreDuplicate){
            if($type === 'REPLACE'){
                throw new DatabaseException(
                    'Cannot call "->replace(...)" method with "->ignoreDuplicate(true)" enabled. ' .
                    'The REPLACE operation conflicts with the duplicate-ignore behavior.'
                );
            }

            if($this->getOptions('duplicate') !== []){
                throw new DatabaseException(
                    'Cannot use "->ignoreDuplicate(true)" with "->onDuplicate(...)" options set. ' .
                    'Both options cannot be enabled at the same time, as they conflict.'
                );
            }
        }

        try {
            return $prepare
                ? $this->executeInsertPrepared($values, $type) 
                : $this->executeInsertQuery($values, $type);
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Return raw SQL query builder execution result.
     * 
     * @param string $sql The SQL query to execute.
     * @param array<string,mixed> $placeholder An associative array of placeholder values to bind to the query.
     * @param int $mode The return result type mode.
     * 
     * @return mixed|DatabaseInterface Return query result, prepared statement object, otherwise false on failure.
     * @throws DatabaseException If placeholder key is not a string.
    */
    private function executeRawSqlQuery(
        string $sql, 
        array $placeholder = [], 
        int $mode = RETURN_ALL
    ): mixed
    {
        if($this->debugMode === self::DEBUG_BUILDER){
            return $this->setDebugInformation($sql, 'execute', $placeholder);
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug($sql, 'SQL QUERY');

            if($placeholder === []){
                return false;
            }
        }

        if($placeholder === []){
            $this->db->query($sql);
        }else{
            $this->db->prepare($sql);
            $this->bindStrictColumns($placeholder);
            
            if($this->debugMode === self::DEBUG_BUILDER_DUMP){
                return false;
            }

            $this->db->execute();
        }

        $response = ($this->db->ok() ? 
            (($mode === RETURN_STMT) 
                ? $this->db
                : $this->db->getResult($mode, $this->resultReturnType)) 
            : false);
        $this->reset();

        return $response;
    }

    /**
     * Constructs and returns the SQL JOIN conditions for the query.
     *
     * This method builds the JOIN part of the SQL query based on the join table,
     * join type, and join conditions that have been set.
     *
     * @return string Return the constructed JOIN clause of the SQL query, or an empty string if no join is set.
     */
    private function getJoinConditions(): string 
    {
        if ($this->tableJoin === []) {
            return '';
        }

        $sql = '';
        foreach($this->tableJoin as $key => $values){
            $table = $values['table'];
            $alias = $values['alias'] ?? '';

            $sql .= " {$values['type']} JOIN {$table} {$alias}";

            if($this->joinConditions !== [] && isset($this->joinConditions[$key])){
                $sql .= " ON {$this->joinConditions[$key][0]}";

                if(($joins = count($this->joinConditions[$key])) > 1){
                    for ($i = 1; $i < $joins; $i++) {
                        $sql .= " AND {$this->joinConditions[$key][$i]}";
                    }
                }
            }
        }

        return $sql;
    }

    /**
     * Executes the appropriate lock/unlock/free query based on the database type.
     *
     * @param string|int $identifier Lock identifier (integer required for PostgreSQL).
     * @param string $action Action to perform: 'lock', 'unlock', or 'isLocked'.
     * @param int $timeout Lock timeout in seconds (only applicable for MySQL).
     * 
     * @return bool Return true if the operation was successful, false otherwise.
     * @throws DatabaseException If an invalid action is provided or an invalid PostgreSQL lock name is used.
     */
    private static function administration(string|int $identifier, string $action, int $timeout = 300): bool 
    {
        $tbl = self::table('locks');
        $driver = $tbl->database()->getDriver();
        $pgsqlPlaceholder = ($driver === 'pgsql') 
            ? (is_int($identifier) ? ':lockName' : 'hashtext(:lockName)')
            : null;

        if ($driver === 'sqlite') {
            static $exists = null;
            $exists = ($exists === null) ? $tbl->exists() : $exists;

            if(!$exists){
                $createTblQuery = 'CREATE TABLE IF NOT EXISTS locks (name TEXT PRIMARY KEY, acquired_at INTEGER)';
                $exists = (bool) $tbl->database()->exec($createTblQuery);

                if(!$exists){
                    throw new DatabaseException(
                        "SQLite Error: Failed to create lock table with query: '{$createTblQuery}'",
                        DatabaseException::INVALID_ARGUMENTS
                    );
                }
            }
        }

        $query = match ($driver) {
            'pgsql' => match ($action) {
                'lock'     => "SELECT pg_advisory_lock({$pgsqlPlaceholder})",
                'unlock'   => "SELECT pg_advisory_unlock({$pgsqlPlaceholder})",
                'isLocked' => "SELECT pg_try_advisory_lock({$pgsqlPlaceholder})",
                default    => null
            },
            'mysql', 'mysqli', 'cubrid' => match ($action) {
                'lock'     => 'SELECT GET_LOCK(:lockName, :waitTimeout) AS isLockDone',
                'unlock'   => 'SELECT RELEASE_LOCK(:lockName) AS isLockDone',
                'isLocked' => 'SELECT IS_FREE_LOCK(:lockName) AS isLockDone',
                default    => null
            },
            'sqlite' => match ($action) {
                'lock'     => 'INSERT INTO locks (name, acquired_at) VALUES (:lockName, strftime("%s", "now")) ON CONFLICT(name) DO NOTHING',
                'unlock'   => 'DELETE FROM locks WHERE name = :lockName',
                'isLocked' => 'SELECT COUNT(*) AS lockCount FROM locks WHERE name = :lockName',
                default    => null,
            },
            'sqlsrv', 'mssql', 'dblib' => match ($action) {
                'lock'     => "EXEC sp_getapplock @Resource = :lockName, @LockMode = 'Exclusive', @LockOwner = 'Session', @Timeout = :waitTimeout",
                'unlock'   => "EXEC sp_releaseapplock @Resource = :lockName, @LockOwner = 'Session'",
                'isLocked' => "SELECT COUNT(*) FROM sys.dm_tran_locks WHERE request_mode = 'X' AND resource_description = :lockName",
                default    => null,
            },
            'oci', 'oracle' => match ($action) {
                'lock'     => "DECLARE v_result NUMBER; BEGIN DBMS_LOCK.REQUEST(:lockName, 6, :waitTimeout, TRUE, v_result); END;",
                'unlock'   => "DECLARE v_result NUMBER; BEGIN DBMS_LOCK.RELEASE(:lockName); END;",
                'isLocked' => "SELECT COUNT(*) FROM V\$LOCK WHERE ID1 = DBMS_LOCK.ALLOCATE_UNIQUE(:lockName) AND REQUEST = 6",
                default    => null,
            },
            default => throw new DatabaseException(
                "Database driver '{$driver}' does not support locks.",
                DatabaseException::INVALID_ARGUMENTS
            )
        };

        if($query === null){
            throw new DatabaseException(
                "Invalid {$driver} lock operation: {$action}",
                DatabaseException::INVALID_ARGUMENTS
            );
        }

        try {
            $stmt = $tbl->database()->prepare($query) ->bind(':lockName', $identifier);
            $tbl = null;

            if (
                $action === 'lock' && 
                in_array($driver, ['mysql', 'mysqli', 'cubrid', 'sqlsrv', 'mssql', 'dblib', 'oci', 'oracle'], true)
            ) {
                $stmt->bind(':waitTimeout', $timeout);
            }

            if (!$stmt->execute() || !$stmt->ok()) {
                return false;
            }

            if($action === 'isLocked' && ($row = $stmt->fetchNext()) !== false){
                return ($driver === 'sqlite') ? ($row->lockCount > 0) : (bool) $row->isLockDone;
            }
        } catch (Throwable $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }
        
        return false;
    }

    /**
     * Build sql query string to drop table.
     * 
     * @param bool $isTempTable Whether to drop temporary table (default false).
     * 
     * @return string Return SQL query string based on database type.
     */
    private function getDropTableSQL(bool $isTempTable = false): string
    {
        $tablePrefix = $isTempTable ? 'temp_' : '';
        $tableIdentifier = $isTempTable ? "#temp_{$this->tableName}" : $this->tableName;

        return match ($this->db->getDriver()) {
            'mysql', 'mysqli' => "DROP " . ($isTempTable ? "TEMPORARY " : "") . "TABLE IF EXISTS {$tablePrefix}{$this->tableName}",
            'dblib' => "DROP TABLE IF EXISTS {$tableIdentifier}",
            'sqlsrv' => "IF OBJECT_ID('{$tablePrefix}{$this->tableName}', 'U') IS NOT NULL DROP TABLE {$tablePrefix}{$this->tableName}",
            'oracle', 'oci' => "BEGIN EXECUTE IMMEDIATE 'DROP TABLE {$tablePrefix}{$this->tableName}'; EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;",
            default => "DROP TABLE IF EXISTS {$tablePrefix}{$this->tableName}"
        };
    }

    /**
     * Outputs debug information once per unique title.
     *
     * Useful for tracing structured data like bind parameters
     * or internal states during query building.
     *
     * @param mixed $input The value to dump (string or array).
     * @param string|null $title Optional label shown only once per title.
     *
     * @return void
     */
    private function echoDebug(mixed $input, ?string $title = null): void 
    {
        if($title && !isset($this->debugTitles[$title])){
            $this->debugTitles[$title] = 1;
            echo "\n{$title}\n\n";
        }

        if(is_array($input)){
            print_r($input);
            echo "\n";
            return;
        }

        echo "{$input}\n";
    }

    /**
     * Binds a value to the specified placeholder in the database query.
     *
     * If debug mode is enabled, the placeholder and value are logged once under the 'BIND PARAMS' label.
     *
     * @param string $placeholder The query placeholder (e.g., :id).
     * @param mixed  $value The value to bind.
     *
     * @return self Return instance of builder class.
     */
    private function setBindValue(string $placeholder, mixed $value): self 
    {
        $this->db->bind($placeholder, $value);

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug("$placeholder = $value", 'BIND PARAMS');
        }

        return $this;
    }

    /**
     * Execute insert query.
     * 
     * @param array<int,array<string,mixed>> $values array of values to insert.
     * @param string $type The type of insert (expected: `INSERT` or `REPLACE`)
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function executeInsertQuery(array $values, string $type): int 
    {
        $inserts = '';
        foreach ($values as $row) {
            $inserts .= "(" . self::getNormalizedValues($row) . "), ";
        }

        $columns = implode(', ', array_keys($values[0]));
        $inserts = rtrim($inserts, ', ');
        $ignore = $this->insertIgnoreDuplicate ? 'IGNORE ' : '';

        $sql = "{$type} {$ignore}INTO {$this->tableName} ({$columns}) VALUES {$inserts}";
        $sql .= $this->buildDuplicateUpdateClause();

        if($this->debugMode === self::DEBUG_BUILDER){
            $this->setDebugInformation($sql, strtolower($type));
            return 0;
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug($sql, 'SQL QUERY');
            return 0;
        }

        $this->db->query($sql);
        $response = $this->db->ok() ? $this->db->rowCount() : 0;
        $this->reset();

        return $response;
    }

    /**
     * Builds the `ON DUPLICATE KEY UPDATE` SQL clause from stored `onDuplicate()` values.
     *
     * @param array &$bindValues Reference to the binding values for prepared statements.
     * 
     * @return string Return the generated `ON DUPLICATE KEY UPDATE` clause.
     */
    private function buildDuplicateUpdateClause(array &$bindValues = []): string 
    {
        if ($this->getOptions('duplicate') === []) {
            return '';
        }

        $isPrepare = !empty($bindValues);
        $updates = [];

        foreach ($this->getOptions('duplicate') as $col => $option) {
            $operation = match ($option['operation']) {
                '+=' => '+', 
                '-=' => '-',
                default => '='
            };

            if ($option['value'] instanceof RawExpression) {
                $value = $option['value']->getExpression();
            } else {
                $upperValue = is_string($option['value']) ? trim(strtoupper($option['value'])) : '';

                $isColumn = $upperValue && str_starts_with($upperValue, 'VALUES(');
                $value = $isColumn ? $option['value'] : self::escape($option['value'], true);

                if (!$isColumn && $isPrepare) {
                    $bindValues["duplicate_{$col}"] = $option['value'];
                    $value = ":duplicate_{$col}";
                }
            }

            $updates[] = ($operation === '=')
                ? "{$col} = {$value}"
                : "{$col} = {$col} {$operation} {$value}";
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    /**
     * Execute insert query using prepared statement.
     * 
     * @param array<int,array<string,mixed>> $values array of values to insert.
     * @param string $type The insert type (expected: `INSERT` or `INSERT`).
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function executeInsertPrepared(array $values, string $type): int
    {
        $count = 0;
        self::$lastInsertId = null;
        
        $replacements = [];
        [$placeholders, $inserts] = self::mapInsertParams($values[0]);
        $ignore = $this->insertIgnoreDuplicate ? 'IGNORE ' : '';

        $sql = "{$type} {$ignore}INTO {$this->tableName} ({$inserts}) VALUES ($placeholders)";
        $sql .= $this->buildDuplicateUpdateClause($replacements);
       
        if($this->debugMode === self::DEBUG_BUILDER){
            $this->setDebugInformation($sql, strtolower($type), $values);
            return 0;
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug($sql, 'SQL QUERY');
        }

        $isDebug = $this->debugMode === self::DEBUG_BUILDER_DUMP;
        $useTransaction = !$isDebug && count($values) > 1;

        $this->db->prepare($sql);
        if($useTransaction){
            $useTransaction = $this->db->beginTransaction();
        }

        try {
            foreach ($values as $columns) {
                $this->bindStrictColumns($columns, $replacements);

                if($isDebug){
                    continue;
                }

                if($this->db->execute()){
                    $count++;
                }
            }
        } catch (Throwable $e) {
            if($useTransaction && $this->db->inTransaction()){
                $this->db->rollback();
            }

            throw $e; 
        }
      
        if($useTransaction && $this->db->inTransaction()){
            if($count > 0){
                $count = $this->db->commit() ? $count : 0;
            }else{
                $this->db->rollback();
            }
        }

        self::$lastInsertId = ($count > 0) ? $this->db->getLastInsertId() : null;
        $this->reset();
        return $count;
    }

    /**
     * Bind insert parameters to the prepared statement.
     *
     * @param array<string,mixed> $columns The column names and value.
     * @param array<string,mixed>  $replacements Optional insert replace values.
     * 
     * @return void
     */
    private function bindStrictColumns(array $columns, array $replacements = []): void
    {
        foreach ($columns as $column => $value) {
            if ($value instanceof RawExpression) {
                continue; 
            }

            if($column === '?' || is_int($column) || str_starts_with($column, ':')){
                throw new DatabaseException(
                    sprintf(
                        "Invalid column placeholder '%s'. Use valid table column names without positional ('?') or prefixed named (':') placeholders.",
                        $column
                    ),
                    DatabaseException::VALUE_FORBIDDEN
                );
            }

            $this->setBindValue(self::trimPlaceholder($column), self::escape($value, false, true));
        }

        foreach ($replacements as $placeholder => $replace) {
            $this->setBindValue($placeholder, $replace);
        }
    }

    /**
     * Build query conditions based on the specified type.
     *
     * @param string $query The SQL query string to which conditions passed by reference.
     * @param bool $isEmptyWhere Whether the where conditions should be added 
     *                          and if false treat it as AND (default: true).
     * 
     * @return bool Return true if has conditions, otherwise false.
     */
    private function buildConditions( string &$query, bool $isEmptyWhere = true): bool
    {
        if ($this->andConditions === []) {
            return false;
        }

        if ($isEmptyWhere) {
            $query .= ' WHERE ';
        }

        $firstCondition = true;
        $bindIndex = 0;

        foreach ($this->andConditions as $index => $condition) {
            if (!$isEmptyWhere || ($isEmptyWhere && !$firstCondition)) {
                $query .= (($condition['type'] ?? '') === 'OR') ? ' OR ' :  ' AND ';
            }

            $query .= match ($condition['function']) {
                'CONJOIN' => $this->buildGroupConditions(
                    $condition['conditions'], 
                    $index,
                    $condition['operator'],
                    $bindIndex
                ),
                'NESTED' => $this->buildGroupBindConditions(
                    $condition, 
                    $index,
                    $bindIndex
                ),
                default => $this->buildSingleConditions(
                    $condition, 
                    $index
                ),
            };

            $firstCondition = false;
        }

        return true;
    }

    /**
     * Bind query where conditions.
     * 
     * @return bool Return true if any bind params, false otherwise.
     */
    private function bindConditions(): bool 
    {
        $totalBinds = 0;

        foreach ($this->andConditions as $index => $bindings) {
            if($bindings['function'] !== 'INARRAY' && ($bindings['value'] instanceof RawExpression)){
                continue;
            }

            switch ($bindings['function']) {
                case 'AGAINST':
                    $totalBinds++;
                    $this->setBindValue(":match_column_{$index}", $bindings['value']);
                break;
                case 'CONJOIN':
                    $bindIndex = 0;
                    $this->bindGroupConditions($bindings['conditions'], $index, $bindIndex);
                    $totalBinds += $bindIndex;
                break;
                case 'NESTED':
                    // Reset index
                    $bindIndex = 0;
                    $this->bindGroupConditions($bindings['X'], $index, $bindIndex);
                    $this->bindGroupConditions($bindings['Y'], $index, $bindIndex);
                    $totalBinds += $bindIndex;
                break;
                case 'INARRAY':
                    $this->bindInConditions($bindings['value'], $bindings['column'], true, $totalBinds);
                break;
                case 'INSET':
                    // skip
                break;
                default:
                    $totalBinds++;
                    $this->setBindValue(self::trimPlaceholder($bindings['column']), $bindings['value']);
                break;
            }
        }

        foreach($this->getOptions('match') as $idx => $order){
            if($order['value'] instanceof RawExpression){
                continue;
            }

            $totalBinds++;
            $this->setBindValue(":match_order_{$idx}", $order['value']);
        }


        return $totalBinds > 0;
    }

    /**
     * Constructs a single condition query string with placeholders for binding values.
     *
     * @param array $condition An array representing the condition.
     * @param int $index The index to append to the placeholder names.
     * @param bool $addOperator Indicates whether is for to add AND OR operator (default: true).
     *          Constructs a single ANDs condition query string with placeholders for binding values.
     *
     * @return string Return query string representation of the single condition.
     */
    private function buildSingleConditions(
        array $condition, 
        int $index, 
        bool $addOperator = false
    ): string
    {
        $comparison = $condition['comparison'] ?? '=';
        $column = $condition['column'] ?? '';
        $value = $condition['value'] ?? null;
        $isRaw = ($value instanceof RawExpression);
        $logical = $addOperator ? (($condition['type'] === 'OR') ? ' OR ' : ' AND ') : '';

        $placeholder = !$isRaw
            ? (($condition['function'] === 'AGAINST') ? ":match_column_{$index}" : self::trimPlaceholder($column))
            : self::escape(value: $value ?? '', addSlashes: true);

        return match ($condition['function']) {
            'REGULAR' => "{$logical}{$column} {$comparison} {$placeholder}",
            'INARRAY' => "{$logical}{$column} {$comparison}(" . (
                !$isRaw
                    ? $this->bindInConditions($value ?? [], $column)
                    : self::getNormalizedValues($value ?? [])
            ) . ')',
            'AGAINST' => "{$logical}MATCH($column) AGAINST ({$placeholder} {$comparison})",
            'INSET' => self::buildInsetConditions($condition, $logical, $comparison),
            default => '',
        }; 
    }

    /**
     * Builds the `FIND_IN_SET` condition for the query.
     *
     * @param array $condition The condition array containing search, list, and operator details.
     * @param string $prefix The prefix to be applied before the clause (e.g., `AND`, `OR`).
     * @param string $operator The operator for comparison or position alias.
     * 
     * @return string Return the generated SQL string for find in set function.
     */
    private static function buildInsetConditions(
        array $condition, 
        string $prefix, 
        string $comparison
    ): string 
    {
        // Sanitize the search term to prevent SQL injection if is not column name
        $search = $condition['isSearchColumn'] ? $condition['search'] : self::escape($condition['search'], true);
        
        // Sanitize the list or assume it's a column
        $values = $condition['isList'] 
            ? self::escape(value: $condition['list'], enQuote: true, addSlashes: true) 
            : $condition['list'];

        $comparison = match($comparison) {
            'position' => 'AS inset_position',
            '>', 'exists' => '> 0',
            '=', 'first' => '= 1',
            'last' => "= (LENGTH({$values}) - LENGTH(REPLACE({$values}, ',', '')) + 1)",
            'none' => '= 0',
            'contains' => "LIKE '%{$search}%'",
            default => $comparison,   
        };
        
        return "{$prefix}FIND_IN_SET({$search}, {$values}) {$comparison}";
    }    

    /**
     * Bind custom placeholder params for join tables.
     * 
     * @return void
     */
    private function bindJoinPlaceholders(): void 
    {
        foreach($this->getOptions('binds') as $key => $value){
            if($value instanceof RawExpression){
               throw new DatabaseException(sprintf('Bind value cannot be instance of %s', RawExpression::class));
            }

            $this->setBindValue($key, $value);
        }
    }

    /**
     * Get array of option key values.
     * 
     * @param string The option key.
     * 
     * @return array Return an array.
     */
    private function getOptions(string $key): array 
    {
        return $this->options[$key] ?? [];
    }

    /**
     * Builds a query string representation of single grouped conditions.
     *
     * @param array|self[]   $conditions   An array of conditions to be grouped.
     * @param int     $index        The index to append to the placeholder names.
     * @param bool    $isBided      Indicates whether placeholders should be used for binding values (default: true).
     * @param string  $operator         The type of logical operator to use between conditions within the group (default: 'OR').
     * @param int     &$lastBindIndex        Reference to the total count of conditions processed so far across all groups.
     *
     * @return string Return query string representation of grouped conditions with placeholders.
     * 
     * @example - Example:
     * ```sql 
     * 'SELECT * FROM foo WHERE (bar = 1 AND baz = 2)'
     * ```
     * 
     * @example - Example: 
     * ```sql 
     * 'SELECT * FROM foo WHERE (boz = 1 OR bra = 2)'
     * ```
     */
    private function buildGroupConditions(
        array $conditions, 
        int $index,   
        string $operator = 'OR', 
        int &$bindIndex = 0
    ): string
    {
        $group = '';
        foreach ($conditions as $idx => $condition) {
            $column = key($condition);
            $value = $condition[$column]['value'];
            $comparison = strtoupper($condition[$column]['comparison'] ?? $condition[$column]['operator'] ?? '=');

            if($value instanceof RawExpression){
                $placeholder = $value->getExpression();
            }else{
                $placeholder = self::trimPlaceholder("{$column}_{$index}_" . ($idx + $bindIndex));
                $bindIndex++;
            }

            if ($idx > 0) {
                $group .= " {$operator} ";
            }

            if(str_ends_with($comparison, 'IN')){
                $placeholder = '(' . $this->bindInConditions($value, $column) . ')';
            }

            $group .= "{$column} {$comparison} {$placeholder}";
        }

        return "({$group})";
    }

    /**
     * Builds a query string representation of multiple group conditions.
     *
     * @param array $condition An array of conditions for group binding.
     * @param int $index The index to append to the placeholder names.
     *
     * @return string Return a query string representation of grouped conditions with placeholders.
     * 
     * @example - Example: 
     * 
     * ```sql 
     * 'SELECT * FROM foo WHERE ((bar = 1 AND baz = 2) AND (boz = 1 AND bra = 5))'
     * ```
     * @example - Example: 
     * 
     * ```sql 
     * 'SELECT * FROM foo WHERE ((bar = 1 OR baz = 2) OR (boz = 1 OR bra = 5))'
     * ```
     */
    private function buildGroupBindConditions(array $condition, int $index, int &$bindIndex = 0): string
    {
        $nestedIndex = 0;
        $sql = '(';
        $sql .= $this->buildGroupConditions($condition['X'], $index, $condition['operator'], $nestedIndex);
        $sql .= ' ' . ($condition['bind'] ?? 'OR') . ' ';
        $sql .= $this->buildGroupConditions($condition['Y'], $index, $condition['operator'], $nestedIndex);
        $sql .= ')';

        $bindIndex += $nestedIndex;

        return $sql;
    }

    /**
     * Bind query in conditions.
     * 
     * @param array  $values  The column array values.
     * @param string $column  The column placeholder names.
     * @param bool $handle Whether to handle or return placeholders.
     * @param int $bindings Reference to Number of bind parameters.
     * 
     * @return string
     */
    private function bindInConditions(
        array $values, 
        string $column,
        bool $handle = false,
        int &$bindings = 0
    ): string 
    {
        $placeholders = '';
        foreach ($values as $idx => $value) {
            if($value instanceof RawExpression){
                $placeholders .= "{$value->getExpression()}, ";
            }else{
                $placeholder = self::trimPlaceholder("{$column}_in_{$idx}");

                if($handle){
                    $this->setBindValue($placeholder, $value);
                    $bindings++;
                }else{
                    $placeholders .= "{$placeholder}, ";
                }
            }
        }

        return trim($placeholders, ', ');
    }

    /**
     * Bind group conditions to the database handler.
     *
     * @param array $bindings An array of conditions to bind.
     * @param int $index The index to append to the placeholder names.
     * @param int &$bindIndex A reference to the last counter used to ensure unique placeholder names.
     *
     * @return void
     */
    private function bindGroupConditions(
        array $bindings, 
        int $index, 
        int &$bindIndex = 0
    ): void 
    {
        foreach ($bindings as $idx => $bind) {
            $column = key($bind);
            $value = $bind[$column]['value'];

            if($value instanceof RawExpression){
                continue;
            }

            $comparison = strtoupper($bind[$column]['comparison'] ?? $bind[$column]['operator'] ?? '');

            if(str_ends_with($comparison, 'IN')){
                $this->bindInConditions($value, $column, true);
            }else{
                $this->setBindValue(
                    self::trimPlaceholder("{$column}_{$index}_" . ($idx + $bindIndex)), 
                    is_array($value) ? self::getNormalizedValues($value, true) : $value
                );
                $bindIndex++;
            }
        }
    }

    /**
     * Print the MySQL query string for debugging purposes.
     * 
     * If this method is invoked in a production environment, 
     * the query string will be logged using the `debug` level along with the calling method,
     * and the method will return false.
     * 
     * @param string $query The MySQL query string to print.
     * @param string $method The name of the calling method.
     * @param array $values Optional values.
     * 
     * @return array|bool Returns false on production, otherwise return query array.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function setDebugInformation(string $query, string $method, array $values = []): bool
    {
        $params = [];
        if($method === 'insert' || $method === 'replace'){
            foreach($values as $idx => $bindings){
                foreach($bindings as $column => $value){
                    $params[$idx][$column] = self::escape($value);
                }
            }
        }else{
            if (isset($this->whereCondition['placeholder'])) {
                $params[$this->whereCondition['placeholder']] = self::escape($this->whereCondition['value']);
            }

            if($method === 'update'){
                foreach($values as $column => $value){
                    $params[$column] = self::escape($value);
                }
            }


            foreach ($this->andConditions as $index => $bindings) {
                switch ($bindings['function']) {
                    case 'AGAINST':
                        $params[":match_column_{$index}"] = self::escape($bindings['value']);
                    break;
                    case 'CONJOIN':
                        self::bindDebugGroupConditions($bindings['conditions'], $index, $params);
                    break;
                    case 'NESTED':
                        $bindIndex = 0;
                        self::bindDebugGroupConditions($bindings['X'], $index, $params, $bindIndex);
                        self::bindDebugGroupConditions($bindings['Y'], $index, $params, $bindIndex);
                    break;
                    case 'INARRAY':
                        foreach ($bindings['value'] as $idx => $value) {
                            $placeholder = self::trimPlaceholder("{$bindings['column']}_in_{$idx}");
                            $params[$placeholder] = is_array($value) ? self::getNormalizedValues($value, true) : $value;
                        }
                    break;
                    default: 
                        $params[self::trimPlaceholder($bindings['column'])] = self::escape($bindings['value']);
                    break;
                }
            }
        }
        
        $this->reset();
        $this->debugInformation = [
            'method' => $method,
            'query' => [
                'placeholder' => $query,
                'positional' => preg_replace('/:([a-zA-Z0-9_]+)/', '?', $query),
            ],
            'binding' => $params
        ];

        if (PRODUCTION) {
            Logger::debug(json_encode($this->debugInformation, JSON_PRETTY_PRINT));
            return false;
        }

        return false;
    }

    /**
     * Orders the query based on the MATCH columns and mode.
     * 
     * @param string &$sql The SQL query string passed by reference.
     * @param bool $isOrdered Whether the query has been ordered.
     * 
     * @return void
     */
    private function setMatchAgainst(string &$sql, bool $isOrdered = false): void 
    {
        if($this->getOptions('match') === []){
            return;
        }

        $match = $isOrdered ? ' , ' : ' ORDER BY';
        foreach($this->getOptions('match') as $idx => $order){
            $value = ($order['value'] instanceof RawExpression) 
                ? self::escape(value: $order['value'], addSlashes: true)
                : ":order_match_{$idx}";

            $match .= "MATCH({$order['column']}) AGAINST ({$value} {$order['mode']}) {$order['order']}, ";
        }

        $sql .= rtrim($match, ', ');
    }

    /**
     * Appends HAVING conditions to the SQL query.
     * 
     * This method processes the stored filter conditions and constructs a HAVING clause, 
     * ensuring that expressions are properly formatted. If no filters are defined, the method exits early.
     * 
     * @param string &$sql The SQL query string to append the HAVING conditions.
     */
    private function setHavingConditions(string &$sql): void 
    {
        if($this->getOptions('filters') === []){
            return;
        }

        $having = ' HAVING';
        foreach($this->getOptions('filters') as $idx => $condition){
            $expression = $condition['expression'];

            if($expression instanceof RawExpression){
                $expression = $expression->getExpression();
            }

            $value = self::escape($condition['value'], true);
            $operator = ($idx > 0) ? ($condition['operator'] ?? 'AND') . ' ' : '';

            $having .= "{$operator}{$expression} {$condition['comparison']}) {$value} ";
            
        }

        $sql .= rtrim($having, ', ');
    }

    /**
     * Binds conditions for debugging purposes in a group.
     * 
     * @param array $bindings The array of bindings.
     * @param int $index The index.
     * @param array &$params The array to store the debug parameters.
     * @param int &$last The last index.
     * 
     * @return void
     */
    private static function bindDebugGroupConditions(
        array $bindings, 
        int $index, 
        array &$params = [], 
        int &$bindIndex = 0
    ): void 
    {
        foreach ($bindings as $idx => $bind) {
            $column = key($bind);
            $placeholder = self::trimPlaceholder("{$column}_{$index}_" . ($idx + $bindIndex));
   
            $params[$placeholder] = self::escape($bind[$column]['value']);
            $bindIndex++;
        }
    }

   /**
     * New cache instance.
     * 
     * @param string|null $storage Optional storage name for the cache.
     * @param string|null $subfolder Optional file-based caching subfolder.
     * @param string|null $persistentId Optional memory-based caching unique persistent connection ID.
     * 
     * @return BaseCache Return instance of cache class.
     */
    private function newCache(
        ?string $storage = null, 
        ?string $subfolder = null,
        ?string $persistentId = null
    ): BaseCache
    {
        $cache = (self::$cacheDriver === 'memcached') 
            ? MemoryCache::getInstance(null, $persistentId ?? '__database_builder__')
            : FileCache::getInstance(null)
                ->setFolder('database' . ($subfolder ? DIRECTORY_SEPARATOR . trim($subfolder, TRIM_DS) : ''));

        return $cache->setStorage('database_' . ($storage ?? $this->tableName ?? 'capture'));
    }

    /**
     * Extracts and converts a column expression into a safe placeholder format.
     *
     * This method trims extra characters (e.g., spaces, colons), and if the input contains a function call
     * like `COUNT(column)`, it extracts just the `column` part.
     *
     * Examples:
     * - " COUNT( column ) " → ":column"
     * - "table.column" → ":table_column"
     * - ": column" → ":column"
     *
     * @param string|null $input The column name or function expression.
     * 
     * @return string Return the formatted placeholder.
     */
    private static function trimPlaceholder(string|null $input): string 
    {
        if (!$input) {
            return '';
        }

        if (preg_match('/\(([^)]+)\)/', $input, $matches)) {
            $input = $matches[1];
        }

        $input = trim($input, " :\t\n\r\0\x0B");
    
        return ':' . (str_contains($input, '.') ? str_replace('.', '_', $input) : $input);
    }
    
    /**
     * Build insert params.
     * 
     * @var array<string,mixed> $values Array of columns and values.
     * 
     * @return array<int,string> Array of insert params and placeholders.
     */
    private static function mapInsertParams(array $values): array 
    {
        $placeholders = '';
        $inserts = '';
        foreach($values as $col => $value){
            $placeholders .= ($value instanceof RawExpression) ? $value->getExpression() . ', ' : ":$col, ";
            $inserts .= "$col, ";
        }

        return [rtrim($placeholders, ', '), rtrim($inserts, ', ')];
    }

    /**
     * Convert array keys to placeholders key = :key for update table.
     * 
     * @param array $columns The columns.
     * @param bool $implode should implode or just return the array.
     * 
     * @return array|string Return array or string.
     */
    private static function buildPlaceholder(array $columns, bool $implode = false): array|string
    {
        $updateColumns = [];
        foreach ($columns as $column => $value) {
            if($value instanceof RawExpression){
                $updateColumns[] = "{$column} = {$value->getExpression()}";
            }else{
                $updateColumns[] = "{$column} = " . self::trimPlaceholder($column);
            }
        }

        return $implode ? implode(', ', $updateColumns) : $updateColumns;
    }

    /**
     * Prepare quoted values from an array of columns.
     *
     * @param array $columns The array of columns to be quoted.
     * @param bool $enQuote Whether to wrap the result in quotes (except for JSON).
     * @param string $return The return type, can be 'array' or 'string'.
     * 
     * @return array|string An array of quoted values or a string of quoted values.
     * @throws JsonException If an error occurs while encoding values.
     */
    private static function getNormalizedValues(
        array $columns, 
        bool $enQuote = true, 
        string $return = 'string'
    ): array|string
    {
        $quoted = [];
        $string = '';

        foreach ($columns as $item) {
            $value = self::escape($item, $enQuote);

            if($return === 'string'){
                $string .= "{$value}, ";
            }else{
                $quoted[] = $value;
            }
        }

        if($return === 'string'){
            return rtrim($string, ', ');
        }

        return $quoted;
    }
}