<?php 
/**
 * Luminova Framework Table Scheme builder helper.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Helpers;

use \Luminova\Exceptions\ErrorCode;
use \Luminova\Exceptions\DatabaseException;

class Alter 
{
    public static function getIncrement(
        string $database, 
        string $table, 
        array $input, 
        ?string $column = null, 
        bool $alter = false
    ): string
    {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return $alter
                    ? "ALTER TABLE {$table} ALTER COLUMN {$column} RESTART WITH {$input['start']};"
                    : "IDENTITY({$input['start']},{$input['increment']})";

            case 'ms-access':
                return $alter
                    ? "ALTER TABLE {$table} ALTER COLUMN {$column} AUTOINCREMENT ({$input['start']}, {$input['increment']});"
                    : "AUTOINCREMENT({$input['start']},{$input['increment']})";

            case 'oracle':
                if ($alter) {
                    return "ALTER SEQUENCE seq_{$column} RESTART START WITH {$input['start']} INCREMENT BY {$input['increment']};";
                } else {
                    return "CREATE SEQUENCE seq_{$column} MINVALUE 1 START WITH {$input['start']} INCREMENT BY {$input['increment']};";
                }

            case 'mysql':
            case 'sqlite':
            default:
                return $alter
                    ? "ALTER TABLE {$table} AUTO_INCREMENT={$input['start']};"
                    : 'AUTO_INCREMENT';
        }
    }

    /**
     * Adds a column to a table.
     *
     * @param string $table The name of the table.
     * @param string $column The name of the column.
     * @param string $attributes The column attributes.
     * @return string The SQL statement.
     */
    public static function addColumn(
        string $table,
        string $column,
        string $typeLength,
        string $attributes
    ): string {
        return "ALTER TABLE {$table} ADD COLUMN {$column} {$typeLength} {$attributes};\n";
    }

    /**
     * Changes the type of a column.
     *
     * @param string $database The database type.
     * @param string $table The name of the table.
     * @param string $column The name of the column.
     * @param string $datatype The new data type.
     * @return string The SQL statement.
     */
    public static function setAttributes(
        string $database,
        string $table,
        string $column,
        string $datatype
    ): string {
        switch ($database) {
            case 'ms-access':
                case 'sqlsrv':
            case 'sql-server':
                return "ALTER TABLE {$table} ALTER COLUMN {$column} {$datatype};\n";
 
            case 'oracle':
                return "ALTER TABLE {$table} MODIFY {$column} {$datatype};\n";

            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} MODIFY COLUMN {$column} {$datatype};\n";
        }
    }

    /**
     * Generate the SQL for moving a column within a table for different databases.
     *
     * @param string $database The type of database (e.g., 'mysql', 'sql-server', 'ms-access', 'oracle').
     * @param string $table The name of the table.
     * @param string $column The name of the column to move.
     * @param string $datatype The datatype of the column.
     * @param string $move The new position of the column (e.g., 'AFTER another_column' or 'FIRST').
     * @return string The generated SQL statement(s) for moving the column.
     */
    public static function setMove(
        string $database,
        string $table,
        string $column,
        string $datatype,
        string $move
    ): string {
        switch ($database) {
            case 'ms-access':
                return "ALTER TABLE {$table} ADD COLUMN {$column}_temp {$datatype};\n" .
                    "UPDATE {$table} SET {$column}_temp = {$column};\n" .
                    "ALTER TABLE {$table} DROP COLUMN {$column};\n" .
                    "ALTER TABLE {$table} ADD COLUMN {$column} {$datatype} {$move};\n" .
                    "UPDATE {$table} SET {$column} = {$column}_temp;\n" .
                    "ALTER TABLE {$table} DROP COLUMN {$column}_temp;\n";

            case 'sqlsrv':
            case 'sql-server':
                return "ALTER TABLE {$table} ADD {$column}_temp {$datatype};\n" .
                    "UPDATE {$table} SET {$column}_temp = {$column};\n" .
                    "ALTER TABLE {$table} DROP COLUMN {$column};\n" .
                    "EXEC sp_rename '{$table}.{$column}_temp', '{$column}', 'COLUMN';\n" .
                    "ALTER TABLE {$table} ALTER COLUMN {$column} {$datatype} {$move};\n";

            case 'oracle':
                return "ALTER TABLE {$table} RENAME COLUMN {$column} TO {$column}_temp;\n" .
                    "ALTER TABLE {$table} ADD ({$column} {$datatype} {$move});\n" .
                    "UPDATE {$table} SET {$column} = {$column}_temp;\n" .
                    "ALTER TABLE {$table} DROP COLUMN {$column}_temp;\n";

            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} MODIFY COLUMN {$column} {$datatype} {$move};\n";
        }
    }

    /**
     * Drops a column from a table.
     *
     * @param string $table The name of the table.
     * @param string $column The name of the column.
     * @return string The SQL statement.
     */
    public static function dropColumn(
        string $table,
        string $column
    ): string {
        return "ALTER TABLE {$table} DROP COLUMN {$column};\n";
    }

    public static function setVisibility(
        string $database,
        string $table,
        string $column,
        string $typeLength,
        string $visibility
    ): string {
        if($database === 'mysql' || $database === 'sqlite'){
            return "ALTER TABLE {$table} MODIFY COLUMN {$column} {$typeLength} {$visibility};\n";
        }

        return "-- Visibility {$visibility} is not supported for {$database}\n";
    }

    /**
     * Renames a column in a table.
     *
     * @param string $database The database type.
     * @param string $table The name of the table.
     * @param string $from The current column name.
     * @param mixed $to The new column name.
     * @return string The SQL statement.
     */
    public static function renameColumn(
        string $database,
        string $table,
        string $from,
        mixed $to
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "EXEC sp_rename '{$table}.{$from}', '{$to}', 'COLUMN';\n";

            case 'ms-access':
                return "ALTER TABLE {$table} RENAME COLUMN {$from} {$to};\n";
    
            case 'oracle':
            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to};\n";
        }
    }

   /**
     * Rename a table in the specified database.
     *
     * @param string $database The type of database (e.g., 'sql-server', 'mysql', 'oracle').
     * @param string $from The current name of the table.
     * @param string $to The new name of the table.
     * @return string The SQL statement to rename the table.
     */
    public static function renameTable(string $database, string $from, string $to): string 
    {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "EXEC sp_rename '$from', '$to'";

            case 'oracle':
                return "ALTER TABLE \"$from\" RENAME TO \"$to\"";
            
            case 'ms-access':
                return "ALTER TABLE [$from] RENAME [$to]";

            case 'mysql':
            case 'sqlite':
            default:
                return "RENAME TABLE `$from` TO `$to`";
        }
    }

    /**
     * Sets a default value for a column.
     *
     * @param string $database The database type.
     * @param string $table The name of the table.
     * @param string $column The name of the column.
     * @param mixed $default The default value.
     * @return string The SQL statement.
     */
    public static function setDefault(
        string $database,
        string $table,
        string $column,
        string $default
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "ALTER TABLE {$table} ADD CONSTRAINT df_{$column} DEFAULT {$default} FOR {$column};\n";
                //return "ALTER TABLE {$table} ALTER CONSTRAINT df_{$column} DEFAULT {$default} FOR {$column};\n";
                /*return "DECLARE @constraint_name NVARCHAR(256);\n" .
                   "SELECT @constraint_name = d.name FROM sys.default_constraints d\n" .
                   "JOIN sys.columns c ON d.parent_object_id = c.object_id AND d.parent_column_id = c.column_id\n" .
                   "WHERE c.object_id = OBJECT_ID('{$table}') AND c.name = '{$column}';\n" .
                   "IF @constraint_name IS NOT NULL EXEC('ALTER TABLE {$table} DROP CONSTRAINT ' + @constraint_name);\n" .
                   "ALTER TABLE {$table} ADD CONSTRAINT df_{$column} DEFAULT '{$default}' FOR {$column};\n";*/
    
            case 'oracle':
                return "ALTER TABLE {$table} MODIFY {$column} DEFAULT {$default};\n";
    
            case 'ms-access':
            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT {$default};\n";
        }
    }

    public static function setPrimary(
        string $database,
        string $table,
        string $column
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
            case 'ms-access':
            case 'oracle':
                return "ALTER TABLE {$table} DROP CONSTRAINT pk_{$table}, ADD CONSTRAINT pk_{$table} PRIMARY KEY ({$column});";
            default:
                return "ALTER TABLE {$table} DROP PRIMARY KEY, ADD PRIMARY KEY ({$column});";
        }
    }    

    /**
     * Drops the default value of a column.
     *
     * @param string $database The database type.
     * @param string $table The name of the table.
     * @param string $column The name of the column.
     * @return string The SQL statement.
     */
    public static function dropDefault(
        string $database,
        string $table,
        string $column
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
            case 'ms-access':
            case 'oracle':
                return "ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT;\n";

            default:
                return "ALTER TABLE {$table} ALTER {$column} DROP DEFAULT;\n";
        }
    }

    /**
     * Adds an index to a column.
     *
     * @param string $database The database type.
     * @param string $table The name of the table.
     * @param string $index The index name.
     * @param string $column The column name.
     * @return string The SQL statement.
     */
    public static function addIndex(
        string $table,
        string $column,
        string $index
    ): string {
        $index = $index === 'INDEX' ? "" : " {$index}";

        return "CREATE{$index} INDEX idx_{$column} ON {$table} ({$column});\n";
    }

    public static function setInlineIndex(
        string $table,
        string $column,
        string $index
    ): string 
    {
        $index = $index === 'INDEX' ? "" : " {$index}";

        return "ALTER TABLE {$table} DROP INDEX idx_{$column};\n
        ALTER TABLE {$table} ADD{$index} INDEX idx_{$column} ({$column});\n";
    }

    public static function setNullable(
        string $database,
        string $table,
        string $column,
        string $nullable
    ): string 
    {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "ALTER TABLE {$table} ALTER COLUMN {$column} {$nullable};\n";

            case 'ms-access':
                return "ALTER TABLE {$table} ALTER COLUMN {$column} SET {$nullable};\n";
            
            case 'oracle':
            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} MODIFY {$column} {$nullable};\n";
        }
    }
    
    public static function setCharset(
        string $database,
        string $table,
        string $column,
        string $charset
    ): string 
    {
        switch ($database) {
            case 'oracle':
                return "ALTER TABLE {$table} MODIFY {$column} CHAR CHARACTER SET {$charset};\n";
    
            case 'sqlsrv':
            case 'sql-server':
            case 'ms-access':
                echo "Charset modification is not supported for '{$database}'.";
                return '';

            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} MODIFY {$column} CHARACTER SET {$charset};\n";
        }
    }
    
    public static function setCollation(
        string $database,
        string $table,
        string $column,
        string $collation
    ): string {
        switch ($database) {
            case 'oracle':
                return "ALTER TABLE {$table} MODIFY {$column} COLLATE {$collation};\n";
    
            case 'sqlsrv':
            case 'sql-server':
            case 'sqlsrv':
            case 'ms-access':
                echo "Collation modification is not supported for '{$database}'.";
                return '';

            case 'mysql':
            case 'sqlite':
            default:
                return "ALTER TABLE {$table} MODIFY {$column} COLLATE {$collation};\n";
        }
    }
    
    /**
     * Drops an index from a table.
     *
     * @param string $database The database type.
     * @param string $table The name of the table.
     * @param string $index The index name.
     * @return string The SQL statement.
     */
    public static function dropIndex(
        string $database,
        string $table,
        string $index
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "DROP INDEX {$table}.{$index};\n";

            case 'ms-access':
                return "DROP INDEX {$index} ON {$table};\n";

            case 'oracle':
                return "DROP INDEX {$index};\n";

            default:
                return "ALTER TABLE {$table} DROP INDEX {$index};\n";
        }
    }

    public static function collate(
        string $database,
        string $table,
        string $value
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "";

            case 'ms-access':
                return "";

            case 'oracle':
                return "";

            default:
                return "ALTER TABLE {$table} COLLATE {$value};\n";
        }
    }

    public static function comment(
        string $database,
        string $table,
        string $comment
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "";

            case 'ms-access':
                return "";

            case 'oracle':
                return "";

            default:
                return "ALTER TABLE {$table} COMMENT='{$comment}';\n";
        }
    }

    public static function engine(
        string $database,
        string $table,
        string $engine
    ): string {
        switch ($database) {
            case 'sqlsrv':
            case 'sql-server':
                return "";

            case 'ms-access':
                return "";

            case 'oracle':
                return "";

            default:
                return "ALTER TABLE {$table} ENGINE={$engine};\n";
        }
    }

    public static function getAdministrator(string $driver, string $action, string $placeholder): string 
    {
        $query = match ($driver) {
            'pgsql' => match ($action) {
                'lock'     => "SELECT pg_advisory_lock({$placeholder})",
                'unlock'   => "SELECT pg_advisory_unlock({$placeholder})",
                'isLocked' => "SELECT pg_try_advisory_lock({$placeholder})",
                default    => null
            },
            'mysql', 'mysqli', 'cubrid' => match ($action) {
                'lock'     => 'SELECT GET_LOCK(:lockName, :waitTimeout) AS isLockDone',
                'unlock'   => 'SELECT RELEASE_LOCK(:lockName) AS isLockDone',
                'isLocked' => 'SELECT IS_FREE_LOCK(:lockName) AS isLockDone',
                default    => null
            },
            'sqlite' => match ($action) {
                'lock'     => 'INSERT INTO locks (name, acquired_at) VALUES (:lockName, strftime("%s", "now")) ON CONFLICT(name) DO NOTHING',
                'unlock'   => 'DELETE FROM locks WHERE name = :lockName',
                'isLocked' => 'SELECT COUNT(*) AS lockCount FROM locks WHERE name = :lockName',
                default    => null,
            },
            'sqlsrv', 'mssql', 'dblib' => match ($action) {
                'lock'     => "EXEC sp_getapplock @Resource = :lockName, @LockMode = 'Exclusive', @LockOwner = 'Session', @Timeout = :waitTimeout",
                'unlock'   => "EXEC sp_releaseapplock @Resource = :lockName, @LockOwner = 'Session'",
                'isLocked' => "SELECT COUNT(*) FROM sys.dm_tran_locks WHERE request_mode = 'X' AND resource_description = :lockName",
                default    => null,
            },
            'oci', 'oracle' => match ($action) {
                'lock'     => "DECLARE v_result NUMBER; BEGIN DBMS_LOCK.REQUEST(:lockName, 6, :waitTimeout, TRUE, v_result); END;",
                'unlock'   => "DECLARE v_result NUMBER; BEGIN DBMS_LOCK.RELEASE(:lockName); END;",
                'isLocked' => "SELECT COUNT(*) FROM V\$LOCK WHERE ID1 = DBMS_LOCK.ALLOCATE_UNIQUE(:lockName) AND REQUEST = 6",
                default    => null,
            },
            default => throw new DatabaseException(
                "Database driver '{$driver}' does not support locks.",
                ErrorCode::INVALID_ARGUMENTS
            )
        };

        if($query === null){
            throw new DatabaseException(
                "Invalid {$driver} lock operation: {$action}",
                ErrorCode::INVALID_ARGUMENTS
            );
        }

        return $query;
    }

    /**
     * Build sql query string to drop table.
     * 
     * @param bool $isTempTable Whether to drop temporary table (default false).
     * 
     * @return string Return SQL query string based on database type.
     */
    public static function getDropTable(string $driver, string $table, bool $isTempTable = false): string
    {
        $prefix = $isTempTable ? 'temp_' : '';
        $identifier = $isTempTable ? "#temp_{$table}" : $table;

        return match ($driver) {
            'mysql', 'mysqli' => "DROP " . ($isTempTable ? "TEMPORARY " : "") . "TABLE IF EXISTS {$prefix}{$table}",
            'dblib' => "DROP TABLE IF EXISTS {$identifier}",
            'sqlsrv' => "IF OBJECT_ID('{$prefix}{$table}', 'U') IS NOT NULL DROP TABLE {$prefix}{$table}",
            'oracle', 'oci' => "BEGIN EXECUTE IMMEDIATE 'DROP TABLE {$prefix}{$table}'; EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;",
            default => "DROP TABLE IF EXISTS {$prefix}{$table}"
        };
    }

    public static function getTableExists(string $driver): string 
    {
        return match ($driver) {
            'mysql', 'mysqli' => 'information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tableName',
            'pgsql'     => "pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename = :tableName",
            'sqlite'    => "sqlite_master WHERE type = 'table' AND name = :tableName",
            'sqlsrv', 'mssql' => 'sys.tables WHERE name = :tableName',
            'cubrid'          => 'db_class WHERE class_name = :tableName',
            'dblib'           => "sysobjects WHERE xtype = 'U' AND name = :tableName",
            'oci', 'oracle'   => 'user_tables WHERE table_name = UPPER(:tableName)',
            default => throw new DatabaseException(
                "Unsupported database driver: {$driver}", 
                ErrorCode::INVALID_ARGUMENTS
            ),
        };
    }

    public static function getBuilderTableRename(string $driver, string $table, string $to): string 
    {
        return match ($driver) {
            'mysql', 'mysqli' => "RENAME TABLE {$table} TO {$to}",
            'pgsql', 'sqlite', 'oci', 'oracle' => "ALTER TABLE {$table} RENAME TO {$to}",
            'sqlsrv', 'mssql', 'dblib' => "EXEC sp_rename '{$table}', '{$to}'",
            'cubrid' => "RENAME TABLE {$table} AS {$to}",
            default  => throw new DatabaseException(
                "Unsupported driver: {$driver}",
                ErrorCode::RUNTIME_ERROR
            ),
        };
    }

    public static function getBuilderTableLock(string $driver, bool $forUpdate): string 
    {
        return match ($driver) {
            'mysql', 'mysqli' => $forUpdate ? 'FOR UPDATE' : 'LOCK IN SHARE MODE',
            'pgsql'           => $forUpdate ? 'FOR UPDATE' : 'FOR SHARE',
            'sqlite'          => '', // SQLite locks the whole DB automatically
            'sqlsrv', 'mssql', 'dblib' => $forUpdate 
                ? 'WITH (UPDLOCK, ROWLOCK)' 
                : 'WITH (HOLDLOCK, ROWLOCK)',
            'cubrid'          => $forUpdate ? 'WITH LOCK' : '',
            'oci', 'oracle'   => $forUpdate ? 'FOR UPDATE' : '',
            default           => '',
        };
    }
}