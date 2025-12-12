<?php 
/**
 * Luminova Framework queue table schemes.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base\Helper;

use \Luminova\Interface\DatabaseInterface;

final class QueueTables
{
    public function __construct(private DatabaseInterface $db, private string $table){}

    public function createMysql(): int
    {
        return $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            priority TINYINT NOT NULL DEFAULT 0,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            retries TINYINT NOT NULL DEFAULT 0,
            auto_delete TINYINT(1) NOT NULL DEFAULT 0,
            forever INT UNSIGNED DEFAULT NULL,
            status ENUM('pending','running','failed','completed','paused') DEFAULT 'pending',
            group_name VARCHAR(150) NOT NULL,
            handler MEDIUMTEXT NOT NULL,
            arguments TEXT DEFAULT NULL,
            signature CHAR(32) NOT NULL,
            outputs LONGTEXT DEFAULT NULL,
            scheduled_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_task_group_signature (group_name, signature),
            KEY idx_group_status (group_name, status),
            KEY idx_group_forever_status (group_name, forever, status),
            KEY idx_group_scheduled (group_name, scheduled_at)
        ) DEFAULT CHARSET=utf8mb4");
    }

    public function createSqlite(): int
    {
        $result = $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            priority INTEGER NOT NULL DEFAULT 0,
            attempts INTEGER NOT NULL DEFAULT 0,
            retries INTEGER NOT NULL DEFAULT 0,
            auto_delete INTEGER NOT NULL DEFAULT 0,
            forever INTEGER DEFAULT NULL,
            status TEXT DEFAULT 'pending'
                CHECK(status IN ('pending','running','failed','completed','paused')),
            group_name TEXT NOT NULL,
            handler TEXT NOT NULL,
            arguments TEXT DEFAULT NULL,
            signature TEXT NOT NULL,
            outputs TEXT DEFAULT NULL,
            scheduled_at TEXT DEFAULT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT NULL
        )");

        if ($result > 0) {
            $this->db->exec("CREATE UNIQUE INDEX IF NOT EXISTS uniq_task_group_signature 
                ON {$this->table} (group_name, signature)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_group_status 
                ON {$this->table} (group_name, status)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_group_forever_status 
                ON {$this->table} (group_name, forever, status)");
            $this->db->exec("CREATE INDEX IF NOT EXISTS idx_group_scheduled 
                ON {$this->table} (group_name, scheduled_at)");
        }

        return $result;
    }

    public function createSqlServer(): int
    {
        $result = $this->db->exec("IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='{$this->table}' AND xtype='U')
            CREATE TABLE {$this->table} (
                id INT IDENTITY(1,1) PRIMARY KEY,
                priority TINYINT NOT NULL DEFAULT 0,
                attempts INT NOT NULL DEFAULT 0,
                retries TINYINT NOT NULL DEFAULT 0,
                auto_delete BIT NOT NULL DEFAULT 0,
                forever INT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                group_name VARCHAR(150) NOT NULL,
                handler NVARCHAR(MAX) NOT NULL,
                arguments NVARCHAR(MAX) NULL,
                signature CHAR(32) NOT NULL,
                outputs NVARCHAR(MAX) NULL,
                scheduled_at DATETIME NULL,
                created_at DATETIME DEFAULT GETDATE(),
                updated_at DATETIME NULL,
                CONSTRAINT chk_status CHECK (status IN ('pending','running','failed','completed','paused')),
                CONSTRAINT uniq_task_group_signature UNIQUE (group_name, signature)
            )");

        if ($result > 0) {
            $this->db->exec("CREATE INDEX idx_group_status ON {$this->table} (group_name, status)");
            $this->db->exec("CREATE INDEX idx_group_forever_status ON {$this->table} (group_name, forever, status)");
            $this->db->exec("CREATE INDEX idx_group_scheduled ON {$this->table} (group_name, scheduled_at)");
        }

        return $result;
    }

    public function createOracle(): int
    {
        $result = $this->db->exec("BEGIN
            EXECUTE IMMEDIATE '
                CREATE TABLE {$this->table} (
                    id NUMBER PRIMARY KEY,
                    priority NUMBER(3) DEFAULT 0 NOT NULL,
                    attempts NUMBER DEFAULT 0 NOT NULL,
                    retries NUMBER(3) DEFAULT 0 NOT NULL,
                    auto_delete NUMBER(1) DEFAULT 0 NOT NULL,
                    forever NUMBER NULL,
                    status VARCHAR2(20) DEFAULT ''pending'',
                    group_name VARCHAR2(150) NOT NULL,
                    handler CLOB NOT NULL,
                    arguments CLOB NULL,
                    signature CHAR(32) NOT NULL,
                    outputs CLOB NULL,
                    scheduled_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL,
                    CONSTRAINT chk_status CHECK (status IN (''pending'',''running'',''failed'',''completed'',''paused'')),
                    CONSTRAINT uniq_task_group_signature UNIQUE (group_name, signature)
                )
            ';
        EXCEPTION
            WHEN OTHERS THEN
                IF SQLCODE != -955 THEN RAISE; END IF;
        END;");

        if ($result > 0) {
            $this->db->exec("BEGIN
                EXECUTE IMMEDIATE 'CREATE SEQUENCE {$this->table}_seq START WITH 1';
            EXCEPTION WHEN OTHERS THEN NULL;
            END;");

            $this->db->exec("CREATE OR REPLACE TRIGGER {$this->table}_trg
            BEFORE INSERT ON {$this->table}
            FOR EACH ROW
            BEGIN
                IF :NEW.id IS NULL THEN
                    SELECT {$this->table}_seq.NEXTVAL INTO :NEW.id FROM dual;
                END IF;
            END;");
        }

        return $result;
    }

    public function createMsAccess(): int
    {
        return $this->db->exec("CREATE TABLE {$this->table} (
            id AUTOINCREMENT PRIMARY KEY,
            priority BYTE DEFAULT 0,
            attempts INTEGER DEFAULT 0,
            retries BYTE DEFAULT 0,
            auto_delete BIT DEFAULT 0,
            forever INTEGER,
            status TEXT(20) DEFAULT 'pending',
            group_name TEXT(150),
            handler MEMO,
            arguments MEMO,
            signature TEXT(32),
            outputs MEMO,
            scheduled_at DATETIME,
            created_at DATETIME DEFAULT NOW(),
            updated_at DATETIME
        )");
    }
}