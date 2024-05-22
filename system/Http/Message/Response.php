<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Http\Message;

class Response
{
    /**
     * @var int Status code of the response.
     */
    private int $statusCode = 0;

    /**
     * @var array Response headers.
     */
    private array $headers = [];

    /**
     * @var mixed Response body.
     */
    private mixed $body = null;

    /**
     * @var mixed Response contents.
     */
    private mixed $bodyContents = null;

    /**
     * @var array Response info.
     */
    private array $info = [];

    /**
     * Initializes a new Response instance.
     *
     * @param int $statusCode The HTTP status code.
     * @param array $headers The response headers.
     * @param mixed $body The response body.
     * @param mixed $contents The response contents.
     * @param array $info The response info.
     * @ignore
     */
    public function __construct(int $statusCode, array $headers, mixed $body, mixed $contents, array $info = [])
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->bodyContents = $contents;
        $this->info = $info;
    }

    /**
     * Get the HTTP status code of the response.
     *
     * @return int The status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response headers.
     *
     * @return array The response headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get the response body.
     *
     * @return mixed The response body.
     */
    public function getBody(): mixed
    {
        return $this->body;
    }
    
    /**
     * Get the response contents.
     *
     * @return mixed The response contents.
     */
    public function getContents(): mixed
    {
        return $this->bodyContents;
    }

    /**
     * Get the response info.
     *
     * @return array The response info.
     */
    public function getInfo(): array
    {
        return $this->info;
    }
}