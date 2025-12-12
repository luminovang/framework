<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Driver\Helpers;

use \Throwable;
use Luminova\Boot;
use Luminova\Exceptions\ErrorCode;
use Luminova\Database\Driver\Driver;
use Luminova\Database\Helpers\Scheme;
use Luminova\Interface\ConnInterface;
use Luminova\Foundation\Core\Database;
use Luminova\Interface\DatabaseInterface;
use Luminova\Exceptions\DatabaseException;

/**
 * @mixin DatabaseInterface
 */
trait DriversTrait 
{
    /**
     * Disable/skip changing the current transaction mode.
     */
    public const TRANSACTION_MODE_NONE = 0;

    /**
     * Transaction flag: Start with a consistent snapshot.
     *
     * Creates a consistent snapshot at the beginning of the transaction.
     * Supported by storage engines such as InnoDB.
     */
    public const TRANSACTION_WITH_CONSISTENT_SNAPSHOT = 1;

    /**
     * Transaction access mode/flag: READ WRITE.
     *
     * Allows both read and write operations within the transaction.
     */
    public const TRANSACTION_READ_WRITE = 2;

    /**
     * Transaction isolation level: READ UNCOMMITTED.
     *
     * Allows reading rows modified by uncommitted transactions (dirty reads).
     * This provides the highest concurrency but the weakest isolation.
     */
    public const TRANSACTION_READ_UNCOMMITTED = 3;

    /**
     * Transaction access mode/flag: READ ONLY.
     *
     * Restricts the transaction to read operations only. Some database
     * engines may optimize execution for read-only transactions.
     */
    public const TRANSACTION_READ_ONLY = 4;

    /**
     * Transaction isolation level: READ COMMITTED.
     *
     * Prevents dirty reads by exposing only committed data. Non-repeatable
     * reads and phantom reads may still occur.
     */
    public const TRANSACTION_READ_COMMITTED = 5;

    /**
     * Transaction isolation level: REPEATABLE READ.
     *
     * Guarantees that rows read during a transaction remain consistent for
     * subsequent reads. Phantom reads may still occur depending on the
     * database implementation.
     */
    public const TRANSACTION_REPEATABLE_READ = 6;

    /**
     * Transaction isolation level: SERIALIZABLE.
     *
     * Provides the highest isolation level by executing transactions as if
     * they were performed sequentially. This offers the strongest consistency
     * but may reduce concurrency.
     */
    public const TRANSACTION_SERIALIZABLE = 7;

    /**
     * Shared database object.
     * 
     * @var DatabaseInterface|null $instance
     */
    private static ?DatabaseInterface $instance = null;

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
     * Start Execution time.
     * 
     * @var float|int $startTime
     */
    private float|int $startTime = 0;

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
     * Number of open connections.
     *
     * @var int
     */
    private static int $openConnections = 0;

    /**
     * Transaction savepoint mapping.
     * 
     * @var array<string,true> $savepoint 
     */
    private array $savepoint = [];

    /**
     * Last executed query.
     * 
     * @var array $query
     */
    private array $query = ['query' => '', 'params' => []];

    /**
     * Administrative lock.
     *
     * @var array<string,bool> $locks
     */
    private array $locks = [];

    /**
     * {@inheritdoc}
     */
    public static function getInstance(Database $config) : DatabaseInterface
    {
        if (!static::$instance instanceof DatabaseInterface) {
            static::$instance = new static($config);
        }

        return static::$instance;
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

        self::$showProfiling = (!PRODUCTION || STAGING)
            && env('debug.show.performance.profiling', false);
            
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
    public function getResult(int $returnMode = RETURN_ALL, int $fetchAS = FETCH_OBJ): mixed 
    {
        return match ($returnMode) {
            RETURN_NEXT     => $this->fetchNext($fetchAS),
            RETURN_ALL      => $this->fetchAll($fetchAS),
            RETURN_2D_NUM   => $this->getInt(),
            RETURN_INT      => $this->getCount(),
            RETURN_ID       => $this->getLastInsertId(),
            RETURN_COUNT    => $this->rowCount(),
            RETURN_COLUMN   => $this->getColumns(),
            RETURN_STMT     => $this->getStatement(),
            RETURN_RESULT   => $this->getCursorResult(),
            default         => $this->fetch($returnMode, $fetchAS),
        };
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
    public static function getOpenConnections(): int
    {
        return self::$openConnections;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(?string $property): mixed
    {
        if($property === null){
            return $this->config->toArray();
        }

        $property = trim($property);

        if($property === ''){
            return null;
        }

        return $this->config->getValue(strtolower($property));
    }

    /**
     * {@inheritdoc}
     */
    public function raw(): ConnInterface 
    {
        return new Driver($this->connection);
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
    public function exists(string $table): bool
    {
        $this->prepare(
            'SELECT 1 FROM ' . Scheme::getTableExists($this->getDriver())
        )->bind(':tableName', $table);

        return $this->execute()
            && $this->fetchNext() !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function lock(string|int $identifier, int $timeout = 300): bool 
    {
        $result = $this->databaseLocking($identifier, 'lock', $timeout);

        if($result){
            $this->locks[$identifier] = true;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function unlock(string|int $identifier): bool 
    {
        $result = $this->databaseLocking($identifier, 'unlock');

        if($result){
            $this->locks[$identifier] = false;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function tryLock(string|int $identifier): bool 
    {   
        try{
            $result = $this->databaseLocking($identifier, 'tryLock');
        } catch (Throwable){
            $result = false;
        }

        $this->locks[$identifier] = $result;
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isLocked(string|int $identifier): bool 
    {   
        if(array_key_exists($identifier, $this->locks)){
            return $this->locks[$identifier] === true;
        }

        $result = $this->databaseLocking($identifier, 'isLocked');

        if($result){
            $this->locks[$identifier] = true;
        }

        return $result;
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
    public function getNext(int $mode = FETCH_OBJ): array|object|bool 
    {
        return $this->fetchNext($mode);
    }

    /**
     * {@inheritdoc}
     */
    public function beginNestedTransaction(
        int $flags = self::TRANSACTION_MODE_NONE,
        bool $closeCursor = false
    ): string|bool|null
    {
        $this->assertConnection();

        if($closeCursor){
            $this->free();
        }
        
        if (!$this->inTransaction()) {
            return $this->beginTransaction($flags) ? null : false;
        }

        $savepoint = bin2hex(random_bytes(5));
        return $this->savepoint($savepoint) 
            ? $savepoint 
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function tryBeginNestedTransaction(
        int $flags = self::TRANSACTION_MODE_NONE,
        bool $closeCursor = false
    ): string|bool|null
    {
        try{
            return $this->beginNestedTransaction($flags, $closeCursor);
        }catch(Throwable){
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function tryBeginTransaction(int $flags = self::TRANSACTION_MODE_NONE, ?string $name = null): bool
    {
        try{
            return $this->beginTransaction($flags, $name);
        }catch(Throwable){
            return false;
        }
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

        $executions = Boot::get(Boot::QUERY_PROFILING) ?? [];
        $executions['global'] = [
            'driver'      => $this->driver,
            'time'        => $this->queryTotalTime,
            'connections' => self::$openConnections
        ];

        $executions['queries'][] = [
            'time' => $this->lastQueryTime,
            'query'    => $this->query['query'],
            'method'   => $fn,
            'params'   => $this->query['params']
        ];

        // Store it in a shared memory to retrieve later when needed.
        Boot::set(Boot::QUERY_PROFILING, $executions);

        $this->startTime = 0;
    }

    /**
     * Parse transaction isolation level to mode.
     *
     * @param int $level The level identifier.
     * 
     * @return string Return transaction isolation mode.
     * @throws DatabaseException If invalid level or transaction
     */
    private function parseTransactionLevel(int $level = self::TRANSACTION_READ_COMMITTED): string
    {
        $this->assertConnection();

        if($this->inTransaction()){
            throw new DatabaseException(
                "Cannot set transaction isolation level inside an active transaction",
                ErrorCode::DATABASE_TRANSACTION_FAILED
            );
        }

        return match ($level) {
            self::TRANSACTION_READ_UNCOMMITTED => 'READ UNCOMMITTED',
            self::TRANSACTION_READ_COMMITTED   => 'READ COMMITTED',
            self::TRANSACTION_REPEATABLE_READ  => 'REPEATABLE READ',
            self::TRANSACTION_SERIALIZABLE     => 'SERIALIZABLE',
            self::TRANSACTION_READ_WRITE       => 'READ WRITE',
            self::TRANSACTION_READ_ONLY        => 'READ ONLY',
            default => throw new DatabaseException(
                sprintf(
                    'Invalid transaction isolation level "%s". Use %s::TRANSACTION_* constants.',
                    $level,
                    static::class
                ),
                ErrorCode::DATABASE_TRANSACTION_FAILED
            ),
        };
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
     * Executes the appropriate lock/unlock/free query based on the database type.
     *
     * @param string|int $identifier Lock identifier (integer required for PostgreSQL).
     * @param string $action Action to perform: 'lock', 'unlock', or 'tryLock'.
     * @param int $timeout Lock timeout in seconds (only applicable for MySQL). 
     * 
     * @return bool Return true if the operation was successful, false otherwise.
     * @throws DatabaseException If an invalid action is provided or an invalid PostgreSQL lock name is used.
     */
    private function databaseLocking(
        string|int $identifier,
        string $action,
        int $timeout = 300
    ): bool
    {
        $driver = $this->getDriver();

        if ($driver === 'sqlite') {
            $result = $this->createLockTable();

            if($result && in_array($action, ['lock', 'tryLock', 'isLocked'], true)){
                try{
                    $this->exec('DELETE FROM dbms_locks WHERE expires_at < strftime("%s", "now")');
                } catch(Throwable){}
            }
        }

        $lockName = ($driver === 'pgsql' && !is_numeric($identifier))
            ? 'hashtext(:lockName)'
            : ':lockName';

        $this->prepare(
            Scheme::getAdministrator($driver, $action, $lockName)
        )->bind(':lockName', $identifier);

        if ($driver !== 'pgsql' && $action === 'lock') {
            $this->bind(':waitTimeout', $timeout);
        }

        if (!$this->execute() || !$this->ok()) {
            return false;
        }

        return $this->parseLockResult($driver, $action);
    }

    /**
     * Parse lock result.
     *
     * @param string $driver
     * @return bool
     */
    private function parseLockResult(string $driver, string $action): bool
    {
        if ($driver === 'pgsql' && $action === 'lock') {
            return true;
        }

        if($driver === 'sqlite' && $action === 'unlock'){
            return $this->rowCount() > 0;
        }

        $row = $this->fetchNext();
        
        if($row === false){
            return false;
        }

        return match ($driver) {
            'pgsql',
            'mysql',
            'mysqli',
            'sqlite'
                => (bool) $row->result || $this->rowCount() > 0,
            'sqlsrv',
            'mssql',
            'dblib'
                => (int) $row->result >= 0,
            'oci',
            'oracle'
                => (int) $row->result === 0,
            default => false
        };
    }

    /**
     * Create sqlite table locking.
     *
     * @return bool
     */
    private function createLockTable(): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        if ($this->exists('dbms_locks')) {
            return $exists = true;
        }

        $query = <<<'SQL'
        CREATE TABLE IF NOT EXISTS dbms_locks (
            name TEXT PRIMARY KEY,
            expires_at INTEGER NOT NULL,
            acquired_at INTEGER NOT NULL
        )
        SQL;

        if (!$this->exec($query)) {
            throw new DatabaseException(
                "Failed to create SQLite lock table.",
                ErrorCode::EXECUTION_FAILED
            );
        }

        $exists = true;
        return false;
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
}