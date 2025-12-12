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
namespace Luminova\Debugger; 

use \Luminova\Utility\Math;
use \Luminova\Storage\Filesystem;

final class Tracer
{
    /**
     * The point to trigger break.
     * 
     * @var int $breakAt
     */
    private static int $breakAt  = 0;

    /**
     * Registered breakpoints.
     * 
     * @var array<int,?string> $breakpoints
     */
    private static array $breakpoints = [];

    /**
     * Set the active breakpoint identifier.
     *
     * When a matching breakpoint is triggered via {@see break()},
     * execution will stop and output debug data.
     *
     * @param int $at Breakpoint identifier to activate.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * Tracer::break(2);
     * // Only Tracer::breakpoint(2, ...) will trigger a stop
     * ```
     */
    public static function break(int $at): void
    {
        self::$breakAt = $at;
    }

    /**
     * Get all registered breakpoints.
     *
     * Returns a map of breakpoint identifiers to their labels.
     *
     * @return array<int,string|null> Return all registered breakpoints (index → label).
     *
     * @example - Example:
     * ```php
     * Tracer::breakpoint(1, label: 'Before parsing');
     * Tracer::breakpoint(2, label: 'After parsing');
     *
     * print_r(Tracer::getBreakpoints());
     * // [1 => 'Before parsing', 2 => 'After parsing']
     * ```
     */
    public static function getBreakpoints(): array
    {
        return self::$breakpoints;
    }

    /**
     * Register and optionally trigger a breakpoint.
     *
     * If the given identifier matches the active breakpoint set via {@see break()},
     * this method will:
     * - Execute the optional callback
     * - Capture file and line of invocation
     * - Return or output debug information
     * - Terminate execution (unless return mode is enabled)
     *
     * @param int  $identifier Unique breakpoint identifier.
     * @param mixed|null $data  Optional data to inspect.
     * @param string|null $label Optional label for easier identification.
     * @param (callable(array $debug, array $points):void)|null $onBreak Optional callback executed before stopping.
     * @param bool $return   If true, returns debug info instead of exiting.
     *
     * @return array<string,mixed>|null Returns debug info when triggered in return mode, otherwise null.
     *
     * @example - Basic usage:
     * ```php
     * Tracer::break(1);
     *
     * Tracer::breakpoint(1, $users, 'User list');
     * // Execution stops here and dumps data
     * ```
     *
     * @example - Multiple checkpoints:
     * ```php
     * Tracer::breakpoint(1, $users, 'Before processing');
     * $parsed = process($users);
     * Tracer::breakpoint(2, $parsed, 'After processing');
     * ```
     *
     * @example - Return instead of exit:
     * ```php
     * Tracer::break(1);
     * $point = Tracer::breakpoint(1, $data, 'Inspect', return: true);
     *
     * if ($point !== null) {
     *     print_r($point);
     * }
     * ```
     *
     * @example - Using callback:
     * ```php
     * Tracer::breakpoint(1, $data, 'DB State', function (array $debug, array $points): void {
     *     // e.g. rollback transaction or log state
     * });
     * ```
     */
    public static function breakpoint(
        int $identifier,
        mixed $data = null,
        ?string $label = null,
        ?callable $onBreak = null,
        bool $return = false
    ): ?array 
    {
        self::$breakpoints[$identifier] = $label;

        if (self::$breakAt !== $identifier) {
            return null;
        }

        $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $debug = [
            'identifier' => $identifier,
            'file'       => $frame['file'] ?? null,
            'line'       => $frame['line'] ?? null,
            'label'      => $label,
            'data'       => $data,
        ];

        if ($onBreak !== null) {
            $onBreak($debug, self::$breakpoints);
        }

        if ($return) {
            return $debug;
        }

        exit(var_export($debug, true));
    }

    /**
     * Creates a syntax-highlighted version of a PHP file.
     * 
     * @param string $file File to highlight.
     * @param int $line Line number.
     * @param int $lines Maximum number of lines.
     * 
     * @return string|bool Return html highlight of the passed file.
     */
    public static function highlight(string $file, int $line, int $lines = 15): string|bool
    {
        if ($file === '' || ! is_readable($file)) {
            return false;
        }

        // Set our highlight colors:
        self::highlightColor();
        $source = Filesystem::contents($file);

        if($source === false){
            return false;
        }
          
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $source = explode("\n", highlight_string($source, true));
        $source = str_replace('<br />', "\n", $source[1]);
        $source = explode("\n", str_replace("\r\n", "\n", $source));

        // Get just the part to show
        $start = max($line - (int) round($lines / 2), 0);
        $source = array_splice($source, $start, $lines, true);
        $number = '% ' . strlen((string) ($start + $lines)) . 'd';

        $code = '';
        $spans = 0;

        foreach ($source as $index => $row) {
            $spans += substr_count($row, '<span') - substr_count($row, '</span');
            $row = str_replace(["\r", "\n"], ['', ''], $row);
            $lineNumber = ($index + $start + 1);

            if ($lineNumber === $line) {
                preg_match_all('#<[^>]+>#', $row, $tags);
                
                $editor = self::getIdeEditorUri($file, $lineNumber);
                $code .= sprintf(
                    "<span class=\"line highlight\"><span class=\"number\">{$number}</span>%s %s\n</span>%s",
                    $lineNumber,
                    "<a href=\"{$editor}\" class=\"line-editable\"></a>",
                    strip_tags($row),
                    implode('', $tags[0])
                );
            } else {
                $code .= sprintf(
                    "<span class=\"line\"><span class=\"number\">{$number}</span> %s\n",
                    $lineNumber, 
                    $row
                );
                $spans++;
            }
        }

        if ($spans > 0) {
            $code .= str_repeat('</span>', $spans);
        }

        return '<pre><code>' . $code . '</code></pre>';
    }

    /**
     * Dump one or more values for debugging purposes.
     *
     * Displays variables in a readable format without terminating execution.
     * Output is automatically formatted for both CLI and HTTP environments.
     *
     * The dump includes:
     * - Caller file and line number.
     * - Peak memory usage.
     * - All supplied values.
     *
     * For web requests, output is rendered inside a styled container for
     * improved readability. For CLI execution, output is displayed as plain text.
     *
     * @param int $depth Stack frame depth used to determine the caller file and line number.
     *        Increase when dump() is wrapped by additional helper methods.
     * @param mixed ...$vars One or more values to dump.
     *
     * @return void
     * @see dd() global helper to dump values and terminate execution.
     */
    public static function dump(int $depth = 2, mixed ...$vars): void
    {
        $depth = max(1, $depth);

        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth)[$depth - 1] ?? [];
        $file = htmlspecialchars(
            $caller['file'] ?? 'unknown',
            ENT_QUOTES,
            'UTF-8'
        );
        $line = $caller['line'] ?? 0;
        $time = date('Y-m-d H:i:s');

        $memory = Math::toUnit(
            memory_get_peak_usage(true),
            withName: true
        );

        if (PHP_SAPI === 'cli') {
            echo PHP_EOL;
            echo "Time: {$time}" . PHP_EOL;
            echo "Dumped at {$file}:{$line}" . PHP_EOL;
            echo "Peak Memory: {$memory}" . PHP_EOL;
            echo str_repeat('-', 80) . PHP_EOL;

            foreach ($vars as $var) {
                var_dump($var);
                echo PHP_EOL;
            }

            return;
        }

        echo <<<HTML
        <style>
        .dd-container{font-family: monospace;padding:15px;margin:10px;border:1px solid #ddd;background:#fff;}
        .dd-meta{color:#666;margin-bottom:10px;}
        .dd-dump{background:#f7f7f7;padding:10px;overflow:auto;}
        .dd-pre{margin:0;white-space:pre-wrap;word-break:break-word;}
        </style>
        <div class="dd-container">
            <div class="dd-meta">
                <strong>Time:</strong> {$time}<br>
                <strong>Dumped at:</strong> {$file}:{$line}<br>
                <strong>Peak Memory:</strong> {$memory}
            </div>
        HTML;

        foreach ($vars as $var) {
            echo '<div class="dd-dump"><pre class="dd-pre">';
            var_dump($var);
            echo '</pre></div>';
        }

        echo '</div>';
    }

    /**
     * Build an IDE deep-link for opening a file at a specific line.
     *
     * Detects the configured IDE from the debug environment and returns the
     * correct URI scheme used by editors like VS Code, PhpStorm, Sublime,
     * Atom, and others.
     *
     * If a file path is provided, the returned link opens that file at the
     * given line number. If no file is provided, only the IDE scheme is
     * returned.
     *
     * @param string|null $file Optional absolute file path to open.
     * @param int|string  $line Line number to focus on (defaults to 1).
     *
     * @return string IDE URI scheme or full deep-link.
     */
    public static function getIdeEditorUri(?string $file = null, string|int $line = 1): string 
    {
        $ide = defined('APP_BOOTED') ? strtolower(env('debug.coding.ide', 'vscode')) : 'vscode';
        $scheme = match ($ide) {
            'phpstorm' => 'phpstorm://open?file=',
            'sublime' => 'sublimetext://open?url=file:',
            'vscode' => 'vscode://file',
            'idea' => 'idea//open?file=',
            'mvim' => 'mvim://open/?url=',
            'atom' => 'atom://core/open/file?filename=',
            'txmt' => 'txmt://open?url=file://',
            'vscode-remote' => 'vscode://vscode-remote/',
            default => "{$ide}://open?=file",
        };

        return $file ? $scheme . urlencode($file) . ":{$line}" : $scheme;
    }

    /**
     * Initialize ini_set highlight.
     * 
     * @param bool $forDarkTheme Wether for dark theme.
     * 
     * @return void
     */
    private static function highlightColor(bool $forDarkTheme = true): void
    {
        if (!function_exists('ini_set')) {
            return;
        }

        if ($forDarkTheme) {
            ini_set('highlight.comment', '#767a7e; font-style: italic');
            ini_set('highlight.default', '#c7c7c7');
            ini_set('highlight.html', '#06B');
            ini_set('highlight.keyword', '#f1ce61;');
            ini_set('highlight.string', '#869d6a');
        } else {
            ini_set('highlight.comment', '#6a737d; font-style: italic');
            ini_set('highlight.default', '#24292e');
            ini_set('highlight.html',    '#0550ae');
            ini_set('highlight.keyword', '#d73a49');
            ini_set('highlight.string',  '#032f62');
        }
    }
}