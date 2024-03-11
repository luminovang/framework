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

use \Luminova\Database\Connection;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Cache\FileCache;
use \Luminova\Database\Results\Statements;

//use \Luminova\Arrays\ArrayCountable;

class Query extends Connection 
{  
    /**
    * Class instance
    * @var Query|null $instance 
    */
    private static ?Query $instance = null;

    /**
    * Table name to query
    * @var string $databaseTable 
    */
    private string $databaseTable = '';

    /**
    * Table name to join query
    * @var string $databaseJoinTable 
    */
    private string $databaseJoinTable = '';

    /**
    * Table join query type
    * @var string $databaseJoinType 
    */
    private string $databaseJoinType = '';

    /**
    * Table join bind parameters
    * @var array $databaseJoinSelectors 
    */
    private array $databaseJoinSelectors = [];

    /**
    * Table query order limit offset and count query 
    * @var string $queryLimit 
    */
    private string $queryLimit = '';

    /**
    * Table query order rows 
    * @var string $queryOrder 
    */
    private string $queryOrder = '';

    /**
    * Table query group column by
    * @var string $queryGroup 
    */
    private string $queryGroup = '';

    /**
    * Table query where column
    * @var string $queryWhere 
    */
    private string $queryWhere = '';

    /**
    * Table query where column value
    * @var string $queryWhereValue 
    */
    private string $queryWhereValue = '';

    /**
    * Table query and query column
    * @var array $queryWhereConditions 
    */
    private array $queryWhereConditions = [];

    /**
    * able query update set values
    * @var array $querySetValues 
    */
    private array $querySetValues = [];

    /**
    * Has Cache flag
    * @var bool $hasCache 
    */
    private bool $hasCache = false;


    /**
    * Cache class instance
    * @var FileCache $cache 
    */
    private ?FileCache $cache = null;

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
    * Query operators 
    * 
    * @var array $queryOperators 
    */
    private static array $queryOperators = ['=', '!=', '<', '<=','>','>='];
    

    /**
     * Class Constructor
     * @throws DatabaseException|InvalidException If fails
    */
	public function __construct()
    {
		parent::__construct();
	}

    /*
        Class cloning
    */
    private function __clone() {
        $this->reset();
    }
    
    /*
        Prevent unserialization of the singleton instance
    */
    public function __wakeup() { }

    /**
     * Get database connection
     * 
     * @return MySqlDriver|PdoDriver|null 
    */
    public function getConn(): ?object
    {
        return $this->db;
    }

    /**
     * Close database connection
     * 
     * @return void 
    */
    public function closeConn(): void 
    {
       $this->db->close();
    }

    /**
    * Class Singleton
    *
    * @return static object $instance
    * @throws DatabaseException|InvalidException If fails
    */
    public static function getInstance(): static 
    {
        return static::$instance ??= new static();
    }

    /**
     * Sets table name
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
     * Sets join table name and method
     * @param string $table The table name
     * @param string $type The join type
     * @param string $alias join table alias
     * 
     * @return self $this Class instance.
     */
    public function join(string $table, string $type = 'INNER', string $alias = ''): self
    {
        $this->databaseJoinType = $type;
        $this->databaseJoinTable = $table;
        if($alias !== ''){
            $this->jointTableAlias = $alias;
        }
        return $this;
    }

    /**
     * Sets join table on clause
     * 
     * @param array<string> $selectors Join selectors ['key = table_key', 'u.key = u2.key']
     * 
     * @return self $this Class instance.
     */
    public function on(array $selectors): self
    {
        $this->databaseJoinSelectors = $selectors;

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
        $this->join($table, "INNER", $alias);

        return $this;
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
        $this->join($table, "LEFT", $alias);

        return $this;
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
            $this->queryLimit = " LIMIT {$offset},{$limit}";
        }

        return $this;
    }

    /**
     * Set query order
     * @param string $order uid ASC, name DESC
     * 
     * @return self class instance.
     */
    public function order(string $order): self 
    {
        $this->queryOrder = " ORDER BY {$order}";

        return $this;
    }

    /**
     * Set query grouping
     * 
     * @param string $group group by column name
     * 
     * @return self class instance.
     */
    public function group(string $group): self 
    {
        $this->queryGroup = " GROUP BY {$group}";

        return $this;
    }

    /**
     * Set query where
     * 
     * @param string $column column name
     * @param mixed $operator Comparison Operator
     * @param mixed $key column key value
     * 
     * @return self class instance.
     */
    public function where(string $column, mixed $operator, mixed $key = null): self
    {
        [$key, $operator] = static::fixLegacyOperators($key, $operator);

        $this->queryWhereValue = $key;
        $this->queryWhere = " WHERE {$column} {$operator} :where_column";

        return $this;
    }

    /**
     * Set query where and
     * 
     * @param string $column column name
     * @param mixed $operator Comparison operator
     * @param mixed $key column key value
     * 
     * @return self class instance.
     */
    public function and(string $column, mixed $operator, mixed $key = null): self
    {
        [$key, $operator] = static::fixLegacyOperators($key, $operator);

        $this->queryWhereConditions[] = [
            'type' => 'AND', 
            'column' => $column, 
            'key' => $key,
            'operator' => $operator
        ];

        return $this;
    }

    /**
     * Set update columns and values
     * 
     * @param string $column column name
     * @param string|int $value column key value
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
     * @param mixed $operator Comparison operator
     * @param mixed $key column key value
     * 
     * @return self class instance.
     */
    public function or(string $column, mixed $operator, mixed $key = null): self
    {
        [$key, $operator] = static::fixLegacyOperators($key, $operator);

        $this->queryWhereConditions[] = [
            'type' => 'OR', 
            'column' => $column, 
            'key' => $key,
            'operator' => $operator
        ];
        return $this;
    }
    
     /**
     * Set query AND (? OR ?)
     * @param string $column column name
     * @param mixed $operator Comparison operator
     * @param mixed $key column key value
     * @param string $orColumn column name
     * @param mixed $orOperator Comparison operator
     * @param mixed $orKey column key value
     * 
     * @return self class instance.
     */
    public function andOr(string $column, mixed $operator, mixed $key, string $orColumn, mixed $orOperator, mixed $orKey): self
    {
        [$key, $operator] = static::fixLegacyOperators($key, $operator);
        [$orKey, $orOperator] = static::fixLegacyOperators($orKey, $orOperator);

        $this->queryWhereConditions[] = [
            'type' => 'AND_OR', 
            'column' => $column, 
            'key' => $key,
            'operator' => $operator,
            'orColumn' => $orColumn, 
            'orKey' => $orKey,
            'orOperator' => $orOperator,
        ];

        return $this;
    }

    /**
     * Set query where IN () expression
     * @param string $column column name
     * @param array $lists of values
     * 
     * @return self class instance.
     */
    public function in(string $column, array $lists = []): self
    {
        if ($lists === []) {
            return $this;
        }

        $values = static::quotedValues($lists);

        $this->queryWhereConditions[] = [
            'type' => 'IN', 
            'column' => $column, 
            'values' => $values
        ];

        return $this;
    }


     /**
     * Set query where FIND_IN_SET () expression
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
        $this->queryWhereConditions[] = [
            'type' => 'IN_SET', 
            'list' => $values, 
            'search' => $search, 
            'operator' => $operator
        ];

        return $this;
    }

    /**
     * Cache the query result using a specified storage.
     *
     * @param string $key The storage cache key
     * @param string $storage Private storage name hash name (optional): but is recommended to void storing large data in one file.
     * @param int $expiry The cache expiry time in seconds (default: 7 days).
     * 
     * @return self $this class instance.
     */
    public function cache(string $key, string $storage = null, int $expiry = 7 * 24 * 60 * 60): self
    {
        $storage ??=  'database_' . ($this->databaseTable ?? 'capture');
        $this->cache = FileCache::getInstance($storage, 'database');
        $this->cache->setExpire($expiry);
        $this->cacheKey = md5($key);

        // Check if the cache exists and handle expiration
        if ($this->cache->hasItem($this->cacheKey)) {
            $this->hasCache = true;
            if ($this->cache->hasExpired($this->cacheKey)) {
                $this->cache->deleteItem($this->cacheKey);
                $this->hasCache = false;
            }
        }

        return $this;
    }

    /**
     * Insert into table
     * @param array $values array of values to insert into table
     * @param bool $bind Use bind values and prepare statement instead of query
     * 
     * @return int returns affected row counts.
     */
    public function insert(array $values, bool $bind = true): int 
    {
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
            if($bind){
                return $this->executeInsertPrepared($columns, $values);
            }
            return $this->executeInsertQuery($columns, $values);
        } catch (DatabaseException $e) {
            $e->handle();
        }
        return 0;
    }

    /**
     * Select from table,
     * 
     * @param array $columns select columns
     * 
     * @return object|null|array returns selected rows.
     */
    public function select(array $columns = ["*"]): mixed 
    {
        if($this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();

                return $response;
            }
        }

        $columns = implode(", ", $columns);
        $selectQuery = "SELECT {$columns} FROM {$this->databaseTable} {$this->tableAlias}";
        if ($this->databaseJoinTable !== '') {
            $selectQuery .= " {$this->databaseJoinType} JOIN {$this->databaseJoinTable} {$this->jointTableAlias}";
            if ($this->databaseJoinSelectors !== []) {
                $selectQuery .= " ON {$this->databaseJoinSelectors[0]}";
                if(count($this->databaseJoinSelectors) > 1){
                    for ($i = 1; $i < count($this->databaseJoinSelectors); $i++) {
                        $selectQuery .= " AND {$this->databaseJoinSelectors[$i]}";
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
        if ($this->queryWhere === '') {
            $this->buildSearchConditions($selectQuery);
        }else{
            $isBided = true;
            $selectQuery .= $this->queryWhere;
            $this->buildWhereConditions($selectQuery);
        }
        $selectQuery .= $this->queryGroup;
        $selectQuery .= $this->queryOrder;
        $selectQuery .= $this->queryLimit;
 
        if($isBided){
            $this->db->prepare($selectQuery);
            $this->db->bind(':where_column', $this->queryWhereValue);
            if ($this->queryWhereConditions !== []) {
                foreach ($this->queryWhereConditions as $bindings) {
                    if (in_array($bindings['type'], ['AND', 'OR'], true)) {
                        $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                    } elseif ($bindings['type'] === 'AND_OR') {
                        $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                        $this->db->bind(static::trimPlaceholder($bindings['orColumn']), $bindings['orKey']);
                    }
                }
            }
            $this->db->execute();
        }else{
            $this->db->query($selectQuery);
        }
        $return = $this->db->getAll();
        $this->reset();

        return $return;
    }

    /**
     * Bind placeholder values to builder
     * 
     * @param array $values
     * @deprecated Don't use this method anymore use execute instead
     * @return self
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
    */
    public function builder(string $query): self 
    {
        return $this->query($query);
    }

    /**
     * Execute query
     * Execute does not support cache method
     * 
     * @param array $binds binds placeholder to query
     * @param string $type [all, one, object, total, lastId, count or stmt]  or 'stmt' to return Statements
     * 
     * @return Statements|object|array|int|null Statements or null when failed
     * @throws DatabaseException 
    */
    public function execute(?array $binds = null, string $type = 'all'): mixed 
    {
        if($type !== 'stmt' && $this->cache !== null && $this->hasCache){
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

        $this->bindValues = ($binds === null || $binds === []) ? [] : $binds;

        try {
    
            if($type === 'stmt' || $type === 'count' || $this->cache === null){
                return $this->returnQuery($this->buildQuery, $type);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($type) {
                return $this->returnQuery($this->buildQuery, $type);
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
     * @param string $result return result type 
     * 
     * @return mixed|Statements false to return Statements
     * @throws DatabaseException
    */
    private function returnQuery(string $buildQuery, string $result): mixed 
    {
        if($this->bindValues === []){
            $this->db->query($buildQuery);
        }else{
            $this->db->prepare($buildQuery);
            foreach ($this->bindValues as $key => $value) {
                if(!is_string($key) || $key === '?'){
                    throw new DatabaseException("Invalid bind placeholder {$key}, placeholder key must be same with your table mapped column key, example :foo");
                }
                $this->db->bind(static::trimPlaceholder($key), $value);
            }
            $this->db->execute();
        }
        if($result === 'stmt'){
            $clone = clone $this->db;
            $response = new Statements($clone);
        }else{
            $response = match ($result) {
                'all' => $this->db->getAll(),
                'one' => $this->db->getOne(),
                'total' => $this->db->getInt(),
                'object' => $this->db->getObject(),
                'array' => $this->db->getArray(),
                'lastId' => $this->db->getLastInsertId(),
                default => $this->db->rowCount(),
            };
        }

        $this->reset();

        return $response;
    }

    /**
     * Select on record from table,
     * @param array $columns select columns
     * 
     * @return object|null returns selected row.
     */
    public function find(array $columns = ["*"]): mixed 
    {
        if ($this->queryWhere === '') {
            throw new DatabaseException("Find operation without a WHERE condition is not allowed.");
        }

        if($this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();

                return $response;
            }
        }

        $columns = implode(", ", $columns);
        $findQuery = "SELECT {$columns} FROM {$this->databaseTable} {$this->tableAlias}";
        if ($this->databaseJoinTable !== '') {
            $findQuery .= " {$this->databaseJoinType} JOIN {$this->databaseJoinTable} {$this->jointTableAlias}";
            if ($this->databaseJoinSelectors !== []) {
                $findQuery .= " ON {$this->databaseJoinSelectors[0]}";
                //$countable = new ArrayCountable($this->databaseJoinSelectors);
                if(count($this->databaseJoinSelectors) > 1){
                    for ($i = 1; $i < count($this->databaseJoinSelectors); $i++) {
                        $findQuery .= " AND {$this->databaseJoinSelectors[$i]}";
                    }
                }
            } 
        }
        
        try {
            if($this->cache === null){
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
     * @param string $findQuery query
     * 
     * @return mixed
    */
    private function returnFind(string &$findQuery): mixed 
    {
        $findQuery .= $this->queryWhere;
        $this->buildWhereConditions($findQuery);
        $findQuery .= ' LIMIT 1';
 
        $this->db->prepare($findQuery);
        $this->db->bind(':where_column', $this->queryWhereValue);
        if ($this->queryWhereConditions !== []) {
            foreach ($this->queryWhereConditions as $bindings) {
                if (in_array($bindings['type'], ['AND', 'OR'], true)) {
                    $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                } elseif ($bindings['type'] === 'AND_OR') {
                    $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                    $this->db->bind(static::trimPlaceholder($bindings['orColumn']), $bindings['orKey']);
                }
            }
        }
        $this->db->execute();
        $return = $this->db->getOne();
        $this->reset();
        return $return;
    }


    /**
     * Select on record from table,
     * @param array $columns select columns
     * 
     * @return int returns selected row.
    */
    public function total(string $column = "*"): int 
    {
        if($this->cache !== null && $this->hasCache){
            $response = $this->cache->getItem($this->cacheKey);
            if($response !== null){
                $this->cacheKey = '';
                $this->reset();

                return $response??0;
            }
        }
           
        $totalQuery = "SELECT COUNT({$column}) FROM {$this->databaseTable} {$this->tableAlias}";
        
        if ($this->databaseJoinTable !== '') {
            $totalQuery .= " {$this->databaseJoinType} JOIN {$this->databaseJoinTable} {$this->jointTableAlias}";
            if ($this->databaseJoinSelectors !== []) {
                $totalQuery .= " ON {$this->databaseJoinSelectors[0]}";
                if(count($this->databaseJoinSelectors) > 1){
                    for ($i = 1; $i < count($this->databaseJoinSelectors); $i++) {
                        $totalQuery .= " AND {$this->databaseJoinSelectors[$i]}";
                    }
                }
            } 
        }
    
        try {
            if($this->cache === null){
                return $this->returnTotal($totalQuery);
            }

            return $this->cache->onExpired($this->cacheKey, function() use($totalQuery) {
                return $this->returnTotal($totalQuery);
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
     * 
     * @return int returns selected row.
    */
    private function returnTotal(string $totalQuery): int 
    {
        if ($this->queryWhere === '') {
            $this->db->query($totalQuery);
        }else{
            $totalQuery .= $this->queryWhere;
            $this->buildWhereConditions($totalQuery);
            $this->db->prepare($totalQuery);
            $this->db->bind(':where_column', $this->queryWhereValue);
            if ($this->queryWhereConditions !== []) {
                foreach ($this->queryWhereConditions as $bindings) {
                    if (in_array($bindings['type'], ['AND', 'OR'], true)) {
                        $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                    } elseif ($bindings['type'] === 'AND_OR') {
                        $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                        $this->db->bind(static::trimPlaceholder($bindings['orColumn']), $bindings['orKey']);
                    }
                }
            }
            $this->db->execute();
        }
        $total = $this->db->getInt();
        $this->reset();
        return $total;
    }

    /**
     * Update table with columns and values
     * @param array $setValues associative array of columns and values to update
     * @param int $limit number of records to update 
     * 
     * @return int returns affected row counts.
     */
    public function update(?array $setValues = [], int $limit = 1): int 
    {
        $columns = $setValues === [] ? $this->querySetValues : $setValues;

        if ($columns === []) {
            static::error("Update operation without SET values is not allowed.");
            return 0;
        }

        if ($this->queryWhere === '') {
            static::error("Update operation without a WHERE condition is not allowed.");
            return 0;
        }

        $updateColumns = static::buildPlaceholder($columns, true);
        $updateQuery = "UPDATE {$this->databaseTable} SET {$updateColumns}";
        $updateQuery .= $this->queryWhere;
        $this->buildWhereConditions($updateQuery);

        if($limit > 0){
            $updateQuery .= " LIMIT {$limit}";
        }
        try {
            $this->db->prepare($updateQuery);
            foreach ($columns as $key => $value) {
                $this->db->bind(static::trimPlaceholder($key), $value);
            }
            $this->db->bind(':where_column', $this->queryWhereValue);
            if ($this->queryWhereConditions !== []) {
                foreach ($this->queryWhereConditions as $bindings) {
                    if (in_array($bindings['type'], ['AND', 'OR'], true)) {
                        $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                    } elseif ($bindings['type'] === 'AND_OR') {
                        $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                        $this->db->bind(static::trimPlaceholder($bindings['orColumn']), $bindings['orKey']);
                    }
                }
            }
            
            $this->db->execute();
            $return = $this->db->rowCount();
            $this->reset();
            return $return;
        } catch (DatabaseException $e) {
            $e->handle();
        }
        return 0;
    }

    /**
     * Delete from table
     * @param int $limit row limit
     * 
     * @return int returns affected row counts.
     */
    public function delete(int $limit = 0): int
    {
         if ($this->queryWhere === '') {
            static::error('Delete operation without a WHERE condition is not allowed.');
         }else{
            $deleteQuery = "DELETE FROM {$this->databaseTable}";
            $deleteQuery .= $this->queryWhere;
            $this->buildWhereConditions($deleteQuery);

            if($limit > 0){
                $deleteQuery .= " LIMIT {$limit}";
            }

            try {
                $this->db->prepare($deleteQuery);
                $this->db->bind(':where_column', $this->queryWhereValue);
                if ($this->queryWhereConditions !== []) {
                    foreach ($this->queryWhereConditions as $bindings) {
                        if (in_array($bindings['type'], ['AND', 'OR'], true)) {
                            $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                        } elseif ($bindings['type'] === 'AND_OR') {
                            $this->db->bind(static::trimPlaceholder($bindings['column']), $bindings['key']);
                            $this->db->bind(static::trimPlaceholder($bindings['orColumn']), $bindings['orKey']);
                        }
                    }
                }
                $this->db->execute();
                $rowCount = $this->db->rowCount();
                $this->reset();
                return $rowCount;
            } catch (DatabaseException $e) {
                $e->handle();
            }
        }
        return 0;
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
     * @param bool $transaction row limit
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
                } else {
                    $this->db->rollback();
                    return false;
                }
            } else {
                return $deleteSuccess && $resetSuccess;
            }
        } catch (DatabaseException $e) {
            $e->handle();
        }
    }

    /**
     * Drop table from database
     * 
     * @return int returns affected row counts.
    */
    public function drop(): int 
    {
        try {
            $return = $this->db->exec("DROP TABLE IF EXISTS {$this->databaseTable}");
            $this->reset();

            return $return;
        } catch (DatabaseException $e) {
            $e->handle();
        }
        return 0;
    }

    /**
     * Create a new table if it doesn't exist
     * 
     * @param array $columns table columns and options
     * 
     * @return int returns affected row counts.
     */
    public function createTable(array $columns): int 
    {
        try {
            $return = $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->databaseTable} ($columns)");
            $this->reset();

            return $return;
        } catch (DatabaseException $e) {
            $e->handle();
        }
        return 0;
    }

    /**
     * Get table column instance 
     * 
     * @param Columns $column table column instance
     * 
     * @return int affected row count
    */
    public function create(Columns $column): int 
    {
        try {
            $query = $column->generate();
            if(empty($query)){
                return 0;
            }
            return $this->db->exec($query);
        } catch (DatabaseException $e) {
            $e->handle();
        }
        return 0;
    }

    /**
     * Get table column instance 
     * 
     * @return Columns column class instance
    */
    public function withColumns() : Columns
    {
        return new Columns($this->databaseTable);
    }

    /**
     * Execute insert query
     * @param array $columns column name to target insert
     * @param array $values array of values to insert
     * @return int returns affected row counts.
    */
    private function executeInsertQuery(array $columns, array $values): int 
    {
        $inserts = [];
    
        foreach ($values as $row) {
            $inserts[] = "(" . static::quotedValues($row) . ")";
        }

        $keys = implode(', ', $columns);
        $value = implode(', ', $inserts);

        $insertQuery = "INSERT INTO {$this->databaseTable} ({$keys}) VALUES {$value}";

        $this->db->query($insertQuery);
        $return = $this->db->rowCount();
        $this->reset();
        return $return;
    }

    /**
     * Execute insert query using prepared statement
     * @param array $columns column name to target insert
     * @param array $values array of values to insert
     * @return int returns affected row counts.
    */
    private function executeInsertPrepared(array $columns, array $values): int 
    {
        $column = array_map(function ($col) {return ":$col";}, $columns);
        $placeholders = implode(', ', $column);
        $inserts = implode(', ', $columns);
    
        $insertQuery = "INSERT INTO {$this->databaseTable} ({$inserts}) VALUES ($placeholders)";
    
        $this->db->prepare($insertQuery);
    
        foreach ($values as $row) {
            foreach ($row as $key => $value) {
                $this->db->bind(static::trimPlaceholder($key), $value);
            }
            $this->db->execute();
        }
        $return = $this->db->rowCount();
        $this->reset();
        return $return;
    } 

    /**
     * Build query conditions.
     *
     * @param string $query The SQL query string to which conditions are added.
    */
    private function buildWhereConditions(string &$query): void
    {
        if ($this->queryWhereConditions !== []) {
            foreach ($this->queryWhereConditions as $condition) {
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
        if ($this->queryWhereConditions !== []) {
            $query .= ' WHERE';
            $firstCondition = true;
            foreach ($this->queryWhereConditions as $condition) {
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
     * Throw an exception 
     * 
     * @param string $message
     * 
     * @throws DatabaseException
    */
    private static function error(string $message): void
    {
        DatabaseException::throwException($message);
    }

    /**
     * Mart the fixed for older version that doesn't support passing operators in query selector method
     * 
     * @param mixed $key lookup key
     * @param mixed $operator operator for now we use mixed in future it will be string
     * 
     * @return array 
    */
    private static function fixLegacyOperators(mixed $key, mixed $operator): array 
    {
        if($key === null && !in_array($operator, static::$queryOperators)){
            $key = $operator;
            $operator = '=';
        }

        return [
            $key,
            $operator
        ];
    }

    /**
     * Trim placeholder
     *
     * @param string $input 
     * 
     * @return string $placeholder
    */
    private static function trimPlaceholder(string $input): string 
    {
        $dotPosition = strpos($input, '.');
        $placeholder = ($dotPosition !== false) ? substr($input, $dotPosition + 1) : $input;

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

        /*$quoted = array_map(function($item) {
            return is_string($item) ? "'$item'" : $item;
        }, $columns);*/

        if($implode){
            return implode(', ', $quoted);
        }

        return $quoted;
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
        $this->databaseJoinTable = '';
        $this->databaseJoinType = '';
        $this->databaseJoinSelectors = [];
        $this->queryLimit = '';
        $this->queryOrder = '';
        $this->queryGroup = '';
        $this->queryWhere = '';
        $this->queryWhereValue = '';
        $this->queryWhereConditions = [];
        $this->querySetValues = [];
        $this->hasCache = false;
        $this->cache = null;
        $this->bindValues = [];
        $this->buildQuery = '';
        if($this->db->getDriver() === 'pdo'){
            $this->db->free();
        }
    }

    /**
     * Free database resources
     * 
     * @return void 
    */
    public function free(): void 
    {
        $this->db->free();
    }

    /**
     * Close database connection
     * 
     * @return void 
    */
    public function close(): void 
    {
        $this->db->close();
    }
}