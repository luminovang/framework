<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command\Utils;

final class Text
{
    /**
     * ansi character reset flag.
     * 
     * @var int ANSI_RESET
    */
    public const ANSI_RESET = 0;

    /**
     * ansi character bold flag.
     * 
     * @var int ANSI_BOLD
    */
    public const ANSI_BOLD = 1;

    /**
     * ansi character italic flag.
     * 
     * @var int ANSI_ITALIC
    */
    public const ANSI_ITALIC = 3;

    /**
     * ansi character underline flag.
     * 
     * @var int ANSI_UNDERLINE
    */
    public const ANSI_UNDERLINE = 4;

    /**
     * ansi character strikethrough flag.
     * 
     * @var int ANSI_STRIKETHROUGH 
    */
    public const ANSI_STRIKETHROUGH = 9;

    /**
     * Pads string left
     *
     * @param string $text The string to pad.
     * @param int $length Maximum length of padding.
     * @param string $char Padding character (default: ' ').
     * 
     * @return string Return left padded string.
    */
    public static function padStart(string $text, int $length, string $char = ' '): string 
    {
        return static::padding($text, $length, $char, STR_PAD_LEFT);
    }
    
    /**
     * Pads string right
     *
     * @param string $text The string to pad.
     * @param int $length Maximum length of padding.
     * @param string $char Padding character (default: ' ').
     * 
     * @return string Return right padded string.
    */
    public static function padEnd(string $text, int $length, string $char = ' '): string 
    {
        return static::padding($text, $length, $char, STR_PAD_RIGHT);
    }

    /**
     * Create a border around text.
     *
     * @param string $text The string to pad.
     * @param int $padding Additional optional padding to apply (default: null).
     * 
     * @return string Return text with border round.
    */
    public static function border(string $text, ?int $padding = null): string 
    {
        $text = trim($text, PHP_EOL);
        $padding ??= static::strlen($text);
        $padding = max(0, $padding);
        $topLeft = '┌';
        $topRight = '┐';
        $bottomLeft = '└';
        $bottomRight = '┘';
        $horizontal= '─';
        $vertical = '│';
    
        $horizontalBorder = $topLeft . str_repeat($horizontal, $padding) . $topRight . PHP_EOL;
        $bottomBorder = $bottomLeft . str_repeat($horizontal, $padding) . $bottomRight . PHP_EOL;

        return $horizontalBorder . $vertical . $text . $vertical . PHP_EOL . $bottomBorder;
    }

    /**
     * Create a centered text.
     *
     * @param string $text The string to pad.
     * @param int|null $padding Additional optional padding to apply (default: null).
     * 
     * @return string Return centered text.
    */
    public static function center(string $text, ?int $padding = null): string 
    {
        $padding ??= static::strlen($text) - 2;
        $size = max(0, $padding);
        $leftPadding = floor($size / 2);
        $rightPadding = ceil($size / 2);

        return str_repeat(' ', (int) $leftPadding) . $text . str_repeat(' ', (int) $rightPadding);
    }

    /**
     * Pads string both left and right.
     *
     * @param string $text The string to pad.
     * @param int $length Maximum length of padding.
     * @param string $char Padding character (default: ' ').
     * @param int $position The position to apply padding (e.g, `STR_PAD_*`), (default: STR_PAD_BOTH).
     * 
     * @return string Return padded string.
    */
    public static function padding(
        string $text, 
        int $length, 
        string $char = ' ', 
        int $position = STR_PAD_BOTH
    ): string 
    {
        return str_pad($text, $length, $char, $position);
    }

    /**
     * Pads string to fit same length.
     *
     * @param string $text string to pad.
     * @param int $max The maximum padding to apply.
     * @param int $extra How many extra spaces to add at the end.
     * @param int $indent Optional indent to apply (default: 0).
     * 
     * @return string Return fitted string.
    */
    public static function fit(string $text, int $max, int $extra = 2, int $indent = 0): string
    {
        $max += $extra + $indent;

        return str_pad(str_repeat(' ', $indent) . $text, (int) $max);
    }

    /**
     * Get the length of characters in a string and ignore ANSI that was applied to the text.
     *
     * @param string $string The string to calculate it's length.
     * @param string $encoding Text encoding to use (default: `UTF-8`).
     * 
     * @return int Return the number of characters in the string.
     * > It replace all ANSI color codes and styles with an empty string.
    */
    public static function strlen(?string $string = null, string $encoding = 'UTF-8'): int
    {
        if ($string === null) {
            return 0;
        }

        $string = preg_replace('/\033\[[0-9;]*m/', '', $string);
        return $string ? mb_strlen($string, $encoding) : 0;
    }

    /**
     * Apply ANSI style formatting to a text string.
     *
     * @param string $text The text to which the style will be applied.
     * @param int|null $format The ANSI style constant (e.g., `Text::ANSI_*`) to apply.
     * @param bool $formatted Whether to return the styled text or just the ANSI code (default: true).
     *
     * @return string Returns the styled text or the ANSI code.
     */
    public static function style(
        string $text, 
        ?int $format = null, 
        bool $formatted = true
    ): string
    {
        if ($text === '' || static::hasAnsi($text)) {
            return $text;
        }

        $formatCode = match ($format) {
            self::ANSI_UNDERLINE => '4',
            self::ANSI_BOLD => '1',
            self::ANSI_ITALIC => '3',
            self::ANSI_STRIKETHROUGH => '9',
            self::ANSI_RESET => '0',
            default => '',
        };

        return $formatted ? "\033[{$formatCode}m{$text}\033[0m" : $formatCode;
    }


    /**
     * Determine if the given text contains ANSI escape sequences.
     * 
     * @param string $text The text to check for ANSI codes.
     * 
     * @return bool Returns true if the text contains ANSI codes, otherwise false.
     */
    public static function hasAnsi(string $text): bool
    {
        return preg_match('/\033\[[0-9;]*m/u', $text) === 1;
    }

    /**
     * Get the longest line from a text string that may contain various newline formats.
     * 
     * @param string $text The text to process.
     * 
     * @return array<int,mixed> Returns the longest line as the first element and its length as the second element.
     */
    public static function largest(string $text): array
    {
        $normalized = preg_replace('/\r\n|\r/', "\n", $text);
        $lines = explode("\n", $normalized);

        $longestLine = '';
        $maxLength = 0;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            $lineLength = mb_strlen($trimmedLine);

            if ($lineLength > $maxLength) {
                $longestLine = $trimmedLine;
                $maxLength = $lineLength;
            }
        }

        return [
            $longestLine,
            $maxLength,
        ];
    }

}