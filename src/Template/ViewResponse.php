<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
*/
namespace Luminova\Template;

use \Luminova\Functions\Files;

class ViewResponse 
{
    /**
     * @var int $statusCode
    */
    private int $statusCode = 200; 

    /**
     * Response constructor.
     *
     * @param int $status HTTP status code (default: 200 OK)
     */
    public function __construct(int $status = 200)
    {
        $this->statusCode = $status;
    }

    /**
     * Set status code
     *
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @return self $this
     */
    public function setStatus(int $status = 200): self 
    {
        $this->statusCode = $status;

        return $this;
    }

    /**
     * Render the response.
     *
     * @param mixed $content Response content
     * @param string $contentType Content type of the response
     */
    public function render(string $content, string $contentType): void
    {
        http_response_code($this->statusCode);

        header("Content-Type: $contentType");

        echo $content;
    }

    /**
     * Send a JSON response.
     *
     * @param array|object $content Data to be encoded as JSON
     */
    public function json(array|object $content): void 
    {
        if (is_object($content)) {
            $content = (array) $content;
        }

        $body = json_encode($content);

        $this->render($body, 'application/json');
    }

    /**
     * Send a plain text response.
     *
     * @param string $content Text content
     */
    public function text(string $content): void 
    {
        $this->render($content, 'text/plain');
    }

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content
     */
    public function html(string $content): void 
    {
        $this->render($content, 'text/html');
    }

    /**
     * Send an XML response.
     *
     * @param string $content XML content
     */
    public function xml(string $content): void 
    {
        $this->render($content, 'application/xml');
    }

    /**
     * Write content to a file and send as a download response.
     *
     * @param mixed $content Content to be written to the file
     * @param string $filename Name of the file to be downloaded
     * 
     * @return bool True if the content was saved successful, false otherwise
     */
    public function write(mixed $content, string $filename): bool 
    {
       return write_content($filename, $content);
    }

    /**
     * Download a file as a response.
     *
     * @param string $path Path to the file to be downloaded
     * @param string|null $name (Optional) Name to be used for the downloaded file
     * @return bool True if the download was successful, false otherwise
     */
    public function download(string $path, ?string $name = null): bool 
    {
        return Files::download($path, $name);
    }

    /** 
    * redirect to url
    *
    * @param string $url url location
    * @param int $response_code response status code
    *
    * @return void
    */
    public function redirect(string $url = '/', int $response_code = 0): void 
    {
        header("Location: $url", true, $response_code);

        exit();
    }
}