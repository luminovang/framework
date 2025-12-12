<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Helpers;

use \Closure;
use \Throwable;
use \PDOStatement;
use \mysqli_result;
use Luminova\Luminova;
use \ReflectionFunction;
use Luminova\Base\Cache;
use Luminova\Database\Builder;
use Luminova\Exceptions\ErrorCode;
use Luminova\Database\Helpers\Util;
use Luminova\Database\Helpers\Alter;
use Luminova\Exceptions\LogicException;
use Luminova\Exceptions\RuntimeException;
use Luminova\Interface\DatabaseInterface;
use Luminova\Exceptions\DatabaseException;
use Luminova\Interface\ExceptionInterface;
use function Luminova\Funcs\is_associative;
use Luminova\Exceptions\BadMethodCallException;
use Luminova\Exceptions\InvalidArgumentException;
use Luminova\Database\{Manager, Connection, Expression};

/**
 * Search and comparison
 *
 * @method self orWhereLike(
 *     string $column,
 *     string $expression,
 *     string $connector = 'OR'
 * ) Matches a column using a LIKE expression.
 *
 * @method self orWhereNotLike(
 *     string $column,
 *     string $expression,
 *     string $connector = 'OR'
 * ) Matches a column excluding a LIKE expression.
 *
 * @method self orWhereBetween(
 *     string $column,
 *     array $values,
 *     string $connector = 'OR'
 * ) Checks whether a column value is within a range.
 *
 * @method self orWhereNotBetween(
 *     string $column,
 *     array $values,
 *     string $groupConnector = 'OR',
 *     string $connector = 'OR'
 * ) Checks whether a column value is outside a range.
 *
 * @method self orWhereIn(
 *     string $column,
 *     Closure|array $values,
 *     string $connector = 'OR'
 * ) Checks whether a column value exists in a list.
 *
 * @method self orWhereNotIn(
 *     string $column,
 *     Closure|array $values,
 *     string $connector = 'OR'
 * ) Checks whether a column value does not exist in a list.
 *
 * @method self orWhereInset(
 *     string $search,
 *     string $operator,
 *     array|string $list,
 *     bool $searchAsColumn = false,
 *     string $connector = 'OR'
 * ) Checks whether a value exists within a set.
 * 
 * @method self orWhereNotInset(
 *     string $search,
 *     array|string $list,
 *     bool $searchAsColumn = false,
 *     string $connector = 'OR'
 * ) Checks whether a negative value exists within a set.
 *
 * @method self orWhereSearch(
 *     string $keyword,
 *     string $pattern = self::SEARCH_CONTAINS,
 *     bool $splitKeyword = false,
 *     bool $caseSensitive = false,
 *     ?string $collation = null,
 *     string $connector = 'OR'
 * ) Performs keyword search across searchable columns.
 *
 * @method self orWhereAgainst(
 *     mixed $value,
 *     string|int $mode = self::MATCH_NATURAL,
 *     string $connector = 'OR'
 * ) Performs full-text search matching.
 *
 * @method self orWhereRegex(
 *     string $column,
 *     string $pattern,
 *     string $connector = 'OR'
 * ) Matches a column using a regular expression.
 *
 * @method self orWherePattern(
 *     string $column,
 *     string $value,
 *     string $connector = 'OR'
 * ) Matches a column using a pattern expression.
 *
 * @method self orWhereCondition(
 *     string $connector,
 *     string $column,
 *     string $operator,
 *     mixed $value
 * ) Adds a custom OR comparison condition.
 *
 * Null checks
 *
 * @method self orWhereNull(
 *     string $column,
 *     string $connector = 'OR'
 * ) Checks whether a column is NULL.
 *
 * @method self orWhereNotNull(
 *     string $column,
 *     string $connector = 'OR'
 * ) Checks whether a column is not NULL.
 *
 * Spatial
 *
 * @method self orWhereDistance(
 *     string $lngColumn,
 *     string $latColumn,
 *     string|float $longitudeExpr,
 *     string|float $latitudeExpr,
 *     float $radius = 10.0,
 *     string $unit = 'km',
 *     string $connector = 'OR'
 * ) Filters records within a geographic distance.
 *
 * @method self orWhereWithin(
 *     float $radius,
 *     string $connector = 'OR'
 * ) Filters records within the configured spatial boundary.
 * 
 * @method self orWhereNotWithin(
 *     float $radius,
 *     string $connector = 'OR'
 * ) Filters records within the nagative configured spatial boundary.
 *
 * Grouping
 *
 * @method self orWhereGroup(
 *     array $conditions,
 *     string $groupConnector = 'AND',
 *     string $connector = 'OR'
 * ) Adds grouped conditions using OR logic.
 *
 * @method self orWhereNested(
 *     array $leftConditions,
 *     array $rightConditions,
 *     string $groupConnector = 'AND',
 *     string $nestedConnector = 'AND',
 *     string $connector = 'OR'
 * ) Adds nested grouped conditions.
 *
 * HAVING
 *
 * @method self orWhereHaving(
 *     Expression|string $expression,
 *     string $operator,
 *     Expression|array|string|int|float|null $value,
 *     string $connector = 'OR'
 * ) Adds an OR HAVING condition.
 *
 * @method self orWhereNotHaving(
 *     Expression|string $expression,
 *     string $operator,
 *     Expression|array|string|int|float|null $value,
 *     string $connector = 'OR'
 * ) Adds an OR negative HAVING condition.
 *
 * Raw expressions
 *
 * @method self orWhereRaw(Expression|string $sql, string $connector = 'OR') Adds a raw SQL condition using OR logic.
 *
 * @method self orWhereClause(
 *     Expression|string $sql,
 *     string $connector = 'OR'
 * ) Adds a raw SQL clause using OR logic.
 *
 * @mixin \Luminova\Database\Builder
 */
trait BuilderTrait 
{
    /**
     * Prepared statement object.
     * 
     * @var DatabaseInterface|mixed $stmt
     */
    private mixed $stmt = null;

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
    private ?Cache $cache = null;

    /**
     * Shared configuration instance.
     * 
     * @var self|null $configure 
     */
    private static ?self $configure = null;

    /**
     * Database table name to query.
     * 
     * @var string $tableName 
     */
    private string $tableName = '';

    /**
     * CTE SQL query.
     * 
     * @var string|null $cteQuery 
     */
    private ?string $cteQuery = null;

    /**
     * CTE query enabled.
     * 
     * @var bool $isCteWith 
     */
    private bool $isCteWith = false;

    /**
     * Valid WITH CTE final query.
     * 
     * @var bool $isCteFinalQuery 
     */
    private bool $isCteFinalQuery = false;

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
     * @var array<int,int> $limiting 
     */
    private array $limiting = [];

    /**
     * Table query group column by.
     * 
     * @var array{
     *      grouping:array,
     *      bindings:array,
     *      ordering:array,
     *      filters:array,
     *      match:array,
     *      matches:array,
     *      where:array,
     *      duplicate:array,
     *      unionColumns:array,
     *      class:array,
     *      metadata:array{
     *          sql:string,
     *          params:array,
     *          columns:array,
     *          cache:array
     *      }
     * } $options 
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
    protected bool $isCollectMetadata = false;

    /**
     * Query selector handler.
     * 
     * @var array<string,mixed> $selector 
     */
    private array $selector = [];

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
     * Detect base where for raw query.
     * 
     * This avoid re-parsing SQL to scan for WHERE.
     *
     * @var bool $hasBaseWhereConnector
     */
    private bool $hasBaseWhereConnector = false;

    /**
     * Detect base where for raw query.
     * 
     * This avoid re-parsing SQL to scan for WHERE.
     *
     * @var bool $hasInjectedBaseWhere
     */
    private bool $hasInjectedBaseWhere = false;

    /**
     * Next cursor start mode.
     *
     * @var bool $nextCursorStarted
     */
    private bool $nextCursorStarted = false;

    /**
     * Enable query debugging.
     * 
     * @var int $debugMode 
     */
    private int $debugMode = self::DEBUG_NONE;

    /**
     * Config strict check.
     * 
     * @var bool $isStrictMode
     */
    private bool $isStrictMode = true;

    /**
     * Result return type.
     * 
     * @var string|null $returns 
     */
    private ?string $returns = null;

    /**
     * Current builder object Id.
     * 
     * @var string|null $objectId
     */
    private ?string $objectId = null;

    /**
     * Cache key.
     * 
     * @var string|null $cacheKey 
     */
    private ?string $cacheKey = null;

    /**
     * Raw cache key (without hashing).
     * 
     * @var string|null $cacheKeyValue 
     */
    private ?string $cacheKeyValue = null;

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
     * @var string $sqlQuery 
     */
    private string $sqlQuery = '';

    /**
     * Query builder caching driver.
     * 
     * @var string|null $cacheDriver 
     */
    private static ?string $cacheDriver = null;

    /**
     * The last inserted Id.
     * 
     * @var mixed $lastInsertId
     */
    private mixed $lastInsertId = null;

    /**
     * Cache information for the current query.
     * 
     * @var array<string,mixed> $cacheInfo
     */
    private array $cacheInfo = [
        'hit'          => false,
        'stored'       => false,
        'connected'    => false,
        'driver'       => null,
        'storage'      => null,
        'expiry'       => null,
        'persistentId' => null
    ];

    /**
     * Drivers with row-level locking after select
     *
     * @var array WITH_DRIVER_LOCK
     */
    private const WITH_DRIVER_LOCK = [
        'sqlsrv' => true, 
        'mssql'  => true, 
        'dblib'  => true,
    ];

    /**
     * Last calculated distance params.
     *
     * @var array $distances
     */
    private array $distances = [];

    /**
     * Debug object.
     *
     * @var Debugger|null $debugger
     */
    private ?Debugger $debugger = null;

    /**
     * @deprecated Use DEBUG_BUILDER_COLLECT instead.
     *
     * @var int DEBUG_BUILDER
     * @codeCoverageIgnore
     */
    public const DEBUG_BUILDER = 1;

    /**
     * @deprecated Use DEBUG_DRIVER_DUMP instead.
     *
     * @var int DEBUG_DRIVER
     * @codeCoverageIgnore
     */
    public const DEBUG_DRIVER = 2;
    
    /**
     * @deprecated Use DEBUG_BUILDER_OUTPUT instead.
     *
     * @var int DEBUG_BUILDER_DUMP
     * @codeCoverageIgnore
     */
    public const DEBUG_BUILDER_DUMP = 3;

    /**
     * Return result as an class object.
     * 
     * @var string RETURN_CLASS
     * @see self::returns()
     */
    private const RETURN_CLASS = 'class';

    /**
     * km per degree of latitude (constant everywhere on Earth)
     * 
     * @var float KM_PER_DEGREE_LATITUDE
     */
    private const KM_PER_DEGREE_LATITUDE = 111.045;

    /**
     * Clause method modes.
     * 
     * @var array<string,bool> CLAUSE_MODES
     */
    private const CLAUSE_MODES = [
        self::RAW     => true, 
        self::REGULAR => true, 
        self::CONJOIN => true, 
        self::NESTED  => true, 
        self::AGAINST => true,  
        self::INARRAY => true, 
    ];

    /**
     * Supported SQL comparison operators.
     *
     * @var array<string, true>
     */
    private const OPERATORS = [
        '='              => true,
        '!='             => true,
        '<>'             => true,
        '>'              => true,
        '>='             => true,
        '<'              => true,
        '<='             => true,
        'LIKE'           => true,
        'NOT LIKE'       => true,
        'ILIKE'          => true,
        'NOT ILIKE'      => true,
        'IN'             => true,
        'NOT IN'         => true,
        'BETWEEN'        => true,
        'HAVING'         => true,
        'HAVING NOT'     => true,
        'NOT BETWEEN'    => true,
        'IS'             => true,
        'IS NOT'         => true,
        'REGEXP'         => true,
        'REGEXP BINARY'  => true,
    ];

    /**
     * Supported inset SQL expression operators.
     *
     * @var array<string,true> INSET_OPERATORS
     */
    private const INSET_OPERATORS = [
        'last' => true, 
        'none' => true,   
        'first' => true,  
        'exists' => true,
        'position' => true,
        'contains' => true, 
    ];

    /**
     * @var array OR_WHERE_METHOD_PROXY
     */
    private const OR_WHERE_METHOD_PROXY = [
        // Search and comparison
        'whereLike'        => true,
        'whereNotLike'     => true,
        'whereBetween'     => true,
        'whereNotBetween'  => true,
        'whereIn'          => true,
        'whereNotIn'       => true,
        'whereInset'       => true,
        'whereSearch'      => true,
        'whereAgainst'     => true,
        'whereNotAgainst'  => true,
        'whereRegex'       => true,
        'whereCondition'   => true,
        'whereExists'      => true,

        // Null checks
        'whereNull'        => true,
        'whereNotNull'     => true,

        // Spatial
        'whereDistance'    => true,
        'whereWithin'      => true,
        'whereNotWithin'   => true,

        // Grouping 
        'whereGroup'       => true,
        'whereNested'      => true,

        // HAVING
        'whereHaving'      => true,
        'whereNotHaving'   => true,

        // Raw expressions
        'whereRaw'         => true,
    ];

    /**
     * Dynamically resolves connector-prefixed query methods.
     *
     * Intercepts calls to methods prefixed with `andWhere` or `orWhere` and
     * forwards them to the corresponding query builder method while automatically
     * injecting the appropriate logical connector.
     *
     * For example, calling `orWhereLike()` is internally forwarded to
     * {@see self::like()} using the `OR` connector, while `andWhereBetween()`
     * is forwarded to {@see self::between()} using the `AND` connector.
     *
     * Only methods registered in {@see self::OR_WHERE_METHOD_PROXY} can be
     * resolved. Calling an unsupported method results in a
     * {@see BadMethodCallException}.
     *
     * @param string $method The intercepted method name.
     * @param array<int, mixed> $arguments Arguments passed to the method.
     *
     * @return mixed The result returned by the resolved query builder method.
     *
     * @throws BadMethodCallException If the method cannot be resolved.
     */
    public function __call(string $method, array $arguments): mixed
    {
        $connector = match (true) {
            str_starts_with($method, 'orWhere') => 'OR',
            str_starts_with($method, 'andWhere') => 'AND',
            default => null,
        };

        if ($connector === null) {
            throw new BadMethodCallException(
                sprintf('Call to undefined method %s::%s().', static::class, $method)
            );
        }

        $method = lcfirst(substr($method, ($connector === 'OR') ? 2 : 3));

        if (!isset(self::OR_WHERE_METHOD_PROXY[$method])) {
            throw new BadMethodCallException(
                sprintf('Call to undefined method %s::%s().', static::class, $method)
            );
        }

        if ($method === 'whereCondition') {
            array_unshift($arguments, $connector);
        } else {
            $arguments[] = $connector;
        }

        return $this->{$method}(...$arguments);
    }

    /**
     * Adds a condition to filter results where the given column is `NULL` or `NOT NULL`.
     *
     * This method appends a null match based on "$connector" condition to the query.
     * It ensures that only records with a null or non-null value in the specified column are retrieved.
     *
     * @param string $column The column name to check for non-null values.
     * @param bool $whereNull Whether the the column should be null or not (default: true).
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * 
     * @return self Return current builder instance.
     * 
     * @group QUERY_CONDITION
     * 
     * @see self::whereNull()
     * @see self::whereNotNull()
     * 
     * @example - Example usage:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * Builder::table('users')
     *     ->select()
     *      ->where('country', '=', 'NG')
     *      ->whereNullable('address')
     *      ->get();
     * ```
     */
    private function whereNullable(string $connector, string $column, bool $whereNull): self
    {
        return $this->whereClause(
            $connector, 
            $column, 
            $whereNull ? 'IS' : 'IS NOT', 
            self::expression('NULL')
        );
    }

    /**
     * Get an array of debug query information.
     * 
     * Returns detailed debug information about the query string, including formats for `MySQL` 
     * and `PDO` placeholders, as well as the exact binding mappings for each column.
     * 
     * @return array{method:string,binding:array,query:array} Return array containing query information.
     * 
     * @group QUERY_DEBUGGER
     * 
     * @see self::dumpDebug()
     */
    public function getDebug(): array 
    {
        return $this->debugger->getDebug();
    }

    /**
     * Get cache information for the current query.
     * 
     * - `driver`: The cache driver in use (e.g., 'file', 'redis').
     * - `storage`: The storage type (e.g., 'database', 'memory').
     * - `persistentId`: The persistent identifier for the cache entry.
     * - `expiry`: The expiration time for the cached result (if applicable).
     * - `hit`: Boolean indicating if the result was retrieved from cache.
     * - `connected`: Boolean indicating if the cache connection is active.
     * - `stored`: Boolean indicating if the result was successfully stored in cache.
     *
     * @return array{
     *      driver:?string,
     *      storage:?string,
     *      persistentId:?string,
     *      expiry:?int,
     *      connected:bool,
     *      hit:bool,
     *      stored:bool
     * } Returns an associative array containing cache details.
     * 
     * @group QUERY_CACHING
     */
    public function getCacheInfo(): array
    {
        return array_merge($this->cacheInfo, [
            'hit'          => false,
            'stored'       => false,
            'connected'    => false,
            'driver'       => null,
            'storage'      => null,
            'expiry'       => null,
            'persistentId' => null
        ]);
    }

    /**
     * Assigns a database connection to the builder.
     *
     * Resolves and validates the provided connection source before attaching it
     * to the builder. When no connection is provided, the default database
     * connection is created.
     *
     * @param DatabaseInterface|Connection|null $connection Connection source.
     * @param bool $releaseResources Whether to release active statements before use.
     *
     * @return self Return current builder instance.
     * @throws DatabaseException If the connection cannot be established.
     */
    public function useConnection(
        DatabaseInterface|Connection|null $connection = null,
        bool $releaseResources = false
    ): self
    {
        $database = match (true) {
            $connection === null => self::database(shared: false),
            $connection instanceof DatabaseInterface => $connection,
            default => $connection->database() ?? $connection->connect()
        };

        if (!$database instanceof DatabaseInterface) {
            throw new DatabaseException(
                'Unable to establish database connection.',
                ErrorCode::CONNECTION_DENIED
            );
        }

        if (!$database->isConnected() && !$database->connect()) {
            throw new DatabaseException(
                'Database connection is not active.',
                ErrorCode::CONNECTION_DENIED
            );
        }

        if ($releaseResources) {
            $database->free();
        }

        $this->db = $database;
        $this->objectId = $this->createObjectId();

        return $this;
    }

    /**
     * Attach a database connection or driver to the builder.
     *
     * @deprecated Use useConnection() instead.
     *
     * @param DatabaseInterface|Connection|null $conn Connection source or null to assign new connection.
     * @param bool $freeStmt Whether to free active statements and release cursor before use.
     * 
     * @return self Return current builder instance.
     * @throws DatabaseException If no active connection is available.
     * 
     * @group QUERY_OPTION
     */
    public function connection(DatabaseInterface|Connection|null $conn = null, bool $freeStmt = false): self
    {
        return $this->useConnection($conn, $freeStmt);
    }

    /**
     * Applies a row-level lock to the query for transaction concurrency control.
     *
     * The lock is applied when the query is executed and should be used with
     * fetch operations such as {@see self::find()} or {@see self::select()} inside
     * an active transaction.
     *
     * Supported lock modes:
     * 
     * - `update`: Acquires an exclusive row lock to prevent conflicting updates
     *   or deletes by other transactions {@see self::lockForUpdate()}.
     * - `share`: Acquires a shared row lock that allows concurrent reads while
     *   preventing conflicting writes {@see self::lockForShare()}.
     *
     * @param string $mode Lock mode: `update` or `share` (default: `update`).
     *
     * @return self The current builder instance.
     *
     * @throws InvalidArgumentException If an unsupported lock mode is provided.
     * @throws DatabaseException If the current database driver does not support
     *                            the requested lock mode.
     *
     * @group QUERY_OPTION
     *
     * @example - Example:
     * ```php
     * $tbl = Builder::table('users');
     *
     * $tbl->transaction();
     *
     * $user = $tbl->where('user_id', '=', 123)
     *     ->lock('update')
     *     ->find();
     *
     * $tbl->commit();
     * ```
     * 
     * > **Note:** 
     * > Should be used inside a transaction.
     * > Locking is only useful if you need selected value to decide the update/insert.
     */
    public function lock(string $mode = 'update'): self
    {
        $mode = strtolower($mode);

        if (!in_array($mode, ['update', 'share'], true)) {
            throw new InvalidArgumentException(
                "Invalid lock mode: {$mode}. Supported modes [update, share]."
            );
        }

        $driver = $this->db->getDriver();
        $lock = Alter::getBuilderTableLock(
            $driver,
            $mode === 'update'
        );

        if ($lock === null || $lock === '') {
            throw new DatabaseException(
                sprintf(
                    'Lock mode "%s" is not supported in "%s" driver.',
                    $mode,
                    $driver
                )
            );
        }

        $this->lock = $lock;

        return $this;
    }

    /**
     * Apply a row-level lock to the query for concurrency control.
     *
     * @deprecated Use lock() instead.
     * 
     * 
     * @param string $mode The lock mode: 'update' or 'share' (default: `update`).
     * 
     * @return self Returns the current Builder instance.
     * @throws InvalidArgumentException If invalid lock type is given.
     * @throws DatabaseException If driver lock not supported.
     * 
     * @codeCoverageIgnore
     */
    public function lockFor(string $mode = 'update'): self 
    {
        if ($mode === 'shared') {
            $mode = 'share';
        }

        return $this->lock($mode);
    }

    /**
     * Generate an SQL alias clause.
     *
     * Returns an `AS` clause when an alias is provided. Empty or null aliases
     * return an empty string.
     *
     * @param string|null $alias SQL alias name.
     *
     * @return string Returns the formatted alias clause or an empty string.
     *
     * @throws InvalidArgumentException If the alias contains invalid characters.
     *
     * @example - Example:
     * ```php
     * Builder::alias('distance');
     * // Returns: " AS distance"
     *
     * Builder::alias(null);
     * // Returns: ""
     * ```
     */
    public static function alias(?string $alias): string
    {
        if ($alias === null || $alias === '') {
            return '';
        }

        self::assertTableAlias($alias);

        return " AS {$alias}";
    }

    /**
     * Executes a prepared `copy()` query and inserts its result into the specified target table.
     *
     * This method finalizes a `copy()` operation by executing the selection and inserting
     * the results into another table using either `INSERT`, `INSERT IGNORE`, or `REPLACE`.
     *
     * @param string $table Target table to insert copied data into.
     * @param string[] $columns Target table columns to insert data into.
     *
     * @return int Return the number of affected rows.
     * @throws InvalidArgumentException If the target table name is empty.
     * @throws DatabaseException If copy mode isn't active, or if column mismatch occurs.
     * @throws \Luminova\Exceptions\JsonException If operation involves JSON-encodable values and encoding fails.
     * 
     * @group QUERY_EXECUTOR
     *
     * @see self::copy() - To prepare copy operation.
     *
     * > **Warning:** 
     * > Ensure that source and destination columns match in count and structure.
     */
    public function to(string $table, array $columns): int
    {
        $table = trim($table);

        self::assertTableName($table);
        $this->assertInsertOptions();

        $isCopyInsert = $this->selector['isCopy'] ?? false;

        if (!$isCopyInsert || $this->selector === []) {
            throw new DatabaseException(
                'The copy(...) method must be called before to(...).',
                ErrorCode::BAD_METHOD_CALL
            );
        }

        $fromColumns = $this->selector['columns'] ?? [];
        $isEmptyColumns = $fromColumns === [] || $columns === [];

        if (
            $isEmptyColumns
            || in_array('*', $fromColumns, true)
            || in_array('*', $columns, true)
            || count($fromColumns) !== count($columns)
        ) {
            throw new DatabaseException(
                $isEmptyColumns
                    ? 'Source and destination columns must not be empty.'
                    : 'Mismatch between source and destination column counts.',
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        $this->isCollectMetadata = true;
        $this->lastInsertId = null;

        // Build query metadata without execution
        if(!$this->get()){
            return 0;
        }

        $metadata = $this->getOptions('metadata');
        $this->isCollectMetadata = false;

        if($metadata === []){
            return 0;
        }

        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';
        $this->sqlQuery = $this->startQueryWith();

        if($this->isCteFinalQuery){
            throw new DatabaseException('Final CET query is not supported for table copy.');
        }

        $this->sqlQuery = $this->isReplace ? 'REPLACE' : 'INSERT';
        $this->sqlQuery .= " {$ignore}INTO {$table}";
        $this->sqlQuery .= ' (' . trim(implode(',', $columns), ',') . ')';
        $this->sqlQuery .= " {$metadata['sql']}"; // select statements

        $placeholders = $metadata['params'] ?? [];
        $this->sqlQuery .= $this->buildDuplicateUpdateClause($placeholders);

        if($this->isBuilderDebugging()){
            $this->addDebug($this->sqlQuery, 'copy');
            return 0;
        }

        $savepoint = null;
        $useTransaction = false;
        $isCacheable = $this->isCacheable;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        try {
            if($placeholders === []){
                $inserted = $this->db->query($this->sqlQuery)->ok() 
                    ? $this->db->rowCount() 
                    : 0;
            }else{
                $inserted = $this->cacheable(false)
                    ->execute(
                        $placeholders, 
                        RETURN_COUNT, 
                        FETCH_NUM, 
                        false
                    );
            }

            return $this->finishInsert($useTransaction, $inserted, $savepoint);
        } catch (Throwable $e) {
            $this->resolveException($e, true);
        } finally {
            $this->isCacheable = $isCacheable;
            $this->isCollectMetadata = false;
        }

        return 0;
    }

    /**
     * Retrieves the database manager instance.
     * 
     * Returns a singleton instance of the Manager class initialized with the current database connection.
     * 
     * @return Manager Database manager class instance.
     * @throws DatabaseException Throws if database connection failed.
     * @group QUERY_UTIL
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
     * @param string[] $columns Table columns to export (default: all).
     * 
     * @return bool Return true if export is successful, false otherwise.
     * @throws DatabaseException If an invalid format is provided or if unable to create the export.
     * 
     * @group QUERY_UTIL
     */
    public function export(string $as = 'csv', ?string $filename = null, array $columns = ['*']): bool 
    {
        return $this->manager()
            ->export($as, $filename, $columns);
    }

    /**
     * Creates a backup of the database table.
     * 
     * @param string|null $filename Optional name of the backup file (default: null). 
     *              If not provided, table name and timestamp will be used.
     * 
     * @return bool Return true if backup is successful, false otherwise.
     * @throws DatabaseException If unable to create the backup directory or if failed to create the backup.
     * 
     * @group QUERY_UTIL
     */
    public function backup(?string $filename = null): bool 
    {
        return $this->manager()
            ->backup($filename, true);
    }

    /**
     * Adds a grouped condition set to the query.
     *
     * Creates a logical condition group where all conditions are combined using
     * the specified group connector. The resulting group is then joined to the
     * query using the outer connector.
     *
     * @param string $connector The logical connector (`AND` or `OR`) used to join
     *                          the group with previous query conditions.
     * @param string $fn The calling method name used for connector validation.
     * @param array $conditions The grouped conditions.
     * @param string $groupConnector The logical connector (`AND` or `OR`) used
     *                               within the condition group.
     *
     * @return self Returns the builder instance.
     *
     * @throws InvalidArgumentException If an invalid connector is specified.
     */
    private function whereConjoinGroup(
        string $connector,
        string $fn,
        array $conditions, 
        string $groupConnector = 'AND'
    ): self
    {
        [$connector, $groupConnector,] = $this->parseConnectors(
            $fn ?: __METHOD__, 
            $connector,
            $groupConnector
        );

        $this->conditions[] = [
            'connector'        => $connector,
            'mode'             => self::CONJOIN,
            'conditions'       => $conditions,
            'groupConnector'   => $groupConnector,
        ];

        return $this;
    }

    /**
     * Adds a nested condition group to the query.
     *
     * Creates two grouped condition sets and combines them using the specified
     * nested connector. Each group uses the same group connector for its internal
     * conditions, and the resulting nested group is joined to the query using the
     * outer connector.
     *
     * @param string $connector The logical connector (`AND` or `OR`) used to join
     *                          the nested group with previous query conditions.
     * @param string $fn The calling method name used for connector validation.
     * @param array $leftConditions The conditions for the left group.
     * @param array $rightConditions The conditions for the right group.
     * @param string $groupConnector The logical connector (`AND` or `OR`) used
     *                               within each condition group.
     * @param string $nestedConnector The logical connector (`AND` or `OR`) used
     *                                to combine the two groups.
     *
     * @return self Returns the builder instance.
     *
     * @throws InvalidArgumentException If an invalid connector is specified.
     */
    private function whereNestedGroup(
        string $connector,
        string $fn,
        array $leftConditions, 
        array $rightConditions, 
        string $groupConnector = 'AND', 
        string $nestedConnector = 'AND'
    ): self
    {
        [$connector, $groupConnector, $nestedConnector] = $this->parseConnectors(
            $fn ?: __METHOD__,
            $connector, 
            $groupConnector, 
            $nestedConnector
        );

        $this->conditions[] = [
            'left'      => $leftConditions,
            'right'     => $rightConditions,
            'mode'      => self::NESTED,
            'connector' => $connector,
            'nestedConnector' => $nestedConnector,
            'groupConnector'  => $groupConnector
        ];

        return $this;
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
     * @param ?string $operator Comparison operator (e.g., `=`, `<>`, `>=`, `LIKE`, etc.) or null for raw.
     * @param (Closure(Builder $static):mixed)|mixed $value The condition value to compare. 
     *              Can be scalar or array (for `Builder::INARRAY`).
     * @param string|null $mode The clause mode. One of the supported modes (default: Builder::REGULAR`).
     *
     * @return self Returns instance for builder class.
     * @throws InvalidArgumentException If an unsupported mode is given or if `INARRAY` is used with an empty array.
     * 
     * @group QUERY_CONDITION
     *
     * @internal Used internally by the builder to compose query conditions.
     *           Can also be called directly to manually define clauses without relying on
     *           higher-level methods like `where()`, `or()`, or `whereAgainst()`.
     *           Useful when you want full control and to skip additional processing.
     *
     * @example - Example usage:
     * 
     * ```php
     * use Luminova\Database\Builder;
     * 
     * $builder = Builder::table('users')
     *     ->select()
     *     ->whereClause('AND', 'id', '=', 100)
     *     ->whereClause('OR', 'id', '=', 101)
     *     ->whereClause('AND', 'name', '=', 'Peter')
     *     ->whereClause('AND', 'roles', 'IN', ['admin', 'editor'], Builder::INARRAY)
     *     ->get();
     * ```
     * 
     * @see self::where()
     * @see self::and()
     * @see self::or()
     * @see self::whereIn()
     * @see self::whereNotIn()
     * @see self::whereCondition()
     * @see self::whereAgainst()
     */
    private function whereClause(
        string $connector,
        ?string $column,
        ?string $operator,
        mixed $value,
        ?string $mode = null,
        bool $whereNot = false 
    ): self 
    {
        $connector = $this->resolveConnector(
            $this->parseConnector($connector, __METHOD__)
        )[0];

        $mode = strtoupper($mode ?? self::REGULAR);

        if (!isset(self::CLAUSE_MODES[$mode])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid clause mode "%s". Supported modes: %s.',
                $mode,
                implode(', ', array_keys(self::CLAUSE_MODES))
            ));
        }

        if ($mode === self::INARRAY) {
            $value = $this->getValue($value);

            if ($value === [] || !is_array($value)) {
                throw new InvalidArgumentException(
                    'The INARRAY mode requires a non-empty array of values.'
                );
            }
        }

        if ($mode === self::RAW && ($column !== null || ($value !== null && !is_scalar($value)))) {
            throw new InvalidArgumentException(
                'The RAW mode requires a null column name and non-collection value.'
            );
        }

        $this->conditions[] = [
            'connector'  => $connector,
            'mode'       => $mode,
            'column'     => $column,
            'value'      => $value,
            'operator'   => $operator,
            'not'        => $whereNot
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
     * @return self Return current builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see self::on(...)
     * @see self::join(...)
     */
    public function innerJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->joinTables(
            table: $table,
            alias: $alias, 
            type: 'INNER', 
            forSubquery: $forSubquery
        );
    }

    /**
     * Sets table join condition as `LEFT JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Return current builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @see self::on(...)
     * @see self::join(...)
     */
    public function leftJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->joinTables(
            table: $table,
            alias: $alias, 
            type: 'LEFT', 
            forSubquery: $forSubquery
        );
    }

    /**
     * Sets table join condition as `RIGHT JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Return current builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @group QUERY_INITIALIZER
     * 
     * @see self::on(...)
     * @see self::join(...)
     */
    public function rightJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->joinTables(
            table: $table,
            alias: $alias, 
            type: 'RIGHT', 
            forSubquery: $forSubquery
        );
    }

    /**
     * Sets table join condition as `CROSS JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Return current builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @group QUERY_INITIALIZER
     * 
     * @see self::on(...)
     * @see self::join(...)
     */
    public function crossJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->joinTables(
            table: $table,
            alias: $alias, 
            type: 'CROSS', 
            forSubquery: $forSubquery
        );
    }

    /**
     * Sets table join condition as `FULL JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Return current builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @group QUERY_INITIALIZER
     * 
     * @see self::on(...)
     * @see self::join(...)
     */
    public function fullJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->joinTables(
            table: $table,
            alias: $alias, 
            type: 'FULL', 
            forSubquery: $forSubquery
        );
    }

    /**
     * Sets table join condition as `FULL OUTER JOIN`.
     * 
     * @param string $table The table name
     * @param string|null $alias Optional table join alias (default: NULL).
     * @param bool $forSubquery Set to `true` if the joined source is a subquery instead of a normal table.
     * 
     * @return self Return current builder instance.
     * @throws InvalidArgumentException Throws if invalid argument is provided.
     * 
     * @group QUERY_INITIALIZER
     * 
     * @see self::on(...)
     * @see self::join(...)
     */
    public function fullOuterJoin(string $table, ?string $alias = null, bool $forSubquery = false): self
    {
        return $this->joinTables(
            table: $table,
            alias: $alias, 
            type: 'FULL OUTER', 
            forSubquery: $forSubquery
        );
    }

    /**
     * Escapes a value for safe use in SQL queries, handling various types and formats.
     *
     * This method ensures that values are properly escaped and formatted for use in SQL statements,
     * 
     * - `null`, `bool`, and numeric values are cast based on the `$strict` flag.
     * - Arrays and objects are encoded as JSON. Existing valid JSON strings are preserved.
     * - `Expression` instances are returned as-is, unescaped.
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
     * @return mixed Returns a properly escaped and type-safe value.
     * @throws \JsonException If JSON encoding fails for arrays or objects.
     * @throws DatabaseException If value is resource and failed to read content.
     * 
     * @group QUERY_UTIL
     */
    public static function escape(
        mixed $value, 
        bool $enQuote = false, 
        bool $strict = false,
        bool $numericCheck = false,
        bool $addSlashes = false
    ): mixed
    {
        return match(true) {
            $value === ''           => '',
            $value === null         => $strict ? null : 'NULL',
            $value === []           => $strict ? [] : '[]',
            $value === (object)[]   => $strict ? (object)[] : '{}',
            $value instanceof Expression => $value->toString(),
            $value instanceof Closure       => self::escape(
                $value(), 
                $enQuote, 
                $strict, 
                $numericCheck, 
                $addSlashes
            ), 
            is_bool($value)     => $strict ? (int) $strict : ($value ? 'TRUE' : 'FALSE'), 
            is_int($value) || is_float($value) => (string) $value,
            is_numeric($value)    => $numericCheck ? to_numeric($value, true) : (string) $value,
            is_array($value) || is_object($value) => self::escapeCollection(
                $value, 
                $enQuote, 
                $strict, 
                $numericCheck
            ),
            is_resource($value)   => self::escapeResource($value, $enQuote, $strict),
            json_validate($value) => $enQuote ? self::quote($value) : $value,
            default               => self::escapeStringLiteral((string) $value, $enQuote, $addSlashes)
        };
    }

    /**
     * Quote literal string.
     *
     * @param string $value
     * 
     * @return string Return quoted string value.
     * 
     * @group QUERY_UTIL
     * @codeCoverageIgnore
     */
    public static function quote(string $value): string 
    { 
        return  "'" . str_replace("'", "''", (string) $value) . "'"; 
    }

    /**
     * Converts a column name or SQL expression into a safe named placeholder.
     *
     * The method normalizes input values by removing invalid characters and
     * formatting SQL function expressions. When an SQL function is detected,
     * the function name is prefixed to the column name to prevent placeholder
     * collisions (e.g., `COUNT(id)` becomes `:count_id`).
     *
     * Examples:
     * - "created_at" → ":created_at"
     * - ":created_at" → ":created_at"
     * - "table.created_at" → ":table_created_at"
     * - "COUNT(id)" → ":count_id"
     * - "MONTH(created_at)_2_68838" → ":month_created_at_2_68838"
     *
     * @param string|null $input The column name, alias, or SQL function expression.
     * @param string|null $objectId Optional object identifier appended to the placeholder.
     *
     * @return string Returns a formatted named placeholder.
     * 
     * @group QUERY_UTIL
     * @codeCoverageIgnore
     */
    public static function toNamedParameter(?string $input, ?string $objectId = null): string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\(([^()]*)\)(.*)$/', $input, $matches)) {
            $input = sprintf(
                '%s_%s%s',
                strtolower($matches[1]),
                trim($matches[2]),
                trim($matches[3])
            );
        }

        $input = preg_replace('/[^A-Za-z0-9_]+/', '_', $input);
        $input = trim($input, "_ :\t\n\r\0\x0B");

        return ($objectId === null)
            ? ":{$input}"
            : ":{$input}_{$objectId}";
    }
  
    /**
     * Escapes and prepares comma-separated values from an array.
     *
     * Each value is escaped using the configured quoting rules. Values can be
     * wrapped in quotes unless quoting is disabled, which is useful for JSON or
     * other special expressions.
     *
     * @param array<int,mixed> $columns The values to escape and join.
     * @param bool $enQuote Whether to wrap escaped values in quotes (except JSON values).
     *
     * @return string A comma-separated string of escaped values.
     *
     * @throws \Luminova\Exceptions\JsonException If a value cannot be encoded.
     *
     * @group QUERY_UTIL
     * @codeCoverageIgnore
     */
    public static function escapeValueList(array $columns, bool $enQuote = true): string
    {
        if ($columns === []) {
            return '';
        }

        $values = [];

        foreach ($columns as $item) {
            $values[] = self::escape($item, $enQuote);
        }

        return implode(', ', $values);
    }

    /**
     * Build query to calculate the average value of a numeric column.
     * 
     * @param string[]|string $column The column name or list of columns to average.
     * @param bool $distinct Whether to average only distinct values.
     *
     * @return self Return current builder instance.
     * 
     * @group QUERY_SELECTOR
     *
     * @example - Get the average user votes in country `NG`:
     *
     * ```php
     * use Luminova\Database\Builder;
     *
     * $votes = Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->avg('votes')
     *      ->get();
     * ```
     */
    public function avg(array|string $column = '*', bool $distinct = false): self
    {
        return $this->aggregate(
            'AVG',
            $column,
            $distinct
        );
    }

    /**
     * Output collected query debug information in the requested format.
     *
     * Supported formats:
     *
     * - `null`   Default output using readable array format.
     * - `html`   Wrap output in an escaped HTML `<pre>` block.
     * - `json`   Output formatted JSON.
     *
     * The selected format only applies to builder dump debugging mode.
     * CLI and command execution always use plain text output.
     *
     * @param string|null $format Output format (`html`, `json`, or null).
     *
     * @return void
     * @group QUERY_DEBUGGER
     *
     * @see self::getDebug()
     */
    public function dump(?string $format = null): void
    {
        if ($this->debugMode === self::DEBUG_BUILDER_COLLECT) {
            $this->debugger->dump($format);
            return;
        }

        if ($this->debugMode === self::DEBUG_DRIVER_DUMP) {
            $this->db->dumpDebug();
            return;
        }
    }
    
    /**
     * Build an aggregate SQL function selector.
     *
     * Creates an aggregate expression such as `COUNT`, `SUM`, `AVG`, `MIN`,
     * or `MAX` and sets it as the query selector.
     *
     * **Applies to:**
     *
     * - `count()`
     * - `sum()`
     * - `avg()`
     * - `min()`
     * - `max()`
     *
     * @param string       $function The aggregate SQL function name.
     * @param string[]|string $column The column name or list of columns to aggregate.
     * @param bool         $distinct Whether to apply the `DISTINCT` modifier.
     *
     * @return self Return current builder instance.
     */
    private function aggregate(
        string $function, 
        array|string $column = '*', 
        bool $distinct = false
    ): self
    {
        if (is_array($column)) {
            $column = ($column === []) ? '*'  : implode(', ', $column);
        }

        if ($distinct) {
            $column = "DISTINCT {$column}";
        }

        $this->selector = [
            'sql'    => "{$function}({$column}) AS aggregate",
            'method' => 'aggregate'
        ];

        return $this;
    }

    /**
     * Prepare a CET query head.
     *
     * @return string Return query if any.
     */
    private function startQueryWith(): string 
    {
        if(!$this->isCteWith || $this->cteQuery === null){
            return '';
        }

        return "{$this->cteQuery} ";
    }

    /**
     * Check if builder level debugging is enabled.
     *
     * @return bool
     */
    private function isBuilderDebugging(): bool 
    {
        return $this->debugger instanceof DEbugger
            && $this->debugMode !== self::DEBUG_NONE 
            && $this->debugMode !== self::DEBUG_DRIVER_DUMP;
    }

    /**
     * Escapes a string value for safe use in SQL queries.
     *
     * @param string $value The string value to escape.
     * @param bool $enQuote If true, wraps the value in single quotes.
     * @param bool $addSlashes If true, applies `addslashes()` to the value.
     *
     * @return string Returns the escaped string, optionally quoted.
     */
    private static function escapeStringLiteral(
        string $value, 
        bool $enQuote, 
        bool $addSlashes
    ): string
    {
        if($addSlashes){
            $value = addslashes($value);
        }

        if(!$enQuote){
            return $value;
        }

        return self::quote($value);
    }

    /**
     * Escapes an array or object for safe use in SQL queries.
     *
     * @param array|object $collection The collection to escape.
     * @param bool $enQuote If true, wraps the JSON-encoded value in single quotes.
     * @param bool $strict Whether to use strict type casting (default: false).
     * @param bool $numericCheck If true, numeric strings are cast to int/float during JSON encoding.
     *
     * @return string Returns the escaped JSON string, optionally quoted.
     * @throws \JsonException If JSON encoding fails.
     */
    private static function escapeCollection(
        array|object $collection, 
        bool $enQuote, 
        bool $strict,
        bool $numericCheck
    ): string
    {
        $isObject = is_object($collection);

        if ($isObject) {
            $collection = get_object_vars($collection);
        }

        if ($strict && empty($collection)) {
            return $isObject ? '{}' : '[]';
        }

        $flags = JSON_THROW_ON_ERROR
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION;

        if($numericCheck){
            $flags |= JSON_NUMERIC_CHECK;
        }

        return $enQuote 
            ? self::quote(json_encode($collection, $flags))
            : json_encode($collection, $flags);
    }

    /**
     * Escapes a resource for safe use in SQL queries.
     *
     * @param mixed $resource The resource to escape.
     * @param bool $enQuote If true, wraps the base64-encoded value in single quotes.
     * @param bool $strict Whether to use strict type casting (default: false).
     *
     * @return string Returns the escaped resource string, optionally quoted.
     * @throws DatabaseException If failed to read content from resource stream.
     */
    private static function escapeResource(mixed $resource, bool $enQuote, bool $strict): string
    {
        $stream = stream_get_contents($resource);

        if ($stream === false) {
            throw new DatabaseException(
                'Failed to read content from resource stream.',
                ErrorCode::RUNTIME_ERROR
            );
        }
        
        if ($strict) {
            return $stream;
        }

        $encoded = base64_encode($stream);

        return $enQuote ? "'{$encoded}'" : $encoded;
    }

    /**
     * Initializes a builder instance from the shared configuration when available.
     *
     * If a shared Builder instance exists, its global configuration is cloned into
     * a new builder instance while keeping query-specific state isolated.
     *
     * When no shared instance exists, a fresh builder instance is created without
     * storing it as the shared singleton instance.
     *
     * @param string|null $table The name of the table to initialize the builder with.
     * @param string|null $alias The alias for the table.
     *
     * @return self A new configured Builder instance.
     * @throws DatabaseException If an error occurs during initialization.
     */
    private static function instance(
        ?string $table = null, 
        ?string $alias = null
    ): self
    {
        $new = new self($table, $alias);

        if (!self::$configure instanceof self) {
            $new->db = self::database();
            return $new;
        }

        // Reset state
        // Reset object id first
        $new->objectId = $new->createObjectId();
        $new->lastInsertId = null;
        
        // Shared configs
        $new->returns = self::$configure->returns;
        $new->debugMode = self::$configure->debugMode;
        $new->isSafeMode = self::$configure->isSafeMode;
        $new->isStrictMode = self::$configure->isStrictMode;
        $new->closeConnection = self::$configure->closeConnection;

        $new->db = (self::$configure->db instanceof DatabaseInterface) 
            ? self::$configure->db 
            : self::database();

        $new->free();

        return $new;
    }

    /**
     * Generate an object id once if not already generated.
     *
     * @return string
     * @see self::getObjectId()
     */
    private function createObjectId(): string
    {
        $objectId = sprintf(
            'i%s%s%s',
            ($this->db instanceof DatabaseInterface) 
                ? spl_object_id($this->db) 
                : 0,
            spl_object_id($this),
            bin2hex(random_bytes(3))
        );

        if($this->isBuilderDebugging()){
            $this->debugger->setObjectId($objectId);
        }

        return $objectId;
    }

    /**
     * Reset object state to allow reuse for different query.
     * 
     * @return void
     * @see self::reset()
     */
    private function resetState(): void 
    {
        $this->debugMode = 0;
        $this->isStrictMode = true;
        $this->isSafeMode = false;
        $this->returns = null;

        $this->unions = [];
        $this->selector = [];
        $this->limiting = [];
        $this->distances = [];
        $this->cacheInfo = [];
        $this->tableJoin = [];
        $this->conditions = [];
        $this->joinConditions = [];
        $this->querySetValues = [];

        $this->sqlQuery = '';
        $this->cteQuery = null;

        $this->isReplace = false;
        $this->isDistinct = false;
        $this->isCacheReady = true;
        $this->isCollectMetadata = false;
        $this->isIgnoreDuplicate = false;

        $this->cache = null;
        $this->cacheKey = null;
        $this->lastInsertId = null;
        $this->cacheKeyValue = null;

        $this->resetOptions();
    }

    /**
     * Reset the builder options to their default state.
     * 
     * @return void 
     */
    private function resetOptions(): void 
    {
        $this->options = [
            'grouping'      => [],
            'bindings'      => [],
            'ordering'      => [],
            'having'        => [],
            'match'         => [],
            'matches'       => [],
            'where'         => [],
            'duplicate'     => [],
            'unionColumns'  => [],
            'metadata'      => [
                'sql'       => '', 
                'params'    => [], 
                'columns'   => [], 
                'cache'     => []
            ]
        ];
    }

    /**
     * Start or create nested transaction.
     * 
     * @return array{0:bool,1:?string} Returns an array with two elements:
     */
    private function withTransaction(): array
    {
        try{
            if(!$this->inTransaction()){
                return [$this->transaction(), null];
            }
        
            $savepoint = sprintf(
                's%s%s', 
                $this->getObjectId(), 
                bin2hex(random_bytes(4))
            );

            if($this->savepoint($savepoint)){
                return [true, $savepoint];
            }
        }catch(Throwable){}

        return [false, null];
    }

    /**
     * Finalize safe mode transaction.
     *
     * @param bool $useTransaction If the query uses transaction.
     * @param mixed $result The result of the executed query.
     * @param string|null $savepoint Optional savepoint name.
     * 
     * @return mixed Return query result if no transaction or commitid otherwise 0.
     */
    private function finishTransaction(bool $useTransaction, mixed $result, ?string $savepoint = null): mixed 
    {
        if(!$useTransaction){
            return $result;
        }

        if(!$this->db->inTransaction()){
            return $result;
        }

        if($result === 0 || $result === null){
            $this->rollback(name: $savepoint);
            return 0;
        }

        if($this->commit()){
            return $result;
        }

        return 0;
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
     * @throws \Luminova\Exceptions\InvalidArgumentException If values is not provided.
     * @throws \Luminova\Exceptions\JsonException If an error occurs while encoding values.
     *
     * @example - Example:
     * ```php
     * Builder::table('languages')
     *     ->select()
     *     ->whereinArray('post_tags', 'NOT', ['php', 'sql'])
     *     ->get();
     * // Generates: `NOT IN(...)`
     * ```
     */
    private function whereinArray(
        string $column,
        string $expression,
        Closure|array $values,
        string $connector = 'AND'
    ): self
    {
        return $this->whereClause(
            $connector, 
            $column, 
            self::toInArrayOperator($expression), 
            $values, 
            self::INARRAY
        );
    }

    /**
     * Normalizes an operator for array-based comparisons.
     *
     * Converts comparison operators to their equivalent SQL array operators. In
     * strict mode, only the explicit SQL operators `IN` and `NOT IN` are accepted.
     * Otherwise, equality operators are normalized to `IN` and inequality
     * operators to `NOT IN`.
     *
     * @param string $expression The operator to normalize.
     * @param bool $strict Whether to require explicit SQL array operators.
     *
     * @return string Returns either `IN` or `NOT IN`.
     *
     * @throws InvalidArgumentException If strict mode is enabled and the operator
     *                                  is not `IN` or `NOT IN`.
     *
     * @group QUERY_UTIL
     */
    private static function toInArrayOperator(
        string $expression,
        bool $strict = false
    ): string
    {
        $expression = strtoupper(trim($expression));
        $operator = match ($expression) {
            'IN'        => 'IN',
            'NOT IN'    => 'NOT IN',
            '=', '=='   => $strict ? null : 'IN',
            'NOT', '!=', '<>', '!' => $strict ? null : 'NOT IN',
            default     => str_contains($expression, 'NOT')
                ? 'NOT IN'
                : 'IN',
        };

        if($operator === null){
            throw new InvalidArgumentException(
                "Invalid array operator '{$expression}'. Expected 'IN' or 'NOT IN'."
            );
        }

        return $operator;
    }

    /**
     * Determines whether an operator is valid for array-based comparisons.
     *
     * In non-strict mode, equality and inequality operators are treated as array
     * operators and may be normalized to `IN` or `NOT IN`. In strict mode, only
     * the explicit SQL operators `IN` and `NOT IN` are accepted.
     *
     * @param string $expression The operator to validate.
     * @param bool $strict Whether to require explicit SQL array operators.
     *
     * @return bool Returns true if the operator supports array comparisons,
     *              otherwise false.
     *
     * @group QUERY_UTIL
     */
    private static function isArrayOperator(string $expression, bool $strict = false): bool
    {
        return match (strtoupper(trim($expression))) {
            'IN'        => true, 
            'NOT'       => true,
            'NOT IN'    => true,
            '=', 
            '==',
            '!=', 
            '<>', 
            '!' => !$strict,
            default => false,
        };
    }

    /**
     * Add on conditions.
     *
     * @param Expression|string $sql SQL query expression
     * @param string $fn calling method name.
     * @param string $connector Base connector.
     * 
     * @return self
     */
    private function onClause(Expression|string $sql, string $fn, string $connector = 'AND'): self
    {
        $sql = trim(($sql instanceof Expression) ? $sql->toString() : $sql);

        if($sql === ''){
            return $this;
        }

        $connector = $this->parseConnector($connector, $fn);
        // [$connector, $sql] = $this->resolveConnector($connector, $sql);

        $this->joinConditions[array_key_last($this->tableJoin)][] = [
            'connector' => $connector,
            'sql'       => $sql
        ];

        return $this;
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
            && $this->debugMode === self::DEBUG_NONE;
    }

    /**
     * Store a query result in cache when it meets cache eligibility requirements.
     *
     * Skips empty results, database resources, unsafe query states, and
     * non-cacheable responses.
     *
     * @param mixed $result Query result to cache.
     *
     * @return void
     */
    private function storeCacheResult(mixed $result): void
    {
        if (
            !$result
            || $result === []
            || $result === (object)[]
            || ($result instanceof DatabaseInterface)
            || ($result instanceof PDOStatement)
            || ($result instanceof mysqli_result)
        ) {
            return;
        }

        if (
            !$this->isCacheable() 
            || $this->inSafeMode() 
            || (is_object($result) && count(get_object_vars($result)) === 0)
        ) {
            return;
        }

        if($this->cache->set($this->cacheKey, $result)){
            $this->cacheInfo['stored'] = true;
            $this->cacheInfo['hit'] = false;
        }
    }

    /**
     * Determines whether the current query context allows result caching.
     *
     * @return bool Return true if caching is allowed, false otherwise.
     */
    private function isCacheable(): bool
    {
        return (
            $this->isCacheReady
            && !$this->isCollectMetadata
            && $this->debugMode === self::DEBUG_NONE
            && $this->returns !== self::RETURN_STATEMENT
            && ($this->cache instanceof Cache)
        );
    }

    /**
     * Retrieves the most recent set of match columns defined by `match()`.
     *
     * @param string $fn The calling method method `whereAgainst()` or `orderAgainst()`.
     *
     * @return string Return a comma-separated list of column names to be used in MATCH().
     * @throws DatabaseException If no match columns have been defined or the format is invalid.
     */
    private function getMatchColumns(string $fn): string
    {
        $matches = $this->getOptions('matches');

        if($matches === []){
            throw new DatabaseException(
                sprintf(
                    'No match columns defined. Use %s before calling $query->%s(...).', 
                    '$query->match([...])',
                    $fn
                ),
                ErrorCode::LOGIC_ERROR
            );
        }

        $columns = $matches[array_key_last($matches)]['columns'] ?? null;

        if($columns === null || $columns === ''){
            throw new DatabaseException(
                'Invalid or missing match columns. 
                Expected non-empty array of column names.',
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
        if (
            $mode === RETURN_STMT 
            || !$this->isCacheable() 
            || !$this->hasCache()
        ) {
            return null;
        }

        $response = $this->cache->getItem($this->cacheKey);

        if ($response === null) {
            return null;
        }

        $this->cacheInfo['hit'] = true;
        $this->cacheInfo['stored'] = false;

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
     * @param string|null $savepoint
     *
     * @throws Throwable If $throwNow is true.
     */
    private function resolveException(
        Throwable $e, 
        bool $throwInstant = false,
        ?string $savepoint = null
    ): void  
    {
        if ($this->inTransaction()) {
            $this->rollback(name: $savepoint);
        }

        if(
            $throwInstant 
            || (!PRODUCTION && !STAGING) 
            || $e->getCode() === ErrorCode::TERMINATED
        ){
            throw $e;
        }

        if($e instanceof ExceptionInterface){
            $e->handle();
            return;
        }

        DatabaseException::handleException(
            $e->getMessage(), 
            $e->getCode(), 
            $e
        );
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
    private function finishInsert(bool $useTransaction, mixed $result, ?string $savepoint): int 
    {
        $result = (int) $this->finishTransaction($useTransaction, $result, $savepoint);
        
        $this->lastInsertId = ($result > 0) 
            ? $this->db->getLastInsertId() 
            : null;
   
        return $result;
    }

    /**
     * Undocumented function
     *
     * @param string $function
     * @return self
     * @throws RuntimeException If instance is not multiple select.
     * 
     * @see self::cursor()
     * @see self::next()
     * @see self::scan()
     */
    private function multipleSelector(string $function): self 
    {
        if(!isset($this->selector['method'])){
            $this->select(['*']);
        }

        if (($this->selector['method'] ?? null) !== 'select') {
            throw new RuntimeException(sprintf(
                'Call to %s() requires a select instance that returns rows. Use %s instead.',
                $function,
                '->select(...)'
            ));
        }

        return $this;
    }

    /**
     * Build and execute the query, then return the hydrated result.
     *
     * Handles normal queries and union queries, delegating SQL generation and
     * execution to the appropriate builder method.
     *
     * @param int $fetchAs Result hydration mode (e.g., `FETCH_OBJ`, `FETCH_ASSOC`).
     * @param int|null $returnMode Result collection mode:
     *                             - `RETURN_ALL` Fetch all rows.
     *                             - `RETURN_NEXT` Fetch one row.
     *                             - `RETURN_STREAM` Stream rows.
     * @param string|null $method Optional execution method override.
     *
     * @return mixed The executed query result.
     */
    private function result(
        int $fetchAs = FETCH_OBJ,
        ?int $returnMode = null,
        ?string $method = null
    ): mixed 
    {
        $returnMode ??= RETURN_ALL;

        if (!$this->isCollectMetadata && $this->unions !== []) {
            return $this->buildExecutableUnionQuery(
                $returnMode,
                $fetchAs,
                $method
            );
        }

        return $this->buildExecutableStatement(
            $this->selector['sql'] ?? '',
            $method,
            $this->selector['columns'] ?? ['*'],
            $returnMode,
            $fetchAs
        );
    }

    /**
     * Internal scanner implementation for both offset and keyset pagination.
     *
     * This method handles the core scanning logic used by:
     * 
     * - self::scan()           → keyset/cursor-based pagination
     * - self::offsetScan()     → offset-based pagination
     *
     * It determines the scanning strategy based on whether a column is provided.
     *
     * Behavior:
     * - Offset mode: cursor is treated as a numeric offset.
     * - Keyset mode: cursor is treated as the last seen column value.
     *
     * The cursor is updated after each call:
     * - Offset mode: incremented by number of returned rows.
     * - Keyset mode: set to last row's column value.
     *
     * @param int $cursor Reference cursor used for pagination state.
     * @param int $limit Number of rows to fetch per iteration.
     * @param string|null $column Column used for keyset pagination (null = offset mode).
     * 
     * @return array|null Returns result set or null if no more records exist.
     * @throws RuntimeException If called in unsupported builder object.
     */
    private function scanner(int &$cursor, int $limit = 100, ?string $column = null): ?array
    {
        $query = clone $this;

        $offset = 0;
        $cursor = (int) $cursor;
        $limit = max(2, $limit);
        $isKeySet = ($column !== null && $column !== '');

        if(!$isKeySet){
           $offset = max(0, $cursor);
        }else{
            $column = trim($column);

            if($column === ''){
                throw new RuntimeException(sprintf(
                    'Invalid keyset scan column: "%s". Column name cannot be empty.',
                    $column
                ));
            }

            if ($cursor > 0) {
                $query->where($column, '>', $cursor);
            }

            $query->order($column);
        }

        $result = $query
            ->returns(self::RETURN_ARRAY)
            ->limit($limit, $offset)
            ->get();

        if (!$result) {
            $cursor = 0;
            return null;
        }

        $count = count($result);

        if ($count < $limit) {
            $cursor = 0;
        } elseif ($isKeySet) {
            $last = end($result);
            $position = $last[$column] 
                ?? $last[self::toColumnName($column)] 
                ?? null;

            if ($position === null) {
                throw new RuntimeException(sprintf(
                    'Keyset scan failed: column "%s" not found in result set.',
                    $column
                ));
            }

            if (!is_numeric($position)) {
                throw new RuntimeException(sprintf(
                    'Keyset scan requires a numeric cursor for column "%s", "%s" given.',
                    $column,
                    gettype($position)
                ));
            }

            $cursor = (int) $position;
        } else{
            $cursor += $count;
        }

        return $result;
    }

    /**
     * Add a `BETWEEN` condition to the query.
     *
     * Adds a SQL `BETWEEN` or `NOT BETWEEN` clause. Multiple ranges are joined
     * using the specified group connector.
     *
     * @param string $column Column name.
     * @param array $values Range boundaries. Must contain an even number of values.
     * @param string $connector Logical connector with previous conditions.
     * @param string $groupConnector Connector between multiple ranges.
     * @param bool $isWhereNot Use `NOT BETWEEN` when true.
     *
     * @return self
     *
     * @throws DatabaseException If values are invalid.
     */
    private function whereBetweenConditions(
        string $column,
        array $values,
        string $connector = 'AND',
        string $groupConnector = 'OR',
        bool $isWhereNot = false
    ): self
    {
        $count = count($values);
        $operator = $isWhereNot ? 'NOT BETWEEN' : 'BETWEEN';

        if ($count < 2) {
            throw new DatabaseException(
                "{$operator} requires at least two values for column {$column}.",
                ErrorCode::VALUE_FORBIDDEN
            );
        }

        if (($count & 1) !== 0) {
            throw new DatabaseException(
                "Odd number of values passed to {$operator} for column {$column}; " .
                "last value should be removed.",
                ErrorCode::USER_WARNING
            );
        }

        [, $groupConnector,] = self::parseConnectors(
            $isWhereNot ? 'whereNotBetween' : 'whereBetween', 
            groupConnector: $groupConnector
        );

        $segments = [];
        $placeholder = self::toNamedParameter(
            "{$column}_btw",
            $this->getObjectId()
        );

        for ($i = 0; $i < $count; $i += 2) {
            $left = "{$placeholder}_{$i}_a";
            $right = "{$placeholder}_{$i}_b";

            $segments[] = "({$column} {$operator} {$left} AND {$right})";

            $this->bind($left, $values[$i])
                ->bind($right, $values[$i + 1]);
        }

        return $this->whereRawCondition(
            (count($segments) > 1)
                ? '(' . implode(" {$groupConnector} ", $segments) . ')'
                : $segments[0], 
            $connector,
            $isWhereNot ? 'whereNotBetween' : 'whereBetween'
        );
    }

    /**
     * Builds and appends search conditions for configured match columns.
     *
     * @param array<int,string> $keywords The keywords to search for.
     * @param array<int,string> $matches The columns to search against.
     * @param bool $caseSensitive Whether matching should consider character case.
     * @param string $pattern The search pattern used to generate keyword matching.
     * @param string|null $collation The SQL collation to apply when comparing search values.
     *
     * @return self Returns the builder instance.
     *
     * @see self::match()
     * @see self::search()
     */
    private function whereSearchConditions(
        string $connector,
        array $keywords,
        array $matches,
        bool $caseSensitive,
        string $pattern,
        ?string $collation = null
    ): self 
    {
        $conditions = [];
        $counter = 0;
        $isRegex = $pattern === self::SEARCH_REGEX;
        $objectId = $this->getObjectId();

        $match = $isRegex
            ? ($this->isMySql8()
                ? '(^|[^\p{L}\p{N}_])%s([^\p{L}\p{N}_]|$)'
                : '(^|[^[:alnum:]_])%s([^[:alnum:]_]|$)')
            : null;

        $collation = ($collation !== null)
            ? self::parseCollation($collation)
            : ($caseSensitive
                ? 'COLLATE utf8mb4_bin '
                : 'COLLATE utf8mb4_unicode_ci ');

        $binary = ($isRegex && $caseSensitive && $collation === '') ? 'BINARY ' : ')';

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if (!$caseSensitive) {
                $keyword = strtolower($keyword);
            }

            $value = $isRegex
                ? sprintf($match, preg_quote($keyword, '/'))
                : str_replace('{keyword}', addcslashes($keyword, '%_'), $pattern);

            foreach ($matches as $column) {
                $placeholder = ":keyword{$counter}{$objectId}";
                $this->options['bindings'][$placeholder] = $value;

                $conditions[] = $isRegex
                    ? "{$column} {$collation}REGEXP {$binary}{$placeholder}"
                    : "{$column} {$collation}LIKE {$placeholder}";

                $counter++;
            }
        }

        if ($conditions === []) {
            return $this;
        }

        return $this->whereRawCondition(
            '(' . implode(' OR ', $conditions) . ')',
            $connector,
            'whereSearch'
        );
    }

    /**
     * Splits a search keyword string into individual terms.
     *
     * The keyword string is separated by whitespace, merged with any additional
     * keywords, trimmed, and filtered to remove empty values. Duplicate keywords
     * are removed while preserving the original order.
     *
     * @param string $keyword The keyword string to split into individual terms.
     * @param array<int,string> $merge Additional keywords to merge with the split
     *                              keyword list.
     *
     * @return array<int,string> A list of unique, normalized keywords.
     */
    private function splitKeywords(string $keyword, array $merge = []): array
    {
        $keywords = preg_split('/\s+/', $keyword);

        if ($keywords === false || $keywords === []) {
            return [];
        }

        $keywords = array_merge(
            $merge,
            $keywords
        );

        $normalized = [];

        foreach ($keywords as $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $normalized[$value] = true;
        }

        return array_keys($normalized);
    }

    /**
     * Normalizes a SQL collation value for use in a query.
     *
     * @param string|null $collation The collation name or complete COLLATE expression.
     *
     * @return string The normalized collation SQL fragment.
     */
    private static function parseCollation(?string $collation): string 
    {
        $collation = trim((string) $collation);

        if ($collation === '') {
            return '';
        }

        return preg_match('/^\s*COLLATE\b/i', $collation)
            ? "{$collation} "
            : "COLLATE {$collation} ";
    }
   
    /**
     * Checks whether the connected database server is MySQL 8.0 or newer.
     *
     * @return bool Returns true when the server is MySQL 8.0 or newer.
     */
    private function isMySql8(): bool
    {
        $version = $this->db->getVersion();

        if ($version === null) {
            return false;
        }

        // MariaDB is not MySQL 8 compatible.
        if (stripos($version, 'mariadb') !== false) {
            return false;
        }

        return version_compare($version, '8.0.0', '>=');
    }

    /**
     * Ensures a query has a filtering condition before executing a statement.
     *
     * In strict mode, update and delete operations require a WHERE condition to
     * prevent unintended changes to all records. Metadata collection and injected
     * base conditions bypass this validation.
     *
     * Select queries may bypass the check when HAVING conditions are present because
     * they provide a filtering clause at the result level.
     *
     * @param string $function The calling method name.
     * @param bool $isRequired Whether to enforce the check regardless of strict mode.
     * @param bool $allowHaving Whether HAVING conditions are allowed as a valid filter.
     *
     * @return void
     *
     * @throws DatabaseException If no valid filtering condition exists while the
     *                           condition requirement is enforced.
     */
    private function assertStrictModeCondition(
        string $function,
        bool $isRequired = false,
        bool $allowHaving = true
    ): void
    {
        if ($this->isCollectMetadata || $this->hasInjectedBaseWhere) {
            return;
        }

        if (!$isRequired && !$this->isStrictMode) {
            return;
        }

        if (
            $allowHaving
            && ($this->options['having'] ?? []) !== []
        ) {
            return;
        }

         if (
            !$allowHaving
            && ($this->conditions !== [] || ($this->options['where'] ?? []) !== [])
        ) {
            return;
        }

        throw new DatabaseException(
            sprintf(
                '%s requires a WHERE clause when strict mode is enabled.',
                $function
            ),
            ErrorCode::VALUE_FORBIDDEN
        );
    }

    /**
     * Ensure the builder contains a valid query selector.
     *
     * Some operations require a SELECT target before execution. This validation
     * prevents executing query handlers without selected columns, aggregate
     * selectors, or union queries.
     *
     * @param string $method Method name attempting to execute.
     *
     * @return void
     *
     * @throws DatabaseException If no valid selector exists.
     */
    private function assertQuerySelector(string $method): void
    {
        if (
            $this->isCollectMetadata
            || $this->selector !== []
            || $this->unions !== []
        ) {
            return;
        }

        throw new DatabaseException(
            sprintf(
                'Cannot call "%s()" without a valid query selector. 
                Use select(), find(), count(), or another selector method first.',
                $method
            ),
            ErrorCode::BAD_METHOD_CALL
        );
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
            throw new InvalidArgumentException(
                'Column name must be a non-empty string for ordering.'
            );
        }
    }

    /**
     * Assert SQL query.
     * 
     * @param string $query The query string to check.
     * @param string $function The method that is called.
     * 
     * @return void
     * @throws DatabaseException If failed.
     */
    private static function assertQuery(string $query, string $function): void 
    {
        if (trim($query) === '') {
            throw new InvalidArgumentException(sprintf(
                'Invalid: %s($query) requires a non-empty SQL query string.', 
                $function
            ));
        }
    }

    /**
     * Validate values before executing an INSERT or UPDATE query.
     *
     * Ensures values are provided and that UPDATE operations receive an
     * associative array (`column => value`) rather than an indexed array.
     *
     * @param array $values The values to validate.
     * @param bool $isInsert Whether the validation is for an INSERT operation
     *                       (default: `true`).
     *
     * @return void
     *
     * @throws DatabaseException If no values are provided or the values are
     *                           invalid for the operation.
     */
    private function assertInsertOrUpdateValues(array $values, bool $isInsert = true): void
    {
        if ($values === []) {
            $ctx = $isInsert ? 'insert' : 'update';

            throw new DatabaseException(
                sprintf(
                    'No values specified for %s on table "%s". 
                    Use Builder::set() or pass values directly to %s([...]).',
                    strtoupper($ctx),
                    $this->tableName,
                    $ctx
                ),
                ErrorCode::VALUE_FORBIDDEN
            );
        }

        if ($isInsert) {
            if (!is_associative($values[0])) {
                throw new DatabaseException(
                    sprintf(
                        'Invalid %s values: each row must be an associative array.', 
                        $this->isReplace ? 'REPLACE' : 'INSERT'
                    ), 
                    ErrorCode::VALUE_FORBIDDEN
                );
            }

            return;
        }

        if (array_is_list($values)) {
            throw new DatabaseException(
                'UPDATE values must be provided as an associative array (e.g, [column => value]).',
                ErrorCode::VALUE_FORBIDDEN
            );
        }
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
        if (!$this->isReplace && !$this->isIgnoreDuplicate) {
            return;
        }

        if ($this->isIgnoreDuplicate && $this->isReplace) {
            throw new DatabaseException(
                'Cannot combine "Builder::replace(true)" and "Builder::ignoreDuplicate(true)". 
                These insert modes are mutually exclusive.',
                ErrorCode::LOGIC_ERROR
            );
        }

        // on duplicate options
        if($this->getOptions('duplicate') === []){
            return;
        }

        if ($this->isIgnoreDuplicate) {
            throw new DatabaseException(
                'Cannot combine "Builder::ignoreDuplicate(true)" with "Builder::onDuplicate(...)". 
                These duplicate handling options are mutually exclusive.',
                ErrorCode::LOGIC_ERROR
            );
        }

        if ($this->isReplace) {
            throw new DatabaseException(
                'Cannot combine "Builder::replace(true)" with "Builder::onDuplicate(...)". 
                REPLACE already handles duplicate keys by replacing existing rows.',
                ErrorCode::LOGIC_ERROR
            );
        }
    }

    /**
     * Validates a database table name.
     *
     * Accepts unquoted names or names enclosed in backticks. Valid characters
     * include letters, numbers, underscores (`_`), hyphens (`-`), and dots (`.`).
     *
     * @param string|null $table The table name to validate.
     *
     * @throws InvalidArgumentException If the table name is invalid.
     */
    private static function assertTableName(?string $table): void
    {
        if ($table === null) {
            return;
        }

        if (trim($table, '`') === '') {
            throw new InvalidArgumentException(
                'Table name cannot be empty.'
            );
        }

        if (!preg_match('/^`?[A-Za-z_][A-Za-z0-9_.-]+`?$/u', $table)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid table name "%s". It must start with a letter or underscore and contain only letters, numbers, underscores (_), hyphens (-), or dots (.). Backticks are optional.',
                $table
            ));
        }
    }

    /**
     * Validates a database table alias.
     *
     * Accepts unquoted aliases or aliases enclosed in backticks. Aliases must
     * start with a letter or underscore and may contain only letters, numbers,
     * and underscores.
     *
     * @param string|null $alias The table alias to validate.
     *
     * @throws InvalidArgumentException If the alias is invalid.
     */
    private static function assertTableAlias(?string $alias): void
    {
        if ($alias === null) {
            return;
        }

        if (trim($alias, '`') === '') {
            throw new InvalidArgumentException(
                'Table alias cannot be empty.'
            );
        }

        if (!preg_match('/^`?[A-Za-z_][A-Za-z0-9_]*`?$/u', $alias)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid SQL alias: "%s".',
                $alias
            ));
        }
    }

    /**
     * Create query and execute it.
     * 
     * @param string $query The base SQL query string to execute.
     * @param string $method The execution method called 
     *          (expected: `total`, `stmt`, `select`, `find`, `delete`, `fetch`).
     * @param array $columns For select and find methods, the column names to return.
     * @param int $returns The fetch result return mode (`RETURN_ALL` or `RETURN_NEXT`).
     * @param int $fetch The database result fetch mode for retrieval (e.g., `FETCH_OBJ`, `FETCH_*`).
     * 
     * @return mixed Return the execution result, value varies based on the `$method` and `$mode` parameter.
     * @throws DatabaseException If an error occurs during query execution or result fetching.
     */
    private function buildExecutableStatement(
        string $query, 
        string $method = 'aggregate', 
        array $columns = ['*'], 
        int $returns = RETURN_ALL, 
        int $fetch = FETCH_OBJ
    ): mixed
    {
        $top = $this->limiting['top'] ?? '';
        $sql = $this->startQueryWith();

        if(!$this->isCteFinalQuery){
            $sql .= $this->isDistinct ? "SELECT {$top}DISTINCT" : "SELECT {$top}";
        }

        $sql .= $query;

        if($query === '' || in_array($method, ['select', 'find', 'stmt', 'fetch'], true)){
            $sql .= ($columns === ['*']) ? ' *' : ' ' . implode(', ', $columns);
        }
        
        $sql .= " FROM {$this->tableName}";
        $sql .= $this->tableAlias ? " AS {$this->tableAlias}" : '';

        if($this->lock && isset(self::WITH_DRIVER_LOCK[$this->db->getDriver()])){
            $sql .= " {$this->lock}";
        }
     
        $sql .= $this->getJoinConditions();

        if($this->isCollectMetadata){
            $this->options['metadata']['columns'] = $columns;
        }

        try {
            $response = $this->getStatementExecutionResult(
                $sql, 
                $method, 
                $returns, 
                $fetch
            );

            if($returns === RETURN_STMT){
                return $response;
            }

            $this->storeCacheResult($response);
            return $response;
        } catch (Throwable $e) {
            $this->resolveException($e);
        }
        
        return false;
    }

    /**
     * Executes an SQL statement and returns the result based on the specified method.
     *
     * Supported methods:
     *  - stmt       Return prepared statement.
     *  - select     Return all matching rows.
     *  - find       Return the first matching row.
     *  - aggregate  Return aggregate value.
     *  - exists     Check whether any matching row exists.
     *  - count      Return affected row count.
     *  - delete     Return deleted row count.
     *  - default    Return fetched result using the configured return mode.
     *
     * @param string $sql The SQL statement.
     * @param string $method Execution method.
     * @param int $return Fetch return type.
     * @param int $mode Fetch mode.
     *
     * @return mixed
     * @throws DatabaseException
     */
    private function getStatementExecutionResult(
        string $sql,
        string $method = 'aggregate',
        int $return = RETURN_ALL,
        int $mode = FETCH_OBJ
    ): mixed
    {
        $isDelete = ($method === 'delete');
        $isNext = ($method === 'find');
        $isDebugging = $this->isBuilderDebugging();

        if ($this->conditions !== []) {
            $this->buildConditions($sql);
        }

        $this->injectRawWhereQuery($sql);

        if ($isDelete) {
            if (($ordering = $this->getOptions('ordering')) !== []) {
                $sql .= ' ORDER BY ' . implode(', ', $ordering);
            }
        } else {
            [$query, $isOrdered] = $this->resolveQueryFilters();
            $sql .= $query;

            $this->setMatchAgainst($sql, $isOrdered);
        }

        $offset = $this->limiting['offset'] ?? 0;
        $limit = $isNext ? 1 : ($this->limiting['limit'] ?? 0);

        if ($limit > 0) {
            if ($isDelete || $isNext) {
                $sql .= " LIMIT {$limit}";
            } else {
                $sql .= " LIMIT {$offset},{$limit}";
            }
        }

        if ($method === 'exists') {
            $sql = "SELECT EXISTS ({$sql}) AS hasRecord";
        }

        if ($this->lock && !isset(self::WITH_DRIVER_LOCK[$this->db->getDriver()])) {
            $sql .= " {$this->lock}";
        }

        if ($isDebugging && $this->addDebug($sql, $method)) {
            return 0;
        }

        if ($this->isCollectMetadata) {
            $this->options['metadata']['sql'] = $sql;
        }

        $isExecutable = !$this->isCollectMetadata && !$isDebugging;

        $savepoint = null;
        $useTransaction = false;

        if ($isExecutable && $isDelete && $this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        $hasBindings = (
            $this->conditions !== []
            || $this->getOptions('match') !== []
            || $this->getOptions('bindings') !== []
        );

        if ($hasBindings) {
            if ($isExecutable) {
                $this->db->prepare($sql);
            }

            $bindResult = $this->bindConditions();
            $joinResult = $this->bindJoinPlaceholders();

            $hasBindings = $bindResult || $joinResult;
        }

        if (!$isExecutable) {
            return $this->isCollectMetadata;
        }

        if($function = ($this->options['assert'] ?? null) !== null){
            unset($this->options['assert']);

            $this->assertStrictModeCondition(
                $function ?? $method, 
                isRequired: $isNext,
                allowHaving: !$isDelete
            );
        }

        $hasBindings
            ? $this->db->execute()
            : $this->db->query($sql);

        return $this->resolveResult(
            $return,
            $mode,
            $useTransaction, 
            $savepoint,
            $method
        );
    }

    /**
     * Return raw SQL query builder execution result.
     * 
     * @param array<string,mixed> $placeholder Placeholder values to bind to the query.
     * @param int $returnMode The return result type mode.
     * @param int $fetchAs The return result type mode.
     * @param bool $escapePlaceholders Whether to validate and escape placeholders.
     * 
     * @return mixed Return query result, prepared statement object, 
     *      otherwise false on failure.
     * 
     * @throws DatabaseException If placeholder key is not a string.
     */
    private function runExecutableQueryResult(
        ?array $placeholder = null, 
        int $returnMode = RETURN_ALL,
        int $fetchAs = FETCH_OBJ,
        bool $escapePlaceholders = false,
        ?string $method = null
    ): mixed
    {
        if($this->sqlQuery === ''){
            return false;
        }
        
        $isDebugging = $this->isBuilderDebugging();
        $hasBindings = $placeholder !== [] && $placeholder !== null;

        if($isDebugging){
            $this->addDebug($this->sqlQuery, 'execute', $placeholder);

            if(!$hasBindings || !$escapePlaceholders){
                return false;
            }
        }

        $savepoint = null;
        $useTransaction = false;

        $isCacheable = $method !== 'fetch'
            && $returnMode !== RETURN_STMT 
            && Util::isSqlQuery($this->sqlQuery);

        if($isCacheable){
            $result = $this->getFromCache($returnMode);

            if($result !== null){
                return $result;
            }
        }

        if(!$isDebugging){
            if ($this->inSafeMode()) {
                [$useTransaction, $savepoint] = $this->withTransaction();
            }

            $hasBindings
                ? $this->db->prepare($this->sqlQuery)
                : $this->db->query($this->sqlQuery);
        }

        if($hasBindings){
            if($escapePlaceholders){
                $this->bindColumnPlaceholders($placeholder);
                $placeholder = null;
            }

            if($isDebugging){
                return false;
            }

            $this->db->execute($placeholder);
        }

        $result = $this->resolveResult(
            $returnMode,
            $fetchAs,
            $useTransaction, 
            $savepoint,
            $method
        );

        if($isCacheable && $result){
            $this->storeCacheResult($result);
        }

        return $result;
    }

    /**
     * Resolve and process the query result.
     *
     * Handles transaction completion, custom return types, statement returns,
     * and execution-specific result fetching.
     *
     * @param int $return Result return mode:
     *                    - `RETURN_ALL`
     *                    - `RETURN_NEXT`
     *                    - `RETURN_STMT`
     * @param int $mode Fetch mode (e.g., `FETCH_OBJ`, `FETCH_ASSOC`).
     * @param bool $useTransaction Whether the query is wrapped in a transaction.
     * @param string|null $savepoint Optional transaction savepoint name.
     * @param string|null $method Execution operation name (`select`, `find`, `count`, etc.).
     *
     * @return mixed The processed query result.
     *
     * @throws DatabaseException If result processing fails.
     */
    private function resolveResult(
        int $return = RETURN_ALL,
        int $mode = FETCH_OBJ,
        bool $useTransaction = false,
        ?string $savepoint = null,
        ?string $method = null
    ): mixed 
    {
        if (!$this->finishTransaction($useTransaction, $this->db->ok(), $savepoint)) {
            return false;
        }

        if($method === 'fetch'){
            return true;
        }

        if ($this->returns === self::RETURN_STATEMENT || $return === RETURN_STMT) {
            $this->returns = self::RETURN_STATEMENT;

            return $this->db;
        }

        if (
            $this->returns === self::RETURN_CLASS
            && !in_array($method, ['count', 'delete', 'exists', 'aggregate'], true)
        ) {
            $result = $this->fetchClass(
                ($method === 'find') ? RETURN_NEXT : $return
            );
        } else{
            $fetchMode = $this->getFetchMode($mode);

            $result = match ($method) {
                'stmt'      => $this->db,
                'count'     => $this->db->getCount(),
                'delete'    => $this->db->rowCount(),
                'select'    => $this->db->fetch(RETURN_ALL, $fetchMode),
                'find'      => $this->db->fetch(RETURN_NEXT, $fetchMode),
                'exists'    => (bool) (
                    $this->db->fetchNext()?->hasRecord ?? false
                ),
                'aggregate' => (
                    $this->db->fetchNext()?->aggregate ?? 0
                ),
                default     => $this->db->getResult($return, $fetchMode),
            };
        }

        if(
            $this->returns === self::RETURN_OBJECT_LIST 
            && is_array($result)
        ){
            $result = (object) $result;
        }

        return $this->finishTransaction(
            $useTransaction,
            $result,
            $savepoint
        );
    }

    /**
     * Fetch query results as instances of the configured return class.
     *
     * Resolves the configured class name and constructor arguments from the
     * builder return options, then delegates object hydration to the database
     * driver.
     * 
     * @template T of object
     *
     * @param int $return Fetch mode:
     *                    - `RETURN_NEXT` Fetch a single object.
     *                    - `RETURN_ALL` Fetch all objects.
     *
     * @return T|array<int,T>|null Returns a hydrated object, an array of objects, or null when
     *               class return mode is not configured.
     */
    private function fetchClass(int $return): mixed
    {
        if ($this->returns !== self::RETURN_CLASS) {
            return null;
        }

        $options = $this->getOptions(self::RETURN_CLASS);
        $arguments = $options['arguments'];

        if (is_callable($arguments)) {
            $arguments = $arguments();
        }

        return match($return) {
            RETURN_NEXT => $this->db->fetchObject(
                $options['name'],
                ...$arguments
            ),
            default => $this->db->fetchAllObject(
                $options['name'],
                ...$arguments
            )
        };
    }

    /**
     * Adds a raw SQL condition to the WHERE clause.
     *
     * @param string $sql Raw SQL condition.
     * @param string $connector Default logical connector.
     *
     * @return self
     *
     * @throws InvalidArgumentException If a raw WHERE clause is added after existing conditions.
     */
    private function whereRawCondition(string $sql, string $connector, ?string $fn = null): self
    {
        $connector = self::parseConnector($connector, $fn ?? 'whereRaw', allowed: [
            'WHERE',
            'AND',
            'OR'
        ]);

        [$baseConnector, $sql]= $this->resolveConnector($connector, $sql);

        if ($sql === null || $sql === '') {
            return $this;
        }

        $this->options['where'][] = "{$baseConnector} {$sql}";

        return $this;
    }

    /**
     * Appends raw WHERE fragments into the final SQL string.
     *
     * Normalizes raw conditions and ensures they are appended with a valid
     * WHERE clause or logical connector.
     *
     * @param string $sql The SQL string being built.
     *
     * @return void
     */
    private function injectRawWhereQuery(string &$sql): void 
    {
        $query = $this->getOptions('where');

        if ($query === []) {
            return;
        }

        $query = trim(implode(' ', $query));

        if ($query === '') {
            return;
        }

        $connector = 'AND';

        // Extract query leading connector
        if (preg_match('/^(?:WHERE\s+)?(AND|OR)\b/i', $query, $matches)) {
            $connector = strtoupper($matches[1]);
        }

        // Remove leading WHERE/AND/OR connector keywords.
        $query = preg_replace('/^(?:(?:WHERE|AND|OR)\s+)+/i', '', $query);

        if ($query === '') {
            return;
        }

        // Remove incomplete trailing logical operator from SQL.
        $sql = preg_replace('/\s+(AND|OR)\s*$/i', '', $sql);

        $hasWhere = $this->hasInjectedBaseWhere
            || self::findOuterWhere($sql);

        $this->hasInjectedBaseWhere = true;

        if ($hasWhere) {
            $sql .= " {$connector} {$query}";
            return;
        }

        $sql .= " WHERE {$query}";
    }

    /**
     * Checks if the SQL query has an outer-level WHERE clause.
     *
     * Starts scanning from the first WHERE found and walks backwards from that position to see if the 
     * WHERE occurs inside parentheses (a subquery) or at the outer level.
     *
     * @param string $sql The SQL query string to check.
     *
     * @return bool Returns true if an outer WHERE clause exists, otherwise false.
     */
    private static function findOuterWhere(string $sql): bool
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
            throw new DatabaseException(
                'No UNION queries to compile.', ErrorCode::BAD_METHOD_CALL
            );
        }

        $sqlParts = [];
        $params = [];
        $columns = $this->getOptions('unionColumns');
        
        $offset = $this->limiting['offset'] ?? 0;
        $limit = $this->limiting['limit'] ?? 0;

        $isColumns = $columns !== [];
        $isConditions = $this->conditions !== [];
        $isCompound = $isColumns 
            || $isConditions 
            || $limit > 0;

        $alias = ($this->unionCombineAlias ?: 'un_compound');

        if($isCompound){
            $query = 'SELECT ';
            $query .= $this->isDistinct ? 'DISTINCT ' : '';
            $query .= ($isColumns && $columns !== ['*']) 
                ? implode(', ', $columns) 
                : "{$alias}.*";

            $sqlParts[] = "{$query} FROM (";
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

        $sqlParts[] = trim($this->resolveQueryFilters()[0]);

        if($limit > 0 && ($offset > 0 || $offset instanceof Expression)){
            $sqlParts[] = "LIMIT {$offset},{$limit}";
        }elseif($limit > 0){
            $sqlParts[] = "LIMIT {$limit}";
        }

        return [implode(' ', $sqlParts), $params];
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
        $parent = $this->getOptions('metadata');

        $union->get();
        $child = $union->getOptions('metadata');

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

        $this->isCollectMetadata = false;
        $union->isCollectMetadata = false;

        return $this;
    }

    /**
     * Build the GROUP BY and ORDER BY clauses.
     *
     * @return array{0:string,1:bool,2:bool} The SQL fragment, whether an ORDER BY
     *                                       clause exists, and whether a GROUP BY
     *                                       clause exists.
     */
    private function resolveQueryFilters(): array
    {
        $sql = '';

        $this->injectHavingConditions($sql);

        $grouping = $this->getOptions('grouping');
        $ordering = $this->getOptions('ordering');

        $hasGrouping = $grouping !== [];
        $hasOrdering = $ordering !== [];

        if ($hasGrouping) {
            $sql .= ' GROUP BY ' . implode(', ', $grouping);
        }

        if ($hasOrdering) {
            $sql .= ' ORDER BY ' . implode(', ', $ordering);
        }

        return [$sql, $hasOrdering, $hasGrouping];
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
            self::RETURN_OBJECT_LIST,
            self::RETURN_OBJECT => FETCH_OBJ,
            self::RETURN_ARRAY  => FETCH_ASSOC,
            default => $mode
        };
    }

    /**
     * Build the SQL JOIN clause.
     *
     * Constructs all configured table and subquery joins, including their
     * corresponding `ON` conditions.
     *
     * @return string The generated JOIN clause, or an empty string if no joins exist.
     *
     * @throws InvalidArgumentException If a join alias is used more than once.
     */
    private function getJoinConditions(): string
    {
        if ($this->tableJoin === []) {
            return '';
        }

        $sql = '';
        $aliases = [];

        foreach ($this->tableJoin as $key => $join) {
            $alias = $join['alias'];

            if ($alias !== '' && isset($aliases[$alias])) {
                throw new InvalidArgumentException(
                    "Join alias '{$alias}' is already in use."
                );
            }

            $conditions = $this->joinConditions[$key] ?? [];
            $query = " {$join['type']} JOIN ";

            if ($join['isForSubquery']) {
                if (!isset($conditions[0])) {
                    continue;
                }

                $subquery = trim($conditions[0]['sql']);

                if (!str_starts_with($subquery, '(')) {
                    $subquery = "({$subquery})";
                }

                $query .= "{$subquery} {$join['as']}";

                if (count($conditions) > 1) {
                    $query .= $this->injectOnJoinConditions($key, offset: 1);
                }
            } else {
                $query .= "{$join['table']} {$join['as']}";

                if ($conditions !== []) {
                    $query .= $this->injectOnJoinConditions($key);
                }
            }

            $sql .= str_replace(
                ['{{tableName}}', '{{tableAlias}}'],
                [$join['table'], $alias],
                $query
            );

            if ($alias !== '') {
                $aliases[$alias] = true;
            }
        }

        return $sql;
    }

    /**
     * Build the ON clause for a JOIN.
     *
     * @param string|int $key The join identifier.
     * @param int $offset The condition index to start from.
     *
     * @return string The constructed ON clause.
     */
    private function injectOnJoinConditions(string|int $key, int $offset = 0): string
    {
        $conditions = $this->joinConditions[$key];
        $sql = " ON {$conditions[$offset]['sql']}";

        $count = count($conditions);

        for ($i = $offset + 1; $i < $count; $i++) {
            $condition = $conditions[$i];

            $sql .= " {$condition['connector']} {$condition['sql']}";
        }

        return $sql;
    }

    /**
     * Adds a table join to the current query.
     *
     * @param string|null $table Table name.
     * @param string|null $alias Table alias.
     * @param string|null $type Table join type
     * @param bool $forSubquery For sub table join.
     * 
     * @return self
     */
    private function joinTables(
        ?string $table = null,
        ?string $alias = null,
        ?string $type = null,
        bool $forSubquery = false
    ): self
    {
        $table = ($table === null) ? null : trim($table);
        $alias = ($alias === null) ? null : trim($alias);

        self::assertTableName($table);
        self::assertTableAlias($alias);
        
        $id =  $this->getObjectId();
        $id .= "{$table}_{$alias}";
        $id .= count($this->tableJoin);

        $this->tableJoin[$id] = [
            'type'          => strtoupper($type ?? ''),
            'table'         => (string) $table,
            'alias'         => (string) $alias,
            'as'            => $alias ? "AS {$alias}" : '',
            'isForSubquery' => $forSubquery
        ];

        return $this;
    }

    /**
     * Binds a value to the specified placeholder in the database query.
     *
     * If debug mode is enabled, the placeholder and value are logged once under the 'BIND PARAMS' label.
     *
     * @param string $placeholder The query placeholder (e.g., :id).
     * @param mixed  $value The value to bind.
     * @param array<string,mixed> &$params Params reference.
     *
     * @return self Return current builder instance.
     */
    private function bindValue(string $placeholder, mixed $value, ?array &$params = null): self 
    {
        $placeholder = ltrim($placeholder, ':');

        if ($this->isCollectMetadata){
            $this->options['metadata']['params'][$placeholder] = $value;
            return $this;
        }
        
        if(!$this->isBuilderDebugging()){
            if($params === null){
                $this->db->bind(":{$placeholder}", $value);
                return $this;
            }
            
            $params[$placeholder] = $value;
            return $this;
        }

        if($this->debugMode === self::DEBUG_BUILDER_OUTPUT){
            $value = self::escape($value, false);

            $this->debugger->printLine(
                "{$placeholder} = {$value}", 
                'BIND PARAMS'
            );
        }

        return $this;
    }

    /**
     * Resolves a value or executes a closure and returns its result.
     *
     * If the given value is not a closure, it is returned unchanged. When a closure
     * is provided, it is invoked and its return value is returned. Closures that
     * declare no parameters are invoked without arguments, while closures that
     * accept parameters receive a cloned instance of the current query builder.
     *
     * Any exception thrown by the closure is wrapped in a {@see RuntimeException},
     * except exceptions with the {@see ErrorCode::TERMINATED} error code, which are
     * rethrown unchanged.
     *
     * @param mixed $input The value or closure to resolve.
     *
     * @return mixed The resolved value.
     *
     * @throws RuntimeException If the closure throws an exception.
     * @throws Throwable If the closure throws an exception with the
     *                   {@see ErrorCode::TERMINATED} error code.
     */
    private function getValue(mixed $input): mixed
    {
        if (!$input instanceof Closure) {
            return $input;
        }

        try {
            return (new ReflectionFunction($input))->getNumberOfParameters() === 0
                ? $input()
                : $input(clone $this);
        } catch (Throwable $e) {
            if ($e->getCode() === ErrorCode::TERMINATED) {
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
     * @throws Throwable If an invalid operator.
     *
     * @example - Example:
     * 
     * ```php
     * [$name, $operator, $value] = $this->getFromColumn(Builder::column('foo', '=', 'bar'));
     * 
     * // $name = 'foo'
     * // $operator = '='
     * // $value = 'bar'
     * ```
     */
    private function getFromColumn(array $column, bool $extractRaw = false, ?string $key = null): mixed 
    {
        $key ??= array_key_first($column);
        $value = $this->getValue($column[$key]['value'] ?? null);

        $column = [$key, self::parseOperator($column[$key]['operator'] ?? '=')];

        if($value === null){
            $column[] = 'NULL';

            return $column;
        }

        $column[] = ($extractRaw && $value instanceof Expression) 
            ? $value->toString() 
            : $value;

        return $column;
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

            if ($value instanceof Expression) {
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
     * Check if column name is valid.
     *
     * @param string $value
     * 
     * @return bool
     */
    private static function isValidColumnIdentifier(string $value): bool
    {
        return preg_match(
            '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/',
            $value
        ) === 1;
    }

    /**
     * Asset column identifier.
     *
     * @param string $value
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    private static function assertColumnName(string $value): void
    {
        if (!self::isValidColumnIdentifier($value)) {
            throw new InvalidArgumentException("Invalid column name identifier: \"{$value}\".");
        }
    }

    /**
     * Normalize and validate a logical SQL connector.
     *
     * @param string $connector SQL connector to validate.
     * @param string $fn Calling method name for exception messages.
     * @param string $name Parameter name used in exception messages.
     * @param string[] $allowed Allowed connector values.
     *
     * @return string Normalized uppercase connector.
     *
     * @throws InvalidArgumentException If the connector is invalid.
     */
    private static function parseConnector(
        string $connector,
        ?string $fn,
        string $name = 'connector',
        array $allowed = ['AND', 'OR']
    ): string
    {
        $connector = strtoupper(trim($connector));

        if (!in_array($connector, $allowed, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid connector "%s" for %s($%s). Allowed values: %s.',
                $connector,
                $fn ?? '',
                $name,
                implode(', ', $allowed)
            ));
        }

        return $connector;
    }

    /**
     * Resolves the logical connector for a base query condition.
     *
     * Determines whether a condition should begin with `WHERE` or be joined using
     * `AND` or `OR` based on the current query state. When a SQL fragment is
     * provided, any leading connector (`WHERE`, `AND`, or `OR`) is extracted and
     * used in preference to the connector parameter.
     *
     * The first condition in a query always resolves to `WHERE`. Subsequent
     * conditions cannot introduce another `WHERE` clause and must instead use
     * `AND` or `OR`.
     *
     * @param string $paramConnector The requested logical connector (`WHERE`, `AND`, or `OR`).
     * @param string|null $fromSqlQuery Optional SQL fragment that may begin with a logical connector.
     *
     * @return array{0:string|null,1:string|null} Returns the resolved connector and
     *                                            normalized SQL fragment. Both values
     *                                            are `null` when the SQL fragment
     *                                            contains only a connector.
     *
     * @throws InvalidArgumentException If a subsequent condition attempts to
     *                                  introduce another `WHERE` clause.
     */
    private function resolveConnector(string $paramConnector, ?string $fromSqlQuery = null): array 
    {
        $sqlConnector = null;

        if ($fromSqlQuery !== null && preg_match('/^(WHERE|AND|OR)\b\s*/i', $fromSqlQuery, $matches)) {
            $sqlConnector = strtoupper($matches[1]);
            $fromSqlQuery = trim(substr($fromSqlQuery, strlen($matches[0])));

            if ($fromSqlQuery === '') {
                return [null, null];
            }
        }

        if (!$this->hasBaseWhereConnector) {
            $this->hasBaseWhereConnector = true;
            return ['WHERE', $fromSqlQuery];
        }

        $paramConnector = strtoupper(trim($paramConnector));
        
        if (
            $sqlConnector === 'WHERE'
            || ($fromSqlQuery === null && $paramConnector === 'WHERE')
        ) {
            throw new InvalidArgumentException(
                'WHERE can only be used for the first query condition. Use AND or OR for subsequent conditions.'
            );
        }
        
        if ($sqlConnector === null) {
            return [
                ($paramConnector === 'WHERE') ? 'AND' : $paramConnector,
                $fromSqlQuery
            ];
        }

        return [$sqlConnector, $fromSqlQuery];
    }

    /**
     * Normalize and validate SQL connectors.
     *
     * @param string $fn Calling method name for exception messages.
     * @param string|null $baseConnector Primary connector.
     * @param string|null $groupConnector Group connector.
     * @param string|null $nestedConnector Nested connector.
     *
     * @return array{0:?string,1:?string,2:?string} Normalized connectors.
     *
     * @throws InvalidArgumentException If a connector is invalid.
     */
    private function parseConnectors(
        string $fn,
        ?string $baseConnector = null,
        ?string $groupConnector = null,
        ?string $nestedConnector = null
    ): array
    {
        $connectors = [
            'connector'       => $baseConnector, 
            'groupConnector'  => $groupConnector, 
            'nestedConnector' => $nestedConnector
        ];

        foreach ($connectors as $name => $connector) {
            if ($connector === null) {
                continue;
            }

            $connector = self::parseConnector($connector, $fn, $name);
            $connectors[$name] = ($name === 'connector') 
                ? $this->resolveConnector($connector)[0] 
                : $connector;
        }

        return [
            $connectors['connector'],
            $connectors['groupConnector'],
            $connectors['nestedConnector'],
        ];
    }

    /**
     * Execute insert query.
     * 
     * @param array<int,array<string,mixed>> $values array of values to insert.
     * @param string $type The type of insert (expected: `INSERT` or `REPLACE`).
     * @param int $length Values count.
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws \Luminova\Exceptions\JsonException If an error occurs while encoding values.
     */
    private function executeInsertQuery(array $values, string $type, int $length): int 
    {
        $inserts = '';

        for ($i = 0; $i < $length; $i++) {
            $inserts .= "(" . self::escapeValueList($values[$i]) . "), ";
        }

        $columns = implode(', ', array_keys($values[0]));
        $inserts = rtrim($inserts, ', ');
        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';

        $sql = $this->startQueryWith();

        if(!$this->isCteFinalQuery){
            $sql .= "{$type} {$ignore}INTO {$this->tableName} ({$columns}) VALUES {$inserts}";
        }

        $sql .= $this->buildDuplicateUpdateClause();

        if($this->isBuilderDebugging()){
            $this->addDebug($sql, 'insert');
            return 0;
        }

        return $this->db->query($sql)->ok() 
            ? $this->db->rowCount() 
            : 0;
    }

    /**
     * Execute insert query using prepared statement.
     * 
     * @param array<int,array<string,mixed>> $values array of values to insert.
     * @param string $type The insert type (expected: `INSERT` or `INSERT`).
     * @param int $length Length of values.
     * @param bool $isEscapedValues Whether to escape values option was enabled (default: true).
     * 
     * @return int Return number affected row.
     * @throws DatabaseException If an error occurs.
     * @throws \Luminova\Exceptions\JsonException If an error occurs while encoding values.
     */
    private function executeInsertPrepared(
        array $values, 
        string $type, 
        int $length,
        bool $isEscapedValues = true
    ): int
    {
        $inserted = 0;
        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';
        $isDebugging = $this->isBuilderDebugging();
        $this->lastInsertId = null;

        $replacements = [];
        [$placeholders, $inserts] = self::mapInsertColumns($values[0]);

        $sql = $this->startQueryWith();
  
        if(!$this->isCteFinalQuery){
            $sql .= "{$type} {$ignore}INTO {$this->tableName} ({$inserts}) VALUES ($placeholders)";
        }

        $sql .= $this->buildDuplicateUpdateClause($replacements);
       
        if($isDebugging){
            if($this->addDebug($sql, 'insert', $values)){
                return 0;
            }
        }else{
            $this->db->prepare($sql);
        }

        for ($i = 0; $i < $length; $i++) {
            if($isEscapedValues || $isDebugging){
                $this->bindColumnPlaceholders($values[$i], $replacements);
            }

            if($isDebugging){
                continue;
            }

            if($this->db->execute($isEscapedValues ? null : array_merge($values[$i], $replacements))){
                $inserted++;
            }
        }

        return $inserted;
    }

    /**
     * Binds named placeholder values and replacement parameters to the prepared statement.
     *
     * Skips SQL expressions because they are inserted directly into the query.
     * Validates column names to prevent invalid placeholder usage, then binds each
     * column value using generated placeholders. Optional replacement values are
     * bound separately using named placeholders.
     *
     * @param array<string,mixed> $columns The column names and values to bind.
     * @param array<string,mixed> $replacements Optional insert placeholder replacement values.
     * @param string|null $objectId Optional object identifier used when generating column placeholders.
     *
     * @return void
     *
     * @throws DatabaseException If an invalid column placeholder is provided.
     */
    private function bindColumnPlaceholders(
        array $columns,
        array $replacements = [],
        ?string $objectId = null
    ): void
    {
        foreach ($columns as $column => $value) {
            if ($value instanceof Expression) {
                continue;
            }

            if (
                $column === '?'
                || is_int($column)
                || str_starts_with($column, ':')
            ) {
                throw new DatabaseException(
                    sprintf(
                        "Invalid column placeholder '%s'. Use a valid column name without positional ('?') or named (':') placeholder prefixes.",
                        $column
                    ),
                    ErrorCode::VALUE_FORBIDDEN
                );
            }

            $this->bindValue(
                self::toNamedParameter($column, $objectId),
                self::escape($value, strict: true)
            );
        }

        foreach ($replacements as $placeholder => $replace) {
            $this->bindValue(
                ":{$placeholder}",
                $replace
            );
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

        if ($addWhere && !$this->hasInjectedBaseWhere) {
            $this->hasInjectedBaseWhere = true;
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
                    $condition['groupConnector'],
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
     * Binds query condition values to prepared statement parameters.
     *
     * Processes all configured query conditions and binds their associated values
     * based on the condition mode. Handles grouped conditions, nested conditions,
     * array-based conditions, full-text matching parameters, and regular column
     * comparisons.
     *
     * Conditions containing raw expressions are skipped because they do not require
     * bound parameters. Optional parameter collection can be provided for queries
     * that reuse bindings, such as union queries.
     *
     * @param array|null $params Optional parameter collection to append bindings to.
     *
     * @return bool Returns true if one or more values were bound, otherwise false.
     */
    private function bindConditions(?array &$params = null): bool 
    {
        $totalBinds = 0;
        $matches = $this->getOptions('match');

        if($this->conditions === [] && $matches === []){
            return false;
        }

        $objectId = $this->getObjectId();

        foreach ($this->conditions as $index => $condition) {
            $value = $condition['value'] ?? null;

            if($condition['mode'] !== self::INARRAY && ($value instanceof Expression)){
                continue;
            }

            $value = $this->getValue($value);

            switch ($condition['mode']) {
                case self::AGAINST:
                    $totalBinds++;
                    $this->bindValue(":match_column_{$index}", $value, $params);
                break;
                case self::CONJOIN:
                    $bindIndex = 0;
                    $this->bindGroupConditions($condition['conditions'], $index, $bindIndex, $params);
                    $totalBinds += $bindIndex;
                break;
                case self::NESTED:
                    // Reset index
                    $bindIndex = 0;
                    $this->bindGroupConditions($condition['left'], $index, $bindIndex, $params);
                    $this->bindGroupConditions($condition['right'], $index, $bindIndex, $params);
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
                    $this->bindValue(self::toNamedParameter(
                        $condition['column'],
                        $objectId
                    ), $value, $params);
                break;
            }
        }
 
        foreach ($matches as $idx => $order) {
            if ($order['value'] instanceof Expression) {
                continue;
            }

            $totalBinds++;
            $this->bindValue(
                ":match_order_{$idx}", 
                $this->getValue($order['value']), 
                $params
            );
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
        $operator = $condition['operator'] ?? '=';
        $column = $condition['column'] ?? '';
        $not = ($condition['not'] ?? false) ? 'NOT ' : '';
        $value = $this->getValue($condition['value'] ?? null);
        $isRaw = ($value instanceof Expression);
        
        $connector = $addOperator ? ' ' . ($condition['connector'] ?? 'AND') . ' ' : '';

        $placeholder = $isRaw
            ? self::escape(value: (string) $value, addSlashes: true)
            : self::toNamedParameter(
                ($condition['mode'] === self::AGAINST) 
                    ? "match_column_{$index}" 
                    : $column,
                $this->getObjectId()
            );

        return match ($condition['mode']) {
            self::REGULAR => "{$connector}{$column} {$operator} {$placeholder}",
            self::INARRAY => "{$connector}{$column} {$operator}(" . (
                $isRaw
                    ? self::escapeValueList((array) ($value ?? []))
                    : $this->bindInConditions((array) ($value ?? []), $column)
            ) . ')',
            self::AGAINST => "{$connector}{$not}MATCH($column) AGAINST ({$placeholder} {$operator})",
            self::INSET => self::buildInsetConditions(
                $condition, 
                $connector, 
                $operator
            ),
            default => '',
        }; 
    }

    /**
     * Normalize and validate a SQL operator.
     *
     * Converts the operator to the expected format and ensures it is supported.
     * When enabled, inset operators such as nested logical operators are also
     * accepted.
     *
     * @param string $operator The operator to validate.
     * @param bool $allowInset Whether to allow inset-specific operators.
     *
     * @return string The normalized operator.
     *
     * @throws InvalidArgumentException If the operator is not supported.
     */
    private static function parseOperator(
        ?string $operator,
        bool $allowInset = false
    ): string
    {
        if($operator === null){
            return '';
        }

        $operator = strtoupper(trim($operator));

        if($operator === ''){
            return '';
        }

        if (isset(self::OPERATORS[$operator])) {
            return $operator;
        }

        if ($allowInset) {
            $lower = strtolower($operator);

            if (isset(self::INSET_OPERATORS[$lower])) {
                return $lower;
            }
        }

        throw new InvalidArgumentException(
            "Unsupported operator: {$operator}"
        );
    }

    /**
     * Builds a SQL FIND_IN_SET condition clause from the provided configuration.
     *
     * Generates a conditional expression for searching values inside a comma-separated
     * list using MySQL's FIND_IN_SET() function. Supports custom operators for checking
     * existence, position, first/last occurrence, absence, and partial matching.
     *
     * When the search value or list is marked as a column expression, it is used directly;
     * otherwise, values are escaped and quoted before being added to the SQL clause.
     *
     * @param array{
     *     search: mixed,
     *     list: string,
     *     searchAsColumn: bool,
     *     isList: bool,
     *     not?: bool
     * } $condition FIND_IN_SET condition configuration.
     * @param string $connector Logical connector used to join the generated condition.
     * @param string $operator FIND_IN_SET comparison operator or predefined mode.
     *
     * @return string Generated SQL condition clause.
     */
    private static function buildInsetConditions(
        array $condition,
        string $connector,
        string $operator
    ): string 
    {
        // Sanitize the search term unless it represents a column/expression
        $search = $condition['searchAsColumn']
            ? $condition['search']
            : self::escape($condition['search'], true);

        // Sanitize the list value unless it represents a column/expression
        $values = $condition['isList']
            ? self::escape(value: $condition['list'], enQuote: true, addSlashes: true)
            : $condition['list'];

        $expression = "FIND_IN_SET({$search}, {$values})";

        $condition = match ($operator) {
            'position'  => $expression,
            '>', 'exists' => "{$expression} > 0",
            '=', 'first'  => "{$expression} = 1",
            'last'        => "{$expression} = (LENGTH({$values}) - LENGTH(REPLACE({$values}, ',', '')) + 1)",
            'none'        => "{$expression} = 0",
            'contains'    => "{$values} LIKE '%{$search}%'",
            default       => "{$expression} {$operator}",
        };

        if ($condition['not'] ?? false) {
            $condition = "NOT ({$condition})";
        }

        return "{$connector}{$condition}";
    }

    /**
     * Bind custom placeholder params for join tables.
     * 
     * @return bool
     */
    private function bindJoinPlaceholders(): bool 
    {
        $binds = 0;
        foreach($this->getOptions('bindings') as $placeholder => $value){
            if($value instanceof Expression){
                throw new DatabaseException(
                    sprintf('Bind value cannot be instance of %s', Expression::class),
                    ErrorCode::LOGIC_ERROR
                );
            }

            $this->bindValue($placeholder, $value);
            $binds++;
        }

        return $binds > 0;
    }

    /**
     * Convert unit to kilometer.
     * 
     * unit => kilometers per 1 unit, used for the bounding box.
     *
     * @param string $unit The unit identifier
     * 
     * @return float Return unit in km
     */
    private static function toKilometer(string $unit): float
    {
        $unit = strtolower($unit);
        return match($unit) {
            'km', 'kilometer'   => 1.0,
            'mi', 'miles'       => 1.60934,
            'm', 'meters'       => 0.001,
            'ft', 'feet'        => 0.0003048,
            default => throw new InvalidArgumentException(
                "Unsupported distance unit [{$unit}]."
            )
        };
    }

    /**
     * Apply a geographic bounding box filter.
     *
     * Calculates the minimum and maximum latitude and longitude for the
     * specified search radius and adds the corresponding WHERE conditions.
     * This greatly reduces the number of rows that require an expensive
     * distance calculation.
     *
     * The center coordinate may be supplied as numeric values or SQL
     * placeholders.
     *
     * @param string $connector Logical operator to join with previous conditions (`AND` or `OR`).
     * @param string $fn .
     * @param string $lngColumn Longitude column name.
     * @param string $latColumn Latitude column name.
     * @param string|float $longitude Center longitude or SQL placeholder.
     * @param string|float $latitude Center latitude or SQL placeholder.
     * @param float $radius Search radius.
     * @param string $unit Distance unit.
     *
     * @return self Return the builder instance.
     *
     * @throws InvalidArgumentException If the radius, unit, or coordinates are invalid.
     */
    private function whereDistanceBoundingBox(
        string $connector,
        string $fn,
        string $lngColumn,
        string $latColumn,
        string|float $longitude,
        string|float $latitude,
        float $radius = 10.0,
        string $unit = 'km',
        bool $notBetween = false
    ): self
    {
        if ($radius <= 0 || !is_finite($radius)) {
            throw new InvalidArgumentException(
                "Invalid bounding box radius: {$radius}."
            );
        }

        $radiusKm = $radius * self::toKilometer($unit);
        $latDelta = $radiusKm / self::KM_PER_DEGREE_LATITUDE;

        if (is_numeric($latitude) && is_numeric($longitude)) {
            $longitude = (float) Util::normalizeCoordinate(
                $longitude,
                'longitude',
                -180,
                180
            );

            $latitude = (float) Util::normalizeCoordinate(
                $latitude,
                'latitude',
                -90,
                90
            );

            $cosLat = cos(deg2rad($latitude));

            $lngDelta = (abs($cosLat) < 1e-12)
                ? 180.0
                : min(180.0, $latDelta / $cosLat);

            $latMin = max(-90.0, $latitude - $latDelta);
            $latMax = min(90.0, $latitude + $latDelta);

            $lngMin = max(-180.0, $longitude - $lngDelta);
            $lngMax = min(180.0, $longitude + $lngDelta);
        } else {
            $latMin = "GREATEST(-90, {$latitude} - {$latDelta})";
            $latMax = "LEAST(90, {$latitude} + {$latDelta})";

            $lngDelta = "(CASE
                WHEN ABS(COS(RADIANS({$latitude}))) < 1e-12
                THEN 180
                ELSE LEAST(180, {$latDelta} / COS(RADIANS({$latitude})))
            END)";

            $lngMin = "GREATEST(-180, {$longitude} - {$lngDelta})";
            $lngMax = "LEAST(180, {$longitude} + {$lngDelta})";
        }

        $operator = $notBetween ? 'NOT BETWEEN' : 'BETWEEN';

        return $this->whereRawCondition("
            (
                ({$latColumn} {$operator} {$latMin} AND {$latMax})
                AND ({$lngColumn} {$operator} {$lngMin} AND {$lngMax})
            )", 
            $connector, 
            $fn
        );
    }

    /**
     * Adds a geographic distance exclusion condition to the query.
     *
     * @param string $fn
     * @param float $radius
     * @param string $connector
     * @param bool $whereNot
     * 
     * @return self
     */
    private function whereWithinCondition(
        string $fn,
        float $radius, 
        string $connector = 'AND', 
        bool $whereNot = false
    ): self
    {
        if($this->distances === []){
            throw new LogicException(
                "The {$fn}() method requires Builder::distance() to be called first."
            );
        }

        return $this->whereDistanceBoundingBox(
            $connector, 
            $fn,
            $this->distances['lngColumn'],
            $this->distances['latColumn'],
            $this->distances['longitude'],
            $this->distances['latitude'],
            (float) $radius,
            $this->distances['unit'],
            $whereNot
        );
    }

    /**
     * Merge distance columns with query columns.
     *
     * @param array $columns Query columns.
     *
     * @return array Return merged columns.
     */
    private function mergeDistanceColumns(array $columns): array
    {
        if (!($this->selector['distances'] ?? false)) {
            return $columns;
        }

        $columns = array_merge(
            $columns,
            $this->selector['columns'] ?? []
        );

        // Reset distance column flag
        $this->selector['distances'] = false;

        return $columns;
    }

    /**
     * Adds an EXISTS or NOT EXISTS subquery condition to the current query.
     *
     * This method builds a subquery against the specified table and appends it to
     * the current query using an EXISTS condition. When {@see $whereNot} is enabled,
     * the condition is negated using NOT EXISTS.
     *
     * The callback is used to configure the subquery builder before SQL generation.
     * The generated subquery only checks for row existence and does not retrieve
     * actual result records.
     *
     * @param string $fn The calling method name used for query metadata tracking.
     * @param string $table The table used by the subquery.
     * @param (Closure(self):void) $callback Callback used to configure the subquery builder.
     * @param string|null $alias Optional table alias for the subquery.
     * @param string $connector Logical connector used to join the condition.
     * @param bool $whereNot Whether to generate a NOT EXISTS condition instead of EXISTS.
     *
     * @return self The current query builder instance.
     *
     * @throws InvalidArgumentException If the table name is empty.
     * @throws LogicException If the callback fails to produce a valid subquery.
     */
    private function whereExistsCondition(
        string $fn,
        string $table,
        Closure $callback,
        ?string $alias = null,
        string $connector = 'AND',
        bool $whereNot = false
    ): self
    {
        if ($table === '') {
            throw new InvalidArgumentException(
                'EXISTS subquery table name cannot be empty.'
            );
        }

        $query = new self($table, $alias);
        $query->find([1]);

        $query->isCollectMetadata = true;

        $result = $callback($query);

        if ($result !== null && !$result instanceof $query) {
            throw new LogicException(
                'EXISTS callback must configure the provided query builder and return nothing.'
            );
        }

        $query->get();
        $metadata = $query->getOptions('metadata');

         if ($metadata === []) {
            throw new LogicException(
                'EXISTS callback did not produce query metadata.'
            );
        }

        $sql = $metadata['sql'] ?? null;

        if ($sql === null || $sql === '') {
            throw new LogicException(
                'Unable to compile EXISTS subquery.'
            );
        }

        $this->options['bindings'] = array_merge(
            $this->options['bindings'] ?? [],
            (array) ($metadata['params'] ?? [])
        );

        $not = $whereNot ? 'NOT ' : '';
        $query = null;

        return $this->whereRawCondition(
            "{$not}EXISTS ({$sql})",
            $connector,
            $fn
        );
    }

    /**
     * Adds a negative `FIND_IN_SET()` condition.
     *
     * Filters records where the given value does not exist in the specified
     * comma-separated list.
     *
     * @param string $fn
     * @param bool $notInset
     * @param string $search
     * @param string $operator
     * @param array|string $list
     * @param bool $searchAsColumn
     * @param string $connector
     * @return self
     */
    private function whereInsetCondition(
        string $fn,
        bool $notInset,
        string $search, 
        string $operator, 
        array|string $list,
        bool $searchAsColumn = false,
        string $connector = 'AND'
    ): self
    {
        if($list === [] || $list === ''){
            throw new InvalidArgumentException(
                'Invalid argument $list, expected non-empty array or string.'
            );
        }

        $connector = $this->resolveConnector(
            $this->parseConnector($connector, $fn)
        )[0];

        $isList = is_array($list);
        $this->conditions[] = [
            'connector' => $connector, 
            'mode'      => self::INSET,
            'list'      => $isList ? implode(',', array_values($list)) : $list, 
            'isList'    => $isList,
            'search'    => $search, 
            'not'       => $notInset,
            'operator'  => self::parseOperator($operator, true),
            'searchAsColumn' => $searchAsColumn,
        ];

        return $this;
    }

    /**
     * Adds a negative full-text search condition using `MATCH (...) AGAINST (...)`.
     *
     * @param string $fn
     * @param mixed $value
     * @param string|int $mode
     * @param string $connector
     * @param bool $whereNot
     * 
     * @return self
     */
    private function whereAgainstCondition(
        string $fn,
        mixed $value, 
        string|int $mode = self::MATCH_NATURAL, 
        string $connector = 'AND',
        bool $whereNot = false
    ): self
    {
        $column = $this->getMatchColumns($fn);

        return $this->whereClause(
            $connector, 
            $column,
            self::MATCH_MODES[$mode] ?? $mode, 
            $value,
            self::AGAINST,
            $whereNot
        );
    }

    /**
     * Retrieves the current builder instance options for a given key.
     * 
     * Keys: 
     * 
     * - `this` - The current builder instance options.
     * - `unionColumns` - The columns used in UNION queries.
     * - `grouping`  - The columns used in GROUP BY clauses.
     * - `ordering`  - The columns used in ORDER BY clauses.
     * - `duplicate` - The columns used in ON DUPLICATE KEY UPDATE clauses.
     * - `match`     - The columns used in MATCH AGAINST clauses.
     * - `matches`   - The columns used in MATCH AGAINST clauses for multiple columns.
     * - `where`     - The raw WHERE conditions.
     * - `having`    - The columns used in WHERE HAVING clauses.
     * - `bindings`  - The columns used in prepared statement bindings.
     * 
     * @param string $key The option key (e.g., 'having', 'bindings').
     * 
     * @return array Return an array of option values.
     */
    protected function getOptions(string $key): array 
    {
        return $this->options[$key] ?? [];
    }

    /**
     * Executes a UNION or UNION ALL query and returns the result.
     *
     * This method is called internally when executing queries that involve
     * multiple tables combined with UNION or UNION ALL. It compiles the
     * SQL for all union tables, executes the combined query, and returns
     * the result in the specified format.
     *
     * @param int $returnMode Record return mode e.g. `RETURN_NEXT`, (default: `RETURN_ALL`).
     * @param int|null $fetchAs Result fetch mode e.g, `FETCH_ASSOC`, `FETCH_CLASS`, (default: `FETCH_OBJ`).
     *
     * @return mixed Returns query result on success, or `false`/`null` on failure.
     * @throws DatabaseException If no query is set or execution fails.
     * 
     * @see self::get() - To execute query and return result.
     */
    private function buildExecutableUnionQuery(
        int $returnMode, 
        ?int $fetchAs,
        ?string $method = null
    ): mixed 
    {
        [$sql, $placeholders] = $this->compileTableUnions();
            
        $this->unions = [];
        $this->options['unionColumns'] = [];

        self::assertQuery($sql, __METHOD__);
        $this->sqlQuery = $sql;

        if($this->isCacheable){
            $cache = (array) ($this->options['metadata']['cache'] ?? []);

            if($cache){
                $this->cache(...$cache);
            }
        }

         try {
            return $this->runExecutableQueryResult(
                $placeholders, 
                $returnMode, 
                $fetchAs,
                method: $method
            );
        } catch (Throwable $e) {
            $this->resolveException($e);
        }

        return false;
    }

    /**
     * Builds a query string representation of single grouped conditions.
     *
     * @param array|self[] $conditions An array of conditions to be grouped.
     * @param int $index The index to append to the placeholder names.
     * @param string $connector The logical connector (default: 'OR').
     * @param int &$bindIndex Reference to the total count of conditions processed so far across all groups.
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
        string $connector = 'OR', 
        int &$bindIndex = 0
    ): string
    {
        $group = '';
        $length = count($conditions);
        $objectId = $this->getObjectId();

        for ($idx = 0; $idx < $length; $idx++) {
            $condition = $conditions[$idx];
            $column = key($condition);
            
            $value = $this->getValue($condition[$column]['value']);
            $operator = strtoupper($condition[$column]['operator'] ?? '=');

            if($value instanceof Expression){
                $placeholder = $value->toString();
            }else{
                $pIndex = $idx + $bindIndex;
                $placeholder = self::toNamedParameter(
                    "{$column}_{$index}_{$pIndex}",
                    $objectId
                );

                $bindIndex++;
            }

            if ($idx > 0) {
                $group .= " {$connector} ";
            }

            if(str_ends_with($operator, 'IN')){
                $placeholder = '(' . $this->bindInConditions($value, $column) . ')';
            }

            $group .= "{$column} {$operator} {$placeholder}";
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
        $sql .= $this->buildGroupConditions(
            $condition['left'], 
            $index, 
            $condition['groupConnector'],
            $nestedIndex
        );

        $sql .= ' ';
        $sql .= ($condition['nestedConnector'] ?? 'OR');
        $sql .= ' ';

        $sql .= $this->buildGroupConditions(
            $condition['right'], 
            $index, 
            $condition['groupConnector'], 
            $nestedIndex
        );
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
        $objectId = $this->getObjectId();

        for ($idx = 0; $idx < $length; $idx++) {
            $value = $values[$idx];

            if($value instanceof Expression){
                $placeholders .= "{$value->toString()}, ";
            }else{
                $placeholder = self::toNamedParameter(
                    "{$column}_in_{$idx}", 
                    $objectId
                );

                if($handle){
                    $this->bindValue($placeholder, $value, $params);
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
     * @param array|null $params
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
        $objectId = $this->getObjectId();

        for ($idx = 0; $idx < $length; $idx++) {
            $bind = $bindings[$idx];
            $column = key($bind);
            $value = $this->getValue($bind[$column]['value']);

            if($value instanceof Expression){
                continue;
            }

            $operator = strtoupper($bind[$column]['operator'] ?? '');

            if(str_ends_with($operator, 'IN')){
                $totalBinds = 0;
                $this->bindInConditions($value, $column, true, $totalBinds, $params);
            }else{
                $pIndex = $idx + $bindIndex;
                $this->bindValue(
                    self::toNamedParameter(
                        "{$column}_{$index}_{$pIndex}",
                        $objectId
                    ), 
                    is_array($value) ? self::escapeValueList($value, true) : $value,
                    $params
                );
                $bindIndex++;
            }
        }
    }

    /**
     * Handle query debug output or storage.
     *
     * In production environments, debug output of `elf::DEBUG_BUILDER_COLLECT` is logged using debug level.
     *
     * @param string $query The generated SQL query string.
     * @param string $method The calling method name for debug context.
     * @param array $values Query parameter bindings.
     *
     * @return bool Returns true when debug information is stored internally,
     *              otherwise false.
     *
     * @throws \Luminova\Exceptions\JsonException If parameter values cannot be encoded.
     */
    private function addDebug(string $query, string $method = '', array $values = []): bool
    {
        if($this->debugMode === self::DEBUG_BUILDER_COLLECT){
            $this->debugger->enqueue(
                $query, 
                $method, 
                $values,
                $this->conditions,
                fn($value) => $this->getValue($value)
            );
            return true;
        }

        if ($this->debugMode === self::DEBUG_BUILDER_OUTPUT) {
            $this->debugger->printLine($query, 'SQL QUERY');
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
            $value = ($value instanceof Expression) 
                ? self::escape(value: $value, addSlashes: true)
                : ":match_order_{$idx}";

            $match .= "MATCH({$order['column']}) AGAINST ({$value} {$order['mode']}) {$order['order']}, ";
        }

        $sql .= rtrim($match, ', ');
    }

    private function whereHavingCondition(
        Expression|string $expression, 
        string $operator, 
        string $groupConnector,
        mixed $value,
        string $connector,
        bool $isNotHaving,
        string $fn
    ): self
    {
        $connector = $this->resolveConnector(
            $this->parseConnector($connector, $fn)
        )[0];

        $this->options['having'][] = [
            'column'          => $expression,
            'operator'        => self::parseOperator($operator),
            'connector'       => $connector,
            'groupConnector'  => $groupConnector,
            'value'           => $value,
            'not'             => $isNotHaving
        ];
        
        return $this;
    }

    /**
     * Appends HAVING conditions to the SQL query.
     * 
     * This method processes the stored filter conditions and constructs a HAVING clause, 
     * ensuring that expressions are properly formatted. If no filters are defined, the method exits early.
     * 
     * @param string &$sql The SQL query string to append the HAVING conditions.
     */

    private function injectHavingConditions(string &$sql): void
    {
        $filters = $this->getOptions('having');

        if ($filters === []) {
            return;
        }

        $having = ' HAVING';

        foreach ($filters as $idx => $filter) {
            $expression = $filter['column'];

            if ($expression instanceof Expression) {
                $expression = $expression->toString();
            }

            $operator = strtoupper($filter['operator']);
            $value = $filter['value'];
            $isNotHaving = $filter['not'];

            $connector = ($idx === 0)
                ? ''
                : ' ' . ($filter['connector'] ?? 'AND');

            if (!is_array($value)) {
                $condition = sprintf(
                    '%s %s %s',
                    $expression,
                    $operator,
                    self::escape($value, enQuote: true)
                );
            } elseif (self::isArrayOperator($operator, true)) {
                $condition = sprintf(
                    '%s %s (%s)',
                    $expression,
                    self::toInArrayOperator($operator),
                    self::escapeValueList($value, enQuote: true)
                );
            } else {
                $conditions = [];
                $groupConnector = $filter['groupConnector'] ?? $operator;

                foreach ($value as $item) {
                    $conditions[] = sprintf(
                        '%s %s %s',
                        $expression,
                        $operator,
                        self::escape($item, enQuote: true)
                    );
                }

                $condition = implode(" {$groupConnector} ", $conditions);
                $condition = $isNotHaving ? $condition : "({$condition})";
            }

            if ($isNotHaving) {
                $condition = "NOT ({$condition})";
            }

            $having .= "{$connector} {$condition}";
        }

        $sql .= $having;
    }

    /**
     * Initializes a new cache instance for the ORM query builder.
     * 
     * @param string|null $storage Optional storage name for the cache.
     * @param string|null $persistentId Optional memory-based caching unique persistent connection ID.
     * 
     * @return void
     */
    private function newCache(
        ?string $storage = null, 
        ?string $persistentId = null
    ): void
    {
        if(self::$cacheDriver === null){
            self::$cacheDriver = env('database.orm.cache.driver') 
                ?? env('system.cache.driver', 'filecache');
        }

        $isFile = self::$cacheDriver === 'filecache' 
            || self::$cacheDriver === 'filesystem';

        $persistentId = ($isFile && $persistentId)
            ? trim($persistentId, TRIM_DS)
            : $persistentId;

        if($this->cache instanceof Cache){
            if($storage !== null){
                $this->cache->setStorage($storage);
            }

            if($persistentId !== null){
                $this->cache->setPersistentId($persistentId);
            }
        }else{
            $this->cache = Luminova::kernel(
                'cache', 
                true,
                self::$cacheDriver,
                $storage ?? 'database',
                $persistentId,
                'orm'
            );
        }

        if(!$this->cache->isConnected() && !$this->cache->connect()){
            return;
        }

        $this->cacheInfo = [
            'connected'    => $this->cache->isConnected(),
            'driver'       => self::$cacheDriver,
            'storage'      => $storage ?? 'database',
            'persistentId' => $persistentId,
        ];
    }

    /**
     * Generate a deterministic cache key for the current query context.
     *
     * The key is built from the table name (or raw query fallback) and
     * a filtered subset of selector values. Only relevant SQL-related
     * selector fields are included to ensure stability and avoid
     * unnecessary cache fragmentation.
     *
     * The final key is normalized through Luminova::getCacheId() to
     * guarantee consistent formatting across the system.
     *
     * @return string A stable cache key representing the query state.
     */
    private function toCacheKey(): string
    {
        $prefix = $this->tableName ?: (empty($this->sqlQuery) ? 'sql-query' : $this->sqlQuery);
        $keys = [];
        $selector = ['sql', 'columns', 'method'];

        foreach ($selector as $key) {
            $value = $this->selector[$key] ?? null;

            if ($value === null) {
                continue;
            }

            $value = is_array($value) ? implode(':', $value) : (string) $value;

            $keys[] =  "{$key}:{$value}";
        }

        if ($keys !== []) {
            $prefix .= ':' . implode(':', $keys);
        }

        return Luminova::getCacheId($prefix, true, false);
    }

    /**
     * Extract column name without alias.
     *
     * @param string $column The column name expression (e.g., "table.column" or "alias.column").
     * 
     * @return string Return extracted column name.
     */
    private static function toColumnName(string $column): string
    {
        if (str_contains($column, '(') && preg_match('/\(([^()]*)\)/', $column, $m)) {
            $column = trim($m[1]);
        }

        $pos = strrpos($column, '.');

        return $pos === false
            ? $column
            : substr($column, $pos + 1);
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
            $inserts .= "{$column}, ";
            $placeholders .= ($value instanceof Expression) 
                ? $value->toString() . ', ' 
                : ":{$column}, ";
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
        $objectId = $this->getObjectId();

        foreach ($columns as $column => $val) {
            $value = $this->getValue($val);

            $placeholders[] = "{$column} = " . (($value instanceof Expression) 
                ? $value->toString() 
                : self::toNamedParameter($column, $objectId)
            );
        }

        return $asString ? implode(', ', $placeholders) : $placeholders;
    }
    
    /**
     * Convert a raw SQL query into a Common Table Expression (CTE) format.
     *
     * This method normalizes the input query to ensure it adheres to CTE syntax,
     * handling cases such as missing table names, recursive queries, and shorthand
     * notations. It also supports legacy shorthand inputs and ensures that the
     * resulting query is valid for execution.
     *
     * @param string $query The raw SQL query to convert.
     * @param string $defaultTable The default table name to use if not specified in the query (default: 'users').
     *
     * @return string The normalized CTE query string.
     */
    private static function toCteQuery(string $query, string $defaultTable = 'users'): string
    {
        $isRecursive = false;
        $query = preg_replace('/\s+/', ' ', $query);

        if (stripos($query, 'with recursive ') === 0) {
            $isRecursive = true;
            $query = substr($query, 15);
        } elseif (stripos($query, 'recursive ') === 0) {
            $isRecursive = true;
            $query = substr($query, 10);
        }

        $query = trim($query);

        if (preg_match('/^with\s+/i', $query)) {
            return $query;
        }

        if ($isRecursive && !preg_match('/^with\s+/i', $query)) {
            $query = "WITH RECURSIVE {$query}";
            return $query;
        }

        if (preg_match('/^select\s+/i', $query)) {
            return "WITH {$defaultTable} AS ({$query})";
        }

        // Normalize legacy shorthand inputs
        // $query = preg_replace('/^with\s+/i', '', $query);

        $parts = self::toCteParts($query);
        $normalized = [];

        foreach ($parts as $part) {
            $normalized[] = self::normalizeSingleCtePart($part, $defaultTable);
        }

        $query = implode(', ', $normalized);

        return $isRecursive
            ? "WITH RECURSIVE {$query}"
            : "WITH {$query}";
    }

    /**
     * Splits a CTE query into its individual parts, handling nested parentheses.
     *
     * This method parses the input query string and separates it into distinct
     * components based on commas, while respecting nested structures (e.g., subqueries).
     * It ensures that commas within parentheses do not split the query incorrectly.
     *
     * @param string $query The raw SQL query to split.
     *
     * @return array An array of individual CTE parts.
     */
    private static function toCteParts(string $query): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;

        for ($i = 0; $i < strlen($query); $i++) {
            $ch = $query[$i];

            if ($ch === '(') $depth++;
            if ($ch === ')') $depth--;

            if ($ch === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        if ($buffer !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    /**
     * Normalizes a single CTE part, ensuring it has a table name and proper syntax.
     *
     * This method checks the provided CTE part for missing table names or incorrect
     * syntax and adjusts it accordingly. It handles various cases, including shorthand
     * notations and ensures that the resulting part is valid for inclusion in a CTE.
     *
     * @param string $part The individual CTE part to normalize.
     * @param string $defaultTable The default table name to use if not specified (default: 'users').
     *
     * @return string The normalized CTE part.
     */
    private static function normalizeSingleCtePart(string $part, string $defaultTable): string
    {
        $part = trim($part);

        /**
         * CASE 1:
         * AS (...) → missing table name
         */
        if (preg_match('/^as\s*\(/i', $part)) {
            return "{$defaultTable} {$part}";
        }

        /**
         * CASE 2:
         * (columns) AS (...) → missing table name
         */
        if (preg_match('/^\s*\([^)]+\)\s*as\s*\(/i', $part)) {
            return "{$defaultTable} {$part}";
        }

        /**
         * CASE 3:
         * table (columns) AS (...)
         */
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]+\)\s*as\s*\(/i', $part)) {
            return $part;
        }

        /**
         * CASE 4:
         * table (columns) AS (...) BUT missing AS injection fix
         */
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*\((.+)\)$/s', $part, $m)) {

            $table = $m[1];
            $inside = trim($m[2]);

            // KEY FIX: detect if it's a real SQL query, not column list
            if (stripos($inside, 'select') === 0) {
                return "{$table} AS ({$inside})";
            }

            // fallback: column list case
            return "{$table} ({$inside})";
        }

        /**
         * CASE 5:
         * (columns) AS (...) inside multiple CTEs
         */
        if (preg_match('/^\s*\(/', $part)) {
            return "{$defaultTable} AS {$part}";
        }

        return $part;
    }


    /**
     * Validate that a query is a safe and valid CTE (Common Table Expression).
     *
     * This method performs a lightweight validation to ensure that only
     * read-only SELECT-based queries are accepted. It prevents accidental
     * execution of destructive SQL statements within a CTE context.
     *
     * @param string $query The raw SQL query to validate.
     * @param bool $isWithFullValidation
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *  Thrown when the query is:
     *  - Empty
     *  - Not starting with SELECT or WITH ... SELECT
     * - Must not contain SELECT after body parentheses block
     */
    private static function assertCte(string $query, bool $isWithFullValidation = false): void
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException(
                'CTE query cannot be empty.'
            );
        }

        // if (str_contains($query, ';')) {
        //    throw new InvalidArgumentException('CTE must not contain multiple statements.');
        // }

        if ($isWithFullValidation) {
            if (!preg_match('/^\s*with\s+/i', $query)) {
                throw new InvalidArgumentException(
                    'WITH expression must start with WITH.'
                );
            }

            if (!preg_match('/\bas\s*\(/i', $query)) {
                throw new InvalidArgumentException(
                    'Invalid WITH format. Missing AS ( ... ) block.'
                );
            }

            $lastParen = strrpos($query, ')');

            if ($lastParen !== false) {
                $after = trim(substr($query, $lastParen + 1));

                if ($after !== '') {
                    throw new InvalidArgumentException(
                        'WITH expression must not contain trailing SQL after CTE block.'
                    );
                }
            }

            return;
        }

        // if (!preg_match('/^\s*(with\s+.*select|select)\s+/is', $query)) {
        //     throw new InvalidArgumentException(
        //        'CTE query must be SELECT or WITH SELECT statement.'
        //    );
        // }
    }
}