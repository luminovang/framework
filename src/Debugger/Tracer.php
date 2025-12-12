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

use \Luminova\Storage\Filesystem;

final class Tracer
{
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
        $ide = defined('IS_UP') ? strtolower(env('debug.coding.ide', 'vscode')) : 'vscode';
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