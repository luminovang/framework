<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Database;

use \Luminova\Cache\FileCache;
use \Luminova\Database\Connection;
use \Luminova\Database\Manager;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Time\Time;
use \Luminova\Exceptions\InvalidArgumentException;
use \DateTimeInterface;
use \Exception;

final class Builder extends Connection 
{  
    /**
     * Class instance
     * 
     * @var Builder|null $instance 
    */
    private static ?Builder $instance = null;

    /**
     * Database table name to query.
     * 
     * @var string $tableName 
    */
    private string $tableName = '';

    /**
     * Table name to join query
     * 
     * @var string $joinTable 
    */
    private string $joinTable = '';

    /**
     * Table join query type
     * 
     * @var string $joinType 
    */
    private string $joinType = '';

    /**
     * Table join bind parameters
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
    private int $maxLimit = 1;

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
     * Table query group column by
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
     * Table query and query column
     * 
     * @var array<int,mixed>  $andConditions 
    */
    private array $andConditions = [];

    /**
     * Table query update set values
     * 
     * @var array<int,mixed>  $querySetValues 
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
     * Cache class instance.
     * 
     * @var FileCache $cache 
    */
    private ?FileCache $cache = null;

    /**
     * Result return type.
     * 
     * @var string $returnType 
    */
    private string $returnType = 'object';

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
     * Join table alias.
     * 
     * @var string $jointTableAlias 
    */
    private string $jointTableAlias = '';

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
     * Query statement handler.
     * 
     * @var DatabaseInterface|bool|null $handler
    */
    private static DatabaseInterface|bool|null $handler = null;

    /**
     * Reset query properties before cloning
     * 
     * @ignore
    */
    private function __clone() 
    {
        $this->reset();
    }

    /**
     * Get database connection instance.
     * 
     * @return DatabaseInterface|null 
    */
    public function db(): ?DatabaseInterface
    {
        return $this->db;
    }

    /**
     * Class shared singleton class instance
     *
     * @return static object $instance.
     * @throws DatabaseException If the database connection fails.
    */
    public static function getInstance(): static 
    {
        return static::$instance ??= new static();
    }

    /**
     * Sets database table name to query.
     *
     * @param string $table The table name.
     * @param string $alias table alias.
     * 
     * @return self $this Class instance.
    */
    public function table(string $table, string $alias = ''): self
    {
        $this->tableName = $table;

        if($alias !== ''){
            $this->tableAlias = $alias;
        }
        return $this;
    }

    /**
     * Specifies a join operation in the query.
     *
     * @param string $table The name of the table to join.
     * @param string $type The type of join (default: "INNER").
     * @param string $alias The alias for the joined table (optional).
     * 
     * @return self Returns the instance of the class.
    */
    public function join(string $table, string $type = 'INNER', string $alias = ''): self
    {
        $this->joinType = $type;
        $this->joinTable = $table;
        
        if($alias !== ''){
            $this->jointTableAlias = $alias;
        }
        
        return $this;
    }

    /**
     * Specifies join conditions for the query.
     *
     * @param string $condition Join condition or column name.
     * @param string $operator Join operator (default: '=').
     * @param mixed $value Value to bind to the condition or another table column.
     * 
     * @example string $tbl->on('a.column', '=', 'b.column);
     * 
     * @return self Returns the instance of the class.
    */
    public function on(string $condition, string $operator, mixed $value): self
    {
        $this->joinConditions[] = "{$condition} {$operator} {$value}";

        return $this;
    }

    /**
     * Sets join table inner.
     * 
     * @param string $table The table name.
     * @param string $alias join table alias.
     * 
     * @return self $this Class instance.
    */
    public function innerJoin(string $table, string $alias = ''): self
    {
        return $this->join($table, 'INNER', $alias);
    }

    /**
     * Sets join table left
     * 
     * @param string $table The table name
     * @param string $alias join table alias
     * 
     * @return self $this Class instance.
    */
    public function leftJoin(string $table, string $alias = ''): self
    {
        return $this->join($table, 'LEFT', $alias);
    }

    /**
     * Sets join table right.
     * 
     * @param string $table The table name.
     * @param string $alias join table alias.
     * 
     * @return self $this Class instance.
    */
    public function rightJoin(string $table, string $alias = ''): self
    {
        return $this->join($table, 'RIGHT', $alias);
    }

    /**
     * Sets join table cross.
     * 
     * @param string $table The table name.
     * @param string $alias join table alias.
     * 
     * @return self $this Class instance.
    */
    public function crossJoin(string $table, string $alias = ''): self
    {
        return $this->join($table, 'CROSS', $alias);
    }

    /**
     * Set query limit for select, update.
     * 
     * @param int $limit limit threshold.
     * @param int $offset start offset query limit
     * 
     * @return self Return instance of builder class.
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
     * Set max limit for update, delete queries.
     * 
     * @param int $limit number of records to update or delete 
     * 
     * @return self Return instance of builder class.
    */
    public function max(int $limit): self
    {
        $this->maxLimit = max(0, $limit);

        return $this;
    }

    /**
     * Set result return order for query selection (e.g., "id ASC", "date DESC").
     * 
     * @param string $column The column name to index order.
     * @param string $order The order algorithm to use (either "ASC" or "DESC").
     * 
     * @return self Returns an instance of the class.
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
     * @return self Returns an instance of the class.
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
     * Set query grouping for the SELECT statement.
     * 
     * @param string $group The column name to group by.
     * 
     * @return self The class instance.
    */
    public function group(string $group): self 
    {
        $this->queryGroup[] = $group;

        return $this;
    }

    /**
     * Set query where
     * 
     * @param string $column column name.
     * @param string $operator Comparison Operator.
     * @param mixed $value Where condition value.
     * 
     * @return self Return instance of builder class.
    */
    public function where(string $column, string $operator, mixed $value): self
    {
        $placeholder = static::trimPlaceholder($column);

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
     * Set query where and
     * 
     * @param string $column column name
     * @param string $operator Comparison operator
     * @param mixed $value column key value
     * 
     * @return self Return instance of builder class.
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
     * Set update columns and values
     * 
     * @param string $column column name
     * @param mixed $value column key value
     * 
     * @return self Return instance of builder class.
    */
    public function set(string $column, mixed $value): self
    {
        $this->querySetValues[$column] = $value;

        return $this;
    }

    /**
     * Set query where or | and or
     * 
     * @param string $column column name
     * @param string $operator Comparison operator
     * @param mixed $value column key value
     * 
     * @return self Return instance of builder class.
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
     * Set query condition for OR grouping (e.g (? OR ?)).
     * 
     * @param string $column column name
     * @param string $operator Comparison operator
     * @param mixed $value column key value
     * @param string $orColumn column name
     * @param string $orOperator Comparison operator
     * @param mixed $orValue column or key value
     * 
     * @return self Return instance of builder class.
     * @deprecated This method is deprecated and will be removed in a future release, use `orGroup` instead.
    */
    public function andor(
        string $column, 
        string $operator, 
        mixed $value, 
        string $orColumn, 
        string $orOperator, 
        mixed $orValue
    ): self
    {
        $conditions = [];
        $conditions[][$column] = [
            'operator' => $operator,
            'value' => $value,
        ];
        $conditions[][$orColumn] = [
            'operator' => $orOperator,
            'value' => $orValue,
        ];

        return $this->orGroup($conditions);
    }

    /**
     * Adds a group of conditions combined with OR to the query.
     *
     * @example 'WHERE (foo = 1 OR bar = 2)'.
     * 
     * @param array $conditions Array of conditions to be grouped with OR.
     * 
     * @return self Return instance of builder class.
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
     * Adds two groups of conditions combined with OR to the query.
     *
     * @example 'WHERE ((foo = 1 OR bar = 2) OR (baz = 3 AND bra = 4))'.
     * 
     * @param array $group1 First group of conditions.
     * @param array $group2 Second group of conditions.
     * @param string $bind The type of logical operator to use in binding groups (default: 'OR').
     *      - `AND` or `OR`.
     * 
     * @return self Return instance of builder class.
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
     * Adds a group of conditions combined with AND to the query.
     *
     * @example 'WHERE (foo = 1 AND bar = 2)'.
     * 
     * @param array $conditions Array of conditions to be grouped with AND.
     * 
     * @return self Return instance of builder class.
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
     * Adds two groups of conditions combined with AND condition.
     * 
     * @example 'WHERE ((foo = 1 AND bar = 2) AND (baz = 3 AND bra = 4))'.
     * 
     * @param array $group1 First group of conditions.
     * @param array $group2 Second group of conditions.
     * @param string $bind The type of logical operator to use in binding groups (default: 'AND').
     *      - `AND` or `OR`.
     * 
     * @return self Return instance of builder class.
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
     * Set query where IN () expression
     * 
     * @param string $column column name.
     * @param array $lists of values.
     * 
     * @return self Return instance of builder class.
    */
    public function in(string $column, array $lists = []): self
    {
        if ($lists === []) {
            return $this;
        }

        $values = static::quotedValues($lists);

        $this->andConditions[] = [
            'type' => 'IN', 
            'column' => $column, 
            'values' => $values
        ];

        return $this;
    }

    /**
     * Set query where FIND_IN_SET() expression
     * 
     * @param string $search search value
     * @param string $operator allow specifying the operator for matching (e.g., > or =)
     * @param array $list of values
     * 
     * @return self Return instance of builder class.
    */
    public function inset(string $search, string $operator = '=', array $list = []): self
    {
        if($list === []){
            return $this;
        }

        $this->andConditions[] = [
            'type' => 'IN_SET', 
            'list' => implode(',', $list), 
            'search' => $search, 
            'operator' => $operator
        ];

        return $this;
    }

    /**
     * Set result return type type, object or array (default: object).
     * 
     * @param string $type Return type 'object' or 'array'.
     * 
     * @return self Return instance of builder class.
     * @throws InvalidArgumentException Throws if an invalid type is provided.
    */
    public function returns(string $type): self
    {
        $type = strtolower($type);

        if($type === 'object' || $type === 'array'){
            $this->returnType = $type;
            
            return $this;
        }

        throw new InvalidArgumentException('Invalid return type "' . $type . '", expected array or object');
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
     * @return string Formatted date/time/timestamp.
     */
    public static function datetime(string $format = 'datetime', ?int $timestamp = null): string
    {
        $format = ($format === 'time') ? 'H:i:s' : (($format === 'date') ? 'Y-m-d' : 'Y-m-d H:i:s');
        $time = ($timestamp === null) ? Time::now() : Time::fromTimestamp($timestamp);

        return $time->format($format);
    }

    /**
     * Enable or disabled all caching 
     *
     * @param bool $enable Status action.
     * 
     * @return self Return instance of builder class.
    */
    public function caching(bool $enable): self
    {
        $this->caching = $enable;

        return $this;
    }

    /**
     * Cache the query result using a specified storage.
     *
     * @param string $key The storage cache key
     * @param string $storage Private storage name hash name (optional): but is recommended to void storing large data in one file.
     * @param DateTimeInterface|int $expiry The cache expiry time in seconds (default: 7 days).
     * @param string|null $folder Optionally set a folder name to store caches.
     * 
     * @return self Return instance of builder class.
    */
    public function cache(
        string $key, 
        string $storage = null, 
        DateTimeInterface|int $expiry = 7 * 24 * 60 * 60, 
        ?string $folder = null
    ): self
    {
        if($this->caching){
            $storage ??=  'database_' . ($this->tableName ?? 'capture');
            $folder = ($folder === null) ? '' : DIRECTORY_SEPARATOR . trim($folder, DIRECTORY_SEPARATOR);
            $this->cache = FileCache::getInstance(null, 'database' . $folder);
            $this->cache->setStorage($storage);
            $this->cache->setExpire($expiry);
            $this->cache->create();
            $this->cacheKey = md5($key);

            // Check if the cache exists and handle expiration
            if ($this->cache->hasItem($this->cacheKey)) {
                $this->hasCache = true;
                if ($this->cache->hasExpired($this->cacheKey)) {
                    $this->cache->deleteItem($this->cacheKey);
                    $this->hasCache = false;
                }
            }
        }

        return $this;
    }

    /**
     * Insert records into table.
     * 
     * @param array<string, mixed> $values array of values to insert into table.
     * @param bool $prepare Use bind values and execute prepare statement instead of query.
     * 
     * @return int Return number of affected rows.
    */
    public function insert(array $values, bool $prepare = true): int
    {
        if ($values === []) {
            return 0;
        }

        if (!isset($values[0])) {
            $values = [$values];
        }
        
        if (!is_associative($values[0])) {
            return 0;
        }

        static::$handler = null;
        $columns = array_keys($values[0]);

        try {
            if($prepare){
                return $this->executeInsertPrepared($columns, $values);
            }
            return $this->executeInsertQuery($columns, $values);
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }
        return 0;
    }

    /**
     * Build a custom SQL query string to execute when calling the execute method.
     * This method also supports caching and uses prepared statements if array values are passed to the execute method.
     * Otherwise, it uses query execution to execute the query, so ensure that values passed directly to the query are escaped.
     * 
     * @param string $query The SQL query string.
     * 
     * @return self Returns an instance of the builder class.
     * @throws DatabaseException When the query is empty.
     */
    public function query(string $query): self 
    {
        if (empty($query)) {
            throw new DatabaseException("Builder operation without a query condition is not allowed.");
        }

        $this->buildQuery = $query;

        return $this;
    }

    /**
     * Executes an SQL query string that was previously prepared in the `query()` method.
     * 
     * @param array<string,mixed>|null $placeholder Binds placeholder and value to the query.
     * @param int $mode Result return mode RETURN_* (default: RETURN_ALL).
     *                - RETURN_ALL: Returns all rows (default).
     *                - RETURN_NEXT: Returns the single/next row from the result set.
     *                - RETURN_2D_NUM: Returns a 2D array with numerical indices.
     *                - RETURN_ID: Returns the last inserted ID.
     *                - RETURN_INT: Returns an integer count of records.
     *                - RETURN_STMT: Returns an prepared statement object.
     * 
     * @return mixed|DatabaseInterface Returns the query result, prepared statement object, otherwise false on failure.
     * @throws DatabaseException Throws if called executed without query conditions.
     */
    public function execute(?array $placeholder = null, int $mode = RETURN_ALL): mixed 
    {
        $placeholder ??= [];
        static::$handler = null;

        if($mode !== RETURN_STMT && $this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();

                return $response;
            }
        }

        if($this->buildQuery === ''){
            throw new DatabaseException("Execute operation without a query condition is not allowed. call query() before execute()");
        }

        $this->bindValues = $placeholder;

        try {
            if($mode === RETURN_STMT || $this->cache === null){
                return $this->returnExecute($this->buildQuery, $mode);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($mode) {
                return $this->returnExecute($this->buildQuery, $mode);
            });
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
    */
    public function total(string $column = '*'): int|bool 
    {
        return $this->createQueryExecution("SELECT COUNT({$column})");
    }

    /**
     * Calculate the total sum of a numeric column in the table.
     * 
     * @param string $column The column to calculate the sum.
     * 
     * @return int|float|bool Return total sum columns, otherwise false if execution failed.
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
     * @throws DatabaseException If where method was not called.
    */
    public function find(array $columns = ['*']): mixed 
    {
        if ($this->whereCondition === []) {
            throw new DatabaseException('Find cannot be called without a where method being called first.');
        }
        
        return $this->createQueryExecution('', 'find', $columns);
    }

    /**
     * Select records from table, by passing desired fetch mode and result type.
     * 
     * @param string $result The fetch result type (next or all).
     * @param int $mode The fetch result mode FETCH_*.
     * @param array<int,string> $columns The table columns to return (default: *).
     * 
     * @return object|null|array|int|float|bool Return selected records, otherwise false if execution failed.
     * @throws DatabaseException If where method was not called.
    */
    public function fetch(string $result = 'all', int $mode = FETCH_OBJ, array $columns = ['*']): mixed 
    {
        if ($result === 'all' || $result === 'next') {
            return $this->createQueryExecution('', 'fetch', $columns, $result, $mode);
        }

        throw new DatabaseException('Invalid fetch result type, expected "all or next".');
    }

    /**
     * Returns query prepared statement based on build up method conditions.
     * 
     * @param array<int,string> $columns The table columns to return (default: *).
     * 
     * @return DatabaseInterface Return prepared statement if query is successful otherwise null.
    */
    public function stmt(array $columns = ['*']): DatabaseInterface|null
    {
        $this->returnType = 'stmt';
        if($this->createQueryExecution('', 'stmt', $columns)){
            return static::$handler;
        }

        $this->free();
        static::$handler?->free();
        static::$handler = null;

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
    */
    private function createQueryExecution(
        string $query, 
        string $return = 'total', 
        array $columns = ['*'], 
        string $result = 'all', 
        int $mode = FETCH_OBJ
    ): mixed
    {
        static::$handler = null;
        if(!$this->printQuery && $return !== 'stmt' && $this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';

                $this->reset();
                return $response;
            }
        }

        if($return === 'select' || $return === 'find' || $return === 'stmt'|| $return === 'fetch'){
            $columns = ($columns === ['*']) ? '*' : implode(", ", $columns);
            $query = "SELECT {$columns}";
        }

        $sqlQuery = "{$query} FROM {$this->tableName} {$this->tableAlias}";

        if ($this->joinTable !== '') {
            $sqlQuery .= " {$this->joinType} JOIN {$this->joinTable} {$this->jointTableAlias}";

            if ($this->joinConditions !== []) {
                $sqlQuery .= " ON {$this->joinConditions[0]}";

                if(($joins = count($this->joinConditions)) > 1){
                    for ($i = 1; $i < $joins; $i++) {
                        $sqlQuery .= " AND {$this->joinConditions[$i]}";
                    }
                }
            } 
        }
    
        try {
            if($this->printQuery || $return === 'stmt' || $this->cache === null){
                return $this->returnExecutedResult($sqlQuery, $return, $result, $mode);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($sqlQuery, $return, $result, $mode) {
                return $this->returnExecutedResult($sqlQuery, $return, $result, $mode);
            });
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
    */
    private function returnExecutedResult(
        string $sqlQuery, 
        string $return = 'total', 
        string $result = 'all', 
        int $mode = FETCH_OBJ
    ): mixed
    {
        $isBided = false;
        $isOrdered = false;
        $response = false;

        if ($this->whereCondition === []) {
            // When using IN as WHERE and it has other ANDs ORs as binding.
            $isBided = $this->andConditions !== [];
            $this->buildAndConditions($sqlQuery, $isBided);
        }else{
            $isBided = true;
            $sqlQuery .= $this->whereCondition['query'];
            $this->buildWhereConditions($sqlQuery, $isBided);
        }

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

        if($this->printQuery){
            return $this->printDebugQuery($sqlQuery, $return);
        }

        if($isBided){
            static::$handler = $this->db->prepare($sqlQuery);
            if ($this->whereCondition !== []) {
                static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            }
            $this->bindConditions(static::$handler);
            static::$handler->execute();
        }else{
            static::$handler = $this->db->query($sqlQuery);
        }

        if(static::$handler->ok()){
            if($return === 'stmt'){
                $response = true;
            }elseif($return === 'select'){
                $response = static::$handler->getAll($this->returnType);
            }elseif($return === 'find'){
                $response = static::$handler->getNext($this->returnType);
            }elseif($return === 'total'){
                $response = static::$handler->getCount();
            }elseif($return === 'fetch'){
                $response = static::$handler->fetch($result, $mode);
            }else{
                $response = static::$handler->getNext()->totalCalc ?? 0;
            }
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
     * @throws DatabaseException Throw if error occurred while updating.
     */
    public function update(?array $setValues = []): int 
    {
        $columns = ($setValues === []) ? $this->querySetValues : $setValues;
        static::$handler = null;

        if ($columns === []) {
            throw new DatabaseException('Update operation without SET values is not allowed.');
        }

        if ($this->whereCondition === []) {
            throw new DatabaseException('Update operation without a WHERE condition is not allowed.');
        }

        $updateColumns = static::buildPlaceholder($columns, true);
        $updateQuery = "UPDATE {$this->tableName} SET {$updateColumns}";
        $updateQuery .= $this->whereCondition['query'];
        $this->buildWhereConditions($updateQuery);

        if($this->maxLimit > 0){
            $updateQuery .= " LIMIT {$this->maxLimit}";
        }

        if($this->printQuery){
            $this->printDebugQuery($updateQuery, 'update');
            return 0;
        }

        try {
            static::$handler = $this->db->prepare($updateQuery);
            foreach($columns as $key => $value){
                if(!is_string($key) || $key === '?'){
                    throw new DatabaseException("Invalid update key {$key}, update key must be a valid table column name.");
                }

                $value = is_array($value) ? json_encode($value) : $value;
                static::$handler->bind(static::trimPlaceholder($key), $value);
            }
            static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions(static::$handler);
            static::$handler->execute();

            $response = (static::$handler->ok() ? static::$handler->rowCount() : 0);
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
        static::$handler = null;

        if ($this->whereCondition === []) {
            throw new DatabaseException('Delete operation without a WHERE condition is not allowed.');
        }

        $deleteQuery = "DELETE FROM {$this->tableName}";
        $deleteQuery .= $this->whereCondition['query'];
        $this->buildWhereConditions($deleteQuery);

        if($this->maxLimit > 0){
            $deleteQuery .= " LIMIT {$this->maxLimit}";
        }

        if($this->printQuery){
            $this->printDebugQuery($deleteQuery, 'delete');
            return 0;
        }

        try {
            static::$handler = $this->db->prepare($deleteQuery);
            static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions(static::$handler);
            static::$handler->execute();

            $response = (static::$handler->ok() ? static::$handler->rowCount() : 0);
            $this->reset();

            return $response;
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
            static::$handler = $this->db->query($buildQuery);
        }else{
            static::$handler = $this->db->prepare($buildQuery);
            foreach ($this->bindValues as $key => $value) {
                if(!is_string($key) || $key === '?'){
                    throw new DatabaseException("Invalid bind placeholder {$key}, placeholder key must be same with your table mapped column key, example :foo");
                }

                static::$handler->bind(static::trimPlaceholder($key), $value);
            } 
            static::$handler->execute();
        }

        $response = (static::$handler->ok() ? 
            (($mode === RETURN_STMT) ? static::$handler : static::$handler->getItem($mode, $this->returnType)) : false);
        $this->reset();

        return $response;
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
     */
    public function transaction(int $flags = 0, ?string $name = null): bool 
    {
        return $this->db->beginTransaction($flags, $name);
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
        return $this->db->commit($flags, $name);
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
        return $this->db->rollback($flags, $name);
    }

    /**
     * Truncate database table records.
     * If transaction failed rollback to default.
     * 
     * @param bool $transaction Weather to use transaction.
     * 
     * @return bool Return true truncation was completed, otherwise false.
     * @throws DatabaseException Throws if an error occured during execution.
    */
    public function truncate(bool $transaction = true): bool 
    {
        try {
            $driverName = $this->db->getDriver();
            $transaction = ($transaction && $driverName !== 'sqlite');

            if ($transaction) {
                $this->db->beginTransaction();
            }

            if ($driverName === 'mysql' || $driverName === 'pgsql') {
                $completed = $this->db->exec("TRUNCATE TABLE {$this->tableName}");
            } elseif ($driverName === 'sqlite') {
                $deleteSuccess = $this->db->exec("DELETE FROM {$this->tableName}");
                $resetSuccess = true;

                $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='sqlite_sequence'")->getNext('array');
                if ($result) {
                    $resetSuccess = $this->db->exec("DELETE FROM sqlite_sequence WHERE name = '{$this->tableName}'");
                }

                $completed = $deleteSuccess && $resetSuccess;

                if ($completed && !$this->db->exec("VACUUM")) {
                    $completed = false;
                }
            } else {
                $deleteSuccess = $this->db->exec("DELETE FROM {$this->tableName}");
                $resetSuccess = $this->db->exec("ALTER TABLE {$this->tableName} AUTO_INCREMENT = 1");

                $completed = $deleteSuccess && $resetSuccess;
            }

            if ($transaction) {
                if ($completed) {
                    $this->db->commit();
                    return true;
                }

                $this->db->rollback();
                return false;
            }

            return (bool) $completed;

        } catch (DatabaseException|Exception $e) {
            if ($transaction) {
                $this->db->rollback();
            }

            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Execute an SQL query string and return the number of affected rows.
     * 
     * @param string $query Query string to execute.
     * 
     * @return int Return number affected rows.
     * @throws DatabaseException Throws if error occurs.
    */
    public function exec(string $query): int 
    {
        try {
            return $this->db->exec($query);
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Drop database table if table exists.
     * 
     * @return int Return number affected rows.
     * @throws DatabaseException Throws if error occurs.
    */
    public function drop(): int 
    {
        try {
            $return = $this->db->exec("DROP TABLE IF EXISTS {$this->tableName}");
            $this->reset();
            return $return;
        } catch (DatabaseException|Exception $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Execute insert query.
     * 
     * @param array $columns column name to target insert.
     * @param array $values array of values to insert.
     * 
     * @return int Return number affected row.
    */
    private function executeInsertQuery(array $columns, array $values): int 
    {
        $inserts = '';
        foreach ($values as $row) {
            $inserts .= "(" . static::quotedValues($row) . "), ";
        }

        $keys = implode(', ', $columns);
        $inserts = rtrim($inserts, ', ');
        $insertQuery = "INSERT INTO {$this->tableName} ({$keys}) VALUES {$inserts}";

        if($this->printQuery){
            $this->printDebugQuery($insertQuery, 'insert');
            return 0;
        }
        
        static::$handler = $this->db->query($insertQuery);
        $response = (static::$handler->ok() ? static::$handler->rowCount() : 0);

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
    */
    private function executeInsertPrepared(array $columns, array $values): int
    {
        $count = 0;
        [$placeholders, $inserts] = self::mapParams($columns);
        $insertQuery = "INSERT INTO {$this->tableName} ({$inserts}) VALUES ($placeholders)";
       
        if($this->printQuery){
            $this->printDebugQuery($insertQuery, 'insert', $values);
            return 0;
        }

        static::$handler = $this->db->prepare($insertQuery);
    
        foreach ($values as $row) {
            foreach ($row as $key => $value) {
                $value = is_array($value) ? json_encode($value) : $value;
                static::$handler->bind(static::trimPlaceholder($key), $value);
            }

            static::$handler->execute();

            if(static::$handler->ok()){
                $count++;
            }
        }

        $this->reset();
        return $count;
    } 

    /**
     * Build query conditions.
     *
     * @param string $query The SQL query string to which conditions passed by reference.
     * @param bool $isBided Wether the param is bind params (default: true).
     * 
     * @return void
    */
    private function buildWhereConditions(string &$query, bool $isBided = true): void
    {
        if ($this->andConditions === []) {
            return;
        }

        foreach ($this->andConditions as $index => $condition) {
            $query .= match ($condition['type']) {
                'GROUP_OR' => " AND " . self::buildGroupConditions($condition['conditions'], $index, $isBided, 'OR'),
                'GROUP_AND' => " AND " . self::buildGroupConditions($condition['conditions'], $index, $isBided, 'AND'),
                'BIND_OR' => " AND " . self::buildGroupBindConditions($condition['X'], $condition['Y'], $index, $isBided, 'OR', $condition['bind']),
                'BIND_AND' => " AND " . self::buildGroupBindConditions($condition['X'], $condition['Y'], $index, $isBided, 'AND', $condition['bind']),
                default => self::buildSingleWhereConditions($condition, $index, $isBided),
            };
        }
    }

    /**
     * Build query for ands conditions.
     *
     * @param string $query The SQL query string to which search conditions passed by reference.
     * @param bool $isBided Wether the param is bind params (default: true).
     * 
     * @return void
    */
    private function buildAndConditions(string &$query, bool $isBided = true): void
    {
        if ($this->andConditions === []) {
            return;
        }

        $query .= ' WHERE ';
        $firstCondition = true;

        foreach ($this->andConditions as $index => $condition) {
            if (!$firstCondition) {
                $query .= ($condition['type'] === 'OR') ? ' OR' : ' AND';
            }

            $query .= match ($condition['type']) {
                'GROUP_OR' => self::buildGroupConditions($condition['conditions'], $index, $isBided, 'OR'),
                'GROUP_AND' => self::buildGroupConditions($condition['conditions'], $index, $isBided, 'AND'),
                'BIND_OR' => self::buildGroupBindConditions($condition['X'], $condition['Y'], $index, $isBided, 'OR', $condition['bind']),
                'BIND_AND' => self::buildGroupBindConditions($condition['X'], $condition['Y'], $index, $isBided, 'AND', $condition['bind']),
                default => self::buildSingleAndCondition($condition, $index, $isBided),
            };

            $firstCondition = false;
        }
    }

    /**
     * Constructs a single ANDs condition query string with placeholders for binding values.
     *
     * @param array   $condition  An array representing the search condition.
     * @param int     $index      The index to append to the placeholder names.
     * @param bool    $isBided    Indicates whether placeholders should be used for binding values (default: true).
     *
     * @return string Return query string representation of the single AND condition.
     */
    private static function buildSingleAndCondition(array $condition, int $index, bool $isBided = true): string
    {
        $operator = $condition['operator'] ?? '=';
        $column = $condition['column'];
        $placeholder = ($isBided ? 
            (($condition['type'] === 'AGAINST') ? ":match_column_{$index}" : static::trimPlaceholder($column)) : 
            addslashes($condition['value'])
        );

        return match ($condition['type']) {
            'IN' => " {$column} IN ({$condition['values']})",
            'AGAINST' => " MATCH($column) AGAINST ({$placeholder} {$operator})",
            'AND', 'OR' => " $column $operator $placeholder",
            'IN_SET' => ($operator === '>') ?
                " FIND_IN_SET('{$condition['search']}', '{$condition['list']}') > 0" :
                " FIND_IN_SET('{$condition['search']}', '{$condition['list']}')",
            'LIKE' => " $column LIKE ?",
            default => '',
        };
    }

    /**
     * Constructs a single condition query string with placeholders for binding values.
     *
     * @param array   $condition  An array representing the condition.
     * @param int     $index      The index to append to the placeholder names.
     * @param bool    $isBided    Indicates whether placeholders should be used for binding values (default: true).
     *
     * @return string Return query string representation of the single condition.
     */
    private static function buildSingleWhereConditions(array $condition, int $index, $isBided = true): string
    {
        $column = $condition['column'];
        $operator = $condition['operator'] ?? '=';
        $placeholder = ($isBided ? 
            (($condition['type'] === 'AGAINST') ? ":match_column_{$index}" : static::trimPlaceholder($column)) : 
            addslashes($condition['value'])
        );

        return match ($condition['type']) {
            'AND' => " AND $column $operator $placeholder",
            'OR' => " OR $column $operator $placeholder",
            'IN' => " AND $column IN ({$condition['values']})",
            'AGAINST' => " AND MATCH($column) AGAINST ({$placeholder} {$operator})",
            'IN_SET' => ($operator === '>') ?
                " AND FIND_IN_SET('{$condition['search']}', '{$condition['list']}') > 0" :
                " AND FIND_IN_SET('{$condition['search']}', '{$condition['list']}')",
            'LIKE' => " AND $column LIKE ?",
            default => '',
        }; 
    }

    /**
     * Builds a query string representation of single grouped conditions.
     *
     * @example 'SELECT * FROM foo WHERE (bar = 1 AND baz = 2)'.
     * @example 'SELECT * FROM foo WHERE (boz = 1 OR bra = 2)'.
     *
     * @param array   $conditions   An array of conditions to be grouped.
     * @param int     $index        The index to append to the placeholder names.
     * @param bool    $isBided      Indicates whether placeholders should be used for binding values (default: true).
     * @param string  $type         The type of logical operator to use between conditions within the group (default: 'OR').
     * @param int     &$last        Reference to the total count of conditions processed so far across all groups.
     *
     * @return string Return query string representation of grouped conditions with placeholders.
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
            $placeholder = $isBided ? static::trimPlaceholder("{$column}_{$index}_" . ($idx + $last + 1)) : addslashes($condition[$column]['value']);

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
     * @example 'SELECT * FROM foo WHERE ((bar = 1 AND baz = 2) AND (boz = 1 AND bra = 5))'.
     * @example 'SELECT * FROM foo WHERE ((bar = 1 OR baz = 2) OR (boz = 1 OR bra = 5))'.
     *
     * @param array   $conditionsX  An array of conditions for the first group.
     * @param array   $conditionsY  An array of conditions for the second group.
     * @param int     $index        The index to append to the placeholder names.
     * @param bool    $isBided      Indicates whether placeholders should be used for binding values (default: true).
     * @param string  $type         The type of logical operator to use between groups (default: 'OR').
     * @param string  $bind         The type of logical operator to use in binding groups (default: 'OR').
     *
     * @return string Return a query string representation of grouped conditions with placeholders.
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
     * Bind query where conditions
     * 
     * @param DatabaseInterface &$handler Database handler passed by reference.
     * 
     * @return void
    */
    private function bindConditions(DatabaseInterface &$handler): void 
    {
        if($this->andConditions !== []) {
            foreach ($this->andConditions as $index => $bindings) {
                switch ($bindings['type']) {
                    case 'AGAINST':
                        $handler->bind(":match_column_{$index}", $bindings['value']);
                    break;
                    case 'GROUP_OR':
                    case 'GROUP_AND':
                        self::bindGroupConditions($bindings['conditions'], $handler, $index);
                    break;
                    case 'BIND_OR':
                    case 'BIND_AND':
                        $last = 0;
                        self::bindGroupConditions($bindings['X'], $handler, $index, $last);
                        self::bindGroupConditions($bindings['Y'], $handler, $index, $last);
                    break;
                    default:
                        $handler->bind(static::trimPlaceholder($bindings['column']), $bindings['value']);
                    break;
                }
            }
        }

        if($this->queryMatchOrder !== []){
            foreach($this->queryMatchOrder as $idx => $order){
                $handler->bind(":match_order_{$idx}", $order['value']);
            }
        }
    }

    /**
     * Bind group conditions to the database handler.
     *
     * @param array               $bindings  An array of conditions to bind.
     * @param DatabaseInterface   $handler   The database handler to bind the values to.
     * @param int                 $index     The index to append to the placeholder names.
     * @param int                 &$last     A reference to the last counter used to ensure unique placeholder names.
     *
     * @return void
     */
    private function bindGroupConditions(array $bindings, DatabaseInterface &$handler, int $index, int &$last = 0): void 
    {
        $count = 0;
        foreach ($bindings as $idx => $bind) {
            $column = key($bind);
            $placeholder = static::trimPlaceholder("{$column}_{$index}_" . ($idx + $last + 1));
            $handler->bind($placeholder, $bind[$column]['value']);
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
     */
    private function printDebugQuery(string $query, string $method, array $values = []): bool
    {
        $params = [];
        if($method === 'insert'){
            foreach($values as $bindings){
                $column = key($bindings);
                $value = is_array($bindings[$column]) ? json_encode($bindings[$column]) : $bindings[$column];
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
                        $placeholder = static::trimPlaceholder($bindings['column']);
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
            logger('debug', json_encode( $this->debugQuery, JSON_PRETTY_PRINT));
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
            $value =  ($isBided ? ":order_match_{$idx}" : 
                (is_string($order['value']) ? "'" . addslashes($order['value']) . "'" :
                 $order['value'])
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
    private function bindDebugGroupConditions(array $bindings, int $index, array &$params = [], int &$last = 0): void 
    {
        $count = 0;
        foreach ($bindings as $idx => $bind) {
            $column = key($bind);
            $placeholder = static::trimPlaceholder("{$column}_{$index}_" . ($idx + $last + 1));
            $params[] = "{$placeholder} = " . $bind[$column]['value'];
            $count++;
        }
        $last += $count;
    }

    /**
     * Retrieves the database manager instance.
     * 
     * Returns a singleton instance of the Manager class initialized with the current database connection.
     * 
     * @return Manager Database manager class instance.
     * 
     * @see https://luminova.ng/docs/0.0.0/database/manager
     */
    public function manager(): Manager 
    {
        static $manager = null;
        $manager ??= new Manager($this->db);

        return $manager;
    }

    /**
     * Exports a database table and downloads it to the browser as JSON or CSV format.
     * 
     * @param string $as Export as csv or json format.
     * @param string|null $filename Filename to download.
     * @param array $columns Table columns to export (default: all).
     * 
     * @return bool True if export is successful, false otherwise.
     * 
     * @throws DatabaseException If an invalid format is provided or if unable to create the export.
     */
    public function export(string $as = 'csv', ?string $filename = null, array $columns = ['*']): bool 
    {
        $manager = $this->manager();
        $manager->setTable($this->tableName);

        return $manager->export($as, $filename, $columns);
    }

    /**
     * Creates a backup of the database.
     * 
     * @param string|null $filename Filename to store the backup.
     * 
     * @return bool True if backup is successful, false otherwise.
     * 
     * @throws DatabaseException If unable to create the backup directory or if failed to create the backup.
     */
    public function backup(?string $filename = null): bool 
    {
        return $this->manager()->backup($filename);
    }

    /**
     * Trim placeholder and remove`()` if present
     *
     * @param string $input 
     * 
     * @return string $placeholder
    */
    private static function trimPlaceholder(string $input): string 
    {
        if (preg_match('/\(([^)]+)\)/', $input, $matches)) {
            $input = $matches[1];
        }
    
        $lastDotPosition = strrpos($input, '.');
        $placeholder = ($lastDotPosition === false) ? $input : substr($input, $lastDotPosition + 1);
  
        return ":$placeholder";
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
     * Convert array keys to placeholders key = :key
     * 
     * @param array $columns columns
     * @param bool $implode should implode or just return the array
     * 
     * @return array|string 
    */
    private static function buildPlaceholder(array $columns, bool $implode = false): array|string
    {
        $updateColumns = [];
        foreach ($columns as $column => $value) {
            $updateColumns[] = "$column = :$column";
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
     */
    private static function quotedValues(array $columns, string $return = 'string'): array|string
    {
        $quoted = [];
        $string = '';
        foreach ($columns as $item) {
            if(is_array($item)){
                $value = "'" . json_encode($item) . "'" ;
            }elseif(is_numeric($item)){
                $value = $item;
            }else{
                $value = "'" . addslashes($item) . "'";
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

    /**
     * Debug dump statement information for the last statement execution.
     *
     * @return bool|null trues else false or null.
    */
    public function dump(): bool|null
    {
        return $this->db->dumpDebug();
    }
    
    /**
     * Reset query conditions and Free database resources
     * 
     * @return void 
    */
    public function reset(): void 
    {
        $this->tableName = ''; 
        $this->jointTableAlias = '';
        $this->tableAlias = '';
        $this->joinTable = '';
        $this->joinType = '';
        $this->joinConditions = [];
        $this->selectLimit = '';
        $this->maxLimit = 1;
        $this->queryOrder = [];
        $this->queryMatchOrder = [];
        $this->queryGroup = [];
        $this->whereCondition = [];
        $this->andConditions = [];
        $this->querySetValues = [];
        $this->hasCache = false;
        $this->cache = null;
        $this->printQuery = false;
        $this->bindValues = [];
        $this->buildQuery = '';
        if($this->returnType !== 'stmt'){
            $this->free();
            static::$handler?->free();
            static::$handler = null;
        }
        $this->returnType = 'object';
    }

    /**
     * Free database resources
     * 
     * @return void 
    */
    public function free(): void 
    {
        $this->db?->free();
    }

    /**
     * Close database connection
     * 
     * @return void 
    */
    public function close(): void 
    {
        $this->db?->close();
    }
}