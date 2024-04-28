<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Database;

use \Luminova\Interface\DatabaseInterface;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Application\FileSystem;

class Manager 
{
    /**
     * @var null|DatabaseInterface $db 
    */
    private ?DatabaseInterface $db = null;

    /**
     * @var null|string $table 
    */
    private ?string $table = null;
    
    /**
     * Initializes contructor 
     * 
     * @param DatabaseInterface $db
     * @param null|string $table
    */
    public function __construct(DatabaseInterface $db, ?string $table = null)
    {
        $this->table = $table;
        $this->db = $db;
    }

    /**
     * Set the databse table to backup.
     * 
     * @param string $table
     * @return void 
    */
    public function setTable(string $table): void 
    {
        $this->table = $table;
    }

    /**
     * Export database table and download it to browser as JSON or CSV format.
     * 
     * @param string $as Expirt as csv or json format.
     * @param string $filename Filename to download it as.
     * @param array $columns Table columns to export (default: all)
     * 
     * @throws DatabaseException If invalid format is provided.
     * @throws DatabaseException If unable to create export temp directory.
     * @throws DatabaseException If failed to create export.
    */
    public function export(string $as = 'csv', ?string $filename = null, array $columns = ['*']): bool 
    {
        $filename ??= $this->table;
        $as = strtolower($as);
        if(!in_array($as, ['csv', 'json'], true)){
            static::error("Unsupported export format: {$as} allowed formats [csv, json]");
            return false;
        }

        $directory = path('writeable') . 'temps' . DIRECTORY_SEPARATOR;

        if (!make_dir($directory)) {
            static::error("Failed to create temp directory: $directory");
            return false;
        }

        $count = 0;
        $filepath = $directory . $filename . '.' . $as;
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            static::error("Failed to open file for writing: $filepath");
            return false;
        }

        $columns = ($columns === ['*'])  ? '*' : implode(", ", $columns);
        $values = $this->db->query("SELECT {$columns} FROM " . $this->table)->fetch('all', FETCH_ASSOC);

        if (!empty($values)) {
            if($as === 'csv'){
                $headerWritten = false;
                foreach ($values as $row) {
                    if (!$headerWritten) {
                        fputcsv($handle, array_keys($row));
                        $headerWritten = true;
                    }

                    if(fputcsv($handle, $row)){
                        $count++;
                    }
                }
            }else{
                if(fwrite($handle, json_encode($values))){
                    $count++;
                }
            }

            if($count > 0 && FileSystem::download($filepath, $filename, [], true)){
                $count++;
            }
        }

        fclose($handle);
        unlink($filepath);

        return $count > 0;
    }

    /**
     * Backup database 
     * 
     * @param string $filename Filename to store backup as.
     * 
     * @throws DatabaseException If unable to create backup directory.
     * @throws DatabaseException If failed to create backup.
    */
    public function backup(?string $filename = null): bool 
    {
        $filename ??= uniqid();
        $directory = path('writeable') . 'backups' . DIRECTORY_SEPARATOR;

        if (!make_dir($directory)) {
            static::error("Failed to create backup directory: $directory");
            return false;
        }

        $var = PRODUCTION ? 'database' : 'database.development';
        $databse = env("{$var}.name");
        $filepath = $directory . $filename . '-' . date('d-m-Y-h-i-sa') . '-db.sql';
        $handle = fopen($filepath, 'w');

        if (!$handle) {
            static::error("Failed to open file for writing backup: $filepath");
            return false;
        }

        $structure = $this->db->query("SHOW CREATE DATABASE {$databse}")->fetch('next', FETCH_ASSOC)['Create Database'];

        fwrite($handle, "-- Database structure\n\n");
        fwrite($handle, "$structure;\n\n");


        $tables = $this->db->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetch('all', FETCH_COLUMN);

        foreach ($tables as $table) {
            $tableStructure = $this->db->query("SHOW CREATE TABLE $table")->fetch('next', FETCH_ASSOC)['Create Table'];

            fwrite($handle, "-- Table structure for $table\n\n");
            fwrite($handle, "$tableStructure;\n\n");

            $rows = $this->db->query("SELECT * FROM $table")->fetch('all', FETCH_ASSOC);

            if ($rows) {
                fwrite($handle, "-- Data for $table\n\n");
                foreach ($rows as $row) {
                    $escapedRow = array_map(function ($value) {
                        if (is_string($value)) {
                            return addslashes($value);
                        }

                        return $value;
                    }, $row);

                    $rowValues = implode("', '", $escapedRow);
                    fwrite($handle, "INSERT INTO $table VALUES ('$rowValues');\n");
                }
                fwrite($handle, "\n");
            }
        }

        $triggers = $this->db->query("SHOW TRIGGERS")->fetch('all', FETCH_ASSOC);

        if (!empty($triggers)) {
            fwrite($handle, "-- Triggers\n\n");
            foreach ($triggers as $trigger) {
                fwrite($handle, "DELIMITER //\n");
                fwrite($handle, $trigger['SQL Original Statement']);
                fwrite($handle, "//\n\n");
                fwrite($handle, "DELIMITER ;\n\n");
            }
        }

        fclose($handle);

        return true;
    }

    /**
     * Throw an exception 
     * 
     * @param string $message
     * 
     * @throws DatabaseException
    */
    private static function error(string $message): void
    {
        DatabaseException::throwException($message);
    }
}