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
use \JsonException;

final class PageMinifier 
{
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
    private static array $patterns = [
        "find" => [
            '/\>[^\S ]+/s',          // Strip whitespace after HTML tags
            '/[^\S ]+\</s',          // Strip whitespace before HTML tags
            '/\s+/',                 // Strip excessive whitespace
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
        
    }
    
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
     * @return string|null|false Return minified content encoding.
     */
    public function getEncoding(): string|null|false
    {
		return $this->headers['Content-Encoding']??false;
    }

     /**
     * Get content encoding
     * 
     * @return int Return content length.
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
     * 
     * @return self Returns minifier class instance.
     */
    public function compress(
        string|array|object $data,
        string $contentType
    ): self {
       
        // Convert data to string if not already
        $content = is_string($data) ? $data : static::toJsonString($data);

        // Minify content if required
        $content = ($this->codeblocks ? static::minify($content) : static::minifyIgnore($content, $this->copiable));

        // Resolve content type if it's a shorthand
        if (strpos($contentType, '/') === false) {
            $contentType = Header::getContentTypes($contentType);
        }

        // Set response headers
        $this->headers['Content-Length'] = string_length($content);
        $this->headers['Content-Type'] = $contentType;
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
        } catch (JsonException) {
            return '';
        }
        
        return '';
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
        $content = preg_replace(
            static::$patterns["find"], 
            static::$patterns["replace"], 
            str_replace(static::$patterns["line"], '', $content)
        );

        return trim($content);
    }

    /**
     * Minify the given content by removing unwanted tags and whitespace.
     * Ignore html <code></code> block
     * 
     * @param string $content The content to minify.
     * @param bool $button Allow copying codeblock (default: false).
     * 
     * @return string Return minified content.
    */
    public static function minifyIgnore(string $content, bool $button = false): string 
    {
        $ignores = [];
        $pattern = '/<pre[^>]*>\s*<code[^>]*>[\s\S]*?<\/code>\s*<\/pre>/i';
        $ignorePattern = '###IGNORED_CODE_BLOCK###';

        $content = preg_replace_callback($pattern, function ($matches) use (&$ignores, $ignorePattern) {
            $ignores[] = $matches[0];
            return $ignorePattern;
        }, $content);

        // Restore the code blocks back to its original state
        $content = preg_replace_callback('/' . $ignorePattern . '/', function () use (&$ignores, $button) {
            return preg_replace(
                '/<pre([^>]*)class="([^"]*)"([^>]*)>/i', 
                '<pre$1class="$2 pre-codeblock"$3>' . ($button ? '<button type="button" class="copy-snippet">copy</button>' : ''),
                array_shift($ignores)
            );
        }, static::minify($content));

        return $content;
    }
}