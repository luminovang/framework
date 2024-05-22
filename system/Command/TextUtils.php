<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Command;

final class TextUtils 
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
     * @param string $text String to pad.
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
     * @param string $text String to pad.
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
     * @param string $text string to pad.
     * @param int $padding Padding location default is both left and reight.
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
    
        $horizontalBorder = $topLeft . str_repeat($horizontal, (int) $padding) . $topRight . PHP_EOL;
        $bottomBorder = $bottomLeft . str_repeat($horizontal, (int) $padding) . $bottomRight . PHP_EOL;

        return $horizontalBorder . $vertical . $text . $vertical . PHP_EOL . $bottomBorder;
    }

    /**
     * Create a centered text
     *
     * @param string $text string to pad
     * @param int|null $padding maximum padding
     * 
     * @return string Return centered text.
    */
    public static function center(string $text, ?int $padding = null): string 
    {
        $padding ??= static::strlen($text) - 2;
        $size = max(0, $padding);
        $leftPadding = floor($size / 2);
        $rightPadding = ceil($size / 2);

        $centered = str_repeat(' ', (int) $leftPadding) . $text . str_repeat(' ', (int) $rightPadding);

        return $centered;
    }

    /**
     * Pads string both left and right.
     *
     * @param string $text string to pad.
     * @param int $length Maximum length of padding.
     * @param string $char Padding character (default: ' ').
     * @param int $padd Padding location default is both left and reight (default: STR_PAD_BOTH).
     * 
     * @return string Return padded string.
    */
    public static function padding(string $text, int $length, string $char = ' ', int $padd = STR_PAD_BOTH): string 
    {
        return str_pad($text, $length, $char, $padd);
    }

    /**
     * Pads string to fit same length.
     *
     * @param string $text string to pad.
     * @param int $max maximum padding.
     * @param int $extra How many extra spaces to add at the end.
     * @param int $indent Optional indent.
     * 
     * @return string Return fitted string.
    */
    public static function fit(string $text, int $max, int $extra = 2, int $indent = 0): string
    {
        $max += $extra + $indent;

        return str_pad(str_repeat(' ', $indent) . $text, (int) $max);
    }

    /**
     * Get the length of characters in a string and ignore styles 
     *
     * @param string $string Optional string
     * @param string $encoding Text encoding
     * 
     * @return int The number of characters in the string
    */
    public static function strlen(?string $string = null, string $encoding = 'UTF-8'): int
    {
        if ($string === null) {
            return 0;
        }

        // Replace all ANSI color codes and styles with an empty string
        $string = preg_replace('/\033\[[0-9;]*m/', '', $string);

        return mb_strlen($string, $encoding);
    }

    /**
     * Apply style format on text string
     *
     * @param string $text Text to style
     * @param int|null $format  Style to apply text.
     * @param bool $formatted Return a formatted string or string with style code
     * 
     *
     * @return string A style formatted ansi string 
    */
    public static function style(string $text, ?int $format = null, bool $formatted = true): string
    {
        if ($text === '' || static::hasAnsi($text)) {
            return $text;
        }

        $formatCode = match ($format) {
            self::ANSI_UNDERLINE => ';4',
            self::ANSI_BOLD => ';1',
            self::ANSI_ITALIC => ';3',
            self::ANSI_STRIKETHROUGH => ';9',
            self::ANSI_RESET => ';0',
            default => '',
        };

        if($formatted){
            return "\033[{$formatCode};m{$text}\033[0m";
        }

        return $formatCode;
    }

     /**
     * Check if text already has ANSI method in place
     * 
     * @param string $text Text string
     * 
     * @return bool true or false
    */
    public static function hasAnsi(string $text): bool
    {
        $pattern = '/\033\[[0-9;]*m/u';

        return preg_match($pattern, $text) === 1;
    }

     /**
     * Get the largest line from text
     * 
     * @param string $text Text to process.
     * 
     * @return array<int, mixed> Return largest line from text and it length.
    */
    public static function largest(string $text): array 
    {
        $lines = explode("\n", $text);
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
            $maxLength
        ];
    }
}