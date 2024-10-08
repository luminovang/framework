<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storages;

use \Luminova\Http\File;
use \Luminova\Storages\FileManager;
use \Luminova\Exceptions\StorageException;

final class Uploader
{
    /**
     * Upload file to server, by reading entire file from temp and writing to destination.
     *
     * @param File $file Instance of file being uploaded.
     * @param string|null $path Upload file location if not already set in file setConfig method.
     * 
     * @return bool Return true if upload was successful false otherwise.
     * @throws StorageException If upload path is not specified in configuration.
    */
    public static function upload(File $file, ?string $path = null): bool
    {
        $destination = static::beforeUpload($file, $path, $symlink);
      
        if ($destination === false) {
            return false;
        }

        $config = $file->getConfig();
        $chunk = (isset($config->chunkLength) ? (int) $config->chunkLength : 5_242_880);
        $temp = $destination . '.part';

        if (static::execute($temp, $file->getTemp(), $chunk) && rename($temp, $destination)) {
            $file->free();
            if($symlink !== null){
                FileManager::symbolic($destination, $symlink);
            }
            return true;
        }

        $file->free();
        return false;
    }

    /**
     * Moves an uploaded file to a new location.
     *
     * @param File $file Instance of file being uploaded.
     * @param string|null $path Upload file location if not already set in file setConfig method.
     * 
     * @return bool Return true if upload was successful false otherwise.
     * @throws StorageException If upload path is not specified in configuration.
     */
    public static function move(File $file, ?string $path = null): bool
    {
        $destination = static::beforeUpload($file, $path, $symlink);

        if ($destination === false) {
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
        return false;
    }

    /**
     * Uploads a file to the server using stream file upload, allowing large files to be uploaded in chunks.
     *
     * @param File $file Instance of file being uploaded.
     * @param string|null $path The directory path where the file will be stored.
     * @param int $chunk The current chunk part index (start: 0).
     * @param int $chunks The total number of chunks parts the server will be expecting (start: 0).
     * 
     * @return bool|int Return true if upload was successful, false otherwise. If chunks are being uploaded, returns remaining chunks count.
     * 
     * @throws StorageException If upload path is not specified in configuration.
     */
    public static function chunk(File $file, ?string $path = null, int $chunk = 0, int $chunks = 0): bool|int
    {
        $destination = static::beforeUpload($file, $path, $symlink);

        if ($destination === false) {
            return false;
        }
        
        $config = $file->getConfig();
        $length = (isset($config->chunkLength) ? (int) $config->chunkLength : 5_242_880);
        $temp = $destination . '.part';
        $out = fopen($temp, $chunk === 0 ? 'wb' : 'ab');

        if ($out === false) {
            return false;
        }

        $in = fopen($file->getTemp(), "rb");
        if ($in === false) {
            fclose($out);
            $file->free();
            return false;
        }

        while ($buffer = fread($in, $length)) {
            fwrite($out, $buffer);
        }

        fclose($in);
        fclose($out);
        $file->free();

        if (!$chunks || $chunk === $chunks - 1) {
            if (rename($temp, $destination)) {
                if($symlink !== null){
                    FileManager::symbolic($destination, $symlink);
                }
                return true;
            }
            
            return false;
        }

        return $chunks - $chunk;
    }

    /**
     * Save contents to a file.
     *
     * @param string $filename The file path and and name the to put content.
     * @param string|resource $contents The contents to be written to the file.
     * 
     * @return bool Returns true if the file was successfully written, false otherwise.
     */
    public static function write(string $filename, mixed $contents): bool 
    {
       if(is_string($contents) || is_resource($contents)){
            $file = fopen($filename, 'w');

            if ($file === false) {
                return false;
            }

            $result = fwrite($file, is_resource($contents) ? stream_get_contents($contents) : $contents);
            fclose($file);

            return $result !== false;
        }
        return false;
    }

    /**
     * Handles chunked upload of the file.
     *
     * @param string $destination Destination to custom temporal file.
     * @param string $temp The temporary file path for on the server.
     * @param int $chunk Chunk read size in byte of the uploaded file (default: 5mb)
     * 
     * @return bool True on success, false on failure.
    */
    private static function execute(string $destination, string $temp, int $chunk = 5_242_880): bool
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

        while ($buffer = fread($in, $chunk)) {
            fwrite($out, $buffer);
        }

        fclose($in);
        fclose($out);

        return true;
    }

    /**
     * Validate file before uploading
     * 
     * @param File $file Instance of file being uploaded.
     * @param string|null $path Upload file location if not already set in file setConfig method.
     * 
     * @return string|false Return upload destination or false on failure.
     * @throws StorageException If upload path is not specified in configuration.
    */
    private static function beforeUpload(File $file, ?string $path = null, ?string &$symlink = null): string|bool
    {
        if ($file === false) {
            return false;
        }

        $config = $file->getConfig();

        if(!isset($config->uploadPath) && $path === null){
            throw new StorageException('Upload path must be specified in setConfig of file object or pass in second parameter.');
        }

        if (!$file->valid()) {
            return false;
        }

        $path = rtrim($path ?? $config->uploadPath, TRIM_DS) . DIRECTORY_SEPARATOR;
        make_dir($path);

        $filename = basename($file->getName());
        $destination = $path . $filename;
      
        if(file_exists($destination)){
            if(($config->ifExisted ?? File::IF_EXIST_OVERWRITE) === File::IF_EXIST_OVERWRITE){
                unlink($destination);
            }else{
                $filename =  uniqid() . '-' . $filename;
                $destination = $path . $filename;
            }
        }

        $symlink = (isset($config->symlink) ? rtrim($config->symlink, TRIM_DS) . DIRECTORY_SEPARATOR . $filename : null);

        return $destination;
    }
}