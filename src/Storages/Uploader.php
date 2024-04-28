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
use \Luminova\Exceptions\StorageException;

final class Uploader
{
    /**
     * Upload file to server.
     *
     * @param File $file Instance of file being uploaded.
     * @param string|null $path Upload file location if not already set in file setConfig method.
     * 
     * @return bool Return true if upload was successful false otherwise.
     * @throws StorageException If upload path is not specified in configuration.
    */
    public static function upload(File $file, ?string $path = null): bool
    {
        $destination = static::beforeUpload($file, $path);
      
        if ($destination === false) {
            return false;
        }

        $config = $file->getConfig();
        $chunk = (isset($config->chunkLength) ? (int) $config->chunkLength : 5242880);
        $temp = $destination . '.part';

        if (static::execute($temp, $file->getTemp(), $chunk) && rename($temp, $destination)) {
            $file->free();
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
        $destination = static::beforeUpload($file, $path);

        if ($destination === false) {
            return false;
        }

        if (move_uploaded_file($file->getTemp(),  $destination)) {
            $file->free();
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
     * @param int $chunk The current chunk part index (start: 1).
     * @param int $chunks The total number of chunks parts the server will be expecting (start: 1).
     * 
     * @return bool|int Return true if upload was successful, false otherwise. If chunks are being uploaded, returns remaining chunks count.
     * 
     * @throws StorageException If upload path is not specified in configuration.
     */
    public static function chunk(File $file, ?string $path = null, int $chunk = 1, int $chunks = 1): bool|int
    {
        $destination = static::beforeUpload($file, $path);

        if ($destination === false) {
            return false;
        }
        
        $config = $file->getConfig();
        $length = (isset($config->chunkLength) ? (int) $config->chunkLength : 5242880);
        $temp = $destination . '.part';
        $out = fopen($temp, $chunk === 1 ? 'wb' : 'ab');

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

        if ($chunks !== 1 || $chunk === $chunks) {
            if (rename($temp, $destination)) {
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
     * @param string $contents The contents to be written to the file.
     * 
     * @return bool Returns true if the file was successfully written, false otherwise.
     */
    public static function write(string $filename, string $contents): bool 
    {
        $file = fopen($filename, 'w');

        if ($file === false) {
            return false;
        }

        $result = fwrite($file, $contents);
        fclose($file);

        if ($result !== false) {
            return true;
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
    private static function execute(string $destination, string $temp, int $chunk = 5242880): bool
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
    private static function beforeUpload(File $file, ?string $path = null): string|false
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

        $path = rtrim($path ?? $config->uploadPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        make_dir($path);

        $filename = basename($file->getName());
        $destination = $path . $filename;

        if(file_exists($destination)){
            $overwrite = (isset($config->ifExisted) ? $config->ifExisted : 'overwrite');
            if($overwrite === 'overwrite'){
                unlink($destination);
            }else{
                $destination = $path . uniqid() . '-' . $filename;
            }
        }

        return $destination;
    }
}