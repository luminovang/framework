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
use \stdClass;

interface DriversInterface  
{
    /**
     * Get driver name
     * 
     * @return string Database driver name
    */
    public function getDriver(): string;

    /**
     * Sets the debug mode.
     *
     * @param bool $debug The debug mode.
     * @return self The current class instance.
    */
    public function setDebug(bool $debug): self;

    /**
     * Returns the error information for the last statement execution.
     *
     * @return string The error information array.
    */
    public function error(): string;

    /**
     * Returns the error information.
     *
     * @return array The error information.
     */
    public function errors(): array;

    /**
     * Dumps the debug information for the last statement execution.
     *
     * @return string|null The debug information or null if debug mode is off.
     */
    public function dumpDebug(): mixed;

    /**
     * Returns the error information for the last statement execution.
     *
     * @return array $info The error information array.
    */
    public function info(): array;

    /**
     * Prepares a statement for execution.
     *
     * @param string $query The SQL query.
     * @return self The current class instance.
     */
    public function prepare(string $query): self;

    /**
     * Executes a query.
     *
     * @param string $query The SQL query.
     * @return self The current class instance.
     */
    public function query(string $query): self;

    /**
     * Executes a query.
     *
     * @param string $query The SQL query.
     * @return int The affected row counts
     */
    public function exec(string $query): int;

    /**
     * Begin transaction
     *
     * @return void 
     */
    public function beginTransaction(): void;

    /**
     * Commits transaction
     *
     * @return void 
     */
    public function commit(): void;

    /**
     * Rollback transaction if fails
     *
     * @return void
     */
    public function rollback(): void;

    /**
     * Returns the appropriate parameter type based on the value and type.
     *
     * @param mixed       $value The parameter value.
     * @param ?int    $type  The parameter type.
     *
     * @return int The parameter type.
     */
    public function getType(mixed $value, ?int $type = null) : mixed;

    /**
     * Binds a value to a parameter.
     *
     * @param string       $param The parameter identifier.
     * @param mixed       $value The parameter value.
     * @param null|int    $type  The parameter type.
     *
     * @return self The current class instance.
     */
    public function bind(string $param, mixed $value, mixed $type = null): self;

    /**
     * Binds a variable to a parameter.
     *
     * @param string       $param The parameter identifier.
     * @param mixed       $value The parameter value.
     * @param null|int    $type  The parameter type.
     *
     * @return self The current class instance.
     */
    public function param(string $param, mixed $value, mixed $type = null): self;

    /**
     * Executes the prepared statement.
     * @param array $values execute statement with values
     * 
     * @throws DatabaseException 
     * @return void
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
     * Fetches all rows as a array.
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
     * Frees up the statement cursor and close database connection
     * 
     * @return void
     */
    public function close(): void;
}
