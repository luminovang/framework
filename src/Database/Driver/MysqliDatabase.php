<?php
declare(strict_types=1);
/**
 * Luminova Framework mysqli database driver extension.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Database\Driver;

use \mysqli;
use \Throwable;
use \mysqli_stmt;
use \mysqli_result;
use Luminova\Luminova;
use Luminova\Foundation\Core\Database;
use Luminova\Interface\DatabaseInterface;
use Luminova\Exceptions\{ErrorCode, DatabaseException};
use Luminova\Database\Helpers\{Util, Debugger, DriversTrait};

final class MysqliDatabase implements DatabaseInterface 
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
     * MYSQLI emulate prepares.
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
     * Query metadata.
     * 
     * @var array $metadata
     */
    private array $metadata = [];

    /**
     * Match SQL named placeholders 
     * while ignoring quoted strings and identifiers.
     *
     * @var string PATTERN SQL named placeholder matcher.
     */
    private const PATTERN = '/
        (\'(?:\\\\.|\'\'|[^\'\\\\])*\'|"(?:\\\\.|""|[^"\\\\])*"|`(?:\\\\.|``|[^`\\\\])*`)
        |
        (?<!:):([a-zA-Z_][a-zA-Z0-9_]*)
    /x';

    /**
     * Bind param identifier.
     * 
     * @var string MYSQLI_BIND
     */
    private const MYSQLI_BIND = '__MYSQLI_BIND_VALUE__';

    /**
     * Bind param reference identifier.
     * 
     * @var string MYSQLI_PARAM_REF
     */
    private const MYSQLI_PARAM_REF = '__MYSQLI_PARAM_REFERENCE__';

    /**
     * Flag for unbound placeholder key.
     * 
     * @var string NO_BIND_KEY
     */
    private const NO_BIND_KEY = '__LMV_MYSQLI_NO_BIND_KEY__';

    /**
     * Database driver.
     * 
     * @var string $driver
     */
    private string $driver = 'mysqli';

    /**
     * Supported fetch modes.
     * 
     * @var array<int,bool> FETCH_MODES
     */
    private const FETCH_MODES = [
        FETCH_ASSOC     => true,
        FETCH_BOTH      => true,
        FETCH_OBJ       => true, 
        FETCH_COLUMN    => true,
        FETCH_KEY_PAIR  => true,
        FETCH_NUM       => true,
        FETCH_CLASS     => true,
    ];

    /**
     * Result fetch modes.
     * 
     * @var array<int,mixed> MYSQLI_FETCH_MODES
     */
    private const MYSQLI_FETCH_MODES = [
        FETCH_ASSOC => MYSQLI_ASSOC,
        FETCH_BOTH  => MYSQLI_BOTH,
        FETCH_NUM   => MYSQLI_NUM
    ];

    use DriversTrait;

    /**
     * {@inheritdoc}
     */
    public function __construct(Database $config) 
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        $this->config = $config;
        $this->usePrepares = (bool) $this->config->getValue('emulate_prepares');
        
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver(): ?string 
    {
        return $this->isConnected() ? $this->driver : null;
    }

     /**
     * {@inheritdoc}
     */
    public function getVersion(): ?string
    {
        return $this->isConnected() ? $this->connection->server_info : null;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool 
    {
        return (
            $this->connected 
            && $this->connection instanceof mysqli
        );
    }
    

    /**
     * {@inheritdoc}
     */
    public function error(): string 
    {
        return $this->isStatement() 
            ? $this->stmt->error 
            : $this->connection->error;
    }

    /**
     * {@inheritdoc}
     */
    public function errors(): array
    {
        $hasStatement = $this->isStatement();
        $hasConnection = $this->isConnected();

        return [
            'statement' => [
                'errno'   => $hasStatement ? $this->stmt->errno : -1,
                'error'   => $hasStatement ? $this->stmt->error : null,
                'num_rows' => $hasStatement ? $this->rowCount() : null,
                
            ],
            'connection' => [
                'errno' => $hasConnection ? $this->connection->errno : -1,
                'error' => $hasConnection
                    ? $this->connection->error
                    : 'Connection not established',
            ],
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

        print_r(Debugger::debugMySqliDumpParams($this->query));
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query): self 
    {
        $this->assertConnection();
        $this->profiling(true);

        $this->query = ['query' => '', 'params' => []];
        $this->executed = false;
        $this->rowCount = 0;
        $this->metadata = [];

        if($this->onDebug){
            $this->query['raw'] = $query;
        }

        $query = $this->normalizeQuery($query);
        $this->stmt = $this->connection->prepare($query);

        if($this->stmt instanceof mysqli_stmt){
            $this->isSelect = Util::isSqlQuery($query, 'SELECT');
        }

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
        $this->executed = false;
        $this->rowCount = 0;

        $this->stmt = $this->connection->query($query);

        if ($this->stmt) {
            $this->executed = true;
            $rowCount = $this->isResult() 
                ? $this->stmt->num_rows 
                : $this->connection->affected_rows;

            $this->rowCount = max(1, (int) $rowCount);
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
        return $this->__exec($query);
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

        if ($this->inTransaction()) {
            throw new DatabaseException(
                "Cannot set transaction isolation level inside an active transaction",
                ErrorCode::DATABASE_TRANSACTION_FAILED
            );
        }

        try{
            return (bool) $this->__exec(sprintf(
                'SET TRANSACTION ISOLATION LEVEL %s', 
                $mode
            ), true);
        }catch(Throwable $e){
            $this->profiling(false, true, __METHOD__);
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        $this->assertConnection();

        if($this->inTransaction && $name === null){
            throw new DatabaseException(
                'Nested transaction requires a savepoint name',
                ErrorCode::TRANSACTION_SAVEPOINT_FAILED
            );
        }

        $startedTransaction = false;
        $name = $this->parseSavepoint($name, __METHOD__, 1);

        try{
            if(!$this->inTransaction){
                if(!$this->connection->begin_transaction($flags, $name)){
                    return false;
                }

                $startedTransaction = true;
                $this->inTransaction = true;
            }

            if ($name === null) {
                return true;
            }

            return $this->savepoint($name);
        }catch(Throwable $e){
            $this->profiling(false, true, __METHOD__);

            if ($startedTransaction && $this->inTransaction()) {
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
    }

    /**
     * {@inheritdoc}
     */
    public function savepoint(string $name): bool
    {
        $this->assertConnection();

        if (!$this->inTransaction) {
            throw new DatabaseException(
                'Cannot create savepoint outside transaction'
            );
        }

        $name = $this->parseSavepoint($name, __METHOD__, 1);

        try{
            if($this->connection->savepoint($name)){
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

        if (!$this->inTransaction) {
           return true;
        }

        $name = $this->parseSavepoint($name, __METHOD__, 2);

        try{
            $committed = $this->connection->commit($flags, $name);
        }catch(Throwable $e){
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }finally{
            $this->profiling(false, true, __METHOD__);
        }

        return $this->finishTransaction($committed, $name, true);
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(int $flags = 0, ?string $name = null): bool 
    {
        $this->assertConnection();

        if (!$this->inTransaction) {
            return true;
        }

        $name = $this->parseSavepoint($name, __METHOD__, 2);

        try{
            $rollback = $this->connection->rollback($flags, $name);
        }catch(Throwable $e){
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }finally{
            $this->profiling(false, true, __METHOD__);
        }

        return $this->finishTransaction($rollback, $name, true);
    }

    /**
     * {@inheritdoc}
     */
    public function release(string $name): bool 
    {
        $this->assertConnection();

        if (!$this->inTransaction) {
            return false;
        }

        $name = $this->parseSavepoint($name, __METHOD__, 2);

        try{
            $released = $this->connection->release_savepoint($name);
        }catch(Throwable $e){
            throw new DatabaseException(
                $e->getMessage(), 
                $e->getCode(), 
                $e
            );
        }

        return $this->finishTransaction($released, $name);
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
    public static function getType(mixed $value): int  
    {
       return Util::getMySqliTypeFromValue($value, true);
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $param, mixed $value, ?int $type = null): self 
    {
        $this->assertStatement();

        $this->bindValues[$param] = [
            'type'         => ($type === null) ? null : self::fromTypes($type),
            'value'        => $value,
            self::MYSQLI_BIND       => true,
            self::MYSQLI_PARAM_REF  => false,
        ];
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

        $this->bindValues[$param] = [
            'type'         => ($type === null) ? null : self::fromTypes($type),
            'value'        => &$value,
            self::MYSQLI_BIND       => true,
            self::MYSQLI_PARAM_REF  => true,
        ];
        $this->addQueryInfo('params', [$param => $value]);

        return $this;
    }
  
    /**
     * {@inheritdoc}
     */
    public function execute(?array $params = null): bool
    {
        $this->assertStatement();

        try {
            $this->bindParams($params);

            $this->executed = $this->stmt->execute();

            if (!$this->executed || $this->stmt->errno) {
                throw new DatabaseException(
                    $this->stmt->error,
                    $this->stmt->errno
                );
            }

            $this->rowCount = max(1, (int) (
                $this->isSelect
                    ? $this->stmt->num_rows
                    : $this->stmt->affected_rows
            ));

            return true;
        } catch (Throwable $e) {
            if (!$e instanceof DatabaseException) {
                throw new DatabaseException(
                    $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }

            throw $e;
        } finally {
            if ($params !== null && $params !== []) {
                $this->addQueryInfo('params', $params);
            }

            $this->bindValues = [];
            $this->metadata = [];

            $this->profiling(false, fn: __METHOD__);
        }
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
    public function columnCount(): int 
    {
        return ($this->isStatement() || $this->isResult()) 
            ? $this->stmt->field_count 
            : 0;
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
    public function fetch(int $returnMode = RETURN_ALL, int $fetchAS = FETCH_OBJ): mixed 
    {
        if ($this->stmt === true) {
            return false;
        }
      
        if (!isset(self::FETCH_MODES[$fetchAS])) {
            throw new DatabaseException(
                sprintf('Unsupported database fetch mode: %d. Use FETCH_*', $fetchAS),
                ErrorCode::NOT_SUPPORTED
            );
        }

        $this->stmt = $this->getCursorResult();

        if ($this->stmt === false) {
            return false;
        }

        if($returnMode === RETURN_NEXT || $returnMode === RETURN_STREAM) {
            return match($fetchAS) {
                FETCH_CLASS,
                FETCH_OBJ => $this->stmt->fetch_object(),
                FETCH_NUM => $this->stmt->fetch_row(),
                default   => $this->stmt->fetch_assoc()
             };
        }
   
        $fetchMode = self::MYSQLI_FETCH_MODES[$fetchAS] ?? MYSQLI_ASSOC;
        $result = $this->stmt->fetch_all($fetchMode);

        if(empty($result)){
            return $result;
        }

        return match ($fetchAS) {
            FETCH_COLUMN,
            FETCH_KEY_PAIR,
            FETCH_NUM,
            FETCH_CLASS,
            FETCH_OBJ => self::fetchAllResault($fetchAS, $result),
            default   => $result
        };
    }

    /**
     * {@inheritdoc}
     */ 
    public function fetchObject(?string $class = null, mixed ...$arguments): ?object 
    {
        return $this->fetchClassObject($class, RETURN_NEXT, $arguments);
    }

    /**
     * {@inheritdoc}
     */ 
    public function fetchAllObject(?string $class = null, mixed ...$arguments): array 
    {
        return $this->fetchClassObject($class, RETURN_ALL, $arguments) ?? [];
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
        self::$openConnections--;
    }

    /**
     * Finalize transaction.
     * 
     * @param bool $success
     * @return bool Return status. 
     */
    private function finishTransaction(
        bool $success, 
        ?string $name,
        bool $all = false
    ): bool 
    {
        if (!$success) {
            return false;
        }

        if($name){
            unset($this->savepoint[$name]);
        }

        if($all && !$name){
            $this->savepoint = [];
        }

        if ($this->savepoint === []) {
            $this->inTransaction = false;
        }

        return true;
    }

    /**
     * Execute query and return number of raws or count of affected rows.
     * 
     * @param string $query SQL query to execute.
     * @param bool $internal Use internally to omit profiling.
     * 
     * @return int
     */
    private function __exec(string $query, bool $internal = false): int 
    {
        $this->assertConnection();

        if(!$internal){
            $this->profiling(true);

            $this->query = ['query' => '', 'params' => []];
            $this->executed = false;
            $this->rowCount = 0;
        }

        try{
            $result = $this->connection->query($query);
        } finally {
            if(!$internal){
                $this->addQueryInfo('query', $query);
                $this->profiling(false, fn: __METHOD__);
            }
        }

        if ($result === false) {
            return 0;
        }

        $this->executed = true;

        if ($result instanceof mysqli_result) {
            $count = (int) $result->num_rows;
            $result->free();
            return max(1, $count);
        } 

        $count = (int) $this->connection->affected_rows;

        if ($count === -1 && Util::isDDLQuery($query)) {
            return 1;
        }

        return max(1, $count);
    }

    /**
     * Normalize transaction savepoint name.
     * 
     * @param string|null $name Savepoint name.
     * @param int $check 1 if already exist, 2 if not exist.
     * 
     * @return string|null Return normalized name or null.
     * @throws DatabaseException if invalid name.
     */
    private function parseSavepoint(?string $name, string $fn, int $check = 0): ?string 
    {
        if($name === null){
            return null;
        }

        $name = preg_replace('/[^a-zA-Z0-9_]/', '', trim($name));

        if ($name === '') {
            $this->profiling(false, true, $fn);
            throw new DatabaseException(
                'Failed to create an invalid savepoint name.', 
                ErrorCode::TRANSACTION_SAVEPOINT_FAILED
            );
        }

        $prefix = is_numeric($name) ? 'tnx_' : '';
        $name = substr($prefix . $name, 0, 64);

        if($check > 0){
            $isExist = isset($this->savepoint[$name]);
            $err = null;

            if($check === 1 && $isExist){
                $err = 'Savepoint %s already exist';
            }elseif($check === 2 && !$isExist){
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
     * Get result.
     * 
     * @return mysqli_result|bool 
     */
    private function getCursorResult(): mysqli_result|bool 
    {
        if ($this->stmt === true) {
            return false;
        }

        $this->assertStatement(true);

        if ($this->isStatement()) {
            // $this->stmt->store_result();
            $this->stmt = $this->stmt->get_result();
        }

        if ($this->stmt instanceof mysqli_result) {
            return $this->stmt;
        }

        return false;
    }

    /**
     * Fetch query results as instances of a specified class.
     *
     * @param class-string|null $class Class name used for result hydration..
     * @param int $returnMode Result fetch mode.
     * @param array $arguments Constructor arguments passed to the class constructor.
     *
     * @return object|array|null Returns a hydrated object, an array of objects,
     *                           or null when no result exists.
     *
     * @throws DatabaseException If an unsupported return mode is provided.
     */
    private function fetchClassObject(
        ?string $class, 
        int $returnMode,
        array $arguments
    ): ?object 
    {
        if ($this->stmt === true) {
            return null;
        }

        $this->stmt = $this->getCursorResult();

        if ($this->stmt === false) {
            return null;
        }

        $class ??= \stdClass::class;

        $result = match ($returnMode) {
            RETURN_STREAM,
            RETURN_NEXT => $this->stmt->fetch_object($class, $arguments),
            RETURN_ALL  => $this->fetchAllObjects($class, $arguments),
            default => throw new DatabaseException('Invalid return mode.')
        };

        if($result === false || $result === null){
            return null;
        }

        return ($returnMode === RETURN_NEXT) 
            ? $result 
            : (object) $result;
    }

    /**
     * Convert a result set into a structure matching PDO fetch modes.
     *
     * Supports object, numeric, key-pair, and default column value mappings.
     *
     * @param int $mode Fetch mode compatible with PDO-style constants.
     * @param array $response Raw result rows to transform.
     *
     * @return array Returns the transformed result set.
     *
     * @throws DatabaseException If `FETCH_KEY_PAIR` is used with a result
     *                           containing anything other than exactly two columns.
     */
    private static function fetchAllResault(int $mode, array $response): array
    {
        $result = [];
        $isObject = ($mode === FETCH_CLASS || $mode === FETCH_OBJ);
        $isKeyPair = $mode === FETCH_KEY_PAIR;
        $isNum = $mode === FETCH_NUM;

        foreach ($response as $row) {
            if($isObject){
                $result[] = (object) $row;
                continue;
            }
            
            if(!$isKeyPair && !$isNum){
                $result[] = (is_array($row) || is_object($row)) 
                    ? reset($row) 
                    : $row;

                continue;
            }

            $values = array_values((array) $row);

            if($isNum){
                $result[] = $values;
                continue;
            }

            if(count($values) != 2){
                throw new DatabaseException(
                    'FETCH_KEY_PAIR fetch mode requires the result set to contain exactly 2 columns',
                    ErrorCode::NOT_SUPPORTED
                );
            }

            $result[(string) $values[0]] = $values[1];
        }

        return $result;
    }

    /**
     * Fetch all remaining rows as instances of the specified class.
     *
     * Each row is hydrated into a new object instance using the provided
     * constructor arguments.
     *
     * @param class-string $class Fully qualified class name to instantiate.
     * @param array $arguments Constructor arguments passed to each instance.
     *
     * @return array<object> Returns an array of hydrated class instances.
     */
    private function fetchAllObjects(
        string $class,
        array $arguments
    ): array
    {
        $rows = [];

        while ($row = $this->stmt->fetch_object($class, $arguments)) {
            $rows[] = $row;
        }

        return $rows;
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
            ErrorCode::NO_STATEMENT_TO_EXECUTE
        );
    }

    /**
     * Bind parameters to the prepared statement.
     *
     * Supports both:
     * - Value bindings created through value().
     * - Reference bindings created through param().
     *
     * Handles named placeholder emulation and prepares values for MySQLi,
     * which requires bound parameters to be passed by reference.
     *
     * @param array<string,mixed>|null $params Parameters provided during execute().
     *
     * @return bool Returns true when parameters were bound successfully.
     */
    private function bindParams(?array $params = null): bool
    {
        $placeholders = $this->mergeBindings($params);

        if ($placeholders === []) {
            return false;
        }

        [$types, $values] = $this->prepareNamedBindings($placeholders);

        if ($types === '' || $values === []) {
            return false;
        }

        return $this->stmt->bind_param($types, ...$values);
    }

    /**
     * Binds type and value from a parameter row.
     *
     * Handles both reference-based (`param()`) and value-based (`value()`) parameters. Automatically
     * determines type if not explicitly set in the row.
     *
     * @param mixed $row The parameter row (array or direct value).
     *
     * @return array{string,mixed} A tuple containing:
     *   - string: The detected or specified parameter type.
     *   - mixed: The value or reference to bind.
     */
    private function bindValue(mixed &$row): array
    {
        try{
            if (!is_array($row) || !isset($row[self::MYSQLI_BIND])) {
                return [
                    Util::getMySqliTypeFromValue($row),
                    $row
                ];
            }

            if (($row[self::MYSQLI_PARAM_REF] ?? false) === true) {
                $value =& $row['value'];
            } else {
                $value = $row['value'];
            }

            return [
                $row['type'] ?? Util::getMySqliTypeFromValue($value),
                $value
            ];
        } finally {
            unset($value);
        }
    }

    /**
     * Merge stored bindings with runtime parameters.
     *
     * Stored bindings have priority over execution-time parameters.
     * This preserves reference bindings created through param().
     *
     * @param array<string,mixed>|null $params Runtime parameters.
     *
     * @return array<string,mixed>
     */
    private function mergeBindings(?array $params = null): array
    {
        if (!$params) {
            return $this->bindValues;
        }

        if (!$this->bindValues) {
            return $params;
        }

       return array_merge($this->bindValues, $params);
    }

    /**
     * Normalize a SQL query by converting named placeholders to MySQLi positional
     * placeholders.
     *
     * MySQLi only supports positional placeholders (`?`) while PDO supports
     * reusable named placeholders. This method converts named placeholders into
     * positional placeholders and stores placeholder metadata required to rebuild
     * bindings during execution.
     *
     * Example:
     *
     * ```sql
     * SELECT * FROM users WHERE id = :id OR owner_id = :id
     * ```
     *
     * Becomes:
     *
     * ```sql
     * SELECT * FROM users WHERE id = ? OR owner_id = ?
     * ```
     *
     * Metadata keeps both occurrences mapped to the original parameter name.
     *
     * @param string $query SQL query containing named placeholders.
     *
     * @return string Query with named placeholders replaced by `?`.
     */
    private function normalizeQuery(string $query): string
    {
        $placeholders = [];
        $converted = preg_replace_callback(
            self::PATTERN,
            function (array $match) use (&$placeholders): string {
                // Keep quoted strings.
                if ($match[1] !== '') {
                    return $match[1];
                }

                $placeholders[] = $match[2];
                return '?';
            },
            $query
        );

        if ($placeholders === []) {
            return $query;
        }

        $this->metadata = [
            'count'        => count($placeholders),
            'placeholders' => $placeholders,
        ];

        return $converted;
    }

    /**
     * Prepare named parameters for MySQLi binding.
     *
     * Normalizes parameter names, expands repeated named placeholders when
     * prepare emulation is enabled, and returns the binding types and values
     * in the correct placeholder order.
     *
     * @param array<string,mixed> $params Parameters to bind.
     *
     * @return array{string,array} Binding types and ordered values.
     *
     * @throws DatabaseException If a placeholder has no matching parameter.
     * @example - Example:
     * ```sql
     * WHERE id = :id OR parent_id = :id
     * ```
     *
     * Becomes internally:
     * ```php
     * [
     *     ':id'   => value,
     *     ':id_2' => value
     * ]
     * ```
     */
    private function prepareNamedBindings(array $params): array
    {
        $count = $this->metadata['count'] ?? 0;

        // No repeated placeholders; use default binding.
        if (!$this->usePrepares || $count === 0) {
            return $this->defaultPrepares($params);
        }

        $types = '';
        $bindings = [];
        $resolved = [];

        foreach ($this->metadata['placeholders'] as $name) {
            $key = $resolved[$name] ??= self::findNamedBinding(
                $name, 
                $params
            );

            [$type, $value] = $this->bindValue($params[$key]);

            $types .= $type;
            $bindings[] = $value;
        }

        return [$types, $bindings];
    }

    /**
     * Prepare default query bindings.
     *
     * Converts parameter values into MySQLi binding types and values.
     *
     * @param array<string,mixed> $params Parameters to bind.
     *
     * @return array{string,array} Binding types and ordered values.
     */
    private function defaultPrepares(array $params): array
    {
        if (!$params) {
            return ['', []];
        }

        $types = '';
        $values = [];

        foreach ($params as $value) {
            [$type, $value] = $this->bindValue($value);

            $types .= $type;
            $values[] = $value;
        }

        return [$types, $values];
    }

    /**
     * Find named placeholder value key binding.
     *
     * @param string $name
     * @param array $params
     * 
     * @return string
     * @throws DatabaseException
     */
    private static function findNamedBinding(string $name, array $params): string
    {
        return match(true) {
            array_key_exists($name, $params) => $name,
            array_key_exists(":{$name}", $params) => ":{$name}",
            default =>  throw new DatabaseException(
                "Missing binding parameter ':{$name}'.",
                ErrorCode::NOT_ALLOWED
            )
        };
    }

    /**
     * Maps a PHP parameter type constant to the corresponding MySQLi type character.
     * 
     * @param int $type The PHP parameter type constant (e.g., PARAM_INT, PARAM_STR).
     * 
     * @return string The corresponding MySQLi type character ('i', 'd', 's', 'b').
     */
    private static function fromTypes(int $type): string  
    {
        return match ($type) {
            PARAM_INT,
            PARAM_BOOL     => 'i',
            PARAM_FLOAT, 6 => 'd',
            PARAM_LOB      => 'b',
            PARAM_STR,
            PARAM_NULL     => 's',
            default        => 's'
        };
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

        $isCommand = Luminova::isCommand();
        $socketPath = null;
        
        if (NOVAKIT_ENV !== null || $this->config->getValue('socket') || $isCommand) {
            $socketPath = $this->config->getValue('socket_path') ?: ini_get('mysqli.default_socket');

            if(!$socketPath){
                throw new DatabaseException(sprintf(
                    'MySQLi socket path is missing. 
                    Configure either "%s" in your environment or "%s" in php.ini.',
                    'database.socket.path',
                    'mysqli.default_socket'
                ));
            }
        }

        $this->connection = mysqli_init() ?: null;

        if (!$this->connection instanceof mysqli) {
            throw new DatabaseException(
                'Failed to initialize MySQLi instance',
                ErrorCode::DATABASE_DRIVER_NOT_AVAILABLE
            );
        }

        $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

        if ($timeout = $this->config->getValue('timeout')) {
            $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) $timeout);
        }

        $host = $this->config->getValue('host');

        if(
            $host && 
            ($isCommand || (bool) $this->config->getValue('persistent', false)) &&
            !str_starts_with((string) $host, 'p:')
        ){
            $host = "p:{$host}";
        }

        if(!$this->connection->real_connect(
            $host,
            $this->config->getValue('username'),
            $this->config->getValue('password'),
            $this->config->getValue('database'),
            $this->config->getValue('port'),
            $socketPath
        )){
            $this->connection = null;
            throw new DatabaseException(
                'Failed to establish database connection'
            );
        }

       $this->setInitCommands();
       self::$openConnections++;
    }

    /**
     * Apply developers defined command.
     * 
     * @return void 
     */
    private function setInitCommands(): void 
    {
        $charset = $this->config->getValue('charset');
        $commands = (array) $this->config->getValue('commands', []);
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

                if (!$this->__exec(rtrim($command, ';'), true)) {
                    throw new DatabaseException(sprintf(
                            'Failed to execute init command: %s. Error: %s',
                            $command, 
                            $this->connection->error,
                        ), 
                        $this->connection->errno
                    );
                }
            }
        }

        if ($charset && !$hasSetNames) {
            if (!preg_match('/^[a-z0-9_]+$/i', $charset)) {
                throw new DatabaseException(
                    "Invalid MySQL charset: {$charset}", 
                    ErrorCode::VALUE_FORBIDDEN
                );
            }

            $charset = strtolower($charset);

            if ($charset === 'utf8' || $charset === 'utf-8') {
                $charset = 'utf8mb4';
            }

            if (!$this->connection->set_charset($charset)) {
                throw new DatabaseException(sprintf(
                        'Failed to set charset: %s', 
                        $this->connection->error,
                    ), 
                    $this->connection->errno
                );
            }
        }
    }
}