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
use \Luminova\Security\InputValidator;
use \Peterujah\NanoBlock\SearchController as Searchable;
use \Luminova\Exceptions\RuntimeException;

abstract class BaseModel
{
   /**
     * Model table name name.
     * 
     * @var string $table
    */
    protected string $table = ''; 

    /**
     *  Default primary key column.
     * 
     * @var string $primaryKey
    */
    protected string $primaryKey = ''; 

    /**
     * Serachable table column names.
     * 
     * @var array<int, string> $searchables
    */
    protected array $searchables = [];

    /**
     *  Enable databse caching for query builder
     * 
     * @var bool $cachable
    */
    protected bool $cachable = true; 

    /**
     * Specify whether model table is updatable, deletable and insertable.
     * 
     * @var bool $readOnly
    */
    protected bool $readOnly = false; 

    /**
     * Fields that can be inserted.
     * 
     * @var array $insertables
    */
    protected array $insertables = []; 

    /**
     * Fields that can be updated.
     * 
     * @var array $updatables
    */
    protected array $updatables = []; 

    /**
     * Input validation rules.
     * 
     * @var array $rules
    */
    protected array $rules = [];

    /**
     * Input validation errors messages for rules.
     * 
     * @var array $messages
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
     * @var InputValidator $validation
    */
    protected ?InputValidator $validation = null;

    /**
     * Search database controller instance.
     * 
     * @var Searchable $searchInstance
    */
    private static ?Searchable $searchInstance = null;

    /**
     * Constructor for the Model class.
     * If null is passed fromework will insitalize builder lass instance.
     * 
     * @var null|Builder $builder Query builder class instance.
     * 
    */
    public function __construct(?Builder $builder = null)
    {
        $this->builder ??= ($builder ?? Builder::getInstance());
        $this->validation ??= new InputValidator();

        if($this->rules !== []){
            $this->validation->rules = $this->rules;
        }
        if($this->messages !== []){
            $this->validation->messages = $this->messages;
        }

        $this->builder->caching($this->cachable);
        $this->builder->table($this->table);
    }

    /**
     * Insert a new record into the current database.
     *
     * @param array<string, mixed> $values nested array of values to insert into table.
     * 
     * @return int Return the number of records inserted.
    */
    abstract protected function insert(array $values): int;

    /**
     * Update current record in the database.
     *
     * @param string|array<int, mixed> $key The key?s to update its record
     * @param array<string, mixed> $setValues associative array of columns and values to update.
     * 
     * @return int Return the number of records updated.
    */
    abstract protected function update(string|array $key, array $data): int;

    /**
     * Fine next or a single record from the database table.
     *
     * @param string|array<int, mixed> $key The key?s to find its record
     * @param array<int, string> $fields The fields to retrieve (default is all).
     * 
     * @return mixed Return selected records or false on failure.
    */
    abstract protected function find(string|array $key, array $fields = ['*']): mixed;

    /**
     * Select records from the database table.
     *
     * @param string|array<int, mixed> $key The key?s to select its record, if null all recoard in table will be selected.
     * @param array<int, string> $fields The fields to retrieve (default is all).
     * 
     * @return mixed Return selected records or false on failure.
    */
    abstract protected function select(string|array $key = null, array $fields = ['*']): mixed;

    /**
     * Select records from the database.
     *
     * @param string $query Search query string, escape string before passing.
     * @param array<int, string> $fields The fields to retrieve (default is all).
     * 
     * @return mixed Return found records or false on failure.
    */
    abstract protected function search(string $query, array $fields = ['*']): mixed;

    /**
     * Delete a record from the database.
     * 
     * @param string|array<int, mixed> $key The keys to delete, if null all recoard in table will be deleted.
     * 
     * @return bool Return true if the record was successfully deleted otherwise false.
    */
    abstract protected function delete(string|array $key = null): bool;

    /**
     * Get total number of records in the database.
     * 
     * @return int Return the number of records.
    */
    abstract protected function total(): int;

    /**
     * Get total number of records in the database based on the keys.
     * 
     * @param string|array<int, mixed> $key The key?s to find total number of matched.
     * 
     * @return bool Return the number of records.
    */
    abstract protected function count(string|array $key): int;

    /**
     * Run a search in database table of current model. 
     * 
     * @param string $query search query string, escape string before passing.
     * @param array<int, string> $fields The fields to retrieve (default is all).
     * @param int $limit search limit default is 100.
     * @param string $offset search limit offset default is 0.
     * @param string $flag Search matching flag, default is (any) any matching keyword.
     * 
     * @return mixed $results results.  
     * @throws RuntimeException If the third pary search controller class is not installed.
    */
    public function doSearch(string $query, array $fields = ['*'], int $limit = 100, int $offset = 0, string $flag = 'any'): mixed 
    {
        if ($limit < 0 || $offset < 0 || empty($query)) {
            return false;
        }

        $search = $this->searchInstance($query, $flag);
        $search->setQuery($query)->split();
        $sqls = $search->getQuery();

        if(empty($sqls)){
            return false;
        }

        $sql = 'SELECT ' . implode(',', $fields) . ' FROM ' . $this->table . ' ' . $sqls . ' LIMIT ' . $offset . ', ' . $limit;

        $tbl = $this->builder->query($sql);
        $tbl->cache('SEARCH', md5($this->table . $query));
        $result = $tbl->execute();

        if(empty($result)){
            return false;
        }

        return $result;
    }

    /**
     * Return search controller class instance. 
     * 
     * @param string $flag Search matching flag.
     * 
     * @return Searchable Search controller instance.
     * @throws RuntimeException If the third pary search controller class is not installed.
    */
    protected function searchInstance(string $flag): Searchable
    {
        if(!class_uses(Searchable::class)){
            throw new RuntimeException('The search controller library is not installed. Please run the composer command "composer require peterujah/php-search-controller" to install it.');
        }

        $flags = [
            'start' => Searchable::START_WITH_QUERY,
            'end' => Searchable::END_WITH_QUERY,
            'any' => Searchable::HAVE_ANY_QUERY,
            'second' => Searchable::HAVE_SECOND_QUERY,
            'length2' => Searchable::START_WITH_QUERY_2LENGTH,
            'length3' => Searchable::START_WITH_QUERY_3LENGTH,
            'startend' => Searchable::START_END_WITH_QUERY,
        ];

        $flag = $flags[$flag] ?? Searchable::HAVE_ANY_QUERY;

        static::$searchInstance ??= new Searchable();
        static::$searchInstance->setOperators($flag);
        static::$searchInstance->setParameter($this->searchables);

        return static::$searchInstance;
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
     * @return array<int, string> Return table searchable column names.
     */
    public function getSearchable(): array
    {
        return $this->searchables;
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
     * Magic method getter
     *
     * @param string $key property key
     * 
     * @return ?mixed return property else null
     * @ignore
    */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }
    
    /**
     * Magic method isset
     * Check if property is set
     *
     * @param string $key property key
     * 
     * @return bool 
     * @ignore
    */
    public function __isset(string $key): bool
    {
        return isset($this->{$key});
    }
}