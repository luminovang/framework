<?php 
/**
 * Luminova Framework Entry class for building and formatting structured log messages.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Logger;

use \Luminova\Logger\NovaLogger;

class Entry
{
    /**
     * Stores the list of log entries.
     *
     * @var array<int,string> $entries
     */
    private array $entries = [];

    /**
     * Constructs a new Entry instance.
     * 
     * @param string $name Optional log system name.
     */
    public function __construct(protected string $name = '') {}

    /**
     * Returns the full log entries as a single string.
     * This method is automatically called when the object is used in a string context.
     *
     * @return string Return entries as string.
     */
    public function __toString(): string 
    {
        return $this->toString();
    }

    /**
     * Returns the log entries as a single string.
     *
     * @return string Return entries as string.
     */
    public function toString(): string 
    {
        return ($this->entries === []) ? '' : implode(PHP_EOL, $this->entries);
    }

    /**
     * Returns the raw array of individual log entries.
     *
     * @return array<int,string> Return array of log entries.
     */
    public function getEntries(): array 
    {
        return $this->entries;
    }

    /**
     * Adds a new log entry.
     *
     * @param string $level The log level (e.g., info, error, warning).
     * @param string $message The log message.
     * @param array  $context Optional context array with additional debug information.
     *
     * @return self Return instance of entry.
     */
    public function add(string $level, string $message, array $context = []): self
    {
        $this->entries[] = NovaLogger::formatMessage($level, $message, $this->name, $context);

        return $this;
    }
}