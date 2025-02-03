<?php 
/**
 * Luminova Framework aware psr logger class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Logger;

use \Psr\Log\AbstractLogger;
use \Psr\Log\LoggerInterface;
use \Psr\Log\LoggerAwareInterface;
use \Psr\Log\LoggerAwareTrait;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\AppException;
use \Throwable;

class LoggerAware extends AbstractLogger implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Initialize LoggerAware with preferred psr logger.
     * 
     * @param LoggerInterface|null $logger The logger class instance.
     */
    public function __construct(protected ?LoggerInterface $logger = null){}

    /**
     * Support for other logger class methods.
     *
     * @param string $method The method name.
     * @param array $arguments Argument to be passed to the method.
     * 
     * @return void 
     * @throws RuntimeException If an error is encountered.
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        $this->assertPsrLogger();
        
        try{
            return $this->logger->{$method}(...$arguments);
        }catch(Throwable $e){
            if($e instanceof AppException){
                throw $e;
            }

            throw new RuntimeException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
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
     * @param array<string|int,mixed> $context Additional context data (optional).
     *
     * @return void
     * @throws InvalidArgumentException If logger does not implement LoggerInterface.
     */
    public function log($level, $message, array $context = []): void
    {
        $this->assertPsrLogger();
        $this->logger->log($level, $message, $context);
    }

    /**
     * Log an exception message.
     *
     * @param Throwable|string $message The EXCEPTION message to log.
     * @param array<string|int,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public function exception(Throwable|string $message, array $context = []): void
    {
        $this->log(
            LogLevel::EXCEPTION, 
            ($message instanceof Throwable) ? $message->getMessage() : $message,
            $context
        );
    }

    /**
     * Log an php message.
     *
     * @param string $message The php message to log.
     * @param array<string|int,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public function php(string $message, array $context = []): void
    {
        $this->log(LogLevel::PHP, $message, $context);
    }

    /**
     * Log an performance metric.
     *
     * @param string $data The profiling data to log.
     * @param array<string|int,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public function metrics(string $data, array $context = []): void
    {
        $this->log(LogLevel::METRICS, $data, $context);
    }

    /**
     * Asserts that the current logger instance implements the PSR LoggerInterface.
     *
     * @throws RuntimeException If the logger does not implement LoggerInterface.
     * @return void
     */
    private function assertPsrLogger(): void 
    {
        if($this->logger instanceof LoggerInterface){
            return;
        }

        throw new RuntimeException(sprintf(
            'Invalid Logger Interface: "%s", logger class: "%s", must implement "%s".', 
            $this->logger ? $this->logger::class : 'NULL',
            LoggerInterface::class,
        ), RuntimeException::NOT_SUPPORTED);
    }
}