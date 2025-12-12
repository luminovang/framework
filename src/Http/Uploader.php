<?php
declare(strict_types=1);
/**
 * Luminova Framework HTTP File uploader
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http;

use \Throwable;
use \SplFileObject;
use Luminova\Http\File;
use Luminova\Storage\Filesystem;
use Luminova\Exceptions\StorageException;
use function Luminova\Funcs\make_dir;

final class Uploader
{
    /**
     * Uploads a file to the server.
     *
     * Transfers data from the file's temporary source or raw content to the
     * configured destination. Large files are written incrementally in chunks
     * to reduce memory and resource usage.
     *
     * Files up to 5 MB with a temporary upload source are moved directly without
     * chunked writing.
     *
     * @param File $file The file instance being uploaded.
     * @param string|null $path Optional upload directory. Uses the file
     *                          configuration when omitted.
     * @param int $delay Delay in microseconds between chunk writes to reduce
     *                    resource usage (default: 0).
     * @param string|null &$destination Reference that receives the final upload
     *                                   path on success, or null when the upload
     *                                   fails.
     *
     * @return bool Returns true when the file is uploaded successfully, or false
     *              when the upload fails.
     *
     * @throws StorageException If the upload path is not configured or the
     *                          destination cannot be created.
     */
    public static function upload(
        File $file,
        ?string $path = null,
        int $delay = 0,
        ?string &$destination = null
    ): bool 
    {
        $destination = null;

        if ($file->getTemp() === null && $file->getContent() === null) {
            $file->setMessage(
                'No temporary file or content found for upload.',
                UPLOAD_ERR_NO_TMP_DIR
            );

            return false;
        }

        // Move small temporary uploads directly.
        if (
            $file->getTemp() !== null
            && $file->getSize() <= 5_242_880
        ) {
            return self::move($file, $path, $destination);
        }

        $symlink = null;
        $result = self::beforeUpload($file, $path, $symlink);

        if (is_bool($result)) {
            return $result;
        }

        $config = $file->getConfig();
        $chunk = ($config->chunkLength > 0)
            ? (int) $config->chunkLength
            : 5_242_880;

        $isUploaded = false;

        if ($file->getTemp() !== null) {
            $temp = "{$result}.part";

            if (!self::execute(
                $temp,
                $file->getTemp(),
                $chunk,
                $delay
            )) {
                if (is_file($temp)) {
                    @unlink($temp);
                }

                $file->free();
                $file->setMessage(
                    "Failed to write uploaded file to: {$temp}"
                );

                return false;
            }

            if (!rename($temp, $result)) {
                if (is_file($temp)) {
                    @unlink($temp);
                }

                $file->free();
                $file->setMessage(
                    "Failed to move uploaded file to: {$result}"
                );

                return false;
            }

            $isUploaded = true;
        } elseif ($file->getContent() !== null) {
            try {
                $content = $file->getContent();

                if ($file->isBase64Encoded()) {
                    $content = base64_decode(
                        $content,
                        (bool) $config->base64StrictDecode
                    );

                    if ($content === false) {
                        $file->setMessage(
                            'Failed to decode base64 file content.'
                        );

                        return false;
                    }
                }

                $isUploaded = Filesystem::write(
                    $result,
                    $content,
                    $file->isBinary()
                        ? Filesystem::FILE_BINARY
                        : 0
                );
            } catch (Throwable $e) {
                $file->setMessage(
                    "Failed to write file to: {$result}. Error: "
                    . $e->getMessage()
                );

                return false;
            }
        }

        $file->free();

        if (!$isUploaded) {
            $file->setMessage(
                "Failed to upload file to: {$result}"
            );

            return false;
        }

        $err = null;
        $destination = $result;

        self::trySymlink($destination, $symlink, $err);

        $file->setMessage(
            sprintf(
                'File uploaded successfully to: %s%s',
                $destination,
                ($err === null) ? '' : ", {$err}"
            ),
            UPLOAD_ERR_OK
        );

        return true;
    }

    /**
     * Moves an uploaded file from its temporary location to a permanent destination.
     *
     * This method is intended for files already uploaded to the server's
     * temporary directory and validated as HTTP file uploads.
     *
     * @param File $file The uploaded file instance.
     * @param string|null $path Optional upload directory. Uses the file
     *                          configuration when omitted.
     * @param string|null &$destination Reference that receives the final
     *                                   destination path on success, or null
     *                                   when the move fails.
     *
     * @return bool Returns true when the file is moved successfully, or false
     *              when the upload is invalid or the move fails.
     *
     * @throws StorageException If the upload path is not configured or the
     *                          destination cannot be created.
     */
    public static function move(
        File $file,
        ?string $path = null,
        ?string &$destination = null
    ): bool 
    {
        $destination = null;

        if ($file->getTemp() === null) {
            $file->setMessage(
                'No temporary file found on server.',
                UPLOAD_ERR_NO_TMP_DIR
            );

            return false;
        }

        $result = self::beforeUpload($file, $path, $symlink);

        if (is_bool($result)) {
            $file->free();

            return $result;
        }

        if (!is_uploaded_file($file->getTemp())) {
            $file->free();
            $file->setMessage('Invalid file upload.');

            return false;
        }

        if (!move_uploaded_file($file->getTemp(), $result)) {
            $file->free();
            $file->setMessage(
                "Failed to move uploaded file to: {$result}"
            );

            return false;
        }

        $file->free();

        $destination = $result;
        $err = null;

        self::trySymlink($destination, $symlink, $err);

        $file->setMessage(
            sprintf(
                'File moved successfully to: %s%s',
                $destination,
                ($err === null) ? '' : ", {$err}"
            ),
            UPLOAD_ERR_OK
        );

        return true;
    }

    /**
     * Upload a file in chunks to the configured destination.
     *
     * Large files are written incrementally to a temporary `.part` file and
     * renamed to the final destination after the last chunk is written.
     * The client may split the file into chunks before uploading, such as with
     * Plupload JS.
     *
     * Files smaller than 5 MB are moved directly when a temporary file is
     * available and chunking is not explicitly requested.
     *
     * The chunk size is read from the file configuration and defaults to 5 MB
     * when no valid chunk size is configured.
     *
     * @param File $file The file being uploaded.
     * @param string|null $path Optional upload directory. Uses the file
     *                          configuration when omitted.
     * @param int $chunkIndex Zero-based index of the current chunk (default: 0).
     * @param int $totalChunks Total number of chunks in the upload (default: 1).
     * @param float|int $delay Delay in seconds between chunk writes (default: 0).
     * @param string|null &$destination Reference that receives the final file
     *                                   destination when the upload completes.
     *
     * @return bool|int Returns true when the upload is complete, false on failure,
     *                  or the number of remaining chunks when the upload is
     *                  incomplete.
     *
     * @throws StorageException If the upload path is not configured or cannot
     *                          be written.
     *
     * @example - Uploading a file in chunks:
     * ```php
     * $file = new Luminova\Http\File(...);
     * $chunkIndex = 0;
     * $totalChunks = 5;
     *
     * $result = Uploader::chunk(
     *     $file,
     *     'path/to/upload/',
     *     $chunkIndex,
     *     $totalChunks,
     *     0.01,
     *     $destination
     * );
     *
     * if ($result === true) {
     *     echo "Upload complete: {$destination}";
     * } elseif ($result === false) {
     *     echo "Upload failed.";
     * } else {
     *     echo "Remaining chunks: {$result}";
     * }
     * ```
     */
    public static function chunk(
        File $file,
        ?string $path = null,
        int $chunkIndex = 0,
        int $totalChunks = 1,
        float|int $delay = 0,
        ?string &$destination = null
    ): bool|int 
    {
        $destination = null;

        if ($file->getTemp() === null && $file->getContent() === null) {
            $file->setMessage(
                'No temporary file or content found for chunk upload.',
                UPLOAD_ERR_NO_TMP_DIR
            );

            return false;
        }

        if ($chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks) {
            $file->setMessage(
                sprintf(
                    'Invalid chunk parameters: index=%d, total=%d.',
                    $chunkIndex,
                    $totalChunks
                ),
                UPLOAD_ERR_CANT_WRITE
            );

            return false;
        }

        $forceChunking = (
            $totalChunks > 1
            || $file->getSize() > 5_242_880
        );

        // Use a direct move for files that do not require chunking.
        if (!$forceChunking && $file->getTemp() !== null) {
            return self::move($file, $path, $destination);
        }

        $result = self::beforeUpload($file, $path, $symlink);

        if (is_bool($result)) {
            return $result;
        }

        $destination = $result;
        $isFirstChunk = $chunkIndex === 0;

        $config = $file->getConfig();
        $chunk = ($config->chunkLength > 0)
            ? (int) $config->chunkLength
            : 5_242_880;

        $temp = $destination . '.part';

        try {
            $out = new SplFileObject(
                $temp,
                $isFirstChunk ? 'wb' : 'ab'
            );
        } catch (Throwable $e) {
            $file->setMessage(
                "Failed to open file for writing: {$temp}. Error: "
                . $e->getMessage()
            );

            $destination = null;

            if (is_file($temp)) {
                @unlink($temp);
            }

            return false;
        }

        $delay = (
            $delay === 0
            && $chunk > 10_000_000
        ) ? 0.01 : $delay;

        $isCompleted = match (true) {
            $file->getTemp() !== null => self::writeChunkFromTemp(
                $file,
                $out,
                $temp,
                $chunk,
                $delay
            ),
            $file->getContent() !== null => self::writeChunkFromContent(
                $file,
                $out,
                $file->getContent(),
                $chunk,
                $delay
            ),
            default => false,
        };

        $file->free();
        $out = null;

        if (!$isCompleted) {
            $file->setMessage("Failed to write file: {$destination}");
            $destination = null;

            if (is_file($temp)) {
                @unlink($temp);
            }

            return false;
        }

        $isFinalChunk = (
            $totalChunks === 1
            || $chunkIndex === $totalChunks - 1
        );

        if(!$isFinalChunk){
            $destination = null;
            $remainingChunks = max(0, $totalChunks - $chunkIndex - 1);

            $file->setMessage(
                sprintf(
                    'Chunk %d written to temporary file: %s. Remaining chunks: %d',
                    $chunkIndex,
                    $temp,
                    $remainingChunks
                ),
                UPLOAD_ERR_PARTIAL
            );

            return $remainingChunks;
        }

        if (!rename($temp, $destination)) {
            $file->setMessage(
                "Failed to move file to destination: {$destination}"
            );

            $destination = null;
            @unlink($temp);

            return false;
        }

        $err = null;

        self::trySymlink($destination, $symlink, $err);

        $file->setMessage(
            sprintf(
                'File uploaded successfully to: %s%s',
                $destination,
                ($err === null) ? '' : ", {$err}"
            ),
            UPLOAD_ERR_OK
        );

        return true;
    }

    /**
     * Writes upload content to a file at the specified path.
     * 
     * This method handles writing content to a file, supporting both string and stream resource inputs.
     *
     * @param string $filename The full file path where the content should be written (e.g, `path/to/text.txt`).
     * @param resource|File|string $contents  The content to write, either a string or a stream resource.
     * @param string $mode The file write mode (e.g., 'w' for text, 'wb' for binary).
     * 
     * @return bool Return true if the file is written successfully, false otherwise.
     * 
     * @example - Writing a string to a text file.
     * ```php
     * Uploader::write('path/to/file.txt', 'Hello, World!', 'w');
     * ```
     */
    public static function write(string $filename, mixed $contents, string $mode = 'w'): bool
    {
        $path = dirname($filename);

        if (!make_dir($path)) {
            return false;
        }

        $isResource = is_resource($contents);
        $isFileUploaded = $contents instanceof File;

        if (!$isResource && !$isFileUploaded && !is_string($contents)) {
            return false;
        }

        if ($isResource) {
            $meta = stream_get_meta_data($contents);
            
            // skip pipes/sockets
            if (!isset($meta['uri']) || str_starts_with($meta['wrapper_type'] ?? '', 'php')) {
                return false;
            }
        }

        $file = null;
        $symlink = null;
        $flag = ($mode === 'a' || $mode === 'ab') ? FILE_APPEND : 0;

        if($isFileUploaded){
            $file = $contents;
            $contents = $file->getContent();

            if ($contents !== null) {
                $destination = self::beforeUpload($file, $path, $symlink);
                $filename = ($file->getName() === null) 
                    ? $filename 
                    : $destination;

                $flag = $file->isBinary() ? Filesystem::FILE_BINARY : 0;

                if($file->isBase64Encoded()){
                    $contents = base64_decode($contents, (bool) $file->getConfig()->base64StrictDecode);

                    if ($contents === false) {
                        $file->setMessage(
                            'Failed to decode base64 file content.'
                        );

                        return false;
                    }
                }
            }
    
            if ($contents === null || !is_string($contents)) {
                $file->setMessage(
                    'Invalid file content for writing.'
                );
                return false;
            }
        }

        try {
            $result = Filesystem::write(
                $filename, 
                $contents, 
                $flag
            );
        } catch (Throwable) {
            $result = false;
        } finally {
            if ($isResource) {
                fclose($contents);
            }

            if ($isFileUploaded) {
                $file->free();
            }
        }

        if ($result === false && is_file($filename)) {
            @unlink($filename);
        }

        if($isFileUploaded && $result){
            $err = null;

            self::trySymlink($filename, $symlink, $err);

            $file->setMessage(
                sprintf(
                    'File uploaded successfully to: %s%s', 
                    $filename, 
                    ($err === null) ? '' : ", {$err}"
                ),
                UPLOAD_ERR_OK
            );
            
            return true;
        }

        return $result;
    }

    /**
     * Creates a symbolic link from the target file to the specified link path.
     *
     * @param string $target The target file or directory to which the symlink should point.
     * @param string $link The path where the symbolic link should be created.
     * @param string|null &$error Optional output variable to capture any error message if symlink creation fails.
     * 
     * @return bool Returns true if the symbolic link was successfully created, false otherwise.
     */
    private static function trySymlink(string $target, ?string $link, ?string &$error = null): bool
    {
        $error = null;

        if ($link === null) {
            return true;
        }

        $err = null;

        try{
            if(Filesystem::symbolic($target, $link)){
                return true;
            }
        }catch(Throwable $e){
            $err = $e->getMessage();
        }
    
        $error = sprintf(
            'Failed to create symlink: %s. Error: %s', 
            $link, 
            $err ?? 'Unknown error'
        );

        return false;
    }

    /**
     * Transfers data from a temporary file to a permanent destination in chunks, 
     * enabling controlled resource usage.
     * 
     * @param string $destination The file path to write data to.
     * @param string $temp Temporary file path of the source data.
     * @param int $chunk Size of each data chunk in bytes (default: 5 MB).
     * @param int $delay Optional microsecond delay between chunks for resource management (default: 0).
     * 
     * @return bool Return true if transfer completes successfully, false otherwise.
     */
    private static function execute(
        string $destination, 
        string $temp, 
        int $chunk = 5_242_880,
        int $delay = 0
    ): bool
    {
        try {
            return Filesystem::sink(
                new SplFileObject($destination, 'wb'), 
                new SplFileObject($temp, 'rb'), 
                $chunk, 
                ($delay === 0 && $chunk > 10_000_000) ? 0.01 : $delay
            ) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Writes data from a temporary file to an output file in chunks, with optional delay between writes.
     *
     * @param File $file The file object containing the temporary file path.
     * @param SplFileObject $out The output file object to write data to.
     * @param string $temp The temporary file path to read data from.
     * @param int $chunk The size of each chunk to read and write in bytes (default: 5 MB).
     * @param float|int $delay Optional delay in seconds between writing each chunk (default: 0).
     *
     * @return bool Returns true if the entire file was written successfully, false otherwise.
     */
    private static function writeChunkFromTemp(
        File $file, 
        SplFileObject $out, 
        string $temp, 
        int $chunk, 
        float|int $delay
    ): bool
    {
        $in = null;

        try{
            $in = new SplFileObject($file->getTemp(), 'rb');

            if (!$in->valid()) {
                return false;
            }
      
            return Filesystem::sink($out, $in, $chunk, $delay) > 0;
        } catch (Throwable $e) {
            $file->setMessage(
                "Failed to open temp file for reading: {$temp}. Error: " 
                . $e->getMessage()
            );
        } finally {
            $in = null;
        }

        return false;
    }

    /**
     * Writes data from a string content to an output file in chunks, with optional delay between writes.
     *
     * @param File $file The file object containing the content to write.
     * @param SplFileObject $out The output file object to write data to.
     * @param string $content The string content to write to the output file.
     * @param int $chunk The size of each chunk to read and write in bytes (default: 5 MB).
     * @param float|int $delay Optional delay in seconds between writing each chunk (default: 0).
     *
     * @return bool Returns true if the entire content was written successfully, false otherwise.
     */
    private static function writeChunkFromContent(
        File $file, 
        SplFileObject $out, 
        string $content, 
        int $chunk, 
        float|int $delay
    ): bool
    {
        $content = $file->isBase64Encoded() 
            ? base64_decode($file->getContent(), (bool) $file->getConfig()->base64StrictDecode) 
            : $file->getContent();

        if ($content === false || !is_string($content)) {
            return false;
        }

        return Filesystem::sink($out, $content, $chunk, $delay) > 0;
    } 

    /**
     * Prepares a file for uploading by validating configuration and setting up destination paths.
     * 
     * @param File $file The file object to be validated for upload.
     * @param string|null $path Optional custom path for the upload location.
     * @param string|null $symlink Optional symbolic link path reference.
     * 
     * @return string|bool Returns the final destination path: 
     *              - `true` if the file should be skipped, 
     *              - `false` if upload cannot proceed.
     * @throws StorageException Throws if the upload path is not configured 
     *          or permission to create it was denied.
     */
    private static function beforeUpload(
        File $file,
        ?string $path = null,
        ?string &$symlink = null
    ): string|bool 
    {
        $config = $file->getConfig();
        $path ??= $config->uploadPath;

        if ($path === null || $path === '') {
            throw new StorageException(
                'Upload path must be specified in $file->setConfig(["upload_path" => "path/to/upload/"]) '
                . 'or passed as the second parameter.'
            );
        }

        if (!Filesystem::isPath($path)) {
            throw new StorageException(
                "Expected a directory path, but got: {$path}"
            );
        }

        if ($file->hasError()) {
            throw new StorageException(sprintf(
                'Upload validation failed: %s',
                $file->getMessage()
            ));
        }

        $path = rtrim($path, TRIM_DS) . DIRECTORY_SEPARATOR;

        if (!make_dir($path)) {
            throw new StorageException(sprintf(
                'Failed to create upload directory at "%s". '
                . 'The path does not exist or permission was denied.',
                $path
            ));
        }

        if (!is_writable($path)) {
            $file->setMessage(
                "Upload directory is not writable: {$path}"
            );

            return false;
        }

        $filename = basename($file->getName());
        $destination = $path . $filename;

        if (is_file($destination)) {
            switch ($config->ifExisted) {
                case File::IF_EXIST_OVERWRITE:
                    if (!unlink($destination)) {
                        $file->setMessage(
                            "Failed to remove existing file: {$destination}"
                        );

                        return false;
                    }
                    break;

                case File::IF_EXIST_RENAME:
                    $backup = sprintf(
                        '%sbackup_%s_%s_%s',
                        $path,
                        date('Ymd_His'),
                        bin2hex(random_bytes(4)),
                        $filename
                    );

                    if (!rename($destination, $backup)) {
                        $file->setMessage(
                            "Failed to rename existing file: {$destination}"
                        );

                        return false;
                    }
                    break;

                case File::IF_EXIST_RETAIN:
                    $filename = sprintf(
                        'new_%s_%s_%s',
                        date('Ymd_His'),
                        bin2hex(random_bytes(4)),
                        $filename
                    );

                    $destination = $path . $filename;
                    break;

                default:
                    $file->setMessage(
                        "Skipping upload for '{$filename}', file exists and "
                        . 'the rule is set to skip.',
                        File::UPLOAD_ERR_SKIPPED
                    );

                    return true;
            }
        }

        $symlink = ($config->symlink !== null)
            ? rtrim($config->symlink, TRIM_DS)
                . DIRECTORY_SEPARATOR
                . $filename
            : null;

        return $destination;
    }
}