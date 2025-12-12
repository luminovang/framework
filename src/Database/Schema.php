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

use \Closure;
use \Throwable;
use \Luminova\Boot;
use \Luminova\Luminova;
use \Luminova\Command\Terminal;
use \Luminova\Command\Utils\Color;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Database\{Table, Helpers\Alter, Connection};

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

        if (!self::$db->isConnected() && !self::$db->connect()) {
            throw new DatabaseException('Database connection failed.');
        }

        return self::$db;
    }

    /**
     * Creates a new table by using a callback function to build your table schema.
     *
     * @param string $tableName The name of the table (non-empty string).
     * @param (Closure(Table $table): Table) $onTable The callback function that defines the table structure.
     * 
     * @return bool
     * @throws DatabaseException If any error occurred.
     * @see Migration::up()
     * 
     * 
     * @example - Implementation:
     * 
     * ```php
     * Schema::create('foo', function(\Luminova\Database\Table $table){
     *      $table->string('bar');
     *      return $table;
     * });
     * ```
     */
    public static function create(string $tableName, Closure $onTable): bool
    {
        Terminal::init();

        if ($tableName === '') {
            self::report('Create table name cannot be an empty string.', $tableName);
            return false;
        }

        Boot::set(Boot::MIGRATION_SUCCESS, false);

        try {
            $table = $onTable(new Table($tableName));

            if (!$table instanceof Table) {
                self::report("Table callback must return an instance of `\Luminova\Database\Table`.", $tableName);
                return false;
            }            

            if(Boot::get(Boot::QUERY_DEBUG) === true){
                self::$columns = $table->getColumns();
                Terminal::writeln("Table Creation SQL query string.", 'green');
                Terminal::newLine();
                Terminal::writeln($table->getCreateQuery());
                Terminal::newLine();
                return true;
            }

            if(Boot::get(Boot::CHECK_ALTER_TABLE) === true){
                self::$columns = $table->getColumns();
                return true;
            }

            $query = $table->getCreateQuery();
            $inTransaction = false;

            if(!self::$db->inTransaction()){
                $inTransaction = self::$db->beginTransaction();
            }

            $created = self::$db->exec($query) > 0;

            if($inTransaction){
                $created ? self::$db->commit() : self::$db->rollback();
            }

            if ($created) {
                Boot::set(Boot::MIGRATION_SUCCESS, true);
                self::report("Table was created successfully.", $tableName, true);
                return true;
            }

            self::report("Unable to create table or table already exists.\nRun migration command with `--drop` flag to drop table before running migration.", $tableName);
        } catch (Throwable $e) {
            if(self::$db->inTransaction()){
                self::$db->rollback();
            }

            self::report($e->getMessage(), $tableName);
        }  finally {
            self::$db->close();
            self::$db = null;
        }

        return false;
    }

    /**
     * Alter table table and columns by using a callback function to build your table schema.
     *
     * @param string $tableName The name of the table (non-empty string).
     * @param (Closure(Table $table): Table) $onTable The callback function that defines the table structure.
     * 
     * @return void
     * @throws DatabaseException If any error occurred.
     * @see Migration::alter()
     * 
     * @example - Implementation:
     * 
     * ```php
     * Schema::modify('foo', function(\Luminova\Database\Table $table){
     *      $table->string('bar');
     *      return $table;
     * });
     * ```
     */
    public static function modify(string $tableName, Closure $onTable): bool
    {
        Terminal::init();

        if ($tableName === '') {
            self::report('Alter table name cannot be an empty string.', $tableName);
            return false;
        }

        Boot::set(Boot::ALTER_SUCCESS, false);

        $table = $onTable(new Table($tableName));

        if (!$table instanceof Table) {
            self::report("Table callback must return an instance of `\Luminova\Database\Table`.", $tableName);
            return false;
        } 

        $query = $table->getAlterQuery(self::$columns, Boot::get(Boot::ALTER_DROP_COLUMNS) ?? false);

        if(Boot::get(Boot::QUERY_DEBUG) === true){
            Terminal::writeln("Table Alteration SQL query string.", 'green');
            Terminal::newLine();
            Terminal::writeln(($query === '') ? 'No SQL column to print' : $query);
            return true;
        }

        if($query === ''){
            self::report('No changes was altered to table: %s.', $tableName);
            return false;
        }

        self::db();

        try {
            $inTransaction = false;

            if(!self::$db->inTransaction()){
                $inTransaction = self::$db->beginTransaction();
            }

            $modified = self::$db->exec($query) > 0;

            if($inTransaction){
                $modified ? self::$db->commit() : self::$db->rollback();
            }

            if ($modified) {
                Boot::set(Boot::ALTER_SUCCESS, true);
                self::report("Table was altered successfully.", $tableName, true);
                return true;
            } 
            
            self::report("Unable to alter table '%s'.", $tableName);
        } catch (Throwable $e) {
            if(self::$db->inTransaction()){
                self::$db->rollback();
            }

            self::report($e->getMessage(), $tableName);
        }  finally {
            self::$db->close();
            self::$db = null;
        }

        return false;
    }

    /**
     * Alter rename a table in the specified database.
     *
     * @param string $from The current name of the table.
     * @param string $to The new name of the table.
     * @param string $database The type of database (e.g., 'sql-server', 'mysql', 'oracle', 'ms-access').
     * 
     * @return bool
     * @throws DatabaseException If any error occurred.
     * @see Migration::alter()
     */
    public static function rename(string $from, string $to, string $database = 'mysql'): bool 
    {
        Terminal::init();

        if (!$from || !$to) {
            self::report('Rename table "from" or "to" name cannot be an empty string.', $from);
            return false;
        }

        Boot::set(Boot::ALTER_SUCCESS, false);

        $query = Alter::renameTable(
            $database,
            $from,
            $to
        );

        if(Boot::get(Boot::QUERY_DEBUG) === true){
            Terminal::writeln("Table Rename SQL query string.", 'green');
            Terminal::newLine();
            Terminal::writeln(($query === '') ? 'No SQL column to print' : $query);
            return true;
        }

        if($query === ''){
            self::report('No changes was altered to table: %s.', $from);
            return false;
        }

        self::db();

        try {
            $inTransaction = false;

            if(!self::$db->inTransaction()){
                $inTransaction = self::$db->beginTransaction();
            }

            $renamed = self::$db->exec($query) > 0;

            if($inTransaction){
                $renamed ? self::$db->commit() : self::$db->rollback();
            }

            if ($renamed) {
                Boot::set(Boot::ALTER_SUCCESS, true);
                self::report("Table was successfully renamed to {$to}.", $from, true);
                return true;
            } 

            self::report("Unable to rename table '%s'.", $from);

        }catch (Throwable $e) {
            if(self::$db->inTransaction()){
                self::$db->rollback();
            }
            self::report($e->getMessage(), $from);
        } finally {
            self::$db->close();
            self::$db = null;
        }

        return false;
    }

    /**
     * Drops a table if it exists.
     *
     * @param string $tableName The name of the table (non-empty string).
     * 
     * @return bool Return true if table was dropped, otherwise false.
     * @see Migration::down()
     */
    public static function dropIfExists(string $tableName): bool
    {
        return self::dropTable($tableName, true);
    }

    /**
     * Drops a table.
     *
     * @param string $tableName The name of the table (non-empty string).
     * 
     * @return bool Return true if table was dropped, otherwise false.
     * @see Migration::down()
     */
    public static function drop(string $tableName): bool
    {
        return self::dropTable($tableName, false);
    }

    /**
     * Drops a table with the specified name.
     *
     * @param string $tableName The name of the table.
     * @param bool $ifExists Whether to add IF EXISTS to the SQL statement.
     * 
     * @return bool
     */
    private static function dropTable(string $tableName, bool $ifExists): bool
    {
        Terminal::init();

        if ($tableName === '') {
            self::report('Table name cannot be an empty string.', $tableName);
            return false;
        }

        /** Hold database object which maybe required to rollback transaction. */
        Boot::set(Boot::DROP_TRANSACTION, null);

        /** Hold migration drop static. */
        Boot::set(Boot::MIGRATION_SUCCESS, false);

        self::db();

        try {
            $query = "DROP TABLE " . ($ifExists ? "IF EXISTS " : "") . $tableName;

            if(!self::$db->inTransaction()){
                self::$db->beginNestedTransaction();
            }

            $dropped = self::$db->exec($query) > 0;

            // if($inTransaction){
            //    $dropped ? self::$db->commit() : self::$db->rollback();
            // }

            if ($dropped) {
                Boot::set(Boot::DROP_TRANSACTION, self::$db);
                Boot::set(Boot::MIGRATION_SUCCESS, true);
                self::report("Table has been dropped", $tableName, true);
                return true;
            }
            
            self::report("Unable to create table or table already exists.\nRun migration command with `--drop` flag to drop table before running migration.", $tableName);
        } catch (Throwable $e) {
            self::report($e->getMessage(), $tableName);
        }

        return false;
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
        if(Luminova::isCommand()){
            $message = "[" .  Color::style($tableName, $passed ? 'green' : 'red') . "] {$message}";

            Terminal::writeln($message);

            if(!$passed){
               Terminal::beeps();
            }

            return;
            //exit(STATUS_ERROR);
        }

        self::$reports[] = [
            'message' => $message,
            'table' => $tableName,
            'passed' => $passed
        ];
    }
}