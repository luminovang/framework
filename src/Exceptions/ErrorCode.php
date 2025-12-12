<?php
/**
 * Luminova Framework base exception and error codes.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Exceptions;

class ErrorCode
{
    /** 
     * @var int ERROR 
     */
    public const ERROR = E_ERROR;
    
    /** 
     * @var int PARSE_ERROR 
     */
    public const PARSE_ERROR = E_PARSE;
    
    /** 
     * @var int CORE_ERROR 
     */
    public const CORE_ERROR = E_CORE_ERROR;
    
    /** 
     * @var int COMPILE_ERROR 
     */
    public const COMPILE_ERROR = E_COMPILE_ERROR;
    
    /** 
     * @var int IO_ERROR 
     */
    public const IO_ERROR = 4080;

    /** 
     * @var int WARNING 
     */
    public const WARNING = E_WARNING;
    
    /** 
     * @var int CORE_WARNING 
     */
    public const CORE_WARNING = E_CORE_WARNING;
    
    /** 
     * @var int COMPILE_WARNING 
     */
    public const COMPILE_WARNING = E_COMPILE_WARNING;
    
    /** 
     * @var int USER_WARNING 
     */
    public const USER_WARNING = E_USER_WARNING;

    /** 
     * @var int NOTICE 
     */
    public const NOTICE = E_NOTICE;
    
    /** 
     * @var int USER_NOTICE 
     */
    public const USER_NOTICE = E_USER_NOTICE;

    /** 
     * @var int USER_ERROR 
     */
    public const USER_ERROR = E_USER_ERROR;
    
    /** 
     * @var int RECOVERABLE_ERROR 
     */
    public const RECOVERABLE_ERROR = E_RECOVERABLE_ERROR;

    /** 
     * @var int DEPRECATED 
     */
    public const DEPRECATED = E_DEPRECATED;
    
    /** 
     * @var int USER_DEPRECATED 
     */
    public const USER_DEPRECATED = E_USER_DEPRECATED;

    // PDO SQLSTATE Codes
    /** 
     * @var string UNABLE_TO_CONNECT 
     */
    public const UNABLE_TO_CONNECT = '08001';
    
    /** 
     * @var string CONNECTION_DENIED 
     */
    public const CONNECTION_DENIED = '08004';
    
    /** 
     * @var string INTEGRITY_CONSTRAINT_VIOLATION 
     */
    public const INTEGRITY_CONSTRAINT_VIOLATION = '23000';
    
    /** 
     * @var string SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION 
     */
    public const SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION = '42000';

    // MySQL Error Codes
    /** 
     * @var int ACCESS_DENIED_FOR_USER 
     */
    public const ACCESS_DENIED_FOR_USER = 1044;
    
    /** 
     * @var int ACCESS_DENIED_INVALID_PASSWORD 
     */
    public const ACCESS_DENIED_INVALID_PASSWORD = 1045;
    
    /** 
     * @var int UNKNOWN_DATABASE 
     */
    public const UNKNOWN_DATABASE = 1049;
    
    /** 
     * @var int SYNTAX_ERROR_IN_SQL_STATEMENT 
     */
    public const SYNTAX_ERROR_IN_SQL_STATEMENT = 1064;
    
    /** 
     * @var int TABLE_DOES_NOT_EXIST 
     */
    public const TABLE_DOES_NOT_EXIST = 1146;

    // PostgreSQL Error Codes
    /** 
     * @var string INVALID_AUTHORIZATION_SPECIFICATION 
     */
    public const INVALID_AUTHORIZATION_SPECIFICATION = '28000';
    
    /** 
     * @var string INVALID_CATALOG_NAME 
     */
    public const INVALID_CATALOG_NAME = '3D000';

    // Custom Luminova Error Codes
    /** 
     * @var int EXECUTION_FAILED 
     */
    public const EXECUTION_FAILED = 1510;

    /** 
     * @var int DATABASE_ERROR 
     */
    public const DATABASE_ERROR = 1500;

    /** 
     * @var int DATABASE_PERMISSION_DENIED 
     */
    public const DATABASE_PERMISSION_DENIED = 13;
    
    /** 
     * @var int FAILED_ALL_CONNECTION_ATTEMPTS 
     */
    public const FAILED_ALL_CONNECTION_ATTEMPTS = 1503;
    
    /** 
     * @var int CONNECTION_LIMIT_EXCEEDED 
     */
    public const CONNECTION_LIMIT_EXCEEDED = 1509;
    
    /** 
     * @var int INVALID_DATABASE_DRIVER 
     */
    public const INVALID_DATABASE_DRIVER = 1406;
    
    /** 
     * @var int DATABASE_DRIVER_NOT_AVAILABLE 
     */
    public const DATABASE_DRIVER_NOT_AVAILABLE = 1501;
    
    /** 
     * @var int DATABASE_TRANSACTION_READONLY_FAILED 
     */
    public const DATABASE_TRANSACTION_READONLY_FAILED = 1417;
    
    /** 
     * @var int DATABASE_TRANSACTION_FAILED 
     */
    public const DATABASE_TRANSACTION_FAILED = 1420;
    
    /** 
     * @var int TRANSACTION_SAVEPOINT_FAILED 
     */
    public const TRANSACTION_SAVEPOINT_FAILED = 1418;
    
    /** 
     * @var int FAILED_TO_ROLLBACK_TRANSACTION 
     */
    public const FAILED_TO_ROLLBACK_TRANSACTION = 1419;
    
    /** 
     * @var int NO_STATEMENT_TO_EXECUTE 
     */
    public const NO_STATEMENT_TO_EXECUTE = 1499;
    
    /** 
     * @var int VALUE_FORBIDDEN 
     */
    public const VALUE_FORBIDDEN = 1403;
    
    /** 
     * @var int INVALID_ARGUMENTS 
     */
    public const INVALID_ARGUMENTS = 1001;
    
    /** 
     * @var int INVALID 
     */
    public const INVALID = 1002;

    /** 
     * @var int TERMINATED 
     */
    public const TERMINATED = 10001;
    
    /** 
     * @var int RUNTIME_ERROR 
     */
    public const RUNTIME_ERROR = 5001;
    
    /** 
     * @var int CLASS_NOT_FOUND 
     */
    public const CLASS_NOT_FOUND = 5011;
    
    /** 
     * @var int STORAGE_ERROR 
     */
    public const STORAGE_ERROR = 5079;
    
    /** 
     * @var int VIEW_NOT_FOUND 
     */
    public const VIEW_NOT_FOUND = 404;
    
    /** 
     * @var int INPUT_VALIDATION_ERROR 
     */
    public const INPUT_VALIDATION_ERROR = 4070;
    
    /** 
     * @var int ROUTING_ERROR 
     */
    public const ROUTING_ERROR = 4161;
    
    /** 
     * @var int NOT_FOUND 
     */
    public const NOT_FOUND = 4040;
    
    /** 
     * @var int BAD_METHOD_CALL 
     */
    public const BAD_METHOD_CALL = 4051;
    
    /** 
     * @var int CACHE_ERROR 
     */
    public const CACHE_ERROR = 5071;
    
    /** 
     * @var int FILESYSTEM_ERROR 
     */
    public const FILESYSTEM_ERROR = 6204;
    
    /** 
     * @var int COOKIE_ERROR 
     */
    public const COOKIE_ERROR = 4961;
    
    /** 
     * @var int DATETIME_ERROR 
     */
    public const DATETIME_ERROR = 2306;
    
    /** 
     * @var int CRYPTOGRAPHY_ERROR 
     */
    public const CRYPTOGRAPHY_ERROR = 3423;
    
    /** 
     * @var int WRITE_PERMISSION_DENIED 
     */
    public const WRITE_PERMISSION_DENIED = 6205;
    
    /** 
     * @var int READ_PERMISSION_DENIED 
     */
    public const READ_PERMISSION_DENIED = 6206;
    
    /** 
     * @var int READ_WRITE_PERMISSION_DENIED 
     */
    public const READ_WRITE_PERMISSION_DENIED = 6209;
    
    /** 
     * @var int CREATE_DIR_FAILED 
     */
    public const CREATE_DIR_FAILED = 6207;
    
    /** 
     * @var int SET_PERMISSION_FAILED 
     */
    public const SET_PERMISSION_FAILED = 6208;
    
    /** 
     * @var int JSON_ERROR 
     */
    public const JSON_ERROR = 4180;
    
    /** 
     * @var int SECURITY_ISSUE 
     */
    public const SECURITY_ISSUE = 4973;
    
    /** 
     * @var int MAILER_ERROR 
     */
    public const MAILER_ERROR = 449;
    
    /** 
     * @var int INVALID_CONTROLLER 
     */
    public const INVALID_CONTROLLER = 1003;
    
    /** 
     * @var int INVALID_METHOD 
     */
    public const INVALID_METHOD = 4052;
    
    /** 
     * @var int INVALID_REQUEST_METHOD 
     */
    public const INVALID_REQUEST_METHOD = 4053;
    
    /** 
     * @var int NOT_ALLOWED 
     */
    public const NOT_ALLOWED = 4061;
    
    /** 
     * @var int NOT_ALLOWED 
     */
    public const NOT_SUPPORTED = 4062;
    
    /** 
     * @var int LOGIC_ERROR 
     */
    public const LOGIC_ERROR = 4229;
    
    /** 
     * @var int UNDEFINED 
     */
    public const UNDEFINED = 8790;

    /** 
     * @var int HTTP_RESPONSE_ERROR 
     */
    public const HTTP_RESPONSE_ERROR = 4978;
    
    /** 
     * @var int HTTP_CLIENT_ERROR 
     */
    public const HTTP_CLIENT_ERROR = 4974;
    
    /** 
     * @var int HTTP_CONNECTION_ERROR 
     */
    public const HTTP_CONNECTION_ERROR = 4975;
    
    /** 
     * @var int HTTP_REQUEST_ERROR 
     */
    public const HTTP_REQUEST_ERROR = 4976;
    
    /** 
     * @var int HTTP_SERVER_ERROR 
     */
    public const HTTP_SERVER_ERROR = 4977;

    /** 
     * @var int TERMINATE 
     */
    public const TERMINATE = 1200;
    
    /** 
     * @var int TIMEOUT 
     */
    public const TIMEOUT_ERROR = 1201;
    
    /** 
     * @var int PROCESS_ERROR 
     */
    public const PROCESS_ERROR = 1202;

    // SQLite Error Codes
    /** 
     * @var int DATABASE_IS_FULL 
     */
    public const DATABASE_IS_FULL = 5;
    
    /** 
     * @var int DATABASE_LOCKED 
     */
    public const DATABASE_LOCKED = 6;
    
    /** 
     * @var int CANNOT_OPEN_DATABASE_FILE 
     */
    public const CANNOT_OPEN_DATABASE_FILE = 14;

    /**
     * Retrieves the descriptive name of a given error or exception code.
     *
     * @param string|int $severity The error/exception code to get readable name.
     * @param bool $long If true, returns the long descriptive name, otherwise returns the short name.
     *
     * @return string Return the corresponding error name for the given code.
     */
    public static function getName(string|int $severity, bool $long = false): string 
    {
        $info = self::resolve($severity) 
            ?? ['Unknown error', 'Unknown error code'];

        return $long ? $info[1] : $info[0];
    }

    /**
     * Checks whether the given error or exception code is recognized.
     *
     * @param string|int $severity The error or exception code to check.
     *
     * @return bool Returns `true` if the code is recognized, otherwise `false`.
     *
     * > **Note:** A `false` result does not necessarily mean the code is invalid
     * > it only indicates that Luminova does not recognize the code.
     */
    public static function has(string|int $severity): bool 
    {
        return self::resolve($severity) !== null;
    }

    /**
     * Determine if the error is fatal.
     *
     * Fatal errors are unrecoverable and halt script execution immediately.
     * This includes:
     * - Core runtime errors (E_ERROR)
     * - Parse errors (E_PARSE)
     * - Core startup errors (E_CORE_ERROR)
     * - Compile-time errors (E_COMPILE_ERROR)
     *
     * @param string|int $severity PHP error severity level (E_* constant).
     * 
     * @return bool Return true if the error is fatal, false otherwise.
     */
    public static function isFatal(string|int $severity): bool 
    {
        return in_array($severity, [
            E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR
        ], true);
    }

    /**
     * Determine if the error is critical.
     *
     * Critical errors are severe problems that require immediate attention,
     * but may not always halt execution. This includes:
     * - All fatal errors (see isFatal())
     * - User-generated fatal errors (E_USER_ERROR)
     * - Recoverable fatal errors (E_RECOVERABLE_ERROR)
     *
     * @param string|int $severity PHP error severity level (E_* constant).
     * 
     * @return bool Return true if the error is critical, false otherwise.
     */
    public static function isCritical(string|int $severity): bool 
    {
        return in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true) 
            || self::isFatal($severity);
    }

    /**
     * Get error logging level.
     * 
     * @param string|int $severity The error code or type.
     * 
     * @return string Return error log level by error code.
     */
    public static function getLevel(string|int $severity): string 
    {
        return match ($severity) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'critical',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
            E_PARSE => 'emergency',
            E_NOTICE, E_USER_NOTICE => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'info',
            E_RECOVERABLE_ERROR => 'error',
            E_ALL, 0 => 'exception',
            default => 'php_error'
        };
    }

    /**
     * Resolves a given error or exception code and return array of description.
     *
     * @param string|int $severity The error/exception code to get readable name.
     *
     * @return array<int,string|null Return a list array corresponding error name for the given code or null.
     * @result {string: short-name, string: long-name}
     */
    private static function resolve(string|int $severity): ?array 
    {
        return match ($severity) {
            // PHP Error Constants
            self::ERROR => ['Fatal Error', 'Fatal runtime error'],
            self::PARSE_ERROR => ['Parse Error', 'Parse error'],
            self::CORE_ERROR => ['Core Error', 'PHP core error'],
            self::COMPILE_ERROR => ['Compile Error', 'Compilation error'],
            self::IO_ERROR => ['IO Error', 'Input/output error'],

            self::WARNING => ['Warning', 'Runtime warning'],
            self::CORE_WARNING => ['Core Warning', 'PHP core warning'],
            self::COMPILE_WARNING => ['Compile Warning', 'Compilation warning'],
            self::USER_WARNING => ['User Warning', 'User-generated warning'],

            self::NOTICE => ['Notice', 'Runtime notice'],
            self::USER_NOTICE => ['User Notice', 'User-generated notice'],

            self::USER_ERROR => ['User Error', 'User-generated error'],
            self::RECOVERABLE_ERROR => ['Recoverable Error', 'Catchable fatal error'],

            self::DEPRECATED => ['Deprecated', 'Deprecated feature usage'],
            self::USER_DEPRECATED => ['User Deprecated', 'User-generated deprecation notice'],

            // PDO SQLSTATE Codes
            self::UNABLE_TO_CONNECT => ['Unable to Connect', 'Database connection failed'],
            self::CONNECTION_DENIED => ['Connection Denied', 'Connection denied'],
            self::INTEGRITY_CONSTRAINT_VIOLATION => ['Integrity Violation', 'Integrity constraint violation'],
            self::SQL_SYNTAX_ERROR_OR_ACCESS_VIOLATION => ['SQL Syntax/Access Error', 'SQL syntax or access violation'],

            // MySQL Error Codes
            self::ACCESS_DENIED_FOR_USER => ['Access Denied', 'Access denied for user'],
            self::ACCESS_DENIED_INVALID_PASSWORD => ['Invalid Password', 'Invalid database user password'],
            self::UNKNOWN_DATABASE => ['Unknown Database', 'Unknown database not supported'],
            self::SYNTAX_ERROR_IN_SQL_STATEMENT => ['SQL Syntax Error', 'Syntax error in SQL statement'],
            self::TABLE_DOES_NOT_EXIST => ['Table Missing', 'Table does not exist'],

            // PostgreSQL Error Codes
            self::INVALID_AUTHORIZATION_SPECIFICATION => ['Invalid Auth', 'Invalid authorization specification'],
            self::INVALID_CATALOG_NAME => ['Invalid Catalog', 'Invalid database catalog name'],

            // Custom Luminova Error Codes
            self::EXECUTION_FAILED => ['Execution Failed', 'Execution operation failed'],
            self::DATABASE_ERROR => ['Database Error', 'General database error'],
            self::DATABASE_PERMISSION_DENIED => ['Permission Denied', 'Database Permission Denied'],
            self::FAILED_ALL_CONNECTION_ATTEMPTS => ['Connection Attempts Failed', 'All connection attempts failed'],
            self::CONNECTION_LIMIT_EXCEEDED => ['Connection Limit Exceeded', 'Database connection limit exceeded'],
            self::INVALID_DATABASE_DRIVER => ['Invalid DB Driver', 'Invalid database driver specified'],
            self::DATABASE_DRIVER_NOT_AVAILABLE => ['DB Driver Not Available', 'Database driver not available'],
            self::DATABASE_TRANSACTION_READONLY_FAILED => ['Read-only Transaction Failed', 'Read-only transaction failed'],
            self::DATABASE_TRANSACTION_FAILED => ['Transaction Failed', 'Database transaction failed'],
            self::TRANSACTION_SAVEPOINT_FAILED => ['Savepoint Failed', 'Transaction savepoint failed'],
            self::FAILED_TO_ROLLBACK_TRANSACTION => ['Rollback Failed', 'Failed to rollback transaction'],
            self::NO_STATEMENT_TO_EXECUTE => ['No Statement', 'No statement to execute'],
            self::VALUE_FORBIDDEN => ['Value Forbidden', 'Value not allowed'],
            self::INVALID_ARGUMENTS => ['Invalid Arguments', 'Invalid arguments provided'],
            self::INVALID => ['Invalid', 'Invalid operation'],
            self::TERMINATED  => ['Terminated', 'Operation manually terminated'],
            self::RUNTIME_ERROR => ['Runtime Error', 'Runtime execution error'],
            self::CLASS_NOT_FOUND => ['Class Not Found', 'Class not found'],
            self::STORAGE_ERROR => ['Storage Error', 'Storage operation error'],
            self::VIEW_NOT_FOUND => ['View Not Found', 'View not found'],
            self::INPUT_VALIDATION_ERROR => ['Validation Error', 'Input validation failed'],
            self::ROUTING_ERROR => ['Routing Error', 'Routing error'],
            self::NOT_FOUND => ['Not Found', 'Resource not found'],
            self::BAD_METHOD_CALL => ['Bad Method Call', 'Bad method call'],
            self::CACHE_ERROR => ['Cache Error', 'Cache operation error'],
            self::FILESYSTEM_ERROR => ['Filesystem Error', 'Filesystem error'],
            self::COOKIE_ERROR => ['Cookie Error', 'Cookie handling error'],
            self::DATETIME_ERROR => ['DateTime Error', 'Date/time operation error'],
            self::CRYPTOGRAPHY_ERROR => ['Cryptography Error', 'Cryptography error'],
            self::WRITE_PERMISSION_DENIED => ['Write Permission Denied', 'Write permission denied'],
            self::READ_PERMISSION_DENIED => ['Read Permission Denied', 'Read permission denied'],
            self::READ_WRITE_PERMISSION_DENIED => ['Read/Write Permission Denied', 'Read/write permission denied'],
            self::CREATE_DIR_FAILED => ['Create Directory Failed', 'Failed to create directory'],
            self::SET_PERMISSION_FAILED => ['Set Permission Failed', 'Failed to set file permissions'],
            self::JSON_ERROR => ['JSON Error', 'JSON encoding/decoding error'],
            self::SECURITY_ISSUE => ['Security Issue', 'Security issue detected'],
            self::MAILER_ERROR => ['Mailer Error', 'Mail sending error'],
            self::INVALID_CONTROLLER => ['Invalid Controller', 'Invalid controller'],
            self::INVALID_METHOD => ['Invalid Method', 'Invalid method'],
            self::INVALID_REQUEST_METHOD => ['Invalid Request Method', 'Invalid HTTP request method'],
            self::NOT_ALLOWED => ['Not Allowed', 'Operation not allowed'],
            self::NOT_SUPPORTED => ['Not Supported', 'Operation not supported'],
            self::LOGIC_ERROR => ['Logic Error', 'Logic error'],
            self::UNDEFINED => ['Undefined', 'Undefined error'],

            self::HTTP_RESPONSE_ERROR => ['HTTP Response Error', 'HTTP response error'],
            self::HTTP_CLIENT_ERROR => ['HTTP Client Error', 'HTTP client error'],
            self::HTTP_CONNECTION_ERROR => ['HTTP Connection Error', 'HTTP connection error'],
            self::HTTP_REQUEST_ERROR => ['HTTP Request Error', 'HTTP request error'],
            self::HTTP_SERVER_ERROR => ['HTTP Server Error', 'HTTP server error'],

            self::TERMINATE => ['Terminate', 'Process terminated'],
            self::TIMEOUT_ERROR => ['Timeout', 'Operation timed out'],
            self::PROCESS_ERROR => ['Process Error', 'Process execution error'],

            // SQLite Error Codes
            self::DATABASE_IS_FULL => ['Database Full', 'Database is full'],
            self::DATABASE_LOCKED => ['Database Locked', 'Database is locked'],
            self::CANNOT_OPEN_DATABASE_FILE => ['Cannot Open DB File', 'Cannot open database file'],
            default => null
        };
    }
}