<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Http\Helper;

use function \Luminova\Funcs\string_length;

final class Encoder
{
    /**
     * The default env configuration encoding.
     * 
     * @var mixed $encoding
    */
    private static mixed $encoding = null;

    /**
     * Encode the content using the specified compression algorithm if supported by the client.
     * Or automatically determine the best encoding method and encode the content accordingly.
     *
     * @param string $content The content to encode.
     * @param string|null $encoding Optional. The desired encoding type ('gzip', 'deflate', etc.). If null, the method
     *                              will automatically choose the client's accepted encoding.
     *
     * @return array<int,mixed> Return an array containing the encoding type and the encoded content.
     *                          - The first element will be the encoding type (e.g., 'gzip', 'deflate').
     *                          - If encoding is unsuccessful, the first element will be false.
     *                          - The second element is the encoded content or the original content if encoding failed.
     *                          - The third element is the encoded content length.
     */
    public static function encode(string $content, ?string $encoding = null): array
    {
        $encoding ??= self::getEncoding();
        if ($encoding) {
            $handler = self::getHandler($encoding);

            if ($handler !== null && function_exists($handler)) {
                $compression = min(9, (int) env('compression.level', 6));
                $compressed = $handler($content, $compression);
                
                if ($compressed !== false) {
                    return [$encoding, $compressed, strlen($compressed)];
                }
            }
        }

        return [false, $content, string_length($content)];
    }

    /**
     * Decode compressed data using the specified encoding method.
     *
     * @param string $content The compressed data to decode.
     * @param int $max_length Optional. The maximum length of the decoded data. Default is 0 (no limit).
     *
     * @return array<int,mixed> Return an array containing the encoding method and the decoded data.
     *                          If decoding fails, the first element will be false, and the second will be
     *                          the original content.
     */
    public static function decode(string $content, int $max_length = 0): array
    {
        $encoding = self::getEncoding();

        if ($encoding) {
            $handler = self::getHandler($encoding, false);

            if ($handler !== null && function_exists($handler)) {
                $uncompressed = $handler($content, $max_length);
                if ($uncompressed !== false) {
                    return [$encoding, $uncompressed];
                }
            }
        }

        return [false, $content];
    }

    /**
     * Determine the content encoding based on the request headers and env configuration.
     *
     * This method checks the following, in order:
     * 1. The `HTTP_CONTENT_ENCODING` server variable to see if the request body is already encoded.
     * 2. A stored encoding in the class (`self::$encoding`).
     * 3. The configured compression encoding from the environment settings, 
     *    and checks if it is accepted by the client using the `HTTP_ACCEPT_ENCODING` header.
     *
     * @return string|false Returns the content encoding handler if found; otherwise, returns false.
     */
    public static function getEncoding(): string|bool
    {
        if(!env('enable.encoding', true)){
            return false;
        }

        if(self::$encoding){
            return self::$encoding;
        }

        if (isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
            return self::$encoding = strtolower($_SERVER['HTTP_CONTENT_ENCODING']);
        }

        $encoding = env('compression.encoding', false);
        $accepts = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? null;

        if ($encoding && $accepts) {
            $encoding = strtolower($encoding);
            if (str_contains($accepts, $encoding)) {
                return self::$encoding = $encoding;
            }
        }
        
        return false;
    }

    /**
     * Get the appropriate handler function for encoding or decoding content.
     *
     * @param string $accept The encoding/decoding type (e.g., 'gzip', 'deflate', 'compress').
     * @param bool $encode Optional. Determines whether to return an encoding or decoding function (default: true `encode`).
     *
     * @return string|null Returns the name of the appropriate PHP function for the encoding/decoding method, or null if none is found.
     */
    public static function getHandler(string $accept, bool $encode = true): ?string 
    {
        return match($accept){
            'gzip', 'x-gzip' => ($encode ? 'gzencode' : 'gzdecode'),
            'deflate' => ($encode ? 'gzdeflate' : 'gzdecode'),
            'compress' => ($encode ? 'gzcompress' : 'gzuncompress'),
            default => null
        };
    }
}