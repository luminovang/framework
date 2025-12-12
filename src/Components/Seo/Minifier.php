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

use \Throwable;
use \Luminova\Utility\MIME;
use \Luminova\Exceptions\{JsonException, RuntimeException};

/**
 * Class Minifier
 *
 * Provides HTML and JSON content minification with optional handling for code blocks.
 * Supports adding copy, run, and AI buttons for code snippets in HTML.
 *
 * @package Luminova\Components\Seo
 */
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
     * Minifier constructor.
     *
     * @param bool $ignoreCodeblocks Ignore `<code>` blocks during minification.
     * @param bool $copyable Add copy button to code blocks.
     * @param bool $isHtml Treat content as HTML for minification.
     * @param bool $runnable Add run button to code blocks.
     * @param bool $askAi Add AI assistant button to code blocks.
     */
    public function __construct(
        private bool $ignoreCodeblocks = false,
        private bool $copyable = false,
        private bool $isHtml = true,
        private bool $runnable = false,
        private bool $askAi = false
    ) {}
    
    /**
     * Enable or disable HTML minification.
     *
     * @param bool $isHtml True if content is HTML, false otherwise.
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
     * Enable or disable ignoring of code blocks during minification.
     *
     * @param bool $ignore True to ignore code blocks.
     * 
     * @return self Returns minification class instance.
     */
	public function copyable(bool $allow): self 
    {
		$this->copyable = $allow;
		return $this;
	}

    /**
     * Enable or disable run button for code blocks.
     *
     * @param bool $allow True to allow run button.
     * 
     * @return self Returns minification class instance.
     */
    public function runnable(bool $allow): self 
    {
        $this->runnable = $allow;
        return $this;
    }

    /**
     * Enable or disable AI assistant button for code blocks.
     *
     * @param bool $allow True to allow AI button.
     * 
     * @return self Returns minification class instance.
     */
    public function askAi(bool $allow): self 
    {
        $this->askAi = $allow;
        return $this;
    }

    /**
     * Get minified content as string.
     * 
     * @return string Return minified contents.
     */
    public function getContent(): string 
    {
		return $this->contents;
    }

    /**
     * Get content encoding from headers.
     * 
     * @return string|null|false Return minified content encoding.
     */
    public function getEncoding(): mixed
    {
		return $this->headers['Content-Encoding']??false;
    }

     /**
     * Get length of minified content.
     * 
     * @return int Return content length.
     */
    public function getLength(): int 
    {
		return $this->headers['Content-Length'] ?? 0;
    }

    /**
     * Get HTTP headers for minified content.
     * 
     * @return array Get minified content headers.
     */
    public function getHeaders(): array 
    {
		return $this->headers;
    }

    /**
     * Compress content and set response headers.
     *
     * @param string|array|object $data Content to minify (can be array/object for JSON).
     * @param string $viewType MIME type or shorthand (e.g., "html", "json").
     * 
     * @return self Returns minification class instance.
     * @throws RuntimeException If array or object and json error occurs.
     */
    public function compress(string|array|object $data, string $viewType): self 
    {
        // Convert data to string if not already
        $data = is_scalar($data) ? (string) $data : self::toJsonString($data);

        // Minify content if required
        $data = $this->isHtml
            ? self::doMinifyIgnore(
                $data, 
                $this->copyable, 
                $this->ignoreCodeblocks,
                $this->runnable,
                $this->askAi
             )
            : self::minify($data);

        // Resolve content type if it's a shorthand
        if (!str_contains($viewType, '/')) {
            $viewType = MIME::findType($viewType);
        }

        // $charset = null;
        // if (
        //     str_contains($viewType, 'charset=') &&
        //     preg_match('/charset\s*=\s*["\']?([\w\-]+)["\']?/i', $viewType, $matches)
        // ) {
        //     $charset = $matches[1];
        // }
        // $length = string_length($data);

        // Set response headers
        $this->headers['Content-Length'] = strlen($data);
        $this->headers['Content-Type'] = $viewType;
        $this->contents = $data;

        return $this;
    }

    /**
     * Convert array or object to JSON string.
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
        } catch (Throwable $e) {
           throw new JsonException(
                'Json Minification Error: ' . $e->getMessage(),
                previous: $e
            );
        }
        
        return '';
    }

    /**
     * Minify string content by removing comments, whitespace, and newlines.
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
     * Minify content but preserve code blocks with optional copy button.
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
     * Internal method for minifying content with code block preservation and optional buttons.
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
        bool $ignoreCodeblocks = false,
        bool $runnable = false,
        bool $askAi = false
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
            if(!$content){
                continue;
            }

            $content = preg_replace_callback($pattern, function ($m) use (&$ignores, $token, $type) {
                $key = sprintf($token, count($ignores));
                $ignores[$key] = ['type' => $type, 'html' => $m[0]];
                return $key;
            }, $content);
        }

        if(!$content){
            return '';
        }

        $id = 1;

        return preg_replace_callback(
            '/###IGNORED_BLOCK_\d+###/', 
            function ($m) use (&$ignores, $button, $runnable, $askAi, &$id) {
                $block = $ignores[$m[0]] ?? null;

                if (!$block) {
                    return $m[0];
                }

                if ($block['type'] === 'TEXTAREA') {
                    return $block['html'];
                }

                $formatter = '';
                $isButton = $button || $runnable || $askAi;
                $target = "lmv-snippet-{$id}";

                if ($isButton) {
                    $formatter .= '<div class="lmv-snippet-header">';
                    if($button){
                        $formatter .= self::button('copy', $target, 'Copy this snippet');
                    }

                    if($askAi){
                        $formatter .= self::button('ai', $target, 'Ask AI about this snippet');
                    }
                    
                    if($runnable){
                        $formatter .= self::button('run', $target, 'Run this snippet');
                    }

                    $formatter .= '</div>';
                    $id++;
                }

                $formatted = preg_replace(
                    '/<pre\b(?:\s+([^=>\s]+)="[^"]*")*\s*(?:class="([^"]*)")?(.*?)(?:\s+([^=>\s]+)="[^"]*")*\s*>/i',
                    '<pre $1 class="lmv-pre-block $2" $3 aria-label="Code sample">',
                    $block['html']
                );

                $formatted = "<div class='lmv-snippet-container' id='{$target}'>{$formatter}{$formatted}</div>";

                if(!$runnable){
                    return $formatted;
                }

                $formatted .= "<div class='lmv-snippet-run-output' id='{$target}-output'></div>";
                return $formatted;
            }, 
            self::minify((string) $content)
        );
    }

    /**
     * Generate a snippet button HTML.
     *
     * @param string $name Button type ('copy', 'run', 'ai').
     * @param string $target Snippet target ID.
     * @param string $label Tooltip and ARIA label.
     * 
     * @return string Return button string.
     */
    private static function button(string $name, string $target, string $label): string 
    {
        return '<button 
            type="button" 
            class="lmv-' . $name . '-snippet" 
            target-id="'. $target . '" 
            title="'. $label .'" 
            aria-label="'. $label .'"><span>' . ucfirst($name) . '</span></button>';
    }
}