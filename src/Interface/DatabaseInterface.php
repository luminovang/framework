<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \Luminova\Base\BaseDatabase;
use \Luminova\Interface\ConnInterface;
use \Luminova\Exceptions\DatabaseException;
use \PDOStatement;
use \mysqli_stmt;
use \mysqli_result;

interface DatabaseInterface  
{
    /**
     * Constructor.
     *
     * @param BaseDatabase $config Database configuration.
     * 
     * @throws DatabaseException If the database connection fails.
     */
    public function __construct(BaseDatabase $config);

    /**
     * Get database connection driver name.
     * 
     * @return string|null Return driver name if connection is open, otherwise null.
     */
    public function getDriver(): ?string;

    /**
     * Checks if the database is connected.
     * 
     * @return bool True if connected, false otherwise.
     */
    public function isConnected(): bool;

    /**
     * Get the actual raw database connection instance of PDO or mysqli.
     * 
     * @return ConnInterface|null Connection instance if connected, null otherwise.
     */
    public function raw(): ConnInterface|null;

    /**
     * Get prepared statement of a query result.
     * 
     * @return PDOStatement|mysqli_stmt|mysqli_result|bool|null Statement instance.
     */
    public function statement(): PDOStatement|mysqli_stmt|mysqli_result|bool|null;

    /**
     * Sets the debug mode.
     *
     * @param bool $debug The debug mode.
     * 
     * @return self The current instance.
     */
    public function setDebug(bool $debug): self;

    /**
     * Returns the error information for the last statement execution.
     *
     * @return string The error information.
     */
    public function error(): string;

    /**
     * Returns all error information.
     *
     * @return array The error information.
     */
    public function errors(): array;

    /**
     * Debug dumps statement information for the last statement execution.
     *
     * @return bool Dumped statement or false if no prepared statement or debug mode is disabled.
     */
    public function dumpDebug(): bool;

    /**
     * Returns information about the last statement execution.
     *
     * @return array The statement execution information.
     */
    public function info(): array;

    /**
     * Prepares a statement for execution.
     *
     * @param string $query The SQL query.
     * 
     * @return self The current instance.
     */
    public function prepare(string $query): self;

    /**
     * Executes a query.
     *
     * @param string $query The SQL query.
     * 
     * @return self The current instance.
     */
    public function query(string $query): self;

    /**
     * Execute an SQL statement and return the number of affected rows.
     * 
     * @param string $query The SQL statement to execute.
     * 
     * @return int The number of affected rows.
     */
    public function exec(string $query): int;

    /**
     * Begins a transaction with optional read-only isolation level and savepoint.
     *
     * @param int $flags Optional flags to set transaction properties.
     *                  For MySQLi:
     *                      - MYSQLI_TRANS_START_READ_ONLY: Set transaction as read-only.
     *                  For PDO:
     *                      - No predefined flags, specify `4` to create read-only isolation.
     * @param ?string $name Optional name for a savepoint.
     *                    If provided in PDO, savepoint will be created instead.
     * 
     * @return bool Returns true if the transaction and optional savepoint were successfully started.
     * @throws DatabaseException Throws exception on PDO if failure to set transaction isolation level or create savepoint.
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool;

    /**
     * Commits a transaction.
     *
     * @param int $flags Optional flags for custom handling.
     *                 Only supported in MySQLi.
     * @param ?string $name Optional name for a savepoint.
     *                Only supported in MySQLi.
     * 
     * @return bool Returns true if the transaction was successfully committed.
     */
    public function commit(int $flags = 0, ?string $name = null): bool;

    /**
     * Rolls back the current transaction or to a specific savepoint.
     *
     * @param int $flags Optional flags for custom handling.
     *                   Only supported in MySQLi.
     * @param ?string $name Optional name of the savepoint to roll back to.
     *                    If provided in PDO, rolls back to the savepoint named.
     * 
     * @return bool Return true if rolled back was successful, otherwise false.
     * @throws DatabaseException Throws exception on PDO if failure to create savepoint.
     */
    public function rollback(int $flags = 0, ?string $name = null): bool;

    /**
     * Check if any active transaction.
     * 
     * @return bool Return true if any active transaction, otherwise false.
    */
    public function inTransaction(): bool;

    /**
     * Returns the appropriate parameter type based on the value and type.
     *
     * @param mixed $value The parameter value.
     *
     * @return string|int|null The parameter type.
     */
    public static function getType(mixed $value): string|int|null;

    /**
     * Binds a value to a parameter.
     *
     * @param string $param The parameter identifier.
     * @param mixed $value The parameter value.
     * @param int|null $type The parameter type.
     *
     * @return self The current instance.
     */
    public function bind(string $param, mixed $value, int|null $type = null): self;

    /**
     * Binds a parameter to the specified variable name.
     *
     * @param string $param The parameter identifier.
     * @param mixed $value The parameter value passed by reference.
     * @param int|null $type The parameter type.
     *
     * @return self The current instance.
     */
    public function param(string $param, mixed &$value, int|null $type = null): self;

    /**
     * Executes the prepared statement.
     * 
     * @param array|null $params An optional list array to bound parameters while executing statement. Each value is treated as a string.
     * 
     * @return bool True on success, false on failure.
     * 
     * @throws DatabaseException 
     */
    public function execute(?array $params = null): bool;

    /**
     * Check if query execution is completed successfully.
     * 
     * @return bool True on success, false on failure.
    */
    public function ok(): bool;

    /**
     * Returns the number of rows affected by the last statement execution.
     *
     * @return int The number of rows.
     */
    public function rowCount(): int;

    /**
     * Fetches a next single row as an object or array.
     * 
     * @param string $type The type of result to return ('object' or 'array').
     * 
     * @return array|object The result object or false if no row is found.
     */
    public function getNext(string $type = 'object'): array|object|bool;

    /**
     * Fetches all rows as an array or objects.
     * 
     * @param string $type The type of result to return ('object' or 'array').
     * 
     * @return array|object The array of result objects.
     */
    public function getAll(string $type = 'object'): array|object|bool;

    /**
     * Fetches selected rows as a 2D array of integers.
     *
     * @return array|bool The 2D array of integers else return false on failure.
     */
    public function getInt(): array|bool;

     /**
     * Fetches total count of selected rows as integer.
     *
     * @return int|bool Return integers else return false on failure.
     */
    public function getCount(): int|bool;

    /**
     * Get columns
     * 
     * @param int $mode Fetch column mode [FETCH_COLUMN or FETCH_COLUMN_ASSOC]
     * 
     * @return array 
     */
    public function getColumns(int $mode = FETCH_COLUMN): array;

    /**
     * Fetch result with a specific type and mode 
     * 
     * @param string $type The type of fetch method ('all' or 'next').
     * @param int $mode Controls the contents of the returned 
     * 
     * @return mixed 
     */
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed;

    /**
     * Fetches the result set as an object of the specified class or stdClass.
     * 
     * @param string|null $class The name of the class to instantiate, defaults to stdClass.
     * @param array $arguments Additional arguments to pass to the class constructor.
     * 
     * @return object|false Returns the fetched object or false if no more rows are available or an error occurs.
     */
    public function fetchObject(string|null $class = 'stdClass', array $arguments = []): object|bool;

    /**
     * Get prepared statement
     *
     * @return PDOStatement|mysqli_stmt|mysqli_result|bool|null
    */
    public function getStatement(): PDOStatement|mysqli_stmt|mysqli_result|bool|null;

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @return string The last insert ID.
     */
    public function getLastInsertId(): string;

    /**
     * Get result item response.
     * 
     * @param int $mode Return mode RETURN_*.
     * @param string $return [array or object].
     * 
     * @return mixed
     */
    public function getItem(int $mode = RETURN_ALL, string $return = 'object'): mixed;

    /**
     * Frees up the statement cursor and sets the statement object to null.
     * 
     * @return void
     */
    public function free(): void;

    /**
     * Frees up the statement cursor and closes the database
     * 
     * @return void
     */
    public function close(): void;
}