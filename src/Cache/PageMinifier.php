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

class PageMinifier 
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
	*  Gzip compression status
	* @var bool $gzip
	*/
    private bool $gzip; 

    /** 
     * Ignore html code block tag <code></code>
     * @var bool $minifyCodeTags
     */
    private bool $minifyCodeTags = false;

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
    public function __construct() 
    {
        $this->gzip = true;
    }
   
    /**
     * Enable or disable Gzip compression.
     *
     * @param bool $gzip Enable Gzip compression (true) or disable it (false).
     * @return self Returns the class instance for method chaining.
     */
    public function useGzip(bool $gzip): self 
    {
        $this->gzip = $gzip;

        return $this;
    }

    /**
     * sets compression level
     *
     * @param int $level Level
     * @return self $this
     */
	public function setCompressionLevel(int $level): self 
    {
		$this->compressionLevel = min(9, $level);

		return $this;
	}
    
    /**
     * sets ignore minifying code block
     *
     * @param bool $ignore
     * @return self Returns the class instance for method chaining.
     */
	public function minifyCodeblock(bool $ignore): self 
    {
		$this->minifyCodeTags = $ignore;

		return $this;
	}

     /**
     * sets allow copy code block
     *
     * @param bool $allow
     * 
     * @return self Returns the class instance for method chaining.
     */
	public function allowCopyCodeblock(bool $allow): self 
    {
		$this->enableCopy = $allow;

		return $this;
	}

    /**
     * Get compressed content
     * 
     * @return mixed compressed content $this->compressedContent
     */
    public function getCompressed(): mixed 
    {
		return $this->compressedContent;
    }

    /**
     * Get minified content
     * 
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
     * 
     * @return string $compressed The compressed content for output.
     */
    public function compress(string|array|object $data, string $contentType): string 
    {
        $content = (!is_string($data) ? static::toJsonString($data) : $data);

        $this->minifiedContent = $this->minifyCodeTags ? 
            static::minify($content) : 
                static::minifyIgnore($content, $this->enableCopy);
        $compressed = $this->minifiedContent;
        $headers = Header::getSystemHeaders();

        if ($this->gzip && function_exists('gzencode') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
            $compressed = gzencode($this->minifiedContent, $this->compressionLevel);

            if($compressed === false){
                $compressed = $this->minifiedContent;
            }else{
                $headers['Content-Encoding'] = 'gzip';
                $this->info['Content-Encoding'] = 'gzip';
            }
        }

        $this->info['Content-Length'] = strlen($compressed);
        $this->info['Content-Type'] = $contentType;

        $headers['Content-Length'] = $this->info['Content-Length'];
        $headers['Content-Type'] = $contentType;
  
        foreach ($headers as $header => $value) {
            header("$header: $value");
        }

        return $compressed;
    }

    /**
     * Get page header information
     * 
     * @return array
    */
    public function getHeaderInfo(): array
    {
        return $this->info;
    }

    /**
     * Convert content to json string
     * 
     * @param array|object $data
     * 
     * @return string
    */
    private static function toJsonString(array|object $data): string
    {
        if(is_object($data)){
            $data = (array) $data;
        }

        $encoded = json_encode($data);

        if ($encoded !== false) {
            return $encoded;
        }
        
        return '';
    }    

    /**
     * Sends the response with the specified content type and status code.
     *
     * @param string|array|object $body The content body to be sent in the response.
     * @param string $type The expected content type for the response.
     * 
    */
    private function respond(mixed $body, string $type): void 
    {
        // Echo the stored compressed content
        echo $this->compress($body, $type);
    
        // If there is any content in the output buffer, end and flush it
        if (ob_get_length() > 0) {
            ob_end_flush();
        }
    }
    
    /**
     * Send the output in HTML format.
     *
     * @param string $body The content body to be sent in the response.
     */
    public function html(string $body): void 
    {
        $this->respond($body, self::HTML);
    }

    /**
     * Send the output in text format.
     *
     * @param string $body The content body to be sent in the response.
     */
    public function text(string $body): void 
    {
        $this->respond($body, self::TEXT);
    }

    /**
     * Send the output in XML format.
     *
     * @param string $body The content body to be sent in the response.
     */
    public function xml(string $body): void 
    {
        $this->respond($body, self::XML);
    }

     /**
     * Send the output in JSON format.
     *
     * @param string|array|object $body The content body to be sent in the response.
     */
    public function json(string|array|object $body): void 
    {
        $this->respond($body, self::JSON);
    }

    /**
     * Send the output based on the specified content type.
     *
     * @param string|array|object $body The content body to be sent in the response.
     * @param string $type The expected content type for the response.
     */
    public function run(mixed $body, string $type = self::HTML): void 
    {
        $this->respond($body, $type);
    }

    /**
     * End output buffering and send the response.
     *
     * @param string $type The expected content type for the response.
     */
    public function end(string $type = self::HTML): void 
    {
        $this->respond(ob_get_contents(), $type);
    }

    /** 
     * Start output buffering and minify the content by removing unwanted tags and whitespace.
    */
    public function startMinify(): void 
    {
        ob_start(['self', $this->minifyCodeTags ? 'minifyIgnore' : 'minify']);
    }
    
    /**
     * Minify the given content by removing unwanted tags and whitespace.
     *
     * @param string $content The content to minify.
     * @return string minified content.
     */
    public static function minify(string $content): string 
    {
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
    public static function minifyIgnore(string $content, bool $allowCopy = false): string 
    {
        $ignores = [];
        //$pattern = '/<pre[^>]*><code[^>]*>[\s\S]*?<\/code><\/pre>/i';
        $pattern = '/<pre[^>]*>\s*<code[^>]*>[\s\S]*?<\/code>\s*<\/pre>/i';
        $ignorePatten = '###IGNORED_CODE_BLOCK###';

        $content = preg_replace_callback($pattern, function ($matches) use (&$ignores, $ignorePatten) {
            $ignores[] = $matches[0];
            
            return $ignorePatten;
        }, $content);
        
        $content = static::minify($content);

        // Restore the code blocks back to its original state
        $content = preg_replace_callback('/' . $ignorePatten . '/', function () use (&$ignores, $allowCopy) {
            $codeBlock =  array_shift($ignores);
            $copyButton = $allowCopy ? '<button type="button" class="copy-snippet">copy</button>' : '';
        
            $modifiedCodeBlock = preg_replace(
                '/<pre([^>]*)class="([^"]*)"([^>]*)>/i', 
                '<pre$1class="$2 pre-codeblock"$3>' . $copyButton,
                $codeBlock
            );

            return $modifiedCodeBlock;
        }, $content);

        return $content;
    }
}