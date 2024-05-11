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

use \Luminova\Base\BaseDatabase;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Database\Conn\mysqliConn;
use \Luminova\Interface\ConnInterface;
use \mysqli_stmt;
use \mysqli_result;
use \stdClass;
use \mysqli_sql_exception;
use \TypeError;
use \Exception;
use mysqli;
use \ReflectionClass;
use \ReflectionException;

class MySqliDriver implements DatabaseInterface 
{
    /**
     * Mysqli Database connection instance
     * 
     * @var mysqliConn|null $connection 
    */
    private ?mysqliConn $connection = null; 

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
     * @var BaseDatabase|null $config Database configuration
    */
    private ?BaseDatabase $config = null;  

    /**
     * @var array $bindParams Database queries bind params
    */
    private array $bindParams = [];

     /**
     * @var array $bindValues Database queries bind values
    */
    private array $bindValues = [];

    /**
     * @var int $rowCount last row count
    */
    private int $rowCount = 0;

    /**
     * @var bool $isSelect is select query
    */
    private bool $isSelect = false;

    /**
     * @var bool $connected Connection status flag
    */
    private bool $connected = false;

    /**
     * Query executed successfully.
     * 
     * @var bool $executed
    */
    private bool $executed = false;

    /**
     * {@inheritdoc}
    */
    public function __construct(BaseDatabase $config) 
    {
        $this->config = $config;

        try{
            $this->newConnection();
            $this->connected = true;
        }catch(Exception|DatabaseException $e){
            $this->connected = false;
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }
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
            $socket = null;
            if (is_command() || NOVAKIT_ENV !== null || $this->config->socket) {
                $socket = (empty($this->config->socket_path) ? ini_get('mysqli.default_socket') : $this->config->socket_path);
            }
         
            $this->connection = new mysqliConn(
                $this->config->host,
                $this->config->username,
                $this->config->password,
                $this->config->database,
                $this->config->port,
                $socket
            );

            if ($this->connection->connect_error) {
                DatabaseException::throwException($this->connection->connect_error, $this->connection->connect_errno);
            }
            $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, $this->config->emulate_preparse);

            if($this->config->charset !== ''){
                $this->connection->set_charset($this->config->charset);
            }
        }catch(Exception|mysqli_sql_exception $e){
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
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
    public function raw(): ConnInterface|null 
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
    */
    public function statement(): mysqli_stmt|mysqli_result|bool|null
    {
        return $this->stmt;
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
    public function dumpDebug(): bool|null 
    {
        if (!$this->onDebug || $this->stmt === null || $this->stmt === false) {
            return false;
        }

        var_dump($this->stmt);

        return true;
    }

    /**
     * {@inheritdoc}
    */
    public function prepare(string $query): self 
    {
        $query = preg_replace('/:([a-zA-Z0-9_]+)/', '?', $query);
        $this->rowCount = 0;
        $this->stmt = $this->connection->prepare($query);
        $this->isSelect = (stripos($query, 'SELECT') === 0);

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function query(string $query): self 
    {
        $this->executed = false;
        $this->rowCount = 0;
        $this->stmt = $this->connection->query($query);

        if ($this->stmt !== null || $this->stmt !== false) {
            $this->executed = true;
            if (stripos($query, 'SELECT') === 0) {
                $this->rowCount = $this->stmt->num_rows;
            } else {
                $this->rowCount = $this->connection->affected_rows;
            }
        }

        //$this->rowCount = ($affected === 0) ? 1 : $affected;

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function exec(string $query): int 
    {
        $this->query($query);

        if ($this->stmt == null || $this->stmt === false) {
            return 0;
        }

        return $this->rowCount;
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
    public static function getType(mixed $value): string|int  
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
    public function bind(string $param, mixed $value, int|null $type = null): self 
    {
        $this->bindValues[$param] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function param(string $param, mixed &$value, int|null $type = null): self 
    {
        $this->bindParams[$param] = &$value;

        return $this;
    }

    /**
     * Parses the query parameters and binds them to the statement.
     * 
     * @param array<int, array> $values An array of parameter values.
     * @param string $type Type.
    */
    private function parseParams(array $values, $type = 'values'): void 
    {
        if($values === []){
            return;
        }

        $types = '';
        $params = [];
        if($type === 'values'){
            foreach ($values as $value) {
                $types .= static::getType($value);
                $params[] = $value;
            }
        }else{
            foreach ($values as &$value) {
                $types .= static::getType($value);
                $params[] = $value;
            }
        }

        array_unshift($params, $types);
        $this->stmt->bind_param(...$params);
        //call_user_func_array([$this->stmt, 'bind_param'], $params);
    }
  
    /**
     * {@inheritdoc}
    */
    public function execute(?array $params = null): bool 
    {
        $this->executed = false;
        if($this->stmt === null || $this->stmt === false){
            DatabaseException::throwException("Database operation error: Statement execution failed");
            return false;
        }

        $executed = false;
        try {
            $bindParams = ($this->bindParams ?: $this->bindValues);

            if(!empty($bindParams)){
                $params = null;
                $bindType = (empty($this->bindParams)) ? 'values' : 'params';

                $this->parseParams($bindParams, $bindType);
            }

            $executed = $this->stmt->execute($params);

            if (!$executed || $this->stmt->errno) {
                throw new DatabaseException($this->stmt->error, $this->stmt->errno);
            }
            $this->executed = true;
            $this->rowCount = $this->isSelect ? $this->stmt->num_rows : $this->stmt->affected_rows;
        } catch (mysqli_sql_exception | TypeError $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }
        
        $this->bindParams = [];
        $this->bindValues = [];

        return $executed;
    }

    /**
     * {@inheritdoc}
    */
    public function ok(): bool 
    {
        return $this->executed;
    }

    /**
     * {@inheritdoc}
    */
    public function rowCount(): int 
    {
        return $this->rowCount;
    }

    /**
     * {@inheritdoc}
    */
    public function getItem(int $mode = RETURN_ALL, string $return = 'object'): mixed 
    {
        return match ($mode) {
            RETURN_NEXT => $this->getNext($return),
            RETURN_2D_NUM => $this->getInt(),
            RETURN_INT => $this->getCount(),
            RETURN_ID => $this->getLastInsertId(),
            RETURN_COUNT => $this->rowCount(),
            RETURN_COLUMN => $this->getColumns(),
            RETURN_ALL => $this->getAll($return),
            default => false
        };
    }

    /**
     * {@inheritdoc}
    */
    public function getNext(string $type = 'object'): array|object|bool 
    {
        $result = $this->fetch('next', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if($result === false || $result === null){
            return false;
        }

        if($type === 'array'){
            return (array) $result;
        }

        return (object) $result;
    }

    /**
     * {@inheritdoc}
    */
    public function getAll(string $type = 'object'): array|object|bool 
    {
        $result = $this->fetch('all', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if($result === false || $result === null){
            return false;
        }

        if($type === 'array'){
            return (array) $result;
        }

        return (object) $result;
    }

    /**
     * {@inheritdoc}
    */
    public function getResult(string $type = 'object'): array|stdClass
    {
        $response = $this->fetch('all', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if ($response === null || $response === false) {
            return ($type === 'object') ? new stdClass : [];
        }

        return $response;
    }

    /**
     * {@inheritdoc}
    */
    public function getColumns(int $mode = FETCH_COLUMN): array 
    {
        $response = $this->fetch('all', $mode);

        if($response === null || $response === false){
            return [];
        }

        return $response;
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
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed
    {
        if ($this->stmt === null || $this->stmt === false) {
            return false;
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

        if ($this->stmt instanceof mysqli_stmt) {
            $this->stmt = $this->stmt->get_result();
        }

        if ($this->stmt === null || $this->stmt === false) {
            return false;
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

        $response = $msqliMode === null ? $this->stmt->$method() : $this->stmt->$method($msqliMode);
        //$this->stmt->close();

        if(empty($response) || $response === false){
            return false;
        }

        if($mode === FETCH_NUM_OBJ || $mode === FETCH_OBJ){
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
 
        return $response;
    }

    /**
     * {@inheritdoc}
    */ 
    public function fetchObject(string|null $class = "stdClass", array $arguments = []): object|false 
    {
        $response = $this->fetch('all', FETCH_ASSOC);
        $objects = [];

        if ($response === null || $response === false) {
            return false;
        }

        foreach ($response as $row) {
            $objects[] = (object) $row;
        }

        if ($class === null || $class === 'stdClass') {
            return $objects;
        }

        try {
            $reflection = new ReflectionClass($class);

            if ($reflection->isInstantiable()) {
                return $reflection->newInstanceArgs([$objects, ...$arguments]);
            }
        } catch (ReflectionException $e) {
            logger('error', $e->getMessage(), [
                'class' => $class
            ]);
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function getInt(): array|bool
    {
        $integers = $this->fetch('all', FETCH_NUM);

        if($integers === false || $integers === null){
            return false;
        }

        return $integers;
    }
    
    /**
     * {@inheritdoc}
    */
    public function getCount(): int|bool
    {
        $integers = $this->getInt();
        
        if($integers === false || $integers === []){
            return false;
        }

        if(isset($integers[0][0])) {
            return (int) $integers[0][0];
        }

        return (int) $integers ?? 0;
    }
    
    /**
     * {@inheritdoc}
    */
    public function getLastInsertId(): string 
    {
        return (string) $this->connection->insert_id;
    }

    /**
     * {@inheritdoc}
    */
    public function free(): void 
    {
        if($this->stmt === null || $this->stmt === false){
            return;
        }

        if ($this->stmt instanceof mysqli_result) {
            $this->stmt->free();
        } elseif($this->stmt instanceof mysqli_stmt) {
            $this->stmt->free_result();
        }

        //$this->stmt->close();
        
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