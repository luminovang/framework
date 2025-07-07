<?php
/**
 * Luminova Framework abstract model.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \DateTimeInterface;
use \Luminova\Database\Builder;
use \Luminova\Security\Validation;
use \Luminova\Storages\FileManager;
use \Peterujah\NanoBlock\SearchController as SearchInstance;
use \Luminova\Interface\{DatabaseInterface, LazyInterface};
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};
use function \Luminova\Funcs\{root, get_class_name};

abstract class BaseModel implements LazyInterface
{
    /**
     * The name of the model's table.
     * 
     * @var string $table
     */
    protected string $table = ''; 

    /**
     * The default primary key column.
     * 
     * @var string $primaryKey
     */
    protected string $primaryKey = ''; 

    /**
     *  Enable database caching for query builder.
     * 
     * @var bool $cacheable
     */
    protected bool $cacheable = true; 

    /**
     * Type of result to be returned (e.g, `array` or `object`).
     * 
     * @var string $resultType
     */
    protected string $resultType = 'object';

    /**
     * Custom folder for model caches.
     * 
     * @var string $cacheFolder
     */
    protected static string $cacheFolder = '';

    /**
     * Specify whether the model's table is updatable, 
     * deletable, and insertable.
     * 
     * @var bool $readOnly
     */
    protected bool $readOnly = false; 

    /**
     * Searchable table column names.
     * 
     * @var array<int,string> $searchable
     */
    protected array $searchable = [];

    /**
     * Fields that can be inserted.
     * 
     * @var array<int,string> $insertable
     */
    protected array $insertable = []; 

    /**
     * Fields that can be updated.
     * 
     * @var array<int,string> $updatable
     */
    protected array $updatable = []; 

    /**
     * Input validation rules.
     * 
     * @var array<string,string> $rules
     */
    protected array $rules = [];

    /**
     * Input validation error messages for rules.
     * 
     * @var array<string,array> $messages.
     */
    protected array $messages = [];

    /**
     * Database cache expiration time in seconds.
     * 
     * @var DateTimeInterface|int $expiry
     */
    protected DateTimeInterface|int $expiry = 7 * 24 * 60 * 60;

    /**
     * Input validation class instance.
     * 
     * @var Validation $validation
     */
    protected static ?Validation $validation = null;

    /**
     * Builder class instance.
     * 
     * @var Builder|null $builder
     */
    protected ?Builder $builder = null;

    /**
     * Search database controller instance.
     * 
     * @var SearchInstance $searchInstance
     */
    private static ?SearchInstance $searchInstance = null;

    /**
     * Search flags.
     * 
     * @var array<string,string> $searchFilters
     */
    private static array $searchFilters = [
        'start'     => 'query%',
        'end'       => '%query',
        'any'       => '%query%',
        'second'    => '_query%',
        'length2'   => 'query_%',
        'length3'   => 'query__%',
        'startend'  => 'query%query'
    ];

    /**
     * Constructor for the Model class.
     */
    public function __construct()
    {
        $this->builder = Builder::getInstance();
        $this->builder->caching($this->cacheable);
        $this->builder->returns($this->resultType);

        $this->onCreate();
       
        if($this->cacheable && static::$cacheFolder === ''){
            static::$cacheFolder = get_class_name(static::class);
        }
    }

    /**
     * onCreate method that gets triggered on object creation, 
     * designed to be overridden in subclasses for custom initialization.
     * 
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Insert a new record into the current database.
     *
     * @param array<string,mixed> $values nested array of values to insert into table.
     * 
     * @return bool Return true if records was inserted, otherwise false.
     * @throws RuntimeException Throws if insert columns contains column names that isn't defined in `$insertable`.
     */
    public function insert(array $values): bool 
    {
        if($this->readOnly){
            return false;
        }

        $this->assertAllowedColumns($this->insertable, $values, 'insert');
        return $this->builder->table($this->table)
            ->insert($values) > 0;
    }

    /**
     * Update current record in the database.
     *
     * @param array<int,mixed>|string|float|int|null $key The key?s to update its record or null to update all records in table.
     * @param array<string,mixed> $data An associative array of columns and values to update.
     * @param int|null $max The maximum number of records to update (default: null).
     * 
     * @return bool Return true if records was updated, otherwise false.
     * @throws RuntimeException Throws if update columns contains column names that isn't defined in `$updatable`.
     */
    public function update(mixed $key, array $data, ?int $max = null): bool  
    {
        if($this->readOnly){
            return false;
        }

        $this->assertAllowedColumns($this->updatable, $data, 'update');
        $tbl = $this->builder->table($this->table);

        if($max > 0){
            $tbl->max($max);
        }

        if($key === null){
            return $tbl->strict(false)
                ->update($data) > 0;
        }

        return $tbl->where($this->primaryKey, is_array($key) ? 'IN' : '=', $key)
            ->update($data) > 0;
    }

    /**
     * Fine next or a single record from the database table.
     *
     * @param array<int,mixed>|string|float|int $key The key?s to find its record
     * @param array<int,string> $fields The fields to retrieve (default is all).
     * 
     * @return mixed Return selected records or false on failure.
     */
    public function find(mixed $key, array $fields = ['*']): mixed 
    {
        return $this->builder->table($this->table)
            ->find($fields)
            ->where($this->primaryKey, is_array($key) ? 'IN' : '=', $key)
            ->cache(
                self::cacheKey($key, $fields, 'find'), 
                $this->table . '_find', 
                $this->expiry, 
                static::$cacheFolder
            )
            ->get();
    }

    /**
     * Select records from the database table.
     *
     * @param string|int|float|array<int,mixed>|null $key The key?s to select its record, if null all record in table will be selected.
     * @param array<int,string> $fields The fields to retrieve (default is all).
     * @param int $limit Select result limit (default: 100).
     * @param int $offset Select limit offset (default: 0).
     * 
     * @return mixed Return selected records or false on failure.
     */
    public function select(
        mixed $key = null, 
        array $fields = ['*'],  
        int $limit = 100, 
        int $offset = 0
    ): mixed 
    {
        $tbl = $this->builder->table($this->table)->select($fields);

        if($key !== null){
            $tbl->where($this->primaryKey, is_array($key) ? 'IN' : '=', $key);
        }
        
        return $tbl->limit($limit, $offset)
            ->cache(
                self::cacheKey($key, $fields, 'select'), 
                $this->table . '_select', 
                $this->expiry, 
                static::$cacheFolder
            )->get();
    }

    /**
     * Delete a record from the database.
     * 
     * @param string|array<int,mixed>|float|int|null $key The keys to delete, if null all record in table will be deleted.
     * @param int|null $max The maximum number of records to delete (default: null).
     * 
     * @return bool Return true if the record was successfully deleted otherwise false.
     */
    public function delete(
        mixed $key = null, 
        ?int $max = null
    ): bool 
    {
        if($this->readOnly){
            return false;
        }

        $tbl = $this->builder->table($this->table);
        if($max){
            $tbl->max($max);
        }

        if($key === null){
            if($tbl->strict(false)->delete() > 0){
                $this->purge();
                return true;
            }

            return false;
        }

        return $tbl->where($this->primaryKey, is_array($key) ? 'IN' : '=', $key)
            ->delete() > 0;
    }

    /**
     * Get total number of records in the database.
     * 
     * @return int Return the number of records.
     */
    public function total(): int 
    {
        return (int) $this->builder->table($this->table)
            ->total()
            ->cache('total', $this->table . '_total', $this->expiry, static::$cacheFolder)
            ->get();
    }

    /**
     * Determine if a record exists in the database.
     * 
     * @param string|float|int $key The key to check if it exists in the database.
     * 
     * @return bool Return true if the record exists otherwise false.
     */
    public function exists(mixed $key): bool 
    {
        return (int) $this->count($key) > 0;
    }

    /**
     * Get total number of records in the database based on the keys.
     * 
     * @param array<int,mixed>|string|float|int $key The key?s to find total number of matched.
     * 
     * @return int Return the number of records.
     */
    public function count(mixed $key): int 
    {
        return (int) $this->builder->table($this->table)
            ->total()
            ->where($this->primaryKey, is_array($key) ? 'IN' : '=', $key)
            ->cache(
                self::cacheKey($key, [], 'count'), 
                $this->table . '_total', 
                $this->expiry, 
                static::$cacheFolder
            )->get();
    }

    /**
     * Search records from the database table using the `$searchable` to index search columns.
     *
     * @param string $query The Search query string, escape string before passing.
     * @param array<int,string> $fields The fields to retrieve (default is all).
     * @param int $limit Search result limit (default: 100).
     * @param int $offset Search limit offset (default: 0).
     * 
     * @return mixed Return found records or false on failure.
     */
    public function search(string $query, array $fields = ['*'], int $limit = 100, int $offset = 0): mixed
    {
        if($query === ''){
            return false;
        }

        $query = strtolower($query);
        $cache_key = self::cacheKey($query, $fields, 'search');
        $fields = ($fields === ['*'])  ? '*' : implode(", ", $fields);
        $columns = 'WHERE';

        foreach($this->searchable as $column){
            $columns .= " LOWER({$column}) LIKE :keyword OR";
        }

        $columns = rtrim($columns, ' OR');
        
        return $this->builder->query("SELECT {$fields} FROM {$this->table} {$columns} LIMIT {$offset}, {$limit}")
            ->cache($cache_key, $this->table . '_search', $this->expiry, static::$cacheFolder)
            ->execute([
                'keyword' => "%{$query}%"
            ]);
    }

    /**
     * Run a search in database table using the `$searchable` to index search columns.
     * This method uses third-party libraries to search database table.
     * 
     * @param string $query search query string, escape string before passing.
     * @param array<int,string> $fields The fields to retrieve (default is all).
     * @param int $limit Search result limit (default: 100).
     * @param int $offset Search limit offset (default: 0).
     * @param string $flag Search matching flag, default is (any) any matching keyword.
     * 
     * @return mixed Return search results.  
     * @throws RuntimeException If the third party search controller class is not installed.
     */
    public final function doSearch(
        string $query, 
        array $fields = ['*'], 
        int $limit = 100, 
        int $offset = 0, 
        string $flag = 'any'
    ): mixed 
    {
        if ($limit < 0 || $offset < 0 || $query === '') {
            return false;
        }

        $search = $this->searchInstance($flag);
        $search->setQuery($query)->split();
        $queries = $search->getQuery();

        if($queries === ''){
            return false;
        }

        $cache_key = self::cacheKey($query, $fields, 'doSearch');
        $fields = ($fields === ['*'])  ? '*' : implode(", ", $fields);
        $sql = "SELECT {$fields} FROM {$this->table} {$queries} LIMIT {$offset}, {$limit}";

        $result = $this->builder->query($sql)
            ->cache($cache_key, $this->table . '_doSearch', $this->expiry, static::$cacheFolder)
            ->execute();

        if($result === false || empty($result)){
            return false;
        }

        return $result;
    }

    /**
     * Deletes all cache entries related to the current model.
     * 
     * @return bool Returns true if the cache files are successfully deleted, false otherwise.
     */
    public function purge(): bool 
    {
        if(static::$cacheFolder === ''){
            return false;
        }

        $path = root('/writeable/caches/filesystem/database/' . static::$cacheFolder);
        return FileManager::remove($path) > 0;
    }

    /**
     * Change the database result return type (e.g, array or object).
     * 
     * @param string $returns The result returned as (e.g, `array` or `object`).
     * 
     * @return static Return instance of model class.
     */
    public function setReturn(string $returns): self
    {
        $this->resultType = $returns;
        $this->builder->returns($returns);

        return $this;
    }

    /**
     * Get the name of the database table associated with this model.
     *
     * @return string Return the name of the database table.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the primary key field name for this model.
     *
     * @return string The primary key field name.
     */
    public function getKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the table searchable array of column names.
     *
     * @return array<int,string> Return table searchable column names.
     */
    public function getSearchable(): array
    {
        return $this->searchable;
    }

    /**
     * Get instance of database connection.
     * 
     * @return DatabaseInterface Return database driver connection instance.
     * @throws DatabaseException Throws if database connection failed.
     */
    public function getConn(): DatabaseInterface
    {
        return $this->builder->database();
    }

    /**
     * Get instance of database builder class.
     * 
     * @return Builder|null Return the instance database builder.
     */
    public function getBuilder(): ?Builder
    {
        return $this->builder;
    }

    /**
     * Retrieve last inserted id from database after insert method is called.
     * 
     * @return mixed Return last inserted id from database.
     */
    public function getLastInsertedId(): mixed
    {
        return $this->builder->getLastInsertedId();
    }

    /**
     * Check if method is read only 
     *
     * @return bool Return true if is read only otherwise false.
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Initialize and ser validation class object.
     *
     * @return Validation Validation class instance.
     * 
     * > After first initialization you can then use `static::$validation` to access the object.
     */
    protected function validation(): Validation
    {
        static::$validation ??= new Validation();

        if($this->rules !== []){
            static::$validation->rules = $this->rules;
        }

        if($this->messages !== []){
            static::$validation->messages = $this->messages;
        }

        return static::$validation;
    }

    /**
     * Generate a unique cache key based on the query key(s) and fields.
     * This ensures that the cache key reflects the select statement's return columns and primary key(s).
     * 
     * @param string|int|float|array|null $key The query lookup key(s).
     * @param array $fields The optional query fields to include in the cache key.
     * @param string $prefix An optional prefix to prepend to the cache key.
     * 
     * @return string Return hashed cache key.
     */
    protected static function cacheKey(string|int|float|array|null $key, array $fields = [], string $prefix = ''): string 
    {
        // Handle fields: if no fields are specified or '*' is used, set it as '__any__'
        if($fields === ['*'] || $fields === []){
            $prefix .= '__any__';
        }else{
            sort($fields);
            $prefix .= implode(',', $fields);
        }

        if ($key && $key !== []) {
            // Convert $key to an array if it's not already one
            $key = (array) $key;
            sort($key);
            $prefix .= implode(',', $key);
        }else{
            // Handle key: if $key is null or an empty string, use '__all__'
            $prefix .= '__all__';
        }
   
        return $prefix;
    }

    /**
     * Get an instance of search controller class.
     * 
     * @param string $filter The search matching filter (e.g. `any`).
     * 
     * @return SearchInstance Return search controller instance.
     * @throws RuntimeException If the third-party search controller class is not installed.
     * @throws InvalidArgumentException If invalid search filter is provided.
     * 
     * This method provides a search instance configured with the specified filter.
     * The following filters are available:
     * 
     * - **start**: Matches queries starting with the specified term.
     * - **end**: Matches queries ending with the specified term.
     * - **any**: Matches queries containing the specified term anywhere.
     * - **second**: Matches queries where the term starts with an underscore followed by the specified term.
     * - **length2**: Matches queries where the term is exactly two characters long, followed by the specified term.
     * - **length3**: Matches queries where the term is exactly three characters long, followed by the specified term.
     * - **startend**: Matches queries starting with the specified term and ending with the term.
     */
    protected function searchInstance(string $filter = 'any'): object
    {
        if(self::$searchInstance === null && !class_uses(SearchInstance::class)){
            throw new RuntimeException('The search controller library is not installed. Run composer command "composer require peterujah/php-search-controller" to install it.');
        }

        $filterLike = self::$searchFilters[$filter] ?? false;

        if($filterLike === false){
            throw new InvalidArgumentException(sprintf(
                'Invalid unsupported search filter: "%s", expected filters are [%s]',
                $filter,
                implode(', ', self::$searchFilters)
            ));
        }

        self::$searchInstance ??= new SearchInstance();
        self::$searchInstance->setOperators($filterLike);
        self::$searchInstance->setParameter($this->searchable);

        return self::$searchInstance;
    }
    
    /**
     * Check if insert, update or select columns are in allowed list.
     * 
     * @param array<int,string> $allowed The allowed list of columns.
     * @param array<string,mixed> $columns The column keys and value to check.
     * 
     * @return void 
     * @throws RuntimeException Throws if columns contains unsupported keys.
     */
    protected function assertAllowedColumns(array $allowed, array $columns, string $from = ''): void 
    {
        if ($allowed === []) {
            return;
        }

        $columns = array_keys($columns);
        $unsupported = array_diff($columns, $allowed);

        if($unsupported === []){
            return;
        }

        $unsupported = implode(', ', $unsupported);
        throw new RuntimeException(sprintf(
            'The %s %s contains unsupported columns: [%s].',
            $from,
            ($from === 'insert' ? 'values' : 'data'),
            $unsupported
        ));
    }
}