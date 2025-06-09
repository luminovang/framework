<?php
/**
 * Luminova Framework mysqli database driver extension.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Drivers;

use JsonException;
use \Luminova\Core\CoreDatabase;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\ConnInterface;
use \Luminova\Logger\Logger;
use \mysqli;
use \mysqli_stmt;
use \mysqli_result;
use \TypeError;
use \Throwable;
use \ReflectionClass;
use \ReflectionException;

final class MysqliDriver implements DatabaseInterface 
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
     * Whether queries is bind params or values.
     * 
     * @var bool $isParams
     */
    private bool $isParams = false;

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
     * Result fetch modes.
     * 
     * @var array<int,string> $fetchModes
     */
    private static array $fetchModes = [
        FETCH_ASSOC     => 'default',
        FETCH_BOTH      => 'default',
        FETCH_OBJ       => 'fetch_object', 
        FETCH_COLUMN    => 'default',
        FETCH_COLUMN_ASSOC => 'default',
        FETCH_NUM       => 'default',
        FETCH_NUM_OBJ   => 'default',
        FETCH_ALL       => 'fetch_all',
        'mysqli'        => [
            FETCH_ASSOC => MYSQLI_ASSOC,
            FETCH_BOTH => MYSQLI_BOTH,
            FETCH_NUM => MYSQLI_NUM
        ]
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct(CoreDatabase $config) 
    {
        $this->config = $config;

        try{
            $this->newConnection();
            $this->connected = true;
        }catch(Throwable $e){
            if($e instanceof DatabaseException){
                throw $e;
            }

            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }

        self::$showProfiling = ($this->isConnected() && !PRODUCTION && env('debug.show.performance.profiling', false));
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
        return $this->isConnected() ? 'mysqli' : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(string $property): mixed
    {
        $property = strtolower($property);

        if(
            $property === 'username' || 
            $property === 'password' || 
            $property === 'port' || 
            $property === 'host'
        ){
            return null;
        }

        return $this->config->getValue($property);
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool 
    {
        return ($this->connected && $this->connection instanceof mysqli);
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
        return $this->isStatement() ? $this->stmt->error : $this->connection->error;
    }

    /**
     * {@inheritdoc}
     */
    public function errors(): array 
    {
        return [
            'statement' => [
                'errno' => $this->isStatement() ? $this->stmt->errno : -1,
                'error' => $this->isStatement() ? $this->stmt->error : null
            ],
            'connection' => [
                'errno' => $this->isConnected() ? $this->connection->errno : -1,
                'error' => $this->isConnected() ? $this->connection->error : 'Connection not established'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function info(): array 
    {
        if(!$this->isConnected()){
            return ['status' => 'disconnected'];
        }

        if(!$this->connection->info){
            return ['status' => 'idle'];
        }

        preg_match_all('/(\S[^:]+): (\d+)/',  $this->connection->info, $matches); 
        return array_combine($matches[1], $matches[2]) ?: [];
    }

    /**
     * {@inheritdoc}
     */
    public function dumpDebug(): bool 
    {
        if (!$this->onDebug || is_bool($this->stmt)) {
            return false;
        }

        var_dump($this->stmt);
        return true;
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
        $this->assertConnection();
        $this->profiling(true);
        $this->executed = false;
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
        $this->assertConnection();
        $this->profiling(true);
        $this->executed = false;
        $this->rowCount = 0;
        $this->stmt = $this->connection->query($query);

        if ($this->stmt) {
            $this->executed = true;
            $this->rowCount = (int) ($this->stmt instanceof mysqli_result) 
                    ? (str_starts_with($query, 'SELECT') 
                        ? $this->stmt->num_rows 
                        : $this->connection->affected_rows
                      )
                    : $this->connection->affected_rows;
        }

        $this->profiling(false);
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $query): int 
    {
        $this->query($query);
        if(!$this->executed || $this->stmt === false){
            return 0;
        }

        return ($this->rowCount === 0 && CoreDatabase::isDDLQuery($query)) ? 1 : $this->rowCount;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        $this->assertConnection();
        $this->profiling(true);
        if($this->connection->begin_transaction($flags, $name)){
            $this->inTransaction = true;
            return true;
        }

        $this->profiling(false, true);
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();
        if($this->connection->commit($flags, $name)){
            $this->profiling(false, true);
            $this->inTransaction = false;
            return true;
        }

        $this->profiling(false, true);
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();
        if($this->connection->rollback($flags, $name)){
            $this->profiling(false, true);
            $this->inTransaction = false;
            return true;
        }

        $this->profiling(false, true);
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
    public function bind(string $param, mixed $value, ?int $type = null): self 
    {
        $this->assertStatement();
        $this->bindValues[$param] = $value;
        $this->isParams = false;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function value(string $param, mixed $value, ?int $type = null): self 
    {
        return $this->bind($param, $value, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function param(string $param, mixed &$value, ?int $type = null): self 
    {
        $this->assertStatement();
        $this->bindValues[$param] = &$value;
        $this->isParams = true;

        return $this;
    }
  
    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): bool 
    {
        if($this->executed){
            return false;
        }
        
        $this->assertStatement();

        try {
            $params = $this->parseParams() ? null : $params;
            $this->executed = $this->stmt->execute($params);

            if (!$this->executed || $this->stmt->errno) {
                throw new DatabaseException($this->stmt->error, $this->stmt->errno);
            }

            $this->rowCount = (int) ($this->isSelect ? $this->stmt->num_rows : $this->stmt->affected_rows);
        } catch (Throwable|TypeError $e) {
            if($e instanceof DatabaseException){
                throw $e;
            }

            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }
        
        $this->isParams = false;
        $this->bindValues = [];

        return $this->executed;
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
            RETURN_NEXT => $this->fetchNext($fetch),
            RETURN_2D_NUM => $this->getInt(),
            RETURN_INT => $this->getCount(),
            RETURN_ID => $this->getLastInsertId(),
            RETURN_COUNT => $this->rowCount(),
            RETURN_COLUMN => $this->getColumns(),
            RETURN_ALL => $this->fetchAll($fetch),
            RETURN_STMT => $this->getStatement(),
            RETURN_RESULT => ($this->stmt instanceof mysqli_result) ? $this->stmt : null,
            default => false
        };
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNext(string $type = 'object'): array|object|bool 
    {
        $result = $this->fetch('next', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if($result === false || $result === null){
            return false;
        }

        return ($type === 'array') 
            ? (array) $result 
            : (object) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getNext(string $type = 'object'): array|object|bool 
    {
        return $this->fetchNext($type);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(string $type = 'object'): array|object|bool 
    {
        $result = $this->fetch('all', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if($result === false || $result === null){
            return false;
        }

        return ($type === 'array') 
            ? (array) $result 
            : (object) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(string $type = 'object'): array|object|bool 
    {
        return $this->fetchAll($type);
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
    public function getStatement(): ?mysqli_stmt
    {
        return ($this->stmt instanceof mysqli_stmt) ? $this->stmt : null;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed
    {
        if ($this->stmt === true) {
            return null;
        }

        $this->assertStatement(true);
        $fetchMode = self::$fetchModes[$mode] ?? null;

        if ($fetchMode === null) {
            throw new DatabaseException(
                sprintf('Unsupported database fetch mode: %d. Use FETCH_*', $mode),
                DatabaseException::NOT_SUPPORTED
            );
        }

        if ($this->isStatement()) {
            $this->stmt = $this->stmt->get_result();
        }

        if (!$this->stmt instanceof mysqli_result) {
            return false;
        }

        $method = ($type === 'next' || $type === 'stream') 
            ? (($mode === FETCH_OBJ) ? 'fetch_object' : 'fetch_assoc') 
            : 'fetch_all';

        $fetchMode = ($method === 'fetch_all') 
            ? (self::$fetchModes['mysqli'][$mode] ?? MYSQLI_ASSOC)
            : null;
  
        $response = ($fetchMode === null) 
            ? $this->stmt->{$method}() 
            : $this->stmt->{$method}($fetchMode);

        if($response === false || empty($response)){
            return false;
        }

        if(($mode === FETCH_NUM_OBJ || $mode === FETCH_OBJ) && !is_object($response)){
            try{
                $toObject = json_encode($response, JSON_THROW_ON_ERROR);
                return ($toObject === false) 
                    ? (object) $response 
                    : (object) json_decode($toObject);
            }catch(JsonException){
                return (object) $response;
            }
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
            Logger::dispatch('error', $e->getMessage(), [
                'class' => $class
            ]);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getInt(): array
    {
        $integers = $this->fetch('all', FETCH_NUM);

        if(!$integers){
            return [];
        }

        return $integers;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCount(): int
    {
        $integers = $this->getInt();

        if (!$integers || $integers === []) {
            return 0;
        }

        $integers = $integers[0] ?? null;

        return ($integers && is_array($integers)) 
            ? (int) ($integers[0] ?? 0) 
            : (int) $integers;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLastInsertId(?string $name = null): string|int|null|bool
    {
        return $this->isConnected() ? $this->connection->insert_id : false;
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
        $this->connected = !$this->connection->close();
    }

    /**
     * {@inheritdoc}
     */
    public function profiling(bool $start = true, bool $finishedTransaction = false): void
    {
        if(!self::$showProfiling || (!$start && $this->inTransaction && !$finishedTransaction)){
            return;
        }
         
        if ($start) {
            self::$startTime = microtime(true);
            return;
        }

        $end = microtime(true);
        $this->lastQueryTime = abs($end - self::$startTime);
        $this->queryTime += ($this->lastQueryTime * 1_000);

        shared('__DB_QUERY_EXECUTION_TIME__', $this->queryTime);
        self::$startTime = 0;
    }

    /**
     * Determine Whether the executed query returned prepared statement object.
     * 
     * @return bool Return true if is a prepared statement.
     */
    private function isStatement(): bool 
    {
        return ($this->stmt instanceof mysqli_stmt);
    }

    /**
     * Ensures that a database connection is established before proceeding.
     * 
     * @throws DatabaseException If the database connection is not active.
     */
    private function assertConnection(): void 
    {
        if (!$this->isConnected()) {
            throw new DatabaseException(
                'No active database connection found. Connect before executing queries.',
                DatabaseException::CONNECTION_DENIED
            );
        } 
    }

    /**
     * Ensures that a valid SQL statement is available before execution.
     * 
     * @param bool $assertResult If true, also checks for a valid mysqli_result.
     * 
     * @throws DatabaseException If no valid SQL statement or result set exists.
     */
    private function assertStatement(bool $assertResult = false): void 
    {
        $noResult = $assertResult && (!$this->stmt instanceof mysqli_result);

        if (!$this->isStatement() || $noResult) {
            throw new DatabaseException(
                sprintf(
                    'No valid SQL statement%s set found. Ensure a query is prepared or available.',
                    $assertResult ? ' or result' : ''
                ),
                DatabaseException::NO_STATEMENT_TO_EXECUTE
            );
        }
    }

    /**
     * Parses the query parameters and binds them to the statement.
     * 
     * @return bool 
     */
    private function parseParams(): bool 
    {
        if($this->bindValues === []){
            return false;
        }

        $type = $this->isParams ? 'params' : 'values';
        $types = '';
        $params = [];
        
        if($type === 'values'){
            foreach ($this->bindValues as $value) {
                $types .= self::getType($value);
                $params[] = $value;
            }
        }else{
            foreach ($this->bindValues as &$value) {
                $types .= self::getType($value);
                $params[] = $value;
            }
        }

        array_unshift($params, $types);
        $this->stmt->bind_param(...$params);
        return true;
    }

    /**
     * Initializes the database connection.
     * This method is called internally and should not be called directly.
     * 
     * @throws DatabaseException Throws if no driver is specified.
     */
    private function newConnection(): void 
    {
        if ($this->connection instanceof mysqli) {
            return;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $socketPath = null;
        if (is_command() || NOVAKIT_ENV !== null || $this->config->getValue('socket')) {
            $socketPath = $this->config->getValue('socket_path');
            $socketPath = empty($socketPath) ? ini_get('mysqli.default_socket') : $socketPath;
        }
        
        $this->connection = new mysqli(
            $this->config->getValue('host'),
            $this->config->getValue('username'),
            $this->config->getValue('password'),
            $this->config->getValue('database'),
            $this->config->getValue('port'),
            $socketPath
        );

        if ($this->connection->connect_error) {
            throw new DatabaseException(
                $this->connection->connect_error, 
                $this->connection->connect_errno
            );
        }

        $this->connection->options(
            MYSQLI_OPT_INT_AND_FLOAT_NATIVE, (int) $this->config->getValue('emulate_preparse')
        );

        $charset = $this->config->getValue('charset');

        if($charset){
            $this->connection->set_charset($charset);
        }
    }
}