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

use \mysqli;
use \stdClass;
use \Throwable;
use \mysqli_stmt;
use \mysqli_result;
use \ReflectionClass;
use \Luminova\Logger\Logger;
use \Luminova\Core\CoreDatabase;
use \Luminova\Interface\ConnInterface;
use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;

final class MysqliDriver implements DatabaseInterface 
{
    /**
     * Flag for unbound placeholder key.
     * 
     * @var string NO_BIND_KEY
     */
    private const NO_BIND_KEY = '__LMV_MYSQLI_NO_BIND_KEY__';

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
     * Database configuration.
     * 
     * @var CoreDatabase|null $config
     */
    private ?CoreDatabase $config = null;

    /**
     * Debug mode flag.
     * 
     * @var bool $onDebug
     */
    private bool $onDebug = false;

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
     * Mode if any prepares emulation was found.
     * 
     * @var bool $usePrepares
     */
    private bool $usePrepares = false;

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
     * MYSQLI emulate prepares.
     * 
     * @var bool $isEmulatePrepares
     */
    private static bool $isEmulatePrepares = false;

    /**
     * Query metadata.
     * 
     * @var array $metadata
     */
    private array $metadata = [];

    /**
     * Named placeholder pattern.
     * 
     * @var string $pattern
     */
    private static string $pattern = '/:([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)/';

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
        FETCH_KEY_PAIR  => 'fetch_row',
        FETCH_NUM       => 'default',
        FETCH_NUM_OBJ   => 'default',
        FETCH_CLASS     => 'default',
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
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $this->config = $config;
        self::$isEmulatePrepares = (bool) $this->config->getValue('emulate_prepares');
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
        $this->rowCount = 0;
        $this->metadata = [];
        
        $query = $this->normalizeQuery($query);

        $this->stmt = $this->connection->prepare($query);

        if($this->stmt instanceof mysqli_stmt){
            $this->isSelect = CoreDatabase::isSqlQuery($query, 'SELECT');
        }

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
                ? (CoreDatabase::isSqlQuery($query, 'SELECT') 
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
        $commit = $this->connection->commit($flags, $name);
        $this->inTransaction = false;

        $this->profiling(false, true);
        return $commit;
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();
        $rollback = $this->connection->rollback($flags, $name);

        $this->inTransaction = false;
        $this->profiling(false, true);

        return $rollback;
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool 
    {
        return $this->isConnected() && $this->inTransaction;
    }

    /**
     * {@inheritdoc}
     */
    public static function getType(mixed $value): string  
    {
       return match (true) {
            is_null($value)  => 's',
            is_int($value),  is_bool($value) => 'i',
            is_float($value) => 'd',
            is_resource($value), 
            (is_string($value) && (bool) preg_match('~[^\x09\x0A\x0D\x20-\x7E]~', $value)) => 'b',
            default => 's'
        };
    }

    /**
     * {@inheritdoc}
     */
    public static function fromTypes(int $type): string  
    {
        return match ($type) {
            PARAM_INT,
            PARAM_BOOL  => 'i',
            PARAM_FLOAT => 'd',
            PARAM_STR,
            PARAM_LOB,
            PARAM_NULL  => 's',
            default     => 'b'
        };
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $param, mixed $value, ?int $type = null): self 
    {
        $this->assertStatement();

        $this->bindValues[$param] = [
            '_isReference' => false,
            'type' => ($type === null) ? null : self::fromTypes($type),
            'value' => $value
        ];

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

        $this->bindValues[$param] = [
            '_isReference' => true,
            'type' => ($type === null) ? null : self::fromTypes($type),
            'value' => &$value
        ];

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
            $this->bindParams($this->bindValues);
            $this->bindParams($params);

            $this->executed = $this->stmt->execute();

            if (!$this->executed || $this->stmt->errno) {
                throw new DatabaseException($this->stmt->error, $this->stmt->errno);
            }

            $this->rowCount = (int) ($this->isSelect ? $this->stmt->num_rows : $this->stmt->affected_rows);
        } catch (Throwable $e) {
            if (!$e instanceof DatabaseException) {
                throw new DatabaseException($e->getMessage(), $e->getCode(), $e);
            }

            throw $e;
        }
        
        $this->bindValues = [];
        $this->metadata = [];

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
            RETURN_STMT => $this->getStatement(),
            RETURN_RESULT => ($this->stmt instanceof mysqli_result) ? $this->stmt : null,
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
        return $this->fetchAll($mode) ?: false;
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
    public function getStatement(): ?mysqli_stmt
    {
        return ($this->stmt instanceof mysqli_stmt) ? $this->stmt : null;
    }

    /**
     * {@inheritdoc}
     */
    public function fetch(int $returnMode = RETURN_ALL, int $fetchMode = FETCH_OBJ): mixed 
    {
        if ($this->stmt === true) {
            return false;
        }

        $this->assertStatement(true);
        $withMode = self::$fetchModes[$fetchMode] ?? null;

        if ($withMode === null) {
            throw new DatabaseException(
                sprintf('Unsupported database fetch mode: %d. Use FETCH_*', $fetchMode),
                DatabaseException::NOT_SUPPORTED
            );
        }

        if ($this->isStatement()) {
            $this->stmt = $this->stmt->get_result();
        }

        if (!$this->stmt instanceof mysqli_result) {
            return false;
        }

        $method = ($returnMode === RETURN_NEXT || $returnMode === RETURN_STREAM) 
            ? (($withMode === FETCH_OBJ) ? 'fetch_object' : 'fetch_assoc') 
            : 'fetch_all';

        $withMode = ($method === 'fetch_all') 
            ? (self::$fetchModes['mysqli'][$withMode] ?? MYSQLI_ASSOC)
            : null;

        $response = ($withMode === null) 
            ? $this->stmt->{$method}() 
            : $this->stmt->{$method}($withMode);

        if(empty($response)){
            return $response;
        }

        if($fetchMode === FETCH_NUM_OBJ || $fetchMode === FETCH_OBJ){
            return CoreDatabase::toResultObject($response);
        }

        if($fetchMode === FETCH_CLASS && $returnMode === RETURN_NEXT){
            return $this->fetchClass(stdClass::class, $response);
        }

        if(
            $fetchMode === FETCH_COLUMN || 
            $fetchMode === FETCH_KEY_PAIR ||
            $fetchMode === FETCH_NUM
        ){
            $columns = [];
            $isKeyPair = $fetchMode === FETCH_KEY_PAIR;
            $isNum = $fetchMode === FETCH_NUM;

            foreach ($response as $column) {
                if($isKeyPair || $isNum){

                    $values = array_values((array) $column);

                    if($isKeyPair && count($values) != 2){
                        throw new DatabaseException(
                            'FETCH_KEY_PAIR fetch mode requires the result set to contain exactly 2 columns',
                            DatabaseException::NOT_SUPPORTED
                        );
                    }

                    if($isNum){
                        $columns[] = $values;
                    }else{
                        $columns[(string) $values[0]] = $values[1];
                    }
                    continue;
                }

                $columns[] = (is_array($column) || is_object($column)) ? reset($column) : $column;
            }

            return $columns;
        }
 
        return $response;
    }

    /**
     * {@inheritdoc}
     */ 
    public function fetchObject(?string $class = null, mixed ...$arguments): ?object 
    {
        return $this->fetchClass(
            $class, 
            $this->fetch(RETURN_NEXT, FETCH_ASSOC),
            ...$arguments
        );
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
    public function getLastInsertId(?string $name = null): mixed
    {
        return $this->isConnected() ? $this->connection->insert_id : null;
    }

    /**
     * {@inheritdoc}
     */
    public function isStatement(): bool 
    {
        return ($this->stmt instanceof mysqli_stmt);
    }

    /**
     * {@inheritdoc}
     */
    public function isResult(): bool 
    {
        return ($this->stmt instanceof mysqli_result);
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
     * Transform response to class object.
     *
     * @param \T<string> $class The class name to transform (e.g, `stdClass::class`),
     * @param mixed $response The response array, object or false/null if error.
     * @param mixed ...$arguments Optional constructor arguments.
     * 
     * @return \T<object>|null Return class object.
     */
    private function fetchClass(string $class, mixed $response, mixed ...$arguments): ?object
    {
        if (!$response) {
            return null;
        }

        if ($class === null || $class === stdClass::class) {
            return CoreDatabase::toResultObject($response);
        }

        try {
            $reflection = new ReflectionClass($class);

            if (!$reflection->isInstantiable()) {
                throw new DatabaseException(
                    sprintf('Fetch class: %s is not instantiatable.', $class),
                    DatabaseException::ERROR
                );
            }

            $instance = $reflection->newInstance(...$arguments);

            foreach ((array) $response as $name => $value) {
                if ($reflection->hasProperty($name)) {
                    $property = $reflection->getProperty($name);
                    $isSettable = (PHP_VERSION_ID >= 80100) ? !$property->isReadOnly() : true;

                    if(!$isSettable){
                        continue;
                    }

                    if($property->isStatic()){ 
                        $property->setValue($value);
                    }else{
                        $property->setValue($instance, $value);
                    }
                }
            }

            return $instance;

        } catch (Throwable $e) {
            $error = sprintf('FETCH_CLASS error: %s, %s', $class, $e->getMessage());

            if (PRODUCTION) {
                Logger::dispatch('error', $error, [
                    'class' => $class,
                    'code'  => $e->getCode()
                ]);
                return null;
            }

            throw new DatabaseException($error, $e->getCode(), $e);
        }
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
        $isStatement = $this->isStatement();

        if(
            ($assertResult && ($isStatement || ($this->stmt instanceof mysqli_result))) ||
            !$assertResult && $isStatement
        ){
            return;
        }

        throw new DatabaseException(
            $assertResult 
                ? 'No result found. Ensure a query is prepared correctly.'
                : 'No valid prepared statement to execute.',
            DatabaseException::NO_STATEMENT_TO_EXECUTE
        );
    }

    /**
     * Binds the provided parameters to the prepared statement.
     *
     * This method handles both value-based parameters (via `value()`) and reference-based parameters (via `param()`).
     * It supports emulated prepares to handle repeated named placeholders and internally determines the parameter types.
     *
     * @param array|null $placeholders Optional parameter set passed during `execute()`.
     *
     * @return bool Returns true if binding was successful, false otherwise.
     */
    private function bindParams(?array $placeholders = null): bool 
    {
        if (!$placeholders || $placeholders === []) {
            return false;
        }

        [$types, $values] = $this->prepareValues($placeholders);

        if($values === [] && $types === ''){
            return false;
        }

        array_unshift($values, $types);
  
        return $this->stmt->bind_param(...$values);
    }

    /**
     * Converts placeholder data into type strings and value arrays for `bind_param`.
     *
     * Used when prepare emulation is disabled. Determines type and value for each parameter row.
     *
     * @param array<string,mixed> $placeholders The raw placeholder array to process.
     *
     * @return array{string,array,string} A tuple containing:
     *   - string: Parameter types (e.g., "iss").
     *   - array: Values to bind.
     */
    private function defaultPrepares(array $placeholders): array 
    {
        if (!$placeholders) {
            return ['', []];
        }

        $types = '';
        $values = [];

        foreach ($placeholders as $name => &$row) {
            [$type, $value] = $this->getRow($row);
            $types .= $type;
            $index = $this->metadata['positions'][ltrim($name, ':')] ?? null;

            if($index === null){
                $values[] = $value;
            }else{
                $values[$index] = $value;
            }
        }
        
        ksort($values);
        unset($row);
        return [$types, $values];
    }

    /**
     * Extracts the binding type and value from a parameter row.
     *
     * Handles both reference-based (`param()`) and value-based (`value()`) parameters. Automatically
     * determines type if not explicitly set in the row.
     *
     * @param mixed &$row The parameter row (array or direct value).
     *
     * @return array{string,mixed} A tuple containing:
     *   - string: The detected or specified parameter type.
     *   - mixed: The value or reference to bind.
     */
    private function getRow(mixed &$row): array 
    {
        $isReference = (is_array($row) && array_key_exists('_isReference', $row))
            ? $row['_isReference']
            : null;

        if($isReference === null){
            return [self::getType($row), $row];
        }

        if ($isReference) {
            $value = &$row['value'];
        } else {
            $value = $row['value'];
        }

        return [$row['type'] ?? self::getType($value), $value];
    }

    /**
     * Normalizes a SQL query by extracting named placeholders and converting it for MySQLi use.
     *
     * This method replaces named placeholders (e.g., `:email`, `:status`) with `?` for MySQLi,
     * and stores the original query and placeholder names in metadata if emulate prepares is enabled.
     *
     * @param string $query SQL query containing named placeholders.
     * @return string Query with placeholders converted to MySQLi format.
     */
    private function normalizeQuery(string $query): string
    {
        $count = 0;
        $positions = [];
        $placeholders = [];

        $converted = preg_replace_callback(
            self::$pattern,
            function (array $match) use (&$count, &$positions, &$placeholders): string {
                // Ensure placeholders maintained the current position
                $name = $match[1] . (isset($positions[$match[1]]) ? '_' . ($count + 1) : '');

                $positions[$name] = $count;
                $placeholders[] = $match[1];
                $count++;

                return '?';
            },
            $query
        );

        if ($count === 0) {
            $this->usePrepares = false;
            return $converted;
        }

        $this->metadata = [
            'count' => $count,
            'positions' => $positions,
            'placeholders' => $placeholders,
            'query' => $query
        ];

        $this->usePrepares = self::$isEmulatePrepares;
        return $converted;
    }

    /**
     * Normalizes and prepares query parameters for execution.
     *
     * Handles transformation of repeated named placeholders to ensure compatibility with 
     * MySQLi drivers that do not support reusing the same named parameter more than once.
     * If emulation is disabled or unnecessary, it returns the default binding structure.
     *
     * @param array<string,mixed> $params Associative array of named parameters to bind in the query. 
     *                      Will be unset internally once processed.
     *
     * @return array{string,array} Return a tuple containing the types string and the bindings array.
     * @throws DatabaseException If a placeholder is used in the query without a corresponding value.
     */
    private function prepareValues(array &$params): array
    {
        $count = $this->metadata['count'] ?? 0;

        if (!self::$isEmulatePrepares || !$this->usePrepares || $count === 0 || count($params) === $count) {
            $bindings = $this->defaultPrepares($params);
            
            unset($params);
            return $bindings;
        }

        $nameCounts = [];
        $bindings = [];
        $types = '';

        foreach ($this->metadata['placeholders'] as $name) {
            $this->emulatePrepares(
                $name,
                $nameCounts,
                $bindings,
                $params,
                $types
            );
        }
       
        ksort($bindings);
        unset($params);

        return [$types, $bindings];
    }

    /**
     * Rewrites repeated named placeholders into unique keys with proper bindings.
     *
     * This method is called per named placeholder to emulate parameter binding by creating
     * unique keys (e.g., `:name`, `:name_2`, `:name_3`) and collecting their values and types.
     *
     * @param string $name         The original placeholder name (without colon).
     * @param array  $nameCounts   Reference to a map tracking how many times a name appears.
     * @param array  $bindings     Reference to the final list of values to bind by position.
     * @param array  $params       Reference to the original parameters (by name or `:name`).
     * @param string $types        Reference to the growing string of parameter types.
     *
     * @return string Return the rewritten placeholder (e.g., `:name_2`).
     * @throws DatabaseException If the expected named parameter is not present in `$params`.
     */
    private function emulatePrepares(
        string $name, 
        array &$nameCounts, 
        array &$bindings, 
        array &$params, 
        string &$types
    ): string 
    {
        $row = $params[$name] ?? $params[":$name"] ?? self::NO_BIND_KEY;

        if ($row === self::NO_BIND_KEY) {
            throw new DatabaseException(
                "Missing parameter for placeholder '$name' (expected in params array or binding).",
                DatabaseException::NOT_ALLOWED
            );
        }

        $count = $nameCounts[$name] = ($nameCounts[$name] ?? 0) + 1;
        $unique = ($count === 1) ? $name : "{$name}_$count";
        $index = $this->metadata['positions'][$unique] ?? null;

        [$type, $value] = $this->getRow($row);
        $types .= $type;

        if($index === null){
            $bindings[] = $value;
        }elseif(isset($bindings[$index])){
            $bindings[$count] = $value;
        }else{
            $bindings[$index] = $value;
        }

        unset($row);

        return ":$unique";
    }

    /**
     * Initializes the database connection.
     * 
     * This method is called internally and should not be called directly.
     * 
     * @throws DatabaseException Throws if no driver is specified.
     */
    private function newConnection(): void 
    {
        if ($this->connection instanceof mysqli) {
            return;
        }

        $socketPath = null;
        if (NOVAKIT_ENV !== null || $this->config->getValue('socket') || is_command()) {
            $socketPath = $this->config->getValue('socket_path') ?: ini_get('mysqli.default_socket');
        }

        $this->connection = mysqli_init() ?: null;

        if (!$this->connection instanceof mysqli) {
            throw new DatabaseException(
                'Failed to initialize MySQLi instance',
                DatabaseException::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }

        $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

        if ($timeout = $this->config->getValue('timeout')) {
            $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) $timeout);
        }

        $this->connection->real_connect(
            $this->config->getValue('host'),
            $this->config->getValue('username'),
            $this->config->getValue('password'),
            $this->config->getValue('database'),
            $this->config->getValue('port'),
            $socketPath
        );

        $charset = $this->config->getValue('charset');

        if ($charset && !$this->connection->set_charset($charset)) {
            throw new DatabaseException('Failed to set charset: ' . $this->connection->error, $this->connection->errno);
        }
    }
}