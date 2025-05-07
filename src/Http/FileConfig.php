<?php 
/**
 * File represents an uploaded file.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Luminova\Http\File;
use \JsonSerializable;

class FileConfig implements JsonSerializable
{
    /**
     * File configuration options keys
     * 
     * @var array<string,string> $configurations
     */
    private static array $configurations = [
        'upload_path'       => 'uploadPath',
        'max_size'          => 'maxSize',
        'min_size'          => 'minSize',
        'allowed_types'     => 'allowedTypes',
        'chunk_length'      => 'chunkLength',
        'if_existed'        => 'ifExisted',
        'symlink'           => 'symlink',
        'base64_strict'     => 'base64Strict',
        'data'              => 'data'
    ];

    /**
     * Create a file upload configurations.
     *
     * @param string|null $uploadPath The target path where uploaded files will be saved.
     * @param int|null $maxSize Maximum allowed file size in bytes.
     * @param int|null $minSize Minimum allowed file size in bytes.
     * @param string[]|string|null $allowedTypes List of permitted file extensions (e.g, `png|jpg` or `['png', 'jpg']`).
     * @param int|null $chunkLength Length of each file chunk in bytes (used for chunked uploads).
     * @param string $ifExisted Strategy to apply if the file already exists (e.g., `File::IF_EXIST_*`).
     * @param string|null $symlink Path to create a symbolic link of the uploaded file.
     * @param string|null $base64Strict Whether to enforce strict Base64 decoding for Base64-encoded uploads.
     * @param mixed $data Additional custom configuration information.
     */
    public function __construct(
        public ?string $uploadPath = null,
        public ?int $maxSize = null,
        public ?int $minSize = null,
        public array|string|null $allowedTypes = null,
        public ?int $chunkLength = null,
        public string $ifExisted = File::IF_EXIST_OVERWRITE,
        public ?string $symlink = null,
        public bool $base64Strict = false,
        public mixed $data = null
    ) {}

    /**
     * Resolves and returns the actual property name if it exists in the configuration.
     *
     * This checks against a static alias map (if defined), or falls back to checking if
     * the property exists directly on the instance.
     *
     * @param string $name The input name or alias of the property.
     * @return string|null The resolved property name if found; otherwise, null.
     */
    public function getName(string $name): ?string
    {
        $property = self::$configurations[$name] ?? null;

        if ($property === null && property_exists($this, $name)) {
            return $name;
        }

        return $property;
    }

    /**
     * Sets the value of a configuration property if it exists.
     *
     * This uses `getName()` to resolve aliases or validate the property name.
     *
     * @param string $property The property name or alias.
     * @param mixed $value The value to assign to the property.
     * @return bool True if the property was successfully set; false if the property does not exist.
     */
    public function setValue(string $property, mixed $value): bool 
    {
        $name = $this->getName($property);

        if ($name === null) {
            return false;
        }

        $this->{$name} = $value;
        return true;
    }

    /**
     * Allow properties to be serialized automatically called when `json_encode()` is used.
     *
     * @return array Return an array representation of the configuration for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    /**
     * Converts the current configuration to an associative array.
     *
     * @return array Return an array containing all configuration properties as key-value pairs.
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Converts the current configuration to a stdClass object.
     *
     * @return object Return stdClass object containing all configuration properties.
     */
    public function toObject(): object
    {
        return (object) $this->jsonSerialize();
    }
}