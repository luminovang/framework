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

class Colors 
{
    /**
     * Text Foreground color list
     *
     * @var array<string, string>
     */
    protected static $foregroundColors = [
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
     * @var array<string, string>
     */
    protected static $backgroundColors = [
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
     * Returns the given text with the correct color codes for a foreground and
     * optionally a background color.
     *
     * @param string $text Text to color
     * @param int|null $format Optionally apply text formatting.
     * @param string $foreground Foreground color name
     * @param string|null $background Optional background color name
     * 
     *
     * @return string A colored text if color is supported
    */
    public static function apply(string $text, ?int $format = null, ?string $foreground = null, ?string $background = null): string
    {
        if ($text === '' || TextUtils::hasAnsiMethod($text)) {
            return $text;
        }

        $formatCode = TextUtils::style($text, $format, false);

        if (!static::isValidColor($foreground, static::$foregroundColors)) {
            return "\033[{$formatCode}m{$text}\033[0m";
        }

        if ($background !== null && !static::isValidColor($background, static::$backgroundColors)) {
            return "\033[{$formatCode}m{$text}\033[0m";
        }

        $colorCode = static::$foregroundColors[$foreground];
        if ($background !== null) {
            $colorCode .= ';' . static::$backgroundColors[$background];
        }

        return "\033[{$formatCode};{$colorCode}m{$text}\033[0m";
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
