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
    * @var bool $connected 
    */
    private bool $connected = false;

    /**
    * @var Database $config Database configuration
    */
    private Database $config; 

    /**
    * @var int PARAM_INT Integer Parameter
    */
    public const PARAM_INT = 1; 
    
    /**
    * @var int PARAM_BOOL Boolean Parameter
    */
    public const PARAM_BOOL = 5;

    /**
    * @var int PARAM_NULL Null Parameter
    */
    public const PARAM_NULL = 0;

    /**
    * @var int PARAM_STRING String Parameter
    */
    public const PARAM_STRING = 2;

    /**
     * Constructor.
     *
     * @param Database $config database configuration. array
     * @throws InvalidArgumentException If a required configuration key is missing.
     * @throws PDOException
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
        }catch(PDOException|DatabaseException $e){
            $this->connected = false;
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
     * @throws PDOException
    */
    private function newConnection(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $options = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => $this->config->persistent,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        $driver = strtolower($this->config->pdo_driver);
        $dns = $this->getConnectionDriver($driver);

        if ($dns === '' || ($driver === "sqlite" && empty($this->config->sqlite_path))) {
            throw new DatabaseException("No PDO database driver found for: '{$driver}'");
        }

        $username = $password = null;

        if (!in_array($driver, ['pgsql', 'sqlite'], true)) {
            $username = $this->config->username;
            $password = $this->config->password;
        }

        $this->connection = new PDO($dns, $username, $password, $options);
    }

    /**
     * Get driver dns connection
     *
     * @param string $name Driver name 
     * 
     * @return string
    */
    private function getConnectionDriver(string $name): string
    {
        $drivers = [
            'cubrid' => "cubrid:dbname={$this->config->database};host={$this->config->host};port={$this->config->port}",
            'dblib' => "dblib:host={$this->config->host};dbname={$this->config->database};port={$this->config->port}",
            'oci' => "oci:dbname={$this->config->database}",
            'pgsql' => "pgsql:host={$this->config->host} port={$this->config->port} dbname={$this->config->database} user={$this->config->username} password={$this->config->password}",
            'sqlite' => "sqlite:/{$this->config->sqlite_path}",
            'mysql' => "mysql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database}"
        ];

        return $drivers[$name] ?? '';
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
        $driverInfo = $this->connection?->getAttribute(PDO::ATTR_CONNECTION_STATUS);

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
    public function commit(): void 
    {
        $this->connection->commit();
        
    }

    /**
     * Rollback transaction if fails
     *
     * @return void
     */
    public function rollback(): void 
    {
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
