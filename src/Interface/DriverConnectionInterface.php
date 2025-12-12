<?php
declare(strict_types=1);
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \PDO;
use \mysqli;

interface DriverConnectionInterface
{
    /**
     * Retrieves the underlying database connection object.
     *
     * @return PDO|mysqli|null Returns the PDO or MySQLi connection object, or null
     *                         if no connection is available.
     *
     * @example - Example:
     * ```php
     * $conn = $driver->getConn();
     *
     * if ($conn instanceof PDO) {
     *     $conn->query('SELECT 1');
     * } elseif ($conn instanceof mysqli) {
     *     $conn->query('SELECT 1');
     * }
     * ```
     */
    public function getConn(): PDO|mysqli|null;
    
    /**
     * Closes the database connection and releases the underlying connection reference.
     *
     * @return bool Return true if connection is close, otherwise false.
     *
     * @example - Example:
     * ```php
     * $driver->close();
     * ```
     */
    public function close(): bool;

    /**
     * Checks whether the database connection is active and can execute queries.
     *
     * Executes a lightweight `SELECT 1` query to verify the connection.
     * Supports both PDO and MySQLi connections.
     *
     * @return bool Returns true if the connection can execute queries, or false
     *               if the connection is unavailable or the query fails.
     */
    public function isConnected(): bool;

    /**
     * Checks whether the underlying database connection uses MySQLi.
     *
     * @return bool Returns true if the connection is a MySQLi instance, or false otherwise.
     */
    public function isMysqli(): bool;

    /**
     * Checks whether the underlying database connection uses PDO.
     *
     * @return bool Returns true if the connection is a PDO instance, or false otherwise.
     */
    public function isPdo(): bool;
}