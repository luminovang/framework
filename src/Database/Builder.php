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

use \Closure;
use \Throwable;
use \DateTimeZone;
use \JsonException;
use \DateTimeInterface;
use \Luminova\Time\Time;
use \Luminova\Logger\Logger;
use \Luminova\Base\BaseCache;
use \Luminova\Utils\LazyObject;
use \Luminova\Utils\Promise\Promise;
use \Luminova\Cache\{FileCache, MemoryCache};
use \Luminova\Database\{Connection, Manager, RawExpression};
use \Luminova\Interface\{LazyInterface, PromiseInterface, DatabaseInterface};
use \Luminova\Exceptions\{CacheException, DatabaseException, InvalidArgumentException};
use function \Luminova\Funcs\{is_associative, is_command};

final class Builder implements LazyInterface
{  
    /**
     * Debug mode none.
     * 
     * @var int DEBUG_NONE
     */
    public const DEBUG_NONE = 0;

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
     * Full-text match using Natural Language Mode.
     * 
     * MySQL interprets the search string as a natural human language phrase.
     * This is the default mode for most full-text searches.
     *
     * SQL: MATCH (...) AGAINST (... IN NATURAL LANGUAGE MODE)
     * 
     * @var int MATCH_NATURAL
     */
    public const MATCH_NATURAL = 1;

    /**
     * Full-text match using Boolean Mode.
     * 
     * Allows logical operators (e.g. +, -, *, >) in the search string 
     * to refine the matching process.
     *
     * SQL: MATCH (...) AGAINST (... IN BOOLEAN MODE)
     * 
     * @var int MATCH_BOOLEAN
     */
    public const MATCH_BOOLEAN = 2;

    /**
     * Natural Language Mode with Query Expansion.
     * 
     * MySQL performs the search in natural language mode, 
     * and then automatically expands the query based on top matching results.
     *
     * SQL: MATCH (...) AGAINST (... IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION)
     * 
     * @var int MATCH_NATURAL_EXPANDED
     */
    public const MATCH_NATURAL_EXPANDED = 3;

    /**
     * Query Expansion without specifying base mode.
     * 
     * Equivalent to using WITH QUERY EXPANSION only. Typically expands
     * the search using results from a prior MATCH clause.
     *
     * SQL: MATCH (...) AGAINST (... WITH QUERY EXPANSION)
     * 
     * @var int MATCH_EXPANSION
     */
    public const MATCH_EXPANSION = 4;

    /**
     * Default mode for clause method.
     * 
     * Standard comparison (e.g., `WHERE column = value`).
     * 
     * @var string REGULAR
     */
    public const REGULAR = 'REGULAR';

    /**
     * Combine mode for clause method.
     * 
     * Combined expressions (e.g., `WHERE (a = 1 OR b = 2)`).
     * 
     * @var string REGULAR
     */
    public const CONJOIN = 'CONJOIN';

    /**
     * Nested combine mode for clause method.
     * 
     * Deeply grouped conditions (e.g., `WHERE ((a = 1 AND b = 2) OR (c = 3))`).
     * 
     * @var string REGULAR
     */
    public const NESTED = 'NESTED';

    /**
     * Search full-text mode for clause method.
     * 
     * Full-text match using `MATCH (...) AGAINST (...)`.
     * 
     * @var string REGULAR
     */
    public const AGAINST = 'AGAINST';

    /**
     * Find in-array mode for clause method.
     * 
     * Filters using `IN (...)` with array values.
     * 
     * @var string REGULAR
     */
    public const INARRAY = 'INARRAY';

    /**
     * Find inset clause.
     * 
     * @var string INSET
     */
    private const INSET = 'INSET';

    /**
     * Database connection instance.
     *
     * @var Connection<LazyInterface>|null $conn
     */
    private static ?LazyInterface $conn = null;

    /**
     * Prepared statement object.
     * 
     * @var DatabaseInterface|mixed $stmt
     */
    private static mixed $stmt = null;

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
     * Table join bind parameters.
     * 
     * @var array $joinConditions 
     */
    private array $joinConditions = [];

    /**
     * Supports row-level locking.
     * 
     * @var string $lock
     */
    private string $lock = '';

    /**
     * Table query max limits.
     * 
     * @var array $maxLimit 
     */
    private array $maxLimit = [];

    /**
     * Table query group column by.
     * 
     * @var array<string,array<int,mixed>> $options 
     */
    private array $options = [];

    /**
     * Union tables.
     * 
     * @var array<string,mixed> $unions 
     */
    private array $unions = [];

    /**
     * Table query and query columns.
     * 
     * @var array<int,mixed> $conditions 
     */
    private array $conditions = [];

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
        self::MATCH_NATURAL => 'IN NATURAL LANGUAGE MODE',
        self::MATCH_BOOLEAN => 'IN BOOLEAN MODE',
        self::MATCH_NATURAL_EXPANDED => 'IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION',
        self::MATCH_EXPANSION => 'WITH QUERY EXPANSION',
    ];

    /**
     * Has Cache flag.
     * 
     * @var bool $hasCache 
     */
    private bool $hasCache = false;

    /**
     * Distinct selection flag.
     * 
     * @var bool $isDistinct 
     */
    private bool $isDistinct = false;

    /**
     * Use REPLACE insertion.
     * 
     * @var bool $isReplace 
     */
    private bool $isReplace = false;

    /**
     * Use internal transaction.
     * 
     * @var bool $isSafeMode 
     */
    private bool $isSafeMode = false;

    /**
     * Flag to prevent executing result.
     * 
     * @var bool $isCollectMetadata 
     */
    private bool $isCollectMetadata = false;

    /**
     * Query selection handler.
     * 
     * @var array $handler 
     */
    private array $handler = [];

    /**
     * Ignore duplicates during insertion.
     * 
     * @var bool $isIgnoreDuplicate 
     */
    private bool $isIgnoreDuplicate = false;

    /**
     * Caching status flag.
     * 
     * @var bool $isCacheable 
     */
    private bool $isCacheable = true;

    /**
     * Close connection after execution.
     * 
     * @var bool $closeConnection 
     */
    private bool $closeConnection = false;

    /**
     * is cache method is called for current query.
     * 
     * @var bool $isCacheReady 
     */
    private bool $isCacheReady = false;

    /**
     * Enable query debugging.
     * 
     * @var int $debugMode 
     */
    private int $debugMode = self::DEBUG_NONE;

    /**
     * Strict check.
     * 
     * @var bool $isStrictMode
     */
    private bool $isStrictMode = true;

    /**
     * The debug query information.
     * 
     * @var array<string,mixed> $debugInformation 
     */
    private array $debugInformation = [];

    /**
     * Result return type.
     * 
     * @var string|null $returns 
     */
    private ?string $returns = null;

    /**
     * Current class Id.
     * 
     * @var int|null $objectId
     */
    private ?int $objectId = null;

    /**
     * Cache key.
     * 
     * @var string $cacheKey 
     */
    private string $cacheKey = 'default';

    /**
     * Table name alias.
     * 
     * @var string $tableAlias 
     */
    private string $tableAlias = '';

    /**
     * Combine union alias.
     * 
     * @var string $unionCombineAlias 
     */
    private string $unionCombineAlias = '';

    /**
     * Join table.
     * 
     * @var array $tableJoin 
     */
    private array $tableJoin = [];

    /**
     * Raw SQL Query string.
     * 
     * @var string $rawQuery 
     */
    private string $rawQuery = '';

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
     * @param string|null $table Optional database table name (must be a valid non-empty string).
     * @param string|null $alias Optional table alias (default: null).
     *
     * @throws InvalidArgumentException If the table name is empty or contains invalid characters.
     */
    private function __construct(?string $table = null, ?string $alias = null)
    {
        $table = ($table === null) ? null : trim($table);
        
        if($table !== null || $alias !== null){
            self::assertTableName($table, $alias);
        }

        $this->resetState(true);
        $this->freeStmt();

        self::$conn ??= LazyObject::newObject(fn() => Connection::getInstance());
        self::$cacheDriver = env('database.caching.driver', 'filesystem');
        $this->tableName = $table ?? '';
        $this->tableAlias = $alias ?? '';
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
     * 
     * ```php
     * $instance = Builder::getInstance()
     *     ->cacheable(true)
     *     ->returns('array')
     *     ->strict(true);
     * ```
     * Now use the instance with inherited settings:
     * 
     * ```php
     * $result = $instance->table('users')
     *     ->where('id', '=', 100)
     *     ->select(['name']);
     * ```
     */
    public static function getInstance(?string $table = null, ?string $alias = null): self 
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
     * Get an integer object ID for given table object. 
     * 
     * @return int Return the current object identifier.
     */
    public function getObjectId(): int
    {
        return $this->objectId ??= random_int(1000000, 999999999);
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
     * 
     * ```php
     * $tbl = Builder::table('users', 'u')
     *     ->innerJoin('roles', 'r')
     *          ->on('u.user_id', '=', 'r.role_user_id')
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
        return self::initializer($table, $alias, true);
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
     * 
     * ```php
     * $result = Builder::query("SELECT * FROM users WHERE id = :user_id")
     *      ->execute(['user_id' => 1]);
     * ```
     * > **Note:** To cache result, you must call `cache()` before the `execute()` method.
     */
    public static function query(string $query): self 
    {
        self::assertQuery($query, __METHOD__);

        $extend = self::initializer();
        $extend->rawQuery = $query;

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
        self::assertQuery($query, __METHOD__);

        try {
            return self::initializer()->db->exec($query);
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
     * @example - Using RawExpression in an INSERT Query:
     * 
     * ```php
     * Builder::table('logs')
     *      ->insert([
     *          'message' => 'User login',
     *          'created_at' => Builder::raw('NOW()'),
     *          'updated_at' => Luminova\Database\RawExpression::now()
     *      ]);
     * ```
     * 
     * @example - Using REPLACE instead of INSERT:
     * 
     * ```php
     * Builder::table('logs')
     *      ->replace(true)
     *      ->insert([
     *          'message' => 'User login',
     *          'created_at' => Builder::raw('NOW()')
     *      ]);
     * ```
     */
    public static function raw(?string $expression = null): RawExpression 
    {
        return new RawExpression($expression);
    }

    /**
     * Specifies a table join operation in the query.
     *
     * This method defines how another table should be joined to the current query.
     *
     * @param string $table The name of the table to join.
     * @param string|null $alias Optional alias for the joined table (default: `null`).
     * @param string|null $type The type of join to perform (e.g, `INNER`, `LEFT`, `FULL`).
     * 
     * @return self Returns the instance of builder class.
     * @throws InvalidArgumentException If either `$table` or `$type` is an empty string.
     * 
     * @see innerJoin()
     * @see leftJoin()
     * @see fullJoin()
     * @see rightJoin()
     * @see crossJoin()
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
     * Builder::table('product', 'p')->join('users', 'u', 'LEFT');
     * ```
     * @example - Joining without an alias:
     * 
     * ```php
     * Builder::table('users')->join('orders', null, 'INNER');
     * ```
     */
    public function join(string $table, ?string $alias = null, ?string $type = null): self
    {
        $table = trim($table);
        self::assertTableName($table, $alias);

        $this->tableJoin[$table . ($alias ?? '')] = [
            'type' => strtoupper($type ?? ''),
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
     * @param mixed|Closure $value The value to compare, placeholder or another table column.
     *                      - String literals must be wrapped in quotes.
     *                      - Unquoted values are treated as column names.
     *                      - Placeholder, a named colon prefixed placeholder string to be referenced to `bind(...)`.
     * @param string $clause Optional nested clause condition (e.g, `AND` or `OR`).
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
     * @example - Multiple table join:
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
     * @see onCompound()
     * @see join()
     * @see leftJoin()
     * @see rightJoin()
     * @see innerJoin()
     * @see crossJoin()
     * @see fullJoin()
     * @see bind()
     */
    public function on(string $condition, string $comparison, mixed $value, string $clause = 'AND'): self
    {
        [, $clause, ] = $this->assertOperators(__METHOD__, null, $clause);
        $value = $this->getValue($value);
        $value = ($value instanceof RawExpression) 
            ? $value->getExpression() 
            : (($value === null) ? 'NULL' : $value);

        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'clause' => $clause,
            'sql' => "{$condition} {$comparison} {$value}"
        ];

        return $this;
    }

    /**
     * Adds a full SQL clause to the current join table condition.
     *
     * This method allows injecting a complete SQL `ON` clause expression as-is, useful for 
     * complex logic or syntax that can't be represented using standard `on()` parameters.
     *
     * @param RawExpression|string $sql An SQL string or an instance of raw SQL expression object to use directly in the join.
     * @param string $clause Optional logical clause (`AND` or `OR`) to combine with previous conditions.
     *
     * @return self Returns the instance of builder class.
     *
     * @example - Using `onClause()` with custom SQL logic:
     * 
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('logs', 'l')
     *         ->onClause('(u.id = 100 OR u.id = 200)')
     *         ->onClause(Builder::raw('DATE(l.created_at) = CURDATE()'));
     * ```
     *
     * @see on()
     * @see onCompound()
     * @see join()
     * @see bind()
     */
    public function onClause(RawExpression|string $sql, string $clause = 'AND'): self
    {
        [,$clause,] = $this->assertOperators(__METHOD__, null, $clause);

        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'clause' => $clause,
            'sql' => ($sql instanceof RawExpression) ? $sql->getExpression() : $sql
        ];

        return $this;
    }

    /**
     * Adds a compound join condition combining two column conditions with a connector.
     *
     * This defines an ON condition that joins two column conditions (or value comparisons)
     * linked by an operator like `AND`, `OR`, or any custom SQL operator.
     *
     * Both columns should use the structure from `Builder::column()`.
     *
     * @param array<int,array<string,array>> $columns The columns conditions (from `[Builder::column(...), //...]`).
     * @param string $operator The operator that connects both column conditions (e.g., `AND`, `OR`).
     * @param string $clause The outer clause linking this to previous ON conditions (e.g., `AND`, `OR`), (default `AND`).
     *
     * @return self Returns the instance of builder class.
     * @throws InvalidArgumentException If empty array columns or invalid was provided.
     * 
     * @example - Example:
     * 
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('contacts', 'c')
     *         ->onCompound([
     *              Builder::column('u.user_id' '=', 'c.contact_user_id'), 
     *              Builder::column('u.user_group', '=', 2)
     *         ], 'OR', 'AND')
     *     ->select();
     * ```
     *
     * @see on()
     */
    public function onCompound(array $columns, string $operator, string $clause = 'AND'): self
    {
        if($columns === []){
            throw new InvalidArgumentException(
                'The $columns array must not be empty. Use "Builder::column()" to create condition arrays.'
            );
        }

        [$operator, $clause, ] = $this->assertOperators(__METHOD__, $operator, $clause);

        $parts = [];

        foreach($columns as $column){
            [$name, $comparison, $value] = $this->getFromColumn($column, true);
            $parts[] = "{$name} {$comparison} {$value}";
        }

        if($parts === []){
            throw new InvalidArgumentException(
                'No valid conditions found in $columns. Make sure each condition includes a valid column name.'
            );
        }

        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'clause' => $clause,
            'sql' => '(' . implode(" {$operator} ", $parts) . ')'
        ];

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
        return $this->join($table, $alias, 'INNER');
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
        return $this->join($table, $alias, 'LEFT');
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
        return $this->join($table, $alias, 'RIGHT');
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
        return $this->join($table, $alias, 'CROSS');
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
        return $this->join($table, $alias, 'FULL');
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
        return $this->join($table, $alias, 'FULL OUTER');
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
     *      ->select()
     *      ->get();
     * ```
     * Generates: `LIMIT 5,10`
     */
    public function limit(int $limit, int $offset = 0): self
    {
        if($limit > 0){
            $this->maxLimit = [max(0, $offset), $limit];
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
        $this->maxLimit = [0, max(0, $limit)];

        return $this;
    }

    /**
     * Enable or disable strict conditions for query execution.
     *
     * When strict mode is enabled, certain operations (e.g., `delete`, `update`) may require a `WHERE` clause 
     * or logic operator to prevent accidental modifications of all records. This helps enforce safer query execution.
     *
     * @param bool $enable Whether to enable strict mode (default: `true`).
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
    public function strict(bool $enable = true): self
    {
       $this->isStrictMode = $enable;

       return $this;
    }

    /**
     * Sets the alias for the combined UNION subquery.
     *
     * This alias is used to wrap the UNION result in an outer SELECT statement like:
     * SELECT [alias].column FROM ( ... UNION ... ) AS [alias]
     *
     * Useful when applying filters, sorting, or pagination to the result of a UNION query.
     *
     * @param string $alias The alias to assign to the UNION result set.
     * 
     * @return self Returns the instance of the builder class.
     * @throws InvalidArgumentException If the invalid alias was provided.
     */
    public function unionAlias(string $alias): self 
    {
        $this->assertTableName(null, $alias);

        $this->unionCombineAlias = $alias;
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
     * @throws InvalidArgumentException If the column name is empty or the order is invalid.
     * 
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
        $order = strtoupper($order);
        $this->assertOrder($order, $column);

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
     * @throws InvalidArgumentException If the column name is empty or the order is invalid.
     * 
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
     * @throws InvalidArgumentException If the column name is empty or the order is invalid.
     * 
     * @see order()
     * @see descending()
     */
    public function ascending(string $column): self 
    {
        return $this->order($column, 'ASC');
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
     * @param string|Closure $operator Logical operator to combine with other HAVING clauses (default: 'AND').
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
     * @example - Parsing Raw Expression:
     * 
     * ```php
     * Builder::table('orders')
     *      ->select(['category'])
     *      ->group('category')
     *      ->having(RawExpression::sum('amount'), '>=', 1000)
     *      ->having(RawExpression::count('order_id'), '>', 10, 'OR')
     *      ->get();
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
        [$operator,,] = $this->assertOperators(__METHOD__, $operator);
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
     * @param mixed|Closure $value The value to compare against.
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
     *      ->select()
     *      ->where('status', '=', 'active')
     *      ->get();
     * ```
     * Generates: `WHERE status = 'active'`
     * 
     * ```php
     * Builder::table('users')
     *      ->select()
     *      ->where('status', '', ['active', 'disabled'])
     *      ->get();
     * ```
     * Generates: `WHERE status IN ('active', 'disabled')`
     * 
     * ```php
     * Builder::table('users')
     *      ->select()
     *      ->where('status', 'NOT', ['active', 'disabled'])
     *      ->get();
     * ```
     * Generates: `WHERE status NOT IN ('active', 'disabled')`
     * 
     *  ```php
     * Builder::table('users')
     *      ->select()
     *      ->where('status', 'NOT EXISTS', Builder::raw('(SELECT 1 FROM views WHERE id = 1)'))
     *      ->get();
     * ```
     * Generates: `WHERE status NOT EXISTS (SELECT 1 FROM views WHERE id = 1)`
     */
    public function where(string $column, string $comparison, mixed $value): self
    {
        return $this->condition('AND', $column, $comparison, $value);
    }

    /**
     * Add a raw SQL fragment to the WHERE clause.
     *
     * Accepts a string or a RawExpression object. This is useful when you need to insert
     * custom SQL that can't be built using structured conditions.
     *
     * @param RawExpression|string $sql Raw SQL fragment to append to WHERE clause.
     * 
     * @return self Return instance of builder class.
     * 
     * > **Notes:**
     * > - Use this method only when you're sure the input is safe.
     * > - You must include the proper logical operator (e.g. AND, OR) in the raw SQL yourself.
     * 
     * @example - Example:
     * 
     * ```php
     * Builder::table('users')
     *   ->select()
     *   ->whereClause('AND status <> "archived"')
     *   ->whereClause(new RawExpression('OR deleted_at IS NULL'))
     *   ->get()
     * ```
     */
    public function whereClause(RawExpression|string $sql): self
    {
        if ($sql instanceof RawExpression) {
            $sql = $sql->getExpression();
        }

        $sql = trim($sql);

        if ($sql !== '') {
            $this->options['whereRaw'][] = $sql;
        }

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
     * @param mixed|Closure $value The value to compare against.
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
     * @example Using REGEXP for partial match:
     * 
     * ```php
     * Builder::table('users')
     *      ->select()
     *      ->where('status', '=', 'active')
     *      ->and('department', 'REGEXP', 'HR|Finance|Marketing')
     *      ->get();
     * ```
     * Generates: `WHERE status = 'active' AND department REGEXP 'HR|Finance|Marketing'`
     */
    public function and(string $column, string $comparison, mixed $value): self
    {
        return $this->condition('AND', $column, $comparison, $value);
    }

    /**
     * Add a condition to the query using the `OR` operator.
     * 
     * This method appends a conditional clause where the specified column 
     * must satisfy the given comparison operator and value.
     * 
     * @param string $column The name of the column to apply the condition.
     * @param string $comparison The comparison operator to use (e.g., `=`, `>=`, `<>`, `LIKE`, `IN`, `NOT`).
     * @param mixed|Closure $value The value to compare the column against.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Using the `OR` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->select()
     *      ->or('status', '=', 'active')
     *      ->or('role', '!=', 'admin')
     *      ->get();
     * ```
     * Generates: `WHERE status = 'active' OR role != 'admin'`
     */
    public function or(string $column, string $comparison, mixed $value): self
    {
        return $this->condition('OR', $column, $comparison, $value);
    }

    /**
     * Adds a conditional clause to the query builder using scalar or array values.
     *
     * Supports both regular WHERE conditions and array-based `IN`/`NOT IN` clauses.
     *
     * @param string $clause Logical connector: typically `'AND'` or `'OR'`.
     * @param string $column The column name to apply the condition to.
     * @param string $comparison Comparison operator (`=`, `<>`, `>`, `LIKE`, `IN`, `NOT`, etc.).
     * @param mixed $value  The value to compare against. Accepts:
     *                           - Scalar types for standard comparisons
     *                           - Array for `IN`/`NOT IN` queries
     *                           - Closure for nested conditions
     *
     * @return self Returns the current builder instance.
     * @throws InvalidArgumentException If an error occurs.
     *
     * > **Note:**
     * > - When `$value` is an array, it is transformed into an `IN` or `NOT IN` clause depending on `$comparison`.
     * >    - `'IN'` → `WHERE column IN (...)`
     * >    - `'NOT'` → `WHERE column NOT IN (...)`
     *
     * @example - Example usage:
     * ```php
     * $builder = Builder::table('users')
     *     ->select()
     *     ->condition('AND', 'id', '=', 100)
     *     ->condition('OR', 'id', '=', 101)
     *     ->condition('AND', 'name', '=', 'Peter')
     *     ->condition('AND', 'roles', 'IN', ['admin', 'editor']);
     * ```
     *
     * @see where()
     * @see and()
     * @see or()
     * @see in()
     * @see notIn()
     * @see against()
     * @see clause()
     */
    public function condition(string $clause, string $column, string $comparison, mixed $value): self
    {
        if(is_array($value)){
            return $this->inArray($column, $comparison, $value, $clause);
        }

        return $this->clause($clause, $column, $comparison, $value);
    }

    /**
     * Adds a complex conditional clause to the query builder.
     *
     * Enables adding `WHERE` logic using various clause modes, 
     * and is ideal for manually constructing complex expressions.
     *
     * **Supported Modes:**
     * 
     * - `Builder::REGULAR`  — Standard comparison (e.g., `WHERE column = value`)
     * - `Builder::CONJOIN`  — Combined expressions (e.g., `WHERE (a = 1 OR b = 2)`)
     * - `Builder::NESTED`   — Deeply grouped conditions (e.g., `WHERE ((a = 1 AND b = 2) OR (c = 3))`)
     * - `Builder::AGAINST`  — Full-text match using `MATCH (...) AGAINST (...)`
     * - `Builder::INARRAY`  — Filters using `IN (...)` with array values
     *
     * @param string $clause Logical connector: typically `AND` or `OR`.
     * @param string $column The column to apply the condition on.
     * @param string $comparison Comparison operator (e.g., `=`, `<>`, `>=`, `LIKE`, etc.).
     * @param mixed|Closure $value The condition value to compare. Can be scalar or array (for `Builder::INARRAY`).
     * @param string|null $mode The clause mode. One of the supported modes (default: Builder::REGULAR`).
     *
     * @return self Returns instance for builder class.
     * @throws InvalidArgumentException If an unsupported mode is given or if `INARRAY` is used with an empty array.
     *
     * @internal Used internally by the builder to compose query conditions.
     *           Can also be called directly to manually define clauses without relying on
     *           higher-level methods like `where()`, `or()`, or `against()`.
     *           Useful when you want full control and to skip additional processing.
     *
     * @example - Example usage:
     * 
     * ```php
     * $builder = Builder::table('users')
     *     ->select()
     *     ->clause('AND', 'id', '=', 100)
     *     ->clause('OR', 'id', '=', 101)
     *     ->clause('AND', 'name', '=', 'Peter')
     *     ->clause('AND', 'roles', 'IN', ['admin', 'editor'], 'INARRAY');
     * ```
     * 
     * @see where()
     * @see and()
     * @see or()
     * @see in()
     * @see notIn()
     * @see condition()
     * @see against()
     */
    public function clause(
        string $clause,
        string $column,
        string $comparison,
        mixed $value,
        ?string $mode = null
    ): self 
    {
        [,$clause,] = $this->assertOperators(__METHOD__, null, $clause);
        
        $mode = strtoupper($mode ?? self::REGULAR);
        $modes = [self::REGULAR, self::CONJOIN, self::NESTED, self::AGAINST, self::INARRAY];
        
        if (!in_array($mode, $modes, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid clause mode "%s". Supported modes: %s.',
                $mode,
                implode(', ', $modes)
            ));
        }

        if ($mode === self::INARRAY && ($value === [] || !is_array($value))) {
            throw new InvalidArgumentException(
                'The INARRAY mode requires a non-empty array of values.'
            );
        }

        $this->conditions[] = [
            'type' => $clause,
            'mode' => $mode,
            'column' => $column,
            'value' => $value,
            'comparison' => $comparison
        ];

        return $this;
    }

    /**
     * Defines columns to be used for a full-text search match.
     *
     * This method registers a set of columns for a subsequent `AGAINST` clause in a full-text query.
     * Multiple calls to this method will stack multiple `MATCH (...) AGAINST (...)` clauses.
     *
     * @param array<int,string> $columns An array of column names to include in the full-text match.
     *                       All column names must be valid SQL column identifiers.
     *
     * @return self Returns instance for builder class.
     * @throws InvalidArgumentException If the columns array is empty or contains invalid entries.
     *
     * @example - Add match columns for full-text search:
     * 
     * ```php
     * Builder::table('blogs')
     *      ->select()
     *      ->match(['title', 'description'])
     *         ->against('fast laptop', Builder::MATCH_BOOLEAN)
     *      ->match(['title', 'description'])
     *         ->against('low laptop', Builder::MATCH_NATURAL)
     *      ->get();
     * ```
     * 
     * @example - Match against title/description and order by relevance score:
     * ```php
     * Builder::table('blogs')
     *      ->select()
     *      ->match(['title', 'description'])
     *         ->orderAgainst('wireless keyboard', Builder::MATCH_BOOLEAN, 'DESC')
     *      ->get();
     * ```
     *
     * @see against()
     * @see orderAgainst()
     */
    public function match(array $columns): self
    {
        if ($columns === [] || !isset($columns[0])) {
            throw new InvalidArgumentException('The match() method requires at least one column.');
        }
        
        $this->options['matches'][] = [
            'columns' => implode(", ", $columns)
        ];
        
        return $this;
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
     * Set the ordering of full-text search results using `MATCH ... AGAINST`.
     *
     * This method allows ranking and sorting results based on a full-text search score.
     * Useful when prioritizing rows that better match the search term.
     *
     * @param string|int|float $value The value to search against.
     * @param string|int $mode The match mode, can be a predefined constant or raw string.
     *     Constants:
     *       - Builder::MATCH_NATURAL
     *       - Builder::MATCH_BOOLEAN
     *       - Builder::MATCH_NATURAL_EXPANDED
     *       - Builder::MATCH_EXPANSION
     * @param string $order The sort direction, either "ASC" or "DESC". Defaults to "ASC".
     *
     * @return self Returns the instance of the class.
     * @throws DatabaseException If no match columns have been defined via match().
     * @throws InvalidArgumentException If the sort order is invalid.
     *
     * @see match()
     * @see against()
     */
    public function orderAgainst(
        string|int|float $value, 
        string|int $mode = self::MATCH_NATURAL, 
        string $order = 'ASC'
    ): self 
    {
        $order = strtoupper($order);
        $this->assertOrder($order);

        $this->options['match'][] = [
            'mode' => self::$matchModes[$mode] ?? $mode,
            'column' => $this->getMatchColumns(__METHOD__),
            'value' => $value,
            'order' => $order,
        ];
        return $this;
    }

    /**
     * Adds a full-text search clause using `MATCH (...) AGAINST (...)`.
     *
     * This method appends a full-text `AGAINST` clause to the query using the match columns 
     * defined via `match()`. It's typically used for searching textual content.
     *
     * @param mixed|Closure $value The value to match against. Can be a string, number, or a Closure
     *                             to defer value evaluation.
     * @param string|int $mode The match mode, can be a predefined constant or raw string.
     *     Constants:
     *       - Builder::MATCH_NATURAL
     *       - Builder::MATCH_BOOLEAN
     *       - Builder::MATCH_NATURAL_EXPANDED
     *       - Builder::MATCH_EXPANSION
     *
     * @return self Return instance of builder class.
     * @throws DatabaseException If match columns are missing or invalid.
     *
     * @see match()
     * @see orderAgainst()
     */
    public function against(mixed $value, string|int $mode = self::MATCH_NATURAL): self
    {
        return $this->clause(
            'AND', 
            $this->getMatchColumns(__METHOD__),
            self::$matchModes[$mode] ?? $mode, 
            $value,
            self::AGAINST,
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
     * 
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
     * 
     * ```php
     * Builder::table('users')
     *     ->select()
     *      ->where('country', '=', 'NG')
     *      ->nullable('address')
     *      ->get();
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
     * @param mixed|Closure $value The value to set for the column.
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
     * @throws InvalidArgumentException Throws if invalid group operator or chain clause is specified.
     *
     * @example - Group conditions:
     * 
     * ```php
     * Builder::table('fooTable')->conjoin([
     *     ['column1' => ['comparison' => '=', 'value' => 1]],
     *     ['column2' => ['comparison' => '=', 'value' => 2]]
     * ], 'OR');
     * ```
     * 
     * @example - Using Column: 
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
        [$operator, $clause, ] = $this->assertOperators(__METHOD__, $operator, $clause);

        $this->conditions[] = [
            'type' => $clause,
            'mode' => self::CONJOIN,
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
     * @example - Using Column: 
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
        [$operator, $clause, $nestedOperator] = $this->assertOperators(
            __METHOD__,
            $operator, 
            $clause, 
            $nestedOperator
        );

        $this->conditions[] = [
            'type' => $clause,
            'mode' => self::NESTED,
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
     * @param mixed|Closure $value The value to compare against.
     *
     * @return array<string,array> Returns an array representing a column structure.
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
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If the placeholder does not start with a colon `:`.
     * 
     * @example - Bind Placeholder Example:
     * 
     * ```php
     * $result = Builder::table('users', 'u')
     *     ->select()
     *     ->innerJoin('orders', 'o')
     *         ->on('o.order_user_id', '=', 'u.user_id')
     *         ->on('o.order_id', '=', ':order_number')
     *         ->bind(':order_number', 13445)
     *     ->where('u.user_id', '=', 100)
     *     ->get();
     * ```
     */
    public function bind(string $placeholder, mixed $value): self 
    {
        if (!str_starts_with($placeholder, ':')) {
            throw new InvalidArgumentException(sprintf(
                'Invalid param placeholder: %s. Placeholder must start with colon prefix ":" (e.g., "%s")',
                $placeholder,
                $this->trimPlaceholder($placeholder, false)
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
     * 
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
     * @param Closure|array<int,string|int|float> $values An array or Closure that returns array of values to check against.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If values is not provided.
     * @throws JsonException If an error occurs while encoding values.
     */
    public function in(string $column, Closure|array $list, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, 'IN', $list, self::INARRAY);
    }

    /**
     * Adds a IN condition to the query with support for `IN`, `NOT IN`, or other wrappers.
     *
     * This is used for matching one or more values against a comma-separated list column,
     * such as checking if a value exists within a set stored in a single field.
     *
     * @param string $column The column name to search within.
     * @param string $expression A SQL expression modifier (e.g., `IN` or `NOT`).
     * @param Closure|array<int,string|int|float> $values An array or Closure that returns array of values to check against.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     *
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If values is not provided.
     * @throws JsonException If an error occurs while encoding values.
     * 
     * @example - Example:
     * ```php
     * Builder::table('languages')
     *     ->select()
     *     ->inArray('post_tags', 'NOT', ['php', 'sql'])
     *     ->get();
     * ```
     * Will generate a clause like: `NOT IN(...)
     */
    public function inArray(
        string $column, 
        string $expression, 
        Closure|array $values, 
        string $clause = 'AND'
    ): self
    {
        $find = match($expression){
            '=', '==' => '',
            '!=', '<>' => 'NOT',
            default => preg_replace('/\bIN\b/i', '', $expression)
        };

        return $this->clause($clause, $column, "{$find} IN", $values, self::INARRAY);
    }

    /**
     * Adds find `NOT IN` condition to search using `NOT IN ()` expression.
     *
     * This method creates a condition where the specified column's value is not in the provided list.
     *
     * @param string $column The name of the column to check against the list.
     * @param Closure|array<int,string|int|float> $values An array or Closure that returns array of values to check against.
     * @param string $clause Logical clause to chain with (e.g., `AND`, `OR`).
     *
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException If the provided list is empty.
     * 
     * @example - Example:
     * 
     * ```php
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->notIn('state', ['Enugu', 'Lagos', 'Abuja']);
     * ```
     */
    public function notIn(string $column, Closure|array $list, string $clause = 'AND'): self
    {
        return $this->clause($clause, $column, 'NOT IN', $list, self::INARRAY);
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
        $this->conditions[] = [
            'type' => $clause, 
            'mode' => self::INSET,
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
            $this->returns = $type;
            return $this;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid return type: "%s". Expected: "array", "object" or "stmt".', 
            $type
        ));
    }

    /**
     * Enable or disable flag distinct selection for query executions.
     *
     * @param bool $distinct Whether to apply distinct selection (default: true).
     * 
     * @return self Return instance of builder class.
     * 
     * @see select()
     * @see find()
     */
    public function distinct(bool $distinct = true): self 
    {
        $this->isDistinct = $distinct;
        return $this;
    }

    /**
     * Enable or disable replace feature when inserting or copying records in the current database table.
     * 
     * **Applicable To:**
     * 
     * `insert()` - Before calling insert() method.
     * `copy()`  - Before to() method.
     * 
     * If enabled, `insert` method will replaces existing records, 
     * by first **deleting** existing rows with the same primary key or unique key before inserting new ones. 
     * 
     * @param bool $useReplace Set to true to use `REPLACE` instead of `INSERT` (default: true).
     * 
     * @return self Return instance of builder class.
     * 
     * @see insert()
     * @see copy()
     * 
     * > **Note:** Enabling this may lead to unintended data loss, especially if foreign key constraints exist.
     * > **Warning:** Since `replace` removes and re-inserts data, it can reset auto-increment values 
     * and trigger delete/insert events instead of update events.
     */
    public function replace(bool $useReplace = true): self
    {
        $this->isReplace = $useReplace;

        return $this;
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
     *        - `Builder::DEBUG_NONE`: Disable debugging.
     *
     * @return self Return instance of builder class.
     */
    public function debug(int $mode = self::DEBUG_BUILDER_DUMP): self
    {
        $this->debugMode = $mode;
        $this->debugTitles = [];

        if(
            $mode === self::DEBUG_BUILDER || 
            $mode === self::DEBUG_BUILDER_DUMP ||
            $mode === self::DEBUG_NONE
        ){
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
     * - `Resource`  are read using `stream_get_contents` and returned string contents.
     * - Strings can optionally be escaped with `addslashes()` and/or wrapped in quotes.
     * 
     * @param mixed $value The value to escape.
     * @param bool $enQuote If true, wraps the value in single quotes unless it's JSON.
     * @param bool $strict Whether to use strict type casting (default: false).
     *       If true:
     *        - `null` returns `null` instead of `'NULL'`
     *        - `bool` returns `true|false` instead of `1|0`
     *        - `resource` returns `content` instead of `base64` encoded
     *        - Empty arrays return `[]` instead of `'[]'`
     *       If false:
     *        - `null` returns `'NULL'` (as string)
     *        - `bool` returns `1|0`
     *        - `resource` returns `base64` encoded contents.
     *        - Empty arrays return `'[]'`
     * @param bool $numericCheck If true, numeric strings are cast to int/float:
     *        - Enables `+0` cast and `JSON_NUMERIC_CHECK` for JSON encoding.
     *        - If false, numeric strings are preserved as-is.
     * @param bool $addSlashes If true, string values are passed through `addslashes()`.
     * 
     * @return string|int|float|bool|null Returns a properly escaped and type-safe value.
     * @throws JsonException If JSON encoding fails for arrays or objects.
     * @throws DatabaseException If value is resource and failed to read content.
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

        if ($value === (object)[]) {
            return $strict ? (object)[] : '{}';
        }

        if ($value instanceof RawExpression) {
            return $value->getExpression();
        }

        if ($value instanceof Closure) {
            return self::escape($value(), $enQuote, $strict, $numericCheck, $addSlashes);
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

        if (is_resource($value)) {
            $stream = stream_get_contents($value);

            if ($stream === false) {
                throw new DatabaseException(
                    'Failed to read from resource stream.',
                    DatabaseException::RUNTIME_ERROR
                );
            }
            
            if ($strict) {
                return $stream;
            }

            $encoded = base64_encode($stream);

            return $enQuote ? "'{$encoded}'" : $encoded;
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
     * 
     * > **Note:** By default caching is enabled once you call the `cache` method.
     */
    public function cacheable(bool $enable): self
    {
        $this->isCacheable = $enable;
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
     * Enables or disables safe mode for write and altering current table.
     *
     * When enabled, the next query operation will automatically be wrapped in an internal transaction 
     * if no explicit transaction is already active.
     * 
     * @param bool $enable Whether to enable or disable safe mode.
     * 
     * @return self Returns the current builder instance.
     * 
     * Supported:
     * 
     * @see insert()
     * @see update()
     * @see delete()
     * @see drop()
     * @see truncate()
     * @see copy()
     * @see temp()
     * @see execute()
     * 
     * @example - Using safe mode:
     * 
     * Automatically commits or rolls back.
     * 
     * ```php
     *     $tbl = Builder::table('users')
     *      ->safeMode()
     *      ->insert([...]);
     * ```
     */
    public function safeMode(bool $enable = true): self 
    {
        $this->isSafeMode = $enable;
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
     * 
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
     * 
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
     * Initialize result caching for the current database query or operation.
     *
     * This method configures a cache key and optionally sets the storage location, expiration,
     * subfolder path (for file-based cache), and persistent ID (for memory-based cache).
     * 
     * > If global caching is enabled via `cacheable()` method.
     *
     * @param string $key Unique key to identify the cache item.
     * @param string|null $storage Optional cache storage name. 
     *                  Defaults to the current table or `'capture'`.
     * @param DateTimeInterface|int $expiry Cache expiration time (default: 7 days).
     * @param string|null $subfolder Optional subdirectory for file-based cache (default: `'database'`).
     * @param string|null $persistentId Optional ID for memory-based cache connections (default: `'__database_builder__'`).
     *
     * @return self Returns the current builder instance.
     * @throws CacheException If a cache initialization or read operation fails.
     */
    public function cache(
        string $key,
        ?string $storage = null,
        DateTimeInterface|int $expiry = 7 * 24 * 60 * 60,
        ?string $subfolder = null,
        ?string $persistentId = null
    ): self 
    {
        if (!$this->isCacheable) {
            return $this;
        }

        if ($this->isCollectMetadata || $this->unions !== []) {
            $this->options['current']['cache'] = [
                $key, $storage, $expiry, $subfolder, $persistentId
            ];
            return $this;
        }

        self::$cache ??= $this->newCache($storage, $subfolder, $persistentId);
        self::$cache->setExpire($expiry);

        $this->cacheKey = md5($key);
        $this->isCacheReady = true;
        $this->hasCache = (
            self::$cache->hasItem($this->cacheKey) &&
            !self::$cache->hasExpired($this->cacheKey)
        );

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
     * @param bool $escapeValues Whether to escape insert values if `$usePrepare` is true (default: true).
     * 
     * @return int Returns the number of affected rows, 0 if no rows were inserted.
     * @throws DatabaseException If an error occurs during the insert operation or if the provided values are not associative arrays.
     * @throws JsonException If an error occurs while encoding array values to JSON format.
     * 
     * @example - Insert example:
     * 
     * ```php
     * Builder::table('logs')
     *      //->replace(true) Optional replace 
     *      //->onDuplicate('column', '=', Builder::raw('VALUES(columns)'))
     *      //->ignoreDuplicate()true
     *      ->insert([
     *          [
     *              'message' => 'User login',
     *              'created_at' => Builder::raw('NOW()')
     *          ]
     *          // More rows
     *      ]);
     * ```
     * 
     * @example - Insert using transaction:
     * 
     * ```php
     * $tbl = Builder::table('users')
     *      //->replace(true) Optional replace 
     *      ->transaction();
     * 
     * $inserted = $tbl->insert([
     *     ['name' => 'Peter', 'age' => 33],
     *     ['name' => 'John Deo', 'age' => 40]
     *     // More rows
     * ]);
     * 
     * if($inserted){
     *      $tbl->commit();
     * }else{
     *      $tbl->rollback();
     * }
     * ```
     */
    public function insert(array $values, bool $usePrepare = true, bool $escapeValues = true): int
    {
        if ($values === []) {
            return 0;
        }

        if (!isset($values[0])) {
            $values = [$values];
        }
        
        $inserted = 0;
        $type = $this->isReplace ? 'REPLACE' : 'INSERT';

        if (!is_associative($values[0])) {
            throw new DatabaseException(
                sprintf('Invalid %s values, values must be an associative array.', $type), 
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        $this->assertInsertOptions();
        $length = count($values);
        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        try {
            $inserted = $usePrepare
                ? $this->executeInsertPrepared($values, $type, $length, $escapeValues) 
                : $this->executeInsertQuery($values, $type, $length);
        } catch (Throwable $e) {
            $this->resolveException($e);
            return 0;
        }

        return $this->finishInsert($useTransaction, $inserted);
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
     * @param mixed|Closure $value The new value or increment amount.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Example usage:
     * 
     * ```php
     * use \Luminova\Database\RawExpression;
     * 
     * Builder::table('users')
     *     ->onDuplicate('points', '=', Builder::raw('VALUES(points)'))
     *     ->onDuplicate('points', '+=', 10) // Increment points by 10 on duplicate key
     *     ->onDuplicate('email', '=', 'new@example.com') // Update email on duplicate key
     *     ->insert([
     *         [
     *              'id' => 1, 
     *              'name' => 'Alice', 
     *              'points' => 50, 
     *              'email' => 'alice@example.com'
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
     * 
     * ```php
     * Builder::table('users')
     *     ->ignoreDuplicate()
     *     ->insert([
     *         [
     *              'id' => 1, 
     *              'name' => 'Alice', 
     *              'points' => 50, 
     *              'email' => 'alice@example.com'
     *         ]
     *     ]);
     * ```
     */
    public function ignoreDuplicate(bool $ignore = true): self 
    {
        $this->isIgnoreDuplicate = $ignore;
        return $this;
    }

    /**
     * Executes a prepared `copy()` query and inserts its result into the specified target table.
     *
     * This method finalizes a `copy()` operation by executing the selection and inserting
     * the results into another table using either `INSERT`, `INSERT IGNORE`, or `REPLACE`.
     *
     * @param string $table Target table to insert copied data into.
     * @param array<int,string> $columns Target table columns to insert data into.
     *
     * @return int Return the number of affected rows, or 0 if no rows were inserted or if debugging is enabled.
     * @throws InvalidArgumentException If the target table name is empty.
     * @throws DatabaseException If copy mode isn't active, or if column mismatch occurs.
     * @throws JsonException If copy operation involves JSON-encodable values and encoding fails.
     *
     * @see copy()
     *
     * > warning Ensure that source and destination columns match in count and structure.
     */
    public function to(string $table, array $columns = ['*']): int
    {
        $table = trim($table);

        self::assertTableName($table, null);
        $this->assertInsertOptions();

        $isCopy = $this->handler['isCopy'] ?? false;
        $fromColumns = $this->handler['columns'] ?? [];

         if (!$isCopy || $this->handler === []) {
            throw new DatabaseException(
                'The copy(...) method must be called before to(...).',
                DatabaseException::BAD_METHOD_CALL
            );
        }

        if ($columns === [] || ($fromColumns !== ['*'] && count($fromColumns) !== count($columns))) {
            throw new DatabaseException(
                ($columns === [] || $fromColumns === []) 
                    ? 'Source and destination columns must not be empty.'
                    : 'Mismatch between source and destination column counts.',
                DatabaseException::INVALID_ARGUMENTS
            );
        }

        $this->isCollectMetadata = true;
        self::$lastInsertId = null;

        if(!$this->get()){
            return 0;
        }

        $metadata = $this->getOptions('current');
        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';
        $this->rawQuery = $this->isReplace ? 'REPLACE' : 'INSERT';
        $this->rawQuery .= " {$ignore}INTO {$table}";

        if($fromColumns !== ['*']){
            $this->rawQuery .= ' (' . trim(implode(',', $columns), ',') . ')';
        }

        $this->isCollectMetadata = false;
        $this->rawQuery .= " {$metadata['sql']}";

        $placeholders = $metadata['params'] ?? [];
        $this->rawQuery .= $this->buildDuplicateUpdateClause($placeholders);

        if($this->debugMode !== self::DEBUG_NONE){
            ($this->debugMode === self::DEBUG_BUILDER)
                ? $this->setDebugInformation($this->rawQuery, 'copy')
                : $this->echoDebug($this->rawQuery, 'SQL QUERY');

            return 0;
        }

        $cacheable = $this->isCacheable;
        $inserted = 0;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        try {
            $inserted = ($placeholders === [])
                ? ($this->db->query($this->rawQuery)->ok() ? $this->db->rowCount() : 0)
                : $this->cacheable(false)
                    ->execute($placeholders, RETURN_COUNT, FETCH_NUM, false);

            $this->isCacheable = $cacheable;
        } catch (Throwable $e) {
            $this->isCacheable = $cacheable;
            $this->resolveException($e, true);
        }

        return $this->finishInsert($useTransaction, $inserted);
    }

    /**
     * Combines the current query with another using the `UNION` operator.
     *
     * Ensures both queries have the same number of columns. Resulting rows will be distinct.
     *
     * @param Builder|Closure $union Another Builder instance or closure that return builder to union with.
     *
     * @return self Return current parent Builder object.
     * @throws DatabaseException If error occurs.
     * 
     * @see columns()
     *
     * @example - Union Example:
     * 
     * ```php
     * $active = Builder::table('users')->select(['id', 'email'])
     *     ->where('status', '=', 'active');
     * 
     * $inactive = Builder::table('users')->select(['id', 'email'])
     *     ->where('status', '=', 'inactive');
     * 
     * $all = $active->union($inactive)
     *     ->descending('id')
     *     ->limit(10)
     *     ->get();
     * ```
     * > **Note:** When using union tables, do not call `get`, `fetch` or `stmt` before adding table to another.
     * > Always call after all tables has been added.
     */
    public function union(Builder|Closure $union): self
    {
        return $this->doUnionTables($union);
    }

    /**
     * Combines the current query with another using the `UNION ALL` operator.
     *
     * Unlike `UNION`, this includes duplicate rows from both queries.
     *
     * @param Builder|Closure $union Another Builder instance or closure that return builder to union with.
     *
     * @return self Return current parent Builder object.
     * @throws DatabaseException If error occurs.
     * 
     * @see columns()
     *
     * @example - Union All example:
     * 
     * ```php
     * $active = Builder::table('users')->select(['id', 'email'])
     *     ->where('status', '=', 'active');
     * 
     * $inactive = Builder::table('users')->select(['id', 'email'])
     *     ->where('status', '=', 'inactive');
     * 
     * $all = $active->unionAll($inactive)->get();
     * ```
     * > **Note:** When using union tables, do not call `get`, `fetch` or `stmt` before adding table to another.
     * > Always call after all tables has been added.
     */
    public function unionAll(Builder|Closure $union): self
    {
        return $this->doUnionTables($union, true);
    }

    /**
     * Sets the columns to be used in a UNION/UNION ALL operation.
     *
     * This method specifies which columns should be included when combining
     * results from multiple queries using UNION or UNION ALL. By default,
     * all columns ('*') are included.
     *
     * @param array $columns The columns to include in the union operation.
     *                      Defaults to ['*'] (all columns).
     *                      Example: ['id', 'name', 'email']
     *
     * @return self Returns instance of builder class.
     * @see union()
     * @see unionAll()
     *
     * @example - Basic usage with UNION ALL:
     * 
     * ```php
     * $active = Builder::table('users')
     *      ->select(['id', 'email'])
     *     ->where('status', '=', 'active');
     * 
     * $inactive = Builder::table('users')
     *      ->select(['id', 'email'])
     *     ->where('status', '=', 'inactive');
     * 
     * $result = $active->unionAll($inactive)
     *     ->columns(['id', 'email']) // Explicitly set UNION columns
     *     ->get();
     * ```
     * 
     * @example - Using with different column selections:
     * 
     * ```php
     * $employees = Builder::table('employees')
     *      ->select(['emp_id AS id', 'full_name AS name']);
     * $contractors = Builder::table('contractors')
     *      ->select(['contractor_id' AS id, 'contractor_name AS name']);
     * 
     * $result = $employees->union($contractors)
     *     ->unionAlias('combined')
     *     ->columns(['combined.id', 'combined.name']) / Maps and aligns columns
     *     ->where('combined.status', '=', 'active')
     *     ->limit(5)
     *     ->get();
     * ```
     * 
     * @example - Less recommended: 
     * 
     * ```php
     * $employees = Builder::table('employees')
     *      ->select(['emp_id', 'full_name']);
     * $contractors = Builder::table('contractors')
     *      ->select(['contractor_id', 'contractor_name']);
     * 
     * $result = $employees->union($contractors)
     *     ->columns(['emp_id AS id', 'full_name AS name']) // Maps and aligns columns
     *     ->where('status', '=', 'active')
     *     ->limit(5)
     *     ->get();
     * ```
     */
    public function columns(array $columns = ['*']): self
    {
        $this->options['unionColumns'] = $columns;
        return $this;
    }
 
    /**
     * Executes an SQL query that was previously set using the `query()` method.
     * 
     * **Available Return Modes:**
     * 
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
     * @param array<string,mixed>|null $placeholder An optional associative array of placeholder key-pair to bind with query.
     * @param int $returnMode The result return mode (default: `RETURN_ALL`).
     * @param int $fetchMode The result fetch mode (default: `FETCH_OBJ`).
     * @param bool $escapePlaceholders Whether to escape placeholders if any (default: `true`).
     * 
     * @return mixed|DatabaseInterface Returns the query result, a database driver interface for prepared statement object, or `false` on failure.
     * @throws DatabaseException If `execute()` is called without setting a query.
     * 
     * @see query()
     * 
     * @example - Executing a prepared query:
     * 
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
    public function execute(
        ?array $placeholder = null, 
        int $returnMode = RETURN_ALL, 
        int $fetchMode = FETCH_OBJ,
        bool $escapePlaceholders = false
    ): mixed 
    {
        if($this->rawQuery === ''){
            throw new DatabaseException(
                sprintf(
                    'Cannot call "%s" without a prepared SQL query. Use "%s" first.',
                    '$stmt->execute(...)',
                    'Builder::query(...)'
                ),
                DatabaseException::VALUE_FORBIDDEN
            );
        }

        $response = $this->getFromCache($returnMode);

        if($response !== null){
            return $response;
        }

        try {
            $response = $this->executeRawSqlQuery(
                $this->rawQuery, 
                $placeholder ?? [], 
                $returnMode, 
                $fetchMode,
                $escapePlaceholders
            );

            if($returnMode !== RETURN_STMT){
                $this->cacheResultIfValid($response);
            }

            return $response;
        } catch (Throwable $e) {
            $this->resolveException($e);
        }

        return null;
    }

    /**
     * Executes the current query and returns result(s).
     *
     * **Applicable To:**
     * 
     * - `select()`
     * - `find()`
     * - `total()`
     * - `sum()`
     * - `average()`
     *
     * @param int $fetchMode The result fetch mode (e.g., `FETCH_OBJ`, `FETCH_ASSOC`), (default: `FETCH_OBJ`).
     * @param int|null $returnMode Optional return mode (e.g., `RETURN_ALL`, `RETURN_NEXT`). 
     *                  Defaults to internal handler or `RETURN_ALL`.
     *
     * @return mixed Return the query result on success, or `false`/`null` on failure.
     * @throws DatabaseException If no query to execute or execution fails.
     * 
     * @see fetch()
     * @see stmt()
     * @see promise()
     * 
     * @example - Get example:
     * 
     * ```php
     * $result = Builder::table('users')
     *      ->select(['email', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get(FETCH_OBJ);
     * ```
     */
    public function get(int $fetchMode = FETCH_OBJ, ?int $returnMode = null): mixed
    {
        $this->assertHandler(__METHOD__);
        
        $returnMode = $this->handler['returns'] 
            ?? $returnMode 
            ?? RETURN_ALL;

        $result = $this->getFromCache($returnMode);

        if($result !== null){
            return $result;
        }

        if($assert = ($this->handler['assert'] ?? null) !== null){
            $this->assertStrictConditions($assert, true);
        }

        if(!$this->isCollectMetadata && $this->unions !== []){
            [$sql, $placeholders] = $this->compileTableUnions();
            
            $this->unions = [];
            $this->options['unionColumns'] = [];

            $query = self::query($sql)
                ->closeAfter($this->closeConnection)
                ->cacheable($this->isCacheable);

            if($this->isCacheable && ($cache = $this->options['current']['cache'])){
                $query->cache(...$cache);
            }

            if($this->returns){
                $query->returns($this->returns);
            }

            return $query->execute($placeholders, $returnMode, $fetchMode, false);
        }

        return $this->buildExecutableStatement(
            $this->handler['sql'] ?? '', 
            $this->handler['method'], 
            $this->handler['columns'] ?? ['*'], 
            $returnMode, 
            $fetchMode
        );
    }

    /**
     * Executes and fetch one row at a time (for while loops or streaming).
     * 
     * **Applicable To:**
     * 
     * - `select()`
     * - `find()`
     * - `total()`
     * - `sum()`
     * - `average()`
     * 
     * @param int $mode Database fetch result mode FETCH_* (default: `FETCH_OBJ`).
     * 
     * @return object|null|array|int|float|bool Return selected records, otherwise false if execution failed.
     * @throws DatabaseException If no query to execute or execution fails.
     * 
     * > **Note:** Fetch method uses statement for execution, so it doesn't support query result caching.
     * 
     * @see get()
     * @see stmt()
     * @see promise()
     * 
     * @example - Statement example:
     * 
     * ```php
     * $stmt = Builder::table('users')
     *      ->select(['email', 'name'])
     *      ->where('country', '=', 'NG');
     * 
     * while($row = $stmt->fetch(FETCH_OBJ)){
     *      echo $row->email;
     * }
     * $stmt->freeStmt()
     * ```
     */
    public function fetch(int $mode = FETCH_OBJ): mixed 
    {
        $this->assertHandler(__METHOD__);

        return ((self::$stmt instanceof DatabaseInterface && self::$stmt->ok()) 
            ? self::$stmt 
            : $this->stmt()
        )->fetch(RETURN_STREAM, $this->getFetchMode($mode));
    }

    /**
     * Executes the current query and returns results wrapped in a Promise.
     * 
     * This method allows asynchronous handling of database operations when used with. It resolve or reject if error occurs during execution.
     * 
     * **Applicable To:**
     * 
     * - `select()`
     * - `find()`
     * - `total()`
     * - `sum()`
     * - `average()`
     *
     * @param int $fetchMode The fetch mode (default: `FETCH_OBJ`)
     *                 Options:
     *                 - FETCH_ASSOC: Associative array
     *                 - FETCH_OBJ: Standard object
     *                 - FETCH_CLASS: Custom class instances
     * @param int|null $returnMode Optional return mode (e.g., `RETURN_ALL`, `RETURN_NEXT`). 
     *                  Defaults to internal handler or `RETURN_ALL`.
     * 
     * @return PromiseInterface Returns a promise that resolves with query results.
     * 
     * @see get()
     * @see stmt()
     * @see fetch()
     * 
     * @example - Promise example:
     * 
     * ```php
     * Builder::table('users')
     *      ->select(['email', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->promise(FETCH_OBJ)
     *      ->then(function(mixed $result){
     *          echo $result->name;
     *      })->catch(function (\Throwable $e) {
     *          echo $e->getMessage();
     *      });
     * ```
     */
    public function promise(int $fetchMode = FETCH_OBJ, ?int $returnMode = null): PromiseInterface 
    {
        return new Promise(function (callable $resolve, callable $reject) use($fetchMode, $returnMode): void {
            try{
                $this->assertHandler('promise');

                $result = $this->get($fetchMode, $returnMode);
                $resolve($result);
            }catch(Throwable $e){
                $reject($e);
            }
        });
    }

    /**
     * Executes the current query and returns prepared statement object.
     * 
     * **Applicable To:**
     * 
     * - `select()`
     * - `find()`
     * - `total()`
     * - `sum()`
     * - `average()`
     * 
     * @return DatabaseInterface|null Return prepared statement if query is successful otherwise null.
     * @throws DatabaseException If no query to execute or execution fails.
     * 
     * > **Note:** Statement doesn't support query result caching.
     * 
     * @see get()
     * @see fetch()
     * @see promise()
     * 
     * @example - Statement example:
     * 
     * ```php
     * $stmt = Builder::table('users')
     *      ->select(['email', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->stmt();
     * 
     * $result = $stmt->fetchAll(FETCH_OBJ);
     * $stmt->freeStmt() // Or $stmt->free();
     * ```
     * 
     * @example - Statement with Class mapping:
     * 
     * ```php
     * $stmt = Builder::table('users')
     *      ->find(['email', 'name'])
     *      ->where('id', '=', 001)
     *      ->stmt();
     * 
     * $user = $stmt->fetchObject(User::class);
     * $stmt->freeStmt() // Or $stmt->free();
     * ```
     */
    public function stmt(): ?DatabaseInterface
    {
        $this->assertHandler(__METHOD__);

        $this->returns = self::RETURN_STATEMENT;
        $this->handler['method'] = 'stmt';
        $this->handler['returns'] = RETURN_STMT;

        self::$stmt = $this->get(FETCH_OBJ, RETURN_STMT);

        if(self::$stmt instanceof DatabaseInterface && self::$stmt->ok()){
            return self::$stmt;
        }

        $this->freeStmt();
        $this->reset();
        return null;
    }

    /**
     * Executes the current query to determine if a records exists in selected table.
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
        $result = $this->getFromCache(RETURN_NEXT);

        if($result !== null){
            return (bool) $result;
        }

        return (bool) $this->buildExecutableStatement(
            ' 1', 'total',
            ['*'], RETURN_NEXT // will be ignored
        );
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
        $query = Alter::getTableExists($this->db->getDriver());
        $stmt = $this->db->prepare("SELECT 1 FROM {$query}")
            ->bind(':tableName', $this->tableName);
        $stmt->execute();
        
        return $stmt->ok() && !empty($stmt->fetch(RETURN_NEXT, FETCH_COLUMN));
    }

    /**
     * Add query to calculate the total number of records in selected table.
     * 
     * When `get` method is called, it returns `int`, the total number of records in table, otherwise `0` if no result.
     * 
     * **Applicable To:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param string $column The column to index calculation (default: *).
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Get the number of users in country `NG`:
     * 
     * ```php
     * $total = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->total()
     *      ->get();
     * ```
     */
    public function total(string $column = '*'): self 
    {
        $this->handler = [
            'sql' => " COUNT({$column})",
            'method' => 'total'
        ];

        return $this;
    }

    /**
     * Add query to calculate the total sum of a numeric column in the table.
     * 
     * When `get` method is called, it returns `int|float`, the total sum columns, otherwise 0 if no result.
     * 
     * **Applicable To:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param string $column The column to calculate the sum.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Get the total sum of users votes in country `NG`:
     * 
     * ```php
     * $votes = Builder::table('users')
     *      ->sum('votes')
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     */
    public function sum(string $column): self
    {
        $this->handler = [
            'sql' => " SUM({$column}) AS totalCalc",
            'method' => 'sum'
        ];
        return $this;
    }

    /**
     * Add query to calculate the average value of a numeric column in the table.
     * 
     * When `get` method is called, it returns `int|float`, the total average of columns, otherwise 0 if no result.
     * 
     * **Applicable To:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param string $column The column to calculate the average.
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Get the total average of users votes in country `NG`:
     * 
     * ```php
     * $votes = Builder::table('users')
     *      ->average('votes')
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     */
    public function average(string $column): self
    {
        $this->handler = [
            'sql' => " AVG({$column}) AS totalCalc",
            'method' => 'average'
        ];

        return $this;
    }

    /**
     * Add query to select multiple records from table.
     * 
     * When `get` method is called, it returns `object|null|array|int|float|bool`, 
     * the selected rows, otherwise false if execution failed.
     * 
     * **Applicable To:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param array<int,string> $columns The table columns to select (e.g, `['foo', 'bar']` or ['*']).
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Get the all users from country `NG`:
     * 
     * ```php
     * $users = Builder::table('users')
     *      ->select(['votes', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     */
    public function select(array $columns = ['*']): self 
    {
        $this->handler = [
            'sql' => '',
            'columns' => $columns,
            'method' => 'select'
        ];
        return $this;
    }

    /**
     * Add query to select a single/next record from table.
     * 
     * When `get` method is called, it returns `object|null|array|int|float|bool`, 
     * the selected single row, otherwise false if execution failed.
     * 
     * **Applicable To:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param array<int,string> $columns The table columns to select (e.g, `['foo', 'bar']` or ['*']).
     * 
     * @return self Return instance of builder class.
     * 
     * @example - Get a single user from country `NG`:
     * 
     * ```php
     * $user = Builder::table('users')
     *      ->find(['votes', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     */
    public function find(array $columns = ['*']): mixed 
    {
        $this->handler = [
            'sql' => '',
            'columns' => $columns,
            'method' => 'find',
            'returns' => RETURN_NEXT,
            'assert' => __METHOD__
        ];
        return $this;
    }

    /**
     * Prepares a selection query to copy data from the current table.
     *
     * This method selects the specified columns in preparation for copying them into another table
     * using the `to()` method.
     *
     * **Usage:** 
     * 
     * - Must be followed by `to()`.
     *
     * @param array<int,string> $columns List of columns to select for copying (defaults: `['*']`).
     * 
     * @return self Returns the current Builder instance.
     * 
     * @see to()
     *
     * @example - Prepare a copy of specific columns:
     * 
     * ```php
     * $result = Builder::table('users')
     *     ->copy(['id', 'email', 'created_at'])
     *     //->replace(true) // Optionally use REPLACE instead of INSERT
     *     ->where('id', '=', 100)
     *     ->onDuplicate('email', '=', RawExpression::values('email'))
     *     ->to('backup_users', ['id', 'email', 'created_at']);
     * ```
     */
    public function copy(array $columns = ['*']): self
    {
        $this->handler = [
            'sql' => '',
            'columns' => $columns,
            'method' => 'select',
            'isCopy' => true
        ];

        return $this;
    }

    /**
     * Execute query to update table with columns and values.
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
     * @example - Update table with columns and values:
     * 
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

        $sql = "UPDATE {$this->tableName}";
        $sql .= $this->tableAlias ? " AS {$this->tableAlias}" : '';
        $sql .= $this->getJoinConditions();
        $sql .= ' SET ' . $this->buildPlaceholder($columns, true);
        $this->buildConditions($sql);
        $this->addRawWhereClause($sql);

        $limit = $this->maxLimit[1] ?? 0;

        if($limit > 0){
            $sql .= " LIMIT {$limit}";
        }

        if($this->debugMode !== self::DEBUG_NONE){
            if($this->debugMode === self::DEBUG_BUILDER){
                $this->setDebugInformation($sql, 'update', $columns);
                return 0;
            }

            $this->echoDebug($sql, 'SQL QUERY');
        }

        $response = 0;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        try {
            $this->db->prepare($sql);
            $this->bindStrictColumns($columns);
            $this->bindConditions();
            $this->bindJoinPlaceholders();

            if($this->debugMode !== self::DEBUG_NONE){
                $this->reset();
                return 0;
            }

            $response = $this->db->execute() ? $this->db->rowCount() : 0;
        } catch (Throwable $e) {
            $this->resolveException($e);
            return 0;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($response > 0){
                return $this->commit() ? $response : 0;
            }

            $this->rollback();
            return 0;
        }
        
        $this->reset();
        return $response;
    }

    /**
     * Execute query to delete records from the table.
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

        $alias = $this->tableAlias ? " AS {$this->tableAlias}" : '';
        $sql = match ($this->db->getDriver()) {
            'pgsql' => "DELETE FROM {$this->tableName} USING {$this->tableName}{$alias}",
            default => "DELETE {$this->tableAlias} FROM {$this->tableName} {$this->tableAlias}",
        };
        $sql .= $this->getJoinConditions();

        try {
            return (int) $this->getStatementExecutionResult($sql, 'delete');
        } catch (Throwable $e) {
            $this->resolveException($e);
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
     * @see inTransaction()
     * 
     * @example - Transaction:
     * 
     * ```php
     * $tbl = Builder::table('users')
     *  ->transaction()
     *  ->where('country', '=', 'NG');
     * 
     * if($tbl->update(['suburb' => 'Enugu'])){
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
            return true;
        }

        DatabaseException::throwException(sprintf(
                'Transaction failed to start%s (flags: %d)', 
                $name ? " for \"$name\"" : '', 
                $flags
            ),
            DatabaseException::DATABASE_TRANSACTION_FAILED
        );

        return false;
    }

    /**
     * Checks if a transaction is currently active.
     *
     * @return bool Returns true if a transaction is active, false otherwise.
     * 
     * @see commit()
     * @see rollback();
     * @see transaction()
     */
    public function inTransaction(): bool 
    {
        return ($this->db instanceof DatabaseInterface) && $this->db->inTransaction();
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
     * @see rollback()
     * @see inTransaction()
     */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        $commit = false;

        if($this->inTransaction()){
            $commit = $this->db->commit($flags, $name);
        }

        $this->reset();
        return $commit;
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
     * @see commit()
     * @see inTransaction()
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $rollback = false;

        if($this->inTransaction()){
            $rollback = $this->db->rollback($flags, $name);
        }

        $this->reset();
        return $rollback;
    }

    /**
     * Releases an active database transaction if one is in progress.
     * 
     * This method performs a rollback only if a transaction is currently open.
     * It is safe to call regardless of whether a transaction exists.
     * 
     * Useful for cleaning up unused or failed transactions in `finally` blocks,
     * or in cases where safe mode or conditional transactional logic is used.
     * 
     * @param int $flags Optional flags to pass to the rollback operation (driver-specific).
     * @param string|null $name Optional savepoint name if partial rollback is supported.
     * 
     * @return bool Returns true if no transaction was active or rollback succeeded, false on rollback failure.
     * @see rollback()
     * 
     * @internal - Used internally to release transaction, returning false instead exception if error.
     */
    public function release(int $flags = 0, ?string $name = null): bool 
    {
        if (!$this->inTransaction()) {
            return true;
        }

        try {
            return $this->db->rollback($flags, $name);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Rename the current table to a new name.
     *
     * @param string $to The new table name.
     * 
     * @return bool Return true if the rename operation was successful, false otherwise.
     * @throws DatabaseException If the database driver is unsupported.
     * 
     * @example - Rename table name.
     * 
     * ```php
     * $renamed = Builder::table('users')
     *      ->rename('new_users');
     * ```
     */
    public function rename(string $to): bool 
    {
        self::assertTableName($to);
        $sql = Alter::getBuilderTableRename($this->db->getDriver(), $this->tableName, $to);

        return (bool) $this->db->exec($sql);
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
     * @return self Returns the current Builder instance.
     * @throws InvalidArgumentException If invalid lock type is given.
     * 
     * > **Note:** Must be used inside a transaction.
     *
     * @example Lock rows for update (exclusive lock):
     * 
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
     * 
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
 
        $this->lock = Alter::getBuilderTableLock($this->db->getDriver(), ($mode === 'update'));

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
     * This method will attempt to clear all table records and reset auto-increment. 
     * 
     * @param int|null $restIncrement Index to reset auto-increment if applicable (default `null`).
     * 
     * @return bool Return true truncation was completed, otherwise false.
     * @throws DatabaseException Throws if an error occurred during execution.
     * 
     * @example - Clear all records in table:
     * 
     * ```php
     * Builder::table('users')->truncate();
     * ```
     * 
     * @example - Clear all records in table using transaction:
     * 
     * ```php
     * $stmt = Builder::table('users')
     *  ->transaction();
     * 
     * if($stmt->truncate()){
     *      $stmt->commit();
     * }else{
     *      $stmt->rollback();
     * }
     * ```
     */
    public function truncate(?int $resetIncrement = null): bool 
    {
        $deleted = false;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        try {
            $driver = $this->db->getDriver();

            if (in_array($driver, ['mysql', 'mysqli', 'pgsql'], true)) {
                $deleted = (bool) $this->db->exec("TRUNCATE TABLE {$this->tableName}");

                if ($deleted && $resetIncrement !== null && $driver !== 'pgsql') {
                    $this->db->exec("ALTER TABLE {$this->tableName} AUTO_INCREMENT = {$resetIncrement}");
                }
            } else {
                $deleted = (bool) $this->db->exec("DELETE FROM {$this->tableName}");

                if ($driver === 'sqlite') {
                    if ($deleted && $resetIncrement !== null) {
                        $result = $this->db->query("
                            SELECT name FROM sqlite_master 
                            WHERE type = 'table' AND name = 'sqlite_sequence'
                        ")->fetchNext(FETCH_ASSOC);

                        if (
                            $result && 
                            $this->db->exec("DELETE FROM sqlite_sequence WHERE name = '{$this->tableName}'")
                        ) {
                            $this->db->exec("VACUUM");
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->resolveException($e);
            return false;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($result){
                return $this->commit();
            }

            $this->rollback();
            return false;
        }

        return $deleted;
    }

    /**
     * Creates a temporary table and copies all records from the main table to the temporary table.
     *
     * @return bool Returns true if the operation was successful; false otherwise.
     * @throws DatabaseException Throws an exception if a database error occurs during the operation.
     *
     * @example - Example:
     * 
     * ```php
     * if (Builder::table('users')->temp()) {
     *     $data = Builder::table('temp_users')->select();
     * }
     * ```
     * 
     * @example - Example Using Transaction:
     * 
     * ```php
     * $stmt = (Builder::table('users')
     *      ->transaction();
     * 
     * if ($stmt->temp()) {
     *      if ($stmt->commit()) {
     *          $data = Builder::table('temp_users')->select();
     *      }
     * }else{
     *      $stmt->rollback();
     * }
     * ```
     * 
     * > **Note:**
     * > - Temporary tables are automatically deleted when the current session ends.
     * > - To query the temporary table, use the `temp_` prefix before the main table name.
     */
    public function temp(): bool 
    {
        self::assertTableName($this->tableName);

        $result = false;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        try {
            $create = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_{$this->tableName} ";
            $create .= "AS (SELECT * FROM {$this->tableName} WHERE 1 = 0)";
            $result = (
                $this->db->exec($create) > 0 && 
                $this->db->exec("INSERT INTO temp_{$this->tableName} SELECT * FROM {$this->tableName}") > 0
            );
        } catch (Throwable $e) {
            $this->resolveException($e);
            return false;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($result){
                return $this->commit();
            }

            $this->rollback();
            return false;
        }

        $this->reset();
        return $result;
    }

    /**
     * Drop database table table or temporal if it exists.
     * 
     * @param bool $isTemporalTable Whether the table is a temporary table (default false).
     * 
     * @return bool Return true if table was successfully dropped, false otherwise.
     * @throws DatabaseException Throws if error occurs.
     * 
     * @example - Drop table example:
     * 
     * ```php
     * Builder::table('users')
     *      ->drop();
     * ```
     * 
     * @example - Drop table using transaction: 
     * 
     * ```php
     * Builder::table('users')
     *      ->drop(true);
     * ```
     * 
     * @example - Drop table example using transaction:
     * 
     * ```php
     * $stmt = (Builder::table('users')
     *      ->transaction();
     * 
     * if ($stmt->drop()) {
     *      $stmt->commit()
     * }else{
     *      $stmt->rollback();
     * }
     * ```
     */
    public function drop(bool $isTemporalTable = false): bool 
    {
        self::assertTableName($this->tableName);

        $sql = Alter::getDropTable($this->db->getDriver(), $this->tableName, $isTemporalTable);
        $result = false;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        try {
            $result = (bool) $this->db->exec($sql);
        } catch (Throwable $e) {
            $this->resolveException($e);
            return false;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($result){
                return $this->commit();
            }

            $this->rollback();
            return false;
        }

        $this->reset();
        return $result;
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
     * 
     * @return bool Return true if successful, otherwise false.
     * 
     * > **Note:** It will automatically closes database connection if `closeAfter` is enabled.
     */
    public function free(): bool 
    {
        if(
            ($inTransaction = $this->inTransaction()) || 
            !($this->db instanceof DatabaseInterface)
        ){
            return !$inTransaction;
        }

        if($this->closeConnection){
            return $this->close();
        }

        $this->freeStmt();
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
        $this->freeStmt();

        if($this->db instanceof DatabaseInterface){
            $this->release();
            $this->db->free();

            if($this->db->isConnected()){
                $this->db->close();
            }
        }

        $this->db = null;

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
     * 
     * @return true Always return true. 
     * 
     * > **Note:** It automatically closes database connection if `closeAfter` is enabled.
     */
    public function reset(): bool 
    {
        $this->resetState();

        if(!$this->inTransaction() || $this->returns !== self::RETURN_STATEMENT){
            $this->free();
        }
        
        $this->returns = null;
        return true;
    }

    /**
     * Free statement cursor after executing result using `stmt` method.
     * 
     * @return true Always return true. 
     */
    public function freeStmt(): bool 
    {
        if(self::$stmt instanceof DatabaseInterface){
            if(self::$stmt->inTransaction()){
                self::$stmt->rollback();
            }
            
            self::$stmt->free();

            if(self::$stmt->isConnected()){
                self::$stmt->close();
            }
        }

        self::$stmt = null;
        $this->handler = [];
        $this->returns = null;

        return true;
    }

    /**
     * Rest options.
     * 
     * @return void 
     */
    private function resetOptions(): void 
    {
        $this->options = [
            'grouping' => [],
            'binds'    => [],
            'ordering' => [],
            'filters'  => [],
            'match'    => [],
            'matches'  => [],
            'whereRaw' => [],
            'duplicate'    => [],
            'unionColumns' => [],
            'current'  => ['sql' => '', 'params' => [], 'columns' => [], 'cache' => []]
        ];
    }

    /**
     * Initializes a new singleton instance and inherit parent the `getInstance` method setup.
     *
     * @param string|null $table The name of the table to initialize the builder with.
     * @param string|null $alias The alias for the table.
     * @param bool $assertTable Assert table name and alias.
     *
     * @return self Return a new instance of the Builder class.
     * @throws Exception If an error occurs during initialization.
     */
    private static function initializer(
        ?string $table = null, 
        ?string $alias = null,
        bool $assertTable = false
    ): Builder
    {
        if (!self::$instance instanceof self) {
            $instance = new self($table, $alias);
            $instance->db = self::database();
            return $instance;
        }

        if ($assertTable) {
            self::assertTableName($table, $alias);
        }

        $clone = clone self::$instance;

        $clone->tableName = $table ?? '';
        $clone->tableAlias = $alias ?? '';
        $clone->db = self::database();

        return $clone;
    }

    /**
     * Reset builder state.
     * 
     * @param bool $new The current object state.
     * 
     * @return void
     */
    private function resetState(bool $new = false): void 
    {
        $this->tableJoin = [];
        $this->joinConditions = [];
        $this->maxLimit = [];
        $this->conditions = [];
        $this->querySetValues = [];
        $this->hasCache = false;
        $this->rawQuery = '';
        $this->debugMode = 0;
        $this->isDistinct = false;
        $this->isCollectMetadata = false;
        $this->isStrictMode = true;
        $this->isSafeMode = false;
        $this->isIgnoreDuplicate = false;
        $this->isReplace = false;
        $this->resetOptions();

        if(!$new && (self::$stmt === null || $this->returns !== self::RETURN_STATEMENT)){
            $this->handler = [];
        }

        if($new){
            $this->returns = null;
        }
    }

    /**
     * Determines whether safe mode should be applied to the current operation.
     *
     * @return bool Return true if safe mode should apply, false otherwise.
     */
    private function inSafeMode(): bool
    {
        return $this->isSafeMode 
            && !$this->isCollectMetadata
            && $this->debugMode === self::DEBUG_NONE 
            && !$this->inTransaction();
    }

    /**
     * Determines if a given response is safe and meaningful to cache.
     *
     * @param mixed $response The result returned from a query or fetch operation.
     * 
     * @return void
     */
    private function cacheResultIfValid(mixed $response): void
    {
        if (
            !$response || 
            $response === [] ||
            $response === (object)[] ||
            !$this->isCacheable() || 
            ($response instanceof DatabaseInterface) || 
            ($response instanceof \PDOStatement) || 
            ($response instanceof \mysqli_result)
        ) {
            return;
        }

        if ($this->inSafeMode() || (is_object($response) && count(get_object_vars($response)) === 0)) {
            return;
        }

        self::$cache->set($this->cacheKey, $response);
        self::$cache = null;
        $this->isCacheReady = false;
    }

    /**
     * Determines whether the current query context allows result caching.
     *
     * @return bool Return true if caching is allowed, false otherwise.
     */
    private function isCacheable(): bool
    {
        return (
            $this->isCacheReady &&
            !$this->isCollectMetadata &&
            $this->debugMode === self::DEBUG_NONE &&
            $this->returns !== self::RETURN_STATEMENT &&
            self::$cache instanceof BaseCache
        );
    }

    /**
     * Retrieves the most recent set of match columns defined by `match()`.
     *
     * @param string $fn The calling method method `against()` or `orderAgainst()`.
     *
     * @return string Return a comma-separated list of column names to be used in MATCH().
     * @throws DatabaseException If no match columns have been defined or the format is invalid.
     */
    private function getMatchColumns(string $fn): string
    {
        $matches = $this->getOptions('matches');

        if($matches === []){
            throw new DatabaseException(
                sprintf('No match columns defined. Use $query->match([...]) before calling $query->%s(...).', $fn),
                DatabaseException::LOGIC_ERROR
            );
        }

        $columns = $matches[array_key_last($matches)]['columns'] ?? null;

        if($columns === null || $columns === ''){
            throw new DatabaseException(
                'Invalid or missing match columns. Expected non-empty array of column names.',
                DatabaseException::LOGIC_ERROR
            );
        }

        return $columns;
    }

    /**
     * Attempts to retrieve a cached result based on the current query state.
     *
     * @param int|null $mode The expected return mode (e.g. RETURN_STMT).
     * 
     * @return mixed|null Return the cached result if available, otherwise null.
     */
    private function getFromCache(?int $mode): mixed
    {
        if (!$this->hasCache || $mode === RETURN_STMT || !$this->isCacheable($mode)) {
            return null;
        }

        $response = self::$cache->getItem($this->cacheKey);

        if ($response === null) {
            return null;
        }

        $this->cacheKey = '';
        $this->isCacheReady = false;
        $this->release();
        $this->reset();

        return $response;
    }

    /**
     * Handles a throwable by rolling back any active transaction and 
     * optionally re-throwing it immediately.
     *
     * Useful in safe mode or transactional contexts to centralize exception handling.
     *
     * @param Throwable $e The exception or error to handle.
     * @param bool $throwInstant If true, rethrows the original exception after rollback.
     *
     * @throws Throwable If $throwNow is true.
     */
    private function resolveException(Throwable $e, bool $throwInstant = false): void  
    {
        if ($this->inTransaction()) {
            $this->rollback();
        }

        if($throwInstant){
            throw $e;
        }

        $this->reset();
        DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
    }

    /**
     * Finalizes an insert operation by committing or rolling back the transaction
     * and optionally capturing the last insert ID.
     *
     * If a transaction was used and is still active:
     * - Commits if rows were inserted.
     * - Rolls back otherwise.
     *
     * @param bool $useTransaction Whether an internal transaction was used.
     * @param mixed $result The insert result (number of rows inserted).
     * 
     * @return int Return number of inserted rows if successful, 0 on failure.
     */
    private function finishInsert(bool $useTransaction, mixed $result): int 
    {
        if($useTransaction && $this->db->inTransaction()){
            if($result > 0){
               return $this->commit() ? $result : 0;
            }

            $this->rollback();
            return 0;
        }
        
        if(!$this->db->inTransaction()){
            self::$lastInsertId = ($result > 0) ? $this->db?->getLastInsertId() : null;
        }

        $this->reset();
        return (int) $result;
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
            !$this->isCollectMetadata && 
            ($required || $this->isStrictMode) && 
            $this->conditions === [] &&
            $this->options['whereRaw'] === []
        ) {
            throw new DatabaseException(
                sprintf('Execution of %s is not allowed in strict mode without a "WHERE" condition.', $fn), 
                DatabaseException::VALUE_FORBIDDEN
            );
        }
    }

    /**
     * Assert query result execution methods.
     * 
     * Check if any build available before executing.
     * 
     * @param string $fn The method that is called.
     * 
     * @return void
     */
    private function assertHandler(string $fn): void 
    {
        if(!$this->isCollectMetadata && $this->handler === [] && $this->unions === []){
            throw new DatabaseException(
                "Calling {$fn}(...) without a valid query build is not allowed.",
                DatabaseException::BAD_METHOD_CALL
            );
        }
    }

    /**
     * Validates the SQL order direction.
     *
     * Ensures that the given `$order` value is either "ASC" or "DESC".
     * Throws an exception if the value is not valid.
     *
     * @param string $order The order direction to validate.
     * @param string|null $column Optional column name to validate.
     *
     * @return void
     * @throws InvalidArgumentException If the order is not "ASC" or "DESC".
     */
    private function assertOrder(string $order, ?string $column = null): void 
    {
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid order "%s". Only "ASC" or "DESC" are allowed.',
                $order
            ));
        }

        if ($column !== null && (!$column || trim($column) === '')) {
            throw new InvalidArgumentException('Column name must be a non-empty string for ordering.');
        }
    }

    /**
     * Assert SQL query.
     * 
     * @param string $query The query string to check.
     * @param string $fn The method that is called.
     * 
     * @return void
     */
    private static function assertQuery(string $query, string $fn): void 
    {
        if (!$query || trim($query) === '') {
            throw new InvalidArgumentException(
                sprintf('Invalid: %s($query) requires a non-empty SQL query string.', $fn)
            );
        }
    }

    /**
     * Assert SQL logical operators.
     * 
     * @param string $fn
     * @param string|null $operator The base operator to check.
     * @param string|null $clause An optional chain operator to check.
     * @param string|null $nested An optional combined nested operator to check.
     * 
     * @return array{?operator,?clause,?nested} Return the value as upper-cased.
     */
    private function assertOperators(
        string $fn,
        ?string $operator, 
        ?string $clause = null, 
        ?string $nested = null
    ): array 
    {
        $allowed = ['AND', 'OR'];
        $suffix = 'Allowed operators are [AND, OR].';

        $operator = ($operator !== null) ? strtoupper($operator) : null;
        $clause = ($clause !== null) ? strtoupper($clause) : null;
        $nested = ($nested !== null) ? strtoupper($nested) : null;

        if ($operator !== null && !in_array($operator, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid %s(...) logical operator '%s'. %s.",
                $fn,
                $operator,
                $suffix
            ));
        }

        if ($clause !== null && !in_array($clause, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid %s(...) clause chain operator '%s'. %s",
                $fn,
                $clause,
                $suffix
            ));
        }

        if ($nested !== null && !in_array($nested, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid %s(...) combined nested operator '%s'. %s",
                $fn,
                $nested,
                $suffix
            ));
        }

        return [$operator, $clause, $nested];
    }

    /**
     * Validates insert mode options for conflicting behaviors.
     *
     * This method checks for logical conflicts when insert mode is set to
     * `IGNORE`, `REPLACE`, or when `ON DUPLICATE` conditions are defined.
     *
     * > Applies to insert operations such as `insert()`, `copy()->to()` or `replace()`.
     * 
     * @throws DatabaseException If conflicting insert options are detected.
     */
    private function assertInsertOptions(): void 
    {
        if(!$this->isReplace && !$this->isIgnoreDuplicate){
            return;
        }

        if ($this->isIgnoreDuplicate && $this->isReplace) {
            throw new DatabaseException(
                'Cannot use "->replace(true)" with "->ignoreDuplicate(true)". REPLACE mode conflicts with duplicate ignore behavior.', 
                DatabaseException::LOGIC_ERROR
            );
        }

        $onDuplicate = $this->getOptions('duplicate');

         if($this->isIgnoreDuplicate && $onDuplicate !== []){
                throw new DatabaseException(
                    'Cannot use "->ignoreDuplicate(true)" with "->onDuplicate(...)" options. These behaviors are mutually exclusive.',
                    DatabaseException::LOGIC_ERROR
                );
            }

        if ($this->isReplace && $onDuplicate !== []) {
            throw new DatabaseException(
                'Cannot use "->replace(true)" with "->onDuplicate(...)". REPLACE already overwrites existing rows and conflicts with duplicate key logic.', 
                DatabaseException::LOGIC_ERROR
            );
        }
    }

    /**
     * Validates the given table name and optional alias.
     *
     * Ensures the table name is non-empty and contains only valid characters.
     * Alias must begin with a letter or underscore and use only alphanumeric characters or underscores.
     *
     * @param string|null $table Table name to validate.
     * @param string|null $alias Optional table alias.
     *
     * @throws InvalidArgumentException If the table name or alias is invalid.
     */
    private static function assertTableName(?string $table, ?string $alias = null): void
    {
        if ($table !== null) {
            if (trim($table, '`') === '') {
                throw new InvalidArgumentException(
                    'Table name must be a non-empty string or a valid backtick wrapped name.'
                );
            }

            if (!preg_match('/^`?[a-zA-Z0-9_.-]+`?$/', $table)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid table name "%s". Only letters, numbers, underscores, hyphens, and dots are allowed. Table name may be optionally enclosed in backticks.',
                    $table
                ));
            }
        }

        if ($alias) {
            if (trim($alias, '`') === '') {
                throw new InvalidArgumentException(
                    'Table alias must be a non-empty backtick.'
                );
            }

            if (!preg_match('/^`?[a-zA-Z_][a-zA-Z0-9_]*`?$/', $alias)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid table alias "%s".%s%s',
                    $alias,
                    ' Must start with a letter or underscore and contain only letters, numbers, or underscores.',
                    ' Aliases may be optionally enclosed in backticks.'
                ));
            }
        }
    }

    /**
     * Create query and execute it.
     * 
     * @param string $query The base SQL query string to execute.
     * @param string $method The execution method called (expected: `total`, `stmt`, `select`, `find`, `delete`, `fetch`).
     * @param array $columns For select and find methods, the column names to return.
     * @param int $returnMode The fetch result return mode (`RETURN_ALL` or `RETURN_NEXT`).
     * @param int $fetchMode The database result fetch mode for retrieval (e.g., `FETCH_OBJ`, `FETCH_*`).
     * 
     * @return mixed Return the execution result, value varies based on the `$method` and `$mode` parameter.
     * @throws DatabaseException If an error occurs during query execution or result fetching.
     */
    private function buildExecutableStatement(
        string $query, 
        string $method = 'total', 
        array $columns = ['*'], 
        int $returnMode = RETURN_ALL, 
        int $fetchMode = FETCH_OBJ
    ): mixed
    {
        $sql = $this->isDistinct ? "SELECT DISTINCT{$query}" : "SELECT{$query}";

        if($query === '' || in_array($method, ['select', 'find', 'stmt'], true)){
            $sql .= ($columns === ['*']) ? ' *' : ' ' . implode(', ', $columns);
        }
        
        $sql .= " FROM {$this->tableName}";
        $sql .= $this->tableAlias ? " AS {$this->tableAlias}" : '';

        if($this->lock && in_array($this->db->getDriver(), ['sqlsrv', 'mssql', 'dblib'])){
            $sql .= ' ' . $this->lock;
        }
     
        $sql .= $this->getJoinConditions();

        if($this->isCollectMetadata){
            $this->options['current']['columns'] = $columns;
        }

        try {
            $response = $this->getStatementExecutionResult($sql, $method, $returnMode, $fetchMode);

            if($returnMode !== RETURN_STMT){
                $this->cacheResultIfValid($response);
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
     *  - default: calculated total result.
     *
     * @param string $sql The base SQL query string to execute.
     * @param string $method The execution method called (expected: `total`, `stmt`, `select`, `find`, `delete`, `fetch`).
     * @param int $result The return result type for `$method` operations when `fetch` is used (expected: `next`, `all` or `stream`).
     * @param int $mode The database result mode for result retrieval (e.g., `FETCH_OBJ`).
     *
     * @return mixed Return the execution result, value varies based on the `$method` and `$mode` parameter.
     * @throws DatabaseException If an error occurs during query execution or result fetching.
     */
    private function getStatementExecutionResult(
        string $sql, 
        string $method = 'total', 
        int $result = RETURN_ALL, 
        int $mode = FETCH_OBJ
    ): mixed
    {
        $isOrdered = false;
        $response = false;
        $isDelete = $method === 'delete';
        $isNext = $method === 'find' || $result === 'next';
        
        if($this->conditions !== []){
            $this->buildConditions($sql);
        }

        $this->addRawWhereClause($sql);

        if(!$isDelete){
            [$query, $isOrdered] = $this->addOrderAndGrouping();
            $sql .= $query;

            $this->setHavingConditions($sql);
            $this->setMatchAgainst($sql, $isOrdered);
        }

        [$offset, $limit] = $this->maxLimit + [0, 0];

        if($isDelete || $isNext){
            $limit = $isNext ? 1 : $limit;
            $sql .= ($limit > 0) ? " LIMIT {$limit}" : '';
        }elseif($limit > 0){
            $sql .= " LIMIT {$offset},{$limit}";
        }

        if($this->lock && !in_array($this->db->getDriver(), ['sqlsrv', 'mssql', 'dblib'])){
            $sql .= ' ' . $this->lock;
        }

        if($this->debugMode !== self::DEBUG_NONE){
            if($this->debugMode === self::DEBUG_BUILDER){
                return $this->setDebugInformation($sql, $method);
            }

            $this->echoDebug($sql, 'SQL QUERY');
        }

        if($this->isCollectMetadata){
            $this->options['current']['sql'] = $sql;
        }

        $useTransaction = false;
        $canExecute = (!$this->isCollectMetadata || $this->debugMode === self::DEBUG_NONE);
        $hasParams = ($this->conditions !== [] 
            || $this->getOptions('match') !== [] 
            || $this->getOptions('binds') !== []);

        if ($method === 'delete' && $this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        if($hasParams){
            if($canExecute){
                $this->db->prepare($sql);
            }

            $c = $this->bindConditions();
            $b = $this->bindJoinPlaceholders();

            $hasParams = $c || $b;
        }

        if(!$canExecute){
            return $this->isCollectMetadata;
        }

        $hasParams ? $this->db->execute() : $this->db->query($sql);
        $sql = null;

        if ($this->db->ok()) {
            if($this->returns === self::RETURN_STATEMENT || $result === RETURN_STMT){
                $this->returns = self::RETURN_STATEMENT;
                return $this->db;
            }

            $response = match ($method) {
                'stmt' => $this->db,
                'total' => $this->db->getCount(),
                'delete' => $this->db->rowCount(),
                'select', 'find' => $this->db->fetch(($method === 'select')
                    ? RETURN_ALL 
                    : RETURN_NEXT,
                    $this->getFetchMode($mode)
                ),
                default => ($this->db->fetchNext() ?: (object) ['totalCalc' => 0])->totalCalc
            };
        }

        if($useTransaction && $this->db->inTransaction()){
            if($response > 0){
                return $this->commit() ? $response : 0;
            }

            $this->rollback();
            return 0;
        }
        
        $this->reset();
        return $response;
    }

    /**
     * Appends raw WHERE fragments into the final SQL string.
     *
     * @param string $sql The SQL string being built to appended.
     */
    private function addRawWhereClause(string &$sql): void 
    {
        $raw = $this->getOptions('whereRaw');
    
        if ($raw === []) {
            return;
        }
        
        $query = trim(implode(' ', $raw));

        if($this->conditions === [] && stripos($sql, 'WHERE') === false){
            $query = preg_replace('/^\s*(AND|OR)\b\s*/i', '', $query);
            $sql .= ' WHERE';
        }

        $sql .= ' ' . $query;
    }

    /**
     * Compiles all UNION/UNION ALL statements into a single executable SQL string.
     *
     * @return array First item is the full SQL string, second is merged parameter bindings.
     *
     * @throws DatabaseException If no union queries are defined.
     */
    private function compileTableUnions(): array
    {
        if ($this->unions === []) {
            throw new DatabaseException('No UNION queries to compile.', DatabaseException::BAD_METHOD_CALL);
        }

        $sqlParts = [];
        $params = [];
        $columns = $this->getOptions('unionColumns');
        [$offset, $limit] = $this->maxLimit + [0, 0];

        $isColumns = $columns !== [];
        $isConditions = $this->conditions !== [];

        $isCompound = $isColumns || $isConditions || $limit > 0;
        $alias = ($this->unionCombineAlias ?: 'un_compound');

        if($isCompound){
            $columns = ($isColumns && $columns !== ['*']) 
                ? implode(', ', $columns) 
                : "{$alias}.*";
            $sqlParts[] = $this->isDistinct ? "SELECT DISTINCT {$columns} FROM (" : "SELECT {$columns} FROM (";
        }

        foreach ($this->unions as $index => $union) {
            $sql = '(' . trim($union['sql']) . ')';

            if ($index === 0) {
                $sqlParts[] = $sql;
            } else {
                $type = $union['type'] ?? 'UNION';
                $sqlParts[] = "{$type} {$sql}";
            }

            $params = array_merge($params, $union['params']);
        }

        if($isCompound){
            $sqlParts[] = ") AS {$alias}";
        }

        $sql = '';
        if($this->buildConditions($sql)){
            $sqlParts[] = trim($sql);
            $this->bindConditions($params);
        }

        $sqlParts[] = trim($this->addOrderAndGrouping()[0]);

        if($limit > 0 && $offset > 0){
            $sqlParts[] = "LIMIT {$offset},{$limit}";
        }elseif($limit > 0){
            $sqlParts[] = "LIMIT {$limit}";
        }

        return [implode(' ', $sqlParts), $params];
    }

    /**
     * Return raw SQL query builder execution result.
     * 
     * @param string $sql The SQL query to execute.
     * @param array<string,mixed> $placeholder An associative array of placeholder values to bind to the query.
     * @param int $returnMode The return result type mode.
     * @param int $fetchMode The return result type mode.
     * @param bool $escapePlaceholders Whether to validate and escape placeholders.
     * 
     * @return mixed|DatabaseInterface Return query result, prepared statement object, otherwise false on failure.
     * @throws DatabaseException If placeholder key is not a string.
    */
    private function executeRawSqlQuery(
        string $sql, 
        array $placeholder = [], 
        int $returnMode = RETURN_ALL,
        int $fetchMode = FETCH_OBJ,
        bool $escapePlaceholders = true
    ): mixed
    {
        if($this->debugMode !== self::DEBUG_NONE){
            if($this->debugMode === self::DEBUG_BUILDER){
                return $this->setDebugInformation($sql, 'execute', $placeholder);
            }

            $this->echoDebug($sql, 'SQL QUERY');

            if($placeholder === []){
                return false;
            }
        }

        $useTransaction = false;

        if ($this->inSafeMode()) {
            $useTransaction = $this->transaction();
        }

        if($placeholder === []){
            $this->db->query($sql);
        }else{
            $this->db->prepare($sql);

            if($escapePlaceholders){
                $this->bindStrictColumns($placeholder, [], false);
                $placeholder = null;
            }
            
            if($this->debugMode !== self::DEBUG_NONE){
                return false;
            }

            $this->db->execute($placeholder);
        }

        if(!$this->db->ok()){
            $this->reset();
            return false;
        }

        $response = true;

        if($returnMode === RETURN_STMT || $this->returns === self::RETURN_STATEMENT){
            $this->returns = self::RETURN_STATEMENT;
            return $this->db;
        }

        $mode = $this->getFetchMode($fetchMode);

        if($useTransaction && $this->db->inTransaction()){
            if($response){
                if($this->db->commit()){
                    return $this->db->getResult($returnMode, $mode);
                }

                return false;
            }

            $this->rollback();
            return false;
        }

        $response = $response ? $this->db->getResult($returnMode, $mode) : false;
        $this->reset();
        
        return $response;
    }

    /**
     * Combines the current builder's query with another using a `UNION` or `UNION ALL` clause.
     *
     * @param Builder|Closure $union Query builder to union with. 
     * @param bool $all Whether to use `UNION ALL` instead of plain `UNION`.
     * 
     * @return self Returns instance of builder.
     * @throws DatabaseException If the column counts between queries differ.
     */
    private function doUnionTables(Builder|Closure $union, bool $all = false): self
    {
        $union = $this->getValue($union);

        if(!$union instanceof Builder){
            return $this;
        }

        $this->isCollectMetadata = true;
        $union->isCollectMetadata = true;

        $this->get(); 
        $parent = $this->getOptions('current');

        $union->get();
        $child = $union->getOptions('current');

        $parentColumnCount = count($parent['columns']);
        $childColumnCount = count($child['columns']);
        $type = $all ? 'UNION ALL' : 'UNION';

        if ($parentColumnCount !== $childColumnCount) {
            throw new DatabaseException(sprintf(
                '%s queries must have the same number of columns (%d vs %d)',
                $type,
                $parentColumnCount,
                $childColumnCount
            ), DatabaseException::COMPILE_ERROR);
        }
        if ($this->unions === []) {
            $this->unions[] = [
                'sql' => $parent['sql'],
                'params' => $parent['params'],
                'type' => $type,
            ];
        }

        $this->unions[] = [
            'sql' => $child['sql'],
            'params' => $child['params'],
            'type' => $type
        ];
        
        $this->reset();
        $union->reset();
        return $this;
    }

    /**
     * Builds and returns the SQL for any `GROUP BY` or `ORDER BY` clauses.
     *
     * @return array{string,bool} First item is the SQL string, second is a boolean whether ORDER BY exists.
     */
    private function addOrderAndGrouping(): array
    {
        $sql = '';
        $grouping = $this->getOptions('grouping');
        $ordering = $this->getOptions('ordering');

        if($grouping !== []){
            $sql .= ' GROUP BY ' . rtrim(implode(', ', $grouping), ', ');
        }

        if($ordering !== []){
            $sql .= ' ORDER BY ' . rtrim(implode(', ', $ordering), ', ');
        }

        return [$sql, $ordering !== [], $grouping !== []];
    }

    /**
     * Get the default fetch mode or fallback.
     * 
     * @param int $mode The fallback mode.
     * 
     * @return int Return database fetch mode.
     */
    private function getFetchMode(int $mode): int 
    {
        return match($this->returns) {
            self::RETURN_OBJECT => FETCH_OBJ,
            self::RETURN_ARRAY => FETCH_ASSOC,
            default => $mode
        };
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
                $sql .= " ON {$this->joinConditions[$key][0]['sql']}";

                if(($joins = count($this->joinConditions[$key])) > 1){
                    for ($i = 1; $i < $joins; $i++) {
                        $current = $this->joinConditions[$key][$i];
                        $sql .= " {$current['clause']} {$current['sql']}";
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

        $query = Alter::getAdministrator($driver, $action, $pgsqlPlaceholder);

        try {
            $stmt = $tbl->database()->prepare($query)->bind(':lockName', $identifier);
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
    private function setBindValue(string $placeholder, mixed $value, ?array &$params = null): self 
    {
        $placeholder = ltrim($placeholder, ':');

        if($this->isCollectMetadata){
            $this->options['current']['params'][$placeholder] = $value;
        }else{
            if($params === null){
                $this->db->bind(":$placeholder", $value);
            }else{
                $params[$placeholder] = $value;
            }
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug("$placeholder = $value", 'BIND PARAMS');
        }

        return $this;
    }

    /**
     * Parse value and execute closure if value is callable.
     * 
     * @param mixed $input The input value.
     * 
     * @return mixed Return the value of closure of original value if it's not a closure.
    */
    private function getValue(mixed $input): mixed 
    {
        if($input instanceof Closure){
            return $input($this);
        }

        return $input;
    }

    /**
     * Extracts the column name, comparison operator, and value from a column condition array.
     *
     * @param array $column The column condition array.
     * @param bool $extractRaw Weather to extract string value of raw expression (default: false).
     * @param string|null $key Optional key to use instead of the first array key.
     *
     * @return array Returns an array with:
     *               [0] string The column name.
     *               [1] string The comparison operator.
     *               [2] mixed  The value to compare.
     *
     * @example - Example:
     * 
     * ```php
     * [$name, $comparison, $value] = $this->getFromColumn(Builder::column('foo', '=', 'bar'));
     * 
     * // $name = 'foo'
     * // $comparison = '='
     * // $value = 'bar'
     * ```
     */
    private function getFromColumn(array $column, bool $extractRaw = false, ?string $key = null): mixed 
    {
        $key ??= array_key_first($column);
        $value = $this->getValue($column[$key]['value'] ?? null);

        return [
            $key, 
            $column[$key]['comparison'] ?? '=', 
            ($extractRaw && $value instanceof RawExpression) 
                ? $value->getExpression() 
                : (($value === null) ? 'NULL' : $value)
        ];
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
        $id = $this->getObjectId();

        foreach ($this->getOptions('duplicate') as $col => $option) {
            $value = $this->getValue($option['value']);
            $operation = match ($option['operation']) {
                '+=' => '+', 
                '-=' => '-',
                '=', '=='  => '=',
                default => $option['operation']
            };

            if ($value instanceof RawExpression) {
                $value = $value->getExpression();
            } else {
                $value = self::escape($value, true);

                if ($isPrepare) {
                    $placeholder = "duplicate_{$col}_{$id}";
                    $bindValues[$placeholder] = $value;
                    $value = ":{$placeholder}";
                }
            }

            $updates[] = ($operation === '=')
                ? "{$col} = {$value}"
                : "{$col} = {$col} {$operation} {$value}";
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
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
    private function executeInsertQuery(array $values, string $type, int $length): int 
    {
        $inserts = '';
        $isDebug = $this->debugMode !== self::DEBUG_NONE;

        for ($i = 0; $i < $length; $i++) {
            $inserts .= "(" . self::escapeValues($values[$i]) . "), ";
        }

        $columns = implode(', ', array_keys($values[0]));
        $inserts = rtrim($inserts, ', ');
        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';

        $sql = "{$type} {$ignore}INTO {$this->tableName} ({$columns}) VALUES {$inserts}";
        $sql .= $this->buildDuplicateUpdateClause();

        if($isDebug){
            ($this->debugMode === self::DEBUG_BUILDER)
                ? $this->setDebugInformation($sql, 'insert')
                : $this->echoDebug($sql, 'SQL QUERY');

            return 0;
        }

        $this->db->query($sql);
        return $this->db->ok() ? $this->db->rowCount() : 0;
    }

    /**
     * Execute insert query using prepared statement.
     * 
     * @param array<int,array<string,mixed>> $values array of values to insert.
     * @param string $type The insert type (expected: `INSERT` or `INSERT`).
     * @param int $length Length of values.
     * @param bool $escapeValues Whether to escape values (default: true).
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function executeInsertPrepared(
        array $values, 
        string $type, 
        int $length,
        bool $escapeValues = true
    ): int
    {
        $inserted = 0;
        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';
        $isDebug = $this->debugMode !== self::DEBUG_NONE;
        self::$lastInsertId = null;

        $replacements = [];
        [$placeholders, $inserts] = self::mapInsertColumns($values[0]);

        $sql = "{$type} {$ignore}INTO {$this->tableName} ({$inserts}) VALUES ($placeholders)";
        $sql .= $this->buildDuplicateUpdateClause($replacements);
       
        if($isDebug){
            if($this->debugMode === self::DEBUG_BUILDER){
                $this->setDebugInformation($sql, 'insert', $values);
                return 0;
            }

            $this->echoDebug($sql, 'SQL QUERY');
        }
 
        $this->db->prepare($sql);

        for ($i = 0; $i < $length; $i++) {
            if($escapeValues || $isDebug){
                $this->bindStrictColumns($values[$i], $replacements, false);
            }

            if($isDebug){
                continue;
            }

            if($this->db->execute($escapeValues ? null : array_merge($values[$i], $replacements))){
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Bind insert parameters to the prepared statement.
     *
     * @param array<string,mixed> $columns The column names and value.
     * @param array<string,mixed> $replacements Optional insert replace values.
     * @param bool $withObjectId If object id should be added to column placeholders.
     * 
     * @return void
     */
    private function bindStrictColumns(array $columns, array $replacements = [], bool $withObjectId = true): void
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

            $this->setBindValue(
                $this->trimPlaceholder($column, $withObjectId), 
                self::escape($value, false, true)
            );
        }

        foreach ($replacements as $placeholder => $replace) {
            $this->setBindValue(":{$placeholder}", $replace);
        }
    }

    /**
     * Build query conditions based on the specified type.
     *
     * @param string $query The SQL query string to which conditions passed by reference.
     * @param bool $addWhere Whether the where conditions should be added 
     *                          and if false treat it as AND (default: true).
     * 
     * @return bool Return true if has conditions, otherwise false.
     */
    private function buildConditions(string &$query, bool $addWhere = true): bool
    {
        if ($this->conditions === []) {
            return false;
        }

        if ($addWhere) {
            $query .= ' WHERE ';
        }

        $firstCondition = true;
        $bindIndex = 0;

        foreach ($this->conditions as $index => $condition) {
            if (!$addWhere || ($addWhere && !$firstCondition)) {
                $query .= (($condition['type'] ?? '') === 'OR') ? ' OR ' :  ' AND ';
            }

            $query .= match ($condition['mode']) {
                self::CONJOIN => $this->buildGroupConditions(
                    $condition['conditions'], 
                    $index,
                    $condition['operator'],
                    $bindIndex
                ),
                self::NESTED => $this->buildGroupBindConditions(
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
     * @param array|null $params Update params, used in union.
     * 
     * @return bool Return true if any bind params, false otherwise.
     */
    private function bindConditions(?array &$params = null): bool 
    {
        $totalBinds = 0;
        $matches = $this->getOptions('match');

        if($this->conditions === [] && $matches === []){
            return false;
        }

        foreach ($this->conditions as $index => $condition) {
            $value = $this->getValue($condition['value'] ?? null);

            if($condition['mode'] !== self::INARRAY && ($value instanceof RawExpression)){
                continue;
            }

            switch ($condition['mode']) {
                case self::AGAINST:
                    $totalBinds++;
                    $this->setBindValue(":match_column_{$index}", $value, $params);
                break;
                case self::CONJOIN:
                    $bindIndex = 0;
                    $this->bindGroupConditions($condition['conditions'], $index, $bindIndex, $params);
                    $totalBinds += $bindIndex;
                break;
                case self::NESTED:
                    // Reset index
                    $bindIndex = 0;
                    $this->bindGroupConditions($condition['X'], $index, $bindIndex, $params);
                    $this->bindGroupConditions($condition['Y'], $index, $bindIndex, $params);
                    $totalBinds += $bindIndex;
                break;
                case self::INARRAY:
                    $this->bindInConditions($value, $condition['column'], true, $totalBinds, $params);
                break;
                case self::INSET:
                    // skip
                break;
                default:
                    $totalBinds++;
                    $this->setBindValue($this->trimPlaceholder($condition['column']), $value, $params);
                break;
            }
        }
 
        foreach ($matches as $idx => $order) {
            $value = $this->getValue($order['value']);

            if ($value instanceof RawExpression) {
                continue;
            }

            $totalBinds++;
            $this->setBindValue(":match_order_{$idx}", $value, $params);
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
        $value = $this->getValue($condition['value'] ?? null);
        $isRaw = ($value instanceof RawExpression);
        $logical = $addOperator ? (($condition['type'] === 'OR') ? ' OR ' : ' AND ') : '';

        $placeholder = $isRaw
            ? self::escape(value: $value ?? '', addSlashes: true)
            : $this->trimPlaceholder(($condition['mode'] === self::AGAINST) ? "match_column_{$index}" : $column);

        return match ($condition['mode']) {
            self::REGULAR => "{$logical}{$column} {$comparison} {$placeholder}",
            self::INARRAY => "{$logical}{$column} {$comparison}(" . (
                $isRaw
                    ? self::escapeValues($value ?? [])
                    : $this->bindInConditions($value ?? [], $column)
            ) . ')',
            self::AGAINST => "{$logical}MATCH($column) AGAINST ({$placeholder} {$comparison})",
            self::INSET => self::buildInsetConditions($condition, $logical, $comparison),
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
        $search = $condition['isSearchColumn'] 
            ? $condition['search'] 
            : self::escape($condition['search'], true);
        
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
     * @return bool
     */
    private function bindJoinPlaceholders(): bool 
    {
        $binds = 0;
        foreach($this->getOptions('binds') as $placeholder => $value){
            if($value instanceof RawExpression){
               throw new DatabaseException(
                    sprintf('Bind value cannot be instance of %s', RawExpression::class),
                    DatabaseException::LOGIC_ERROR
                );
            }

            $this->setBindValue($placeholder, $value);
            $binds++;
        }

        return $binds > 0;
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
     * @param array|self[] $conditions An array of conditions to be grouped.
     * @param int $index The index to append to the placeholder names.
     * @param bool $isBided Indicates whether placeholders should be used for binding values (default: true).
     * @param string $operator The type of logical operator to use between conditions within the group (default: 'OR').
     * @param int &$lastBindIndex Reference to the total count of conditions processed so far across all groups.
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
        $length = count($conditions);

        for ($idx = 0; $idx < $length; $idx++) {
            $condition = $conditions[$idx];
            $column = key($condition);
            $value = $this->getValue($condition[$column]['value']);
            $comparison = strtoupper($condition[$column]['comparison'] ?? $condition[$column]['operator'] ?? '=');

            if($value instanceof RawExpression){
                $placeholder = $value->getExpression();
            }else{
                $placeholder = $this->trimPlaceholder("{$column}_{$index}_" . ($idx + $bindIndex));
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
     * @param int $bindIndex The total bind indexes.
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
     * @param array|null $params Union params.
     * 
     * @return string
     */
    private function bindInConditions(
        array $values, 
        string $column,
        bool $handle = false,
        int &$bindings = 0,
        ?array &$params = null
    ): string 
    {
        $placeholders = '';
        $length = count($values);

        for ($idx = 0; $idx < $length; $idx++) {
            $value = $values[$idx];

            if($value instanceof RawExpression){
                $placeholders .= "{$value->getExpression()}, ";
            }else{
                $placeholder = $this->trimPlaceholder("{$column}_in_{$idx}");

                if($handle){
                    $this->setBindValue($placeholder, $value, $params);
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
        int &$bindIndex = 0,
        ?array &$params = null
    ): void 
    {
        $length = count($bindings);

        for ($idx = 0; $idx < $length; $idx++) {
            $bind = $bindings[$idx];
            $column = key($bind);
            $value = $this->getValue($bind[$column]['value']);

            if($value instanceof RawExpression){
                continue;
            }

            $comparison = strtoupper($bind[$column]['comparison'] ?? $bind[$column]['operator'] ?? '');

            if(str_ends_with($comparison, 'IN')){
                $totalBinds = 0;
                $this->bindInConditions($value, $column, true, $totalBinds, $params);
            }else{
                $this->setBindValue(
                    $this->trimPlaceholder("{$column}_{$index}_" . ($idx + $bindIndex)), 
                    is_array($value) ? self::escapeValues($value, true) : $value,
                    $params
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
        if($method === 'insert'){
            $length = count($values);

            for ($i = 0; $i < $length; $i++) {
                foreach($values[$i] as $column => $value){
                    $params[$i][$column] = self::escape($value);
                }
            }
        }else{
            if($method === 'update'){
                foreach($values as $column => $value){
                    $params[$column] = self::escape($value);
                }
            }

            foreach ($this->conditions as $index => $condition) {
                $value = $this->getValue($condition['value']);

                switch ($condition['mode']) {
                    case self::AGAINST:
                        $params[$this->trimPlaceholder("match_column_{$index}")] = self::escape($value);
                    break;
                    case self::CONJOIN:
                        $this->bindDebugGroupConditions($condition['conditions'], $index, $params);
                    break;
                    case self::NESTED:
                        $bindIndex = 0;
                        $this->bindDebugGroupConditions($condition['X'], $index, $params, $bindIndex);
                        $this->bindDebugGroupConditions($condition['Y'], $index, $params, $bindIndex);
                    break;
                    case self::INARRAY:
                        foreach ($value as $idx => $val) {
                            $placeholder = $this->trimPlaceholder("{$condition['column']}_in_{$idx}");

                            $params[$placeholder] = is_array($val) 
                                ? self::escapeValues($val, true) 
                                : $val;
                        }
                    break;
                    default: 
                        $params[$this->trimPlaceholder($condition['column'])] = self::escape($value);
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
        $matches = $this->getOptions('match');

        if($matches === []){
            return;
        }

        $match = $isOrdered ? ' , ' : ' ORDER BY';

        foreach ($matches as $idx => $order) {
            $value = $this->getValue($order['value']);
            $value = ($value instanceof RawExpression) 
                ? self::escape(value: $value, addSlashes: true)
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
        $filters = $this->getOptions('filters');

        if($filters === []){
            return;
        }

        $having = ' HAVING';
   
        foreach ($filters as $idx => $filter) {
            $expression = $filter['expression'];

            if($expression instanceof RawExpression){
                $expression = $expression->getExpression();
            }

            $value = self::escape($filter['value'], true);
            $operator = ($idx > 0) ? ($filter['operator'] ?? 'AND') . ' ' : '';

            $having .= "{$operator}{$expression} {$filter['comparison']}) {$value} ";
            
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
    private function bindDebugGroupConditions(
        array $bindings, 
        int $index, 
        array &$params = [], 
        int &$bindIndex = 0
    ): void 
    {
        $length = count($bindings);

        for ($idx = 0; $idx < $length; $idx++) {
            $bind = $bindings[$idx];
            $column = key($bind);
            $placeholder = $this->trimPlaceholder("{$column}_{$index}_" . ($idx + $bindIndex));
   
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
    private function trimPlaceholder(string|null $input, bool $withId = true): string 
    {
        if (!$input) {
            return '';
        }

        if (preg_match('/\(([^)]+)\)/', $input, $matches)) {
            $input = $matches[1];
        }

        $input = trim($input, " :\t\n\r\0\x0B");
        
        $value = ':';
        $value .= (str_contains($input, '.') ? str_replace('.', '_', $input) : $input);
        $value .= ($withId ? '_' . $this->getObjectId() : '');
    
        return $value;
    }
    
    /**
     * Map insert columns and values.
     * 
     * @var array<string,mixed> $values Array of columns and values.
     * 
     * @return array<int,string> Array of insert params and placeholders.
     */
    private static function mapInsertColumns(array $values): array 
    {
        $placeholders = '';
        $inserts = '';

        foreach($values as $column => $value){
            $inserts .= "$column, ";
            $placeholders .= ($value instanceof RawExpression) 
                ? $value->getExpression() . ', ' 
                : ":$column, ";
        }

        return [rtrim($placeholders, ', '), rtrim($inserts, ', ')];
    }

    /**
     * Convert array keys to placeholders key = :key for update table.
     * 
     * @param array $columns The columns.
     * @param bool $asString Should implode or just return the array.
     * 
     * @return array|string Return array or string.
     */
    private function buildPlaceholder(array $columns, bool $asString = false): array|string
    {
        $placeholders = [];

        foreach ($columns as $column => $val) {
            $value = $this->getValue($val);
            $placeholders[] = "{$column} = " . (($value instanceof RawExpression) 
                ? $value->getExpression() 
                : $this->trimPlaceholder($column)
            );
        }

        return $asString ? implode(', ', $placeholders) : $placeholders;
    }

    /**
     * Prepare quoted values from an array of columns.
     *
     * @param array<int,mixed> $columns The array of columns to be quoted.
     * @param bool $enQuote Whether to wrap the result in quotes (except for JSON).
     * 
     * @return string An string of quoted and comma separated values.
     * @throws JsonException If an error occurs while encoding values.
     */
    private static function escapeValues(array $columns, bool $enQuote = true): string
    {
        if($columns === []){
            return '';
        }

        $result = '';

        foreach ($columns as $item) {
            $result .= self::escape($item, $enQuote) . ', ';
        }

        return  rtrim($result, ', ');
    }
}