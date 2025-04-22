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

use \Luminova\Command\Utils\Color;
use \Luminova\Command\Terminal;

/**
 * Text class for managing and applying ANSI styling codes to text dynamically.
 *
 * @method static string {StyleName}(string $text, ?int $fonts = Text::NO_FONT, ?string $background = null) Applies the specified style to the text and returns the styled text with ANSI code.
 * @method static string red(string   $text, ?int $fonts = Text::NO_FONT, ?string $background = null) Styles the text in red and returns the styled text with ANSI code.
 * @method static string white(string $text, ?int $fonts = Text::NO_FONT, ?string $background = null) Styles the text in white and returns the styled text with ANSI code.
 * @method static string green(string $text, ?int $fonts = Text::NO_FONT, ?string $background = null) Styles the text in green and returns the styled text with ANSI code.
 * @method static string cyan(string  $text, ?int $fonts = Text::NO_FONT, ?string $background = null) Styles the text in cyan and returns the styled text with ANSI code.
 */
final class Text
{
    /**
     * Reset any ANSI styles applied.
     * 
     * @var int ANSI_RESET
     */
    public const ANSI_RESET = 0b0;

    /**
     * Default to no font style applied.
     * 
     * @var int NO_FONT
     */
    public const NO_FONT = 0b100000000;

    /**
     * Apply bold text style.
     * 
     * @var int FONT_BOLD
     */
    public const FONT_BOLD = 0b1;

    /**
     * Apply italic text style.
     * 
     * @var int FONT_ITALIC
     */
    public const FONT_ITALIC = 0b10;

    /**
     * Apply underline text style.
     * 
     * @var int FONT_UNDERLINE
     */
    public const FONT_UNDERLINE = 0b100;

    /**
     * Apply strikethrough text style.
     * 
     * @var int FONT_STRIKETHROUGH
     */
    public const FONT_STRIKETHROUGH = 0b1000;

    /**
     * Apply blinking text style.
     * 
     * @var int FONT_BLINK
     */
    public const FONT_BLINK = 0b10000;

    /**
     * Swap foreground and background colors.
     * 
     * @var int FONT_INVERSE
     */
    public const FONT_INVERSE = 0b100000;

    /**
     * Apply invisible text style (hidden).
     * 
     * @var int FONT_INVISIBLE
     */
    public const FONT_INVISIBLE = 0b1000000;

    /**
     * Apply double underline text style.
     * 
     * @var int FONT_DOUBLE_UNDERLINE
     */
    public const FONT_DOUBLE_UNDERLINE = 0b10000000;

    /**
     * Align text to the right.
     * 
     * @var int RIGHT
     */
    public const RIGHT = 0;

    /**
     * Align text to the left.
     * 
     * @var int LEFT
     */
    public const LEFT = 1;

    /**
     * Align text to the center.
     * 
     * @var int CENTER
     */
    public const CENTER = 2;

    /**
     * Animate text bottom to top.
     * 
     * @var int UP
     */
    public const UP = 3;

    /**
     * Animate text top to bottom.
     * 
     * @var int DOWN
     */
    public const DOWN = 4;

    /**
     * Apply default border style.
     * 
     * @var int BORDER.
     */
    public const BORDER = 0b0;

    /**
     * No border style applied.
     * 
     * @var int NO_BORDER
     */
    public const NO_BORDER = 0b1011;

    /**
     * Apply border with rounded corners.
     * 
     * @var int BORDER_RADIUS
     */
    public const BORDER_RADIUS = 0b1;

    /**
     * Apply thicker border style.
     * 
     * @var int BORDER_THICKER
     */
    public const BORDER_THICKER = 0b10;

    /**
     * Emoji pattern.
     * 
     * @var string $emojiPattern
     */
    private static string $emojiPattern = '/[\x{1F300}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';

    /**
     * Handles dynamic static method calls for text styling.
     * 
     * @param string $name The static method name representing the text style (e.g., 'red', 'blue').
     * @param array[]{string:text,?int:Text::FONT_*,?string:Color::bg{ColorName}} $arguments The arguments for styling:
     *                        - 0: The text to style.
     *                        - 1: An optional font style (e.g., 'Text::FONT_BOLD').
     *                        - 2: An optional background color (e.g, `red` or `Color::bgRed()`).
     *
     * @return string|null Returns the styled text or null if the style is not applicable.
     */
    public static function __callStatic(string $name, array $arguments): ?string
    {
        return Color::apply(
            $arguments[0] ?? 'I\'m a text!',
            $arguments[1] ?? null,
            lcfirst($name),
            $arguments[2] ?? null
        );
    }

    /**
     * Create a block of text with optional padding, colors, and alignment.
     *
     * @param string $text The text to display.
     * @param int $position Text alignment (e.g., Text::LEFT, Text::RIGHT, Text::CENTER).
     * @param int $padding Optional padding around the text.
     * @param string|null $foreground Optional foreground color for the text (e.g, `white`, `cyan`).
     * @param int $fonts Optional font style(s) to apply, which can be combined using bitwise operators (e.g., Text::FONT_ITALIC).
     * @param int $borders The layout border style, supports combining using bitwise operator (e.g, `Text::BORDER_RADIUS`, `Text::BORDER_THICKER`).
     * @param string|null $borderColor Optional layout border color (e.g, `white`, `cyan`).
     * @param string|null $shadow Optional layout border shadow color (e.g, `white`, `cyan`).
     * 
     * @return string Return rendered block layout. with text.
     */
    public static function block(
        string $text,  
        int $position = self::LEFT,
        int $padding = 0,
        ?string $foreground = null,
        ?string $background = null,
        int $fonts = self::NO_FONT,
        int $borders = self::NO_BORDER,
        ?string $borderColor = null,
        ?string $shadow = null
    ): string 
    {
        $text = trim($text, PHP_EOL);
        [$border, $radius, $thicker] = self::borders($borders);

        $padding = max(0, $padding);
        $offset = ($border || $shadow ? 2 : 0);
        $layout = ($padding * 2);
        $window = Terminal::getWidth() - ($layout - $offset);
        $width = self::largest($text)[1];
        $shadows = [];

        if($width >= $window){
            $text = self::wrap($text, $window);
            $width = $window;
        }
        
        $layout += $width + ((!$border && $shadow) ? 2 : 0);
        $horizontal = ($border || $shadow)
            ? str_repeat($border ? self::corners('horizontal', false, $thicker) : ' ', $layout)
            : '';
        $vertical = $border 
            ? self::corners('vertical', false, $thicker)
            : '';

        $marginX = self::margin(max(0, $padding ), $vertical, $layout);
        $marginY = self::margin($padding, '', null);
        $topLeft = self::corners('topLeft', $radius, $thicker);
        $topRight = self::corners('topRight', $radius, $thicker);
        $bottomLeft = self::corners('bottomLeft', $radius, $thicker);
        $bottomRight =  self::corners('bottomRight', $radius, $thicker);

        $card = ($border || $shadow) 
            ? self::addBorderXAxis(
                $topLeft, 
                $topRight, 
                $horizontal,
                $border,
                $borderColor,
                $shadow ?? $background
            ) : '';

        if($padding > 0){
            $shadows = self::lines($marginX);
            $card .= self::addBorderShadow($shadows, $background, $borderColor, $shadow);
        }

        foreach (self::lines($text) as $line) {
            $line = self::align(trim($line), $width , $position);

            $card .=  PHP_EOL . (($border || $shadow) ? Color::style($border ? $vertical : ' ', $borderColor, $shadow ?? $background) : '');
            $card .=  Color::style($marginY, $borderColor, $background);
            $card .=  Color::apply($line, $fonts, $foreground, $background);
            $card .=  Color::style($marginY, $borderColor, $background);
            $card .=  (($border || $shadow) ? Color::style($border ? $vertical : ' ', $borderColor, $shadow ?? $background) : '');
        }

        if($padding > 0){
            $card .= self::addBorderShadow($shadows, $background, $borderColor, $shadow);
        }

        $card .= PHP_EOL . (($border || $shadow) 
            ? self::addBorderXAxis(
                $bottomLeft, 
                $bottomRight, 
                $horizontal,
                $border,
                $borderColor,
                $shadow ?? $background
            ) : '');
    
        return trim($card) . PHP_EOL;
    }

    /**
     * Create a card-like layout with optional formatting and alignment.
     *
     * @param string $text The text to display.
     * @param int $position Text alignment (e.g., Text::LEFT, Text::RIGHT, Text::CENTER).
     * @param int $padding Optional padding around the text.
     * @param string|null $foreground Optional foreground color for the text (e.g, `white`, `cyan`).
     * @param string|null $background Optional background color for the text (e.g, `red`, `green`).
     * @param int $fonts Optional font style(s) to apply, which can be combined using bitwise operators (e.g., Text::FONT_ITALIC).
     * @param int $borders Optional layout border style, supports combining using bitwise operator (e.g, `Text::BORDER_RADIUS`, `Text::BORDER_THICKER`).
     * 
     * @return string Return rendered card block with text.
     */
    public static function card(
        string $text,  
        int $position = self::LEFT,
        int $padding = 0,
        ?string $foreground = null,
        ?string $background = null,
        int $fonts = self::NO_FONT,
        int $borders = self::BORDER
    ): string 
    {
        return self::block(
            $text, 
            $position, 
            $padding, 
            $foreground, 
            $background,
            $fonts,
            $borders,
            $foreground,
            $background
        );
    }

    /**
     * Renders the given text like an (inline element) with optional styling.
     *
     * @param string $text The text to display.
     * @param int $position Text alignment (e.g., Text::LEFT, Text::RIGHT, Text::CENTER).
     * @param string|null $foreground Optional foreground color for the text (default: null).
     * @param string|null $background Optional background color for the text (default: null).
     * @param int $fonts Optional font style(s) to apply, which can be combined using bitwise operators (e.g., Text::FONT_ITALIC).
     * 
     * @return string Return the rendered inline text block.
     */
    public static function inline(
        string $text, 
        int $position = self::LEFT,
        ?string $foreground = null,
        ?string $background = null,
        int $fonts = self::NO_FONT
    ): string 
    {
        $text = trim($text);
        if($text === ''){
            return $text;
        }

        $width = Terminal::getWidth() - 2;
        $length = self::largest($text)[1];
        $text = ($length >= $width) 
            ? self::wrap($text, $width - (($length - $width) / 2)) 
            : $text; 

        $inline = '';

        foreach (self::lines($text) as $line) {
            $inline .= self::align(
                Color::apply(trim($line), $fonts, $foreground, $background), 
                $width, 
                $position, 
                'inline'
            );
        }

        return (!$inline) ? $inline : trim($inline, PHP_EOL);
    }

    /**
     * Formats plain text with optional font styles and alignment.
     * 
     * @param string $text The text to be formatted.
     * @param int $position Text alignment (e.g., Text::LEFT, Text::RIGHT, Text::CENTER).
     * @param int $fonts Optional font style(s) to apply, which can be combined using bitwise operators (e.g., `Text::FONT_ITALIC | Text::FONT_BOLD`).
     * 
     * @return string Return the formatted plain text text with the applied alignment and optional font styles.
     */
    public static function plain(
        string $text, 
        int $position = self::LEFT,
        int $fonts = self::NO_FONT
    ): string 
    {
        return self::inline(
            $text, 
            $position, 
            null, 
            null, 
            $fonts
        );
    }

    /**
     * Displays a scrolling marquee effect for the given text in the terminal.
     * 
     * @param string $text The text to display as a marquee.
     * @param int $speed The speed of scrolling, with higher values resulting in slower scroll.
     * @param int $direction The direction of the scroll (e.g, `Text::LEFT`, `Text::RIGHT`, `Text::UP`, or `Text::DOWN`).
     * @param int $repeat The number of times the marquee scroll repeats.
     * @param string|null $foreground Optional foreground color name for the text (e.g., 'red', 'blue').
     * @param string|null $background Optional background color name for the text (e.g., 'green', 'cyan').
     * @param int $fonts Optional font style(s) for the text (e.g., `Text::FONT_BOLD`, `Text::FONT_ITALIC | Text::FONT_UNDERLINE`).
     * 
     * @return void
     */
    public static function marquee(
        string $text, 
        int $speed, 
        int $direction = self::LEFT, 
        int $repeat = 1,
        ?string $foreground = null,
        ?string $background = null,
        int $fonts = self::NO_FONT
    ): void 
    {
        Terminal::clear();
        $text = trim($text);
        $height = self::height($text) + 4;
        $text = self::line($text);
        $width = Terminal::getWidth() - 2;
        $height = min($height, Terminal::getHeight() - 2);
        $length = self::strlen($text);
        $spaces = str_repeat(' ', $width);
        $speed = max($speed, intval($speed * ($length / $width)));
     
        for ($r = 0; $r < $repeat; $r++) {
            switch ($direction) {
                case self::LEFT:
                    for ($i = 0; $i < $width + $length; $i++) {
                        Terminal::write(Color::apply(substr($spaces . $text, -$i), $fonts, $foreground, $background));
                        usleep($speed);
                        Terminal::clear();
                    }

                    break;

                case self::RIGHT:
                    for ($i = 0; $i < $width + $length; $i++) {
                        Terminal::write(Color::apply(substr($text . $spaces, $i), $fonts, $foreground, $background));
                        usleep($speed);
                        Terminal::clear();
                    }
                    break;

                case self::DOWN:
                    for ($i = 0; $i < $height; $i++) {
                        Terminal::write(str_repeat("\n", $i) . Color::apply($text, $fonts, $foreground, $background));
                        usleep($speed);
                        Terminal::clear();
                    }
                    break;

                case self::UP:
                    for ($i = $height; $i > 0; $i--) {
                        Terminal::write(str_repeat("\n", $i) . Color::apply($text, $fonts, $foreground, $background));
                        usleep($speed);
                        Terminal::clear();
                    }
                    break;
            }
        }
    }

    /**
     * Displays a flashing effect for the given text in the terminal for a specified duration.
     *
     * @param string $text The text to flash.
     * @param int $duration The duration of the flashing effect in seconds.
     * @param int $speed The flashing speed in microseconds (higher values slow the flash).
     * @param string|null $foreground Optional foreground color name for the text (e.g., 'red', 'blue').
     * @param string|null $background Optional background color name for the text (e.g., 'green', 'cyan').
     * @param int $fonts Optional font style(s) for the text (e.g., `Text::FONT_BOLD`, `Text::FONT_ITALIC | Text::FONT_UNDERLINE`).
     * 
     * @return void
     */
    public static function flash(
        string $text, 
        int $duration = 3,
        int $speed = 500000,
        ?string $foreground = null,
        ?string $background = null,
        int $fonts = self::NO_FONT
    ): void
    {
        $start = time();
        $isVisible = true;

        while ((time() - $start) < $duration) {
            Terminal::write(Color::apply($isVisible ? "\r$text" : "\r    ", $fonts, $foreground, $background));
            $isVisible = !$isVisible; 
            usleep($speed);
        }
        
        Terminal::write(Color::apply("\r$text", $fonts, $foreground, $background));
    }

    /**
     * Applies specified ANSI font styles to the provided text.
     * 
     * @param string $text The text to style with ANSI font codes.
     * @param int $fonts Optional font style(s) to apply, which can be combined using bitwise operators (e.g., `Text::FONT_ITALIC`).
     * 
     * @return string The styled text with ANSI codes applied, or the original text if no valid styles are given.
     */
    public static function style(string $text, int $fonts): string
    {
        if (
            $text === '' || 
            !$fonts || 
            ($fonts & self::NO_FONT) === self::NO_FONT || 
            self::hasAnsi($text) || 
            !Terminal::isAnsiSupported()
        ) {
            return $text;
        }

        $fonts = self::fonts($fonts);
        return "\033[{$fonts}m{$text}\033[0m";
    }

    /**
    * Wraps the given text to a specified maximum width, applying padding on all sides.
    *
    * @param string $text The text to be wrapped and padded.
    * @param int $width The maximum width for each line of text after padding.
    * @param int $left The number of spaces to add as left padding.
    * @param int $right The number of spaces to add as right padding.
    * @param int $top The number of new lines to add above the text.
    * @param int $bottom The number of new lines to add below the text.
    *
    * @return string Return the padded wrapped text.
    */
    public static function wrap(
        string $text, 
        int $width, 
        int $left = 0, 
        int $right = 0, 
        int $top = 0, 
        int $bottom = 0
    ): string 
    {
        if (!$text) {
            return '';
        }

        $width = max(0, $width);
        $width = min($width, Terminal::getWidth() - 2);
        $width -= ($left - $right);
        $text = wordwrap(trim($text), $width, PHP_EOL);
    
        if ($left > 0) {
            $text = preg_replace('/^/m', str_repeat(' ', $left), $text);
        }
    
        if ($right > 0) {
            $text = preg_replace_callback('/^.*$/m', function ($matches) use ($right) {
                return $matches[0] . str_repeat(' ', $right);
            }, $text);
        }
    
        if ($top > 0) {
            $text = str_repeat(PHP_EOL, $top) . $text;
        }
    
        if ($bottom > 0) {
            $text .= str_repeat(PHP_EOL, $bottom);
        }
    
        return $text;
    }

    /**
     * Pads a string to fit a specified length by adding spaces.
     * 
     * @param string $text The string to pad.
     * @param int $max The maximum length to which the string should be padded.
     * @param int $extra The number of extra spaces to add at the end of the padded string (default: 2).
     * @param int $indent Optional indent to apply at the start of the string (default: 0).
     * 
     * @return string Returns the padded string, ensuring it meets the specified length.
     * 
     * @example - Usage Example:
     * ```php
     * echo Text::fit('Hello', 10); 
     * // Output: "Hello     "
     * ```
    */
    public static function fit(
        string $text, 
        int $max, 
        int $extra = 2, 
        int $indent = 0
    ): string
    {
        $max += ($extra + $indent);

        return str_pad(str_repeat(' ', $indent) . $text, (int) $max);
    }

    /**
     * Calculate the character length of a string, ignoring any applied emoji, ANSI color or style codes.
     *
     * @param string $string The string to calculate its length.
     * @param string $encoding The text encoding to use (default: `UTF-8`).
     * 
     * @return int Return the number of visible characters in the string.
     */
    public static function strlen(string $string, string $encoding = 'UTF-8'): int
    {
        if ($string === '') {
            return 0;
        }

        $string = self::hasAnsi($string) ? self::strip($string) : $string;
        $string = self::hasEmoji($string) ? self::stripEmojis($string) : $string;

        return mb_strlen($string, $encoding);
    }

    /**
     * Calculate the total character length of an emoji in a string.
     * 
     * @param string $string The string to calculate its emoji length.
     * 
     * @return int Return the number of emojis length in the string.
     */
    public static function emojiStrlen(string $string): int 
    {
        preg_match_all('/\X/u', $string, $matches);
        
        $emojiLength = 0;
        foreach ($matches[0] as $char) {
            if (preg_match(self::$emojiPattern, $char)) {
                $emojiLength += mb_strlen($char, 'UTF-8');
            }
        }
        
        return $emojiLength;
    }

    /**
     * Count the total number of emojis in a string.
     * 
     * @param string $string The string to count emojis.
     * 
     * @return int Return the number of emojis in the string.
     */
    public static function countEmojis(string $string): int 
    {
        preg_match_all(self::$emojiPattern, $string, $matches);
        return count($matches[0]);
    }

    /**
     * Remove any emojis in a given string.
     * 
     * @param string $string The string to strip emojis.
     * 
     * @return int Return string without any emojis.
     */
    public static function stripEmojis(string $string): string 
    {
        return preg_replace(self::$emojiPattern, '', $string);
    }

    /**
     * Calculate the length of ANSI formatting codes applied to a given text.
     *
     * @param int|null $fonts Optional text formatting (e.g., `Text::FONT_BOLD`).
     * @param string|null $foreground Optional foreground color name (default: null).
     * @param string|null $background Optional background color name (default: null).
     *
     * @return int Return the total length of ANSI formatting characters included.
     */
    public static function length(
        ?int $fonts, 
        ?string $foreground = null, 
        ?string $background = null
    ): int
    {
        $text = Color::apply('T', $fonts, $foreground, $background);
        preg_match_all('/\033\[[0-9;]*m/', $text, $matches);

        $length = 0;
        foreach ($matches[0] as $ansi) {
            $length += strlen($ansi);
        }

        return $length;
    }

    /**
     * Calculate the height of a string based on the number of lines (newline).
     *
     * @param string $text The text whose height (line count) is calculated.
     *
     * @return int Return the total number of lines in the text.
     */
    public static function height(string $text): int 
    {
        $text = trim($text);
        if($text === ''){
            return 0;
        }

        $lines = explode("\n", $text);
        return count($lines);
    }

    /**
     * Finds the longest line from a multi-line text string, that may contain various newline formats.
     * 
     * @param string $text The input text to analyze.
     * 
     * @return array{0:string,1:int} Returns an array containing the longest line.
     *         - The longest line as the first element.
     *         - Its length as the second element.
     * 
     * > **Note:** Each line is trimmed of leading and trailing whitespace before its length is calculated.
     */
    public static function largest(string $text): array
    {
        if($text === ''){
            return ['', 0];
        }

        $longest = '';
        $max = 0;

        foreach (self::lines($text) as $line) {
            $line = trim($line);
            $length = self::strlen($line);

            if ($length > $max) {
                $longest = $line;
                $max = $length;
            }
        }

        return [$longest, $max];
    }

    /**
     * Splits text into an array of lines, normalizing different newline formats.
     * 
     * @param string $text The input text to be split into lines.
     * 
     * @return string[] Returns an array of strings, where each string represents a line from the input text.
     */
    public static function lines(string $text): array
    {
        return ($text === '') 
            ? [] 
            : explode("\n", preg_replace('/\r\n|\r/', "\n", $text));
    }

    /**
     * Removes all line breaks (newlines) from the given text and replaces them with an optional specified string.
     * 
     * @param string $text The input text that may contain line breaks.
     * @param string $replace The string to replace line breaks with (default: ' ').
     * 
     * @return string Returns the processed string with all line breaks replaced by the specified string.
     *                If the input text is empty, an empty string is returned.
     * 
     * @example - Usage Example:
     * ```php
     * $text = "This is line one\nThis is line two\r\nThis is line three";
     * echo Text::line($text); 
     * // Output: "This is line one This is line two This is line three"
     * 
     * echo Text::line($text, ', '); 
     * // Output: "This is line one, This is line two, This is line three"
     * ```
     */
    public static function line(string $text, string $replace = ' '): string
    {
        return ($text === '') 
            ? '' 
            : preg_replace('/\r\n|\r|\n/', $replace, $text);
    }

    /**
     * Removes ANSI escape sequences from the provided text.
     * 
     * @param string $text The input text that may contain ANSI escape sequences.
     * 
     * @return string Return the text with all ANSI escape sequences removed.
     */
    public static function strip(string $text): string 
    {
        return preg_replace('/\033\[[0-9;]*m/', '', $text);
    }

    /**
     * Format and align text with specified padding and alignment, accounting only for visible characters.
     * 
     * @param string $text The input text to be formatted.
     * @param int $padding The total width for the formatted text, including padding.
     * @param int $alignment Text alignment option (e.g., `Text::LEFT`, `Text::RIGHT`, `Text::CENTER`).
     * @param string $char The padding character to use (default: ' ').
     * 
     * @return string Return the formatted text with applied padding and alignment.
     */
    public static function format( 
        string $text, 
        int $padding, 
        int $alignment = self::LEFT,
        string $char = ' '
    ): string 
    {
        $length = self::strlen($text);
        $padding = max(0, $padding - $length);

        if($alignment === self::RIGHT){
            return sprintf("%{$padding}s%s", $char, $text);
        }

        if($alignment === self::LEFT){
            return sprintf("%-{$length}s", $text . str_repeat($char, $padding));
        }
        
        $left = intdiv($padding, 2);
        $right = max(0, ($padding - $left));

        return sprintf("%{$left}s%s%{$right}s", $char, $text, $char);
    }

    /**
     * Pads a string on both the left and right sides based on alignment.
     *
     * @param string $text The string to pad.
     * @param int $length The total length of the resulting string after padding.
     * @param int $alignment Padding alignment option (e.g., `STR_PAD_*` or `Text::LEFT`, `Text::RIGHT`, `Text::CENTER`).
     * @param string $char The padding character (default: space).
     * 
     * @return string Return the padded string.
     */
    public static function padding(
        string $text, 
        int $length, 
        int $alignment = STR_PAD_BOTH,
        string $char = ' ',
    ): string 
    {
        return str_pad($text, $length, $char, $alignment);
    }

    /**
     * Generates a margin with optional horizontal (left and right) padding and border character.
     *
     * @param int $padding The number of lines to add as vertical padding (height of the margin).
     * @param string $char The margin character or string to place on the sides of the margin (default: `space`).
     * @param int|null $horizontal Optional horizontal (left and right) margin, the number of spaces to add between border character as horizontal padding.
     *                             If null, no horizontal padding will be applied, only the vertical margin.
     *
     * @return string Return the generated margin with vertical and optional horizontal padding.
     */
    public static function margin(int $padding, string $char = ' ', ?int $horizontal = null): string 
    {
        if ($padding <= 0) {
            return '';
        }

        return ($horizontal === null)
            ? sprintf('%s%s', $char, str_repeat(' ', $padding)) 
            : str_repeat(sprintf('%s%s%s', $char, str_repeat(' ', $horizontal), $char) . PHP_EOL, $padding);
    }

    /**
     * Retrieves the ANSI escape code for a specific font style.
     *
     * @param int $font The font style code to convert to an ANSI escape code (e.g., `Text::FONT_*`).
     * 
     * @return string Return the ANSI escape sequence for the specified font style, or an empty string if the font is not recognized.
     */
    public static function font(int $font): string 
    {
        return match ($font) {
            self::FONT_BOLD => '1',
            self::FONT_ITALIC => '3',
            self::FONT_UNDERLINE => '4',
            self::FONT_BLINK => '5',
            self::FONT_INVERSE => '7',
            self::FONT_INVISIBLE => '8',
            self::FONT_STRIKETHROUGH => '9',
            self::FONT_DOUBLE_UNDERLINE => '21',
            self::ANSI_RESET => '0',
            default => '',
        };
    }

    /**
     * Generates the ANSI escape sequence for a combination of font styles, 
     * or returns no styling if `Text::NO_FONT` is specified.
     * 
     * @param int|null $fonts A bitwise combination of font style constants (e.g., `Text::FONT_BOLD | Text::FONT_UNDERLINE`). 
     *                      If null or `Text::NO_FONT` is specified, returns an empty string.
     * 
     * @return string Return the ANSI escape sequence representing the combined font styles, or an empty string if no style is applied.
     */
    public static function fonts(?int $fonts): string 
    {
        if(!$fonts || ($fonts & self::NO_FONT) === self::NO_FONT){
            return '';
        }

        if ($fonts & self::ANSI_RESET) {
            return '0';
        }

        $ansi = [];

        if ($fonts & self::FONT_BOLD) {
            $ansi[] = '1';
        }
        if ($fonts & self::FONT_ITALIC) {
            $ansi[] = '3'; 
        }
        if ($fonts & self::FONT_UNDERLINE) {
            $ansi[] = '4';
        }
        if ($fonts & self::FONT_BLINK) {
            $ansi[] = '5';
        }
        if ($fonts & self::FONT_INVERSE) {
            $ansi[] = '7';
        }
        if ($fonts & self::FONT_INVISIBLE) {
            $ansi[] = '8';
        }
        if ($fonts & self::FONT_STRIKETHROUGH) {
            $ansi[] = '9'; 
        }
        if ($fonts & self::FONT_DOUBLE_UNDERLINE) {
            $ansi[] = '21';
        }

        return implode(';', $ansi);
    }

    /**
     * Determines the border style flags based on the provided border type.
     * 
     * @param int|null $borders The border style(s) to be applied (e.g, `Text::BORDER_RADIUS`, `Text::BORDER_THICKER`).
     * 
     * @return array{0:bool,1:bool,2:bool} Returns an array with three boolean values:
     *         - **[0]**: `true` if any border is enabled, `false` if `NO_BORDER` is specified.
     *         - **[1]**: `true` if the border has rounded corners (applies `BORDER_RADIUS`), otherwise `false`.
     *         - **[2]**: `true` if the border is thicker (applies `BORDER_THICKER`), otherwise `false`.
     */
    public static function borders(?int $borders): array 
    {
        if ($borders === null || ($borders & self::NO_BORDER) === self::NO_BORDER) {
            return [false, false, false];
        }

        $result = [true];
        $result[] = ($borders & self::BORDER_RADIUS);
        $result[] = ($borders & self::BORDER_THICKER);
        return $result;
    }

    /**
     * Generates the appropriate box-drawing character for borders based on position, 
     * corner rounding (radius), and line thickness.
     * 
     * @param string $position The position of the box corner or edge (default: `topLeft`). 
     * @param bool $radius Whether to use rounded corners (default: false).
     * @param bool $thick Whether to use thick borders (default: false).
     * 
     * @return string Return the character used to render the box at the given position with the specified style.
     * 
     * **Supported Positions Include:**
     *        - **'topLeft'**: Top-left corner.
     *        - **'topRight'**: Top-right corner.
     *        - **'bottomLeft'**: Bottom-left corner.
     *        - **'bottomRight'**: Bottom-right corner.
     *        - **'horizontal'**: Horizontal edges.
     *        - **'vertical'**: Vertical edges.
     *        - **'crossings'**: Intersection or crossing of horizontal and vertical lines.
     *        - **'leftConnector'**: Left T-connector.
     *        - **'rightConnector'**: Right T-connector.
     *        - **'topConnector'**: Top T-connector.
     *        - **'bottomConnector'**: Bottom T-connector.
     *        - **'topLeftAngle'**: Angled top-left corner.
     *        - **'topRightAngle'**: Angled top-right corner.
     *        - **'bottomLeftAngle'**: Angled bottom-left corner.
     *        - **'bottomRightAngle'**: Angled bottom-right corner.
     *        - **'plus'**: A plus sign, often used as a simple intersection.
     */
    public static function corners(
        string $position = 'topLeft', 
        bool $radius = false,
        bool $thick = false
    ): string 
    {
        return match ($position) {
            'topLeft' => $radius 
                ? '╭'
                : ($thick ? '┏' : '┌'),
            'topRight' => $radius 
                ? '╮'
                : ($thick ? '┓' : '┐'),
            'bottomLeft' => $radius 
                ? '╰'
                : ($thick ? '┗' : '└'),
            'bottomRight' => $radius 
                ? '╯'
                : ($thick ? '┛' : '┘'),
            'horizontal' => $thick ? '━' : '─',
            'vertical' => $thick ? '┃' : '│',
            'crossings' => $thick ? '╋' : '┼',
            'leftConnector' => $thick ? '┣' : '├',
            'rightConnector' => $thick ? '┫' : '┤',
            'topConnector' => $thick ? '┳' : '┬',
            'bottomConnector' => $thick ? '┻' : '┴',
            'topLeftAngle' => '◤',
            'topRightAngle' => '◥',
            'bottomLeftAngle' => '◣',
            'bottomRightAngle' => '◢',
            'plus' => '+',
            default => '',
        };
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
     * Determine if the given text contains Emoji.
     * 
     * @param string $text The text to check for emoji codes.
     * 
     * @return bool Returns true if the text contains emoji, otherwise false.
     */
    public static function hasEmoji(string $text): bool 
    {
        return preg_match(self::$emojiPattern, $text) > 0;
    }

    /**
     * Adds a border to the x-axis by combining left, center, and right border segments.
     * 
     * @param string $left The string representing the left border of the x-axis.
     * @param string $right The string representing the right border of the x-axis.
     * @param string $center The string representing the center section of the x-axis.
     * @param bool $border Indicates whether the left and right borders should be displayed.
     * @param string|null $foreground The color to be applied to the foreground of the x-axis.
     * @param string|null $background The color to be applied to the background of the x-axis.
     * 
     * @return string Returns the styled x-axis string, formatted with the specified colors.
     */
    private static function addBorderXAxis(
        string $left, 
        string $right, 
        string $center,
        bool $border,
        ?string $foreground,
        ?string $background
    )
    {
        return Color::style(
            $border ? $left . $center . $right : $center,
            $foreground, 
            $background
        );
    }

    /**
     * Adds shadows to the block elements, applying color styles to create a 
     * shadow effect around the borders.
     * 
     * @param array $shadows An array of strings representing the shadow layers.
     * @param string|null $background The color to be applied to the background of the shadows.
     * @param string|null $borderColor The color to be applied to the border of the shadows.
     * @param string|null $shadow The color to be applied to the shadow effect, or null to use the background.
     * 
     * @return string Returns the styled shadow strings, formatted with the specified colors.
     */
    private static function addBorderShadow(
        array $shadows, 
        ?string $background,
        ?string $borderColor,
        ?string $shadow
    ){
        $card = '';
        foreach ($shadows as $i => $x) {
            if($x === ''){
                continue;
            }

            $slen = mb_strlen($x);
            $card .= PHP_EOL . Color::style(mb_substr($x, 0, 1), $borderColor, $shadow ?? $background);
            $card .= Color::style(mb_substr($x, 1, $slen - 2), null, $background);
            $card .= Color::style(mb_substr($x, -1), $borderColor, $shadow ?? $background);
        }
        return $card;
    }

    /**
     * Aligns the given text within a specified length based on the specified position.
     * 
     * @param string $text The text to be aligned.
     * @param int $length The total length to which the text should be aligned.
     * @param int $position The alignment position (e.g., `Text::LEFT`, `Text::CENTER`, `Text::RIGHT`). 
     * @param string $type The type of alignment format ('block' for block format, 
     *                     'inline' for inline format). Defaults to 'block'.
     *
     * @return string Return the aligned text, formatted according to the specified position and type.
     */
    private static function align(
        string $text, 
        int $length,
        int $position = self::LEFT,
        string $type = 'block'
    ): string 
    {
        return ($position === self::CENTER) 
            ? self::format($text, $length, self::CENTER, '')
            : (($position === self::RIGHT) 
                ? self::format($text, $length, self::RIGHT, '')
                :  self::format($text, $length)
            ) . (($type === 'inline') ? PHP_EOL : '');
    }
}