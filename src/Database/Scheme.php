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

final class Scheme 
{
    public const INT = "INT";
    public const VARCHAR = "VARCHAR";
    public const DEFAULT_NONE = "NONE";
    public const DEFAULT_TIMESTAMP = "CURRENT_TIMESTAMP";
    public const DEFAULT_NULL = "NULL";

    public const INDEX_PRIMARY = "PRIMARY";
    public const INDEX_UNIQUE = "UNIQUE";
    public const INDEX_INDEX = "INDEX";
    public const INDEX_FULLTEXT = "FULLTEXT";
    public const INDEX_SPATIAL = "SPATIAL";

    private $columns = [];
    private $tableName;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
	}

    public function setName(string $name): self
    {
        $this->columns[$name] = [];
        
        return $this;
    }

    public function setType(string $type, int $length = 0): self
    {
        $lastColumn = array_key_last($this->columns);
        $this->columns[$lastColumn]['type'] = $type . ($length > 0 ? "($length)" : '');

        return $this;
    }

    public function setCollation(string $collation): self
    {
        $lastColumn = array_key_last($this->columns);
        $this->columns[$lastColumn]['collation'] = $collation;

        return $this;
    }

    public function setAttributes(array $attributes): self
    {
        $lastColumn = array_key_last($this->columns);
        $this->columns[$lastColumn]['attributes'] = $attributes;

        return $this;
    }

    public function setAutoIncrement(bool $autoIncrement): self
    {
        $lastColumn = array_key_last($this->columns);
        $this->columns[$lastColumn]['auto_increment'] = $autoIncrement;

        return $this;
    }

    public function setDefault(string $default): self
    {
        $lastColumn = array_key_last($this->columns);
        $this->columns[$lastColumn]['default'] = $default;

        return $this;
    }

    public function setIndex(string $indexType): self
    {
        $lastColumn = array_key_last($this->columns);
        $this->columns[$lastColumn]['index'] = $indexType;

        return $this;
    }

    public function getColumns(string $name): mixed 
    {
        return $this->columns[$name] ?? null;
    }

    public function generate(): string 
    {
        $queries = [];

        foreach ($this->columns as $columnName => $column) {
            $query = "$columnName {$column['type']}";

            if (isset($column['collation'])) {
                $query .= " COLLATE {$column['collation']}";
            }

            if (isset($column['attributes'])) {
                $query .= " {$column['attributes']}";
            }

            if (isset($column['auto_increment']) && $column['auto_increment']) {
                $query .= " AUTO_INCREMENT";
            }

            if (isset($column['default'])) {
                if ($column['default'] === self::DEFAULT_NULL) {
                    $query .= " DEFAULT NULL";
                } elseif ($column['default'] === self::DEFAULT_TIMESTAMP) {
                    $query .= " DEFAULT CURRENT_TIMESTAMP";
                } else {
                    $query .= " DEFAULT '{$column['default']}'";
                }
            }

            if (isset($column['index'])) {
                if ($column['index'] === self::INDEX_PRIMARY) {
                    $query .= " PRIMARY KEY";
                } elseif ($column['index'] === self::INDEX_UNIQUE) {
                    $query .= " UNIQUE";
                } elseif ($column['index'] === self::INDEX_INDEX) {
                    $query .= " INDEX";
                } elseif ($column['index'] === self::INDEX_FULLTEXT) {
                    $query .= " FULLTEXT";
                } elseif ($column['index'] === self::INDEX_SPATIAL) {
                    $query .= " SPATIAL";
                }
            }

            $queries[] = $query;
        }

        return "CREATE TABLE IF NOT EXISTS $this->tableName (" . implode(', ', $queries) . ")";
    }
}
