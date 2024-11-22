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

use \Luminova\Interface\LazyInterface;
use \Luminova\Functions\Func;
use \Luminova\Functions\Maths;
use \Luminova\Exceptions\ErrorException;
use \stdClass;

class File implements LazyInterface
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
     * Retain old version and create new version of file if exists.
     * 
     * @var string IF_EXIST_RETAIN
     */
    public const IF_EXIST_RETAIN = 'retain';

    /**
     * Overwrite existing file if exists
     * 
     * @var string IF_EXIST_OVERWRITE
     */
    public const IF_EXIST_OVERWRITE = 'overwrite';

    /**
     * validation message.
     *
     * @var string|null $message
     */
    protected ?string $message = null;

    /**
     * Mime from temp file.
     *
     * @var string|null $mime
     */
    protected ?string $mime = null;

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
        'symlink'           => 'symlink'
    ];

    /**
     * Constructs a File object for handling uploaded file data.
     *
     * @param int $index The index of the file in the uploaded file array, typically representing the position in a multi-file upload scenario.
     * @param string|null $name The original name of the uploaded file (e.g., `document.pdf`).
     * @param string|null $type The MIME type of the file (e.g., `image/jpeg`, `application/pdf`).
     * @param int $size The size of the uploaded file in bytes.
     * @param string|null $extension The file extension (e.g., `jpg`, `png`, `pdf`).
     * @param string|null $temp The temporary file path where the uploaded file is stored on the server.
     * @param int $error The error code associated with the file upload (default: `UPLOAD_ERR_NO_FILE`).
     * @param string|null $content The file's content in string format, typically used when the file data is stored directly in memory as an alternative to using the `temp`.
     * @param bool $is_blob Indicates whether the uploaded file is handled as a binary large object (BLOB), which is commonly used for in-memory file storage (default: `false`).
     */
    public function __construct(
        protected int $index = 0,
        protected ?string $name = null,
        protected ?string $type = null,
        protected int $size = 0,
        protected ?string $extension = null,
        protected ?string $temp = null,
        protected int $error = UPLOAD_ERR_NO_FILE,
        protected ?string $content = null,
        protected bool $is_blob = false
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
     * @return int Return the index of the file.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Gets the name of the file.
     *
     * @return string|null Return the name of the file.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Gets the size of the file in bytes.
     *
     * @return int Return the size of the file in bytes.
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Alias of {@see getType()}.
     *
     * Gets the MIME type of the file.
     *
     * @return string|null Return the MIME type of the file.
     */
    public function getMime(): ?string
    {
        return $this->getType();
    }

    /**
     * Retrieves the MIME type directly from the temporary file path.
     * Useful for cases where the file is uploaded as a large object (BLOB).
     * Typically, the MIME type may default to 'application/octet-stream'.
     *
     * @return string|null Returns the MIME type of the file, or null if no temporary file exists.
     */
    public function getMimeFromTemp(): ?string
    {
        if($this->temp === null || $this->mime !== null){
            return $this->mime;
        }

        $mime = get_mime($this->temp);
        return $this->mime = ($mime === false) ? null : $mime;
    }

    /**
     * Gets the MIME type of the file.
     *
     * @return string|null Return the MIME type of the file.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Gets the file extension.
     *
     * @return string|null Return the file extension.
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * Gets the temporary file path.
     *
     * @return string|null Return the temporary file path.
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
     * @return int Return the error code of the file upload.
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
     * Determines if the file is uploaded as a BLOB (Binary Large Object).
     * 
     * This method checks whether the file was uploaded as a BLOB, 
     * typically used for large file uploads or when the file's content is handled directly in binary form.
     *
     * @return bool Returns true if the file is a BLOB, otherwise false.
     */
    public function isBlob(): bool
    {
        return $this->is_blob;
    }

    /**
     * Determines if the uploaded content string is likely a binary based on the presence of non-printable characters.
     * 
     * @return bool Return true if it's a binary, false otherwise.
     */
    public function isBinary(): bool
    {
        return ($this->content === null) 
            ? false 
            : Func::isBinary($this->content);
    }

    /**
     * Determines if the uploaded content string is likely to be Base64-encoded.
     *
     * @return bool Returns true if the content is likely to be Base64-encoded, false otherwise.
     */
    public function isBase64Encoded(): bool
    {
        return ($this->content === null) 
            ? false 
            : Func::isBase64Encoded($this->content);
    }

    /**
     * Checks if an error occurred during the file upload process.
     *
     * @return bool Returns true if an error occurred; false otherwise.
     */
    public function isError(): bool
    {
        return $this->error !== UPLOAD_ERR_OK;
    }

    /**
     * Sets the file name, with an option to replace its extension.
     *
     * @param string $name The desired name of the file, without directory paths.
     * @param bool $replace_extension (optional) If true, updates the file extension based on 
     * the provided name (default: true).
     * 
     * @return self Return instance of file object.
     * 
     * @throws ErrorException Throws if the file name contains directory paths or, when 
     * `replace_extension` is enabled, lacks a valid file extension.
     */
    public function setName(string $name, bool $replace_extension = true): self
    {
        if (str_contains($name, DIRECTORY_SEPARATOR)) {
            throw new ErrorException('Filename cannot contain paths.');
        }

        if($replace_extension){
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            if ($extension === '') {
                throw new ErrorException('Filename does not have a valid file extension type.');
            }

            $this->extension = strtolower($extension);
        }

        $this->name = $name;
        return $this;
    }

    /**
     * Set file configurations for upload behavior file type restriction.
     * 
     * @param array<string,mixed> $config An associative array of file configuration key and value.
     * 
     * @return self Return instance of file object.
     * 
     * **Supported Configurations:**
     * 
     * - `upload_path`:    (string) The path where files will be uploaded.
     * - `max_size`:       (int) Maximum allowed file size in bytes.
     * - `min_size`:       (int) Minimum allowed file size in bytes.
     * - `allowed_types`:  (string|string[]) A list array of allowed file types or String separated by pipe symbol (e.g, `png|jpg|gif`).
     * - `chunk_length`:   (int) Write length of chunk in bytes (default: 5242880).
     * - `if_existed`:     (string) How to handle existing files [`File::IF_EXIST_OVERWRITE` or `File::IF_EXIST_RETAIN`] (default: `File::IF_EXIST_OVERWRITE`).
     * - `symlink`:        (string) Specify a valid path to create a symlink after upload was completed (e.g `/writeable/storages/`, `/public/assets/`).
     */
    public function setConfig(array $config): self
    {
        $this->config ??= new stdClass();

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
     * Resets the file configuration, clears content, and deletes any temporary file.
     *
     * @return void
     */
    public function free(): void 
    {
        $this->message = null;
        $this->config = null;
        $this->content = null;
        $this->error = UPLOAD_ERR_NO_FILE;
        $this->is_blob = false;
        $this->mime = null;

        if (is_file($this->temp)) {
            @unlink($this->temp);
        }
        $this->temp = null;
    }

    /**
     * Validates the uploaded file against the defined configuration rules.
     *
     * @return bool Returns true if the file is valid; false otherwise, with 
     *              an appropriate error code and message set.
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
            $this->message = 'File not found, or may not have been uploaded correctly.';
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

        if (isset($this->config->allowedTypes) && $this->config->allowedTypes) {
            $isArray = is_array($this->config->allowedTypes);
            $allowed = $isArray 
                ? $this->config->allowedTypes 
                : explode('|', strtolower($this->config->allowedTypes));
            
            if ($allowed !== [] && !in_array($this->extension, $allowed)) {
                $this->error = UPLOAD_ERR_EXTENSION;
                $this->message = 'File type is not supported. Allowed file types: "[' . ($isArray ? implode('|', $this->config->allowedTypes) : $this->config->allowedTypes) . ']".';
                return false;
            }
        }

        $this->error = UPLOAD_ERR_OK;
        $this->message = 'File upload is valid.';
        return true;
    }  
}