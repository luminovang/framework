<?php 
/**
 * Luminova Framework background queue model.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Media;

use \Luminova\Exceptions\RuntimeException;

/**
 * ImageInfo class provides a structured representation of image metadata, 
 * including dimensions, type, MIME type, and additional information.
 * 
 * It offers methods for creating an instance from various sources and checking the image type.
 * 
 * @method bool isJPEG() Checks if the image type is JPEG.
 * @method bool isPNG() Checks if the image type is PNG.
 * @method bool isGIF() Checks if the image type is GIF.
 * @method bool isBMP() Checks if the image type is BMP.
 * @method bool isWEBP() Checks if the image type is WEBP.
 * @method bool isAVIF() Checks if the image type is AVIF.
 * @method bool isSWF() Checks if the image type is SWF.
 * @method bool isPSD() Checks if the image type is PSD.
 * @method bool isTIFF_II() Checks if the image type is TIFF (Intel byte order).
 * @method bool isTIFF_MM() Checks if the image type is TIFF (Motorola byte order).
 * @method bool isJPC() Checks if the image type is JPC.
 * @method bool isJP2() Checks if the image type is JP2.
 * @method bool isJPX() Checks if the image type is JPX.
 * @method bool isJB2() Checks if the image type is JB2.
 * @method bool isSWC() Checks if the image type is SWC.
 * @method bool isIFF() Checks if the image type is IFF.
 * @method bool isWBMP() Checks if the image type is WBMP.
 * @method bool isXBM() Checks if the image type is XBM.
 * @method bool isICO() Checks if the image type is ICO.
 * @method int getWidth() Gets the width of the image.
 * @method int getHeight() Gets the height of the image.
 * @method int getType() Gets the type of the image as an integer.
 * @method string getMime() Gets the MIME type of the image.
 * @method string|null getExtension() Gets the file extension of the image, if available.
 * @method int|null getChannels() Gets the number of channels in the image, if available.
 * @method array getInfo() Gets additional information about the image as an associative array.
 */
class ImageInfo
{
    /**
     * A static array mapping image type constants to their corresponding integer values,
     *
     * @var array|null $types
     */
    private static ?array $types = null;

    /**
     * Constructs an ImageInfo instance with the provided metadata.
     *
     * @param int $width The width of the image in pixels.
     * @param int $height The height of the image in pixels.
     * @param int $type The type of the image as an integer (e.g., IMAGETYPE_JPEG).
     * @param string $mime The MIME type of the image (e.g., "image/jpeg").
     * @param string|null $extension The file extension of the image (e.g., "jpg"), if available.
     * @param int|null $channels The number of channels in the image, if available (e.g., 3 for RGB, 4 for RGBA).
     * @param array $info Additional information about the image as an associative array.
     */
    public function __construct(
        public int $width,
        public int $height,
        public int $type,
        public string $mime,
        public ?string $extension = null,
        public ?int $channels = null,
        public array $info = []
    ) {}

    /**
     * Create an ImageInfo instance from file, URL or raw image data.
     * 
     * This method creates an ImageInfo instance from a given source, 
     * which can be a file path, URL, or raw image data.
     *
     * @param string $source The source of the image, which can be a file path, URL, or raw image data.
     * 
     * @return static An instance of ImageInfo containing the metadata of the image.
     * @throws RuntimeException If the image is invalid or cannot be processed.
     */
    public static function from(string $source): ?static
    {
        $info = [];
        $size = false;
        $isFile = false;
        $extension = null;

        if(is_file($source) || filter_var($source, FILTER_VALIDATE_URL)){
            $size = getimagesize($source, $info);
            $isFile = true;
        }else{
            $size = getimagesizefromstring($source);
        }

		if ($size === false) {
			throw new RuntimeException('Image is invalid or could not be processed');
		}

        if($isFile){
            $extension = pathinfo($source, PATHINFO_EXTENSION);
        }

        if(!$extension){
            $extension = self::getExtensionFromType((int) $size[2]);
        }

        if(!$extension){
            throw new RuntimeException('Could not determine the file extension of the image');
        }

        $instance = new static(
            width: (int) $size[0],
            height: (int) $size[1],
            type: (int) $size[2],
            mime: $size['mime'] ?? image_type_to_mime_type((int) $size[2]),
            extension: strtolower($extension),
            channels: $size['channels'] ?? null,
            info: $info
        );

        if($isFile){
            $instance->info['file'] = $source;
        }

        return $instance;
    }

    /**
     * Retrieves the EXIF data from the image, if available.
     *
     * @return array An associative array containing the EXIF data of the image, or an empty array if not available.
     */
    public function getExifData(): array
    {
        $file = $this->info['file'] ?? null;

        if(!$file  || !function_exists('exif_read_data')){
            return [];
        }

        $exif = @exif_read_data($file, null, true, true);
        return is_array($exif) ? $exif : [];
    }

    /**
     * Checks if the image type matches the given type.
     *
     * @param string|int $type The type to check against, either as a string (e.g., "JPEG") 
     *      or an integer (e.g., IMAGETYPE_JPEG).
     * 
     * @return bool Return true if the image type matches the given type, false otherwise.
     */
    public function is(string|int $type): bool
    {
        self::intSupportedTypes();

        if($this->type === $type){
            return true;
        }

        if(!is_string($type)){
            return false;
        }

        $type = strtoupper($type);
        return isset(self::$types[$type]) && $this->type === self::$types[$type];
    }

    /**
     * Gets the MIME type of the image based on its type.
     *
     * @return string The MIME type of the image (e.g., "image/jpeg").
     */
    public function getTypeName(): string
    {
        return image_type_to_mime_type($this->type);
    }

    /**
     * Magic method to handle dynamic method calls for checking image types and getting properties.
     *
     * This method allows for dynamic method calls such as isJPEG(), getWidth(), etc.
     *
     * @param string $name The name of the method being called.
     * @param array $arguments The arguments passed to the method.
     * 
     * @return mixed The result of the dynamic method call.
     * @throws RuntimeException If the method does not exist or is not properly formatted.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $length = strlen($name);

        if(str_starts_with($name, 'is') && $length > 2){
            return $this->is(substr($name, 2));
        }

        if(str_starts_with($name, 'get') && $length > 3){
            $property = substr($name, 3);
            return $this->{$property} ?? null;
        }

        throw new RuntimeException("Method {$name} does not exist on " . static::class);
    }

    /**
     * Retrieves the file extension corresponding to a given image type integer.
     *
     * @param int $type The image type as an integer (e.g., IMAGETYPE_JPEG).
     * 
     * @return string|null The file extension corresponding to the image type, or null if not found.
     */
    private static function getExtensionFromType(int $type): ?string
    {
        self::intSupportedTypes();

        foreach(self::$types as $ext => $t){
            if($t === $type){
                return strtolower($ext);
            }
        }

        return null;
    }

    /**
     * Initializes the static array of supported image types 
     * if it has not already been initialized.
     *
     * @return void
     */
    private static function intSupportedTypes(): void
    {
        self::$types ??= [
            'JPEG' => IMAGETYPE_JPEG,
            'PNG' => IMAGETYPE_PNG,
            'GIF' => IMAGETYPE_GIF,
            'BMP' => IMAGETYPE_BMP,
            'WEBP' => IMAGETYPE_WEBP,
            'AVIF' => IMAGETYPE_AVIF,
            'SWF' => IMAGETYPE_SWF,
            'PSD' => IMAGETYPE_PSD,
            'TIFF_II' => IMAGETYPE_TIFF_II,
            'TIFF_MM' => IMAGETYPE_TIFF_MM,
            'JPC' => IMAGETYPE_JPC,
            'JP2' => IMAGETYPE_JP2,
            'JPX' => IMAGETYPE_JPX,
            'JB2' => IMAGETYPE_JB2,
            'SWC' => IMAGETYPE_SWC,
            'IFF' => IMAGETYPE_IFF,
            'WBMP' => IMAGETYPE_WBMP,
            'XBM' => IMAGETYPE_XBM,
            'ICO' => IMAGETYPE_ICO,
        ];
    }
}