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

use \Luminova\Command\Terminal;
use \Luminova\Command\Utils\Color;
use \Luminova\Utils\WeakReference;
use \GdImage;
use \WeakMap;

class Image
{
    /**
     * Dense character set for higher detail.
     * 
     * Dark to light progression: @ (darkest) to space (lightest).
     * Provides a good balance of detail for ASCII art.
     * 
     * @var string ASCII_DENSE
     */
    public const ASCII_DENSE = "@%#*+=-:. ";

    /**
     * Extended dense set with more characters for finer detail.
     * 
     * Offers a very wide range of characters for shading, allowing for more nuanced images.
     * Useful when more granularity is needed.
     * 
     * @var string ASCII_EXTENDED_DENSE
     */
    public const ASCII_EXTENDED_DENSE = "$@B%8&WM#*oahkbdpqwmZO0QLCJUYXzcvunxrjft/\|()1{}[]?-_+~<>i!lI;:,\"^`'. ";

    /**
     * Simplified character set for bolder, chunkier ASCII art.
     * 
     * Contains fewer characters, leading to larger blocks of shading.
     * Good for images where fine detail is less important.
     * 
     * @var string ASCII_SIMPLIFIED
     */
    public const ASCII_SIMPLIFIED = "MNHQ\$OC?7>!:-;. ";

    /**
     * Inverted character set for creating a reverse effect.
     * 
     * Light to dark progression: space (darkest) to @ (lightest).
     * This can give an opposite shading style compared to traditional sets.
     * 
     * @var string ASCII_INVERTED
     */
    public const ASCII_INVERTED = " .:-=+*#%@";

    /**
     * Block character set for stylized, pixelated effects.
     * 
     * Utilizes block elements with increasing density from ░ (lightest) to █ (darkest).
     * Ideal for a chunky, retro-pixel look.
     * 
     * @var string ASCII_BLOCKS
     */
    public const ASCII_BLOCKS = "█▓▒░ ";

    /**
     * Classic ASCII character set, minimalistic with 5 characters.
     * 
     * Progresses from light to dark: space (lightest) to @ (darkest).
     * Best for basic or nostalgic ASCII art styles with less shading variation.
     * 
     * @var string ASCII_CLASSIC
     */
    public const ASCII_CLASSIC = " .oO@";

    /**
     * ASCII binary characters typically used for binary visualizations.
     * '0' represents off/black, while '1' represents on/white.
     * 
     * @var string ASCII_BINARY
     */
    public const ASCII_BINARY = "01";

    /**
     * ASCII binary characters with a leading space, often used for padding 
     * or alignment in visual representations. '0' represents off/black, 
     * and '1' represents on/white.
     * 
     * @var string ASCII_BINARY_CLASSIC
     */
    public const ASCII_BINARY_CLASSIC = " 01";

    /**
     * ASCII binary characters with spaces on both sides, suitable for 
     * creating a centered or balanced appearance in visualizations.
     * '0' represents off/black, while '1' represents on/white.
     * 
     * @var string ASCII_BINARY_SIMPLIFIED
     */
    public const ASCII_BINARY_SIMPLIFIED = " 01 ";

    /**
     * Weak preference mapping.
     * 
     * @var WeakMap|null $weak
     */
    protected static ?WeakMap $weak = null;

    /**
     * Weak preference object reference.
     * 
     * @var WeakReference|null $img
     */
    protected static ?WeakReference $img = null;

    /**
     * Converts an image to ASCII art with optional color mapping and lazy printing support.
     * 
     * Supported image formats include JPEG, PNG, GIF, and WEBP.
     * 
     * @param string $path The full file path to the image (supported formats: JPEG, PNG, GIF, WEBP).
     * @param string $ascii A string of ASCII characters used to represent different brightness levels
     *                      (default: `Image::ASCII_CLASSIC`).
     * @param int|null $width Optional target width to resize the image (default: null).
     * @param int|null $height Optional target height to resize the image (default: null).
     * @param bool $ascii_grayscale Whether to use a weighted grayscale for enhanced contrast based on the ASCII character representation (default: false).
     * @param array<string|int,string> $colors Optional mapping of ASCII characters to foreground colors.
     *                      Example: `[' ' => 'red', '@' => 'blue', '#' => 'green']`. Leave empty for no color.
     * @param array{pixel:int,pixels:int}|int|null $lazy_print Optional lazy printing configuration:
     *                      - If an integer is provided, it defines the delay (in microseconds) between 
     *                        each row of ASCII output (lazy row printing).
     *                      - If an array is provided, it can define both pixel and row delays.
     *                        Example: `['pixel' => 10000, 'pixels' => 100000]` where `pixel` controls the 
     *                        delay between individual pixels and `pixels` controls the delay between rows.
     *                      - Default: `null` (no lazy printing).
     * 
     * @return string|null Returns the generated ASCII art as a string if lazy printing is disabled.
     *                If lazy printing is enabled, null is returned as the output is streamed
     *                directly to the terminal with delays.
     * 
     * @example Usage Examples:
     * 
     * Basic ASCII Art:
     * ```php
     * $art = Image::draw('/path/to/image.jpg', Image::ASCII_CLASSIC, 50, 50, true, ['red', 'green', 'blue']);
     * echo $art;
     * ```
     * 
     * Custom ASCII Characters and Color Mapping:
     * ```php
     * $art = Image::draw('/path/to/image.jpg', '&%,. ', 50, 50, true, ['&' => 'red', '%' => 'green', ',' => 'blue']);
     * echo $art;
     * ```
     * 
     * Lazy Printing Example (animate pixel output):
     * ```php
     * Image::draw('/path/to/image.jpg', Image::ASCII_CLASSIC, 50, 50, false, [], ['pixel' => 50000, 'pixels' => 200000]);
     * ```
     */
    public static function draw(
        string $path, 
        string $ascii = self::ASCII_CLASSIC,
        ?int $width = null, 
        ?int $height = null,
        bool $ascii_grayscale = false,
        array $colors = [],
        array|int|null $lazy_print = null,
    ): ?string 
    {
        self::$weak ??= new WeakMap();
        self::$img = new WeakReference();
        self::$weak[self::$img] = self::load($path);

        if (self::$weak[self::$img] === false) {
            return self::error(
                'Could not load image.', 
                ($lazy_print !== null && $lazy_print !== [])
            );
        }

        if (self::$weak[self::$img] === -1) {
            return self::error(
                'Image is not supported, allowed image types: (jp?eg, png, gif and webp).', 
                ($lazy_print !== null && $lazy_print !== [])
            );
        }

        $window = Terminal::getWidth() - 2;
        $img_width = imagesx(self::$weak[self::$img]);
        $img_height = imagesy(self::$weak[self::$img]);

        $max_width = max($img_width, $window / 2);
        $width = min($width ?? $max_width, $window);
        $height = $height ?? 0;
        $resize = ($width !== $img_width || $height !== $img_height);

        $resized = $resize 
            ? self::resize($width, $height, $window, $img_width, $img_height) 
            : false;

        return self::pixel(
            $height, $width, 
            $ascii, $resized,
            $img_width, $img_height,
            $ascii_grayscale, $colors,
            $lazy_print
        );
    }

     /**
     * Maps characters from an ASCII string to corresponding colors.
     *
     * @param string $ascii A string of ASCII characters to be mapped.
     * @param string[] $colors A list array of colors to map to the ASCII characters.
     * 
     * @return array Return an associative array mapping ASCII characters to their corresponding colors.
     */
    public static function colors(string $ascii, array $colors): array 
    {
        if($colors === []){
            return [];
        }

        $map = [];
        $length = strlen($ascii);
        $count = count($colors);

        for ($i = 0; $i < $length; $i++) {
            if ($i < $count) {
                $map[$ascii[$i]] = $colors[$i];
            } else {
                break;
            }
        }

        return $map;
    }

    /**
     * Draw image pixels as ASCII representation of the image.
     *
     * @param int $height The target height for drawing the image.
     * @param int $width The target width for drawing the image.
     * @param string $ascii The ASCII character set used for generating the output.
     * @param bool $resized Indicates whether the image has been resized. If true, the method 
     *                      uses the resized dimensions. If false, the original image dimensions are used.
     * @param int $new_width The actual width of the image, used for scaling when not resized.
     * @param int $new_height The actual height of the image, used for scaling when not resized.
     * @param bool $ascii_grayscale Whether to use a weighted grayscale for enhanced contrast based on the ASCII character representation (default: false).
     * @param array<string|int,string> $colors Optional foreground color mapping for ASCII characters.
     * @param array{pixel:int,pixels:int}|int|null $lazy_print Optional lazy printing configuration.
     *
     * @return string Return the ASCII art generated from the image, represented as a string or null on lazy printing.
     * @ignore
     */
    protected static function pixel(
        int $height,
        int $width,
        string $ascii,
        bool $resized,
        int $new_width,
        int $new_height,
        bool $ascii_grayscale = false,
        array $colors = [],
        array|int|null $lazy_print = null
    ): ?string
    {
        $draw = '';
        $height = ($resized ? $new_height : $height); 
        $horizontal = ($resized ? $new_width : $width);
        $colors = ($colors !== [] && array_is_list($colors)) 
            ? self::colors($ascii, $colors) 
            : $colors;
    
        // Lazy Printing config checks
        $isLazyOption = $lazy_print && is_array($lazy_print);
        $lazyPixel = $isLazyOption ? ($lazy_print['pixel'] ?? null) : null;
        $lazyPixels = $lazy_print ? ($isLazyOption ? ($lazy_print['pixels'] ?? 0) : $lazy_print) : null;
        $lazy_print = ($lazyPixel || $lazyPixels);
    
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $horizontal; $x++) {
                $rgb = $resized 
                    ? imagecolorat(self::$weak[self::$img], $x, $y) 
                    : imagecolorat(self::$weak[self::$img], intval($x * $new_width / $width), intval($y * $new_height / $height));
    
                if ($lazyPixel !== null) {
                    echo self::grayscale($rgb, $ascii, $ascii_grayscale, $colors);
                    usleep($lazyPixel);
                } else {
                    $draw .= self::grayscale($rgb, $ascii, $ascii_grayscale, $colors);
                }
            }
    
            if (!$lazy_print) {
                $draw .= "\n";
            } else {
                if ($lazyPixels && !$lazyPixel) {
                    echo $draw . "\n";
                    usleep($lazyPixels);
                } elseif ($lazyPixel !== null) {
                    echo "\n";
                }

                $draw = null;
            }
        }
    
        self::free();
        return $draw;
    }

    /**
     * Converts a pixel's color to a grayscale ASCII character.
     *
     * @param int|false $rgb The RGB color value of the pixel. If false, an error occurred.
     * @param string $ascii The set of ASCII characters to use for generating grayscale values.
     * @param bool $ascii_grayscale Whether to use a weighted grayscale for enhanced contrast based on the ASCII character representation (default: false).
     * @param array<string,string> $colors Optional foreground color mapping for ASCII characters.
     *
     * @return string Returns the ASCII character that represents the grayscale value of the pixel.
     * @ignore
     */
    protected static function grayscale(
        int|bool $rgb, 
        string $ascii,
        bool $ascii_grayscale = false,
        array $colors = []
    ): string 
    {
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        if ($ascii_grayscale) {
            $gray = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            $index = intval(pow($gray / 255, 1.2) * (strlen($ascii) - 1));
        } else {
            $gray = ($r + $g + $b) / 3;
            $index = intval($gray / 255 * (strlen($ascii) - 1));
        }

        $character = $ascii[$index];
        return $colors === [] 
            ? $character 
            : Color::style($character, $colors[$character] ?? null);
    }

    /**
     * Resizes an image while maintaining its aspect ratio.
     * 
     * @param int $width The desired width for resizing the image.
     * @param int $height The desired height for resizing the image.
     * @param int $window The terminal windows width.
     * @param int &$img_width The width of the original image. Updated to reflect the new width.
     * @param int &$img_height The height of the original image. Updated to reflect the new height.
     *
     * @return bool Returns true if the image was successfully resized, false otherwise.
     * @ignore
     */
    protected static function resize(
        int $width,
        int $height,
        int $window,
        int &$img_width,
        int &$img_height
    ): bool 
    {
        $target_width = min($width, $window);
        $aspect_ratio = $img_width / $img_height;
    
        if (!$width && !$height) {
            $new_width = $window;
            $new_height = intval($new_width / $aspect_ratio);
        } else {
            $new_width = $width ? min($target_width, $width) : intval($height * $aspect_ratio);
            $new_height = $height ? $height : intval($new_width / $aspect_ratio);
        }
    
        if ($img_width === $new_width && $img_height === $new_height) {
            return false;
        }
    
        $image = imagecreatetruecolor($new_width, $new_height);
        if ($image !== false) {
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefilledrectangle($image, 0, 0, $new_width, $new_height, $white);
    
            if (imagecopyresampled($image, self::$weak[self::$img], 0, 0, 0, 0, $new_width, $new_height, $img_width, $img_height)) {
                self::$weak[self::$img] = $image;
                $img_width = $new_width;
                $img_height = $new_height;
                return true;
            }
    
            imagedestroy($image);
        }
    
        return false;
    }

    /**
     * Load an image from a file, supporting various formats such as JPEG, PNG, and GIF.
     * 
     * @param string $path The file path to the image.
     * 
     * @return GdImage|false|int{-1} Returns a GdImage resource if successful, or false if the image could not be loaded.
     * @ignore
     */
    protected static function load(string $path): GdImage|bool|int
    {
        if (!file_exists($path)) {
            return false;
        }

        $info = getimagesize($path);
        if ($info === false) {
            return false;
        }

        return match ($info['mime']) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => imagecreatefromwebp($path),
            default => -1
        };
    }

    /**
     * Frees memory and destroy the image resources.
     * 
     * @return void
     * @ignore
     */
    protected static function free(): void 
    {
        if (self::$weak[self::$img] instanceof GdImage) {
            imagedestroy(self::$weak[self::$img]);
        }
    }

    /**
     * Output error message if it should and return the error message.
     * 
     * @param string $message The error message.
     * @return bool $output Weather to output the error message.
     * 
     * @return string Returns the error message.
     */
    private static function error(string $message, bool $output): string 
    {
        if($output){
            echo $message;
        }

        return $message;
    }
}