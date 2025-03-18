<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database;

use \Luminova\Database\TableTrait;
use \Luminova\Exceptions\DatabaseException;

final class Table 
{
    /**
     * Default field value none.
     * 
     * @var string DEFAULT_NONE
     */
    public const DEFAULT_NONE = '__NONE__';

    /**
     * Default field value timestamp.
     * 
     * @var string DEFAULT_TIMESTAMP
     */
    public const DEFAULT_TIMESTAMP = 'CURRENT_TIMESTAMP';

    /**
     * Default field value null.
     * 
     * @var string DEFAULT_NULL
     */
    public const DEFAULT_NULL = 'NULL';

    /**
     * Primary key.
     * 
     * @var string INDEX_PRIMARY_KEY
     */
    public const INDEX_PRIMARY_KEY = 'PRIMARY KEY';

    /**
     * Foreign key.
     * 
     * @var string INDEX_FOREIGN_KEY
     */
    public const INDEX_FOREIGN_KEY = 'FOREIGN KEY';
   
    /**
     * Unique table index.
     * 
     * @var string INDEX_UNIQUE
     */
    public const INDEX_UNIQUE = 'UNIQUE';

    /**
     * Indexed table index.
     * 
     * @var string INDEX_DEFAULT
     */
    public const INDEX_DEFAULT = 'INDEX';

    /**
     * Full text table index.
     * 
     * @var string INDEX_FULLTEXT
     */
    public const INDEX_FULLTEXT = 'FULLTEXT';

    /**
     * Spatial table index.
     * 
     * @var string INDEX_SPATIAL
     */
    public const INDEX_SPATIAL = 'SPATIAL';

    /**
     * Generate schema for mysql database.
     * 
     * @var string MYSQL
     */
    public const MYSQL = 'mysql';

    /**
     * Generate schema for sql server database.
     * 
     * @var string SQL_SERVER
     */
    public const SQL_SERVER = 'sql-server';

    /**
     * Generate schema for ms access database.
     * 
     * @var string MS_ACCESS
     */
    public const MS_ACCESS = 'ms-access';

    /**
     * Generate schema for oracle database.
     * 
     * @var string ORACLE
     */
    public const ORACLE = 'oracle';

    /**
     * Generate schema for Postgres sql database.
     * 
     * @var string POSTGRES
     */
    public const POSTGRES = 'postgres';

    /**
     * Generate schema for sqlite sql database.
     * 
     * @var string SQLITE
     */
    public const SQLITE = 'sqlite';

    /**
     * Replaces format to column name.
     * 
     * @var string COLUMN_NAME
     */
    public const COLUMN_NAME = '{__REPLACE_COLUMN_NAME__}';
    
    /**
     * @var array<string,mixed> $columns
     */
    private array $columns = [];

    /**
     * @var string $tableName
     */
    private string $tableName = '';

    /**
     * Specify the database type to generate schema for.
     * 
     * @var string $database
     */
    public string $database = 'mysql';

    /**
     * Initialize database sessions that will be created during migration.
     * The array key as the session identifier/name while the value is the session value to apply.
     * 
     * @var array<string,mixed> $session
     */
    public array $session = [];

    /**
     * Initialize database globals that will be created during migration.
     * The array key as the global identifier/name while the value is the global value to apply.
     * 
     * @var array<string,mixed> $global
     */
    public array $global = [];

    /**
     * Set the table collation type.
     * 
     * @var string|null $collation
     */
    public ?string $collation = null;

    /**
     * Set the database default charset.
     * 
     * @var string|null $charset
     */
    public ?string $charset = null;

    /**
     * Set the table command.
     * 
     * @var string|null $comment
     */
    public ?string $comment = null;

    /**
     * Specify weather to use create if not exists or just create table.
     * 
     * @var bool $ifNotExists
     */
    public bool $ifNotExists = false;

    /**
     * Specify if table query should be print in new line.
     * 
     * @var bool $prettify
     */
    public bool $prettify = true;

    /**
     * Set storage engines to handle SQL operations.
     * 
     * @var string|null $engine
     */
    public ?string $engine = null;

    /**
     * Reference to table trait.
     */
    use TableTrait;

    /**
     * Constructor to initialize the table with a name.
     *
     * @param string $tableName The name of the table.
     * @param string $database The database type (default: `MYSQL`).
     * @param bool $ifNotExists Weather to add `if not exists` (default: false).
     * @param string|null $collation Optional table collation type (default: null).
     * @param string|null $comment Optional table comment (default: null).
     * 
     * @throws DatabaseException If the table name is an empty string.
     */
    public function __construct(
        string $tableName, 
        string $database = self::MYSQL, 
        bool $ifNotExists = false, 
        ?string $collation = null, 
        ?string $comment = null
    )
    {
        if ($tableName === '') {
            throw new DatabaseException("Table name cannot be empty string.");
        }
        $this->tableName = $tableName;
        $this->database = $database;
        $this->collation = $collation;
        $this->comment = $comment;
        $this->ifNotExists = $ifNotExists;
    }

    /**
     * Magic method to dynamically add columns based on method calls.
     *
     * @param string $method The method name (column type).
     * @param array $arguments The method arguments (column name, length, attributes).
     * 
     * @return self Return table class instance.
     * @throws DatabaseException If the column type is not supported.
     * @internal
     */
    public function __call(string $method, array $arguments): self
    {
        $method = strtoupper($method);
    
        if ($method === 'STRING') {
            return $this->addColumn($arguments[1] ?? 'VARCHAR', $arguments[0], $arguments[2] ?? 255);
        }

        $method = ($method === 'INTEGER') ? 'INT' : $method;

        if (isset(self::$columnTypes[$method])) {
            return $this->addColumn($method, $arguments[0], $arguments[1] ?? null, $arguments[2] ?? null);
        }

        throw new DatabaseException("Unsupported table column type: $method");
    }

    /**
     * Creates a primary key column `id` set to auto increment.
     * This method is an alias for `BIGINT` with `UNSIGNED` and `AUTO INCREMENT` attributes.
     * 
     * @param string $name Optional column name for the id (default: 'id').
     * 
     * @return self Returns the table class instance.
     */
    public function id(string $name = 'id'): self
    {
        if($this->database === Table::SQLITE){
            return $this->addColumn('INTEGER', $name)
                ->attribute(self::INDEX_PRIMARY_KEY)
                ->attribute('AUTOINCREMENT'); 
        }

        return $this->addColumn('BIGINT', $name)
            ->autoIncrement()
            ->unsigned()
            ->primary();
    }

    /**
     * Creates a new column for `ULID` using `CHAR(25)`.
     * This method defines a column for storing ULIDs (Universally Unique Lexicographically Sortable Identifiers).
     * 
     * @param string $name The name of the ULID column (default: 'ulid').
     * 
     * @return self Returns the table class instance.
     */
    public function ulid(string $name = 'ulid'): self
    {
        return $this->addColumn('CHAR', $name, 25);
    }

    /**
     * Creates a new column for `UUID` using `CHAR(36)`.
     * This method defines a column for storing UUIDs (Universally Unique Identifiers).
     * 
     * @param string $name The name of the UUID column (default: 'uuid').
     * 
     * @return self Returns the table class instance.
     */
    public function uuid(string $name = 'uuid'): self
    {
        return $this->addColumn('CHAR', $name, 36);
    }

    /**
     * Creates columns for timestamps with the names `created_on` and `updated_on`.
     * Both columns are set to be `NULLABLE` with a default value of `NULL`.
     * 
     * @param int|null $precision Optional precision for the timestamp columns.
     * 
     * @return self Returns the table class instance.
     */
    public function timestamps(?int $precision = null): self
    {
        return $this->addColumn('TIMESTAMP', 'created_on', $precision)
            ->nullable()
            ->default(self::DEFAULT_TIMESTAMP)
            ->addColumn('TIMESTAMP', 'updated_on', $precision)
            ->nullable()
            ->default(self::DEFAULT_TIMESTAMP);
    }

    /**
     * Updates the column data type.
     * Use this method to modify or specify the data type of the column.
     * 
     * @param string $type The new data type for the column.
     * 
     * @return self Returns the table class instance.
     */
    public function type(string $type): self 
    {
        return $this->add('type', $type, false);
    }

    /**
     * Adds additional attributes to the current column.
     * This method allows for specifying additional column attributes such as UNSIGNED, AUTO_INCREMENT, etc.
     * 
     * 
     * @param string $attribute The attribute to add to the column definition.
     * 
     * @return self Returns the table class instance for method chaining.
     */
    public function attribute(string $attribute): self
    {
        return $this->add('attributes', $attribute);
    }

    /**
     * Define additional column entry that will be added to the table definition.
     *
     * @param string $query The entry query string to add.
     * 
     * @return self Returns the table class instance.
     * @example Adding a new column entry.
     * 
     * `$table->entry("name VARCHAR(50) NOT NULL DEFAULT 'default value'");`
     */
    public function entry(string $query): self
    {
        return $this->add('entries', $query);
    }

    /**
     * Define additional execution constraint to execute after table creation.
     * 
     * @param string $query The execution query string to add.
     * 
     * @return self Returns the table class instance.
     */
    public function exec(string $query): self
    {
        return $this->add('executions', $query);
    }

    /**
     * Define a CHECK constraint for a column.
     *
     * @param string $column The name of the column to apply the CHECK constraint on.
     * @param string $operator The comparison operator for the CHECK constraint (e.g., '=', '>', '<=').
     * @param string|float|int|null $value The value to compare against in the CHECK constraint.
     * 
     * @return self Returns the table class instance.
     */
    public function check(string $column, string $operator, string|float|int|null $value): self
    {
        return $this->constraints("CHECK ({$column} {$operator} {$value})");
    }

    /**
     * Sets the column to auto increment.
     *
     * This method adds the `AUTO INCREMENT` attribute to an integer column, 
     * suitable for primary key columns to automatically generate unique values.
     *
     * @param int $start The starting value for the auto increment.
     * @param int $increment The increment value for each auto increment step.
     * 
     * @return self Returns the table class instance.
     */
    public function autoIncrement(int $start = 1, int $increment = 1): self
    {
        return $this->add('increment', [
            'start' => $start,
            'increment' => $increment
        ], false);
    }

    /**
     * Sets the column to invisible in `MYSQL`.
     * Invisible columns are not shown in `SELECT *` queries but can still be accessed when explicitly specified in the query.
     * 
     * @return self Returns the table class instance.
     * > The `INVISIBLE` attribute can only be used in MySQL 8.0.23 and later.
     */
    public function invisible(): self
    {
        return $this->add('visibility', 'INVISIBLE', false);
    }

    /**
     * Sets the column to visible in `MYSQL`, this is useful for alter when column was previously set to `INVISIBLE`.
     * 
     * @return self Returns the table class instance.
     * > The `VISIBLE` attribute can only be used in MySQL 8.0.23 and later.
     */
    public function visible(): self
    {
        return $this->add('visibility', 'VISIBLE', false);
    }

    /**
     * Sets the column to nullable.
     * 
     * This method sets the column to allow `NUL`L values in the database.
     * 
     * @param bool $nullable Weather to allow null values in the column or not (default: `TRUE`)
     *
     * @return self Returns the table class instance.
     * > If this method isn't called, then `NULL` or `NOT NULL` attributes will not be added.
     */
    public function nullable(bool $nullable = true): self
    {
        return $this->add('nullable', $nullable ? 'NULL' : 'NOT NULL', false);
    }

    /**
     * Sets the default value for the column.
     * 
     * This method assigns a default value to the column, which can be a string, integer, float, boolean, array, or null.
     * It is often used to provide a fallback value when no explicit value is provided during record insertion.
     *
     * @param mixed $value The default value for the column (default: NULL).
     * 
     * @return self Returns the table class instance.
     */
    public function default(mixed $value = null): self
    {
        if($value === self::DEFAULT_NONE){
            return $this;
        }

        if ($value === null) {
            $value = 'NULL';
        } elseif (is_array($value) || is_object($value)) {
            $value = "'" . json_encode($value) . "'";
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        } elseif (is_string($value) && !is_numeric($value) && !str_ends_with($value, ')')) {
            $check = strtoupper($value);

            if ($check === self::DEFAULT_NULL) {
                $value = 'NULL';
            } elseif ($check === self::DEFAULT_TIMESTAMP) {
                $value = 'CURRENT_TIMESTAMP';
            } else {
                $value = "'" . addslashes($value) . "'";
            }
        }

        return $this->add('default', $value, false);
    }

    /**
     * Sets the precision and optional scale length for a column.
     *
     * @param int $precision The precision length of the column.
     * @param int|null $scale Optional scale length of decimal, float, or double column types (default: NULL).
     * 
     * @return self Returns the table class instance.
     */
    public function length(int $precision, ?int $scale = null): self
    {
        $this->assertColumn();
        $column = array_key_last($this->columns);

        $this->assertLength($this->columns[$column]['type']??'', $precision);
        $this->add('length', $precision, false);

        if($scale !== null){
            $this->add('scale', $scale, false);
        }

        return $this;
    }

    /**
     * Sets the collation for column.
     *
     * @param string $collate The column collation to use (e.g. utf8_unicode_ci).
     * 
     * @return self Return table class instance.
     */
    public function collation(string $collate): self
    {
        return $this->add('collation', $collate, false);
    }

    /**
     * Sets the charset for column.
     *
     * @param string $charset The column charset to use (e.g. utf8).
     * 
     * @return self Return table class instance.
     */
    public function charset(string $charset): self
    {
        return $this->add('charset', $charset, false);
    }

    /**
     * Define an index constraint on a column `Table::INDEX_*`.
     * This method allows you to specify various types of index constraints on a table column,
     * such as default, unique, full-text, spatial, or primary key.
     *
     * @param string $index The type of index constraint to apply (default: 'INDEX').
     *                      Supported values: 'INDEX', 'UNIQUE', 'FULLTEXT', 'SPATIAL', 'PRIMARY KEY'.
     * 
     * @return self Returns the table class instance.
     * @throws DatabaseException If an unsupported index type is provided.
     */
    public function index(string $index = self::INDEX_DEFAULT): self
    {
        $index = strtoupper($index);

        if (in_array($index, static::$supportedIndexes)) {
            $indexType = ($index === self::INDEX_DEFAULT) ? '' : "{$index} ";

            if ($index === self::INDEX_PRIMARY_KEY) {
                return $this->add('index', "{$indexType}(" . self::COLUMN_NAME . ")", false);
            }

            return $this->add('index', "{$indexType}INDEX idx_" . self::COLUMN_NAME . " (" . self::COLUMN_NAME . ")", false);
        }

        throw new DatabaseException(sprintf(
            'Unsupported index type: %s. Supported types: [%s]',
            $index,
            implode(', ', static::$supportedIndexes)
        ));
    }

    /**
     * Sets a column index as `INDEX` (e.g., `column_name INDEX`).
     * This method adds a regular index to the column, optimizing data retrieval and query performance.
     * 
     * @param string|null $columns The column names to apply the index on, separated by commas (e.g., `column1,column2`) (optional).
     * 
     * @return self Returns the table class instance.
     */
    public function defaultIndex(?string $columns = null): self
    {
        return $this->inlineIndex(self::INDEX_DEFAULT, $columns);
    }

    /**
     * Sets a column index as full-text (e.g., `column_name FULLTEXT INDEX`).
     * This method specifies that the column is optimized for full-text searches,
     * allowing efficient keyword-based searching of text within the column.
     * 
     * @param string|null $columns The column names to apply the index on, separated by commas (e.g., `column1,column2`) (optional).
     * 
     * @return self Returns the table class instance.
     */
    public function fullText(?string $columns = null): self
    {
        return $this->inlineIndex(self::INDEX_FULLTEXT, $columns);
    }

    /**
     * Sets a column index as `SPATIAL` (e.g., `column_name SPATIAL INDEX`).
     * This method specifies that the column is used for spatial data types,
     * enabling efficient querying of spatial data like points, lines, and polygons.
     * 
     * @param string|null $columns The column names to apply the index on, separated by commas (e.g., `column1,column2`) (optional).
     * 
     * @return self Returns the table class instance.
     */
    public function spatial(?string $columns = null): self
    {
        return $this->inlineIndex(self::INDEX_SPATIAL, $columns);
    }

    /** 
     * Sets a column as a `UNIQUE` key inline with the column definition (e.g., `column_name UNIQUE INDEX`).
     * This method specifies that the column must contain unique values across all rows in the table.
     * 
     * @param string|null $columns The column names to apply the index on, separated by commas (e.g., `column1,column2`) (optional).
     * 
     * @return self Returns the table class instance.
     */
    public function unique(?string $columns = null): self 
    {
        return $this->inlineIndex(self::INDEX_UNIQUE, $columns);
    }

    /**
     * Sets the column as `PRIMARY KEY`.
     * This method designates the column as the primary key of the table,
     * ensuring each row is uniquely identified by its values in this column.
     * 
     * @param bool $constraint Whether to use a primary key constraint (default: false).
     * 
     * @return self Returns the table class instance.
     * 
     * @throws DatabaseException If attempting to add a primary key constraint while multiple columns are already designated as primary key.
     */
    public function primary(bool $constraint = false): self
    {
        if ($constraint) {
            $primaries = $this->getTableOptions('primary');

            if ($primaries !== null && $primaries !== [] && in_array($this->getColumnName(), $primaries)) {
                throw new DatabaseException('Cannot add primary key constraint when multiple columns are designated as primary key.');
            }

            return $this->add('pkConstraint', self::COLUMN_NAME, false);
        }

        return $this->add('primary', self::COLUMN_NAME, false);
    }

    /**
     * Define a foreign key relationship.
     *
     * @param string $table The name of the referenced table.
     * @param string $column The column in the referenced table.
     * @param bool $constraint Whether to include a named constraint (default: false).
     * 
     * @return self Returns the table class instance.
     */
    public function foreign(string $table, string $column, bool $constraint = false): self
    {
        if ($constraint) {
            $id = "fk_{$this->tableName}_{$table}";
            return $this->entry("CONSTRAINT `{$id}` FOREIGN KEY (`" . self::COLUMN_NAME . "`) REFERENCES `{$table}`({$column})");
        }

        if ($this->database === self::MYSQL) {
            return $this->entry("FOREIGN KEY (`" . self::COLUMN_NAME . "`) REFERENCES `{$table}`({$column})");
        }

        return $this->attribute("FOREIGN KEY REFERENCES `{$table}`({$column})");
    }

    /**
     * Add `UNSIGNED` attribute to a column definition.
     * For non-MySQL databases, a `CHECK (column >= 0)` constraint is used to enforce non-negative values.
     *
     * @return self Returns the table class instance.
     */
    public function unsigned(): self
    {
        if($this->database === self::MYSQL || $this->database === self::SQLITE) {
            return $this->attribute('UNSIGNED');
        }

        return $this->attribute('CHECK (' . self::COLUMN_NAME . ' >= 0)');
    }

    /**
     * Sets a comment for the column.
     *
     * @param string $comment The comment to set.
     * 
     * @return self Return table class instance.
     */
    public function comment(string $comment): self
    {
        $comment = str_replace("'", "''", $comment);
        return $this->attribute("COMMENT='{$comment}'");
    }

    /**
     * Define a VIRTUAL generated column.
     *
     * @param string $expression The SQL expression defining the column's value.
     * 
     * @return self Returns the table class instance.
     */
    public function virtual(string $expression): self
    {
        return match ($this->database) {
            self::SQL_SERVER, self::MS_ACCESS => $this->attribute("AS {$expression}"),
            default => $this->attribute("GENERATED ALWAYS AS ({$expression}) VIRTUAL")
        };
    }

    /**
     * Define a STORED generated column.
     *
     * @param string $expression The SQL expression defining the column's value.
     * 
     * @return self Returns the table class instance.
     */
    public function stored(string $expression): self
    {
        switch ($this->database) {
            case self::MYSQL:
                return $this->attribute("GENERATED ALWAYS AS ({$expression}) STORED");
            
            case self::SQL_SERVER:
                return $this->attribute("AS {$expression} PERSISTED");

            case self::MS_ACCESS:
                return $this->attribute("AS {$expression}");

            case self::ORACLE:
                throw new DatabaseException("Oracle doesn't not support stored column use virtual instead.");
            
            default:
                return $this->attribute("GENERATED ALWAYS AS ({$expression}) STORED");
        }
    }

    /**
     * Sets the media type for the column.
     *
     * @param string $type The media type to set.
     * 
     * @return self Return table class instance.
     */
    public function mediaType(string $type): self
    {
        return $this->attribute("MEDIA TYPE '{$type}'");
    }

    /**
     * Sets browser display transformation for the column.
     *
     * @param string $type The transformation type.
     * @param array|null $option Optional transformation option.
     * 
     * @return self Return table class instance.
     */
    public function browserDisplay(string $type, ?array $option = null): self
    {
        $option = ($option === null || $option === []) ? '' : $this->getValues($option, null, null);
        return $this->attribute("BROWSER DISPLAY {$type} {$option}");
    }

    /**
     * Sets input transformation for the column.
     *
     * @param string $type The transformation type.
     * @param array|null $option Optional transformation option.
     * 
     * @return self Return table class instance.
     */
    public function inputTransformation(string $type, ?array $option = null): self
    {
        $option = ($option === null || $option === []) ? '' : $this->getValues($option, null, null);
        return $this->attribute("INPUT TRANSFORMATION {$type} {$option}");
    }

    /**
     * Reorder column at the beginning.
     * 
     * @return self Return table class instance.
     */
    public function first(): self
    {
        return $this->add('move', 'FIRST', false);
    }

    /**
     * Reorder column after a specific column name.
     *
     * @param string $column The column name to move after it.
     * 
     * @return self Return table class instance.
     */
    public function after(string $column): self
    {
        return $this->add('move', "AFTER {$column}", false);
    }
}