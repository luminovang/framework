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
use \Luminova\Luminova;
use \ReflectionFunction;
use \Luminova\Base\Cache;
use \Luminova\Logger\Logger;
use \Luminova\Cache\FileCache;
use \Luminova\Database\Builder;
use \Luminova\Cache\MemoryCache;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Database\RawExpression;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Interface\ExceptionInterface;
use \Luminova\Exceptions\InvalidArgumentException;

/**
 * @mixin Builder
 */
trait ORMBuilderTrait 
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
     * Database table name to query.
     * 
     * @var string $cteTableQuery 
     */
    private string $cteTableQuery = '';

    /**
     * Valid CTE query WITH.
     * 
     * @var bool $isCteWith 
     */
    private bool $isCteWith = true;

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
     * @var string|null $cacheDriver 
     */
    private ?string $cacheDriver = null;

    /**
     * The last inserted Id.
     * 
     * @var mixed $lastInsertId
     */
    private mixed $lastInsertId = null;

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
     * @param (Closure(Builder $static):mixed)|mixed $value The condition value to compare. 
     *              Can be scalar or array (for `Builder::INARRAY`).
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
     * use Luminova\Database\Builder;
     * 
     * $builder = Builder::table('users')
     *     ->select()
     *     ->clause('AND', 'id', '=', 100)
     *     ->clause('OR', 'id', '=', 101)
     *     ->clause('AND', 'name', '=', 'Peter')
     *     ->clause('AND', 'roles', 'IN', ['admin', 'editor'], Builder::INARRAY)
     *     ->get();
     * ```
     * 
     * @see self::where()
     * @see self::and()
     * @see self::or()
     * @see self::in()
     * @see self::notIn()
     * @see self::condition()
     * @see self::against()
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

        if (!in_array($mode, self::CLAUSE_MODES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid clause mode "%s". Supported modes: %s.',
                $mode,
                implode(', ', self::CLAUSE_MODES)
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
            'connector' => $connector,
            'mode' => $mode,
            'column' => $column,
            'value' => $value,
            'comparison' => $comparison
        ];

        return $this;
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
        $flags = JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION;
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
     * Get an array of debug query information.
     * 
     * Returns detailed debug information about the query string, including formats for `MySQL` and `PDO` placeholders, as well as the exact binding mappings for each column.
     * 
     * @return array<string,mixed> Return array containing query information.
     * 
     * @see self::printDebug()
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
     * @see self::getDebug()
     * @see self::dump()
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
     * 
     * @see self::getDebug()
     * @see self::printDebug()
     */
    public function dump(?string $format = null): void
    {
        if($this->debugMode === self::DEBUG_NONE){
            return;
        }
        
        if($this->debugMode === self::DEBUG_DRIVER){
            $this->db->dumpDebug();
            return;
        }

        if($this->debugMode !== self::DEBUG_BUILDER){
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
     * Reset builder state.
     * 
     * @param bool $new The current object state.
     * 
     * @return void
     */
    private function resetState(bool $new = false): void 
    {
        // $this->cteTableQuery = '';
        $this->tableJoin = [];
        $this->joinConditions = [];
        $this->limiting = [];
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

        if(!$new && ($this->stmt === null || $this->returns !== self::RETURN_STATEMENT)){
            $this->selector = [];
        }

        if($new){
            $this->returns = null;
        }
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
     * Start or create nested transaction.
     * 
     * @return array{bool:inTransaction,?string:savepoint}
     */
    private function withTransaction(): array
    {
        try{
            if(!$this->inTransaction()){
                return [$this->transaction(), null];
            }
        
            $savepoint = uniqid('builder_savepoint_' . $this->getObjectId());

            if($this->savepoint($savepoint)){
                return [true, $savepoint];
            }
        }catch(Throwable){}

        return [false, null];
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
            'IN', '=', '==' => '',
            'NOT', '!=', '<>', '!' => 'NOT ',
            default => (str_contains($expr, 'NOT') ? 'NOT ' : ''),
        };

        return $this->clause($connector, $column, "{$prefix}IN", $values, self::INARRAY);
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

        if(!$clone->db instanceof DatabaseInterface){
            $clone->db = self::database();
        }

        return $clone;
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
            ($response instanceof DatabaseInterface) || 
            ($response instanceof PDOStatement) || 
            ($response instanceof mysqli_result)
        ) {
            return;
        }

        if (
            !$this->isCacheable() 
            || $this->inSafeMode() 
            || (is_object($response) && count(get_object_vars($response)) === 0)
        ) {
            return;
        }

        $this->cache->set($this->cacheKey, $response);
        $this->cache = null;
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
            ($this->cache instanceof Cache)
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
                sprintf(
                    'No match columns defined. Use $query->match([...]) before calling $query->%s(...).', 
                    $fn
                ),
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
        if (!$this->hasCache || $mode === RETURN_STMT || !$this->isCacheable()) {
            return null;
        }

        $response = $this->cache->getItem($this->cacheKey);

        if ($response === null) {
            return null;
        }

        $this->cacheKey = '';
        $this->isCacheReady = false;
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

        $this->reset();

        if($throwInstant || (!PRODUCTION && !STAGING) || $e->getCode() === ErrorCode::TERMINATED){
            throw $e;
        }

        if($e instanceof ExceptionInterface){
            $e->handle();
            return;
        }

        DatabaseException::throwException(
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
        if($useTransaction && $this->db->inTransaction()){
            if($result > 0){
               $result = $this->commit() ? $result : 0;
            }else{
                $result = 0;
                $this->rollback(name: $savepoint);
            }
        }
   
        $this->lastInsertId = ($result > 0) 
            ? $this->db->getLastInsertId() 
            : null;
   
        $this->reset();
        return (int) $result;
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
     * @param bool $isNot Set to `true` to use `NOT BETWEEN` instead of `BETWEEN`.
     * 
     * @return self Returns the current query builder instance.
     * @throws DatabaseException If less than two values are provided, or an odd number of values is passed.
     */
    private function whereBetween(
        string $column, 
        array $values, 
        string $connector = 'AND', 
        bool $isNot = false
    ): self
    {
        $count = count($values);
        $operator = $isNot ? 'NOT BETWEEN' : 'BETWEEN';

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
        if(!$this->isCollectMetadata && $this->selector === [] && $this->unions === []){
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
        string $method = 'total', 
        array $columns = ['*'], 
        int $returns = RETURN_ALL, 
        int $fetch = FETCH_OBJ
    ): mixed
    {
        $top = $this->limiting['top'] ?? '';
        $sql = ($this->cteTableQuery ? "{$this->cteTableQuery} " : '');

        if($this->isCteWith){
            $sql .= $this->isDistinct ? "SELECT {$top}DISTINCT" : "SELECT {$top}";
        }

        $sql .= $query;

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
            $response = $this->getStatementExecutionResult($sql, $method, $returns, $fetch);

            if($returns !== RETURN_STMT){
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
     * @param string $method The execution method called 
     *      (expected: `total`, `stmt`, `select`, `find`, `delete`, `fetch`).
     * @param int $returns The return result type for `$method` operations 
     *          when `fetch` is used (expected: `next`, `all` or `stream`).
     * @param int $mode The database result mode for result retrieval (e.g., `FETCH_OBJ`).
     *
     * @return mixed Return the execution result, value varies based on the `$method` and `$mode` parameter.
     * @throws DatabaseException If an error occurs during query execution or result fetching.
     */
    private function getStatementExecutionResult(
        string $sql, 
        string $method = 'total', 
        int $returns = RETURN_ALL, 
        int $mode = FETCH_OBJ
    ): mixed
    {
        $isOrdered = false;
        $response = false;
        $isDelete = $method === 'delete';
        $isNext = ($method === 'find' || $method === 'next');
        
        if($this->conditions !== []){
            $this->buildConditions($sql);
        }

        $this->addRawWhereClause($sql);

        if($isDelete){
            $ordering = $this->getOptions('ordering');

            if($ordering !== []){
                $sql .= ' ORDER BY ' . rtrim(implode(', ', $ordering), ', ');
            }
        }else{
            [$query, $isOrdered] = $this->addOrderAndGrouping();
            $sql .= $query;

            $this->setMatchAgainst($sql, $isOrdered);
        }

        $offset = $this->limiting['offset'] ?? 0;
        $limit = $this->limiting['limit'] ?? 0;
        $isDebugging = $this->debugMode !== self::DEBUG_NONE;

        if($isDelete || $isNext){
            $limit = $isNext ? 1 : $limit;
            $sql .= ($limit > 0) ? " LIMIT {$limit}" : '';
        }elseif($limit > 0){
            $sql .= " LIMIT {$offset},{$limit}";
        }

        if($this->lock && !in_array($this->db->getDriver(), ['sqlsrv', 'mssql', 'dblib'])){
            $sql .= ' ' . $this->lock;
        }

        if($isDebugging && $this->addDebug($sql, $method)){
            return 0;
        }

        if($this->isCollectMetadata){
            $this->options['current']['sql'] = $sql;
        }

        $savepoint = null;
        $useTransaction = false;
        $isExecutable = (!$this->isCollectMetadata && !$isDebugging);

        $hasParams = ($this->conditions !== [] 
            || $this->getOptions('match') !== [] 
            || $this->getOptions('binds') !== []);

        if ($method === 'delete' && $this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        if($hasParams){
            if($isExecutable){
                $this->db->prepare($sql);
            }

            $c = $this->bindConditions();
            $b = $this->bindJoinPlaceholders();

            $hasParams = $c || $b;
        }

        if(!$isExecutable){
            return $this->isCollectMetadata;
        }

        $hasParams ? $this->db->execute() : $this->db->query($sql);
        $sql = null;

        if ($this->db->ok()) {
            if($this->returns === self::RETURN_STATEMENT || $returns === RETURN_STMT){
                $this->returns = self::RETURN_STATEMENT;
                return $this->db;
            }

            $response = match ($method) {
                'stmt' => $this->db,
                'total' => $this->db->getCount(),
                'delete' => $this->db->rowCount(),
                'average', 'sum' => ($this->db->fetchNext() 
                    ?: (object) ['totalCalc' => 0])->totalCalc,
                'select', 'find' => $this->db->fetch(($method === 'select')
                    ? RETURN_ALL 
                    : RETURN_NEXT,
                    $this->getFetchMode($mode)
                ),
                default => $this->db->getResult($returns, $this->getFetchMode($mode))
            };
        }

        if($useTransaction && $this->db->inTransaction()){
            if(!empty($response)){
                return $this->commit() ? $response : 0;
            }

            $this->rollback(name: $savepoint);
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
        
        $offset = $this->limiting['offset'] ?? 0;
        $limit = $this->limiting['limit'] ?? 0;
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
        $isDebugging = $this->debugMode !== self::DEBUG_NONE;

        if($isDebugging && ($this->addDebug($sql, 'execute', $placeholder) || !$placeholder)){
            return false;
        }

        $savepoint = null;
        $useTransaction = false;

        if ($this->inSafeMode()) {
            [$useTransaction, $savepoint] = $this->withTransaction();
        }

        if($placeholder === []){
            $this->db->query($sql);
        }else{
            if(!$isDebugging){
                $this->db->prepare($sql);
            }

            if($escapePlaceholders){
                $this->bindStrictColumns($placeholder, [], false);
                $placeholder = null;
            }
            
            if($isDebugging){
                return false;
            }

            $this->db->execute($placeholder);
        }

        if(!$this->db->ok()){
            if($useTransaction && $this->db->inTransaction()){
                $this->rollback(name: $savepoint);
            }

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

            $this->rollback(name: $savepoint);
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
        $db = self::database();
        $driver = $db->getDriver();

        $pgsqlPlaceholder = ($driver === 'pgsql') 
            ? (is_int($identifier) ? ':lockName' : 'hashtext(:lockName)')
            : null;

        $tbl = self::table('locks');

        if ($driver === 'sqlite') {
            static $exists = null;
            $exists = ($exists === null) ? $tbl->exists() : $exists;

            if(!$exists){
                $createTblQuery = 'CREATE TABLE IF NOT EXISTS locks (name TEXT PRIMARY KEY, acquired_at INTEGER)';
                $exists = (bool) $db->exec($createTblQuery);

                if(!$exists){
                    throw new DatabaseException(
                        "SQLite Error: Failed to create lock table with query: '{$createTblQuery}'",
                        ErrorCode::INVALID_ARGUMENTS
                    );
                }
            }
        }

        $query = Alter::getAdministrator($driver, $action, $pgsqlPlaceholder);
        $stmt = $db->prepare($query)->bind(':lockName', $identifier);
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
            return ($driver === 'sqlite') 
                ? ($row->lockCount > 0) 
                : (bool) $row->isLockDone;
        }

        return false;
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

        if ($this->isCollectMetadata){
            $this->options['current']['params'][$placeholder] = $value;
        } elseif($this->debugMode === self::DEBUG_NONE){
            if($params === null){
                $this->db->bind(":$placeholder", $value);
                return $this;
            }
            
            $params[$placeholder] = $value;
            return $this;
        }

        if($this->debugMode === self::DEBUG_BUILDER_DUMP){
            $this->echoDebug("{$placeholder} = {$value}", 'BIND PARAMS');
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
            if((new ReflectionFunction($input))->getNumberOfParameters() === 0){
                return $input();
            }

            $new = new self();
            $new->db = self::database(shared: false);

            return $input($new);
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
        $isDebugging = $this->debugMode !== self::DEBUG_NONE;

        for ($i = 0; $i < $length; $i++) {
            $inserts .= "(" . self::escapeValues($values[$i]) . "), ";
        }

        $columns = implode(', ', array_keys($values[0]));
        $inserts = rtrim($inserts, ', ');
        $ignore = $this->isIgnoreDuplicate ? 'IGNORE ' : '';

        $sql = "{$type} {$ignore}INTO {$this->tableName} ({$columns}) VALUES {$inserts}";
        $sql .= $this->buildDuplicateUpdateClause();

        if($isDebugging){
            $this->addDebug($sql, 'insert');
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
        $isDebugging = $this->debugMode !== self::DEBUG_NONE;
        $this->lastInsertId = null;

        $replacements = [];
        [$placeholders, $inserts] = self::mapInsertColumns($values[0]);

        $sql = "{$type} {$ignore}INTO {$this->tableName} ({$inserts}) VALUES ($placeholders)";
        $sql .= $this->buildDuplicateUpdateClause($replacements);
       
        if($isDebugging){
            if($this->addDebug($sql, 'insert', $values)){
                return 0;
            }
        }else{
            $this->db->prepare($sql);
        }

        for ($i = 0; $i < $length; $i++) {
            if($escapeValues || $isDebugging){
                $this->bindStrictColumns($values[$i], $replacements, false);
            }

            if($isDebugging){
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
    private function addDebug(string $query, string $method = '', array $values = []): bool
    {
        if($this->debugMode === self::DEBUG_BUILDER){
            $this->enqueueDebug($query, $method, $values);
            return true;
        }

        $this->echoDebug($query, 'SQL QUERY');
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
     * @return false Returns false.
     * @throws JsonException If an error occurs while encoding values.
     */
    private function enqueueDebug(string $query, string $method, array $values = []): bool
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
                : ":match_order_{$idx}";

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
     * @return void
     */
    private function newCache(
        ?string $storage = null, 
        ?string $subfolder = null,
        ?string $persistentId = null
    ): void
    {
        if(!$this->cache instanceof Cache){
            $this->cache = ($this->cacheDriver === 'memcached') 
                ? MemoryCache::getInstance(null, $persistentId ?? '__database_builder__')
                : FileCache::getInstance(null);
        }

        if($this->cacheDriver === 'memcached'){
            $this->cache->setFolder(
                'database' . ($subfolder ? DIRECTORY_SEPARATOR . trim($subfolder, TRIM_DS) : '')
            );
        }

        $this->cache->setStorage('database_' . ($storage ?? 'capture'));
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
            $inserts .= "{$column}, ";
            $placeholders .= ($value instanceof RawExpression) 
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
     * @param string $query
     *  The raw SQL query to validate.
     *
     * @return void
     *
     * @throws InvalidArgumentException
     *  Thrown when the query is:
     *  - Empty
     *  - Not starting with SELECT or WITH ... SELECT
     * - Must not contain SELECT after body parentheses block
     */
    private static function assertCte(string $query, bool $isWith = false): void
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('CTE query cannot be empty.');
        }

        // if (str_contains($query, ';')) {
        //    throw new InvalidArgumentException('CTE must not contain multiple statements.');
        // }

        if ($isWith) {
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