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

use \Luminova\Command\TextUtils;

final class Colors 
{
    /**
     * Text Foreground color list
     *
     * @var array<string,string> $foregroundColors
     */
    protected static array $foregroundColors = [
        'black'        => '0;30',
        'darkGray'     => '1;30',
        'blue'         => '0;34',
        'darkBlue'     => '0;34',
        'lightBlue'    => '1;34',
        'green'        => '0;32',
        'lightGreen'   => '1;32',
        'cyan'         => '0;36',
        'lightCyan'    => '1;36',
        'red'          => '0;31',
        'lightRed'     => '1;31',
        'purple'       => '0;35',
        'lightPurple'  => '1;35',
        'yellow'       => '0;33',
        'lightYellow'  => '1;33',
        'lightGray'    => '0;37',
        'white'        => '1;37',
    ];

    /**
     * Text Background color list
     *
     * @var array<string,string> $backgroundColors
     */
    protected static array $backgroundColors = [
        'black'      => '40',
        'red'        => '41',
        'green'      => '42',
        'yellow'     => '43',
        'blue'       => '44',
        'magenta'    => '45',
        'cyan'       => '46',
        'lightGray'  => '47',
    ];


     /**
     * Returns the given text with the correct color codes for a foreground and optional background color.
     *
     * @param string $text Text to color
     * @param int|null $format Optionally apply text formatting (ex: TextUtils::ANSI_BOLD).
     * @param string|null $foreground Foreground color name
     * @param string|null $background Optional background color name
     *
     * @return string A colored text if color is supported
    */
    public static function apply(string $text, ?int $format = null, ?string $foreground = null, ?string $background = null): string
    {
        if ($text === '' || TextUtils::hasAnsi($text)) {
            return $text;
        }

        $formatCode = ($format === null) ? '' : TextUtils::style($text, $format, false);

        if (!self::isValidColor($foreground, static::$foregroundColors)) {
            return "\033[{$formatCode}m{$text}\033[0m";
        }

        if ($background !== null && !self::isValidColor($background, static::$backgroundColors)) {
            return "\033[{$formatCode}m{$text}\033[0m";
        }

        $colorCode = static::$foregroundColors[$foreground];
        if ($background !== null) {
            $colorCode .= ';' . static::$backgroundColors[$background];
        }

        return "\033[{$formatCode};{$colorCode}m{$text}\033[0m";
    }

    /**
     * Returns the length of formatting and colors to apply to text.
     *
     * @param int|null $format Optionally apply text formatting (ex: TextUtils::ANSI_BOLD).
     * @param string|null $foreground Foreground color name
     * @param string|null $background Optional background color name
     *
     * @return int Return length of ansi formatting.
     */
    public static function length(?int $format = null, ?string $foreground = null, ?string $background = null): int
    {
        $formatCode = ($format === null) ? 0 : strlen(TextUtils::style('T', $format, false)) - 1;

        if (!self::isValidColor($foreground, static::$foregroundColors)) {
            return strlen("\033[m\033[0m") + $formatCode;
        }

        if ($background !== null && !self::isValidColor($background, static::$backgroundColors)) {
            return strlen("\033[m\033[0m") + $formatCode;
        }

        $colorCode = static::$foregroundColors[$foreground];
        if ($background !== null) {
            $colorCode .= ';' . static::$backgroundColors[$background];
        }

        return strlen("\033[{$colorCode}m\033[0m") + $formatCode;
    }

    /**
     * Returns the length of the text excluding any ANSI color codes.
     *
     * @param string $text The text to measure.
     *
     * @return int The length of the text excluding ANSI color codes.
     */
    public static function textLength(string $text): int
    {
        return strlen(preg_replace('/\033\[[^m]*m/', '', $text));
    }

    /**
     * Check if color name exist
     * 
     * @param string|null $color Color name
     * @param array $colors Color mapping array
     *
     * @return bool true or false
    */
    private static function isValidColor(string|null $color, array $colors = []): bool
    {
        if($color === null || $colors === []){
            return false;
        }

        return isset($colors[$color]);
    }
}
