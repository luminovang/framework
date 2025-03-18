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
use \Luminova\Cache\FileCache;
use \Luminova\Base\BaseCache;
use \Luminova\Cache\MemoryCache;
use \Luminova\Database\Connection;
use \Luminova\Database\Manager;
use \Luminova\Logger\Logger;
use \Luminova\Utils\LazyObject;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\CacheException;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Exceptions\InvalidArgumentException;
use \DateTimeInterface;
use \Exception;
use \JsonException;

final class Builder implements LazyInterface
{  
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
     * Database connection driver instance.
     *
     * @var Connection<LazyInterface>|null $conn
     */
    private static ?LazyInterface $conn = null;

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
     * Table query max limit for update and delete methods.
     * 
     * @var int $maxLimit 
     */
    private int $maxLimit = 0;

    /**
     * Table query order rows.
     * 
     * @var array<int,string> $queryOrder 
     */
    private array $queryOrder = [];

    /**
     * Table query match against order rows.
     * 
     * @var array<int,string> $queryMatchOrder 
     */
    private array $queryMatchOrder = [];

    /**
     * Table query group column by.
     * 
     * @var array<int,string> $queryGroup 
     */
    private array $queryGroup = [];

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
     * Match against modes.
     * 
     * @var array<string,mixed>  $matchModes
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
     * Caching status flag.
     * 
     * @var bool $caching 
     */
    private bool $caching = true;

    /**
     * is cache method is called for current query.
     * 
     * @var bool $queryWithCache 
     */
    private bool $queryWithCache = false;

    /**
     * Print query string.
     * 
     * @var bool $printQuery 
     */
    private bool $printQuery = false;

    /**
     * The debug query.
     * 
     * @var array<string,mixed> $debugQuery 
     */
    private array $debugQuery = [];

    /**
     * Result return type.
     * 
     * @var string $resultType 
     */
    private string $resultType = 'object';

    /**
     * Cache key.
     * 
     * @var string $cacheKey 
     */
    private string $cacheKey = "default";

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
     * Bind values.
     * 
     * @var array $bindValues 
     */
    private array $bindValues = [];

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
    private string $cacheDriver = '';

    /**
     * The last inserted Id.
     * 
     * @var mixed $lastInsertId
     */
    private static mixed $lastInsertId = null;
    /**
     * Private constructor prevents instantiation from outside.
     * 
     * @param string|null $table Optional table name (non-empty string).
     * @param string|null $alias Optional table alias (default: null).
     */
    private function __construct(?string $table = null, ?string $alias = null)
    {
        if($table === ''){
            throw new InvalidArgumentException('Invalid table argument, $table argument expected non-empty string.');
        }

        self::$conn ??= LazyObject::newObject(fn() => Connection::getInstance());
        $this->cacheDriver = env('database.caching.driver', 'filesystem');
        $this->tableName = $table ?? '';
        $this->tableAlias = $alias ? "AS {$alias}" : '';
    }

    /**
     * Initializes a new instance of the Builder class.
     *
     * @param string|null $table The name of the table to initialize the builder with.
     * @param string|null $alias The alias for the table.
     *
     * @return self A new instance of the Builder class.
     *
     * @throws Exception If an error occurs during initialization.
     */
    private static function initializer(?string $table = null, ?string $alias = null): Builder
    {
        if(self::$instance === null){
            return new self($table, $alias);
        }

        $extend = new self($table, $alias);
        $extend->caching = self::$instance->caching;
        $extend->resultType = self::$instance->resultType;

        return $extend;
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
     * Class shared singleton class instance.
     * 
     * @param string|null $table Optional table name (non-empty string).
     * @param string|null $alias Optional table alias (default: null).
     * 
     * @return Builder Return new static instance of builder class.
     * @throws DatabaseException Throws if the database connection fails.
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

        throw new DatabaseException('Database connection error.');
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
     * Builds a custom SQL query string for execution.
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
     * @example Executing a raw query:
     * ```php
     * $builder = Builder::query("SELECT * FROM users WHERE id = :user_id");
     * $result = $builder->execute(['user_id' => 1]);
     * ```
     * @see execute()
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
     * @example Basic join usage:
     * ```php
     * Builder::table('product', 'p')->join('users', 'LEFT', 'u');
     * ```
     * @example Joining without an alias:
     * ```php
     * Builder::table('users')->join('orders', 'INNER');
     * ```
     */
    public function join(string $table, string $type = 'INNER', ?string $alias = null): self
    {
        if(!$table || !$type){
            throw new InvalidArgumentException('Invalid join argument, $table or $type argument expected non-empty string.');
        }

        $this->tableJoin[$table] = [
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
     * @param string $operator The comparison operator (default: `=`).
     * @param mixed  $value The value to compare or another table column.
     *                      - String literals must be wrapped in quotes.
     *                      - Unquoted values are treated as column names.
     *
     * @return self Returns the instance of builder class.
     *
     * @example Using `on()` for table joins:
     * ```php
     * $builder->leftJoin('users', 'u')
     *     ->on('u.user_id', '=', 'r.role_user_id') // Column comparison
     *     ->on('u.user_group', '=', 1)             // Value comparison
     *     ->on('u.user_name', '=', '"peter"');     // String literal (quoted)
     * ```
     *
     * @see join()
     * @see leftJoin()
     * @see rightJoin()
     * @see innerJoin()
     * @see crossJoin()
     * @see fullJoin()
     *
     * > **Note:** When using multiple joins in one query, always call `on()` immediately after each `join()`.
     */
    public function on(string $condition, string $operator, mixed $value): self
    {
        $this->joinConditions[array_key_last($this->tableJoin)][] = "{$condition} {$operator} {$value}";
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
     * This will generate: `LIMIT 5,10`
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
     *  ->where('country', '=','NG')
     *  ->max(50)
     *  ->update(['is_local' => 1]);
     * ```
     * This ensures the query affects at most 50 rows.
     */
    public function max(int $limit): self
    {
        $this->maxLimit = max(0, $limit);

        return $this;
    }

    /**
     * Sets the sorting order for the query results.
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
     *
     * @example - Ordering results:
     * ```php
     * $builder->order('created_at', 'DESC');
     * ```
     * This will generate: `ORDER BY created_at DESC`
     */
    public function order(string $column, string $order = 'ASC'): self 
    {
        $this->queryOrder[] = "{$column} {$order}";

        return $this;
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
    public function orderByMatch(
        array $columns, 
        string|int|float $value, 
        string $mode = 'NATURAL_LANGUAGE', 
        string $order = 'ASC'
    ): self 
    {
        $this->queryMatchOrder[] = [
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
     * ```php
     * $builder->group('category_id');
     * ```
     * This will generate: `GROUP BY category_id`
     */
    public function group(string $group): self 
    {
        $this->queryGroup[] = $group;

        return $this;
    }

    /**
     * Adds a `WHERE` condition to the query.
     *
     * This method sets a conditional clause where the specified column 
     * must satisfy the given comparison operator and value.
     *
     * @param string $column The name of the column to filter by.
     * @param string $operator The comparison operator (e.g., `=`, `>=`, `<>`, `LIKE`).
     * @param mixed $value The value to compare against.
     * 
     * @return self Return instance of builder class.
     *
     * @example - Using the `WHERE` conditioning:
     * ```php
     * Builder::table('users')->where('status', '=', 'active');
     * ```
     * This will generate: `WHERE status = 'active'`
     */
    public function where(string $column, string $operator, mixed $value): self
    {
        $placeholder = self::trimPlaceholder($column);

        $this->whereCondition = [
            'type' => 'WHERE', 
            'query' => " WHERE {$column} {$operator} {$placeholder}",
            'value' => $value,
            'column' => $column,
            'placeholder' => $placeholder
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
     * @param string $operator The comparison operator (e.g., `=`, `>=`, `<>`, `LIKE`).
     * @param mixed $value The value to compare against.
     * 
     * @return self Return instance of builder class.
     *
     * @example - Using the `AND` conditioning:
     * ```php
     * Builder::table('users')->where('status', '=', 'active')
     *         ->and('role', '=', 'admin');
     * ```
     * This will generate: `WHERE status = 'active' AND role = 'admin'`
     */
    public function and(string $column, string $operator, mixed $value): self
    {
        $this->andConditions[] = [
            'type' => 'AND', 
            'column' => $column, 
            'value' => $value,
            'operator' => $operator
        ];

        return $this;
    }

    /**
     * Add a condition to the query using the `OR` operator.
     * 
     * This method appends a conditional clause where the specified column 
     * must satisfy the given comparison operator and value.
     * 
     * @param string $column The name of the column to apply the condition.
     * @param string $operator The comparison operator to use (e.g., `=`, `>=`, `<>`, `LIKE`).
     * @param mixed $value The value to compare the column against.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Using the `OR` conditioning:
     * ```php
     * $builder->or('status', '=', 'active')
     *         ->or('role', '!=', 'admin');
     * ```
     * This will generate: `WHERE status = 'active' OR role != 'admin'`
     */
    public function or(string $column, string $operator, mixed $value): self
    {
        $this->andConditions[] = [
            'type' => 'OR', 
            'column' => $column, 
            'value' => $value,
            'operator' => $operator
        ];
        return $this;
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
        $this->andConditions[] = [
            'type' => 'AGAINST', 
            'column' => implode(", ", $columns), 
            'value' => $value,
            'operator' => self::$matchModes[$mode] ?? $mode
        ];

        return $this;
    }

    /**
     * Set columns and values to be updated in the query.
     * 
     * This method should be invoked before the `update()` method to specify which columns to update.
     *
     * @param string $column The name of the column to update.
     * @param mixed $value The value to set for the column.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Setting update columns and values:
     * ```php
     * $builder->set('status', 'active')
     *         ->set('updated_at', time());
     * ```
     * This will generate: `UPDATE table SET status = 'active', updated_at = 1699999999`
     */
    public function set(string $column, mixed $value): self
    {
        $this->querySetValues[$column] = $value;

        return $this;
    }

    /**
     * Groups multiple conditions using the `OR` operator and adds them to the query.
     *
     * This method creates a logical group where conditions are combined with `OR`, 
     * ensuring that at least one of them must be met.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The list of conditions to be grouped using `OR`.
     *
     * @return self Return instance of builder class.
     *
     * @example Using `orGroup()` to structure a query:
     * ```php
     * $builder->orGroup([
     *     ['column1' => ['operator' => '=', 'value' => 1]],
     *     ['column2' => ['operator' => '=', 'value' => 2]]
     * ]);
     * ```
     * This will generate: `WHERE (column1 = 1 OR column2 = 2)`
     */
    public function orGroup(array $conditions): self
    {
        $this->andConditions[] = [
            'type' => 'GROUP_OR',
            'conditions' => $conditions
        ];

        return $this;
    }

    /**
     * Groups multiple conditions using the `AND` operator and adds them to the query.
     *
     * This method creates a logical group where all conditions must be met simultaneously.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The list of conditions to be grouped using `AND`.
     *
     * @return self Return instance of builder class.
     *
     * @example Using `andGroup()` to structure a query:
     * ```php
     * $builder->andGroup([
     *     ['column1' => ['operator' => '=', 'value' => 1]],
     *     ['column2' => ['operator' => '=', 'value' => 2]]
     * ]);
     * ```
     * This will generate: `WHERE (column1 = 1 AND column2 = 2)`
     */
    public function andGroup(array $conditions): self
    {
        $this->andConditions[] = [
            'type' => 'GROUP_AND',
            'conditions' => $conditions
        ];

        return $this;
    }

    /**
     * Combines two condition groups using the `OR` operator and wraps them in a single condition group.
     * 
     * This method allows you to group conditions using logical `OR` and then bind the entire group 
     * to the query using either `AND` or `OR`.
     * 
     * @param array<int,array<string,array<string,mixed>>> $group1 First group of conditions.
     * @param array<int,array<string,array<string,mixed>>> $group2 Second group of conditions.
     * @param string $bind The logical operator to bind both groups (default: 'OR').
     *       - `AND` - Groups are combined with AND (e.g., WHERE ((a OR b) AND (c OR d))).
     *       - `OR`  - Groups are combined with OR (e.g., WHERE ((a OR b) OR (c OR d))).
     * 
     * @return self Return instance of builder class.
     * @example - Generating a query with `OR` conditions:
     * ```php
     * Builder::table('fooTable')
     * ->orBind([
     *      ['foo' => ['operator' => '=', 'value' => 1]],
     *      ['bar' => ['operator' => '=', 'value' => 2]]
     * ],
     * [
     *      ['baz' => ['operator' => '=', 'value' => 3]],
     *      ['bra' => ['operator' => '=', 'value' => 4]]
     * ]);
     * ```
     * This will generate: `WHERE ((foo = 1 OR bar = 2) OR (baz = 3 AND bra = 4))`
     */
    public function orBind(array $group1, array $group2, string $bind = 'OR'): self
    {
        $this->andConditions[] = [
            'type' => 'BIND_OR',
            'bind' => $bind,
            'X' => $group1,
            'Y' => $group2
        ];

        return $this;
    }

    /**
     * Combines two condition groups using the `AND` operator and wraps them in a single condition group.
     * 
     * This method allows you to group conditions using logical `AND` and then bind the entire group 
     * to the query using either `AND` or `OR`.
     * 
     * @param array<int,array<string,array<string,mixed>>> $group1 First group of conditions.
     * @param array<int,array<string,array<string,mixed>>> $group2 Second group of conditions.
     * @param string $bind The type of logical operator to use in binding groups (default: 'AND').
     *      - `AND` - Groups are combined with AND (e.g., WHERE ((a AND b) AND (c AND d))).
     *      - `OR`  - Groups are combined with OR (e.g., WHERE ((a AND b) OR (c AND d))).
     * 
     * @return self Return instance of builder class.
     * @example - Generating a query with `AND` conditions:
     * ```php
     * Builder::table('fooTable')
     * ->andBind([
     *      ['foo' => ['operator' => '=', 'value' => 1]],
     *      ['bar' => ['operator' => '=', 'value' => 2]]
     * ],
     * [
     *      ['baz' => ['operator' => '=', 'value' => 3]],
     *      ['bra' => ['operator' => '=', 'value' => 4]]
     * ]);
     * ```
     * This will generate: `WHERE ((foo = 1 AND bar = 2) AND (baz = 3 AND bra = 4))`
     */
    public function andBind(array $group1, array $group2, string $bind = 'AND'): self
    {
        $this->andConditions[] = [
            'type' => 'BIND_AND',
            'bind' => $bind,
            'X' => $group1,
            'Y' => $group2
        ];

        return $this;
    }

    /**
     * Set query to search using `IN ()` expression.
     * 
     * @param string $column The column name.
     * @param array<int,mixed> $list The expression values.
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If values is not provided.
     * @throws JsonException If an error occurs while encoding values.
     */
    public function in(string $column, array $list): self
    {
        if($list === []){
            throw new InvalidArgumentException('Invalid argument $list, expected non-empty array list.');
        }

        $this->andConditions[] = [
            'type' => 'IN', 
            'column' => $column, 
            'values' => $list
        ];

        return $this;
    }

    /**
     * Add a `FIND_IN_SET` condition to the query.
     *
     * @param string $search The search value or column name depending on `$isSearchColumn`.
     * @param string $operator The operator for matching (e.g., `exists`, `first`, `>= foo`, `<= bar`).
     * @param array<int,mixed>|string $list The comma-separated values or a column name containing the list.
     * @param bool $isSearchColumn Whether the `$search` argument is a column name (default: false).
     * 
     * @return self Returns the instance of the builder class.
     * @throws InvalidArgumentException Throws if list value is not empty.
     * 
     * Default Operators:
     * 
     * - `exists|>` - Check if exists or match any in the list.
     * - `first|=` - Check if it's the first in the list.
     * - `last` - Check if it's the first in the list.
     * - `position` - Position in the list (as inset_position).
     * - `contains` - Check if it contains the search term (uses the `$search` as the search value).
     * - `none` - No match in the list.
     * 
     * @example - Usage Examples:
     * 
     * **Using the `custom` Operator:**
     * ```php
     * Builder::table('fruits')->inset('banana', '= 2', ['apple','banana','orange']);
     * ```
     * **Using the `exists` Operator with a column:**
     * ```php
     * Builder::table('employees')->inset('PHP', 'exists', 'column_language_skills');
     * ```
     * 
     * **Using the `exists` Operator with a search column:**
     * ```php
     * Builder::table('employees')->inset('department', 'exists', 'HR,Finance,Marketing', true);
     * ```
     */
    public function inset(
        string $search, 
        string $operator, 
        array|string $list,
        bool $isSearchColumn = false
    ): self
    {
        if($list === [] || $list === ''){
            throw new InvalidArgumentException('Invalid argument $list, expected non-empty array or string.');
        }

        $isList = is_array($list);
        $listString = $isList ? implode(',', $list) : $list;

        $this->andConditions[] = [
            'type' => 'IN_SET', 
            'list' => $listString, 
            'isList' => $isList,
            'search' => $search, 
            'isSearchColumn' => $isSearchColumn,
            'operator' => $operator,
        ];

        return $this;
    }

    /**
     * Set result return type to an `object` or `array` (default: `object`).
     * 
     * This method changes the result return type to either `object` or `array`, 
     * this method should be called before `fetch`, `find` or `select` method.
     * 
     * @param string $type Return type (e.g, 'object' or 'array').
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException Throws if an invalid type is provided.
     */
    public function returns(string $type): self
    {
        $type = strtolower($type);

        if($type === self::RETURN_OBJECT || $type === self::RETURN_ARRAY){
            $this->resultType = $type;
            
            return $this;
        }

        throw new InvalidArgumentException(sprintf('Invalid return type: "%s", expected "array" or "object".', $type));
    }

    /**
     * Enable query string debugging, the read and update methods will return false.
     * 
     * If this method is invoked in a production environment, 
     * the query string will be logged using the `debug` level, 
     * 
     * @return self Return instance of builder class.
     */
    public function debug(): self 
    {
        $this->debugQuery = [];
        $this->printQuery = true;
        
        return $this;
    }

    /**
     * Get the debug query information.
     * 
     * @return array<string,mixed> Return array containing query information.
     */
    public function printDebug(): array 
    {
        return $this->debugQuery;
    }

    /**
     * Get date/time format for storing SQL.
     *
     * @param string $format Format to return default is `datetime`.
     *          Available time formats.
     *              - time     - Return time format from timestamp
     *              - datetime - Return SQL datetime format
     *              - date     - Return SQL date format.
     * @param null|int $timestamp Optional timestamp
     *
     * @return string Return Formatted date/time/timestamp.
     */
    public static function datetime(string $format = 'datetime', ?int $timestamp = null): string
    {
        $format = ($format === 'time') 
            ? 'H:i:s' 
            : (($format === 'date') ? 'Y-m-d' : 'Y-m-d H:i:s');

        return ($timestamp === null) 
            ? Time::now()->format($format)
            : Time::fromTimestamp($timestamp)->format($format);
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
        return $this->insertOrReplace($values, 'INSERT', $usePrepare);
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
        return $this->insertOrReplace($values, 'REPLACE', $prepare);
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
     *  ->where('id', '=', 1)
     *  ->copy(['id', 'email', 'created_at'])
     *  ->to('backup_users', ['id', 'email', 'created_at']);
     * ```
     */
    public function copy(array $columns): self
    {
        $this->copySelections = $this->createQueryExecution(
            '', 'select', $columns, 'all', self::MODE_COPY
        );
        return $this;
    }

    /**
     * Executes the selected columns from `copy` method and insert into a target table.
     *
     * This method must be used after calling `copy()`, as it executes the previously prepared 
     * `selection query and inserts the results into the specified target table.
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
        $sqlQuery = "INSERT INTO {$targetTable} ({$inserts}) {$this->copySelections}";

        if($this->printQuery){
            return $this->printDebugQuery($sqlQuery, 'copy') ? 1 : 0;
        }

        $result = self::database()->query($sqlQuery)->ok() 
            ? self::database()->rowCount() 
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
     * @example Executing a prepared query:
     * ```php
     * $query = Builder::query("SELECT * FROM users WHERE user_id = :id");
     * $result = $query->execute(['id' => 1]);
     * 
     * // Fetching a single row:
     * $user = $query->execute(['id' => 1], RETURN_NEXT);
     * ```
     */
    public function execute(?array $placeholder = null, int $mode = RETURN_ALL): mixed 
    {
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

        if($this->buildQuery === ''){
            throw new DatabaseException(
                'Execute operation without a query condition is not allowed. call query() before execute()', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        $this->bindValues = $placeholder ?? [];

        try {
            $response = $this->returnExecute($this->buildQuery, $mode);

            if($mode !== RETURN_STMT && $this->queryWithCache && (self::$cache instanceof BaseCache)){
                self::$cache->set($this->cacheKey, $response);
                self::$cache = null;
                $this->queryWithCache = false;
            }

            return $response;
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Calculate the total number of records table,
     * 
     * @param string $column The column to index calculation (default: *).
     * 
     * @return int|bool Return total number of records in table, otherwise false if execution failed.
     * @throws DatabaseException If an error occurs.
     */
    public function total(string $column = '*'): int|bool 
    {
        return $this->createQueryExecution("SELECT COUNT({$column})");
    }

    /**
     * Determine of a records exists in table.
     * 
     * @return bool Return true if records exists in table, otherwise false.
     * @throws DatabaseException If an error occurs.
     * 
     * @example Check if users in country Nigeria exists in table:
     * 
     * ```php
     * $bool = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->has();
     * ```
     */
    public function has(): bool 
    {
        return $this->createQueryExecution("SELECT COUNT(*)") > 0;
    }

    /**
     * Determine if a database table exists.
     * 
     * @return bool Return true if table exists in database, otherwise false.
     * @throws DatabaseException If an error occurs.
     * 
     * @example Check if user table exists in database:
     * 
     * ```php
     * $bool = Builder::table('users')->exists();
     * ```
     */
    public function exists(): bool 
    {
        $driver = self::database()->getDriver();
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

        $stmt = self::database()
            ->prepare("SELECT COUNT(*) FROM {$query}")
            ->bind('tableName', $this->tableName);
        
        return (bool) $stmt->execute() && $stmt->getCount();
    }

    /**
     * Calculate the total sum of a numeric column in the table.
     * 
     * @param string $column The column to calculate the sum.
     * 
     * @return int|float|bool Return total sum columns, otherwise false if execution failed.
     * @throws DatabaseException If an error occurs.
     */
    public function sum(string $column): int|float|bool
    {
        return $this->createQueryExecution("SELECT SUM({$column}) AS totalCalc", 'sum');
    }

    /**
     * Calculate the average value of a numeric column in the table.
     * 
     * @param string $column The column to calculate the average.
     * 
     * @return int|float|bool Return total average of columns, otherwise false if execution failed.
     * @throws DatabaseException If an error occurs.
     */
    public function average(string $column): int|float|bool
    {
        return $this->createQueryExecution("SELECT AVG({$column}) AS totalCalc", 'average');
    }

    /**
     * Select multiple records from table.
     * 
     * @param array<int,string> $columns select columns.
     * 
     * @return object|null|array|int|float|bool Return selected rows, otherwise false if execution failed.
     * @throws DatabaseException If an error occurs.
     */
    public function select(array $columns = ['*']): mixed 
    {
        return $this->createQueryExecution('', 'select', $columns);
    }

    /**
     * Select a single or next record from table,
     * 
     * @param array<int,string> $columns The table columns to return (default: *).
     * 
     * @return object|null|array|int|float|bool Return selected single row, otherwise false if execution failed.
     * @throws DatabaseException If where method was not called or an error occurs.
     */
    public function find(array $columns = ['*']): mixed 
    {
        if ($this->whereCondition === []) {
            throw new DatabaseException(
                'Find cannot be called without a where method being called first.', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }
        
        return $this->createQueryExecution('', 'find', $columns);
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
            return $this->createQueryExecution('', 'fetch', $columns, $result, $mode);
        }

        throw new DatabaseException(
            'Invalid fetch result type, expected "all or next".', 
            DatabaseException::VALUE_FORBIDDEN
        );
    }

    /**
     * Returns query prepared statement based on build up method conditions.
     * 
     * @param array<int,string> $columns The table columns to return (default: *).
     * 
     * @return DatabaseInterface Return prepared statement if query is successful otherwise null.
     * @throws DatabaseException Throws if an error occurs.
     */
    public function stmt(array $columns = ['*']): ?DatabaseInterface
    {
        $this->resultType = 'stmt';

        if($this->createQueryExecution('', 'stmt', $columns)){
            return self::database();
        }

        $this->free();

        return null;
    }

    /**
     * Create query and execute it.
     * 
     * @param string $query Initial method query starting.
     * @param string $return The method return type based on calling method.
     * @param array $columns For select and find methods, the column names to return.
     * @param string $result The fetch result type (next or all).
     * @param int $mode The fetch result mode FETCH_*.
     * 
     * @return mixed Return result of executed method query.
     * @throws DatabaseException If an error occurs.
     */
    private function createQueryExecution(
        string $query, 
        string $return = 'total', 
        array $columns = ['*'], 
        string $result = 'all', 
        int $mode = FETCH_OBJ
    ): mixed
    {
        if(
            !$this->printQuery && 
            $mode !== self::MODE_COPY &&
            $return !== 'stmt' && 
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

        if($return === 'select' || $return === 'find' || $return === 'stmt'|| $return === 'fetch'){
            $columns = ($columns === ['*']) ? '*' : implode(', ', $columns);
            $query = "SELECT {$columns}";
        }
        
        $sqlQuery = "{$query} FROM {$this->tableName} {$this->tableAlias}";
        $sqlQuery .= $this->getJoinConditions();

        try {
            $response = $this->returnExecutedResult($sqlQuery, $return, $result, $mode);

            if($mode === self::MODE_COPY){
                return $response;
            }

            if(
                !$this->printQuery &&
                $return !== 'stmt' &&
                $this->queryWithCache && 
                (self::$cache instanceof BaseCache)
            ){
                self::$cache->set($this->cacheKey, $response);
                self::$cache = null;
                $this->queryWithCache = false;
            }

            return $response;
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }
        
        return false;
    }

    /**
     * Return executed result records.
     * 
     * @param string $sqlQuery The sql query string to execute.
     * @param string $return The return type.
     * @param string $result The fetch result type (next or all).
     * @param int $mode The fetch result mode FETCH_*.
     * 
     * @return mixed Return query result.
     * @throws DatabaseException If an error occurs.
     */
    private function returnExecutedResult(
        string $sqlQuery, 
        string $return = 'total', 
        string $result = 'all', 
        int $mode = FETCH_OBJ
    ): mixed
    {
        // When using IN as WHERE and it has other ANDs ORs as binding.
        $isBided = $this->andConditions !== [];
        $isOrdered = false;
        $response = false;
        $withWhere = $this->whereCondition === [];
        
        if (!$withWhere) {
            $isBided = true;
            $sqlQuery .= $this->whereCondition['query'];
        }

        $this->buildConditions($sqlQuery, $isBided, $withWhere);

        if($this->queryGroup !== []){
            $sqlQuery .= ' GROUP BY ' . rtrim(implode(', ', $this->queryGroup), ', ');
        }

        if($this->queryOrder !== []){
            $isOrdered = true;
            $sqlQuery .= ' ORDER BY ' . rtrim(implode(', ', $this->queryOrder), ', ');
        }

        if($this->queryMatchOrder !== []){
            $this->orderAgainstMatch($sqlQuery, $isBided, $isOrdered);
        }

        if($return === 'find'){
            $sqlQuery .= ' LIMIT 1';
        }elseif($this->selectLimit !== ''){
            $sqlQuery .= $this->selectLimit;
        }

        if($mode === self::MODE_COPY){
            return $sqlQuery;
        }

        if($this->printQuery){
            return $this->printDebugQuery($sqlQuery, $return);
        }

        if($isBided){
            self::database()->prepare($sqlQuery);
            if ($this->whereCondition !== []) {
                self::database()->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            }
            $this->bindConditions($isBided);
            self::database()->execute();
        }else{
            self::database()->query($sqlQuery);
        }

        if (self::database()->ok()) {
            $response = match ($return) {
                'stmt' => true,
                'select' => self::database()->getAll($this->resultType),
                'find' => self::database()->getNext($this->resultType),
                'total' => self::database()->getCount(),
                'fetch' => self::database()->fetch($result, $mode),
                default => self::database()->getNext()->totalCalc ?? 0,
            };
        }
        
        $this->reset();
        return $response;
    }

    /**
     * Update table with columns and values.
     * 
     * @param array<string,mixed> $setValues associative array of columns and values to update.
     * 
     * @return int Return number of affected rows.
     * @throws DatabaseException Throw if error occurred while updating or where method was never called.
     * @throws JsonException If an error occurs while encoding values.
     */
    public function update(?array $setValues = []): int 
    {
        $columns = ($setValues === []) ? $this->querySetValues : $setValues;

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

        $updateQuery = "UPDATE {$this->tableName} {$this->tableAlias}";
        $updateQuery .= $this->getJoinConditions();
        $updateQuery .= ' SET ' . self::buildPlaceholder($columns, true);
        $updateQuery .= $this->whereCondition['query'] ?? '';
        $this->buildConditions($updateQuery, true, $this->whereCondition === []);

        if($this->maxLimit > 0){
            $updateQuery .= " LIMIT {$this->maxLimit}";
        }

        if($this->printQuery){
            $this->printDebugQuery($updateQuery, 'update');
            return 0;
        }

        try {
            self::database()->prepare($updateQuery);
            foreach($columns as $key => $value){
                if(!is_string($key) || $key === '?'){
                    throw new DatabaseException(
                        "Invalid update key {$key}, update key must be a valid table column name.", 
                        DatabaseException::VALUE_FORBIDDEN
                    );
                }

                $value = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
                self::database()->bind(self::trimPlaceholder($key), $value);
            }
            
            if($this->whereCondition !== []){
                self::database()->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            }
            
            $this->bindConditions();
            $response = self::database()->execute() ? self::database()->rowCount() : 0;
            $this->reset();

            return $response;
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Delete record from table.
     * 
     * @return int Return number of affected rows.
     * @throws DatabaseException Throw if error occurs.
     */
    public function delete(): int
    {
        if ($this->whereCondition === []) {
            throw new DatabaseException(
                'Delete operation without a WHERE condition is not allowed.', 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        $deleteQuery = "DELETE FROM {$this->tableName}";
        $deleteQuery .= $this->whereCondition['query'];

        $this->buildConditions($deleteQuery, true, false);

        if($this->maxLimit > 0){
            $deleteQuery .= " LIMIT {$this->maxLimit}";
        }

        if($this->printQuery){
            $this->printDebugQuery($deleteQuery, 'delete');
            return 0;
        }

        try {
            self::database()->prepare($deleteQuery)
                ->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions();

            $response = self::database()->execute() ? self::database()->rowCount() : 0;
            $this->reset();

            return $response;
        } catch (DatabaseException|Exception $e) {
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
        return self::database()->errors();
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
     */
    public function transaction(int $flags = 0, ?string $name = null): bool 
    {
        return self::database()->beginTransaction($flags, $name);
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
     */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        return self::database()->commit($flags, $name);
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
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        return self::database()->rollback($flags, $name);
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
     * If transaction failed rollback to default.
     * 
     * @param bool $transaction Weather to use transaction (default true).
     * 
     * @return bool Return true truncation was completed, otherwise false.
     * @throws DatabaseException Throws if an error occurred during execution.
     */
    public function truncate(bool $transaction = true): bool 
    {
        try {
            $driverName = self::database()->getDriver();
            $transaction = ($transaction && $driverName !== 'sqlite');

            if ($transaction && !self::database()->beginTransaction()) {
                DatabaseException::throwException(
                    'Failed: Unable to start transaction', 
                    DatabaseException::DATABASE_TRANSACTION_FAILED
                );
                return false;
            }

            if ($driverName === 'mysql' || $driverName === 'mysqli' || $driverName === 'pgsql') {
                $completed = self::database()->exec("TRUNCATE TABLE {$this->tableName}");
            } elseif ($driverName === 'sqlite') {
                $deleteSuccess = self::database()->exec("DELETE FROM {$this->tableName}");
                $resetSuccess = true;

                $result = self::database()->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sqlite_sequence'")->getNext('array');
                if ($result) {
                    $resetSuccess = self::database()->exec("DELETE FROM sqlite_sequence WHERE name = '{$this->tableName}'");
                }

                $completed = $deleteSuccess && $resetSuccess;

                if ($completed && !self::database()->exec("VACUUM")) {
                    $completed = false;
                }
            } else {
                $deleteSuccess = self::database()->exec("DELETE FROM {$this->tableName}");
                $resetSuccess = self::database()->exec("ALTER TABLE {$this->tableName} AUTO_INCREMENT = 1");

                $completed = $deleteSuccess && $resetSuccess;
            }

            if ($transaction && self::database()->inTransaction()) {
                if ($completed && self::database()->commit()) {
                    $this->reset();
                    return true;
                }

                self::database()->rollback();
                $completed = false;
            }

            $this->reset();
            return (bool) $completed;

        } catch (DatabaseException|Exception $e) {
            if ($transaction && self::database()->inTransaction()) {
                self::database()->rollback();
            }

            $this->reset();
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }

        if ($transaction && self::database()->inTransaction()) {
            self::database()->rollback();
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
     * @example
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
            $create = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_{$this->tableName} 
            AS (SELECT * FROM {$this->tableName} WHERE 1 = 0)";
            
            if($transaction && !self::database()->beginTransaction()){
                DatabaseException::throwException(
                    'Failed: Unable to start transaction', 
                    DatabaseException::DATABASE_TRANSACTION_FAILED
                );
                return false;
            }

            if (self::database()->exec($create) > 0 && 
                self::database()->exec("INSERT INTO temp_{$this->tableName} SELECT * FROM {$this->tableName}") > 0
            ) {
                $result = false;
                if($transaction && self::database()->inTransaction()){
                    if(self::database()->commit()){
                        $result = true;
                    }else{
                        self::database()->rollBack();
                    }
                }

                $this->reset();
                return $result;
            }

            if (self::database()->inTransaction()) {
                self::database()->rollBack();
            }

            $this->reset();
            return false;
        } catch (DatabaseException | Exception $e) {
            if (self::database()->inTransaction()) {
                self::database()->rollBack();
            }

            $this->reset();
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }

        if (self::database()->inTransaction()) {
            self::database()->rollBack();
        }

        $this->reset();
        return false;
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
    public function exec(string $query): int 
    {
        if ($query === '') {
            throw new InvalidArgumentException(
                'Invalid: The parameter $query requires a valid and non-empty SQL query string.'
            );
        }

        try {
            return self::database()->exec($query);
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Drop database table table or temporal if it exists.
     * 
     * @param bool $transaction Whether to use a transaction (default: false).
     * @param bool $isTemporalTable Whether the table is a temporary table (default false).
     * 
     * @return bool Return true if table was successfully dropped, false otherwise.
     * @throws DatabaseException Throws if error occurs.
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
            if ($transaction && !self::database()->beginTransaction()) {
                DatabaseException::throwException(
                    'Failed: Unable to start transaction for drop table.', 
                    DatabaseException::DATABASE_TRANSACTION_FAILED
                );
                return false;
            }

            $drop = $this->getDropTableSQL($isTemporalTable);

            if (self::database()->exec($drop) > 1) {
                $result = false;
                if ($transaction && self::database()->inTransaction()) {
                    if (self::database()->commit()) {
                        $result = true;
                    } else {
                        self::database()->rollBack();
                    }
                }

                $this->reset();
                return $result;
            }

            if (self::database()->inTransaction()) {
                self::database()->rollBack();
            }

            $this->reset();
        } catch (DatabaseException | Exception $e) {
            if (self::database()->inTransaction()) {
                self::database()->rollBack();
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
        static $manager = null;
        $manager ??= new Manager(self::database());
        $manager->setTable($this->tableName);

        return $manager;
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
     * Debug dump statement information for the last statement execution.
     *
     * @return bool|null trues else false or null.
     */
    public function dump(): bool|null
    {
        return self::database()->dumpDebug();
    }

    /**
     * Free database resources
     * 
     * @return void 
     */
    public function free(): void 
    {
        if(!(self::$conn instanceof Connection) || !(self::isConnected()) ){
            return;
        }

        self::$conn->database()->free();
        $this->purge();
    }

    /**
     * Close database connection
     * 
     * @return void 
     */
    public function close(): void 
    {
        if(!(self::$conn instanceof Connection) || !self::isConnected()){
            return;
        }

        self::$conn->purge(true);
    }

     /**
     * Reset query conditions and Free database resources
     * 
     * @return void 
     */
    public function reset(): void 
    {
        $this->tableJoin = [];
        $this->joinConditions = [];
        $this->selectLimit = '';
        $this->maxLimit = 0;
        $this->queryOrder = [];
        $this->queryMatchOrder = [];
        $this->queryGroup = [];
        $this->whereCondition = [];
        $this->andConditions = [];
        $this->querySetValues = [];
        $this->hasCache = false;
        $this->printQuery = false;
        $this->copySelections = '';
        $this->bindValues = [];
        $this->buildQuery = '';
        if($this->resultType !== 'stmt'){
            $this->free();
        }
        
        $this->resultType = 'object';
    }

    /**
     * Handle insert or replace statement.
     * 
     * @param array $values The values to insert or replace.
     * @param string $type The type of statement to be executed.
     * @param bool $prepare Weather to use the prepared statement or query execution.
     * 
     * @return int Return the number of affected rows.
     * @throws DatabaseException If an error occurs during the operation or if the provided values are invalid.
     * @throws JsonException If an error occurs while encoding array values to JSON format.
     */
    private function insertOrReplace(array $values, string $type, bool $prepare = true): int
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

        $columns = array_keys($values[0]);

        try {
            if($prepare){
                return $this->executeInsertPrepared($columns, $values, $type);
            }
            return $this->executeInsertQuery($columns, $values, $type);
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Return custom builder result from table.
     * 
     * @param string $buildQuery query.
     * @param int $mode return result type.
     * 
     * @return mixed|DatabaseInterface Return query result, prepared statement object, otherwise false on failure.
     * @throws DatabaseException If placeholder key is not a string.
    */
    private function returnExecute(string $buildQuery, int $mode): mixed
    {
        if($this->printQuery){
            return $this->printDebugQuery($buildQuery, 'execute');
        }

        if($this->bindValues === []){
            self::database()->query($buildQuery);
        }else{
            self::database()->prepare($buildQuery);
            foreach ($this->bindValues as $key => $value) {
                if(!is_string($key) || $key === '?'){
                    throw new DatabaseException(
                        "Invalid bind placeholder {$key}, placeholder key must be same with your table mapped column key, (e.g, :foo, :bar).", 
                        DatabaseException::VALUE_FORBIDDEN
                    );
                }

                self::database()->bind(self::trimPlaceholder($key), $value);
            } 
            
            self::database()->execute();
        }

        $response = (self::database()->ok() ? 
            (($mode === RETURN_STMT) 
                ? self::database()
                : self::database()->getResult($mode, $this->resultType)) 
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
        foreach($this->tableJoin as $table => $values){
            $sql .= " {$values['type']} JOIN {$table} {$values['alias']}";

            if($this->joinConditions !== [] && isset($this->joinConditions[$table])){
                $sql .= " ON {$this->joinConditions[$table][0]}";

                if(($joins = count($this->joinConditions[$table])) > 1){
                    for ($i = 1; $i < $joins; $i++) {
                        $sql .= " AND {$this->joinConditions[$table][$i]}";
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
            $stmt = $tbl->database()->prepare($query)->bind('lockName', $identifier);
            $tbl = null;
            if (
                $action === 'lock' && 
                in_array($driver, ['mysql', 'mysqli', 'cubrid', 'sqlsrv', 'mssql', 'dblib', 'oci', 'oracle'], true)
            ) {
                $stmt->bind('waitTimeout', $timeout);
            }

            if (!$stmt->execute() || !$stmt->ok()) {
                return false;
            }

            return match ($action) {
                'isLocked' => ($driver === 'sqlite') 
                    ? $stmt->getNext()->lockCount > 0 
                    : (bool) $stmt->getNext()->isLockDone,
                default => true
            };
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
            return false;
        }
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

        return match (self::database()->getDriver()) {
            'mysql', 'mysqli' => "DROP " . ($isTempTable ? "TEMPORARY " : "") . "TABLE IF EXISTS {$tablePrefix}{$this->tableName}",
            'dblib' => "DROP TABLE IF EXISTS {$tableIdentifier}",
            'sqlsrv' => "IF OBJECT_ID('{$tablePrefix}{$this->tableName}', 'U') IS NOT NULL DROP TABLE {$tablePrefix}{$this->tableName}",
            'oracle', 'oci' => "BEGIN EXECUTE IMMEDIATE 'DROP TABLE {$tablePrefix}{$this->tableName}'; EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;",
            default => "DROP TABLE IF EXISTS {$tablePrefix}{$this->tableName}"
        };
    }

    /**
     * Execute insert query.
     * 
     * @param array $columns column name to target insert.
     * @param array $values array of values to insert.
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function executeInsertQuery(array $columns, array $values, string $type): int 
    {
        $inserts = '';
        foreach ($values as $row) {
            $inserts .= "(" . self::quotedValues($row) . "), ";
        }

        $keys = implode(', ', $columns);
        $inserts = rtrim($inserts, ', ');
        $insertQuery = "{$type} INTO {$this->tableName} ({$keys}) VALUES {$inserts}";

        if($this->printQuery){
            $this->printDebugQuery($insertQuery, strtolower($type));
            return 0;
        }
        
        $response = self::database()->query($insertQuery)->ok() 
            ? self::database()->rowCount() 
            : 0;

        $this->reset();

        return $response;
    }

    /**
     * Execute insert query using prepared statement.
     * 
     * @param array $columns column name to target insert.
     * @param array $values array of values to insert.
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function executeInsertPrepared(array $columns, array $values, string $type): int
    {
        $count = 0;
        self::$lastInsertId = null;

        [$placeholders, $inserts] = self::mapParams($columns);
        $insertQuery = "{$type} INTO {$this->tableName} ({$inserts}) VALUES ($placeholders)";
       
        if($this->printQuery){
            $this->printDebugQuery($insertQuery, strtolower($type), $values);
            return 0;
        }

        self::database()->prepare($insertQuery);
    
        foreach ($values as $row) {
            foreach ($row as $key => $value) {
                $value = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
                self::database()->bind(self::trimPlaceholder($key), $value);
            }

            if(self::database()->execute()){
                $count++;
            }
        }

        if($count > 0){
            self::$lastInsertId = self::database()->getLastInsertId();
        }

        $this->reset();
        return $count;
    }

    /**
     * Build query conditions based on the specified type.
     *
     * @param string $query The SQL query string to which conditions passed by reference.
     * @param bool $isBided Whether the params are bind params (default: true).
     * @param bool $addWhereOperator Whether the where conditions should be added 
     *                          and if false treat it as AND (default: true).
     * 
     * @return void
     */
    private function buildConditions(
        string &$query, 
        bool $isBided = true, 
        bool $addWhereOperator = true
    ): void
    {
        if ($this->andConditions === []) {
            return;
        }

        if ($addWhereOperator) {
            $query .= ' WHERE ';
        }

        $firstCondition = true;

        foreach ($this->andConditions as $index => $condition) {
            if ($addWhereOperator && !$firstCondition) {
                $query .= ($condition['type'] === 'OR') ? ' OR' : ' AND';
            }

            $query .= match ($condition['type']) {
                'GROUP_OR' => self::buildGroupConditions($condition['conditions'], $index, $isBided, 'OR'),
                'GROUP_AND' => self::buildGroupConditions($condition['conditions'], $index, $isBided, 'AND'),
                'BIND_OR' => self::buildGroupBindConditions($condition['X'], $condition['Y'], $index, $isBided, 'OR', $condition['bind']),
                'BIND_AND' => self::buildGroupBindConditions($condition['X'], $condition['Y'], $index, $isBided, 'AND', $condition['bind']),
                default => $this->buildSingleConditions($condition, $index, $isBided, !$addWhereOperator),
            };

            $firstCondition = false;
        }
    }

    /**
     * Constructs a single condition query string with placeholders for binding values.
     *
     * @param array   $condition  An array representing the condition.
     * @param int     $index      The index to append to the placeholder names.
     * @param bool    $isBided    Indicates whether placeholders should be used for binding values (default: true).
     * @param bool    $addOperator      Indicates whether is for to add AND OR operator (default: true).
     *                                  Constructs a single ANDs condition query string with placeholders for binding values.
     *
     * @return string Return query string representation of the single condition.
     */
    private function buildSingleConditions(
        array $condition, 
        int $index, 
        bool $isBided = true,
        bool $addOperator = true
    ): string
    {
        $operator = $condition['operator'] ?? '=';
        $column = $condition['column'] ?? '';
        $prefix = $addOperator ? (($condition['type'] === 'OR') ? 'OR ' : 'AND ') : '';
        $placeholder = $isBided 
            ? ($condition['type'] === 'AGAINST' 
                ? ":match_column_{$index}" 
                : self::trimPlaceholder($column))
            : addslashes($condition['value']);

        return match ($condition['type']) {
            'AND' => " {$prefix}$column $operator $placeholder",
            'OR' => " {$prefix}$column $operator $placeholder",
            'IN' => " {$prefix}$column IN (" . (
                $isBided 
                ? rtrim($this->bindInConditions($condition['values'], $column), ', ')
                : self::quotedValues($condition['values'])
            ) . ')',
            'AGAINST' => " {$prefix}MATCH($column) AGAINST ({$placeholder} {$operator})",
            'IN_SET' => self::insetQuery($condition, $prefix, $operator),
            'LIKE' => " {$prefix}{$column} LIKE ?",
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
    private static function insetQuery(
        array $condition, 
        string $prefix, 
        string $operator
    ): string 
    {
        // Sanitize the search term to prevent SQL injection if is not column name
        $search = $condition['isSearchColumn'] 
            ? $condition['search'] 
            : "'". addslashes($condition['search'])  . "'";
        
        // Sanitize the list or assume it's a column
        $values = $condition['isList'] ? "'". addslashes($condition['list']) . "'" : $condition['list'];
        $operator = match($operator) {
            'position' => 'AS inset_position',
            '>', 'exists' => '> 0',
            '=', 'first' => '= 1',
            'last' => "= (LENGTH({$values}) - LENGTH(REPLACE({$values}, ',', '')) + 1)",
            'none' => '= 0',
            'contains' => "LIKE '%{$search}%'",
            default => $operator,   
        };
        
        return " {$prefix}FIND_IN_SET({$search}, {$values}) {$operator}";
    }    

    /**
     * Bind query where conditions.
     * 
     * @param bool $isBided Whether the value is bound.
     * 
     * @return void
     */
    private function bindConditions(bool $isBided = false): void 
    {
        foreach ($this->andConditions as $index => $bindings) {
            switch ($bindings['type']) {
                case 'AGAINST':
                    self::database()->bind(":match_column_{$index}", $bindings['value']);
                break;
                case 'GROUP_OR':
                case 'GROUP_AND':
                    $this->bindGroupConditions($bindings['conditions'], $index);
                break;
                case 'BIND_OR':
                case 'BIND_AND':
                    $last = 0;
                    $this->bindGroupConditions($bindings['X'], $index, $last);
                    $this->bindGroupConditions($bindings['Y'], $index, $last);
                break;
                case 'IN_SET':
                    // skip
                break;
                case 'IN':
                    if($isBided){
                        $this->bindInConditions($bindings['values'], $bindings['column'], true);
                    }
                break;
                default:
                    self::database()->bind(self::trimPlaceholder($bindings['column']), $bindings['value']);
                break;
            }
        }

        foreach($this->queryMatchOrder as $idx => $order){
            self::database()->bind(":match_order_{$idx}", $order['value']);
        }
    }

    /**
     * Builds a query string representation of single grouped conditions.
     *
     * @param array   $conditions   An array of conditions to be grouped.
     * @param int     $index        The index to append to the placeholder names.
     * @param bool    $isBided      Indicates whether placeholders should be used for binding values (default: true).
     * @param string  $type         The type of logical operator to use between conditions within the group (default: 'OR').
     * @param int     &$last        Reference to the total count of conditions processed so far across all groups.
     *
     * @return string Return query string representation of grouped conditions with placeholders.
     * 
     * @example 'SELECT * FROM foo WHERE (bar = 1 AND baz = 2)'.
     * @example 'SELECT * FROM foo WHERE (boz = 1 OR bra = 2)'.
     */
    private static function buildGroupConditions(
        array $conditions, 
        int $index, 
        bool $isBided = true,  
        string $type = 'OR', 
        int &$last = 0
    ): string
    {
        $group = '';
        $count = 0;
        foreach ($conditions as $idx => $condition) {
            $column = key($condition);
            $operator = $condition[$column]['operator'] ?? '=';
            $placeholder = $isBided 
                ? self::trimPlaceholder("{$column}_{$index}_" . ($idx + $last + 1)) 
                : addslashes($condition[$column]['value']);

            if ($idx > 0) {
                $group .= " {$type} ";
            }

            $group .= "{$column} {$operator} {$placeholder}";
            $count++;
        }

        $last += $count;
        return "({$group})";
    }

    /**
     * Builds a query string representation of multiple group conditions.
     *
     * @param array   $conditionsX  An array of conditions for the first group.
     * @param array   $conditionsY  An array of conditions for the second group.
     * @param int     $index        The index to append to the placeholder names.
     * @param bool    $isBided      Indicates whether placeholders should be used for binding values (default: true).
     * @param string  $type         The type of logical operator to use between groups (default: 'OR').
     * @param string  $bind         The type of logical operator to use in binding groups (default: 'OR').
     *
     * @return string Return a query string representation of grouped conditions with placeholders.
     * 
     * @example 'SELECT * FROM foo WHERE ((bar = 1 AND baz = 2) AND (boz = 1 AND bra = 5))'.
     * @example 'SELECT * FROM foo WHERE ((bar = 1 OR baz = 2) OR (boz = 1 OR bra = 5))'.
     */
    private static function buildGroupBindConditions(
        array $conditionsX, 
        array $conditionsY, 
        int $index, 
        bool $isBided = true, 
        string $type = 'OR',
        string $bind = 'OR'
    ): string
    {
        $last = 0;
        $groupX = self::buildGroupConditions($conditionsX, $index, $isBided, $type, $last);
        $groupY = self::buildGroupConditions($conditionsY, $index, $isBided, $type, $last);

        return "({$groupX} {$bind} {$groupY})";
    }

    /**
     * Bind query in conditions.
     * 
     * @param array  $values  The column array values.
     * @param string $column  The column placeholder names.
     * @param bool $handle Weather to handle or return placeholders.
     * 
     * @return string
     */
    private function bindInConditions(
        array $values, 
        string $column,
        bool $handle = false,
    ): string 
    {
        $placeholders = '';
        foreach ($values as $idx => $value) {
            $placeholder = self::trimPlaceholder("{$column}_in_{$idx}");

            if($handle){
                self::database()->bind($placeholder, $value);
            }else{
                $placeholders .= "{$placeholder}, ";
            }
        }

        return $placeholders;
    }

    /**
     * Bind group conditions to the database handler.
     *
     * @param array  $bindings  An array of conditions to bind.
     * @param int $index  The index to append to the placeholder names.
     * @param int &$last  A reference to the last counter used to ensure unique placeholder names.
     *
     * @return void
     */
    private function bindGroupConditions(
        array $bindings, 
        int $index, 
        int &$last = 0
    ): void 
    {
        $count = 0;
        foreach ($bindings as $idx => $bind) {
            $column = key($bind);
            $placeholder = self::trimPlaceholder("{$column}_{$index}_" . ($idx + $last + 1));
            self::database()->bind($placeholder, $bind[$column]['value']);
            $count++;
        }
        $last += $count;
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
     * 
     * @return array|bool Returns false on production, otherwise return query array.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function printDebugQuery(string $query, string $method, array $values = []): bool
    {
        $params = [];
        if($method === 'insert' || $method === 'replace'){
            foreach($values as $bindings){
                $column = key($bindings);
                $value = is_array($bindings[$column]) 
                    ? json_encode($bindings[$column], JSON_THROW_ON_ERROR) 
                    : $bindings[$column];
                $params[] = ":{$column} = " . $value;
            }
        }else{
            if ($this->whereCondition !== []) {
                $params[] = "{$this->whereCondition['placeholder']} = " . $this->whereCondition['value'];
            }

            foreach ($this->andConditions as $index => $bindings) {
                switch ($bindings['type']) {
                    case 'AGAINST':
                        $params[] = ":match_column_{$index} = " . $bindings['value'];
                    break;
                    case 'GROUP_OR':
                    case 'GROUP_AND':
                        self::bindDebugGroupConditions($bindings['conditions'], $index, $params);
                    break;
                    case 'BIND_OR':
                    case 'BIND_AND':
                        $last = 0;
                        self::bindDebugGroupConditions($bindings['X'], $index, $params, $last);
                        self::bindDebugGroupConditions($bindings['Y'], $index, $params, $last);
                    break;
                    default: 
                        $placeholder = self::trimPlaceholder($bindings['column']);
                        $params[] = ":$placeholder = " . $bindings['value'];
                    break;
                }
            }
        }
        
        $this->reset();
        $this->debugQuery = [
            'method' => $method,
            'pdo' => $query,
            'mysqli' => preg_replace('/:([a-zA-Z0-9_]+)/', '?', $query),
            'binding' => $params
        ];

        if (PRODUCTION) {
            Logger::debug(json_encode($this->debugQuery, JSON_PRETTY_PRINT));
            return false;
        }

        return false;
    }

    /**
     * Orders the query based on the MATCH columns and mode.
     * 
     * @param string &$selectQuery The SQL query string passed by reference.
     * @param bool $isBided Whether the value is bound.
     * @param bool $isOrdered Whether the query has been ordered.
     * 
     * @return void
     */
    private function orderAgainstMatch(string &$selectQuery, bool $isBided = true, bool $isOrdered = false): void 
    {
        $orders = $isOrdered ? ' , ' : ' ORDER BY';
        foreach($this->queryMatchOrder as $idx => $order){
            $value = $isBided 
                ? ":order_match_{$idx}" 
                : (is_string($order['value']) 
                    ? "'" . addslashes($order['value']) . "'" 
                    : $order['value']
                );
            $orders .= "MATCH({$order['column']}) AGAINST ({$value} {$order['mode']}) {$order['order']}, ";
        }

        $selectQuery .= rtrim($orders, ', ');
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
    private static function bindDebugGroupConditions(array $bindings, int $index, array &$params = [], int &$last = 0): void 
    {
        $count = 0;
        foreach ($bindings as $idx => $bind) {
            $column = key($bind);
            $placeholder = self::trimPlaceholder("{$column}_{$index}_" . ($idx + $last + 1));
            $params[] = "{$placeholder} = " . $bind[$column]['value'];
            $count++;
        }
        $last += $count;
    }

   /**
     * New cache instance.
     * 
     * @param string|null $storage Optional storage name for the cache.
     * @param string|null $subfolder Optional file-based caching subfolder.
     * @param string|null $persistent_id Optional memory-based caching unique persistent connection ID.
     * 
     * @return BaseCache Return instance of cache class.
     */
    private function newCache(
        ?string $storage = null, 
        ?string $subfolder = null,
        ?string $persistent_id = null
    ): BaseCache
    {
        $cache = ($this->cacheDriver === 'memcached') 
            ? MemoryCache::getInstance(null, $persistent_id ?? '__database_builder__')
            : FileCache::getInstance(null)
                ->setFolder('database' . ($subfolder ? DIRECTORY_SEPARATOR . trim($subfolder, TRIM_DS) : ''));

        return $cache->setStorage('database_' . ($storage ?? $this->tableName ?? 'capture'));
    }

    /**
     * Trim placeholder and remove`()` if present
     *
     * @param string|null $input The column name to convert to placeholder.
     * 
     * @return string Return column placeholder.
     */
    private static function trimPlaceholder(string|null $input): string 
    {
        if(!$input){
            return '';
        }

        if (preg_match('/\(([^)]+)\)/', $input, $matches)) {
            $input = $matches[1];
        }
    
        // $lastDotPosition = strrpos($input, '.');
        // $placeholder = ($lastDotPosition === false) ? $input : substr($input, $lastDotPosition + 1);
        // return ":$placeholder";

        return ':' . (str_contains($input, '.') ? str_replace('.', '_', $input) : $input);
    }
    
    /**
     * Build insert params.
     * 
     * @var array $columns Array of column names.
     * 
     * @return array<int,string> Array of insert params and placeholders.
     */
    private static function mapParams(array $columns): array 
    {
        $placeholders = '';
        $inserts = '';
        foreach($columns as $col){
            $placeholders .= ":$col, ";
            $inserts .= "$col, ";
        }

        return [rtrim($placeholders, ', '), rtrim($inserts, ', ')];
    }

    /**
     * Convert array keys to placeholders key = :key.
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
            $updateColumns[] = "{$column} = :" . str_replace('.', '_', $column);
        }

        if($implode){
            return implode(', ', $updateColumns);
        }

        return $updateColumns;
    }

    /**
     * Prepare quoted values from an array of columns.
     *
     * @param array $columns The array of columns to be quoted.
     * @param string $return The return type, can be 'array' or 'string'.
     * 
     * @return array|string An array of quoted values or a string of quoted values.
     * @throws JsonException If an error occurs while encoding values.
     */
    private static function quotedValues(array $columns, string $return = 'string'): array|string
    {
        $quoted = [];
        $string = '';
        foreach ($columns as $item) {
            if(is_array($item)){
                $value = "'" . json_encode($item, JSON_THROW_ON_ERROR) . "'" ;
            }else{
                $value = is_numeric($item) ? $item : "'" . addslashes($item) . "'";
            }

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