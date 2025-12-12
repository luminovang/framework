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
namespace Luminova\Components\Seo;

use \JsonException;
use \Luminova\Utility\MIME;
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
     * @param string $viewType The expected content type for the response.
     * 
     * @return self Returns minification class instance.
     * @throws RuntimeException If array or object and json error occurs.
     */
    public function compress(string|array|object $data, string $viewType): self 
    {
        $charset = null;

        // Convert data to string if not already
        $content = is_string($data) ? $data : self::toJsonString($data);

        // Minify content if required
        $content = $this->isHtml
            ? self::doMinifyIgnore($content, $this->copyable, $this->ignoreCodeblocks)
            : self::minify($content);

        // Resolve content type if it's a shorthand
        if (!str_contains($viewType, '/')) {
            $viewType = MIME::findType($viewType);
        }

        if (
            str_contains($viewType, 'charset=') &&
            preg_match('/charset\s*=\s*["\']?([\w\-]+)["\']?/i', $viewType, $matches)
        ) {
            $charset = $matches[1];
        }

        // Set response headers
        $this->headers['Content-Length'] = string_length($content, $charset);
        $this->headers['Content-Type'] = $viewType;
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
        return self::doMinifyIgnore($content, $button);
    }

    /**
     * Minify the given content by removing unwanted tags and whitespace.
     * Ignore html <code></code> block
     * 
     * @param string $content The content to minify.
     * @param bool $button Allow copying codeblock (default: false).
     * @param bool $ignoreCodeblocks
     * 
     * @return string Return minified content.
     */
    private static function doMinifyIgnore(
        string $content, 
        bool $button = false,
        bool $ignoreCodeblocks = false
    ): string
    {
        $token = '###IGNORED_BLOCK_%d###';
        $ignores = [];
        $patterns = [
            'TEXTAREA' => '/<textarea\b[^>]*>[\s\S]*?<\/textarea>/i'
        ];

        if(!$ignoreCodeblocks){
            $patterns['CODE'] = '/<pre[^>]*>\s*<code[^>]*>[\s\S]*?<\/code>\s*<\/pre>/i';
        }

        foreach ($patterns as $type => $pattern) {
            $content = preg_replace_callback($pattern, function ($m) use (&$ignores, $token, $type) {
                $key = sprintf($token, count($ignores));
                $ignores[$key] = ['type' => $type, 'html' => $m[0]];
                return $key;
            }, $content);
        }

        $content = self::minify($content);
        return preg_replace_callback('/###IGNORED_BLOCK_\d+###/', function ($m) use (&$ignores, $button) {
            $block = $ignores[$m[0]] ?? null;

            if (!$block) {
                return $m[0];
            }

            if ($block['type'] === 'TEXTAREA') {
                return $block['html'];
            }

            $formatter = '';

            if ($button) {
                $formatter .= '<div class="lmv-snippet-header">';
                $formatter .= '<button type="button" class="lmv-copy-snippet" title="Copy this snippet" aria-label="Copy code snippet"><span>Copy</span></button>';
                $formatter .= '</div>';
            }

            $formatted = preg_replace(
                '/<pre\b(?:\s+([^=>\s]+)="[^"]*")*\s*(?:class="([^"]*)")?(.*?)(?:\s+([^=>\s]+)="[^"]*")*\s*>/i',
                '<pre $1 class="lmv-pre-block $2" $3 data-info="code sample">',
                $block['html']
            );

            return "<div class='lmv-snippet-container'>{$formatter}{$formatted}</div>";
        }, $content);
    }
}