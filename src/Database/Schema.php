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

use \Luminova\Database\Table;
use \Luminova\Database\Alter;
use \Luminova\Database\Connection;
use \Luminova\Command\Terminal;
use \Luminova\Command\Utils\Color;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;
use \Closure;
use \Throwable;
use function \Luminova\Funcs\{
    is_command,
    shared,
};

final class Schema
{
    /**
     * Report messages.
     * 
     * @var array<int,array> $reports
     */
    private static array $reports = [];

    /**
     * The previous table columns schema.
     * 
     * @var array<string,mixed> $columns
     */
    private static array $columns = [];

    /**
     * Database connection.
     * 
     * @var DatabaseInterface|null $db 
     */
    private static ?DatabaseInterface $db = null;

    /**
     * Command line instance.
     * 
     * @var Terminal|null $cli 
     */
    private static ?Terminal $cli = null;

    /**
     * Get table execution reports.
     * 
     * @return array<int,array> Return result reports.
     */
    public final function getReport(): array 
    {
        return self::$reports;
    }

    /**
     * Gets the database connection.
     *
     * @return DatabaseInterface The database connection.
     */
    protected static function db(): DatabaseInterface
    {
        if (!self::$db instanceof DatabaseInterface) {
            self::$db = (new Connection())->database();
        }

        return self::$db;
    }

    /**
     * Gets command line class instance.
     *
     * @return Terminal The Terminal instance.
     */
    protected static function cli(): Terminal
    {
        if (!self::$cli instanceof Terminal) {
            self::$cli = new Terminal();
        }

        return self::$cli;
    }

    /**
     * Creates a new table by using a callback function to build your table schema.
     *
     * @param string $tableName The name of the table (non-empty string).
     * @param Closure $tableCallback The callback function that defines the table structure.
     * 
     * @return void
     * @throws DatabaseException If any error occurred.
     * @example - Implementation:
     * 
     * ```php
     * Schema::create('foo', function(\Luminova\Database\Table $table){
     *      $table->string('bar');
     *      return $table;
     * });
     * ```
     */
    public static function create(string $tableName, Closure $tableCallback): void
    {
        if ($tableName === '') {
            self::report('Create table name cannot be an empty string.', $tableName);
            return;
        }

        shared('MIGRATION_SUCCESS', false);
        try {
            $table = $tableCallback(new Table($tableName));

            if (!$table instanceof Table) {
                self::report("Error: Your closure callback must return an instance of `\Luminova\Database\Table`.", $tableName);
                return;
            }            

            if(shared('SHOW_QUERY_DEBUG') === true){
                self::$columns = $table->getColumns();
                self::cli()->writeln("Table Creation SQL query string.", 'green');
                self::cli()->newLine();
                self::cli()->writeln($table->getCreateQuery());
                self::cli()->newLine();
                return;
            }

            if(shared('CHECK_ALTER_TABLE') === true){
                self::$columns = $table->getColumns();
                return;
            }

            $query = $table->getCreateQuery();

            if (self::db()->exec($query) > 0) {
                shared('MIGRATION_SUCCESS', true);
                self::report("Table was created successfully.", $tableName, true);
            } else {
                self::report("Unable to create table or table already exists.\nRun migration command with `--drop` flag to drop table before running migration.", $tableName);
            }
        } catch (Throwable $e) {
            self::report($e->getMessage(), $tableName);
        }
    }

    /**
     * Alter table table and columns by using a callback function to build your table schema.
     *
     * @param string $tableName The name of the table (non-empty string).
     * @param Closure $tableCallback The callback function that defines the table structure.
     * 
     * @return void
     * @throws DatabaseException If any error occurred.
     * @example - Implementation:
     * 
     * ```php
     * Schema::modify('foo', function(\Luminova\Database\Table $table){
     *      $table->string('bar');
     *      return $table;
     * });
     * ```
     */
    public static function modify(string $tableName, Closure $tableCallback): void
    {
        if ($tableName === '') {
            self::report('Alter table name cannot be an empty string.', $tableName);
            return;
        }

        shared('ALTER_SUCCESS', false);
        $db = self::db();
        try {
            $table = $tableCallback(new Table($tableName));

            if (!$table instanceof Table) {
                self::report("Error: Your closure callback must return an instance of `\Luminova\Database\Table`.", $tableName);
                return;
            } 

            $query = $table->getAlterQuery(self::$columns, shared('ALTER_DROP_COLUMNS') ?? false);

            if(shared('SHOW_QUERY_DEBUG') === true){
                self::cli()->writeln("Table Alteration SQL query string.", 'green');
                self::cli()->newLine();
                self::cli()->writeln(($query === '') ? 'No SQL column to print' : $query);
                return;
            }

            if($query === ''){
                self::report('No changes was altered to table: %s.', $tableName);
            }

            $db->beginTransaction();

            if ($db->exec($query) > 0) {
                shared('ALTER_SUCCESS', true);
                $db->commit();
                self::report("Table was altered successfully.", $tableName, true);
            } else {
                $db->rollback();
                self::report("Unable to alter table '%s'.", $tableName);
            }
        } catch (Throwable $e) {
            $db->rollback();
            self::report($e->getMessage(), $tableName);
        }

        self::$db = null;
    }

    /**
     * Alter rename a table in the specified database.
     *
     * @param string $from The current name of the table.
     * @param string $to The new name of the table.
     * @param string $database The type of database (e.g., 'sql-server', 'mysql', 'oracle', 'ms-access').
     * 
     * @return void
     * @throws DatabaseException If any error occurred.
     */
    public static function rename(string $from, string $to, string $database = 'mysql'): void 
    {
        if ($from === '' || $to === '') {
            self::report('Rename table "from" or "to" name cannot be an empty string.', $from);
            return;
        }

        shared('ALTER_SUCCESS', false);
        $db = self::db();
        try {
            $query = Alter::renameTable(
                $database,
                $from,
                $to
            );

            if(shared('SHOW_QUERY_DEBUG') === true){
                self::cli()->writeln("Table Rename SQL query string.", 'green');
                self::cli()->newLine();
                self::cli()->writeln(($query === '') ? 'No SQL column to print' : $query);
                return;
            }

            if($query === ''){
                self::report('No changes was altered to table: %s.', $from);
            }

            $db->beginTransaction();
            if (self::db()->exec($query) > 0) {
                $db->commit();
                shared('ALTER_SUCCESS', true);
                self::report("Table was successfully renamed to {$to}.", $from, true);
            } else {
                $db->rollback();
                self::report("Unable to rename table '%s'.", $from);
            }
        }catch (Throwable $e) {
            self::report($e->getMessage(), $from);
        }

        self::$db = null;
    }

    /**
     * Drops a table if it exists.
     *
     * @param string $tableName The name of the table (non-empty string).
     * 
     * @return void
     */
    public static function dropIfExists(string $tableName): void
    {
        self::dropTable($tableName, true);
    }

    /**
     * Drops a table.
     *
     * @param string $tableName The name of the table (non-empty string).
     * 
     * @return void
     */
    public static function drop(string $tableName): void
    {
        self::dropTable($tableName, false);
    }

    /**
     * Drops a table with the specified name.
     *
     * @param string $tableName The name of the table.
     * @param bool $ifExists Whether to add IF EXISTS to the SQL statement.
     * 
     * @return void
     */
    private static function dropTable(string $tableName, bool $ifExists): void
    {
        if ($tableName === '') {
            self::report('Table name cannot be an empty string.', $tableName);
            return;
        }
        $db = self::db();
        /**
         * Hold database object which maybe required to rollback transaction.
         */
        shared('DROP_TRANSACTION', null);

        /**
         * Hold migration drop static.
         */
        shared('MIGRATION_SUCCESS', false);

        try {
            $query = "DROP TABLE " . ($ifExists ? "IF EXISTS " : "") . $tableName;
            $db->beginTransaction();
            if (self::db()->exec($query) > 0) {
                shared('DROP_TRANSACTION', $db);
                shared('MIGRATION_SUCCESS', true);
                self::report("Table has been dropped", $tableName, true);
            } else {
                self::report("Unable to create table or table already exists.\nRun migration command with `--drop` flag to drop table before running migration.", $tableName);
            }
        } catch (Throwable $e) {
            self::report($e->getMessage(), $tableName);
        }
    }

    /**
     * Reports a message for table operations.
     *
     * @param string $message The message to report.
     * @param string|null $tableName The name of the table.
     * @param bool $passed Whether the operation was successful.
     * 
     * @return void
     */
    private static function report(string $message, ?string $tableName = null, bool $passed = false): void
    {
        if(is_command()){
            $message = "[" .  Color::style("$tableName", $passed ? 'green' : 'red') . "] {$message}";

            self::cli()->writeln($message);

            if($passed){return;}
            self::cli()->beeps();
            exit(STATUS_ERROR);
        }

        self::$reports[] = [
            'message' => $message,
            'table' => $tableName,
            'passed' => $passed
        ];
    }
}