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
use \Luminova\Promise\Promise;
use \Luminova\Foundation\Core\Database;
use function \Luminova\Funcs\is_associative;
use \Luminova\Database\Helpers\ORMBuilderTrait;
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
     * Match against modes.
     * 
     * @var array<int,string> MATCH_MODES
     */
    private const MATCH_MODES = [
        self::MATCH_NATURAL => 'IN NATURAL LANGUAGE MODE',
        self::MATCH_BOOLEAN => 'IN BOOLEAN MODE',
        self::MATCH_NATURAL_EXPANDED => 'IN NATURAL LANGUAGE MODE WITH QUERY EXPANSION',
        self::MATCH_EXPANSION => 'WITH QUERY EXPANSION',
    ];

    /**
     * Clause method modes.
     * 
     * @var string[] CLAUSE_MODES
     */
    private const CLAUSE_MODES = [
        self::REGULAR, 
        self::RAW, 
        self::CONJOIN, 
        self::NESTED, 
        self::AGAINST, 
        self::INARRAY
    ];


    use ORMBuilderTrait;

    /**
     * Create a new instance of builder class.
     *
     * Initializes the Builder with an optional table name and alias,
     * it does not attache database connection object, you must attache before executing query.
     *
     * @param string|null $table Optional database table name (must be a valid non-empty string).
     * @param string|null $alias Optional table alias (default: null).
     *
     * @throws InvalidArgumentException If the table name is empty or contains invalid characters.
     * @example - Example:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $builder = new Builder();
     * 
     * $builder->connection(...);
     * $builder->from('users')->where('id', '=', 100)->get();
     * ```
     */
    public function __construct(?string $table = null, ?string $alias = null)
    {
        $table = ($table === null) ? null : trim($table);
        
        if($table !== null || $alias !== null){
            self::assertTableName($table, $alias);
        }

        $this->stmt = null;
        $this->selector = [];

        $this->cacheDriver ??= env('database.caching.driver', 'filesystem');
        $this->tableName = $table ?? '';
        $this->tableAlias = $alias ?? '';
    }

    /**
     * Check if the current builder database is connected.
     * 
     * @return bool Return true if database connected, false otherwise.
     */
    public function isConnected(): bool 
    {
        return ($this->db instanceof DatabaseInterface) 
            && $this->db->isConnected();
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
     * @return Builder Returns a singleton instance of Builder class.
     * @throws DatabaseException If the database connection fails.
     *
     * @example - Builder Shared Options: 
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $instance = Builder::getInstance()
     *     ->cacheable(true)
     *     ->returns('array')
     *     ->strict(true);
     * ```
     * 
     * Now use the instance with inherited settings:
     * 
     * ```php
     * $result = $instance->from('users')
     *     ->where('id', '=', 100)
     *     ->select(['name']);
     * ```
     */
    public static function getInstance(?string $table = null, ?string $alias = null): self 
    {
        if(!self::$instance instanceof self){
            self::$instance = new self($table, $alias);
        }

        return self::$instance;
    }

    /**
     * Retrieve last inserted id from database after insert method is called.
     * 
     * @return mixed Return last inserted id from database.
     */
    public function getLastInsertedId(): mixed 
    {
        return $this->lastInsertId;
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
     * Return a connected database driver.
     *
     * If $shared is true, reuse the shared connection instance.
     * Otherwise create a new connection.
     *
     * @param bool $shared Use shared connection instance.
     * 
     * @return DatabaseInterface Connected database driver.
     * @throws DatabaseException If connection cannot be established.
     */
    public static function database(bool $shared = true): DatabaseInterface
    {
        $conn = $shared 
            ? Connection::getInstance() 
            : new Connection();

        $db = $conn->database() ?? $conn->connect();

        if($db instanceof DatabaseInterface && $db->isConnected()){
            $db->free();
            return $db;
        }

        $conn = null;
        throw new DatabaseException(
            'Error: Database connection failed.',
            ErrorCode::CONNECTION_DENIED
        );
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
     * @see self::cache() For query result caching.
     * @see self::from() To attache table to existing object.
     * 
     * @example - Performing a table join and executing queries:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * Create a new query builder instance using a Common Table Expression (CTE).
     *
     * This method accepts only a SELECT statement that represents the CTE dataset.
     * The builder will automatically prefix WITH expression if not included.
     *
     * It is intended for structured query building such as ranking, deduplication,
     * window functions, and intermediate dataset preparation.
     *
     * Example output internally:
     * WITH {table} AS ( ...query... )
     *
     * @param string $table The CTE name used as the temporary result set identifier.
     * @param string $query A SELECT-only SQL statement representing the CTE body.
     * @param string|null $alias  Optional alias for the resulting dataset.
     *
     * @return Builder Returns an instance of the builder initialized with the provided CTE query.
     * @throws InvalidArgumentException If the query is empty or not a valid SELECT statement.
     * 
     * @see self::cte() For building full CTE query.
     *
     * @example - Deduplication using window function
     * ```php
     * $result = Builder::with('ranked_games', '
     * WITH ranked_games AS (
     *      SELECT g.*,
     *      ROW_NUMBER() OVER (
     *          PARTITION BY g.home_id, g.away_id
     *          ORDER BY g.created_at DESC
     *      ) AS rn
     * )', 'g')
     * ->where('rn', '=', 1)
     * ->get();
     * ```
     */
    public static function with(string $table, string $query, ?string $alias = null): Builder
    {
        self::assertTableName($table, $alias);

        $query = trim($query);

        if(!preg_match('/^with\s+/i', $query)){
            $query = self::toCteQuery($query, $table);
        }

        self::assertCte($query, true);

        $tbl = self::initializer($table, $alias, false);
        $tbl->cteTableQuery = $query;
        $tbl->isCteWith = true;

        return $tbl;
    }

    /**
     * Attach a full Common Table Expression (CTE) SQL query to the builder.
     *
     * This method accepts a complete CTE statement including the WITH clause.
     * It gives full control over complex SQL structures such as multi-CTE chains,
     * recursive queries, and advanced analytics queries.
     *
     * Unlike `with()`, this method does not modify or wrap the SQL.
     * The query is used as-is.
     *
     * @param string $query A complete CTE SQL statement starting with WITH.
     *
     * @return Builder Returns an instance of the builder.
     * @throws InvalidArgumentException If the query is empty or not a valid WITH statement.
     *
     * @example - Full CTE with final select
     * ```php
     * $builder->cte('
     *     WITH active_games AS (
     *         SELECT * FROM games WHERE game_completed = 0
     *     )
     *     SELECT * FROM active_games
     * ');
     * ```
     *
     * @example - Multi-CTE chain
     * ```php
     * $builder->cte('
     *     WITH a AS (
     *         SELECT * FROM games
     *     ),
     *     b AS (
     *         SELECT * FROM a WHERE game_completed = 0
     *     )
     *     SELECT * FROM b
     * ');
     * ```
     */
    public function cte(string $query): Builder
    {
        $query = trim($query);
        self::assertCte($query, false);

        $this->cteTableQuery = $query;
        $this->isCteWith = false;

        return $this;
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
     * @see self::execute() - To execute this query.
     * @see self::cache() - For query result caching.
     * 
     * @example - Executing a raw query:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * @throws DatabaseException Throws if database error occurs while executing query.
     * 
     * @example - Example:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $result = Builder::exec("
     *      ALTER TABLE `users` 
     *      ADD COLUMN `slug` CHAR(10) DEFAULT NULL AFTER `id`
     * ");
     * ```
     * 
     * @methodGroup QueryExecutor Execute raw SQL query directly using `exec`.
     */
    public static function exec(string $query): int 
    {
        self::assertQuery($query, __METHOD__);
        return self::initializer()
            ->db
            ->exec($query);
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
     * @see RawExpression for more usages.
     * 
     * @example - Using RawExpression in an INSERT Query:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * use Luminova\Database\RawExpression;
     * 
     * $result = Builder::table('logs')
     *      ->insert([
     *          'message' => 'User login',
     *          'created_at' => Builder::raw('NOW()'), // Use raw expression helper method
     *          'updated_at' => RawExpression::now() // Or directly
     *      ]);
     * ```
     * 
     * @example - Using REPLACE instead of INSERT:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $result = Builder::table('logs')
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
     * Reassign table name and alias to the builder object.
     * 
     * This can be used to re-assign new table name after query execution 
     * to continue new query with same object or for sub-query.
     *
     * @param string $table The name of the database table (must be a non-empty string).
     * @param string|null $alias Optional alias for the table (default: `null`).
     * 
     * @return self Returns instance of builder class.
     * @throws InvalidArgumentException If the provided table name is empty.
     * 
     * @see self::cache() For query result caching.
     * @see self::table() To initialize table with new object.
     * 
     * @example - Reassign a table:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $tbl = Builder::table('users', 'u');
     * 
     * // Update table
     * $tbl->from('admins', 'a')
     * ```
     * 
     * @methodGroup QueryInitializer Build query with main table.
     */
    public function from(string $table, ?string $alias = null): self
    {
        self::assertTableName($table, $alias);

        $this->tableName = $table;
        $this->tableAlias = $alias ?? '';

        if(!$this->db instanceof DatabaseInterface){
            $this->db = self::database();
        }

        return $this;
    }

    /**
     * Attach a database connection or driver to the builder.
     *
     * Accepts either a DatabaseInterface or Connection instance.
     * Ensures the database is connected before assigning.
     * Optionally clears any active statements on the driver.
     *
     * @param DatabaseInterface|Connection|null $conn Connection source or null to assign new connection.
     * @param bool $freeStmt Whether to free active statements and release cursor before use.
     * 
     * @return self Return instance of database builder.
     * @throws DatabaseException If no active connection is available.
     */
    public function connection(DatabaseInterface|Connection|null $conn = null, bool $freeStmt = false): self
    {
        if ($conn === null) {
            $db = self::database(shared: false);
        } elseif ($conn instanceof DatabaseInterface) {
            if (!$conn->isConnected()) {
                throw new DatabaseException('Error: Database is not connected.');
            }

            $db = $conn;
        } else {
            $db = $conn->database();

            if (!$db instanceof DatabaseInterface) {
                $db = $conn->connect();
            }
        }

        if (!$db instanceof DatabaseInterface) {
            throw new DatabaseException(
                'Error: Database connection failed.',
                ErrorCode::CONNECTION_DENIED
            );
        }

        $this->db = $db;

        if ($freeStmt) {
            $this->db->free();
        }

        return $this;
    }

    /**
     * Adds a table join to the current query.
     *
     * Use this method to combine data from another table or subquery into your main query.
     * You can specify the type of join (INNER, LEFT, etc.) and optionally assign an alias
     * for the joined table.
     *
     * @param string|null $table The table name to join or null for sub-query join.  
     * @param string|null $alias Optional alias for the joined table.  
     * @param string|null $type The type of join to use (`INNER`, `LEFT`, `RIGHT`, `FULL`, or `CROSS`).  
     * @param bool $forSubquery Set to `true` if the joined source is a subquery 
     *              instead of a normal table.  
     *
     * @return self Returns the instance of builder class.
     * @throws InvalidArgumentException If `$table`, `$alias` or `$type` is invalid or empty string.
     *
     * @example - Basic join:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('products', 'p')
     *     ->join('users', 'u', 'LEFT');
     * ```
     *
     * @example - Join without alias:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *     ->join('orders', type: 'INNER');
     * ```
     *
     * @example - Join using subquery:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users', 'u')
     *     ->join('orders', 'o', 'INNER', true);
     *     ->onSubquery('(SELECT user_id, COUNT(*) AS total FROM orders GROUP BY user_id)');
     * ```
     * 
     * **Supported Join Methods:**
     * 
     * @see self::on()
     * @see self::onCompound()
     * @see self::onCondition()
     * @see self::onSubquery()
     * 
     * @see self::joinSubquery() -  To join a subquery to the current query.
     * @see self::innerJoin() - Use `INNER` when you only want matching rows from both tables.  
     * @see self::leftJoin()  - Use `LEFT` when you want all rows from the left table, even if no match exists.  
     * @see self::rightJoin() - Use `RIGHT` when you want all rows from the right table, even if no match exists.  
     * @see self::fullJoin()  - Use `FULL` (or `FULL OUTER`) when you want all rows from both sides.  
     * @see self::crossJoin() - Use `CROSS` when you want every combination of rows (Cartesian product).  
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     * 
     * > **Note:*
     * > If table name is set to `NULL`, `$forSubquery` will be enabled.
     */
    public function join(
        ?string $table,
        ?string $alias = null,
        ?string $type = null,
        bool $forSubquery = false
    ): self
    {
        $forSubquery = $forSubquery || $table === null;
        $table = ($table === null) ? null : trim($table);

        if($table !== null || $alias !== null){
            self::assertTableName($table, $alias);
        }
        
        $id = $table ?? uniqid('jsq_');

        $this->tableJoin[$id . ($alias ?? '')] = [
            'type'  => strtoupper($type ?? ''),
            'table' => (string) $table,
            'alias' => (string) $alias,
            'as'    => $alias ? "AS {$alias}" : '',
            'isForSubquery' => $forSubquery
        ];

        return $this;
    }

    /**
     * Join a subquery to the current query.
     *
     * This is a convenience wrapper around `join()` that automatically enables
     * subquery join mode. It allows you to attach a derived table and later
     * define the subquery source using `onSubquery()` and `on()`.
     *
     * @param string|null $alias Optional table alias for the subquery.
     * @param string|null $type  The join type (`INNER`, `LEFT`, `RIGHT`, `FULL`, `CROSS`).
     *
     * @return self Returns the instance of the builder.
     * @throws InvalidArgumentException If `$alias` or `$type` is invalid or empty string.
     * 
     * @see self::on()
     * @see self::onCompound()
     * @see self::onCondition()
     * @see self::onSubquery()
     *
     * @example - Subquery table join:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $users = Builder::table('users', 'u')
     *     ->select(['u.name', 'o.id'])
     *     ->joinSubquery('o', 'LEFT')
     *          ->onSubquery('(SELECT user_id, COUNT(*) total FROM orders GROUP BY user_id)')
     *          ->on('o.role', '=', 'admin')
     *     ->get();
     * ```
     */
    public function joinSubquery(?string $alias = null, ?string $type = null): self
    {
        return $this->join(null, $alias, $type, true);
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
     * use Luminova\Database\Builder;
     * 
     * $user = Builder::table('users', 'u')
     *     ->find(['u.name', 'u.id', 'r.role'])
     *     ->leftJoin('roles', 'r')
     *         ->on('u.id', '=', 'r.user_id') // Column comparison
     *         ->on('u.group', '=', 1)             // Numeric value comparison
     *         ->on('u.name', '=', '"peter"')      // String literal
     *         ->on('r.role', '=', ':role_name')   // Placeholder binding
     *             ->bind(':role_name', 'admin')
     *     ->where('u.id', '=', 1)
     *     ->get();
     * ```
     *
     * @example - Multiple joins:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $user = Builder::table('users', 'u')
     *     ->find(['u.name', 'u.id', 'r.role'])
     *     ->innerJoin('roles', 'r')
     *         ->on('u.id', '=', 'r.user_id')
     *     ->leftJoin('orders', 'o')
     *         ->on('u.id', '=', 'o.user_id')
     *     ->where('u.id', '=', 1)
     *     ->get();
     * ```
     * 
     * @example - Using a closure for a subquery condition:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $users = Builder::table('users', 'u')
     *     ->select(['u,name', 'u.id', 'o.oid'])
     *     ->innerJoin('orders', 'o')
     *         ->on('u.id', '=', function (Builder $b): string {
     *             $result = $b->from('payments')
     *                      ->find(['id'])
     *                      ->where('status', '=', '"completed"')
     *                      ->get();
     * 
     *              if(empty($result))
     *                  throw new Exception('User not found');
     * 
     *             return $result->id
     *         })
     *     ->where('u.active', '=', 1)
     *     ->limit(5)
     *     ->get();
     * ```
     *
     * > **Note:** 
     * > When chaining multiple joins, always call `on()` immediately after each table `join()`.
     * > or `joinSubquery()`
     *
     * @see self::onCompound()
     * @see self::onCondition()
     * @see self::onSubquery()
     * @see self::join()
     * @see self::joinSubQuery()
     * @see self::leftJoin()
     * @see self::rightJoin()
     * @see self::innerJoin()
     * @see self::crossJoin()
     * @see self::fullJoin()
     * @see self::bind()
     * 
     * @methodGroup QueryCondition Add simple conditions to join table.
     */
    public function on(
        string $condition, 
        string $comparison, 
        mixed $value, 
        string $connector = 'AND'
    ): self
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
     * use Luminova\Database\Builder;
     * 
     * $users = Builder::table('users', 'u')
     *     ->find(['u.id', 'u.name', 'l.level', 'l.message'])
     *     ->leftJoin('logs', 'l')
     *         ->onCondition('(u.id IN (100,200))')
     *         ->onCondition(Builder::raw('DATE(l.created_at) = CURDATE()'))
     *     ->get();
     * ```
     *
     * @example - Using Subquery with table replacement:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users', 'u')
     *     ->select([...])
     *     ->leftJoin('orders', forSubquery: true)
     *         ->onCondition('(
     *             SELECT order_id
     *             FROM {{tableName}}
     *             WHERE status = "active"
     *             AND amount > 500
     *         ) AS o')
     *     ->get();
     * ```
     *
     * @see self::on()
     * @see self::onSubquery()
     * @see self::onCompound()
     * @see self::join()
     * @see self::joinSubQuery()
     * @see self::bind()
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users', 'u')
     *     ->select([...])
     *     ->leftJoin('logs', 'l', true)
     *         ->onSubquery('(
     *             SELECT name
     *             FROM {{tableName}}
     *             WHERE logger_user_id = 100
     *         )')
     *         ->on('l.foo', '=', 'bar') // Outer condition
     *         ->onCondition('(u.id = 100 OR u.id = 200)')
     *     ->get();
     * ```
     *
     * @example - Manual alias assignment for subquery join:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users', 'u')
     *     ->select([...])
     *     ->leftJoin('logs', forSubquery: true)
     *         ->onSubquery('(
     *             SELECT name
     *             FROM {{tableName}}
     *             WHERE logger_user_id = 100
     *         ) AS l')
     *      ->get();
     * ```
     *
     * @see self::on()
     * @see self::onCondition()
     * @see self::onCompound()
     * @see self::join()
     * @see self::joinSubQuery()
     * @see self::bind()
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
     * use Luminova\Database\Builder;
     * Builder::table('users', 'u')
     *     ->leftJoin('contacts', 'c')
     *         ->onCompound([
     *              Builder::column('u.user_id' '=', 'c.contact_user_id'), 
     *              Builder::column('u.user_group', '=', 2)
     *         ], 'OR', 'AND')
     *     ->select([...])
     *     ->get();
     * ```
     *
     * @see self::on()
     * @see self::onCondition()
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
     * @see self::on(...)
     * @see self::join(...)
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
     * @see self::on(...)
     * @see self::join(...)
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
     * @see self::on(...)
     * @see self::join(...)
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
     * @see self::on(...)
     * @see self::join(...)
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
     * @see self::on(...)
     * @see self::join(...)
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
     * @see self::on(...)
     * @see self::join(...)
     * 
     * @methodGroup QueryInitializer Initialize a new table join. 
     */
    public function fullOuterJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->join($table, $alias, 'FULL OUTER', $forSubquery);
    }

    /**
     * Sets the query limit and optional offset for SELECT statements.
     *
     * This method adds a `LIMIT` clause to the query, restricting the number of 
     * rows returned and optionally specifying an offset.
     *
     * @param int $limit The maximum number of results to return. Must be greater than 0.
     * @param int $offset The starting offset for the results (default: `0`).
     *
     * @return self Returns the instance of the builder class.
     * 
     * @see self::pagination() For dynamic pagination.
     * @see self::offset() For query records offset.
     * @see self::max() For update or delete columns.
     *
     * @example - Limiting number of results:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->limit(10, 5)
     *      ->select()
     *      ->get();
     * 
     * Generates: SELECT * ... LIMIT 5,10
     * ```
     * 
     * @methodGroup QueryConfiguration Enforce query result limit for select operation. 
     */
    public function limit(int $limit, int $offset = 0): self
    {
        $this->limiting['limit'] = max(0, $limit);

        if($offset > 0){
            return $this->offset($offset);
        }

        return $this;
    }

    /**
     * Sets the query pagination offset for SELECT statements.
     *
     * This method adds offset to `LIMIT` clause, specifying start row for returned records.
     *
     * @param int $offset The starting offset for the results.
     *
     * @return self Returns the instance of the builder class.
     * 
     * @see self::limit() For query limit.
     * @see self::pagination() For dynamic pagination.
     * @see self::max() For update or delete columns.
     *
     * @example - Pagination offset:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->limit(10)
     *      ->offset(5)
     *      ->select()
     *      ->get();
     * 
     * Generates: SELECT * ... LIMIT 5,10
     * ```
     * 
     * @methodGroup QueryConfiguration Enforce query result limit for select operation. 
     */
    public function offset(int $offset): self
    {
        $this->limiting['offset'] = max(0, $offset);
        return $this;
    }

    /**
     * Set the maximum number of rows to return in the query.
     * 
     * - SQL Server / MS Access: uses `TOP`. Supports either a literal integer
     *   or an expression/variable (SQL Server only) by wrapping it in parentheses.
     * - MySQL / SQLite: use self::limit() instead (translates to `LIMIT`).
     *
     * @param int|string $limit The maximum number of rows to retrieve. 
     *                          Must be 1 or greater if numeric, or a valid expression for SQL Server.
     * 
     * @return self Returns the instance of the builder class.
     * @see self::limit() For MySQL / SQLite queries using `LIMIT`.
     */
    public function top(string|int $limit): self
    {
        if (is_numeric($limit)) {
            $limit = max(1, (int) $limit);
        } else {
            $limit = '(' . preg_replace('/^\((.*)\)$/', '$1', trim($limit)) . ')';
        }

        $this->limiting['top'] = "TOP {$limit} ";
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
     * @return self Returns the instance of the builder class.
     * 
     * @see self::limit() For selecting rows.
     * @see self::pagination() For dynamic pagination.
     * @see self::offset() For raw selection start.
     *
     * @example - Limiting number of rows to affect:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
        return $this->limit($limit);
    }

    /**
     * Apply pagination to the current query by calculating the SQL `LIMIT`
     * and `OFFSET` values from the given page and limit.
     *
     * This method ensures both the page and limit values are valid, then
     * calculates the offset using the standard formula:
     *
     * `(page - 1) * limit`
     *
     * If the total number of records is provided, the method will also:
     * - Calculate the total number of pages.
     * - Clamp the requested page so it does not exceed the last page.
     * - Prevent the offset from exceeding the available records.
     *
     * Optionally, an information array can be returned by reference to provide
     * pagination metadata useful for building UI navigation or API responses.
     *
     * Pagination information includes:
     * - `page`    Current page number.
     * - `limit`   Number of records per page.
     * - `offset`  Calculated SQL offset.
     * - `records` Total number of records.
     * - `pages`   Total number of pages.
     * - `hasNext` Whether a next page exists.
     * - `hasPrev` Whether a previous page exists.
     * - `next`    Next page number or `null` if none.
     * - `prev`    Previous page number or `null` if none.
     *
     * @param int $page The requested page number (starting from 1).
     * @param int $limit The maximum number of records per page.
     * @param int $records Optional total number of records in the dataset.
     *                     If provided, pagination metadata will be calculated.
     * @param array<string,mixed>|null &$info Optional reference array to receive pagination details.
     *
     * @return self Returns the instance of the builder class.
     * 
     * @see self::limit() For query limit.
     * @see self::offset() For raw selection start.
     * @see self::max() For update or delete columns.
     *
     * @example - Basic pagination
     * ```php
     * $users = Builder::table('users')
     *     ->pagination(1, 10)
     *     ->get();
     * ```
     *
     * @example - Pagination with total records
     * ```php
     * $total = Builder::table('users')->count()->get();
     *
     * $users = Builder::table('users')
     *     ->pagination(2, 10, $total)
     *     ->get();
     * ```
     *
     * @example - Pagination with metadata
     * ```php
     * $total = Builder::table('users')->count()->get();
     *
     * $pageInfo = [];
     *
     * $users = Builder::table('users')
     *     ->pagination(3, 10, $total, $pageInfo)
     *     ->get();
     *
     * print_r($pageInfo);
     * ```
     *
     * Example result:
     * ```
     * [
     *   'page' => 3,
     *   'limit' => 10,
     *   'offset' => 20,
     *   'records' => 125,
     *   'pages' => 13,
     *   'hasNext' => true,
     *   'hasPrev' => true,
     *   'next' => 4,
     *   'prev' => 2
     * ]
     * ```
     */
    public function pagination(int $page, int $limit, int $records = 0, ?array &$info = null): self
    {
        $limit = max(1, $limit);
        $page  = max(1, $page);

        $pages = ($records > 0) ? (int) ceil($records / $limit) : 0;

        if ($pages > 0) {
            $page = min($page, $pages);
        }

        $offset = ($page - 1) * $limit;

        if ($records > 0) {
            $offset = min($offset, max(0, $records - $limit));
        }

        if ($info !== null) {
            $info = [
                'page'    => $page,
                'limit'   => $limit,
                'offset'  => $offset,
                'records' => $records,
                'pages'   => $pages,
                'hasNext' => ($pages > 0 && $page < $pages),
                'hasPrev' => $page > 1,
                'next'    => ($pages > 0 && $page < $pages) ? $page + 1 : null,
                'prev'    => ($page > 1) ? $page - 1 : null,
            ];
        }

        $this->limiting = [
            'offset' => $offset, 
            'limit'  => $limit
        ];

        return $this;
    }

    /**
     * Enable or disable strict conditions for query execution.
     *
     * When strict mode is enabled, certain operations (e.g., `delete`, `update`) may 
     * require a `WHERE` clause or logic operator to prevent accidental modifications of all records. 
     * 
     * This helps enforce safer query execution.
     *
     * @param bool $enable Whether to enable strict mode (default: `true`).
     *
     * @return self Returns the instance of the builder class.
     *
     * @example - Enabling strict mode:
     * 
     * If no `WHERE` condition is set, an exception will be thrown.
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $deleted = Builder::table('users')
     *      ->strict()
     *      ->delete(); 
     * ```
     *
     * @example - Disabling strict mode:
     * 
     * The query will execute even if no `WHERE` condition is present.
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $deleted = Builder::table('users')
     *      ->strict(false)
     *      ->delete();
     * ```
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
     * @see self::ascending()  - Orders results in ascending order (`ASC`).
     * @see self::descending() - Orders results in descending order (`DESC`).
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * @see self::order()
     * @see self::ascending()
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
     * @see self::order()
     * @see self::descending()
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('orders')
     *      ->select(['category', 'SUM(sales) as total_sales'])
     *      ->group('category')
     *      ->having('totalSales', '>', 1000)
     *      ->get()
     * ```
     * Generates: `HAVING totalSales > 1000`
     * 
     * @example - Parsing Raw Expression:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * use Luminova\Database\RawExpression;
     * 
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
     * **Selectors:**
     * 
     * @see self::find()
     * @see self::select()
     * @see self::count()
     * @see self::sum()
     * @see self::average()
     * @see self::delete()
     * @see self::update()
     * @see self::copy()
     * @see self::fetch()
     * @see self::stmt()
     * 
     * **Conditions:**
     * 
     * @see self::and()
     * @see self::or()
     * @see self::in()
     * @see self::notIn()
     * @see self::against()
     * @see self::clause()
     * @see self::condition()
     * @see self::between()
     * @see self::notBetween()
     * @see self::having()
     * @see self::whereClause()
     *
     * @example - Using the `WHERE` conditioning:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->select()
     *      ->where('status', '=', 'active')
     *      ->get();
     * ```
     * Generates: `WHERE status = 'active'`
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->select()
     *      ->where('status', '', ['active', 'disabled'])
     *      ->get();
     * ```
     * Generates: `WHERE status IN ('active', 'disabled')`
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->select()
     *      ->where('status', 'NOT', ['active', 'disabled'])
     *      ->get();
     * ```
     * Generates: `WHERE status NOT IN ('active', 'disabled')`
     * 
     *  ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * use Luminova\Database\RawExpression;
     * 
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

        if ($sql) {
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->where('status', '=', 'active')
     *      ->and('role', '=', 'admin')
     *      ->select()
     *      ->get();
     * ```
     * Generates: `WHERE status = 'active' AND role = 'admin'`
     * 
     * @example Using REGEXP for partial match:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
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
     * 
     * @return self Returns the current query builder instance.
     * @throws DatabaseException If less than two values are provided, or an odd number of values is passed.
     * 
     * @see notBetween() - Opposite behavior (values outside the range).
     * 
     * @example - Examples:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * // Using OR BETWEEN
     * $query->between('balance', [0, 100], 'OR');
     * ```
     * 
     * > **Note:**
     * > This method uses named placeholder parameter binding. 
     * > Passing SQL functions (like `NOW()` or `COUNT()`) as values will fail.  
     * > Use `whereClause()` instead if you need raw SQL conditions.
     * 
     * @methodGroup QueryCondition Add match between conditions.
     */
    public function between(string $column, array $values, string $connector = 'AND'): self
    {
        return $this->whereBetween($column, $values, $connector, false);
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
     * use Luminova\Database\Builder;
     * 
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
        return $this->whereBetween($column, $values, $connector, true);
    }

    /**
     * Adds a conditional clause to the query builder using scalar or array values.
     *
     * Supports both regular WHERE conditions and array-based `IN`/`NOT IN` clauses.
     *
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * @param string $column The column name to apply the condition to.
     * @param string $comparison Comparison operator (`=`, `<>`, `>`,`!`, '!=', `LIKE`, `IN`, `NOT`, etc.).
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
     * use Luminova\Database\Builder;
     * 
     * $builder = Builder::table('users')
     *     ->select()
     *     ->condition('AND', 'id', '=', 100)
     *     ->condition('OR', 'id', '=', 101)
     *     ->condition('AND', 'name', '=', 'Peter')
     *     ->condition('AND', 'roles', 'IN', ['admin', 'editor']);
     * ```
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('blogs')
     *      ->select()
     *      ->match(['title', 'description'])
     *         ->orderAgainst('wireless keyboard', Builder::MATCH_BOOLEAN, 'DESC')
     *      ->get();
     * ```
     *
     * @see self::against()
     * @see self::orderAgainst()
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->select([...])
     *      ->like('name', '%pet%')
     *      ->like('username', '%pet%', 'OR')
     *      ->get();
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
     * use Luminova\Database\Builder;
     * Builder::table('users')
     *      ->select([...])
     *      ->notLike('name', '%pet%')
     *      ->get();
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
     * @see self::match()
     * @see self::against()
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
            'mode' => self::MATCH_MODES[$mode] ?? $mode,
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
     * @see self::match()
     * @see self::orderAgainst()
     * 
     * @methodGroup QueryCondition Add full-text match against conditions.
     */
    public function against(mixed $value, string|int $mode = self::MATCH_NATURAL): self
    {
        return $this->clause(
            'AND', 
            $this->getMatchColumns(__METHOD__),
            self::MATCH_MODES[$mode] ?? $mode, 
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
     * @see self::nullable()
     * 
     * @example - Example usage:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->isNotNull('address')
     *      ->select()
     *      ->get();
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
     * @see self::nullable()
     * 
     * @example - Example usage:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->isNull('address')
     *      ->select()
     *      ->get();
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
     * @see self::isNull()
     * @see self::isNotNull()
     * 
     * @example - Example usage:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
    public function set(string $column, mixed $value, ?int $at = null): self
    {
        $isIndexed = ($at !== null && $at >= 0);
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
            $this->querySetValues[$at][$column] = $value;
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('fooTable')->conjoin([
     *     ['column1' => ['comparison' => '=', 'value' => 1]],
     *     ['column2' => ['comparison' => '=', 'value' => 2]]
     * ], 'OR');
     * ```
     * 
     * @example - Using Column: 
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
        //if (!preg_match('/^:[a-zA-Z_-][a-zA-Z0-9_-]*$/u', $placeholder)) {
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
     * @see self::conjoin()
     *
     * @example - Example: 
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * 
     * @see self::conjoin()
     *
     * @example - Example: 
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * 
     * @see self::nested()
     *
     * @example - Generating a query with nested `OR` conditions:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * 
     * @see self::nested()
     *
     * @example - Generating a query with nested `AND` conditions:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('fruits')
     *      ->inset('banana', '= 2', ['apple','banana','orange']);
     * ```
     * Using the `exists` Operator with a column:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('employees')
     *      ->inset('PHP', 'exists', 'column_language_skills');
     * ```
     * 
     * Using the `exists` Operator with a search column:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * @param string $type Return type 
     *      (e.g, `Builder::RETURN_OBJECT`, `Builder::RETURN_ARRAY` or `Builder::RETURN_STATEMENT`).
     * 
     * @return self Returns the current query builder instance.
     * @throws InvalidArgumentException Throws if an invalid type is provided.
     * 
     * > **Note:** 
     * > Call method before `fetch`, `find` `select` etc...
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
     * @see self::select()
     * @see self::find()
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
     * @see self::insert()
     * @see self::copy()
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
     * 
     * @see self::debug()
     * @see self::getDebug()
     * 
     * @example - Debugging;
     * ```php
     * $tbl = Builder::table('users)
     *      ->debug()
     *      ->find([...])
     *      ->where('id', '=', 100)
     *      ->get();
     * ```
     */
    public function debug(int $mode = self::DEBUG_BUILDER_DUMP): self
    {
        $this->debugMode = $mode;
        $this->debugTitles = [];
        $this->debugInformation = [];

        $this->db?->setDebug($mode !== self::DEBUG_NONE);
        return $this;
    }

    /**
     * Return a formatted date or time string suitable for SQL storage.
     *
     * This helper generates common SQL date formats or a UNIX timestamp.
     * If no timestamp is provided, the current time is used.
     * 
     * **Default Formats:**
     * 
     * - `time`     → `HH:MM:SS` (e.g., `14:30:45`)
     * - `date`     → `YYYY-MM-DD` (e.g., `2025-04-03`)
     * - `datetime` → `YYYY-MM-DD HH:MM:SS` (e.g., `2025-04-03 14:30:45`)
     * - `unix`     → UNIX timestamp (e.g., `1712256645`)
     * Any other value is treated as a valid PHP date format string.
     *
     * @param string $format Output format (default: `datetime`).
     * @param DateTimeZone|string|null $timezone Optional timezone object or name.
     * @param int|null $timestamp Optional UNIX timestamp to format. If null, the current time is used.
     *
     * @return string Returns the formatted date/time string or UNIX timestamp.
     */
    public static function datetime(
        string $format = 'datetime', 
        DateTimeZone|string|null $timezone = null, 
        ?int $timestamp = null
    ): string
    {
        if ($format === 'unix') {
            if(!$timestamp){
                return (string) Time::now($timezone)->getTimestamp();
            }

            return (string) Time::fromTimestamp($timestamp, $timezone)->getTimestamp();
        }

        $format = match ($format) {
            'time'     => 'H:i:s',
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            default    => $format
        };

        return ($timestamp === null) 
            ? Time::now($timezone)->format($format)
            : Time::fromTimestamp($timestamp, $timezone)->format($format);
    }

    /**
     * Globally enable or disabled all caching for subsequent select operations.
     *
     * @param bool $enable The caching status action.
     * 
     * @return self Returns the current query builder instance.
     * 
     * > **Note:** 
     * > By default caching is enabled once you call the `cache` method.
     */
    public function cacheable(bool $enable): self
    {
        $this->isCacheable = $enable;
        return $this;
    }

    /**
     * Sets the auto-close connection status for the current query.
     *
     * This method allows you to control whether the database connection should be 
     * automatically closed after executing the query.
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
     * @see self::insert()
     * @see self::update()
     * @see self::delete()
     * @see self::drop()
     * @see self::truncate()
     * @see self::copy()
     * @see self::temp()
     * @see self::execute()
     * 
     * @example - Using safe mode:
     * 
     * Automatically commits or rolls back.
     * 
     * ```php
     * use Luminova\Database\Builder;
     * $tbl = Builder::table('users')
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
        return $this->cache;
    }

    /**
     * Delete a cached item for the current table query.
     *
     * Removes the cache entry associated with the configured cache key.
     * The cache must be initialized using `cache()` before calling this method.
     *
     * @return bool Returns true if the cache item was deleted, otherwise false.
     * @throws RuntimeException If caching was not initialized or no cache key is available.
     * 
     * @see self::cache()
     * @see self::clearCache()
     *
     * @example - Example:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $key = 'user-1';
     * 
     * $deleted = Builder::table('users')
     *      ->cache($key, ...)
     *      ->deleteCache();
     * ```
     */
    public function deleteCache(): bool
    {
        if(!$this->cacheKey || !$this->cache instanceof Cache){
            throw new RuntimeException(
                'Cannot delete cache: caching is not initialized or the cache key is missing.'
            );
        }

        if (!$this->isCacheable) {
            return false;
        }

        return $this->cache->deleteItem($this->cacheKey, true);
    }

    /**
     * Clear all cached items in the current cache storage.
     *
     * The storage is determined by the `cache()` configuration. If no custom
     * storage was defined, the table name is used as the default storage.
     *
     * @return bool Returns true if the storage was cleared successfully, otherwise false.
     * @throws RuntimeException If caching has not been initialized.
     * 
     * @see self::cache()
     * @see self::deleteCache()
     *
     * @example - Custom storage
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $deleted = Builder::table('users')
     *      ->cache(storage: 'my-users')
     *      ->clearCache();
     * ```
     *
     * @example - Table storage
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $deleted = Builder::table('users')
     *      ->cache(storage: null)
     *      ->clearCache();
     * ```
     */
    public function clearCache(): bool
    {
        if(!$this->cache instanceof Cache){
            throw new RuntimeException(
                'Cannot clear cache: caching is not initialized.'
            );
        }

        if (!$this->isCacheable) {
            return false;
        }

        return $this->cache->clear();
    }

    /**
     * Enable result caching for the current query.
     *
     * Configures the cache key, storage, expiration time, and optional
     * settings for file-based or memory-based cache drivers.
     *
     * When caching is enabled globally through `cacheable()`, this method
     * prepares the cache and checks if a valid cached result already exists.
     *
     * @param string|null $key Unique identifier for the cached result.
     * @param string|null $storage Optional cache storage name (defaults: `tableName` or `'capture'`).
     * @param DateTimeInterface|int $expiry Cache expiration time (default: 7 days).
     * @param string|null $subfolder Optional subdirectory for file cache storage.
     * @param string|null $persistentId Optional persistent ID for memory cache connections.
     *
     * @return self Returns the current builder instance.
     * @throws CacheException If cache initialization or cache access fails.
     * 
     * @see self::clearCache()
     * @see self::deleteCache()
     *
     * @example - Caching Query Result:
     * ```php
     * use Luminova\Database\Builder;
     *
     * $uid = 'u100';
     *
     * $user = Builder::table('users')
     *      ->find([...])
     *      ->where('id', '=', $uid)
     *      ->cache(
     *          key: $uid,
     *          storage: 'userProfile',
     *          expiry: 7 * 24 * 60 * 60,
     *          subfolder: 'profiles',
     *          persistentId: 'app-users'
     *      )
     *      ->get();
     * ```
     * 
     * @example - Caching Raw Query Result:
     * ```php
     * use Luminova\Database\Builder;
     *
     * $uid = 'u100';
     *
     * $user = Builder::query("SELECT * FROM users WHERE id = :id")
     *     ->cache(
     *          key: $uid,
     *          storage: 'userProfile',
     *          expiry: 7 * 24 * 60 * 60,
     *          subfolder: 'profiles',
     *          persistentId: 'app-users'
     *     )
     *     ->execute(['id' => $uid]);
     * ```
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

        if(!$key){
            $key = Luminova::getCacheId(
                ($this->tableName ?: ($this->rawQuery ?: 'raw-query')), 
                false
            );
        }
        
        if ($this->isCollectMetadata || $this->unions !== []) {
            $this->options['current']['cache'] = [
                $key, 
                $storage, 
                $expiry, 
                $subfolder, 
                $persistentId
            ];
            return $this;
        }

        $storage ??= $this->tableName;
        $this->newCache($storage, $subfolder, $persistentId);

        $this->cache->setExpire($expiry);

        $this->cacheKey = md5($key);
        $this->isCacheReady = true;
        $this->hasCache = (
            $this->cache->hasItem($this->cacheKey) &&
            !$this->cache->hasExpired($this->cacheKey)
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
     * @param array<int,array<string,mixed>>|array<string,mixed>|null $values Optional records to insert 
     *      or build using (`set()` method).
     *      Each record must be an associative array where:
     *          - Keys are column names
     *          - Values are the values to insert
     * @param bool $usePrepare Whether to use prepared statements (default: true).
     * @param bool $escapeValues Whether to escape values if `$usePrepare` is true (default: true).
     * 
     * @return int Returns the number of rows inserted.
     * 
     * @throws DatabaseException If the data format is invalid (e.g., not associative arrays).
     * @throws JsonException If array values cannot be encoded to JSON.
     * 
     * @see self::set()
     * @see self::replace()
     * @see self::onDuplicate()
     * @see self::ignoreDuplicate()
     * @see self::copy()
     * 
     * @example - Insert a single row:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('logs')->insert([
     *     'message' => 'User login',
     *     'created_at' => Builder::raw('NOW()')
     * ]);
     * ```
     * 
     * @example - Insert multiple rows:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')->insert([
     *     ['name' => 'Alice', 'age' => 28],
     *     ['name' => 'Bob', 'age' => 34]
     * ]);
     * ```
     * 
     * @example - Insert inside a transaction:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
        $values ??= [];
        $values = array_merge($values, $this->querySetValues);

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
        $savepoint = null;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        try {
            $inserted = $usePrepare
                ? $this->executeInsertPrepared($values, $type, $length, $escapeValues) 
                : $this->executeInsertQuery($values, $type, $length);
        } catch (Throwable $e) {
            $this->resolveException($e, savepoint: $savepoint);
            return 0;
        }

        return $this->finishInsert($useTransaction, $inserted, $savepoint);
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
     * @param array<string,mixed>|null $values Optional array of columns and values to update, 
     *      or build using (`set()` method).
     * 
     * @return int Return the number of rows affected.
     * 
     * @throws DatabaseException If no values are provided, or if input is not an associative array.  
     * @throws JsonException If JSON encoding fails when binding values.  
     * 
     * @see self::set()
     * 
     * @example - Update specific row:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *     ->where('id', '=', 1)
     *     ->set('last_login', Builder::datetime())
     *     ->set('attempts', 0)
     *     ->update();
     * ```
     * 
     * @example - Update with raw expression:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *     ->where('id', '=', 1)
     *     ->update([
     *         'score' => Builder::raw('score + 5')
     *     ]);
     * ```
     * 
     * @example - Update with joins and strict mode:
     * ```php
     * use Luminova\Database\Builder;
     * 
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

        $values ??= [];
        $values = array_merge($values, $this->querySetValues);

        $this->assertInUpValues($values, false);
        $top = $this->limiting['top'] ?? '';
        $sql = ($this->cteTableQuery ? "{$this->cteTableQuery} " : '');
        
        if($this->isCteWith){
            $sql .= "UPDATE {$top}{$this->tableName}";
            $sql .= $this->tableAlias ? " AS {$this->tableAlias}" : '';
        }
    
        $sql .= $this->getJoinConditions();
        $sql .= ' SET ' . $this->buildPlaceholder($values, true);
        $this->buildConditions($sql);
        $this->addRawWhereClause($sql);

        $limit = $this->limiting['limit'] ?? 0;
        $ordering = $this->getOptions('ordering');
        $isDebugging = $this->debugMode !== self::DEBUG_NONE;

        if($ordering !== []){
            $sql .= ' ORDER BY ' . rtrim(implode(', ', $ordering), ', ');
        }

        if($limit > 0){
            $sql .= " LIMIT {$limit}";
        }

        if($isDebugging && $this->addDebug($sql, 'update', $values)){
            return 0;
        }

        $response = 0;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        try {
            if(!$isDebugging){
                $this->db->prepare($sql);
            }

            $this->bindStrictColumns($values);
            $this->bindConditions();
            $this->bindJoinPlaceholders();

            if($isDebugging){
                $this->reset();
                return 0;
            }

            $response = $this->db->execute() ? $this->db->rowCount() : 0;
        } catch (Throwable $e) {
            $this->resolveException($e, savepoint: $savepoint);
            return 0;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($response > 0){
                return $this->commit() ? $response : 0;
            }

            $this->rollback(name: $savepoint);
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
     * use Luminova\Database\Builder;
     * use Luminova\Database\RawExpression;
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
     * use Luminova\Database\Builder;
     * 
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
     * @see self::copy() - To prepare copy operation.
     *
     * > **Warning:** 
     * > Ensure that source and destination columns match in count and structure.
     */
    public function to(string $table, array $columns = ['*']): int
    {
        $table = trim($table);

        self::assertTableName($table, null);
        $this->assertInsertOptions();

        $isCopy = $this->selector['isCopy'] ?? false;
        $fromColumns = $this->selector['columns'] ?? [];

         if (!$isCopy || $this->selector === []) {
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
        $this->lastInsertId = null;

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
            $this->addDebug($this->rawQuery, 'copy');
            return 0;
        }

        $cacheable = $this->isCacheable;
        $inserted = 0;
        $savepoint = null;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
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

        return $this->finishInsert($useTransaction, $inserted, $savepoint);
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
     * @see self::columns()
     *
     * @example - Union Example:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * @see self::columns()
     *
     * @example - Union All example:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * 
     * @see self::union()
     * @see self::unionAll()
     *
     * @example - Basic usage with UNION ALL:
     * 
     * ```php
     * use Luminova\Database\Builder;
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
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
     * @see self::query() - Prepare Raw SQL query.
     *
     * @example - Running Raw SQL query:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $result = Builder::query("SELECT * FROM users LIMIT 10")
     *      ->execute();
     * ```
     *
     * @example Running SQL query with bind param:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $user = Builder::query("SELECT * FROM users WHERE id = :id LIMIT 1")
     *     ->execute(['id' => 100], RETURN_NEXT);
     * ```
     *
     * @example Using cache:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * @see self::fetch() - To execute query and return result one after the other.
     * @see self::stmt() - To execute query and return `DatabaseInstance` that resolve to statement object.
     * @see self::promise() - To execute query and return promise object that resolve to result.
     *
     * @example - Basic SELECT example:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $result = Builder::table('users')
     *      ->select(['email', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get(FETCH_OBJ);
     * ```
     *
     * @example - Fetching a single row:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
        
        $returnMode = $this->selector['returns'] 
            ?? $returnMode 
            ?? RETURN_ALL;

        $result = $this->getFromCache($returnMode);

        if($result !== null){
            return $result;
        }

        if($assert = ($this->selector['assert'] ?? null) !== null){
            $this->assertStrictConditions($assert, true);
        }

        if(!$this->isCollectMetadata && $this->unions !== []){
            [$sql, $placeholders] = $this->compileTableUnions();
            
            $this->unions = [];
            $this->options['unionColumns'] = [];

            $query = self::query($sql)
                ->closeAfter($this->closeConnection)
                ->cacheable($this->isCacheable);

            if($this->isCacheable){
                $cache = (array) ($this->options['current']['cache'] ?? []);

                if($cache){
                    $query->cache(...$cache);
                }
            }

            if($this->returns){
                $query->returns($this->returns);
            }

            return $query->execute($placeholders, $returnMode, $fetchMode, false);
        }

        return $this->buildExecutableStatement(
            $this->selector['sql'] ?? '', 
            $this->selector['method'], 
            $this->selector['columns'] ?? ['*'], 
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
     * @see self::get()
     * @see self::stmt()
     * @see self::promise()
     * 
     * @example - Statement example:
     * ```php
     * use Luminova\Database\Builder;
     * 
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

        return (($this->stmt instanceof DatabaseInterface && $this->stmt->ok()) 
            ? $this->stmt 
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
     * @see self::get()
     * @see self::stmt()
     * @see self::fetch()
     * 
     * @example - Promise example:
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * @see self::get()
     * @see self::fetch()
     * @see self::promise()
     * 
     * @example - Fetch all results:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $stmt = Builder::table('users')
     *     ->select(['email', 'name'])
     *     ->where('country', '=', 'NG')
     *     ->stmt();
     * 
     * $result = $stmt->fetchAll(FETCH_OBJ);
     * $stmt->free();
     * ```
     * 
     * @example - Fetch as object:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $stmt = Builder::table('users')
     *     ->find(['email', 'name'])
     *     ->where('id', '=', 1)
     *     ->stmt();
     * 
     * $user = $stmt->fetchObject(User::class);
     * $stmt->free();
     * ```
     * 
     * @example - Accessing the raw PDO statement:
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $stmt = Builder::table('users')
     *     ->find(['email', 'name'])
     *     ->where('id', '=', 1)
     *     ->stmt();
     * 
     * $user = $stmt->getStatement()
     *     ->fetchAll(\PDO::FETCH_DEFAULT);
     * $stmt->free();
     * ```
     * 
     * @methodGroup QueryExecutor Execute query and return database statement object.
     */
    public function stmt(): ?DatabaseInterface
    {
        $this->assertHandler(__METHOD__);

        $this->returns = self::RETURN_STATEMENT;
        $this->selector['method'] = 'stmt';
        $this->selector['returns'] = RETURN_STMT;

        $this->stmt = $this->get(FETCH_OBJ, RETURN_STMT);

        if($this->stmt instanceof DatabaseInterface && $this->stmt->ok()){
            return $this->stmt;
        }

        $this->free();
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
     * use Luminova\Database\Builder;
     * 
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
            ' 1', 
            'total',
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
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * $total = Builder::table('users')
     *      ->count()
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     * @methodGroup SQLFunction Selector function for SQL `COUNT` querying.
     */
    public function count(string $column = '*'): self 
    {
        $this->selector = [
            'sql' => " COUNT({$column})",
            'method' => 'total'
        ];

        return $this;
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
     * use Luminova\Database\Builder;
     * 
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
        $this->selector = [
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
     * use Luminova\Database\Builder;
     * 
     * $votes = Builder::table('users')
     *      ->average('votes')
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     * @methodGroup SQLFunction Selector function for SQL `AVG` querying.
     */
    public function average(string $column): self
    {
        $this->selector = [
            'sql' => " AVG({$column}) AS totalCalc",
            'method' => 'average',
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
     * @see self::bind() To bind named placeholder in SELECT column expression.
     * 
     * @example - Get the all users from country `NG`:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $users = Builder::table('users')
     *      ->select(['votes', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     */
    public function select(array $columns = ['*']): self 
    {
        $this->selector = [
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
     * @see self::bind() To bind named placeholder in SELECT column expression.
     * 
     * @example - Get a single user from country `NG`:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $user = Builder::table('users')
     *      ->find(['votes', 'name'])
     *      ->where('country', '=', 'NG')
     *      ->get();
     * ```
     */
    public function find(array $columns = ['*']): self 
    {
        $this->selector = [
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
     * @see self::bind() To bind named placeholder in SELECT column expression.
     * 
     * @see self::to() - To execute copy to destination.
     *
     * @example - Copy of specific columns:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * $result = Builder::table('users')
     *     ->copy(['id', 'email', 'created_at'])
     *     ->replace(true)
     *     ->where('id', '=', 100)
     *     ->to('backup_users', ['id', 'email', 'created_at']);
     * ```
     */
    public function copy(array $columns = ['*']): self
    {
        $this->selector = [
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
     * @see self::truncate()
     * @see self::rename()
     * @see self::drop()
     * 
     * @example - Delete table column:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->where('id', '=', 1)
     *      ->strict(true) // Enable or disable strict where clause check
     *      ->delete();
     * ```
     */
    public function delete(): int
    {
        $this->assertStrictConditions(__METHOD__);

        $sql = ($this->cteTableQuery ? "{$this->cteTableQuery} " : '');
        
        if($this->isCteWith){
            $top = $this->limiting['top'] ?? '';
            $alias = $this->tableAlias ? " AS {$this->tableAlias}" : '';

            $sql .= match ($this->db->getDriver()) {
                'pgsql' => "DELETE FROM {$this->tableName} USING {$this->tableName}{$alias}",
                'sqlsrv', 'sql-server', 'ms-access' 
                    => "DELETE {$top}FROM {$this->tableName} {$this->tableAlias}",
                default => "DELETE {$this->tableAlias} FROM {$this->tableName} {$this->tableAlias}"
            };
        }

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
     * @param ?string $name Optional transaction savepoint name to create.
     * 
     * @return bool Returns true if the transaction and optional savepoint were successfully started.
     * @throws DatabaseException If invalid savepoint name 
     *          or if failure to set transaction isolation level or create savepoint.
     * 
     * @see self::nestedTransaction()
     * @see self::commit()
     * @see self::rollback();
     * @see self::release();
     * @see self::savepoint();
     * @see self::inTransaction()
     * 
     * @example - Transaction:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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

        throw new DatabaseException(sprintf(
                'Transaction failed to start%s (flags: %d)', 
                $name ? " for \"$name\"" : '', 
                $flags
            ),
            ErrorCode::DATABASE_TRANSACTION_FAILED
        );
    }

    /**
     * Start a nested transaction using an automatic savepoint name.
     *
     * Useful when running transactions inside loops, allowing partial commits
     * or rollbacks without affecting the outer transaction.
     *
     * @param bool $closeCursor Whether to close existing cursors before starting.
     *
     * @return string|false|null The savepoint name, null if a new root transaction was started or false if failed.
     *
     * @see self::transaction()
     * @see self::commit()
     * @see self::rollback()
     * @see self::release()
     * @see self::savepoint()
     * @see self::inTransaction()
     *
     * @example - Nested Transaction:
     * ```php
     * use Luminova\Database\Builder;
     *
     * $tbl = Builder::table('users');
     * $updated = 0;
     *
     * foreach ($users as $user) {
     *     $sp = $tbl->nestedTransaction();
     *
     *     $tbl->where('country', '=', 'NG');
     *     $tbl->and('id', '=', $user->id);
     *
     *     if ($tbl->update(['suburb' => 'Enugu'])) {
     *         $tbl->release($sp);
     *         $updated++;
     *     }
     * }
     *
     * if ($updated > 0) {
     *     $tbl->commit();
     * } else {
     *     $tbl->rollback();
     * }
     *
     * $tbl->free();
     * ```
     */
    public function nestedTransaction(bool $closeCursor = false): string|bool|null
    {
        return $this->db->beginNestedTransaction($closeCursor);
    }

    /**
     * Set a named transaction savepoint.
     * 
     * @param string $name The name for a savepoint to create.
     * 
     * @return bool Returns true on success or false on failure.
     * @throws DatabaseException If an invalid savepoint name or database error.
     */
    public function savepoint(string $name): bool 
    {
        return $this->db->savepoint($name);
    }

    /**
     * Checks if a transaction is currently active.
     *
     * @return bool Returns true if a transaction is active, false otherwise.
     * 
     * @see self::commit()
     * @see self::rollback();
     * @see self::transaction()
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
     *                If provided in PDO, savepoint will be released instead.
     * 
     * @return bool Returns true if the transaction was successfully committed.
     * @throws DatabaseException Throws if invalid savepoint name or failure to create savepoint.
     * 
     * @see self::transaction()
     * @see self::rollback()
     * @see self::inTransaction()
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
     * @throws DatabaseException Throws if invalid savepoint name or failure to create savepoint.
     * 
     * @see self::transaction()
     * @see self::commit()
     * @see self::inTransaction()
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $rollback = true;

        if($this->inTransaction()){
            $rollback = $this->db->rollback($flags, $name);
        }

        $this->reset();
        return $rollback;
    }

    /**
     * Removes the named savepoint from the set of savepoints of the current transaction.
     * 
     * @param string $name The savepoint name to release.
     * 
     * @return bool Returns true on success or false on failure.
     * @throws DatabaseException Throws if invalid savepoint name.
     */
    public function release(string $name): bool 
    {
        $released = false;

        if($this->inTransaction()){
            $released = $this->db->release($name);
        }

        $this->reset();
        return $released;
    }

    /**
     * Rename the current table to a new name.
     *
     * @param string $to The new table name.
     * 
     * @return bool Return true if the rename operation was successful, false otherwise.
     * @throws DatabaseException If the database driver is unsupported.
     * 
     * @see self::delete()
     * @see self::drop()
     * @see self::truncate()
     * 
     * @example - Rename table name.
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * Apply a row-level update lock to the query for concurrency control.
     *
     * Call this method before executing `find([...])`, `select([...])` or similar fetch operations.
     * 
     * @return self Returns the current Builder instance.
     * 
     * > **Note:** 
     * > Must be used inside a transaction.
     * > Locking is only useful if you need selected value to decide the update/insert.
     *
     * @example Lock rows for update (exclusive lock):
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $tbl = Builder::Table('users');
     * 
     * $tbl->transaction();
     * 
     * $rows = $tbl->where('user_id', '=', 123)
     *     ->lockForUpdate() // Prevents others from reading or writing
     *     ->find();
     * 
     * $tbl->commit();
     * ```
     */
    public function lockForUpdate(): self 
    {
        return $this->lockFor('update');
    }

    /**
     * Apply a row-level share lock to the query for concurrency control.
     *
     * Call this method before executing `find()`, `select()` or similar fetch operations.
     * 
     * @return self Returns the current Builder instance.
     * 
     * > **Note:** 
     * > Must be used inside a transaction.
     * > Locking is only useful if you need to read value only.
     *
     * @example - Lock rows for shared read (shared lock):
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $tbl = Builder::Table('users');
     * 
     * $tbl->transaction();
     * 
     * $rows = $tbl->where('user_id', '=', 123)
     *     ->lockInShare() // Allows others to read, but not write
     *     ->find();
     * 
     * $tbl->commit();
     * ```
     */
    public function lockInShare(): self 
    {
        return $this->lockFor('share');
    }

    /**
     * Apply a row-level lock to the query for concurrency control.
     *
     * Call this method before executing `find()`, `select()` or similar fetch operations.
     * 
     * **Lock Modes:**
     * 
     * - `update`: Exclusive lock. Allows reads, blocks writes by others {@see self::lockForUpdate()}.
     * - `shared`: Shared lock. Allows others to read, but not write {@see self::lockInShare()}.
     * 
     * 
     * @param string $mode The lock mode: 'update' or 'shared' (default: `update`).
     * 
     * @return self Returns the current Builder instance.
     * @throws InvalidArgumentException If invalid lock type is given.
     * 
     * > **Note:** 
     * > Must be used inside a transaction.
     * > Locking is only useful if you need selected value to decide the update/insert.
     *
     * @example Lock rows for update (exclusive lock):
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
     * use Luminova\Database\Builder;
     * 
     * $tbl = Builder::Table('users');
     * 
     * $tbl->transaction();
     * 
     * $rows = $tbl->where('user_id', '=', 123)
     *     ->lockFor('share') // Allows others to read, but not write
     *     ->find();
     * 
     * $tbl->commit();
     * ```
     */
    public function lockFor(string $mode = 'update'): self 
    {
        $mode = strtolower($mode);

        if(!in_array($mode, ['update', 'shared', 'share'], true)){
            throw new InvalidArgumentException("Invalid lock type: $mode");
        }
 
        $this->lock = Alter::getBuilderTableLock(
            $this->db->getDriver(), 
            $mode === 'update'
        );

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
     * @see self::delete()
     * @see self::drop()
     * @see self::rename()
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
     * use Luminova\Database\Builder;
     * 
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
        $savepoint = null;
        $useTransaction = false;
        $result = false;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
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
            $this->resolveException($e, savepoint: $savepoint);
            return false;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($result){
                return $this->commit();
            }

            $this->rollback(name: $savepoint);
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
     * use Luminova\Database\Builder;
     * 
     * if (Builder::table('users')->temp()) {
     *     $data = Builder::table('temp_users')->select();
     * }
     * ```
     * 
     * @example - Example Using Transaction:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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
        $savepoint = null;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        try {
            $create = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_{$this->tableName} ";
            $create .= "AS (SELECT * FROM {$this->tableName} WHERE 1 = 0)";
            $result = (
                $this->db->exec($create) > 0 && 
                $this->db->exec("INSERT INTO temp_{$this->tableName} SELECT * FROM {$this->tableName}") > 0
            );
        } catch (Throwable $e) {
            $this->resolveException($e, savepoint: $savepoint);
            return false;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($result){
                return $this->commit();
            }

            $this->rollback(name: $savepoint);
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
     * @see self::delete()
     * @see self::rename()
     * @see self::truncate()
     * 
     * @example - Drop table example:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->drop();
     * ```
     * 
     * @example - Drop table using transaction: 
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *      ->drop(true);
     * ```
     * 
     * @example - Drop table example using transaction:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
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

        $result = false;
        $savepoint = null;
        $useTransaction = false;
        $sql = Alter::getDropTable($this->db->getDriver(), $this->tableName, $isTemporalTable);

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        try {
            $result = (bool) $this->db->exec($sql);
        } catch (Throwable $e) {
            $this->resolveException($e, savepoint: $savepoint);
            return false;
        }

        if($useTransaction && $this->db->inTransaction()){
            if($result){
                return $this->commit();
            }

            $this->rollback(name: $savepoint);
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
     * Release the active builder statement and database statement.
     *
     * This method frees the current statement cursor (if present) and clears the
     * statement reference.
     *
     * It does not close the database connection.
     *
     * @return bool Returns true if statement was release, otherwise false.
     */
    public function free(): bool 
    {
        if($this->stmt instanceof DatabaseInterface){
            $this->stmt->free();
            $this->stmt = null;
        }

        if($this->db instanceof DatabaseInterface){
            $this->db->free();

            return !$this->stmt && !$this->db->isStatement();
        }

        return !$this->stmt;
    }

    /**
     * Close the active database connection.
     *
     * This method first releases any active statement resources using `free()`.
     * If a transaction is still active, it will be rolled back before closing
     * the connection. The connection is then closed and the database reference
     * cleared.
     *
     * @return bool Returns true after attempting to close the connection, otherwise false if failed.
     */
    public function close(): bool 
    {
        try{
            $this->free();

            if($this->db instanceof DatabaseInterface){
                if ($this->db->inTransaction()) {
                    $this->db->rollback();
                }

                if($this->db->isConnected()){
                    $this->db->close();
                }
            }

            $this->db = null;
            return true;
        }catch(Throwable){}
        return false;
    }

    /**
     * Reset the query builder state after execution.
     *
     * This method releases the active statement cursor unless the return mode
     * requires the statement object (`RETURN_STATEMENT`). If automatic connection
     * closing is enabled, the database connection will also be closed.
     *
     * The builder state is then reset for the next query execution.
     *
     * @return bool Returns false when debug mode is enabled, otherwise true.
     *
     * > **Note:**
     * > When `closeAfter()` is enabled, the database connection will be
     * > automatically closed after the reset.
     */
    public function reset(): bool 
    {
        if($this->debugMode !== self::DEBUG_NONE){
            return false;
        }

        if($this->returns !== self::RETURN_STATEMENT){
            $this->free();
        }

        if($this->closeConnection){
            $this->close();
        }
        
        $this->resetState(true);
        return true;
    }
}