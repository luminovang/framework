<?php
/**
 * Luminova Framework PDO database driver extension.
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
use \PDO;
use \PDOStatement;
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
     * {@inheritdoc}
     */
    public function __construct(CoreDatabase $config) 
    {
        $this->config = $config;
        try{
            $this->newConnection();
            $this->connected = true;
        }catch(PDOException|DatabaseException $e){
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
        return ($this->connection === null) 
            ? null 
            : $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
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
        return $this->stmt?->errorInfo()[2] ?? $this->connection?->errorInfo()[2];
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function info(): array 
    {
        $driverInfo = $this->connection?->getAttribute(PDO::ATTR_CONNECTION_STATUS);

        preg_match_all('/(\S[^:]+): (\S+)/', $driverInfo, $matches);
        $info = array_combine($matches[1], $matches[2]);

        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function dumpDebug(): bool
    {
        return (!$this->onDebug || !$this->stmt) 
            ? false 
            : $this->stmt->debugDumpParams();
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query): self 
    {
        $this->profiling(true);
        $this->stmt = $this->connection->prepare($query);
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
        $this->stmt = $this->connection->query($query);

        if($this->stmt){
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
        $this->profiling(true);
        $this->executed = false;
        $executed = $this->connection->exec($query);
        $this->profiling(false);

        if($executed !== false){
            $this->executed = true;
            return ($executed === 0) ? 1 :$executed;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        $readonly = true;
        $savepoint = true;
        $this->profiling(true);

        if ($flags === 4) {
            $readonly = $this->connection->exec("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
            
            if ($readonly === false) {
                $this->profiling(false, true);
                DatabaseException::throwException(
                    'Failed to set transaction isolation level for read-only.', 
                    DatabaseException::DATABASE_TRANSACTION_READONLY_FAILED
                );

                return false;
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

                DatabaseException::throwException(
                    'Failed to create savepoint name.', 
                    DatabaseException::TRANSACTION_SAVEPOINT_FAILED
                );

                return false;
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
        $result = $this->connection->commit();
        $this->profiling(false, true);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $result = false;
        if ($name === null) {
            $result = $this->connection->rollBack();
        }else{
            $name = $this->connection->quote("tnx_{$name}");

            if ($name === false) {
                $this->profiling(false, true);

                DatabaseException::throwException(
                    'Failed to create savepoint name.', 
                    DatabaseException::TRANSACTION_SAVEPOINT_FAILED
                );

                return false;
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
        return $this->connection->inTransaction();
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
    public function bind(string $param, mixed $value, int|null $type = null): self 
    {
        $this->stmt->bindValue($param, $value, self::getType($value));
        $this->parseParams = true;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function param(string $param, mixed &$value, int|null $type = null): self 
    {
        $this->stmt->bindParam($param, $value, self::getType($value));
        $this->parseParams = true;
        
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): bool 
    {
        $this->executed = false;

        if(!$this->stmt){
            DatabaseException::throwException(
                'Database operation error: Statement execution failed.', 
                DatabaseException::NO_STATEMENT_TO_EXECUTE
            );

            return false;
        }
        
        try {
           $this->executed = $this->stmt->execute(($this->parseParams ? null : $params));
           $this->parseParams = false;
        } catch (PDOException $e) {
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->executed;
    }

    /**
     * {@inheritdoc}
     */
    public function rowCount(): int 
    {
        return $this->stmt ? $this->stmt->rowCount() : 0;
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
        $result = $this->fetch('next', $fetch === 'array' ? FETCH_ASSOC : FETCH_OBJ);

        if($result === false || $result === null){
            return false;
        }

        return ($fetch === 'array') 
            ? (array) $result 
            : (object) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(string $fetch = 'object'): array|object|bool 
    {
        $result = $this->fetch('all', $fetch === 'array' ? FETCH_ASSOC : FETCH_OBJ);

        if($result === false || $result === null){
            return false;
        }

        return ($fetch === 'array') 
            ? (array) $result 
            : (object) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns(int $mode = FETCH_COLUMN): array 
    {
        $response = $this->fetch('all', $mode);
        return $response ? $response : [];
    }

    /**
     * {@inheritdoc}
     */
    public function getInt(): array|bool
    {
        $integers = $this->fetch('all', FETCH_NUM);

        return $integers ? $integers : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getCount(): int|bool
    {
        $integers = $this->getInt();

        if(!$integers || $integers === []){
            return false;
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
        return $this->stmt;
    }

    /**
     * {@inheritdoc}
     */ 
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed  
    {
        if(!$this->stmt){
            return false;
        }

        $modes = [
            FETCH_ASSOC => PDO::FETCH_ASSOC,
            FETCH_BOTH => PDO::FETCH_BOTH,
            FETCH_OBJ => PDO::FETCH_OBJ, 
            FETCH_COLUMN => PDO::FETCH_COLUMN,
            FETCH_COLUMN_ASSOC => PDO::FETCH_KEY_PAIR,
            FETCH_NUM => PDO::FETCH_NUM,
            FETCH_ALL => PDO::FETCH_ASSOC,
            FETCH_NUM_OBJ => PDO::FETCH_OBJ
        ];

        $pdoMode = $modes[$mode] ?? PDO::FETCH_OBJ;
        $method = $type === 'all' ? 'fetchAll' : 'fetch';

        return $this->stmt->$method($pdoMode);
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
        return $this->stmt 
            ? $this->stmt->fetchObject($class, $arguments) 
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastInsertId(?string $name = null): string|int|null|bool
    {
        return $this->connection 
            ? $this->connection->lastInsertId($name)
            : false;
    }

    /**
     * {@inheritdoc}
     */
    public function free(): void 
    {
        if(!$this->stmt){
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
    public function profiling(bool $start = true, bool $finished_transaction = false): void
    {
        if(!self::$showProfiling || (!$start && $this->inTransaction() && !$finished_transaction)){
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
     * Initializes the database connection.
     * This method is called internally and should not be called directly.
     * 
     * @return void 
     * @throws DatabaseException If no driver is specified.
     * @throws PDOException Throws if pdo error occurs.
     */
    private function newConnection(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $driver = strtolower($this->config->pdo_engine);
        $dns = $this->dnsConnection($driver);

        if ($dns === '' || ($driver === 'sqlite' && $this->config->sqlite_path === '')) {
            throw new DatabaseException(
                sprintf('Unsupported PDO driver, no driver found for: "%s"', $driver),
                DatabaseException::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }

        $username = $password = null;

        if ($driver !== 'sqlite' && $driver !== 'pgsql') {
            $username = $this->config->username;
            $password = $this->config->password;
        }

        $options = [
            PDO::ATTR_EMULATE_PREPARES => $this->config->emulate_preparse,
            PDO::ATTR_PERSISTENT => $this->config->persistent,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
        ];

        if($driver === 'mysql' && $this->config->charset !== ''){
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$this->config->charset}";
        }

        $this->connection = new PDO($dns, $username, $password, $options);
    }

    /**
     * Get driver dns connection.
     *
     * @param string $context Connection driver context name.
     * 
     * @return string
     */
    private function dnsConnection(string $context): string
    {
        return match($context){
            'cubrid' => "cubrid:dbname={$this->config->database};host={$this->config->host};port={$this->config->port}",
            'dblib' => "dblib:host={$this->config->host};dbname={$this->config->database};port={$this->config->port}",
            'oci' => "oci:dbname={$this->config->database}",
            'pgsql' => "pgsql:host={$this->config->host} port={$this->config->port} dbname={$this->config->database} user={$this->config->username} password={$this->config->password}",
            'sqlite' => "sqlite:{$this->config->sqlite_path}",
            'sqlsrv' => "sqlsrv:Server={$this->config->host};Database={$this->config->database}",
            'mysql' => $this->mysqlDns(),
            default => ''
        };
    }

    /**
     * Get mysql connection dns based on environment.
     * Cli or Force: Use Unix socket connection
     * Http: Use TCP/IP connection
     * 
     * @return string Return database connection dns string.
     */
    private function mysqlDns(): string
    {
        if (is_command() || NOVAKIT_ENV !== null || $this->config->socket) {
            $socket = (($this->config->socket_path === '' || $this->config->socket_path === null) ? 
                ini_get('pdo_mysql.default_socket') : 
                $this->config->socket_path
            );

            return "mysql:unix_socket={$socket};dbname={$this->config->database}";
        }

        return "mysql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database}";
    }
}