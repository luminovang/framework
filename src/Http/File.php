<?php 
/**
 * File represents an uploaded file.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Http;

use \Luminova\Functions\Maths;
use \Luminova\Exceptions\ErrorException;
use \stdClass;

class File
{
    /**
     * Upload error file has not size.
     * 
     * @var int UPLOAD_ERR_NO_SIZE
     */
    public const UPLOAD_ERR_NO_SIZE = 9;

    /**
     * Upload error file minimum allowed size.
     * 
     * @var int UPLOAD_ERR_MIN_SIZE
     */
    public const UPLOAD_ERR_MIN_SIZE = 10;

    /**
     * Error message.
     *
     * @var string|null $message
     */
    protected ?string $message = null;

    /**
     * File upload configurations.
     *
     * @var stdClass|null $config
     */
    protected ?stdClass $config = null;

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
    ];

    /**
     * Constructs a File object.
     *
     * @param int $index The index of the file.
     * @param string|null $name The name of the file.
     * @param string|null $type The MIME type of the file.
     * @param int $size The size of the file in bytes.
     * @param string|null $mime The MIME type of the file.
     * @param string|null $extension The file extension.
     * @param string|null $temp The temporary file path.
     * @param int $error The error code of the file upload (default: UPLOAD_ERR_NO_FILE).
     * @param string|null $content The file content string available if `temp_name` is not.
     */
    public function __construct(
        protected int $index = 0,
        protected ?string $name = null,
        protected ?string $type = null,
        protected int $size = 0,
        protected ?string $mime = null,
        protected ?string $extension = null,
        protected ?string $temp = null,
        protected int $error = UPLOAD_ERR_NO_FILE,
        protected ?string $content = null,
    ) {
        $this->message = null;
    }

    /**
     * Magic getter method to access file properties.
     *
     * @param string $key The property to get.
     * 
     * @return mixed Return the value of the property.
     */
    public function __get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }

    /**
     * Gets the index of the file.
     *
     * @return int The index of the file.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Gets the name of the file.
     *
     * @return string|null The name of the file.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Gets the MIME type of the file.
     *
     * @return string|null The MIME type of the file.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Gets the size of the file in bytes.
     *
     * @return int The size of the file in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Gets the MIME type of the file.
     *
     * @return string|null The MIME type of the file.
     */
    public function getMime(): ?string
    {
        return $this->mime;
    }

    /**
     * Gets the file extension.
     *
     * @return string|null The file extension.
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * Gets the temporary file path.
     *
     * @return string|null The temporary file path.
     */
    public function getTemp(): ?string
    {
        return $this->temp;
    }

    /**
     * Gets the file content.
     *
     * @return string|null Return the file content or null.
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Gets the upload error code of the file.
     *
     * @return int The error code of the file upload.
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Gets the validation error message.
     *
     * @return string|null Return the validation error message.
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Gets file upload configurations.
     *
     * @return stdClass|null Return upload configurations.
     */
    public function getConfig(): ?stdClass
    {
        return $this->config;
    }

    /**
     * Sets the name of the file.
     *
     * @param string $name The name of the file.
     * 
     * @return self Return instance of file object.
     * 
     * @throws ErrorException If the filename contains paths or does not have a valid file extension type.
     */
    public function setName(string $name): self
    {
        if (str_contains($name, DIRECTORY_SEPARATOR)) {
            throw new ErrorException('Filename cannot contain paths.');
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        if ($extension === '') {
            throw new ErrorException('Filename does not have a valid file extension type.');
        }

        $this->extension = strtolower($extension);
        $this->name = $name;

        return $this;
    }

    /**
     * Set upload file configurations.
     * 
     * @param array<string,mixed> $config Configuration data for the uploader.
     * 
     * @return self Return instance of file object.
     * 
     * **Supported Configurations:**
     * 
     * - `upload_path`:    (string) The path where files will be uploaded.
     * - `max_size`:       (int) Maximum allowed file size in bytes.
     * - `min_size`:       (int) Minimum allowed file size in bytes.
     * - `allowed_types`:  (string) Allowed file types separated by '|'.
     * - `chunk_length`:   (int) Length of chunk in bytes (default: 5242880).
     * - `if_existed`:     (string) How to handle existing files [overwrite or retain] (default: overwrite).
     * - `symlink`:        (string) Specify a valid path to create a symlink after upload was completed (e.g /public/assets/).
     */
    public function setConfig(array $config): self
    {
        $this->config = new stdClass();

        foreach (self::$configurations as $key => $name) {
            if (isset($config[$key])) {
                $this->config->{$name} = ($key === 'upload_path') 
                    ? rtrim($config[$key], TRIM_DS) . DIRECTORY_SEPARATOR
                    : $config[$key];
            }
        }

        return $this;
    }

    /**
     * Reset file configuration and remove temp file.
     * 
     * @return void 
    */
    public function free(): void 
    {
        $this->message = null;
        $this->config = null;
        $this->content = null;
        @unlink($this->temp);
    }

    /**
     * Checks if the file is valid according to the specified configuration.
     * 
     * @return bool Return true if the file is valid, false otherwise.
    */
    public function valid(): bool
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            $this->message = 'File upload error occurred.';
            return false;
        }


        if ($this->size === 0) {
            $this->error = self::UPLOAD_ERR_NO_SIZE;
            $this->message = 'File is empty or corrupted.';
            return false;
        }

        if ($this->temp === null && $this->content === null) {
            $this->error = UPLOAD_ERR_NO_TMP_DIR;
            $this->message = 'File not found, or may not have been uploaded to the server correctly.';
            return false;
        }

        if (isset($this->config->maxSize) && $this->size > $this->config->maxSize) {
            $this->error = UPLOAD_ERR_INI_SIZE;
            $this->message = 'File size exceeds maximum limit. Maximum allowed size: ' . Maths::toUnit($this->config->maxSize, true) . '.';
            return false;
        }

        if (isset($this->config->minSize) && $this->size < $this->config->minSize) {
            $this->error = self::UPLOAD_ERR_MIN_SIZE;
            $this->message = 'File size is too small. Minimum allowed size: ' . Maths::toUnit($this->config->minSize, true) . '.';
            return false;
        }

        if (isset($this->config->allowedTypes) && $this->config->allowedTypes !== '') {
            $allowed = explode('|', strtolower($this->config->allowedTypes));
            
            if (!in_array($this->extension, $allowed)) {
                $this->error = UPLOAD_ERR_EXTENSION;
                $this->message = 'File type is not allowed. Allowed file types: "' . $this->config->allowedTypes . '".';
                return false;
            }
        }

        $this->error = UPLOAD_ERR_OK;
        $this->message = 'File upload is valid.';
        return true;
    }  
}