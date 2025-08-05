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
use \Luminova\Luminova;
use \Luminova\Time\Time;
use \Luminova\Base\Cache;
use \Luminova\Logger\Logger;
use \Luminova\Utility\Promise\Promise;
use \Luminova\Foundation\Core\Database;
use \Luminova\Utility\Object\LazyObject;
use \Luminova\Cache\{FileCache, MemoryCache};
use function \Luminova\Funcs\is_associative;
use \Luminova\Database\{Connection, Manager, RawExpression, Helpers\Alter};
use \Luminova\Interface\{LazyObjectInterface, PromiseInterface, DatabaseInterface};
use \Luminova\Exceptions\{
    ErrorCode, 
    CacheException, 
    RuntimeException, 
    DatabaseException, 
    InvalidArgumentException
};

/**
 * Method Groups Annotations {@see methodGroup}
 * 
 * Each method group is annotated using `@methodGroup` for clarity and IDE integration.
 * 
 * **QueryInitializer**  
 * Methods that begin or set up a new query context for example, defining a table or starting a join operation.
 * 
 * **QueryExecutor**  
 * Methods that execute the built query and return results — such as fetching a single record or multiple rows.
 * 
 * **QueryCondition**  
 * Methods that add `WHERE`, `BETWEEN`, `IN`, or other condition clauses to the query.
 * 
 * **QueryConfiguration**  
 * Methods that control the structure or behavior of the query, such as setting limits, offsets, or distinct selections.
 * 
 * **QueryFilters**  
 * Methods that define result ordering, grouping, or other output filters.
 * 
 * **QueryColumns**  
 * Methods used to build/map columns into one set.
 * 
 * **SQLFunction**  
 * Methods that represent SQL functions (e.g., `COUNT`, `MAX`, `MIN`, `AVG`) used within select statements.
 */
final class Builder implements LazyObjectInterface
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
     * Pass raw conditions in where clause.
     * 
     * Combined expressions (e.g., `WHERE NOT EXISTS (SELECT ...)`).
     * 
     * @var string RAW
     */
    public const RAW = 'RAW';

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
     * @var Connection<LazyObjectInterface>|null $conn
     */
    private static ?LazyObjectInterface $conn = null;

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
     * @var Cache|null $cache 
     */
    private static ?Cache $cache = null;

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

        self::$conn ??= LazyObject::newObject(fn(): Connection => Connection::getInstance());
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
        return (self::$conn->database() instanceof DatabaseInterface) 
            && self::$conn->database()->isConnected();
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
     * 
     * @methodGroup QueryInitializer Build query with main table.
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
     * @see execute() - To execute this query.
     * 
     * @example - Executing a raw query:
     * 
     * ```php
     * $result = Builder::query("SELECT * FROM users WHERE id = :user_id")
     *      ->execute(['user_id' => 1]);
     * ```
     * > **Note:** 
     * > To cache result, you must call `cache()` before the `execute()` method.
     * 
     * @methodGroup QueryInitializer Build RAW SQL query with optional placeholder and cache support.
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
     * 
     * @example - Example:
     * 
     * ```php
     * Builder::exec("ALTER TABLE `users` ADD COLUMN `slug` CHAR(10) DEFAULT NULL AFTER `id`");
     * ```
     * 
     * @methodGroup QueryExecutor Execute raw SQL query directly using `exec`.
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
     * Adds a table join to the current query.
     *
     * Use this method to combine data from another table or subquery into your main query.
     * You can specify the type of join (INNER, LEFT, etc.) and optionally assign an alias
     * for the joined table.
     *
     * @param string $table The table name to join.  
     * @param string|null $alias Optional alias for the joined table.  
     * @param string|null $type The type of join to use (`INNER`, `LEFT`, `RIGHT`, `FULL`, or `CROSS`).  
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.  
     *
     * @return self Returns the instance of builder class.
     * @throws InvalidArgumentException If `$table` or `$type` is an empty string.
     *
     * @example - Basic join:
     * ```php
     * Builder::table('products', 'p')
     *     ->join('users', 'u', 'LEFT');
     * ```
     *
     * @example - Join without alias:
     * ```php
     * Builder::table('users')
     *     ->join('orders', type: 'INNER');
     * ```
     *
     * @example - Join using subquery:
     * ```php
     * Builder::table('users', 'u')
     *     ->join('orders', 'o', 'INNER', true);
     *     ->onSubquery('(SELECT user_id, COUNT(*) AS total FROM orders GROUP BY user_id)');
     * ```
     * 
     * **Supported Join Methods:**
     * 
     * @see innerJoin() - Use `INNER` when you only want matching rows from both tables.  
     * @see leftJoin()  - Use `LEFT` when you want all rows from the left table, even if no match exists.  
     * @see rightJoin() - Use `RIGHT` when you want all rows from the right table, even if no match exists.  
     * @see fullJoin()  - Use `FULL` (or `FULL OUTER`) when you want all rows from both sides.  
     * @see crossJoin() - Use `CROSS` when you want every combination of rows (Cartesian product).  
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function join(
        string $table,
        ?string $alias = null,
        ?string $type = null,
        bool $forSubquery = false
    ): self
    {
        $table = trim($table);
        self::assertTableName($table, $alias);

        $this->tableJoin[$table . ($alias ?? '')] = [
            'type'  => strtoupper($type ?? ''),
            'table' => $table,
            'alias' => (string) $alias,
            'as'    => $alias ? "AS {$alias}" : '',
            'isForSubquery' => $forSubquery
        ];

        return $this;
    }

    /**
     * Adds a join condition to the current sub-table.
     * 
     * This method defines a complete `ON` clause expression for join operations.  
     * It’s ideal for straightforward comparisons that don’t require more advanced methods like  
     * `onCompound()`, `onCondition()` or `onSubquery()`.
     *
     * @param string $condition The column name or condition to join on.
     * @param string $comparison The comparison operator (e.g. `=`, `<>`, `>`, `<`).
     * @param (Closure(Builder $static):mixed)|mixed $value The value or column to compare against.
     *      - Quoted strings are treated as literals.  
     *      - Unquoted strings are treated as column names.  
     *      - Named placeholders (e.g. `:role_name`) must be bound with `bind()`.  
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     *
     * @return self Returns the instance of builder class.
     * @throws InvalidArgumentException If invalid logical clause was provided.
     *
     * @example - Simple join condition:
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('roles', 'r')
     *         ->on('u.user_id', '=', 'r.role_user_id') // Column comparison
     *         ->on('u.user_group', '=', 1)             // Numeric value comparison
     *         ->on('u.user_name', '=', '"peter"')      // String literal
     *         ->on('r.role_name', '=', ':role_name')   // Placeholder binding
     *             ->bind(':role_name', 'foo')
     *     ->where('u.user_id', '=', 1);
     * ```
     *
     * @example - Multiple joins:
     * ```php
     * Builder::table('users', 'u')
     *     ->innerJoin('roles', 'r')
     *         ->on('u.user_id', '=', 'r.role_user_id')
     *     ->leftJoin('orders', 'o')
     *         ->on('u.user_id', '=', 'o.order_user_id')
     *     ->where('u.user_id', '=', 1);
     * ```
     * 
     * @example - Using a closure for a subquery condition:
     * ```php
     * Builder::table('users', 'u')
     *     ->innerJoin('orders', 'o')
     *         ->on('u.user_id', '=', function (Builder $b): string {
     *             $result = $b->table('payments')->find(['user_id'])
     *                      ->where('status', '=', '"completed"')
     *                      ->get();
     *              if(empty($result))
     *                  throw new Exception('User not found');
     * 
     *             return $result->user_id
     *         })
     *     ->where('u.active', '=', 1);
     * ```
     *
     * > **Note:** 
     * > When chaining multiple joins, always call `on()` immediately after each `join()`.
     *
     * @see onCompound()
     * @see onCondition()
     * @see onSubquery()
     * @see join()
     * @see leftJoin()
     * @see rightJoin()
     * @see innerJoin()
     * @see crossJoin()
     * @see fullJoin()
     * @see bind()
     * 
     * @methodGroup QueryCondition Add simple conditions to join table.
     */
    public function on(string $condition, string $comparison, mixed $value, string $connector = 'AND'): self
    {
        [, $connector, ] = $this->assertOperators(__METHOD__, null, $connector);
        $value = $this->getValue($value);
        $value = ($value instanceof RawExpression) 
            ? $value->toString() 
            : (($value === null) ? 'NULL' : $value);

        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'clause' => $connector,
            'sql' => "{$condition} {$comparison} {$value}"
        ];

        return $this;
    }

    /**
     * Adds a raw SQL condition to the current JOIN clause.
     *
     * Use this method when you need full control over the SQL `ON` expression — 
     * for example, when building complex or non-standard join logic that can't 
     * be represented with the basic `on()` method.
     *
     * **Subquery Replace Filters:**
     * - `{{tableName}}` — Replaced with the join table name.
     * - `{{tableAlias}}` — Replaced with the join table alias.
     *
     * @param RawExpression|string $sql A raw SQL string or `RawExpression` instance to use in the join condition.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     *
     * @return self Returns the instance of builder class.
     * @throws InvalidArgumentException If empty array columns or invalid was provided.
     *
     * @example - Complex join conditions:
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('logs', 'l')
     *         ->onCondition('(u.id = 100 OR u.id = 200)')
     *         ->onCondition(Builder::raw('DATE(l.created_at) = CURDATE()'));
     * ```
     *
     * @example - Using subquery:
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('orders', forSubquery: true)
     *         ->onCondition('(
     *             SELECT order_id
     *             FROM {{tableName}}
     *             WHERE status = "active"
     *             AND amount > 500
     *         ) AS o');
     * ```
     *
     * @see on()
     * @see onSubquery()
     * @see onCompound()
     * @see join()
     * @see bind()
     *
     * > **When to Use:**
     * > - When you want to include advanced conditions (`OR`, functions, nested logic).
     * > - When joining on computed columns or database functions.
     * > - When your join needs to mix multiple logical clauses (e.g., `AND`, `OR`).
     * 
     * @methodGroup QueryCondition Add raw SQL query conditions to join table.
     */
    public function onCondition(RawExpression|string $sql, string $connector = 'AND'): self
    {
        [,$connector,] = $this->assertOperators(__METHOD__, null, $connector);

        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'clause' => $connector,
            'sql' => ($sql instanceof RawExpression) ? $sql->toString() : $sql
        ];

        return $this;
    }

    /**
     * Defines a subquery as the source for the current JOIN operation.
     *
     * This method attaches a complete SQL subquery directly to the join,
     * when join is marked `$forSubquery` as subquery join.
     *
     * **Subquery Replace Filters:**
     * - `{{tableName}}` — Replaced with the join table name.
     * - `{{tableAlias}}` — Replaced with the join table alias.
     *
     * @param RawExpression|string $sql A raw SQL string or `RawExpression` representing the subquery.
     *
     * @return self Returns the instance of builder class.
     *
     * @example - Join with subquery and outer conditions:
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('logs', 'l', true)
     *         ->onSubquery('(
     *             SELECT name
     *             FROM {{tableName}}
     *             WHERE logger_user_id = 100
     *         )')
     *         ->on('l.foo', '=', 'bar') // Outer condition
     *         ->onCondition('(u.id = 100 OR u.id = 200)');
     * ```
     *
     * @example - Manual alias assignment for subquery join:
     * ```php
     * Builder::table('users', 'u')
     *     ->leftJoin('logs', forSubquery: true)
     *         ->onSubquery('(
     *             SELECT name
     *             FROM {{tableName}}
     *             WHERE logger_user_id = 100
     *         ) AS l');
     * ```
     *
     * @see on()
     * @see onCondition()
     * @see onCompound()
     * @see join()
     * @see bind()
     * 
     * > **When to Use:**
     * > When the joined table is a subquery instead of a regular table.
     * 
     * @methodGroup QueryCondition Add raw SQL sub-query conditions to join table.
     */
    public function onSubquery(RawExpression|string $sql): self 
    {
        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'clause' => 'AND',
            'sql' => ($sql instanceof RawExpression) ? $sql->toString() : $sql
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
     * **Subquery Replace Filters:**
     * - `{{tableName}}` - Replaces placeholder with join table name.
     * - `{{tableAlias}}` - Replaces placeholder with join table alias.
     *
     * @param array<int,array<string,array>> $columns The columns conditions (from `[Builder::column(...), //...]`).
     * @param string $operator The operator that connects both column conditions (e.g., `AND`, `OR`).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
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
     * @see onCondition()
     * 
     * @methodGroup QueryCondition Add group query conditions to join table.
     */
    public function onCompound(array $columns, string $operator, string $connector = 'AND'): self
    {
        if($columns === []){
            throw new InvalidArgumentException(
                'The $columns array must not be empty. Use "Builder::column()" to create condition arrays.'
            );
        }

        [$operator, $connector, ] = $this->assertOperators(__METHOD__, $operator, $connector);

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
            'clause' => $connector,
            'sql' => '(' . implode(" {$operator} ", $parts) . ')'
        ];

        return $this;
    }

    /**
     * Sets table join condition as `INNER JOIN`.
     * 
     * @param string $table The table name.
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function innerJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'INNER', $forSubquery);
    }

    /**
     * Sets table join condition as `LEFT JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function leftJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'LEFT', $forSubquery);
    }

    /**
     * Sets table join condition as `RIGHT JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function rightJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'RIGHT', $forSubquery);
    }

    /**
     * Sets table join condition as `CROSS JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function crossJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'CROSS', $forSubquery);
    }

    /**
     * Sets table join condition as `FULL JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function fullJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'FULL', $forSubquery);
    }

    /**
     * Sets table join condition as `FULL OUTER JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see on(...)
     * @see join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function fullOuterJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'FULL OUTER', $forSubquery);
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
     * 
     * Generates: LIMIT 5,10
     * ```
     * 
     * @methodGroup QueryConfiguration Enforce query result limit for select operation. 
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
     * 
     * // This ensures the query affects at most 50 rows.
     * ```
     * 
     * @methodGroup QueryConfiguration Enforce query limit for update operation. 
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
     * 
     * @methodGroup QueryConfiguration Enforce strict where conditions for delete and update query. 
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
     * Applies ascending or descending sorting order or a raw SQL expression to query results.
     *
     * This method adds an `ORDER BY` clause to the query. You can specify either a column name
     * or a raw SQL expression for advanced ordering logic (e.g., custom relevance scores).
     *
     * @param string $column The column name or raw SQL expression to order by.
     * @param string $order The sorting direction, `ASC` or `DESC` (default: `ASC`).
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException If the column is empty or the order is invalid.
     * 
     * @see ascending()  - Orders results in ascending order (`ASC`).
     * @see descending() - Orders results in descending order (`DESC`).
     *
     * @example - Simple column ordering:
     * ```php
     * Builder::table('users')
     *      ->order('created_at', 'DESC');
     * // Generates: ORDER BY created_at DESC
     * ```
     *
     * @example - Complex SQL ordering:
     * ```php
     * Builder::table('blog')
     *      ->order("CASE 
     *          WHEN LOWER(title) LIKE '%php framework%' THEN 3
     *          WHEN LOWER(description) LIKE '%php framework%' THEN 2
     *          WHEN LOWER(body) LIKE '%php framework%' THEN 1
     *          ELSE 0 END", 'DESC');
     * // Generates: ORDER BY CASE ... END DESC
     * ```
     *
     * @methodGroup QueryFilters Add result ordering filters.
     */
    public function order(string $column, string $order = 'ASC'): self 
    {
        $order = strtoupper($order);
        $this->assertOrder($order, $column);

        $this->options['ordering'][] = "{$column} {$order}";

        return $this;
    }

    /**
     * Apply random order to the result set.
     * 
     * Add a random ordering to the query, it uses MySQL's `RAND()` function to return rows in a random order.
     * Optionally accepts a seed for repeatable randomness.
     *
     * @param int|null $seed Optional seed for deterministic shuffling (default: null).
     *
     * @return self Returns the current query builder instance.
     *
     * @example - Example:
     * ```php
     * Builder::table('posts')
     *      ->random();
     * // Generates: ORDER BY RAND()
     *
     * Builder::table('posts')
     *      ->random(42);
     * // Generates: ORDER BY RAND(42)
     * ```
     * 
     * @methodGroup QueryFilters Add random result ordering filters.
     */
    public function random(?int $seed = null): self 
    {
        $this->options['ordering'][] = ($seed === null) 
            ? 'RAND()' 
            : "RAND($seed)";

        return $this;
    }

    /**
     * Applies a descending order to the specified column in the result set.
     * 
     * Use when you want results from largest to smallest / newest to oldest  
     * (e.g., 10, 9, 8 …, Z, Y, X …, most recent dates first).
     * 
     * @param string $column The column to sort by in descending order.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException If the column name is empty or invalid.
     * 
     * @see order()
     * @see ascending()
     * 
     * @methodGroup QueryFilters Add result ordering filters.
     */
    public function descending(string $column): self 
    {
        return $this->order($column, 'DESC');
    }

    /**
     * Applies an ascending order to the specified column in the result set.
     * 
     * Use when you want results from smallest to largest / oldest to newest  
     * (e.g., 1, 2, 3 …, A, B, C …, earliest dates first).
     * 
     * @param string $column The column to sort by in ascending order.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException If the column name is empty or invalid.
     * 
     * @see order()
     * @see descending()
     * 
     * @methodGroup QueryFilters Add result ordering filters.
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
     * @return self Returns the current query builder instance.
     *
     * @example - Grouping results:
     * 
     * ```php
     * Builder::table('users')
     *      ->select(['name'])
     *      ->where('status', '=', 'active')
     *      ->group('country')
     *      ->get();
     * 
     * // Generates: GROUP BY country
     * ```
     * 
     * @methodGroup QueryFilters Add result grouping filters.
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
     * @param (Closure(Builder $static):mixed)|mixed $value The value to compare against.
     * @param string $operator Logical operator to combine with other HAVING clauses (default: 'AND').
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * // Generates: HAVING SUM(amount) >= 1000 OR COUNT(order_id) > 10
     * ```
     * 
     * @methodGroup QueryCondition Add query HAVING conditions.
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
     * @param (Closure(Builder $static):mixed)|mixed $value The value to compare against.
     * 
     * @return self Returns the current query builder instance.
     * 
     * @see find()
     * @see select()
     * @see count()
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
     * 
     * @methodGroup QueryCondition Add simple AND query conditions.
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
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     *   ->whereClause('status <> "archived"', 'WHERE')
     *   ->whereClause(new RawExpression('deleted_at IS NULL'), 'OR')
     *   ->get()
     * ```
     * 
     * @methodGroup QueryCondition Add raw SQL query conditions.
     */
    public function whereClause(RawExpression|string $sql, string $connector = 'AND'): self
    {
        $sql = trim(($sql instanceof RawExpression) ? $sql->toString() : $sql);

        if ($sql !== '') {
            $this->options['whereRaw'][] = "{$connector} {$sql}";
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
     * @param (Closure(Builder $static):mixed)|mixed $value The value to compare against.
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * // Generates: WHERE status = 'active' AND department REGEXP 'HR|Finance|Marketing'
     * ```
     * 
     * @methodGroup QueryCondition Add simple AND conditions.
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
     * @param (Closure(Builder $static):mixed)|mixed $value The value to compare the column against.
     * 
     * @return self Returns the current query builder instance.
     * 
     * @example - Using the `OR` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->select()
     *      ->or('status', '=', 'active')
     *      ->or('role', '!=', 'admin')
     *      ->get();
     * 
     * Generates: WHERE status = 'active' OR role != 'admin'
     * ```
     * 
     * @methodGroup QueryCondition Add simple OR conditions.
     */
    public function or(string $column, string $comparison, mixed $value): self
    {
        return $this->condition('OR', $column, $comparison, $value);
    }

    /**
     * Add a `BETWEEN` condition to the query.
     * 
     * This method adds a SQL `BETWEEN` clause for comparing a column's value
     * within one or more numeric or date ranges. You can pass multiple pairs of
     * values to create multiple ranges automatically joined by `OR`.
     * 
     * The `BETWEEN` operator includes both boundary values.
     * 
     * @param string $column The column name to apply the condition on.
     * @param array $values An array of range boundaries. Must contain an even number of values.
     *                        Each pair (e.g., [0, 100]) represents a range.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * @param bool $not Set to `true` to use `NOT BETWEEN` instead of `BETWEEN`.
     * 
     * @return self Returns the current query builder instance.
     * @throws DatabaseException If less than two values are provided, or an odd number of values is passed.
     * 
     * @see notBetween() - Opposite behavior (values outside the range).
     * 
     * @example - Examples:
     * ```php
     * // Single range (balance between 0 and 100)
     * Builder::table('transactions')
     *      ->select()
     *      ->where('status', 'active')
     *      ->between('balance', [0, 100])
     *      ->get();
     * 
     * // Produces:
     * // (balance BETWEEN :balance_btw_0_a AND :balance_btw_0_b)
     * 
     * // Multiple ranges (balance between 0–100 or 300–500)
     * $query->between('balance', [0, 100, 300, 500]);
     * 
     * // Produces:
     * // (balance BETWEEN :balance_btw_0_a AND :balance_btw_0_b
     * //  OR balance BETWEEN :balance_btw_2_a AND :balance_btw_2_b)
     * 
     * // Using NOT BETWEEN
     * $query->between('balance', [0, 100], 'AND', true);
     * ```
     * 
     * > **Note:**
     * > This method uses named placeholder parameter binding. 
     * > Passing SQL functions (like `NOW()` or `COUNT()`) as values will fail.  
     * > Use `whereClause()` instead if you need raw SQL conditions.
     * 
     * @methodGroup QueryCondition Add match between conditions.
     */
    public function between(string $column, array $values, string $connector = 'AND', bool $not = false): self
    {
        $count = count($values);
        $operator = $not ? 'NOT BETWEEN' : 'BETWEEN';

        if ($count < 2) {
            throw new DatabaseException(
                "{$operator} requires at least two values for column {$column}.",
                ErrorCode::VALUE_FORBIDDEN
            );
        }

        if ($count % 2 !== 0) {
            throw new DatabaseException(
                "Odd number of values passed to {$operator} for column {$column}, last value should be removed.",
                ErrorCode::USER_WARNING
            );
        }

        $segments = [];

        for ($i = 0; $i < $count - 1; $i += 2) {
            $a = $values[$i];
            $b = $values[$i + 1];

            $placeholder = $this->trimPlaceholder("{$column}_btw_{$i}");
            $keyA = "{$placeholder}_a";
            $keyB = "{$placeholder}_b";

            $segments[] = "({$column} {$operator} {$keyA} AND {$keyB})";

            $this->bind($keyA, $a)
                ->bind($keyB, $b);
        }

        if ($segments !== []) {
            $this->options['whereRaw'][] = trim($connector . ' (' . implode(' OR ', $segments) . ')');
        }

        return $this;
    }

    /**
     * Add a `NOT BETWEEN` condition to the query.
     * 
     * This method is a shortcut for calling {@see between()} with the `$not` flag set to `true`.
     * It returns rows where the column value is **outside** the specified range(s).
     * 
     * @param string $column The column name to apply the condition on.
     * @param array $values An array of range boundaries. Must contain an even number of values.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * @throws DatabaseException If less than two values are provided, or an odd number of values is passed.
     * 
     * @example - Examples:
     * ```php
     * // Single range (balance not between 0 and 100)
     * Builder::table('transactions')
     *      ->select()
     *      ->where('status', 'active')
     *      ->notBetween('balance', [0, 100])
     *      ->get();
     * 
     * // Produces:
     * // (balance NOT BETWEEN :balance_btw_0_a AND :balance_btw_0_b)
     * 
     * // Multiple NOT BETWEEN ranges
     * $query->notBetween('balance', [0, 100, 300, 500]);
     * 
     * // Produces:
     * // (balance NOT BETWEEN :balance_btw_0_a AND :balance_btw_0_b
     * //  OR balance NOT BETWEEN :balance_btw_2_a AND :balance_btw_2_b)
     * ```
     * 
     * @methodGroup QueryCondition Add match not between conditions.
     */
    public function notBetween(string $column, array $values, string $connector = 'AND'): self
    {
        return $this->between($column, $values, $connector, true);
    }

    /**
     * Adds a conditional clause to the query builder using scalar or array values.
     *
     * Supports both regular WHERE conditions and array-based `IN`/`NOT IN` clauses.
     *
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
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
     * 
     * @methodGroup QueryCondition Add query conditions.
     */
    public function condition(string $connector, string $column, string $comparison, mixed $value): self
    {
        return is_array($value) 
            ? $this->inArray($column, $comparison, $value, $connector) 
            : $this->clause($connector, $column, $comparison, $value);
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
     * - `Builder::RAW`      — Allow raw conditions (e.g., `WHERE NOT EXISTS (SELECT ...)`)
     *
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * @param ?string $column The column to apply the condition on or null for raw.
     * @param ?string $comparison Comparison operator (e.g., `=`, `<>`, `>=`, `LIKE`, etc.) or null for raw.
     * @param (Closure(Builder $static):mixed)|mixed $value The condition value to compare. Can be scalar or array (for `Builder::INARRAY`).
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
     *     ->clause('AND', 'roles', 'IN', ['admin', 'editor'], Builder::INARRAY);
     * ```
     * 
     * @see where()
     * @see and()
     * @see or()
     * @see in()
     * @see notIn()
     * @see condition()
     * @see against()
     * 
     * @methodGroup QueryCondition Add complex query conditions.
     */
    public function clause(
        string $connector,
        ?string $column,
        ?string $comparison,
        mixed $value,
        ?string $mode = null
    ): self 
    {
        [,$connector,] = $this->assertOperators(__METHOD__, null, $connector);
        
        $mode = strtoupper($mode ?? self::REGULAR);
        $modes = [self::REGULAR, self::RAW, self::CONJOIN, self::NESTED, self::AGAINST, self::INARRAY];
        
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

        if ($mode === self::RAW && ($column !== null || is_array($value))) {
            throw new InvalidArgumentException(
                'The RAW mode requires a null column name and non-array value.'
            );
        }

        $this->conditions[] = [
            'connector' => $connector,
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
     * 
     * @methodGroup QueryCondition Add full-text match filter conditions.
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
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * 
     * @example - Using the `LIKE` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->like('name', '%pet%')
     *      ->like('username', '%pet%', 'OR');
     * 
     * // Generates: WHERE name LIKE '%pet%' OR username LIKE '%pet%'
     * ```
     * 
     * @methodGroup QueryCondition Add matching LIKE conditions.
     */
    public function like(string $column, string $expression, string $connector = 'AND'): self
    {
        return $this->clause($connector, $column, 'LIKE', $expression);
    }

    /**
     * Add a `NOT LIKE` clause to the query to exclude pattern matches.
     *
     * @param string $column The column name to compare.
     * @param string $expression The pattern to exclude using SQL `NOT LIKE` (e.g. `%value%`).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * 
     * @example - Using the `NOT LIKE` conditioning:
     * 
     * ```php
     * Builder::table('users')
     *      ->notLike('name', '%pet%');
     * 
     * // Generates: `WHERE name NOT LIKE '%pet%'`
     * ```
     * 
     * @methodGroup QueryCondition Add matching NOT LIKE conditions.
     */
    public function notLike(string $column, string $expression, string $connector = 'AND'): self
    {
        return $this->clause($connector, $column, 'NOT LIKE', $expression);
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
     * @return self Returns the current query builder instance.
     * @throws DatabaseException If no match columns have been defined via match().
     * @throws InvalidArgumentException If the sort order is invalid.
     *
     * @see match()
     * @see against()
     * 
     * @methodGroup QueryCondition Add full-text match against order filters.
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
     * @param (Closure(Builder $static):mixed)|mixed $value The value to match against. 
     *              Can be a string, number, or a Closure
     *                             to defer value evaluation.
     * @param string|int $mode The match mode, can be a predefined constant or raw string.
     *     Constants:
     *       - Builder::MATCH_NATURAL
     *       - Builder::MATCH_BOOLEAN
     *       - Builder::MATCH_NATURAL_EXPANDED
     *       - Builder::MATCH_EXPANSION
     *
     * @return self Returns the current query builder instance.
     * @throws DatabaseException If match columns are missing or invalid.
     *
     * @see match()
     * @see orderAgainst()
     * 
     * @methodGroup QueryCondition Add full-text match against conditions.
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
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * @methodGroup QueryCondition Add is not null check conditions.
     */
    public function isNotNull(string $column, string $connector = 'AND'): self
    {
        return $this->clause($connector, $column, '', self::raw('IS NOT NULL'));
    }

    /**
     * Adds a condition to filter results where the given column is NULL.
     *
     * This method appends an "AND column IS NULL" condition to the query.
     * It ensures that only records with a null value in the specified column are retrieved.
     * 
     * @param string $column The column name to check for null values.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * @methodGroup QueryCondition Add is null check conditions.
     */
    public function isNull(string $column, string $connector = 'AND'): self
    {
        return $this->clause($connector, $column, '', self::raw('IS NULL'));
    }

    /**
     * Adds a condition to filter results where the given column is `NULL` or `NOT NULL`.
     *
     * This method appends a null match based on "$connector" condition to the query.
     * It ensures that only records with a null or non-null value in the specified column are retrieved.
     *
     * @param string $column The column name to check for non-null values.
     * @param bool $isNull Whether the the column should be null or not (default: true).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * @methodGroup QueryCondition Add null check conditions.
     */
    public function nullable(string $column, bool $isNull = true, string $connector = 'AND'): self
    {
        return $this->clause(
            $connector, 
            $column, 
            '', 
            self::raw($isNull ? 'IS NULL' : 'IS NOT NULL')
        );
    }

    /**
     * Add a column and its value to use in an `UPDATE` or `INSERT` query.
     *
     * You can call this method multiple times to set several columns before
     * running `update()` or `insert()`.  
     * 
     * - When used before `update()`, it decides which columns and values will be updated.  
     * - When used before `insert()`, it builds the values that will be inserted.
     *
     * @param string $column The name of the column to set.
     * @param mixed $value The value to assign (can be a simple value, a closure, or an expression).
     * @param int|null $index Optional. Use this when inserting multiple rows at once.
     *
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException If you try to mix indexed and non-indexed set calls.
     *
     * @example - Update example:
     * ```php
     * Builder::table('users')
     *     ->where('id', 1)
     *     ->set('status', 'active')
     *     ->set('updated_at', Builder::datetime())
     *     ->update();
     * 
     * // Result:
     * // UPDATE users SET status = 'active', updated_at = '2024-04-03 14:30:45' WHERE id = 1
     * ```
     *
     * @example - Insert example:
     * ```php
     * Builder::table('users')
     *     ->set('name', 'Peter')
     *     ->set('age', 30)
     *     ->insert();
     * 
     * // Result:
     * // INSERT INTO users (name, age) VALUES ('Peter', 30)
     * ```
     * 
     * > **Note:**
     * > If you use an index (e.g., `$index = 0`), it assumes you're inserting multiple rows.
     * > You can’t mix indexed and non-indexed calls in the same query.
     * 
     * @methodGroup QueryConfiguration Add one or more values to insert or update query.
     */
    public function set(string $column, mixed $value, ?int $index = null): self
    {
        $isIndexed = ($index !== null && $index >= 0);
        $isEmpty = $this->querySetValues === [];
        $hasIndexed = !$isEmpty && isset($this->querySetValues[0]);
        $hasFlat = !$isEmpty && !$hasIndexed;

        if ($isIndexed && $hasFlat) {
            throw new InvalidArgumentException(
                'Cannot use indexed set() after non-indexed set(). ' .
                'Use either indexed or non-indexed calls consistently.'
            );
        }

        if (!$isIndexed && $hasIndexed) {
            throw new InvalidArgumentException(
                'Cannot use non-indexed set() after indexed set(). ' .
                'Use either indexed or non-indexed calls consistently.'
            );
        }

        if ($isIndexed) {
            $this->querySetValues[$index][$column] = $value;
        } else {
            $this->querySetValues[$column] = $value;
        }

        return $this;
    }

    /**
     * Conjoin multiple conditions using either `AND` or `OR`.
     *
     * This method creates a logical condition group where conditions are combined 
     * using the specified operator.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The conditions to group.
     *                          Or `Builder::column(...)` method for simplified builder.
     * @param string $groupConnector The `AND` or `OR` logical connector within group (default: `AND`).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * // Generates: WHERE (column1 = 1 OR column2 = 2)
     * ```
     * 
     * @methodGroup QueryCondition Add group conditions.
     */
    public function conjoin(
        array $conditions, 
        string $groupConnector = 'AND', 
        string $connector = 'AND'
    ): self
    {
        [$groupConnector, $connector, ] = $this->assertOperators(
            __METHOD__, 
            $groupConnector, 
            $connector
        );

        $this->conditions[] = [
            'connector' => $connector,
            'mode' => self::CONJOIN,
            'operator' => $groupConnector,
            'conditions' => $conditions
        ];

        return $this;
    }

    /**
     * Creates a nested conjoin condition group by combining two condition sets.
     *
     * This method groups two sets of conditions and binds them with the specified logical operator.
     * Use `Builder::column()` for simplified column builder.
     *
     * @param array<int,array<string,array<string,mixed>>> $firstGroup An array of first group conditions.
     * @param array<int,array<string,array<string,mixed>>> $secondGroup An array of second group conditions.
     * @param string $groupConnector The `AND` or `OR` logical connector within each group (default: `AND`).
     * @param string $nestedConnector The `AND` or `OR` logical connector to bind groups (default: `AND`).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if invalid group operator is specified.
     *
     * @example - Generating a nested  conditions:
     * 
     * ```php
     * Builder::table('fooTable')
     * ->where('fooUser', '=', 100)
     * ->nested([
     *          ['foo' => ['comparison' => '=', 'value' => 1]],
     *          ['bar' => ['comparison' => '=', 'value' => 2]]
     *      ],
     *      [
     *          ['baz' => ['comparison' => '=', 'value' => 3]],
     *          ['bra' => ['comparison' => '=', 'value' => 4]]
     *      ], 
     *      'OR', 
     *      'AND'
     * );
     * 
     * // Generates: WHERE fooUser = 100 AND ((foo = 1 OR bar = 2) AND (baz = 3 OR bra = 4))
     * ```
     * 
     * @example - Using Column: 
     * 
     * ```php
     * $tbl = Builder::table('fooTable')
     *      ->nested(
     *          [Builder::column('column1', '=', 1), Builder::column('column2', '=', 2)],
     *          [Builder::column('column1', '=', 1), Builder::column('column2', '=', 2)], 
     *          'OR', // Inner group logical connector
     *          'AND' // Outer group logical connector
     *      );
     * ```
     * 
     * @methodGroup QueryCondition Add nested group conditions.
     */
    public function nested(
        array $firstGroup, 
        array $secondGroup, 
        string $groupConnector = 'AND', 
        string $nestedConnector = 'AND',
        string $connector = 'AND'
    ): self
    {
        [$groupConnector, $connector, $nestedConnector] = $this->assertOperators(
            __METHOD__,
            $groupConnector, 
            $connector, 
            $nestedConnector
        );

        $this->conditions[] = [
            'connector' => $connector,
            'mode' => self::NESTED,
            'bind' => $nestedConnector,
            'operator' => $groupConnector,
            'X' => $firstGroup,
            'Y' => $secondGroup
        ];

        return $this;
    }

    /**
     * Define a column condition for use in nested and conjoin queries.
     *
     * This method simplifies the process of specifying a column condition with a comparison operator 
     * and a value.
     * 
     * It is particularly useful when used within methods like:
     * 
     * - `conjoin()`
     * - `nested()`
     * - `andConjoin()`
     * - `orConjoin()`
     * - `orNested()`
     * - `andNested()`
     *
     * @param string $name The name of the column.
     * @param string $comparison The comparison operator (e.g., `=`, `!=`, `<`, `>`, `LIKE`).
     * @param (Closure(Builder $static):mixed)|mixed $value The value to compare against.
     *
     * @return array<string,array> Returns an array representing a column structure.
     *
     * @example - Using `column` with `conjoin()`:
     * 
     * ```php
     * $tbl = Builder::table('users')
     *      ->conjoin([
     *          Builder::column('age', '>=', 18),
     *          Builder::column('status', '=', 'active')
     *      ], 'AND');
     * 
     * // Generates: WHERE (age >= 18 AND status = 'active')
     * ```
     *
     * @example - Using `column` directly in a query:
     * 
     * ```php
     * $tbl = Builder::table('products')
     *      ->nested(
     *          [Builder::column('price', '>', 100), Builder::column('rate', '>=', 10)],  
     *          [Builder::column('price', '>', 100), Builder::column('price', '>', 100)] 
     *      );
     * 
     * // Generates: WHERE ((price > 100 AND rate >= 10) OR (price > 100 AND rate >= 100))
     * ```
     * 
     * @methodGroup QueryColumns Build columns for array columns.
     */
    public static function column(string $name, string $comparison, mixed $value): array
    {
       return [$name => ['comparison' => $comparison, 'value' => $value]];
    }

    /**
     * Bind a named placeholder parameter to a value.
     *
     * This method allows you manually assign values to SQL placeholders (`:param`) 
     * used anywhere in the query — including joins, clauses, or even raw column expressions.
     *
     * @param string $placeholder The named placeholder. Must start with a colon `:` (e.g. `:id`).
     * @param (Closure(Builder $static):mixed)|mixed $value The value to bind to the placeholder. 
     *              Arrays are JSON-encoded.
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException If the placeholder does not start with a colon `:`.
     *
     * @example - Binding inside a JOIN condition:
     * ```php
     * $result = Builder::table('users', 'u')
     *     ->select()
     *     ->innerJoin('orders', 'o')
     *         ->on('o.order_user_id', '=', 'u.user_id')
     *         ->on('o.order_id', '=', ':oid')
     *         ->bind(':oid', 13445)
     *     ->where('u.user_id', '=', 100)
     *     ->get();
     * ```
     *
     * @example - Binding inside a SELECT column expression:
     * ```php
     * $result = Builder::table('users', 'u')
     *     ->select([
     *         'u.*', 
     *         'ST_Distance_Sphere(
     *              u.location,
     *              ST_SRID(POINT(:lng, :lat), 4326)
     *          ) / 1000 AS distance'
     *     ])
     *     ->where('u.status', '=', 'active')
     *     ->having('distance', '<=', 10)
     *     ->bind(':lat', 1.3521)
     *     ->bind(':lng', 103.8198)
     *     ->get();
     * ```
     * > **Note:** 
     * > Arrays are automatically JSON-encoded before binding.
     * 
     * @methodGroup QueryCondition Bind named placeholder to a value.
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

        $this->options['binds'][$placeholder] = self::escape($value, strict: true);

        return $this;
    }

    /**
     * Groups multiple conditions using the `OR` operator.
     *
     * This method creates a logical condition group where at least one condition must be met.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The conditions to be grouped.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * @see conjoin()
     *
     * @example - Example: 
     * 
     * ```php
     * Builder::table('fooTable')->orConjoin([
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ]);
     * 
     * // Generates: WHERE (column1 = 1 OR column2 = 2)
     * ```
     * 
     * @methodGroup QueryCondition Add single group ORs condition.
     */
    public function orConjoin(array $conditions, string $connector = 'AND'): self
    {
        return $this->conjoin($conditions, 'OR', $connector);
    }

    /**
     * Groups multiple conditions using the `AND` operator.
     *
     * This method creates a logical condition group where all conditions must be met.
     *
     * @param array<int,array<string,array<string,mixed>>> $conditions The conditions to be grouped.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * @see conjoin()
     *
     * @example - Example: 
     * 
     * ```php
     * Builder::table('fooTable')->andConjoin([
     *     Builder::column('column1', '=', 1),
     *     Builder::column('column2', '=', 2)
     * ]);
     * 
     * // Generates: WHERE (column1 = 1 AND column2 = 2)
     * ```
     * 
     * @methodGroup QueryCondition Add single group ANDs condition.
     */
    public function andConjoin(array $conditions, string $connector = 'AND'): self
    {
        return $this->conjoin($conditions, 'AND', $connector);
    }

    /**
     * Binds two condition groups using the `OR` operator.
     *
     * This method creates two logical condition groups and combines them using `OR`.
     *
     * @param array<int,array<string,array<string,mixed>>> $firstGroup The first group conditions.
     * @param string $nestedConnector The logical connector to bind both group (e.g, `AND`, `OR`).
     *              - `AND` - Groups are combined with AND (e.g., `WHERE ((a OR b) AND (c OR d))`).
     *              - `OR`  - Groups are combined with OR (e.g., `WHERE ((a OR b) OR (c OR d))`).
     * @param array<int,array<string,array<string,mixed>>> $secondGroup The second group conditions.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * // Generates: WHERE ((foo = 1 OR bar = 2) AND (baz = 3 OR bra = 4))
     * ```
     * 
     * @methodGroup QueryCondition Add nested ORs condition.
     */
    public function orNested(
        array $firstGroup, 
        string $nestedConnector,
        array $secondGroup, 
        string $connector = 'AND'
    ): self
    {
        return $this->nested(
            $firstGroup, 
            $secondGroup, 
            'OR', 
            $nestedConnector, 
            $connector
        );
    }

    /**
     * Binds two condition groups using the `AND` operator.
     *
     * This method creates two logical condition groups and combines them using `AND`.
     *
     * @param array<int,array<string,array<string,mixed>>> $firstGroup The first group conditions.
     * @param string $nestedConnector The logical connector to join both group (e.g, `AND`, `OR`).
     *                  - `AND` - Groups are combined with AND (e.g., `WHERE ((a AND b) AND (c AND d))`).
     *                  - `OR`  - Groups are combined with OR (e.g., `WHERE ((a AND b) OR (c AND d))`).
     * @param array<int,array<string,array<string,mixed>>> $secondGroup The second group conditions.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
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
     * 
     * // Generates: WHERE ((foo = 1 AND bar = 2) OR (baz = 3 AND bra = 4))
     * ```
     * 
     * @methodGroup QueryCondition Add nested ANDs condition.
     */
    public function andNested(
        array $firstGroup, 
        string $nestedConnector, 
        array $secondGroup, 
        string $connector = 'AND'
    ): self
    {
        return $this->nested(
            $firstGroup, 
            $secondGroup, 
            'AND', 
            $nestedConnector, 
            $connector
        );
    }

    /**
     * Adds an `IN` condition to the query using the `IN (...)` SQL expression.
     * 
     * Use this method to find rows where the given column's value matches any value in a provided list.
     * 
     * @param string $column The column name to match against.
     * @param (Closure(Builder $static): array)|array<int,string|int|float> $values 
     *        A list of values or a Closure returning an array of values.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Returns the current query builder instance.
     * 
     * @throws InvalidArgumentException If the provided values are empty or invalid.
     * @throws JsonException If an error occurs while encoding the values.
     * 
     * @example - Example:
     * ```php
     * Builder::table('languages')
     *     ->select()
     *     ->in('tag', ['php', 'sql'])
     *     ->get();
     * 
     * // Generates: `IN ('php', 'sql')`
     * ```
     * 
     * @methodGroup QueryCondition Add IN condition.
     */
    public function in(string $column, Closure|array $values, string $connector = 'AND'): self
    {
        return $this->inArray($column, 'IN', $values, $connector);
    }

    /**
     * Adds a `NOT IN` condition to the query using the `NOT IN (...)` SQL expression.
     *
     * Use this method to find rows where the given column's value does **not** match any value in a provided list.
     *
     * @param string $column The column name to check against.
     * @param (Closure(Builder $static): array)|array<int, string|int|float> $values 
     *        A list of values or a Closure returning an array of values.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     *
     * @return self Returns the current query builder instance.
     *
     * @throws InvalidArgumentException If the provided values are empty or invalid.
     * @throws JsonException If an error occurs while encoding the values.
     *
     * @example - Example:
     * ```php
     * Builder::table('users')
     *     ->where('country', '=', 'NG')
     *     ->notIn('state', ['Enugu', 'Lagos', 'Abuja']);
     * // Generates: `NOT IN ('Enugu', 'Lagos', 'Abuja')`
     * ```
     * 
     * @methodGroup QueryCondition Add NOT IN condition.
     */
    public function notIn(string $column, Closure|array $values, string $connector = 'AND'): self
    {
        return $this->inArray($column, 'NOT', $values, $connector);
    }

    /**
     * Add a condition for `FIND_IN_SET` expression for the given column name.
     *
     * @param string $search The search value or column name depending on `$isSearchColumn`.
     * @param string $comparison The comparison operator for matching (e.g., `exists`, `first`, `>= foo`, `<= bar`).
     * @param array<int,mixed>|string $list The comma-separated values or a column name containing the list.
     * @param bool $isSearchColumn Whether the `$search` argument is a column name (default: false).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
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
     * 
     * @methodGroup QueryCondition Add find in set condition.
     */
    public function inset(
        string $search, 
        string $comparison, 
        array|string $list,
        bool $isSearchColumn = false,
        string $connector = 'AND'
    ): self
    {
        if($list === [] || $list === ''){
            throw new InvalidArgumentException('Invalid argument $list, expected non-empty array or string.');
        }

        $isList = is_array($list);
        $this->conditions[] = [
            'connector' => $connector, 
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
     * @return self Returns the current query builder instance.
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
     * @return self Returns the current query builder instance.
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
     * **Applies to:**
     * 
     * `insert()` - Before calling insert() method.
     * `copy()`  - Before to() method.
     * 
     * If enabled, `insert` method will replaces existing records, 
     * by first **deleting** existing rows with the same primary key or unique key before inserting new ones. 
     * 
     * @param bool $useReplace Set to true to use `REPLACE` instead of `INSERT` (default: true).
     * 
     * @return self Returns the current query builder instance.
     * 
     * @see insert()
     * @see copy()
     * 
     * > **Note:** 
     * > Enabling this may lead to unintended data loss, especially if foreign key constraints exist.
     * >
     * > **Warning:** 
     * > Since `replace` removes and re-inserts data, it can reset auto-increment values 
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
     * @return self Returns the current query builder instance.
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
            return $value->toString();
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
            return $numericCheck ? to_numeric($value, true) : (string) $value;
        }

        if (is_resource($value)) {
            $stream = stream_get_contents($value);

            if ($stream === false) {
                throw new DatabaseException(
                    'Failed to read from resource stream.',
                    ErrorCode::RUNTIME_ERROR
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
     * @return self Returns the current query builder instance.
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
     * @return self Returns the current query builder instance.
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
     * @return Cache|null Returns the current cache instance if set, or null if no cache has been initialized.
     */
    public function getCache(): ?Cache
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

        if($format === null || PHP_SAPI === 'cli' || Luminova::isCommand()){
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
     * @param string|null $storage Optional storage name for the cache. 
     *                      Defaults to the current table name or 'capture' if not specified.
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
     * @param string|null $key Unique key to identify the cache item.
     * @param string|null $storage Optional cache storage name. 
     *                  Defaults to the current table or `'capture'`.
     * @param DateTimeInterface|int $expiry Cache expiration time (default: 7 days).
     * @param string|null $subfolder Optional subdirectory for file-based cache (default: `'database'`).
     * @param string|null $persistentId Optional ID for memory-based cache connections (default: `'__database_builder__'`).
     *
     * @return self Returns the current builder instance.
     * @throws CacheException If a cache initialization or read operation fails.
     * 
     * @methodGroup QueryConfiguration Execute query selectors with cache options.
     */
    public function cache(
        ?string $key = null,
        ?string $storage = null,
        DateTimeInterface|int $expiry = 7 * 24 * 60 * 60,
        ?string $subfolder = null,
        ?string $persistentId = null
    ): self 
    {
        if (!$this->isCacheable) {
            return $this;
        }

        $key ??= Luminova::getCacheId($this->tableName ?: 'raw-query', false);
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
     * Execute and insert one or many records into a database table.
     * 
     * This method accepts either:
     * - A single associative array (column => value).
     * - An array of multiple associative arrays (to insert many rows at once).
     *
     * By default, it uses prepared statements for safety and performance.
     * You can also run a raw query if needed by setting `$usePrepare` to false.
     * 
     * @param array<int,array<string,mixed>>|array<string,mixed>|null $values The records to insert or build using (`set()` method).
     * Each record must be an associative array where:
     *     - Keys are column names
     *     - Values are the values to insert
     * @param bool $usePrepare Whether to use prepared statements (default: true).
     * @param bool $escapeValues Whether to escape values if `$usePrepare` is true (default: true).
     * 
     * @return int Returns the number of rows inserted.
     * 
     * @throws DatabaseException If the data format is invalid (e.g., not associative arrays).
     * @throws JsonException If array values cannot be encoded to JSON.
     * 
     * @see set()
     * @see replace()
     * @see onDuplicate()
     * @see ignoreDuplicate()
     * @see copy()
     * 
     * @example - Insert a single row:
     * ```php
     * Builder::table('logs')->insert([
     *     'message' => 'User login',
     *     'created_at' => Builder::raw('NOW()')
     * ]);
     * ```
     * 
     * @example - Insert multiple rows:
     * ```php
     * Builder::table('users')->insert([
     *     ['name' => 'Alice', 'age' => 28],
     *     ['name' => 'Bob', 'age' => 34]
     * ]);
     * ```
     * 
     * @example - Insert inside a transaction:
     * ```php
     * $tbl = Builder::table('users');
     * $tbl->transaction();
     * 
     * $inserted = $tbl->insert([
     *     ['name' => 'Charlie', 'age' => 40],
     *     ['name' => 'Diana', 'age' => 36]
     * ]);
     * 
     * if ($inserted) {
     *     $tbl->commit();
     * } else {
     *     $tbl->rollback();
     * }
     * ```
     * 
     * @example - Use REPLACE instead of INSERT:
     * ```php
     * Builder::table('logs')
     *     ->replace(true)
     *     ->insert([
     *         'id' => 1, // if row with same PK exists, it will be replaced
     *         'message' => 'System reboot',
     *         'created_at' => Builder::raw('NOW()')
     *     ]);
     * ```
     * 
     * @example - Insert with ON DUPLICATE KEY UPDATE:
     * ```php
     * Builder::table('users')
     *     ->onDuplicate('last_login', '=', Builder::raw('NOW()'))
     *     ->insert([
     *         'user_id' => 1001,
     *         'name' => 'John Doe',
     *         'last_login' => Builder::raw('NOW()')
     *     ]);
     * ```
     * 
     * @example - Insert while ignoring duplicate key errors:
     * ```php
     * Builder::table('users')
     *     ->ignoreDuplicate(true)
     *     ->insert([
     *         'user_id' => 1002,
     *         'name' => 'Jane Doe'
     *     ]);
     * ```
     * 
     * @methodGroup QueryExecutor Execute query and insert one or more records to table.
     */
    public function insert(?array $values = null, bool $usePrepare = true, bool $escapeValues = true): int
    {
        $values = (!$values || $values === []) ? $this->querySetValues : $values;
        $this->assertInUpValues($values);

        if (!isset($values[0])) {
            $values = [$values];
        }
        
        $inserted = 0;
        $type = $this->isReplace ? 'REPLACE' : 'INSERT';

        if (!is_associative($values[0])) {
            throw new DatabaseException(
                sprintf('Invalid %s values: each row must be an associative array.', $type), 
                ErrorCode::VALUE_FORBIDDEN
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
     * Execute and update one or more records in the database table.
     * 
     * This builds and executes an `UPDATE` query with the provided values.
     * It will respect any `WHERE` conditions, joins, and limits you’ve applied
     * with the query builder before calling this method.
     * 
     * **Safety:**
     * - If strict mode is enabled (`strict(true)`), an update without `WHERE`
     *   conditions will throw an exception (to prevent accidental full-table updates).
     * - Values must be passed as an associative array (`column => value`).
     * 
     * @param array<string,mixed>|null $values The array of columns and values to update, or build using (`set()` method).
     * 
     * @return int Return the number of rows affected.
     * 
     * @throws DatabaseException If no values are provided, or if input is not an associative array.  
     * @throws JsonException If JSON encoding fails when binding values.  
     * 
     * @example - Update specific row:
     * ```php
     * Builder::table('users')
     *     ->where('id', '=', 1)
     *     ->update([
     *         'last_login' => Builder::datetime(),
     *         'attempts' => 0
     *     ]);
     * ```
     * 
     * @example - Update rows using set method:
     * ```php
     * Builder::table('users')
     *     ->where('id', '=', 1)
     *     ->set('last_login', Builder::datetime())
     *     ->set('attempts', 0)
     *     ->update();
     * ```
     * 
     * @example - Update with raw expression:
     * ```php
     * Builder::table('users')
     *     ->where('id', '=', 1)
     *     ->update([
     *         'score' => Builder::raw('score + 5')
     *     ]);
     * ```
     * 
     * @example - Update with joins and strict mode:
     * ```php
     * Builder::table('orders', 'o')
     *     ->innerJoin('users', 'u')
     *          ->on('u.id', '=', 'o.user_id')
     *     ->where('u.status', '=', 'inactive')
     *     ->strict(true) // prevents missing WHERE clause accidents
     *     ->update(['o.cancelled' => 1]);
     * ```
     * 
     * @methodGroup QueryExecutor Execute query and update table records.
     */
    public function update(?array $values = null): int 
    {
        $this->assertStrictConditions(__METHOD__);

        $values = (!$values || $values === []) ? $this->querySetValues : $values;
        $this->assertInUpValues($values, false);

        $sql = "UPDATE {$this->tableName}";
        $sql .= $this->tableAlias ? " AS {$this->tableAlias}" : '';
        $sql .= $this->getJoinConditions();
        $sql .= ' SET ' . $this->buildPlaceholder($values, true);
        $this->buildConditions($sql);
        $this->addRawWhereClause($sql);

        $limit = $this->maxLimit[1] ?? 0;

        if($limit > 0){
            $sql .= " LIMIT {$limit}";
        }

        if($this->debugMode !== self::DEBUG_NONE){
            if($this->debugMode === self::DEBUG_BUILDER){
                $this->setDebugInformation($sql, 'update', $values);
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
            if($this->debugMode === self::DEBUG_NONE){
                $this->db->prepare($sql);
            }

            $this->bindStrictColumns($values);
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
     * @return self Returns the current query builder instance.
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
     * When set to `true`, duplicate keys will be ignored. 
     * If set to `false`, the default behavior is to handle duplicates as normal 
     * (which may result in an error or update based on the context).
     * 
     * @param bool $ignore Whether to ignore duplicates (default: `true`).
     * 
     * @return self Returns the current query builder instance.
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
     * @return int Return the number of affected rows.
     * @throws InvalidArgumentException If the target table name is empty.
     * @throws DatabaseException If copy mode isn't active, or if column mismatch occurs.
     * @throws JsonException If copy operation involves JSON-encodable values and encoding fails.
     *
     * @see copy() - To prepare copy operation.
     *
     * > **Warning:** 
     * > Ensure that source and destination columns match in count and structure.
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
                ErrorCode::BAD_METHOD_CALL
            );
        }

        if ($columns === [] || ($fromColumns !== ['*'] && count($fromColumns) !== count($columns))) {
            throw new DatabaseException(
                ($columns === [] || $fromColumns === []) 
                    ? 'Source and destination columns must not be empty.'
                    : 'Mismatch between source and destination column counts.',
                ErrorCode::INVALID_ARGUMENTS
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
     * > **Note:** 
     * > When using union tables, do not call `get`, `fetch` or `stmt` before adding table to another.
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
     * > **Note:** 
     * > When using union tables, do not call `get`, `fetch` or `stmt` before adding table to another.
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
     * 
     * @methodGroup QueryColumns Maps union columns.
     */
    public function columns(array $columns = ['*']): self
    {
        $this->options['unionColumns'] = $columns;
        return $this;
    }
 
    /**
     * Execute a raw SQL query that was set earlier with `query()`.
     *
     * You can use this method to execute prepared SQL statements with optional
     * placeholder values and control how the result is returned.
     *
     * **Return Modes:**
     * - `RETURN_ALL`: Return all rows (default)
     * - `RETURN_NEXT`: Return a single row or the next available row
     * - `RETURN_2D_NUM`: Return a 2D numeric array
     * - `RETURN_ID`: Return the last inserted ID
     * - `RETURN_COUNT`: Return the number of affected rows
     * - `RETURN_COLUMN`: Return a specific column from the result
     * - `RETURN_INT`: Return an integer count of records
     * - `RETURN_STMT`: Return a prepared statement object
     * - `RETURN_RESULT`: Return a raw result object
     *
     * @param array<string,mixed>|null $placeholder Optional key-value pairs for query placeholders.
     * @param int $returnMode Record return mode e.g. `RETURN_NEXT`, (default: `RETURN_ALL`).
     * @param int $fetchMode Result fetch mode e.g, `FETCH_ASSOC`, `FETCH_CLASS`, (default: `FETCH_OBJ`).
     * @param bool $escape Whether to escape placeholder values (default: `false`).
     *
     * @return DatabaseInterface|mixed Returns query result, a statement object, or `false` on failure.
     * @throws DatabaseException If called before setting a query with `query()`.
     *
     * @see query() - Prepare Raw SQL query.
     *
     * @example - Running Raw SQL query:
     * ```php
     * $result = Builder::query("SELECT * FROM users LIMIT 10")
     *      ->execute();
     * ```
     *
     * @example Running SQL query with bind param:
     * ```php
     * $user = Builder::query("SELECT * FROM users WHERE id = :id LIMIT 1")
     *     ->execute(['id' => 100], RETURN_NEXT);
     * ```
     *
     * @example Using cache:
     * ```php
     * $user = Builder::query("SELECT * FROM users WHERE id = :id")
     *     ->cache()
     *     ->execute(['id' => 1]);
     * ```
     * 
     * @methodGroup QueryExecutor Execute raw query build using `query()` method and return result.
     */
    public function execute(
        ?array $placeholder = null, 
        int $returnMode = RETURN_ALL, 
        int $fetchMode = FETCH_OBJ,
        bool $escape = false
    ): mixed 
    {
        if($this->rawQuery === ''){
            throw new DatabaseException(
                sprintf(
                    'Cannot call "%s" without a prepared SQL query. Use "%s" first.',
                    '$stmt->execute(...)',
                    'Builder::query(...)'
                ),
                ErrorCode::VALUE_FORBIDDEN
            );
        }

        $isCacheable = $returnMode !== RETURN_STMT && Database::isSqlQuery($this->rawQuery);

        if($isCacheable){
            $response = $this->getFromCache($returnMode);

            if($response !== null){
                return $response;
            }
        }

        try {
            $response = $this->executeRawSqlQuery(
                $this->rawQuery, 
                $placeholder ?? [], 
                $returnMode, 
                $fetchMode,
                $escape
            );

            if($isCacheable){
                $this->cacheResultIfValid($response);
            }

            return $response;
        } catch (Throwable $e) {
            $this->resolveException($e);
        }

        return null;
    }

    /**
     * Execute selectable query and return the result.
     *
     * This method is used after building a query with methods like:
     * - `select()`
     * - `find()`
     * - `count()`
     * - `sum()`
     * - `average()`
     *
     * It automatically runs the query and returns the result in the format you specify.
     *
     * @param int $fetchMode Result fetch mode e.g, `FETCH_ASSOC`, `FETCH_CLASS`, (default: `FETCH_OBJ`).
     * @param int|null $returnMode Record return mode e.g. `RETURN_NEXT`, (default: `RETURN_ALL`).
     *
     * @return mixed Returns query result on success, or `false`/`null` on failure.
     * @throws DatabaseException If no query is set or execution fails.
     *
     * @see fetch() - To execute query and return result one after the other.
     * @see stmt() - To execute query and return `DatabaseInstance` that resolve to statement object.
     * @see promise() - To execute query and return promise object that resolve to result.
     *
     * @example - Basic SELECT example:
     * ```php
     * $result = Builder::table('users')
     *      ->select(['email', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get(FETCH_OBJ);
     * ```
     *
     * @example - Fetching a single row:
     * ```php
     * $user = Builder::table('users')
     *      ->find(['email', 'name'])
     *      ->get(FETCH_ASSOC, RETURN_NEXT);
     * ```
     * 
     * @methodGroup QueryExecutor Execute query and return one or more results.
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
     * Executes and fetches one row at a time. 
     * 
     * **Suitable for:**
     * - `select()` - When returning more than one results.
     * - `while` - For streaming or while loops.
     * 
     * @param int $fetchMode Result fetch mode e.g, `FETCH_ASSOC`, `FETCH_CLASS`, (default: `FETCH_OBJ`).
     * 
     * @return object|array|int|float|bool|null Returns the fetched row, or `false`/`null` if execution fails.
     * @throws DatabaseException If no query is available or execution fails.
     * 
     * > **Note:** 
     * > The fetch method executes statements directly, so query result caching is not supported.
     * 
     * @see get()
     * @see stmt()
     * @see promise()
     * 
     * @example - Statement example:
     * ```php
     * $stmt = Builder::table('users')
     *     ->select(['email', 'name'])
     *     ->where('country', '=', 'NG');
     * 
     * while ($row = $stmt->fetch(FETCH_OBJ)) {
     *     echo $row->email;
     * }
     * $stmt->freeStmt();
     * ```
     * 
     * @methodGroup QueryExecutor Execute query and fetches raw one after the other.
     */
    public function fetch(int $fetchMode = FETCH_OBJ): mixed 
    {
        $this->assertHandler(__METHOD__);

        return ((self::$stmt instanceof DatabaseInterface && self::$stmt->ok()) 
            ? self::$stmt 
            : $this->stmt()
        )->fetch(RETURN_STREAM, $this->getFetchMode($fetchMode));
    }

    /**
     * Executes query and returns results wrapped in a promise object.
     * 
     * Useful for asynchronous-style handling of database operations.
     * Resolves with the query results or rejects on error.
     * 
     * **Applies to:**
     * - `select()`
     * - `find()`
     * - `count()`
     * - `sum()`
     * - `average()`
     *
     * @param int $fetchMode Result fetch mode e.g, `FETCH_ASSOC`, `FETCH_CLASS`, (default: `FETCH_OBJ`).
     * @param int|null $returnMode Record return mode e.g. `RETURN_NEXT`, (default: `RETURN_ALL`).
     * 
     * @return PromiseInterface Returns promise object that resolves with query results 
     *      or rejects with a `Throwable`.
     * 
     * @see get()
     * @see stmt()
     * @see fetch()
     * 
     * @example - Promise example:
     * ```php
     * Builder::table('users')
     *     ->find(['email', 'name'])
     *     ->where('country', '=', 'NG')
     *     ->promise(FETCH_OBJ)
     *     ->then(function (mixed $result) {
     *         echo $result->name;
     *     })
     *     ->catch(function (Throwable $e) {
     *         echo $e->getMessage();
     *     });
     * ```
     * 
     * @methodGroup QueryExecutor Execute query promise object that resolve with result or reject with exception.
     */
    public function promise(int $fetchMode = FETCH_OBJ, ?int $returnMode = null): PromiseInterface 
    {
        return new Promise(function (callable $resolve, callable $reject) use($fetchMode, $returnMode): void {
            try{
                $this->assertHandler('promise');
                $resolve($this->get($fetchMode, $returnMode));
            }catch(Throwable $e){
                $reject($e);
            }
        });
    }

    /**
     * Executes query and returns a prepared statement object.
     * 
     * This method runs the query and returns an instance of the database driver that
     * wraps the prepared statement. It allows you to work directly with the underlying
     * database driver object, using `PDOStatement`, `mysqli_stmt`, 
     * or `mysqli_result` depending on your database driver.
     * 
     * **Applies to:**
     * - `select()`
     * - `find()`
     * - `count()`
     * - `sum()`
     * - `average()`
     * 
     * @return DatabaseInterface|null Returns a statement object on success, or `null` if execution fails.
     * @throws DatabaseException If no query is set or execution fails.
     * 
     * > **Note:** 
     * > Query result caching is not supported when using `stmt()`.
     * 
     * @see get()
     * @see fetch()
     * @see promise()
     * 
     * @example - Fetch all results:
     * ```php
     * $stmt = Builder::table('users')
     *     ->select(['email', 'name'])
     *     ->where('country', '=', 'NG')
     *     ->stmt();
     * 
     * $result = $stmt->fetchAll(FETCH_OBJ);
     * $stmt->freeStmt();
     * ```
     * 
     * @example - Fetch as object:
     * ```php
     * $stmt = Builder::table('users')
     *     ->find(['email', 'name'])
     *     ->where('id', '=', 1)
     *     ->stmt();
     * 
     * $user = $stmt->fetchObject(User::class);
     * $stmt->freeStmt();
     * ```
     * 
     * @example - Accessing the raw PDO statement:
     * ```php
     * $stmt = Builder::table('users')
     *     ->find(['email', 'name'])
     *     ->where('id', '=', 1)
     *     ->stmt();
     * 
     * $user = $stmt->getStatement()
     *     ->fetchAll(\PDO::FETCH_DEFAULT);
     * $stmt->freeStmt();
     * ```
     * 
     * @methodGroup QueryExecutor Execute query and return database statement object.
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
     * Build and execute query to determine if a records exists in selected table.
     * 
     * @return bool Return true if records exists in table, otherwise false.
     * @throws DatabaseException If an error occurs.
     * 
     * @see exists() To check if table exists in database.
     * 
     * @example - Check if users in country `NG` exists in table:
     * 
     * ```php
     * $has = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->has();
     * ```
     * 
     * @methodGroup QueryExecutor Execute query to determine if record exists.
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
     * @see has() To check if record exists in table.
     *
     * @example - Check if the `users` table exists:
     * 
     * ```php
     * $exists = Builder::table('users')
     *      ->exists();
     * ```
     * > **Note:** 
     * > This method does not require a `WHERE` clause or logical operators.
     * 
     * @methodGroup QueryExecutor Execute query to determine if table exists.
     */
    public function exists(): bool
    {
        $query = Alter::getTableExists($this->db->getDriver());
        $stmt = $this->db->prepare("SELECT 1 FROM {$query}")
            ->bind(':tableName', $this->tableName);
        
        return $stmt->execute() 
            && $stmt->ok() 
            && !empty($stmt->fetch(RETURN_NEXT, FETCH_COLUMN));
    }

    /**
     * Build query to calculate the number of records in the table.
     * 
     * When `get()` is called, it returns an `int` representing the total 
     * number of matching records. If no rows match, it returns `0`.
     * 
     * **Applies to:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param string $column The column to count (default: `*`).
     * 
     * @return self Returns the current query builder instance.
     * 
     * @example - Get the number of users in country `NG`:
     * 
     * ```php
     * $total = Builder::table('users')
     *      ->count()
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     * @methodGroup SQLFunction Selector function for SQL `COUNT` querying.
     */
    public function count(string $column = '*'): self 
    {
        $this->handler = [
            'sql' => " COUNT({$column})",
            'method' => 'total'
        ];

        return $this;
    }

    /**
     * @deprecated Use {@see count()} instead.
     *
     * Alias for `count()`. Retained for backward compatibility.
     *
     * @param string $column Column to count (default: `*`).
     *
     * @return self Returns the current query builder instance.
     */
    public function total(string $column = '*'): self 
    {
        return $this->count($column);
    }

    /**
     * @deprecated Method has been deprecated use {@see onCondition()} instead.
     */
    public function onClause(RawExpression|string $sql, string $connector = 'AND'): self
    {
        return $this->onCondition($sql, $connector);
    }

    /**
     * Build query to calculate the total sum of a numeric column in the table.
     * 
     * When `get` method is called, it returns `int|float`, the total sum columns, otherwise 0 if no result.
     * 
     * **Applies to:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param string $column The column to calculate the sum.
     * 
     * @return self Returns the current query builder instance.
     * 
     * @example - Get the total sum of users votes in country `NG`:
     * 
     * ```php
     * $votes = Builder::table('users')
     *      ->sum('votes')
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     * 
     * @methodGroup SQLFunction Selector function for SQL `SUM` querying.
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
     * Build query to calculate the average value of a numeric column in the table.
     * 
     * When `get` method is called, it returns `int|float`, the total average of columns, otherwise 0 if no result.
     * 
     * **Applies to:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param string $column The column to calculate the average.
     * 
     * @return self Returns the current query builder instance.
     * 
     * @example - Get the total average of users votes in country `NG`:
     * 
     * ```php
     * $votes = Builder::table('users')
     *      ->average('votes')
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     * @methodGroup SQLFunction Selector function for SQL `AVG` querying.
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
     * **Applies to:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param array<int,string> $columns The table columns to select (e.g, `['foo', 'bar']` or ['*']).
     * 
     * @return self Returns the current query builder instance.
     * @see bind() To bind named placeholder in SELECT column expression.
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
     * **Applies to:**
     * 
     * - `get()`
     * - `promise()`
     * - `stmt()`
     * - `fetch()`
     * 
     * @param array<int,string> $columns The table columns to select (e.g, `['foo', 'bar']` or ['*']).
     * 
     * @return self Returns the current query builder instance.
     * @see bind() To bind named placeholder in SELECT column expression.
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
     * @see bind() To bind named placeholder in SELECT column expression.
     * 
     * @see to()
     *
     * @example - Copy of specific columns:
     * 
     * ```php
     * $result = Builder::table('users')
     *     ->copy(['id', 'email', 'created_at'])
     *     ->where('id', '=', 100)
     *     ->onDuplicate('email', '=', RawExpression::values('email'))
     *     ->to('backup_users', ['id', 'email', 'created_at']);
     * ```
     * 
     * @example - Copy with replace function:
     * 
     * ```php
     * $result = Builder::table('users')
     *     ->copy(['id', 'email', 'created_at'])
     *     ->replace(true)
     *     ->where('id', '=', 100)
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
     * $tbl = Builder::table('users');
     * 
     * $tbl->transaction();
     * $tbl->where('country', '=', 'NG');
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
            ErrorCode::DATABASE_TRANSACTION_FAILED
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
     * @return bool Returns true if properties was reset, false if debug is enabled. 
     * 
     * > **Note:** It automatically closes database connection if `closeAfter` is enabled.
     */
    public function reset(): bool 
    {
        if($this->debugMode !== self::DEBUG_NONE){
            return false;
        }

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
     * Adds an IN condition to the query with support for `IN`, `NOT IN`, or custom wrappers.
     *
     * Useful for matching one or more values within a comma-separated list column, 
     * such as checking if a tag exists within a stored set.
     *
     * @param string $column The column name to search within.
     * @param string $expression A modifier or keyword (`IN`, `NOT IN`, etc.).
     * @param Closure|array<int,string|int|float> $values An array or Closure that returns 
     *          array of values to search.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     *
     * @return self
     *
     * @throws InvalidArgumentException If values is not provided.
     * @throws JsonException If an error occurs while encoding values.
     *
     * @example - Example:
     * ```php
     * Builder::table('languages')
     *     ->select()
     *     ->inArray('post_tags', 'NOT', ['php', 'sql'])
     *     ->get();
     * // Generates: `NOT IN(...)`
     * ```
     *
     * @methodGroup QueryCondition Add in/not in condition.
     */
    private function inArray(
        string $column,
        string $expression,
        Closure|array $values,
        string $connector = 'AND'
    ): self
    {
        $expr = strtoupper(trim($expression));

        $prefix = match ($expr) {
            'IN', '=', '=='        => '',
            'NOT', '!=', '<>', '!' => 'NOT ',
            default     => (str_contains($expr, 'NOT') ? 'NOT ' : ''),
        };

        return $this->clause($connector, $column, "{$prefix}IN", $values, self::INARRAY);
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
            (self::$cache instanceof Cache)
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
                ErrorCode::LOGIC_ERROR
            );
        }

        $columns = $matches[array_key_last($matches)]['columns'] ?? null;

        if($columns === null || $columns === ''){
            throw new DatabaseException(
                'Invalid or missing match columns. Expected non-empty array of column names.',
                ErrorCode::LOGIC_ERROR
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

        if($throwInstant || $e->getCode() === ErrorCode::TERMINATED){
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
                ErrorCode::VALUE_FORBIDDEN
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
                ErrorCode::BAD_METHOD_CALL
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
     * Validate input values before performing an INSERT or UPDATE query.
     *
     * Ensures the given values are not empty and that update operations
     * receive an associative array (column => value) instead of indexed data.
     *
     * @param array $values The values to validate.
     * @param bool $isInsert Whether this is for an insert operation (default true).
     *
     * @throws DatabaseException If values are empty or invalid for the given operation.
     */
    private function assertInUpValues(array $values, bool $isInsert = true): void
    {
        if ($values === []) {
            $ctx = $isInsert ? 'insert' : 'update';

            throw new DatabaseException(
                sprintf(
                    'No columns specified for %s on table "%s". Use set() or pass values directly to %s().',
                    $ctx,
                    $this->tableName,
                    $ctx
                ),
                ErrorCode::VALUE_FORBIDDEN
            );
        }

        if (!$isInsert && isset($values[0])) {
            throw new DatabaseException(
                'Invalid update values: must be an associative array (column => value).',
                ErrorCode::VALUE_FORBIDDEN
            );
        }
    }

    /**
     * Assert SQL logical operators.
     * 
     * @param string $fn
     * @param string|null $operator The base operator to check.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * @param string|null $nested An optional combined nested operator to check.
     * 
     * @return array{?operator,?clause,?nested} Return the value as upper-cased.
     * @throws InvalidArgumentException If error.
     */
    private function assertOperators(
        string $fn,
        ?string $operator, 
        ?string $connector = null, 
        ?string $nested = null
    ): array 
    {
        $allowed = ['AND', 'OR'];
        $suffix = 'Allowed operators are [AND, OR].';

        $operator = ($operator !== null) ? strtoupper($operator) : null;
        $connector = ($connector !== null) ? strtoupper($connector) : null;
        $nested = ($nested !== null) ? strtoupper($nested) : null;

        if ($operator !== null && !in_array($operator, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid %s(...) logical operator '%s'. %s.",
                $fn,
                $operator,
                $suffix
            ));
        }

        if ($connector !== null && !in_array($connector, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid %s(...) clause chain operator '%s'. %s",
                $fn,
                $connector,
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

        return [$operator, $connector, $nested];
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
                ErrorCode::LOGIC_ERROR
            );
        }

        $onDuplicate = $this->getOptions('duplicate');

         if($this->isIgnoreDuplicate && $onDuplicate !== []){
                throw new DatabaseException(
                    'Cannot use "->ignoreDuplicate(true)" with "->onDuplicate(...)" options. These behaviors are mutually exclusive.',
                    ErrorCode::LOGIC_ERROR
                );
            }

        if ($this->isReplace && $onDuplicate !== []) {
            throw new DatabaseException(
                'Cannot use "->replace(true)" with "->onDuplicate(...)". REPLACE already overwrites existing rows and conflicts with duplicate key logic.', 
                ErrorCode::LOGIC_ERROR
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

            if (!preg_match('/^`?[A-Za-z_][a-zA-Z0-9_.-]+`?$/u', $table)) {
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

            if (!preg_match('/^`?[a-zA-Z_][a-zA-Z0-9_]*`?$/u', $alias)) {
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
            $this->resolveException($e);
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

        if ($this->conditions === [] && !$this->findOuterWhere($sql)) {
            $query = preg_replace('/^\s*(AND|OR)\b\s*/i', '', $query);
            $sql .= ' WHERE';
        }

        $sql .= " {$query}";
    }

    /**
     * Checks if the SQL query has an outer-level WHERE clause.
     *
     * This version is optimized to start scanning from the first WHERE found,
     * reducing unnecessary iteration over the entire query.
     * It walks backwards from that position to see if the WHERE occurs
     * inside parentheses (a subquery) or at the outer level.
     *
     * @param string $sql The SQL query string to check.
     *
     * @return bool Returns true if an outer WHERE clause exists, otherwise false.
     */
    private function findOuterWhere(string $sql): bool
    {
        $pos = stripos($sql, 'WHERE');
        if ($pos === false) {
            return false;
        }

        $depth = 0;
        $inQuote = false;

        for ($i = 0; $i < $pos; $i++) {
            $char = $sql[$i];

            if ($char === "'" || $char === '"') {
                $inQuote = !$inQuote;
                continue;
            }

            if ($inQuote) {
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            }
        }

        return $depth === 0;
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
            throw new DatabaseException('No UNION queries to compile.', ErrorCode::BAD_METHOD_CALL);
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

        if($limit > 0 && ($offset > 0 || $offset instanceof RawExpression)){
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
            if($this->debugMode === self::DEBUG_NONE){
                $this->db->prepare($sql);
            }

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
            ), ErrorCode::COMPILE_ERROR);
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
        $this->setHavingConditions($sql);

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

        foreach($this->tableJoin as $key => $join){
            $hasConditions = $this->joinConditions !== [] && isset($this->joinConditions[$key]);
            $query = '';

            if($join['isForSubquery']){
                if(!$hasConditions){
                    continue;
                }

                $sub = trim($this->joinConditions[$key][0]['sql']);

                if(!str_starts_with($sub, '(')){
                    $sub = "({$sub})";
                }

                $joins = count($this->joinConditions[$key]); 
                $query .= " {$join['type']} JOIN";
                $query .= "{$sub} {$join['as']}";

                if($joins > 1){
                    $query .= $this->addJoinConditions($key, $joins, 1);
                }
            }else{
                $query .= " {$join['type']} JOIN";
                $query .= " {$join['table']} {$join['as']}";

                if($hasConditions){
                    $joins = count($this->joinConditions[$key]); 
                    $query .= $this->addJoinConditions($key, $joins, 0);
                }
            }

            $sql .= str_replace(
                ['{{tableName}}', '{{tableAlias}}'], 
                [$join['table'], $join['alias']], 
                $query
            );
        }

        return $sql;
    }

    /**
     * Constructs join conditions.
     *
     * @return string Return the constructed JOIN query.
     */
    private function addJoinConditions(mixed $key, int $total, int $onIndex = 0): string
    {
        $sql = " ON {$this->joinConditions[$key][$onIndex]['sql']}";

        $offset = $onIndex + 1;

        if($total > $offset){
            for ($i = $offset; $i < $total; $i++) {
                $current = $this->joinConditions[$key][$i];
                $sql .= " {$current['clause']} {$current['sql']}";
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
                        ErrorCode::INVALID_ARGUMENTS
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
     * @return self Returns the current query builder instance.
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
     * @throws RuntimeException If closure throws exception.
    */
    private function getValue(mixed $input): mixed 
    {
        if(!$input instanceof Closure){
           return $input;
        }

        try{
            return $input(self::initializer());
        }catch(Throwable $e){
            if($e->getCode() === ErrorCode::TERMINATED){
                throw $e;
            }

            throw new RuntimeException(
                $e->getMessage(), 
                ErrorCode::TERMINATED,
                $e
            );
        }
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
                ? $value->toString() 
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
                $value = $value->toString();
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
        }else{
            $this->db->prepare($sql);
        }

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
    private function bindStrictColumns(
        array $columns, 
        array $replacements = [], 
        bool $withObjectId = true
    ): void
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
                    ErrorCode::VALUE_FORBIDDEN
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
                $query .= ' ' . ($condition['connector'] ?? 'AND') . ' ';
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
                self::RAW => $condition['value'],
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
                case self::RAW:
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
        $connector = $addOperator ? ' ' . ($condition['connector'] ?? 'AND') . ' ' : '';

        $placeholder = $isRaw
            ? self::escape(value: $value ?? '', addSlashes: true)
            : $this->trimPlaceholder(($condition['mode'] === self::AGAINST) ? "match_column_{$index}" : $column);

        return match ($condition['mode']) {
            self::REGULAR => "{$connector}{$column} {$comparison} {$placeholder}",
            self::INARRAY => "{$connector}{$column} {$comparison}(" . (
                $isRaw
                    ? self::escapeValues($value ?? [])
                    : $this->bindInConditions($value ?? [], $column)
            ) . ')',
            self::AGAINST => "{$connector}MATCH($column) AGAINST ({$placeholder} {$comparison})",
            self::INSET => self::buildInsetConditions($condition, $connector, $comparison),
            default => '',
        }; 
    }

    /**
     * Builds the `FIND_IN_SET` condition for the query.
     *
     * @param array $condition The condition array containing search, list, and operator details.
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * @param string $comparison The operator for comparison or position alias.
     * 
     * @return string Return the generated SQL string for find in set function.
     */
    private static function buildInsetConditions(
        array $condition, 
        string $connector, 
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
        
        return "{$connector}FIND_IN_SET({$search}, {$values}) {$comparison}";
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
                    ErrorCode::LOGIC_ERROR
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
                $placeholder = $value->toString();
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
                $placeholders .= "{$value->toString()}, ";
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
                    case self::RAW:
                        $params["raw_{$index}"] = $condition['value'];
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

        $having = '';
        $bound = false;
   
        foreach ($filters as $idx => $filter) {
            $expression = $filter['expression'];

            if($expression instanceof RawExpression){
                $expression = $expression->toString();
            }

            $value = self::escape($filter['value'], true);
            $operator = '';

            if($idx > 0){
                $bound = true;
                $operator = ($filter['operator'] ?? 'AND') . ' ';
            }
           
            $having .= "{$operator}{$expression} {$filter['comparison']} {$value} ";
            
        }

        $having = rtrim($having, ' ');

        if($having === ''){
            return;
        }

        $sql .= ($bound ? " HAVING ({$having})" : " HAVING {$having}");
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
     * @return Cache Return instance of cache class.
     */
    private function newCache(
        ?string $storage = null, 
        ?string $subfolder = null,
        ?string $persistentId = null
    ): Cache
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
                ? $value->toString() . ', ' 
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
                ? $value->toString() 
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