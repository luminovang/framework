<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cache;

use Luminova\Http\Header;

class Compress 
{
    /**
	* holds json content type
	* @var string JSON
	*/
	public const JSON = "application/json;";
	 
	/**
	* holds text content type
	* @var string TEXT
	*/
	public const TEXT = "text/plain;";
	 
	/**
	* holds html content type
	* @var string HTML
	*/
	public const HTML = "text/html;";

    /**
	* holds xml content type
	* @var string XML
	*/
	public const XML = 'application/xml';

    /** 
	* Array to hold response headers
	* @var array $headers
	*/
    private array $headers;

    /** 
	*  Gzip compression status
	* @var bool $gzip
	*/
    private bool $gzip; 

    /** 
     * Ignore html code block tag <code></code>
     * @var bool $ignoreCodeblock
     */
    private bool $ignoreCodeblock = false;

    /** 
	*  Compressed content
	* @var mixed $compressedContent
	*/
    private mixed $compressedContent = '';

    /** 
	*  Minified content
	* @var mixed $minifiedContent
	*/
    private mixed $minifiedContent = '';

    /** 
	* Compression level  
	* @var int $compressionLevel
	*/
    private int $compressionLevel = 6;

    /** 
	* Allow copying of code blocks  
	* @var bool $enableCopy
	*/
    private bool $enableCopy = false;

    /** 
	* Info 
	* @var array $info
	*/
    private array $info = [];

    /**
     * Regular expression patterns for content stripping
     * @var array $patterns
    */
    private const PATTERNS = [
        "find" => [
            '/\>[^\S ]+/s',          // Strip whitespace after HTML tags
            '/[^\S ]+\</s',          // Strip whitespace before HTML tags
            '/\s+/', //'/(\s)+/s',   // Strip excessive whitespace
            '/<!--(.*)-->/Uis',      // Strip HTML comments
            '/[[:blank:]]+/'         // Strip blank spaces
        ],
        "replace" => [
            '>',
            '<',
            ' ',
            '',
            ' '
        ],
        "line" =>[
            "\n",
            "\r",
            "\t"
        ]
    ];
    
 
    /**
     * Class constructor.
     * Initializes default settings for the response headers and cache control.
     */
    public function __construct() {
        $this->headers = Header::getSystemHeaders();
        $this->gzip = true;
    }
   
    /**
     * Enable or disable Gzip compression.
     *
     * @param bool $gzip Enable Gzip compression (true) or disable it (false).
     * @return Compress Returns the class instance for method chaining.
     */
    public function useGzip(bool $gzip): self {
        $this->gzip = $gzip;
        return $this;
    }

    /**
     * Set the expiration offset for the Cache-Control header.
     *
     * @param int $offset Cache expiration offset in seconds.
     * @return Compress Returns the class instance for method chaining.
     */
    public function setExpires(int $offset): self {
        $this->headers['Expires'] = gmdate("D, d M Y H:i:s", time() + $offset) . ' GMT';
        return $this;
    }

    /**
     * Set the Cache-Control header.
     *
     * @param string $cacheControl Cache-Control header value.
     * @return Compress Returns the class instance for method chaining.
     */
    public function setCacheControl(string $cacheControl): self 
    {
        $this->headers['Cache-Control'] = $cacheControl;
        return $this;
    }

    /**
     * sets compression level
     *
     * @param int $level Level
     * @return Compress $this
     */
	public function setCompressionLevel(int $level): self 
    {
		$this->compressionLevel = min(9, $level);

		return $this;
	}
    
    /**
     * sets ignore user code block
     *
     * @param bool $ignore
     * @return Compress Returns the class instance for method chaining.
     */
	public function setIgnoreCodeblock(bool $ignore): self 
    {
		$this->ignoreCodeblock = $ignore;

		return $this;
	}

     /**
     * sets allow copy code block
     *
     * @param bool $allow
     * @return self Returns the class instance for method chaining.
     */
	public function allowCopyCodeblock(bool $allow): self 
    {
		$this->enableCopy = $allow;

		return $this;
	}

    /**
     * Get compressed content
     * @return mixed compressed content $this->compressedContent
     */
    public function getCompressed(): mixed 
    {
		return $this->compressedContent;
    }

    /**
     * Get minified content
     * @return string minified content $this->minifiedContent
     */
    public function getMinified(): string 
    {
		return $this->minifiedContent;
    }

    /**
     * Compresses the buffer content and adds necessary headers to optimize the response.
     *
     * @param string|array|object $data The content to compress (can be an array or object for JSON response).
     * @param string $contentType The expected content type for the response.
     * @return string The compressed content for output.
     */
    public function compress(mixed $data, string $contentType): string {
        $content = ($contentType === self::JSON) ? $this->toJsonEncodedString($data) : $data;
        $this->minifiedContent = $this->ignoreCodeblock ? self::minifyIgnoreCodeblock($content, $this->enableCopy) : self::minify($content);
        $compressedContent = '';

        $shouldCompress = false;
        if ($this->gzip && function_exists('gzencode') && !empty($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            $shouldCompress = true;
        }
    
        if ($shouldCompress) {
            $this->headers['Content-Encoding'] = 'gzip';
            // Compress the content and store it in a variable
            $compressedContent = gzencode($this->minifiedContent, $this->compressionLevel);
            //$compressedContent = gzencode($this->minifiedContent, $this->compressionLevel, 9, FORCE_GZIP);
            $this->info['Content-Encoding'] = 'gzip';
        } else {
            // Store the uncompressed content in a property
            $compressedContent = $this->minifiedContent;
        }
        $contentLength = strlen($compressedContent);

        $this->info['Content-Length'] = $contentLength;
        $this->info['Content-Type'] = $contentType;

        $this->headers['Content-Length'] = $contentLength;
        $this->headers['Content-Type'] = $contentType;
        // ?? $this->headers['Content-Type']; 
        //Header::getContentType($contentType);
        foreach ($this->headers as $header => $value) {
            header("$header: $value");
        }
        return $compressedContent;
    }

    /**
     * Get page header information
     * 
     * @return array
    */
    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * Convert content to json string
     * @param mixed $data
     * 
     * @return string
    */
    private function toJsonEncodedString(mixed $data): string
    {
        $decodedData = json_decode($data);
    
        if ($decodedData !== null || json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            $encodedData = json_encode($data);
    
            if ($encodedData !== false) {
                return $encodedData;
            }
        }
        
        return '';
    }    

    /**
     * Sends the response with the specified content type and status code.
     *
     * @param string|array|object $body The content body to be sent in the response.
     * @param int $statusCode The HTTP status code to be sent in the response.
     * @param string $contentType The expected content type for the response.
    */
    private function withViewContent(mixed $body, int $statusCode, string $contentType): void {

        // Compress the content and store it in a variable
        //$this->compressedContent = $this->compress($body, $contentType);
    
        if ($statusCode) {
            http_response_code($statusCode);
        }
        // ob_end_clean();
        // Echo the stored compressed content
        echo $this->compress($body, $contentType);
    
        // If there is any content in the output buffer, end and flush it
        if (ob_get_length() > 0) {
            ob_end_flush();
        }
    }
    
    /**
     * Send the output in HTML format.
     *
     * @param string|array|object $body The content body to be sent in the response.
     */
    public function html(mixed $body): void 
    {
        $this->withViewContent($body, 200, self::HTML);
    }

    /**
     * Send the output in text format.
     *
     * @param string|array|object $body The content body to be sent in the response.
     */
    public function text(mixed $body): void 
    {
        $this->withViewContent($body, 200, self::TEXT);
    }

    /**
     * Send the output in XML format.
     *
     * @param string|array|object $body The content body to be sent in the response.
     */
    public function xml(mixed $body): void 
    {
        $this->withViewContent($body, 200, self::XML);
    }

     /**
     * Send the output in JSON format.
     *
     * @param string|array|object $body The content body to be sent in the response.
     */
    public function json(mixed $body): void 
    {
        $this->withViewContent($body, 200, self::JSON);
    }

    /**
     * Send the output based on the specified content type.
     *
     * @param string|array|object $body The content body to be sent in the response.
     * @param string $contentType The expected content type for the response.
     */
    public function run(mixed $body, string $contentType = self::HTML): void 
    {
        $this->withViewContent($body, 200, $contentType);
    }

    /**
     * End output buffering and send the response.
     *
     * @param string $contentType The expected content type for the response.
     */
    public function end(string $contentType = self::HTML): void 
    {
        $this->withViewContent(ob_get_contents(), 200, $contentType);
    }

    /**
     * Start output buffering and minify the content by removing unwanted tags and whitespace.
    */
    public function startMinify(): void 
    {
        if($this->ignoreCodeblock){
            ob_start(['self', 'minifyIgnoreCodeblock']);
        }else{
            ob_start(['self', 'minify']);
        }
    }
    
    /**
     * Minify the given content by removing unwanted tags and whitespace.
     *
     * @param string $content The content to minify.
     * @return string minified content.
     */
    public static function minify(string $content): string {
        //$patterns = self::PATTERNS;
        //$patterns["find"][] = '/\s+/'; 
        //$patterns["replace"][] = ' ';

        $content = str_replace(self::PATTERNS["line"], '', $content);
        $content = preg_replace(self::PATTERNS["find"], self::PATTERNS["replace"], $content);
        return trim($content);
    }

    /**
     * Minify the given content by removing unwanted tags and whitespace.
     * Ignore html <code></code> block
     * @param string $content The content to minify.
     * @return string minified content.
    */
    public static function minifyIgnoreCodeblock(string $content, bool $allowCopy = false): string 
    {
        $ignores = [];
        $pattern = '/<pre><code[^>]*>[\s\S]*?<\/code><\/pre>/i';
        $ignorePatten = '###IGNORED_CODE_BLOCK###';
        $content = preg_replace_callback($pattern, function ($matches) use (&$ignores, $ignorePatten) {
            $ignores[] = $matches[0];
            return $ignorePatten;
        }, $content);


        $content = self::minify($content);

        // Restore the code blocks back to its original state
        $content = preg_replace_callback('/' . $ignorePatten . '/', function () use (&$ignores, $allowCopy) {
            $copy = '';
            if($allowCopy){
                $copy = '<button type="button" class="copy-snippet">copy</button>';
            }
            $codeBlock =  array_shift($ignores);
            return str_replace('<pre>', '<pre class="pre-codeblock">' . $copy , $codeBlock);
        }, $content);

        return $content;
    }
}