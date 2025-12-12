<?php
/**
 * Luminova Framework File upload helper
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Storage;

use \Throwable;
use \SplFileObject;
use \Luminova\Http\File;
use \Luminova\Storage\Filesystem;
use \Luminova\Exceptions\StorageException;
use function \Luminova\Funcs\make_dir;

final class Uploader
{
    /**
     * Uploads a file to the server.
     * 
     * Uploads a file to the server by transferring data from a temporary source or raw file content
     * of file-object to a specified destination. Supports chunked writing for resource management.
     *
     * @param File $file The instance of file object being uploaded.
     * @param string|null $path Optional upload path, if not set in the file configuration.
     * @param int $delay Optional microsecond delay between chunks to limit resource usage (default: 0).
     * @param string|null $destination Output variable holding the final upload path or null if upload fails.
     * 
     * @return bool Return true if the upload is successful, false otherwise.
     * @throws StorageException Throws if the upload path is not configured or permission to create it was denied.
     */
    public static function upload(
        File $file, 
        ?string $path = null, 
        int $delay = 0,
        ?string &$destination = null
    ): bool
    {
        $destination = self::beforeUpload($file, $path, $symlink);

        if($destination === null){
            return false;
        }

        // If file is less than 5mb use move instead
        if($file->getTemp() !== null && $file->getSize() <= 5_242_880){
            return self::move($file, $path, $destination);
        }

        $config = $file->getConfig();
        $chunk = ($config->chunkLength > 0) ? (int) $config->chunkLength : 5_242_880;
        $uploaded = false;

        if($file->getTemp() !== null){
            $temp = $destination . '.part';
            $uploaded = self::execute($temp, $file->getTemp(), $chunk, $delay) && rename($temp, $destination);
        }elseif($file->getContent() !== null){
            $uploaded = self::write(
                $destination, 
                $file->isBase64Encoded() ? base64_decode($file->getContent()) : $file->getContent(),
                $file->isBinary() ? 'wb' : 'w'
            );
        }

        if ($uploaded) {
            $file->free();
            if($symlink !== null){
                Filesystem::symbolic($destination, $symlink);
            }

            $file->setMessage("File uploaded successfully to: {$destination}", UPLOAD_ERR_OK);
            return true;
        }
       
        $file->free();
        $file->setMessage("Failed to upload file to: {$destination}");
        $destination = null;
        return false;
    }

    /**
     * Moves an uploaded file from a temporary location to a permanent destination.
     *
     * @param File $file The file object being moved.
     * @param string|null $path Optional upload path, if not set in the file configuration.
     * @param string|null $destination Output variable holding the final destination path or null if move fails.
     * 
     * @return bool return true if the move is successful, false otherwise.
     * @throws StorageException Throws if the upload path is not configured or permission to create it was denied.
     */
    public static function move(
        File $file, 
        ?string $path = null,
        ?string &$destination = null
    ): bool
    {
        if(!$file->getTemp()){
            $file->setMessage('No temporary file found on server.', UPLOAD_ERR_NO_TMP_DIR);
            return false;
        }

        $destination = self::beforeUpload($file, $path, $symlink);

        if($destination === null){
            return false;
        }

        if (!is_uploaded_file($file->getTemp())) {
            $file->free();
            $file->setMessage("Invalid file upload.");
            $destination = null;
            return false;
        }

        if (move_uploaded_file($file->getTemp(), $destination)) {
            $file->free();

            if($symlink !== null){
                Filesystem::symbolic($destination, $symlink);
            }

            $file->setMessage("File successfully moved to: {$destination}", UPLOAD_ERR_OK);
            return true;
        }
        
        $file->free();
        $file->setMessage("Failed to move uploaded file to: {$destination}");
        $destination = null;
        return false;
    }

    /**
     * Uploads a file in chunks to a specified destination.
     *
     * This method enables large file uploads by splitting the file into smaller, manageable chunks.
     * The client-side (e.g., `PluUpload Js`) typically handles chunking before sending data.
     *
     * If the file object does not specify a chunk read size, it defaults to 5MB per chunk files larger than 5MB.
     *
     * @param File $file The file instance being uploaded.
     * @param string|null $path Optional directory path for the upload. If not provided, it falls back to the file configuration.
     * @param int $chunkIndex The current chunk's index, starting from 0 (default: 0).
     * @param int $totalChunks The total number of chunks for the file, starting from 0 (default: 0).
     * @param int $uploadDelay Optional microsecond delay between chunk writes to manage resource usage (default: 0).
     * @param string|null $destination Output variable storing the final destination path or null if the upload fails.
     *
     * @return bool|int Returns `true` if the upload is complete, `false` on failure.
     *                  If the upload is still in progress, returns the remaining chunk count.
     *
     * @throws StorageException If the upload path is not configured or lacks proper permissions.
     */
    public static function chunk(
        File $file, 
        ?string $path = null, 
        int $chunkIndex = 0, 
        int $totalChunks = 0,
        int $uploadDelay = 0,
        ?string &$destination = null
    ): bool|int
    {
        $destination = self::beforeUpload($file, $path, $symlink);
    
        if ($destination === null) {
            return false;
        }
    
        // If file is less than 5mb use move instead
        if ($totalChunks <= 1 && $file->getTemp() !== null && $file->getSize() <= 5_242_880) {
            return self::move($file, $path, $destination);
        }
    
        $config = $file->getConfig();
        $chunk = ($config->chunkLength > 0) ? (int) $config->chunkLength : 5_242_880;
        $temp = $destination . '.part';
        
        try {
            $out = new SplFileObject($temp, ($chunkIndex === 0) ? 'wb' : 'ab');
        } catch (Throwable $e) {
            $file->setMessage("Failed to open file for writing: {$temp}. Error: " . $e->getMessage());
            $destination = null;
            return false;
        }
    
        $error = true;
        $uploadDelay = ($uploadDelay === 0 && $chunk > 10_000_000) ? 10000 : $uploadDelay;
    
        if ($file->getTemp() !== null) {
            try{
                $in = new SplFileObject($file->getTemp(), 'rb');
            } catch (Throwable $e) {
                $file->setMessage("Failed to open temp file for reading: {$temp}. Error: " . $e->getMessage());
                $destination = null;
                return false;
            }
    
            if ($in->valid()) {
                $error = false;
    
                while (!$in->eof()) {
                    $buffer = $in->fread($chunk);
                    if($buffer === false){
                        continue;
                    }
    
                    $out->fwrite($buffer);
    
                    if ($uploadDelay > 0) {
                        usleep($uploadDelay);
                    }
                }
            }

            $in = null;
        } elseif ($file->getContent() !== null) {
            $content = $file->isBase64Encoded() 
                ? base64_decode($file->getContent(), $config->base64Strict) 
                : $file->getContent();
    
            if ($content !== false && is_string($content)) {
                $error = false;
    
                for ($offset = 0, $size = strlen($content); $offset < $size; $offset += $chunk) {
                    $out->fwrite(substr($content, $offset, $chunk));
    
                    if ($uploadDelay > 0) {
                        usleep($uploadDelay);
                    }
                }
            }
        }
    
        $file->free();
        $out = null; 
    
        if ($error) {
            $file->setMessage("Failed to write chunk to temp file: {$temp}");
            $destination = null;
            return false;
        }
    
        if (!$totalChunks || $chunkIndex === $totalChunks - 1) {
            if (rename($temp, $destination)) {
                if ($symlink !== null) {
                    Filesystem::symbolic($destination, $symlink);
                }
    
                $file->setMessage("File successfully uploaded to: {$destination}", UPLOAD_ERR_OK);
                return true;
            }
    
            $file->setMessage("Failed to move final chunk to destination: {$destination}");
            $destination = null;
            return false;
        }
    
        $file->setMessage("Chunk {$chunkIndex} written to temp file: {$temp}", UPLOAD_ERR_PARTIAL);
        $destination = null;
        return $totalChunks - $chunkIndex;
    }

    /**
     * Writes content to a file at the specified path.
     *
     * @param string $filename The full file path where the content should be written (e.g, `path/to/text.txt`).
     * @param resource|string $contents  The content to write, either a string or a stream resource.
     * @param string $mode  The file write mode (e.g., 'w' for text, 'wb' for binary).
     * 
     * @return bool Return true if the file is written successfully, false otherwise.
     */
    public static function write(string $filename, mixed $contents, string $mode = 'w'): bool
    {
        if (!make_dir(dirname($filename))) {
            return false;
        }

        $isResource = is_resource($contents);

        if (!is_string($contents) && !$isResource) {
            return false;
        }

        if ($isResource) {
            $meta = stream_get_meta_data($contents);
            
            // skip pipes/sockets
            if (!isset($meta['uri']) || str_starts_with($meta['wrapper_type'] ?? '', 'php')) {
                return false;
            }

            $contents = stream_get_contents($contents);
            if ($contents === false) {
                return false;
            }
        }

        try {
            $file = new SplFileObject($filename, $mode);
            $bytes = $file->fwrite($contents);
            return $bytes !== false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Transfers data from a temporary file to a permanent destination in chunks, enabling controlled resource usage.
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
            $in = new SplFileObject($temp, 'rb');
            $out = new SplFileObject($destination, 'wb');
        } catch (Throwable $e) {
            return false;
        }
    
        $delay = ($delay === 0 && $chunk > 10_000_000) ? 10000 : $delay;
    
        while (!$in->eof()) {
            $buffer = $in->fread($chunk);
    
            if ($buffer === false || $out->fwrite($buffer) === false) {
                return false;
            }
    
            if ($delay > 0) {
                usleep($delay);
            }
        }
    
        return true;
    }    

    /**
     * Prepares a file for uploading by validating configuration and setting up destination paths.
     * 
     * @param File $file The file object to be validated for upload.
     * @param string|null $path Optional custom path for the upload location.
     * @param string|null $symlink Optional symbolic link path reference.
     * 
     * @return string|null Returns the final destination path or null if upload is skipped.
     * @throws StorageException Throws if the upload path is not configured or permission to create it was denied.
     */
    private static function beforeUpload(
        File $file, 
        ?string $path = null, 
        ?string &$symlink = null
    ): ?string
    {
        $config = $file->getConfig();
        $path ??= $config->uploadPath;

        if(!$path){
            throw new StorageException(
                'Upload path must be specified in: $file->setConfig(["upload_path" => "path/to/upload/"]) method of file object or pass in second parameter.'
            );
        }

        if (!Filesystem::isPath($path)) {
            throw new StorageException("Expected a directory path, but got: {$path}");
        }

        if (!$file->valid()) {
            throw new StorageException(sprintf('Upload validation failed: %s', $file->getMessage()));
        }

        $path = rtrim($path, TRIM_DS) . DIRECTORY_SEPARATOR;

        if(!make_dir($path)){
            throw new StorageException(sprintf(
                'Failed to create upload directory at "%s". Path does not exist or permission was denied.',
                $path
            ));
        }
        
        if (!is_writable($path)) {
            $file->setMessage("Directory is not writable: " . $path);
            return null;
        }

        $filename = basename($file->getName());
        $destination = $path . $filename;
      
        if(is_file($destination)){
            if($config->ifExisted === File::IF_EXIST_OVERWRITE){
                unlink($destination);
            }elseif($config->ifExisted === File::IF_EXIST_RENAME){
                rename($destination, $path . uniqid('old', true) . '_' . $filename);
            }elseif($config->ifExisted === File::IF_EXIST_RETAIN){
                $filename = uniqid('copy', true) . '_' . $filename;
                $destination = $path . $filename;
            }else{
                $file->setMessage(
                    "Skipping upload for '{$filename}', file exists and the rule is set to skip.",
                    File::UPLOAD_ERR_SKIPPED
                );
                return null;
            }
        }

        $symlink = $config->symlink
            ? rtrim($config->symlink, TRIM_DS) . DIRECTORY_SEPARATOR . $filename 
            : null;

        return $destination;
    }
}