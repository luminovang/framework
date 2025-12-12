<?php
/**
 * Luminova Framework HTML minifier.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\String;

use \Throwable;
use Luminova\Utility\{Mime, Util};
use Luminova\Exceptions\{JsonException, RuntimeException};

/**
 * Class Minifier
 *
 * Provides HTML and JSON content minification with optional handling for code blocks.
 * Supports adding copy, run, and AI buttons for code snippets in HTML.
 *
 * @package Luminova\Components\String
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
     * Codeblock action buttons
     *
     * @var array $buttons
     */
    private array $buttons = [];

    /**
     * Codeblock action buttons
     *
     * @var array ACTIONS
     */
    private const ACTIONS = [
        'copy'  => 'Copy this snippet', 
        'ai'    => 'Ask AI about this snippet', 
        'run'   => 'Run this snippet'
    ];

    /**
     * Regular expression patterns for content stripping.
     * 
     * @var array PATTERNS
     */
    private const PATTERNS = [
        'find' => [
            '/\>[ \t\r\n]+/',           // '/\>[^\S ]+/s',      Strip whitespace after HTML tags
            '/[ \t\r\n]+\</',           // '/[^\S ]+\</s',      Strip whitespace before HTML tags
            '/\s+/',                    // '/\s{2,}/'           Strip excessive whitespace
            '/<!--(?!\[if).*?-->/s',    // '/<!--(.*)-->/Uis',  Strip HTML comments
            //'/[[:blank:]]+/'          // Strip blank spaces
        ],
        'replace' => [
            '>',
            '<',
            ' ',
            '',
           // ' '
        ],
        'collapse' => [
            "\n",
            "\r",
            "\t"
        ]
    ];

    /**
     * Codeblocks replace pattern. 
     * 
     * @var string PRECODE_REPLACE_ATTR
     */
    private const PRECODE_REPLACE_ATTR = '/<pre\b(?:\s+([^=>\s]+)="[^"]*")*\s*(?:class="([^"]*)")?(.*?)(?:\s+([^=>\s]+)="[^"]*")*\s*>/i';

    /**
     * @var array PATTERNS
     */
    private const PRESERVE_TAGS = [
        'TEXTAREA' => '/<textarea\b[^>]*>[\s\S]*?<\/textarea>/i',
        'SCRIPT'   => '/<script\b[^>]*>[\s\S]*?<\/script>/i',
        'STYLE'    => '/<style\b[^>]*>[\s\S]*?<\/style>/i',
        'SVG'      => '/<svg\b[^>]*>[\s\S]*?<\/svg>/i',
        'MATH'     => '/<math\b[^>]*>[\s\S]*?<\/math>/i',
        'CODE'     => '/<pre\b[^>]*>\s*<code\b[^>]*>[\s\S]*?<\/code>\s*<\/pre>/i',
        'PRE'      => '/<pre\b[^>]*>(?:(?!<code\b)[\s\S])*?<\/pre>/i',
    ];

    /**
     * Initialize the minifier.
     * 
     * Default protected tags:
     * - `pre`  - `<pre>`
     * - `code` - `<pre><code>`
     * - `textarea`
     * - `script`
     * - `style`
     * - `svg`
     * - `math`
     *
     * @param bool $isHtml Whether to treat content as HTML-specific minification rules.
     * @param string[]|null $preserveHtmlTags HTML tags to preserve from minification.
     */
    public function __construct(
        private bool $isHtml = true,
        private ?array $preserveHtmlTags = null
    ) {}

    /**
     * Add actions to code blocks.
     *
     * @param string[] $buttons Code block actions to add
     *                          (e.g. `copy`, `ai`, `run`).
     *
     * @return self Returns minification class instance.
     */
    public function codeBlockButtons(array $buttons): self
    {
        if ($buttons === []) {
            return $this;
        }
        
        $this->buttons = array_merge(
            $this->buttons,
            self::flipArray($buttons, false)
        );

        return $this;
    }
    
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
     * Get minified content as string.
     * 
     * @return string Return minified contents.
     */
    public function getContent(): string 
    {
		return $this->contents;
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
     * Process response content by converting, minifying, and preparing headers.
     *
     * Supports scalar, array, and object content. Arrays and objects are converted
     * to JSON before processing. HTML content can be minified with optional
     * code block preservation and enhancements.
     *
     * @param string|array|object $data Response content.
     * @param string $type MIME type or shorthand type (e.g. `html`, `json`).
     *
     * @return self Returns the current response processor instance.
     *
     * @throws RuntimeException If JSON conversion fails.
     * 
     * @see self::getContent()
     * @see self::getHeaders()
     * @see self::getLength()
     */
    public function minify(
        string|array|object $data,
        string $type = 'html'
    ): self
    {
        $process = true;

        if(!is_scalar($data)){
            $process = false;
            if(is_array($data) && $type === 'text' || $type === 'text/plain'){
                $data = Util::minifyArray($data);
            } else {
                $data = self::toJsonString($data);
            }
        } else {
            $data = (string) $data;
        }

        if($process){
            $this->isHtml = $this->isHtml 
                || $type === 'html' 
                || $type === 'text/html';

            $this->contents = $this->isHtml
                ? self::doMinifyIgnore(
                    $data, 
                    $this->preserveHtmlTags,
                    $this->buttons
                )
                : self::sanitize($data);
        } else{
            $this->contents = $data;
        }

        if (!str_contains($type, '/')) {
            $type = Mime::findType($type) ?: "text/{$type}";
        }

        $this->headers['Content-Length'] = strlen($this->contents);
        $this->headers['Content-Type'] = $type;

        return $this;
    }

    /**
     * Minify string content by removing unnecessary comments,
     * whitespace, and line breaks.
     *
     * @param string $content Content to minify.
     *
     * @return string Minified content.
     */
    public static function sanitize(string $content): string 
    {
        $content = preg_replace(
            self::PATTERNS['find'], 
            self::PATTERNS['replace'], 
            str_replace(self::PATTERNS['collapse'], '', $content)
        );

        return trim($content);
    }

    /**
     * Create a lookup array from values with optional key normalization.
     *
     * Converts each non-empty value into an array key and assigns it a boolean
     * value. Keys can be normalized to uppercase or lowercase to allow
     * case-insensitive lookups.
     *
     * @param array<int, string> $values Values to convert into lookup keys.
     * @param bool $uppercase Whether to convert keys to uppercase. If false,
     *                        keys are converted to lowercase.
     *
     * @return array<string, bool> Lookup array containing normalized keys.
     */
    private static function flipArray(array $values, bool $uppercase = true): array 
    {
        $array = [];

        foreach($values as $value){
            $value = trim($value);

            if($value  === ''){
                continue;
            }

            $value = $uppercase ? strtoupper($value) : strtolower($value);

            $array[$value] = true;
        }

        return $array;
    }

    /**
     * Encode an array or object into a JSON string.
     *
     * Converts the provided data into JSON using safe encoding options that
     * preserve Unicode characters, slashes, and line terminators. JSON encoding
     * failures are converted into a JsonException.
     *
     * @param array|object $data Data to encode as JSON.
     *
     * @return string JSON encoded string.
     *
     * @throws JsonException If the data cannot be encoded.
     */
    private static function toJsonString(array|object $data): string
    {
        try{
            return json_encode(
                (array) $data, 
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_LINE_TERMINATORS
                    | JSON_UNESCAPED_UNICODE
            ) ?: '';
        } catch (Throwable $e) {
           throw new JsonException(
                'Json Minification Error: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Generate an HTML action button for a code snippet block.
     *
     * Creates a button element with the required classes, target reference,
     * tooltip text, and accessibility attributes for snippet actions such as
     * copying, running, or processing code.
     *
     * @param string $name Action name used for the button class and label.
     * @param string $target Target snippet element identifier.
     * @param string $label Button tooltip and ARIA label text.
     *
     * @return string Generated button HTML.
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

    /**
     * Extract and temporarily replace protected HTML tags with unique placeholders.
     *
     * Scans the provided content for configured preservable tags and replaces each
     * matched block with a unique placeholder. The original HTML content is stored
     * in an array for later restoration after processing.
     *
     * @param string $content The HTML content to scan and filter.
     * @param array<string, bool> $preserves List of tag types that should be preserved.
     *
     * @return array{string, array<string, array{type: string, html: string}>}
     *         Returns the filtered content and the preserved tag mappings.
     */
    private static function filterTags(string $content, array $preserves): array
    {
        if ($content === '') {
            return ['', []];
        }

        $filters = [];
        $index = 0;

        foreach (self::PRESERVE_TAGS as $type => $pattern) {
            if (!$content || !isset($preserves[$type])) {
                continue;
            }

            $content = preg_replace_callback(
                $pattern,
                static function ($m) use (&$filters, &$index, $type): string {
                    $key = "###PRESERVE_TAG_{$type}_{$index}###";

                    $filters[$key] = [
                        'type' => $type,
                        'html' => $m[0],
                    ];

                    $index++;

                    return $key;
                },
                $content
            );
        }

        return [$content, $filters];
    }

    /**
     * Minify HTML content while preserving protected tags and optionally enhancing
     * code blocks with interactive actions.
     *
     * @param string $content The HTML content to minify.
     * @param string[]|null $preserves Tag names to preserve during minification.
     * @param array<string, mixed> $buttons Code block actions to enable.
     *
     * @return string The minified HTML content with preserved blocks restored.
     */
    private static function doMinifyIgnore(
        string $content,
        ?array $preserves = null,
        array $buttons = [],
    ): string 
    {
        if ($content === '') {
            return '';
        }

        $id = 1;
        $addCodeActions = $buttons !== [];
        $isPreservedCode = false;

        $preserves = ($preserves !== null)
            ? self::flipArray($preserves, true)
            : [
                'PRE'      => true,
                'TEXTAREA' => true,
            ];

        if ($addCodeActions) {
            $isPreservedCode = isset($preserves['CODE']);
            $preserves['CODE'] = true;
        }

        [$content, $filters] = self::filterTags($content, $preserves);

        if ($content === '') {
            return '';
        }

        return preg_replace_callback(
            '/###PRESERVE_TAG_([A-Z]+)_\d+###/',
            static function ($m) use (
                $filters, 
                &$id, 
                $buttons, 
                $addCodeActions, 
                $isPreservedCode
            ): string {
                $tag = $filters[$m[0]] ?? null;

                if ($tag === null) {
                    return $m[0];
                }

                if ($tag['type'] !== 'CODE' || !$addCodeActions) {
                    return $tag['html'];
                }

                $target = "lmv-snippet-{$id}";
                $id++;

                $header = '<div class="lmv-snippet-header">';

                foreach (self::ACTIONS as $action => $label) {
                    if (isset($buttons[$action])) {
                        $header .= self::button($action, $target, $label);
                    }
                }

                $header .= '</div>';

                $html = $isPreservedCode
                    ? $tag['html']
                    : self::sanitize($tag['html']);

                $html = preg_replace(
                    self::PRECODE_REPLACE_ATTR,
                    '<pre $1 class="lmv-pre-block $2" $3 aria-label="Code sample">',
                    $html
                );

                $result = "<div class='lmv-snippet-container' id='{$target}'>{$header}{$html}</div>";

                if (isset($buttons['run'])) {
                    $result .= "<div class='lmv-snippet-run-output' id='{$target}-output'></div>";
                }

                return $result;
            },
            self::sanitize($content)
        );
    }
}