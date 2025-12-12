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
use Luminova\Interface\ConnInterface;

final class Driver implements ConnInterface 
{
    /**
     * Initialize.
     * 
     * @param mysqli|PDO $conn Raw drivers connection object.
     */
    public function __construct(private mysqli|PDO|null $conn = null){}

    /**
     * Proxy helper for database methods.
     *
     * @param string $method
     * @param array $arguments
     * 
     * @return mixed
     * @throws \Throwable
     */
    public function __call(string $method, array $arguments): mixed 
    {
        return $this->conn->{$method}(...$arguments);
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(): void 
    {
        $this->conn = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getConn(): mysqli|PDO|null 
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
}