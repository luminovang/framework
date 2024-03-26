<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Database\Drivers;

use \Luminova\Config\Database;
use \PDOStatement;
use \mysqli_stmt;
use \mysqli_result;
use \stdClass;

interface DriversInterface  
{
    /**
     * Constructor.
     *
     * @param Database $config Database configuration.
     * 
     * @throws DatabaseException
    */
    public function __construct(Database $config);

    /**
     * Checks if the database is connected.
     * 
     * @return bool True if connected, false otherwise.
    */
    public function isConnected(): bool;

    /**
     * Retrieves the driver name.
     * 
     * @return string The database driver name.
    */
    public static function getDriver(): string;

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
     * Retrieves the debug information for the last statement execution.
     *
     * @return mixed The debug information, or null if debug mode is off.
    */
    public function dumpDebug(): mixed;

    /**
     * Returns the error information for the last statement execution.
     *
     * @return array The error information.
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
     * Executes a query.
     *
     * @param string $query The SQL query.
     * 
     * @return int The affected row count.
    */
    public function exec(string $query): int;

    /**
     * Begins a transaction.
     *
     * @return void 
    */
    public function beginTransaction(): void;

    /**
     * Commits a transaction.
     *
     * @return void 
    */
    public function commit(): void;

    /**
     * Rolls back a transaction.
     *
     * @return void
    */
    public function rollback(): void;

    /**
     * Returns the appropriate parameter type based on the value and type.
     *
     * @param mixed $value The parameter value.
     *
     * @return string|int|null The parameter type.
    */
    public function getType(mixed $value): string|int;

    /**
     * Binds a value to a parameter.
     *
     * @param string $param The parameter identifier.
     * @param mixed $value The parameter value.
     * @param int|null $type The parameter type.
     *
     * @return self The current instance.
    */
    public function bind(string $param, mixed $value, ?int $type = null): self;

    /**
     * Binds a variable to a parameter.
     *
     * @param string $param The parameter identifier.
     * @param mixed $value The parameter value.
     * @param int|null $type The parameter type.
     *
     * @return self The current instance.
    */
    public function param(string $param, mixed $value, ?int $type = null): self;

    /**
     * Executes the prepared statement.
     * 
     * @param array|null $values Optional values to execute with.
     * 
     * @return void
     * 
     * @throws DatabaseException 
    */
    public function execute(?array $values = null): void;

    /**
     * Returns the number of rows affected by the last statement execution.
     *
     * @return int The number of rows.
    */
    public function rowCount(): int;

    /**
     * Fetches a single row as an object.
     *
     * @return mixed The result object or false if no row is found.
    */
    public function getOne(): mixed;

    /**
     * Fetches all rows as an array of objects.
     *
     * @return mixed The array of result objects.
    */
    public function getAll(): mixed;

    /**
     * Fetches all rows as a 2D array of integers.
     *
     * @return int The 2D array of integers.
    */
    public function getInt(): int;

    /**
     * Fetches all rows as an array or stdClass object.
     *
     * @param string $type The type of result to fetch ('object' or 'array').
     * 
     * @return array|stdClass The result containing the rows.
    */
    public function getResult(string $type = 'object'): array|stdClass;

    /**
     * Fetches all rows as a stdClass object.
     *
     * @return stdClass The stdClass object containing the result rows.
    */
    public function getObject(): stdClass;

    /**
     * Get colums  
     *
     * @return mixed 
    */
    public function getColumns(): mixed;

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
     * Get prepered statment
     *
     * @return object|bool 
    */
    public function getStatment(): PDOStatement|mysqli_stmt|mysqli_result|bool|null;

    /**
     * Fetches all rows as an array.
     *
     * @return array The array containing the result rows.
    */
    public function getArray(): array;

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @return string The last insert ID.
    */
    public function getLastInsertId(): string;

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
