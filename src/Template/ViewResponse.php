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
use \Luminova\Cache\PageMinifier;
use \Luminova\Http\Header;
use \Luminova\Exceptions\JsonException;
use \Exception;

class ViewResponse 
{
    /**
     * @var int $status
    */
    private int $status = 200; 

    /**
     * @var bool $encode
    */
    private bool $encode = true;

    /**
     * @var bool $minify
    */
    private bool $minify = false;

    /**
     * @var PageMinifier|null $minifier
    */
    private static ?PageMinifier $minifier = null;

    /**
     * Response constructor.
     *
     * @param int $status HTTP status code (default: 200 OK)
     */
    public function __construct(int $status = 200)
    {
        $this->status = $status;
        $this->encode = (bool) env('enable.encoding', true);
        $this->minify = (bool) env('page.minification', false);
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
        $this->status = $status;

        return $this;
    }

    /**
     * Set enable content encoding
     *
     * @param bool $encode Enable content encoding like gzip.
     * 
     * @return self $this Return class instance.
     */
    public function encode(bool $encode): self 
    {
        $this->encode = $encode;

        return $this;
    }

    /**
     * Set enable content minification
     *
     * @param bool $minify Enable content minification.
     * 
     * @return self $this Return class instance.
     */
    public function minify(bool $minify): self 
    {
        $this->minify = $minify;

        return $this;
    }

    /**
     * Render any content format anywhere.
     *
     * @param mixed $content Response content
     * @param int $status Content type of the response
     * @param array $header Additional headers.
     * @param bool $encode Enable content encoding like gzip.
     * @param bool $minify Enable content minification and compress.
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
    */
    public static function render(
        string $content, 
        int $status = 200, 
        array $headers = [],
        bool $encode = true, 
        bool $minify = false
    ): int
    {
        if(empty($content)){
            return STATUS_ERROR;
        }

        if(!isset($headers['Content-Type'])){
            $headers['Content-Type'] = 'application/json';
        }

        $length = false;
        $encoding = false;

        if($encode || $minify){
            if(static::$minifier === null){
                static::$minifier = new PageMinifier();
                static::$minifier->codeblocks(false);
                static::$minifier->copiable(false);
            }

            $instance = static::$minifier->compress($content, $headers['Content-Type'], $minify, $encode);
            $content = $instance->getContent();
            $encoding = $instance->getEncoding();
            $length = $instance->getLength();
        }

        if(empty($content)){
            return STATUS_ERROR;
        }

        $headers['default_headers'] = true;
        $headers['Content-Encoding'] = $encoding;
        $headers['Content-Length'] = ($length === false ? string_length($content) : $length);

        Header::parseHeaders($headers, $status);
        echo $content;

        return STATUS_SUCCESS;
    }

    /**
     * Send a JSON response.
     *
     * @param array|object $content Data to be encoded as JSON
     * 
     * @return int Response status code STATUS_SUCCESS or STATUS_ERROR.
     * @throws JsonException Throws if json error occurs.
     */
    public function json(array|object $content): int 
    {
        if (is_object($content)) {
            $content = (array) $content;
        }
        try {
            $body = json_encode($content, JSON_THROW_ON_ERROR);

            return static::render($body, $this->status, [], $this->encode, $this->minify);
        }catch(Exception $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
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
        return static::render($content, $this->status, [
            'Content-Type' => 'text/plain'
        ], $this->encode, $this->minify);
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
        return static::render($content, $this->status, [
            'Content-Type' => 'text/html'
        ], $this->encode, $this->minify);
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
        return static::render($content, $this->status, [
            'Content-Type' => 'application/xml'
        ], $this->encode, $this->minify);
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