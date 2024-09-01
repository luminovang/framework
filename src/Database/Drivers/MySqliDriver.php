<?php
/**
 * Luminova Framework mysqli database driver extension.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Database\Drivers;

use \Luminova\Core\CoreDatabase;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\ConnInterface;
use \mysqli;
use \mysqli_stmt;
use \mysqli_result;
use \mysqli_sql_exception;
use \TypeError;
use \Exception;
use \ReflectionClass;
use \ReflectionException;

final class MySqliDriver implements DatabaseInterface 
{
    /**
     * Mysqli Database connection instance.
     * 
     * @var mysqli|null $connection 
    */
    private ?mysqli $connection = null; 

    /**
     * mysqli statement, result object or false.
     * 
     * @var mysqli_stmt|mysqli_result|bool $stmt 
    */
    private mysqli_stmt|mysqli_result|bool $stmt = false;

    /**
     * Debug mode flag.
     * 
     * @var bool $onDebug
    */
    private bool $onDebug = false;

    /**
     * Database configuration.
     * 
     * @var CoreDatabase|null $config
    */
    private ?CoreDatabase $config = null;  

    /**
     * Database queries bind params.
     * 
     * @var array $bindParams
    */
    private array $bindParams = [];

    /**
     * Database queries bind values.
     * 
     * @var array $bindValues
    */
    private array $bindValues = [];

    /**
     * Last row count. 
     * 
     * @var int $rowCount
    */
    private int $rowCount = 0;

    /**
     * Is select query.
     * 
     * @var bool $isSelect
    */
    private bool $isSelect = false;

    /**
     * Connection status flag.
     * 
     * @var bool $connected
    */
    private bool $connected = false;

    /**
     * Query executed successfully.
     * 
     * @var bool $executed
    */
    private bool $executed = false;

    /**
     * Active transaction.
     * 
     * @var bool $inTransaction
    */
    private bool $inTransaction = false;

    /**
     * Show Query Execution profiling.
     * 
     * @var bool $showProfiling
    */
    private static bool $showProfiling = false;

    /**
     * Total Query Execution time.
     * 
     * @var float|int $queryTime
    */
    protected float|int $queryTime = 0;

    /**
     * Last Query Execution time.
     * 
     * @var float|int $lastQueryTime
    */
    protected float|int $lastQueryTime = 0;

    /**
     * Start Execution time.
     * 
     * @var float|int $startTime
    */
    private static float|int $startTime = 0;

    /**
     * {@inheritdoc}
    */
    public function __construct(CoreDatabase $config) 
    {
        $this->config = $config;

        try{
            $this->newConnection();
            $this->connected = true;
        }catch(Exception|DatabaseException $e){
            $this->connected = false;
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        self::$showProfiling = ($this->connected && !PRODUCTION && env('debug.show.performance.profiling', false));
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
     * {@inheritdoc}
    */
    public function getDriver(): ?string 
    {
        if($this->connection === null){
            return null;
        }

        return 'mysqli';
    }

    /**
     * Initializes the database connection.
     * This method is called internally and should not be called directly.
     * 
     * @throws DatabaseException If no driver is specified.
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
                $socket = (($this->config->socket_path === '' || $this->config->socket_path === null) ? ini_get('mysqli.default_socket') : $this->config->socket_path);
            }
         
            $this->connection = new mysqli(
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
            $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, (int) $this->config->emulate_preparse);

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
    public function raw(): ConnInterface 
    {
        return new class($this->connection) implements ConnInterface 
        {
            /**
             * @var ?mysqli $conn
            */
            private ?mysqli $conn = null;

            /**
             * {@inheritdoc}
            */
            public function __construct(?mysqli $conn = null){
                $this->conn = $conn;
            }
            
            /**
             * {@inheritdoc}
            */
            public function close(): void {$this->conn = null;}

            /**
             * {@inheritdoc}
            */
            public function getConn(): ?mysqli {return $this->conn;}
        };
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
        $info = array_combine($matches[1], $matches[2]);

        return $info;
    }

    /**
     * {@inheritdoc}
    */
    public function dumpDebug(): bool 
    {
        if (!$this->onDebug || $this->stmt === null || $this->stmt === false) {
            return false;
        }

        var_dump($this->stmt);
        return true;
    }

    /**
     * Profiles the execution time of a database queries.
     *
     * @param bool $start Indicates whether to start or stop profiling.
     * 
     * @return void
     */
    private function profiling(bool $start = true): void
    {
        if(self::$showProfiling){
         
            if ($start) {
                self::$startTime = microtime(true);
                return;
            }

            $end = microtime(true);
            $this->lastQueryTime = ($end - self::$startTime);
            $this->queryTime += $this->lastQueryTime;

            // Store it in a shared memory to retrieve later when needed.
            shared('__DB_QUERY_EXECUTION_TIME__', $this->queryTime);
            self::$startTime = 0;
        }
    }

    /**
     * {@inheritdoc}
    */
    public function getQueryTime(): float|int 
    {
        return $this->queryTime;
    }

    /**
     * {@inheritdoc}
    */
    public function getLastQueryTime(): float|int 
    {
        return $this->lastQueryTime;
    }

    /**
     * {@inheritdoc}
    */
    public function prepare(string $query): self 
    {
        $this->profiling(true);
        $query = preg_replace('/:([a-zA-Z0-9_]+)/', '?', $query);
        $this->rowCount = 0;
        $this->stmt = $this->connection->prepare($query);
        $this->isSelect = str_starts_with($query, 'SELECT');
        $this->profiling(false);

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function query(string $query): self 
    {
        $this->profiling(true);
        $this->executed = false;
        $this->rowCount = 0;
        $this->stmt = $this->connection->query($query);

        if ($this->stmt !== null || $this->stmt !== false) {
            $this->executed = true;
            $this->rowCount = str_starts_with($query, 'SELECT') ? $this->stmt->num_rows : $this->connection->affected_rows;
        }
        $this->profiling(false);
        
        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function exec(string $query): int 
    {
        $this->profiling(true);
        $this->query($query);
        $this->profiling(false);

        if ($this->stmt == null || $this->stmt === false) {
            return 0;
        }

        return $this->rowCount;
    }

    /**
     * {@inheritdoc}
    */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        if($this->connection->begin_transaction($flags, $name)){
            $this->inTransaction = true;
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        if($this->connection->commit($flags, $name)){
            $this->inTransaction = false;
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        if($this->connection->rollback($flags, $name)){
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function inTransaction(): bool 
    {
        return $this->inTransaction;
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
    }
  
    /**
     * {@inheritdoc}
    */
    public function execute(?array $params = null): bool 
    {
        $this->executed = false;
        if($this->stmt === null || $this->stmt === false){
            DatabaseException::throwException(
                'Database execution error, no statment to execute.', 
                DatabaseException::NO_STATEMENT_TO_EXECUTE
            );
            return false;
        }

        $executed = false;
        try {
            $bindParams = ($this->bindParams ?: $this->bindValues);

            if($bindParams !== []){
                $params = null;
                $bindType = ($this->bindParams === []) ? 'values' : 'params';

                $this->parseParams($bindParams, $bindType);
            }

            $executed = $this->stmt->execute($params);

            if (!$executed || $this->stmt->errno) {
                throw new DatabaseException($this->stmt->error, $this->stmt->errno);
            }
            $this->executed = true;
            $this->rowCount = $this->isSelect ? $this->stmt->num_rows : $this->stmt->affected_rows;
        } catch (mysqli_sql_exception|TypeError $e) {
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
    public function getResult(int $mode = RETURN_ALL, string $fetch = 'object'): mixed 
    {
        return match ($mode) {
            RETURN_NEXT => $this->getNext($fetch),
            RETURN_2D_NUM => $this->getInt(),
            RETURN_INT => $this->getCount(),
            RETURN_ID => $this->getLastInsertId(),
            RETURN_COUNT => $this->rowCount(),
            RETURN_COLUMN => $this->getColumns(),
            RETURN_ALL => $this->getAll($fetch),
            RETURN_STMT => $this->stmt,
            default => false
        };
    }

    /**
     * {@inheritdoc}
    */
    public function getNext(string $fetch = 'object'): array|object|bool 
    {
        $result = $this->fetch('next', ($fetch === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if($result === false || $result === null){
            return false;
        }

        if($fetch === 'array'){
            return (array) $result;
        }

        return (object) $result;
    }

    /**
     * {@inheritdoc}
    */
    public function getAll(string $fetch = 'object'): array|object|bool 
    {
        $result = $this->fetch('all', ($fetch === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if($result === false || $result === null){
            return false;
        }

        if($fetch === 'array'){
            return (array) $result;
        }

        return (object) $result;
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
    public function getStatement(): mysqli_stmt|mysqli_result|bool|null
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
            throw new DatabaseException(
                sprintf('Unsupported databse fetch mode: %d', $mode),
                DatabaseException::NOT_SUPPORTED
            );
        }

        if ($this->stmt instanceof mysqli_stmt) {
            $this->stmt = $this->stmt->get_result();
        }

        if ($this->stmt === null || $this->stmt === false) {
            return false;
        }

        $msqliMode = null;
        $method = (($type === 'next') ? (($mode === FETCH_OBJ) ? 'fetch_object' : 'fetch_assoc') : 'fetch_all');

        if($method === 'fetch_all'){
            $mapping = [
                FETCH_ASSOC => MYSQLI_ASSOC,
                FETCH_BOTH => MYSQLI_BOTH,
                FETCH_NUM => MYSQLI_NUM
            ];
            $msqliMode = $mapping[$mode] ?? MYSQLI_ASSOC;
        }

        $response = $msqliMode === null ? $this->stmt->$method() : $this->stmt->$method($msqliMode);

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
                $columns[] = (is_array($column) || is_object($column)) ? reset($column) : $column;
            }

            return $columns;
        }
 
        return $response;
    }

    /**
     * {@inheritdoc}
    */ 
    public function fetchObject(string|null $class = 'stdClass', mixed ...$arguments): object|bool 
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
            return (object) $objects;
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
    public function getLastInsertId(?string $name = null): string|int|null|bool
    {
        return $this->connection->insert_id;
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