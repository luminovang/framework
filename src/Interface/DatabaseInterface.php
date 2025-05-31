<?php
/**
 * Luminova Framework database driver interface implementation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
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
     * Get the driver version name of the database connection driver.
     * 
     * @return string|null Returns the driver version name if the connection is open, otherwise null.
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
     * @return self Returns the instance of database driver interface.
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
     * @internal
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
     * Prepares an SQL statement for execution.
     *
     * This method initializes a prepared statement for both PDO and MySQLi drivers.
     * The query should contain placeholders (`:columnName`) to be bound later using `bind()` or `param()`.
     *
     * @param string $query The SQL query string to prepare.
     * 
     * @return self Returns the instance of database driver interface.
     * @throws DatabaseException If no database connection is established.
     * 
     * @see execute()
     * @see bind()
     * @see value()
     * @see param()
     *
     * @example - Preparing SQL Statement:
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
     * ```
     */
    public function prepare(string $query): self;

    /**
     * Executes an SQL query without binding or placeholders.
     *
     * This method is used for executing raw SQL statements that do not require 
     * parameter binding, such as DDL operations (CREATE, ALTER, DROP) or 
     * direct SELECT queries.
     *
     * @param string $query The SQL query string to execute.
     * 
     * @return self Returns the instance of database driver interface.
     * @throws DatabaseException If no database connection is established.
     *
     * @example - Query Examples:
     * 
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $stmt = $db->query("SELECT * FROM users");
     * $result = $stmt->fetchAll();
     * 
     * // Create a new table
     * $result = $db->query("CREATE TABLE logs (id INT AUTO_INCREMENT PRIMARY KEY, message TEXT)")
     *       ->rowCount();
     * ```
     */
    public function query(string $query): self;

    /**
     * Executes an SQL statement without placeholders and returns the number of affected rows.
     *
     * This method is useful for operations like INSERT, UPDATE, DELETE, where 
     * you need to know how many rows were modified.
     *
     * @param string $query The SQL query string to execute.
     * 
     * @return int Returns the number of rows affected by the query.
     * @throws DatabaseException If no database connection is established.
     *
     * @example - Execute Example:
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $affected = $db->exec("UPDATE users SET status = 'active' WHERE last_login > NOW() - INTERVAL 30 DAY");
     * echo "Updated {$affected} rows.";
     * ```
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
     * Binds a value to a named parameter for use in a prepared statement.
     *
     * This method works for both PDO and MySQLi drivers. For PDO, the binding 
     * is done via `bindValue()`, while for MySQLi, the value is stored in 
     * an internal array for later binding.
     *
     * @param string $param The placeholder named parameter (e.g., ':columnName').
     * @param mixed $value The value to bind.
     * @param int|null $type (Optional) The data type for the value. If not 
     *                      provided, it is inferred automatically.
     *
     * @return self Returns the instance of database driver interface.
     * @throws DatabaseException If called without a prepared statement.
     *
     * @example - For both PDO and MySQLi:
     * 
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
     * $stmt->bind(':id', 123)->execute();
     * ```
     */
    public function bind(string $param, mixed $value, ?int $type = null): self;

    /**
     * Binds a value to a named parameter for use in a prepared statement.
     *
     * This method is an alias of `bind`.
     *
     * @param string $param The placeholder named parameter (e.g., ':columnName').
     * @param mixed $value The value to bind.
     * @param int|null $type (Optional) The data type for the value. If not 
     *                      provided, it is inferred automatically.
     *
     * @return self Returns the instance of database driver interface.
     * @throws DatabaseException If called without a prepared statement.
     * 
     * @alias bind()
     *
     * @example - For both PDO and MySQLi:
     * 
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
     * $stmt->value(':id', 123)->execute();
     * ```
     */
    public function value(string $param, mixed $value, ?int $type = null): self;

    /**
     * Binds a variable to a named parameter by reference use in a prepared statement.
     *
     * Unlike `bind()`, this method binds the parameter by reference, which 
     * means changes to the variable will be reflected in the query execution.
     * This is useful for scenarios where values might change before execution.
     *
     * For PDO, the method binds using `bindParam()`, while for MySQLi, it 
     * stores a reference in an internal array.
     *
     * @param string $param The placeholder named parameter (e.g., ':columnName').
     * @param mixed &$value The variable to bind by reference.
     * @param int|null $type (Optional) The data type for the value. If not 
     *                      provided, it is inferred automatically.
     *
     * @return self Returns the instance of database driver interface.
     * @throws DatabaseException If called without a prepared statement.
     *
     * @example - For both PDO and MySQLi:
     * 
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $stmt = $db->prepare("UPDATE users SET status = :status WHERE id = :id");
     * 
     * $status = 'active';
     * $stmt->param(':status', $status)
     *      ->param(':id', $userId);
     * 
     * // Changing the variable before execution affects the query
     * $status = 'inactive';
     * $stmt->execute();
     * ```
     */
    public function param(string $param, mixed &$value, ?int $type = null): self;

    /**
     * Executes the prepared statement.
     * 
     * If parameters are provided, they will be bound to the statement during execution.
     * This method works for both PDO and MySQLi drivers.
     *
     * @param array|null $params (Optional) An associative or indexed array of values 
     *                           to bind to placeholders before execution.
     * 
     * @return bool Returns `true` on success or `false` on failure.
     * @throws DatabaseException If execution fails due to a database error.
     *
     * @example - Executing Using Prepared Statement:
     * 
     * ```php
     * use Luminova\Database\Connection;
     * 
     * $db = (new Connection())->database();
     * 
     * $stmt = $db->prepare("INSERT INTO users (name, email) VALUES (:name, :email)");
     * $stmt->execute([':name' => 'John Doe', ':email' => 'john@example.com']);
     * 
     * var_dump($stmt->rowCount());
     * ```
     */
    public function execute(?array $params = null): bool;

    /**
     * Check if the last query execution was successful.
     * 
     * @return bool Returns true on success, false on failure.
     */
    public function ok(): bool;

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
     * @param string $return The return result type (e.g, `object` or `array`).
     *
     * @return array|object|false Returns the next row as an object or array, or false if no more rows are available.
     */
    public function fetchNext(string $return = 'object'): array|object|bool;

    /**
     * Fetch the next row from the result set as an object or array.
     * 
     * @param string $return The return result type (e.g, `object` or `array`).
     * 
     * @return array|object|false Returns the next row as an object or array, or false if no more rows are available.
     * Alias of `fetchNext()`.
     */
    public function getNext(string $return = 'object'): array|object|bool;

    /**
     * Fetch all rows from the result set as an array of objects or arrays.
     *
     * @param string $return The return result type (e.g, `object` or `array`).
     *
     * @return array Returns an array of result objects or arrays, or an empty array if no rows are found.
     */
    public function fetchAll(string $return = 'object'): array|object|bool;

    /**
     * Fetch all rows from the result set as an array of objects or arrays.
     *
     * @param string $return The return result type (e.g, `object` or `array`).
     *
     * @return array Returns an array of result objects or arrays, or an empty array if no rows are found.
     * Alias of `fetchAll()`.  
     */
    public function getAll(string $return = 'object'): array|object|bool;

    /**
     * Get the number of rows affected by the last executed statement.
     *
     * @return int Returns the number of affected rows.
     */
    public function rowCount(): int;

    /**
     * Fetch the result set as a 2D array of integers.
     *
     * @return array Returns a 2D array of integers, or an empty array if no results are found.
     */
    public function getInt(): array;

    /**
     * Get the total count of selected rows as an integer.
     *
     * @return int Returns the total row count, or `0` if no results are found.
     */
    public function getCount(): int;

    /**
     * Retrieve a specific column or multiple columns from the result set.
     *
     * @param int $mode The fetch mode (FETCH_COLUMN for a single column, FETCH_COLUMN_ASSOC for multiple).
     *
     * @return array Returns an array of column values.
     */
    public function getColumns(int $mode = FETCH_COLUMN): array;

    /**
     * Retrieve the last executed prepared statement.
     *
     * @return PDOStatement|mysqli_stmt|null Returns the statement object, or null if no statement exists.
     */
    public function getStatement(): PDOStatement|mysqli_stmt|null;

    /**
     * Fetch data from the result set using a specified fetch mode.
     *
     * @param string $fetch Fetch type:  
     *   - `'all'` to retrieve all rows at once,  
     *   - `'next'` to fetch a single row,  
     *   - `'stream'` to fetch rows one at a time (use in loops).
     * @param int $mode The fetch mode (e.g., `FETCH_ASSOC`, `FETCH_OBJ`, `FETCH_*`).
     *
     * @return mixed Return the fetched result(s) based on the specified type and mode.
     * @throws DatabaseException Throws an exception if an error occurs.
     */
    public function fetch(string $fetch = 'all', int $mode = FETCH_OBJ): mixed;

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
    public function getLastInsertId(?string $name = null): string|int|bool|null;

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