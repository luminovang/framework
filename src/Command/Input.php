<?php 
/**
 * Luminova Framework command input handler.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

final class Input 
{
    /**
     * Create a command input object.
     *
     * @param array<string,mixed> $command The parsed executed command.
     * @example - Example:
     * ```
     * $input = new Input([
     *     'command'    => string,        // Original CLI input (without PHP binary)
     *     'name'       => string,        // Resolved command name.
     *     'group'      => string,        // Command group namespace.
     *     'arguments'  => string[],      // Positional arguments (e.g. ['limit=2'])
     *     'options'    => array<string,mixed>, // Named options (e.g. ['no-header' => null])
     *     'input'      => string,        // Full executable command string
     *     'params'     => string[],      // Parsed parameter values
     * ]);
     * ```
     */
    public function __construct(private array $command){}

    /**
     * Replace the command input with new one.
     *
     * @param array<string,mixed> $command The parsed executed command.
     * 
     * @return self Returns instance of command input.
     */
    public function replace(array $command): self 
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Determines if a specific CLI flag is present.
     *
     * Supports both short (-f) and long (--flag) forms.
     *
     * @param string $flag The flag to search for (with or without leading dashes).
     *
     * @return bool Returns true if the flag exists, false otherwise.
     */
    public function has(string $flag): bool
    {
        $options = $this->getOptions();
        $normalized = ltrim($flag, '-');

        if ($options !== []) {
            return array_key_exists($normalized, $options);
        }

        return false;
    }

    /**
     * Check if command is help command.
     * 
     * @return bool Return true if command is help, false otherwise.
     */
    public final function isHelp(): bool 
    {
        $command = $this->getOptions();

        foreach($command as $arg){
            $help = ltrim($arg, '-');

            if ($help === 'help' || $help === 'h') {
                return true;
            }
        }

        return preg_match('/^(-h|--help)$/', $this->getName()) === 1;;
    }

    /**
     * Check if command is novakit.
     *
     * @return bool Return true if is novakit command, otherwise false.
     */
    public function isNovakit(): bool 
    {
        return str_starts_with($this->getInput(), 'novakit');
    }

    /**
     * Gets a single command-line input entry by name, if it doesn't exists return null.
     *
     * @param string $name The command entry name.
     * 
     * @return mixed Return command option query value.
    */
    public function getEntry(string $name): mixed
    {
        return $this->command[$name] ?? null;
    }

    /**
     * Get command argument by index.
     *
     * Supports negative indexes:
     *   -1 => last argument
     *   -2 => second last, etc.
     *
     * @param int $index Index of the argument to retrieve (0-based). 
     *                   Negative indexes count from the end.
     * 
     * @return mixed Returns the argument, or null if not found.
     */
    public function getArgument(int $index = 0): mixed
    {
        if ($index < 0) {
            $index = count($this->command['arguments']) + $index;
        }

        return $this->command['arguments'][$index] ?? null;
    }

    /**
     * Get command arguments.
     * 
     * @return array Return command arguments.
     */
    public function getArguments(): array
    {
        return $this->command['arguments'] ?? [];
    }

    /**
     * Get executed command name.
     * 
     * Alias {@see self::getName()}
     * 
     * @return string|null Return the command name.
     */
    public function getCommand(): ?string
    {
        return $this->getName();
    }

    /**
     * Get executed command name.
     * 
     * Alias {@see self::getCommand()}
     * 
     * @return string|null Return the command name.
     */
    public function getName(): ?string
    {
        return $this->command['name'] ?? null;
    }

    /**
     * Get executed command group namespace.
     * 
     * @return string|null Return the command group name.
     */
    public function getGroup(): ?string
    {
        return $this->command['group'] ?? null;
    }

    /**
     * Get raw executed command string.
     * 
     * @return string|null Return the full passed command, options and arguments.
     */
    public function getInput(): ?string
    {
        return $this->command['input'] ?? null;
    }

    /**
     * Get options value from command arguments.
     * If option key is passed with an empty value true will be return otherwise the default value.
     * 
     * @param string $key Option key to retrieve.
     * @param mixed $default Default value to return (default: false).
     * 
     * @return mixed Return option value, true if empty value, otherwise default value.
     */
    public function getOption(string $key, mixed $default = false): mixed
    {
        $options = $this->getOptions();

        if (array_key_exists($key, $options)) {
            return $options[$key] ?? true;
        }
    
        return $default;
    }

    /**
     * Get options value from command arguments with an alias key to lookup if main key isn't found.
     * If option key is passed with an empty value true will be return otherwise the default value.
     * 
     * @param string $key Option key to retrieve.
     * @param string $alias Option key alias to retrieve. if main key is not found.
     * @param mixed $default Default value to return (default: false).
     * 
     * @return mixed Return option value, true if empty value, otherwise default value.
     */
    public function getAnyOption(string $key, string $alias, mixed $default = false): mixed
    {
        $options = $this->getOptions();

        if (array_key_exists($key, $options)) {
            return $options[$key] ?? true;
        }

        if (array_key_exists($alias, $options)) {
            return $options[$alias] ?? true;
        }
    
        return $default;
    }

    /**
     * Get the verbosity level from CLI options.
     *
     * This method checks for both short (`-v`, `-vv`, `-vvv`) 
     * and long (`--verbose` or `--verbose=<level>`) flags.  
     * Returns an integer level between 0 (silent) and `$max` (most verbose).
     * 
     * Level meaning:
     * - 0 = Silent or default mode
     * - 1 = Verbose
     * - 2 = More verbose
     * - 3 = Debug-level verbosity
     * - n = More-level verbosity
     *
     * @param string $short The short flag key (default: `v`).
     * @param string $long The long flag alias (default: `verbose`).
     * @param int $maxLevel The maximum verbosity level (default: `3`).
     * @param int $default Default verbose level (default: 0).
     *
     * @return int Returns the verbosity level between 0 and `$max` or default if not specified.
     * @example - In Code:
     * 
     * ```php
     * $verbose = $this->input->getVerbose(maxLevel: 5, default: 0);
     * ```
     * 
     * @example - In Command:
     * ```bash
     *   php novakit script -v           # returns 1
     *   php novakit script -vv          # returns 2
     *   php novakit script -vvv         # returns 3
     *   php novakit script --verbose=2  # returns 2
     *   php novakit script              # returns default level 0
     * ```
     */
    public function getVerbose(
        string $short = 'v', 
        string $long = 'verbose', 
        int $maxLevel = 3,
        int $default = 0
    ): int 
    {
        foreach ($this->getOptions() as $opt => $value) {
            if ($long === $opt) {
                return min((int) $value, $maxLevel);
            }
            
            if (preg_match('/^(' . preg_quote($short, '/') . '+)$/', $opt, $match)) {
                return min(strlen($match[1]), $maxLevel);
            }
        }

        return $default;
    }

    /**
     * Returns the command controller class method name.
     * 
     * @return string|null Return the command controller class method or null.
     */
    public function getMethod(): ?string
    {
        return $this->getEntry('classMethod');
    }

    /**
     * Returns the array of options.
     * 
     * @return array Return array of executed command options.
     */
    public function getOptions(): array
    {
        return $this->command['options']??[];
    }

    /**
     * Returns the entire command associative that was executed.
     * 
     * @return array Return an associative array of the entire command information
     */
    public function getArray(): array
    {
        return $this->command;
    }
}