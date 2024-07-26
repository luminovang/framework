<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Base;

use \Luminova\Database\Builder;
use \Luminova\Security\Validation;
use \Luminova\Storages\FileManager;
use \Peterujah\NanoBlock\SearchController as SearchInstance;
use \Luminova\Exceptions\RuntimeException;
use \DateTimeInterface;

abstract class BaseModel
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
     * Database cache expiration time in seconds.
     * 
     * @var DateTimeInterface|int $expiry
    */
    protected DateTimeInterface|int $expiry = 7 * 24 * 60 * 60;

    /**
     * Custom folder for model caches.
     * 
     * @var string $cacheFolder
    */
    protected static string $cacheFolder = '';

    /**
     * Specify whether the model's table is updatable, deletable, and insertable.
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
     * Database query builder class instance.
     * 
     * @var Builder $builder
    */
    protected ?Builder $builder = null;

    /**
     * Input validation class instance.
     * 
     * @var Validation $validation
    */
    protected static ?Validation $validation = null;

    /**
     * Search database controller instance.
     * 
     * @var SearchInstance $searchInstance
    */
    private static ?SearchInstance $searchInstance = null;

    /**
     * Constructor for the Model class.
     * If null is passed framework will initialize builder lass instance.
     * 
     * @param Builder|null $builder Query builder class instance.
     * 
    */
    public function __construct(?Builder $builder = null)
    {
        $this->builder ??= ($builder ?? Builder::getInstance());
        $this->builder->caching($this->cacheable);
        $this->builder->table($this->table);

        if($this->cacheable && static::$cacheFolder === ''){
            static::$cacheFolder = get_class_name(static::class);
        }

        $this->onCreate();
    }

    /**
     * Model on create method, an alternative method to __construct().
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
            return 0;
        }

        $this->assertIsAllowed($this->insertable, $values);
        return $this->builder->table($this->table)->insert($values) > 0;
    }

    /**
     * Update current record in the database.
     *
     * @param string|array<int,mixed> $key The key?s to update its record
     * @param array<string,mixed> $data associative array of columns and values to update.
     * @param int $max The maximum number of records to update.
     * 
     * @return bool Return true if records was updated, otherwise false.
     * @throws RuntimeException Throws if update columns contains column names that isn't defined in `$updatable`.
    */
    public function update(string|array $key, array $data, int $max = 1): bool  
    {
        if($this->readOnly){
            return 0;
        }

        $this->assertIsAllowed($this->updatable, $data);
        $tbl = $this->builder->table($this->table);
        $tbl->max($max);

        if(is_array($key)){
            return $tbl->in($this->primaryKey, $key)->update($data) > 0;
        }
        
        return $tbl->where($this->primaryKey, '=', $key)->update($data) > 0;
    }

    /**
     * Fine next or a single record from the database table.
     *
     * @param string|array<int,mixed> $key The key?s to find its record
     * @param array<int,string> $fields The fields to retrieve (default is all).
     * 
     * @return mixed Return selected records or false on failure.
    */
    public function find(string|array $key, array $fields = ['*']): mixed 
    {
        $tbl = $this->builder->table($this->table);

        if(is_array($key)){
            $tbl->in($this->primaryKey, $key);
            $cache_key = md5(json_encode($key));
        }else{
            $tbl->where($this->primaryKey, '=', $key);
            $cache_key = $key;
        }

        $tbl->cache($cache_key, $this->table . '_find', $this->expiry, static::$cacheFolder);
        return $tbl->find($fields);
    }

    /**
     * Select records from the database table.
     *
     * @param string|array<int,mixed>|null $key The key?s to select its record, if null all record in table will be selected.
     * @param array<int,string> $fields The fields to retrieve (default is all).
     * @param int $limit Select result limit (default: 100).
     * @param int $offset Select limit offset (default: 0).
     * 
     * @return mixed Return selected records or false on failure.
    */
    public function select(
        string|array|null $key = null, 
        array $fields = ['*'],  
        int $limit = 100, 
        int $offset = 0
    ): mixed 
    {
        $tbl = $this->builder->table($this->table);
        $cache_key = 'select';
        if($key !== null){
            if(is_array($key)){
                $tbl->in($this->primaryKey, $key);
            }else{
                $tbl->where($this->primaryKey, '=', $key);
            }
        }
        
        $cache_key = static::cacheKey($key, $fields);
        $tbl->limit($limit, $offset);
        $tbl->cache($cache_key, $this->table . '_select', $this->expiry, static::$cacheFolder);
        return $tbl->select($fields);
    }

    /**
     * Delete a record from the database.
     * 
     * @param string|array<int,mixed>|null $key The keys to delete, if null all record in table will be deleted.
     * @param int $max The maximum number of records to delete.
     * 
     * @return bool Return true if the record was successfully deleted otherwise false.
    */
    public function delete(string|array|null $key = null, int $max = 1): bool 
    {
        if($this->readOnly){
            return false;
        }

        $tbl = $this->builder->table($this->table);
        $tbl->max($max);

        if($key === null){
            return $tbl->delete() > 0;
        }

        if(is_array($key)){
            return $tbl->in($this->primaryKey, $key)->delete() > 0;
        }

        return $tbl->where($this->primaryKey, '=', $key)->delete() > 0;
    }

    /**
     * Get total number of records in the database.
     * 
     * @return int|bool  Return the number of records.
    */
    public function total(): int|bool 
    {
        return $this->builder->table($this->table)
            ->cache('total', $this->table . '_total', $this->expiry, static::$cacheFolder)
            ->total();
    }

    /**
     * Get total number of records in the database based on the keys.
     * 
     * @param string|array<int,mixed> $key The key?s to find total number of matched.
     * 
     * @return int|bool  Return the number of records.
    */
    public function count(string|array $key): int|bool 
    {
        $tbl = $this->builder->table($this->table);

        if(is_array($key)){
            $tbl->in($this->primaryKey, $key);
        }else{
            $tbl->where($this->primaryKey, '=', $key);
        }

        $cache_key = static::cacheKey($key);
        $tbl->cache('count_' . $cache_key, $this->table . '_total', $this->expiry, static::$cacheFolder);
        return $tbl->total();
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
        $cache_key = static::cacheKey($query, $fields);
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

        if(empty($queries)){
            return false;
        }

        $cache_key = static::cacheKey($query, $fields);
        $fields = ($fields === ['*'])  ? '*' : implode(", ", $fields);
        $sql = "SELECT {$fields} FROM {$this->table} {$queries} LIMIT {$offset}, {$limit}";

        $tbl = $this->builder->query($sql);
        $tbl->cache($cache_key, $this->table . '_doSearch', $this->expiry, static::$cacheFolder);
        $result = $tbl->execute();

        if(empty($result)){
            return false;
        }

        return $result;
    }

    /**
     * Extract cache key from query key(s) and return fields.
     * This ensures that the cache key is unique based on select statement return columns and primary key or keys.
     * 
     * @param string|array $key The query lookup key or keys to extract from.
     * @param array $fields The optional query fields to extract from.
     * 
     * @return string Hashed cache key.
     */
    protected static function cacheKey(string|array $key, array $fields = []): string 
    {
        $key = is_array($key) ? $key : [$key];

        sort($key);
        sort($fields);

        $fields = ($fields === ['*'] || $fields === []) ? '*' : implode(', ', $fields);
        $keyString = implode(', ', $key);
        $combined = $fields . '|' . $keyString;

        return md5($combined);
    }

    /**
     * Return search controller class instance. 
     * 
     * @param string $flag Search matching flag (e.g. `any`).
     * 
     * @return SearchInstance Search controller instance.
     * @throws RuntimeException If the third party search controller class is not installed.
    */
    protected function searchInstance(string $flag): SearchInstance
    {
        if(!class_uses(SearchInstance::class)){
            throw new RuntimeException('The search controller library is not installed. Please run the composer command "composer require peterujah/php-search-controller" to install it.');
        }

        $flags = [
            'start' => SearchInstance::START_WITH_QUERY,
            'end' => SearchInstance::END_WITH_QUERY,
            'any' => SearchInstance::HAVE_ANY_QUERY,
            'second' => SearchInstance::HAVE_SECOND_QUERY,
            'length2' => SearchInstance::START_WITH_QUERY_2LENGTH,
            'length3' => SearchInstance::START_WITH_QUERY_3LENGTH,
            'startend' => SearchInstance::START_END_WITH_QUERY,
        ];

        $flag = $flags[$flag] ?? SearchInstance::HAVE_ANY_QUERY;

        self::$searchInstance ??= new SearchInstance();
        self::$searchInstance->setOperators($flag);
        self::$searchInstance->setParameter($this->searchable);

        return self::$searchInstance;
    }

    /**
     * Delete all model database caches.
     * 
     * @return bool Return true if all caches are deleted, false otherwise.
    */
    public function purge(): bool 
    {
        if(static::$cacheFolder === ''){
            return false;
        }

        $path = root('writeable/caches/database/' . static::$cacheFolder);
        return FileManager::remove($path) > 0;
    }

    /**
     * Initialize and ser validation class object.
     *
     * @return Validation Validation class instance.
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
     * Check if insert, update or select columns are in allowed list.
     * 
     * @param array<int,string> $allowed The allowed list of columns.
     * @param array<string,mixed> $columns The column keys and value to check.
     * 
     * @return void 
     * @throws RuntimeException Throws if columns contains un-allowed key.
    */
    protected function assertIsAllowed(array $allowed, array $columns): void 
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
        throw new RuntimeException("The data contains unsupported columns: $unsupported");
    }

    /**
     * Get the name of the database table associated with this model.
     *
     * @return string The name of the database table.
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
     * Check if method is read only 
     *
     * @return bool Return true if is read only otherwise false.
    */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }
}