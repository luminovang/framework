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
use \Luminova\Exceptions\InvalidException;
use \PDO;
use \PDOStatement;
use \PDOException;
use \stdClass;

class PdoDriver implements DriversInterface 
{
    /**
     * PDO Database connection instance
    * @var PDO $connection 
    */
    private ?PDO $connection = null; 

    /**
     * Pdo statement object
    * @var PDOStatement $stmt
    */
    private ?PDOStatement $stmt = null;

    /**
    * @var bool $onDebug debug mode flag
    */
    private bool $onDebug = false;

    /**
    * @var Database $config Database configuration
    */
    private Database $config; 

    /**
    * @var int PARAM_INT Integer Parameter
    */
    public const PARAM_INT = PDO::PARAM_INT; 
    
    /**
    * @var bool PARAM_BOOL Boolean Parameter
    */
    public const PARAM_BOOL = PDO::PARAM_BOOL;

    /**
    * @var null PARAM_NULL Null Parameter
    */
    public const PARAM_NULL = PDO::PARAM_NULL;

    /**
    * @var string PARAM_STRING String Parameter
    */
    public const PARAM_STRING = PDO::PARAM_STR;

    /**
     * Constructor.
     *
     * @param Database $config database configuration. array
     * @throws InvalidException If a required configuration key is missing.
     */
    public function __construct(Database $config) 
    {
        if (!$config instanceof Database) {
            throw new InvalidException("Invalid database configuration, required type: Database, but " . gettype($config) . " is given instead.");
        }
      
        $this->config = $config;
        $this->initializeDatabase();
    }

    /**
     * Get driver name
     * 
     * @return string Database driver name
    */
    public function getDriver(): string
    {
        return 'pdo';
    }

    /**
     * Sets the debug mode.
     *
     * @param bool $debug The debug mode.
     * 
     * @return self The current class instance.
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
     * @return void 
     * @throws DatabaseException If no driver is specified
     */
    private function initializeDatabase(): void 
    {
        if ($this->connection !== null) {
            return;
        }

  
        // Define options for the PDO connection.
        $options = [
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT => $this->config->persistent, 
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ,
            //PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];


        if ($this->config->version === "mysql") {
            $this->createMySqlConnection($options);
        } elseif ($this->config->version === "pgsql") {
            $this->createPostgreSQLConnection($options);
        } elseif ($this->config->version === "sqlite" && !empty($this->config->sqlite_path)) {
            $this->createSQLiteConnection($options);
        } else {
            DatabaseException::throwException("No database driver found for version '{$this->config->version}'"); 
        }
    }

    /**
     * Create a MySQL database connection.
     *
     * @param array $options An array of PDO options.
     * @return void
     */
    private function createMySqlConnection(array $options): void {
        $connectionDsn = "mysql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database}";
        try {
            $this->connection = new PDO($connectionDsn, $this->config->username, $this->config->password, $options);
        } catch (PDOException $e) {
            DatabaseException::throwException($e->getMessage());
        }
    }

    /**
     * Create a PostgreSQL database connection.
     *
     * @param array $options An array of PDO options.
     * @return void
     */
    private function createPostgreSQLConnection(array $options): void {
        $dns = "pgsql:host={$this->config->host} port={$this->config->port} dbname={$this->config->database}";
        $dns .= " user={$this->config->username} password={$this->config->password}";
        try {
            $this->connection = new PDO($dns, null, null, $options);
        } catch (PDOException $e) {
            DatabaseException::throwException($e->getMessage());
        }
    }

    /**
     * Create an SQLite database connection.
     *
     * @param array $options An array of PDO options.
     * @return void
     */
    private function createSQLiteConnection(array $options): void {
        try {
            $this->connection = new PDO("sqlite:/" . $this->config->sqlite_path, null, null, $options);
        } catch (PDOException $e) {
            DatabaseException::throwException($e->getMessage());
        }
    }


    /**
     * Returns the error information for the last statement execution.
     *
     * @return string The error information.
     */
    public function error(): string 
    {
        return $this->stmt?->errorInfo()[2] ?? $this->connection?->errorInfo()[2];
    }

    /**
     * Returns the error information.
     *
     * @return array The error information.
     */
    public function errors(): array 
    {
        return [
            'statement' => [
                'errno' => $this->stmt?->errorCode() ?? null,
                'error' => $this->stmt?->errorInfo()[2] ?? null
            ],
            'connection' => [
                'errno' => $this->connection?->errorCode() ?? null,
                'error' => $this->connection?->errorInfo()[2] ?? null
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
        // Get driver-specific information
        $driverInfo = $this->connection?->getAttribute(PDO::ATTR_CONNECTION_STATUS);

        // Parse the information into an associative array
        preg_match_all('/(\S[^:]+): (\S+)/', $driverInfo, $matches);
        $info = array_combine($matches[1], $matches[2]);

        return $info;
    }


    /**
     * Dumps the debug information for the last statement execution.
     *
     * @return string|null The debug information or null if debug mode is off.
     */
    public function dumpDebug(): mixed 
    {
        return $this->onDebug ? $this->stmt->debugDumpParams() : null;
    }

    /**
     * Prepares a statement for execution.
     *
     * @param string $query The SQL query.
     * 
     * @return self The current class instance.
     */
    public function prepare(string $query): self 
    {
        $this->stmt = $this->connection->prepare($query);

        return $this;
    }

    /**
     * Executes a query.
     *
     * @param string $query The SQL query.
     * 
     * @return self The current class instance.
     */
    public function query(string $query): self
    {
        $this->stmt = $this->connection->query($query);

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
        $result = $this->connection->exec($query);

        if($result === false){
            return 0;
        }

        return $result;
    }

    /**
     * Begin transaction
     *
     * @return void 
     */
    public function beginTransaction(): void{
        $this->connection->beginTransaction();
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
        $this->connection->rollBack();
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
            is_int($value) => self::PARAM_INT,
            is_bool($value) => self::PARAM_BOOL,
            is_null($value) => self::PARAM_NULL,
            default => self::PARAM_STRING,
        };

        return $type;
    }


    /**
     * Binds a value to a parameter.
     *
     * @param string       $param The parameter identifier.
     * @param mixed       $value The parameter value.
     * @param null|int    $type  The parameter type.
     *
     * @return self The current class instance.
     */
    public function bind(string $param, mixed $value, mixed $type = null): self 
    {
        $this->stmt->bindValue($param, $value, $this->getType($value, $type));
        return $this;
    }

    /**
     * Binds a variable to a parameter.
     *
     * @param string       $param The parameter identifier.
     * @param mixed       $value The parameter value.
     * @param null|int    $type  The parameter type.
     *
     * @return self The current class instance.
     */
    public function param(string $param, mixed $value, mixed $type = null): self 
    {
        $this->stmt->bindParam($param, $value, $this->getType($value, $type));
        return $this;
    }

    /**
     * Executes the prepared statement.
     * @param array $values execute statement with values
     * @throws DatabaseException 
     * 
     * @return void
    */
    public function execute(?array $values = null): void 
    {
        try {
            $this->stmt->execute($values);
        } catch (PDOException $e) {
            DatabaseException::throwException($e->getMessage());
        }
    }

    /**
     * Returns the number of rows affected by the last statement execution.
     *
     * @return int The number of rows.
     */
    public function rowCount(): int 
    {
        return $this->stmt->rowCount();
    }

    /**
     * Fetches a single row as an object.
     *
     * @return mixed The result object or false if no row is found.
     */
    public function getOne(): mixed 
    {
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Fetches all rows as an array of objects.
     *
     * @return mixed The array of result objects.
     */
    public function getAll(): mixed 
    {
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Fetches all rows as a 2D array of integers.
     *
     * @return array The 2D array of integers.
     */
    public function getInt(): int 
    {
        $response = $this->stmt->fetchAll(PDO::FETCH_NUM);
        if (isset($response[0][0])) {
            return (int) $response[0][0];
        }
        return $response??0;
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

        $count = 0;

        while ($row = $this->stmt->fetchObject()) {
            $count++;
            if($type === 'object'){
                $result->$count = (object) $row;
            }else{
                $result[$count] = (array) $row;
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
     * Returns the ID of the last inserted row or sequence value.
     *
     * @return string The last insert ID.
     */
    public function getLastInsertId(): string 
    {
        return (string) $this->connection->lastInsertId();
    }

    /**
     * Frees up the statement cursor and sets the statement object to null.
     * 
     * @return void
    */
    public function free(): void 
    {
        if ($this->stmt !== null) {
            $this->stmt->closeCursor();
            $this->stmt = null;
        }
    }

    /**
     * Frees up the statement cursor and close database connection
     * 
     * @return void
    */
    public function close(): void 
    {
        $this->free();
        $this->connection = null;
    }
}
