<?php 
/**
 * File represents a configuration for uploading a file object.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Luminova\Http\File;
use \Luminova\Interface\Arrayable;
use \Luminova\Exceptions\RuntimeException;

class UploadConfig implements Arrayable
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
     * Create a file upload configuration object.
     *
     * Defines upload rules such as destination path, size limits,
     * allowed file types, chunk handling, and Base64 decoding behavior.
     * This object can be passed directly to a File instance or reused
     * across multiple uploads.
     *
     * @param string|null $uploadPath The target path where uploaded files will be saved.
     * @param int|null $maxSize Maximum allowed file size in bytes.
     * @param int|null $minSize Minimum allowed file size in bytes.
     * @param string[]|string|null $allowedTypes Allowed file extensions (e.g, `png|jpg` or `['png', 'jpg']`).
     * @param int|null $chunkLength Chunk size in bytes for chunked uploads (used for chunked uploads).
     * @param string $ifExisted Strategy to apply if the file already exists 
     *                          (e.g., `File::IF_EXIST_OVERWRITE`, `File::IF_EXIST_*`).
     * @param string|null $symlink Optional path to create a symbolic link after upload.
     * @param string|null $base64Strict Whether to enforce strict Base64 decoding for Base64-encoded uploads.
     * @param mixed $data Additional custom configuration information.
     * 
     * @throws RuntimeException If the upload path is invalid or points to a file.
     * 
     * @example - Example:
     *
     * ```php
     * use Luminova\Http\File;
     * use Luminova\Http\UploadConfig;
     *
     * $config = new UploadConfig(
     *     uploadPath: '/writeable/uploads/',
     *     maxSize: 5_000_000,
     *     allowedTypes: ['jpg', 'png'],
     *     base64Strict: true
     * );
     *
     * $file = new File($_FILES['image']);
     * $file->setConfig($config);
     * ```
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
    ) {

        if ($this->uploadPath !== null){
            $this->uploadPath = $this->normalizeDir($this->uploadPath);
        }
    }

    /**
     * Create a configuration instance from an associative array.
     *
     * Intended as the primary entry point when building configuration objects
     * from user input, request data, or static arrays.
     * 
     * **Supported configuration options:**
     *
     * - `upload_path`   (string)  Destination directory for uploaded files.
     * - `max_size`      (int)     Maximum allowed file size in bytes.
     * - `min_size`      (int)     Minimum allowed file size in bytes.
     * - `allowed_types` (string|string[]) Allowed file extensions or a pipe-separated string (e.g. `png|jpg|gif`).
     * - `chunk_length`  (int)     Chunk write size in bytes (default: 5 MB).
     * - `if_existed`    (string)  How to handle existing files
     *                            (e.g. `File::IF_EXIST_RENAME`, `File::IF_EXIST_OVERWRITE`).
     * - `symlink`       (string)  Optional path to create a symlink after upload completes.
     * - `data`          (mixed)   Custom application-specific data.
     * - `base64_strict` (bool)    If true, `base64_decode()` fails on invalid characters.
     *
     * @param array<string,mixed> $options Configuration options as key–value pairs.
     *
     * @return self Returns a populated configuration instance.
     *
     * @example - Example:
     * ```php
     * use Luminova\Http\File;
     * use Luminova\Http\UploadConfig;
     * 
     * $config = UploadConfig::fromArray([
     *     'uploadPath'   => '/writeable/uploads',
     *     'maxSize'      => 5_000_000,
     *     'allowedTypes' => ['jpg', 'png'],
     * ]);
     * 
     * $file = new File($_FILES['image']);
     * $file->setConfig($config);
     * ```
     */
    public static function fromArray(array $options): self
    {
        $config = new self();

        foreach ($options as $key => $value) {
            $config->setValue($key, $value);
        }

        return $config;
    }

    /**
     * Resolves and returns the actual property name if it exists in the configuration.
     *
     * This checks against a static alias map (if defined), or falls back to checking if
     * the property exists directly on the instance.
     *
     * @param string $name The input name or alias of the property.
     * @return string|null The resolved property name if found; otherwise, null.
     */
    protected function getName(string $name): ?string
    {
        $property = self::$configurations[$name] ?? $name;

        if (property_exists($this, $property)) {
            return $property;
        }

        return null;
    }

    /**
     * Sets the value of a configuration property if it exists.
     *
     * This uses `getName()` to resolve aliases or validate the property name.
     *
     * @param string $property The property name or alias.
     * @param mixed $value The value to assign to the property.
     * 
     * @return bool Return true if the property was successfully set; false if the property does not exist.
     * @throws RuntimeException If the path is empty or points to a file.
     */
    protected function setValue(string $property, mixed $value): bool 
    {
        $name = $this->getName($property);

        if ($name === null) {
            return false;
        }

        if ($name === 'uploadPath' && $value !== null){
            $value = $this->normalizeDir($value);
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

    /**
     * {@inheritDoc}
     */
    public function __toArray(): array
    {
        return $this->jsonSerialize();
    }

    /**
     * Normalize and validate a directory path.
     *
     * @param string $path Directory path to normalize.
     *
     * @return string Normalized directory path with a trailing separator.
     * @throws RuntimeException If the path is empty or points to a file.
     */
    private function normalizeDir(string $path): string
    {
        $path = rtrim($path, TRIM_DS);

        if ($path === '') {
            throw new RuntimeException('Upload path cannot be empty.');
        }

        if (is_file($path)) {
            throw new RuntimeException(sprintf(
                'Invalid upload path "%s". A directory path is required.',
                $path
            ));
        }

        return $path . DIRECTORY_SEPARATOR;
    }
}