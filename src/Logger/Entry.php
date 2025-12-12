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

use \Countable;
use \Stringable;
use \Luminova\Logger\NovaLogger;

class Entry implements Stringable, Countable
{
    /**
     * Stores the list of log entries.
     *
     * @var array<int,string> $entries
     */
    private array $entries = [];

    /**
     * Create a new entry collector.
     *
     * @param string $level Log level applied to all entries (e.g. "info", "error", "warning").
     * @param string|null $name Optional log channel or source name used during formatting.
     *
     * @example - Example:
     * ```php
     * $entry = new Entry('error', 'auth');
     * 
     * $entry->add('User password error');
     * $entry->add('User email error');
     * 
     * $entry->log();
     * ```
     */
    public function __construct(
        protected string $level,
        protected ?string $name = null
    ) {}

    /**
     * Convert entries to string when used in a string context.
     *
     * @return string Concatenated entries separated by PHP_EOL.
     *
     * @example - Example:
     * ```php
     * echo $entry;
     * ```
     */
    public function __toString(): string 
    {
        return $this->toString();
    }

    /**
     * Get all entries as a single string.
     *
     * @return string Concatenated entries, or empty string if none exist.
     *
     * @example - Example:
     * ```php
     * $output = $entry->toString();
     * ```
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
     * Check whether no entries exist.
     *
     * @return bool True if empty, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($entry->isEmpty()) {
     *     // nothing to log
     * }
     * ```
     */
    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * Get the number of collected entries.
     *
     * @return int Total number of entries.
     *
     * @example - Example:
     * ```php
     * $count = $entry->count();
     * ```
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Remove all collected entries.
     *
     * @return static Return instance of entry.
     *
     * @example - Example:
     * ```php
     * $entry->clear();
     * ```
     */
    public function clear(): self
    {
        $this->entries = [];
        return $this;
    }

    /**
     * Add a new entry using the predefined level.
     *
     * @param string $message The log message.
     * @param array  $context Optional context data for interpolation or debugging.
     *
     * @return static Return instance of entry.
     *
     * @example - Example:
     * ```php
     * $entry->add('User login', ['id' => 10]);
     * ```
     */
    public function add(string $message, array $context = []): self
    {
        $this->entries[] = NovaLogger::formatMessage(
            $this->level,
            $message,
            $this->name,
            $context
        );

        return $this;
    }

    /**
     * Dispatch all collected entries using the predefined level.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * $entry->log();
     * ```
     */
    public function log(): void 
    {
        if($this->isEmpty()){
            return;
        }

        Logger::dispatch($this->level, $this->toString());
        $this->clear();
    }
}