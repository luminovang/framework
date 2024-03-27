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

use \Luminova\Config\Database;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Database\Drivers\DriversInterface;
use \PDO;
use \PDOStatement;
use \PDOException;
use \stdClass;

class PdoDriver implements DriversInterface 
{
    /**
     * PDO Database connection instance
     * 
     * @var PDO $connection 
    */
    private ?PDO $connection = null; 

    /**
     * Pdo statement object
     * 
     * @var PDOStatement $stmt
    */
    private ?PDOStatement $stmt = null;

    /**
     * @var bool $onDebug debug mode flag
    */
    private bool $onDebug = false;

    /**
     * @var bool $connected connection status flag
    */
    private bool $connected = false;

    /**
     * @var null|Database $config Database configuration
    */
    private ?Database $config = null; 

    /**
     * {@inheritdoc}
    */
    public function __construct(Database $config) 
    {
        $this->config = $config;

        try{
            $this->newConnection();
            $this->connected = true;
        }catch(PDOException|DatabaseException $e){
            $this->connected = false;
            DatabaseException::throwException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * {@inheritdoc}
    */
    public static function getDriver(): string
    {
        return 'pdo';
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
     * @return void 
     * @throws DatabaseException If no driver is specified
     * @throws PDOException
    */
    private function newConnection(): void
    {
        if ($this->connection !== null) {
            return;
        }

        $driver = strtolower($this->config->pdo_driver);
        $dns = $this->dnsConnection($driver);

        if ($dns === '' || ($driver === "sqlite" && empty($this->config->sqlite_path))) {
            throw new DatabaseException("No PDO database driver found for: '{$driver}'");
        }

        $username = $password = null;

        if (!in_array($driver, ['pgsql', 'sqlite'], true)) {
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
     * Get driver dns connection
     *
     * @param string $context Connection driver context name 
     * 
     * @return string
    */
    private function dnsConnection(string $context): string
    {
        $drivers = [
            'cubrid' => "cubrid:dbname={$this->config->database};host={$this->config->host};port={$this->config->port}",
            'dblib' => "dblib:host={$this->config->host};dbname={$this->config->database};port={$this->config->port}",
            'oci' => "oci:dbname={$this->config->database}",
            'pgsql' => "pgsql:host={$this->config->host} port={$this->config->port} dbname={$this->config->database} user={$this->config->username} password={$this->config->password}",
            'sqlite' => "sqlite:/{$this->config->sqlite_path}",
            'mysql' => "mysql:host={$this->config->host};port={$this->config->port};dbname={$this->config->database}"
        ];

        return $drivers[$context] ?? '';
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
    public function dumpDebug(): mixed 
    {
        return $this->onDebug ? $this->stmt->debugDumpParams() : null;
    }

    /**
     * {@inheritdoc}
    */
    public function prepare(string $query): self 
    {
        $this->stmt = $this->connection->prepare($query);

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function query(string $query): self
    {
        $this->stmt = $this->connection->query($query);

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function exec(string $query): int 
    {
        $result = $this->connection->exec($query);

        if($result !== false){
            if($result === 0){
                return 1;
            }

            return $result;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
    */
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
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
        $this->connection->rollBack();
    }

    /**
     * {@inheritdoc}
    */
    public function getType(mixed $value): string|int 
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
    public function bind(string $param, mixed $value, int $type = null): self 
    {
        $this->stmt->bindValue($param, $value, $this->getType($value, $type));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function param(string $param, mixed $value, int $type = null): self 
    {
        $this->stmt->bindParam($param, $value, $this->getType($value, $type));
        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function execute(?array $values = null): void 
    {
        try {
            $this->stmt->execute($values);
        } catch (PDOException $e) {
            DatabaseException::throwException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
    */
    public function rowCount(): int 
    {
        return $this->stmt->rowCount();
    }

    /**
     * {@inheritdoc}
    */
    public function getOne(): mixed 
    {
        return $this->stmt->fetch(PDO::FETCH_OBJ);
    }

    /**
     * {@inheritdoc}
    */
    public function getAll(): mixed 
    {
        return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * {@inheritdoc}
    */
    public function getColumns(): mixed 
    {
        return $this->stmt->fetchAll(PDO::FETCH_COLUMN);
    }

     /**
     * {@inheritdoc}
    */
    public function getStatment(): PDOStatement|\mysqli_stmt|\mysqli_result|bool|null
    {
        return $this->stmt;
    }

    /**
     * {@inheritdoc}
    */
    public function getInt(): int 
    {
        $response = $this->stmt->fetchAll(PDO::FETCH_NUM);

        if (isset($response[0][0])) {
            return (int) $response[0][0];
        }

        return $response ?? 0;
    }

    /**
     * {@inheritdoc}
    */
    public function getResult(string $type = 'object'): array|stdClass
    {
        $response = $this->fetch('all', ($type === 'object') ? FETCH_NUM_OBJ : FETCH_ASSOC);

        if ($response === null) {
            return ($type === 'object') ? new stdClass : [];
        }

        return $response;
    }

    /**
     * {@inheritdoc}
    */ 
    public function fetch(string $type = 'all', int $mode = FETCH_OBJ): mixed  
    {
        if(!$this->stmt){
            return null;
        }

        $modes = [
            FETCH_ASSOC => PDO::FETCH_ASSOC,
            FETCH_BOTH => PDO::FETCH_BOTH,
            FETCH_OBJ => PDO::FETCH_OBJ, 
            FETCH_COLUMN => PDO::FETCH_COLUMN,
            FETCH_COLUMN_ASSOC => PDO::FETCH_ASSOC,
            FETCH_NUM => PDO::FETCH_NUM,
            FETCH_ALL => PDO::FETCH_ASSOC
        ];

        $pdoMode = $modes[$mode] ?? PDO::FETCH_OBJ;
        $method = $type === 'all' ? 'fetchAll' : 'fetch';

        $response = $this->stmt->$method($pdoMode);

        if($mode === FETCH_NUM_OBJ){
            $count = 0;
            $nums = new stdClass();
            foreach ($response as $row) {
                $count++;
                $nums->{$count} = (object) $row;
            }

            return $nums;
        }

        return $response;
    }

    /**
     * {@inheritdoc}
    */ 
    public function getObject(): stdClass 
    {
        return $this->getResult('object');
    }

    /**
     * {@inheritdoc}
    */
    public function getArray(): array 
    {
        return $this->getResult('array');;
    }

    /**
     * {@inheritdoc}
    */
    public function getLastInsertId(): string 
    {
        return (string) $this->connection->lastInsertId();
    }

    /**
     * {@inheritdoc}
    */
    public function free(): void 
    {
        if ($this->stmt !== null) {
            $this->stmt->closeCursor();
            $this->stmt = null;
        }
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
}
