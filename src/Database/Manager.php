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
namespace Luminova\Database;

use \Luminova\Interface\DatabaseInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Exceptions\DatabaseException;
use \Luminova\Storages\FileManager;

final class Manager implements LazyInterface
{
    /**
     * Initializes the Manager class.
     * 
     * @param DatabaseInterface $db The database connection driver instance.
     * @param string|null $table The name of the database table to export (default: null).
     */
    public function __construct(
        private DatabaseInterface $db, 
        private ?string $table = null
    ){}

    /**
     * Set the database table to back up.
     * 
     * @param string $table The name of the database table name.
     * @return void 
     */
    public function setTable(string $table): void 
    {
        $this->table = $table;
    }

    /**
     * Export the database table and download it as JSON or CSV format.
     * 
     * @param string $as Export format: 'csv' or 'json'.
     * @param string|null $filename Filename for the download.
     * @param array $columns Table columns to export (default: all).
     * 
     * @throws DatabaseException If the format is invalid or export fails.
     * @return bool Returns true on success, false on failure.
     */
    public function export(string $as = 'csv', ?string $filename = null, array $columns = ['*']): bool 
    {
        $filename ??= $this->table;
        $as = strtolower($as);

        if(!in_array($as, ['csv', 'json'], true)){
            throw new DatabaseException("Unsupported export format: {$as}. Allowed formats: [csv, json]");
        }

        $directory = root('writeable/temps');

        if (!make_dir($directory)) {
            return false;
        }

        $count = 0;
        $filepath = $directory . $filename . '.' . $as;
        $handle = fopen($filepath, 'w');

        if (!$handle) {
            throw new DatabaseException("Failed to open file for writing: $filepath");
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

            if($count > 0 && FileManager::download($filepath, $filename, [], true)){
                $count++;
            }
        }

        fclose($handle);
        unlink($filepath);

        return $count > 0;
    }

    /**
     * Backup the database.
     * 
     * @param string|null $filename Filename for the backup.
     * @param bool $forTable Whether to create a backup for the specified table or the entire database (default: false).
     * 
     * @throws DatabaseException If unable to create backup directory or backup fails.
     * @return bool Returns true on success, false on failure.
     */
    public function backup(?string $filename = null, bool $forTable = false): bool 
    {
        $filename ??= ($forTable  ? $this->table : uniqid());
        $directory = root('writeable/backups');

        if (!make_dir($directory)) {
            return false;
        }

        return $forTable 
            ? $this->backupDatabaseTable($filename, $directory) 
            : $this->backupDatabase($filename, $directory);
    }

    /**
     * Create a backup for database table.
     * 
     * @param string $filename The backup filename.
     * @param string $directory The backup directory.
     * 
     * @return bool Return true if the backup was created successfully, false otherwise.
    */
    private function backupDatabaseTable(string $filename, string $directory): bool
    {
        $filepath = $directory . $filename . '-' . date('d-m-Y-h-i-sa') . '-tbl.sql';
        $handle = fopen($filepath, 'w');

        if (!$handle) {
            throw new DatabaseException("Failed to open file for writing backup: $filepath");
        }

        $this->writeTableStructure($handle, $this->table);
        //$this->writeTriggers($handle);

        fclose($handle);
        return true;
    }

    /**
     * Create a backup for database.
     * 
     * @param string $filename The backup filename.
     * @param string $directory The backup directory.
     * 
     * @return bool Return true if the backup was created successfully, false otherwise.
     */
    private function backupDatabase(string $filename, string $directory): bool
    {
        $var = (PRODUCTION ? 'database' : 'database.development');
        $database = env("{$var}.name");
        $filepath = $directory . $filename . '-' . date('d-m-Y-h-i-sa') . '-db.sql';
        $handle = fopen($filepath, 'w');

        if (!$handle) {
            throw new DatabaseException("Failed to open file for writing backup: $filepath");
        }

        $structure = $this->db->query("SHOW CREATE DATABASE {$database}")->fetch('next', FETCH_ASSOC)['Create Database'];

        fwrite($handle, "-- Database structure\n\n");
        fwrite($handle, "$structure;\n\n");

        $tables = $this->db->query("SHOW FULL TABLES WHERE Table_Type = 'BASE TABLE'")->fetch('all', FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->writeTableStructure($handle, $table);
        }

        $this->writeTriggers($handle);
        fclose($handle);

        return true;
    }

    /**
     * Write datable triggers.
     * 
     * @param resource $handle The resource handler.
     * 
     * @return void
     */
    private function writeTriggers($handle): void
    {
        if ($handle && $this->db instanceof DatabaseInterface) {
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
        }
    }

    /**
     * Write datable table structures.
     * 
     * @param resource $handle The resource handler.
     * @param string $table The name of the database table name.
     * 
     * @return void
     */
    private function writeTableStructure($handle, string $table): void
    {
        if ($handle && $this->db instanceof DatabaseInterface) {
            $tableStructure = $this->db->query("SHOW CREATE TABLE {$table}")->fetch('next', FETCH_ASSOC)['Create Table'];

            fwrite($handle, "-- Table structure for {$table}\n\n");
            fwrite($handle, "$tableStructure;\n\n");

            $rows = $this->db->query("SELECT * FROM {$table}")->fetch('all', FETCH_ASSOC);

            if ($rows) {
                fwrite($handle, "-- Data for {$table}\n\n");
                foreach ($rows as $row) {
                    $escapedRow = array_map(fn($value) => is_string($value) ? addslashes($value) : $value, $row);
                    $rowValues = implode("', '", $escapedRow);
                    fwrite($handle, "INSERT INTO $table VALUES ('$rowValues');\n");
                }
                fwrite($handle, "\n");
            }
        }
    }
}