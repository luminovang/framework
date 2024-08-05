<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Logger;

/**
 * Defines various logging levels used for categorizing log messages.
 */
final class LogLevel
{
    /**
     * Emergency level: System is unusable. Critical issues that require immediate attention.
     * 
     * @var string EMERGENCY
     */
    public const EMERGENCY = 'emergency';

    /**
     * Alert level: Action must be taken immediately. Usually used for critical issues that need urgent resolution.
     * 
     * @var string ALERT
     */
    public const ALERT     = 'alert';

    /**
     * Critical level: Critical conditions, such as an application or service being down. Requires immediate attention.
     * 
     * @var string CRITICAL
     */
    public const CRITICAL  = 'critical';

    /**
     * Error level: Runtime errors that require attention. Indicates a problem that needs to be investigated.
     * 
     * @var string ERROR
     */
    public const ERROR     = 'error';

    /**
     * Warning level: Warnings about potential issues that are not immediately problematic but may require attention.
     * 
     * @var string WARNING
     */
    public const WARNING   = 'warning';

    /**
     * Notice level: Normal but significant conditions that require attention. Provides useful information.
     * 
     * @var string NOTICE
     */
    public const NOTICE    = 'notice';

    /**
     * Info level: Informational messages that highlight the progress of the application at a coarse-grained level.
     * 
     * @var string INFO
     */
    public const INFO      = 'info';

    /**
     * Debug level: Detailed information used for debugging and diagnosing issues. Typically used during development.
     * 
     * @var string DEBUG
     */
    public const DEBUG     = 'debug';

    /**
     * Exception level: Exception messages or errors, useful for handling uncaught exceptions or error scenarios.
     * 
     * @var string EXCEPTION
     */
    public const EXCEPTION = 'exception';

    /**
     * PHP level: PHP errors, including parse errors, runtime errors, and warnings.
     * 
     * @var string PHP
     */
    public const PHP       = 'php_errors';
}
