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

use \Luminova\Database\Scheme;
use \Luminova\Cache\FileCache;
use \Luminova\Database\Connection;
use \Luminova\Database\Manager;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Time\Time;
use \Luminova\Exceptions\InvalidArgumentException;
use \DateTimeInterface;

final class Builder extends Connection 
{  
    /**
     * Class instance
     * 
     * @var Builder|null $instance 
    */
    private static ?Builder $instance = null;

    /**
     * Table name to query
     * 
     * @var string $databaseTable 
    */
    private string $databaseTable = '';

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
     * Table query order limit offset and count query 
     * 
     * @var string $queryLimit 
    */
    private string $queryLimit = '';

    /**
     * Table query updatem delete limit
     * 
     * @var int $queryLimit 
    */
    private int $maxLimit = 1;

    /**
     * Table query order rows 
     * 
     * @var array<int, string> $queryOrder 
    */
    private array $queryOrder = [];

    /**
     * Table query group column by
     * 
     * @var array<int, string> $queryGroup 
    */
    private array $queryGroup = [];

    /**
     * Table query where column
     * 
     * @var array $whereCondition 
    */
    private array $whereCondition = [];

    /**
     * Table query and query column
     * 
     * @var array $andConditions 
    */
    private array $andConditions = [];

    /**
     * able query update set values
     * 
     * @var array $querySetValues 
    */
    private array $querySetValues = [];

    /**
     * Has Cache flag
     * 
     * @var bool $hasCache 
    */
    private bool $hasCache = false;

    /**
     * Caching status flag
     * 
     * @var bool $caching 
    */
    private bool $caching = true;

    /**
     * Cache class instance
     * 
     * @var FileCache $cache 
    */
    private ?FileCache $cache = null;

    /**
     * Result return type
     * 
     * @var string $returnType 
    */
    private string $returnType = 'object';

    /**
     * Cache key
     * @var string $cacheKey 
    */
    private string $cacheKey = "default";

    /**
     * Table alias
     * @var string $tableAlias 
    */
    private string $tableAlias = '';

    /**
     * Join table alias
     * @var string $jointTableAlias 
    */
    private string $jointTableAlias = '';

    /**
     * Bind values 
     * @var array $bindValues 
    */
    private array $bindValues = [];

    /**
     * Query builder 
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
     * Returns last prepared statement.
     * 
     * @return DatabaseInterface
    */
    public function stmt(): DatabaseInterface
    {
        return static::$handler;
    }

    /**
     * Class shared singleton class instance
     *
     * @return static object $instance
     * @throws DatabaseException If the database connection fails.
    */
    public static function getInstance(): static 
    {
        return static::$instance ??= new static();
    }

    /**
     * Sets database table name to query.
     *
     * @param string $table The table name
     * @param string $alias table alias
     * 
     * @return self $this Class instance.
    */
    public function table(string $table, string $alias = ''): self
    {
        $this->databaseTable = $table;

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
     * Sets join table inner
     * 
     * @param string $table The table name
     * @param string $alias join table alias
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
     * Sets join table right
     * 
     * @param string $table The table name
     * @param string $alias join table alias
     * 
     * @return self $this Class instance.
    */
    public function rightJoin(string $table, string $alias = ''): self
    {
        return $this->join($table, 'RIGHT', $alias);
    }

    /**
     * Sets join table cross
     * 
     * @param string $table The table name
     * @param string $alias join table alias
     * 
     * @return self $this Class instance.
    */
    public function crossJoin(string $table, string $alias = ''): self
    {
        return $this->join($table, 'CROSS', $alias);
    }

    /**
     * Set query limit
     * 
     * @param int $limit limit threshold 
     * @param int $offset start offset query limit
     * 
     * @return self class instance.
    */
    public function limit(int $limit = 0, int $offset = 0): self
    {
        if($limit > 0){
            $offset = max(0, $offset);
            $this->queryLimit = " LIMIT {$offset},{$limit}";
        }

        return $this;
    }

    /**
     * Set max limit for update, delete queries.
     * 
     * @param int $limit number of records to update or delete 
     * 
     * @return self class instance.
    */
    public function max(int $limit): self
    {
        $this->maxLimit = max(1, $limit);

        return $this;
    }

    /**
     * Set the order for the query results in a select statement (e.g., "id ASC", "date DESC").
     * 
     * @param string $column The column name to set the order for.
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
     * @return self class instance.
    */
    public function where(string $column, string $operator, mixed $value): self
    {
        $placeholder = static::trimPlaceholder($column);

        $this->whereCondition = [
            'type' => 'WHERE', 
            'query' => " WHERE {$column} {$operator} {$placeholder}",
            'value' => $value,
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
     * @return self class instance.
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
     * Set update columns and values
     * 
     * @param string $column column name
     * @param mixed $value column key value
     * 
     * @return self class instance.
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
     * @return self class instance.
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
     * Set query AND (? OR ?)
     * 
     * @param string $column column name
     * @param string $operator Comparison operator
     * @param mixed $value column key value
     * @param string $orColumn column name
     * @param string $orOperator Comparison operator
     * @param mixed $orValue column or key value
     * 
     * @return self class instance.
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
        $this->andConditions[] = [
            'type' => 'AND_OR', 
            'column' => $column, 
            'value' => $value,
            'operator' => $operator,
            'orColumn' => $orColumn, 
            'orValue' => $orValue,
            'orOperator' => $orOperator,
        ];

        return $this;
    }

    /**
     * Set query where IN () expression
     * 
     * @param string $column column name.
     * @param array $lists of values.
     * 
     * @return self class instance.
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
     * @return self class instance.
    */
    public function inset(string $search, string $operator = '=', array $list = []): self
    {
        if($list === []){
            return $this;
        }

        $values = implode(',', $list);
        $this->andConditions[] = [
            'type' => 'IN_SET', 
            'list' => $values, 
            'search' => $search, 
            'operator' => $operator
        ];

        return $this;
    }

    /**
     * Set return type  mode.
     * 
     * @param string $type Return type 'stmt', 'object' or 'array'
     * 
     * @return self class instance.
    */
    public function returns(string $type): self
    {
        $type = strtolower($type);

        if(!in_array($type, ['object', 'array', 'stmt'])){
            throw new InvalidArgumentException('Invalid return type "' . $type . '", expected stmt, array or object');
        }

        $this->returnType = $type;

        return $this;
    }

    /**
     * Get date/time format for storing SQL.
     *
     * @param string $format Format to return default is `datetime`.
     *  Available time formats.
     *  - 'time'     - Return time format from timestamp
     *  - 'datetime' - Return SQL datetime format
     *  - 'date'     - Return SQL date format.
     * @param null|int $timestamp Optional timestamp
     *
     * @return string Formatted date/time/timestamp.
     */
    public static function datetime(string $format = 'datetime', ?int $timestamp = null): string
    {
        if($format === 'time'){
            $format = 'H:i:s';
        }elseif($format === 'date'){
            $format = 'Y-m-d';
        }else{
            $format = 'Y-m-d H:i:s';
        }

        $time = ($timestamp === null) ? Time::now() : Time::fromTimestamp($timestamp);

        return $time->format($format);
    }

    /**
     * Enable or disabled all caching 
     *
     * @param bool $enable Status action.
     * 
     * @return self $this class instance.
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
     * @return self $this class instance.
    */
    public function cache(
        string $key, 
        string $storage = null, 
        DateTimeInterface|int $expiry = 7 * 24 * 60 * 60, 
        ?string $folder = null
    ): self
    {
        if($this->caching){
            $storage ??=  'database_' . ($this->databaseTable ?? 'capture');
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
     * @param array<string, mixed> $values array of values to insert into table
     * @param bool $prepare Use bind values and execute prepare statement instead of query
     * 
     * @return int returns affected row counts.
    */
    public function insert(array $values, bool $prepare = true): int 
    {
        static::$handler = null;
        if ($values === []) {
            return 0;
        }

        if (!is_nested($values)) {
            $values = [$values];
        }
        
        if (!is_associative($values[0])) {
            return 0;
        }
    
        $columns = array_keys($values[0]);
        try {
            if($prepare){
                return $this->executeInsertPrepared($columns, $values);
            }
            return $this->executeInsertQuery($columns, $values);
        } catch (DatabaseException $e) {
            $e->handle();
        }
        return 0;
    }

    /**
     * Select records from table.
     * 
     * @param array<int, string> $columns select columns.
     * 
     * @return object|null|array|int|bool returns selected rows.
    */
    public function select(array $columns = ['*']): mixed 
    {
        static::$handler = null;
        if($this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();

                return $response;
            }
        }

        $columns = ($columns === ['*'])  ? '*' : implode(", ", $columns);
        $selectQuery = "SELECT {$columns} FROM {$this->databaseTable} {$this->tableAlias}";
        if ($this->joinTable !== '') {
            $selectQuery .= " {$this->joinType} JOIN {$this->joinTable} {$this->jointTableAlias}";
            if ($this->joinConditions !== []) {
                $selectQuery .= " ON {$this->joinConditions[0]}";
                if(count($this->joinConditions) > 1){
                    for ($i = 1; $i < count($this->joinConditions); $i++) {
                        $selectQuery .= " AND {$this->joinConditions[$i]}";
                    }
                }
            } 
        }

        try {
            if($this->cache === null){
                return $this->returnSelect($selectQuery);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($selectQuery) {
                return $this->returnSelect($selectQuery);
            });
        } catch (DatabaseException $e) {
            $e->handle();
        }
        
        return null;
    }

    /**
     * Return select result from table
     * 
     * @param string $selectQuery query
     * 
     * @return mixed
    */
    private function returnSelect(string &$selectQuery): mixed 
    {
        $isBided = false;

        if ($this->whereCondition === []) {
            $this->buildSearchConditions($selectQuery);
        }else{
            $isBided = true;
            $selectQuery .= $this->whereCondition['query'];
            $this->buildWhereConditions($selectQuery);
        }

        if($this->queryGroup !== []){
            $selectQuery .= ' GROUP BY ';
            $selectQuery .= rtrim(implode(', ', $this->queryGroup), ', ');
        }

        if($this->queryOrder !== []){
            $selectQuery .= ' ORDER BY ';
            $selectQuery .= rtrim(implode(', ', $this->queryOrder), ', ');
        }

        $selectQuery .= $this->queryLimit;

        if($isBided){
            static::$handler = $this->db->prepare($selectQuery);
            static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions(static::$handler);
            static::$handler->execute();
        }else{
            static::$handler = $this->db->query($selectQuery);
        }

        if(static::$handler->ok()){
            if($this->returnType === 'stmt'){
                $result = true;
            }else{
                $result = static::$handler->getAll($this->returnType);
            }
        }else{
            $result = false;
        }

        $this->reset();

        return $result;
    }

    /**
     * Bind placeholder values to builder
     * 
     * @param array $values
     * @deprecated Don't use this method anymore use execute() instead
     * @return self
     * @ignore
    */
    public function binds(array $values): self 
    {
        $this->bindValues = $values;

        return $this;
    }

    /**
     * Select on record from table using cache
     * 
     * @param string $query database query string
     * 
     * @return self $this 
     * @throws DatabaseException when query is empty
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
     * Bind placeholder values to builder
     * 
     * @param string $query SQL query string
     * 
     * @deprecated Don't use this method anymore use query instead
     * @return self
     * @throws DatabaseException when query is empty
     * @ignore
    */
    public function builder(string $query): self 
    {
        return $this->query($query);
    }

    /**
     * Executes SQL query from `query()` method.
     * 
     * @param array<string, mixed> $placeholder binds placeholder and value to query.
     * @param int $mode Return type [RETURN_ALL, RETURN_NEXT, RETURN_2D_NUM, RETURN_ID, RETURN_INT]
     * 
     * @return PDOStatement|mysqli_stmt|mysqli_result|bool|object|array|int|null Return result or prepared statement.
     * @throws DatabaseException 
    */
    public function execute(?array $placeholder = null, int $mode = RETURN_ALL): mixed 
    {
        static::$handler = null;

        if($this->returnType !== 'stmt' && $this->cache !== null && $this->hasCache){
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

        $this->bindValues = (empty($placeholder) ? [] : $placeholder);

        try {
            if($this->returnType === 'stmt' || $this->cache === null){
                return $this->returnExecute($this->buildQuery, $mode);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($mode) {
                return $this->returnExecute($this->buildQuery, $mode);
            });
        } catch (DatabaseException $e) {
            $e->handle();
        }

        return null;
    }

    /**
     * Return custom builder result from table
     * 
     * @param string $buildQuery query
     * @param int $mode return result type 
     * 
     * @return mixed|PDOStatement|mysqli_stmt|mysqli_result|bool|null Return result or prepared statement.
     * @throws DatabaseException If placeholder key is not a string.
    */
    private function returnExecute(string $buildQuery, int $mode): mixed
    {
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
        
        if(static::$handler->ok()){
            if($this->returnType === 'stmt'){
                $response = true;
            }else{
                $response = static::$handler->getItem($mode, $this->returnType);
            }
        }else{
            $response = false;
        }
    
        $this->reset();

        return $response;
    }

    /**
     * Select a single record from table,
     * 
     * @param array<int, string> $columns select columns to return
     * 
     * @return object|null|array|int|bool returns selected row.
    */
    public function find(array $columns = ['*']): mixed 
    {
        static::$handler = null;

        if ($this->whereCondition === []) {
            throw new DatabaseException("Find operation without a WHERE condition is not allowed.");
        }

        if($this->returnType !== 'stmt' && $this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();

                return $response;
            }
        }

        $columns = ($columns === ['*'])  ? '*' : implode(", ", $columns);
        $findQuery = "SELECT {$columns} FROM {$this->databaseTable} {$this->tableAlias}";
        if ($this->joinTable !== '') {
            $findQuery .= " {$this->joinType} JOIN {$this->joinTable} {$this->jointTableAlias}";
            if ($this->joinConditions !== []) {
                $findQuery .= " ON {$this->joinConditions[0]}";
                //$countable = new ArrayCountable($this->joinConditions);
                if(count($this->joinConditions) > 1){
                    for ($i = 1; $i < count($this->joinConditions); $i++) {
                        $findQuery .= " AND {$this->joinConditions[$i]}";
                    }
                }
            } 
        }
        
        try {
            if($this->returnType === 'stmt' || $this->cache === null){
                return $this->returnFind($findQuery);
            }
            
            return $this->cache->onExpired($this->cacheKey, function() use($findQuery) {
                return $this->returnFind($findQuery);
            });
        } catch (DatabaseException $e) {
            $e->handle();
        }
        
        return null;
    }

    /**
     * Return single result from table
     * 
     * @param string $findQuery query pass by reference 
     * 
     * @return mixed
    */
    private function returnFind(string &$findQuery): mixed 
    {
        $findQuery .= $this->whereCondition['query'];
        $this->buildWhereConditions($findQuery);
        $findQuery .= ' LIMIT 1';
 
        static::$handler = $this->db->prepare($findQuery);
        static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
        $this->bindConditions(static::$handler);
        static::$handler->execute();


        if(static::$handler->ok()){
            if($this->returnType === 'stmt'){
                $response = true;
            }else{
                $response = static::$handler->getNext($this->returnType);
            }
        }else{
            $response = false;
        }

        $this->reset();

        return $response;
    }

    /**
     * Select total counts of records from table,
     * 
     * @param string $column column to index counting (default: *) 
     * 
     * @return int|bool returns total counts of records.
    */
    public function total(string $column = '*'): int|bool 
    {
        return $this->executeTotalOrSum("SELECT COUNT({$column})");
    }

    /**
     * Select total sum of records from table, column
     * 
     * @param string $column column to index sum.
     * 
     * @return int|float|bool Returns total sum of records.
    */
    public function sum(string $column): int|float|bool
    {
        return $this->executeTotalOrSum("SELECT SUM({$column}) AS totalSum", true);
    }

    /**
     * Return total sum or count of records from table, column.
     * 
     * @param string $query Method query.
     * @param bool $sum whether to return total sum or count of records.
     * 
     * @return int|float|bool returns total sum or count of records.
    */
    private function executeTotalOrSum(string $query, bool $sum = false): float|int|bool 
    {
        static::$handler = null;
        if($this->returnType !== 'stmt' && $this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();
                return $response;
            }
        }

        $totalQuery = "{$query} FROM {$this->databaseTable} {$this->tableAlias}";
        
        if ($this->joinTable !== '') {
            $totalQuery .= " {$this->joinType} JOIN {$this->joinTable} {$this->jointTableAlias}";
            if ($this->joinConditions !== []) {
                $totalQuery .= " ON {$this->joinConditions[0]}";
                if(count($this->joinConditions) > 1){
                    for ($i = 1; $i < count($this->joinConditions); $i++) {
                        $totalQuery .= " AND {$this->joinConditions[$i]}";
                    }
                }
            } 
        }
    
        try {
            if($this->returnType === 'stmt' || $this->cache === null){
                return $this->returnTotalOrSum($totalQuery, $sum);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($totalQuery, $sum) {
                return $this->returnTotalOrSum($totalQuery, $sum);
            });
        } catch (DatabaseException $e) {
            $e->handle();
        }
        
        return 0;
    }

    /**
     * Return total number of rows in table
     * 
     * @param string $totalQuery query
     * @param bool $sum Return sum or total
     * 
     * @return int|float|bool  returns selected row.
    */
    private function returnTotalOrSum(string $totalQuery, bool $sum = false): int|float|bool 
    {
        if ($this->whereCondition === []) {
            static::$handler = $this->db->query($totalQuery);
        }else{
            $totalQuery .= $this->whereCondition['query'];
            $this->buildWhereConditions($totalQuery);
            static::$handler = $this->db->prepare($totalQuery);
            static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions(static::$handler);
            static::$handler->execute();
        }

        if(static::$handler->ok()){
            if($this->returnType === 'stmt'){
                $response = true;
            }elseif($sum){
                $response = static::$handler->getNext()?->totalSum ?? 0;
            }else{
                $response = static::$handler->getCount();
            }
        }else{
            $response = false;
        }
        
        $this->reset();

        return $response;
    }


    /**
     * Update table with columns and values
     * 
     * @param array<string, mixed> $setValues associative array of columns and values to update
     * 
     * @return int|bool returns affected row counts or false on failure.
     * @throws DatabaseException Throw if error occurred while updating.
     */
    public function update(?array $setValues = []): int|bool 
    {
        $columns = $setValues === [] ? $this->querySetValues : $setValues;
        static::$handler = null;

        if ($columns === []) {
            throw new DatabaseException('Update operation without SET values is not allowed.');
        }

        if ($this->whereCondition === []) {
            throw new DatabaseException('Update operation without a WHERE condition is not allowed.');
        }

        $updateColumns = static::buildPlaceholder($columns, true);
        $updateQuery = "UPDATE {$this->databaseTable} SET {$updateColumns}";
        $updateQuery .= $this->whereCondition['query'];
        $this->buildWhereConditions($updateQuery);

        if($this->maxLimit > 0){
            $updateQuery .= " LIMIT {$this->maxLimit}";
        }

        try {
            static::$handler = $this->db->prepare($updateQuery);
            foreach($columns as $key => $value){
                static::$handler->bind(static::trimPlaceholder($key), $value);
            }
            static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions(static::$handler);
            static::$handler->execute();

            if(static::$handler->ok()){
                if($this->returnType === 'stmt'){
                    $response = true;
                }else{
                    $response = static::$handler->rowCount();
                }
            }else{
                $response = false;
            }

            $this->reset();

            return $response;
        } catch (DatabaseException $e) {
            $e->handle();
        }

        return false;
    }

    /**
     * Delete record from table
     * 
     * @return int|bool returns number of affected rows or false on failure.
     * @throws DatabaseException Throw if error occurs.
    */
    public function delete(): int|bool
    {
        static::$handler = null;

        if ($this->whereCondition === []) {
            throw new DatabaseException('Delete operation without a WHERE condition is not allowed.');
        }

        $deleteQuery = "DELETE FROM {$this->databaseTable}";
        $deleteQuery .= $this->whereCondition['query'];
        $this->buildWhereConditions($deleteQuery);

        if($this->maxLimit > 0){
            $deleteQuery .= " LIMIT {$this->maxLimit}";
        }

        try {
            static::$handler = $this->db->prepare($deleteQuery);
            static::$handler->bind($this->whereCondition['placeholder'], $this->whereCondition['value']);
            $this->bindConditions(static::$handler);
            static::$handler->execute();

            if(static::$handler->ok()){
                if($this->returnType === 'stmt'){
                    $response = true;
                }else{
                    $response = static::$handler->rowCount();
                }
            }else{
                $response = false;
            }

            $this->reset();

            return $response;
        } catch (DatabaseException $e) {
            $e->handle();
        }
        
        return false;
    }

    /**
     * Get errors 
     * 
     * @return array 
    */
    public function errors(): array 
    {
        return $this->db->errors();
    }

    /**
     * Begin a transaction
     * 
     * @return self 
    */
    public function transaction(): self 
    {
        $this->db->beginTransaction();

        return $this;
    }

    /**
     * Commit a transaction
     * 
     * @return void 
    */
    public function commit(): void 
    {
        $this->db->commit();
    }

    /**
     * Rollback a transaction to default
     * 
     * @return void 
    */
    public function rollback(): void 
    {
        $this->db->rollback();
    }

    /**
     * Delete all records in a table 
     * And alter table auto increment to 1
     * 
     * @param bool $transaction Use query transaction.
     * 
     * @return bool returns true if completed
     * @throws DatabaseException
    */
    public function truncate(bool $transaction = true): bool 
    {
        try {
            if ($transaction) {
                $this->db->beginTransaction();
            }
            $deleteSuccess = $this->db->exec("DELETE FROM {$this->databaseTable}");
            $resetSuccess = $this->db->exec("ALTER TABLE {$this->databaseTable} AUTO_INCREMENT = 1");

            if ($transaction) {
                if ($deleteSuccess && $resetSuccess) {
                    $this->db->commit();
                    return true;
                }

                $this->db->rollback();
                return false;
            }

            return $deleteSuccess && $resetSuccess;

        } catch (DatabaseException $e) {
            $e->handle();
        }

        return false;
    }

    /**
     * Execute an SQL statement and return the number of affected rows.
     * 
     * @param string $query Query statement to execute.
     * 
     * @return int|bool returns affected row counts.
     * @throws DatabaseException
    */
    public function exec(string $query): int|bool 
    {
        try {
            $affected = $this->db->exec($query);

            return $affected;
        } catch (DatabaseException $e) {
            $e->handle();
        }

        return false;
    }

    /**
     * Drop table from database
     * 
     * @return int|bool returns affected row counts.
    */
    public function drop(): int|bool 
    {
        try {
            $return = $this->db->exec("DROP TABLE IF EXISTS {$this->databaseTable}");
            $this->reset();

            return $return;
        } catch (DatabaseException $e) {
            $e->handle();
        }

        return false;
    }

    /**
     * Get table column instance 
     * 
     * @param Scheme $scheme table column instance
     * 
     * @return int|bool affected row count
    */
    public function create(Scheme $scheme): int|bool 
    {
        try {
            $query = $scheme->generate();

            if(empty($query)){
                return false;
            }

            return $this->db->exec($query);
        } catch (DatabaseException $e) {
            $e->handle();
        }

        return false;
    }

    /**
     * Get table column instance 
     * 
     * @return Scheme column class instance
    */
    public function scheme(): Scheme
    {
        return new Scheme($this->databaseTable);
    }

    /**
     * Execute insert query
     * @param array $columns column name to target insert
     * @param array $values array of values to insert
     * @return int|bool returns affected row counts.
    */
    private function executeInsertQuery(array $columns, array $values): int|bool 
    {
        $inserts = [];
    
        foreach ($values as $row) {
            $inserts[] = "(" . static::quotedValues($row) . ")";
        }

        $keys = implode(', ', $columns);
        $value = implode(', ', $inserts);

        $insertQuery = "INSERT INTO {$this->databaseTable} ({$keys}) VALUES {$value}";

        static::$handler = $this->db->query($insertQuery);

        if(static::$handler->ok()){
            if($this->returnType === 'stmt'){
                $response = true;
            }else{
                $response = static::$handler->rowCount();
            }
        }else{
            $response = false;
        }

        $this->reset();

        return $response;
    }

    /**
     * Execute insert query using prepared statement
     * @param array $columns column name to target insert
     * @param array $values array of values to insert
     * @return int|bool returns affected row counts.
    */
    private function executeInsertPrepared(array $columns, array $values): int|bool 
    {
        $column = array_map(function ($col) {return ":$col";}, $columns);
        $placeholders = implode(', ', $column);
        $inserts = implode(', ', $columns);
        $count = 0;
    
        $insertQuery = "INSERT INTO {$this->databaseTable} ({$inserts}) VALUES ($placeholders)";
    
        static::$handler = $this->db->prepare($insertQuery);
    
        foreach ($values as $row) {
            foreach ($row as $key => $value) {
                static::$handler->bind(static::trimPlaceholder($key), $value);
            }

            static::$handler->execute();

            if(static::$handler->ok()){
                $count++;
            }
        }

        if($this->returnType === 'stmt'){
            $response = $count > 0;
        }else{
            $response = $count;
        }

        $this->reset();

        return $response;
    } 

    /**
     * Bind query where conditions
     * 
     * @param DatabaseInterface $handler Pass handler by reference
    */
    private function bindConditions(DatabaseInterface &$handler): void 
    {
        if ($this->andConditions === []) {
            return;
        }

        foreach ($this->andConditions as $bindings) {
            if (in_array($bindings['type'], ['AND', 'OR', 'AND_OR'], true)) {
                $column = static::trimPlaceholder($bindings['column']);
                $handler->bind($column, $bindings['value']);
            }
            
            if ($bindings['type'] === 'AND_OR') {
                $orColumn = static::trimPlaceholder($bindings['orColumn']);
                $handler->bind($orColumn, $bindings['orValue']);
            }
        }
    }

    /**
     * Build query conditions.
     *
     * @param string $query The SQL query string to which conditions are added.
    */
    private function buildWhereConditions(string &$query): void
    {
        if ($this->andConditions !== []) {
            foreach ($this->andConditions as $condition) {
                $column = $condition['column'];
                $operator = $condition['operator'] ?? '=';
                $orOperator = $condition['orOperator'] ?? '=';
                $placeholder = static::trimPlaceholder($column);

                $query .= match ($condition['type']) {
                    'AND' => " AND $column $operator $placeholder",
                    'OR' => " OR $column $operator $placeholder",
                    'AND_OR' => " AND ($column $operator $placeholder OR {$condition['orColumn']}  $orOperator " . static::trimPlaceholder($condition['orColumn']) . ")",
                    'IN' => " AND $column IN ({$condition['values']})",
                    'IN_SET' => ($operator === '>') ?
                        " AND FIND_IN_SET('{$condition['search']}', '{$condition['list']}') > 0" :
                        " AND FIND_IN_SET('{$condition['search']}', '{$condition['list']}')",
                    'LIKE' => " AND $column LIKE ?",
                    default => '',
                };
            }
        }
    }

    /**
     * Build query search conditions.
     *
     * @param string $query The SQL query string to which search conditions are added.
    */
    private function buildSearchConditions(string &$query): void
    {
        if ($this->andConditions !== []) {
            $query .= ' WHERE';
            $firstCondition = true;
            foreach ($this->andConditions as $condition) {
                $operator = $condition['operator'] ?? '=';

                if (!$firstCondition) {
                    $query .= ' AND';
                }

                $query .= match ($condition['type']) {
                    'IN' => " {$condition['column']} IN ({$condition['values']})",
                    'IN_SET' => ($operator === '>') ?
                        " FIND_IN_SET('{$condition['search']}', '{$condition['list']}') > 0" :
                        " FIND_IN_SET('{$condition['search']}', '{$condition['list']}')",
                    default => '',
                };

                $firstCondition = false;
            }
        }
    }

    /**
     * Retrieves the database manager instance.
     * 
     * Returns a singleton instance of the Manager class initialized with the current database connection.
     * 
     * @return Manager Database manager class instance.
     * 
     * @see database/manager - Database Manager
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

        $manager->setTable($this->databaseTable);

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
        if ($lastDotPosition !== false) {
            $placeholder = substr($input, $lastDotPosition + 1);
        } else {
            $placeholder = $input;
        }

        return ":$placeholder";
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
     * Quote array values int = int, string = 'string'
     * 
     * @param array $columns columns
     * @param bool $implode should implode or just return the array
     * 
     * @return array|string 
    */
    private static function quotedValues(array $columns, bool $implode = true): array|string
    {
        $quoted = [];
        foreach ($columns as &$item) {
            $quoted[] = is_string($item) ? addslashes($item) : $item;
        }

        if($implode){
            return implode(', ', $quoted);
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
        $this->databaseTable = ''; 
        $this->jointTableAlias = '';
        $this->tableAlias = '';
        $this->joinTable = '';
        $this->joinType = '';
        $this->joinConditions = [];
        $this->queryLimit = '';
        $this->maxLimit = 1;
        $this->queryOrder = [];
        $this->queryGroup = [];
        $this->whereCondition = [];
        $this->andConditions = [];
        $this->querySetValues = [];
        $this->hasCache = false;
        $this->cache = null;
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