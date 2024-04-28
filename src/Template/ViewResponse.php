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

use \Luminova\Application\FileSystem;
use \Luminova\Http\Header;
use \Luminova\Http\Encoder;

class ViewResponse 
{
    /**
     * @var int $statusCode
    */
    private static int $statusCode = 200; 

    /**
     * @var bool $enableEncoding
    */
    private static bool $enableEncoding = true;

    /**
     * Response constructor.
     *
     * @param int $status HTTP status code (default: 200 OK)
     * @param bool $encode Enable content encoding like gzip.
     */
    public function __construct(int $status = 200, bool $encode = true)
    {
        static::$statusCode = $status;
        static::$enableEncoding = $encode;
    }

    /**
     * Set status code
     *
     * @param int $status HTTP status code (default: 200 OK)
     * 
     * @return self $this Return class instance.
     */
    public function setStatus(int $status = 200): self 
    {
        static::$statusCode = $status;

        return $this;
    }

    /**
     * Set status code
     *
     * @param bool $encode Enable content encoding like gzip.
     * 
     * @return self $this Return class instance.
     */
    public function encode(bool $encode): self 
    {
        static::$enableEncoding = $encode;

        return $this;
    }

    /**
     * Render the response.
     *
     * @param mixed $content Response content
     * @param string $contentType Content type of the response
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
    */
    public static function render(string $content, string $contentType = 'application/json'): int
    {
        if(empty($content)){
            return STATUS_ERROR;
        }

        [$encoding, $content] = (static::$enableEncoding ? Encoder::encode($content) : [false, $content]);

        if(empty($content)){
            return STATUS_ERROR;
        }

        $headers = Header::getSystemHeaders();
        $headers['Content-Type'] = $contentType;
        $headers['Content-Length'] = (is_utf8($content) ? mb_strlen($content, 'utf8') : strlen($content));

        if ($encoding !== false) {
            $headers['Content-Encoding'] = $encoding;
        }

        Header::parseHeaders($headers, static::$statusCode);
        echo $content;

        return STATUS_SUCCESS;
    }

    /**
     * Send a JSON response.
     *
     * @param array|object $content Data to be encoded as JSON
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
     */
    public function json(array|object $content): int 
    {
        if (is_object($content)) {
            $content = (array) $content;
        }

        $body = json_encode($content);

        return static::render($body, 'application/json');
    }

    /**
     * Send a plain text response.
     *
     * @param string $content Text content
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
     */
    public function text(string $content): int 
    {
        return static::render($content, 'text/plain');
    }

    /**
     * Send an HTML response.
     *
     * @param string $content HTML content.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
     */
    public function html(string $content): int 
    {
        return static::render($content, 'text/html');
    }

    /**
     * Send an XML response.
     *
     * @param string $content XML content.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
     */
    public function xml(string $content): int 
    {
        return static::render($content, 'application/xml');
    }

    /**
     * Download a file
     *
     * @param string $fileOrContent Path to the file or content to be downloaded
     * @param string|null $name Optional Name to be used for the downloaded file
     * @param array $headers Optional download headers.
     * 
     * @return bool True if the download was successful, false otherwise
     */
    public function download(string $fileOrContent, ?string $name = null, array $headers = []): bool 
    {
        return FileSystem::download($fileOrContent, $name, $headers);
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

        exit(0);
    }
}