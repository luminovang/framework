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

use \Luminova\Http\Header;
use \Luminova\Http\Encoder;
use \JsonException;

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
     * Ignore html code block tag <code></code>
     * @var bool $codeblocks
     */
    private bool $codeblocks = false;

    /** 
	*  Minified content
	* @var mixed $contents
	*/
    private mixed $contents = '';

    /** 
	* Allow copying of code blocks  
	* @var bool $copiable
	*/
    private bool $copiable = false;

    /** 
	* Minified content headers.
    *
	* @var array $headers
	*/
    private array $headers = [];

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
    public function __construct() {}
    
    /**
     * sets ignore minifying code block
     *
     * @param bool $ignore
     *  @return self Returns minifier class instance.
     */
	public function codeblocks(bool $ignore): self 
    {
		$this->codeblocks = $ignore;

		return $this;
	}

     /**
     * sets allow copy code block
     *
     * @param bool $allow
     * 
     *  @return self Returns minifier class instance.
     */
	public function copiable(bool $allow): self 
    {
		$this->copiable = $allow;

		return $this;
	}

    /**
     * Get minified content
     * 
     * @return string Return minified contents
     */
    public function getContent(): string 
    {
		return $this->contents;
    }

    /**
     * Get content encoding
     * 
     * @return string|null Return minified content encoding.
     */
    public function getEncoding(): ?string 
    {
		return $this->headers['Content-Encoding']??false;
    }

     /**
     * Get content encoding
     * 
     * @return string|null Return minified content encoding.
     */
    public function getLength(): int 
    {
		return $this->headers['Content-Length']??0;
    }

    /**
     * Get page header information
     * 
     * @return array Get minified content headers.
     */
    public function getHeaders(): array 
    {
		return $this->headers;
    }

    /**
     * Compresses the buffer content and adds necessary headers to optimize the response.
     *
     * @param string|array|object $data The content to compress (can be an array or object for JSON response).
     * @param string $contentType The expected content type for the response.
     * @param bool $minify Minify contents.
     * @param bool $encode Compress and encode contents.
     * 
     * @return self Returns minifier class instance.
     */
    public function compress(
        string|array|object $data,
        string $contentType,
        bool $minify = true,
        bool $encode = true
    ): self {
        // Resolve content type if it's a shorthand
        if (strpos($contentType, '/') === false) {
            $contentType = Header::getContentTypes($contentType);
        }

        // Convert data to string if not already
        $content = is_string($data) ? $data : static::toJsonString($data);

        // Minify content if required
        $content = $minify ? ($this->codeblocks ? static::minify($content) : static::minifyIgnore($content, $this->copiable)) : $content;

        // Encode content if required
        [$encoding, $content] = $encode ? Encoder::encode($content) : [false, $content];

        // Set response headers
        $this->headers['Content-Length'] = string_length($content);
        $this->headers['Content-Type'] = $contentType;
        $this->headers['Content-Encoding'] = $encoding;
        $this->contents = $content;

        return $this;
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

        try{
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);

            if ($encoded !== false) {
                return $encoded;
            }
        } catch (JsonException $e) {
            return '';
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
        $this->compress($body, $type);
        $this->headers['default_headers'] = true;
        Header::parseHeaders($this->headers);

        echo $this->contents;

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
        ob_start(['self', $this->codeblocks ? 'minifyIgnore' : 'minify']);
    }
    
    /**
     * Minify the given content by removing unwanted tags and whitespace.
     *
     * @param string $content The content to minify.
     * 
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
     * 
     * @param string $content The content to minify.
     * @param bool $allowCopy Allow copying codeblock (default: false).
     * 
     * @return string Return minified content.
    */
    public static function minifyIgnore(string $content, bool $allowCopy = false): string 
    {
        $ignores = [];
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