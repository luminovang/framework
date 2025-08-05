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
use \Luminova\Foundation\Core\Database;
use \Luminova\Exceptions\{ErrorCode, DatabaseException};
use \Luminova\Interface\{ConnInterface, DatabaseInterface};
use function \Luminova\Funcs\{shared, is_command};

final class PdoDatabase implements DatabaseInterface 
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
     * Result mode.
     * 
     * @var bool $isResult
     */
    private bool $isResult = false;

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
                throw new DatabaseException('Connection failed: ' . $e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }

        self::$showProfiling = ($this->isConnected() && !PRODUCTION && env('debug.show.performance.profiling', false));
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
            ? ($this->connection->getAttribute(PDO::ATTR_DRIVER_NAME) ?? $this->config->getValue('pdo_version'))
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

        $this->isResult = false;
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

        $this->isResult = false;
        $this->executed = false;
        $this->stmt = $this->connection->query($query) ?: null;

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

        $this->isResult = false;
        $this->executed = false;

        $executed = $this->connection->exec($query);
        $this->profiling(false);

        if($executed === false){
            return 0;
        }

        $executed = ($executed === 0 && Database::isDDLQuery($query)) ? 1 : $executed;
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
            $readonly = $this->connection->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            
            if ($readonly === false) {
                $this->profiling(false, true);
                throw new DatabaseException(
                    'Failed to set transaction isolation level for read-only.', 
                    ErrorCode::DATABASE_TRANSACTION_READONLY_FAILED
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
                    ErrorCode::TRANSACTION_SAVEPOINT_FAILED
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
                    ErrorCode::TRANSACTION_SAVEPOINT_FAILED
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
        $version = strtolower($this->config->getValue('pdo_version'));
        $charset = $this->config->getValue('charset');
        $dsn = $this->dsnConnection($version, $charset);

        if ($dsn === null) {
            throw new DatabaseException(
                sprintf('Unsupported PDO driver: "%s"', $version),
                ErrorCode::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }

        if ($version !== 'sqlite') {
            $username = $this->config->getValue('username');
            $password = $this->config->getValue('password');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_PERSISTENT => (bool) $this->config->getValue('persistent'),
            PDO::ATTR_EMULATE_PREPARES => (bool) $this->config->getValue('emulate_prepares'),
        ];

        if ($version === 'mysql') {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool) PRODUCTION;

            if ($charset) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '{$charset}'";
            }

            if ($timeout = $this->config->getValue('timeout')) {
                $options[PDO::ATTR_TIMEOUT] = (int) $timeout;
            }
        }

        $this->connection = new PDO($dsn, $username, $password, $options);
    }

    /**
     * Get driver connection Data Source Name (DSN).
     *
     * @param string $version Connection driver version name.
     * @param string|null $charset
     * 
     * @return string|null
     */
    private function dsnConnection(string $version, ?string $charset = null): ?string
    {
        if($version === 'sqlite'){
            $sqlitePath = $this->config->getValue('sqlite_path');
            if(!$sqlitePath){
                return null;
            }

            return "sqlite:{$sqlitePath}";
        }

        $database = $this->config->getValue('database');
        $host = $this->config->getValue('host');
        $port = $this->config->getValue('port');
        $options = ($charset && $version === 'pgsql') ? ";options='--client_encoding={$charset}'" : '';

        return match($version){
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

        if (NOVAKIT_ENV !== null || $this->config->getValue('socket') || is_command()) {
            $socketPath = $this->config->getValue('socket_path') ?: ini_get('pdo_mysql.default_socket');

            if(!$socketPath){
              throw new DatabaseException(sprintf(
                    'PDO MySQL socket path not set. Define it in the environment as "%s", or configure "%s" in your php.ini.',
                    'database.mysql.socket.path',
                    'pdo_mysql.default_socket'
                ));
            }
  
            return "mysql:unix_socket={$socketPath};dbname={$database}{$options}";
        }

        return "mysql:host={$host};port={$port};dbname={$database}{$options}";
    }
}