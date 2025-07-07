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

use \Luminova\Interface\LazyInterface;
use \Luminova\Functions\Func;
use \Luminova\Functions\Maths;
use \Luminova\Exceptions\ErrorException;
use \Luminova\Http\FileConfig;
use function \Luminova\Funcs\get_mime;

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
     * Upload error no temp file or data.
     * 
     * @var int UPLOAD_ERR_NO_FILE_DATA
     */
    public const UPLOAD_ERR_NO_FILE_DATA = 11;

    /**
     * Upload error no skip existing file.
     * 
     * @var int UPLOAD_ERR_SKIPPED
     */
    public const UPLOAD_ERR_SKIPPED = 12;

    /**
     * Keep the existing file and save the new one with a random prefix.
     *
     * @var string IF_EXIST_RETAIN
     */
    public const IF_EXIST_RETAIN = 'retain';

    /**
     * Rename the existing file with a random prefix and save the new one.
     *
     * @var string IF_EXIST_RENAME
     */
    public const IF_EXIST_RENAME = 'rename';

    /**
     * Overwrite the existing file if it already exists.
     *
     * @var string IF_EXIST_OVERWRITE
     */
    public const IF_EXIST_OVERWRITE = 'overwrite';

    /**
     * Skip the upload if the file already exists.
     *
     * @var string IF_EXIST_SKIP
     */
    public const IF_EXIST_SKIP = 'skip';

    /**
     * validation message.
     *
     * @var string|null $message
     */
    protected ?string $message = null;

    /**
     * The extracted Mime from file path or binary data.
     *
     * @var string|null $mime
     */
    protected ?string $mime = null;

    /**
     * File upload configurations.
     *
     * @var FileConfig|null $config
     */
    protected ?FileConfig $config = null;

    /**
     * Is content binary data.
     *
     * @var bool|null $isBin
     */
    private ?bool $isBin = null;

    /**
     * Is content base64 encoded.
     *
     * @var bool|null $isBase64
     */
    private ?bool $isBase64 = null;

    /**
     * Constructs a File object for handling uploaded file data.
     *
     * @param int $index The index of the file in the uploaded file array, typically representing the position in a multi-file upload scenario.
     * @param string|null $name The original name of the uploaded file (e.g., `document.pdf`).
     * @param string|null $type The MIME type of the file, detected during upload (e.g., `image/jpeg`, `application/pdf`).
     * @param int $size The size of the uploaded file in bytes.
     * @param string|null $extension The file extension (e.g., `jpg`, `png`, `pdf`).
     * @param string|null $temp The temporary file path where the uploaded file is stored on the server.
     * @param int $error The error code associated with the file upload (default: `UPLOAD_ERR_NO_FILE`).
     * @param string|null $content The file's content in string format, typically used when the file data is stored directly in memory as an alternative to using the `temp`.
     * @param bool $isBlob Indicates whether the uploaded file is handled as a binary large object (BLOB), which is commonly used for in-memory file storage (default: `false`).
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
        protected bool $isBlob = false
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
     * Gets the MIME type of the file.
     *
     * @return string|null Return the MIME type of the file.
     * 
     * Alias of {@see getType()}.
     */
    public function getMime(): ?string
    {
        return $this->getType();
    }

    /**
     * Detect and cache the MIME type from a temporary file or raw binary content.
     * 
     * Useful for cases where the file is uploaded as (`BLOB`), typically, 
     * the MIME type may default to 'application/octet-stream'.
     *
     * This method will:
     * 
     * 1. If a temp file path ($file->temp) is set, use it for detection.
     * 2. Otherwise, fall back to raw binary data ($file->content).
     * 3. Cache the result in $file->mime and return it.
     *
     * @return string|null return the detected MIME type (e.g., "image/png"), or null if no source is available or detection fails.
     * @since 3.5.4
     */
    public function getMimeFromFile(): ?string
    {
        if($this->mime !== null || ($this->temp === null && $this->content === null)){
            return $this->mime;
        }

        return $this->mime = get_mime($this->temp ?? $this->content ?? '') ?: null;
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
     * Gets the file raw binary string content.
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
     * Gets the upload file status code.
     *
     * @return int Return the status code of the file upload.
     * 
     * Alias of {@see getError()}.
     */
    public function getCode(): int
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
     * Gets the file upload configurations.
     *
     * > **Note:** The property value maybe be null if not configured.
     *
     * @return FileConfig Returns instabce of upload configuration.
     * 
     * @example - Example:
     * 
     * ```php
     * $config = $file->getConfig();
     * 
     * // Upload path
     * echo $config->uploadPath;
     * 
     * // Custom data
     * var_dump($config->data);
     * ```
     */
    public function getConfig(): FileConfig
    {
        if($this->config instanceof FileConfig){
            return $this->config;
        }

        return $this->config = new FileConfig();
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
        return $this->isBlob;
    }

    /**
     * Determines if the uploaded content string is likely a binary based on the presence of non-printable characters.
     * 
     * @return bool Return true if it's a binary, false otherwise.
     */
    public function isBinary(): bool
    {
        if($this->isBin !== null){
            return $this->isBinary;
        }

        return $this->isBin = ($this->content === null) ? false : Func::isBinary($this->content);
    }

    /**
     * Determines if the uploaded content string is likely to be Base64-encoded.
     * 
     * @return bool Returns true if the content is likely to be Base64-encoded, false otherwise.
     * 
     * @example - Setting base64 strict validation:
     * 
     * If true, base64_decode() will return false on invalid characters.
     * 
     * ```php
     * $file->setConfig([
     *      'base64_strict' => true
     * ]);
     * ```
     */
    public function isBase64Encoded(): bool
    {
        if($this->isBase64 !== null){
            return $this->isBase64;
        }

        return $this->isBase64 = ($this->content === null) 
            ? false 
            : Func::isBase64Encoded(
                $this->content, 
                (!$this->config instanceof FileConfig) ? false : ($this->config->base64Strict ?? false)
            );
    }

    /**
     * Checks if an error occurred during the file upload process.
     *
     * @return bool Returns true if an error occurred; false otherwise.
     */
    public function isError(): bool
    {
        return $this->error !== UPLOAD_ERR_OK && $this->error !== UPLOAD_ERR_PARTIAL;
    }

    /**
     * Sets the file name, with an option to replace its extension.
     *
     * @param string $name The desired name of the file, without directory paths.
     * @param bool $replaceExtension (optional) If true, updates the file extension based on 
     *              the provided name (default: true).
     * 
     * @return self Return instance of file object.
     * @throws ErrorException Throws if the file name contains directory paths or, when 
     *          `replaceExtension` is enabled, lacks a valid file extension.
     */
    public function setName(string $name, bool $replaceExtension = true): self
    {
        if (str_contains($name, DIRECTORY_SEPARATOR)) {
            throw new ErrorException('Filename cannot contain paths.');
        }

        if($replaceExtension){
            $extension = pathinfo($name, PATHINFO_EXTENSION);

            if (!$extension) {
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
     * @return self Returns the current file instance.
     * 
     * **Supported Configurations:**
     * 
     * - `upload_path`:    (string) The path where files will be uploaded.
     * - `max_size`:       (int) Maximum allowed file size in bytes.
     * - `min_size`:       (int) Minimum allowed file size in bytes.
     * - `allowed_types`:  (string|string[]) A list array of allowed file types or String separated by pipe symbol (e.g, `png|jpg|gif`).
     * - `chunk_length`:   (int) Write length of chunk in bytes (default: 5242880).
     * - `if_existed`:     (string) How to handle existing files (e.g, `File::IF_EXIST_RENAME`, `File::IF_EXIST_*`) (default: `File::IF_EXIST_OVERWRITE`).
     * - `symlink`:        (string) Specify a valid path to create a symlink after upload was completed (e.g `/writeable/storages/`, `/public/assets/`).
     * - `data`:           (mixed) Additional custom configuration information.
     * - `base64_strict`:  (bool) If true, base64_decode() will return false on invalid characters.
     * 
     * @example - Setting configurations:
     * 
     * ```php
     * $file->setConfig([
     *      'max_size' => 5000,
     *      'base64_strict' => true
     * ]);
     */
    public function setConfig(array $config): self
    {
        $this->config ??= new FileConfig();

        foreach ($config as $key => $value) {
     
            if($key === 'base64_strict' || $key === 'base64Strict'){
                $this->isBase64 = null;
            }

            $value = ($key === 'upload_path' || $key === 'uploadPath')
                ? rtrim($value, TRIM_DS) . DIRECTORY_SEPARATOR
                : $value;

            $this->config->setValue($key, $value);
        }

        return $this;
    }

    /**
     * Sets the file's error or feedback message and status code.
     * 
     * Commonly used by the `Luminova\Storages\Uploader` class to provide
     * feedback on upload errors or processing issues.
     *
     * @param string $message The descriptive error or feedback message.
     * @param int $code The status code (e.g., `UPLOAD_ERR_*` or `File::UPLOAD_ERR_*`). 
     *              Defaults to `UPLOAD_ERR_CANT_WRITE`.
     * 
     * @return self Returns the current file instance.
     */
    public function setMessage(string $message, int $code = UPLOAD_ERR_CANT_WRITE): self 
    {
        $this->message = $message;
        $this->error = $code;

        return $this;
    }

    /**
     * Clears all file-related data, resets configuration, and removes the temporary file if it exists.
     *
     * This method is typically called after processing or canceling an upload to ensure no 
     * temporary resources are left behind and the file object is safely reset.
     *
     * @return void
     */
    public function free(): void 
    {
        $this->message = null;
        $this->config = null;
        $this->content = null;
        $this->error = UPLOAD_ERR_NO_FILE;
        $this->isBlob = false;
        $this->mime = null;

        if ($this->temp && is_file($this->temp)) {
            @unlink($this->temp);
        }

        $this->temp = null;
    }

    /**
     * Validates the uploaded file using configured rules such as file size, type, and upload status.
     *
     * This method performs a full validation using `valid()` and returns the current file instance.
     * Use `isError()` to determine if validation failed.
     *
     * @return self Returns the current File instance.
     * 
     * @example - Validate a file:
     * 
     * ```php
     * if ($file->validate()->isError()) {
     *     echo $file->getMessage();
     *     echo $file->getCode();
     * }
     * ```
     */
    public function validate(): self 
    {
        $this->valid();
        return $this;
    }

    /**
     * Validates the uploaded file with detailed error reporting.
     * 
     * This method executes file validation checks against the defined configuration rules.
     * 
     * - Ensures upload completed without native PHP errors.
     * - Checks for non-zero size and temporary file/content availability.
     * - Validates against maximum/minimum file size constraints.
     * - Verifies the file extension against allowed types (if defined).
     *
     * If a validation rule fails, an appropriate error code and message are set.
     *
     * @return bool Returns true if file is valid, false otherwise
     */
    public function valid(): bool
    {
         if ($this->temp === null && $this->content === null) {
            $this->error = self::UPLOAD_ERR_NO_FILE_DATA;
            $this->message = sprintf(
                'No file data received for "%s". Possible causes: ' .
                'upload was interrupted, temporary file was deleted, ' .
                'or file exceeded server limits.',
                $this->name ?? 'unknown'
            );
            return false;
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            $this->message = $this->getErrorDetails($this->error);
            return false;
        }

        if ($this->size === 0) {
            $this->error = self::UPLOAD_ERR_NO_SIZE;
            $this->message = sprintf(
                'Uploaded file "%s" is empty (0 bytes). The file may be corrupted or incomplete.',
                $this->name ?? 'unknown'
            );
            return false;
        }

        if ($this->config instanceof FileConfig && !$this->isPassedCustomValidation()) {
            return false;
        }

        $this->error = UPLOAD_ERR_OK;
        $this->message = sprintf(
            'File "%s" (%s, %d bytes) passed all validation checks.',
            $this->name ?? 'unknown',
            $this->mime ?? 'unknown type',
            $this->size
        );
        return true;
    }

    /**
     * Gets detailed error information for file upload errors with debugging support
     * 
     * @param int $code The UPLOAD_ERR_* error code.
     * 
     * @return string Detailed error message with troubleshooting information
     */
    private function getErrorDetails(int $code): string
    {
        $troubleshoot = '';
        $maxUpload = $maxPost = null;

        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            $maxUpload = Maths::toBytes(ini_get('upload_max_filesize'));
            $maxPost = Maths::toBytes(ini_get('post_max_size'));
        }
        
        $message = match($code) {
            UPLOAD_ERR_INI_SIZE => sprintf(
                'File exceeds server size limit (%s). ',
                Maths::toUnit($maxUpload, true)
            ),
            UPLOAD_ERR_FORM_SIZE => sprintf(
                'File exceeds form size limit (%s). ',
                Maths::toUnit(min($maxUpload, $maxPost), true)
            ),
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. ',
            UPLOAD_ERR_NO_FILE => 'No file was selected or uploaded. ',
            UPLOAD_ERR_NO_TMP_DIR => 'Server is missing temporary upload folder. ',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to save uploaded file to disk. ',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by server extension configuration. ',
            default => sprintf('Unknown upload error (code: %d). ', $code)
        };
        
        $troubleshoot = match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                "Current limits: MaxUpload=%s, MaxPost=%s",
                Maths::toUnit($maxUpload, true),
                Maths::toUnit($maxPost, true)
            ),
            UPLOAD_ERR_PARTIAL => 'This may indicate network problems during upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Contact server administrator to create upload_tmp_dir.',
            UPLOAD_ERR_CANT_WRITE => 'Check server disk space and permissions.',
            UPLOAD_ERR_EXTENSION => 'Check PHP configuration for disabled file types.',
            default => 'Please check server error logs for more details.'
        };
        
        return $message . $troubleshoot;
    }

    /**
     * Custom validations based on file config.
     * 
     * @return bool Return true if passed, otherwise false.
     */
    private function isPassedCustomValidation(): bool 
    {
        if ($this->config->maxSize !== null && $this->size > $this->config->maxSize) {
            $this->error = UPLOAD_ERR_INI_SIZE;
            $this->message = sprintf(
                'File size: "%s" exceeds maximum limit. Maximum allowed size: %s',
                Maths::toUnit($this->size, true),
                Maths::toUnit($this->config->maxSize, true)
            );
            return false;
        }

        if ($this->config->minSize !== null && $this->size < $this->config->minSize) {
            $this->error = self::UPLOAD_ERR_MIN_SIZE;
            $this->message = sprintf(
                'File size: "%s" is too small. Minimum allowed size: %s.', 
                Maths::toUnit($this->size, true),
                Maths::toUnit($this->config->minSize, true)
            );
            return false;
        }

        if ($this->config->allowedTypes !== null && $this->config->allowedTypes) {
            $isArray = is_array($this->config->allowedTypes);
        
            $allowed = $isArray 
                ? $this->config->allowedTypes 
                : explode('|', strtolower($this->config->allowedTypes));
            
            if ($allowed !== [] && !in_array($this->extension, $allowed)) {
                $this->error = UPLOAD_ERR_EXTENSION;
                $this->message = sprintf(
                    'File type: "%s" is not supported. Allowed file types: [%s])', 
                    $this->extension ?? '',
                    ($isArray ? implode('|', $this->config->allowedTypes) : $this->config->allowedTypes)
                );
                return false;
            }
        }

        return true;
    }
}