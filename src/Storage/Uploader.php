<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storage;

final class Uploader
{
    /**
     * Handles non-chunked file upload.
     *
     * @param string $path The directory path where the file will be stored.
     * @param array $file An associative array containing information about the uploaded file.
     * 
     * @return object<string, mixed> An object containing the upload status.
     */
    public static function put(string $path, array $file): object
    {
        if ($file['error'] > 0 || empty($file['tmp_name'])) {
            return (object) [
                'status' => false,
                'type' => 'invalid'
            ];
        }

        make_dir($path);

        $filename = basename($file['name']);
        $destination = rtrim($path, '/') . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            unlink($file['tmp_name']);
            return (object) [
                'status' => true,
                'type' => 'uploaded',
                'data' => [
                    'filename' => $filename
                ]
            ];
        } else {
            return (object) [
                'status' => false,
                'type' => 'failed'
            ];
        }
    }

    /**
     * Handles chunked file upload.
     *
     * @param string $path The directory path where the file will be stored.
     * @param array $file An associative array containing information about the uploaded file.
     * @param int $chunk The current chunk index (default is 0).
     * @param int $chunks The total number of chunks (default is 0).
     * @param int $length The length of each chunk in bytes (default is 4096).
     * 
     * @return object<string, mixed> An object containing the upload status.
     */
    public static function chunk(string $path, array $file, int $chunk = 0, int $chunks = 0, int $length = 4096): object
    {
        $response = [];

        if ($file['error'] > 0 || empty($file['tmp_name'])) {
            return (object) [
                'status' => false,
                'type' => 'invalid'
            ];
        }

        make_dir($path);

        $filename = basename($file['name']);
        $destination = rtrim($path, '/') . '/' . $filename;
        $tempName = $destination . ".part";

        $handler = fopen($tempName, $chunk === 0 ? 'wb' : 'ab');
        if (!$handler) {
            return (object) [
                'status' => false,
                'type' => 'failed'
            ];
        }

        $handle = fopen($file['tmp_name'], "rb");
        if ($handle) {
            while ($buffer = fread($handle, $length)) {
                fwrite($handler, $buffer);
            }
            /*while (!feof($handle)) {
                $buffer = fread($handle, $length);
                fwrite($handler, $buffer);
            }*/
            fclose($handle);
        } else {
            fclose($handler);
            unlink($file['tmp_name']);
            return (object) [
                'status' => false,
                'type' => 'failed'
            ];
        }

        fclose($handler);
        unlink($file['tmp_name']);

        if (!$chunks || $chunk === $chunks - 1) {
            if (rename($tempName, $destination)) {
                $response = [
                    'status' => true,
                    'type' => 'uploaded',
                    'data' => [
                        'filename' => $filename
                    ]
                ];
            } else {
                $response = [
                    'status' => false,
                    'type' => 'failed'
                ];
            }
        } else {
            $response = [
                'status' => 202,
                'type' => 'accepted',
                'data' => [
                    'chunk' => $chunk,
                    'chunks' => $chunks
                ]
            ];
        }

        return (object) $response;
    }
}