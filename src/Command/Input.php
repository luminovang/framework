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
     *     'params'     => string[],      // Parsed Positional argument values (e.g. ['limit=2'])
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
     * Determine if an option was provided in the command input.
     *
     * Supports both long (--option) and short (-o) forms.
     *
     * @param string $option The option name (e.g. 'verbose' or '--verbose').
     * @param string|null $alias  Optional short alias (e.g. 'v' or '-v').
     *
     * @return bool Returns true if the option or its alias exists, false otherwise.
     */
    public function hasOption(string $option, ?string $alias = null): bool
    {
        $options = $this->getOptions();

        if ($options === []) {
            return false;
        }

        $option = ltrim($option, '-');
        $alias  = $alias ? ltrim($alias, '-') : null;

        if (array_key_exists($option, $options)) {
            return true;
        }

        return $alias !== null && array_key_exists($alias, $options);
    }

    /**
     * Determine if the command is a help command.
     *
     * @return bool Returns true if the command is 'help' or its alias 'h', false otherwise.
     */
    public function isHelp(): bool 
    {
        return $this->is('help', 'h');
    }

    /**
     * Determine if the command was executed via novakit.
     *
     * @return bool Returns true if the command starts with 'novakit', false otherwise.
     */
    public function isNovakit(): bool 
    {
        return str_starts_with($this->getInput(), 'novakit');
    }

    /**
     * Determine if the command is a dry-run.
     *
     * @return bool Returns true if the command includes the 'dry-run' option, false otherwise.
     */
    public function isDryRun(): bool 
    {
        return $this->is('dry-run');
    }

    /**
     * Determine if the command was run with the 'no-header' option.
     *
     * @return bool Returns true if '--no-header' is present, false otherwise.
     */
    public function isNoHeader(): bool 
    {
        return $this->is('no-header');
    }

    /**
     * Determine if the command was run with the 'no-color' option.
     *
     * @return bool Returns true if '--no-color' is present, false otherwise.
     */
    public function isNoColor(): bool 
    {
        return $this->is('no-color');
    }

    /**
     * Determine if the command was run with the 'no-ansi' option.
     *
     * @return bool Returns true if '--no-ansi' is present, false otherwise.
     */
    public function isNoAnsi(): bool 
    {
        return $this->is('no-ansi');
    }

    /**
     * Determine if the command was run with the 'version' option.
     *
     * Checks for '--version' or its short alias '--v'.
     *
     * @return bool Returns true if the command includes '--version' or '--v', false otherwise.
     */
    public function isVersion(): bool
    {
        return $this->is('version', 'v', true);
    }

    /**
     * Determine if the command was run with the 'system-info' option.
     *
     * Checks for '--system-info' only (no short alias defined).
     *
     * @return bool Returns true if the command includes '--system-info', false otherwise.
     */
    public function isSystemInfo(): bool
    {
        return $this->is('system-info', forName: true);
    }

    /**
     * Gets a single command-line input entry by name, if it doesn't exists return null.
     *
     * @param string $name The command entry name 
     *      (e.g, `group`, `arguments`, `command`, `name`, `options`, `input`).
     * 
     * @return mixed Return command option query value.
     */
    public function getEntry(string $name): mixed
    {
        return $this->command[$name] ?? null;
    }

    /**
     * Get command argument param value by index.
     *
     * Supports negative indexes:
     *   -1 => last param
     *   -2 => second last, etc.
     *
     * @param int $index Index of the param to retrieve (0-based). 
     *                   Negative indexes count from the end.
     * 
     * @return mixed Returns the argument param value, or null if not found.
     */
    public function getParam(int $index = 0): mixed
    {
        if ($index < 0) {
            $index = count($this->command['params']) + $index;
        }
    
        return $this->command['params'][$index] ?? null;
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
     * @param bool $value Wether to return arguments value or full arguments raw string (default: true).
     * 
     * @return mixed Returns the argument, or null if not found.
     * @see self::getParam
     */
    public function getArgument(int $index = 0, bool $value = true): mixed
    {
        if ($index < 0) {
            $index = count($this->command['arguments']) + $index;
        }

        $value = $this->command['arguments'][$index] ?? null;

        if($value === null || !is_string($value)){
            return $value;
        }

        if(str_contains($value, '=')){
            $value = explode('=', $value, 2)[1] ?? null;

            if($value === null){
                return null;
            }

            return trim($value);
        }

        return $value;
    }

    /**
     * Get all command arguments.
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
        return $this->command['input'] 
            ?? $this->command['command'] 
            ?? null;
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
     * 
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
     * Returns an integer level between 0 (silent) and max `$verbosity` (most verbose).
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
     * @param int $verbosity The maximum verbosity level (default: `3`).
     * @param int $default Default verbose level (default: 0).
     *
     * @return int Returns the verbosity level between 0 and `$verbosity` or default if not specified.
     * @example - In Code:
     * 
     * ```php
     * $verbose = $this->input->getVerbose(verbosity: 5, default: 0);
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
        int $verbosity = 3,
        int $default = 0
    ): int 
    {
        foreach ($this->getOptions() as $opt => $value) {
            if ($long === $opt) {
                return min((int) $value, $verbosity);
            }
            
            if (preg_match('/^(' . preg_quote($short, '/') . '+)$/', (string) $opt, $match)) {
                return min(strlen($match[1]), $verbosity);
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
        return $this->command['options'] ?? [];
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

    /**
     * Check if a specific option or its alias was provided in the command input.
     *
     * This method searches both named options and the executed command name
     * to determine if the given option was used.
     *
     * @param string $option The main option name (e.g., 'verbose' for --verbose)
     * @param string|null $alias  Optional single-character alias (e.g., 'v' for -v).
     * @param bool $forName Wether to check command in name only.
     *
     * @return bool Returns true if the option or alias exists, false otherwise.
     */
    private function is(string $option, ?string $alias = null, bool $forName = false): bool 
    {
        $option = ltrim($option, '-');
        $alias  = $alias ? ltrim($alias, '-') : null;

        if(!$forName){
            foreach ($this->getOptions() as $name => $_) {
                $name = ltrim((string) $name, '-');

                if ($name === $option || ($alias && $name === $alias)) {
                    return true;
                }
            }
        }

        return ($this->getName() === "--{$option}")
            || ($alias && $this->getName() === "-{$alias}");
    }
}