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

class NetworkRequest
{
    /**
     * Method 
     * @var string $method
    */
    private string $method = '';

    /**
     * Url
     * @var string $url
    */
    private string $url = '';

    /**
     * Initialize 
     * 
     * @param string $method
     * @param string $url
    */
    public function __construct(string $method, string $url)
    {
        $this->method = $method;
        $this->url = $url;
    }

    /**
     * Get method  
     * @return string 
    */
    public function getMethod(): string 
    {
        return $this->method;
    }

    /**
     * Get url 
     * @return string 
    */
    public function getUrl(): string 
    {
        return $this->url;
    }
}