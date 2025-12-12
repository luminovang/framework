<?php
/**
 * Luminova Framework database model.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database;

use Throwable;
use Luminova\Base\Model;
use Luminova\Database\Builder;
use Luminova\Components\Collections\Collection;
use Luminova\Exceptions\{RuntimeException, DatabaseException};

/**
 * Base model class for database record operations.
 * 
 * @see Model
 */
class DatabaseModel
{
    /**
     * Model table name.
     * @var object{
     *     table:string,
     *     class:class-string<T>,
     *     primaryKey:string,
     *     columns:array<int,string>,
     *     insertable:array<int,string>,
     *     updatable:array<int,string>,
     *     searchable:array<int,string>,
     *     resultType:string,
     *     cacheable:bool,
     *     resultType:string,
     *     expiry: \DateTimeInterface|int|null
     * } $definition
     */
    protected ?object $definition = null;

    /**
     * Last inserted record identifier.
     *
     * @var mixed $lastInsertId
     */
    protected mixed $lastInsertId = null;

    /**
     * Creates a database model wrapper instance.
     *
     * Stores the provided model instance and initializes the model definition
     * metadata used by the database layer for query operations.
     *
     * @param Model $model Model instance containing table configuration,
     *                     attributes, and query-related settings.
     */
    public function __construct(private Model $model)
    {
        $this->definition = $model->getDefinition();
    }

    /**
     * Updates a model definition property.
     *
     * Modifies a configuration value stored in the model definition metadata.
     * This can be used to adjust database model behavior dynamically.
     *
     * @param string $property Definition property name.
     * @param mixed $value New property value.
     *
     * @return void
     */
    public function setDefinition(string $property, mixed $value): void
    {
        $this->definition->{$property} = $value;
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
     * Creates a new query builder for the model's database table.
     *
     * The returned builder is configured using the model's cache and
     * result type settings.
     *
     * @param string|null $alias Optional table alias.
     *
     * @return Builder Returns a configured query builder instance.
     */
    public function table(?string $alias = null): Builder
    {
        return Builder::table($this->definition->table, $alias)
            ->useConnection($this->model->getConnection())
            ->cacheable($this->definition->cacheable)
            ->returns($this->definition->resultType);
    }

    /**
     * Creates a new query builder from a raw SQL statement.
     *
     * The returned builder is configured using the model's cache and
     * result type settings.
     *
     * @param string $sql Raw SQL query.
     *
     * @return Builder Returns a configured query builder instance.
     *
     * @see Builder::execute() Execute the query.
     */
    public function query(string $sql): Builder
    {
        return Builder::query($sql)
            ->useConnection($this->model->getConnection())
            ->cacheable($this->definition->cacheable)
            ->returns($this->definition->resultType);
    }

    /**
     * Creates a new query builder with an initial WHERE condition.
     *
     * The returned builder is configured using the model's cache and
     * result type settings.
     *
     * @param string $column The column name.
     * @param string $operation Comparison operator (e.g, `=`, `!=`, `<`, `<>` etc..).
     * @param mixed $value Value to compare against.
     *
     * @return Builder Returns a configured query builder instance.
     *
     * @see Builder::get() Execute the query and return the result.
     * @see Builder::next() Execute the query and return the next result.
     * @see Builder::count() Execute the query and return the record count.
     * @see Builder::average() Execute the query and return the average value.
     */
    public function where(string $column, string $operation, mixed $value): Builder
    {
        return $this->table()
            ->where($column, $operation, $value);
    }

    /**
     * Creates a single record in the model's database table.
     *
     * Unlike {@see self::insert()}, this method only accepts a single
     * associative array of column values. The inserted record identifier is
     * stored as the current model identifier for subsequent operations such as
     * {@see self::find()}, {@see self::update()}, and {@see self::delete()}.
     *
     * Only columns allowed by `$insertable` can be included in the insert data.
     *
     * @param array<string,mixed> $attributes Associative array of column names and values.
     *
     * @return Model|null Returns instance of model if record was created successfully, otherwise `null`.
     * @throws RuntimeException If the model has no primary key, the data is empty,
     *                          or contains columns that are not allowed by `$insertable`.
     *
     * @see self::insert() Insert one or more records.
     * 
     * > **Note:**
     * > Attributes are synchronized with the current model after record was created.
     */
    public function create(array $attributes): ?Model
    {
        if ($attributes === []) {
            throw new RuntimeException('The insert attributes cannot be empty.');
        }

        if (array_is_list($attributes)) {
            throw new RuntimeException(
                'Bulk inserts are not supported by create(). Use insert() instead.'
            );
        }

        if (!isset($attributes[$this->definition->primaryKey])) {
            $identifier = $this->model->getIdentifier();

            if ($identifier === '' || $identifier === null) {
                throw new RuntimeException(
                    'Cannot create a record without a primary key.'
                );
            }

            $attributes[$this->definition->primaryKey] = $identifier;
        }

        if($this->insert($attributes)){
            $this->model->sync($attributes);
            return $this->model;
        }

        return null;
    }

    /**
     * Inserts one or more record into the model's database table.
     *
     * The inserted record identifier is stored as the current model record
     * identifier and can be used by subsequent operations such as
     * {@see self::find()}, {@see self::update()}, and {@see self::delete()}.
     *
     * Only columns allowed by `$insertable` can be included in the insert data.
     *
     * @param array<string,mixed>|array<int,array<string,mixed>> $values Associative array of column names and values to insert.
     *
     * @return bool Returns `true` if the record was inserted successfully, otherwise `false`.
     * @throws RuntimeException If the insert data contains columns that are not
     *                          allowed by `$insertable`.
     *
     * @see self::sync() Synchronize the inserted record with the current model.
     */
    public function insert(array $values): bool
    {
        if($values === [] || $this->model->isReadOnly()){
            return false;
        }

        $this->model->assertAllowedColumns($values, Model::TYPE_INSERT);
        $tbl = Builder::table($this->definition->table)
            ->useConnection($this->model->getConnection());

        if($tbl->insert($values) > 0){
            $this->lastInsertId = $tbl->getLastInsertedId();

            if($this->model->getIdentifier() === null){
                $this->model->identifier = $this->lastInsertId;
            }

            return true;
        }

        return false;
    }

    /**
     * Updates the current record in the database.
     *
     * Updates the record matching the specified identifier. If no identifier
     * is provided, the model will use the current record identifier,
     * when available.
     *
     * The model must not be marked as read-only, and only columns defined
     * in `$updatable` are allowed to be modified.
     *
     * @param string|int|null $identifier Record identifier. If `null`, the model  identifier is used.
     * @param array<string,mixed> $attributes Associative array of column names and  values to update.
     *
     * @return Model|null Returns instance of model if record was updated successfully, otherwise `null`.
     * @throws RuntimeException If the update data contains columns that are not
     *                          allowed by `$updatable`.
     * 
     * > **Note:**
     * > Attributes are synchronized with the current model after record was updated.
     */
    public function update(string|int|null $identifier, array $attributes): ?Model  
    {
        if($this->model->isReadOnly()){
            return null;
        }
        
        $identifier ??= $this->model->getIdentifier();

        if($identifier === '' || $identifier === null){
            throw new RuntimeException(
                'Cannot update a record without a primary key.'
            );
        }

        $this->model->assertAllowedColumns($attributes, Model::TYPE_UPDATE);

        $result = Builder::table($this->definition->table)
            ->useConnection($this->model->getConnection())
            ->where($this->definition->primaryKey, '=', $identifier)
            ->limit(1)
            ->update($attributes);

        if($result > 0){
            $this->model->sync($attributes);
            return $this->model;
        }

        return null;
    }

    /**
     * Finds a single record by its primary key.
     *
     * Retrieves a record matching the specified identifier. If no identifier
     * is provided, the model will use the mode record identifier, when available.
     *
     * @param string|int|null $identifier Record identifier. If `null`, the model identifier is used.
     * @param array<int,string>|null $columns Columns to retrieve. 
     *                               - Defaults to the  model's configured columns.
     * @param string|null $cacheKey Optional cache key. If `null`, a stable cache
     *                              key is generated from the request URI and query state.
     *
     * @return Model|mixed Returns the matching record, or `false` if no identifier is available.
     * @throws DatabaseException If the query fails.
     *
     * @see self::sync() Synchronize the retrieved record with the current model.
     * @see self::setReturn() Configure the query result hydration type.
     */
    public function find(
        string|int|null $identifier = null, 
        ?array $columns = null, 
        ?string $cacheKey = null
    ): mixed 
    {
        $identifier ??= $this->model->getIdentifier();

        if($identifier === '' || $identifier === null){
            return null;
        }

        return $this->select(
            $identifier, 
            $columns, 
            limit: 1, 
            cacheKey: $cacheKey
        );
    }

    /**
     * Selects all records from current model's database table.
     *
     * Retrieves records from the model's table using the configured result type.
     * Results can be limited, offset, and optionally cached.
     * 
     * @template T of Model|object
     *
     * @param array<int,string>|null $columns Columns to retrieve. If `null`, the
     *                                        model's configured columns are used.
     * @param int|null $limit Maximum number of records to return. If `null`, no
     *                        limit is applied.
     * @param int $offset Number of records to skip before returning results.
     * @param string|null $cacheKey Optional cache key. If `null`, a stable cache
     *                              key is generated from the request URI and query
     *                              state.
     *
     * @return Collection<T> Returns the selected records based on the configured result
     *               type, or `false` on failure.
     * @throws DatabaseException If the query fails.
     */
    public function all(
        ?array $columns = null,
        ?int $limit = null,
        int $offset = 0,
        ?string $cacheKey = null
    ): Collection
    {
        return new Collection($this->select(
            null, 
            $columns, 
            $limit, 
            $offset, 
            $cacheKey
        ) ?: []);
    }

    /**
     * Selects records from the model's database table.
     *
     * Retrieves records optionally filtered by their primary key. When the
     * identifier parameter is an array, records matching any of the provided
     * identifiers are returned. If no identifier is provided, records are
     * selected from the entire table.
     *
     * Results are returned according to the configured model result type.
     *
     * @param string|int|string[]|int[]|null $identifier Record identifier or list of identifiers to select. 
     *                                                      If `null`, all records are selected.
     * @param array<int,string> $columns Columns to retrieve. Defaults to all columns.
     * @param int|null $limit Maximum number of records to return.
     * @param int $offset Number of records to skip before returning results.
     * @param string|null $cacheKey Optional cache key. If `null`, a stable cache
     *                              key is generated from the request URI and query
     *                              state.
     *
     * @return mixed Returns selected records based on the configured result type,
     *               or `false` on failure.
     * 
     * @throws DatabaseException If the query fails.
     */
    public function select(
        array|string|int|null $identifier = null,
        ?array $columns = null,
        ?int $limit = 20,
        int $offset = 0,
        ?string $cacheKey = null
    ): mixed
    {
        $columns ??= $this->definition->columns;

        $tbl = self::table();

        ($limit === 1) 
            ? $tbl->find($columns) 
            : $tbl->select($columns);

        if($limit !== null){
            $tbl->limit($limit, $offset);
        }

        return $this->withWhere($tbl, $identifier, $cacheKey)->get();
    }

    /**
     * Deletes a single record from current model's table by primary key.
     *
     * Deletes the record matching the provided identifier. If no identifier is
     * provided, the current model record identifier is used.
     *
     * @param string|int|null $identifier Record identifier to delete. If `null`,
     *                                    the current model identifier is used.
     *
     * @return bool Returns `true` if the record was deleted successfully,  otherwise `false`.
     * 
     * @see self::purge() To clear cache if any.
     * @throws DatabaseException If the query fails.
     */
    public function delete(string|int|null $identifier = null): bool
    {
        if ($this->model->isReadOnly()) {
            return false;
        }

        $identifier ??= $this->model->getIdentifier();

        if ($identifier === null || $identifier === '') {
            return false;
        }

        if (!$this->deleteItems($identifier, 1)) {
            return false;
        }

        $key = $this->definition->primaryKey;

        if (
            $this->model->has($key)
            && (string) $this->model->get($key) === (string) $identifier
        ) {
            $this->model->clearAttributes();
        }

        return true;
    }

    /**
     * Determines whether one or more records exist in current model's database table.
     *
     * Checks for records matching the provided identifier or list of identifiers.
     * If no identifier is provided, the current model record identifier is used.
     *
     * When an array of identifiers is provided, the query checks whether any
     * matching record exists using the model primary key.
     *
     * @param array<int,string|int>|string|int|null $identifier Record identifier or list of identifiers to check. 
     *                                                  If `null`, the current model identifier is used.
     * @param string|null $cacheKey Optional cache key. If `null`, a stable cache
     *                              key is generated from the request URI and query
     *                              state.
     *
     * @return bool Returns `true` if matching record(s) exist, otherwise `false`.
     * @throws DatabaseException If the query fails.
     */
    public function exists(array|string|int|null $identifier = null, ?string $cacheKey = null): bool
    {
        $identifier ??= $this->model->getIdentifier(); 

        if($identifier === null || $identifier === '' || $identifier === []){
            return false;
        }

        return $this->withWhere(
            Builder::table($this->definition->table)
                ->useConnection($this->model->getConnection()), 
            $identifier,
            $cacheKey
        )->exists();
    }
   
    /**
     * Deletes multiple records from current model's database table by identifier.
     *
     * Deletes records matching the provided list of primary key identifiers.
     * This operation is ignored when the model is marked as read-only.
     *
     * @param array<int,string|int> $identifiers List of record identifiers to delete.
     * @param int|null $limit Maximum number of records to delete.
     *
     * @return bool Returns true if one or more records were deleted; otherwise, false.
     *
     * @throws DatabaseException If the query fails.
     */
    public function deleteMany(array $identifiers, ?int $limit = null): bool
    {
        if ($identifiers === []) {
            return false;
        }

        return $this->deleteItems($identifiers, $limit);
    }

    /**
     * Deletes all records from current model's database table.
     *
     * Removes all records from the configured table. This operation is ignored when
     * the model is marked as read-only.
     *
     * @return bool Returns true if one or more records were deleted; otherwise, false.
     *
     * @throws DatabaseException If the query fails.
     */
    public function deleteAll(): bool
    {
        return $this->deleteItems(null, null);
    }

    /**
     * Gets the total number of unique records in the model's database table.
     *
     * Counts unique primary key values from the model's table. This ensures each
     * record is counted once even when joins or duplicate rows are introduced.
     *
     * @param string|null $cacheKey Optional cache key. If `null`, a stable cache
     * key is generated from the request URI and query state.
     *
     * @return int Returns the number of unique records.
     * @throws DatabaseException If the query fails.
     */
    public function total(?string $cacheKey = null): int
    {
        return $this->count(
            column: $this->definition->primaryKey,
            distinct: true,
            cacheKey: $cacheKey
        );
    }

    /**
     * Counts records in the model's database table.
     *
     * When an identifier or list of identifiers is provided, only records matching
     * the model primary key are counted. Otherwise, all records matching the current
     * query conditions are counted.
     *
     * The count can be performed on a specific column and optionally return only
     * distinct values.
     *
     * @param array<int,string|int>|string|int|null $identifier Record identifier or
     * list of identifiers to count by the model primary key. If `null`, no identifier
     * filter is applied.
     * @param array|string $column The column or columns to count.
     * @param bool $distinct Whether to count only distinct values.
     * @param string|null $cacheKey Optional cache key. If `null`, a stable cache
     * key is generated from the request URI and query state.
     *
     * @return int Returns the number of matching records.
     *
     * @throws DatabaseException If the query fails.
     */
    public function count(
        array|string|int|null $identifier = null,
        array|string $column = '*', 
        bool $distinct = false, 
        ?string $cacheKey = null
    ): int 
    {
        return (int) $this->withWhere(
            Builder::table($this->definition->table)
                ->count($column, $distinct)
                ->useConnection($this->model->getConnection()), 
            $identifier, 
            $cacheKey
        )->get();
    }

    /**
     * Searches records using from current model's database table using configured searchable columns.
     *
     * Performs a keyword search against the columns defined in the model's
     * searchable configuration. Records are returned when any configured column
     * matches the provided keyword.
     *
     * The search supports different matching patterns, optional keyword splitting,
     * case-sensitive comparisons, and custom collations. By default, the search
     * performs a case-insensitive partial match using the configured database
     * collation.
     *
     * When no searchable columns are configured or the keyword is empty, no query
     * is executed and `false` is returned.
     *
     * @param string $keyword The keyword or phrase to search for.
     * @param array<int,string>|null $columns Columns to retrieve. When `null`, the
     *                                        model's default columns are selected.
     * @param string $pattern Search pattern to apply (default: `Builder::SEARCH_CONTAINS`).
     * @param bool $splitKeyword Whether to split a multi-word keyword into separate search terms.
     * @param bool $caseSensitive Whether matching should consider character case.
     * @param int $limit Maximum number of records to return.
     * @param int $offset Number of records to skip before returning results.
     * @param string|null $collation Optional comparison collation used for matching.
     *                               Controls character comparison behavior such as
     *                               case sensitivity and accent handling.
     * @param string|null $cacheKey Optional cache key. When `null`, a stable cache
     *                              key is generated from the query state.
     *
     * @return Collection|Model|mixed Returns matching records based on the configured model result
     *               type, or `false` when the keyword is empty or no searchable
     *               columns are configured.
     *
     * @throws DatabaseException If query execution fails.
     *
     * @example - Search posts by configured searchable columns:
     *
     * ```php
     * Post::search('wireless keyboard');
     * ```
     *
     * @example - Search with split keywords and case-sensitive matching:
     *
     * ```php
     * Post::search(
     *     'Wireless Keyboard',
     *     splitQuery: true,
     *     caseSensitive: true,
     *     collation: 'utf8mb4_bin'
     * );
     * ```
     */
    public function search(
        string $keyword,
        ?array $columns = null,
        string $pattern = Builder::SEARCH_CONTAINS,
        bool $splitKeyword = false,
        bool $caseSensitive = false,
        int $limit = 100,
        int $offset = 0,
        ?string $collation = null,
        ?string $cacheKey = null
    ): mixed
    {
        $keyword = trim($keyword);

        if ($keyword === '' || $this->definition->searchable === []) {
            return false;
        }

        $search = self::table()
            ->select($columns ?? $this->definition->columns)
            ->match($this->definition->searchable)
            ->whereSearch(
                $keyword, 
                $pattern, 
                $splitKeyword, 
                $caseSensitive, 
                $collation
            )
            ->limit($limit, $offset);

        $result = $this->withWhere(
            $search,
            cacheKey: $cacheKey
        )->get();

        if($result && is_array($result)){
            return new Collection($result);
        };

        return $result;
    }

    /**
     * Clears cached data associated with the current model.
     *
     * Removes stored query cache entries for the model table to ensure future
     * queries return fresh data after record changes.
     *
     * @return bool Returns `true` if cached data is cleared successfully,
     *              otherwise `false`.
     */
    public function purge(): bool 
    {
        if(!$this->definition->cacheable){
            return true;
        }

        $storage = $this->model->getCachePersistentId();

        if ($storage === null || $storage === '') {
            return false;
        }

        return Builder::table($this->definition->table)
            ->cacheable($this->definition->cacheable)
            ->cache('*', $this->definition->table, persistentId: $storage)
            ->clearCache();
    }

    /**
     * Deletes records from current model's database table.
     *
     * Deletes records matching the provided identifier or list of identifiers.
     * If the identifier is `null`, all records in the table will be deleted.
     *
     * This operation is ignored when the model is marked as read-only.
     *
     * @param array<int,string|int>|string|int|null $identifier Record identifier
     *                                                          or list of identifiers
     *                                                          to delete. If `null`,
     *                                                          all records are deleted.
     * @param int|null $limit Maximum number of records to delete.
     *
     * @return bool Returns `true` if one or more records were deleted, otherwise `false`.
     * @throws DatabaseException If the query fails.
     */
    private function deleteItems(array|string|int|null $identifier, ?int $limit = null): bool
    {
        if($this->model->isReadOnly()){
            return false;
        }

        $tbl = Builder::table($this->definition->table)
            ->useConnection($this->model->getConnection());

        if($identifier !== null){
            if(is_array($identifier)){
                $limit ??= count($identifier);
                $tbl->whereIn($this->definition->primaryKey, $identifier);
            }else{
                $limit ??= 1;
                $tbl->where($this->definition->primaryKey, '=', $identifier);
            }
        }

        if($limit){
            $tbl->limit($limit);
        }

        if($tbl->delete() > 0){
            if($identifier === null){
                try{
                    $this->purge();
                } catch(Throwable){}
            }

            return true;
        }

        return false;
    }

    /**
     * Apply identifier filtering and cache configuration to a query builder.
     *
     * Adds a primary key condition to the query when an identifier is provided.
     * Supports both single identifiers and multiple identifiers using an `IN`
     * condition. If persistent caching is enabled for the model, the configured
     * cache settings are also applied to the builder.
     *
     * @param Builder $builder Query builder instance.
     * @param array|string|int|null $identifier Optional record identifier or list
     *                                            of identifiers to filter by.
     * @param string|null $cacheKey Optional cache key for storing the query result.
     *
     * @return Builder The configured query builder instance.
     */
    private function withWhere(
        Builder $builder,
        array|string|int|null $identifier = null,
        ?string $cacheKey = null
    ): Builder
    {
        if ($identifier !== null && $identifier !== '') {
            if (is_array($identifier)) {
                $builder->whereIn(
                    $this->definition->primaryKey,
                    $identifier
                );
            } else {
                $builder->where(
                    $this->definition->primaryKey,
                    '=',
                    $identifier
                );
            }
        }

        $storage = $this->model->getCachePersistentId();

        if ($storage === null || $storage === '') {
            return $builder;
        }

        return $builder->cache(
            $cacheKey,
            $this->definition->table,
            $this->definition->expiry,
            $storage
        );
    }
}