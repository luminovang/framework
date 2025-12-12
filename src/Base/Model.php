<?php
declare(strict_types=1);
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

use \Throwable;
use \Serializable;
use \JsonSerializable;
use Luminova\Luminova;
use \DateTimeInterface;
use Luminova\Database\Connection;
use Luminova\Security\Validation;
use Luminova\Database\Query\Builder;
use Luminova\Exceptions\LogicException;
use Luminova\Exceptions\RuntimeException;
use Luminova\Interface\DatabaseInterface;
use Luminova\Interface\LazyObjectInterface;
use Luminova\Components\Collections\Collection;
use Luminova\Database\Query\Helpers\QueryModel;
use Luminova\Exceptions\InvalidArgumentException;
use Luminova\Exceptions\UnexpectedValueException;

/**
 * Base model class for database record operations.
 *
 * Model queries return results based on the configured return type.
 * By default, model queries return hydrated model instances unless the return
 * type is changed using {@see Model::setReturn()}.
 *
 * Static model calls and instantiated model objects may have different
 * execution flows but follow the same configured return behavior.
 * 
 * @link https://luminova.ng/docs/0.0.0/base/database-model
 *
 * @example - Examples:
 * ```php
 * // Static call returns a hydrated model instance.
 * $user = User::find(100);
 *
 * // Instance call returns a hydrated model instance by default.
 * $user = new User(100);
 * $user = $user->find();
 *
 * // Change instance return type.
 * $user = (new User(100))
 *     ->setReturn('object')
 *     ->find();
 * ```
 * 
 * @mixin QueryModel
 * @template T of Model|object
 *
 * Database operations are delegated to the underlying database model handler.
 *
 * @method ?Model create(array<string,mixed>|array<int,array<string,mixed>> $attributes)
 *         Creates a single record in the model's database table {@see QueryModel::create()}.
 * 
 * @method ?Model update(string|int|null $identifier, array<string,mixed> $attributes)
 *         Updates the current record in the database {@see QueryModel::update()}.
 * 
 * @method Model|mixed find(string|int|null $identifier = null, ?array<int,string> $columns = null, ?string $cacheKey = null)
 *         Finds a single record by its primary key {@see QueryModel::find()}.
 *
 * @method bool delete(string|int|null $identifier = null)
 *         Deletes a single record from current model's table by primary key {@see QueryModel::delete()}.
 *
 * @method bool exists(array|string|int|null $identifier = null, ?string $cacheKey = null)
 *         Determines whether one or more records exist in current model's database table {@see QueryModel::exists()}.
 *
 * @method int total(?string $cacheKey = null)
 *         Gets the total number of unique records in the model's database table {@see QueryModel::total()}.
 *
 * 
 * @method bool insert(array<string,mixed>|array<int,array<string,mixed>> $values)
 *         Inserts one or more record into the model's database table {@see QueryModel::insert()}.
 * 
 * @method mixed select(array|string|int|null $identifier = null, array<int,string> $columns = [], int $limit = 20, int $offset = 0, ?string $cacheKey = null)
 *         Selects records from the model's database table {@see QueryModel::select()}.
 * 
 * @method Collection<T> all(?array<int,string> $columns = null, ?int $limit = null, int $offset = 0, ?string $cacheKey = null)
 *         Selects all records from current model's database table {@see QueryModel::all()}.
 *
 * @method bool deleteMany(array<int,string|int> $identifiers, ?int $limit = null)
 *         Deletes multiple records from current model's database table by identifiers {@see QueryModel::deleteMany()}.
 * 
 * @method bool deleteAll()
 *         Deletes all records from current model's database table {@see QueryModel::deleteAll()}.
 *
 * @method int count(array<int,string|int>|string|int|null $identifier = null, array|string $column = '*', bool $distinct = false, ?string $cacheKey = null)
 *         Counts records in the model's database table {@see QueryModel::count()}.
 *
 * @method Collection<T>|Model|mixed search(string $keyword, ?array<int,string> $columns = null, string $pattern = Builder::SEARCH_CONTAINS, bool $splitKeyword = false, bool $caseSensitive = false, int $limit = 100, int $offset = 0, ?string $collation = null, ?string $cacheKey = null)
 *         Searches records using from current model's database table using configured searchable columns {@see QueryModel::search()}.
 * 
 * @method Builder table(?string $alias = null)
 *         Creates a new query builder for the model's database table {@see QueryModel::table()}.
 * 
 * @method Builder query(string $sql)
 *         Creates a new query builder from a raw SQL statement {@see QueryModel::query()}.
 * 
 * @method Builder where(string $column, string $operation, mixed $value)
 *         Creates a new query builder with an initial WHERE condition {@see QueryModel::where()}.
 */
abstract class Model implements Serializable, JsonSerializable, LazyObjectInterface
{
    /**
     * Operation type for validating columns used in insert queries.
     *
     * @var string TYPE_INSERT
     */
    public const TYPE_INSERT = 'insert';

    /**
     * Operation type for validating columns used in update queries.
     *
     * @var string TYPE_UPDATE
     */
    public const TYPE_UPDATE = 'update';

    /**
     * Operation type for validating columns used in search queries.
     *
     * @var string TYPE_SEARCH
     */
    public const TYPE_SEARCH = 'search';

    /**
     * Operation type for validating columns selected from queries.
     *
     * @var string TYPE_SELECT
     */
    public const TYPE_SELECT = 'select';

    /**
     * Operation type for validating model data attributes.
     *
     * @var string TYPE_ATTRIBUTE
     */
    public const TYPE_ATTRIBUTE = 'attribute';

    /**
     * Return query results as instances of the current model class.
     *
     * @var string RETURN_SELF
     */
    public const RETURN_SELF = 'self';

    /**
     * Model attributes.
     *
     * @var array $attributes
     */
    protected array $attributes = [];

    /**
     * Allows direct PHP include/require statements in this model.
     *
     * When enabled, the debugger skips include/require enforcement.
     *
     * @var bool $allowIncludes
     * @see #[AllowIncludes] Class-level attribute.
     */
    protected bool $allowIncludes = false;

    /**
     * Model table name.
     *
     * @var string $table
     */
    protected string $table = '';

    /**
     * Model primary key column.
     *
     * @var string $primaryKey
     */
    protected string $primaryKey = '';

    /**
     * Whether query builder caching is enabled.
     *
     * @var bool $cacheable
     */
    protected bool $cacheable = false;

    /**
     * Query result hydration type.
     *
     * Supported values:
     * - `array`  Returns associative arrays.
     * - `object` Returns standard objects.
     * - `self`   Returns instances of the current model.
     * - Class name Returns instances of the specified class.
     *
     * @var string $resultType
     * 
     * @see self::setReturn()
     * Recommended `self::RETURN_SELF`
     */
    protected string $resultType = self::RETURN_SELF;

    /**
     * Model cache persistent identifier.
     *
     * @var string|null $persistentId
     */
    protected ?string $persistentId = null;

    /**
     * Whether the model is read-only.
     *
     * Prevents insert, update, and delete operations.
     *
     * @var bool $readOnly
     */
    protected bool $readOnly = false;

    /**
     * Columns included when performing search queries.
     *
     * These columns are matched against the search keyword when using
     * the model's search methods. Leave empty to disable column-based
     * searching or define the searchable fields explicitly.
     *
     * @var array<int,string> $searchable
     */
    protected array $searchable = [];

    /**
     * Columns allowed during insert operations.
     *
     * Only the listed columns can be included when creating new records.
     * Leave empty to allow all columns supported by the model.
     *
     * @var array<int,string> $insertable
     */
    protected array $insertable = [];

    /**
     * Default columns selected when retrieving records.
     *
     * These columns are used unless a custom selection is specified
     * for the query. The default value of `['*']` selects all columns.
     *
     * @var array<int,string> $columns
     * 
     * @see self::select()
     * @see self::find()
     * @see self::all()
     * @see self::where()
     * @see QueryModel::where()
     * @see QueryModel::select()
     */
    protected array $columns = ['*'];

    /**
     * Columns allowed during update operations.
     *
     * Only the listed columns can be modified when updating existing
     * records. Leave empty to allow all updatable columns.
     *
     * @var array<int,string> $updatable
     * 
     * @see self::update()
     * @see QueryModel::update()
     */
    protected array $updatable = [];

    /**
     * Validation rules.
     *
     * @var array<string,string> $rules
     * 
     * @see self::validation()
     */
    protected array $rules = [];

    /**
     * Validation error messages.
     *
     * @var array<string,array> $messages
     * 
     * @see self::validation()
     */
    protected array $messages = [];

    /**
     * Query cache lifetime in seconds.
     *
     * @var DateTimeInterface|int $expiry
     */
    protected DateTimeInterface|int $expiry = 7 * 24 * 60 * 60;

    /**
     * Database connection assigned to the model.
     *
     * Stores the database connection or driver instance used by the model for
     * executing database operations. When null, the model uses the default
     * configured database connection.
     *
     * @var DatabaseInterface|Connection|null $dbConnection
     * 
     * @see self::useConnection()
     */
    protected DatabaseInterface|Connection|null $dbConnection = null;

    /**
     * Indicates whether the assigned database connection is shared.
     *
     * A shared connection can be reused across model operations, while a non-shared
     * connection is treated as dedicated to the current model instance.
     *
     * @var bool $isSharedConnection
     * 
     * @see self::useConnection()
     */
    protected bool $isSharedConnection = true;

    /**
     * Current model record identifier.
     *
     * Stores the identifier of the record currently associated with the model.
     * It may be assigned automatically after insert operations or manually set
     * to target a specific record.
     *
     * Used as the default identifier for {@see self::find()},
     * {@see self::update()}, and {@see self::delete()} when no identifier is
     * explicitly provided.
     *
     * @var string|int|null $identifier
     */
    protected string|int|null $identifier = null;

    /**
     * Model properties excluded from attribute synchronization.
     *
     * @var array<int,string> $ignoredColumnAttributes
     * 
     * @see self::sync()
     */
    protected array $ignoredColumnAttributes = [];

    /**
     * Mapped model attribute exclusions.
     *
     * @var array|null $mapIgnoredAttributes
     */
    private static ?array $mapIgnoredAttributes = null;

    /**
     * Database model object.
     *
     * @var QueryModel|null $db
     */
    private ?QueryModel $db = null;

    /**
     * Initialize the model instance.
     *
     * An optional identifier or attribute set may be provided when creating the
     * model. A scalar identifier is assigned to the model primary key and becomes
     * the current record identifier for operations such as {@see self::find()},
     * {@see self::update()}, and {@see self::delete()} when no identifier is given.
     *
     * When an array or object is provided, its attributes are synchronized into
     * the model using the model's attribute assignment rules.
     *
     * The result type is initialized from the configured {@see $resultType}. When
     * set to {@see self::RETURN_SELF}, the current model class is used as the
     * result type.
     *
     * @param array<string,mixed>|object{string:mixed}|string|int|null $attributes Optional record
     *        identifier or initial model attributes.
     *
     * @throws InvalidArgumentException If the supplied attributes are not
     *                                  associative or required primary-key data
     *                                  is missing.
     *
     * @see Builder
     * @see self::onCreate()
     *
     * @example - Create a model instance with an identifier:
     * ```php
     * // Set the user's current identifier.
     * $user = new User(100);
     *
     * // Override the current identifier.
     * $user->find(200);
     * ```
     *
     * @example - Create a model instance with attributes:
     * ```php
     * $user = new User([
     *     'id' => 100,
     *     'name' => 'John',
     *     'email' => 'john@example.com',
     * ]);
     * ```
     */
    public function __construct(array|object|string|int|null $attributes = null)
    {
        if ($this->resultType === self::RETURN_SELF) {
            $this->resultType = static::class;
        }

        if ($attributes !== null) {
            if (is_array($attributes) || is_object($attributes)) {
                $this->sync($attributes);
            } else {
                $this->attributes[$this->primaryKey] = $attributes;
            }
        }

        $this->db = new QueryModel($this);
        $this->onCreate();

        if (!PRODUCTION && !$this->allowIncludes) {
            \Luminova\Debugger\Tracer::assertNoIncludes($this);
        }
    }

    /**
     * Handles static method calls forwarded to the model instance.
     *
     * Creates and reuses a shared instance of the current database model class,
     * then forwards the static call to the wrapped model object.
     *
     * This allows instance model methods to be accessed using a static API style.
     *
     * @param string $method Method name being called.
     * @param array<int,mixed> $arguments Arguments passed to the method.
     *
     * @return mixed Returns the result returned by the forwarded method call.
     * @throws Throwable If the forwarded method throws an exception.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $model = new static();
        $model->assertDatabaseModel();

        return $model->db->{$method}(...$arguments);
    }

    /**
     * Handles calls to inaccessible or undefined instance methods.
     *
     * Forwards method calls to the wrapped model instance, allowing the database
     * model layer to expose model functionality without duplicating methods.
     *
     * @param string $method Method name being called.
     * @param array<int,mixed> $arguments Arguments passed to the method.
     *
     * @return mixed Returns the result returned by the forwarded method call.
     * @throws Throwable If the forwarded method throws an exception.
     */
    public function __call(string $method, array $arguments): mixed
    {
        $this->assertDatabaseModel();

        return $this->db->{$method}(...$arguments);
    }

    /**
     * Called during model initialization.
     *
     * This method is executed when the model instance is created and can be
     * overridden by subclasses to perform custom setup or initialization logic.
     *
     * @return void
     */
    protected function onCreate(): void {}

    /**
     * Set the database connection used by the model.
     *
     * Assigns a database connection or driver instance to the model for executing
     * database operations. The connection can be marked as shared or isolated
     * depending on whether it should be reused across model operations.
     *
     * @param DatabaseInterface|Connection $conn Database connection or driver instance.
     * @param bool $shared Whether the connection is shared with other operations.
     *
     * @return self The current model instance.
     */
    public function useConnection(DatabaseInterface|Connection $conn, bool $shared = true): self
    {
        $this->dbConnection = $conn;
        $this->isSharedConnection = $shared;

        return $this;
    }

    /**
     * Create a model instance from existing record data.
     *
     * The provided attributes are used to hydrate the model and must contain
     * the configured primary key. This method is intended for existing database
     * records where the record identity is required.
     *
     * @param array<string,mixed>|object{string:mixed}|self $attributes Existing model record data.
     *
     * @return static Return a new hydrated model instance.
     *
     * @throws InvalidArgumentException If the attributes are empty, not associative,
     *                                  or missing the required primary key.
     *
     * @see self::sync()
     */
    public static function make(array|object $attributes): static
    {
        if ($attributes instanceof self) {
            $attributes = $attributes->toArray();
        } elseif (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }

        if ($attributes === []) {
            throw new InvalidArgumentException(
                'Cannot create model from empty attributes.'
            );
        }

        return new static($attributes);
    }

    /**
     * Retrieve an attribute value from the model.
     *
     * Returns the value of the specified attribute, or the provided default value
     * when the attribute does not exist.
     *
     * @param string $name Attribute name.
     * @param mixed $default Default value returned when the attribute is missing.
     *
     * @return mixed The attribute value or the default value.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if ($this->has($name)) {
            return $this->attributes[$name];
        }

        return $default;
    }

    /**
     * Retrieve model attributes with reset numeric keys.
     *
     * Returns all attribute values as a sequentially indexed array.
     *
     * @return array<int,mixed> Model attribute values.
     */
    public function values(): array
    {
        return array_values($this->attributes);
    }

    /**
     * Retrieve all model attribute names.
     *
     * @return array<int,string> List of attribute names.
     */
    public function keys(): array
    {
        return array_keys($this->attributes);
    }

    /**
     * Find the first attribute value matching a callback condition.
     *
     * Iterates through the model attributes and returns the first value for which
     * the callback returns `true`. Returns `null` when no attribute matches.
     *
     * @param callable(mixed,string):bool $callback Callback receiving the attribute
     *                                               value and name.
     *
     * @return mixed The matching attribute value or null.
     */
    public function scan(callable $callback): mixed
    {
        foreach ($this->attributes as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Execute a callback for each model attribute.
     *
     * The callback receives the attribute value and attribute name. The current
     * model instance is returned to allow method chaining.
     *
     * @param callable(mixed,string):mixed $callback Callback receiving the attribute
     *                                               value and name.
     *
     * @return static The current model instance.
     */
    public function each(callable $callback): static
    {
        foreach ($this->attributes as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * Determine whether the model contains the specified attribute.
     *
     * Checks whether the attribute key exists in the model's internal attribute
     * storage. Attributes with `null` values are considered present.
     *
     * @param string $name Attribute name to check.
     *
     * @return bool Returns `true` if the attribute exists, otherwise `false`.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Determine whether the model has a primary-key identifier.
     *
     * @return bool `true` if the primary-key value is set, otherwise `false`.
     */
    public final function hasIdentifier(): bool
    {
        return isset($this->attributes[$this->primaryKey]);
    }

    /**
     * Set a model attribute value.
     *
     * Assigns the provided value to the model attributes if the attribute is
     * allowed by the model's attribute protection rules. Ignored or restricted
     * attributes are silently skipped.
     *
     * This method is intended for controlled attribute assignment where only
     * permitted fields should be modified.
     *
     * @param string $name Attribute name.
     * @param mixed $value Attribute value.
     *
     * @return self Returns the current model instance.
     */
    public function set(string $name, mixed $value): self
    {
        if (!$this->canHydrateAttribute($name)) {
            return $this;
        }

        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Clears all model attributes while preserving the primary key value.
     *
     * This method removes all loaded or assigned attributes from the model instance
     * and restores the current primary key attribute if it exists. It is useful when
     * resetting the model state while keeping the record identity intact.
     *
     * @return void
     */
    public final function clearAttributes(): void 
    {
        $identifier = $this->attributes[$this->primaryKey] ?? null;

        $this->attributes = [];
        $this->attributes[$this->primaryKey] = $identifier;
    }

    /**
     * Synchronize model attributes with the given data.
     *
     * Object properties are converted to an associative array before
     * synchronization. Only allowed attributes are updated; unknown or
     * ignored attributes are skipped.
     *
     * @param array<string,mixed>|object{string:mixed} $attributes Source data used to update model attributes.
     *
     * @return bool `true` if at least one attribute was synchronized, otherwise `false`.
     *
     * @throws InvalidArgumentException If the attributes are not associative or
     *                                  the primary key is missing for an
     *                                  unidentified model.
     */
    public final function sync(array|object $attributes): bool
    {
        if (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }

        if ($attributes === []) {
            return false;
        }

        $this->assertAttributes($attributes);

        $synced = 0;

        foreach ($attributes as $property => $value) {
            if (!$this->canHydrateAttribute($property)) {
                continue;
            }

            $this->attributes[$property] = $value;
            $synced++;
        }

        return $synced > 0;
    }

    /**
     * Convert the collection into an array.
     *
     * Returns the underlying collection items as an indexed array.
     *
     * @return array<int,mixed> Collection items.
     */
    public final function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the collection into an object.
     *
     * Returns the collection items wrapped in a standard object instance.
     *
     * @return object Object containing the collection items as properties.
     */
    public final function toObject(): object
    {
        return (object) $this->attributes;
    }

    /**
     * Returns model data for JSON serialization.
     *
     * @return array<string,mixed> The model attributes to encode as JSON.
     * @example - Example:
     * ```php
     * class User extends Model 
     * {
     *      public function jsonSerialize(): array
     *      {
     *          $attributes = parent::jsonSerialize();
     *          unset($attributes['password']);
     * 
     *          return $attributes;
     *      }
     * }
     * ```
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    /**
     * Determine whether the collection contains no items.
     *
     * @return bool Returns `true` when the collection is empty, otherwise `false`.
     */
    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    /**
     * Determine whether the collection is read-only.
     *
     * A read-only collection prevents modification of its underlying items.
     *
     * @return bool Returns `true` when the collection is read-only, otherwise `false`.
     */
    public final function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Determine whether the model uses a shared database connection.
     *
     * A shared connection may be reused by other model operations, while an
     * isolated connection is dedicated to this model instance.
     *
     * @return bool Returns `true` when using a shared connection, otherwise `false`.
     */
    public final function isSharedConnection(): bool
    {
        return $this->isSharedConnection;
    }

    /**
     * Determine whether a column can be hydrated as a model attribute.
     *
     * Checks the configured ignored column attributes and returns `false` when
     * the specified column is excluded from model attribute assignment.
     *
     * @param string $attribute Column name to check.
     *
     * @return bool `true` if the column can be hydrated as an attribute, otherwise `false`.
     */
    public function canHydrateAttribute(string $attribute): bool
    {
        if ($this->ignoredColumnAttributes === []) {
            return true;
        }

        static::$mapIgnoredAttributes ??= array_flip(
            $this->ignoredColumnAttributes
        );

        return !isset(static::$mapIgnoredAttributes[$attribute]);
    }

    /**
     * Determine whether columns are allowed for a specific operation.
     *
     * Validates the provided column names against the configured allow list for
     * the requested operation. Any columns that are not permitted are returned
     * through the `$unsupported` reference parameter.
     *
     * When no allowed columns are configured for the operation, all columns are
     * considered valid.
     *
     * Supported operation types:
     * - `insert` Columns allowed during record creation.
     * - `update` Columns allowed during record updates.
     * - `search` Columns allowed for filtering queries.
     * - `select` Columns allowed for result selection.
     * - `attribute` Columns allowed model data attributes.
     *
     * @param array<string,mixed>|array<int,string> $columns Columns to validate.
     *                                                        Associative arrays use
     *                                                        their keys as column names.
     * @param string $type Operation type:  `insert`, `update`, `search`, `select` or `attribute`.
     * @param array<int,string> $unsupported Receives columns that are not allowed.
     *
     * @return bool Returns `true` when all columns are allowed, otherwise `false`.
     */
    public function validateColumns(
        array $columns,
        string $type,
        array &$unsupported = []
    ): bool
    {
        $unsupported = [];
        $allows = match($type) {
            self::TYPE_UPDATE => $this->updatable,
            self::TYPE_INSERT => $this->insertable,
            self::TYPE_SEARCH => $this->searchable,
            self::TYPE_SELECT => $this->columns,
            self::TYPE_ATTRIBUTE   => $this->ignoredColumnAttributes,
            default => []
        };

        if ($allows === []) {
            return true;
        }

        $columns = array_is_list($columns) 
            ? $columns 
            : array_keys($columns);

        $unsupported = array_diff($columns, $allows);

        return $unsupported === [];
    }

    /**
     * Set the database result hydration type.
     *
     * Defines how query results should be returned, such as associative arrays,
     * standard objects, or instances of the current model class.
     *
     * Use `self` to return results as instances of the current model class.
     *
     * @param string $returns Result type:
     *                        - `array` Return results as associative arrays.
     *                        - `object` Return results as standard objects.
     *                        - `self` Return results as instances of this model class.
     *                        - A valid class name Return results as instances of that class.
     *
     * @return static Returns the current model instance.
     * 
     * > **Recommendation:**
     * > Use `self::RETURN_SELF` to always return instance of model class.
     */
    public function setReturn(string $returns): static
    {
        if ($returns === self::RETURN_SELF) {
            $returns = static::class;
        }

        $this->resultType = $returns;

        if ($this->db instanceof QueryModel) {
            $this->db->setDefinition('resultType', $returns);
        }

        return $this;
    }

    /**
     * Retrieve the database connection assigned to the model.
     *
     * @return DatabaseInterface|Connection|null The assigned connection instance,
     *                                          or null when no connection is set.
     */
    public final function getConnection(): DatabaseInterface|Connection|null
    {
        return $this->dbConnection;
    }

    /**
     * Retrieve last inserted id from database after insert method is called.
     * 
     * @return mixed Return last inserted id from database.
     */
    public function getLastInsertedId(): mixed
    {
        if (!$this->db instanceof QueryModel) {
            return null;
        }

        return $this->db->getLastInsertedId();
    }

    /**
     * Sets the current model record identifier.
     *
     * Assigns the identifier value to the model's primary key attribute. The
     * assigned value is used as the default identifier for record operations such
     * as {@see self::find()}, {@see self::update()}, and {@see self::delete()}.
     *
     * @param string|int $identifier Record primary key value.
     *
     * @return static Returns the current model instance.
     */
    public final function setIdentifier(string|int $identifier): static 
    {
        $this->attributes[$this->primaryKey] = $identifier;
        return $this;
    }

    /**
     * Retrieves the current model record identifier.
     *
     * Returns the value assigned to the model's primary key attribute. If no
     * identifier has been assigned, `null` is returned.
     *
     * @return string|int|null Returns the current record identifier.
     */
    public final function getIdentifier(): string|int|null
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    /**
     * Retrieve the folder name where model database cache will be stored.
     * 
     * @return string Return the cache folder name or empty string if cache is disabled.
     */
    public final function getCachePersistentId(): ?string 
    {
        if(!$this->cacheable){
            return null;
        }

        return $this->persistentId ??= Luminova::getClassBasename(
            static::class
        );
    }

    /**
     * Initializes and returns the model validation instance.
     *
     * Creates a new validation instance when one has not already been provided,
     * then applies the model's configured validation rules and error messages.
     *
     * The initialized instance is stored in `$this->input` and can be reused
     * throughout the model lifecycle.
     *
     * @return Validation Returns the validation instance associated with the model.
     *
     * @see Validation
     * @see self::$rules
     * @see self::$messages
     */
    protected function validation(): Validation
    {
        $this->input ??= new Validation();

        if($this->rules !== []){
            $this->input->rules = $this->rules;
        }

        if($this->messages !== []){
            $this->input->messages = $this->messages;
        }

        return $this->input;
    }

    /**
     * Retrieve model definition metadata.
     *
     * Returns the configuration details used by the model, including table
     * information, attribute rules, result hydration settings, and cache options.
     *
     * @return object{
     *     table:string,
     *     class:class-string,
     *     primaryKey:string,
     *     columns:array<int,string>,
     *     insertable:array<int,string>,
     *     updatable:array<int,string>,
     *     searchable:array<int,string>,
     *     resultType:string,
     *     cacheable:bool,
     *     expiry:\DateTimeInterface|int|null
     * }
     * 
     * @see QueryModel
     * 
     * @internal
     * @codeCoverageIgnore
     */
    public final function getDefinition(): object
    {
        return (object) [
            'table'      => $this->table,
            'class'      => static::class,
            'primaryKey' => $this->primaryKey,
            'columns'    => $this->columns,
            'insertable' => $this->insertable,
            'updatable'  => $this->updatable,
            'searchable' => $this->searchable,
            'resultType' => $this->resultType,
            'cacheable'  => $this->cacheable,
            'expiry'     => $this->expiry
        ];
    }

    /**
     * Serialize model attributes into a PHP serialized string.
     *
     * @return string Serialized model attributes.
     */
    public function serialize(): string
    {
        return serialize($this->attributes);
    }

    /**
     * Restore model attributes from a PHP serialized string.
     *
     * @param string $data Serialized model attributes.
     *
     * @return void
     * @throws UnexpectedValueException If the serialized data does not contain
     *                                  an array of model attributes.
     */
    public function unserialize(string $data): void
    {
        $attributes = unserialize($data);

        if (!is_array($attributes)) {
            throw new UnexpectedValueException(
                'Cannot unserialize model attributes. Expected an array.'
            );
        }

        $this->attributes = $attributes;
    }

    /**
     * Retrieves a model attribute value.
     *
     * Allows dynamic access to attributes stored in the model attribute collection.
     *
     * @param string $name Attribute name to retrieve.
     *
     * @return mixed Returns the attribute value or `null` if it does not exist.
     */
    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Sets a model attribute value.
     *
     * Allows dynamic assignment of attributes stored in the model attribute
     * collection.
     *
     * @param string $name Attribute name to assign.
     * @param mixed $value Attribute value.
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Determines whether a model attribute is set.
     *
     * Supports `isset()` checks for dynamically stored model attributes.
     *
     * @param string $name Attribute name to check.
     *
     * @return bool Returns `true` if the attribute exists and is not `null`,
     *              otherwise `false`.
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Removes a model attribute.
     *
     * Deletes the specified attribute from the model attribute collection.
     *
     * @param string $name Attribute name to remove.
     *
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * Prepare model attributes for native PHP serialization.
     *
     * @return array<string,mixed> Serialized model attributes.
     */
    public function __serialize(): array
    {
        return $this->attributes;
    }

    /**
     * Restore model attributes from native PHP serialization data.
     *
     * @param array<string,mixed> $data Serialized model attributes.
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->attributes = $data;
    }

    /**
     * Assert that columns are allowed for the specified operation.
     *
     * Validates the provided column names against the configured allow list
     * and throws an exception when unsupported columns are detected.
     *
     * @param array<string,mixed> $columns Column names and values to validate.
     * @param string $type Operation type used to determine the allowed columns:
     *                     `insert`, `update`, `select`, or `search`.
     *
     * @return void
     *
     * @throws RuntimeException If one or more columns are not allowed.
     */
    public final function assertColumnsAllowed(array $columns, string $type): void
    {
        $unsupported = [];

        if ($this->validateColumns($columns, $type, $unsupported)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'The %s %s contains unsupported columns: [%s].',
            $type,
            ($type === self::TYPE_INSERT) ? 'values' : 'data',
            implode(', ', $unsupported)
        ));
    }

    /**
     * Validate model attributes before synchronization.
     *
     * Ensures the attributes are associative and, when the model has no
     * identifier, contain the configured primary key.
     *
     * @param array<string,mixed> $attributes Model attributes to validate.
     *
     * @return void
     * @throws InvalidArgumentException If the attributes are a list or the
     *                                  primary key is missing.
     */
    protected final function assertAttributes(array $attributes): void
    {
        if (array_is_list($attributes)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot parse %s attributes. Expected an associative array of model attributes.',
                static::class
            ));
        }

        if (
            !$this->hasIdentifier() 
            && !array_key_exists($this->primaryKey, $attributes)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Cannot parse %s attributes. Missing primary key "%s".',
                static::class,
                $this->primaryKey
            ));
        }
    }

    /**
    * Assert that the model database query handler has been initialized.
    *
    * @return void
    *
    * @throws LogicException If the query handler has not been initialized.
    */
    protected final function assertDatabaseModel(): void
    {
        if (!$this->db instanceof QueryModel) {
            throw new LogicException(sprintf(
                'Cannot access database for %s. The query model has not been initialized.',
                static::class
            ));
        }
    }
}