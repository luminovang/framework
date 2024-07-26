<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Http\Client;

use \Luminova\Http\Message\Response;
use \Luminova\Interface\HttpClientInterface;
use \Luminova\Exceptions\Http\RequestException;
use \Luminova\Exceptions\Http\ConnectException;
use \Luminova\Exceptions\Http\ClientException;
use \Luminova\Exceptions\Http\ServerException;
use \Throwable;
use \JsonException;

class Curl implements HttpClientInterface
{
    private array $config = [];
    /**
     * {@inheritdoc}
     * 
    */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    /**
      * {@inheritdoc}
    */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
            throw new ClientException('Invalid request method. Supported methods: GET, POST, HEAD.');
        }

        $ch = curl_init();
        if( $ch === false ){
            throw new ClientException('Failed to initialize cURL');
        }

        if(isset($this->config['headers'])){
            $headers = array_merge($this->config['headers'], $headers);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        //curl_setopt($ch, CURLOPT_ENCODING, '');

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            try{
                if ($data !== []) {
                    $data = json_encode($data, JSON_THROW_ON_ERROR);
                    if(!isset($headers['Content-Type'])){
                        $headers['Content-Type'] = 'application/json';
                    }
                }
            }catch(JsonException|Throwable $e){
                throw new ClientException($e->getMessage(), $e->getCode(), $e);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }elseif ($method !== 'GET' || $method !== 'HEAD') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, self::toRequestHeaders($headers));
        }

        $response = curl_exec($ch);
        if (($error = curl_error($ch)) !== 0 || $response === false || $response === '') {
            $errorCode = curl_errno($ch);
            curl_close($ch);

            switch ($errorCode) {
                case CURLE_COULDNT_CONNECT:
                    throw new ConnectException($error, $errorCode);
                case CURLE_HTTP_NOT_FOUND:
                case CURLE_URL_MALFORMAT:
                case CURLE_UNSUPPORTED_PROTOCOL:
                    throw new RequestException($error, $errorCode);
                case CURLE_OPERATION_TIMEOUTED:
                    throw new RequestException('Request timed out', $errorCode);
                case CURLE_SSL_CONNECT_ERROR:
                    throw new ConnectException('SSL connection error', $errorCode);
                case CURLE_GOT_NOTHING:
                    throw new ClientException($error, $errorCode);
                case CURLE_WEIRD_SERVER_REPLY:
                case CURLE_TOO_MANY_REDIRECTS:
                    throw new ServerException($error, $errorCode);
                default:
                    throw new RequestException(sprintf("Request to %s ended in %d", $error, $errorCode));
            }
        }

        $info = curl_getinfo($ch);
        $statusCode = (int) ($info['http_code'] ?? 0);
        $headerSize = $info['header_size'] ?? 0;
        $responseHeaders = substr($response, 0, strpos($response, "\r\n\r\n"));
        $contents = substr($response, $headerSize);
        $responseHeaders = self::headerToArray($responseHeaders, $statusCode);
        curl_close($ch);

        return new Response($statusCode, $responseHeaders, $response, $contents, $info);
    }
 
    /**
      * Convert an array of headers to cURL format.
      *
      * @param array $headers
      *
      * @return array<int,string> Return request headers as array.
    */
    private static function toRequestHeaders(array $headers): array
    {
        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = "{$key}: {$value}";
        }
        return $headerLines;
    }
 
    /**
      * Convert a raw header string to an associative array.
      *
      * @param string $header Header string.
      * @param int $code Status code
      *
      * @return array<string,mixed> Return array of headers.
    */
    private static function headerToArray(string $header, int $code): array
    {
        $headers = ['statusCode' => $code];
        foreach (explode("\r\n", $header) as $i => $line) {
            if ($i === 0) {
                $headers['http_code'] = $line;
            } else {
                [$key, $value] = explode(': ', $line);
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
} 