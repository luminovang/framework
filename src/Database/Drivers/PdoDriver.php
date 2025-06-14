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
namespace Luminova\Database\Drivers;

use \Luminova\Core\CoreDatabase;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\ConnInterface;
use \PDO;
use \PDOStatement;
use \Throwable;
use \PDOException;

final class PdoDriver implements DatabaseInterface 
{
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
     * Database configuration.
     * 
     * @var CoreDatabase|null $config 
     */
    private ?CoreDatabase $config = null; 

    /**
     * Using bind and param parsing.
     * 
     * @var bool $parseParams
     */
    private bool $parseParams = false;

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
        FETCH_ASSOC     => PDO::FETCH_ASSOC,
        FETCH_BOTH      => PDO::FETCH_BOTH,
        FETCH_OBJ       => PDO::FETCH_OBJ, 
        FETCH_COLUMN    => PDO::FETCH_COLUMN,
        FETCH_COLUMN_ASSOC => PDO::FETCH_KEY_PAIR,
        FETCH_NUM       => PDO::FETCH_NUM,
        FETCH_ALL       => PDO::FETCH_ASSOC,
        FETCH_NUM_OBJ   => PDO::FETCH_OBJ
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
        return $this->isConnected() 
            ? $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) ?? $this->config->getValue('pdo_version')
            : null;
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

        preg_match_all(
            '/(\S[^:]+): (\S+)/', 
            $info, 
            $matches
        );
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
        $this->executed = false;
        $this->stmt = $this->connection->prepare($query);
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
        $this->stmt = $this->connection->query($query);

        if($this->stmt instanceof PDOStatement){
            $this->executed = true;
        }

        $this->profiling(false);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exec(string $query): int 
    {
        $this->assertConnection();
        $this->profiling(true);
        $this->executed = false;
        $executed = $this->connection->exec($query);
        $this->profiling(false);

        if($executed === false){
            return 0;
        }

        $executed = ($executed === 0 && CoreDatabase::isDDLQuery($query)) ? 1 : $executed;
        $this->executed = $executed > 0;

        return $executed;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        $readonly = true;
        $savepoint = true;
        $this->assertConnection();
        $this->profiling(true);

        if ($flags === 4) {
            $readonly = $this->connection->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            
            if ($readonly === false) {
                $this->profiling(false, true);
                throw new DatabaseException(
                    'Failed to set transaction isolation level for read-only.', 
                    DatabaseException::DATABASE_TRANSACTION_READONLY_FAILED
                );
            }
        }

        $status = $this->connection->beginTransaction();
        if ($status === false) {
            $this->profiling(false, true);

            return false;
        }

        if ($name !== null) {
            $name = $this->connection->quote("tnx_{$name}");
            if ($name === false) {
                $this->profiling(false, true);

                throw new DatabaseException(
                    'Failed to create savepoint name.', 
                    DatabaseException::TRANSACTION_SAVEPOINT_FAILED
                );
            }

            $savepoint = $this->connection->exec("SAVEPOINT {$name}") !== false;
            
            if ($savepoint === false) {
                $this->connection->rollBack(); 
                $this->profiling(false, true);

                return false;
            }
        }

        return $status && $readonly && $savepoint;
    }

    /**
     * {@inheritdoc}
     */
    public function commit(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();
        $result = $this->connection->commit();
        $this->profiling(false, true);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();
        $result = false;
        if ($name === null) {
            $result = $this->connection->rollBack();
        }else{
            $name = $this->connection->quote("tnx_{$name}");

            if ($name === false) {
                $this->profiling(false, true);

                throw new DatabaseException(
                    'Failed to create savepoint name.', 
                    DatabaseException::TRANSACTION_SAVEPOINT_FAILED
                );
            }

            $result = $this->connection->exec("ROLLBACK TO SAVEPOINT {$name}") !== false;
        }

        $this->profiling(false, true);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool 
    {
        return $this->isConnected() ? $this->connection->inTransaction() : false;
    }

    /**
     * {@inheritdoc}
     */
    public static function getType(mixed $value): string|int 
    {
        return match (true) {
            is_int($value) => 1,
            is_bool($value) => 5,
            is_null($value) => 0,
            default => 2,
        };
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $param, mixed $value, ?int $type = null): self 
    {
        $this->assertStatement();
        $this->stmt->bindValue($param, $value, $type ?? self::getType($value));
        $this->parseParams = true;

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
        $this->stmt->bindParam($param, $value, $type ?? self::getType($value));
        $this->parseParams = true;
        
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
           $this->executed = $this->stmt->execute($this->parseParams ? null : $params);
           $this->parseParams = false;
        } catch (Throwable $e) {
            throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->executed;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount(): int 
    {
        return $this->isStatement() ? $this->stmt->rowCount() : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(int $mode = RETURN_ALL, string $type = 'object'): mixed 
    {
        return match ($mode) {
            RETURN_NEXT => $this->fetchNext($type),
            RETURN_2D_NUM => $this->getInt(),
            RETURN_INT => $this->getCount(),
            RETURN_ID => $this->getLastInsertId(),
            RETURN_COUNT => $this->rowCount(),
            RETURN_COLUMN => $this->getColumns(),
            RETURN_ALL => $this->fetchAll($type),
            RETURN_STMT, RETURN_RESULT => $this->getStatement(),
            default => false
        };
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNext(string $type = 'object'): array|object|bool 
    {
        $result = $this->fetch('next', ($type === 'array') ? FETCH_ASSOC : FETCH_OBJ);

        if(!$result){
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
        $result = $this->fetch('all', ($type === 'array') ? FETCH_ASSOC : FETCH_OBJ);

        if(!$result){
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
        return $this->fetch('all', $mode)?:[];
    }

    /**
     * {@inheritdoc}
     */
    public function getInt(): array
    {
        return $this->fetch('all', FETCH_NUM)?:[];
    }

    /**
     * {@inheritdoc}
     */
    public function getCount(): int
    {
        $integers = $this->getInt();

        if(!$integers || $integers === []){
            return 0;
        }

        return isset($integers[0][0]) 
            ? (int) $integers[0][0] 
            : (int) ($integers ?? 0);
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
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed  
    {
        $this->assertStatement();
        $fetchMode = self::$fetchModes[$mode] ?? PDO::FETCH_OBJ;

        if ($fetchMode === null) {
            throw new DatabaseException(
                sprintf('Unsupported database fetch mode: %d. Use FETCH_*', $mode),
                DatabaseException::NOT_SUPPORTED
            );
        }

        if($type === 'all'){
            return $this->stmt->fetchAll($fetchMode);
        }

        return $this->stmt->fetch($fetchMode);
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
    public function fetchObject(string|null $class = 'stdClass', mixed ...$arguments): object|bool 
    {
        return $this->isStatement() 
            ? $this->stmt->fetchObject($class, $arguments) 
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastInsertId(?string $name = null): string|int|null|bool
    {
        return $this->isConnected() 
            ? $this->connection->lastInsertId($name)
            : false;
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
    public function profiling(bool $start = true, bool $finishedTransaction = false): void
    {
        if(!self::$showProfiling || (!$start && $this->inTransaction() && !$finishedTransaction)){
            return;
        }
         
        if ($start) {
            self::$startTime = microtime(true);
            return;
        }

        $end = microtime(true);
        $this->lastQueryTime = abs($end - self::$startTime);
        $this->queryTime += ($this->lastQueryTime * 1_000);

        // Store it in a shared memory to retrieve later when needed.
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
        return ($this->stmt instanceof PDOStatement);
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
                ' No active database connection found. Connect before executing queries.',
                DatabaseException::CONNECTION_DENIED
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
                DatabaseException::NO_STATEMENT_TO_EXECUTE
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

        $version = strtolower($this->config->getValue('pdo_version'));
        $dns = $this->dnsConnection($version);

        if ($dns === null) {
            throw new DatabaseException(
                sprintf('Unsupported PDO driver, no driver found for: "%s"', $version),
                DatabaseException::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }

        $username = $password = null;

        if ($version !== 'sqlite' && $version !== 'pgsql') {
            $username = $this->config->getValue('username');
            $password = $this->config->getValue('password');
        }

        $options = [
            PDO::ATTR_EMULATE_PREPARES => $this->config->getValue('emulate_preparse'),
            PDO::ATTR_PERSISTENT => $this->config->getValue('persistent'),
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        if($version === 'mysql' && ($charset = $this->config->getValue('charset'))){
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset}";
        }

        $this->connection = new PDO($dns, $username, $password, $options);
    }

    /**
     * Get driver dns connection.
     *
     * @param string $version Connection driver version name.
     * 
     * @return string|null
     */
    private function dnsConnection(string $version): ?string
    {
        $sqlitePath = $this->config->getValue('sqlite_path');

        if($version === 'sqlite'){
            if(!$sqlitePath){
                return null;
            }

            return "sqlite:{$sqlitePath}";
        }

        $database = $this->config->getValue('database');
        $host = $this->config->getValue('host');
        $port = $this->config->getValue('port');

        return match($version){
            'cubrid' => "cubrid:dbname={$database};host={$host};port={$port}",
            'dblib' => "dblib:host={$host};dbname={$database};port={$port}",
            'oci' => "oci:dbname={$database}",
            'pgsql' => "pgsql:host={$host} port={$port} dbname={$database} user={$this->config->getValue('username')} password={$this->config->getValue('password')}",
            'sqlsrv' => "sqlsrv:Server={$host};Database={$database}",
            'mysql' => $this->mysqlDns($database, $host, $port),
            default => null
        };
    }

    /**
     * Get mysql connection dns based on environment.
     * Cli or Force: Use Unix socket connection
     * Http: Use TCP/IP connection
     * 
     * @return string Return database connection dns string.
     */
    private function mysqlDns(string $database, string $host, int $port): string
    {
        if (is_command() || NOVAKIT_ENV !== null || $this->config->getValue('socket')) {
            $socketPath = $this->config->getValue('socket_path');
            $socketPath = empty($socketPath) ? ini_get('pdo_mysql.default_socket') : $socketPath;

            return "mysql:unix_socket={$socketPath};dbname={$database}";
        }

        return "mysql:host={$host};port={$port};dbname={$database}";
    }
}