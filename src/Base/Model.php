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

use \Throwable;
use \Serializable;
use \JsonSerializable;
use \DateTimeInterface;
use Luminova\Database\Builder;
use Luminova\Database\Connection;
use Luminova\Security\Validation;
use Luminova\Database\DatabaseModel;
use Luminova\Exceptions\RuntimeException;
use Luminova\Interface\DatabaseInterface;
use function Luminova\Funcs\get_class_name;
use Luminova\Interface\LazyObjectInterface;
use Luminova\Components\Collections\Collection;
use Luminova\Exceptions\InvalidArgumentException;

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
 * @mixin DatabaseModel
 * @template T of Model|object
 *
 * Database operations are delegated to the underlying database model handler.
 *
 * @method ?Model create(array<string,mixed>|array<int,array<string,mixed>> $attributes)
 *         Creates a single record in the model's database table {@see DatabaseModel::create()}.
 * 
 * @method ?Model update(string|int|null $identifier, array<string,mixed> $attributes)
 *         Updates the current record in the database {@see DatabaseModel::update()}.
 * 
 * @method Model|mixed find(string|int|null $identifier = null, ?array $columns = null, ?string $cacheKey = null)
 *         Finds a single record by its primary key {@see DatabaseModel::find()}.
 *
 * @method bool delete(string|int|null $identifier = null)
 *         Deletes a single record from current model's table by primary key {@see DatabaseModel::delete()}.
 *
 * @method bool exists(array|string|int|null $identifier = null, ?string $cacheKey = null)
 *         Determines whether one or more records exist in current model's database table {@see DatabaseModel::exists()}.
 *
 * @method int total(?string $cacheKey = null)
 *         Gets the total number of unique records in the model's database table {@see DatabaseModel::total()}.
 *
 * 
 * 
 * @method bool insert(array<string,mixed>|array<int,array<string,mixed>> $values)
 *         Inserts one or more record into the model's database table {@see DatabaseModel::insert()}.
 * 
 * @method mixed select(array|string|int|null $identifier = null, array $columns = [], int $limit = 20, int $offset = 0, ?string $cacheKey = null)
 *         Selects records from the model's database table {@see DatabaseModel::select()}.
 * 
 * @method Collection<T> all(?array $columns = null, ?int $limit = null, int $offset = 0, ?string $cacheKey = null)
 *         Selects all records from current model's database table {@see DatabaseModel::all()}.
 *
 * @method bool deleteMany(array $identifiers, ?int $limit = null)
 *         Deletes multiple records from current model's database table by identifiers {@see DatabaseModel::deleteMany()}.
 * 
 * @method bool deleteAll()
 *         Deletes all records from current model's database table {@see DatabaseModel::deleteAll()}.
 *
 * @method int count(array|string|int|null $identifier = null, array|string $column = '*', bool $distinct = false, ?string $cacheKey = null)
 *         Counts records in the model's database table {@see DatabaseModel::count()}.
 *
 * @method Collection<T>|Model|mixed search(string $keyword, ?array $columns = null, string $pattern = Builder::SEARCH_CONTAINS, bool $splitKeyword = false, bool $caseSensitive = false, int $limit = 100, int $offset = 0, ?string $collation = null, ?string $cacheKey = null)
 *         Searches records using from current model's database table using configured searchable columns {@see DatabaseModel::search()}.
 * 
 * 
 * @method Builder table(?string $alias = null)
 *         Creates a new query builder for the model's database table {@see DatabaseModel::table()}.
 * 
 * @method Builder query(string $sql)
 *         Creates a new query builder from a raw SQL statement {@see DatabaseModel::query()}.
 * 
 * @method Builder where(string $column, string $operation, mixed $value)
 *         Creates a new query builder with an initial WHERE condition {@see DatabaseModel::where()}.
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
     * Database model object.
     *
     * @var DatabaseModel|null $db
     */
    private ?DatabaseModel $db = null;

    /**
     * Allows direct PHP include/require statements in this model.
     *
     * When enabled, the debugger skips include/require enforcement.
     *
     * @var bool $allowDirectIncludes
     * @see #[AllowDirectIncludes] Class-level attribute.
     */
    protected bool $allowDirectIncludes = false;

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
     * @see self::select()
     * @see self::find()
     * @see self::all()
     * @see DatabaseModel::select()
     */
    protected array $columns = ['*'];

    /**
     * Columns allowed during update operations.
     *
     * Only the listed columns can be modified when updating existing
     * records. Leave empty to allow all updatable columns.
     *
     * @var array<int,string> $updatable
     * @see self::update()
     * @see DatabaseModel::update()
     */
    protected array $updatable = [];

    /**
     * Validation rules.
     *
     * @var array<string,string> $rules
     * @see self::validation()
     */
    protected array $rules = [];

    /**
     * Validation error messages.
     *
     * @var array<string,array> $messages
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
     * @see self::connection()
     */
    protected DatabaseInterface|Connection|null $dbConnection = null;

    /**
     * Indicates whether the assigned database connection is shared.
     *
     * A shared connection can be reused across model operations, while a non-shared
     * connection is treated as dedicated to the current model instance.
     *
     * @var bool $isSharedConnection
     * @see self::connection()
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
     * @var array<int,string> $ignoreAttributes
     * @see self::sync()
     */
    protected array $ignoreAttributes = [];

    /**
     * Mapped model attribute exclusions.
     *
     * @var array|null $mapIgnoredAttributes
     */
    private static ?array $mapIgnoredAttributes = null;

    /**
     * Initializes the model instance.
     *
     * An optional identifier or attribute set can be provided when creating the
     * model. When a scalar identifier is provided, it is assigned as the current
     * record primary key and becomes the default target for record operations such
     * as {@see self::find()}, {@see self::update()}, and {@see self::delete()} when
     * no identifier is explicitly specified.
     *
     * When an array or object is provided, the values are synchronized into the
     * model attributes using the model attribute assignment rules.
     *
     * The model result type is initialized based on the configured `$resultType`
     * value. When set to `self`, the current model class is used as the return type.
     *
     * @param string|int|array<string,mixed>|object|null $attributes Optional record
     *        identifier or initial model attributes.
     *
     * @see Builder
     *
     * @example - Create a model instance with an identifier:
     * ```php
     * Sets the user current identifier
     * $user = new User(100);
     *
     * // Override the current identifier.
     * $user->find(200);
     * ```
     *
     * @example - Create a model instance with attributes:
     * ```php
     * $user = new User([
     *     'name' => 'John',
     *     'email' => 'john@example.com'
     * ]);
     * ```
     */
    public function __construct(array|object|string|int|null $attributes = null)
    {
        $this->onCreate();

        if (!PRODUCTION && !$this->allowDirectIncludes) {
            \Luminova\Debugger\Tracer::assertNoIncludes($this);
        }

        if ($this->resultType === self::RETURN_SELF) {
            $this->resultType = static::class;
        }

        if ($attributes !== null) {
            if (is_array($attributes) || is_object($attributes)) {
                $this->sync($attributes);
            }else {
                $this->attributes[$this->primaryKey] = $attributes;
            }
        }

        $this->db = new DatabaseModel($this);
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
        return (new static())->db->{$method}(...$arguments);
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
    public function setConnection(DatabaseInterface|Connection $conn, bool $shared = true): self
    {
        $this->dbConnection = $conn;
        $this->isSharedConnection = $shared;

        return $this;
    }

    /**
     * Create a model instance from the provided attributes.
     *
     * The provided attributes are converted into model attributes without requiring
     * the model primary key. This method is suitable for creating new model
     * instances or hydrating partial data.
     *
     * @param array|object $attributes Model attributes.
     *
     * @return static Model instance containing the provided attributes.
     *
     * @see self::sync()
     */
    public static function make(array|object $attributes): static
    {
        return new static($attributes);
    }

    /**
     * Create a model instance from an existing database record.
     *
     * The provided attributes are converted into model attributes and must contain
     * the model primary key. This method is intended for hydrating complete
     * database records where the record identity is required.
     *
     * @param array|object $attributes Existing model attributes.
     *
     * @return static Hydrated model instance.
     * @throws InvalidArgumentException If attributes are empty or the primary key is missing.
     *
     * @see self::sync()
     */
    public static function makeFrom(array|object $attributes): static
    {
        if (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }

        if ($attributes === []) {
            throw new InvalidArgumentException(
                'Cannot create model from empty attributes.'
            );
        }

        $model = new static();

        $primaryKey = $model->primaryKey;

        if (!array_key_exists($primaryKey, $attributes)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot create %s. Missing primary key "%s".',
                static::class,
                $primaryKey
            ));
        }

        $model->sync($attributes);

        return $model;
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
        if (!$this->isAllowedAttribute($name)) {
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
    public function clearAttributes(): void 
    {
        $identifier = $this->attributes[$this->primaryKey] ?? null;

        $this->attributes = [];
        $this->attributes[$this->primaryKey] = $identifier;
    }

    /**
     * Synchronize model properties with result data.
     *
     * Updates the current model instance using values from an array or object.
     * Ignored properties and unknown properties are skipped.
     *
     * When no data is provided, the last query result is used.
     *
     * @param array<string,mixed>|object<string,mixed> $attributes Source data used to update model properties.
     *
     * @return bool Returns the current model instance.
     */
    public function sync(array|object $attributes): bool
    {
        if(is_object($attributes)){
            $attributes = get_object_vars($attributes);
        }

        if($attributes === []){
            return false;
        }

        $synced = 0;

        foreach ($attributes as $property => $value) {
            if (!$this->isAllowedAttribute($property)) {
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
    public function toArray(): array
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
    public function toObject(): object
    {
        return (object) $this->attributes;
    }

    /**
     * Returns model data for JSON serialization.
     *
     * @return array<string,mixed> The model attributes to encode as JSON.
     * @example - Example:
     * ```php
     * class User extends Model {
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
    public function isReadOnly(): bool
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
    public function isSharedConnection(): bool
    {
        return $this->isSharedConnection;
    }

    /**
     * Determine whether an attribute is allowed for assignment or hydration.
     *
     * Checks the configured ignored attributes list and returns `false` when the
     * specified property is excluded. When no ignored attributes are configured,
     * all attributes are considered allowed.
     *
     * @param string $property Attribute name to check.
     *
     * @return bool Returns `true` if the attribute is allowed, otherwise `false`.
     */
    public function isAllowedAttribute(string $property): bool
    {
        if ($this->ignoreAttributes === []) {
            return true;
        }

        static::$mapIgnoredAttributes ??= array_flip($this->ignoreAttributes);

        return !isset(static::$mapIgnoredAttributes[$property]);
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
    public function isAllowedColumns(
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
            self::TYPE_ATTRIBUTE   => $this->ignoreAttributes,
            default => []
        };

        if ($allows === []) {
            return true;
        }

        $columns = array_is_list($columns) 
            ? $columns 
            : array_keys($columns);

        $unsupported = array_diff($columns, $allows);

        if($unsupported === []){
            return true;
        }

        return false;
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
        $this->db->setDefinition('resultType', $returns);

        return $this;
    }

    /**
     * Retrieve the database connection assigned to the model.
     *
     * @return DatabaseInterface|Connection|null The assigned connection instance,
     *                                          or null when no connection is set.
     */
    public function getConnection(): DatabaseInterface|Connection|null
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

        return $this->persistentId ??= get_class_name(
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
     * @see self::$rules
     * @see self::$messages
     * @deprecated 
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


    public function __serialize(): array
    {
        return $this->attributes;
    }

    public function __unserialize(array $data): void
    {
        $this->attributes = $data;
    }

    public function serialize(): string
    {
        return serialize($this->attributes);
    }

    public function unserialize(string $data): void
    {
        $this->attributes = unserialize($data);
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
     * Ensures that columns are allowed for the specified operation.
     *
     * Validates the provided column names against the configured allow list.
     * Throws an exception when unsupported columns are detected.
     *
     * @param array<string,mixed> $columns Column names and values to validate.
     * @param string $type Operation type used to determine the allowed columns:
     *                     `insert`, `update`, `select`, or `search`.
     *
     * @return void
     * @throws RuntimeException If the columns contain unsupported keys.
     */
    public function assertAllowedColumns(array $columns, string $type): void 
    {
        $unsupported = [];

        if ($this->isAllowedColumns($columns, $type, $unsupported)) {
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
}