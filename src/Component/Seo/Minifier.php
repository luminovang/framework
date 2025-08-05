<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Component\Seo;

use \JsonException;
use \Luminova\Http\Header;
use \Luminova\Exceptions\RuntimeException;
use function \Luminova\Funcs\string_length;

final class Minifier
{
    /** 
	 *  Minified content.
     *
	 * @var mixed $contents
	 */
    private mixed $contents = '';

    /** 
	 * Minified content headers.
     *
	 * @var array $headers
	 */
    private array $headers = [];

    /**
     * Regular expression patterns for content stripping.
     * 
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
        "line" => [
            "\n",
            "\r",
            "\t"
        ]
    ];
    
    /**
     * Class constructor.
     * Initializes default settings for the response headers and cache control.
     * 
     * @param bool $ignoreCodeblocks Whether to ignore html code block tag `<code></code>` (default: false). 
     * @param bool $copyable Whether to include a copy button to code blocks (default: false).
     * @param bool $isHtml Whether minification is for HTML contents (default: true).
     */
    public function __construct(
        private bool $ignoreCodeblocks = false,
        private bool $copyable = false,
        private bool $isHtml = true
    ) {}
    
    /**
     * Set if minifying content is kind of HTML.
     *
     * @param bool $isHtml Whether minification is for HTML contents (default: true).
     * 
     * @return self Returns minification class instance.
     */
	public function isHtml(bool $isHtml = true): self 
    {
		$this->isHtml = $isHtml;
		return $this;
	}

    /**
     * sets ignore minifying code block.
     *
     * @param bool $ignore Whether to ignore minifying code blocks.
     * 
     * @return self Returns minification class instance.
     */
	public function codeblocks(bool $ignore): self 
    {
		$this->ignoreCodeblocks = $ignore;
		return $this;
	}

    /**
     * sets allow copy code block.
     *
     * @param bool $allow Whether to include code copy button.
     * 
     *  @return self Returns minification class instance.
     */
	public function copyable(bool $allow): self 
    {
		$this->copyable = $allow;
		return $this;
	}

    /**
     * Get minified content,
     * 
     * @return string Return minified contents.
     */
    public function getContent(): string 
    {
		return $this->contents;
    }

    /**
     * Get content encoding.
     * 
     * @return string|null|false Return minified content encoding.
     */
    public function getEncoding(): mixed
    {
		return $this->headers['Content-Encoding']??false;
    }

     /**
     * Get content encoding.
     * 
     * @return int Return content length.
     */
    public function getLength(): int 
    {
		return $this->headers['Content-Length'] ?? 0;
    }

    /**
     * Get page header information.
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
     * @return self Returns minification class instance.
     * @throws RuntimeException If array or object and json error occurs.
     */
    public function compress(
        string|array|object $data,
        string $contentType
    ): self 
    {
        $charset = null;

        // Convert data to string if not already
        $content = is_string($data) ? $data : self::toJsonString($data);

        // Minify content if required
        $content = (!$this->isHtml || $this->ignoreCodeblocks) 
            ? self::minify($content) 
            : self::minifyIgnore($content, $this->copyable);

        // Resolve content type if it's a shorthand
        if (!str_contains($contentType, '/')) {
            $contentType = Header::getContentTypes($contentType);
        }

        if (
            str_contains($contentType, 'charset=') &&
            preg_match('/charset\s*=\s*["\']?([\w\-]+)["\']?/i', $contentType, $matches)
        ) {
            $charset = $matches[1];
        }

        // Set response headers
        $this->headers['Content-Length'] = string_length($content, $charset);
        $this->headers['Content-Type'] = $contentType;
        $this->contents = $content;

        return $this;
    }

    /**
     * Convert content to json string.
     * 
     * @param array|object $data The content to convert to json string.
     * 
     * @return string Return json string or empty string.
     * @throws RuntimeException
     */
    private static function toJsonString(array|object $data): string
    {
        try{
            return json_encode(
                (array) $data, 
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_UNESCAPED_UNICODE
            ) ?: '';
        } catch (JsonException $e) {
           throw new RuntimeException(
                'Json Minification Error: ' . $e->getMessage(), 
                RuntimeException::JSON_ERROR, 
                $e
            );
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
            self::$patterns["find"], 
            self::$patterns["replace"], 
            str_replace(self::$patterns["line"], '', $content)
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
        return preg_replace_callback('/' . $ignorePattern . '/', function () use (&$ignores, $button) {
            $formatter = '';
            if ($button) {
                $formatter .= '<div class="lmv-snippet-header">';
                $formatter .= '<button type="button" class="lmv-copy-snippet" title="Copy this snippet" aria-label="Copy code snippet"><span>Copy</span></button>';
                $formatter .= '</div>';
            }

            $formatter .= '<pre $1 class="lmv-pre-block $2" $3 data-info="code sample">';
            $formatter = preg_replace(
                '/<pre\b(?:\s+([^=>\s]+)="[^"]*")*\s*(?:class="([^"]*)")?(.*?)(?:\s+([^=>\s]+)="[^"]*")*\s*>/i',
                $formatter,
                array_shift($ignores)
            );

            return "<div class='lmv-snippet-container'>{$formatter}</div>";
        }, self::minify($content));
    }
}