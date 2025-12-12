<?php
/**
 * Luminova Framework PDO database driver extension.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Driver;

use \PDO;
use \stdClass;
use \Throwable;
use \PDOException;
use \PDOStatement;
use \Luminova\Boot;
use \Luminova\Luminova;
use \Luminova\Foundation\Core\Database;
use \Luminova\Exceptions\{ErrorCode, DatabaseException};
use \Luminova\Interface\{ConnInterface, DatabaseInterface};

final class PdoDatabase implements DatabaseInterface 
{
    /**
     * Shared database object.
     * 
     * @var DatabaseInterface|null $instance
     */
    private static ?DatabaseInterface $instance = null;

    /**
     * PDO Database connection instance.
     * 
     * @var PDO $connection 
     */
    private ?PDO $connection = null; 

    /**
     * PDO statement object.
     * 
     * @var PDOStatement|null $stmt
     */
    private ?PDOStatement $stmt = null;

    /**
     * Database configuration.
     * 
     * @var Database|null $config 
     */
    private ?Database $config = null;

    /**
     * Debug mode flag.
     * 
     * @var bool $onDebug
     */
    private bool $onDebug = false;

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
     * Show Query Execution profiling.
     * 
     * @var bool $showProfiling
     */
    private static bool $showProfiling = false;

    /**
     * Total Query Execution time.
     * 
     * @var float|int $queryTotalTime
     */
    protected float|int $queryTotalTime = 0;

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
    private float|int $startTime = 0;

    /**
     * Result mode.
     * 
     * @var bool $isResult
     */
    private bool $isResult = false;

    /**
     * Database version.
     * 
     * @var string $version
     */
    private string $version = 'mysql';

    /**
     * Last executed query.
     * 
     * @var array $query
     */
    private array $query = ['query' => '', 'params' => []];

    /**
     * Transaction savepoint mapping.
     * 
     * @var array<string,true> $savepoint 
     */
    private array $savepoint = [];

    /**
     * Result fetch modes.
     * 
     * @var array<int,string> $fetchModes
     */
    private static array $fetchModes = [
        FETCH_ASSOC     => PDO::FETCH_ASSOC,
        FETCH_BOTH      => PDO::FETCH_BOTH,
        FETCH_OBJ       => PDO::FETCH_OBJ, 
        FETCH_COLUMN    => PDO::FETCH_COLUMN,
        FETCH_KEY_PAIR  => PDO::FETCH_KEY_PAIR,
        FETCH_NUM       => PDO::FETCH_NUM,
        FETCH_NUM_OBJ   => PDO::FETCH_OBJ,
        FETCH_CLASS     => PDO::FETCH_CLASS
    ];

    /**
     * {@inheritdoc}
     */
    public function __construct(Database $config) 
    {
        $this->config = $config;
        $this->version = strtolower($this->config->getValue('pdo_version'));
    }

    /**
     * {@inheritdoc}
     */
    public static function getInstance(Database $config) : DatabaseInterface
    {
        if (!self::$instance instanceof DatabaseInterface) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(): bool 
    {
        try{
            $this->newConnection();
            $this->connected = true;
        }catch(Throwable $e){
            if(!$e instanceof DatabaseException){
                throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        self::$showProfiling = (
            $this->isConnected() && 
            (!PRODUCTION || STAGING) && 
            env('debug.show.performance.profiling', false)
        );

        return $this->connected;
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
        return $this->isConnected() 
            ? ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) ?? $this->version)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion(): ?string
    {
        $version = match($this->getDriver()) {
            'mysql'   => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'cubrid'  => $this->query("SELECT version()"),
            'dblib', 'sqlsrv'  => $this->query("SELECT @@VERSION"),
            'sqlite'  => $this->query("SELECT sqlite_version()"),
            'pgsql'   => $this->query("SHOW server_version"),
            'oci'     => $this->query("SELECT * FROM v\$version"),
            default   => null,
        };

        if($version instanceof PdoDatabase && $version->ok()){
            $version = $version->getStatement()
                ->fetchColumn();
        }

        return $version;
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
    public function getQueryTime(): float|int 
    {
        return $this->queryTotalTime;
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
    public function isConnected(): bool 
    {
        return ($this->connected && $this->connection instanceof PDO);
    }

    /**
     * {@inheritdoc}
     */
    public function raw(): ConnInterface 
    {
        return new class($this->connection) implements ConnInterface 
        {
            /**
             * @var ?PDO $conn
             */
            private ?PDO $conn = null;

            /**
             * {@inheritdoc}
             */
            public function __construct(?PDO $conn = null){
                $this->conn = $conn;
            }
            
            /**
             * {@inheritdoc}
             */
            public function close(): void {$this->conn = null;}

            /**
             * {@inheritdoc}
             */
            public function getConn(): ?PDO { return $this->conn;}
        };
    }

    /**
     * {@inheritdoc}
     */
    public function error(): string 
    {
        return $this->isStatement() 
            ? $this->stmt->errorInfo()[2] ?? '' 
            : ($this->isConnected() ? ($this->connection->errorInfo()[2] ?? '') : 'Connection is not established');
    }

    /**
     * {@inheritdoc}
     */
    public function errors(): array 
    {
        return [
            'statement' => [
                'errno' => $this->isStatement() ? $this->stmt->errorCode() : -1,
                'error' => $this->isStatement() ? $this->stmt->errorInfo()[2] : null
            ],
            'connection' => [
                'errno' => ($this->isConnected() ? $this->connection->errorCode() : -1) ?? -1,
                'error' => $this->isConnected() 
                    ? ($this->connection->errorInfo()[2] ?? null) 
                    : 'Connection is not established'
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

        $info = $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        if(!$info){
            return ['status' => 'idle'];
        }

        preg_match_all('/(\S[^:]+): (\S+)/', $info, $matches);
        return array_combine($matches[1], $matches[2]) ?: [];
    }

    /**
     * {@inheritdoc}
     */
    public function dumpDebug(): bool
    {
        return (!$this->onDebug || !$this->isStatement()) 
            ? false 
            : $this->stmt->debugDumpParams();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query): self 
    {
        $this->assertConnection();
        $this->profiling(true);

        $this->query = ['query' => '', 'params' => []];
        $this->isResult = false;
        $this->executed = false;
        $this->stmt = $this->connection->prepare($query);
        $this->addQueryInfo('query', $query);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function query(string $query): self
    {
        $this->assertConnection();
        $this->profiling(true);

        $this->query = ['query' => '', 'params' => []];
        $this->isResult = false;
        $this->executed = false;

        $this->stmt = $this->connection->query($query) ?: null;

        if($this->stmt instanceof PDOStatement){
            $this->executed = true;
        }

        $this->addQueryInfo('query', $query);
        $this->profiling(false, fn: __METHOD__);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $query): int 
    {
        $this->assertConnection();
        $this->profiling(true);

        $this->query = ['query' => '', 'params' => []];
        $this->isResult = false;
        $this->executed = false;

        $result = $this->connection->exec($query);

        $this->addQueryInfo('query', $query);
        $this->profiling(false, fn: __METHOD__);

        if($result === false){
            return 0;
        }

        $this->executed = true;

        if ($result === -1 && Database::isDDLQuery($query)) {
            return 1;
        }

        return max(1, $result);
    }

    /**
     * {@inheritdoc}
     */
    public function setTransactionIsolation(int $level = 2): bool
    {
        if($level === 0){
            return true;
        }

        $this->assertConnection();
        $mode = match($level){
            1 => 'READ UNCOMMITTED',
            2 => 'READ COMMITTED',
            3 => 'REPEATABLE READ',
            4 => 'SERIALIZABLE',
            5 => 'READ WRITE',
            6 => 'READ ONLY',
            default => throw new DatabaseException(
                "Invalid transaction isolation level: {$level}. Allowed levels are integers between 1 and 6.",
                ErrorCode::DATABASE_TRANSACTION_FAILED
            )
        };

        if($this->connection->inTransaction()){
            throw new DatabaseException(
                "Cannot set transaction isolation level inside an active transaction",
                ErrorCode::DATABASE_TRANSACTION_FAILED
            );
        }

        try{
            return $this->connection->exec(sprintf(
                'SET TRANSACTION ISOLATION LEVEL %s', 
                $mode
            )) !== false;
        }catch(Throwable $e){
            $this->profiling(false, true, __METHOD__);

            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        $this->assertConnection();
        $inTransaction = $this->connection->inTransaction();

        if($inTransaction && $name === null){
            throw new DatabaseException(
                'Nested transaction requires a savepoint name',
                ErrorCode::TRANSACTION_SAVEPOINT_FAILED
            );
        }

        $startedTransaction = false;

        try{
            if ($flags !== 0 && !$inTransaction) {
                if($this->startTransactionWithFlags($flags)){
                    $inTransaction = $this->connection->inTransaction();
                    $startedTransaction = $inTransaction;
                }
            }
            
            if (!$inTransaction) {
                if (!$this->connection->beginTransaction()) {
                    return false;
                }
                
                $startedTransaction = true;
            }

            if ($name === null) {
                return true;
            }

            return $this->savepoint($name);
        }catch(Throwable $e){
            $this->profiling(false, true, __METHOD__);

            if ($startedTransaction && $this->connection->inTransaction()) {
                try{
                    $this->connection->rollBack();
                }catch(Throwable){}
            }

            if($e instanceof DatabaseException){
                throw $e;
            }

            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function beginNestedTransaction(bool $closeCursor = false): string|bool|null
    {
        $this->assertConnection();

        if($closeCursor){
            $this->free();
        }

        try{
            if (!$this->inTransaction()) {
                return $this->beginTransaction() ? null : false;
            }

            $savepoint = uniqid('nested_savepoint_');
            return $this->savepoint($savepoint) 
                ? $savepoint 
                : false;
        }catch(Throwable){
            return false;
        }
    }

    /**
     * Set the transaction isolation level and options for the current PDO connection.
     *
     * This method interprets MySQLi-style bitmask flags to construct the appropriate
     * `START TRANSACTION` statement for PDO. It supports the following flags:
     *
     * Bitmask flags:
     * 1 (MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT) - Starts transaction with a consistent snapshot (InnoDB behavior)
     * 2 (MYSQLI_TRANS_START_READ_WRITE)              - Starts transaction in read/write mode
     * 4 (MYSQLI_TRANS_START_READ_ONLY)               - Starts transaction in read-only mode
     *
     * Flags can be combined using bitwise OR, e.g. (1 | 4) to start a read-only transaction
     * with a consistent snapshot.
     *
     * @param int $flags Bitmask representing transaction isolation and mode options.
     *
     * @return bool Returns rue on success, false if no recognized flags were provided.
     *
     * @throws DatabaseException If the SQL execution fails.
     */
    private function startTransactionWithFlags(int $flags)
    {
        $clauses = [];

        if ($flags & 1) {
            $clauses[] = 'WITH CONSISTENT SNAPSHOT';
        }

        if ($flags & 2) {
            $clauses[] = 'READ WRITE';
        }

        if ($flags & 4) {
            $clauses[] = 'READ ONLY';
        }

        if ($flags & 5) {
            $clauses[] = 'ISOLATION LEVEL READ COMMITTED';
        }

        if ($clauses === []) {
            return false;
        }

        $sql = 'START TRANSACTION ' . implode(', ', $clauses);

        if ($this->connection->exec($sql) === false) {
            throw new DatabaseException(
                'Failed to start transaction with flags.',
                ErrorCode::DATABASE_TRANSACTION_FAILED
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function savepoint(string $name): bool
    {
        $this->assertConnection();

        if (!$this->connection->inTransaction()) {
            throw new DatabaseException(
                'Cannot create savepoint outside transaction'
            );
        }

        $name = $this->parseSavepoint($name, __METHOD__, 1);

        try{
            if($this->connection->exec("SAVEPOINT {$name}") !== false){
                $this->savepoint[$name] = true;
                return true;
            }
        }catch(Throwable $e){
            $this->profiling(false, true, __METHOD__);
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();

        if (!$this->inTransaction()) {
            return false;
        }

        $name = $this->parseSavepoint($name, __METHOD__, 2);

        try{
            if ($name === null) {
                if($this->connection->commit()){
                    $this->savepoint = [];
                    return true;
                }

                return false;
            }

            if($this->connection->exec("RELEASE SAVEPOINT {$name}") !== false){
                unset($this->savepoint[$name]);
                return true;
            }
        }catch(Throwable $e){
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        } finally {
            if(!$name){
                $this->profiling(false, true, __METHOD__);
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();

        if (!$this->inTransaction()) {
            return true;
        }

        $name = $this->parseSavepoint($name, __METHOD__, 2);
        
        try{
            if ($name === null) {
                if($this->connection->rollBack()){
                    $this->savepoint = [];
                    return true;
                }

                return false;
            }

            if($this->connection->exec("ROLLBACK TO SAVEPOINT {$name}") !== false){
                unset($this->savepoint[$name]);
                return true;
            }
        }catch(Throwable $e){
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        } finally {
            if(!$name){
                $this->profiling(false, true, __METHOD__);
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function release(string $name): bool 
    {
        $this->assertConnection();

        if (!$this->inTransaction()) {
            return false;
        }

        $name = $this->parseSavepoint($name, __METHOD__, 2);

        try{
            if($this->connection->exec("RELEASE SAVEPOINT {$name}") !== false){
                unset($this->savepoint[$name]);
                return true;
            }
        }catch(Throwable $e){
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool 
    {
        return $this->isConnected() && $this->connection->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public static function getType(mixed $value): int
    {
        return match (true) {
            is_null($value) => PDO::PARAM_NULL,
            is_bool($value)  => PDO::PARAM_BOOL,
            is_int($value)  => PDO::PARAM_INT,
            is_resource($value), 
            (is_string($value) && (bool) preg_match('~[^\x09\x0A\x0D\x20-\x7E]~', $value)) => PDO::PARAM_LOB,
            default  => PDO::PARAM_STR
        };
    }

    /**
     * {@inheritdoc}
     */
    public static function fromTypes(int $type): int  
    {
        return match ($type) {
            PARAM_INT   => PDO::PARAM_INT,
            PARAM_BOOL  => PDO::PARAM_BOOL,
            PARAM_NULL  => PDO::PARAM_NULL,
            PARAM_LOB   => PDO::PARAM_LOB,
            PARAM_STR,
            PARAM_FLOAT => PDO::PARAM_STR,
            default     => $type
        };
    }
    
    /**
     * {@inheritdoc}
     */
    public function bind(string $param, mixed $value, ?int $type = null): self 
    {
        $this->assertStatement();
        $type = ($type === null) ? self::getType($value) : self::fromTypes($type);

        $this->stmt->bindValue($param, $value, $type);
        $this->addQueryInfo('params', [$param => $value]);

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
        $type = ($type === null) ? self::getType($value) : self::fromTypes($type);

        $this->stmt->bindParam($param, $value, $type);
        $this->addQueryInfo('params', [$param => $value]);
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): bool 
    {
        //if($this->executed){
        //    return false;
        //}

        $this->assertStatement();

        try {
           $this->executed = $this->stmt->execute($params);
        } catch (Throwable $e) {
            if(!$e instanceof DatabaseException){
                throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        } finally{
            if($params){
                $this->addQueryInfo('params', $params);
            }

            $this->profiling(false, fn: __METHOD__);
        }

        return $this->executed;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount(): int 
    {
        $this->isResult = true;
        return $this->isStatement() ? $this->stmt->rowCount() : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(int $returnMode = RETURN_ALL, int $fetchMode = FETCH_OBJ): mixed 
    {
        return match ($returnMode) {
            RETURN_NEXT => $this->fetchNext($fetchMode),
            RETURN_ALL => $this->fetchAll($fetchMode),
            RETURN_STREAM => $this->fetch(RETURN_STREAM, $fetchMode),
            RETURN_2D_NUM => $this->getInt(),
            RETURN_INT => $this->getCount(),
            RETURN_ID => $this->getLastInsertId(),
            RETURN_COUNT => $this->rowCount(),
            RETURN_COLUMN => $this->getColumns(),
            RETURN_STMT, RETURN_RESULT => $this->getStatement(),
            default => false
        };
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNext(int $mode = FETCH_OBJ): array|object|bool 
    {
        return $this->fetch(RETURN_NEXT, $mode) ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function getNext(int $mode = FETCH_OBJ): array|object|bool 
    {
        return $this->fetchNext($mode);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchAll(int $mode = FETCH_OBJ): array|object|bool 
    {
        return $this->fetch(RETURN_ALL, $mode) ?: false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(int $mode = FETCH_OBJ): array|object|bool 
    {
        return $this->fetchAll($mode);
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns(int $mode = FETCH_COLUMN): array 
    {
        return $this->fetch(RETURN_ALL, $mode) ?: [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInt(): array
    {
        return $this->fetch(RETURN_ALL, FETCH_NUM) ?: [];
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

        $integers = $integers[0] ?? 0;

        return ($integers && is_array($integers)) 
            ? (int) ($integers[0] ?? 0) 
            : (int) $integers;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatement(): ?PDOStatement
    {
        return ($this->stmt instanceof PDOStatement) ? $this->stmt : null;
    }

    /**
     * {@inheritdoc}
     */ 
    public function fetch(int $type = RETURN_ALL, int $mode = FETCH_OBJ): mixed  
    {
        $this->assertStatement();
        $fetchMode = self::$fetchModes[$mode] ?? PDO::FETCH_OBJ;

        if ($fetchMode === null) {
            throw new DatabaseException(
                sprintf('Unsupported database fetch mode: %d. Use FETCH_*', $mode),
                ErrorCode::NOT_SUPPORTED
            );
        }
        
        $this->isResult = true;

        if(($mode === FETCH_CLASS || $mode === FETCH_OBJ) && $type === RETURN_NEXT){
            $result = $this->fetchObject(stdClass::class);
        }elseif($type === RETURN_ALL){
            $result = $this->stmt->fetchAll($fetchMode);
        }else{
            $result = $this->stmt->fetch($fetchMode);
        }

        if(!$result){
            return $result;
        }

        return match($mode){
            FETCH_OBJ, FETCH_NUM_OBJ => Database::toResultObject($result),
            default => (array) $result
        };
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
    public function fetchObject(?string $class = null, mixed ...$arguments): ?object 
    {
        if(!$this->isStatement()){
            return null;
        }

        return $this->stmt->fetchObject($class, $arguments) ?: null;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastInsertId(?string $name = null): mixed
    {
        return $this->isConnected() 
            ? ($this->connection->lastInsertId($name) ?: null)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function free(): void 
    {
        if($this->stmt === null){
            return;
        }

        $this->stmt->closeCursor();
        $this->stmt = null;
        $this->isResult = false;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void 
    {
        $this->free();
        $this->connection = null;
        $this->connected = false;
    }

    /**
     * {@inheritdoc}
     */
    public function profiling(
        bool $start = true, 
        bool $finishedTransaction = false,
        ?string $fn = null
    ): void
    {
        if(!self::$showProfiling || (!$start && $this->inTransaction() && !$finishedTransaction)){
            return;
        }

        if ($start) {
            $this->startTime = microtime(true);
            return;
        }

        if ($this->startTime <= 0) {
            return;
        }

        $end = microtime(true);
        $this->lastQueryTime = $end - $this->startTime;
        $this->queryTotalTime += $this->lastQueryTime;

        $executions = Boot::get('__DB_QUERY_EXEC_PROFILING__') ?? [];
        $executions['global'] = [
            'time' => $this->queryTotalTime,
            'driver'   => $this->version,
        ];

        $executions['queries'][] = [
            'time' => $this->lastQueryTime,
            'query'    => $this->query['query'],
            'method'   => $fn,
            'params'   => $this->query['params']
        ];

        // Store it in a shared memory to retrieve later when needed.
        Boot::set('__DB_QUERY_EXEC_PROFILING__', $executions);

        $this->startTime = 0;
    }

    /**
     * Normalize transaction savepoint name.
     * 
     * @param string|null $name Savepoint name.
     * @param string $fn called method name.
     * @param int $check 1 if already exist, 2 if not exist.
     * 
     * @return string|null Return normalized name or null if invalid.
     * @throws DatabaseException
     */
    private function parseSavepoint(?string $name, string $fn, int $check = 0): ?string 
    {
        if ($name === null) {
            return null;
        }

        $name = preg_replace('/[^a-zA-Z0-9_]/', '', trim($name));

        if ($name === '') {
            $this->profiling(false, true, $fn);

            throw new DatabaseException(
                'Invalid savepoint name.', 
                ErrorCode::TRANSACTION_SAVEPOINT_FAILED
            );
        }

        $prefix = is_numeric($name) ? 'tnx_' : '';
        $name = substr($prefix . $name, 0, 64);

        if($check > 0){
            $isExsit = isset($this->savepoint[$name]);
            $err = null;

            if($check === 1 && $isExsit){
                $err = 'Savepoint %s already exist';
            }elseif($check === 2 && !$isExsit){
                $err = 'Savepoint %s does not exist.';
            }

            if($err !== null){
                $this->profiling(false, true, $fn);
                throw new DatabaseException(sprintf(
                    $err,
                    $name
                ));
            }
        }

        return $name;
    }

    /**
     * Add query profiling.
     * 
     * @param string $key The query profile key.
     * @param mixed $value The value.
     * 
     * @return void
     */
    private function addQueryInfo(string $key, mixed $value): void 
    {
        if(!self::$showProfiling){
            return;
        }

        if($key === 'query'){
            $this->query[$key] = $value;
            return;
        }

        $this->query[$key] = array_merge(
            $this->query[$key], 
            $value
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isStatement(): bool 
    {
        return ($this->stmt instanceof PDOStatement);
    }

    /**
     * {@inheritdoc}
     */
    public function isResult(): bool 
    {
        return ($this->stmt instanceof PDOStatement) && $this->isResult;
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
                ErrorCode::CONNECTION_DENIED
            );
        } 
    }

    /**
     * Ensures that a valid SQL statement is available before execution.
     * 
     * @throws DatabaseException If no prepared or valid statement exists.
     */
    private function assertStatement(): void 
    {
        if(!$this->isStatement()){
            throw new DatabaseException(
                'No valid SQL statement to execute. Ensure a query is prepared or available.',
                ErrorCode::NO_STATEMENT_TO_EXECUTE
            );
        }
    }

    /**
     * Initializes the database connection.
     * This method is called internally and should not be called directly.
     * 
     * @return void 
     * @throws DatabaseException If no driver is specified.
     * @throws PDOException Throws if pdo error occurs.
     */
    private function newConnection(): void
    {
        if ($this->connection instanceof PDO) {
            return;
        }

        $username = $password = null;
        $charset = $this->config->getValue('charset');

        if ($charset) {
            $charset = strtolower($charset);
            $charset = ($charset === 'utf8' || $charset === 'utf-8') ? 'utf8mb4' : $charset;
        }

        $dsn = $this->dsnConnection($charset);

        if ($dsn === null) {
            throw new DatabaseException(
                sprintf('Unsupported PDO driver: "%s"', $this->version),
                ErrorCode::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }

        if ($this->version !== 'sqlite') {
            $username = $this->config->getValue('username');
            $password = $this->config->getValue('password');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_PERSISTENT => Luminova::isCommand() || (bool) $this->config->getValue('persistent', false),
            PDO::ATTR_EMULATE_PREPARES => (bool) $this->config->getValue('emulate_prepares'),
        ];

        if ($this->version === 'mysql') {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = (bool) $this->config->getValue(
                'buffered_query', 
                false
            );
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = PRODUCTION;
            $this->setInitCommands($charset, $options);

            if ($timeout = $this->config->getValue('timeout')) {
                $options[PDO::ATTR_TIMEOUT] = (int) $timeout;
            }
        }

        $this->connection = new PDO($dsn, $username, $password, $options);
    }

    /**
     * Apply developers defined command.
     * 
     * @param string|null $charset
     * @param array &$options
     * 
     * @return void 
     */
    private function setInitCommands(?string $charset, array &$options): void 
    {
        $commands = (array) $this->config->getValue('commands', []);
        $statements = [];
        $hasSetNames = false;

        if($commands){
            foreach ($commands as $command) {
                $command = trim($command);

                if ($command === '') {
                    continue;
                }

                if (!str_starts_with(strtoupper($command), 'SET ')) {
                    throw new DatabaseException(
                        sprintf(
                            'Invalid command: %s. Only SET statements are allowed.',
                            $command
                        ),
                        ErrorCode::VALUE_FORBIDDEN
                    );
                }

                if (preg_match('/^SET\s+NAMES\b/i', $command)) {
                    if (!preg_match(
                        '/^SET\s+NAMES\s+[a-z0-9_]+(\s+COLLATE\s+[a-z0-9_]+)?$/i',
                        $command
                    )) {
                        throw new DatabaseException(
                            "Invalid SET NAMES statement: {$command}",
                            ErrorCode::VALUE_FORBIDDEN
                        );
                    }

                    $hasSetNames = true;
                }

                $statements[] = rtrim($command, ';');
            }
        }

        if ($charset && !$hasSetNames) {
            if (!preg_match('/^[a-z0-9_]+$/i', $charset)) {
                throw new DatabaseException(
                    "Invalid MySQL charset: {$charset}", 
                    ErrorCode::VALUE_FORBIDDEN
                );
            }

            $statements[] = "SET NAMES {$charset}";
        }

        if($statements){
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = implode('; ', $statements);
        }
    }

    /**
     * Get driver connection Data Source Name (DSN).
     *
     * @param string|null $charset
     * 
     * @return string|null
     */
    private function dsnConnection(?string $charset = null): ?string
    {
        if($this->version === 'sqlite'){
            $sqlitePath = $this->config->getValue('sqlite_path');
            if(!$sqlitePath){
                return null;
            }

            return "sqlite:{$sqlitePath}";
        }

        $database = $this->config->getValue('database');
        $host = $this->config->getValue('host');
        $port = $this->config->getValue('port');
        $options = ($charset && $this->version === 'pgsql') ? ";options='--client_encoding={$charset}'" : '';

        return match($this->version){
            'mysql' => $this->withMysqlDsn($database, $host, $port, $charset),
            'cubrid' => "cubrid:host={$host};port={$port};dbname={$database}",
            'dblib' => "dblib:host={$host}:{$port};dbname={$database}",
            'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}{$options}",
            'sqlsrv' => "sqlsrv:Server={$host};Database={$database}",
            'oci' => "oci:dbname=" . (
                (str_contains($database, '/') || str_contains($database, ':')) 
                    ? "//{$database}" 
                    : $database
            ),
            default => null
        };
    }

    /**
     * Get mysql connection dsn based on environment.
     * Cli or Force: Use Unix socket connection
     * Http: Use TCP/IP connection
     * 
     * @return string Return database connection dsn string.
     */
    private function withMysqlDsn(string $database, string $host, int $port, ?string $charset = null): string
    {
        $options = $charset ? ";charset={$charset}" : '';

        if (NOVAKIT_ENV !== null || $this->config->getValue('socket') || Luminova::isCommand()) {
            $socketPath = $this->config->getValue('socket_path') ?: ini_get('pdo_mysql.default_socket');

            if(!$socketPath){
              throw new DatabaseException(sprintf(
                    'PDO MySQL socket path not set. Define it in the environment as "%s", ' . 
                    'or configure "%s" in your php.ini.',
                    'database.mysql.socket.path',
                    'pdo_mysql.default_socket'
                ));
            }
  
            return "mysql:unix_socket={$socketPath};dbname={$database}{$options}";
        }

        return "mysql:host={$host};port={$port};dbname={$database}{$options}";
    }
}