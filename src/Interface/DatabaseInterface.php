<?php
/**
 * Luminova Framework database driver interface implementation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \Luminova\Core\CoreDatabase;
use \Luminova\Interface\ConnInterface;
use \Luminova\Exceptions\DatabaseException;
use \PDOStatement;
use \mysqli_stmt;

interface DatabaseInterface  
{
    /**
     * Initialize database driver constructor.
     *
     * @param CoreDatabase $config The database connection configuration.
     * 
     * @throws DatabaseException If the database connection fails.
     */
    public function __construct(CoreDatabase $config);

    /**
     * Get the name of the database connection driver.
     * 
     * @return string|null Returns the driver name if the connection is open, otherwise null.
     */
    public function getDriver(): ?string;

    /**
    * Retrieve a specific database configuration property.
    * 
    * This method allows fetching configuration values related to the database connection.
    * If the requested property does not exist, `null` is returned.
    * 
    * ### Available Properties:
    * - **port** *(null)*: The database server port (always: `null`).
    * - **host** *(null)*: The database server hostname or IP (always: `null`).
    * - **connection** *(string)*: The connection method, typically `'pdo'` or other (default: `'pdo'`).
    * - **pdo_engine** *(string)*: The PDO driver to use (e.g., `'mysql'`, `'sqlite'`, etc.) (default: `'mysql'`).
    * - **charset** *(string)*: The character encoding for the connection (default: `'utf8mb4'`).
    * - **sqlite_path** *(string|null)*: Path to the SQLite database file (default: `null`).
    * - **production** *(bool)*: Indicates if the connection is in a production environment (default: `false`).
    * - **username** *(null)*: The database username (always: `null`).
    * - **password** *(null)*: The database password (always: `null`).
    * - **database** *(string)*: The name of the selected database (default: `''`).
    * - **persistent** *(bool)*: Whether to use persistent connections (default: `true`).
    * - **socket** *(bool)*: Whether to use a Unix socket instead of TCP/IP (default: `false`).
    * - **socket_path** *(string)*: The Unix socket path if `socket` is enabled (default: `''`).
    * - **emulate_preparse** *(bool)*: Enables query emulation before execution (default: `false`).
    * 
    * @param string $property The name of the configuration property.
    * 
    * @return mixed Returns the property value if it exists, otherwise `null`.
    */
    public function getConfig(string $property): mixed;


    /**
     * Check if the database is connected.
     * 
     * @return bool Returns true if connected, false otherwise.
     */
    public function isConnected(): bool;

    /**
     * Get the raw database connection instance (e.g., PDO or mysqli).
     * 
     * @return ConnInterface|null Returns the connection instance if connected, otherwise null.
     */
    public function raw(): ?ConnInterface;

    /**
     * Set the debug mode.
     *
     * @param bool $debug Enable or disable debug mode.
     * 
     * @return self Returns the current instance.
     */
    public function setDebug(bool $debug): self;

    /**
     * Get the error information for the last executed statement.
     *
     * @return string Returns the error information as a string.
     */
    public function error(): string;

    /**
     * Get all error information.
     *
     * @return array Returns an array of error information.
     */
    public function errors(): array;

    /**
     * Dump debug information for the last executed statement.
     *
     * @return bool Returns true if debug information is dumped, otherwise false.
     */
    public function dumpDebug(): bool;

    /**
     * Record the database query execution time.
     * This method stores the execution time in a shared memory using `__DB_QUERY_EXECUTION_TIME__`, 
     * to retrieve later when needed.
     * 
     * Note: To call this method you must first enable `debug.show.performance.profiling` in environment variables file.
     *
     * @param bool $start Indicates whether to start or stop recording (default: true).
     * @param bool $finishedTransaction Indicates whether is stopping recording for a transaction commit or rollback (default: false).
     *              This is used internally to stop recording after transaction has been committed or rolled back.
     * 
     * @return void
     * 
     * @example - To get the query execution in any application scope. 
     * 
     * ```php
     * $time = shared('__DB_QUERY_EXECUTION_TIME__', null, 0);
     * ```
     */
    public function profiling(bool $start = true, bool $finishedTransaction = false): void;

    /**
     * Get information about the last executed statement.
     *
     * @return array Returns an array of statement execution information.
     */
    public function info(): array;

    /**
     * Frees up the statement cursor and sets the statement object to null.
     * 
     * @return void
     */
    public function free(): void;

    /**
     * Frees up the statement cursor and closes the database connection.
     * 
     * @return void
     */
    public function close(): void;

    /**
     * Prepare a statement for execution.
     *
     * @param string $query The SQL query string to execute.
     * 
     * @return self Returns the current instance.
     * @throws DatabaseException Throw if an called when no connection is established.
     */
    public function prepare(string $query): self;

    /**
     * Execute a query without using placeholders.
     *
     * @param string $query The SQL query string to execute.
     * 
     * @return self Returns the current instance.
     * @throws DatabaseException Throw if an called when no connection is established.
     */
    public function query(string $query): self;

    /**
     * Execute an SQL statement and return the number of affected rows.
     * 
     * @param string $query The SQL query string to execute.
     * 
     * @return int Returns the number of affected rows.
     * @throws DatabaseException Throw if an called when no connection is established.
     */
    public function exec(string $query): int;

    /**
     * Begin a transaction with an optional read-only isolation level and savepoint.
     *
     * @param int $flags Optional flags to set transaction properties.
     *                   For MySQLi:
     *                   - MYSQLI_TRANS_START_READ_ONLY: Set transaction as read-only.
     *                   For PDO:
     *                   - Specify `4` to create a read-only isolation level.
     * @param string|null $name Optional name for a savepoint.
     *                          If provided, a savepoint will be created in PDO.
     * 
     * @return bool Returns true if the transaction and optional savepoint were successfully started.
     * @throws DatabaseException Throws an exception in PDO if setting the transaction isolation level or creating a savepoint fails.
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool;

    /**
     * Commit a transaction.
     *
     * @param int $flags Optional flags for custom handling (MySQLi only).
     * @param string|null $name Optional name for a savepoint (MySQLi only).
     * 
     * @return bool Returns true if the transaction was successfully committed.
     * @throws DatabaseException Throw if an called when no connection is established.
     */
    public function commit(int $flags = 0, ?string $name = null): bool;

    /**
     * Roll back the current transaction or to a specific savepoint.
     *
     * @param int $flags Optional flags for custom handling (MySQLi only).
     * @param string|null $name Optional name of the savepoint to roll back to.
     *                          If provided, rolls back to the savepoint in PDO.
     * 
     * @return bool Returns true if the rollback was successful, otherwise false.
     * @throws DatabaseException Throws an exception in PDO if rolling back to a savepoint fails.
     */
    public function rollback(int $flags = 0, ?string $name = null): bool;

    /**
     * Check if there is an active transaction.
     * 
     * @return bool Returns true if there is an active transaction, otherwise false.
     * @throws DatabaseException Throw if an called when no connection is established.
     */
    public function inTransaction(): bool;

    /**
     * Get the appropriate parameter type based on the value.
     *
     * @param mixed $value The parameter value.
     *
     * @return string|int|null Returns the parameter type as a string, int, or null.
     */
    public static function getType(mixed $value): string|int|null;

    /**
     * Bind a value to a parameter.
     *
     * @param string $param The parameter identifier.
     * @param mixed $value The parameter value.
     * @param int|null $type The parameter type.
     *
     * @return self Returns the current instance.
     * @throws DatabaseException Throw if an called with preparing query.
     */
    public function bind(string $param, mixed $value, ?int $type = null): self;

    /**
     * Bind a parameter to a specified variable by reference.
     *
     * @param string $param The parameter identifier.
     * @param mixed &$value The parameter value passed by reference.
     * @param int|null $type The parameter type.
     *
     * @return self Returns the current instance.
     * @throws DatabaseException Throw if an called with preparing query.
     */
    public function param(string $param, mixed &$value, ?int $type = null): self;

    /**
     * Execute the prepared statement.
     * 
     * @param array|null $params Optional array of parameters to bind during statement execution. Each value is treated as a string.
     * 
     * @return bool Returns true on success, false on failure.
     * @throws DatabaseException Throws if an error occurs while executing query.
     */
    public function execute(?array $params = null): bool;

    /**
     * Check if the last query execution was successful.
     * 
     * @return bool Returns true on success, false on failure.
     */
    public function ok(): bool;

    /**
     * Get the number of rows affected by the last executed statement.
     *
     * @return int Returns the number of affected rows.
     */
    public function rowCount(): int;

    /**
     * Retrieves a result item based on the specified mode and return type.
     *
     * This method allows flexible result retrieval using a single interface, depending on the mode and return type provided.
     *
     * @param int $mode The mode of the result to return (e.g., RETURN_* constants).
     *                  Available Modes:
     *                  - RETURN_NEXT: Fetch the next row (same as `$db->getNext($return)`).
     *                  - RETURN_2D_NUM: Fetch a 2D numeric array (same as `$db->getInt()`).
     *                  - RETURN_INT: Fetch a single integer value (same as `$db->getCount()`).
     *                  - RETURN_ID: Fetch the last insert ID (same as `$db->getLastInsertId()`).
     *                  - RETURN_COUNT: Fetch the count of affected rows (same as `$db->rowCount()`).
     *                  - RETURN_COLUMN: Fetch specific columns (same as `$db->getColumns()`).
     *                  - RETURN_ALL: Fetch all rows (same as `$db->getAll($return)`).
     *                  - RETURN_STMT: Return the statement object itself (same as `$db->getStatement()`).
     *                  - RETURN_RESULT: Return the query result in mysqli or statement in PDO.
     * 
     * @param string $return The return type when applicable (e.g., `array` or `object`).
     *                       Used only with `RETURN_NEXT` and `RETURN_ALL` modes.
     *
     * @return mixed|false Return the result based on the specified mode and return type.
     * @throws DatabaseException Throw if an error occurs.
     */
    public function getResult(int $mode = RETURN_ALL, string $return = 'object'): mixed;

    /**
     * Fetch the next row from the result set as an object or array.
     * 
     * @param string $type The return type ('object' or 'array').
     * 
     * @return array|object|false Returns the result as an object or array, or false if no more rows are available.
     */
    public function getNext(string $type = 'object'): array|object|bool;

    /**
     * Fetch all rows from the result set as an array of objects or arrays.
     * 
     * @param string $type The return type ('object' or 'array').
     * 
     * @return array|object|false Returns an array of result objects or arrays, or false on failure.
     */
    public function getAll(string $type = 'object'): array|object|bool;

    /**
     * Fetch the result set as a 2D array of integers.
     *
     * @return array|false Returns a 2D array of integers, or false on failure.
     */
    public function getInt(): array|bool;

    /**
     * Get the total count of selected rows as an integer.
     *
     * @return int Returns the total count as an integer, or `0` on failure.
     */
    public function getCount(): int;

    /**
     * Get columns from the result set.
     * 
     * @param int $mode The fetch mode (FETCH_COLUMN or FETCH_COLUMN_ASSOC).
     * 
     * @return array Returns an array of columns.
     */
    public function getColumns(int $mode = FETCH_COLUMN): array;

    /**
     * Retrieve mysqli or PDO prepared statement of the last executed query.
     *
     * @return PDOStatement|mysqli_stmt|null Returns the statement object, or null on failure.
     */
    public function getStatement(): PDOStatement|mysqli_stmt|null;

    /**
     * Fetch the result with a specific type and mode.
     * 
     * @param string $type The fetch method ('all' or 'next').
     * @param int $mode The fetch mode.
     * 
     * @return mixed Returns the fetched result.
     * @throws DatabaseException Throw if an error occurs.
     */
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed;

    /**
     * Fetch the result set as an object of the specified class or stdClass.
     * 
     * @param class-string|null $class The full qualify class name to instantiate (default: stdClass).
     * @param mixed ...$arguments Additional arguments to initialize the class constructor with.
     * 
     * @return object|false Returns the fetched object, or false if no more rows are available or an error occurs.
     */
    public function fetchObject(string|null $class = 'stdClass', mixed ...$arguments): object|bool;

    /**
     * Get the ID of the last inserted row or sequence value.
     * 
     * @param string|null $name Optional name of the sequence object from which the ID should be returned (MySQL/PostgreSQL only).
     * 
     * @return int|string|false|null Returns the last inserted ID, or null/false on failure.
     */
    public function getLastInsertId(?string $name = null): string|int|null|bool;

    /**
     * Retrieves the total query execution time.
     *
     * This method returns the accumulated time spent on executing queries
     * in either float or integer format, depending on the internal state
     * of the query time.
     *
     * @return float|int Return the total query execution time in seconds.
     */
    public function getQueryTime(): float|int;

    /**
     * Retrieves the last query execution time.
     *
     * This method returns the time spent on the last query execution
     * in either float or integer format, depending on the internal state
     * of the query time.
     *
     * @return float|int Return the last query execution time in seconds.
     */
    public function getLastQueryTime(): float|int;
}