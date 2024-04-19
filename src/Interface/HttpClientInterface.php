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
     * @param array $config client configuration
     * 
    */
    public function __construct(array $config = []);
    
    /**
     * Send an HTTP request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return Response
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): Response;
} 