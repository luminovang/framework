<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command\Consoles;

use Luminova\Luminova;
use Luminova\Command\Utils\Text;
use Luminova\Command\Utils\Color;
use Luminova\Command\Consoles\CommandsTrait;

final class Commands 
{
    use CommandsTrait;
    
    /**
     * List of available command aliases.
     *
     * @var array<string,array<string,mixed>> $aliases
     */
    private static array $aliases = [
        'http' => 'server',
        'serve' => 'server'
    ];

    /**
     * Get all available commands.
     * 
     * @return array<string,array<string,mixed>> Return all available commands and their information.
     */
    public static function getCommands(): array 
    {
        self::register();
        self::$commands['help']['description'] = self::getDescription();

        return self::$commands;
    }

    /**
     * Get command information.
     * 
     * @param string $group The command group.
     * 
     * @return array<string,mixed> Return a specific command information.
     */
    public static function get(string $group): array 
    {
        $command = self::getCommands();
        $alias = self::$aliases[$group] ?? 'noop';

        return $command[$group] 
            ?? $command[$alias] 
            ?? [];
    }

    /**
     * Check if command exists.
     * 
     * @param string $group The command group to check.
     * 
     * @return bool Return true if the command exists, false otherwise.
     */
    public static function has(string $group): bool
    {
        return self::get($group) !== [];
    }

    /**
     * Search the closest command match.
     *
     * This is responsible for providing suggestions for command groups 
     * based on a given input string. It utilizes the Levenshtein distance algorithm 
     * to find the closest match from a list of available commands, helping users 
     * identify the intended command when a typo or similar mistake is made.
     * 
     * @param string $input The user input to find a close match for.
     * 
     * @return string|null Return the closest matching command group, or null if no close match is found.
     */
    public static function search(string $input, int $threshold = 3): ?string
    {
        $input = strtolower($input);
        $suggestion = null;
        $shortestDistance = PHP_INT_MAX;

        foreach (self::getCommands() as $command) {
            $group = strtolower($command['group']);

            $aliases = array_map(
                'strtolower', 
                $command['aliases'] ?? []
            );

            if ($input === $group || in_array($input, $aliases, true)) {
                return $command['group'];
            }

            $candidates = array_merge(['main' => $group], $aliases);

            foreach ($candidates as $from => $candidate) {
                $distance = levenshtein($input, $candidate);

                if ($distance < $shortestDistance) {
                    $shortestDistance = $distance;
                    $suggestion = ($from === 'main') ? $command['group'] : $candidate;
                }
            }
        }

        return ($shortestDistance <= $threshold) ? $suggestion : null;
    }

    /**
     * Suggest a similar commands.
     * 
     * @param string $input The user input to suggest a close match for.
     * 
     * @return string Return a formatted suggestion string, or an empty string if no suggestion is found.
     */
    public static function suggest(string $input): string
    {
        $suggestion = self::search($input);

        if(!$suggestion){
            return '';
        }

        $suggestion = Color::style($suggestion, 'cyan');

        return "Do you mean: '{$suggestion}'?";
    }

    /**
     * Get help examples of all commands.
     * 
     * @return array
     */
    public static function getGlobalHelps(?string $group = null, ?int &$largest = null): array 
    {
        $examples = [];
        $last = 0;
        self::register();

        foreach (self::$commands as $command => $value) {
            if ($command === 'help') {
                continue;
            }

            if ($group) {
                $aliases = $value['aliases'] ?? [];

                if (!str_starts_with($command, $group) && !in_array($group, $aliases, true)) {
                    continue;
                }
            }

            $name = strstr($command, ':', true) ?: $command;
            $key = "php novakit {$command} --help";

            if($largest !== null){
                $length = strlen($key);

                if($length > $last){
                    $largest = $length;
                }
            }

            $examples[$key] = $group 
                ? ($value['description'] ?? 'Show available command usage and help.')
                : sprintf(
                    "Display help for %s command group: %s",
                    $command,
                    $name
                );
            }

        if(!$group){
            $examples['php index.php CommandGroup --help'] = 'Display help for app CLI controller commands.';
        }

        return $examples;
    }

    /**
     * Format help command descriptions.
     * 
     * @return string Return formatted help command descriptions.
     */
    private static function getDescription(): string 
    {
        $title = Color::apply(
            "PHP Luminova Novakit Command Help (Novakit: " . Luminova::NOVAKIT_VERSION .
            ", Framework: " . Luminova::VERSION . ")",
            Text::FONT_BOLD,
            'brightBlack'
        );

        $note = Color::apply('IMPORTANT:', Text::FONT_BOLD, 'red');
        $flags = Color::apply('--help (-h)', null, 'yellow');

        return <<<TEXT
            {$title}\n
            Displays help information for the Novakit CLI tool.

            Run Novakit commands from your application root directory:
                php novakit command

            Run controller-based commands from the public directory:
                php index.php command

            {$note}
            The {$flags} option is reserved for displaying command help.
            Do not use it as a custom argument when creating CLI commands.
        TEXT;
    }
}