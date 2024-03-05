<?php 
namespace Luminova\Http;

class NetworkResponse
{
    /**
     * @var int $statusCode
    */
    private int $statusCode = 0;

    /**
     * @var array $headers
    */
    private array $headers = [];

    /**
     * @var mixed $body
    */
    private mixed $body = null;

    /**
     * @var mixed $bodyContents
    */
    private mixed $bodyContents = null;

    /**
     * Initializes network response class instance
     * 
     * @param int $statusCode status code 
     * @param array $headers response headers 
     * @param mixed $body response body
     * @param mixed $contents response contents
     * 
    */
    public function __construct(int $statusCode, array $headers, mixed $body, mixed $contents)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->bodyContents = $contents;
    }

    /**
     * Get request response https status code
     * 
     * @return int status code
    */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get request response headers
     * 
     * @return array response headers
    */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get request response body
     * 
     * @return mixed response body
    */
    public function getBody(): mixed
    {
        return $this->body;
    }
    
    /**
     * Get request response contents
     * 
     * @return mixed response contents
    */
    public function getContents(): mixed
    {
        return $this->bodyContents;
    }

    /**
     * Get request response infos
     * 
     * @return array response info
    */
    public function getInfos(): array
    {
        return [];
    }
}