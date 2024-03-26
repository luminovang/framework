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

class TextUtils 
{
    /**
     * @var int ANSI_RESET ansi character reset flag
    */
    public const ANSI_RESET = 0;

    /**
     * @var int ANSI_BOLD ansi character bold flag
    */
    public const ANSI_BOLD = 1;

    /**
     * @var int ANSI_ITALIC ansi character italic flag
    */
    public const ANSI_ITALIC = 3;

    /**
     * @var int ANSI_UNDERLINE ansi character underline flag
    */
    public const ANSI_UNDERLINE = 4;

    /**
     * @var int ANSI_STRIKETHROUGH ansi character strikethrough flag
    */
    public const ANSI_STRIKETHROUGH = 9;

    /**
     * Pads string left
     *
     * @param string $text string to pad
     * @param int $max maximum padding 
     * @param string $char Padding character
     * 
     * @return string
    */
    public static function leftPad(string $text, int $length, string $char = ' '): string 
    {
        return str_pad($text, $length, $char, STR_PAD_LEFT);
    }
    
    /**
     * Pads string right
     *
     * @param string $text string to pad
     * @param int $max maximum padding 
     * @param string $char Padding character
     * 
     * @return string
    */
    public static function rightPad(string $text, int $max, string $char = ' '): string 
    {
        return str_pad($text, $max, $char, STR_PAD_RIGHT);
    }

    /**
     * Pads string to fit same length
     *
     * @param string $text string to pad
     * @param int $max maximum padding 
     * @param int $extra How many extra spaces to add at the end
     * @param int $index index of
     * 
     * @return string
    */
    public static function padding(string $text, int $max, int $extra = 2, int $indent = 0): string
    {
        $max += $extra + $indent;

        return str_pad(str_repeat(' ', $indent) . $text, $max);
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
        if ($text === '' || static::hasAnsiMethod($text)) {
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
    public static function hasAnsiMethod(string $text): bool
    {
        $pattern = '/\033\[[0-9;]*m/u';

        return preg_match($pattern, $text) === 1;
    }
}