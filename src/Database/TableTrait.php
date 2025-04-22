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

use \Luminova\Database\Alter;
use \Luminova\Database\Table;
use \Luminova\Exceptions\DatabaseException;

/**
 * Table colum creation methods.
 *                 
 * @method Table      number(string $name, ?int $precision = null, ?int $scale = null)  Creates a `DECIMAL` column with precision and scale length (default: 10,0).
 * @method Table      integer(string $name, ?int $length = null)                        Creates an `INT` column with optional length.
 * @method Table      string(string $name, string $type = 'VARCHAR', int|null $length = 255)    Create a new column to store string values (default: VARCHAR(255)).
 * @method Table      varchar(string $name, int|null $length = 255)                     Creates a `VARCHAR` column with optional length.
 * @method Table      text(string $name, int|null $length = 255)                        Creates a `TEXT` column with optional length (defaultL 255).
 * @method Table      date(string $name, ?int $length = null)                           Creates a `DATE` column with optional length.
 * @method Table      tinyInt(string $name, ?int $length = null)                        Creates a `TINYINT` column with optional length.
 * @method Table      smallInt(string $name, ?int $length = null)                       Creates a `SMALLINT` column with optional length.
 * @method Table      mediumInt(string $name, ?int $length = null)                      Creates a `MEDIUMINT` column with optional length.
 * @method Table      bigInt(string $name, ?int $length = null)                         Creates a `BIGINT` column with optional length.
 * @method Table      decimal(string $name, int $precision = 10, int $scale = 0)        Creates a `DECIMAL` column with precision and scale length (default: 10,0).
 * @method Table      float(string $name, int $precision = 10, int $scale = 2)          Creates a `FLOAT` column with precision and scale length (default: 10,2).
 * @method Table      double(string $name, int $precision = 10, int $scale = 2)         Creates a `DOUBLE` column with precision and scale length (default: 10,2).
 * @method Table      real(string $name, ?int $length = null)                           Creates a `REAL` column with optional length.
 * @method Table      bit(string $name, int $length = 1)                                Creates a `BIT` column with optional length.
 * @method Table      boolean(string $name)                                             Creates a `BOOLEAN` column, a synonym for `TINYINT(1)`.
 * @method Table      serial(string $name)                                              Creates a `SERIAL` column, an alias for `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE`.
 * @method Table      datetime(string $name, ?int $precision = null)                    Creates a `DATETIME` column with optional precision length.
 * @method Table      timestamp(string $name, ?int $precision = null)                   Creates a `TIMESTAMP` column with optional precision length.
 * @method Table      time(string $name, ?int $precision = null)                        Creates a `TIME` column with optional precision length.
 * @method Table      year(string $name, ?int $precision = null)                        Creates a `YEAR` column with optional precision length.
 * @method Table      char(string $name, int|null $length = 255)                        Creates a `CHAR` column with optional length (default: 255).
 * @method Table      tinyText(string $name)                                            Creates a `TINYTEXT` column.
 * @method Table      mediumText(string $name)                                          Creates a `MEDIUMTEXT` column.
 * @method Table      longText(string $name)                                            Creates a `LONGTEXT` column.
 * @method Table      binary(string $name, ?int $length = null)                         Creates a `BINARY` column with optional length (default: 255).
 * @method Table      varBinary(string $name, int $length = 255)                        Creates a `VARBINARY` column with optional length (default: 255).
 * @method Table      tinyBlob(string $name, int|null $length = null)                   Creates a `TINYBLOB` column with optional length.
 * @method Table      blob(string $name, int $length = 1)                               Creates a `BLOB` column.
 * @method Table      mediumBlob(string $name)                                          Creates a `MEDIUMBLOB` column.
 * @method Table      longBlob(string $name)                                            Creates a `LONGBLOB` column.
 * @method Table      enum(string $name, array $values)                                 Creates an `ENUM` column with default values (e.g. ['php', 'swift', 'java']).
 * @method Table      set(string $name, array $values)                                  Creates a `SET` column with default values (e.g. ['male', 'female', 'trans-broke']).
 * @method Table      geometry(string $name)                                            Creates a `GEOMETRY` column.
 * @method Table      point(string $name)                                               Creates a `POINT` column.
 * @method Table      lineString(string $name)                                          Creates a `LINESTRING` column.
 * @method Table      polygon(string $name)                                             Creates a `POLYGON` column.
 * @method Table      multiPoint(string $name)                                          Creates a `MULTIPOINT` column.
 * @method Table      multiLineString(string $name)                                     Creates a `MULTILINESTRING` column.
 * @method Table      multiPolygon(string $name)                                        Creates a `MULTIPOLYGON` column.
 * @method Table      geometryCollection(string $name)                                  Creates a `GEOMETRYCOLLECTION` column.
 * @method Table      json(string $name)                                                Creates a `JSON` column.
*/
trait TableTrait 
{
    /**
     * Previous types to change changes.
     * 
     * @var array<int,string> $typesToCheck
     */
    protected static array $typesToCheck = [
        'type', 
        'length', 
        'inlineIndex', 
        'increment',
        'scale', 
        'attributes', 
        'move',
        'primary',
        'default',
        'collation',
        'charset',
        'nullable',
        'index',
        'visibility'
    ];

    /**
     * @var array<string,array> $columnTypes
     */
    protected static array $columnTypes = [
        'INT'            =>         ['restricted'      =>         false],
        'INTEGER'        =>         ['restricted'      =>         false],
        'NUMBER'         =>         ['restricted'      =>         true,       'maxLength'  =>        38],
        'VARCHAR'        =>         ['restricted'      =>         true,       'maxLength'  =>        65535],
        'TEXT'           =>         ['restricted'      =>         false],
        'DATE'           =>         ['restricted'      =>         false],
        'TINYINT'        =>         ['restricted'      =>         false],
        'SMALLINT'       =>         ['restricted'      =>         false],
        'MEDIUMINT'      =>         ['restricted'      =>         false],
        'BIGINT'         =>         ['restricted'      =>         false],
        'DECIMAL'        =>         ['restricted'      =>         true,       'maxLength'  =>        65],
        'FLOAT'          =>         ['restricted'      =>         false],
        'DOUBLE'         =>         ['restricted'      =>         false],
        'REAL'           =>         ['restricted'      =>         false],
        'BIT'            =>         ['restricted'      =>         true,       'maxLength'  =>        64],
        'BOOLEAN'        =>         ['restricted'      =>         false],
        'SERIAL'         =>         ['restricted'      =>         false],
        'DATETIME'       =>         ['restricted'      =>         false],
        'TIMESTAMP'      =>         ['restricted'      =>         false],
        'TIME'           =>         ['restricted'      =>         false],
        'YEAR'           =>         ['restricted'      =>         false],
        'CHAR'           =>         ['restricted'      =>         true,       'maxLength'   =>      255],
        'TINYTEXT'       =>         ['restricted'      =>         false],
        'MEDIUMTEXT'     =>         ['restricted'      =>         false],
        'LONGTEXT'       =>         ['restricted'      =>         false],
        'BINARY'         =>         ['restricted'      =>         true,       'maxLength'   =>      255],
        'VARBINARY'      =>         ['restricted'      =>         true,       'maxLength'   =>      65535],
        'TINYBLOB'       =>         ['restricted'      =>         false],
        'BLOB'           =>         ['restricted'      =>         false],
        'MEDIUMBLOB'     =>         ['restricted'      =>         false],
        'LONGBLOB'       =>         ['restricted'      =>         false],
        'ENUM'           =>         ['restricted'      =>         false],
        'SET'            =>         ['restricted'      =>         false],
        'GEOMETRY'       =>         ['restricted'      =>         false],
        'POINT'          =>         ['restricted'      =>         false],
        'LINESTRING'     =>         ['restricted'      =>         false],
        'POLYGON'        =>         ['restricted'      =>         false],
        'MULTIPOINT'           =>   ['restricted'      =>         false],
        'MULTILINESTRING'      =>   ['restricted'      =>         false],
        'MULTIPOLYGON'         =>   ['restricted'      =>         false],
        'GEOMETRYCOLLECTION'   =>   ['restricted'      =>         false],
        'JSON'                 =>   ['restricted'      =>         false],
    ];

    /**
     * Supported index types.
     * 
     * @var array<int,string> $supportedIndexes
     */
    protected static array $supportedIndexes = [
        Table::INDEX_DEFAULT,
        Table::INDEX_UNIQUE,
        Table::INDEX_FULLTEXT,
        Table::INDEX_SPATIAL,
        Table::INDEX_PRIMARY_KEY
    ];

    /**
     * Adds to column, a key-value pair to the current column definition.
     *
     * @param string $key The key to add.
     * @param mixed $value The value to add.
     * @param bool $append Weather to append value as new array element.
     * 
     * @return self Return table class instance.
     */
    protected function add(string $key, mixed $value, bool $append = true): self
    {
        $this->assertColumn();
        $column = array_key_last($this->columns);

        if ($column !== null) {
            if(is_string($value) && str_contains($value, Table::COLUMN_NAME)){
                $value = str_replace(Table::COLUMN_NAME, "{$column}", $value);
            }

            if($append){
                $this->columns[$column][$key][] = $value;
            }else{
                $this->columns[$column][$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Validates the length of the column based on its type.
     *
     * @param string $type The column type.
     * @param mixed $length The length to validate.
     * 
     * @return void
     * @throws DatabaseException If the length exceeds the allowed limit for the column type.
     */
    protected function assertLength(string $type, mixed $length = 255): void
    {
        $columnType = self::$columnTypes[$type];
        if ($columnType['restricted'] && (!is_numeric($length) || $length > $columnType['maxLength'])) {
            throw new DatabaseException("Length for column type $type exceeds allowed limit of {$columnType['maxLength']}");
        }
    }

    /**
     * Validates that at least one column is defined before adding attributes or options.
     *
     * @param string|null $type Optional column type to check support.
     * 
     * @return void
     * @throws DatabaseException If no columns are defined or an unsupported column type is provided.
     */
    protected function assertColumn(?string $type = null): void
    {
        if ($this->columns === []) {
            throw new DatabaseException("You need to add columns first before adding attributes and options to table.");
        }

        if ($type !== null && !isset(self::$columnTypes[strtoupper($type)])) {
            throw new DatabaseException("Unsupported column type: $type");
        }
    }

    /**
     * Adds a new column to the table schema.
     *
     * @param string $type The column type.
     * @param string $name The column name.
     * @param int|array|null $length The column length or enum and set array constants values.
     * @param int|null $scale The column scale length.
     * 
     * @return self Return table class instance.
     * @throws DatabaseException
     */
    protected function addColumn(
        string $type, 
        string $name, 
        int|array|null $length = null, 
        ?int $scale = null
    ): self {
        if ($type === 'ENUM' || $type === 'SET') {
            $length = $this->getValues($length, $type, $name);
        } else {
            $this->assertLength($type, $length);
        }
    
        $this->columns[$name] = compact('type', 'length', 'scale');
    
        return $this;
    }  

    /**
     * Formats and escapes values for SQL for default enum, set and transformation values.
     *
     * @param array<int,mixed> $input The array of values to be formatted.
     * @param string|null $type The data type of the column (optional).
     * @param string|null $name The name of the column (optional).
     * 
     * @return string The formatted and escaped values as a string.
     * @throws DatabaseException If the input array is empty and either $type or $name is null.
     */
    protected function getValues(array $input, ?string $type = null, ?string $name = null): string
    {
        if ($input === []) {
            if($type === null || $name === null){
                $name = array_key_last($this->columns);
                $type = $this->columns[$name]['type']??'UNKNOWN';
            }

            throw new DatabaseException("The '$type' column '$name' must have at least one value.");
        }

        $values = array_map(fn($value) => is_numeric($value) ? $value : "'" . str_replace(['\\', '\''], ['\\\\', '\\\''], $value) . "'", $input);

        return implode(', ', $values);
    }

    /**
     * Checks if a specific attribute of a column has changed.
     *
     * @param string $type The type of attribute to check (e.g., 'type', 'length').
     * @param string $name The name of the column.
     * @param array $previous The previous column definitions.
     * @param array $current The current column definitions.
     * 
     * @return bool Returns true if the attribute has changed, false otherwise.
     */
    private function hasChanged(string $type, string $name, array $previous, array $current): bool
    {
        $pre = $previous[$name] ?? [];
        $previous = $pre[$type] ?? null;
        $current = $current[$type] ?? null;

        if (is_array($previous) && is_array($current)) {
            return !(array_diff($previous, $current) === [] && array_diff($current, $previous) === []);
        }
        
        return $previous !== $current;
    }

    /**
     * Retrieves the current SQL column definitions.
     *
     * @return array<string,array> Return associative array of column definitions.
     * @internal
     */
    public function getColumns(): array
    {
        $columns = $this->columns;
        $columns['info']['ifNotExists'] = $this->ifNotExists;
        $columns['info']['prettify'] = $this->prettify;
        $columns['info']['collation'] = $this->collation;
        $columns['info']['comment'] = $this->comment;
        $columns['info']['tableName'] = $this->tableName;
        $columns['info']['engine'] = $this->engine;
        $columns['info']['session'] = $this->session;
        $columns['info']['global'] = $this->global;
        
        return $columns;
    }

    /**
     * Generates and returns the SQL query for altering the table.
     * Check if column exists in the previous schema and modify if not add new column or dropped.
     * 
     * @param array<string,array> $previous Optional prevues column definitions to match difference to be dropped.
     * @param bool $dropDiffColumns Indicate weather to drop columns that doesn't exists in alter columns (default: false).
     * 
     * @return string Return SQL queries for altering the table.
     * @throws DatabaseException Throws if previous table name does not match with current table name.
     * @internal
     */
    public function getAlterQuery(array $previous = [], bool $dropDiffColumns = false): string
    {
        $alters = '';
        $info = ($previous === []) ? false : ($previous['info']??false);

        if($info !== false && $previous['info']['tableName'] !== $this->tableName){
            throw new DatabaseException("The previous table name: '{$previous['info']['tableName']}' does not match with current table name: '{$this->tableName}'.");
        }
        $primaries = [];
        $executions = [];
        $entries = [];

        foreach ($this->columns as $name => $column) {
            $typeLength = "{$column['type']}";
            $attributes = "";

            if (!is_null($column['length'])) {
                $typeLength .= "({$column['length']}";
                if (!is_null($column['scale'])) {
                    $typeLength .= ",{$column['scale']}";
                }
                $typeLength .= ")";
            }

            if (!empty($column['nullable'])) {
                $attributes .= " {$column['nullable']}";
            }

            if (isset($previous[$name])) {
                if (!empty($previous[$name]['primary'])) {
                    $primaries[$name] = $name;
                }

                foreach (static::$typesToCheck as $type) {
                    if ($this->hasChanged($type, $name, $previous, $column)) {

                        if($type === 'visibility'){
                            $alters .= Alter::setVisibility(
                                $this->database, 
                                $this->tableName,
                                $name,
                                $typeLength,
                                $column['visibility']
                            );
                        }

                        if($type === 'default'){
                            $alters .= Alter::setDefault(
                                $this->database, 
                                $this->tableName,
                                $name,
                                $column['default']
                            );
                        }

                        if($type === 'increment'){
                            $alters .= Alter::getIncrement(
                                $this->database, 
                                $this->tableName,
                                $column['increment'], 
                                $name,
                                true
                            );
                        }

                        if ($type === 'nullable') {
                            $alters .= Alter::setNullable(
                                $this->database, 
                                $this->tableName,
                                $name,
                                $column['nullable']
                            );
                        }
                      
                        if($type === 'move'){
                            $alters .= Alter::setMove(
                                $this->database,
                                $this->tableName,
                                $name,
                                $typeLength,
                                $column['move']
                            );
                        }

                        if($type === 'attributes'){
                            $alters .= Alter::setAttributes(
                                $this->database,
                                $this->tableName,
                                $name,
                                $typeLength
                            );
                        }

                        if($type === 'collation'){
                            $alters .= Alter::setCollation(
                                $this->database, 
                                $this->tableName,
                                $name,
                                $column['collation']
                            );
                        }
            
                        if($type === 'charset'){
                            $alters .= Alter::setCharset(
                                $this->database, 
                                $this->tableName,
                                $name,
                                $column['charset']
                            );
                        }
        
                        if($type === 'inlineIndex'){
                            $alters .= Alter::setInlineIndex(
                                $this->tableName,
                                $name,
                                $column['inlineIndex']
                            );
                        }
                    }
                }
            } else {
                if (!empty($column['primary'])) {
                    $primaries[$name] = $name;
                }

                if (!empty($column['attributes'])) {
                    $attributes .= " " . implode(' ', $column['attributes']);
                }

                if (!empty($column['default'])) {
                    $attributes .= " DEFAULT {$column['default']}";
                }

                if (!empty($column['index'])) {
                    $attributes .= ",\nADD {$column['index']}";
                }
                
                if (!empty($column['inlineIndex'])) {
                   $attributes .= ",\nADD {$column['inlineIndex']}";
                }

                if (!empty($column['collation'])) {
                    $attributes .= ",\nCOLLATE {$column['collation']}";
                }
    
                if (!empty($column['charset'])) {
                    $attributes .= ",\nCHARACTER SET {$column['charset']}";
                }

                if (!empty($column['move'])) {
                    $alters .= Alter::setMove(
                        $this->database,
                        $this->tableName,
                        $name,
                        $typeLength,
                        $column['move']
                    );
                }

                $alters .= Alter::addColumn(
                    $this->tableName,
                    $name,
                    $typeLength,
                    $attributes
                );

                if (!empty($column['entries'])) {
                    $entries = array_merge($entries, $column['entries']);
                }
    
                if (!empty($column['executions'])) {
                    $executions = array_merge($executions, $column['executions']);
                }
            }
        }
       
        foreach ($previous as $name => $attr) {
            if ($dropDiffColumns && !isset($this->columns[$name])) {
                $alters .= Alter::dropColumn($this->tableName, $name);
            }

            if (!empty($attr['primary'])) {
                $primaries[$name] = $name;
            }
        }

        if($primaries !== []){
            $primaries = "`" . implode("`,`", $primaries) . "`";
            $alters .= Alter::setPrimary(
                $this->database, 
                $this->tableName,
                $primaries
            );
        }

        if ($this->collation !== null && (!$info || $this->collation !== $info['collation'])) {
            $alters .= Alter::collate($this->database, $this->tableName, $this->collation);
        }
    
        if ($this->comment !== null && (!$info || $this->comment !== $info['comment'])) {
            $alters .= Alter::comment($this->database, $this->tableName, $this->comment);
        }

        if ($this->engine !== null && (!$info || $this->engine !== $info['engine'])) {
            $alters .= Alter::engine($this->database, $this->tableName, $this->engine);
        }

        return $this->prettify ? $alters : str_replace("\n", " ", $alters);
    }

    /**
     * Generate the SQL create table query with appropriate attributes and settings.
     *
     * @return string Return SQL query for creating the table.
     * @internal
     */
    public function getCreateQuery(): string
    {
        $sql = $this->sqlHeader();
        $primaries = $this->getTableOptions('primary');
        $primaryLength = ($primaries === [] || $primaries === null) ? 0 : count($primaries);
        $sql .= "\n-- SQL Table Definitions\n\n";
        $sql .= "CREATE TABLE " . ($this->ifNotExists ? "IF NOT EXISTS " : "") . "`{$this->tableName}` (\n"; 
        $executions = [];
        $entries = [];
        $columns = '';
        $indexes = '';
        $alters = '';
        
        foreach ($this->columns as $name => $column) {
            $entry = "`{$name}` {$column['type']}";
            $length = "";

            if (!is_null($column['length'])) {
                $length = "({$column['length']}";
                if (!is_null($column['scale'])) {
                    $length .= ",{$column['scale']}";
                }
                $length .= ")";
                $entry .= $length;
            }

            if (!empty($column['attributes'])) {
                $entry .= " " . implode(' ', $column['attributes']);
            }
     
            if (!empty($column['increment'])) {
                if($this->database === Table::ORACLE){
                    $alters .= Alter::getIncrement(
                        $this->database,
                        $this->tableName,
                        $column['increment'],
                        $name
                    );
                }else{
                    $entry .= " " . Alter::getIncrement(
                        $this->database,
                        $this->tableName,
                        $column['increment'],
                        $name
                    );
                }
            }

            if (!empty($column['visibility'])) {
                $entry .= " {$column['visibility']}";
            }

            if (!empty($column['primary']) && $primaryLength === 1) {
                $entry .= " PRIMARY KEY";
            }

            if (!empty($column['nullable'])) {
                $entry .= " {$column['nullable']}";
            }

            if (!empty($column['collation'])) {
                $entry .= " COLLATE {$column['collation']}";
            }

            if (!empty($column['charset'])) {
                $entry .= " CHARACTER SET {$column['charset']}";
            }

            if (!empty($column['default'])) {
                $entry .= " DEFAULT {$column['default']}";
            }

            if (!empty($column['inlineIndex'])) {
                $entry .= " {$column['inlineIndex']}";
            }

            if (!empty($column['entries'])) {
                $entries = array_merge($entries, $column['entries']);
            }

            if (!empty($column['executions'])) {
                $executions = array_merge($executions, $column['executions']);
            }
 
            if (!empty($column['index'])) {
                $indexes .= "{$column['index']},\n";
            }

            if (!empty($column['move'])) {
                $alters .= Alter::setMove(
                    $this->database,
                    $this->tableName,
                    $name,
                    $column['type'] . $length,
                    $column['move']
                );
            }

            $columns .= "{$entry},\n";
        }

        if ($columns !== '') {
            $sql .= $columns;
            if ($indexes !== '') {
                $sql .= "\n-- SQL Query Indexes\n\n";
                $sql .= $indexes;
            }
            $sql = rtrim($sql, ",\n");

            if ($primaries !== [] && $primaryLength > 1) {
                $sql .= ",\nPRIMARY KEY (`" . implode("`,`", $primaries) . "`)";
            }

            $pkConstraint = $this->getTableOptions('pkConstraint');
            if (!empty($pkConstraint)) {
                $pkConstraint = is_array($pkConstraint) ? implode("`,`", $pkConstraint) : $pkConstraint;
                $sql .= ",\nCONSTRAINT pk_{$this->tableName} PRIMARY KEY (`{$pkConstraint}`)";
            }
        }

        if(!empty($entries)){
           $sql .= ",\n" . implode(",\n", $entries);
        }

        $sql .= "\n)";
        $sql .= $this->sqlFooter();
        $sql .= ";";

        if($executions !== []){
            $sql .= "\n\n-- SQL Additional Query Executions\n";
            $sql .= "\n" . implode(";\n", $executions);
            $sql .= ";"; 
        }

        if ($alters !== '') {
            $sql .= "\n\n-- SQL Query Alterations\n";
            $sql .= "\n" . $alters;
        }

        return $this->prettify ? $sql : str_replace("\n", " ", $sql);
    }

    /**
     * Generates SQL session configurations based on stored session variables.
     *
     * @return string Returns the SQL string for session configurations.
     */
    protected function sqlHeader(): string 
    {
        $sql = '';

        if (!empty($this->session)) {
            $sql .= "\n-- SQL Session Configurations\n\n";
            foreach ($this->session as $name => $value) {
                $sql .= "SET SESSION {$name} = {$value};\n";
            }
        }

        if (!empty($this->global)) {
            $sql .= "\n-- SQL Global Configurations\n\n";
            foreach ($this->global as $name => $value) {
                $sql .= "SET GLOBAL {$name} = {$value};\n";
            }
        }

        return $sql;
    }

    /**
     * Generates SQL footer configurations for engine, charset, collation, and comment.
     *
     * @return string Returns the SQL string for footer configurations.
     */
    protected function sqlFooter(): string 
    {
        $sql = '';

        if ($this->engine !== null) {
            $sql .= "\nENGINE={$this->engine}";
        }

        if ($this->charset !== null) {
            $sql .= "\nDEFAULT CHARSET={$this->charset}";
        }

        if ($this->collation !== null) {
            $sql .= "\nCOLLATE={$this->collation}";
        }

        if ($this->comment !== null) {
            $sql .= "\nCOMMENT='{$this->comment}'";
        }

        return $sql;
    }

    /**
     * Retrieves an array of specified table options from the provided columns or default columns.
     *
     * @param string $key The key of the options to retrieve (e.g., 'engine', 'charset', 'collation', 'comment').
     * @param array|null $columns Optional. The columns array to retrieve options from (default: null, uses class property $this->columns).
     * @return array|null Returns an array of options or null if no options are found.
     */
    protected function getTableOptions(string $key, ?array $columns = null): ?array 
    {
        $columns ??= $this->columns;
        $entry = [];

        foreach ($columns as $column) {
            if (!empty($column[$key])) {
                $entry[] = $column[$key] ?? null;
            }
        }

        if ($entry === []) {
            return null;
        }

        return $entry;
    }

    /**
     * Retrieves the last column name from the stored columns array.
     *
     * @return string|int|null Returns the last column name or null if columns array is empty.
     */
    protected function getColumnName(): string|int|null 
    {
        return array_key_last($this->columns);
    }

    /**
     * Define an inline index constraint on a column.
     * This method allows you to specify various types of inline index constraints on a table column,
     * such as default, unique, full-text, spatial, or primary key.
     *
     * @param string $index The type of index constraint to apply (e.g., 'INDEX', 'UNIQUE') (default: 'INDEX').
     * @param string|null $columns The column names to apply the index on (optional).
     *          Separated by comma (e.g. `column1,column2`).
     * 
     * @return self Returns the table class instance.
     * 
     * @throws DatabaseException If an unsupported index type is provided.
     */
    protected function inlineIndex(string $index, ?string $columns = null): self
    {
        $uppIndex = strtoupper($index);

        if(in_array($uppIndex, static::$supportedIndexes)){
            if($columns !== null && $columns !== ''){
                $id = strtolower("idx_{$this->tableName}_" . Table::COLUMN_NAME. "_{$uppIndex}");
                $uppIndex = ($uppIndex === Table::INDEX_DEFAULT) ? '' : "{$uppIndex} ";

                return $this->entry("CONSTRAINT `{$id}` {$uppIndex}INDEX ({$columns})");
            }

            return $this->add('inlineIndex', $uppIndex, false);
        }

        throw new DatabaseException(sprintf(
            'Unsupported constraint index type: %s, supported types: [%s]', 
            $index, 
            implode(', ', static::$supportedIndexes)
        ));
    }

    /**
     * Reorders a column within the table definition.
     *
     * @param string $key The key (name) of the column to reorder.
     * @param string $position The new position for the column ('first', 'last', or 'after') (default: 'last').
     * @param string|null $column Optional. The column key after which to position the reordered column (used only if $position is 'after').
     * 
     * @return void
     */
    protected function order(string $key, string $position = 'last', ?string $column = null) 
    {
        if (!isset($this->columns[$key])) {
            return;
        }
    
        $value = $this->columns[$key];
        unset($this->columns[$key]);
    
        switch ($position) {
            case 'first':
                $this->columns = array_merge([$key => $value], $this->columns);
                break;
    
            case 'last':
                $this->columns[$key] = $value;
                break;
    
            case 'after':
                if ($column === null || !isset($this->columns[$column])) {
                    $this->columns[$key] = $value;
                } else {
                    $arrayKeys = array_keys($this->columns);
                    $index = array_search($column, $arrayKeys);
                    $this->columns = array_slice($this->columns, 0, $index + 1, true) +
                    [$key => $value] +
                     array_slice($this->columns, $index + 1, null, true);
                }
                break;
    
            default:
                $this->columns[$key] = $value;
                break;
        }
    }
}