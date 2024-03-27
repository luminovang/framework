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
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Database\Drivers\DriversInterface;
use \mysqli;
use \mysqli_stmt;
use \mysqli_result;
use \stdClass;
use \mysqli_sql_exception;
use \TypeError;
use \Exception;

class MySqliDriver implements DriversInterface 
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
     * @var mysqli_stmt|mysqli_result|bool $stmt 
    */
    private mysqli_stmt|mysqli_result|bool $stmt = false;

    /**
     * @var bool $onDebug debug mode flag
    */
    private bool $onDebug = false;

    /**
     * @var null|Database $config Database configuration
    */
    private ?Database $config = null;  

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
     * @var bool $connected Connection status flag
    */
    private bool $connected = false;

    /**
     * {@inheritdoc}
    */
    public function __construct(Database $config) 
    {
        $this->config = $config;

        try{
            $this->newConnection();
            $this->connected = true;
        }catch(Exception|DatabaseException $e){
            $this->connected = false;
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * {@inheritdoc}
    */
    public static function getDriver(): string
    {
        return 'mysqli';
    }

    /**
     * {@inheritdoc}
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
            }
            $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, $this->config->emulate_preparse);

            if($this->config->charset !== ''){
                $this->connection->set_charset($this->config->charset);
            }
        }catch(Exception|mysqli_sql_exception $e){
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
    */
    public function isConnected(): bool 
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
    */
    public function error(): string 
    {
        return $this->stmt->error ?? $this->connection->error;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function info(): array 
    {
        preg_match_all('/(\S[^:]+): (\d+)/',  $this->connection->info, $matches); 
        $info = array_combine ($matches[1], $matches[2]);

        return $info;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function query(string $query): self 
    {
        $this->lastRowCount = 0;
        $this->stmt = $this->connection->query($query);

        if ($this->stmt === false) {
            return $this;
        }

        if (stripos($query, 'SELECT') === 0) {
            $affected = $this->stmt->num_rows;
        } else {
            $affected = $this->connection->affected_rows;
        }

        $this->lastRowCount = ($affected === 0) ? 1 : $affected;

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function exec(string $query): int 
    {
        return $this->query($query)->lastRowCount;
    }

    /**
     * {@inheritdoc}
    */
    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    /**
     * {@inheritdoc}
    */
    public function commit(): void 
    {
        $this->connection->commit();
        
    }

    /**
     * {@inheritdoc}
    */
    public function rollback(): void 
    {
        $this->connection->rollback();
    }

    /**
     * {@inheritdoc}
    */
    public function getType(mixed $value): string|int  
    {
       return match (true) {
            is_int($value) => 'i',
            is_float($value) => 'd',
            is_blob($value) => 'b',
            default => 's',
        };
    }

    /**
     * {@inheritdoc}
    */
    public function bind(string $param, mixed $value, mixed $type = null): self 
    {
        $this->queryParams[$param] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
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
     * @return self The current instance of the MySqliDriver class.
    */
    public function bindValues(array $values): self 
    {
        foreach ($values as $key => $value) {
            $this->queryParams[$key] = $value;
        }

        return $this;
    }
  
    /**
     * {@inheritdoc}
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

                array_unshift($params, $types);
                $this->stmt->bind_param(...$params);
            }
            $this->stmt->execute();

            if ($this->stmt->errno) {
                logger('emergency', 'Database query execution error', $this->errors());
            }
        
            $this->lastRowCount = $this->isSelect ? $this->stmt->num_rows : $this->stmt->affected_rows;
        } catch (mysqli_sql_exception | TypeError $e) {
            DatabaseException::throwException($e->getMessage());
        }
        
        $this->queryParams = [];
    }

    /**
     * {@inheritdoc}
    */
    public function rowCount(): int 
    {
        return $this->lastRowCount;
    }

    /**
     * {@inheritdoc}
    */
    public function getOne(): mixed 
    {
        return $this->fetch('next');
    }
    
    /**
     * {@inheritdoc}
    */
    public function getAll(): mixed 
    {
        return $this->fetch();
    }

    /**
     * {@inheritdoc}
    */
    public function getResult(string $type = 'object'): array|stdClass
    {
        $response = $this->fetch('all', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if ($response === null) {
            return ($type === 'object') ? new stdClass : [];
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */ 
    public function getObject(): stdClass 
    {
        return $this->getResult('object');
    }

    /**
     * {@inheritdoc}
    */
    public function getColumns(): mixed 
    {
        return null;
    }

    /**
     * {@inheritdoc}
    */
    public function getStatment(): \PDOStatement|mysqli_stmt|mysqli_result|bool|null
    {
        return $this->stmt;
    }

    /**
     * {@inheritdoc}
    */
    public function getArray(): array 
    {
        return $this->getResult('array');;
    }
    
    /**
     * {@inheritdoc}
    */
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed
    {
        if (!$this->stmt) {
            return null;
        }

        $modes = [
            FETCH_ASSOC => 'default',
            FETCH_BOTH => 'default',
            FETCH_OBJ => 'fetch_object', 
            FETCH_COLUMN => 'default',
            FETCH_COLUMN_ASSOC => 'default',
            FETCH_NUM => 'default',
            FETCH_NUM_OBJ => 'default',
            FETCH_ALL => 'fetch_all',
        ];

        if (!isset($modes[$mode])) {
            throw new DatabaseException("Unsupported fetch mode: $mode");
        }

        $msqliMode = null;

        if($type === 'next'){
            $method = ($mode === FETCH_OBJ) ? 'fetch_object' : 'fetch_assoc';
        }else{
            $method = 'fetch_all';
        }

        if($method === 'fetch_all'){
            $mapping = [
                FETCH_ASSOC => MYSQLI_ASSOC,
                FETCH_BOTH => MYSQLI_BOTH,
                FETCH_NUM => MYSQLI_NUM
            ];
            $msqliMode = $mapping[$mode] ?? MYSQLI_ASSOC;
        }

        if ($this->stmt instanceof mysqli_result) {
            $response = $msqliMode === null ? $this->stmt->$method() : $this->stmt->$method($msqliMode);

            $this->stmt->close();
        }else{
            $result = $this->stmt->get_result();

            if ($result === false) {
                return null;
            }
            
            $response = $msqliMode === null ? $result->$method() : $result->$method($msqliMode);

            $result->close();
        }

        if(empty($response)){
            return null;
        }

        if($type === 'all' && $mode === FETCH_OBJ){
            $json = json_encode($response);

            if($json === false){
                return (object) $response;
            }
            
            return (object) json_decode($json);
        }

        if($mode === FETCH_COLUMN){
            $columns = [];
            foreach ($response as $column) {
                if(is_array($column) || is_object($column)){
                    $columns[] = reset($column);
                }else{
                    $columns[] = $column;
                }
            }

            return $columns;
        }

        if($mode === FETCH_NUM_OBJ){
            $count = 0;
            $nums = new stdClass();
            foreach ($response as $row) {
                $count++;
                $nums->{$count} = (object) $row;
            }

            return $nums;
        }
 
        return $response;
    }

    /**
     * {@inheritdoc}
    */
    public function getInt(): int 
    {
        if(!$this->stmt){
            return 0;
        }

        $total = 0;

        if ($this->stmt instanceof mysqli_stmt) {
            $this->stmt->store_result();
            $this->stmt->bind_result($total);
            $this->stmt->fetch();
            $this->stmt->free_result();
        }elseif ($this->stmt instanceof mysqli_result) {
            $result = $this->stmt->fetch_row();
            $total = $result[0];
        }
    
        return (int) $total;
    }  
    
    /**
     * {@inheritdoc}
    */
    public function getLastInsertId(): string 
    {
        return (string) $this->connection->insert_id;
    }

    /**
     * Is statement object
     * 
     * @return bool 
    */
    private function isStatement(): bool 
    {
        return $this->stmt instanceof mysqli_stmt;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function close(): void 
    {
        $this->free();
        $this->connection->close();
        $this->connected = false;
    }
}