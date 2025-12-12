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
namespace Luminova\Command\Utils;

use \Luminova\Command\Terminal;
use \Luminova\Command\Utils\Text;

/**
 * Color class for managing and retrieving ANSI color codes and names dynamically.
 *
 * @method static ?string fg{ColorName}(?string $text) Returns the foreground (text) color name if valid.
 * @method static ?string fgRed(?string $text) Returns the foreground color 'red' if valid.
 * @method static ?string fgGreen(?string $text) Returns the foreground color 'green' if valid.
 * @method static ?string fgWhite(?string $text) Returns the foreground color 'White' if valid.
 * @method static ?string fgCyan(?string $text) Returns the foreground color 'cyan' if valid.
 * 
 * @method static ?string bg{ColorName}(?string $text) Returns the background color name if valid.
 * @method static ?string bgRed(?string $text) Returns the background color 'red' if valid.
 * @method static ?string bgGreen(?string $text) Returns the background color 'green' if valid.
 * @method static ?string bgWhite(?string $text) Returns the background color 'wite' if valid.
 * @method static ?string bgCyan(?string $text) Returns the background color 'cyan' if valid.
 * 
 * @method static ?string fgc{ColorName}() Returns the ANSI code for the foreground color.
 * @method static ?string fgcRed() Returns the ANSI code for foreground red (e.g., '0;31').
 * @method static ?string fgcGreen() Returns the ANSI code for foreground green (e.g., '0;32').
 * @method static ?string fgcWhite() Returns the ANSI code for foreground white (e.g., '1;37').
 * @method static ?string fgcCyan() Returns the ANSI code for foreground cyan (e.g., '0;36').
 * 
 * @method static ?string bgc{ColorName}() Returns the ANSI code for the background color.
 * @method static ?string bgcRed() Returns the ANSI code for background red (e.g., '41').
 * @method static ?string bgcGreen() Returns the ANSI code for background green (e.g., '42').
 * @method static ?string bgcWhite() Returns the ANSI code for background white (e.g., '47').
 * @method static ?string bgcCyan() Returns the ANSI code for background cyan (e.g., '46').
 */
final class Color
{
    /**
     * List of text foreground colors with their ANSI codes.
     *
     * @var array<string,string> $foregrounds
     */
    private static array $foregrounds = [
        'black'         => '0;30',
        'darkGray'      => '1;30',
        'red'           => '0;31',
        'lightRed'      => '1;31',
        'darkRed'       => '2;31',
        'green'         => '0;32',
        'lightGreen'    => '1;32',
        'darkGreen'     => '2;32',
        'yellow'        => '0;33',
        'lightYellow'   => '1;33',
        'darkYellow'    => '2;33',
        'blue'          => '0;34',
        'lightBlue'     => '1;34',
        'magenta'       => '0;35',
        'lightMagenta'  => '1;35',
        'cyan'          => '0;36',
        'lightCyan'     => '1;36',
        'lightGray'     => '0;37',
        'white'         => '1;37',
        'brightBlack'   => '90',
        'brightRed'     => '91',
        'brightGreen'   => '92',
        'brightYellow'  => '93',
        'brightBlue'    => '94',
        'brightMagenta' => '95',
        'brightCyan'    => '96',
        'brightWhite'   => '97',
    ];

    /**
     * List of text background colors with their ANSI codes.
     *
     * @var array<string,string> $backgrounds
     */
    private static array $backgrounds = [
        'black'         => '40',
        'red'           => '41',
        'green'         => '42',
        'yellow'        => '43',
        'blue'          => '44',
        'magenta'       => '45',
        'cyan'          => '46',
        'lightGray'     => '47',
        'darkGray'      => '100',
        'lightRed'      => '101',
        'lightGreen'    => '102',
        'lightYellow'   => '103',
        'lightBlue'     => '104',
        'lightMagenta'  => '105',
        'lightCyan'     => '106',
        'white'         => '107'
    ];
        
    /**
     * Magic method for handling static method calls related to color names and ANSI color codes.
     *
     * @param string $name The name of the static method being called, representing a color or color code.
     * @param array $arguments Unused.
     * 
     * @return string|null Return the color name for `fg`/`bg` prefixes or ANSI code for `fgc`/`bgc` prefixes.
     */
    public static function __callStatic(string $name, array $arguments): ?string
    {
        $text = $arguments[0] ?? null;

        $p2 = substr($name, 0, 2);
        $type2 = lcfirst(substr($name, 2));

        if ($text === null) {
            return match ($p2) {
                'fg' => self::has($type2, 'fg') ? $type2 : null,
                'bg' => self::has($type2, 'bg') ? $type2 : null,
                default => self::get(lcfirst(substr($name, 3)), substr($name, 0, 3))
            };
        }

        if ($p2 === 'fg') {
            return self::style($text, $type2);
        }

        if ($p2 === 'bg') {
            return self::style($text, null, $type2);
        }

        return $text;
    }

    /**
     * Retrieves the ANSI code for a given color name based on the specified type.
     * 
     * @param string $color The name of the color to retrieve (e.g., 'red', 'green').
     * @param string $type  The type of color, either 'fgc' for foreground 
     *          or 'bgc' for background (default is 'fgc').
     * 
     * @return string Return the ANSI code for the requested color, 
     *          or an empty string if the color is not defined.
     */
    public static function get(string $color, string $type = 'fgc'): string
    {
        if($color === '' || $type === ''){
            return '';
        }

        return ($type === 'fgc') 
            ? self::foreground($color)
            : self::background($color);
    }

    /**
     * Retrieves the ANSI code for a foreground (text) color.
     * 
     * @param string $name The name of the foreground color (e.g., 'red', 'green').
     * 
     * @return string Return the ANSI code for the specified foreground color, 
     *          or an empty string if not found.
     */
    public static function foreground(string $name): string
    {
        return self::$foregrounds[$name] ?? '';
    }

    /**
     * Retrieves the ANSI code for a background color.
     * 
     * @param string $name The name of the background color (e.g., 'blue', 'yellow').
     * 
     * @return string Return the ANSI code for the specified background color, 
     *          or an empty string if not found.
     */
    public static function background(string $name): string
    {
        return self::$backgrounds[$name] ?? '';
    }

    /**
     * Styles the provided text with optional foreground or background colors using ANSI formatting.
     * If the text already contains ANSI codes, it applies the colors regardless.
     *
     * @param string $text The text to be styled.
     * @param string|null $foreground Optional text foreground color name (e.g., 'red', 'green').
     * @param string|null $background Optional background color name (e.g., 'blue', 'yellow').
     *
     * @return string Return the styled text with ANSI color codes 
     *          or the original text if no valid colors are given.
     */
    public static function style(
        string $text, 
        ?string $foreground, 
        ?string $background = null
    ): string
    {
        if($text === ''){
            return '';
        }

        if($foreground && !self::has($foreground)){
            if(!$background){
                return $text;
            }

            $foreground = null;
        }

        if($background && !self::has($background, 'bg')){
            if(!$foreground){
                return $text;
            }

            $background = null;
        }

        if ((!$foreground && !$background) || !Terminal::isColorSupported()) {
            return $text;
        }

        $color = '';
        if ($foreground !== null ) {
            $color .= self::foreground($foreground);
        }

        if ($background !== null) {
            $color .= ($color === '') ? '' : ';';
            $color .= self::background($background);
        }

        return "\033[{$color}m{$text}\033[0m";
    }

    /**
     * Applies optional font styles, foreground or optional background colors to the given text.
     * Returns the text unchanged if it already contains ANSI codes or if no formatting options are provided.
     *
     * @param string $text The text to color.
     * @param int|null $fonts Optional font style(s) (e.g., `Text::FONT_BOLD` or `Text::FONT_BOLD | Text::FONT_UNDERLINR`).
     * @param string|null $foreground Optional text foreground color name (e.g, `red`, `white`).
     * @param string|null $background Optional background color name (e.g, `cyan`, `green`).
     *
     * @return string Return the formatted text with ANSI color codes or the original text if unsupported.
     */
    public static function apply(
        string $text, 
        ?int $fonts, 
        ?string $foreground = null, 
        ?string $background = null
    ): string
    {
        if($text === ''){
            return '';
        }

        $isNoFonts = (!$fonts || $fonts === Text::NO_FONT);

        if($foreground && !self::has($foreground)){
            if(!$background && $isNoFonts){
                return $text;
            }

            $foreground = null;
        }

        if($background && !self::has($background, 'bg')){
            if(!$foreground && $isNoFonts){
                return $text;
            }

            $background = null;
        }

        if ((!$foreground && !$background && $isNoFonts) || Text::hasAnsi($text)) {
            return $text;
        }

        $fonts = Text::fonts($fonts);

        if ((!$foreground && !$background) || !Terminal::isColorSupported()) {
            return ($fonts === '' || !Terminal::isAnsiSupported()) 
                ? $text 
                : "\033[{$fonts}m{$text}\033[0m";
        }

        $color = '';
        if ($foreground !== null ) {
            $color .= self::foreground($foreground);
        }

        if ($background !== null) {
            $color .= ($color === '') ? '' : ';';
            $color .= self::background($background);
        }

        $style = ($fonts !== '') 
            ? (($color === '') ? $fonts : "{$color};{$fonts}")
            : $color;

        return "\033[{$style}m{$text}\033[0m";
    }

    /**
     * Validates if the specified color name exists in the given color mapping array.
     *
     * @param string|null $color The color name to validate.
     * @param string $type The mapping of color names to ANSI codes (e.g, `fg`, `bg`).
     *
     * @return bool Return true if the color exists, otherwise false.
     */
    public static function has(?string $color, string $type = 'fg'): bool
    {
        if($color === '' || $color === null || $type === ''){
            return false;
        }

        return ($type === 'fg') 
            ? isset(self::$foregrounds[$color])
            : isset(self::$backgrounds[$color]);
    }
}