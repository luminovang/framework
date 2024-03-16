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

use \Luminova\Database\Drivers\DriversInterface;
use \Luminova\Config\Database;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Exceptions\InvalidArgumentException;
use \mysqli;
use \mysqli_stmt;
use \mysqli_result;
use \stdClass;
use \mysqli_sql_exception;
use \TypeError;
use \Exception;

class MySqlDriver implements DriversInterface 
{
    /**
     * Mysqli Database connection instance
     * 
    * @var mysqli|null $connection 
    */
    private ?mysqli $connection = null; 

    /**
     * mysqli statement, result object or false
     * 
    * @var object|mysqli_stmt|mysqli_result|bool $stmt 
    */
    private object|bool $stmt = false;

    /**
    * @var bool $onDebug debug mode flag
    */
    private bool $onDebug = false;

    /**
     * Database configuration
     * 
    * @var Database $config 
    */
    private Database $config; 

    /**
    * @var array $queryParams Database queries
    */
    private array $queryParams = [];

    /**
    * @var int $lastRowCount last row count
    */
    private int $lastRowCount = 0;

    /**
    * @var bool $isSelect is select query
    */
    private bool $isSelect = false;

    /**
    * @var bool $connected 
    */
    private bool $connected = false;

    /**
     * Constructor.
     *
     * @param Database $config database configuration. array
     * @throws InvalidArgumentException If a required configuration key is missing.
     * @throws Exception
     * @throws DatabaseException
     */
    public function __construct(Database $config) 
    {
        if (!$config instanceof Database) {
            throw new InvalidArgumentException("Invalid database configuration, required type: Database, but " . gettype($config) . " is given instead.");
        }
      
        $this->config = $config;

        try{
            $this->newConnection();
            $this->connected = true;
        }catch(Exception|DatabaseException $e){
            $this->connected = true;
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * Get driver name
     * 
     * @return string Database driver name
    */
    public function getDriver(): string
    {
        return 'mysqli';
    }

    /**
     * Sets the debug mode.
     *
     * @param bool $debug The debug mode.
     * @return self The current instance of the MySqlDriver class.
     */
    public function setDebug(bool $debug): self 
    {
        $this->onDebug = $debug;
        return $this;
    }

    /**
     * Initializes the database connection.
     * This method is called internally and should not be called directly.
     * 
     * @throws DatabaseException If no driver is specified
     */
    private function newConnection(): void 
    {
        if ($this->connection !== null) {
            return;
        }
        //mysqli_report(MYSQLI_REPORT_ALL);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try{
            $this->connection = new mysqli(
                $this->config->host,
                $this->config->username,
                $this->config->password,
                $this->config->database,
                $this->config->port
            );
        
            if ($this->connection->connect_error) {
                throw new DatabaseException($this->connection->connect_error, $this->connection->connect_errno);
            } else {
                $this->connection->set_charset($this->config->charset);
            }
        }catch(Exception|mysqli_sql_exception $e){
            throw $e;
        }
    }

    /**
     * Check if database is connected
     * 
     * @return bool 
    */
    public function isConnected(): bool 
    {
        return $this->connected;
    }

    /**
     * Returns the error information for the last statement execution.
     *
     * @return string The error information.
    */
    public function error(): string 
    {
        return $this->stmt->error ?? $this->connection->error;
    }

    /**
     * Returns the error information 
     *
     * @return array The error information.
    */
    public function errors(): array 
    {
        return [
            'statement' => [
                'errno' => $this->stmt->errno ?? null,
                'error' => $this->stmt->error ?? null
            ],
            'connection' => [
                'errno' => $this->connection->errno ?? null,
                'error' => $this->connection->error ?? null
            ]
        ];
    }

    /**
     * Returns the error information for the last statement execution.
     *
     * @return array $info The error information array.
    */
    public function info(): array 
    {
        preg_match_all('/(\S[^:]+): (\d+)/',  $this->connection->info, $matches); 
        $info = array_combine ($matches[1], $matches[2]);

        return $info;
    }

    /**
     * Dumps the debug information for the last statement execution.
     *
     * @return string $debug The debug information
     */
    public function dumpDebug(): string 
    {
        if (!$this->onDebug || $this->stmt === false) {
            return '';
        }

        $debug = '';
        try {
            if (method_exists($this->stmt, 'debugDumpParams')) {
                $debug = $this->stmt->debugDumpParams();
            }
        } catch (Exception $e) {
            $debug = 'Error during debugDumpParams: ' . $e->getMessage();
        }

        return $debug;
    }


    /**
     * Prepares a statement for execution.
     *
     * @param string $query The SQL query.
     *
     * @return self The current instance of the MySqlDriver class.
     */
    public function prepare(string $query): self 
    {
        $query = preg_replace('/:([a-zA-Z0-9_]+)/', '?', $query);
        $this->lastRowCount = 0;
        $this->stmt = $this->connection->prepare($query);
        $this->isSelect = (stripos($query, 'SELECT') === 0);

        return $this;
    }

    /**
     * Executes a query.
     *
     * @param string $query The SQL query.
     *
     * @return self The current instance of the MySqlDriver class.
    */
    public function query(string $query): self 
    {
        $this->lastRowCount = 0;
        $this->stmt = $this->connection->query($query);

        if ($this->stmt === false) {
            return $this;
        }

        if (stripos($query, 'SELECT') === 0) {
            $this->lastRowCount = $this->stmt->num_rows;
        } else {
            $this->lastRowCount = $this->connection->affected_rows;
        }

        return $this;
    }

    /**
     * Executes a query.
     *
     * @param string $query The SQL query.
     * @return int The affected row counts
     */
    public function exec(string $query): int 
    {
        $stmt = $this->query($query);

        return $stmt->lastRowCount;
    }

    /**
     * Begin transaction
     *
     * @return void 
    */
    public function beginTransaction(): void{
        $this->connection->begin_transaction();
    }

    /**
     * Commits transaction
     *
     * @return void 
    */
    public function commit(): void {
        $this->connection->commit();
        
    }

    /**
     * Rollback transaction if fails
     *
     * @return void
    */
    public function rollback(): void {
        $this->connection->rollback();
    }

    /**
     * Returns the appropriate parameter type based on the value and type.
     *
     * @param mixed       $value The parameter value.
     * @param null|int    $type  The parameter type.
     *
     * @return int The parameter type.
    */
    public function getType(mixed $value, ?int $type = null): mixed 
    {
        $type ??= match (true) {
            is_int($value) => 'i',
            is_float($value) => 'd',
            $this->isBlob($value) => 'b',
            default => 's',
        };

        return $type;
    }

    /**
     * Binds a value to a parameter.
     *
     * @param string       $param The parameter identifier.
     * @param mixed       $value The parameter value.
     * @param int|null    $type  The parameter type.
     *
     * @return self The current instance of the MySqlDriver class.
     */
    public function bind(string $param, mixed $value, mixed $type = null): self 
    {
        $this->queryParams[$param] = $value;

        return $this;
    }

    /**
     * Binds a variable to a parameter.
     *
     * @param string       $param The parameter identifier.
     * @param mixed       $value The parameter value.
     * @param int|null    $type  The parameter type.
     *
     * @return self The current instance of the MySqlDriver class.
     */
    public function param(string $param, mixed $value, mixed $type = null): self 
    {
        $this->stmt->bind_param($param, $value);
        
        return $this;
    }

    /**
     * Binds an array of values to the query parameters.
     *
     * @param array $values An associative array of parameter names and their corresponding values.
     * 
     * @return self The current instance of the MySqlDriver class.
     */
    public function bindValues(array $values): self 
    {
        foreach ($values as $key => $value) {
            $this->queryParams[$key] = $value;
        }

        return $this;
    }
  
    
    /**
     * Executes the prepared statement.
     * 
     * @param array $values execute statement with values
     * 
     * @throws DatabaseException 
     * @return void
    */
    public function execute(?array $values = null): void 
    {
        if(!$this->stmt){
            DatabaseException::throwException("Database operation error: Statement execution failed");
            return;
        }

        $values = $this->queryParams === [] ? $values ?? [] : $this->queryParams;

        try {
            if($values !== []){
                $types = '';
                $params = [];
                foreach ($values as $value) {
                    $types .= $this->getType($value);
                    $params[] = $value;
                }
        
                /*$bindParams = [$types];
                foreach ($params as &$value) {
                    $bindParams[] = &$value;
                }
                call_user_func_array([$this->stmt, 'bind_param'], $bindParams);*/

                array_unshift($params, $types);
                $this->stmt->bind_param(...$params);
            }
            $this->stmt->execute();

            if ($this->stmt->errno) {
                // Handle error in executing the statement
            }
        
            $this->lastRowCount = $this->isSelect ? $this->stmt->num_rows : $this->stmt->affected_rows;
        } catch (mysqli_sql_exception | TypeError $e) {
            DatabaseException::throwException($e->getMessage());
        }
        $this->queryParams = [];
    }

    /**
     * Is blob
     *
     * @param mixed $value
     * 
     * @return bool 
    */
    private function isBlob(mixed $value): bool 
    {
        return is_resource($value) && get_resource_type($value) === 'stream';
    }

    /**
     * Returns the number of rows affected by the last statement execution.
     *
     * @return int The number of rows.
    */
    public function rowCount(): int 
    {
        return $this->lastRowCount;
    }

    /**
     * Fetches a single row as an object.
     * 
     * @return array|object|null The result object or false if no row is found.
     */
    public function getOne(): mixed 
    {
        if(!$this->stmt){
            return null;
        }
        $result = $this->stmt->get_result();

        if ($result === false) {
            return null;
        }
        
        $row = $result->fetch_object();
        //$this->lastRowCount = $result->num_rows;
        $result->close();
        
        return $row;
    }
    
    /**
     * Fetches all rows as an array of objects.
     *
     * @return array|object|null The array of result objects.
     */
    public function getAll(): mixed 
    {
        if ($this->stmt instanceof mysqli_result) {
            return $this->getFromQueryResult($this->stmt);
        } else {
            $result = $this->stmt->get_result();
            return $this->getFromQueryResult($result);
        }
    }

    /**
     * Fetches all rows from a query result as an array of objects.
     *
     * @param mixed $queryResult The query result object.
     * 
     * @return array An array of objects representing the result rows.
     */
    private function getFromQueryResult(mixed $result): array 
    {
        $response = [];

        // Check if the query result is false, indicating an error
        if ($result === false) {
            return [];
        }

        // Fetch rows from the query result and add them to the response array
        while ($row = $result->fetch_object()) {
            $response[] = $row;
        }

        // Close the query result
        $result->close();

        // Update the last row count
       // $this->lastRowCount = count($response);

        return $response;
    }

    /**
     * Fetches all rows as an array or stdClass object.
     *
     * @param string $type The type of result to fetch ('object' or 'array').
     * 
     * @return array|stdClass The result containing the rows.
    */
    public function getResult(string $type = 'object'): array|stdClass
    {
        $result = ($type === 'object') ? new stdClass : [];

        if ($this->stmt instanceof mysqli_stmt) {
            // Handle prepared statement
            $meta = $this->stmt->result_metadata();
            $row = [];

            while ($field = $meta->fetch_field()) {
                if($type === 'object'){
                    $result->{$field->name} = null;
                    $row[] = &$result->{$field->name};
                }else{
                    $result[$field->name] = null;
                    $row[] = &$result[$field->name];
                }
            }

            $this->stmt->bind_result(...$row);

            $count = 0;
            while ($this->stmt->fetch()) {
                $count++;
                if($type === 'object'){
                    $result->{$count} = (object) $result->{$count};
                }else{
                    $result[$count] = (array) $result[$count];
                }
            }

            $meta->close();
        } elseif ($this->stmt instanceof mysqli_result) {
            $count = 0;

            while ($row = $this->stmt->fetch_assoc()) {
                $count++;
                if($type === 'object'){
                    $result->{$count} = (object) $row;
                }else{
                    $result[$count] = (array) $row;
                }
            }
        }

        return $result;
    }

    /**
     * Fetches all rows as a stdClass object.
     *
     * @return stdClass The stdClass object containing the result rows.
     */ 
    public function getObject(): stdClass 
    {
        return $this->getResult('object');
    }

    /**
     * Fetches all rows as a array.
     *
     * @return array The array containing the result rows.
    */
    public function getArray(): array 
    {
        return $this->getResult('array');;
    }


    /**
     * Fetches all rows as a 2D array of integers.
     *
     * @return int $total
     */
    public function getInt(): int 
    {
        $total = 0;

        if($this->stmt){
            if ($this->stmt instanceof mysqli_stmt) {
                $this->stmt->store_result();
                $this->stmt->bind_result($total);
                $this->stmt->fetch();
                $this->stmt->free_result();
            }elseif ($this->stmt instanceof mysqli_result) {
                $result = $this->stmt->fetch_row();
                $total = $result[0];
            }
        }

        return (int) $total;
    }  
    

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @return string The last insert ID.
     */
    public function getLastInsertId(): string 
    {
        return (string) $this->connection->insert_id;
    }

    /**
     * Frees up the statement cursor and sets the statement object to null.
     * 
     * @return void 
    */
    public function free(): void 
    {
        if($this->stmt === false){
            return;
        }

        if ($this->stmt instanceof mysqli_result) {
            $this->stmt->free();
        } elseif ($this->isStatement()) {
            $this->stmt->free_result();
        }
        

        $this->stmt = false;
    }
    

    /**
     * Is statement object
     * 
     * @return bool 
    */
    private function isStatement(): bool 
    {
        //return $this->stmt !== null && !is_bool($this->stmt);
        return $this->stmt instanceof mysqli_stmt;
    }

    /**
     * Frees up the statement cursor and close database connection
     * 
     * @return void 
    */
    public function close(): void {
        $this->free();
        $this->connection->close();
    }
}
