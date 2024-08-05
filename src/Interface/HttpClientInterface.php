<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Interface;

use \Luminova\Http\Message\Response;

interface HttpClientInterface 
{
    /**
     * Curl client constructor.
     * 
     * @param array $config client configuration.
     * 
    */
    public function __construct(array $config = []);
    
    /**
     * Send an HTTP request to a url.
     *
     * @param string $method The request method.
     * @param string $url The URL to send the request.
     * @param array<string,mixed> $data The data to send.
     * @param array<string,mixed> $headers The headers to send with the request.
     *
     * @return Response Return request response object.
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response;
} 