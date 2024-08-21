<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \PDO;
use \mysqli;

interface ConnInterface
{
    /**
     * Initialized connection.
     * 
     * @param PDO|mysqli|null $conn The database connection object.
    */
    public function __construct(private PDO|mysqli|null $conn = null);

    /**
     * Retrieve the connection object.
     * 
     * @return PDO|mysqli|null Return the connection object, otherwise null.
    */
    public function getConn(): PDO|mysqli|null;
    
    /**
     * Close the connection.
     * 
     * @return void
    */
    public function close(): void;
}