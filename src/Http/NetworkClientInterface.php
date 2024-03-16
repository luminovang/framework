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

use \Luminova\Http\NetworkResponse;

interface NetworkClientInterface 
{
    /**
     * Send an HTTP request.
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     *
     * @return NetworkResponse
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): NetworkResponse;
} 