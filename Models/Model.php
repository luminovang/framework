<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Models;

use Luminova\Database\Query;

abstract class Model
{
    /**
     *  Table name should be specified in child models.
     * @var string $table
    */
    protected string $table = ''; 

    /**
     *  Default primary key column.
     * @var string $primaryKey
    */
    protected string $primaryKey = 'uid'; 

    /**
     * Fields that can be inserted or updated.
     * @var array $allowedFields
    */
    protected array $allowedFields = []; 

    /**
     * Input validation rules for
     * @var array $validationRules
    */
    protected array $validationRules = [];

    /**
     * Input validation message for rules
     * @var array $validationMessages
    */
    protected array $validationMessages = [];

    /**
     * Database query class instance
     * @var Query $query
    */
    protected $query = null;

    /**
     * Constructor for the Model class.
     */
    public function __construct()
    {
        $this->query = Query::getInstance();
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $data The data to be inserted.
     * 
     * @return int 
     */
    public function insertRecord(array $data): int
    {
        return $this->query->table($this->table)->insert($data);
    }

    /**
     * Update a record in the database.
     *
     * @param string $key The primary key value for the record to be updated.
     * @param array $data The data to be updated.
     * 
     * @return int 
     */
    public function updateRecord(string $key, array $data): int
    {
        return $this->query->table($this->table)->where($this->primaryKey, $key)->update($data);
    }

    /**
     * Get a record from the database.
     *
     * @param string $key The primary key value for the record to retrieve.
     * @param array $fields The fields to retrieve (default is all).
     * 
     * @return mixed An associative array representing the record, or null if not found.
     */
    public function getRecord(string $key, array $fields = ["*"]): mixed
    {
        return $this->query->table($this->table)->where($this->primaryKey, $key)->find($fields);
    }

    /**
     * Select records from the database.
     *
     * @param string $key The primary key value for the record to start the selection from.
     * @param array $fields Additional selection criteria.
     * 
     * @return mixed An array of records matching the criteria.
     */
    public function selectRecords(string $key, array $fields): mixed
    {
        return $this->query->table($this->table)->where($this->primaryKey, $key)->select($fields);
    }

    /**
     * Delete a record from the database.
     *
     * @param string $key The primary key value for the record to be deleted.
     * 
     * @return int 
     */
    public function deleteRecord(string $key): bool
    {
        return $this->query->table($this->table)->where($this->primaryKey, $key)->delete();
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
}