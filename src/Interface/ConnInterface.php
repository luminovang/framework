<?php 
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

interface ConnInterface
{
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