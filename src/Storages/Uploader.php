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
namespace Luminova\Storages;

use \Luminova\Http\File;
use \Luminova\Storages\FileManager;
use \Luminova\Exceptions\StorageException;

final class Uploader
{
    /**
     * Uploads a file to the server by transferring data from a temporary source or string content
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
      
        if ($destination === null) {
            return false;
        }
       
        $config = $file->getConfig();
        $chunk = (isset($config->chunkLength) ? (int) $config->chunkLength : 5_242_880);
        $uploaded = false;

        if($file->getTemp() !== null){
            $temp = $destination . '.part';
            $uploaded = self::execute($temp, $file->getTemp(), $chunk, $delay) && rename($temp, $destination);
        }elseif($file->getContent() !== null){
            $uploaded = self::write(
                $destination, 
                $file->isBase64Encoded() ? base64_decode($file->getContent()) :  $file->getContent()
            );
        }

        if ($uploaded) {
            $file->free();
            if($symlink !== null){
                FileManager::symbolic($destination, $symlink);
            }

            return true;
        }
       
        $file->free();
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
            return false;
        }

        $destination = self::beforeUpload($file, $path, $symlink);

        if ($destination === null) {
            return false;
        }

        if (move_uploaded_file($file->getTemp(),  $destination)) {
            $file->free();

            if($symlink !== null){
                FileManager::symbolic($destination, $symlink);
            }
            return true;
        }
        
        $file->free();
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
        
        $config = $file->getConfig();
        $length = (isset($config->chunkLength) ? (int) $config->chunkLength : 5_242_880);
        $temp = $destination . '.part';
        $out = fopen($temp, $chunkIndex === 0 ? 'wb' : 'ab');

        if ($out === false) {
            $destination = null;
            return false;
        }

        $in = fopen($file->getTemp(), "rb");
        if ($in === false) {
            fclose($out);
            $file->free();
            $destination = null;
            return false;
        }

        $uploadDelay = ($uploadDelay === 0 && $length > 10_000_000) ? 10000 : $uploadDelay;
        while ($buffer = fread($in, $length)) {
            fwrite($out, $buffer);

            if($uploadDelay > 0){
                usleep($uploadDelay);
            }
        }

        fclose($in);
        fclose($out);
        $file->free();

        if (!$totalChunks || $chunkIndex === $totalChunks - 1) {
            if (rename($temp, $destination)) {
                if($symlink !== null){
                    FileManager::symbolic($destination, $symlink);
                }
                return true;
            }

            $destination = null;
            return false;
        }

        $destination = null;
        return $totalChunks - $chunkIndex;
    }

    /**
     * Writes provided content to a specified file path.
     *
     * @param string $filename The path and filename to write the content (e.g, `path/to/text.txt`).
     * @param resource|string $contents The string content or resource to be written.
     * 
     * @return bool Return true if the file is written successfully, false otherwise.
     */
    public static function write(string $filename, mixed $contents): bool 
    {
        if(!make_dir(dirname($filename))){
            return false;
        }

        $isResource = is_resource($contents);
        if(is_string($contents) || $isResource){
            $file = fopen($filename, 'w');

            if ($file === false) {
                return false;
            }

            $result = fwrite($file, $isResource ? stream_get_contents($contents) : $contents);
            fclose($file);

            return $result !== false;
        }

        return false;
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
        $in = fopen($temp, 'rb');
        
        if ($in === false) {
            return false;
        }

        $out = fopen($destination, 'wb');

        if ($out === false) {
            fclose($in);
            return false;
        }

        $delay = ($delay === 0 && $chunk > 10_000_000) ? 10000 : $delay;
        while ($buffer = fread($in, $chunk)) {
            fwrite($out, $buffer);

            if($delay > 0){
                usleep($delay);
            }
        }

        fclose($in);
        fclose($out);

        return true;
    }

    /**
     * Prepares a file for uploading by validating configuration and setting up destination paths.
     * 
     * @param File $file The file object to be validated for upload.
     * @param string|null $path Optional custom path for the upload location.
     * @param string|null $symlink Optional symbolic link path reference.
     * 
     * @return string|null Return the upload destination path or null if preparation fails.
     * @throws StorageException Throws if the upload path is not configured or permission to create it was denied.
     */
    private static function beforeUpload(
        File $file, 
        ?string $path = null, 
        ?string &$symlink = null
    ): ?string
    {
        if ($file === false) {
            return null;
        }

        $config = $file->getConfig();

        if(!$path && !isset($config->uploadPath)){
            throw new StorageException('Upload path must be specified in setConfig() method of file object or pass in second parameter.');
        }

        if (!$file->valid()) {
            throw new StorageException('Upload validation failed: ' . $file->getMessage());
        }

        $path = rtrim($path ?? $config->uploadPath, TRIM_DS) . DIRECTORY_SEPARATOR;

        if(!make_dir($path)){
            throw new StorageException("Failed to create upload directory at '{$path}': Path does not exist or permission was denied.");
        }

        $filename = basename($file->getName());
        $destination = $path . $filename;
      
        if(file_exists($destination)){
            if(($config->ifExisted ?? File::IF_EXIST_OVERWRITE) === File::IF_EXIST_OVERWRITE){
                unlink($destination);
            }else{
                $filename =  uniqid('copy') . '-' . $filename;
                $destination = $path . $filename;
            }
        }

        $symlink = isset($config->symlink)
            ? rtrim($config->symlink, TRIM_DS) . DIRECTORY_SEPARATOR . $filename 
            : null;

        return $destination;
    }
}