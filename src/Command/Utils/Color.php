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

use \Luminova\Command\Utils\Text;

final class Color
{
    /**
     * List of text foreground colors with their ANSI codes.
     *
     * @var array<string, string> $foregroundColors
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
        'darkRed'      => '2;31',
        'darkGreen'    => '2;32',
        'darkYellow'   => '2;33',
        'magenta'      => '0;35',
        'lightMagenta' => '1;35',
        'orange'       => '0;33',
        'pink'         => '1;35',
        'teal'         => '0;36',
        'olive'        => '0;33',
        'navy'         => '0;34',
    ];

    /**
     * List of text background colors with their ANSI codes.
     *
     * @var array<string, string> $backgroundColors
     */
    protected static array $backgroundColors = [
        'black'       => '40',
        'red'         => '41',
        'green'       => '42',
        'yellow'      => '43',
        'blue'        => '44',
        'magenta'     => '45',
        'cyan'        => '46',
        'lightGray'   => '47',
        'darkGray'    => '100',
        'lightRed'    => '101',
        'lightGreen'  => '102',
        'lightYellow' => '103',
        'lightBlue'   => '104',
        'lightMagenta'=> '105',
        'lightCyan'   => '106',
        'white'       => '107',
    ];

    /**
     * Applies foreground and optional background colors to the given text.
     *
     * @param string $text The text to color.
     * @param int|null $format Optional text formatting (e.g., Text::ANSI_BOLD).
     * @param string|null $foreground The foreground color name.
     * @param string|null $background Optional background color name.
     *
     * @return string Return the formatted text with ANSI color codes, or the original text if unsupported.
     */
    public static function apply(string $text, ?int $format = null, ?string $foreground = null, ?string $background = null): string
    {
        if ($text === '' || Text::hasAnsi($text)) {
            return $text;
        }

        $formatCode = ($format === null) ? '' : Text::style($text, $format, false);

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
     * Calculates the length of ANSI formatting for given that was applied to text.
     *
     * @param int|null $format And optional text formatting to include (e.g., Text::ANSI_BOLD).
     * @param string|null $foreground An optional foreground color name to include (default: null).
     * @param string|null $background An optional background color name to include (default: null).
     *
     * @return int Return the total length of ANSI formatting that is included.
     */
    public static function length(?int $format = null, ?string $foreground = null, ?string $background = null): int
    {
        $formatCode = ($format === null) ? 0 : strlen(Text::style('T', $format, false)) - 1;

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
     * Validates if the specified color name exists in the given color mapping array.
     *
     * @param string|null $color The color name to validate.
     * @param array $colors The mapping of color names to ANSI codes.
     *
     * @return bool Return true if the color exists, otherwise false.
     */
    private static function isValidColor(string|null $color, array $colors = []): bool
    {
        if($color === null || $colors === []){
            return false;
        }

        return isset($colors[$color]);
    }
}