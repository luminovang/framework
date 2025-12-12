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

use \PDO;
use \mysqli;
use \Throwable;
use Luminova\Exceptions\BadMethodCallException;
use Luminova\Interface\DriverConnectionInterface;

/**
 * Manages underlying database driver connection.
 * 
 * This class exposes proxy class to PHP PDO or mysqli database classes.
 * 
 * @mixin PDO
 * @mixin mysqli
 * 
 * @implements DriverConnectionInterface<PDO>
 * @implements DriverConnectionInterface<mysqli>
 */
final class DriverConnection implements DriverConnectionInterface 
{
    /**
     * Creates the wrapper around a native connection.
     * 
     * @param PDO|mysqli|null $conn The native open connection object.
     */
    public function __construct(private PDO|mysqli|null $conn = null) {}

    /**
     * Proxies method calls to the underlying PDO or MySQLi connection.
     *
     * @param string $method The method name.
     * @param array $arguments Arguments passed to the method.
     *
     * @return mixed Returns the result of the underlying method.
     *
     * @throws BadMethodCallException If the connection is unavailable or the method does not exist.
     * @throws Throwable If the underlying method throws an exception.
     */
    public function __call(string $method, array $arguments): mixed
    {
        if ($this->conn === null) {
            throw new BadMethodCallException(sprintf(
                'Cannot call method "%s": the database connection is closed.',
                $method
            ));
        }

        if (!method_exists($this->conn, $method)) {
            throw new BadMethodCallException(sprintf(
                'Method "%s" does not exist on %s.',
                $method,
                $this->conn::class
            ));
        }

        return $this->conn->{$method}(...$arguments);
    }

    /**
     * Retrieves a property from the underlying database connection.
     *
     * @param string $property The property name.
     *
     * @return mixed Returns the property value, or null if the connection is unavailable
     *               or the property does not exist.
     */
    public function __get(string $property): mixed
    {
        if (
            $this->conn === null
            || !property_exists($this->conn, $property)
        ) {
            return null;
        }

        return $this->conn->{$property};
    }

    /**
     * Checks whether a property exists on the underlying database connection.
     *
     * @param string $property The property name.
     *
     * @return bool Returns true if the property exists, or false otherwise.
     */
    public function __isset(string $property): bool
    {
        return $this->conn !== null
            && isset($this->conn->{$property});
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(): bool 
    {
        if(!$this->isMysqli()){
            $this->conn = null;

            return true;
        }

        try{
            return $this->conn->close();
        } finally {
            $this->conn = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConn(): PDO|mysqli|null 
    {
        return $this->conn;
    }

    /**
     * {@inheritdoc}
     */
    public function isMysqli(): bool
    {
        return $this->conn instanceof mysqli;
    }

    /**
     * {@inheritdoc}
     */
    public function isPdo(): bool 
    {
        return $this->conn instanceof PDO;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        if ($this->conn === null) {
            return false;
        }

        try {
            $this->conn->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}