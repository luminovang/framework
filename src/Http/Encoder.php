<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Http;

final class Encoder
{
    /**
     * Encode the content using specified compression algorithm if supported by the client.
     *
     * @param string $content The content to encode.
     *
     * @return array<int,mixed> An array containing the encoding type and the compressed content.
     *               The encoding type will be 'gzip' or 'deflate' if compression is successful,
     *               otherwise, it will be false. The compressed content is the encoded content
     *               or the original content if compression fails.
    */
    public static function encode(string $content): array
    {
        $encoding = env('compression.encoding', false);
        if ($encoding !== false ) {
    
            if (isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
                return [$_SERVER['HTTP_CONTENT_ENCODING'], $content];
            }
        
            if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
                $encoding = strtolower($encoding);
                $compression = min(9, (int) env('compression.level', 6));
                $handler = null;

                switch ($encoding) {
                    case 'gzip':
                    case 'x-gzip':
                        $handler = 'gzencode';
                    break;
                    case 'deflate':
                        $handler = 'gzdeflate';
                    break;
                    case 'compress':
                        $handler = 'gzcompress';
                    break;
                }

                if ($handler !== null && function_exists($handler) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], $encoding) !== false) {
                
                    $compressed = $handler($content, $compression);
                    if ($compressed !== false) {
                        return [$encoding, $compressed];
                    }
                }
            }
        }

        return [false, $content];
    }

    /**
     * Decode compressed data using the specified encoding method.
     *
     * @param string $content    The compressed data to decode.
     * @param int    $max_length Optional. The maximum length of the decoded data. Default is 0 (no limit).
     *
     * @return arrayarray<int, mixed> An array containing the encoding method and the decoded data, or false if decoding fails.
     */
    public static function decode(string $content, int $max_length = 0): array
    {
        $encoding = env('compression.encoding', false);

        if ($encoding !== false) {
            $encoding = strtolower($encoding);
            $handler = null;

            switch ($encoding) {
                case 'compress':
                    $handler = 'gzuncompress';
                    break;
                case 'gzip':
                case 'x-gzip':
                    $handler = 'gzdecode';
                break;
                case 'deflate':
                    $handler = 'gzinflate';
                break;
            }

            if ($handler !== null && function_exists($handler)) {
                $uncompressed = $handler($content, $max_length);
                if ($uncompressed !== false) {
                    return [$encoding, $uncompressed];
                }
            }
        }

        return [false, $content];
    }
}