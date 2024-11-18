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

use \Psr\Log\LoggerInterface;
use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;
use \Luminova\Logger\LoggerTrait;
use \Luminova\Exceptions\InvalidArgumentException;

class LoggerAware implements LoggerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * Initialize NovaLogger
     * 
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(protected ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Get the instance of PSR logger class.
     * 
     * @return LoggerInterface Return instance of logger class inuse.
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Log a message at a specified log level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * @throws InvalidArgumentException If logger does not implement LoggerInterface.
     */
    public function log($level, $message, array $context = []): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Logger: %s must implement Psr\Log\LoggerInterface', 
            $this->logger ? $this->logger::class : 'NULL'
        ));
    }
}