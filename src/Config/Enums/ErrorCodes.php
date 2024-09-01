<?php
/**
 * Luminova Framework ErrorCodes class defines constants for various error types,
 * including PHP error types, SQLSTATE codes, and custom database error codes.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Config\Enums;

trait ErrorCodes
{
    // PHP Error Types
    /** @var int ERROR */
    public const ERROR = E_ERROR;
    /** @var int PARSE_ERROR */
    public const PARSE_ERROR = E_PARSE;
    /** @var int CORE_ERROR */
    public const CORE_ERROR = E_CORE_ERROR;
    /** @var int COMPILE_ERROR */
    public const COMPILE_ERROR = E_COMPILE_ERROR;

    /** @var int WARNING */
    public const WARNING = E_WARNING;
    /** @var int CORE_WARNING */
    public const CORE_WARNING = E_CORE_WARNING;
    /** @var int COMPILE_WARNING */
    public const COMPILE_WARNING = E_COMPILE_WARNING;
    /** @var int USER_WARNING */
    public const USER_WARNING = E_USER_WARNING;

    /** @var int NOTICE */
    public const NOTICE = E_NOTICE;
    /** @var int USER_NOTICE */
    public const USER_NOTICE = E_USER_NOTICE;
    /** @var int STRICT_NOTICE */
    public const STRICT_NOTICE = E_STRICT;

    /** @var int USER_ERROR */
    public const USER_ERROR = E_USER_ERROR;
    /** @var int RECOVERABLE_ERROR */
    public const RECOVERABLE_ERROR = E_RECOVERABLE_ERROR;

    /** @var int DEPRECATED */
    public const DEPRECATED = E_DEPRECATED;
    /** @var int USER_DEPRECATED */
    public const USER_DEPRECATED = E_USER_DEPRECATED;

    // PDO SQLSTATE Codes
    /** @var string UNABLE_TO_CONNECT */
    public const UNABLE_TO_CONNECT = '08001';
    /** @var string CONNECTION_DENIED */
    public const CONNECTION_DENIED = '08004';
    /** @var string INTEGRITY_CONSTRAINT_VIOLATION */
    public const INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    /** @var string SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION */
    public const SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION = '42000';

    // MySQL Error Codes
    /** @var int ACCESS_DENIED_FOR_USER */
    public const ACCESS_DENIED_FOR_USER = 1044;
    /** @var int ACCESS_DENIED_INVALID_PASSWORD */
    public const ACCESS_DENIED_INVALID_PASSWORD = 1045;
    /** @var int UNKNOWN_DATABASE */
    public const UNKNOWN_DATABASE = 1049;
    /** @var int SYNTAX_ERROR_IN_SQL_STATEMENT */
    public const SYNTAX_ERROR_IN_SQL_STATEMENT = 1064;
    /** @var int TABLE_DOES_NOT_EXIST */
    public const TABLE_DOES_NOT_EXIST = 1146;

    // PostgreSQL Error Codes
    /** @var string INVALID_AUTHORIZATION_SPECIFICATION */
    public const INVALID_AUTHORIZATION_SPECIFICATION = '28000';
    /** @var string INVALID_CATALOG_NAME */
    public const INVALID_CATALOG_NAME = '3D000';

    // Custom Luminova Error Codes
    /** @var int DATABASE_ERROR */
    public const DATABASE_ERROR = 1500;
    /** @var int FAILED_ALL_CONNECTION_ATTEMPTS */
    public const FAILED_ALL_CONNECTION_ATTEMPTS = 1503;
    /** @var int CONNECTION_LIMIT_EXCEEDED */
    public const CONNECTION_LIMIT_EXCEEDED = 1509;
    /** @var int INVALID_DATABASE_DRIVER */
    public const INVALID_DATABASE_DRIVER = 1406;
    /** @var int DATABASE_DRIVER_NOT_AVAILABLE */
    public const DATABASE_DRIVER_NOT_AVAILABLE = 1501;
    /** @var int DATABASE_TRANSACTION_READONLY_FAILED */
    public const DATABASE_TRANSACTION_READONLY_FAILED = 1417;
    /** @var int DATABASE_TRANSACTION_FAILED */
    public const DATABASE_TRANSACTION_FAILED = 1420;
    /** @var int TRANSACTION_SAVEPOINT_FAILED */
    public const TRANSACTION_SAVEPOINT_FAILED = 1418;
    /** @var int FAILED_TO_ROLLBACK_TRANSACTION */
    public const FAILED_TO_ROLLBACK_TRANSACTION = 1419;
    /** @var int NO_STATEMENT_TO_EXECUTE */
    public const NO_STATEMENT_TO_EXECUTE = 1499;
    /** @var int VALUE_FORBIDDEN */
    public const VALUE_FORBIDDEN = 1403;
    /** @var int INVALID_ARGUMENTS */
    public const INVALID_ARGUMENTS = 1001;
    /** @var int INVALID */
    public const INVALID = 1002;
    /** @var int RUNTIME_ERROR */
    public const RUNTIME_ERROR = 5001;
    /** @var int CLASS_NOT_FOUND */
    public const CLASS_NOT_FOUND = 5011;
    /** @var int STORAGE_ERROR */
    public const STORAGE_ERROR = 5079;
    /** @var int VIEW_NOT_FOUND */
    public const VIEW_NOT_FOUND = 404;
    /** @var int INPUT_VALIDATION_ERROR */
    public const INPUT_VALIDATION_ERROR = 4070;
    /** @var int ROUTING_ERROR */
    public const ROUTING_ERROR = 4161;
    /** @var int NOT_FOUND */
    public const NOT_FOUND = 4040;
    /** @var int BAD_METHOD_CALL */
    public const BAD_METHOD_CALL = 4051;
    /** @var int CACHE_ERROR */
    public const CACHE_ERROR = 5071;
    /** @var int FILESYSTEM_ERROR */
    public const FILESYSTEM_ERROR = 6204;
    /** @var int COOKIE_ERROR */
    public const COOKIE_ERROR = 4961;
    /** @var int DATETIME_ERROR */
    public const DATETIME_ERROR = 2306;
    /** @var int CRYPTOGRAPHY_ERROR */
    public const CRYPTOGRAPHY_ERROR = 3423;
    /** @var int WRITE_PERMISSION_DENIED */
    public const WRITE_PERMISSION_DENIED = 6205;
    /** @var int READ_PERMISSION_DENIED */
    public const READ_PERMISSION_DENIED = 6206;
    /** @var int READ_WRITE_PERMISSION_DENIED */
    public const READ_WRITE_PERMISSION_DENIED = 6209;
    /** @var int CREATE_DIR_FAILED */
    public const CREATE_DIR_FAILED = 6207;
    /** @var int SET_PERMISSION_FAILED */
    public const SET_PERMISSION_FAILED = 6208;
    /** @var int JSON_ERROR */
    public const JSON_ERROR = 4180;
    /** @var int SECURITY_ISSUE */
    public const SECURITY_ISSUE = 4973;
    /** @var int MAILER_ERROR */
    public const MAILER_ERROR = 449;
    /** @var int INVALID_CONTROLLER */
    public const INVALID_CONTROLLER = 1003;
    /** @var int INVALID_METHOD */
    public const INVALID_METHOD = 4052;
    /** @var int INVALID_REQUEST_METHOD */
    public const INVALID_REQUEST_METHOD = 4053;
    /** @var int NOT_ALLOWED */
    public const NOT_ALLOWED = 4061;
    /** @var int NOT_ALLOWED */
    public const NOT_SUPPORTED = 4062;
    /** @var int LOGIC_ERROR */
    public const LOGIC_ERROR = 4229;
    /** @var int UNDEFINED */
    public const UNDEFINED = 8790;

    /** @var int HTTP_CLIENT_ERROR */
    public const HTTP_CLIENT_ERROR = 4974;
    /** @var int HTTP_CONNECTION_ERROR */
    public const HTTP_CONNECTION_ERROR = 4975;
    /** @var int HTTP_REQUEST_ERROR */
    public const HTTP_REQUEST_ERROR = 4976;
    /** @var int HTTP_SERVER_ERROR */
    public const HTTP_SERVER_ERROR = 4977;

    /** @var int TERMINATE */
    public const TERMINATE = 1200;
    
    // SQLite Error Codes
    /** @var int DATABASE_IS_FULL */
    public const DATABASE_IS_FULL = 5;
    /** @var int DATABASE_LOCKED */
    public const DATABASE_LOCKED = 6;
    /** @var int CANNOT_OPEN_DATABASE_FILE */
    public const CANNOT_OPEN_DATABASE_FILE = 14;
}